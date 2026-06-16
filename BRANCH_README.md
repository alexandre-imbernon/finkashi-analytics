# Branche `feature/nosql-archives`

Cette branche démontre la capacité de l'architecture en couches de Finkashi
Analytics à accueillir une persistance alternative **sans aucune modification
de la couche métier**. Elle illustre concrètement le principe d'inversion
des dépendances (le « D » de SOLID) et la pratique appelée *polyglot
persistence*.

## Contexte

Le titre RNCP DWWM (RNCP37674) exige la maîtrise de composants d'accès aux
données **SQL et NoSQL**. Le projet principal Finkashi Analytics est déployé
sur un hébergement mutualisé OVH Starter qui ne propose pas de service
MongoDB. Cette branche permet donc de **démontrer la compétence sans la
déployer en production**, ce qui est un choix d'architecture défendable et
courant en industrie.

La branche reste isolée de `main` ; elle est destinée à servir de support
de démonstration et de soutenance, pas à fusionner.

## Modifications apportées

### Couche application

Ajout d'une interface `ArchiveStockage` dans `src/Application/Persistance/`,
qui exprime le contrat de persistance sans préjuger de la technologie utilisée.

`ServiceArchivage` est modifié pour dépendre de cette interface et non plus
d'une classe concrète. **C'est le seul changement métier de toute la branche.**

### Couche infrastructure

Deux implémentations cohabitent désormais :

- `ArchiveRepository` (existant) : table MySQL.
- `ArchiveRepositoryMongo` (nouveau) : collection MongoDB.

La `Fabrique` choisit l'une ou l'autre selon la variable de configuration
`APP_ARCHIVE_STORE` (`mysql` ou `mongo`). Aucun code applicatif n'est touché
par ce choix.

### Environnement de développement

`docker-compose.yml` reçoit deux nouveaux services :

- `mongo` (image `mongo:7`) — la base MongoDB.
- `mongo-express` (image `mongo-express:1`) — interface d'administration,
  accessible sur `http://localhost:8082`.

Le `Dockerfile` PHP installe l'extension `mongodb` via PECL.

Le `composer.json` ajoute la dépendance `mongodb/mongodb` pour l'API
PHP de haut niveau.

## Activation de la persistance MongoDB

```bash
# Recréer le conteneur PHP avec la nouvelle extension MongoDB
docker compose build php
docker compose up -d

# Installer la nouvelle dépendance Composer
docker compose exec php composer require mongodb/mongodb
```

Puis ajouter dans `.env` :

```
APP_ARCHIVE_STORE=mongo
```

Et recréer le conteneur PHP pour qu'il prenne la variable :

```bash
docker compose up -d --force-recreate php
```

À partir de ce moment, chaque exécution du cron quotidien enregistrera
les métadonnées d'archive dans MongoDB plutôt que dans MySQL. Le reste
de l'application est strictement identique.

## Vérification

Après une exécution du cron, l'interface mongo-express
(`http://localhost:8082`) permet de voir la base `finkashi_analytics`
et sa collection `archives` se peupler. Chaque document a la structure
suivante :

```json
{
  "_id": "ObjectId(...)",
  "periode_debut": "1970-01-01",
  "periode_fin":   "2026-04-05",
  "fichier":       "evenements-avant-2026-04-05-20260605030147.json.gz",
  "nb_evenements": 142,
  "cree_le":       "2026-06-05T03:01:47+00:00"
}
```

## Tests

Un test d'intégration (`tests/Application/ServiceArchivageAvecInterfaceTest.php`)
vérifie que `ServiceArchivage` fonctionne avec une implémentation factice
de l'interface. Ce test passe sans dépendre ni de MySQL ni de MongoDB, ce
qui prouve formellement le découplage.

```bash
docker compose exec php vendor/bin/phpunit tests/Application/ServiceArchivageAvecInterfaceTest.php
```

## Pour aller plus loin

D'autres parties du système pourraient bénéficier d'une persistance NoSQL :

- Les **événements bruts** eux-mêmes (table `evenement`), qui sont
  append-only et accédés par date, sont un cas d'usage classique pour
  une base time-series comme MongoDB ou ClickHouse.
- Les **règles d'alerte**, dont le schéma pourrait évoluer (ajout de
  notifications, de conditions composées) sans nécessiter de migration
  SQL, sont également un bon candidat.

Ces extensions ne sont pas implémentées dans cette branche.
