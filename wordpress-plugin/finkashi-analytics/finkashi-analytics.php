<?php

/**
 * Plugin Name:       Finkashi Analytics
 * Plugin URI:        https://finkashi.fr
 * Description:       Mesure d'audience auto-hebergee, sans cookie, integree au tableau de bord WordPress.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Finkashi
 * License:           MIT
 * Text Domain:       finkashi-analytics
 */

declare(strict_types=1);

// Securite : empeche l'execution directe du fichier hors WordPress.
if (!defined('ABSPATH')) {
    exit;
}

// Constantes pratiques utilisees dans tout le plugin.
define('FINKASHI_PLUGIN_FILE',    __FILE__);
define('FINKASHI_PLUGIN_DIR',     plugin_dir_path(__FILE__));
define('FINKASHI_PLUGIN_URL',     plugin_dir_url(__FILE__));
define('FINKASHI_PLUGIN_VERSION', '0.1.0');

// Autoloader simple PSR-4 propre au plugin, sans dependre de Composer.
// La convention : la classe Finkashi\Plugin\Foo\Bar est dans
// src/Foo/Bar.php.
spl_autoload_register(static function (string $classe): void {
    $prefixe = 'Finkashi\\Plugin\\';
    if (!str_starts_with($classe, $prefixe)) {
        return;
    }
    $chemin = FINKASHI_PLUGIN_DIR . 'src/'
        . str_replace('\\', '/', substr($classe, strlen($prefixe)))
        . '.php';
    if (is_file($chemin)) {
        require_once $chemin;
    }
});

// Demarrage du plugin : la classe Plugin orchestre toute la suite.
add_action('plugins_loaded', static function (): void {
    (new \Finkashi\Plugin\Plugin())->demarrer();
});

// Hook d'activation : appele une seule fois, quand l'admin active
// le plugin. On y pose les options par defaut.
register_activation_hook(__FILE__, static function (): void {
    \Finkashi\Plugin\Installation::activer();
});

// Hook de desactivation : appele quand l'admin desactive le plugin.
// On ne supprime rien ici (les options peuvent servir si l'admin
// reactive le plugin plus tard).
register_deactivation_hook(__FILE__, static function (): void {
    \Finkashi\Plugin\Installation::desactiver();
});