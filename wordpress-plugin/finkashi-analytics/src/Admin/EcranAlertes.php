<?php

declare(strict_types=1);

namespace Finkashi\Plugin\Admin;

final class EcranAlertes
{
    public static function afficher(): void
    {
        require FINKASHI_PLUGIN_DIR . 'views/alertes.php';
    }
}
