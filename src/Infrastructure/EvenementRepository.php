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
}
