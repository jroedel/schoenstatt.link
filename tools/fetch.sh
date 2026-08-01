#!/usr/bin/env bash
# Fetch pinned dev tools (phars are gitignored; run this after cloning).
# Versions are pinned to the newest releases that still RUN on PHP 7.4,
# matching the time-capsule container. Bump when the PHP floor rises.
set -euo pipefail
cd "$(dirname "$0")"

curl -sSL -o phpunit.phar https://phar.phpunit.de/phpunit-9.6.phar
curl -sSL -o phpstan.phar https://github.com/phpstan/phpstan/releases/download/1.12.13/phpstan.phar

chmod +x phpunit.phar phpstan.phar
echo "tools ready:"
php phpunit.phar --version 2>/dev/null || true
php phpstan.phar --version 2>/dev/null || true
