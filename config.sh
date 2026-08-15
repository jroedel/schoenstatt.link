#!/usr/bin/env bash

# The idea of this file is to simplify development setup on a new machine

# Deploy configuration and credentials — one file, replacing phploy.ini and
# .phploy. It holds a full-DDL database password, so the mode is not optional.
if [ ! -f .deploy.local ]; then
  cp .deploy.local.dist .deploy.local
fi
chmod 600 .deploy.local

# phploy is retired (see docs/DEPLOY.md); these two are seeded only while the
# old pipeline is still on disk as the way back from the first atomic deploy.
if [ ! -f phploy.ini ] && [ -f phploy.ini.dist ]; then
  cp phploy.ini.dist phploy.ini
fi
if [ ! -f .phploy ] && [ -f .phploy.dist ]; then
  cp .phploy.dist .phploy
  chmod 740 .phploy
fi

# copy a sample local.php file
if [ ! -f config/autoload/local.php ]; then
  cp config/autoload/local.php.dist config/autoload/local.php
fi
if [ ! -f config/autoload/laminas-developer-tools.local.php ]; then
  cp config/autoload/laminas-developer-tools.local.php.dist config/autoload/laminas-developer-tools.local.php
fi
if [ ! -f config/autoload/cache.local.php ]; then
  cp config/autoload/cache.local.php.dist config/autoload/cache.local.php
fi

# fetch the pinned dev-tool phars (phpunit, phpstan) — untracked, capsule/CI only
bash tools/get-phars.sh

#self update composer
php composer.phar self-update

# install packages
php composer.phar install

# make sure important data directories exist
mkdir -p data/logs
mkdir -p data/config
# Twig's compile cache. App\Twig\TwigFactory falls back to compiling in memory when
# this is missing or unwritable rather than failing, so this is an optimisation, not
# a prerequisite — but a deployment that never creates it pays for a recompile on
# every request.
mkdir -p data/cache/twig
