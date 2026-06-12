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
 *    indisponible, le visiteur ne voit rien, le site continue,
 *  - dedoublonne les visites a l'echelle de la journee : une page
 *    visitee plusieurs fois par la meme personne sur une meme journee
 *    n'est comptee qu'une seule fois (cohérent avec le modèle « 1
 *    visiteur unique = 1 jour » du sel quotidien serveur).
 *
 * Les decisions d'exclusion (tracker desactive, admin connecte, page
 * dans la liste d'exclusion) sont prises cote PHP : si l'on ne doit
 * pas mesurer, le script n'est tout simplement pas injecte.
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
        var STORAGE_KEY = 'finkashi_seen';

        var chemin = window.location.pathname || '/';
        var aujourdhui = (new Date()).toISOString().slice(0, 10);

        // Lecture du registre local des pages deja vues aujourd'hui.
        // localStorage peut etre indisponible (mode prive strict,
        // quotas, navigateurs anciens). Dans ce cas, on bascule sur
        // un envoi systematique (mieux vaut surcompter que ne rien
        // mesurer).
        var registre = { date: aujourdhui, chemins: [] };
        var stockageDispo = false;
        try {
            window.localStorage.setItem('_finkashi_test', '1');
            window.localStorage.removeItem('_finkashi_test');
            stockageDispo = true;
        } catch (e) {
            stockageDispo = false;
        }

        if (stockageDispo) {
            try {
                var brut = window.localStorage.getItem(STORAGE_KEY);
                if (brut) {
                    var decode = JSON.parse(brut);
                    // On ne garde le registre que s'il correspond au
                    // jour courant. Sinon on repart de zero : c'est
                    // un nouveau jour, donc un nouveau "visiteur
                    // unique" du point de vue de notre modele.
                    if (decode && decode.date === aujourdhui && Array.isArray(decode.chemins)) {
                        registre = decode;
                    }
                }
            } catch (e) {
                // JSON corrompu : on repart de zero.
            }

            // Page deja vue aujourd'hui par ce visiteur : on n'envoie
            // pas d'event. C'est exactement le comportement attendu.
            if (registre.chemins.indexOf(chemin) !== -1) {
                return;
            }
        }

        // Envoi de l'event au backend.
        var donnees = {
            chemin:   chemin,
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
            }).then(function (reponse) {
                // On n'enregistre la page comme "vue" QUE si la
                // collecte a abouti cote serveur. Sinon on retentera
                // sur la prochaine vue de cette page.
                if (stockageDispo && reponse && reponse.ok) {
                    registre.chemins.push(chemin);
                    try {
                        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(registre));
                    } catch (e) {
                        // Quota depasse ou ecriture refusee : tant
                        // pis, on continuera a renvoyer la prochaine
                        // fois (legere surcomptabilisation).
                    }
                }
            }).catch(function () {
                // API injoignable : silencieux par design, le site
                // continue de fonctionner normalement.
            });
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
