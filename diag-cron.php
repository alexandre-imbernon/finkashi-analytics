<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use Finkashi\Analytics\Infrastructure\Fabrique;

$config = require __DIR__ . '/../config/config.php';
$fabrique = new Fabrique($config);

echo "Test agregation... ";
try {
    $r = $fabrique->serviceAgregation()->agregerJour(date('Y-m-d'));
    echo "OK : " . json_encode($r) . "<br>\n";
} catch (\Throwable $e) {
    echo "ECHEC : " . htmlspecialchars($e->getMessage()) . "<br>at " . $e->getFile() . ":" . $e->getLine() . "<br>\n";
}

echo "Test alertes... ";
try {
    $r = $fabrique->serviceDetectionAlerte()->evaluerJour(date('Y-m-d', strtotime('-1 day')));
    echo "OK : " . count($r) . " declenchee(s)<br>\n";
} catch (\Throwable $e) {
    echo "ECHEC : " . htmlspecialchars($e->getMessage()) . "<br>at " . $e->getFile() . ":" . $e->getLine() . "<br>\n";
}

echo "Test archivage... ";
try {
    $r = $fabrique->serviceArchivage()->archiverEtPurger(date('Y-m-d', strtotime('-60 days')));
    echo "OK : " . json_encode($r) . "<br>\n";
} catch (\Throwable $e) {
    echo "ECHEC : " . htmlspecialchars($e->getMessage()) . "<br>at " . $e->getFile() . ":" . $e->getLine() . "<br>\n";
}