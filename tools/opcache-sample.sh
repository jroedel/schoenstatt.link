#!/usr/bin/env bash
# What each PHP pool's OPcache actually holds, sampled without a deploy.
#
#   ./tools/opcache-sample.sh              # 20 polls
#   ./tools/opcache-sample.sh -n 40        # more, when a segment is hiding
#   ./tools/opcache-sample.sh --raw        # one line per poll, nothing grouped
#
# ## Why one request is not an answer
#
# This host runs at least three PHP pools, each with its OWN OPcache segment
# (measured 2026-08-17: three distinct uptimes from one URL). A request reads
# whichever segment served it, so a single /sm/cache-status reading describes a
# third of production and cannot say which third. That is not a rounding error
# when the question is "did the new opcache.interned_strings_buffer take effect":
# the answer is genuinely different per pool, because the directive is
# PHP_INI_SYSTEM and each pool picks it up only when its processes restart.
#
# So: poll, and group by startTimeUnix — the only per-segment identity OPcache
# exposes. uptimeSeconds cannot do that job; it differs between two polls of the
# same segment.
#
# ## Why to run this BEFORE a deploy
#
# The interned-strings buffer is append-only. Nothing is ever evicted from it, so
# its usage only climbs within a segment's life and a deploy's opcache_reset()
# puts every segment back to near zero. A reading taken after a deploy is
# therefore a cold reading that will look healthy no matter how badly the buffer
# is undersized — 84% at nine minutes and 84% at nine days are opposite findings.
# The warmest reading available is the one taken just before a deploy resets it,
# which is also why tools/deploy.sh now prints each segment's usage at the moment
# it wipes it.
set -uo pipefail

cd "$(dirname "$0")/.."

POLLS=20
INTERVAL=0.4
RAW=0
while [ $# -gt 0 ]; do
    case $1 in
        -n|--polls)    POLLS=$2; shift 2 ;;
        --interval)    INTERVAL=$2; shift 2 ;;
        --raw)         RAW=1; shift ;;
        -h|--help)     sed -n '2,30p' "$0"; exit 0 ;;
        *) echo "unknown argument: $1" >&2; exit 2 ;;
    esac
done

# Same configuration file as the deploy, same two values out of it. Sourced
# rather than parsed because that is what tools/deploy.sh does and a second
# dialect of the same file is a bug waiting to happen.
CONFIG=.deploy.local
if [ -f "$CONFIG" ]; then
    # shellcheck disable=SC1090
    . "$CONFIG"
fi
BASE=${OPCACHE_SAMPLE_BASE_URL:-${DEPLOY_BASE_URL:-https://schoenstatt.link}}
KEY=${OPCACHE_SAMPLE_API_KEY:-${DEPLOY_API_KEY:-}}
[ -n "$KEY" ] || { echo "No maintenance key. Set DEPLOY_API_KEY in .deploy.local, or OPCACHE_SAMPLE_API_KEY." >&2; exit 1; }

URL="$BASE/en/sm/cache-status"
SAMPLES=$(mktemp -t opcache-sample-XXXXXX)
trap 'rm -f "$SAMPLES"' EXIT

field() { printf '%s' "$1" | grep -o "\"$2\":[0-9.]*" | head -1 | cut -d: -f2; }

echo "Polling $URL — $POLLS times, ${INTERVAL}s apart"
misses=0
for _ in $(seq 1 "$POLLS"); do
    body=$(curl --silent --show-error --max-time 20 --compressed \
        --header "X-Api-Key: $KEY" "$URL" 2>/dev/null) || body=''
    seg=$(field "$body" startTimeUnix)
    if [ -z "$seg" ]; then
        misses=$((misses + 1))
        printf '.'
        sleep "$INTERVAL"
        continue
    fi
    printf '%s %s %s %s %s %s %s %s\n' \
        "$seg" \
        "$(field "$body" uptimeSeconds)" \
        "$(field "$body" internedUsedBytes)" \
        "$(field "$body" internedBufferBytes)" \
        "$(field "$body" internedBufferConfiguredMb)" \
        "$(field "$body" internedStrings)" \
        "$(field "$body" cachedScripts)" \
        "$(field "$body" memoryPercentUsed)" >> "$SAMPLES"
    printf '#'
    sleep "$INTERVAL"
done
echo

if [ "$misses" -gt 0 ]; then
    echo "  ! $misses of $POLLS polls returned no segment (wrong key, or OPcache disabled on that pool)" >&2
fi
[ -s "$SAMPLES" ] || { echo "Nothing to report." >&2; exit 1; }

if [ "$RAW" = "1" ]; then
    echo
    echo "segment  uptime  internedUsed  internedBuffer  cfgMB  strings  scripts  mem%"
    cat "$SAMPLES"
    exit 0
fi

# Keep the LAST reading of each segment: within one segment the interned figures
# only rise, so the newest sample is the high-water mark this run observed.
awk '
{
    seg = $1
    seen[seg] = 1
    hits[seg]++
    uptime[seg] = $2; used[seg] = $3; buf[seg] = $4
    cfg[seg] = $5; strings[seg] = $6; scripts[seg] = $7; mem[seg] = $8
}
END {
    n = 0
    printf "\n%-12s %10s %9s %8s %10s %9s %7s %6s\n", \
        "segment", "uptime", "interned", "of", "strings", "scripts", "mem%", "polls"
    for (s in seen) {
        n++
        pct = buf[s] > 0 ? 100 * used[s] / buf[s] : 0
        hrs = uptime[s] / 3600
        printf "%-12s %8.1fh %8.1f%% %6dMB %10d %9d %6s%% %6d\n", \
            s, hrs, pct, cfg[s], strings[s], scripts[s], mem[s], hits[s]
    }
    printf "\n%d distinct segment%s in %d polls.\n", n, n == 1 ? "" : "s", NR
    # Honest bound rather than a claim of completeness: assuming requests land on
    # pools uniformly, this is the chance a further pool existed and never answered.
    if (n > 0) {
        p = 100 * exp(NR * log(n / (n + 1)))
        printf "A further pool would have had a %.2f%% chance of never answering — this is a lower bound, not a count.\n", p
    }
}
' "$SAMPLES"

echo
echo "Read the interned percentage together with the uptime beside it: the buffer is"
echo "append-only, so a young segment is always going to look healthy."
