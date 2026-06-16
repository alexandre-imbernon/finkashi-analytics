FROM php:8.3-apache

# Outils systeme necessaires aux extensions PHP et a Composer.
RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libzip-dev \
        libssl-dev \
        pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Extensions PHP necessaires a l'application.
RUN docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip

# Extension MongoDB (branche NoSQL).
# Installee via PECL puis activee.
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# Activation du module rewrite pour le routage via .htaccess.
RUN a2enmod rewrite

# Composer en standalone.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
