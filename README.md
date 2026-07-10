# Finkashi Analytics

Outil de mesure d'audience web auto-hébergé, sans cookie, conforme RGPD par
conception. Développé dans le cadre du titre professionnel **Développeur web
et web mobile (RNCP niveau 5)**, et utilisé en production sur
[finkashi.fr](https://finkashi.fr).

![Tableau de bord Finkashi Analytics](docs/dashboard.png)

---

## Présentation

Finkashi Analytics est une solution de mesure d'audience pensée comme une
alternative légère à Google Analytics, Matomo ou Plausible, taillée pour les
petits sites éditoriaux qui veulent maîtriser leurs données sans dépendre
d'un service tiers.

L'outil se compose de deux briques :

- une **API REST** en PHP 8.3 (architecture en couches, sans framework),
  responsable de la collecte, de l'agrégation et de la restitution des
  statistiques ;
- un **plugin WordPress** qui injecte le tracker côté visiteur et offre une
  interface d'administration intégrée à WordPress (dashboard, alertes,
  réglages).

Le projet est déployé sur un hébergement mutualisé OVH (sans SSH, base MySQL
unique partagée avec WordPress), ce qui a guidé plusieurs choix d'architecture
détaillés plus bas.

## Fonctionnalités

- **Collecte cookieless** : aucune donnée individuelle stockée, aucun bandeau
  consentement nécessaire.
- **Déduplication par visiteur et par jour** : une même page consultée
  plusieurs fois par le même visiteur au cours d'une journée n'est comptée
  qu'une seule fois, via un registre `localStorage` renouvelé chaque jour.
- **Dashboard intégré** à l'administration WordPress : trafic quotidien,
  pages populaires, sources, répartition géographique, KPIs synthétiques.
- **Alertes paramétrables** sur seuils journaliers (chute de trafic, pic de
  pages vues, etc.) avec historique des déclenchements.
- **Stratégie chaud / froid** : événements bruts conservés 60 jours puis
  archivés et purgés ; agrégats journaliers conservés indéfiniment.
- **Géolocalisation** par IP via la base MaxMind GeoLite2, intégralement
  côté serveur (jamais transmise au navigateur).
- **Cron quotidien** d'agrégation, d'évaluation des alertes et d'archivage,
  déclenché par un service externe (cron-job.org) appelant un endpoint HTTP
  dédié, pour fonctionner sur mutualisé sans SSH.

## RGPD par conception

Le mécanisme d'identification des visiteurs est l'un des points centraux du
projet :

```
hash_visiteur = SHA-256( IP + User-Agent + Domaine + sel_du_jour )
```

Le **sel quotidien** change toutes les nuits. Conséquence : un même visiteur
garde la même empreinte pendant une journée (ce qui permet de le compter
comme unique), mais **ne peut plus être reconnu d'un jour à l'autre**. La
ré-identification inter-journées est techniquement impossible.

