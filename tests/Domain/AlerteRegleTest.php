<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Tests\Domain;

use Finkashi\Analytics\Domain\AlerteRegle;
use Finkashi\Analytics\Domain\Metrique;
use Finkashi\Analytics\Domain\Operateur;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AlerteRegleTest extends TestCase
{
    #[Test]
    public function operateur_inferieur_evalue_correctement(): void
    {
        $this->assertTrue(Operateur::Inferieur->evalue(5, 10));
        $this->assertFalse(Operateur::Inferieur->evalue(10, 10));
        $this->assertFalse(Operateur::Inferieur->evalue(15, 10));
    }

    #[Test]
    public function operateur_superieur_evalue_correctement(): void
    {
        $this->assertTrue(Operateur::Superieur->evalue(15, 10));
        $this->assertFalse(Operateur::Superieur->evalue(10, 10));
        $this->assertFalse(Operateur::Superieur->evalue(5, 10));
    }

    #[Test]
    public function regle_active_se_declenche_sous_le_seuil(): void
    {
        $regle = new AlerteRegle(Metrique::VisiteursJour, Operateur::Inferieur, 10);

        $this->assertTrue($regle->declencheePar(3));
        $this->assertFalse($regle->declencheePar(15));
    }

    #[Test]
    public function regle_inactive_ne_se_declenche_jamais(): void
    {
        $regle = new AlerteRegle(Metrique::VisiteursJour, Operateur::Inferieur, 10, active: false);

        $this->assertFalse($regle->declencheePar(0),
            'Une regle inactive ne doit jamais se declencher, meme si la valeur franchit le seuil.');
    }

    #[Test]
    public function refuse_un_seuil_negatif(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AlerteRegle(Metrique::VisiteursJour, Operateur::Inferieur, -5);
    }
}
