# Time capsule: matches production as verified 2026-08-01 (PHP 7.4.33 + Apache)
FROM php:7.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip opcache gd \
    && pecl install apcu-5.1.22 \
    && docker-php-ext-enable apcu \
    && echo "apc.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini \
    && a2enmod rewrite headers deflate expires \
    && rm -rf /var/lib/apt/lists/*

# Make Apache workers run with the host user's uid/gid so the app can write to
# the bind-mounted data/ dir, and new files stay owned by the host user.
ARG HOST_UID=1000
ARG HOST_GID=1000
RUN usermod -o -u "${HOST_UID}" www-data && groupmod -o -g "${HOST_GID}" www-data

COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/schoenstatt.link
