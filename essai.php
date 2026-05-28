<?php
require 'vendor/autoload.php';
use Finkashi\Analytics\Infrastructure\{ConnexionBaseDeDonnees, EvenementRepository};

$cnx = new ConnexionBaseDeDonnees('mysql', '3306', 'finkashi_analytics', 'finkashi', 'finkashi_dev');
$repo = new EvenementRepository($cnx->pdo());

echo "Total events : " . $repo->compter() . "\n";          // doit afficher 12
echo "Uniques le 28/05 : " . $repo->compterVisiteursUniques('2026-05-28') . "\n";