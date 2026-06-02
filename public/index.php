<?php

declare(strict_types=1);

/**
 * Point d'entree unique de l'API (front controller).
 *
 * Toutes les requetes HTTP arrivent ici (grace au .htaccess), sont
 * routees vers le bon controleur, puis traitees. La configuration
 * vient de variables d'environnement ; aucun secret n'apparait dans
 * le code.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Finkashi\Analytics\Http\AuthentificationClef;
use Finkashi\Analytics\Http\ControleurCollecte;
use Finkashi\Analytics\Http\ControleurStats;
use Finkashi\Analytics\Http\ReponseJson;
use Finkashi\Analytics\Http\Routeur;
use Finkashi\Analytics\Infrastructure\Fabrique;

$config = require __DIR__ . '/../config/config.php';
$fabrique = new Fabrique($config);

// Construction des controleurs avec leurs dependances.
$auth = new AuthentificationClef($config['cle_api']);
$controleurCollecte = new ControleurCollecte($fabrique->serviceCollecte(), $config['domaines_cors']);
$controleurStats    = new ControleurStats($fabrique->statistiqueRepository(), $auth);

// Definition des routes.
$routeur = new Routeur();

$routeur->ajouter('POST',    '/collect', fn () => $controleurCollecte->gerer());
$routeur->ajouter('OPTIONS', '/collect', fn () => $controleurCollecte->gerer());

$routeur->ajouter('GET', '/stats/trafic',  fn () => $controleurStats->trafficGlobal());
$routeur->ajouter('GET', '/stats/pages',   fn () => $controleurStats->classementPages());
$routeur->ajouter('GET', '/stats/canaux',  fn () => $controleurStats->repartitionParCanal());
$routeur->ajouter('GET', '/stats/sources', fn () => $controleurStats->classementSources());
$routeur->ajouter('GET', '/stats/pays',    fn () => $controleurStats->repartitionParPays());

// Extraction de la methode et du chemin (sans query string).
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$chemin  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Dispatch.
if (!$routeur->dispatcher($methode, $chemin)) {
    ReponseJson::erreur('Route inconnue.', 404);
}
