<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use PDO;

/**
 * Composant d'acces a la table `archive`, qui trace les fichiers
 * d'archive d'evenements bruts crees avant chaque purge.
 */
final class ArchiveRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function enregistrer(
        string $periodeDebut,
        string $periodeFin,
        string $cheminFichier,
        int $nbEvenements,
    ): void {
        $requete = $this->pdo->prepare(
            'INSERT INTO archive (periode_debut, periode_fin, fichier, nb_evenements)
             VALUES (:debut, :fin, :fichier, :nb)'
        );
        $requete->execute([
            ':debut'   => $periodeDebut,
            ':fin'     => $periodeFin,
            ':fichier' => $cheminFichier,
            ':nb'      => $nbEvenements,
        ]);
    }

    /**
     * Estimation grossiere du poids de la base, en octets. Sert au
     * tracker de remplissage cote interface.
     */
    public function tailleBase(string $nomBase): int
    {
        $requete = $this->pdo->prepare(
            'SELECT SUM(data_length + index_length)
             FROM information_schema.tables
             WHERE table_schema = :base'
        );
        $requete->execute([':base' => $nomBase]);

        return (int) $requete->fetchColumn();
    }
}
