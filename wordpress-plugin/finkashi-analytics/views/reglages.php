<?php
// Vue : Reglages du plugin.
// Le rendu des champs et la soumission sont entierement geres par
// la Settings API : settings_fields() injecte le nonce et l'action,
// do_settings_sections() boucle sur les sections et leurs champs.
// Le JavaScript du bouton "Tester la connexion" vit dans
// assets/js/reglages.js, charge proprement via wp_enqueue_script.

if (!defined('ABSPATH')) { exit; }
?>
<div class="wrap finkashi-wrap">
    <h1 class="wp-heading-inline">Reglages Finkashi Analytics</h1>
    <p class="finkashi-subtitle">Configuration de la connexion au service de mesure d'audience.</p>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php
        settings_fields(\Finkashi\Plugin\Admin\Reglages::GROUPE_OPTION);
        do_settings_sections('finkashi-reglages');
        submit_button('Enregistrer les modifications');
        ?>
    </form>

    <hr>

    <h2>Verification</h2>
    <p>
        <button type="button" class="button button-secondary" id="finkashi-tester-connexion">
            Tester la connexion
        </button>
        <span id="finkashi-test-resultat" class="finkashi-test-resultat" role="status"></span>
    </p>
    <p class="description">
        Envoie un appel reel au service pour verifier que l'URL et la cle saisies fonctionnent.
        Pensez a enregistrer les modifications avant de tester.
    </p>
</div>
