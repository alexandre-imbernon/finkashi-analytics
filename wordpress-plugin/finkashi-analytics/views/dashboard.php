<?php
// Vue : Dashboard du plugin.
// Le rendu des donnees est entierement cote JS (voir assets/js/dashboard.js).
// Cette vue ne fournit que la structure et les emplacements (canvas, tbody)
// que le script viendra remplir au chargement.

if (!defined('ABSPATH')) { exit; }

$reglages = get_option(\Finkashi\Plugin\Installation::OPTION_REGLAGES, []);
$configure = !empty($reglages['url_service']) && !empty($reglages['cle_api']);
?>
<div class="wrap finkashi-wrap">
    <h1 class="wp-heading-inline">Finkashi Analytics</h1>
    <p class="finkashi-subtitle">Statistiques de frequentation du site, sans cookie ni traceur publicitaire.</p>

    <?php if (!$configure): ?>
        <div class="notice notice-warning inline">
            <p>
                <strong>Configuration requise.</strong>
                Renseignez l'URL du service et la cle d'API avant de consulter les statistiques.
                <a href="<?php echo esc_url(admin_url('admin.php?page=finkashi-reglages')); ?>">Aller aux reglages</a>
            </p>
        </div>
    <?php else: ?>

        <div class="finkashi-toolbar">
            <label for="finkashi-periode">Periode :</label>
            <select id="finkashi-periode" class="finkashi-select">
                <option value="7">7 derniers jours</option>
                <option value="30" selected>30 derniers jours</option>
                <option value="90">90 derniers jours</option>
            </select>
        </div>

        <div id="finkashi-erreur" class="notice notice-error inline" style="display:none;" role="alert"></div>

        <div id="finkashi-dashboard-zone">

            <div class="finkashi-grid">
                <div class="finkashi-card">
                    <div class="finkashi-stat-label">Visiteurs uniques</div>
                    <div class="finkashi-stat-value" id="stat-visiteurs">-</div>
                </div>
                <div class="finkashi-card">
                    <div class="finkashi-stat-label">Pages vues</div>
                    <div class="finkashi-stat-value" id="stat-pages-vues">-</div>
                </div>
                <div class="finkashi-card">
                    <div class="finkashi-stat-label">Pages / visite</div>
                    <div class="finkashi-stat-value" id="stat-pages-par-visite">-</div>
                </div>
                <div class="finkashi-card">
                    <div class="finkashi-stat-label">Pays touches</div>
                    <div class="finkashi-stat-value" id="stat-pays">-</div>
                </div>
            </div>

            <div class="finkashi-card-full">
                <h2 class="finkashi-card-title">Trafic quotidien</h2>
                <div class="finkashi-canvas-wrap">
                    <canvas id="graphe-trafic"></canvas>
                </div>
            </div>

            <div class="finkashi-two-cols">
                <div class="finkashi-card-full">
                    <h2 class="finkashi-card-title">Origine du trafic</h2>
                    <div class="finkashi-canvas-wrap canvas-donut">
                        <canvas id="graphe-canaux"></canvas>
                    </div>
                </div>
                <div class="finkashi-card-full">
                    <h2 class="finkashi-card-title">Pages les plus consultees</h2>
                    <table class="wp-list-table widefat striped finkashi-table">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th class="num">Visiteurs</th>
                                <th class="num">Vues</th>
                            </tr>
                        </thead>
                        <tbody id="tableau-pages-corps">
                            <tr><td colspan="3" class="finkashi-vide">Chargement...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="finkashi-two-cols">
                <div class="finkashi-card-full">
                    <h2 class="finkashi-card-title">Top sources de trafic</h2>
                    <div class="finkashi-canvas-wrap">
                        <canvas id="graphe-sources"></canvas>
                    </div>
                </div>
                <div class="finkashi-card-full">
                    <h2 class="finkashi-card-title">Repartition geographique</h2>
                    <table class="wp-list-table widefat striped finkashi-table">
                        <thead>
                            <tr>
                                <th>Pays</th>
                                <th class="num">Visiteurs</th>
                                <th class="num">Part</th>
                            </tr>
                        </thead>
                        <tbody id="tableau-pays-corps">
                            <tr><td colspan="3" class="finkashi-vide">Chargement...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    <?php endif; ?>
</div>
