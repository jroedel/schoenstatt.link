# Time capsule. PHP_VERSION selects the target:
#   7.4 — production as verified 2026-08-01 (the pre-rung-4 baseline)
#   8.3 — the rung-4 target (production offers 5.3 through 8.5)
# Switch with PHP_VERSION in .env, then `docker compose build && docker compose up -d`.
# vendor/ is bind-mounted and platform-specific, so re-run composer install after
# switching — the two versions resolve to different package sets.
ARG PHP_VERSION=7.4
FROM php:${PHP_VERSION}-apache

# APCu 5.1.22 is the last release that builds on 7.4; 8.x needs 5.1.24+.
ARG APCU_VERSION=5.1.22

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
    && pecl install "apcu-${APCU_VERSION}" \
    && docker-php-ext-enable apcu \
    && echo "apc.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini \
    # 32M default is too small for this app's entity caches; exhaustion used to
    # trigger the SionCacheTrait fatal-200 wedge and still costs cache misses
    && echo "apc.shm_size=256M" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini \
    && a2enmod rewrite headers deflate expires \
    && rm -rf /var/lib/apt/lists/*

# Make Apache workers run with the host user's uid/gid so the app can write to
# the bind-mounted data/ dir, and new files stay owned by the host user.
ARG HOST_UID=1000
ARG HOST_GID=1000
RUN usermod -o -u "${HOST_UID}" www-data && groupmod -o -g "${HOST_GID}" www-data

COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Resource ceilings — keep these as the last layers so editing a limit does not
# invalidate the extension-build cache above. See the files themselves for the
# reasoning; the matching cgroup limits live in docker-compose.yml.
COPY apache-limits.conf /etc/apache2/conf-available/zz-limits.conf
RUN a2enconf zz-limits
COPY php-limits.ini /usr/local/etc/php/conf.d/zz-limits.ini

WORKDIR /var/www/schoenstatt.link
