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
FATAL_200S=0
BODY=$(mktemp -t smoke-prod-body-XXXXXX)
HDRS=$(mktemp -t smoke-prod-hdrs-XXXXXX)
trap 'rm -f "$BODY" "$HDRS"' EXIT

# fetch <url> [--follow] — body lands in $BODY, response headers in $HDRS;
# sets STATUS, REDIRECT, CTYPE, HTTPVER. Fields are joined with the ASCII
# unit separator: tab is IFS *whitespace*, so empty fields (e.g. no
# redirect_url) would collapse and shift everything left.
US=$'\x1f'
# Extra curl args (request headers) applied to every fetch; a caller sets it for
# one request and resets it. Used for the maintenance key, which must not travel
# in a query string — that lands in the server's access log on every deploy.
EXTRA_HEADERS=()
fetch() {
    local url=$1 follow=() meta
    [ "${2:-}" = "--follow" ] && follow=(--location)
    meta=$(curl "${CURL_OPTS[@]}" ${follow[@]+"${follow[@]}"} \
        ${EXTRA_HEADERS[@]+"${EXTRA_HEADERS[@]}"} -o "$BODY" -D "$HDRS" \
        -w "%{http_code}${US}%{redirect_url}${US}%{content_type}${US}%{http_version}${US}%{num_redirects}${US}%{url_effective}" "$url") \
        || meta="000${US}${US}${US}${US}0${US}"
    # HOPS/EFFECTIVE are the pair that matters under --follow, where %{redirect_url} is empty
    # (curl only reports a redirect it did NOT take) and %{http_code} is the destination's.
    # Without them a URL that 301s is indistinguishable from one that answers 200.
    IFS="$US" read -r STATUS REDIRECT CTYPE HTTPVER HOPS EFFECTIVE <<<"$meta"

    # A PHP fatal with display_errors=Off is an HTTP 200 with an EMPTY body, and
    # every individual check below asks a question that answer can satisfy by
    # accident: a check expecting 404 reports "got 200" and a check expecting 200
    # sees the status it wanted. On 2026-08-17 that is exactly what happened —
    # 21 checks failed reading "got 200", every one of them a zero-byte fatal, and
    # the run named the wrong problem in 21 different ways.
    #
    # So the detection lives here, once, before any check gets to interpret the
    # response. no_fatals() cannot do this job: it greps for PHP's error text, and
    # the whole point of an empty fatal is that there is no text to find.
    if [ "$STATUS" = "200" ] && [ ! -s "$BODY" ]; then
        fail "fatal-200: $url answered 200 with an EMPTY body — a PHP fatal, not a page.
      Look in shared/data/exceptions/ on the server. If the newest report names an
      older .revision than the live release, OPcache is still serving the previous
      release — see docs/incident-2026-08-17-stale-opcache.md."
        FATAL_200S=$((FATAL_200S + 1))
    fi
}

# header <name> — value of <name> from the LAST response in $HDRS (--follow
# accumulates one header block per hop), lowercase, CR stripped.
header() {
    sed -n "s/^$1: *//Ip" "$HDRS" | tr -d '\r' | tail -1 | tr '[:upper:]' '[:lower:]'
}

# >>> cache-status parsing
# One number out of /en/sm/cache-status's "opcache" object. $1 key, $2 body file.
#
# Scoped to that object, never read from the whole document, because three keys —
# uptimeSeconds, hits and misses — exist in BOTH the APCu and the OPcache sections.
# An unqualified grep then returns two lines, and the newline between them makes
# $(( )) a syntax ERROR rather than a wrong number:
#
#   tools/smoke-prod.sh: line 423: 77
#   838 / 60 : syntax error in expression
#
# That is not hypothetical — it is what the interned-strings line did on its first
# production run, 2026-08-19, having been added the day before. It could not have
# been caught before then: tools/ci-local.sh runs the PHPUnit test/Smoke suite, not
# this script, and this script only ever executes against the live site. So the
# parsing lives in its own marked block, and test/Deploy/smoke-parsing-test.sh
# drives it against a fixture with the duplicates in it.
#
# `head -1` after the slice is belt and braces: the slice already removes the APCu
# copy, and a future duplicate INSIDE the opcache object would otherwise reintroduce
# exactly this failure.
oc_num() {
    # Answer nothing when there is no opcache object, rather than falling through to
    # a whole-document read. Without this guard `sed` substitutes nothing, passes the
    # entire body along, and the APCu copy of a duplicated key comes back as if it
    # were OPcache's — the same bug again, in the one case the caller's own
    # `grep -q '"opcache"'` is what happens to be preventing. A parser should not
    # depend on its caller's guard.
    grep -q '"opcache":' "$2" || return 0
    sed 's/.*"opcache":/{/' "$2" | grep -o "\"$1\":[0-9.]*" | head -1 | cut -d: -f2
}

