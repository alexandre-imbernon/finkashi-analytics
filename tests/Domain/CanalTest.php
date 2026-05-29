<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Tests\Domain;

use Finkashi\Analytics\Domain\Canal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'enumeration Canal.
 *
 * On verifie principalement la logique de classification du trafic
 * a partir du domaine referent. Comme c'est une logique pure (sans
 * dependance externe), les tests sont rapides et deterministes.
 */
final class CanalTest extends TestCase
{
    #[Test]
    #[DataProvider('referentsConnus')]
    public function classifie_correctement_les_referents_connus(?string $referent, Canal $attendu): void
    {
        $this->assertSame($attendu, Canal::depuisReferent($referent));
    }

    /**
     * @return iterable<string, array{0: string|null, 1: Canal}>
     */
    public static function referentsConnus(): iterable
    {
        yield 'Google'            => ['google.com',             Canal::Recherche];
        yield 'DuckDuckGo'        => ['www.duckduckgo.com',     Canal::Recherche];
        yield 'Bing'              => ['bing.com',               Canal::Recherche];
        yield 'Reddit'            => ['reddit.com',             Canal::Social];
        yield 'Bluesky'           => ['bsky.app',               Canal::Social];
        yield 'X (Twitter)'       => ['x.com',                  Canal::Social];
        yield 'Site quelconque'   => ['senscritique.com',       Canal::Referent];
        yield 'Acces direct'      => [null,                     Canal::Direct];
        yield 'Chaine vide'       => ['',                       Canal::Direct];
        yield 'Espaces seuls'     => ['   ',                    Canal::Direct];
    }

    #[Test]
    public function chaque_canal_a_un_libelle_distinct(): void
    {
        $libelles = array_map(static fn (Canal $c): string => $c->libelle(), Canal::cases());

        $this->assertCount(count($libelles), array_unique($libelles));
    }

    #[Test]
    public function la_classification_est_insensible_a_la_casse(): void
    {
        $this->assertSame(Canal::Social, Canal::depuisReferent('REDDIT.COM'));
        $this->assertSame(Canal::Recherche, Canal::depuisReferent('Google.Com'));
    }
}
