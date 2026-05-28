<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Domain;

use InvalidArgumentException;

/**
 * Source de trafic : domaine referent d'ou provient un visiteur,
 * rattache a un canal d'acquisition.
 *
 * Le type Canal (enumeration) garantit qu'une source ne peut pas
 * etre rattachee a un canal invalide.
 */
final class Source
{
    private const LONGUEUR_MAX_DOMAINE = 255;

    public function __construct(
        private readonly string $domaine,
        private readonly Canal $canal,
        private readonly ?int $id = null,
    ) {
        $domaineNettoye = trim($domaine);

        if ($domaineNettoye === '') {
            throw new InvalidArgumentException('Le domaine de la source ne peut pas etre vide.');
        }

        if (mb_strlen($domaineNettoye) > self::LONGUEUR_MAX_DOMAINE) {
            throw new InvalidArgumentException(
                'Le domaine de la source depasse ' . self::LONGUEUR_MAX_DOMAINE . ' caracteres.'
            );
        }
    }

    public function domaine(): string
    {
        return strtolower(trim($this->domaine));
    }

    public function canal(): Canal
    {
        return $this->canal;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function avecId(int $id): self
    {
        return new self($this->domaine, $this->canal, $id);
    }
}
