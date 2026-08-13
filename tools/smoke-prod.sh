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
# Extra curl args (request headers) applied to every fetch; a caller sets it for
# one request and resets it. Used for the maintenance key, which must not travel
# in a query string — that lands in the server's access log on every deploy.
EXTRA_HEADERS=()
fetch() {
    local url=$1 follow=() meta
    [ "${2:-}" = "--follow" ] && follow=(--location)
    meta=$(curl "${CURL_OPTS[@]}" ${follow[@]+"${follow[@]}"} \
        ${EXTRA_HEADERS[@]+"${EXTRA_HEADERS[@]}"} -o "$BODY" -D "$HDRS" \
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

# --- Public JSON API, both versions ---------------------------------------
#
# The mobile apps read these, and until now the deploy checked no API endpoint
# that actually returns data — only that an unknown one 404s. A JSON endpoint
# fails differently from a page: the fatal-200 class shows up as valid HTTP 200
# with an HTML fatal where JSON should be, which every check above would miss
# because they look at pages.
#
# Both versions are exercised on purpose. v1 and v2 are separate controllers
# with separate schema builders (getAssociationListSchemaV1/V2), so a deploy can
# break one and leave the other working, and v1 is the one older app installs
# are pinned to.
#
# Unauthenticated on purpose too: these guards carry a null role, i.e. public.
# If one of them starts redirecting to the sign-in page, that is a guard
# regression this will catch.
#
# The locale-prefixed form is requested directly rather than relying on
# --follow: SlmLocale's 302 does not carry the query string, so
# `?kind=sch-shrine` would be lost on the way to /en/. The prefixed form is also
# what stays stable if SYMFONY_KERNEL is ever set here.

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

for V in v1 v2; do
    # findByKind is the endpoint the shrine index itself advertises to API
    # consumers, and it takes a query parameter — so this also proves query
    # handling survived the deploy, which a bare collection GET would not.
    fetch "$BASE/en/api/$V/associations/findByKind?kind=sch-shrine"
    json_ok "api/$V findByKind?kind=sch-shrine" '"items"'
    # md5 is what a client polls to decide whether to re-download; it returns
    # the same envelope with items nulled, so an empty "items" here is correct.
    fetch "$BASE/en/api/$V/associations/findByKindMd5?kind=sch-shrine"
    json_ok "api/$V findByKindMd5" '"md5"'
done

# The GeoJSON feed is DEPRECATED (2026-08-07, docs/BACKLOG.md) but still served,
# so the deploy still has to keep it working — and has to keep announcing it.
# Checked on both versions because both are still live and byte-identical.
for V in v1 v2; do
    fetch "$BASE/en/api/$V/associations/shrines.json"
    json_ok "api/$V shrines.json (deprecated)" '"FeatureCollection"'
    DEPRECATION=$(header deprecation)
    if [ "$DEPRECATION" = "true" ]; then
        pass "api/$V shrines.json announces Deprecation: true"
    else
        fail "api/$V shrines.json should send 'Deprecation: true' (got '${DEPRECATION:-none}')"
    fi
done

# `<sitemapindex>`, not `<urlset>`, since /sitemap.xml was ported on 2026-08-13: the
# page URLs live in the parts the index names. This check greped for `<urlset>` and so
# failed on the first deploy after that, twice over — once here and once as "no
# sampleable URLs", which also silently dropped the cold-page sampling below, the part
# that actually catches fatal-200s.
fetch "$BASE/en/sitemap.xml"
if [ "$STATUS" = "200" ] && grep -q '<sitemapindex' "$BODY"; then
    pass "sitemap index renders"
    mapfile -t SITEMAP_PARTS < <(grep -oE '<loc>[^<]+</loc>' "$BODY" \
        | sed -e 's|</\?loc>||g' | grep -F "$BASE")
else
    fail "sitemap index should render (got $STATUS)"
    SITEMAP_PARTS=()
fi

# One part per deploy, chosen at random. Each is ~10 MB uncompressed, and sampling from
# one of them is what the old single-file version effectively did — except that this one
# can land anywhere in the catalogue, where the old file only ever held the first 3,022
# pages. Downloading all of them on every deploy would cost 30 MB for five sampled URLs.
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
            OC_MEM=$(grep -o '"memoryPercentUsed":[0-9.]*' "$BODY" | cut -d: -f2)
            OC_KEYS=$(grep -o '"keysPercentUsed":[0-9.]*' "$BODY" | cut -d: -f2)
            OC_SCRIPTS=$(grep -o '"cachedScripts":[0-9]*' "$BODY" | cut -d: -f2)
            OC_MAXKEYS=$(grep -o '"maxCachedKeys":[0-9]*' "$BODY" | cut -d: -f2)
            OC_HIT=$(grep -o '"hitRatePercent":[0-9.]*' "$BODY" | cut -d: -f2)
            OC_INTERNED=$(grep -o '"internedPercentUsed":[0-9.]*' "$BODY" | cut -d: -f2)
            OC_OOM=$(grep -o '"oomRestarts":[0-9]*' "$BODY" | cut -d: -f2)
            OC_HASH=$(grep -o '"hashRestarts":[0-9]*' "$BODY" | cut -d: -f2)
            pass "OPcache ${OC_MEM:-?}% memory, ${OC_KEYS:-?}% of ${OC_MAXKEYS:-?} keys (${OC_SCRIPTS:-?} scripts), ${OC_HIT:-?}% hit rate"

            if awk "BEGIN { exit !(${OC_MEM:-0} >= 80) }"; then
                echo "WARN  OPcache memory is ${OC_MEM}% used — raise opcache.memory_consumption" >&2
            fi
            if awk "BEGIN { exit !(${OC_KEYS:-0} >= 80) }"; then
                echo "WARN  OPcache is using ${OC_KEYS}% of its key table — raise opcache.max_accelerated_files" >&2
            fi
            if awk "BEGIN { exit !(${OC_INTERNED:-0} >= 90) }"; then
                echo "WARN  OPcache interned-strings buffer is ${OC_INTERNED}% used — raise opcache.interned_strings_buffer" >&2
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

    # The ported JSON routes, including the deprecation header.
    for V in v1 v2; do
        fetch "$BASE/en/api/$V/associations/shrines.json"
        json_ok "symfony: api/$V shrines.json" '"FeatureCollection"'
        if [ "$(header deprecation)" = "true" ]; then
            pass "symfony: api/$V shrines.json still announces its deprecation"
        else
            fail "symfony: api/$V shrines.json lost Deprecation: true under the Symfony kernel"
        fi
    done

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

    # Gzip integrity on /en/sitemap.xml. **This no longer tests
    # App\Http\LaminasResponseConverter**, which is what it was written for: the route was
    # ported on 2026-08-13, so the response is Symfony's now, and what it proves today is
    # that App\Http\GzipListener leaves an already-gzipped body alone instead of encoding
    # it twice — the failure that would make the file unreadable to every crawler while
    # still answering 200. The converter's own behaviour needs another vehicle; no
    # remaining bridged route gzips unconditionally, which is the property this one was
    # borrowing.
    # `--no-compressed` matters, and a request header alone will not do it: CURL_OPTS
    # carries --compressed, which makes curl decode any Content-Encoding it understands
    # whatever Accept-Encoding says — so the magic-byte check below would compare against
    # plaintext no matter what the server actually sent. The generator writes the file
    # gzipped, so identity encoding still yields gzip.
    EXTRA_HEADERS=(${SYMFONY_HEADERS[@]+"${SYMFONY_HEADERS[@]}"} --no-compressed
        -H 'Accept-Encoding: identity')
    fetch "$BASE/en/sitemap.xml"
    if [ "$STATUS" = "200" ] && [ "$(header content-encoding)" = "gzip" ] \
        && [ "$(head -c2 "$BODY" | od -An -tx1 | tr -d ' \n')" = "1f8b" ]; then
        pass "sitemap.xml is still real gzip under its Content-Encoding"
    else
        # a body that is not gzip under a gzip header is either double-encoded on the way
        # out or de-gzipped with the header left on — unreadable to a crawler either way
        fail "sitemap.xml should be gzip-encoded gzip (got $STATUS, '$(header content-encoding)')"
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
if [ "$FAILURES" -gt 0 ]; then
    echo "$FAILURES smoke check(s) FAILED against $BASE" >&2
    exit 1
fi
echo "All production smoke checks passed. Still manual: a sign-in round trip."
