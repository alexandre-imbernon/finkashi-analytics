<?php
header('Content-Type: application/json');

$donnees = [
    'serveur_x_api_key' => $_SERVER['HTTP_X_API_KEY'] ?? null,
    'serveur_auth'      => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    'redirect_auth'     => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
    'tous_les_entetes_serveur' => array_filter(
        $_SERVER,
        fn($k) => str_starts_with($k, 'HTTP_'),
        ARRAY_FILTER_USE_KEY
    ),
    'apache_request_headers' => function_exists('apache_request_headers')
        ? apache_request_headers()
        : 'fonction indisponible',
];

echo json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);