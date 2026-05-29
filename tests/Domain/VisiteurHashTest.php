<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Tests\Domain;

use Finkashi\Analytics\Domain\VisiteurHash;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests des invariants de l'empreinte visiteur, brique RGPD du projet.
 *
 * Ces tests prouvent que :
 *  - la meme entree produit la meme empreinte (necessaire pour compter
 *    les visiteurs uniques d'une journee) ;
 *  - un changement de sel produit une empreinte differente (necessaire
 *    pour empecher le suivi inter-journalier).
 */
final class VisiteurHashTest extends TestCase
{
    #[Test]
    public function le_calcul_produit_un_sha256_hexadecimal(): void
    {
        $hash = VisiteurHash::calculer('1.2.3.4', 'UA', 'finkashi.fr', 'sel-jour');

        $this->assertSame(64, strlen($hash->valeur()));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash->valeur());
    }

    #[Test]
    public function meme_entree_meme_sel_produit_meme_empreinte(): void
    {
        $a = VisiteurHash::calculer('1.2.3.4', 'UA', 'finkashi.fr', 'sel-jour');
        $b = VisiteurHash::calculer('1.2.3.4', 'UA', 'finkashi.fr', 'sel-jour');

        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function changement_de_sel_change_l_empreinte(): void
    {
        $lundi = VisiteurHash::calculer('1.2.3.4', 'UA', 'finkashi.fr', 'sel-lundi');
        $mardi = VisiteurHash::calculer('1.2.3.4', 'UA', 'finkashi.fr', 'sel-mardi');

        $this->assertFalse($lundi->equals($mardi),
            'Le sel rotatif doit garantir l\'absence de suivi inter-journalier.');
    }

    #[Test]
    public function changement_d_ip_change_l_empreinte(): void
    {
        $a = VisiteurHash::calculer('1.2.3.4', 'UA', 'finkashi.fr', 'sel');
        $b = VisiteurHash::calculer('5.6.7.8', 'UA', 'finkashi.fr', 'sel');

        $this->assertFalse($a->equals($b));
    }

    #[Test]
    public function refuse_un_sel_vide(): void
    {
        $this->expectException(InvalidArgumentException::class);

        VisiteurHash::calculer('1.2.3.4', 'UA', 'finkashi.fr', '');
    }

    #[Test]
    public function refuse_une_valeur_directe_invalide(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VisiteurHash('pas-un-hash');
    }
}
