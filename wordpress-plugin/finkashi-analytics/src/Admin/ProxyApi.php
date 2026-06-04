<?php

declare(strict_types=1);

namespace Finkashi\Plugin\Admin;

use Finkashi\Plugin\Installation;

/**
 * Proxy AJAX vers l'API Finkashi Analytics.
 *
 * Le navigateur appelle cette action AJAX WordPress, qui relaie la
 * requete vers l'API en y ajoutant la cle d'authentification.
 *
 * Securite :
 *  - hook reserve aux utilisateurs connectes ;
 *  - capability "manage_options" exigee ;
 *  - nonce verifie a chaque appel ;
 *  - whitelist d'endpoints : seuls les endpoints listes ici peuvent
 *    etre appeles, ce qui empeche un acteur malveillant d'utiliser
 *    le proxy comme passerelle generique.
 *  - whitelist de methodes HTTP par endpoint.
 */
final class ProxyApi
{
    public const ACTION = 'finkashi_proxy_api';

    /**
     * Liste des endpoints autorises. Chaque cle est le nom court
     * utilise cote JS, chaque valeur est une description :
     *  - chemin : chemin sur l'API (peut contenir :id),
     *  - methodes : liste des verbes HTTP autorises.
     */
    private const ENDPOINTS_AUTORISES = [
        // Lecture des stats (GET).
        'trafic'             => ['chemin' => '/stats/trafic',          'methodes' => ['GET']],
        'pages'              => ['chemin' => '/stats/pages',           'methodes' => ['GET']],
        'canaux'             => ['chemin' => '/stats/canaux',          'methodes' => ['GET']],
        'sources'            => ['chemin' => '/stats/sources',         'methodes' => ['GET']],
        'pays'               => ['chemin' => '/stats/pays',            'methodes' => ['GET']],
        // Gestion des alertes (GET, POST, PUT, DELETE).
        'alertes_regles'     => ['chemin' => '/alertes/regles',        'methodes' => ['GET', 'POST']],
        'alertes_regle'      => ['chemin' => '/alertes/regles/:id',    'methodes' => ['PUT', 'DELETE']],
        'alertes_historique' => ['chemin' => '/alertes/historique',    'methodes' => ['GET']],
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

        // 1. Validation de l'endpoint demande.
        $endpoint = isset($_POST['endpoint']) ? sanitize_key((string) $_POST['endpoint']) : '';
        if (!isset(self::ENDPOINTS_AUTORISES[$endpoint])) {
            wp_send_json_error(['message' => 'Endpoint inconnu.'], 400);
        }
        $definition = self::ENDPOINTS_AUTORISES[$endpoint];

        // 2. Validation de la methode HTTP.
        $methode = strtoupper(sanitize_key((string) ($_POST['methode'] ?? 'GET')));
        if (!in_array($methode, $definition['methodes'], true)) {
            wp_send_json_error(['message' => 'Methode HTTP non autorisee pour cet endpoint.'], 400);
        }

        // 3. Lecture des reglages.
        $reglages = get_option(Installation::OPTION_REGLAGES, []);
        $url = trim((string) ($reglages['url_service'] ?? ''));
        $cle = trim((string) ($reglages['cle_api'] ?? ''));
        if ($url === '' || $cle === '') {
            wp_send_json_error([
                'message' => 'Le plugin n\'est pas configure. Renseignez d\'abord l\'URL et la cle d\'API.',
                'code'    => 'NON_CONFIGURE',
            ], 412);
        }

        // 4. Construction de l'URL cible, avec substitution de :id si present.
        $chemin = $definition['chemin'];
        if (str_contains($chemin, ':id')) {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            if ($id < 1) {
                wp_send_json_error(['message' => 'Identifiant manquant ou invalide.'], 400);
            }
            $chemin = str_replace(':id', (string) $id, $chemin);
        }
        $cible = rtrim($url, '/') . $chemin;

        // 5. Pour GET : query string. Pour POST/PUT : corps JSON.
        // On envoie la cle de deux facons : "Authorization: Bearer"
        // (standard) et "X-Api-Key" (fallback pour les hebergeurs
        // mutualises qui filtrent Authorization).
        $arguments = [
            'timeout' => 10,
            'method'  => $methode,
            'headers' => [
                'Authorization' => 'Bearer ' . $cle,
                'X-Api-Key'     => $cle,
                'Accept'        => 'application/json',
            ],
        ];

        if ($methode === 'GET') {
            $parametresAutorises = ['depuis', 'jusque', 'limite'];
            $query = [];
            foreach ($parametresAutorises as $nom) {
                if (isset($_POST[$nom]) && $_POST[$nom] !== '') {
                    $query[$nom] = sanitize_text_field((string) $_POST[$nom]);
                }
            }
            if ($query !== []) {
                $cible .= '?' . http_build_query($query);
            }
        } elseif (in_array($methode, ['POST', 'PUT'], true)) {
            // Le corps JSON est passe tel quel depuis le navigateur.
            $corps = isset($_POST['corps']) ? (string) wp_unslash($_POST['corps']) : '{}';
            $decode = json_decode($corps, true);
            if (!is_array($decode)) {
                wp_send_json_error(['message' => 'Corps JSON invalide cote client.'], 400);
            }
            $arguments['headers']['Content-Type'] = 'application/json';
            $arguments['body'] = wp_json_encode($decode);
        }

        // 6. Appel reel a l'API.
        $reponse = wp_remote_request($cible, $arguments);

        if (is_wp_error($reponse)) {
            wp_send_json_error([
                'message' => 'Impossible de joindre le service : ' . $reponse->get_error_message(),
            ], 502);
        }

        $code  = (int) wp_remote_retrieve_response_code($reponse);
        $corps = (string) wp_remote_retrieve_body($reponse);

        // 7. 204 : pas de corps, on renvoie un succes vide.
        if ($code === 204) {
            wp_send_json_success(null);
        }

        // 8. Autres 2xx : on transmet le JSON tel quel.
        if ($code >= 200 && $code < 300) {
            $donnees = json_decode($corps, true);
            if (!is_array($donnees)) {
                wp_send_json_error(['message' => 'Reponse non-JSON du service.'], 502);
            }
            wp_send_json_success($donnees);
        }

        // 9. Erreur cote API : on transmet le message et le code HTTP.
        $message = 'Le service a repondu avec un code ' . $code . '.';
        $decodeErreur = json_decode($corps, true);
        if (is_array($decodeErreur) && isset($decodeErreur['erreur'])) {
            $message = (string) $decodeErreur['erreur'];
        }
        wp_send_json_error(['message' => $message, 'http' => $code], 502);
    }
}
