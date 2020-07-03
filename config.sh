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
if [ ! -f config/autoload/zenddevelopertools.local.php ]; then
  cp config/autoload/zenddevelopertools.local.php.dist config/autoload/zenddevelopertools.local.php
fi
if [ ! -f config/autoload/cache.local.php ]; then
  cp config/autoload/cache.local.php.dist config/autoload/cache.local.php
fi

#self update composer
php composer.phar self-update

############## setup sub modules ################

# TODO clone them to parent directory

# TODO create soft links to the module directory

# create hard links of the phploy password file to their directories
ln -f .phploy ../zf2-sion-model/.phploy
ln -f .phploy ../zf2-juser/.phploy
ln -f .phploy ../zf2-jtranslate/.phploy

