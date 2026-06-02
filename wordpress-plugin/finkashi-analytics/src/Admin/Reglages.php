<?php

declare(strict_types=1);

namespace Finkashi\Plugin\Admin;

use Finkashi\Plugin\Installation;

/**
 * Declare les reglages du plugin aupres de WordPress via la Settings
 * API, et fournit la fonction de validation/assainissement.
 *
 * La Settings API gere pour nous :
 *  - la generation du nonce de securite (parade CSRF) ;
 *  - la soumission du formulaire ;
 *  - la redirection apres sauvegarde ;
 *  - l'affichage du message de succes.
 *
 * On ne s'occupe que de la validation metier des donnees entrantes.
 */
final class Reglages
{
    public const GROUPE_OPTION = 'finkashi_analytics_groupe';

    public function enregistrer(): void
    {
        add_action('admin_init', [$this, 'declarer']);
    }

    /**
     * Declare l'option, ses sections et ses champs. Appele par
     * WordPress au debut de chaque requete d'admin.
     */
    public function declarer(): void
    {
        register_setting(
            option_group: self::GROUPE_OPTION,
            option_name:  Installation::OPTION_REGLAGES,
            args:         [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'assainir'],
                'default'           => Installation::reglagesParDefaut(),
            ],
        );

        // --- Section : Connexion au service ----------------------------
        add_settings_section(
            id:       'finkashi_section_connexion',
            title:    'Connexion au service',
            callback: static function (): void {
                echo '<p>Configuration de la communication avec le back-end Finkashi Analytics.</p>';
            },
            page:     'finkashi-reglages',
        );

        $this->ajouterChamp('url_service',  'URL du service (interne)', 'champUrlService',  'finkashi_section_connexion');
        $this->ajouterChamp('url_publique', 'URL du service (publique)', 'champUrlPublique', 'finkashi_section_connexion');
        $this->ajouterChamp('cle_api',      'Cle d\'API',                'champCleApi',      'finkashi_section_connexion');
        $this->ajouterChamp('domaine_site', 'Domaine du site',           'champDomaineSite', 'finkashi_section_connexion');

        // --- Section : Suivi des visites -------------------------------
        add_settings_section(
            id:       'finkashi_section_suivi',
            title:    'Suivi des visites',
            callback: static function (): void {
                echo '<p>Controle de l\'injection du script de mesure dans les pages publiques.</p>';
            },
            page:     'finkashi-reglages',
        );

