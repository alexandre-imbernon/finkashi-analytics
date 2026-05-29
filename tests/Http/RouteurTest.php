<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Tests\Http;

use Finkashi\Analytics\Http\Routeur;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RouteurTest extends TestCase
{
    #[Test]
    public function dispatche_une_route_simple(): void
    {
        $routeur = new Routeur();
        $appelee = false;
        $routeur->ajouter('GET', '/stats/trafic', static function () use (&$appelee): void {
            $appelee = true;
        });

        $this->assertTrue($routeur->dispatcher('GET', '/stats/trafic'));
        $this->assertTrue($appelee);
    }

    #[Test]
    public function refuse_une_route_inconnue(): void
    {
        $routeur = new Routeur();
        $routeur->ajouter('GET', '/stats/trafic', static fn () => null);

        $this->assertFalse($routeur->dispatcher('GET', '/inconnu'));
    }

    #[Test]
    public function distingue_les_methodes_http(): void
    {
        $routeur = new Routeur();
        $routeur->ajouter('GET', '/collect', static fn () => null);

        $this->assertFalse($routeur->dispatcher('POST', '/collect'),
            'GET ne doit pas matcher POST sur le meme chemin.');
    }

    #[Test]
    public function capture_les_parametres_nommes(): void
    {
        $routeur = new Routeur();
        $recus = [];
        $routeur->ajouter('GET', '/pages/:id', static function (array $args) use (&$recus): void {
            $recus = $args;
        });

        $this->assertTrue($routeur->dispatcher('GET', '/pages/42'));
        $this->assertSame(['id' => '42'], $recus);
    }
}
