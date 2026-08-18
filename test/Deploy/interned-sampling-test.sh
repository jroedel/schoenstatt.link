#!/usr/bin/env bash
#
# The deploy's warm interned-strings reading, exercised without deploying anything.
#
# Same technique as rsh-behaviour-test.sh: tools/deploy.sh cannot be sourced, so this
# extracts the block between its `>>> interned sampling` markers and drives the real
# function with synthetic helper responses. A failed extraction is a failure, not a
# skip.
#
# ## Why this is worth a test at all
#
# The reading has exactly one opportunity to be taken — the request that resets the
# segment destroys the thing being measured, and the next chance is the next deploy.
# So a parsing bug here does not produce a wrong number that someone notices; it
# produces silence, on a line nobody was watching for, and the failure is
# indistinguishable from "no pool had been up long enough". That is precisely the
# shape of defect that survives for years.
#
# The cold-segment guard is the half that matters most. The interned buffer is
# append-only, so a young segment always looks healthy; reporting one would invite
# the exact misreading this whole mechanism exists to prevent.
set -uo pipefail

DEPLOY_SH=${1:-$(dirname "$0")/../../tools/deploy.sh}
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

awk '/^# >>> interned sampling/{f=1} f{print} /^# <<< interned sampling/{exit}' \
    "$DEPLOY_SH" > "$WORK/sampling.sh"
if ! grep -q '^sample_interned() {' "$WORK/sampling.sh"; then
    echo "FAIL: could not extract the interned sampler from $DEPLOY_SH" >&2
    echo "      (the >>> / <<< markers moved, or sample_interned() left the block)" >&2
    exit 1
fi
# shellcheck source=/dev/null
source "$WORK/sampling.sh"

FAILURES=0
check() {
    if [ "$2" = PASS ]; then
        printf '  \033[32mok\033[0m    %s\n' "$1"
    else
        printf '  \033[31mFAIL\033[0m  %s\n' "$1" >&2
        [ -n "${3:-}" ] && printf '        %s\n' "$3" >&2
        FAILURES=$((FAILURES + 1))
    fi
}

# What the helper writes, as bytes, so this test breaks if its output shape changes.
body() { printf 'age=%s\ninterned=%s\nreset=true\nfrom=/x\n' "$1" "$2"; }

# --- 1. a warm segment is recorded, with the arithmetic done right -----------
WARM_INTERNED=''
sample_interned "$(body 124200 '7053896/8388608/77504')"
# 124200s = 34.5h; 7053896/8388608 = 84.1%; 8388608 bytes = 8MB
case $WARM_INTERNED in
    *"34.5h"*"84.1% of 8MB"*"77504 strings"*) check "a warm segment renders age, ratio, size and count" PASS ;;
    *) check "a warm segment renders age, ratio, size and count" FAIL "got: $WARM_INTERNED" ;;
esac

# --- 2. a cold segment is not reported at all --------------------------------
# The single most important case. This loop resets every segment it touches, so
# most hits after the first per pool ARE cold, and reporting them would bury the
# three readings that mean something under thirty that do not.
WARM_INTERNED=''
sample_interned "$(body 12 '81920/8388608/900')"
check "a segment reset seconds ago is not reported" \
    "$([ -z "$WARM_INTERNED" ] && echo PASS || echo FAIL)" "got: $WARM_INTERNED"

# --- 3. the boundary is stated, not approximate ------------------------------
WARM_INTERNED=''
sample_interned "$(body 299 '4194304/8388608/40000')"
check "299s is still cold" "$([ -z "$WARM_INTERNED" ] && echo PASS || echo FAIL)" "got: $WARM_INTERNED"
WARM_INTERNED=''
sample_interned "$(body 300 '4194304/8388608/40000')"
check "300s is warm enough to report" \
    "$([ -n "$WARM_INTERNED" ] && echo PASS || echo FAIL)" "recorded nothing"

# --- 4. a host with no interned block at all ---------------------------------
# The helper prints `interned=-` rather than omitting the line, so this is about
# the parser refusing a sentinel rather than about a missing key.
WARM_INTERNED=''
sample_interned "$(body 90000 '-')"
check "a host reporting no interned block is skipped" \
    "$([ -z "$WARM_INTERNED" ] && echo PASS || echo FAIL)" "got: $WARM_INTERNED"

# --- 5. OPcache disabled ------------------------------------------------------
WARM_INTERNED=''
sample_interned "$(printf 'age=-1\nreset=NULL\n')"
check "an OPcache-less host is skipped" \
    "$([ -z "$WARM_INTERNED" ] && echo PASS || echo FAIL)" "got: $WARM_INTERNED"

# --- 6. a zero-sized buffer must not divide by zero ---------------------------
WARM_INTERNED=''
OUT=$(sample_interned "$(body 90000 '0/0/0')" 2>&1)
check "a zero-sized buffer is skipped rather than divided by" \
    "$([ -z "$WARM_INTERNED" ] && echo PASS || echo FAIL)" "got: $WARM_INTERNED / $OUT"

# --- 7. distinct pools accumulate, the same pool does not --------------------
# The reason the loop can call this on every hit: it fires ~40 times across three
# pools, and the operator wants three lines.
WARM_INTERNED=''
sample_interned "$(body 124200 '7053896/8388608/77504')"
sample_interned "$(body 124200 '7053896/8388608/77504')"
LINES=$(printf '%s' "$WARM_INTERNED" | grep -c .)
check "the same pool hit twice records one line" \
    "$([ "$LINES" = 1 ] && echo PASS || echo FAIL)" "recorded $LINES lines"

sample_interned "$(body 3600 '2097152/8388608/20000')"
LINES=$(printf '%s' "$WARM_INTERNED" | grep -c .)
check "a second, distinct pool records a second line" \
    "$([ "$LINES" = 2 ] && echo PASS || echo FAIL)" "recorded $LINES lines"

# --- 8. a raised buffer is reported as raised --------------------------------
# The whole point of the exercise: after opcache.interned_strings_buffer goes to
# 32, the line has to say 32 and a fraction of it, not 8 and a percentage of 8.
WARM_INTERNED=''
sample_interned "$(body 124200 '8388608/33554432/90000')"
case $WARM_INTERNED in
    *"25.0% of 32MB"*) check "a 32MB buffer is reported as 32MB at a quarter used" PASS ;;
    *) check "a 32MB buffer is reported as 32MB at a quarter used" FAIL "got: $WARM_INTERNED" ;;
esac

echo
if [ "$FAILURES" -gt 0 ]; then
    echo "$FAILURES check(s) failed" >&2
    exit 1
fi
echo "interned sampling: all checks passed"
