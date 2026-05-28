<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Application;

/**
 * Contrat d'un service de geolocalisation par adresse IP.
 *
 * Definir une interface (et non une classe concrete) permet au service
 * de collecte de ne dependre que du contrat, pas d'une implementation
 * particuliere. On peut ainsi substituer une implementation de test,
 * ou changer de fournisseur de geolocalisation, sans modifier le code
 * appelant.
 */
interface Geolocalisateur
{
    /**
     * Retourne le code pays ISO 3166-1 alpha-2 (ex. 'FR') correspondant
     * a l'adresse IP, ou null si le pays ne peut pas etre determine.
     */
    public function paysPourIp(string $ip): ?string;
}
