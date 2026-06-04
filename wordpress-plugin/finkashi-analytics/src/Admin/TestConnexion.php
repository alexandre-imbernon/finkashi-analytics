<?php

declare(strict_types=1);

namespace Finkashi\Plugin\Admin;

use Finkashi\Plugin\Installation;

/**
 * Action AJAX "Tester la connexion".
 *
 * Permet a l'administrateur de verifier, depuis l'ecran de reglages,
 * que les valeurs saisies (URL du service + cle d'API) permettent
 * effectivement de joindre le back-end. Le test envoie une vraie
 * requete au service et rapporte le resultat.
 *
 * Securite :
 *  - hook reserve aux utilisateurs connectes (wp_ajax_*, pas wp_ajax_nopriv_*) ;
 *  - verification de la capability "manage_options" ;
 *  - verification d'un nonce dedie.
 */
final class TestConnexion
{
    public const ACTION = 'finkashi_test_connexion';

    public function enregistrer(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'gerer']);
    }

    public function gerer(): void
    {
        // 1. Capability : seul un admin peut declencher l'action.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissions insuffisantes.'], 403);
        }

        // 2. Nonce : prouve que la requete vient du formulaire genere
        //    par le plugin lui-meme. Stoppe net les attaques CSRF.
        check_ajax_referer(self::ACTION, 'nonce');

        // 3. Lecture des reglages courants.
        $reglages = get_option(Installation::OPTION_REGLAGES, []);
        $url = trim((string) ($reglages['url_service'] ?? ''));
        $cle = trim((string) ($reglages['cle_api'] ?? ''));

        if ($url === '' || $cle === '') {
            wp_send_json_error([
                'message' => 'L\'URL du service et la cle d\'API doivent etre renseignees et enregistrees avant de tester.',
            ]);
        }

        // 4. Appel reel au back-end : un GET sur /stats/trafic avec
        //    un intervalle minimal. C'est l'endpoint le plus simple
        //    qui exige une authentification : s'il repond 200, tout
        //    est bon.
        $aujourdhui = gmdate('Y-m-d');
        $cible = rtrim($url, '/') . '/stats/trafic?depuis=' . $aujourdhui . '&jusque=' . $aujourdhui;

        $reponse = wp_remote_get($cible, [
            'timeout' => 5,
            'headers' => [
                'Authorization' => 'Bearer ' . $cle,
                'X-Api-Key'     => $cle,
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($reponse)) {
            wp_send_json_error([
                'message' => 'Impossible de joindre le service : ' . $reponse->get_error_message(),
            ]);
        }

        $code = (int) wp_remote_retrieve_response_code($reponse);
        $corps = (string) wp_remote_retrieve_body($reponse);

        if ($code === 200) {
            wp_send_json_success([
                'message' => 'Connexion etablie. Le service repond correctement.',
            ]);
        }

        if ($code === 401) {
            wp_send_json_error([
                'message' => 'Le service est joignable mais a refuse la cle d\'API (401). Verifiez la cle.',
            ]);
        }

        wp_send_json_error([
            'message' => "Le service a repondu avec un code inattendu : {$code}.",
            'details' => mb_substr($corps, 0, 200),
        ]);
    }
}
