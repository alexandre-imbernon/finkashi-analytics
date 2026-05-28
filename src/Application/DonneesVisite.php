<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Application;

use InvalidArgumentException;

/**
 * Donnees brutes d'une visite a collecter, telles que recues du client.
 *
 * Cet objet regroupe et valide les entrees avant tout traitement.
 * Il constitue le point unique de controle des donnees externes, ce
 * qui evite de disperser la validation dans le reste du code.
 *
 * L'adresse IP et le user-agent ne servent qu'au calcul de l'empreinte
 * anonyme et a la geolocalisation : ils ne sont jamais stockes tels
 * quels.
 */
final class DonneesVisite
{
    private const LONGUEUR_MAX_CHEMIN = 255;

    public function __construct(
        private readonly string $chemin,
        private readonly ?string $titre,
        private readonly ?string $domaineReferent,
        private readonly string $ip,
        private readonly string $userAgent,
    ) {
        $cheminNettoye = trim($chemin);

        if ($cheminNettoye === '') {
            throw new InvalidArgumentException('Le chemin visite est obligatoire.');
        }

        if (!str_starts_with($cheminNettoye, '/')) {
            throw new InvalidArgumentException('Le chemin visite doit commencer par "/".');
        }

        if (mb_strlen($cheminNettoye) > self::LONGUEUR_MAX_CHEMIN) {
            throw new InvalidArgumentException('Le chemin visite est trop long.');
        }

        if (trim($this->ip) === '') {
            throw new InvalidArgumentException('L\'adresse IP est requise pour le calcul de l\'empreinte.');
        }
    }

    public function chemin(): string
    {
        return trim($this->chemin);
    }

    public function titre(): ?string
    {
        $titre = $this->titre !== null ? trim($this->titre) : null;

        return ($titre === null || $titre === '') ? null : $titre;
    }

    public function domaineReferent(): ?string
    {
        $ref = $this->domaineReferent !== null ? trim($this->domaineReferent) : null;

        return ($ref === null || $ref === '') ? null : strtolower($ref);
    }

    public function ip(): string
    {
        return trim($this->ip);
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }
}
