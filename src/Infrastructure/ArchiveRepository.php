<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use Finkashi\Analytics\Application\Persistance\ArchiveStockage;
use PDO;

/**
 * Implementation MySQL du contrat ArchiveStockage.
 *
 * Stocke les metadonnees d'archives dans la table relationnelle
 * `archive` (eventuellement prefixee pour cohabitation avec
 * d'autres applications dans la meme base).
 *
 * Cette classe expose egalement une methode utilitaire {@see tailleBase}
 * qui ne fait pas partie du contrat ArchiveStockage : elle est
 * specifique a MySQL (information_schema) et utilisee par le
 * dashboard pour afficher l'occupation du quota d'hebergement.
 */
final class ArchiveRepository implements ArchiveStockage
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $prefixe = '',
    ) {
    }

    public function enregistrer(
        string $periodeDebut,
        string $periodeFin,
        string $cheminFichier,
        int $nbEvenements,
    ): void {
        $requete = $this->pdo->prepare(
            "INSERT INTO {$this->prefixe}archive (periode_debut, periode_fin, fichier, nb_evenements)
             VALUES (:debut, :fin, :fichier, :nb)"
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
     *
     * Note : cette methode est en dehors du contrat ArchiveStockage
     * car elle exploite une fonctionnalite specifique a MySQL
     * (information_schema). Elle n'a pas d'equivalent direct cote
     * NoSQL et n'est donc accessible que lorsque le projet utilise
     * effectivement MySQL.
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
