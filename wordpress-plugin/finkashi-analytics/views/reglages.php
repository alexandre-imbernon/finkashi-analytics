<?php
// Vue : Reglages du plugin.
// Le formulaire complet sera cable a l'API Settings de WordPress
// a l'etape suivante. Pour l'instant, on affiche la structure.

if (!defined('ABSPATH')) { exit; }

$reglages = get_option(\Finkashi\Plugin\Installation::OPTION_REGLAGES, []);
?>
<div class="wrap finkashi-wrap">
    <h1 class="wp-heading-inline">Reglages Finkashi Analytics</h1>
    <p class="finkashi-subtitle">Configuration de la connexion au service de mesure d'audience.</p>

    <div class="notice notice-info inline">
        <p>
            <strong>Etape suivante.</strong> Le formulaire ci-dessous affiche la structure validee.
            La sauvegarde reelle des reglages sera ajoutee a la prochaine etape.
        </p>
    </div>

    <h2>Connexion au service</h2>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="url_service">URL du service</label></th>
            <td>
                <input type="text" id="url_service" class="regular-text"
                       value="<?php echo esc_attr($reglages['url_service'] ?? ''); ?>"
                       placeholder="https://analytics.finkashi.fr" disabled>
                <p class="description">URL publique de votre back-end Finkashi Analytics.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="cle_api">Cle d'API</label></th>
            <td>
                <input type="password" id="cle_api" class="regular-text"
                       value="<?php echo esc_attr($reglages['cle_api'] ?? ''); ?>" disabled>
                <p class="description">Cle secrete partagee avec le service.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="domaine_site">Domaine du site</label></th>
            <td>
                <input type="text" id="domaine_site" class="regular-text"
                       value="<?php echo esc_attr($reglages['domaine_site'] ?? ''); ?>"
                       placeholder="finkashi.fr" disabled>
            </td>
        </tr>
    </table>
</div>
