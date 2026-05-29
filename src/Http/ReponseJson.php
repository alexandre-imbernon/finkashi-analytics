<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Http;

/**
 * Utilitaire de production de reponses HTTP JSON.
 *
 * Centralise les en-tetes (Content-Type, encodage) et le formatage,
 * pour garantir que toutes les reponses de l'API ont la meme forme.
 */
final class ReponseJson
{
    /**
     * @param array<mixed>|list<mixed> $donnees
     */
    public static function envoyer(mixed $donnees, int $statut = 200): void
    {
        http_response_code($statut);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function erreur(string $message, int $statut): void
    {
        self::envoyer(['erreur' => $message], $statut);
    }
}
