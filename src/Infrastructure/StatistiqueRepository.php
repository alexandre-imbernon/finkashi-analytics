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

    // -------------------------------------------------------------
    // Methodes de lecture, exposees par l'API au dashboard.
    // Bornees par une plage de dates (depuis/jusque) pour permettre
    // au client de demander la periode qu'il souhaite afficher.
    // -------------------------------------------------------------

    /**
     * Trafic global du site, jour par jour, sur une periode donnee.
     *
     * @return list<array{jour:string, visiteurs:int, pages_vues:int}>
     */
    public function trafficGlobal(string $depuis, string $jusque): array
    {
        $requete = $this->pdo->prepare(
            'SELECT jour,
                    COALESCE(SUM(visiteurs), 0)  AS visiteurs,
                    COALESCE(SUM(pages_vues), 0) AS pages_vues
             FROM stat_jour_page
             WHERE jour BETWEEN :depuis AND :jusque
             GROUP BY jour
             ORDER BY jour'
        );
        $requete->execute([':depuis' => $depuis, ':jusque' => $jusque]);

        return array_map(
            static fn (array $l): array => [
                'jour'       => (string) $l['jour'],
                'visiteurs'  => (int) $l['visiteurs'],
                'pages_vues' => (int) $l['pages_vues'],
            ],
            $requete->fetchAll(),
        );
    }

    /**
     * Classement des pages les plus consultees sur une periode.
     *
     * @return list<array{chemin:string, titre:?string, visiteurs:int, pages_vues:int}>
     */
    public function classementPages(string $depuis, string $jusque, int $limite = 20): array
    {
        $requete = $this->pdo->prepare(
            'SELECT p.chemin, p.titre,
                    COALESCE(SUM(s.visiteurs), 0)  AS visiteurs,
                    COALESCE(SUM(s.pages_vues), 0) AS pages_vues
             FROM stat_jour_page s
             JOIN page p ON p.id = s.page_id
             WHERE s.jour BETWEEN :depuis AND :jusque
             GROUP BY p.id, p.chemin, p.titre
             ORDER BY visiteurs DESC, pages_vues DESC
             LIMIT :limite'
        );
        $requete->bindValue(':depuis', $depuis);
        $requete->bindValue(':jusque', $jusque);
        $requete->bindValue(':limite', $limite, PDO::PARAM_INT);
        $requete->execute();

        return array_map(
            static fn (array $l): array => [
                'chemin'     => (string) $l['chemin'],
                'titre'      => $l['titre'] !== null ? (string) $l['titre'] : null,
                'visiteurs'  => (int) $l['visiteurs'],
                'pages_vues' => (int) $l['pages_vues'],
            ],
            $requete->fetchAll(),
        );
    }

    /**
     * Repartition des visiteurs par canal d'acquisition.
     *
     * @return list<array{canal:string, visiteurs:int}>
     */
    public function repartitionParCanal(string $depuis, string $jusque): array
    {
        $requete = $this->pdo->prepare(
            'SELECT canal, COALESCE(SUM(visiteurs), 0) AS visiteurs
             FROM stat_jour_canal
             WHERE jour BETWEEN :depuis AND :jusque
             GROUP BY canal
             ORDER BY visiteurs DESC'
        );
        $requete->execute([':depuis' => $depuis, ':jusque' => $jusque]);

        return array_map(
            static fn (array $l): array => [
                'canal'     => (string) $l['canal'],
                'visiteurs' => (int) $l['visiteurs'],
            ],
            $requete->fetchAll(),
        );
    }

    /**
     * Classement des sources de trafic les plus actives.
     *
     * @return list<array{domaine:string, canal:string, visiteurs:int}>
     */
    public function classementSources(string $depuis, string $jusque, int $limite = 20): array
    {
        $requete = $this->pdo->prepare(
            'SELECT src.domaine, src.canal,
                    COALESCE(SUM(s.visiteurs), 0) AS visiteurs
             FROM stat_jour_source s
             JOIN source src ON src.id = s.source_id
             WHERE s.jour BETWEEN :depuis AND :jusque
             GROUP BY src.id, src.domaine, src.canal
             ORDER BY visiteurs DESC
             LIMIT :limite'
        );
        $requete->bindValue(':depuis', $depuis);
        $requete->bindValue(':jusque', $jusque);
        $requete->bindValue(':limite', $limite, PDO::PARAM_INT);
        $requete->execute();

        return array_map(
            static fn (array $l): array => [
                'domaine'   => (string) $l['domaine'],
                'canal'     => (string) $l['canal'],
                'visiteurs' => (int) $l['visiteurs'],
            ],
            $requete->fetchAll(),
        );
    }

    /**
     * Repartition des visiteurs par pays.
     *
     * @return list<array{pays:string, visiteurs:int}>
     */
    public function repartitionParPays(string $depuis, string $jusque): array
    {
        $requete = $this->pdo->prepare(
            'SELECT pays, COALESCE(SUM(visiteurs), 0) AS visiteurs
             FROM stat_jour_pays
             WHERE jour BETWEEN :depuis AND :jusque
             GROUP BY pays
             ORDER BY visiteurs DESC'
        );
        $requete->execute([':depuis' => $depuis, ':jusque' => $jusque]);

        return array_map(
            static fn (array $l): array => [
                'pays'      => (string) $l['pays'],
                'visiteurs' => (int) $l['visiteurs'],
            ],
            $requete->fetchAll(),
        );
    }
}
