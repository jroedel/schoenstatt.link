#!/usr/bin/env bash
#
# The destructive-migration drift wait in tools/deploy.sh, driven without a server.
#
# WHAT THIS PROTECTS. Before a destructive migration runs, every PHP pool must report the
# release that was just swapped in — otherwise a DROP lands under code that still SELECTs
# what it drops, which is the 2026-08-18 outage. The check that enforces this used to
# abort the first time any round disagreed, so its real patience was one interval. Its own
# error message said drift takes about four minutes to clear. Those two numbers disagreed,
# and the code's was the smaller one: on 2026-09-24 the first destructive migration to
# meet it (#310, dropping eight IP columns) aborted after 56 seconds on a correct deploy.
#
# So the loop now waits drift out. That makes it a state machine with three ways to be
# wrong, and every one of them is silent in the dangerous direction:
#
#   * returning success on a streak that was interrupted — the whole point is three
#     CONSECUTIVE agreements, and a pool that answers correctly once before revealing
#     itself must not count;
#   * waiting out a condition that will never clear — a wrong API key answers "cannot
#     read" forever, and burning the full timeout on it just delays the same failure;
#   * never giving up at all, which turns a stuck pool into a hung deploy.
#
# None of those produce an error anywhere. They produce a migration that ran when it
# should not have, or a deploy that hangs.
#
# HOW. The loop is extracted between its `>>> drift bookkeeping` markers and sourced, the
# same technique segment-gate-test.sh and rsh-behaviour-test.sh use. `assert_live_revision`
# is replaced by a scripted sequence of return codes and `sleep` by a counter, so a
# 480-second timeout is exercised in microseconds. An extraction that finds nothing is a
# failure, not a skip.
#
# UNDER `set -euo pipefail`, WHICH IS NOT OPTIONAL HERE. tools/deploy.sh sets it at line
# 56, and this loop's whole job is to keep going when a helper returns non-zero — which is
# exactly what errexit turns into an immediate exit. The first version of this file sourced
# the block into a plain shell, so a bare `assert_live_revision "$want"` passed every check
# here and killed the deploy on the first disagreement in production (#311, 2026-09-24),
# reproducing the bug it was written to fix while printing a message promising to wait 480s.
# An extraction test that runs code under different shell options is testing different code.
#
# WHAT THIS DOES NOT COVER, because the gap is the interesting half: whether
# `assert_live_revision` itself can see a stale pool. That needs a live multi-pool host and
# is what opcache-swap-test.sh and segment-gate-test.sh address from the other side. Here
# its answers are assumed and only the decision made from them is under test.
set -uo pipefail

DEPLOY_SH=${1:-$(dirname "$0")/../../tools/deploy.sh}
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

awk '/^# >>> drift bookkeeping/{f=1} f{print} /^# <<< drift bookkeeping/{exit}' \
    "$DEPLOY_SH" > "$WORK/drift.sh"
if ! grep -q '^await_revision_agreement() {' "$WORK/drift.sh"; then
    echo "FAIL: could not extract the drift bookkeeping from $DEPLOY_SH" >&2
    echo "      (the >>> / <<< markers moved, or await_revision_agreement left the block)" >&2
    exit 1
fi

# Fast, deterministic stand-ins. The interval stays 45 so the arithmetic under test is the
# real one; `sleep` is what gets replaced, not the number it is given.
export DEPLOY_DRIFT_TIMEOUT=480
export DEPLOY_DRIFT_INTERVAL=45
export DEPLOY_DRIFT_STREAK=3
# shellcheck source=/dev/null
source "$WORK/drift.sh"

