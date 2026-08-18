#!/usr/bin/env bash
#
# Does a release symlink swap take effect, and does opcache_reset() force it?
#
# `docs/incident-2026-08-17-stale-opcache.md` said this could not be tested here —
# "the capsule cannot reproduce any of it: one pool, no release symlink". That was
# wrong, and it mattered: four protections in tools/deploy.sh were built on an
# unverified mechanism, one of which (breaking the index.php hardlink) is described
# in the script itself as "reasoned insurance, not a proven fix".
#
# It reproduces with four files. Two release directories holding a **byte-identical**
# index.php — production's really is identical across releases, which is why it had
# eight hard links — plus a lib.php that differs, and a symlink between them.
#
# The one precondition, and the reason this looked untestable: **warm workers**. With
# two requests of warm-up the swap takes effect instantly and it reads as a clean
# non-repro. After ~60 requests, enough for every prefork worker to have served one,
# the old release keeps being served. Measured 2026-08-18 on PHP 8.5.9.
#
# What each check is for:
#
#   2. Records which path OPcache keys on. The incident doc records this as "never
#      established" and assumed the symlink path; it is the **resolved** path. The
#      stale mapping lives in the path-alias keys instead, which is why production
#      reports roughly twice as many cachedKeys as cachedScripts.
#   3. The hazard itself. A failure here is not necessarily a break — it may mean a
#      PHP or host change removed the hazard, which would be excellent news and must
#      be read before anyone relaxes the deploy gate on the strength of it.
#   4. **The check that earns this file.** tools/deploy.sh answers the hazard by
#      writing a randomly-named PHP file into every release and calling
#      opcache_reset() until /_health agrees. If reset ever stops clearing a stale
#      resolution, that entire protection is void and no post-deploy migration is
#      safe. Nothing else in the repository would notice.
#
# What it does NOT cover: production has three OPcache segments and this has one, so
# per-segment coverage — the thing that actually failed on 2026-08-18, when the reset
# never landed on the busiest pool — cannot be exercised here. Local green does not
# predict swap coherence across pools.
#
# Two side effects, both deliberate: it calls opcache_reset() on the capsule (a later
# smoke run recompiles, costing seconds), and it writes into public/ for the duration.
# Both are cleaned up on exit, including on Ctrl-C.
#
#   bash test/Deploy/opcache-swap-test.sh
#   OPCACHE_SWAP_FULL=1 bash test/Deploy/opcache-swap-test.sh   # + the ~140s expiry check
set -uo pipefail

BASE=${OPCACHE_SWAP_BASE_URL:-http://localhost:8080}
WARM=${OPCACHE_SWAP_WARM:-60}
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
RIG=$ROOT/public/zz-swap-test
URL=$BASE/zz-swap-test/current/index.php

# This writes files into a docroot and resets an opcode cache. Against production it
# would do both to the live site, so refuse anything that is not plainly local.
case $BASE in
    http://localhost:*|http://127.0.0.1:*|http://[::1]:*) ;;
    *)
        echo "REFUSING: $BASE is not a local capsule URL." >&2
        echo "opcache swap coherence: REFUSED (non-local base URL)"
        exit 1
        ;;
esac

if ! curl --silent --max-time 5 -o /dev/null "$BASE/" 2>/dev/null; then
    # A skip, not a failure: this is the one test/Deploy check that needs a server,
    # and `tools/ci-local.sh --ci` is expected to run without one.
    echo "opcache swap coherence: SKIPPED (no capsule answering at $BASE)"
    exit 0
fi

rm -rf "$RIG"
trap 'rm -rf "$RIG"' EXIT
mkdir -p "$RIG/rel-a" "$RIG/rel-b"

cat > "$RIG/rel-a/index.php" <<'PHPEOF'
<?php

// Byte-identical in both releases, exactly as production's public/index.php is.
// The release-identifying difference lives in the included file, as it does there.
header('Content-Type: text/plain');
require __DIR__ . '/lib.php';
printf("release=%s\n", zz_release());
printf("dir=%s lib_dir=%s\n", __DIR__, zz_lib_dir());
printf("script_filename=%s\n", $_SERVER['SCRIPT_FILENAME'] ?? '-');
printf("realpath_ttl=%s\n", ini_get('realpath_cache_ttl'));
$status = @opcache_get_status(true);
foreach (($status['scripts'] ?? []) as $key => $script) {
    if (str_contains($key, 'zz-swap-test')) {
        printf("cached_key=%s\n", $key);
    }
}
if (isset($_GET['reset'])) {
    echo 'reset=', var_export(opcache_reset(), true), "\n";
}
PHPEOF

printf '<?php\nfunction zz_release(): string { return "A"; }\nfunction zz_lib_dir(): string { return __DIR__; }\n' > "$RIG/rel-a/lib.php"
cp -p "$RIG/rel-a/index.php" "$RIG/rel-b/index.php"
sed 's/return "A";/return "B";/' "$RIG/rel-a/lib.php" > "$RIG/rel-b/lib.php"
ln -sfn rel-a "$RIG/current"

FAILURES=0
check() {
    if [ "$2" = PASS ]; then
        printf '  \033[32mok\033[0m    %s\n' "$1"
    else
        printf '  \033[1;31mFAIL\033[0m  %s\n' "$1"
        FAILURES=$((FAILURES + 1))
    fi
}

