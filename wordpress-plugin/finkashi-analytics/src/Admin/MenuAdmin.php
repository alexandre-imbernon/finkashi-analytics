<?php

declare(strict_types=1);

namespace Finkashi\Plugin\Admin;

/**
 * Enregistre les entrees de menu du plugin dans l'admin WordPress
 * et associe chaque entree a son ecran.
 *
 * La structure suit le schema d'enchainement valide en CP2 :
 *  - Finkashi (menu principal)
 *      - Dashboard (ecran par defaut)
 *      - Alertes
 *      - Reglages
 */
final class MenuAdmin
{
    public function enregistrer(): void
    {
        add_action('admin_menu', [$this, 'declarerMenus']);
        add_action('admin_enqueue_scripts', [$this, 'chargerAssets']);
    }

    /**
     * Appele par WordPress au moment d'assembler le menu d'admin.
     * On y declare le menu principal et ses sous-entrees.
     */
    public function declarerMenus(): void
    {
        // Menu principal. Le 5e parametre (slug) sera le 1er sous-menu
        // par defaut : c'est le Dashboard.
        add_menu_page(
            page_title: 'Finkashi Analytics',
            menu_title: 'Finkashi',
            capability: 'manage_options',
            menu_slug:  'finkashi',
            callback:   [EcranDashboard::class, 'afficher'],
            icon_url:   'dashicons-chart-area',
            position:   30,
        );

        // Le premier sous-menu reprend le slug du menu principal :
        // c'est ce qui permet de renommer son intitule en "Dashboard".
        add_submenu_page(
            parent_slug: 'finkashi',
            page_title:  'Dashboard',
            menu_title:  'Dashboard',
            capability:  'manage_options',
            menu_slug:   'finkashi',
            callback:    [EcranDashboard::class, 'afficher'],
        );

        add_submenu_page(
            parent_slug: 'finkashi',
            page_title:  'Alertes',
            menu_title:  'Alertes',
            capability:  'manage_options',
            menu_slug:   'finkashi-alertes',
            callback:    [EcranAlertes::class, 'afficher'],
        );

        add_submenu_page(
            parent_slug: 'finkashi',
            page_title:  'Reglages',
            menu_title:  'Reglages',
            capability:  'manage_options',
            menu_slug:   'finkashi-reglages',
            callback:    [EcranReglages::class, 'afficher'],
        );
    }

    /**
     * Charge les feuilles de style et scripts uniquement sur les
     * pages du plugin (eviter de polluer le reste de l'admin).
     */
    public function chargerAssets(string $hook): void
    {
        // Les hooks generes par add_menu_page suivent la convention
        // "toplevel_page_<slug>" pour le menu principal et
        // "<slug-parent>_page_<slug-enfant>" pour les sous-menus.
        $hooksPlugin = [
            'toplevel_page_finkashi',
            'finkashi_page_finkashi-alertes',
            'finkashi_page_finkashi-reglages',
        ];

        if (!in_array($hook, $hooksPlugin, true)) {
            return;
        }

        wp_enqueue_style(
            'finkashi-admin',
            FINKASHI_PLUGIN_URL . 'assets/css/admin.css',
            [],
            FINKASHI_PLUGIN_VERSION,
        );

        // Le JS de la page reglages : un vrai fichier, charge en
        // fin de page (footer), avec donnees serveur injectees via
        // wp_localize_script.
        wp_enqueue_script(
            'finkashi-admin-reglages',
            FINKASHI_PLUGIN_URL . 'assets/js/reglages.js',
            [],
            FINKASHI_PLUGIN_VERSION,
            true, // charger dans le footer, apres le DOM
        );
        wp_localize_script('finkashi-admin-reglages', 'finkashiAdmin', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'actionTest' => TestConnexion::ACTION,
            'nonceTest'  => wp_create_nonce(TestConnexion::ACTION),
        ]);
    }
}
