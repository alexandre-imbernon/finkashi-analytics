# Finkashi Analytics

Outil de mesure d'audience web auto-hébergé, sans cookie, respectueux de la vie privée.
Développé dans le cadre du titre professionnel **Développeur web et web mobile (DWWM)**.

## Présentation

Le projet collecte, historise, agrège et restitue les statistiques de fréquentation
du site finkashi.fr, sans cookie ni service tiers payant. Les statistiques sont
consultables depuis le tableau de bord WordPress.

## Environnement de développement

L'environnement est conteneurisé avec Docker afin de reproduire localement les
conditions de l'hébergement de production (PHP 8.3, MySQL), tout en isolant les
outils du poste de travail.

### Prérequis

- Docker Desktop
- Git

### Démarrage

```bash
# Construire et lancer les conteneurs
docker compose up -d --build

# Installer les dépendances PHP (Composer s'exécute dans le conteneur)
docker compose exec php composer install
```

### Services disponibles

| Service       | URL                     | Rôle                        |
|---------------|-------------------------|-----------------------------|
| Application   | http://localhost:8080   | Point d'entrée PHP          |
| phpMyAdmin    | http://localhost:8081   | Administration de la base   |
| MySQL         | localhost:3306          | Base de données             |

### Vérification

Ouvrir http://localhost:8080 : la page confirme la version de PHP et l'état
de la connexion à MySQL.

## Structure du projet

```
finkashi-analytics/
├── docker/              Configuration des conteneurs
│   └── php/Dockerfile
├── public/              Racine web exposée (front controller)
│   ├── index.php
│   └── .htaccess
├── src/                 Code source (PSR-4 : Finkashi\Analytics\)
│   ├── Domain/          Logique métier (entités, règles)
│   ├── Application/     Cas d'usage, services applicatifs
│   └── Infrastructure/  Accès aux données, services techniques
├── config/              Configuration applicative
├── database/
│   └── migrations/      Scripts de création/évolution du schéma
├── scripts/             Scripts utilitaires (migration, agrégation…)
├── storage/
│   └── archives/        Archives d'événements (hors versionnement)
├── tests/               Tests unitaires (PHPUnit)
├── composer.json
└── docker-compose.yml
```

L'architecture suit une séparation en couches : la couche **Domain** contient la
logique métier indépendante de toute technologie, la couche **Application**
orchestre les cas d'usage, et la couche **Infrastructure** gère les accès
techniques (base de données, géolocalisation).

## Déploiement

Le code est déployé sur l'hébergement mutualisé OVH via Git et une procédure
automatisée (voir `scripts/` et la documentation de déploiement).
```
