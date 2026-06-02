<?php

declare(strict_types=1);

namespace Finkashi\Plugin;

use Finkashi\Plugin\Admin\MenuAdmin;
use Finkashi\Plugin\Admin\ProxyApi;
use Finkashi\Plugin\Admin\Reglages;
use Finkashi\Plugin\Admin\TestConnexion;
use Finkashi\Plugin\Front\Tracker;

/**
 * Classe principale du plugin.
 *
 * Joue le role de "chef d'orchestre" : elle ne fait pas elle-meme le
 * travail, elle enregistre les composants qui s'en chargeront.
 */
final class Plugin
{
    public function demarrer(): void
    {
        if (is_admin()) {
            (new MenuAdmin())->enregistrer();
            (new Reglages())->enregistrer();
            (new TestConnexion())->enregistrer();
            (new ProxyApi())->enregistrer();
        }

        (new Tracker())->enregistrer();
    }
}
