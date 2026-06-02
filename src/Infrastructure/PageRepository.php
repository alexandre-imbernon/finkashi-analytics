<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use Finkashi\Analytics\Domain\Page;
use PDO;

/**
 * Composant d'acces aux donnees pour l'entite Page.
 *
 * Traduit entre les objets Page du domaine et la table `page`.
 * Toutes les requetes sont preparees : les valeurs transmises par
 * l'exterieur ne sont jamais concatenees dans le SQL, ce qui protege
 * contre l'injection SQL.
 */
final class PageRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $prefixe = '',
    ) {
    }

    /**
     * Recherche une page par son chemin. Retourne null si absente.
     */
    public function trouverParChemin(string $chemin): ?Page
    {
        $requete = $this->pdo->prepare(
            "SELECT id, chemin, titre FROM {$this->prefixe}page WHERE chemin = :chemin LIMIT 1"
        );
        $requete->execute([':chemin' => trim($chemin)]);

        $ligne = $requete->fetch();
        if ($ligne === false) {
            return null;
        }

        return $this->hydrater($ligne);
    }

    /**
     * Enregistre une nouvelle page et retourne l'objet dote de son id.
     */
    public function enregistrer(Page $page): Page
    {
        $requete = $this->pdo->prepare(
            "INSERT INTO {$this->prefixe}page (chemin, titre) VALUES (:chemin, :titre)"
        );
        $requete->execute([
            ':chemin' => $page->chemin(),
            ':titre'  => $page->titre(),
        ]);

        return $page->avecId((int) $this->pdo->lastInsertId());
    }

    /**
     * Retourne la page correspondant au chemin, en la creant si elle
     * n'existe pas encore. Operation courante lors de la collecte.
     */
    public function trouverOuCreer(string $chemin, ?string $titre = null): Page
    {
        $existante = $this->trouverParChemin($chemin);
        if ($existante !== null) {
            return $existante;
        }

        return $this->enregistrer(new Page($chemin, $titre));
    }

    /**
     * Reconstruit un objet Page a partir d'une ligne de la base.
     *
     * @param array{id:int|string, chemin:string, titre:string|null} $ligne
     */
    private function hydrater(array $ligne): Page
    {
        return new Page(
            $ligne['chemin'],
            $ligne['titre'],
            (int) $ligne['id'],
        );
    }
}
