<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use Finkashi\Analytics\Application\FournisseurSelQuotidien;
use Finkashi\Analytics\Application\Geolocalisateur;
use Finkashi\Analytics\Application\ServiceAgregation;
use Finkashi\Analytics\Application\ServiceArchivage;
use Finkashi\Analytics\Application\ServiceCollecte;
use Finkashi\Analytics\Application\ServiceDetectionAlerte;
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

    /**
     * @param array{
     *   db_host:string, db_port:string, db_name:string,
     *   db_user:string, db_password:string,
     *   sel_secret:string, domaine_site:string,
     *   chemin_base_geo:string, cle_api:string
     * } $config
     */
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

    public function serviceCollecte(): ServiceCollecte
    {
        return new ServiceCollecte(
            new PageRepository($this->pdo()),
            new SourceRepository($this->pdo()),
            new EvenementRepository($this->pdo()),
            new FournisseurSelQuotidien($this->config['sel_secret']),
            $this->geolocalisateur(),
            $this->config['domaine_site'],
        );
    }

    public function serviceAgregation(): ServiceAgregation
    {
        return new ServiceAgregation(
            new EvenementRepository($this->pdo()),
            new StatistiqueRepository($this->pdo()),
        );
    }

    public function serviceDetectionAlerte(): ServiceDetectionAlerte
    {
        return new ServiceDetectionAlerte(
            new AlerteRepository($this->pdo()),
            $this->pdo(),
        );
    }

    public function serviceArchivage(): ServiceArchivage
    {
        return new ServiceArchivage(
            new EvenementRepository($this->pdo()),
            new ArchiveRepository($this->pdo()),
            $this->config['dossier_archives'] ?? __DIR__ . '/../../storage/archives',
        );
    }

    public function statistiqueRepository(): StatistiqueRepository
    {
        return new StatistiqueRepository($this->pdo());
    }

    private function geolocalisateur(): Geolocalisateur
    {
        return $this->geolocalisateur ??= new GeolocalisateurMaxMind(
            $this->config['chemin_base_geo'],
        );
    }
}
