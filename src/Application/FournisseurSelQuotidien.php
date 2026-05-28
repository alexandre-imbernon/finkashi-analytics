<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Application;

use DateTimeImmutable;

/**
 * Fournit le sel quotidien servant au calcul des empreintes visiteurs.
 *
 * Le sel combine un secret fixe (connu de l'application seule) et la
 * date du jour. Il change donc chaque jour : une meme personne produit
 * une empreinte differente d'un jour a l'autre, ce qui empeche tout
 * suivi dans le temps. Le secret fixe empeche un tiers de recalculer
 * les empreintes meme s'il connaissait la methode.
 */
final class FournisseurSelQuotidien
{
    public function __construct(private readonly string $secret)
    {
    }

    /**
     * Retourne le sel applicable a une date donnee (aujourd'hui par
     * defaut).
     */
    public function pour(?DateTimeImmutable $date = null): string
    {
        $date ??= new DateTimeImmutable();

        return $this->secret . ':' . $date->format('Y-m-d');
    }
}
