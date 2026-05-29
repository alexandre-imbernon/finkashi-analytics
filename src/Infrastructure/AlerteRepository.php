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
