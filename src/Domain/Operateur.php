<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Domain;

/**
 * Operateur de comparaison utilise par une regle d'alerte.
 *
 * Encapsule la logique de comparaison : la regle elle-meme ne sait
 * pas comparer, elle delegue a l'enumeration. Ajouter un operateur
 * (egal, entre, etc.) ne necessitera que d'enrichir cet enum.
 */
enum Operateur: string
{
    case Inferieur = 'inferieur';
    case Superieur = 'superieur';

    /**
     * Evalue si la valeur observee franchit le seuil selon cet operateur.
     */
    public function evalue(int $valeur, int $seuil): bool
    {
        return match ($this) {
            self::Inferieur => $valeur < $seuil,
            self::Superieur => $valeur > $seuil,
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Inferieur => 'inferieur a',
            self::Superieur => 'superieur a',
        };
    }
}
