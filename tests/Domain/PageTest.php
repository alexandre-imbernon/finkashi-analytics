<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Tests\Domain;

use Finkashi\Analytics\Domain\Page;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    #[Test]
    public function cree_une_page_valide(): void
    {
        $page = new Page('/articles/test', 'Mon article');

        $this->assertSame('/articles/test', $page->chemin());
        $this->assertSame('Mon article', $page->titre());
        $this->assertNull($page->id());
    }

    #[Test]
    public function refuse_un_chemin_vide(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Page('   ');
    }

    #[Test]
    public function refuse_un_chemin_sans_slash_initial(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Page('articles/test');
    }

    #[Test]
    public function refuse_un_chemin_trop_long(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Page('/' . str_repeat('a', 255));
    }

    #[Test]
    public function avecId_renvoie_une_copie_immuable(): void
    {
        $sansId  = new Page('/');
        $avecId  = $sansId->avecId(42);

        $this->assertNull($sansId->id(), 'l\'original doit rester sans id');
        $this->assertSame(42, $avecId->id());
        $this->assertSame('/', $avecId->chemin());
    }
}
