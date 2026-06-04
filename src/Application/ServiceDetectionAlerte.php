<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Application;

use DateTimeImmutable;
use Finkashi\Analytics\Domain\AlerteRegle;
use Finkashi\Analytics\Domain\Metrique;
use Finkashi\Analytics\Infrastructure\AlerteRepository;
use PDO;

/**
 * Service de detection de seuils.
 *
 * Pour chaque regle active, evalue la metrique correspondante a partir
 * des agregats du jour et declenche une alerte si le seuil est franchi.
 * Une regle n'est declenchee qu'une seule fois par jour.
 */
final class ServiceDetectionAlerte
{
    public function __construct(
        private readonly AlerteRepository $alertes,
        private readonly PDO $pdo,
        private readonly string $prefixe = '',
    ) {
    }

    /**
     * Evalue toutes les regles actives pour un jour donne.
     * Retourne la liste des regles qui ont declenche une alerte.
     *
     * @return list<AlerteRegle>
     */
    public function evaluerJour(string $jour, ?DateTimeImmutable $instant = null): array
    {
        $instant ??= new DateTimeImmutable();
        $declenchees = [];

        foreach ($this->alertes->reglesActives() as $regle) {
            // Eviter un second declenchement le meme jour
            if ($this->alertes->dejaDeclencheeLeJour($regle, $jour)) {
                continue;
            }

            $valeur = $this->mesurer($regle->metrique(), $jour);

            if ($regle->declencheePar($valeur)) {
                $this->alertes->enregistrerDeclenchement($regle, $valeur, $instant);
                $declenchees[] = $regle;
            }
        }

        return $declenchees;
    }

    /**
     * Calcule la valeur d'une metrique pour une journee, en s'appuyant
     * sur les tables d'agregats. Lire les agregats (plutot que de
     * recompter les evenements) garantit la coherence avec les chiffres
     * affiches dans le tableau de bord.
     */
    private function mesurer(Metrique $metrique, string $jour): int
    {
        $requete = match ($metrique) {
            // Visiteurs uniques du jour = somme des visiteurs par canal,
            // car les canaux partitionnent les visiteurs (un visiteur a
            // un seul canal de provenance).
            Metrique::VisiteursJour => "SELECT COALESCE(SUM(visiteurs), 0)
                                        FROM {$this->prefixe}stat_jour_canal
                                        WHERE jour = :jour",
            // Pages vues du jour = somme sur stat_jour_page.
            Metrique::PagesVuesJour => "SELECT COALESCE(SUM(pages_vues), 0)
                                        FROM {$this->prefixe}stat_jour_page
                                        WHERE jour = :jour",
        };

        $stmt = $this->pdo->prepare($requete);
        $stmt->execute([':jour' => $jour]);

        return (int) $stmt->fetchColumn();
    }
}
