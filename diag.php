<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "Étape 1 : PHP démarre OK<br>\n";

echo "Étape 2 : Test autoload... ";
require_once __DIR__ . '/../vendor/autoload.php';
echo "OK<br>\n";

echo "Étape 3 : Test config... ";
$config = require __DIR__ . '/../config/config.php';
echo "OK<br>\n";

echo "Étape 4 : Clés présentes : " . implode(', ', array_keys($config)) . "<br>\n";

echo "Étape 5 : Test connexion PDO... ";
try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_password']);
    echo "OK<br>\n";
} catch (\Throwable $e) {
    echo "ECHEC : " . htmlspecialchars($e->getMessage()) . "<br>\n";
}

echo "Étape 6 : Tables présentes... ";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$config['prefixe_tables']}%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo count($tables) . " tables trouvées : " . implode(', ', $tables) . "<br>\n";
} catch (\Throwable $e) {
    echo "ECHEC : " . htmlspecialchars($e->getMessage()) . "<br>\n";
}

echo "<br>FIN.";