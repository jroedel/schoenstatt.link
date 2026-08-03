#!/usr/bin/env bash
# Post-deploy smoke checks against the LIVE site.
#
# Read-only GETs mirroring test/Smoke's key assertions, plus a random sample
# of *cold* pages from the sitemap — the fatal-200 regression class (PHP
# fatals served as ~800-byte HTTP 200 pages) only ever showed on cold pages,
# so the fixed checks alone can't catch it.
#
# Runs on the deploying machine (curl + coreutils only). Wire it in as the
# LAST post-deploy hook in phploy.ini so a failure ends the deploy loudly:
#   post-deploy[] = "bash tools/smoke-prod.sh"
# The sign-in round trip stays manual.
set -uo pipefail

BASE=${SMOKE_PROD_BASE_URL:-https://schoenstatt.link}
COLD_SAMPLES=${SMOKE_PROD_COLD_SAMPLES:-5}
CURL_OPTS=(--silent --show-error --compressed --max-time 45
    --user-agent 'schoenstatt-smoke-prod (tools/smoke-prod.sh)')

FAILURES=0
BODY=$(mktemp -t smoke-prod-body-XXXXXX)
HDRS=$(mktemp -t smoke-prod-hdrs-XXXXXX)
trap 'rm -f "$BODY" "$HDRS"' EXIT

# fetch <url> [--follow] — body lands in $BODY, response headers in $HDRS;
# sets STATUS, REDIRECT, CTYPE, HTTPVER. Fields are joined with the ASCII
# unit separator: tab is IFS *whitespace*, so empty fields (e.g. no
# redirect_url) would collapse and shift everything left.
US=$'\x1f'
fetch() {
    local url=$1 follow=() meta
    [ "${2:-}" = "--follow" ] && follow=(--location)
    meta=$(curl "${CURL_OPTS[@]}" ${follow[@]+"${follow[@]}"} -o "$BODY" -D "$HDRS" \
        -w "%{http_code}${US}%{redirect_url}${US}%{content_type}${US}%{http_version}" "$url") \
        || meta="000${US}${US}${US}"
    IFS="$US" read -r STATUS REDIRECT CTYPE HTTPVER <<<"$meta"
}

# header <name> — value of <name> from the LAST response in $HDRS (--follow
# accumulates one header block per hop), lowercase, CR stripped.
header() {
    sed -n "s/^$1: *//Ip" "$HDRS" | tr -d '\r' | tail -1 | tr '[:upper:]' '[:lower:]'
}

pass() { echo "  ok  $*"; }
fail() {
    echo "FAIL  $*" >&2
    FAILURES=$((FAILURES + 1))
}

# The one marker set safe from false positives in real page content: PHP's
# own fatal/trace output, not Notice/Warning words that could appear in prose.
no_fatals() { ! grep -qE 'Fatal error|Parse error|Stack trace:|Uncaught (Error|Exception)' "$BODY"; }

echo "Production smoke checks against $BASE"

fetch "$BASE/"
if [ "$STATUS" = "302" ] && [[ "$REDIRECT" == */en/ ]]; then
    pass "/ redirects to /en/"
else
    fail "/ should 302 to /en/ (got $STATUS -> '$REDIRECT')"
fi

fetch "$BASE/en/"
if [ "$STATUS" = "200" ] && no_fatals \
    && grep -qE '<title>[^<]*Schoenstatt Link' "$BODY" \
    && grep -q 'Our mission' "$BODY"; then
    pass "/en/ renders the homepage"
else
    fail "/en/ should render the homepage (got $STATUS)"
fi

# --- Transport/performance checks (same homepage response) ---

# Compression is our .htaccess deflate config (Hetzner upgrades it to
# brotli server-side), so a missing Content-Encoding is a misdeploy.
ENCODING=$(header content-encoding)
if [ "$ENCODING" = "br" ] || [ "$ENCODING" = "gzip" ]; then
    pass "homepage is compressed ($ENCODING)"
else
    fail "homepage should be br/gzip compressed (got '${ENCODING:-none}')"
fi

# HTTP/2 is hoster-provided, not ours to fix — advisory only.
if [[ "$BASE" == https://* ]] && [[ "${HTTPVER:-0}" != [23]* ]]; then
    echo "WARN  homepage served over HTTP/${HTTPVER:-?}, expected HTTP/2 (hoster config?)" >&2
fi

# Static-asset caching comes straight from public/.htaccess — a regression
# here means the deployed .htaccess is wrong or missing.
fetch "$BASE/favicon-32.png"
CACHE=$(header cache-control)
if [ "$STATUS" = "200" ] && [[ "$CACHE" == *max-age=31536000* ]] \
    && [[ "$CACHE" == *immutable* ]] && [[ "$CACHE" != *only-if-cached* ]]; then
    pass "images cache for 1y immutable"
else
    fail "favicon-32.png should be 'max-age=31536000, public, immutable' (got $STATUS, '$CACHE')"
fi

fetch "$BASE/css/style.css"
CACHE=$(header cache-control)
if [ "$STATUS" = "200" ] && [[ "$CACHE" == *max-age=2628000* ]] \
    && [[ "$CACHE" != *only-if-cached* ]]; then
    pass "css caches for 1 month"
else
    fail "css/style.css should be 'max-age=2628000, public' (got $STATUS, '$CACHE')"
fi

fetch "$BASE/en/there-is-no-such-page-xyz"
if [ "$STATUS" = "404" ] && no_fatals; then
    pass "unknown web URL is a clean 404"
else
    fail "unknown web URL should 404 cleanly (got $STATUS, redirect '$REDIRECT')"
fi

# SlmLocale 302s /api/* to /<locale>/api/* first, so follow redirects.
fetch "$BASE/api/there-is-no-such-endpoint" --follow
if [ "$STATUS" = "404" ] && no_fatals; then
    pass "unknown API path is a clean 404"
else
    fail "unknown API path should 404 cleanly (got $STATUS)"
fi

fetch "$BASE/en/sitemap.xml"
if [ "$STATUS" = "200" ] && grep -q '<urlset' "$BODY"; then
    pass "sitemap renders"
    mapfile -t COLD_URLS < <(grep -oE '<loc>[^<]+</loc>' "$BODY" \
        | sed -e 's|</\?loc>||g' | grep -F "$BASE" | shuf -n "$COLD_SAMPLES")
else
    fail "sitemap should render (got $STATUS)"
    COLD_URLS=()
fi

if [ "$COLD_SAMPLES" -gt 0 ] && [ "${#COLD_URLS[@]}" -eq 0 ]; then
    fail "sitemap yielded no sampleable URLs on $BASE"
fi

# Follow redirects: superseded short links legitimately 301 to their
# successor (e.g. SL206282L -> SL207792L) and the sitemap lags behind.
for url in ${COLD_URLS[@]+"${COLD_URLS[@]}"}; do
    fetch "$url" --follow
    # Fatal-200 pages were ~800 bytes; any real page with the layout is far
    # bigger, so a size floor catches error pages regardless of their text.
    if [ "$STATUS" = "200" ] && no_fatals && [ "$(wc -c <"$BODY")" -ge 2000 ]; then
        pass "cold page renders: ${url#"$BASE"}"
    else
        fail "cold page broken: ${url#"$BASE"} (status $STATUS, $(wc -c <"$BODY") bytes)"
    fi
    sleep 1
done

# APCu occupancy — advisory only (warns, never fails: a filling cache is a
# capacity signal, not a broken deploy). Needs a sion_model api key; the
# phploy hook passes it via the environment.
if [ -n "${SMOKE_PROD_CACHE_KEY:-}" ]; then
    fetch "$BASE/en/sm/cache-status?key=$SMOKE_PROD_CACHE_KEY"
    if [ "$STATUS" = "200" ] && grep -q '"apcuEnabled":true' "$BODY"; then
        PERCENT=$(grep -o '"percentUsed":[0-9.]*' "$BODY" | cut -d: -f2)
        EXPUNGES=$(grep -o '"expunges":[0-9]*' "$BODY" | cut -d: -f2)
        pass "APCu segment ${PERCENT:-?}% used, ${EXPUNGES:-?} expunge(s) since restart"
        if awk "BEGIN { exit !(${PERCENT:-0} >= 80) }"; then
            echo "WARN  APCu segment is ${PERCENT}% full — raise apc.shm_size" >&2
        fi
        if [ "${EXPUNGES:-0}" -gt 0 ]; then
            echo "WARN  APCu expunged ${EXPUNGES} time(s) — the segment is too small" >&2
        fi
    else
        echo "note  cache-status unavailable (status $STATUS) — skipping the APCu check"
    fi
fi

echo
if [ "$FAILURES" -gt 0 ]; then
    echo "$FAILURES smoke check(s) FAILED against $BASE" >&2
    exit 1
fi
echo "All production smoke checks passed. Still manual: a sign-in round trip."
