<?php

declare(strict_types=1);

namespace Finkashi\Analytics\Application;

use Finkashi\Analytics\Infrastructure\EvenementRepository;
use Finkashi\Analytics\Infrastructure\StatistiqueRepository;

/**
 * Service d'agregation journaliere.
 *
 * Transforme les evenements bruts d'une journee en statistiques
 * agregees, reparties selon les quatre axes d'analyse (page, source,
 * canal, pays). Une fois agregee, une journee n'a plus besoin de ses
 * evenements bruts : c'est ce service qui permet la strategie de
 * retention (donnees brutes ephemeres, agregats permanents).
 *
 * L'operation est idempotente : agreger deux fois le meme jour produit
 * le meme resultat, sans doublon.
 */
final class ServiceAgregation
{
    public function __construct(
        private readonly EvenementRepository $evenements,
        private readonly StatistiqueRepository $statistiques,
    ) {
    }

    /**
     * Calcule et enregistre tous les agregats d'une journee.
     *
     * @param string $jour Date au format 'Y-m-d'.
     * @return array{pages:int, sources:int, canaux:int, pays:int, global:int}
     *         Nombre d'agregats ecrits par axe (utile pour le suivi).
     */
    public function agregerJour(string $jour): array
    {
        $compteurs = ['pages' => 0, 'sources' => 0, 'canaux' => 0, 'pays' => 0, 'global' => 0];

        // Agregation globale du jour : visiteurs uniques (tout axe
        // confondu) et pages vues totales. Doit etre faite en premier
        // car c'est la source de verite pour les KPI du dashboard.
        $global = $this->evenements->agregerGlobal($jour);
        $this->statistiques->enregistrerStatGlobal($jour, $global['visiteurs'], $global['pages_vues']);
        $compteurs['global'] = 1;

        foreach ($this->evenements->agregerParPage($jour) as $ligne) {
            $this->statistiques->enregistrerStatPage(
                $jour,
                $ligne['page_id'],
                $ligne['pages_vues'],
                $ligne['visiteurs'],
            );
            $compteurs['pages']++;
        }

        foreach ($this->evenements->agregerParSource($jour) as $ligne) {
            $this->statistiques->enregistrerStatSource(
                $jour,
                $ligne['source_id'],
                $ligne['visiteurs'],
            );
            $compteurs['sources']++;
        }

        foreach ($this->evenements->agregerParCanal($jour) as $ligne) {
            $this->statistiques->enregistrerStatCanal(
                $jour,
                $ligne['canal'],
                $ligne['visiteurs'],
            );
            $compteurs['canaux']++;
        }

        foreach ($this->evenements->agregerParPays($jour) as $ligne) {
            $this->statistiques->enregistrerStatPays(
                $jour,
                $ligne['pays'],
                $ligne['visiteurs'],
            );
            $compteurs['pays']++;
        }

        return $compteurs;
    }
}