# The `sionModel.retiredConfigKeys` list, as a bare space-separated string. $1 body
# file. Empty output means the expected reading: nothing configured that this
# package has stopped honouring.
#
# Whole-document, deliberately, unlike oc_num: the key exists once and a scoped
# read would need a second slice expression to maintain. It is safe here for the
# reason oc_num is not — no other section of the payload uses this name — and that
# is a fact about today's document, so the test below pins it against a fixture
# carrying every other section.
#
# An absent field reads empty too, which is the right answer for an older server
# answering a newer script: "nothing to report" and "cannot tell" both mean do not
# warn, and a deploy's last step is the wrong place to fail on a missing diagnostic.
retired_keys() {
    sed -n 's/.*"retiredConfigKeys":\[\([^]]*\)\].*/\1/p' "$1" | head -1 | tr -d '"' | tr ',' ' '
}
# <<< cache-status parsing

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
#
# Checked on a live-v3 typo as well as a versionless path, because since the v1/v2
# retirement the 404 has a neighbour: those two versions answer 410 Gone, and this is
# what pins the 410 to them. An unknown path in a *live* API is a typo, and answering
# "permanently gone" to a misspelling is a lie the caller would act on.
for UNKNOWN in /api/there-is-no-such-endpoint /api/v3/phrasez /api/v9/associations; do
    fetch "$BASE$UNKNOWN" --follow
    if [ "$STATUS" = "404" ] && no_fatals; then
        pass "$UNKNOWN is a clean 404"
    else
        fail "$UNKNOWN should 404 cleanly, not 410 (got $STATUS)"
    fi
done

# --- The retired v1/v2 API stays retired ----------------------------------
#
# All 26 /api/v1 and /api/v2 routes were withdrawn on 2026-08-14, after eight
# years of access logs showed no client had used any of them since 2022. This
# block used to prove they still worked; it now proves they are gone, which is
# the same deploy risk read the other way round — a route tree resurrected by a
# bad merge, or a public_html/api/ directory the deploy failed to delete, both
# show up here.
#
# The check is on the SHAPE of the refusal, not just the status. RestApi's
# api-route-not-found catch-all must answer these as a JSON 410: an HTML error page
# would mean the catch-all stopped matching and the ordinary 404 page took over,
# which is a regression for every remaining machine caller including /api/v3. A 404
# here would mean the retired-version branch stopped firing, which costs the indexing
# signal these URLs need.
#
# The locale-prefixed form is requested directly rather than relying on
# --follow: SlmLocale's 302 does not carry the query string, so
# `?kind=sch-shrine` would be lost on the way to /en/.

# json_ok <what> <expect-substring> — asserts the LAST fetch returned parseable
# JSON of the expected shape. No jq: this script is curl + coreutils only.
json_ok() {
    local what=$1 expect=$2
    if [ "$STATUS" != "200" ]; then
        fail "$what should be 200 (got $STATUS, redirect '$REDIRECT')"
        return
    fi
    if ! no_fatals; then
        fail "$what returned a PHP fatal in its body"
        return
    fi
    case "$CTYPE" in
        application/json*) ;;
        *) fail "$what should be application/json (got '${CTYPE:-none}')"; return ;;
    esac
    if ! grep -q "$expect" "$BODY"; then
        fail "$what is missing '$expect'"
        return
    fi
    pass "$what returns JSON ($(wc -c <"$BODY") bytes)"
}

# json_gone <what> — asserts the LAST fetch was refused as a JSON 410 Gone carrying a
# successor-version Link. 410 rather than 404 on purpose: these resources existed and
# are permanently removed, which is what gets an indexed URL dropped rather than merely
# demoted, and two of them were published as a schema.org Dataset distribution.
json_gone() {
    local what=$1
    if [ "$STATUS" != "410" ]; then
        fail "$what should be 410 Gone now that the v1/v2 API is retired (got $STATUS, redirect '$REDIRECT')"
        return
    fi
    if ! no_fatals; then
        fail "$what returned a PHP fatal in its body"
        return
    fi
    case "$CTYPE" in
        application/json*) ;;
        *) fail "$what should be refused as application/json (got '${CTYPE:-none}'): has api-route-not-found stopped matching?"; return ;;
    esac
    case "$(header link)" in
        *"</api/v3/schema>"*successor-version*)
            pass "$what is a JSON 410 pointing at /api/v3/schema" ;;
        *successor-version*)
            fail "$what advertises a successor, but not /api/v3/schema (got '$(header link)') — a bare /api/v3 has no route and 404s" ;;
        *)
            fail "$what should send 'Link: </api/v3/schema>; rel=\"successor-version\"' (got '$(header link)')" ;;
    esac
}

