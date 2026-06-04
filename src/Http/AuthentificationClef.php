<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Http;

/**
 * Authentification par cle d'API partagee.
 *
 * La cle peut etre transmise de deux manieres :
 *  - en-tete Authorization : "Bearer <cle>" (standard, utilise en dev) ;
 *  - en-tete X-Api-Key : "<cle>" (utilise en production OVH, ou
 *    l'en-tete Authorization est filtree par l'hebergement mutualise).
 *
 * Le code essaie les deux dans cet ordre. La comparaison utilise
 * hash_equals pour resister aux attaques par mesure du temps de
 * reponse (timing attacks).
 */
final class AuthentificationClef
{
    public function __construct(private readonly string $cleAttendue)
    {
    }

    /**
     * Verifie la cle. Si elle est absente ou incorrecte, repond 401
     * et interrompt l'execution. Ne revele aucune information sur
     * la cle attendue ou sur ce qui a ete recu : message generique.
     */
    public function exiger(): void
    {
        $cleFournie = $this->lireCle();

        if ($cleFournie === '' || !hash_equals($this->cleAttendue, $cleFournie)) {
            ReponseJson::erreur('Authentification requise.', 401);
            exit;
        }
    }

    /**
     * Recupere la cle d'API a partir des en-tetes disponibles.
     * Tente plusieurs sources pour resister aux differents
     * environnements d'execution (Docker en dev, Apache mutualise
     * en prod).
     */
    private function lireCle(): string
    {
        // Source 1 : en-tete X-Api-Key (production OVH).
        $xKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($xKey === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $xKey = $headers['X-Api-Key'] ?? $headers['x-api-key'] ?? '';
        }
        if ($xKey !== '') {
            return trim((string) $xKey);
        }

        // Source 2 : en-tete Authorization avec prefixe "Bearer ".
        $auth = $_SERVER['HTTP_AUTHORIZATION']
             ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
             ?? '';
        if ($auth === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (str_starts_with((string) $auth, 'Bearer ')) {
            return substr((string) $auth, 7);
        }

        return '';
    }
}
