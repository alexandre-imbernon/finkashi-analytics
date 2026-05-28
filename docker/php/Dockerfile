# ---------------------------------------------------------------
# Image PHP 8.3 + Apache, alignée sur l'environnement OVH.
# On reste volontairement sobre : seules les extensions
# disponibles sur le mutualisé sont activées, pour garantir que
# le code développé ici fonctionnera à l'identique en production.
# ---------------------------------------------------------------
FROM php:8.3-apache

# Outils système requis par Composer (git pour les sources,
# unzip pour les archives) puis extensions PHP du projet.
RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Activation de la réécriture d'URL Apache (routage du point
# d'entrée unique de l'application).
RUN a2enmod rewrite

# Installation de Composer depuis l'image officielle.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# La racine web pointe sur le dossier public/ : seul ce dossier
# est exposé, le reste du code reste inaccessible depuis le web.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html