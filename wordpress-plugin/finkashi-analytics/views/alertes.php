<?php
// Vue : Alertes du plugin.
// Le rendu des donnees est entierement cote JS (alertes.js).
// Cette vue ne fournit que la structure : tableaux vides et modale
// cachee, que le script peuplera apres ses appels au proxy.

if (!defined('ABSPATH')) { exit; }

$reglages = get_option(\Finkashi\Plugin\Installation::OPTION_REGLAGES, []);
$configure = !empty($reglages['url_service']) && !empty($reglages['cle_api']);
?>
<div class="wrap finkashi-wrap">
    <h1 class="wp-heading-inline">Alertes</h1>
    <p class="finkashi-subtitle">Soyez prevenu lorsque le trafic franchit un seuil que vous avez defini.</p>

    <?php if (!$configure): ?>
        <div class="notice notice-warning inline">
            <p>
                <strong>Configuration requise.</strong>
                Renseignez l'URL du service et la cle d'API avant de gerer les alertes.
                <a href="<?php echo esc_url(admin_url('admin.php?page=finkashi-reglages')); ?>">Aller aux reglages</a>
            </p>
        </div>
    <?php else: ?>

        <p>
            <button type="button" class="button button-primary" id="finkashi-nouvelle-regle">
                + Nouvelle regle
            </button>
        </p>

        <div id="finkashi-alertes-erreur" class="notice notice-error inline" style="display:none;" role="alert"></div>

        <div class="finkashi-card-full">
            <h2 class="finkashi-card-title">Regles configurees</h2>
            <table class="wp-list-table widefat striped finkashi-table">
                <thead>
                    <tr>
                        <th>Etat</th>
                        <th>Metrique</th>
                        <th>Condition</th>
                        <th class="num">Seuil</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableau-regles-corps">
                    <tr><td colspan="5" class="finkashi-vide">Chargement...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="finkashi-card-full">
            <h2 class="finkashi-card-title">Historique des declenchements (30 derniers jours)</h2>
            <table class="wp-list-table widefat striped finkashi-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Regle</th>
                        <th class="num">Valeur observee</th>
                        <th>Notification</th>
                    </tr>
                </thead>
                <tbody id="tableau-historique-corps">
                    <tr><td colspan="4" class="finkashi-vide">Chargement...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Modale de creation / edition : cachee par defaut -->
        <div id="finkashi-modale" class="finkashi-modale" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="finkashi-modale-titre">
            <div class="finkashi-modale-fond"></div>
            <div class="finkashi-modale-corps">
                <h2 id="finkashi-modale-titre">Nouvelle regle d'alerte</h2>
                <input type="hidden" id="finkashi-regle-id" value="">
                <table class="form-table">
                    <tr>
                        <th><label for="finkashi-regle-metrique">Metrique</label></th>
                        <td>
                            <select id="finkashi-regle-metrique" class="regular-text">
                                <option value="visiteurs_jour">Visiteurs / jour</option>
                                <option value="pages_vues_jour">Pages vues / jour</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="finkashi-regle-operateur">Condition</label></th>
                        <td>
                            <select id="finkashi-regle-operateur" class="regular-text">
                                <option value="inferieur">Inferieur a</option>
                                <option value="superieur">Superieur a</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="finkashi-regle-seuil">Seuil</label></th>
                        <td>
                            <input type="number" id="finkashi-regle-seuil" class="regular-text" min="0" value="10">
                        </td>
                    </tr>
                    <tr>
                        <th>Etat</th>
                        <td>
                            <label>
                                <input type="checkbox" id="finkashi-regle-active" checked>
                                Activer cette regle immediatement
                            </label>
                        </td>
                    </tr>
                </table>
                <div id="finkashi-modale-erreur" class="notice notice-error inline" style="display:none;" role="alert"></div>
                <p class="finkashi-modale-actions">
                    <button type="button" class="button button-primary" id="finkashi-modale-enregistrer">Enregistrer</button>
                    <button type="button" class="button" id="finkashi-modale-annuler">Annuler</button>
                </p>
            </div>
        </div>

    <?php endif; ?>
</div>
