<?php

declare(strict_types=1);

namespace Finkashi\Plugin\Admin;

/**
 * Ecran "Dashboard" : affichage des statistiques de frequentation.
 *
 * A ce stade (squelette du plugin), la page affiche la structure
 * statique reproduite des maquettes. Le branchement a l'API et les
 * graphiques dynamiques seront ajoutes a l'etape suivante.
 */
final class EcranDashboard
{
    public static function afficher(): void
    {
        // Le rendu est delegue a un fichier de vue pour separer la
        // logique PHP du HTML.
        require FINKASHI_PLUGIN_DIR . 'views/dashboard.php';
    }
}
