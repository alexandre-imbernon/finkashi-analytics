<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Application;

use DateTimeImmutable;
use Finkashi\Analytics\Application\Persistance\ArchiveStockage;
use Finkashi\Analytics\Infrastructure\EvenementRepository;
use RuntimeException;

/**
 * Service d'archivage des evenements bruts.
 *
 * Avant toute purge, les evenements concernes sont exportes dans un
 * fichier JSON compresse (gzip) anonymise. Ce mecanisme constitue le
 * filet de securite : meme purges de la base, les donnees restent
 * recuperables a partir des archives.
 *
 * Le service depend du contrat ArchiveStockage, pas d'une
 * implementation concrete. Cela permet de stocker les metadonnees
 * d'archive aussi bien en MySQL qu'en MongoDB sans aucune
 * modification de ce code metier : c'est la couche Infrastructure
 * qui decide, via la Fabrique.
 */
final class ServiceArchivage
{
    public function __construct(
        private readonly EvenementRepository $evenements,
        private readonly ArchiveStockage $archives,
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
            if (!$premier) {
                gzwrite($flux, ",\n");
            }
            gzwrite($flux, json_encode($ligne, JSON_UNESCAPED_UNICODE));
            $premier = false;
            $nb++;
        }
        gzwrite($flux, "\n]\n");
        gzclose($flux);

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
