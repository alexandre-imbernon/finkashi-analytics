<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Application;

use DateTimeImmutable;
use Finkashi\Analytics\Infrastructure\ArchiveRepository;
use Finkashi\Analytics\Infrastructure\EvenementRepository;
use RuntimeException;

/**
 * Service d'archivage des evenements bruts.
 *
 * Avant toute purge, les evenements concernes sont exportes dans un
 * fichier JSON compresse (gzip) anonymise. Ce mecanisme constitue le
 * filet de securite : meme purges de la base, les donnees restent
 * recuperables a partir des archives. L'enregistrement en base
 * (table `archive`) trace l'historique de ces operations.
 */
final class ServiceArchivage
{
    public function __construct(
        private readonly EvenementRepository $evenements,
        private readonly ArchiveRepository $archives,
        private readonly string $dossierArchives,
    ) {
    }

    /**
     * Archive et purge tous les evenements anterieurs a la date
     * passee en parametre. L'archive est creee meme s'il n'y a rien
     * a archiver (fichier vide) ; la purge n'est effectuee que si
     * l'archive a ete ecrite avec succes.
     *
     * @return array{nb_evenements:int, fichier:string}
     */
    public function archiverEtPurger(string $limite): array
    {
        if (!is_dir($this->dossierArchives) && !mkdir($this->dossierArchives, 0775, true)) {
            throw new RuntimeException("Impossible de creer le dossier d'archives : {$this->dossierArchives}");
        }

        $horodatage = (new DateTimeImmutable())->format('YmdHis');
        $nomFichier = "evenements-avant-{$limite}-{$horodatage}.json.gz";
        $cheminComplet = rtrim($this->dossierArchives, '/') . '/' . $nomFichier;

        $flux = gzopen($cheminComplet, 'wb9');
        if ($flux === false) {
            throw new RuntimeException("Impossible d'ouvrir le fichier d'archive : {$cheminComplet}");
        }

        $nb = 0;
        gzwrite($flux, "[\n");
        $premier = true;
        foreach ($this->evenements->lireAvant($limite) as $ligne) {
            // Anonymisation supplementaire dans l'archive : l'id et le
            // hash sont conserves pour la coherence statistique, mais
            // aucune donnee plus identifiante n'est ecrite (l'IP n'a
            // jamais ete stockee, le user-agent non plus).
            if (!$premier) {
                gzwrite($flux, ",\n");
            }
            gzwrite($flux, json_encode($ligne, JSON_UNESCAPED_UNICODE));
            $premier = false;
            $nb++;
        }
        gzwrite($flux, "\n]\n");
        gzclose($flux);

        // Purge effective.
        if ($nb > 0) {
            $supprimes = $this->evenements->supprimerAvant($limite);
            $this->archives->enregistrer(
                periodeDebut: '1970-01-01',
                periodeFin:   $limite,
                cheminFichier: $nomFichier,
                nbEvenements:  $supprimes,
            );
        }

        return ['nb_evenements' => $nb, 'fichier' => $nomFichier];
    }
}