Aucun cookie n'est déposé, aucune adresse IP n'est conservée en clair, et la
solution rentre dans le cadre de la
[délibération 2020-091 de la CNIL](https://www.cnil.fr/fr/cookies-et-autres-traceurs-la-cnil-publie-des-lignes-directrices-modificatives-et-sa-recommandation)
sur les outils de mesure d'audience exemptés de consentement.

## Agrégation des statistiques

Les événements bruts sont agrégés chaque nuit en statistiques journalières,
précalculées pour servir rapidement le tableau de bord sans requêter la
table d'événements (qui croît vite).

L'agrégation se fait sur plusieurs axes indépendants : par page, par source,
par canal (direct / recherche / référent) et par pays. À ces axes s'ajoute
une table d'agrégat **global** (`stat_jour_global`) qui stocke, pour chaque
jour, le nombre exact de visiteurs uniques et de pages vues tous axes
confondus. Cette table dédiée évite un écueil : additionner les visiteurs
uniques d'un axe (par exemple la somme par page) surcompte les visiteurs qui
apparaissent sur plusieurs valeurs de l'axe. Le total global est donc calculé
directement depuis les événements via `COUNT(DISTINCT hash_visiteur)`, et non
en sommant un agrégat existant.

## Stack technique

| Couche                 | Technologies                                            |
| ---------------------- | ------------------------------------------------------- |
| Back-end API           | PHP 8.3, PDO, Composer, PSR-4 (sans framework)          |
| Base de données        | MySQL 8 (InnoDB, utf8mb4)                               |
| Base NoSQL (démo)      | MongoDB 4.4 (branche `feature/nosql-archives`)          |
| Plugin                 | WordPress 6.7, PHP 8.3, JavaScript natif, Chart.js      |
| Géolocalisation        | MaxMind GeoLite2-Country (lecture locale)               |
| Tests                  | PHPUnit 11 (47 tests ; 48 sur la branche NoSQL)         |
| Dev local              | Docker (PHP-Apache, MySQL, phpMyAdmin, WordPress, Mongo)|
| Production             | OVH mutualisé Starter (sans SSH, base unique partagée)  |

## Architecture

L'API suit une séparation en quatre couches, inspirée de la *Clean Architecture* :

```
Domain          ──  Entités et règles métier indépendantes de toute techno
   ↑
Application     ──  Cas d'usage et services applicatifs (collecte, agrégation,
   ↑               détection d'alertes, archivage) ; définit les interfaces
   ↑               de persistance
Infrastructure  ──  Accès aux données (repositories PDO, MongoDB, géoloc)
   ↑
Http            ──  Contrôleurs, routeur, authentification, sérialisation JSON
```

Ce découpage permet de tester unitairement les services applicatifs sans
toucher à la base, et de changer de moyen de persistance sans rien modifier
au domaine. C'est ce qui rend possible la cohabitation avec WordPress
(préfixage des tables paramétrable) et l'existence d'une implémentation NoSQL
alternative (voir plus bas), sans jamais toucher au code métier.

## Persistance NoSQL (branche de démonstration)

La branche `feature/nosql-archives` démontre l'intégration d'une base **NoSQL
(MongoDB)** pour la persistance des métadonnées d'archives, en alternative à
MySQL. Elle illustre le principe d'inversion des dépendances : la couche
Application définit une interface `ArchiveStockage`, que deux implémentations
satisfont indifféremment (`ArchiveRepository` en MySQL, `ArchiveRepositoryMongo`
en MongoDB). Le choix du backend se fait par configuration
(`APP_ARCHIVE_STORE=mysql|mongo`), sans aucune modification du code métier.

Cette branche est volontairement isolée de `main` : la production tourne en
MySQL (l'hébergement mutualisé ne propose pas MongoDB). Voir le
`BRANCH_README.md` de la branche pour la procédure d'activation.

## Démarrage local

### Prérequis

- Docker Desktop
- Git

### Installation

```bash
git clone https://github.com/alexandre-imbernon/finkashi-analytics.git
cd finkashi-analytics

# Construire et lancer les conteneurs
docker compose up -d --build

# Installer les dépendances PHP (Composer s'exécute dans le conteneur)
docker compose exec php composer install
```

### Services disponibles

| Service       | URL                     | Rôle                         |
| ------------- | ----------------------- | ---------------------------- |
| API           | http://localhost:8080   | Point d'entrée PHP           |
| phpMyAdmin    | http://localhost:8081   | Administration de la base    |
| WordPress     | http://localhost:8090   | Site de test                 |
| MySQL         | localhost:3306          | Base de données              |

Sur la branche `feature/nosql-archives`, deux services supplémentaires sont
disponibles : MongoDB (port 27017) et Mongo Express (http://localhost:8082).

### Vérification

```bash
# Lancer la suite de tests
docker compose exec php vendor/bin/phpunit
# OK (47 tests, 71 assertions)

# Tester l'API (avec une clé d'API définie dans .env)
curl http://localhost:8080/stats/trafic
# {"erreur":"Authentification requise."}
```

## Déploiement en production

Le projet est conçu pour fonctionner sur un hébergement mutualisé contraint
(OVH Starter), c'est-à-dire :

- **Pas de SSH** : déploiement par FTP, dépendances Composer pré-installées
  et envoyées dans le `vendor/`.
- **Une seule base MySQL** : cohabitation avec WordPress grâce à un système
  de préfixe de tables (`finkashi_*`) injecté dans tous les repositories
  via la fabrique.
- **Pas de variables d'environnement** : les secrets sont lus depuis un
  fichier `config/secrets.php` non versionné, qui prend le relai du `.env`
  utilisé en développement.
- **Cron externe** : un endpoint dédié `/cron/quotidien` (protégé par clé)
  permet de déclencher la maintenance quotidienne. En production, il est
  appelé chaque nuit par le service cron-job.org, choisi pour son
  observabilité (historique des exécutions, notifications en cas d'échec)
  que le cron mutualisé n'offre pas.

La documentation détaillée du déploiement est dans le dossier professionnel
du titre, disponible sur demande.

## Sécurité

- **Authentification de l'API** par clé partagée sur en-tête `X-Api-Key`
  (et `Authorization: Bearer` en fallback, certains hébergeurs filtrant
  ce dernier).
- **Comparaison en temps constant** des clés via `hash_equals()` pour
  résister aux attaques temporelles.
- **Proxy AJAX** côté plugin WordPress : la clé d'API ne traverse jamais
  le navigateur. Les appels du dashboard passent par un proxy
  authentifié avec capability et nonce.
- **Whitelist d'endpoints et de méthodes HTTP** côté proxy : impossible
  d'utiliser celui-ci comme passerelle générique vers l'API.
- **Requêtes préparées partout** (PDO), pas de concaténation SQL.
- **Hash SHA-256** avec sel quotidien rotatif pour anonymiser les
  visiteurs.

## Tests

La suite couvre les couches métier (domaine, application) et HTTP :

```bash
docker compose exec php vendor/bin/phpunit
```

Les tests utilisent une base SQLite en mémoire pour rester rapides et
totalement isolés du conteneur MySQL. La branche NoSQL ajoute un test
d'intégration vérifiant que le service d'archivage fonctionne avec une
implémentation factice de l'interface de persistance, sans démarrer aucune
base — preuve du découplage.

## Structure du projet

```
finkashi-analytics/
├── config/                 Configuration et secrets (.env / secrets.php)
├── data/                   Base GeoLite2 (non versionnée)
├── database/migrations/    Schémas SQL (dev et prod)
├── docker/                 Configuration des conteneurs
├── public/                 Racine web (front controller + .htaccess)
├── scripts/                Scripts utilitaires (cron CLI)
├── src/
│   ├── Domain/             Entités et règles métier
│   ├── Application/        Services applicatifs et interfaces de persistance
│   ├── Infrastructure/     Repositories PDO / MongoDB, géolocalisation, fabrique
│   └── Http/               Contrôleurs, routeur, authentification
├── storage/archives/       Archives JSON.gz d'événements purgés
├── tests/                  Tests PHPUnit (Domain, Application, Http)
└── wordpress-plugin/
    └── finkashi-analytics/ Plugin WordPress (dashboard, alertes, tracker)
```

## Compétences couvertes (RNCP37674 — DWWM)

| CP   | Intitulé du référentiel                                        |
| ---- | ------------------------------------------------------------- |
| CP1  | Installer et configurer son environnement de travail          |
| CP2  | Maquetter des interfaces utilisateur                          |
| CP3  | Réaliser des interfaces utilisateur statiques                 |
| CP4  | Développer la partie dynamique des interfaces utilisateur     |
| CP5  | Mettre en place une base de données relationnelle             |
| CP6  | Développer des composants d'accès aux données SQL et NoSQL    |
| CP7  | Développer des composants métier côté serveur                 |
| CP8  | Documenter le déploiement d'une application dynamique          |

## Auteur

**Alexandre Imbernon**

[LinkedIn](https://www.linkedin.com/in/alexandre-imbernon/)
