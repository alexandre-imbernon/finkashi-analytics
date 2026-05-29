<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Tests\Domain;

use Finkashi\Analytics\Domain\Canal;
use Finkashi\Analytics\Domain\Source;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SourceTest extends TestCase
{
    #[Test]
    public function cree_une_source_valide(): void
    {
        $source = new Source('reddit.com', Canal::Social);

        $this->assertSame('reddit.com', $source->domaine());
        $this->assertSame(Canal::Social, $source->canal());
    }

    #[Test]
    public function normalise_le_domaine_en_minuscules(): void
    {
        $source = new Source('  REDDIT.COM  ', Canal::Social);

        $this->assertSame('reddit.com', $source->domaine());
    }

    #[Test]
    public function refuse_un_domaine_vide(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Source('', Canal::Social);
    }
}
