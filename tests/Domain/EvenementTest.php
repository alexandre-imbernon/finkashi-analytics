<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Tests\Domain;

use DateTimeImmutable;
use Finkashi\Analytics\Domain\Canal;
use Finkashi\Analytics\Domain\Evenement;
use Finkashi\Analytics\Domain\Page;
use Finkashi\Analytics\Domain\Source;
use Finkashi\Analytics\Domain\VisiteurHash;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EvenementTest extends TestCase
{
    private function hash(): VisiteurHash
    {
        return VisiteurHash::calculer('1.2.3.4', 'UA', 'finkashi.fr', 'sel');
    }

    #[Test]
    public function cree_un_evenement_direct(): void
    {
        $page = new Page('/');
        $evt = new Evenement($page, Canal::Direct, $this->hash(), new DateTimeImmutable());

        $this->assertSame(Canal::Direct, $evt->canal());
        $this->assertNull($evt->source());
        $this->assertNull($evt->pays());
    }

    #[Test]
    public function cree_un_evenement_avec_source_et_pays(): void
    {
        $page = new Page('/');
        $source = new Source('reddit.com', Canal::Social);
        $evt = new Evenement($page, Canal::Social, $this->hash(), new DateTimeImmutable(), $source, 'FR');

        $this->assertSame('FR', $evt->pays());
        $this->assertSame($source, $evt->source());
    }

    #[Test]
    public function normalise_le_pays_en_majuscules(): void
    {
        $evt = new Evenement(new Page('/'), Canal::Direct, $this->hash(), new DateTimeImmutable(), null, 'fr');

        $this->assertSame('FR', $evt->pays());
    }

    #[Test]
    public function refuse_un_pays_de_mauvaise_longueur(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Evenement(new Page('/'), Canal::Direct, $this->hash(), new DateTimeImmutable(), null, 'FRA');
    }

    #[Test]
    public function refuse_un_referent_sans_source(): void
    {
        // Coherence metier : on ne peut pas etre "referent" sans avoir
        // une source identifiee.
        $this->expectException(InvalidArgumentException::class);

        new Evenement(new Page('/'), Canal::Referent, $this->hash(), new DateTimeImmutable(), null, 'FR');
    }
}
