<?php

declare(strict_types=1);

/**
 * Configuration de l'application.
 *
 * Strategie a deux niveaux :
 *  - En developpement (Docker), les valeurs viennent des variables
 *    d'environnement (chargees depuis .env via docker-compose).
 *  - En production OVH mutualisee (sans SSH, donc sans env), les
 *    valeurs viennent d'un fichier secrets.php non versionne, place
 *    dans le meme dossier. Si ce fichier existe, il prime.
 *
 * Les secrets critiques sont obligatoires : l'application refuse de
 * demarrer sans, plutot que de retomber sur une valeur par defaut
 * qui constituerait une faille de securite.
 */

$secrets = [];
if (is_file(__DIR__ . '/secrets.php')) {
    $secrets = require __DIR__ . '/secrets.php';
    if (!is_array($secrets)) {
        throw new RuntimeException('Le fichier secrets.php doit retourner un tableau.');
    }
}

$lire = static function (string $cle, ?string $envVar, ?string $defaut = null) use ($secrets) {
    if (array_key_exists($cle, $secrets)) {
        return (string) $secrets[$cle];
    }
    if ($envVar !== null) {
        $valeur = getenv($envVar);
        if ($valeur !== false && $valeur !== '') {
            return (string) $valeur;
        }
    }
    return $defaut;
};

$obligatoire = static function (string $cle, string $envVar) use ($secrets, $lire): string {
    $valeur = $lire($cle, $envVar);
    if ($valeur === null || trim($valeur) === '') {
        throw new RuntimeException(
            "Le secret '{$cle}' est obligatoire (a definir dans secrets.php ou via la variable d'environnement {$envVar})."
        );
    }
    return $valeur;
};

return [
    'db_host'         => $lire('db_host',     'DB_HOST',     'mysql'),
    'db_port'         => $lire('db_port',     'DB_PORT',     '3306'),
    'db_name'         => $lire('db_name',     'DB_NAME',     'finkashi_analytics'),
    'db_user'         => $lire('db_user',     'DB_USER',     'finkashi'),
    'db_password'     => $lire('db_password', 'DB_PASSWORD', 'finkashi_dev'),

    'domaine_site'    => $lire('app_domaine',     'APP_DOMAINE',     'finkashi.fr'),
    'chemin_base_geo' => $lire('app_geoip_path',  'APP_GEOIP_PATH',  __DIR__ . '/../data/GeoLite2-Country.mmdb'),
    'prefixe_tables'  => $lire('app_prefixe_tables', 'APP_PREFIXE_TABLES', ''),

    'domaines_cors'   => array_values(array_filter(array_merge(
        [$lire('app_domaine', 'APP_DOMAINE', 'finkashi.fr')],
        array_map('trim', explode(',', $lire('app_origines_dev', 'APP_ORIGINES_DEV', '') ?? '')),
    ))),

    // Backend de stockage des metadonnees d'archives.
    // Valeurs acceptees :
    //   'mysql' (defaut) : table relationnelle dans la base principale ;
    //   'mongo'          : collection MongoDB (necessite l'extension PHP
    //                      mongodb et un service MongoDB joignable).
    'archive_store'   => $lire('app_archive_store', 'APP_ARCHIVE_STORE', 'mysql'),
    'mongo_uri'       => $lire('app_mongo_uri',     'APP_MONGO_URI',     'mongodb://mongo:27017'),
    'mongo_base'      => $lire('app_mongo_base',    'APP_MONGO_BASE',    'finkashi_analytics'),

    'sel_secret'      => $obligatoire('app_sel_secret', 'APP_SEL_SECRET'),
    'cle_api'         => $obligatoire('app_api_key',    'APP_API_KEY'),
];
