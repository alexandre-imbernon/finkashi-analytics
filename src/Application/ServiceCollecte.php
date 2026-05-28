<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Application;

use DateTimeImmutable;
use Finkashi\Analytics\Domain\Canal;
use Finkashi\Analytics\Domain\Evenement;
use Finkashi\Analytics\Domain\VisiteurHash;
use Finkashi\Analytics\Infrastructure\EvenementRepository;
use Finkashi\Analytics\Infrastructure\PageRepository;
use Finkashi\Analytics\Infrastructure\SourceRepository;

/**
 * Service de collecte d'une visite.
 *
 * Cas d'usage central : transformer des donnees brutes de visite en un
 * evenement persiste. Le service orchestre les composants (repositories,
 * calcul d'empreinte) sans realiser lui-meme les acces de bas niveau.
 *
 * La geolocalisation est fournie par un service externe injecte, ce qui
 * permet de la remplacer ou de la tester independamment.
 */
final class ServiceCollecte
{
    public function __construct(
        private readonly PageRepository $pages,
        private readonly SourceRepository $sources,
        private readonly EvenementRepository $evenements,
        private readonly FournisseurSelQuotidien $sel,
        private readonly Geolocalisateur $geolocalisateur,
        private readonly string $domaineSite,
    ) {
    }

    /**
     * Collecte une visite et retourne l'evenement enregistre.
     */
    public function collecter(DonneesVisite $visite, ?DateTimeImmutable $instant = null): Evenement
    {
        $instant ??= new DateTimeImmutable();

        // 1. Resoudre la page (creee si premiere visite).
        $page = $this->pages->trouverOuCreer($visite->chemin(), $visite->titre());

        // 2. Resoudre la source et le canal a partir du referent.
        $domaineReferent = $visite->domaineReferent();
        $source = null;
        $canal = Canal::Direct;

        if ($domaineReferent !== null && $domaineReferent !== $this->domaineSite) {
            $source = $this->sources->trouverOuCreer($domaineReferent);
            $canal = $source->canal();
        }

        // 3. Calculer l'empreinte anonyme (l'IP est utilisee puis oubliee).
        $hash = VisiteurHash::calculer(
            $visite->ip(),
            $visite->userAgent(),
            $this->domaineSite,
            $this->sel->pour($instant),
        );

        // 4. Determiner le pays (geolocalisation au niveau pays).
        $pays = $this->geolocalisateur->paysPourIp($visite->ip());

        // 5. Construire et persister l'evenement.
        $evenement = new Evenement($page, $canal, $hash, $instant, $source, $pays);

        return $this->evenements->enregistrer($evenement);
    }
}
