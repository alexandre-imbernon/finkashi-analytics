<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Application\Persistance;

/**
 * Contrat de persistance des metadonnees d'archives.
 *
 * Cette interface est definie par la couche Application : c'est la
 * couche metier qui exprime SON besoin de stocker une trace de chaque
 * archive creee, sans rien dire de la facon dont elle sera stockee
 * (table relationnelle, document NoSQL, fichier plat, etc.).
 *
 * Plusieurs implementations existent :
 *   - ArchiveRepository       : table MySQL (production)
 *   - ArchiveRepositoryMongo  : collection MongoDB (alternative NoSQL,
 *                               isolee sur une branche de demonstration)
 *
 * Le service applicatif (ServiceArchivage) ne connait que cette
 * interface : il est donc agnostique de la technologie de stockage.
 * Ce decouplage est un exemple concret du principe d'inversion des
 * dependances (le D de SOLID).
 */
interface ArchiveStockage
{
    /**
     * Enregistre la metadonnee d'une archive nouvellement creee.
     *
     * @param string $periodeDebut  Date de debut couverte par l'archive (YYYY-MM-DD).
     * @param string $periodeFin    Date de fin couverte par l'archive (YYYY-MM-DD).
     * @param string $cheminFichier Nom du fichier d'archive sur le disque.
     * @param int    $nbEvenements  Nombre d'evenements contenus dans l'archive.
     */
    public function enregistrer(
        string $periodeDebut,
        string $periodeFin,
        string $cheminFichier,
        int $nbEvenements,
    ): void;
}
