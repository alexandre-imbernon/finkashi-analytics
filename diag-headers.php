<?php
$logFile = __DIR__ . '/diag-headers.log';
$entree = [
    'date'             => date('c'),
    'methode'          => $_SERVER['REQUEST_METHOD'] ?? '?',
    'uri'              => $_SERVER['REQUEST_URI'] ?? '?',
    'http_x_api_key'   => $_SERVER['HTTP_X_API_KEY'] ?? '(absent)',
    'http_auth'        => $_SERVER['HTTP_AUTHORIZATION'] ?? '(absent)',
    'redirect_auth'    => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '(absent)',
    'tous_http'        => array_filter(array_keys($_SERVER), fn($k) => str_starts_with($k, 'HTTP_')),
];
file_put_contents($logFile, json_encode($entree, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);
echo "OK, log écrit.\n";