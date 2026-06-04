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
use Finkashi\Analytics\Http\ControleurAlertes;
use Finkashi\Analytics\Http\ControleurCollecte;
use Finkashi\Analytics\Http\ControleurCron;
use Finkashi\Analytics\Http\ControleurStats;
use Finkashi\Analytics\Http\ReponseJson;
use Finkashi\Analytics\Http\Routeur;
use Finkashi\Analytics\Infrastructure\AlerteRepository;
use Finkashi\Analytics\Infrastructure\Fabrique;

$config = require __DIR__ . '/../config/config.php';
$fabrique = new Fabrique($config);

// Construction des controleurs avec leurs dependances.
$auth = new AuthentificationClef($config['cle_api']);
$controleurCollecte = new ControleurCollecte($fabrique->serviceCollecte(), $config['domaines_cors']);
$controleurStats    = new ControleurStats($fabrique->statistiqueRepository(), $auth);
$controleurAlertes  = new ControleurAlertes(new AlerteRepository($fabrique->pdo(), $config['prefixe_tables'] ?? ''), $auth);
$controleurCron     = new ControleurCron($fabrique, $config['cle_api']);

// Definition des routes.
$routeur = new Routeur();

$routeur->ajouter('POST',    '/collect', fn () => $controleurCollecte->gerer());
$routeur->ajouter('OPTIONS', '/collect', fn () => $controleurCollecte->gerer());

$routeur->ajouter('GET', '/stats/trafic',  fn () => $controleurStats->trafficGlobal());
$routeur->ajouter('GET', '/stats/pages',   fn () => $controleurStats->classementPages());
$routeur->ajouter('GET', '/stats/canaux',  fn () => $controleurStats->repartitionParCanal());
$routeur->ajouter('GET', '/stats/sources', fn () => $controleurStats->classementSources());
$routeur->ajouter('GET', '/stats/pays',    fn () => $controleurStats->repartitionParPays());

$routeur->ajouter('GET',    '/alertes/regles',     fn ()      => $controleurAlertes->lister());
$routeur->ajouter('POST',   '/alertes/regles',     fn ()      => $controleurAlertes->creer());
$routeur->ajouter('PUT',    '/alertes/regles/:id', fn (array $p) => $controleurAlertes->modifier($p));
$routeur->ajouter('DELETE', '/alertes/regles/:id', fn (array $p) => $controleurAlertes->supprimer($p));
$routeur->ajouter('GET',    '/alertes/historique', fn ()      => $controleurAlertes->historique());

$routeur->ajouter('GET',  '/cron/quotidien', fn () => $controleurCron->executer());
$routeur->ajouter('POST', '/cron/quotidien', fn () => $controleurCron->executer());

// Extraction de la methode et du chemin (sans query string).
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$chemin  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// En production, l'API est installee dans un sous-dossier de
// l'hebergement (ex. /finkashi-analytics/). On retire ce prefixe
// pour que les routes definies plus haut ("/stats/trafic", etc.)
// matchent correctement. Le prefixe est determine dynamiquement
// a partir de SCRIPT_NAME.
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$basePath = preg_replace('#/public$#', '', $basePath);
if ($basePath !== '' && str_starts_with($chemin, $basePath)) {
    $chemin = substr($chemin, strlen($basePath)) ?: '/';
}

// Dispatch.
if (!$routeur->dispatcher($methode, $chemin)) {
    ReponseJson::erreur('Route inconnue.', 404);
}
