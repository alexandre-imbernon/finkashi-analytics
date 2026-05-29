<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use Finkashi\Analytics\Domain\Evenement;
use PDO;

/**
 * Composant d'acces aux donnees pour l'entite Evenement.
 *
 * Gere l'enregistrement des consultations (forte volumetrie) et
 * fournit les comptages necessaires au calcul des agregats.
 */
final class EvenementRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Enregistre un evenement de consultation.
     *
     * Les identifiants de page et de source proviennent d'objets deja
     * persistes ; on s'appuie sur eux pour renseigner les cles
     * etrangeres.
     */
    public function enregistrer(Evenement $evenement): Evenement
    {
        $requete = $this->pdo->prepare(
            'INSERT INTO evenement
                (page_id, source_id, canal, pays, visiteur_hash, survenu_le)
             VALUES
                (:page_id, :source_id, :canal, :pays, :visiteur_hash, :survenu_le)'
        );

        $requete->execute([
            ':page_id'       => $evenement->page()->id(),
            ':source_id'     => $evenement->source()?->id(),
            ':canal'         => $evenement->canal()->value,
            ':pays'          => $evenement->pays(),
            ':visiteur_hash' => $evenement->visiteurHash()->valeur(),
            ':survenu_le'    => $evenement->survenuLe()->format('Y-m-d H:i:s'),
        ]);

        return $evenement->avecId((int) $this->pdo->lastInsertId());
    }

    /**
     * Compte le nombre d'evenements bruts presents en base.
     * Utile pour le suivi du remplissage et la decision de purge.
     */
    public function compter(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM evenement')->fetchColumn();
    }

    /**
     * Compte les visiteurs uniques d'un jour donne (empreintes
     * distinctes). Base du calcul d'agregat journalier.
     */
    public function compterVisiteursUniques(string $jour): int
    {
        $requete = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT visiteur_hash) FROM evenement
             WHERE DATE(survenu_le) = :jour'
        );
        $requete->execute([':jour' => $jour]);

        return (int) $requete->fetchColumn();
    }

    /**
     * Agrege les consultations d'un jour par page : pages vues (total)
     * et visiteurs uniques (empreintes distinctes).
     *
     * @return list<array{page_id:int, pages_vues:int, visiteurs:int}>
     */
    public function agregerParPage(string $jour): array
    {
        $requete = $this->pdo->prepare(
            'SELECT page_id,
                    COUNT(*)                     AS pages_vues,
                    COUNT(DISTINCT visiteur_hash) AS visiteurs
             FROM evenement
             WHERE DATE(survenu_le) = :jour
             GROUP BY page_id'
        );
        $requete->execute([':jour' => $jour]);

        return array_map(
            static fn (array $l): array => [
                'page_id'    => (int) $l['page_id'],
                'pages_vues' => (int) $l['pages_vues'],
                'visiteurs'  => (int) $l['visiteurs'],
            ],
            $requete->fetchAll(),
        );
    }

    /**
     * Agrege les visiteurs uniques d'un jour par source.
     * Les acces directs (source_id NULL) sont exclus.
     *
     * @return list<array{source_id:int, visiteurs:int}>
     */
    public function agregerParSource(string $jour): array
    {
        $requete = $this->pdo->prepare(
            'SELECT source_id,
                    COUNT(DISTINCT visiteur_hash) AS visiteurs
             FROM evenement
             WHERE DATE(survenu_le) = :jour AND source_id IS NOT NULL
             GROUP BY source_id'
        );
        $requete->execute([':jour' => $jour]);

        return array_map(
            static fn (array $l): array => [
                'source_id' => (int) $l['source_id'],
                'visiteurs' => (int) $l['visiteurs'],
            ],
            $requete->fetchAll(),
        );
    }

    /**
     * Agrege les visiteurs uniques d'un jour par canal.
     *
     * @return list<array{canal:string, visiteurs:int}>
     */
    public function agregerParCanal(string $jour): array
    {
        $requete = $this->pdo->prepare(
            'SELECT canal,
                    COUNT(DISTINCT visiteur_hash) AS visiteurs
             FROM evenement
             WHERE DATE(survenu_le) = :jour
             GROUP BY canal'
        );
        $requete->execute([':jour' => $jour]);

        return array_map(
            static fn (array $l): array => [
                'canal'     => (string) $l['canal'],
                'visiteurs' => (int) $l['visiteurs'],
            ],
            $requete->fetchAll(),
        );
    }

    /**
     * Agrege les visiteurs uniques d'un jour par pays.
     * Les visites sans pays identifie sont exclues.
     *
     * @return list<array{pays:string, visiteurs:int}>
     */
    public function agregerParPays(string $jour): array
    {
        $requete = $this->pdo->prepare(
            'SELECT pays,
                    COUNT(DISTINCT visiteur_hash) AS visiteurs
             FROM evenement
             WHERE DATE(survenu_le) = :jour AND pays IS NOT NULL
             GROUP BY pays'
        );
        $requete->execute([':jour' => $jour]);

        return array_map(
            static fn (array $l): array => [
                'pays'      => (string) $l['pays'],
                'visiteurs' => (int) $l['visiteurs'],
            ],
            $requete->fetchAll(),
        );
    }
}
