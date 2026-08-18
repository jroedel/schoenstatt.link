#!/usr/bin/env bash
#
# The per-segment gate in tools/deploy.sh, exercised without deploying anything.
#
# Two deploys (2026-08-18, 2026-08-19) swapped correctly and then aborted, because
# the reset loop stopped on "8 consecutive /_health probes agree". /_health and the
# reset helper are separate requests that need not land on the same pool, so eight
# agreements can be one lucky pool answering while another has never been reset —
# and afterwards every production segment read `manualRestarts: 0`. The loop now
# tracks each segment by its own id and refuses to finish while any of them is
# still serving the previous release.
#
# The decision that encodes is `segment_verdict`, and it is the kind of thing that
# fails silently in the dangerous direction: a verdict that answers `ok` too readily
# makes the gate pass while a pool serves old code, which by construction produces
# no error anywhere. So it is extracted between its `>>> segment bookkeeping`
# markers and driven with synthetic responses, the same way
# rsh-behaviour-test.sh drives the remote-call machinery. An extraction that finds
# nothing is a failure, not a skip.
#
# Check 4 is the one worth stating: **census mode must not read as healthy.** The
# helper answers `stale=-` when asked to report without resetting, and treating that
# as `ok` would let a run of census-mode reads satisfy the gate having reset nothing
# at all.
#
# This needs no server and no capsule — it is text handling.
#
# What it does NOT cover, stated because the gap is the interesting half: the loop's
# own state machine — one entry per segment, the coverage floor from the pre-swap
# census, the run of clean hits — needs a live multi-pool host, and this host does
# not exist locally. Mutating `pending` to `ok` inside the loop passes every check
# here. `test/Deploy/opcache-swap-test.sh` covers the single-segment mechanism the
# loop rests on; nothing covers the multi-segment arithmetic but production.
set -uo pipefail

DEPLOY_SH=${1:-$(dirname "$0")/../../tools/deploy.sh}
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

awk '/^# >>> segment bookkeeping/{f=1} f{print} /^# <<< segment bookkeeping/{exit}' \
    "$DEPLOY_SH" > "$WORK/segments.sh"
if ! grep -q '^segment_verdict() {' "$WORK/segments.sh"; then
    echo "FAIL: could not extract the segment bookkeeping from $DEPLOY_SH" >&2
    echo "      (the >>> / <<< markers moved, or segment_verdict left the block)" >&2
    exit 1
fi
# shellcheck source=/dev/null
source "$WORK/segments.sh"

FAILURES=0
check() {
    if [ "$2" = PASS ]; then
        printf '  \033[32mok\033[0m    %s\n' "$1"
    else
        printf '  \033[1;31mFAIL\033[0m  %s\n' "$1"
        FAILURES=$((FAILURES + 1))
    fi
}
verdict_is() {
    local label=$1 body=$2 want=$3 got
    got=$(segment_verdict "$body")
    [ "$got" = "$want" ] && check "$label" PASS || check "$label (got '$got', wanted '$want')" FAIL
}

RESET_DONE=$'age=1200\nsegment=1787086000\nrestarted=1787089000\ninterned=7000000/8388608/62867\nstale=0\nreset=skipped\nfrom=/x/releases/new/public'
STILL_STALE=$'age=1200\nsegment=1787086000\nrestarted=0\ninterned=7000000/8388608/62867\nstale=1\nreset=true\nfrom=/x/releases/new/public'
BORN_AFTER=$'age=30\nsegment=1787089500\nrestarted=0\ninterned=100/8388608/12\nstale=0\nreset=skipped\nfrom=/x/releases/new/public'
CENSUS=$'age=1200\nsegment=1787086000\nrestarted=0\ninterned=7000000/8388608/62867\nstale=-\nreset=census\nfrom=/x/releases/new/public'
NO_OPCACHE=$'age=-1\nsegment=0\nrestarted=0\ninterned=-\nstale=0\nreset=skipped\nfrom=/x/releases/new/public'

# 1-3. The three states the loop acts on.
verdict_is "a segment restarted after the swap is current" "$RESET_DONE" ok
verdict_is "a segment predating the swap is pending" "$STILL_STALE" pending
verdict_is "a segment born after the swap is current without a reset" "$BORN_AFTER" ok

# 4. Census mode reports; it does not certify. See the header.
verdict_is "census mode is unknown, never ok" "$CENSUS" unknown

# 5. A host with no OPcache reports segment=0 and cannot be judged. It must not
#    count as a healthy segment — the loop has a separate age=-1 exit for that.
verdict_is "segment=0 (no OPcache) is unknown, never ok" "$NO_OPCACHE" unknown

# 6. A dead or truncated read says nothing about any pool.
verdict_is "an empty body is unknown" "" unknown
verdict_is "a partial body with no stale line is unknown" $'age=5\nsegment=17' unknown

# 7. Fields are read from line starts only. `from=` carries a filesystem path, and a
#    release directory is free to contain any string at all — including one that
#    looks like another field.
#
#    The decoy is placed BEFORE the real field deliberately. With it after, `head -1`
#    hides a missing `^` anchor and the check passes either way — which is what the
#    first version of this test did, and mutating the anchor out proved it worthless.
#    Field order in the helper is not a property anything guarantees, so a parser that
#    depends on it is one reordering away from reading a path as a verdict.
tricky=$'from=/srv/segment=999/stale=0/public\nage=10\nsegment=42\nstale=1\nreset=true'
got=$(segment_field "$tricky" segment)
[ "$got" = 42 ] && check "segment_field anchors to line starts (got $got)" PASS \
                || check "segment_field read '$got' from a path containing segment= " FAIL
verdict_is "a path that looks like a field does not flip the verdict" "$tricky" pending

# 8. The helper PHP must parse. A hand-written one shipped a parse error during the
#    2026-08-17 incident and 30 requests to the resulting 500 were mistaken for 30
#    successful resets, so this is the cheapest check in the file and the one with
#    the worst precedent behind it.
awk '/src=\$\(cat <<.PHPEOF./{f=1;next} /^PHPEOF$/{exit} f{print}' "$DEPLOY_SH" \
    | sed 's/__TOKEN__/deadbeef/' > "$WORK/helper.php"
if ! grep -q 'opcache_reset' "$WORK/helper.php"; then
    check "could not extract the helper PHP from $DEPLOY_SH" FAIL
elif ! command -v php > /dev/null 2>&1; then
    printf '  \033[33mSKIP\033[0m  no php on PATH, cannot lint the helper\n'
elif php -l "$WORK/helper.php" > "$WORK/lint" 2>&1; then
    check "the opcache helper PHP parses ($(wc -l < "$WORK/helper.php") lines)" PASS
else
    sed 's/^/        /' "$WORK/lint" >&2
    check "the opcache helper PHP does not parse" FAIL
fi

echo
if [ "$FAILURES" = 0 ]; then
    echo "segment gate: all checks passed"
else
    echo "segment gate: $FAILURES check(s) FAILED"
fi
exit "$FAILURES"
