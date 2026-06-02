<?php

/**
 * Fichier de secrets de production.
 *
 * Ce fichier est destine a l'hebergement OVH mutualise, qui ne permet
 * pas l'usage de variables d'environnement (pas de SSH, pas de
 * conteneur). Il prend la place du fichier ".env" utilise en
 * developpement local Docker.
 *
 * Procedure :
 *  1. Copier ce fichier en "secrets.php" dans le meme dossier.
 *  2. Remplacer toutes les valeurs par les vraies.
 *  3. NE JAMAIS COMMITER "secrets.php" : il est dans .gitignore.
 *  4. Verifier que le .htaccess interdit son acces web.
 *
 * Generation de secrets robustes :
 *   docker compose exec php php -r "echo bin2hex(random_bytes(32));"
 *   (ou un equivalent en ligne)
 */

return [
    // --- Connexion a la base de donnees OVH -------------------------
    // Valeurs visibles dans le manager OVH, onglet "Bases de donnees".
    'db_host'     => 'finkashi.mysql.db',   // serveur de la base
    'db_port'     => '3306',
    'db_name'     => 'finkashi',            // nom de la base
    'db_user'     => 'finkashi',            // utilisateur
    'db_password' => 'REMPLACER',           // mot de passe

    // --- Secrets applicatifs ----------------------------------------
    // OBLIGATOIRES. Generer chacun avec bin2hex(random_bytes(32)).
    'app_api_key'    => 'REMPLACER-PAR-UNE-CLE-DE-64-CARACTERES',
    'app_sel_secret' => 'REMPLACER-PAR-UN-SECRET-DE-64-CARACTERES',

    // --- Configuration applicative ----------------------------------
    'app_domaine'        => 'finkashi.fr',     // sans protocole, sans www
    'app_origines_dev'   => '',                // vide en prod, "localhost:8090" en dev
    'app_prefixe_tables' => 'finkashi_',       // cohabitation avec WordPress

    // --- Chemin vers la base de geolocalisation ---------------------
    'app_geoip_path' => __DIR__ . '/../data/GeoLite2-Country.mmdb',
];
