<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use PDO;

/**
 * Composant d'acces aux donnees pour les statistiques journalieres
 * agregees (tables stat_jour_*).
 *
 * Chaque methode d'enregistrement utilise une logique "upsert" :
 * si un agregat existe deja pour ce jour et cet axe, il est mis a
 * jour ; sinon il est cree. Le recalcul d'un jour est donc idempotent
 * (il ne cree jamais de doublon), ce qui s'appuie sur les contraintes
 * d'unicite definies dans le schema.
 */
final class StatistiqueRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function enregistrerStatPage(string $jour, int $pageId, int $pagesVues, int $visiteurs): void
    {
        $requete = $this->pdo->prepare(
            'INSERT INTO stat_jour_page (jour, page_id, pages_vues, visiteurs)
             VALUES (:jour, :page_id, :pages_vues, :visiteurs)
             ON DUPLICATE KEY UPDATE
                pages_vues = VALUES(pages_vues),
                visiteurs  = VALUES(visiteurs)'
        );
        $requete->execute([
            ':jour'       => $jour,
            ':page_id'    => $pageId,
            ':pages_vues' => $pagesVues,
            ':visiteurs'  => $visiteurs,
        ]);
    }

    public function enregistrerStatSource(string $jour, int $sourceId, int $visiteurs): void
    {
        $requete = $this->pdo->prepare(
            'INSERT INTO stat_jour_source (jour, source_id, visiteurs)
             VALUES (:jour, :source_id, :visiteurs)
             ON DUPLICATE KEY UPDATE visiteurs = VALUES(visiteurs)'
        );
        $requete->execute([
            ':jour'      => $jour,
            ':source_id' => $sourceId,
            ':visiteurs' => $visiteurs,
        ]);
    }

    public function enregistrerStatCanal(string $jour, string $canal, int $visiteurs): void
    {
        $requete = $this->pdo->prepare(
            'INSERT INTO stat_jour_canal (jour, canal, visiteurs)
             VALUES (:jour, :canal, :visiteurs)
             ON DUPLICATE KEY UPDATE visiteurs = VALUES(visiteurs)'
        );
        $requete->execute([
            ':jour'      => $jour,
            ':canal'     => $canal,
            ':visiteurs' => $visiteurs,
        ]);
    }

    public function enregistrerStatPays(string $jour, string $pays, int $visiteurs): void
    {
        $requete = $this->pdo->prepare(
            'INSERT INTO stat_jour_pays (jour, pays, visiteurs)
             VALUES (:jour, :pays, :visiteurs)
             ON DUPLICATE KEY UPDATE visiteurs = VALUES(visiteurs)'
        );
        $requete->execute([
            ':jour'      => $jour,
            ':pays'      => $pays,
            ':visiteurs' => $visiteurs,
        ]);
    }
}
