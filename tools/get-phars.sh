#!/usr/bin/env bash
# Fetch the pinned dev-tool phars. Capsule and CI run these; production
# never does — which is why they stay untracked (contrast composer.phar:
# tracked because the deploy runs it on the server, and phploy only syncs
# tracked files).
#
# Usage: tools/get-phars.sh [phpunit|phpstan ...]   (default: all)
#
# To upgrade a tool: bump its VERSION and SHA256 below, run this script,
# commit the diff. The sha256 pins the exact reviewed binary — a download
# that doesn't match is refused, not installed.
set -euo pipefail
cd "$(dirname "$0")/.."

PHPUNIT_VERSION=12.5.33
PHPUNIT_SHA256=c8af6400e0cd81da027e2b4d6387733983f1f97f64fe80ae639c84b421e9cd55

PHPSTAN_VERSION=1.12.13
PHPSTAN_SHA256=d366f8eff842ce1195b4caa5827e0b56a85c4b8a60aa83e471c4e16a2bb45fc8

fetch() { # <dest> <url> <sha256>
    local dest=$1 url=$2 sha=$3 tmp
    if [ -f "$dest" ] && echo "$sha  $dest" | sha256sum -c --quiet - 2>/dev/null; then
        echo "$dest is already at the pinned version"
        return
    fi
    tmp=$(mktemp "$dest.XXXXXX")
    curl -sSfL -o "$tmp" "$url"
    if ! echo "$sha  $tmp" | sha256sum -c --quiet -; then
        rm -f "$tmp"
        echo "ABORT: $url does not match its pinned sha256 — not installing." >&2
        exit 1
    fi
    chmod +x "$tmp"
    mv "$tmp" "$dest"
    echo "installed $dest"
}

TOOLS=("$@")
[ "${#TOOLS[@]}" -eq 0 ] && TOOLS=(phpunit phpstan)
for tool in "${TOOLS[@]}"; do
    case "$tool" in
        phpunit)
            fetch tools/phpunit.phar \
                "https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar" \
                "$PHPUNIT_SHA256"
            ;;
        phpstan)
            fetch tools/phpstan.phar \
                "https://github.com/phpstan/phpstan/releases/download/${PHPSTAN_VERSION}/phpstan.phar" \
                "$PHPSTAN_SHA256"
            ;;
        *)
            echo "unknown tool: $tool (expected phpunit and/or phpstan)" >&2
            exit 1
            ;;
    esac
done
