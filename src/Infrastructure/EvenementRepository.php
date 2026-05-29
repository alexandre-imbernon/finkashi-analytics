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
     * Compte les evenements anterieurs a une date (utile pour decider
     * de l'archivage et estimer le poids de la purge).
     */
    public function compterAvant(string $jour): int
    {
        $requete = $this->pdo->prepare(
            'SELECT COUNT(*) FROM evenement WHERE DATE(survenu_le) < :jour'
        );
        $requete->execute([':jour' => $jour]);

        return (int) $requete->fetchColumn();
    }

    /**
     * Recupere les evenements anterieurs a une date, sous forme de
     * lignes brutes pretes a etre archivees. Volontairement sans
     * objet pour limiter la consommation memoire sur de gros volumes.
     *
     * @return iterable<array{
     *     id:int, page_id:int, source_id:?int, canal:string,
     *     pays:?string, visiteur_hash:string, survenu_le:string
     * }>
     */
    public function lireAvant(string $jour): iterable
    {
        $requete = $this->pdo->prepare(
            'SELECT id, page_id, source_id, canal, pays, visiteur_hash, survenu_le
             FROM evenement
             WHERE DATE(survenu_le) < :jour
             ORDER BY survenu_le'
        );
        $requete->execute([':jour' => $jour]);

        while (($ligne = $requete->fetch()) !== false) {
            yield [
                'id'            => (int) $ligne['id'],
                'page_id'       => (int) $ligne['page_id'],
                'source_id'     => $ligne['source_id'] !== null ? (int) $ligne['source_id'] : null,
                'canal'         => (string) $ligne['canal'],
                'pays'          => $ligne['pays'] !== null ? (string) $ligne['pays'] : null,
                'visiteur_hash' => (string) $ligne['visiteur_hash'],
                'survenu_le'    => (string) $ligne['survenu_le'],
            ];
        }
    }

    /**
     * Supprime les evenements anterieurs a une date donnee.
     * Retourne le nombre de lignes effectivement supprimees.
     *
     * A n'appeler qu'apres avoir verifie que les donnees ont ete
     * archivees et que les agregats correspondants existent.
     */
    public function supprimerAvant(string $jour): int
    {
        $requete = $this->pdo->prepare(
            'DELETE FROM evenement WHERE DATE(survenu_le) < :jour'
        );
        $requete->execute([':jour' => $jour]);

        return $requete->rowCount();
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
