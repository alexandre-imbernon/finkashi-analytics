<?php

declare(strict_types=1);

namespace Finkashi\Plugin;

use Finkashi\Plugin\Admin\MenuAdmin;
use Finkashi\Plugin\Public\Tracker;

/**
 * Classe principale du plugin.
 *
 * Joue le role de "chef d'orchestre" : elle ne fait pas elle-meme le
 * travail, elle enregistre les composants qui s'en chargeront.
 * Cette separation rend le plugin lisible et chaque responsabilite
 * est isolee dans sa propre classe.
 */
final class Plugin
{
    /**
     * Demarre le plugin : enregistre les composants aupres de
     * WordPress.
     */
    public function demarrer(): void
    {
        // Si l'utilisateur est dans l'admin WordPress, on charge la
        // partie administration (menus, ecrans, formulaires).
        if (is_admin()) {
            (new MenuAdmin())->enregistrer();
        }

        // Sur le site public, on charge le tracker qui injecte le
        // script de collecte dans les pages.
        (new Tracker())->enregistrer();
    }
}
