<?php

declare(strict_types=1);

namespace Finkashi\Plugin\Front;

/**
 * Tracker : injecte le script de mesure d'audience dans les pages
 * publiques du site.
 *
 * Pour l'instant, cette classe est un squelette : elle declare son
 * hook mais n'injecte rien. L'implementation complete viendra a
 * l'etape suivante (Phase 4.4), une fois les ecrans d'admin valides.
 */
final class Tracker
{
    public function enregistrer(): void
    {
        // Hook reserve pour l'injection du script de tracking
        // dans le head des pages publiques. Implementation a venir.
        add_action('wp_head', [$this, 'injecterTracker']);
    }

    public function injecterTracker(): void
    {
        // Vide pour l'instant. L'implementation reelle :
        //   - lit les reglages,
        //   - genere et injecte un <script> qui POST sur l'API.
    }
}
