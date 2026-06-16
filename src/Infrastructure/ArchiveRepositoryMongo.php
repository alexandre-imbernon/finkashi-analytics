<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use DateTimeImmutable;
use Finkashi\Analytics\Application\Persistance\ArchiveStockage;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Exception\Exception as MongoException;
use RuntimeException;

/**
 * Implementation MongoDB du contrat ArchiveStockage.
 *
 * Stocke les metadonnees d'archives dans une collection MongoDB.
 * Le choix de MongoDB plutot que MySQL pour cette fonction precise
 * est motive par la nature des donnees :
 *
 *  - Les metadonnees d'archives sont des documents independants,
 *    sans relation avec d'autres entites du systeme (contrairement
 *    aux evenements, qui referencent des pages et des sources).
 *  - Le format des documents peut evoluer dans le temps (ajout de
 *    nouveaux champs comme une signature cryptographique, des
 *    statistiques d'archive, etc.) sans necessiter de migration de
 *    schema.
 *  - Le volume est modeste (quelques entrees par mois) mais l'acces
 *    en lecture est ponctuel : MongoDB y est aussi performant que
 *    MySQL sans surcout notable.
 *
 * C'est une illustration du principe **polyglot persistence** :
 * utiliser la bonne technologie pour chaque cas d'usage, plutot
 * qu'une seule base pour tout.
 *
 * Cette implementation n'est pas deployee en production sur
 * l'hebergement OVH mutualise (qui ne propose pas de MongoDB) ;
 * elle existe sur une branche dediee de demonstration.
 */
final class ArchiveRepositoryMongo implements ArchiveStockage
{
    private readonly Collection $collection;

    /**
     * @param string $uri        URI de connexion MongoDB (ex. mongodb://mongo:27017).
     * @param string $base       Nom de la base de donnees MongoDB.
     * @param string $collection Nom de la collection des archives.
     */
    public function __construct(
        string $uri,
        string $base,
        string $collection = 'archives',
    ) {
        try {
            $client = new Client($uri);
            $this->collection = $client->selectCollection($base, $collection);
        } catch (MongoException $e) {
            throw new RuntimeException(
                'Impossible de se connecter a MongoDB : ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function enregistrer(
        string $periodeDebut,
        string $periodeFin,
        string $cheminFichier,
        int $nbEvenements,
    ): void {
        try {
            $this->collection->insertOne([
                'periode_debut' => $periodeDebut,
                'periode_fin'   => $periodeFin,
                'fichier'       => $cheminFichier,
                'nb_evenements' => $nbEvenements,
                'cree_le'       => (new DateTimeImmutable())->format(DATE_ATOM),
            ]);
        } catch (MongoException $e) {
            throw new RuntimeException(
                "Echec de l'ecriture du document d'archive dans MongoDB : " . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
