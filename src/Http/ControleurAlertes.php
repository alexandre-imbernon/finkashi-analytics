<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Http;

use Finkashi\Analytics\Domain\AlerteRegle;
use Finkashi\Analytics\Domain\Metrique;
use Finkashi\Analytics\Domain\Operateur;
use Finkashi\Analytics\Infrastructure\AlerteRepository;
use InvalidArgumentException;
use Throwable;

/**
 * Controleur HTTP pour la gestion des regles d'alerte.
 *
 * Toutes les operations exigent la cle d'API : il s'agit d'actions
 * d'administration, jamais sollicitees par un visiteur public.
 */
final class ControleurAlertes
{
    public function __construct(
        private readonly AlerteRepository $repository,
        private readonly AuthentificationClef $auth,
    ) {
    }

    /**
     * GET /alertes/regles : liste de toutes les regles.
     */
    public function lister(): void
    {
        $this->auth->exiger();

        $regles = array_map(
            $this->serialiser(...),
            $this->repository->toutes(),
        );

        ReponseJson::envoyer($regles);
    }

    /**
     * POST /alertes/regles : creation d'une regle.
     */
    public function creer(): void
    {
        $this->auth->exiger();

        $donnees = $this->lireCorpsJson();

        try {
            $regle = new AlerteRegle(
                Metrique::from((string) ($donnees['metrique'] ?? '')),
                Operateur::from((string) ($donnees['operateur'] ?? '')),
                (int) ($donnees['seuil'] ?? 0),
                (bool) ($donnees['active'] ?? true),
            );
        } catch (InvalidArgumentException | \ValueError $e) {
            ReponseJson::erreur($e->getMessage(), 400);
            return;
        }

        $creee = $this->repository->creer($regle);
        ReponseJson::envoyer($this->serialiser($creee), 201);
    }

    /**
     * PUT /alertes/regles/:id : mise a jour d'une regle.
     */
    public function modifier(array $params): void
    {
        $this->auth->exiger();

        $id = (int) ($params['id'] ?? 0);
        $existante = $this->repository->parId($id);
        if ($existante === null) {
            ReponseJson::erreur('Regle inconnue.', 404);
            return;
        }

        $donnees = $this->lireCorpsJson();

        try {
            $modifiee = new AlerteRegle(
                Metrique::from((string) ($donnees['metrique'] ?? $existante->metrique()->value)),
                Operateur::from((string) ($donnees['operateur'] ?? $existante->operateur()->value)),
                (int) ($donnees['seuil'] ?? $existante->seuil()),
                array_key_exists('active', $donnees) ? (bool) $donnees['active'] : $existante->estActive(),
                $id,
            );
        } catch (InvalidArgumentException | \ValueError $e) {
            ReponseJson::erreur($e->getMessage(), 400);
            return;
        }

        $this->repository->mettreAJour($modifiee);
        ReponseJson::envoyer($this->serialiser($modifiee));
    }

    /**
     * DELETE /alertes/regles/:id : suppression d'une regle.
     */
    public function supprimer(array $params): void
    {
        $this->auth->exiger();

        $id = (int) ($params['id'] ?? 0);
        if (!$this->repository->supprimer($id)) {
            ReponseJson::erreur('Regle inconnue.', 404);
            return;
        }

        http_response_code(204);
    }

    /**
     * GET /alertes/historique : historique des declenchements.
     */
    public function historique(): void
    {
        $this->auth->exiger();

        $depuis = $this->dateOuParDefaut('depuis', '-30 days');
        $jusque = $this->dateOuParDefaut('jusque', 'today');
        $limite = $this->limite();

        ReponseJson::envoyer($this->repository->historique($depuis, $jusque, $limite));
    }

    // -----------------------------------------------------------------
    // Helpers prives
    // -----------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function lireCorpsJson(): array
    {
        try {
            $corps = file_get_contents('php://input');
            $donnees = json_decode($corps ?: '', true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            ReponseJson::erreur('Corps JSON invalide.', 400);
            exit;
        }
        if (!is_array($donnees)) {
            ReponseJson::erreur('Corps JSON invalide.', 400);
            exit;
        }
        return $donnees;
    }

    /**
     * @return array{id:int, metrique:string, operateur:string, seuil:int, active:bool}
     */
    private function serialiser(AlerteRegle $regle): array
    {
        return [
            'id'        => (int) $regle->id(),
            'metrique'  => $regle->metrique()->value,
            'operateur' => $regle->operateur()->value,
            'seuil'     => $regle->seuil(),
            'active'    => $regle->estActive(),
        ];
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
        $brut = $_GET['limite'] ?? '50';
        if (!is_string($brut) || !ctype_digit($brut)) {
            ReponseJson::erreur('"limite" doit etre un entier positif.', 400);
            exit;
        }
        $limite = (int) $brut;
        if ($limite < 1 || $limite > 200) {
            ReponseJson::erreur('"limite" doit etre comprise entre 1 et 200.', 400);
            exit;
        }
        return $limite;
    }
}
