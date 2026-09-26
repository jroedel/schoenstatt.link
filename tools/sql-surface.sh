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

# True when any line of $2 is a substring of $1. A statement built by concatenation reaches
# tools/sql-test-literals.php as its first fragment only, so an exact match would subtract
# almost nothing.
contains_literal() {
    local shape=$1 file=$2 literal
    while IFS= read -r literal; do
        [ ${#literal} -ge 30 ] || continue
        case "$shape" in *"$literal"*) return 0 ;; esac
    done < "$file"
    return 1
}

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
    # -L, and 200 only. An entity path redirects to its slug, and a redirect counted as a
    # success records the queries of the redirect — which are none — while the page itself
    # is never fetched. That silently cost the recording every table only a show page reads.
    STATUS=$(curl -sSL -o /dev/null -w '%{http_code}' "$BASE$LINE")
    case "$STATUS" in
        200) COUNT=$((COUNT + 1)) ;;
        *) echo "  warn  $LINE answered $STATUS; its queries are missing from the recording" >&2 ;;
    esac
done < "$URLS"

echo "captured $COUNT pages"

# The HTTP pass on its own, kept: it is the one driver that issues nothing but the
# application's SQL, and it is what decides a tie below.
HTTP_RAW=$(mktemp)
db "SELECT command_type, REPLACE(REPLACE(CONVERT(argument USING utf8mb4), '\t', ' '), '\n', ' ') FROM mysql.general_log ORDER BY event_time" > "$HTTP_RAW"
HTTP=$(mktemp)
docker compose exec -T app php tools/sql-normalise.php < "$HTTP_RAW" > "$HTTP"

# The write paths and every table no anonymous page reaches. Its output goes nowhere: what
# is wanted is the SQL it issues on the way.
echo "running the integration suite"
db "TRUNCATE TABLE mysql.general_log;"
docker compose exec -T app php -d memory_limit=1G tools/phpunit.phar --testsuite integration >/dev/null 2>&1

# The console, which reaches what neither a page nor a test does. Both are read-only in
# effect: the migration runner finds its table already at the latest version and only reads
# the ledger, and the sitemap builder writes files rather than rows.
echo "running the console commands"
docker compose exec -T -u www-data app php bin/console jtranslate:migrate >/dev/null 2>&1
# Twice, so the recording holds both paths whatever state the files are in. A plain build
# asks whether anything changed and exits when nothing has, so the per-entity lastmod query
# came and went with whatever last touched those files; --force skips that question, which
# would lose the staleness query instead. The forced build runs first, so the plain one
# always finds the files fresh.
docker compose exec -T -u www-data app php bin/console sitemap:build --force >/dev/null 2>&1
docker compose exec -T -u www-data app php bin/console sitemap:build >/dev/null 2>&1

# The three tables nothing above reaches, driven through their own table classes inside a
# transaction that is rolled back. See test/Db/drive-tables.php for why that is sound.
echo "driving the tables no page reaches"
docker compose exec -T -u www-data app php -d memory_limit=1G test/Db/drive-tables.php >/dev/null 2>&1

db "SET GLOBAL general_log='OFF';"

RAW=$(mktemp)
trap 'rm -f "$RAW" "$HTTP_RAW" "$HTTP" "$INT" "$LITERALS"' EXIT
db "SELECT command_type, REPLACE(REPLACE(CONVERT(argument USING utf8mb4), '\t', ' '), '\n', ' ') FROM mysql.general_log ORDER BY event_time" > "$RAW"

INT=$(mktemp)
docker compose exec -T app php tools/sql-normalise.php < "$RAW" > "$INT"

# The integration suite issues SQL of its own — fixture counts and the like — and those
# statements are not the application's, so a recording holding them moves whenever a test is
# edited. They are subtracted here rather than kept in a hand-maintained ignore list, which
# would rot. A shape the HTTP pass also produced is never dropped: that pass runs nothing but
# the application, so it settles any case where a test happens to write out a statement the
# application also makes.
LITERALS=$(mktemp)
docker compose exec -T app php tools/sql-test-literals.php \
    | docker compose exec -T app php tools/sql-normalise.php > "$LITERALS"

TMP=$(mktemp)
DROPPED=0
while IFS= read -r SHAPE; do
    if grep -qxF "$SHAPE" "$HTTP"; then
        printf '%s\n' "$SHAPE" >> "$TMP"
        continue
    fi
    if contains_literal "$SHAPE" "$LITERALS"; then
        DROPPED=$((DROPPED + 1))
        continue
    fi
    printf '%s\n' "$SHAPE" >> "$TMP"
done < "$INT"

# The HTTP pass in full: the log is truncated between the two phases, so its shapes are not
# in $INT and the loop above never sees the ones only it produces.
cat "$HTTP" >> "$TMP"

sort -u -o "$TMP" "$TMP"
SHAPES=$(wc -l < "$TMP" | tr -d ' ')
echo "kept $SHAPES statement shapes, dropped $DROPPED issued by the tests themselves"

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
