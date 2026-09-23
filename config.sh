#!/usr/bin/env bash

# The idea of this file is to simplify development setup on a new machine

# Deploy configuration and credentials — one file, replacing phploy.ini and
# .phploy. It holds a full-DDL database password, so the mode is not optional.
if [ ! -f .deploy.local ]; then
  cp .deploy.local.dist .deploy.local
fi
chmod 600 .deploy.local

# phploy was retired 2026-08-16 (docs/DEPLOY.md). If this machine still has its
# credential files lying about, say so once rather than deleting them: they hold
# secrets, and whether they are worth keeping as a record is the operator's call.
if [ -f phploy.ini ] || [ -f .phploy ]; then
  echo "NOTE: phploy.ini/.phploy are left over from the retired phploy pipeline."
  echo "      Nothing reads them now. They contain credentials — delete them when ready."
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

# Seed the capsule's database from the two tracked files, when nothing else has.
#
# docker-compose mounts database/dumps/ as the db container's initdb directory, and
# that directory is gitignored because what normally sits in it is a production
# export. Anyone without one — which is everyone outside the project — had no way to
# bring the capsule up at all. database/ci/base-schema.sql is the application's DDL
# with no rows in it and database/ci/ci-seed.sql is a small set of invented rows, and
# the two together are what the GitHub integration job builds its database from on
# every push.
#
# The numeric prefixes are load-bearing: initdb runs files in alphabetical order and
# the seed has to follow the schema.
#
# Only when no .sql is there already. A machine holding a real export must not have it
# joined by a second, conflicting set of CREATE TABLEs — and the export is not
# reproducible, so the safe default is to do nothing and say so.
mkdir -p database/dumps
if ! ls database/dumps/*.sql >/dev/null 2>&1; then
  cp database/ci/base-schema.sql database/dumps/01-base-schema.sql
  cp database/ci/ci-seed.sql     database/dumps/02-ci-seed.sql
  echo "NOTE: seeded database/dumps/ from database/ci/ — the invented corpus, not production."
  echo "      The unit and integration suites pass against it; smoke and fuzz assume the"
  echo "      real catalogue. See CONTRIBUTING.md."
else
  echo "NOTE: database/dumps/ already holds .sql files — left untouched."
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