for V in v1 v2; do
    fetch "$BASE/en/api/$V/associations/findByKind?kind=sch-shrine"
    json_gone "api/$V findByKind"
    fetch "$BASE/en/api/$V/associations/findByKindMd5?kind=sch-shrine"
    json_gone "api/$V findByKindMd5"
    fetch "$BASE/en/api/$V/associations/shrines.json"
    json_gone "api/$V shrines.json"
done

# The rest of the retired surface: one path per former controller, plus the two
# documentation artefacts. v1.yaml and the Swagger UI bundle were STATIC FILES
# under public/api/, so these two also verify the deploy removed files rather
# than only shipping changed ones — a phploy run that skips deletions leaves the
# OpenAPI document live, still advertising 26 endpoints that no longer exist.
fetch "$BASE/en/api/v1/dictionary";        json_gone "api/v1 dictionary"
fetch "$BASE/en/api/v1/literature";        json_gone "api/v1 literature"
fetch "$BASE/en/api/v1/libraries/3";       json_gone "api/v1 library detail"
fetch "$BASE/en/api/v1/users/login";       json_gone "api/v1 login"
fetch "$BASE/en/api/v1";                   json_gone "api/v1 documentation shell"
fetch "$BASE/api/v1.yaml" --follow;        json_gone "the OpenAPI document"

# Follow the successor-version Link the 410s send. A Link header naming a URL that does
# not answer is worse than none — it reads as "the API is gone entirely" — and the first
# deploy of the 410 pointed at /api/v3, which has no route and 404s. Only fetching the
# advertised target catches that; every pattern check on the header passed.
fetch "$BASE/api/v3/schema"
if [ "$STATUS" = "200" ] && grep -q '"entities"' "$BODY"; then
    pass "the advertised successor /api/v3/schema answers with the discovery document"
else
    fail "/api/v3/schema must answer 200 with the v3 discovery document (got $STATUS) — the 410s point callers here"
fi

# Retired administrator endpoints, all reached by a plain GET while they existed. 404 is
# what carries the signal: a *guarded* route answers an anonymous request with a 302 to
# the login form, so a 302 here means the route is back. /en/texts/import was a call to a
# method that exists in no class; /en/music/import was a hardcoded 2019 id list whose
# input files are gone; the two /en/literature/* went in the previous sweep.
for path in /en/texts/import /en/music/import /en/literature/import /en/literature/admin-tasks; do
    fetch "$BASE$path"
    if [ "$STATUS" = "404" ] && no_fatals; then
        pass "$path stays retired"
    else
        fail "$path should 404 (got $STATUS, redirect '$REDIRECT') — a 302 means the route came back"
    fi
done

# The sitemap is a static file at the docroot ROOT since 2026-08-13, and the root is the
# point: a sitemap may only list URLs at or below its own directory, so the previous
# layout — parts under /sitemap/, index advertised as /en/sitemap.xml — put all 36,730
# URLs out of scope and Google discarded the lot. That is invisible in a response, which
# is why the path shape is asserted here and not just the status.
fetch "$BASE/sitemap.xml"
if [ "$STATUS" = "200" ] && grep -q '<sitemapindex' "$BODY"; then
    pass "sitemap index renders"
    mapfile -t SITEMAP_PARTS < <(grep -oE '<loc>[^<]+</loc>' "$BODY" \
        | sed -e 's|</\?loc>||g' | grep -F "$BASE")
else
    fail "sitemap index should render (got $STATUS)"
    SITEMAP_PARTS=()
fi

# Every listed file must be one path segment deep. A part that reappears under a
# subdirectory is the original bug returning, and it fails no other check.
OUT_OF_SCOPE=()
for part in ${SITEMAP_PARTS[@]+"${SITEMAP_PARTS[@]}"}; do
    case "${part#"$BASE"}" in
        /sitemap-*.xml) ;;
        *) OUT_OF_SCOPE+=("$part") ;;
    esac
done
if [ "${#SITEMAP_PARTS[@]}" -gt 0 ] && [ "${#OUT_OF_SCOPE[@]}" -eq 0 ]; then
    pass "every sitemap file is at the docroot root"
