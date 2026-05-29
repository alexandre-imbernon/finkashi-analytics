<?php

declare(strict_types=1);

/**
 * Configuration de l'application.
 *
 * Toutes les valeurs sensibles (mots de passe, secrets, cles d'API)
 * proviennent de variables d'environnement. Le code ne contient
 * aucun secret en dur, ce qui permet de versionner le projet sans
 * craindre une fuite.
 *
 * Les secrets critiques (cle d'API, sel d'anonymisation) doivent
 * obligatoirement etre definis : l'application refuse de demarrer
 * si l'un d'eux manque, plutot que de retomber sur une valeur par
 * defaut qui constituerait une faille de securite.
 */

/**
 * Lit une variable d'environnement obligatoire. Leve une exception
 * si elle est absente ou vide.
 */
$obligatoire = static function (string $nom): string {
    $valeur = getenv($nom);
    if ($valeur === false || trim($valeur) === '') {
        throw new RuntimeException(
            "La variable d'environnement {$nom} est obligatoire et doit etre definie."
        );
    }
    return $valeur;
};

return [
    // Connexion a la base : valeurs par defaut acceptables car
    // strictement de developpement.
    'db_host'         => getenv('DB_HOST')         ?: 'mysql',
    'db_port'         => getenv('DB_PORT')         ?: '3306',
    'db_name'         => getenv('DB_NAME')         ?: 'finkashi_analytics',
    'db_user'         => getenv('DB_USER')         ?: 'finkashi',
    'db_password'     => getenv('DB_PASSWORD')     ?: 'finkashi_dev',

    // Configuration applicative.
    'domaine_site'    => getenv('APP_DOMAINE')     ?: 'finkashi.fr',
    'chemin_base_geo' => getenv('APP_GEOIP_PATH')  ?: __DIR__ . '/../data/GeoLite2-Country.mmdb',

    // Secrets : OBLIGATOIRES. Aucune valeur par defaut.
    'sel_secret'      => $obligatoire('APP_SEL_SECRET'),
    'cle_api'         => $obligatoire('APP_API_KEY'),
];
