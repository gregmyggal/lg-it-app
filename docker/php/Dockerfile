FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
        libzip-dev unzip git \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY apache-docroot.conf /etc/apache2/conf-available/docroot.conf
RUN a2enconf docroot
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