        $this->ajouterChamp('tracker_actif',  'Activation',            'champTrackerActif',  'finkashi_section_suivi');
        $this->ajouterChamp('exclure_admins', 'Exclure les admins',    'champExclureAdmins', 'finkashi_section_suivi');
        $this->ajouterChamp('pages_exclues',  'Pages a exclure',       'champPagesExclues',  'finkashi_section_suivi');
    }

    /**
     * Petit raccourci pour eviter de repeter add_settings_field
     * avec ses six parametres.
     */
    private function ajouterChamp(string $cle, string $label, string $methode, string $section): void
    {
        add_settings_field(
            id:       'finkashi_champ_' . $cle,
            title:    $label,
            callback: [$this, $methode],
            page:     'finkashi-reglages',
            section:  $section,
            args:     ['label_for' => 'finkashi_' . $cle],
        );
    }

    // -----------------------------------------------------------------
    // Rendu des champs : un par methode, pour rester lisible.
    // -----------------------------------------------------------------

    public function champUrlService(): void
    {
        $valeur = $this->valeur('url_service');
        $name = $this->name('url_service');
        echo '<input type="url" id="finkashi_url_service" name="' . esc_attr($name) . '" '
           . 'value="' . esc_attr($valeur) . '" class="regular-text" placeholder="https://analytics.exemple.fr">';
        echo '<p class="description">URL utilisee par WordPress pour appeler l\'API en server-to-server '
           . '(test de connexion, dashboard). En production, c\'est la meme que l\'URL publique. '
           . 'En dev Docker, ce sera un nom de service interne (ex. http://php).</p>';
    }

    public function champUrlPublique(): void
    {
        $valeur = $this->valeur('url_publique');
        $name = $this->name('url_publique');
        echo '<input type="url" id="finkashi_url_publique" name="' . esc_attr($name) . '" '
           . 'value="' . esc_attr($valeur) . '" class="regular-text" placeholder="https://analytics.exemple.fr">';
        echo '<p class="description">URL utilisee par le tracker JavaScript depuis le navigateur du visiteur. '
           . 'En production, identique a l\'URL interne. En dev Docker, c\'est l\'URL exposee sur l\'hote '
           . '(ex. http://localhost:8080).</p>';
    }

    public function champCleApi(): void
    {
        $valeur = $this->valeur('cle_api');
        $name = $this->name('cle_api');
        // Type "password" : le navigateur masque la valeur. La cle
        // reste lisible cote serveur (pas de chiffrement, juste de
        // l'affichage discret).
        echo '<input type="password" id="finkashi_cle_api" name="' . esc_attr($name) . '" '
           . 'value="' . esc_attr($valeur) . '" class="regular-text" autocomplete="off">';
        echo '<p class="description">Cle secrete partagee avec le service (header Authorization: Bearer).</p>';
    }

    public function champDomaineSite(): void
    {
        $valeur = $this->valeur('domaine_site');
        $name = $this->name('domaine_site');
        echo '<input type="text" id="finkashi_domaine_site" name="' . esc_attr($name) . '" '
           . 'value="' . esc_attr($valeur) . '" class="regular-text" placeholder="exemple.fr">';
        echo '<p class="description">Domaine surveille, sans le protocole.</p>';
    }

    public function champTrackerActif(): void
    {
        $coche = (bool) $this->valeur('tracker_actif');
        $name = $this->name('tracker_actif');
        echo '<label><input type="checkbox" id="finkashi_tracker_actif" name="' . esc_attr($name) . '" '
           . 'value="1" ' . checked($coche, true, false) . '> '
           . 'Injecter le script de mesure dans les pages publiques</label>';
        echo '<p class="description">Decochez pour suspendre la collecte sans desinstaller le plugin.</p>';
    }

    public function champExclureAdmins(): void
    {
        $coche = (bool) $this->valeur('exclure_admins');
        $name = $this->name('exclure_admins');
        echo '<label><input type="checkbox" id="finkashi_exclure_admins" name="' . esc_attr($name) . '" '
           . 'value="1" ' . checked($coche, true, false) . '> '
           . 'Ne pas mesurer les visites des administrateurs connectes</label>';
    }

    public function champPagesExclues(): void
    {
        $valeur = $this->valeur('pages_exclues');
        $name = $this->name('pages_exclues');
        echo '<textarea id="finkashi_pages_exclues" name="' . esc_attr($name) . '" '
           . 'rows="3" class="large-text" placeholder="/admin/&#10;/preview/">'
           . esc_textarea($valeur) . '</textarea>';
        echo '<p class="description">Un prefixe d\'URL par ligne. Les pages dont le chemin commence par '
           . 'l\'un de ces prefixes ne sont pas mesurees.</p>';
    }

    // -----------------------------------------------------------------
    // Validation et assainissement
    // -----------------------------------------------------------------

    /**
     * Appele par WordPress avec les donnees brutes du formulaire.
     * Doit retourner les donnees validees a stocker.
     *
     * Cette methode est la barriere de securite : aucune donnee
     * exterieure ne doit etre stockee sans passer ici.
     *
     * @param array<string,mixed>|null $brut
     * @return array<string,mixed>
     */
    public function assainir(mixed $brut): array
    {
        $brut = is_array($brut) ? $brut : [];
        $existant = get_option(Installation::OPTION_REGLAGES, Installation::reglagesParDefaut());

        $propre = $existant;

        // URL du service : doit etre une URL valide ou vide.
        if (isset($brut['url_service'])) {
            $url = trim((string) $brut['url_service']);
            if ($url === '') {
                $propre['url_service'] = '';
            } elseif (filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $propre['url_service'] = rtrim($url, '/');
            } else {
                add_settings_error(
                    Installation::OPTION_REGLAGES,
                    'url_invalide',
                    'L\'URL du service (interne) est invalide. Elle doit ressembler a https://exemple.fr.',
                );
            }
        }

        // URL publique : meme regle.
        if (isset($brut['url_publique'])) {
            $url = trim((string) $brut['url_publique']);
            if ($url === '') {
                $propre['url_publique'] = '';
            } elseif (filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $propre['url_publique'] = rtrim($url, '/');
            } else {
                add_settings_error(
                    Installation::OPTION_REGLAGES,
                    'url_publique_invalide',
                    'L\'URL publique est invalide. Elle doit ressembler a https://exemple.fr.',
                );
            }
        }

        // Cle d'API : champ texte simple, pas de validation de format
        // (on ne connait pas la forme exacte choisie par le back-end).
        if (isset($brut['cle_api'])) {
            $propre['cle_api'] = sanitize_text_field((string) $brut['cle_api']);
        }

        // Domaine : pas de protocole, pas d'espaces.
        if (isset($brut['domaine_site'])) {
            $domaine = strtolower(trim((string) $brut['domaine_site']));
            $domaine = preg_replace('#^https?://#', '', $domaine);
            $domaine = preg_replace('#/.*$#', '', $domaine);
            if ($domaine === '' || preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domaine) === 1) {
                $propre['domaine_site'] = $domaine;
            } else {
                add_settings_error(
                    Installation::OPTION_REGLAGES,
                    'domaine_invalide',
                    'Le domaine semble mal forme (attendu : exemple.fr).',
                );
            }
        }

        // Cases a cocher : presentes dans $brut si cochees, absentes sinon.
        $propre['tracker_actif']  = isset($brut['tracker_actif']);
        $propre['exclure_admins'] = isset($brut['exclure_admins']);

        // Pages exclues : on accepte le texte brut, en nettoyant
        // chaque ligne.
        if (isset($brut['pages_exclues'])) {
            $lignes = preg_split('/\r\n|\r|\n/', (string) $brut['pages_exclues']) ?: [];
            $lignesPropres = [];
            foreach ($lignes as $ligne) {
                $ligne = trim($ligne);
                if ($ligne !== '') {
                    $lignesPropres[] = sanitize_text_field($ligne);
                }
            }
            $propre['pages_exclues'] = implode("\n", $lignesPropres);
        }

        return $propre;
    }

    // -----------------------------------------------------------------
    // Aides internes
    // -----------------------------------------------------------------

    private function valeur(string $cle): mixed
    {
        $options = get_option(Installation::OPTION_REGLAGES, Installation::reglagesParDefaut());
        return $options[$cle] ?? '';
    }

    /**
     * Genere le name HTML d'un champ, sous la forme
     * "finkashi_analytics_reglages[cle]". La Settings API agrege
     * automatiquement tous les champs portant ce prefixe en un seul
     * tableau, transmis a notre fonction d'assainissement.
     */
    private function name(string $cle): string
    {
        return Installation::OPTION_REGLAGES . '[' . $cle . ']';
    }
}
