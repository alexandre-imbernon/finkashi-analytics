<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Domain;

use InvalidArgumentException;

/**
 * Regle d'alerte : declenche une notification lorsque la valeur
 * mesuree pour une metrique franchit un seuil selon un operateur.
 */
final class AlerteRegle
{
    public function __construct(
        private readonly Metrique $metrique,
        private readonly Operateur $operateur,
        private readonly int $seuil,
        private readonly bool $active = true,
        private readonly ?int $id = null,
    ) {
        if ($seuil < 0) {
            throw new InvalidArgumentException('Le seuil d\'alerte doit etre positif ou nul.');
        }
    }

    /**
     * Determine si la valeur observee declenche cette regle.
     * Une regle inactive ne declenche jamais.
     */
    public function declencheePar(int $valeurObservee): bool
    {
        if (!$this->active) {
            return false;
        }

        return $this->operateur->evalue($valeurObservee, $this->seuil);
    }

    public function metrique(): Metrique  { return $this->metrique; }
    public function operateur(): Operateur { return $this->operateur; }
    public function seuil(): int           { return $this->seuil; }
    public function estActive(): bool      { return $this->active; }
    public function id(): ?int             { return $this->id; }

    public function avecId(int $id): self
    {
        return new self($this->metrique, $this->operateur, $this->seuil, $this->active, $id);
    }
}
