<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Tests\Application;

use DateTimeImmutable;
use Finkashi\Analytics\Application\DonneesVisite;
use Finkashi\Analytics\Application\FournisseurSelQuotidien;
use Finkashi\Analytics\Application\Geolocalisateur;
use Finkashi\Analytics\Application\ServiceCollecte;
use Finkashi\Analytics\Domain\Canal;
use Finkashi\Analytics\Domain\Evenement;
use Finkashi\Analytics\Domain\Page;
use Finkashi\Analytics\Domain\Source;
use Finkashi\Analytics\Infrastructure\EvenementRepository;
use Finkashi\Analytics\Infrastructure\PageRepository;
use Finkashi\Analytics\Infrastructure\SourceRepository;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests du service de collecte avec des doubles de test.
 *
 * On utilise une base SQLite en memoire pour faire fonctionner les
 * vrais repositories sans dependre d'un serveur MySQL. Le
 * geolocalisateur est remplace par un double qui retourne un pays
 * connu, ce qui permet d'isoler la logique du service.
 */
final class ServiceCollecteTest extends TestCase
{
    private PDO $pdo;
    private ServiceCollecte $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Schema simplifie compatible des deux SGBD pour les tests.
        $this->pdo->exec('CREATE TABLE page (id INTEGER PRIMARY KEY AUTOINCREMENT, chemin TEXT UNIQUE NOT NULL, titre TEXT, decouverte_le TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE source (id INTEGER PRIMARY KEY AUTOINCREMENT, domaine TEXT UNIQUE NOT NULL, canal TEXT NOT NULL, decouverte_le TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE evenement (id INTEGER PRIMARY KEY AUTOINCREMENT, page_id INTEGER NOT NULL, source_id INTEGER, canal TEXT NOT NULL, pays TEXT, visiteur_hash TEXT NOT NULL, survenu_le TEXT NOT NULL)');

        $geolocalisateur = new class implements Geolocalisateur {
            public function paysPourIp(string $ip): ?string
            {
                return $ip === '2.2.2.2' ? 'JP' : 'FR';
            }
        };

        $this->service = new ServiceCollecte(
            new PageRepository($this->pdo),
            new SourceRepository($this->pdo),
            new EvenementRepository($this->pdo),
            new FournisseurSelQuotidien('secret-de-test'),
            $geolocalisateur,
            'finkashi.fr',
        );
    }

    #[Test]
    public function collecte_une_visite_depuis_un_reseau_social(): void
    {
        $evt = $this->service->collecter(
            new DonneesVisite('/article', 'Article', 'reddit.com', '1.1.1.1', 'UA'),
        );

        $this->assertSame(Canal::Social, $evt->canal());
        $this->assertInstanceOf(Source::class, $evt->source());
        $this->assertSame('reddit.com', $evt->source()->domaine());
        $this->assertSame('FR', $evt->pays());
    }

    #[Test]
    public function collecte_une_visite_sans_referent_comme_acces_direct(): void
    {
        $evt = $this->service->collecter(
            new DonneesVisite('/accueil', null, null, '1.1.1.1', 'UA'),
        );

        $this->assertSame(Canal::Direct, $evt->canal());
        $this->assertNull($evt->source());
    }

    #[Test]
    public function ne_compte_pas_la_navigation_interne_comme_du_referent(): void
    {
        $evt = $this->service->collecter(
            new DonneesVisite('/page', null, 'finkashi.fr', '1.1.1.1', 'UA'),
        );

        $this->assertSame(Canal::Direct, $evt->canal(),
            'Un referent egal au propre domaine du site doit etre traite comme direct.');
        $this->assertNull($evt->source());
    }

    #[Test]
    public function la_geolocalisation_renvoie_le_pays_attendu(): void
    {
        $evt = $this->service->collecter(
            new DonneesVisite('/article', null, null, '2.2.2.2', 'UA'),
        );

        $this->assertSame('JP', $evt->pays());
    }

    #[Test]
    public function refuse_un_chemin_invalide(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->collecter(new DonneesVisite('sans-slash', null, null, '1.1.1.1', 'UA'));
    }

    #[Test]
    public function un_meme_visiteur_le_meme_jour_produit_la_meme_empreinte(): void
    {
        $instant = new DateTimeImmutable('2026-05-28 10:00:00');

        $evt1 = $this->service->collecter(
            new DonneesVisite('/page-a', null, null, '9.9.9.9', 'Firefox'),
            $instant,
        );
        $evt2 = $this->service->collecter(
            new DonneesVisite('/page-b', null, null, '9.9.9.9', 'Firefox'),
            $instant,
        );

        $this->assertTrue($evt1->visiteurHash()->equals($evt2->visiteurHash()),
            'Le determinisme du hash est indispensable au comptage des visiteurs uniques.');
    }

    #[Test]
    public function la_collecte_persiste_l_evenement(): void
    {
        $evt = $this->service->collecter(
            new DonneesVisite('/x', null, null, '1.1.1.1', 'UA'),
        );

        $this->assertInstanceOf(Evenement::class, $evt);
        $this->assertNotNull($evt->id(), 'L\'evenement doit recevoir un identifiant apres persistance.');

        $nb = (int) $this->pdo->query('SELECT COUNT(*) FROM evenement')->fetchColumn();
        $this->assertSame(1, $nb);
    }
}
