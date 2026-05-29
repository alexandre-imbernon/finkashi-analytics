<?php
require 'vendor/autoload.php';
use Finkashi\Analytics\Infrastructure\{ConnexionBaseDeDonnees, EvenementRepository, StatistiqueRepository};
use Finkashi\Analytics\Application\ServiceAgregation;

$pdo = (new ConnexionBaseDeDonnees('mysql','3306','finkashi_analytics','finkashi','finkashi_dev'))->pdo();
$agreg = new ServiceAgregation(new EvenementRepository($pdo), new StatistiqueRepository($pdo));
foreach (['2026-05-26','2026-05-27','2026-05-28'] as $jour) {
    print_r($agreg->agregerJour($jour));
}