<?php
require 'vendor/autoload.php';
use Finkashi\Analytics\Infrastructure\{ConnexionBaseDeDonnees, AlerteRepository};
use Finkashi\Analytics\Application\ServiceDetectionAlerte;

$pdo = (new ConnexionBaseDeDonnees('mysql','3306','finkashi_analytics','finkashi','finkashi_dev'))->pdo();
$service = new ServiceDetectionAlerte(new AlerteRepository($pdo), $pdo);
foreach (['2026-05-26','2026-05-27','2026-05-28'] as $jour) {
    $declenchees = $service->evaluerJour($jour);
    echo "$jour : ".count($declenchees)." alerte(s)\n";
}