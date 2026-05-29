<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Domain;

/**
 * Metriques surveillables par une regle d'alerte.
 *
 * Les valeurs correspondent a l'ENUM `metrique` de la table
 * alerte_regle. Restreindre l'univers des metriques au moyen d'une
 * enumeration garantit qu'aucune metrique inconnue ne peut etre
 * configuree.
 */
enum Metrique: string
{
    case VisiteursJour  = 'visiteurs_jour';
    case PagesVuesJour  = 'pages_vues_jour';

    public function libelle(): string
    {
        return match ($this) {
            self::VisiteursJour => 'Visiteurs uniques sur la journee',
            self::PagesVuesJour => 'Pages vues sur la journee',
        };
    }
}
