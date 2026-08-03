#!/usr/bin/env bash

# The idea of this file is to simplify development setup on a new machine

# first check that the files don't already exist
if [ ! -f phploy.ini ]; then
  cp example.phploy phploy.ini
fi
#this file is for storing ssh authentication
if [ ! -f .phploy ]; then
  cp hideme.phploy .phploy
fi
chmod 740 .phploy

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
