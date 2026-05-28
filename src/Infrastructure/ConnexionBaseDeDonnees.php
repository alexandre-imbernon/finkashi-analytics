<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Infrastructure;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Fabrique de connexion a la base de donnees.
 *
 * Centralise la creation de l'objet PDO et sa configuration de
 * securite. Regrouper cette logique en un seul endroit garantit que
 * toutes les connexions de l'application partagent les memes reglages
 * (gestion des erreurs par exceptions, requetes reellement preparees,
 * encodage utf8mb4).
 */
final class ConnexionBaseDeDonnees
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $hote,
        private readonly string $port,
        private readonly string $nomBase,
        private readonly string $utilisateur,
        private readonly string $motDePasse,
    ) {
    }

    /**
     * Retourne la connexion PDO, en la creant a la premiere demande
     * (connexion paresseuse : on ne se connecte que si necessaire).
     */
    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $this->hote,
            $this->port,
            $this->nomBase,
        );

        try {
            $this->pdo = new PDO($dsn, $this->utilisateur, $this->motDePasse, [
                // Toute erreur SQL leve une exception : on ne laisse
                // jamais passer une erreur silencieusement.
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Les resultats sont retournes sous forme de tableaux
                // associatifs par defaut.
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Desactive l'emulation : les requetes preparees sont
                // reellement preparees cote serveur MySQL, ce qui
                // renforce la protection contre l'injection SQL.
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // On ne divulgue pas les details de connexion dans le
            // message remonte a l'appelant.
            throw new RuntimeException(
                'Impossible de se connecter a la base de donnees.',
                (int) $e->getCode(),
                $e,
            );
        }

        return $this->pdo;
    }
}