probe() { curl --silent --max-time 15 "$URL"; }
# $1 probes, counting how many report the release named in $2.
count_release() {
    local n=$1 want=$2 i hits=0
    for ((i = 0; i < n; i++)); do
        [ "$(probe | sed -n 's/^release=//p')" = "$want" ] && hits=$((hits + 1))
    done
    printf '%s' "$hits"
}

# Start from an empty cache so the rig's own state cannot be inherited from a
# previous run, then warm every worker.
curl --silent --max-time 15 -o /dev/null "$URL?reset=1"
sleep 1
for ((i = 0; i < WARM; i++)); do curl --silent --max-time 15 -o /dev/null "$URL"; done

# 1. The rig itself works before anything is swapped. Without this a broken rig
#    would present as the hazard having disappeared.
warm_a=$(count_release 12 A)
if [ "$warm_a" = 12 ]; then
    check "the rig serves release A before the swap (12/12)" PASS
else
    check "the rig should serve A before the swap, got $warm_a/12 — rig broken, later checks are meaningless" FAIL
fi

# 2. Which path is the cache entry filed under?
#
#    Retried, because an empty script list is a timing artefact rather than an
#    answer: the run opens with opcache_reset(), the restart completes only once
#    every worker has detached, and until it does OPcache caches nothing. Sampling
#    once failed roughly one run in three. This is also worth knowing about the
#    deploy, which fires resets and then immediately reads what is cached.
keys=''
for _ in $(seq 1 10); do
    keys=$(probe | sed -n 's/^cached_key=//p')
    [ -n "$keys" ] && break
    sleep 1
done
if printf '%s' "$keys" | grep -q '/rel-a/index.php' && ! printf '%s' "$keys" | grep -q '/current/index.php'; then
    check "OPcache keys the entry on the resolved path, not the requested symlink path" PASS
else
    check "cache keys changed shape — expected a /rel-a/ key and no /current/ key, got: $(printf '%s' "$keys" | tr '\n' ' ')" FAIL
fi

# The swap, done exactly as tools/deploy.sh does it: a single rename(2), then the
# hardlink-breaking re-stamp of index.php.
ln -sfn rel-b "$RIG/current.swap" && mv -Tf "$RIG/current.swap" "$RIG/current"
touch "$RIG/rel-b/index.php"

# 3. Warm workers keep serving the old release. Asserted as "at least one", not
#    "all": a worker that never served a warm request resolves the symlink fresh and
#    correctly answers B, and how many of those there are is scheduling, not
#    behaviour. One stale answer is the whole hazard — it is a live request served
#    by code the symlink no longer points at.
stale=$(count_release 12 A)
if [ "$stale" -ge 1 ]; then
    check "a swap is invisible to warm workers ($stale/12 probes still served the old release)" PASS
else
    check "the swap was picked up immediately by all 12 probes — the hazard did not reproduce; see this file's header before relaxing anything in tools/deploy.sh" FAIL
fi

# 4. opcache_reset() clears it. This is the deploy's actual mechanism.
for ((i = 0; i < 15; i++)); do curl --silent --max-time 15 -o /dev/null "$URL?reset=1"; done
fresh=$(count_release 20 B)
if [ "$fresh" = 20 ]; then
    check "opcache_reset() makes the swap visible (20/20 probes served the new release)" PASS
else
    check "after 15 resets only $fresh/20 probes served the new release — tools/deploy.sh's cache reset no longer forces a swap, and its migration gate rests on it" FAIL
fi

# 5. Optional: the stale resolution also expires on its own, at realpath_cache_ttl.
#    Off by default because it costs ~140s of waiting. It is the measurement behind
#    "production stayed stale for 12 and 20 minutes against the capsule's 120s", the
#    open question about production's own realpath_cache_ttl.
if [ "${OPCACHE_SWAP_FULL:-0}" = 1 ]; then
    # Read from the SAPI that will actually serve the probes, not assumed: it is
    # PHP_INI_SYSTEM, so it is whatever the image was built with.
    ttl=$(probe | sed -n 's/^realpath_ttl=//p')
    [ -n "$ttl" ] || ttl=120
    ln -sfn rel-a "$RIG/current.swap" && mv -Tf "$RIG/current.swap" "$RIG/current"
    curl --silent --max-time 15 -o /dev/null "$URL?reset=1"
    sleep 1
    for ((i = 0; i < WARM; i++)); do curl --silent --max-time 15 -o /dev/null "$URL"; done
    t0=$(date +%s)
    ln -sfn rel-b "$RIG/current.swap" && mv -Tf "$RIG/current.swap" "$RIG/current"
    touch "$RIG/rel-b/index.php"
    flipped=''
    while [ $(($(date +%s) - t0)) -lt $((ttl + 40)) ]; do
        if [ "$(count_release 6 B)" = 6 ]; then
            flipped=$(($(date +%s) - t0))
            break
        fi
        sleep 5
    done
    if [ -n "$flipped" ] && [ "$flipped" -ge 60 ]; then
        check "the stale resolution expires on its own after ${flipped}s (realpath_cache_ttl=$ttl)" PASS
    elif [ -n "$flipped" ]; then
        check "expired after only ${flipped}s, far below realpath_cache_ttl=$ttl — a different mechanism is at work" FAIL
    else
        check "still stale after $((ttl + 40))s; it did not expire at realpath_cache_ttl=$ttl" FAIL
    fi
fi

echo
if [ "$FAILURES" = 0 ]; then
    echo "opcache swap coherence: all checks passed"
else
    echo "opcache swap coherence: $FAILURES check(s) FAILED"
fi
exit "$FAILURES"