elif [ "${#OUT_OF_SCOPE[@]}" -gt 0 ]; then
    fail "sitemap files outside the docroot root — Google ignores every URL in them: ${OUT_OF_SCOPE[*]}"
fi

# Apache serves these, not PHP. Losing that is silent: the sitemap keeps working and the
# 0.6s navigation walk comes back into the request path.
#
# **Not by ETag.** This checked for one and failed against production on 2026-08-13 while
# everything was in fact working: this hoster sends no ETag on *any* static file —
# /css/gen-basic.css, /favicon.ico and /robots.txt are all without one — so the check was
# asserting a capsule-only property. Apache in the capsule does send them, which is exactly
# why it passed locally and failed live.
#
# What does hold in both places is the pair below. `Accept-Ranges: bytes` comes from
# Apache's static file handler and Symfony's BinaryFileResponse does not set it; and PHP
# cannot answer without starting a session, so the fallback always carries two Set-Cookie
# headers and the no-store/Pragma trio while Apache carries none.
if [ -n "$(header accept-ranges)" ] && [ -z "$(header set-cookie)" ]; then
    pass "sitemap is served statically by Apache"
elif [ -n "$(header set-cookie)" ]; then
    fail "sitemap set a session cookie, so PHP served it — did bin/console sitemap:build write public/sitemap.xml?"
else
    fail "sitemap sent no Accept-Ranges, so Apache is not serving it as a static file"
fi

# One part per deploy, chosen at random. Publications alone is ~24 MB, so downloading all
# of them on every deploy would cost 26 MB for five sampled URLs. Sampling one at random
# can land anywhere in the catalogue, where the old single-file version only ever held the
# first 3,022 pages.
COLD_URLS=()
if [ "${#SITEMAP_PARTS[@]}" -gt 0 ]; then
    part=$(printf '%s\n' "${SITEMAP_PARTS[@]}" | shuf -n1)
    fetch "$part"
    if [ "$STATUS" = "200" ]; then
        pass "sitemap part fetches: ${part#"$BASE"}"
        mapfile -t COLD_URLS < <(grep -oE '<loc>[^<]+</loc>' "$BODY" \
            | sed -e 's|</\?loc>||g' | grep -F "$BASE" | shuf -n "$COLD_SAMPLES")
    else
        fail "sitemap part should fetch: ${part#"$BASE"} (got $STATUS)"
    fi
else
    fail "sitemap index listed no parts on $BASE"
fi

if [ "$COLD_SAMPLES" -gt 0 ] && [ "${#COLD_URLS[@]}" -eq 0 ]; then
    fail "sitemap yielded no sampleable URLs on $BASE"
fi

# Redirects are followed so the fatal-200 check lands on a real page either way — but a
# sitemap URL that redirects at all is a defect, and this loop used to say the opposite:
# "superseded short links legitimately 301 to their successor and the sitemap lags behind".
# They do 301, and it is not legitimate to publish them. A merged publication answers only
# a permanent redirect to the edition it was merged into, 3,627 of the 10,104 public rows
# are merged, and `SitemapGenerator::mergedPublicationIds()` excludes every one of them —
# failing OPEN if it cannot, which publishes all 3,627 and breaks nothing else. Following
# the redirect silently turned the one symptom of that into a pass.
#
# WARN rather than FAIL on purpose: the sitemap is rebuilt by cron, not by the deploy, so a
# redirect in it is not evidence the deploy went wrong and should not end the deploy loop.
# The authoritative check is SitemapSmokeTest::testMergedPublicationsAreExcluded(), which
# compares the whole published set against the database rather than sampling.
for url in ${COLD_URLS[@]+"${COLD_URLS[@]}"}; do
    fetch "$url" --follow
    # Fatal-200 pages were ~800 bytes; any real page with the layout is far
    # bigger, so a size floor catches error pages regardless of their text.
    if [ "$STATUS" = "200" ] && no_fatals && [ "$(wc -c <"$BODY")" -ge 2000 ]; then
        pass "cold page renders: ${url#"$BASE"}"
    else
        fail "cold page broken: ${url#"$BASE"} (status $STATUS, $(wc -c <"$BODY") bytes)"
    fi
    if [ "${HOPS:-0}" -gt 0 ]; then
        echo "WARN  the sitemap advertises ${url#"$BASE"}, which redirects (${HOPS} hop(s)) to" >&2
        echo "      ${EFFECTIVE#"$BASE"} — a sitemap must list only URLs that answer 200. If" >&2
        echo "      this is a merged publication, the exclusion in SitemapGenerator failed open." >&2
    fi
    sleep 1
done

