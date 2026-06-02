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
    private const HOOK_DASHBOARD = 'toplevel_page_finkashi';
    private const HOOK_ALERTES   = 'finkashi_page_finkashi-alertes';
    private const HOOK_REGLAGES  = 'finkashi_page_finkashi-reglages';

    public function enregistrer(): void
    {
        add_action('admin_menu', [$this, 'declarerMenus']);
        add_action('admin_enqueue_scripts', [$this, 'chargerAssets']);
    }

    public function declarerMenus(): void
    {
        add_menu_page(
            page_title: 'Finkashi Analytics',
            menu_title: 'Finkashi',
            capability: 'manage_options',
            menu_slug:  'finkashi',
            callback:   [EcranDashboard::class, 'afficher'],
            icon_url:   'dashicons-chart-area',
            position:   30,
        );

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

    public function chargerAssets(string $hook): void
    {
        $hooksPlugin = [self::HOOK_DASHBOARD, self::HOOK_ALERTES, self::HOOK_REGLAGES];
        if (!in_array($hook, $hooksPlugin, true)) {
            return;
        }

        // CSS commun a tous les ecrans du plugin.
        wp_enqueue_style(
            'finkashi-admin',
            FINKASHI_PLUGIN_URL . 'assets/css/admin.css',
            [],
            FINKASHI_PLUGIN_VERSION,
        );

        // JS specifique a chaque page.
        if ($hook === self::HOOK_REGLAGES) {
            $this->chargerAssetsReglages();
        } elseif ($hook === self::HOOK_DASHBOARD) {
            $this->chargerAssetsDashboard();
        }
    }

    private function chargerAssetsReglages(): void
    {
        wp_enqueue_script(
            'finkashi-admin-reglages',
            FINKASHI_PLUGIN_URL . 'assets/js/reglages.js',
            [],
            FINKASHI_PLUGIN_VERSION,
            true,
        );
        wp_localize_script('finkashi-admin-reglages', 'finkashiAdmin', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'actionTest' => TestConnexion::ACTION,
            'nonceTest'  => wp_create_nonce(TestConnexion::ACTION),
        ]);
    }

    private function chargerAssetsDashboard(): void
    {
        // Chart.js d'abord, puis notre script qui en depend.
        wp_enqueue_script(
            'finkashi-chartjs',
            FINKASHI_PLUGIN_URL . 'assets/js/vendor/chart.umd.min.js',
            [],
            '4.4.7',
            true,
        );
        wp_enqueue_script(
            'finkashi-admin-dashboard',
            FINKASHI_PLUGIN_URL . 'assets/js/dashboard.js',
            ['finkashi-chartjs'],
            FINKASHI_PLUGIN_VERSION,
            true,
        );
        wp_localize_script('finkashi-admin-dashboard', 'finkashiDashboard', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'actionProxy' => ProxyApi::ACTION,
            'nonceProxy'  => wp_create_nonce(ProxyApi::ACTION),
            'urlReglages' => admin_url('admin.php?page=finkashi-reglages'),
        ]);
    }
}
