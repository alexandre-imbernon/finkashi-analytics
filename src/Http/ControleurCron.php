<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Http;

use DateInterval;
use DateTimeImmutable;
use Finkashi\Analytics\Infrastructure\Fabrique;

/**
 * Controleur d'execution du cron via HTTP.
 *
 * Utilise par les hebergements mutualises (OVH) qui ne donnent pas
 * d'acces CLI mais permettent de planifier l'appel d'une URL.
 *
 * Securite : la cle d'API doit etre fournie en query string
 * (?cle=...). On n'utilise pas l'en-tete Authorization car le cron
 * OVH ne le propage pas. La cle reste secrete tant que personne
 * n'intercepte la requete (HTTPS oblige).
 */
final class ControleurCron
{
    public function __construct(
        private readonly Fabrique $fabrique,
        private readonly string $cleAttendue,
    ) {
    }

    public function executer(): void
    {
        // 1. Verification de la cle. Sans elle, un visiteur pourrait
        //    declencher l'agregation en boucle (operation couteuse).
        $cleFournie = (string) ($_GET['cle'] ?? '');
        if ($cleFournie === '' || !hash_equals($this->cleAttendue, $cleFournie)) {
            ReponseJson::erreur('Authentification requise.', 401);
            exit;
        }

        // 2. Sortie en text/plain : lisible dans les logs OVH.
        header('Content-Type: text/plain; charset=utf-8');
        set_time_limit(300);

        // 3. Parametres metier (durees de retention, etc.).
        $joursAReAgreger  = 3;
        $joursDeRetention = 60;

        $aujourdhui = new DateTimeImmutable('today');
        echo '[' . $aujourdhui->format('Y-m-d H:i:s') . "] Demarrage du cron quotidien\n";

        // Chaque etape est isolee : si l'une plante, on continue les
        // autres et on rapporte l'erreur. C'est crucial pour un cron :
        // un probleme ponctuel d'archivage ne doit pas empecher les
        // agregations du lendemain.
        $this->executerEtape('Agregation', function () use ($aujourdhui, $joursAReAgreger) {
            $agregation = $this->fabrique->serviceAgregation();
            for ($i = 0; $i <= $joursAReAgreger; $i++) {
                $jour = $aujourdhui->sub(new DateInterval("P{$i}D"))->format('Y-m-d');
                $r = $agregation->agregerJour($jour);
                echo "  - Agregation du {$jour} : "
                    . "global={$r['global']}, pages={$r['pages']}, "
                    . "sources={$r['sources']}, canaux={$r['canaux']}, pays={$r['pays']}\n";
            }
        });

        $this->executerEtape('Alertes', function () use ($aujourdhui) {
            $hier = $aujourdhui->sub(new DateInterval('P1D'))->format('Y-m-d');
            $declenchees = $this->fabrique->serviceDetectionAlerte()->evaluerJour($hier);
            echo "  - Evaluation des alertes pour {$hier} : "
                . count($declenchees) . " declenchee(s)\n";
        });

        $this->executerEtape('Archivage', function () use ($aujourdhui, $joursDeRetention) {
            $limite = $aujourdhui->sub(new DateInterval("P{$joursDeRetention}D"))->format('Y-m-d');
            $r = $this->fabrique->serviceArchivage()->archiverEtPurger($limite);
            echo "  - Archivage avant {$limite} : "
                . "{$r['nb_evenements']} evenement(s) archive(s) dans {$r['fichier']}\n";
        });

        echo '[' . (new DateTimeImmutable())->format('Y-m-d H:i:s') . "] Cron quotidien termine\n";
    }

    /**
     * Execute une etape du cron en isolation. En cas d'erreur,
     * affiche un message lisible et continue avec les etapes
     * suivantes.
     */
    private function executerEtape(string $nom, callable $action): void
    {
        try {
            $action();
        } catch (\Throwable $e) {
            echo "  ! ECHEC {$nom} : " . $e->getMessage() . "\n";
            echo "    Fichier : " . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    }
}
