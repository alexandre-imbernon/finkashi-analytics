<?php

declare(strict_types=1);

namespace Finkashi\Plugin;

/**
 * Gere les operations d'installation : activation et desactivation
 * du plugin. Ces operations sont appelees une seule fois par
 * WordPress aux moments correspondants.
 */
final class Installation
{
    /** Nom unique de l'option WordPress qui stocke nos reglages. */
    public const OPTION_REGLAGES = 'finkashi_analytics_reglages';

    /**
     * Valeurs par defaut des reglages, posees a l'activation si
     * elles n'existent pas deja. Cela evite que le plugin demarre
     * dans un etat indefini.
     */
    public static function reglagesParDefaut(): array
    {
        return [
            'url_service'       => '',
            'url_publique'      => '',
            'cle_api'           => '',
            'domaine_site'      => '',
            'tracker_actif'     => true,
            'exclure_admins'    => true,
            'pages_exclues'     => '',
        ];
    }

    public static function activer(): void
    {
        // add_option ne fait rien si l'option existe deja : pas de
        // risque d'ecraser une configuration existante en cas de
        // reactivation.
        add_option(self::OPTION_REGLAGES, self::reglagesParDefaut());
    }

    public static function desactiver(): void
    {
        // On ne supprime pas les reglages : si l'admin reactive le
        // plugin plus tard, il retrouve sa configuration. La
        // suppression complete passerait par un hook de
        // desinstallation (uninstall.php), volontairement absent ici.
    }
}
