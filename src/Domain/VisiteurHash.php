<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Domain;

use InvalidArgumentException;

/**
 * Empreinte anonyme d'un visiteur, valable une seule journee.
 *
 * Objectif RGPD : compter les visiteurs uniques d'une journee SANS
 * permettre leur suivi dans le temps ni leur reidentification.
 *
 * Principe :
 *  - l'empreinte est un SHA-256 calcule a partir de l'adresse IP,
 *    du user-agent, du domaine du site et d'un sel secret quotidien ;
 *  - l'adresse IP n'est JAMAIS conservee : elle ne sert qu'au calcul ;
 *  - le sel change chaque jour, donc le meme visiteur produit une
 *    empreinte differente d'un jour a l'autre : aucun suivi possible.
 *
 * Cette conception correspond aux conditions d'exemption de
 * consentement posees par la CNIL pour la mesure d'audience.
 */
final class VisiteurHash
{
    /** Empreinte SHA-256 : 64 caracteres hexadecimaux. */
    private const LONGUEUR = 64;

    public function __construct(private readonly string $valeur)
    {
        if (strlen($valeur) !== self::LONGUEUR || !ctype_xdigit($valeur)) {
            throw new InvalidArgumentException(
                'Une empreinte visiteur doit etre un SHA-256 (64 caracteres hexadecimaux).'
            );
        }
    }

    /**
     * Calcule l'empreinte anonyme d'un visiteur pour la journee en cours.
     *
     * @param string $ip          Adresse IP du visiteur (utilisee puis oubliee).
     * @param string $userAgent   En-tete User-Agent du navigateur.
     * @param string $domaineSite Domaine du site mesure.
     * @param string $selQuotidien Secret rotatif propre a la journee.
     */
    public static function calculer(
        string $ip,
        string $userAgent,
        string $domaineSite,
        string $selQuotidien,
    ): self {
        if (trim($selQuotidien) === '') {
            throw new InvalidArgumentException('Le sel quotidien ne peut pas etre vide.');
        }

        // L'IP n'apparait que dans ce calcul local ; elle n'est ni
        // retournee, ni stockee, ni journalisee.
        $empreinte = hash('sha256', $ip . '|' . $userAgent . '|' . $domaineSite . '|' . $selQuotidien);

        return new self($empreinte);
    }

    public function valeur(): string
    {
        return $this->valeur;
    }

    public function equals(VisiteurHash $autre): bool
    {
        return hash_equals($this->valeur, $autre->valeur);
    }
}
