<?php

declare(strict_types=1);

/**
 * Script de maintenance quotidienne, destine a etre execute par le
 * planificateur de taches (cron).
 *
 * Enchaine trois operations :
 *   1. Agregation des donnees journalieres des derniers jours, pour
 *      capter les visites qui seraient arrivees apres la derniere
 *      agregation.
 *   2. Evaluation des regles d'alerte et declenchement des alertes
 *      pour la journee qui vient de s'achever.
 *   3. Archivage et purge des evenements bruts trop anciens (au-dela
 *      de la duree de retention configuree).
 *
 * Sortie : un journal lisible sur stdout, qu'on peut rediriger dans
 * un fichier de log si besoin.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Finkashi\Analytics\Infrastructure\Fabrique;

$config = require __DIR__ . '/../config/config.php';
$fabrique = new Fabrique($config);

// Nombre de jours retroactifs a re-agreger (capte les visites
// arrivees apres la derniere execution du cron).
$joursAReAgreger = 3;

// Duree de retention des evenements bruts, en jours. Au-dela, ils
// sont archives puis purges. Les agregats, eux, restent indefiniment.
$joursDeRetention = 60;

$aujourdhui = new DateTimeImmutable('today');

echo "[" . $aujourdhui->format('Y-m-d H:i:s') . "] Demarrage du cron quotidien\n";

// --- 1. Agregation ----------------------------------------------------
$agregation = $fabrique->serviceAgregation();
for ($i = 0; $i <= $joursAReAgreger; $i++) {
    $jour = $aujourdhui->sub(new DateInterval("P{$i}D"))->format('Y-m-d');
    $resultat = $agregation->agregerJour($jour);
    echo "  - Agregation du {$jour} : "
        . "pages={$resultat['pages']}, sources={$resultat['sources']}, "
        . "canaux={$resultat['canaux']}, pays={$resultat['pays']}\n";
}

// --- 2. Alertes -------------------------------------------------------
$alertes = $fabrique->serviceDetectionAlerte();
$hier = $aujourdhui->sub(new DateInterval('P1D'))->format('Y-m-d');
$declenchees = $alertes->evaluerJour($hier);
echo "  - Evaluation des alertes pour {$hier} : " . count($declenchees) . " declenchee(s)\n";

// --- 3. Archivage et purge -------------------------------------------
$archivage = $fabrique->serviceArchivage();
$limitePurge = $aujourdhui->sub(new DateInterval("P{$joursDeRetention}D"))->format('Y-m-d');
$resultat = $archivage->archiverEtPurger($limitePurge);
echo "  - Archivage avant {$limitePurge} : "
    . "{$resultat['nb_evenements']} evenement(s) archive(s) dans {$resultat['fichier']}\n";

echo "[" . (new DateTimeImmutable())->format('Y-m-d H:i:s') . "] Cron quotidien termine\n";
