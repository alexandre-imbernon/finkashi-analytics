<?php
// Vue : Dashboard du plugin.
// A ce stade, contenu statique pour valider l'integration visuelle.
// Les donnees seront injectees dynamiquement a l'etape suivante.

if (!defined('ABSPATH')) { exit; }
?>
<div class="wrap finkashi-wrap">
    <h1 class="wp-heading-inline">Finkashi Analytics</h1>
    <p class="finkashi-subtitle">Statistiques de frequentation du site, sans cookie ni traceur publicitaire.</p>

    <div class="notice notice-info inline">
        <p>
            <strong>Plugin actif.</strong>
            Les donnees dynamiques seront branchees a l'API a la prochaine etape.
            Pour l'instant, cet ecran montre la structure validee en CP2.
        </p>
    </div>

    <div class="finkashi-toolbar">
        <label>Periode :</label>
        <select class="finkashi-select">
            <option>7 derniers jours</option>
            <option selected>30 derniers jours</option>
            <option>90 derniers jours</option>
        </select>
    </div>

    <div class="finkashi-grid">
        <div class="finkashi-card">
            <div class="finkashi-stat-label">Visiteurs uniques</div>
            <div class="finkashi-stat-value">-</div>
        </div>
        <div class="finkashi-card">
            <div class="finkashi-stat-label">Pages vues</div>
            <div class="finkashi-stat-value">-</div>
        </div>
        <div class="finkashi-card">
            <div class="finkashi-stat-label">Pages / visite</div>
            <div class="finkashi-stat-value">-</div>
        </div>
        <div class="finkashi-card">
            <div class="finkashi-stat-label">Pays touches</div>
            <div class="finkashi-stat-value">-</div>
        </div>
    </div>

    <div class="finkashi-card-full">
        <h2 class="finkashi-card-title">Trafic quotidien</h2>
        <p class="finkashi-card-placeholder">Le graphique sera affiche ici (Chart.js).</p>
    </div>

    <div class="finkashi-two-cols">
        <div class="finkashi-card-full">
            <h2 class="finkashi-card-title">Origine du trafic</h2>
            <p class="finkashi-card-placeholder">Donut des canaux d'acquisition.</p>
        </div>
        <div class="finkashi-card-full">
            <h2 class="finkashi-card-title">Pages les plus consultees</h2>
            <p class="finkashi-card-placeholder">Tableau des pages.</p>
        </div>
    </div>
</div>
