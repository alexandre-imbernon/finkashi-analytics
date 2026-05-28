<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Evenement de consultation : le fait qu'une page ait ete vue, a un
 * instant donne, avec une origine et un contexte geographique.
 *
 * C'est l'entite centrale du domaine. Elle relie une Page, une Source
 * optionnelle (un acces direct n'a pas de source), un Canal, un pays
 * et l'empreinte anonyme du visiteur.
 */
final class Evenement
{
    /**
     * @param string|null $pays Code pays ISO 3166-1 alpha-2 (ex. 'FR'),
     *                          ou null si la geolocalisation a echoue.
     */
    public function __construct(
        private readonly Page $page,
        private readonly Canal $canal,
        private readonly VisiteurHash $visiteurHash,
        private readonly DateTimeImmutable $survenuLe,
        private readonly ?Source $source = null,
        private readonly ?string $pays = null,
        private readonly ?int $id = null,
    ) {
        if ($pays !== null) {
            if (strlen($pays) !== 2 || !ctype_alpha($pays)) {
                throw new InvalidArgumentException(
                    "Le code pays doit etre au format ISO alpha-2 (deux lettres). Recu : {$pays}"
                );
            }
        }

        // Coherence metier : un evenement avec une source devrait avoir
        // un canal coherent avec celle-ci. Un acces direct (sans source)
        // ne peut pas etre classe comme provenant d'un referent.
        if ($source === null && $canal === Canal::Referent) {
            throw new InvalidArgumentException(
                'Un evenement sans source ne peut pas relever du canal "referent".'
            );
        }
    }

    public function page(): Page
    {
        return $this->page;
    }

    public function canal(): Canal
    {
        return $this->canal;
    }

    public function visiteurHash(): VisiteurHash
    {
        return $this->visiteurHash;
    }

    public function survenuLe(): DateTimeImmutable
    {
        return $this->survenuLe;
    }

    public function source(): ?Source
    {
        return $this->source;
    }

    public function pays(): ?string
    {
        return $this->pays === null ? null : strtoupper($this->pays);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function avecId(int $id): self
    {
        return new self(
            $this->page,
            $this->canal,
            $this->visiteurHash,
            $this->survenuLe,
            $this->source,
            $this->pays,
            $id,
        );
    }
}
