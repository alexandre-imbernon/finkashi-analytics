<?php

declare(strict_types=1);

namespace Finkashi\Plugin\Admin;

use Finkashi\Plugin\Installation;

/**
 * Proxy AJAX vers l'API Finkashi Analytics.
 *
 * Le navigateur appelle cette action AJAX WordPress, qui relaie la
 * requete vers l'API en y ajoutant la cle d'authentification. La
 * cle reste cote serveur, jamais exposee au navigateur.
 *
 * Securite a plusieurs niveaux :
 *  - hook reserve aux utilisateurs connectes (wp_ajax_*) ;
 *  - capability "manage_options" exigee ;
 *  - nonce verifie a chaque appel ;
 *  - whitelist d'endpoints : seuls les endpoints listes ici peuvent
 *    etre appeles, ce qui empeche un acteur malveillant d'utiliser
 *    le proxy comme passerelle generique.
 */
final class ProxyApi
{
    public const ACTION = 'finkashi_proxy_api';

    /**
     * Liste des endpoints autorises. Chaque cle est le nom court
     * utilise cote JS, chaque valeur est le chemin reel sur l'API.
     */
    private const ENDPOINTS_AUTORISES = [
        'trafic'  => '/stats/trafic',
        'pages'   => '/stats/pages',
        'canaux'  => '/stats/canaux',
        'sources' => '/stats/sources',
        'pays'    => '/stats/pays',
    ];

    public function enregistrer(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'gerer']);
    }

    public function gerer(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissions insuffisantes.'], 403);
        }

        check_ajax_referer(self::ACTION, 'nonce');

        // 1. Validation du nom d'endpoint demande.
        $endpoint = isset($_POST['endpoint']) ? sanitize_key((string) $_POST['endpoint']) : '';
        if (!isset(self::ENDPOINTS_AUTORISES[$endpoint])) {
            wp_send_json_error(['message' => 'Endpoint inconnu.'], 400);
        }
        $cheminApi = self::ENDPOINTS_AUTORISES[$endpoint];

        // 2. Lecture des reglages.
        $reglages = get_option(Installation::OPTION_REGLAGES, []);
        $url = trim((string) ($reglages['url_service'] ?? ''));
        $cle = trim((string) ($reglages['cle_api'] ?? ''));

        if ($url === '' || $cle === '') {
            wp_send_json_error([
                'message' => 'Le plugin n\'est pas configure. Renseignez d\'abord l\'URL et la cle d\'API.',
                'code'    => 'NON_CONFIGURE',
            ], 412);
        }

        // 3. Construction de la query string filtree.
        $parametresAutorises = ['depuis', 'jusque', 'limite'];
        $query = [];
        foreach ($parametresAutorises as $nom) {
            if (isset($_POST[$nom]) && $_POST[$nom] !== '') {
                $query[$nom] = sanitize_text_field((string) $_POST[$nom]);
            }
        }
        $cible = rtrim($url, '/') . $cheminApi;
        if ($query !== []) {
            $cible .= '?' . http_build_query($query);
        }

        // 4. Appel reel a l'API avec la cle.
        $reponse = wp_remote_get($cible, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $cle,
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($reponse)) {
            wp_send_json_error([
                'message' => 'Impossible de joindre le service : ' . $reponse->get_error_message(),
            ], 502);
        }

        $code = (int) wp_remote_retrieve_response_code($reponse);
        $corps = (string) wp_remote_retrieve_body($reponse);

        if ($code !== 200) {
            wp_send_json_error([
                'message' => "Le service a repondu avec un code {$code}.",
                'details' => mb_substr($corps, 0, 200),
            ], 502);
        }

        // 5. Renvoi tel quel du JSON de l'API au navigateur. On
        //    decode/reencode pour s'assurer que c'est bien du JSON
        //    valide (et pas du HTML d'erreur).
        $donnees = json_decode($corps, true);
        if (!is_array($donnees)) {
            wp_send_json_error(['message' => 'Reponse non-JSON du service.'], 502);
        }

        wp_send_json_success($donnees);
    }
}
