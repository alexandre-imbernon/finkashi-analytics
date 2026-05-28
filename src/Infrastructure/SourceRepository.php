<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use Finkashi\Analytics\Domain\Canal;
use Finkashi\Analytics\Domain\Source;
use PDO;

/**
 * Composant d'acces aux donnees pour l'entite Source.
 *
 * Traduit entre les objets Source du domaine et la table `source`.
 * La conversion du canal s'appuie sur l'enumeration Canal, ce qui
 * garantit qu'une valeur invalide lue en base serait detectee.
 */
final class SourceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function trouverParDomaine(string $domaine): ?Source
    {
        $requete = $this->pdo->prepare(
            'SELECT id, domaine, canal FROM source WHERE domaine = :domaine LIMIT 1'
        );
        $requete->execute([':domaine' => strtolower(trim($domaine))]);

        $ligne = $requete->fetch();
        if ($ligne === false) {
            return null;
        }

        return $this->hydrater($ligne);
    }

    public function enregistrer(Source $source): Source
    {
        $requete = $this->pdo->prepare(
            'INSERT INTO source (domaine, canal) VALUES (:domaine, :canal)'
        );
        $requete->execute([
            ':domaine' => $source->domaine(),
            ':canal'   => $source->canal()->value,
        ]);

        return $source->avecId((int) $this->pdo->lastInsertId());
    }

    /**
     * Retourne la source correspondant au domaine, en la creant si
     * elle n'existe pas. Le canal est determine automatiquement a
     * partir du domaine si la source est nouvelle.
     */
    public function trouverOuCreer(string $domaine): Source
    {
        $existante = $this->trouverParDomaine($domaine);
        if ($existante !== null) {
            return $existante;
        }

        $canal = Canal::depuisReferent($domaine);

        return $this->enregistrer(new Source($domaine, $canal));
    }

    /**
     * @param array{id:int|string, domaine:string, canal:string} $ligne
     */
    private function hydrater(array $ligne): Source
    {
        return new Source(
            $ligne['domaine'],
            Canal::from($ligne['canal']),
            (int) $ligne['id'],
        );
    }
}
