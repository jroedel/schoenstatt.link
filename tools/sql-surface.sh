#!/usr/bin/env bash
#
# Records the SQL the application issues, into test/Db/sql-surface.txt.
#
#     ./tools/sql-surface.sh            # rewrite the recording
#     ./tools/sql-surface.sh --check    # regenerate into a temp file and diff, exit 1 on drift
#
# Two drivers, because neither reaches what the other does. The HTTP pass over
# test/Db/sql-surface-urls.txt records what a visitor's page actually asks the database,
# through the web SAPI and the persistent cache. The integration suite reaches the write
# paths and the tables no anonymous page touches, and it contributes about seven times as
# many statement shapes. Measured 2026-09-21: HTTP 27 shapes, integration 191, together 201.
#
# Why a script and not a test: capturing requires `SET GLOBAL general_log`, which needs
# privileges the application's database user does not have and should never be given, and a
# full HTTP pass through the capsule. So this is a tool that writes a reviewed artefact, the
# same arrangement as tools/acl-table.php and docs/acl-baseline.json.
#
# The persistent cache is flushed over HTTP first, and that is not optional: APCu belongs to
# the SAPI that created it, a warm cache answers reads the database never sees, and a
# recording taken warm is a recording of whatever happened to be cached that minute.
set -uo pipefail

cd "$(dirname "$0")/.."

URLS=test/Db/sql-surface-urls.txt
OUT=test/Db/sql-surface.txt
BASE=${SQL_SURFACE_BASE:-http://localhost:8080}
KEY=${SQL_SURFACE_KEY:-local-dev-api-key}
CHECK=0
[ "${1:-}" = "--check" ] && CHECK=1

db() { docker compose exec -T db mariadb -uroot -proot -N -B -e "$1" 2>/dev/null; }

if ! docker compose ps --format '{{.Service}} {{.State}}' 2>/dev/null | grep -q '^db running'; then
    echo "the capsule is not running: docker compose up -d" >&2
    exit 1
fi

flush() {
    local status
    status=$(curl -sS -o /dev/null -w '%{http_code}' -H "X-Api-Key: $KEY" "$BASE/sm/clear-persistent-cache")
    [ "$status" = "200" ] && return 0
    echo "could not flush the persistent cache (status $status); a warm cache hides reads" >&2
    return 1
}

flush || exit 1
db "SET GLOBAL log_output='TABLE'; TRUNCATE TABLE mysql.general_log; SET GLOBAL general_log='ON';"

COUNT=0
while read -r LINE; do
    case "$LINE" in ''|\#*) continue ;; esac
    # Before *each* page, not once at the start. The persistent cache is what the
    # application uses to not ask the database, so the first page warms it and every page
    # after it reads APCu instead — which both shrinks the recording and makes it depend on
    # the order of this file. Flushing per page is what makes the result a property of the
    # pages rather than of their sequence.
    flush || exit 1
    STATUS=$(curl -sS -o /dev/null -w '%{http_code}' "$BASE$LINE")
    case "$STATUS" in
        200|30[0-9]) COUNT=$((COUNT + 1)) ;;
        *) echo "  warn  $LINE answered $STATUS; its queries are missing from the recording" >&2 ;;
    esac
done < "$URLS"

echo "captured $COUNT pages"

# The write paths and every table no anonymous page reaches. Its output goes nowhere: what
# is wanted is the SQL it issues on the way.
echo "running the integration suite"
docker compose exec -T app php -d memory_limit=1G tools/phpunit.phar --testsuite integration >/dev/null 2>&1

db "SET GLOBAL general_log='OFF';"

RAW=$(mktemp)
trap 'rm -f "$RAW"' EXIT
db "SELECT command_type, REPLACE(REPLACE(CONVERT(argument USING utf8mb4), '\t', ' '), '\n', ' ') FROM mysql.general_log ORDER BY event_time" > "$RAW"

TMP=$(mktemp)
docker compose exec -T app php tools/sql-normalise.php < "$RAW" > "$TMP"
SHAPES=$(wc -l < "$TMP" | tr -d ' ')

if [ "$SHAPES" -lt 150 ]; then
    echo "only $SHAPES statement shapes captured — the log was not recording" >&2
    rm -f "$TMP"
    exit 1
fi

if [ "$CHECK" = "1" ]; then
    if diff -u "$OUT" "$TMP"; then
        echo "ok    $SHAPES statement shapes, unchanged"
        rm -f "$TMP"
        exit 0
    fi
    echo "FAIL  the SQL the application issues has changed; read every line above" >&2
    rm -f "$TMP"
    exit 1
fi

mv "$TMP" "$OUT"
echo "wrote $SHAPES statement shapes to $OUT"
