<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Http;

/**
 * Authentification par cle d'API partagee.
 *
 * Le client (le plugin WordPress, ou tout autre outil interne) envoie
 * la cle dans l'en-tete Authorization : "Bearer <cle>". La comparaison
 * utilise hash_equals pour resister aux attaques par mesure du temps
 * de reponse (timing attacks).
 */
final class AuthentificationClef
{
    public function __construct(private readonly string $cleAttendue)
    {
    }

    /**
     * Verifie la cle presente dans l'en-tete Authorization. Si elle
     * est absente ou incorrecte, repond 401 et interrompt l'execution.
     */
    public function exiger(): void
    {
        $entete = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($entete, 'Bearer ')) {
            ReponseJson::erreur('Authentification requise.', 401);
            exit;
        }

        $cleFournie = substr($entete, 7);

        if (!hash_equals($this->cleAttendue, $cleFournie)) {
            ReponseJson::erreur('Cle d\'API invalide.', 401);
            exit;
        }
    }
}
