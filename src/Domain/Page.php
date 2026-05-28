<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Domain;

use InvalidArgumentException;

/**
 * Page consultee du site.
 *
 * Entite du domaine : elle represente un concept metier (une page web)
 * independamment de la maniere dont elle est stockee. La validation
 * dans le constructeur garantit qu'une Page ne peut jamais exister
 * dans un etat incoherent (style defensif).
 */
final class Page
{
    private const LONGUEUR_MAX_CHEMIN = 255;
    private const LONGUEUR_MAX_TITRE  = 255;

    /**
     * @param int|null $id Identifiant en base (null tant que la page
     *                     n'a pas ete persistee).
     */
    public function __construct(
        private readonly string $chemin,
        private readonly ?string $titre = null,
        private readonly ?int $id = null,
    ) {
        $cheminNettoye = trim($chemin);

        if ($cheminNettoye === '') {
            throw new InvalidArgumentException('Le chemin de la page ne peut pas etre vide.');
        }

        if (!str_starts_with($cheminNettoye, '/')) {
            throw new InvalidArgumentException(
                "Le chemin de la page doit commencer par '/' (recu : {$cheminNettoye})."
            );
        }

        if (mb_strlen($cheminNettoye) > self::LONGUEUR_MAX_CHEMIN) {
            throw new InvalidArgumentException(
                'Le chemin de la page depasse ' . self::LONGUEUR_MAX_CHEMIN . ' caracteres.'
            );
        }

        if ($titre !== null && mb_strlen($titre) > self::LONGUEUR_MAX_TITRE) {
            throw new InvalidArgumentException(
                'Le titre de la page depasse ' . self::LONGUEUR_MAX_TITRE . ' caracteres.'
            );
        }
    }

    public function chemin(): string
    {
        return trim($this->chemin);
    }

    public function titre(): ?string
    {
        return $this->titre;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    /**
     * Retourne une nouvelle instance dotee d'un identifiant.
     * L'entite etant immuable, on ne modifie pas l'objet existant :
     * on en cree une copie enrichie (utile apres l'insertion en base).
     */
    public function avecId(int $id): self
    {
        return new self($this->chemin, $this->titre, $id);
    }
}
