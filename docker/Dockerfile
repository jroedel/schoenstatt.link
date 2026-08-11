# Time capsule. PHP_VERSION selects the target:
#   7.4 — production as verified 2026-08-01 (the pre-rung-4 baseline)
#   8.3 — the rung-4 target (production offers 5.3 through 8.5)
#   8.4 — production from rung 4b (2026-08-04) until 2026-08-11
#   8.5 — production as of 2026-08-11, and what this capsule serves
# Switch with PHP_VERSION in .env, then `docker compose build && docker compose up -d`.
# vendor/ is bind-mounted and platform-specific, so re-run composer install after
# switching — the two versions resolve to different package sets.
#
# Note the base image's Debian release moves under us: php:7.4-apache is still
# bullseye, but 8.4 and 8.5 are both trixie now (8.4 was bookworm when rung 4b
# was measured). That matters for one thing in particular — trixie carries ICU
# 76.1 while production carries 72.1 on its 8.4 *and* its 8.5 build (measured
# 2026-08-11) — so a rebuilt capsule formats dates against newer locale data than
# production, the opposite of the drift recorded in docs/BACKLOG.md. Now that the
# PHP versions match exactly, this is the one way the capsule still fails to
# reproduce production. composer's lib-icu pin says 72.1 and is correct: it
# describes production, not this image.
ARG PHP_VERSION=7.4
FROM php:${PHP_VERSION}-apache

# APCu 5.1.22 is the last release that builds on 7.4; 8.x needs 5.1.24+.
ARG APCU_VERSION=5.1.22

# libfreetype-dev, not libfreetype6-dev: the versioned name is a transitional
# package that trixie dropped, so it breaks the 8.4 and 8.5 builds. The
# unversioned name resolves on bullseye too, so one spelling covers every rung.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip gd \
    # OPcache is built into the base image on 8.4 and 8.5 — already loaded and
    # already enabled — and on 8.5 it is compiled in statically, with no shared
    # module to build. Asking docker-php-ext-install for it there produces no
    # .so and dies with `cp: cannot stat 'modules/*'`, which reads like a
    # missing source file rather than "you already have this". 7.4 does not
    # ship it, so the install still has to happen on that rung. Hence the test
    # rather than either hardcoded answer: the base images changed under us once
    # (8.4 was bookworm when rung 4b was measured, it is trixie now) and can
    # change again.
    && { php -m | grep -q 'Zend OPcache' || docker-php-ext-install -j"$(nproc)" opcache; } \
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
