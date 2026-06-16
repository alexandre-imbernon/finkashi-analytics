<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use Finkashi\Analytics\Application\FournisseurSelQuotidien;
use Finkashi\Analytics\Application\Geolocalisateur;
use Finkashi\Analytics\Application\Persistance\ArchiveStockage;
use Finkashi\Analytics\Application\ServiceAgregation;
use Finkashi\Analytics\Application\ServiceArchivage;
use Finkashi\Analytics\Application\ServiceCollecte;
use Finkashi\Analytics\Application\ServiceDetectionAlerte;
use InvalidArgumentException;
use PDO;

/**
 * Fabrique de services : point unique de construction de l'application.
 *
 * Toutes les dependances sont cablees ici, ce qui evite de disperser
 * les "new" partout dans le code et facilite les changements de
 * configuration. Chaque service est mis en cache : on n'en construit
 * qu'une instance par requete HTTP.
 */
final class Fabrique
{
    private ?PDO $pdo = null;
    private ?Geolocalisateur $geolocalisateur = null;

    public function __construct(private readonly array $config)
    {
    }

    public function config(): array
    {
        return $this->config;
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $connexion = new ConnexionBaseDeDonnees(
                $this->config['db_host'],
                $this->config['db_port'],
                $this->config['db_name'],
                $this->config['db_user'],
                $this->config['db_password'],
            );
            $this->pdo = $connexion->pdo();
        }

        return $this->pdo;
    }

    private function prefixe(): string
    {
        return $this->config['prefixe_tables'] ?? '';
    }

    public function serviceCollecte(): ServiceCollecte
    {
        return new ServiceCollecte(
            new PageRepository($this->pdo(), $this->prefixe()),
            new SourceRepository($this->pdo(), $this->prefixe()),
            new EvenementRepository($this->pdo(), $this->prefixe()),
            new FournisseurSelQuotidien($this->config['sel_secret']),
            $this->geolocalisateur(),
            $this->config['domaine_site'],
        );
    }

    public function serviceAgregation(): ServiceAgregation
    {
        return new ServiceAgregation(
            new EvenementRepository($this->pdo(), $this->prefixe()),
            new StatistiqueRepository($this->pdo(), $this->prefixe()),
        );
    }

    public function serviceDetectionAlerte(): ServiceDetectionAlerte
    {
        return new ServiceDetectionAlerte(
            new AlerteRepository($this->pdo(), $this->prefixe()),
            $this->pdo(),
            $this->prefixe(),
        );
    }

    public function serviceArchivage(): ServiceArchivage
    {
        return new ServiceArchivage(
            new EvenementRepository($this->pdo(), $this->prefixe()),
            $this->archiveStockage(),
            $this->config['dossier_archives'] ?? __DIR__ . '/../../storage/archives',
        );
    }

    /**
     * Selectionne l'implementation de persistance des archives selon
     * la configuration. Deux backends sont disponibles :
     *
     *  - "mysql" (defaut) : table relationnelle dans la base principale.
     *    Utilise en production et en developpement standard.
     *
     *  - "mongo"          : collection MongoDB. Implementation alternative
     *    qui demontre la portabilite de l'architecture en couches : seul
     *    le constructeur change, ServiceArchivage est inchange.
     *
     * Le choix est pilote par la variable de config "archive_store".
     * Si la variable est absente, on retombe sur MySQL pour ne rien
     * casser de l'existant.
     */
    private function archiveStockage(): ArchiveStockage
    {
        $backend = strtolower((string) ($this->config['archive_store'] ?? 'mysql'));

        return match ($backend) {
            'mysql' => new ArchiveRepository($this->pdo(), $this->prefixe()),
            'mongo' => new ArchiveRepositoryMongo(
                (string) ($this->config['mongo_uri']  ?? 'mongodb://mongo:27017'),
                (string) ($this->config['mongo_base'] ?? 'finkashi_analytics'),
            ),
            default => throw new InvalidArgumentException(
                "Backend d'archive inconnu : {$backend}. Valeurs attendues : 'mysql' ou 'mongo'."
            ),
        };
    }

    public function statistiqueRepository(): StatistiqueRepository
    {
        return new StatistiqueRepository($this->pdo(), $this->prefixe());
    }

    private function geolocalisateur(): Geolocalisateur
    {
        return $this->geolocalisateur ??= new GeolocalisateurMaxMind(
            $this->config['chemin_base_geo'],
        );
    }
}
