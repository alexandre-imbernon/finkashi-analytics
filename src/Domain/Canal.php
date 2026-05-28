<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Domain;

/**
 * Canal d'acquisition du trafic.
 *
 * Une enumeration PHP garantit qu'aucune valeur invalide ne peut
 * circuler dans l'application : le typage est verifie par le moteur
 * lui-meme. Les valeurs correspondent exactement a l'ENUM de la base
 * de donnees (colonne `canal`), ce qui assure la coherence entre le
 * code et le schema.
 */
enum Canal: string
{
    case Recherche = 'recherche';
    case Social    = 'social';
    case Referent  = 'referent';
    case Direct    = 'direct';

    /**
     * Determine le canal a partir du domaine referent et du chemin
     * de provenance. Cette logique metier centralise la classification
     * du trafic en un seul endroit.
     *
     * @param string|null $domaineReferent Domaine d'ou vient le visiteur
     *                                     (null = acces direct).
     */
    public static function depuisReferent(?string $domaineReferent): self
    {
        if ($domaineReferent === null || trim($domaineReferent) === '') {
            return self::Direct;
        }

        $domaine = strtolower(trim($domaineReferent));

        $moteurs = ['google.', 'bing.', 'duckduckgo.', 'qwant.', 'ecosia.', 'yahoo.'];
        foreach ($moteurs as $moteur) {
            if (str_contains($domaine, $moteur)) {
                return self::Recherche;
            }
        }

        $reseaux = ['reddit.', 'bsky.', 'mastodon.', 'twitter.', 'x.com',
                    'facebook.', 'instagram.', 'youtube.', 'linkedin.'];
        foreach ($reseaux as $reseau) {
            if (str_contains($domaine, $reseau)) {
                return self::Social;
            }
        }

        return self::Referent;
    }

    /**
     * Libelle lisible pour l'affichage dans l'interface.
     */
    public function libelle(): string
    {
        return match ($this) {
            self::Recherche => 'Moteur de recherche',
            self::Social    => 'Reseau social',
            self::Referent  => 'Site referent',
            self::Direct    => 'Acces direct',
        };
    }
}