# A loop that never terminates must FAIL, not hang. The first draft of this file let a
# no-timeout mutation run forever; the harness killed it, the truncated output contained no
# FAIL lines, and the mutation therefore read as caught when nothing had caught it. In CI
# that shape is worse than having no test: it hangs the job instead of failing it. So the
# sleep stub is also the watchdog — past a ceiling far above any legitimate run (480/45 is
# eleven) it aborts the attempt, which run_case reports as rc 99 and every check rejects.
SLEEP_CEILING=200
SLEEPS=0
sleep() {
    SLEEPS=$((SLEEPS + 1))
    if [ "$SLEEPS" -gt "$SLEEP_CEILING" ]; then
        echo "RUNAWAY: await_revision_agreement slept $SLEEPS times without returning" >&2
        exit 99
    fi
}
warn() { WARNINGS="$WARNINGS|$*"; }

# assert_live_revision walks SEQUENCE, one code per call, repeating the LAST code forever
# once exhausted — which is what makes "drifts and never recovers" expressible.
SEQUENCE=()
CALLS=0
assert_live_revision() {
    local idx=$CALLS
    CALLS=$((CALLS + 1))
    [ "$idx" -ge "${#SEQUENCE[@]}" ] && idx=$(( ${#SEQUENCE[@]} - 1 ))
    return "${SEQUENCE[$idx]}"
}

FAILURES=0
check() {
    if [ "$1" = 0 ]; then
        printf '  ok    %s\n' "$2"
    else
        printf '  FAIL  %s\n' "$2"
        FAILURES=$((FAILURES + 1))
    fi
}

# Run one attempt in a subshell so the watchdog's exit is contained, and read the state
# back out. rc 99 means the attempt never returned — a watchdog abort, or errexit killing
# the subshell at an unguarded call — and no check accepts it.
#
# `set -euo pipefail` inside the subshell is the point, not housekeeping: it is what
# tools/deploy.sh runs under. The `|| rc=$?` here guards only THIS call; an unguarded call
# *inside* await_revision_agreement still takes the subshell down, which is the production
# failure this reproduces.
run_case() {
    local out status
    out=$(
        set -euo pipefail
        SEQUENCE=("$@")
        CALLS=0
        SLEEPS=0
        WARNINGS=
        rc=0
        await_revision_agreement deadbeef || rc=$?
        printf '%s %s %s %s %s' "$rc" "$DRIFT_WAITED" "$CALLS" "$SLEEPS" "$WARNINGS"
    )
    status=$?
    if [ "$status" != 0 ]; then
        RC=99; DRIFT_WAITED=0; CALLS=0; SLEEPS=0; WARNINGS=
        return
    fi
    read -r RC DRIFT_WAITED CALLS SLEEPS WARNINGS <<<"$out"
}

# --- 1. The happy path must not become slower. ------------------------------------------
run_case 0 0 0
[ "$RC" = 0 ] && [ "$SLEEPS" = 2 ] && [ "$CALLS" = 3 ]
check $? "three clean rounds return success after exactly 2 sleeps (rc=$RC sleeps=$SLEEPS calls=$CALLS)"

# --- 2. Drift is waited out rather than aborted on. --------------------------------------
# The exact shape of #310: agreement, then a pool reveals itself, then it recycles.
run_case 0 1 0 0 0
[ "$RC" = 0 ]
check $? "a pool appearing mid-check is waited out instead of failing the deploy (rc=$RC)"
case $WARNINGS in *'agreement broke after 1 round(s)'*) check 0 "the broken streak is reported, not swallowed" ;;
                  *) check 1 "nothing warned that the streak restarted; got: ${WARNINGS:-<nothing>}" ;; esac

# --- 3. The streak must be CONSECUTIVE. --------------------------------------------------
# Five agreements in total, never three in a row, then it settles. A loop that counted
# agreements rather than consecutive ones would have returned success at the third call.
run_case 0 0 1 0 0 1 0 0 0
[ "$RC" = 0 ] && [ "$CALLS" = 9 ]
check $? "interrupted agreements do not accumulate; success needs 3 in a row (calls=$CALLS)"

# --- 4. It gives up eventually rather than hanging. --------------------------------------
run_case 0 1
[ "$RC" = 1 ] && [ "$DRIFT_WAITED" -ge 480 ]
check $? "drift that never clears times out instead of hanging (rc=$RC waited=${DRIFT_WAITED}s)"
[ "$SLEEPS" -ge 10 ]
check $? "and it really did wait ~480s of intervals first, not one (sleeps=$SLEEPS)"

# --- 5. An unreadable revision is NOT drift. ---------------------------------------------
# A wrong key answers 2 forever. Waiting it out would delay the same failure by the whole
# timeout while the log looked like an ordinary slow recycle.
run_case 2
[ "$RC" = 2 ] && [ "$SLEEPS" = 0 ] && [ "$CALLS" = 1 ]
check $? "an unreadable revision returns immediately, unwaited (rc=$RC sleeps=$SLEEPS calls=$CALLS)"

# --- 6. …even after the streak has started. ----------------------------------------------
run_case 0 0 2
[ "$RC" = 2 ] && [ "$CALLS" = 3 ]
check $? "an unreadable revision mid-streak still refuses rather than waiting (rc=$RC)"

# --- 7. Success must never be reachable while a round disagrees. --------------------------
# The dangerous direction, stated as its own check: whatever the sequence, rc 0 means the
# last DRIFT_STREAK calls all answered 0.
run_case 1 1 1 1 0 0 0
[ "$RC" = 0 ]
check $? "recovery after prolonged drift still succeeds once it holds (rc=$RC)"

# --- 8. The loop must survive errexit being ACTIVE inside it. ----------------------------
# Bash suppresses `set -e` inside a function invoked as part of a `||` or `&&` list, so
# run_case above — which needs `|| rc=$?` to read the return code — cannot see an unguarded
# call inside the body. That is not a detail: it is why the first version of this file
# passed while the shipped loop died on the first disagreement.
#
# So this calls the function BARE, with errexit live inside it, using a sequence that ends
# in agreement. A guarded body runs to completion and prints. An unguarded
# `assert_live_revision "$want"` returns 1 at the second round and errexit kills the
# subshell before the printf, so the output is empty.
# NOTE the missing `|| BARE=`. Measured on bash 5.x:
#
#   o=$( set -e; ... )            errexit ACTIVE inside the substitution
#   o=$( set -e; ... ) || o=x     errexit SUPPRESSED inside the substitution
#
# A `||` on the assignment disables errexit in the very code being tested, which is how
# the first two attempts at this check reported a clean pass against the bug that had
# just taken a production deploy down. The status is read from $? on the next line
# instead. The outer script deliberately does not use `set -e`, so a dying subshell here
# is a failed check rather than a dead test run.
BARE=$(
    set -euo pipefail
    SEQUENCE=(0 1 0 0 0)
    CALLS=0
    SLEEPS=0
    WARNINGS=
    await_revision_agreement deadbeef
    printf 'completed after %s round(s)' "$CALLS"
)
BARE_STATUS=$?
[ "$BARE_STATUS" = 0 ] || BARE=
case $BARE in
    'completed after 5 round(s)')
        check 0 "the loop survives a non-zero helper with errexit active (called bare)" ;;
    '')
        check 1 "errexit killed the loop at the first disagreement: an unguarded call inside
        await_revision_agreement. deploy.sh runs under set -euo pipefail; use '|| rc=\$?'" ;;
    *)
        check 1 "bare call under errexit produced unexpected output: $BARE" ;;
esac

# --- 9. The call site outside the extracted block must be guarded too. -------------------
# await_revision_agreement returns non-zero by design, so deploy.sh calling it bare would
# end the deploy before the `case` that turns those codes into messages. That call lives
# outside the >>> markers, so only a textual check reaches it.
if grep -qE '^\s*await_revision_agreement "\$SHA" \|\| [A-Z_]+=\$\?' "$DEPLOY_SH"; then
    check 0 "deploy.sh calls await_revision_agreement with a '|| rc=\$?' errexit guard"
else
    check 1 "deploy.sh calls await_revision_agreement unguarded; set -e will kill the deploy
        before the case that reports why"
fi

if [ "$FAILURES" = 0 ]; then
    echo "drift wait: all checks passed"
    exit 0
fi
echo "drift wait: $FAILURES check(s) failed" >&2
exit 1
