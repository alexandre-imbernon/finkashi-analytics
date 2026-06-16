<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Tests\Application;

use Finkashi\Analytics\Application\Persistance\ArchiveStockage;
use Finkashi\Analytics\Application\ServiceArchivage;
use Finkashi\Analytics\Infrastructure\EvenementRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Verifie que ServiceArchivage est bien decouple de la technologie
 * de persistance utilisee pour stocker les metadonnees d'archives.
 *
 * On lui injecte ici une implementation factice de ArchiveStockage
 * qui n'utilise ni MySQL ni MongoDB : elle garde simplement les
 * appels en memoire. Si ce test passe, c'est qu'on peut substituer
 * n'importe quelle implementation concrete sans modifier le service
 * applicatif. C'est exactement ce que demontre la branche NoSQL :
 * remplacer ArchiveRepository (MySQL) par ArchiveRepositoryMongo
 * n'a aucun impact sur la couche metier.
 */
final class ServiceArchivageAvecInterfaceTest extends TestCase
{
    public function testServiceArchivageFonctionneAvecImplementationFactice(): void
    {
        // Base SQLite en memoire pour le repo des evenements.
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(<<<SQL
            CREATE TABLE evenement (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                page_id     INTEGER NOT NULL,
                source_id   INTEGER,
                canal       TEXT NOT NULL,
                pays        TEXT,
                visiteur_hash TEXT NOT NULL,
                survenu_le  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
        // Un evenement ancien a archiver.
        $pdo->exec("INSERT INTO evenement (page_id, canal, visiteur_hash, survenu_le)
                    VALUES (1, 'direct', 'abc', '2020-01-01 00:00:00')");

        $evenements = new EvenementRepository($pdo);

        // Stockage factice : capture les appels en memoire.
        $stockage = new class implements ArchiveStockage {
            /** @var list<array<string, mixed>> */
            public array $appels = [];

            public function enregistrer(
                string $periodeDebut,
                string $periodeFin,
                string $cheminFichier,
                int $nbEvenements,
            ): void {
                $this->appels[] = [
                    'periode_debut' => $periodeDebut,
                    'periode_fin'   => $periodeFin,
                    'fichier'       => $cheminFichier,
                    'nb_evenements' => $nbEvenements,
                ];
            }
        };

        $service = new ServiceArchivage($evenements, $stockage, sys_get_temp_dir() . '/finkashi-test-archives');
        $resultat = $service->archiverEtPurger('2025-01-01');

        // Le service a bien archive l'evenement.
        self::assertSame(1, $resultat['nb_evenements']);

        // Et il a delegue l'enregistrement de la metadonnee a l'interface,
        // SANS connaitre l'implementation concrete utilisee.
        self::assertCount(1, $stockage->appels);
        self::assertSame('2025-01-01', $stockage->appels[0]['periode_fin']);
        self::assertSame(1, $stockage->appels[0]['nb_evenements']);
    }
}
