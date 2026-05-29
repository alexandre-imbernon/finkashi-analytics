<?php

declare(strict_types=1);

namespace Finkashi\Plugin\Admin;

final class EcranReglages
{
    public static function afficher(): void
    {
        require FINKASHI_PLUGIN_DIR . 'views/reglages.php';
    }
}