# APCu occupancy — advisory only (warns, never fails: a filling cache is a
# capacity signal, not a broken deploy). Needs a sion_model api key; the
# phploy hook passes it via the environment.
if [ -n "${SMOKE_PROD_CACHE_KEY:-}" ]; then
    EXTRA_HEADERS=(-H "X-Api-Key: $SMOKE_PROD_CACHE_KEY")
    fetch "$BASE/en/sm/cache-status"
    EXTRA_HEADERS=()
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

        # SionModel's own cache settings, from the same response. The retired-key
        # list is the point: config/autoload/local.php is gitignored, so a setting
        # this package stopped honouring can sit on a server indefinitely with
        # nothing anywhere to say so. Empty is the expected reading. Advisory —
        # an ignored key breaks nothing, it just isn't doing what someone thinks.
        RETIRED=$(retired_keys "$BODY")
        if [ -n "$RETIRED" ]; then
            echo "WARN  sion_model config still names retired key(s): $RETIRED — remove from local.php" >&2
        else
            ITEM_SIZE=$(grep -o '"maxCachedItemSize":[0-9]*' "$BODY" | cut -d: -f2)
            pass "sion_model cache item bound ${ITEM_SIZE:-?} bytes, no retired config keys"
        fi
    else
        echo "note  cache-status unavailable (status $STATUS) — skipping the APCu check"
    fi

    # OPcache, from the same response. Also advisory. OPcache does not degrade
    # gracefully the way APCu does — when it runs out of memory or hash slots it
    # restarts and discards every compiled script, so the restart counters are
    # the signal that something already went wrong, and the percentages are the
    # warning before it does. Keys are compared against max_cached_keys (the real
    # prime-rounded table size), not the configured max_accelerated_files.
    if [ "$STATUS" = "200" ] && grep -q '"opcache"' "$BODY"; then
        if grep -q '"enabled":true' "$BODY"; then
            OC_MEM=$(oc_num memoryPercentUsed "$BODY")
            OC_KEYS=$(oc_num keysPercentUsed "$BODY")
            OC_SCRIPTS=$(oc_num cachedScripts "$BODY")
            OC_MAXKEYS=$(oc_num maxCachedKeys "$BODY")
            OC_HIT=$(oc_num hitRatePercent "$BODY")
            OC_INTERNED=$(oc_num internedPercentUsed "$BODY")
            OC_INTERNED_MB=$(oc_num internedBufferConfiguredMb "$BODY")
            OC_UPTIME=$(oc_num uptimeSeconds "$BODY")
            OC_OOM=$(oc_num oomRestarts "$BODY")
            OC_HASH=$(oc_num hashRestarts "$BODY")
            pass "OPcache ${OC_MEM:-?}% memory, ${OC_KEYS:-?}% of ${OC_MAXKEYS:-?} keys (${OC_SCRIPTS:-?} scripts), ${OC_HIT:-?}% hit rate"
            # Interned usage is meaningless without the segment's age beside it: the
            # buffer is append-only, so the percentage only ever rises within one
            # segment's life and a deploy's reset puts it back near zero. Both are
            # printed together, always, so nobody reads a cold number as a healthy one.
            pass "OPcache interned strings ${OC_INTERNED:-?}% of ${OC_INTERNED_MB:-?}MB after $(( ${OC_UPTIME:-0} / 60 ))m uptime"

            if awk "BEGIN { exit !(${OC_MEM:-0} >= 80) }"; then
                echo "WARN  OPcache memory is ${OC_MEM}% used — raise opcache.memory_consumption" >&2
            fi
            if awk "BEGIN { exit !(${OC_KEYS:-0} >= 80) }"; then
                echo "WARN  OPcache is using ${OC_KEYS}% of its key table — raise opcache.max_accelerated_files" >&2
            fi
            if awk "BEGIN { exit !(${OC_INTERNED:-0} >= 90) }"; then
                echo "WARN  OPcache interned strings ${OC_INTERNED}% of ${OC_INTERNED_MB:-?}MB after only $(( ${OC_UPTIME:-0} / 60 ))m — raise opcache.interned_strings_buffer" >&2
                echo "      Nothing is ever evicted from this buffer, so it fills once and then stops interning silently." >&2
            fi
            # One pool answered this. There are three, each with its own segment, and
            # this run cannot say which one it reached — tools/opcache-sample.sh polls
            # and groups by startTimeUnix when that distinction matters.
            if [ -n "${OC_INTERNED_MB:-}" ] && [ "${OC_INTERNED_MB:-0}" -lt 32 ]; then
                echo "note  opcache.interned_strings_buffer is ${OC_INTERNED_MB}MB on the pool that answered; 32 is what docs/php-85.md asks for"
            fi
            if [ "${OC_OOM:-0}" -gt 0 ] || [ "${OC_HASH:-0}" -gt 0 ]; then
                echo "WARN  OPcache restarted (${OC_OOM:-0} out-of-memory, ${OC_HASH:-0} hash) — it has been discarding the whole cache" >&2
            fi
            if grep -q '"cacheFull":true' "$BODY"; then
                echo "WARN  OPcache reports cache_full — new scripts are no longer being cached" >&2
            fi
            # With timestamp validation off, a deploy is invisible to OPcache until
            # the pool is restarted, which would serve the previous release forever.
            if grep -q '"validateTimestamps":false' "$BODY"; then
                echo "WARN  opcache.validate_timestamps is off — deploys need 'pkill -u ourlink -f php'" >&2
            fi
        else
            echo "WARN  OPcache is disabled — every request is recompiling PHP" >&2
        fi
    fi
