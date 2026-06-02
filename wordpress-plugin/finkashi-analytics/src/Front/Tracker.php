<?php

declare(strict_types=1);

namespace Finkashi\Plugin\Front;

use Finkashi\Plugin\Installation;

/**
 * Injecte le script de mesure d'audience dans les pages publiques.
 *
 * Le script est volontairement minimaliste :
 *  - charge en footer pour ne pas ralentir l'affichage,
 *  - envoie un POST asynchrone vers /collect,
 *  - encapsule l'appel dans un try/catch global : si l'API est
 *    indisponible, le visiteur ne voit rien, le site continue.
 *
 * Les decisions d'exclusion (tracker desactive, admin connecte, page
 * dans la liste d'exclusion) sont prises ici, cote PHP : si l'on ne
 * doit pas mesurer, le script n'est tout simplement pas injecte.
 */
final class Tracker
{
    public function enregistrer(): void
    {
        add_action('wp_footer', [$this, 'injecterTracker']);
    }

    public function injecterTracker(): void
    {
        $reglages = get_option(Installation::OPTION_REGLAGES, []);

        if (!$this->doitInjecter($reglages)) {
            return;
        }

        // Le tracker s'execute dans le navigateur du visiteur :
        // on utilise donc l'URL publique du service, pas l'URL
        // interne reservee aux appels server-to-server.
        $urlBase = !empty($reglages['url_publique'])
            ? (string) $reglages['url_publique']
            : (string) $reglages['url_service'];
        $urlCible = rtrim($urlBase, '/') . '/collect';
        $config = [
            'url'     => esc_url_raw($urlCible),
            'domaine' => (string) ($reglages['domaine_site'] ?? ''),
        ];
        ?>
<script>
(function () {
    'use strict';
    try {
        var cfg = <?php echo wp_json_encode($config); ?>;
        var donnees = {
            chemin:   window.location.pathname || '/',
            titre:    document.title || null,
            referent: document.referrer || null,
        };
        if (typeof fetch === 'function') {
            fetch(cfg.url, {
                method:      'POST',
                headers:     { 'Content-Type': 'application/json' },
                body:        JSON.stringify(donnees),
                credentials: 'omit',
                keepalive:   true,
            }).catch(function () { /* silencieux par design */ });
        }
    } catch (e) {
        // Aucune erreur ne doit perturber l'experience visiteur.
    }
})();
</script>
        <?php
    }

    private function doitInjecter(array $reglages): bool
    {
        if (empty($reglages['tracker_actif'])) {
            return false;
        }
        if (empty($reglages['url_service'])) {
            return false;
        }
        if (is_admin() || is_feed() || is_robots() || is_preview()) {
            return false;
        }
        if (!empty($reglages['exclure_admins']) && current_user_can('manage_options')) {
            return false;
        }
        if (!empty($reglages['pages_exclues'])) {
            $cheminCourant = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $lignes = preg_split('/\r\n|\r|\n/', (string) $reglages['pages_exclues']) ?: [];
            foreach ($lignes as $prefixe) {
                $prefixe = trim($prefixe);
                if ($prefixe !== '' && str_starts_with($cheminCourant, $prefixe)) {
                    return false;
                }
            }
        }
        return true;
    }
}
