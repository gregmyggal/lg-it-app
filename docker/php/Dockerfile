FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
        libzip-dev unzip git libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY apache-docroot.conf /etc/apache2/conf-available/docroot.conf
RUN a2enconf docroot
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
