<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Http;

use Finkashi\Analytics\Application\DonneesVisite;
use Finkashi\Analytics\Application\ServiceCollecte;
use InvalidArgumentException;
use Throwable;

/**
 * Controleur de collecte d'evenements de visite.
 *
 * Endpoint public : n'importe quel visiteur du site doit pouvoir y
 * envoyer un evenement (c'est le tracker JS qui appellera). La
 * protection repose sur :
 *  - la validation stricte des entrees (via DonneesVisite) ;
 *  - le fait qu'un attaquant ne peut au pire que polluer les stats
 *    avec ses propres visites, pas exfiltrer de donnees.
 *
 * Les en-tetes CORS autorisent l'appel depuis le domaine du site.
 */
final class ControleurCollecte
{
    public function __construct(
        private readonly ServiceCollecte $service,
        private readonly string $domaineSite,
    ) {
    }

    public function gerer(): void
    {

        // CORS : autoriser le navigateur du visiteur a appeler l'API.
        $origine = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origine !== '' && $this->origineAutorisee($origine)) {
            header('Access-Control-Allow-Origin: ' . $origine);
            header('Vary: Origin');
        }

        // Requete pre-vol CORS : repondre puis sortir.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            header('Access-Control-Allow-Methods: POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            http_response_code(204);
            return;
        }

        try {
            $corps = file_get_contents('php://input');
            $donnees = json_decode($corps ?: '', true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            ReponseJson::erreur('Corps JSON invalide.', 400);
            return;
        }

        if (!is_array($donnees)) {
            ReponseJson::erreur('Corps JSON invalide.', 400);
            return;
        }

        try {
            $visite = new DonneesVisite(
                chemin:          (string) ($donnees['chemin'] ?? ''),
                titre:           isset($donnees['titre']) ? (string) $donnees['titre'] : null,
                domaineReferent: $this->extraireDomaine($donnees['referent'] ?? null),
                ip:              $this->ipDuVisiteur(),
                userAgent:       (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            );

            $this->service->collecter($visite);
        } catch (InvalidArgumentException $e) {
            ReponseJson::erreur($e->getMessage(), 400);
            return;
        } catch (Throwable) {
            // Pas de detail technique remonte au client.
            ReponseJson::erreur('Erreur interne.', 500);
            return;
        }

        // 204 No Content : le tracker n'a rien a faire de la reponse.
        http_response_code(204);
    }

    private function origineAutorisee(string $origine): bool
    {
        // L'origine doit correspondre au domaine du site (avec ou sans www).
        $hote = parse_url($origine, PHP_URL_HOST) ?? '';
        $hote = preg_replace('/^www\./', '', strtolower($hote));

        return $hote === $this->domaineSite;
    }

    private function extraireDomaine(mixed $referentBrut): ?string
    {
        if (!is_string($referentBrut) || $referentBrut === '') {
            return null;
        }
        // Si le client envoie une URL complete, on n'en garde que le domaine.
        $hote = parse_url($referentBrut, PHP_URL_HOST);

        return $hote !== null ? strtolower($hote) : strtolower($referentBrut);
    }

    private function ipDuVisiteur(): string
    {
        // En production derriere un reverse-proxy, prendre X-Forwarded-For.
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

        // X-Forwarded-For peut contenir une liste : on prend la premiere.
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[1] ?? $ip);
        }

        return $ip !== '' ? $ip : '0.0.0.0';
    }
}
