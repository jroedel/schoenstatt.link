#!/usr/bin/env bash
#
# The cache-status number extraction in tools/smoke-prod.sh, without a live site.
#
# On 2026-08-19 a successful deploy's smoke run died with
#
#   tools/smoke-prod.sh: line 423: 77
#   838 / 60 : syntax error in expression (error token is "838 / 60 ")
#
# `uptimeSeconds` appears in BOTH the APCu and the OPcache sections of
# /en/sm/cache-status, so an unqualified grep returned two lines and $(( )) was
# handed a newline. `hits` and `misses` are duplicated the same way and are simply
# not read yet.
#
# The bug was unreachable locally, which is the real point of this file:
# tools/ci-local.sh runs the PHPUnit test/Smoke suite against the capsule, while
# tools/smoke-prod.sh is a separate script that only ever executes against
# production, as the last step of a deploy. Nothing exercised its parsing until it
# ran for real. So the parsing now lives between `>>> cache-status parsing` markers
# and is driven here against a fixture that contains the duplicates.
#
# The fixture is shaped like the real payload (checked against production
# 2026-08-19) with deliberately DIFFERENT values in the two sections, so reading the
# wrong one is a wrong answer rather than a coincidence.
set -uo pipefail

SMOKE_SH=${1:-$(dirname "$0")/../../tools/smoke-prod.sh}
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

awk '/^# >>> cache-status parsing/{f=1} f{print} /^# <<< cache-status parsing/{exit}' \
    "$SMOKE_SH" > "$WORK/parsing.sh"
for fn in oc_num retired_keys; do
    if ! grep -q "^$fn() {" "$WORK/parsing.sh"; then
        echo "FAIL: could not extract $fn from $SMOKE_SH" >&2
        echo "      (the >>> / <<< markers moved, or the function left the block)" >&2
        exit 1
    fi
done
# shellcheck source=/dev/null
source "$WORK/parsing.sh"

# APCu first, then the nested opcache object — the real order. uptimeSeconds, hits
# and misses appear in both, with different values.
cat > "$WORK/body.json" <<'JSON'
{"apcuEnabled":true,"totalBytes":268435336,"usedBytes":8590360,"percentUsed":3.3,"entries":26,"hits":1108,"misses":929,"expunges":0,"uptimeSeconds":77,"sionModel":{"maxCachedItemSize":4194304,"retiredConfigKeys":[]},"opcache":{"enabled":true,"memoryPercentUsed":24.3,"keysPercentUsed":12.9,"cachedScripts":1069,"maxCachedKeys":16229,"hits":69889,"misses":1215,"hitRatePercent":96.8,"oomRestarts":0,"hashRestarts":0,"internedPercentUsed":88.4,"internedBufferConfiguredMb":8,"uptimeSeconds":838,"ini":{"opcache.validate_timestamps":"1","opcache.revalidate_freq":"2"}}}
JSON

FAILURES=0
check() {
    if [ "$2" = PASS ]; then
        printf '  \033[32mok\033[0m    %s\n' "$1"
    else
        printf '  \033[1;31mFAIL\033[0m  %s\n' "$1"
        FAILURES=$((FAILURES + 1))
    fi
}
is() {
    local label=$1 got=$2 want=$3
    [ "$got" = "$want" ] && check "$label" PASS || check "$label (got '$got', wanted '$want')" FAIL
}

# 1. The bug itself. 838 is OPcache's; 77 is APCu's and must not appear.
is "uptimeSeconds comes from the opcache object" "$(oc_num uptimeSeconds "$WORK/body.json")" 838

# 2. Single line, which is the property $(( )) actually needs. A value can be right
#    and still be two lines, and that is the failure mode that shipped.
lines=$(oc_num uptimeSeconds "$WORK/body.json" | wc -l | tr -d ' ')
is "it returns exactly one line" "$lines" 1

# 3. The arithmetic that died. Run it for real rather than asserting about it.
if mins=$(( $(oc_num uptimeSeconds "$WORK/body.json") / 60 )) 2>/dev/null; then
    is "the uptime arithmetic evaluates" "$mins" 13
else
    check "the uptime arithmetic still fails" FAIL
fi

# 4. The other two duplicated keys, not read today. They are checked because the
#    next person to surface a hit rate will reach for exactly these names.
is "hits comes from the opcache object" "$(oc_num hits "$WORK/body.json")" 69889
is "misses comes from the opcache object" "$(oc_num misses "$WORK/body.json")" 1215

# 5. Values that never collided must still read correctly.
is "memoryPercentUsed" "$(oc_num memoryPercentUsed "$WORK/body.json")" 24.3
is "internedPercentUsed" "$(oc_num internedPercentUsed "$WORK/body.json")" 88.4
is "maxCachedKeys" "$(oc_num maxCachedKeys "$WORK/body.json")" 16229

# 6. An APCu-only key must come back EMPTY, not with the APCu value. This is what
#    proves the slice happened rather than the grep merely having got lucky on
#    ordering — `expunges` sits before the opcache object.
is "an APCu-only key reads empty when scoped to opcache" "$(oc_num expunges "$WORK/body.json")" ""

# 7. A key that does not exist reads empty, so the ${VAR:-?} fallbacks show '?'
#    rather than the script dying.
is "an absent key reads empty" "$(oc_num noSuchKey "$WORK/body.json")" ""

# 8. A body with no opcache object at all: the caller guards on that, but the
#    parser must not invent a number from the APCu half if the guard is ever moved.
printf '%s\n' '{"apcuEnabled":true,"uptimeSeconds":77,"expunges":0}' > "$WORK/apcu-only.json"
is "no opcache object yields nothing, not the APCu value" "$(oc_num uptimeSeconds "$WORK/apcu-only.json")" ""

# 9. The sionModel block. maxCachedItemSize must not be confused with OPcache's
#    maxCachedKeys, which is a different key with a common prefix — a grep for
#    "maxCached" would match both, and the two live in different objects.
is "maxCachedItemSize is not confused with maxCachedKeys" \
    "$(grep -o '"maxCachedItemSize":[0-9]*' "$WORK/body.json" | cut -d: -f2)" 4194304

# 10. The expected reading: an empty list is empty output, so the caller's
#     `[ -n "$RETIRED" ]` does not warn about nothing.
is "an empty retired-key list reads empty" "$(retired_keys "$WORK/body.json")" ""

# 11. A populated list. Bare names, space separated — the JSON quoting and commas
#     must not reach the warning text a deploy prints.
printf '%s\n' '{"apcuEnabled":true,"sionModel":{"maxCachedItemSize":4194304,"retiredConfigKeys":["max_items_to_cache","some_other_key"]},"opcache":{"enabled":true}}' > "$WORK/retired.json"
is "a populated retired-key list is unquoted and space separated" \
    "$(retired_keys "$WORK/retired.json")" "max_items_to_cache some_other_key"

# 12. A server older than this script has no such field. That must read as "nothing
#     to report", never as a warning, and never as a parse that swallows the whole
#     document — which is what an unguarded sed substitution would do.
is "an absent sionModel block reads empty" "$(retired_keys "$WORK/apcu-only.json")" ""

echo
if [ "$FAILURES" = 0 ]; then
    echo "cache-status parsing: all checks passed"
else
    echo "cache-status parsing: $FAILURES check(s) FAILED"
fi
exit "$FAILURES"
