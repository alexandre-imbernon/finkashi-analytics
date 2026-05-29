<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Http;

use Finkashi\Analytics\Infrastructure\StatistiqueRepository;
use InvalidArgumentException;

/**
 * Controleur de lecture des statistiques.
 *
 * Tous les endpoints exigent une cle d'API valide : ils sont destines
 * au dashboard d'administration, pas au public.
 *
 * Les bornes "depuis" et "jusque" sont prises dans les parametres de
 * requete et validees pour eviter toute valeur fantaisiste.
 */
final class ControleurStats
{
    public function __construct(
        private readonly StatistiqueRepository $repository,
        private readonly AuthentificationClef $auth,
    ) {
    }

    public function trafficGlobal(): void
    {
        $this->auth->exiger();
        [$depuis, $jusque] = $this->bornes();

        ReponseJson::envoyer($this->repository->trafficGlobal($depuis, $jusque));
    }

    public function classementPages(): void
    {
        $this->auth->exiger();
        [$depuis, $jusque] = $this->bornes();
        $limite = $this->limite();

        ReponseJson::envoyer($this->repository->classementPages($depuis, $jusque, $limite));
    }

    public function repartitionParCanal(): void
    {
        $this->auth->exiger();
        [$depuis, $jusque] = $this->bornes();

        ReponseJson::envoyer($this->repository->repartitionParCanal($depuis, $jusque));
    }

    public function classementSources(): void
    {
        $this->auth->exiger();
        [$depuis, $jusque] = $this->bornes();
        $limite = $this->limite();

        ReponseJson::envoyer($this->repository->classementSources($depuis, $jusque, $limite));
    }

    public function repartitionParPays(): void
    {
        $this->auth->exiger();
        [$depuis, $jusque] = $this->bornes();

        ReponseJson::envoyer($this->repository->repartitionParPays($depuis, $jusque));
    }

    /**
     * Extrait et valide la plage [depuis, jusque] depuis la query string.
     * Par defaut : les 30 derniers jours.
     *
     * @return array{0:string, 1:string}
     */
    private function bornes(): array
    {
        $depuis = $this->dateOuParDefaut('depuis', '-30 days');
        $jusque = $this->dateOuParDefaut('jusque', 'today');

        if ($depuis > $jusque) {
            ReponseJson::erreur('La borne "depuis" doit etre anterieure a "jusque".', 400);
            exit;
        }

        return [$depuis, $jusque];
    }

    private function dateOuParDefaut(string $param, string $defaut): string
    {
        $brut = $_GET[$param] ?? null;

        if ($brut === null) {
            return (new \DateTimeImmutable($defaut))->format('Y-m-d');
        }

        if (!is_string($brut) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $brut) !== 1) {
            ReponseJson::erreur('Format de date attendu : YYYY-MM-DD.', 400);
            exit;
        }

        return $brut;
    }

    private function limite(): int
    {
        $brut = $_GET['limite'] ?? '20';

        if (!is_string($brut) || !ctype_digit($brut)) {
            ReponseJson::erreur('"limite" doit etre un entier positif.', 400);
            exit;
        }

        $limite = (int) $brut;

        if ($limite < 1 || $limite > 100) {
            ReponseJson::erreur('"limite" doit etre comprise entre 1 et 100.', 400);
            exit;
        }

        return $limite;
    }
}
