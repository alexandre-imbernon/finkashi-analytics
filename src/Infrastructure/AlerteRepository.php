<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use DateTimeImmutable;
use Finkashi\Analytics\Domain\AlerteRegle;
use Finkashi\Analytics\Domain\Metrique;
use Finkashi\Analytics\Domain\Operateur;
use PDO;

/**
 * Composant d'acces aux donnees pour les alertes : regles configurees
 * (table alerte_regle) et historique des declenchements
 * (table alerte_declenchee).
 */
final class AlerteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Retourne toutes les regles actives.
     *
     * @return list<AlerteRegle>
     */
    public function reglesActives(): array
    {
        $resultat = $this->pdo->query(
            'SELECT id, metrique, operateur, seuil, active
             FROM alerte_regle
             WHERE active = TRUE'
        );

        return array_map(
            fn (array $l): AlerteRegle => $this->hydrater($l),
            $resultat->fetchAll(),
        );
    }

    /**
     * Retourne toutes les regles, actives ou non, dans l'ordre de
     * creation (id croissant). Utilise pour la liste d'administration.
     *
     * @return list<AlerteRegle>
     */
    public function toutes(): array
    {
        $resultat = $this->pdo->query(
            'SELECT id, metrique, operateur, seuil, active
             FROM alerte_regle
             ORDER BY id'
        );

        return array_map(
            fn (array $l): AlerteRegle => $this->hydrater($l),
            $resultat->fetchAll(),
        );
    }

    /**
     * Cherche une regle par son identifiant. Retourne null si absente.
     */
    public function parId(int $id): ?AlerteRegle
    {
        $requete = $this->pdo->prepare(
            'SELECT id, metrique, operateur, seuil, active
             FROM alerte_regle
             WHERE id = :id'
        );
        $requete->execute([':id' => $id]);
        $ligne = $requete->fetch();

        return $ligne !== false ? $this->hydrater($ligne) : null;
    }

    /**
     * Cree une nouvelle regle et retourne celle-ci enrichie de son id.
     */
    public function creer(AlerteRegle $regle): AlerteRegle
    {
        $requete = $this->pdo->prepare(
            'INSERT INTO alerte_regle (metrique, operateur, seuil, active)
             VALUES (:metrique, :operateur, :seuil, :active)'
        );
        $requete->execute([
            ':metrique'  => $regle->metrique()->value,
            ':operateur' => $regle->operateur()->value,
            ':seuil'     => $regle->seuil(),
            ':active'    => $regle->estActive() ? 1 : 0,
        ]);

        return new AlerteRegle(
            $regle->metrique(),
            $regle->operateur(),
            $regle->seuil(),
            $regle->estActive(),
            (int) $this->pdo->lastInsertId(),
        );
    }

    /**
     * Met a jour une regle existante. Renvoie true si une ligne a ete
     * effectivement modifiee.
     */
    public function mettreAJour(AlerteRegle $regle): bool
    {
        if ($regle->id() === null) {
            return false;
        }
        $requete = $this->pdo->prepare(
            'UPDATE alerte_regle
                SET metrique = :metrique,
                    operateur = :operateur,
                    seuil = :seuil,
                    active = :active
              WHERE id = :id'
        );
        $requete->execute([
            ':id'        => $regle->id(),
            ':metrique'  => $regle->metrique()->value,
            ':operateur' => $regle->operateur()->value,
            ':seuil'     => $regle->seuil(),
            ':active'    => $regle->estActive() ? 1 : 0,
        ]);

        return $requete->rowCount() > 0;
    }

    /**
     * Supprime une regle et son historique de declenchements
     * (cascade definie au niveau de la base).
     */
    public function supprimer(int $id): bool
    {
        $requete = $this->pdo->prepare('DELETE FROM alerte_regle WHERE id = :id');
        $requete->execute([':id' => $id]);

        return $requete->rowCount() > 0;
    }

    /**
     * Recupere l'historique des declenchements sur une plage de jours.
     *
     * @return list<array{
     *     id:int, regle_id:int, metrique:string, operateur:string,
     *     seuil:int, valeur_constatee:int, declenchee_le:string,
     *     notifiee:bool
     * }>
     */
    public function historique(string $depuis, string $jusque, int $limite = 50): array
    {
        $requete = $this->pdo->prepare(
            'SELECT d.id, d.regle_id, d.valeur_constatee, d.declenchee_le, d.notifiee,
                    r.metrique, r.operateur, r.seuil
             FROM alerte_declenchee d
             JOIN alerte_regle r ON r.id = d.regle_id
             WHERE DATE(d.declenchee_le) BETWEEN :depuis AND :jusque
             ORDER BY d.declenchee_le DESC
             LIMIT :limite'
        );
        $requete->bindValue(':depuis', $depuis);
        $requete->bindValue(':jusque', $jusque);
        $requete->bindValue(':limite', $limite, PDO::PARAM_INT);
        $requete->execute();

        return array_map(
            static fn (array $l): array => [
                'id'               => (int) $l['id'],
                'regle_id'         => (int) $l['regle_id'],
                'metrique'         => (string) $l['metrique'],
                'operateur'        => (string) $l['operateur'],
                'seuil'            => (int) $l['seuil'],
                'valeur_constatee' => (int) $l['valeur_constatee'],
                'declenchee_le'    => (string) $l['declenchee_le'],
                'notifiee'         => (bool) $l['notifiee'],
            ],
            $requete->fetchAll(),
        );
    }

    /**
     * Enregistre le declenchement d'une alerte.
     */
    public function enregistrerDeclenchement(
        AlerteRegle $regle,
        int $valeurConstatee,
        DateTimeImmutable $instant,
    ): void {
        $requete = $this->pdo->prepare(
            'INSERT INTO alerte_declenchee
                (regle_id, valeur_constatee, declenchee_le, notifiee)
             VALUES
                (:regle_id, :valeur, :instant, FALSE)'
        );
        $requete->execute([
            ':regle_id' => $regle->id(),
            ':valeur'   => $valeurConstatee,
            ':instant'  => $instant->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Verifie si une regle a deja ete declenchee pour un jour donne,
     * afin d'eviter les doublons sur une meme journee.
     */
    public function dejaDeclencheeLeJour(AlerteRegle $regle, string $jour): bool
    {
        $requete = $this->pdo->prepare(
            'SELECT 1 FROM alerte_declenchee
             WHERE regle_id = :regle_id
               AND DATE(declenchee_le) = :jour
             LIMIT 1'
        );
        $requete->execute([
            ':regle_id' => $regle->id(),
            ':jour'     => $jour,
        ]);

        return $requete->fetchColumn() !== false;
    }

    /**
     * @param array{id:int|string, metrique:string, operateur:string,
     *              seuil:int|string, active:int|string|bool} $ligne
     */
    private function hydrater(array $ligne): AlerteRegle
    {
        return new AlerteRegle(
            Metrique::from($ligne['metrique']),
            Operateur::from($ligne['operateur']),
            (int) $ligne['seuil'],
            (bool) $ligne['active'],
            (int) $ligne['id'],
        );
    }
}
