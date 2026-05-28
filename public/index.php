<?php

declare(strict_types=1);

/**
 * Point d'entree unique de l'application (front controller).
 *
 * A ce stade de la Phase 1, ce fichier sert uniquement a verifier
 * que l'atelier Docker fonctionne : PHP repond, et la connexion a
 * MySQL est operationnelle. Le routage reel viendra plus tard.
 */

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: text/plain; charset=utf-8');

echo "Finkashi Analytics — verification de l'environnement\n";
echo "----------------------------------------------------\n";
echo 'Version de PHP : ' . PHP_VERSION . "\n";

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'mysql',
        getenv('DB_PORT') ?: '3306',
        getenv('DB_NAME') ?: 'finkashi_analytics'
    );

    $pdo = new PDO(
        $dsn,
        getenv('DB_USER') ?: 'finkashi',
        getenv('DB_PASSWORD') ?: 'finkashi_dev',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo 'Connexion MySQL : OK (serveur ' . $version . ")\n";
    echo "\nL'atelier est operationnel.\n";
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Connexion MySQL : ECHEC — ' . $e->getMessage() . "\n";
}