fi

# --- The two front controllers --------------------------------------------
#
# public/.htaccess picks one per request: a site-wide default plus two cookie
# overrides, `sl_symfony_canary=1` for App\Kernel and `=0` for laminas-mvc. See
# docs/strangler.md.
#
# **Which one is the default is asked, not assumed.** This section used to hard-code
# "production is laminas, the cookie is the exception", and that assertion becomes a
# false failure the day the flip lands — the one day the script most needs to be
# believed. So it probes first and then asserts the *pair*: whichever kernel is the
# default serves ordinary traffic, and the override reaches the other one. A default
# that has silently reverted and an override that has silently stopped working are
# both failures, and only checking both directions tells either from success.
#
# This is also the only place the ported code is checked outside the capsule, so the
# assertions have to *discriminate*. Every check above passes under either front
# controller by design — flipping a kernel and re-running them would prove nothing.
# Two markers separate them:
#
#   /_health          exists only in the Symfony route table
#   slm_locale=en_US  is set by SlmLocale, which only laminas runs
#
# Skipped unless SMOKE_PROD_CANARY_COOKIE is set; the phploy hook passes it. Its
# *value* is no longer read: both cookies are named here because there are now two of
# them and neither is a secret (docs/strangler.md explains why that is safe), and
# because each deploying machine's phploy.ini is a file no commit here can update.
#
# **Pointing this at the capsule fails two checks by design.** docker/apache-vhost.conf
# sets SYMFONY_KERNEL with `SetEnv`, and mod_env beats all of mod_setenvif, so no cookie
# can move a capsule request off the Symfony kernel — the opt-out assertions below have
# nothing to work with there. Everything else in this section is useful locally.
if [ -n "${SMOKE_PROD_CANARY_COOKIE:-}" ]; then
    echo
    FORCE_SYMFONY_COOKIE="sl_symfony_canary=1"
    FORCE_LAMINAS_COOKIE="sl_symfony_canary=0"

    fetch "$BASE/_health"
    if [ "$STATUS" = "200" ] && grep -q '"status"' "$BODY"; then
        DEFAULT_KERNEL=symfony
    else
        DEFAULT_KERNEL=laminas
    fi
    echo "Front controllers (site default: $DEFAULT_KERNEL)"

    if [ "$DEFAULT_KERNEL" = "laminas" ]; then
        pass "ordinary traffic gets laminas (/_health is not served, status $STATUS)"

        fetch "$BASE/en/shrines"
        if grep -qi '^set-cookie:.*slm_locale=en_US' "$HDRS"; then
            pass "ordinary traffic gets laminas (SlmLocale set its cookie)"
        else
            fail "no slm_locale cookie without an override — has SYMFONY_KERNEL been set for everyone?"
        fi

        # The opt-in override has to reach App\Kernel, or nothing below this proves
        # anything about the ported routes. Everything after this point runs through it,
        # including the *bridged* checks at the end — an unported page fetched without the
        # cookie never touches LaminasResponseConverter at all, so checking it would
        # assert nothing about the code the flip puts in front of the whole site.
        SYMFONY_HEADERS=(-H "Cookie: $FORCE_SYMFONY_COOKIE")
        EXTRA_HEADERS=("${SYMFONY_HEADERS[@]}")
        fetch "$BASE/_health"
        if [ "$STATUS" = "200" ] && grep -q '"status"' "$BODY"; then
            pass "the opt-in cookie reaches the Symfony kernel (/_health answers)"
        else
            fail "/_health should answer 200 with $FORCE_SYMFONY_COOKIE (got $STATUS) — is the SetEnvIf deployed?"
        fi
        # ...and the ported routes below are then checked through that same cookie
    else
        pass "ordinary traffic gets the Symfony kernel (/_health answers)"

        # After the flip this is the check that matters most, because it is the way
        # back. An admin has no other route to laminas without a deploy, and a
        # mis-ordered .htaccess kills it with no other symptom (see
        # test/Integration/KernelCanaryTest).
        EXTRA_HEADERS=(-H "Cookie: $FORCE_LAMINAS_COOKIE")
        fetch "$BASE/_health"
        if [ "$STATUS" != "200" ]; then
            pass "the opt-out cookie reaches laminas (/_health stops answering, status $STATUS)"
        else
            fail "/_health still answered 200 with $FORCE_LAMINAS_COOKIE — the escape hatch back to laminas is dead"
        fi

        fetch "$BASE/en/shrines"
        if grep -qi '^set-cookie:.*slm_locale=en_US' "$HDRS"; then
            pass "the opt-out cookie really renders through laminas (SlmLocale set its cookie)"
        else
            fail "no slm_locale cookie with $FORCE_LAMINAS_COOKIE — the opt-out is not reaching laminas"
        fi

        # the ported routes are checked as ordinary traffic sees them, i.e. no cookie
        SYMFONY_HEADERS=()
        EXTRA_HEADERS=()
    fi

    # Every ported HTML route that a signed-out visitor may see. The absence of the
    # SlmLocale cookie is what proves each was not quietly bridged back to laminas —
    # which is what a route slipping below the catch-all looks like.
    for path in / /developers /acknowledgements /privacy /shrines/submitting-photos /shrines \
        /wayside-shrines /timeline /music /dictionary /literature/150-preguntas-sobre-schoenstatt; do
        url="$BASE/en${path%/}"
        [ "$path" = "/" ] && url="$BASE/en/"
        fetch "$url"
        if [ "$STATUS" != "200" ] || ! no_fatals; then
            fail "symfony: /en${path} should render (got $STATUS)"
        elif grep -qi '^set-cookie:.*slm_locale=en_US' "$HDRS"; then
            fail "symfony: /en${path} was served by laminas — is the route still above the catch-all?"
        elif [ "$(wc -c <"$BODY")" -lt 2000 ]; then
            # the empty-200 class: a Twig failure records an exception and emits
            # nothing, so a size floor is what catches it
            fail "symfony: /en${path} rendered only $(wc -c <"$BODY") bytes — Twig cache writable?"
        else
            pass "symfony: /en${path} rendered by Symfony ($(wc -c <"$BODY") bytes)"
        fi
    done

    # HTTP/1.0 is App\Http\ProtocolVersionListener's failure mode, and it is invisible
    # to every status assertion above: Symfony's Response defaults to 1.0, Apache
    # honours the status line and closes the connection on every ported page.
    if [[ "${HTTPVER:-0}" == 1.0* ]]; then
        fail "symfony: a ported page was served over HTTP/1.0 — ProtocolVersionListener is not running"
    else
        pass "symfony: ported pages keep the request's protocol version (HTTP/${HTTPVER:-?})"
    fi

    # The v3 API, which exists *only* on the Symfony kernel and is the reason the flip
    # matters to anything other than this migration: an automated agent sends no cookie,
    # so before the flip v3 is unreachable for its actual callers.
    fetch "$BASE/api/v3/schema"
    json_ok "symfony: api/v3 schema" '"entity":"association"'
    fetch "$BASE/api/v3/associations"
    if [ "$STATUS" = "401" ] && [[ "$CTYPE" == application/json* ]]; then
        pass "symfony: api/v3 associations refuses an unauthenticated caller as JSON (401)"
    else
        # a 302 here means the guard answered with JUser's HTML sign-in page, which a
        # machine caller reads as success
        fail "symfony: api/v3 associations should 401 as JSON (got $STATUS '${CTYPE:-none}' -> '$REDIRECT')"
    fi

    # The guarded HTML routes. No sign-in here, so what is checked is the *refusal* —
    # exactly the half that only exists on the Symfony side: App\Authorization\RouteGuard,
    # reproducing JUser\View\RedirectionStrategy. A ported guarded route that admitted
    # everyone would look perfectly healthy, and this is the check that would see it.
    for path in /admin /sm/phpinfo /sm/data-problems /sm/view-changes /associations /roles /libraries; do
        fetch "$BASE/en$path"
        if [ "$STATUS" = "302" ] && [[ "$REDIRECT" == *"/en/user/login?redirect=/en$path" ]]; then
            pass "symfony: /en$path refuses anonymous access (302 to sign-in)"
        else
            fail "symfony: /en$path should 302 anonymous to sign-in (got $STATUS -> '$REDIRECT')"
        fi
    done

    # The sitemap is plain XML at rest as of 2026-08-13, and this check inverted with it.
    #
    # It used to assert the opposite — that the body was *real gzip under a gzip header* —
    # because the generator wrote gzip bytes into a .xml file and the controller declared
    # the encoding by hand. That is what forced App\Http\GzipListener to honour a
    # response's own Content-Encoding, and it is the reason Apache could not serve the
    # file itself. Now the file is plain XML, Apache serves it, and mod_deflate compresses
    # it on the way out, so the property worth pinning is that the bytes on disk are XML:
    # a gzip magic number here again would mean something has started double-encoding.
    #
    # `--no-compressed` matters, and a request header alone will not do it: CURL_OPTS
    # carries --compressed, which makes curl decode any Content-Encoding it understands
    # whatever Accept-Encoding says.
    EXTRA_HEADERS=(${SYMFONY_HEADERS[@]+"${SYMFONY_HEADERS[@]}"} --no-compressed
        -H 'Accept-Encoding: identity')
    fetch "$BASE/sitemap.xml"
    if [ "$STATUS" = "200" ] && [ -z "$(header content-encoding)" ] \
        && head -c6 "$BODY" | grep -q '<?xml'; then
        pass "sitemap.xml is plain XML under identity encoding"
    else
        fail "sitemap.xml should be uncompressed XML for an identity request (got $STATUS, '$(header content-encoding)')"
    fi
    EXTRA_HEADERS=(${SYMFONY_HEADERS[@]+"${SYMFONY_HEADERS[@]}"})

    # …and that Apache still compresses it when asked. 24 MB of XML uncompressed is the
    # reason this is worth a check of its own rather than trusting the .htaccess rule.
    EXTRA_HEADERS=(${SYMFONY_HEADERS[@]+"${SYMFONY_HEADERS[@]}"} --no-compressed
        -H 'Accept-Encoding: gzip')
    fetch "$BASE/sitemap-associations.xml"
    if [ "$STATUS" = "200" ] && [ "$(header content-encoding)" = "gzip" ] \
        && [ "$(head -c2 "$BODY" | od -An -tx1 | tr -d ' \n')" = "1f8b" ]; then
        pass "Apache compresses the sitemap parts"
    else
        fail "sitemap parts should be gzipped by Apache (got $STATUS, '$(header content-encoding)')"
    fi
    EXTRA_HEADERS=(${SYMFONY_HEADERS[@]+"${SYMFONY_HEADERS[@]}"})

    # Duplicated *identical* Set-Cookie lines, which is what a hand-rolled loop over
    # Laminas\Http\Headers produces where PhpEnvironment\Response replaces all but the
    # MultipleHeaderInterface ones. Counting cookies by name would not do: /en/user/login
    # legitimately sends `slm_locale=deleted; expires=1970` (the GDPR strategy) and then
    # `slm_locale=en_US`, i.e. two lines for one cookie by design.
    fetch "$BASE/en/user/login"
    if [ -z "$(grep -i '^set-cookie:' "$HDRS" | sort | uniq -d)" ]; then
        pass "bridged: no duplicated Set-Cookie (Headers::toArray semantics preserved)"
    else
        fail "bridged: an identical Set-Cookie is sent twice — the converter is duplicating headers"
    fi

    if [[ "$(header cache-control)" == *"no-cache, private"* ]]; then
        fail "bridged: ResponseHeaderBag's invented 'no-cache, private' Cache-Control is reaching visitors"
    else
        pass "bridged: no invented Cache-Control on a bridged page"
    fi

    EXTRA_HEADERS=()
fi

echo
if [ "$FATAL_200S" -gt 0 ]; then
    # Said separately from the failure count, because these are not N independent
    # problems. One stale code path produces a fatal-200 on every URL that reaches
    # it, and the count is a measure of coverage, not of causes.
    echo "$FATAL_200S response(s) were zero-byte HTTP 200s — PHP fatals." >&2
    echo "Treat that as ONE fault with many symptoms, and diagnose it before reading" >&2
    echo "the other failures: most of them are the same fatal seen through a check" >&2
    echo "that expected some other status." >&2
fi
if [ "$FAILURES" -gt 0 ]; then
    echo "$FAILURES smoke check(s) FAILED against $BASE" >&2
    exit 1
fi
echo "All production smoke checks passed. Still manual: a sign-in round trip."
