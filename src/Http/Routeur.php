<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Http;

/**
 * Routeur minimal.
 *
 * Enregistre des associations (methode HTTP + chemin) -> action, puis
 * dispatche une requete entrante vers l'action correspondante.
 * Le chemin peut contenir des parametres nommes prefixes par ":".
 */
final class Routeur
{
    /** @var list<array{methode:string, motif:string, parametres:list<string>, action:callable}> */
    private array $routes = [];

    public function ajouter(string $methode, string $chemin, callable $action): void
    {
        // Transforme "/stats/pages/:limite" en regex avec capture nommee.
        $parametres = [];
        $motif = preg_replace_callback(
            '/:([a-zA-Z_]+)/',
            static function (array $m) use (&$parametres): string {
                $parametres[] = $m[1];
                return '([^/]+)';
            },
            $chemin,
        );
        $motif = '#^' . $motif . '$#';

        $this->routes[] = [
            'methode'    => strtoupper($methode),
            'motif'      => $motif,
            'parametres' => $parametres,
            'action'     => $action,
        ];
    }

    /**
     * Dispatche la requete. Retourne true si une route a matche, false
     * sinon (404 a la charge de l'appelant).
     */
    public function dispatcher(string $methode, string $chemin): bool
    {
        $methode = strtoupper($methode);

        foreach ($this->routes as $route) {
            if ($route['methode'] !== $methode) {
                continue;
            }
            if (preg_match($route['motif'], $chemin, $captures) === 1) {
                array_shift($captures); // retire la correspondance complete
                $arguments = array_combine($route['parametres'], $captures) ?: [];
                ($route['action'])($arguments);
                return true;
            }
        }

        return false;
    }
}
