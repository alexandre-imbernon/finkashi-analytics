<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use Finkashi\Analytics\Application\Geolocalisateur;
use GeoIp2\Database\Reader;
use Throwable;

/**
 * Geolocalisation basee sur la base hors-ligne MaxMind GeoLite2.
 *
 * Lit le pays directement depuis un fichier local (.mmdb), sans appel
 * reseau : rapide, gratuit et respectueux de la vie privee (l'IP ne
 * quitte jamais le serveur).
 *
 * En cas d'echec (IP privee, base absente, adresse introuvable), la
 * methode retourne null plutot que de lever une exception : une visite
 * doit pouvoir etre collectee meme sans information de pays.
 */
final class GeolocalisateurMaxMind implements Geolocalisateur
{
    private ?Reader $reader = null;

    public function __construct(private readonly string $cheminBase)
    {
    }

    public function paysPourIp(string $ip): ?string
    {
        try {
            $this->reader ??= new Reader($this->cheminBase);
            $resultat = $this->reader->country($ip);
            $code = $resultat->country->isoCode;

            return $code !== null ? strtoupper($code) : null;
        } catch (Throwable) {
            // IP privee, hors plage, base indisponible : pas de pays.
            return null;
        }
    }
}
