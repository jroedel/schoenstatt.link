#!/usr/bin/env bash
#
# The deploy's remote-call machinery, exercised without deploying anything.
#
# tools/deploy.sh cannot be sourced — it loads .deploy.local and would try to deploy —
# so this extracts the block between its `>>> remote-call machinery` markers and drives
# the real functions with `bash -c` standing in for ssh. If the extraction finds
# nothing, that is a failure and not a skip: a test that quietly stops testing is worse
# than no test.
#
# The checks that earn this file are 3 and 7, both about **stdin**. Two call sites pipe
# into rsh — the `.revision` write and the opcache-helper distribution — and anything
# that takes stdin away breaks them silently: an empty .revision is then blamed on the
# server by the revision gate downstream. The realistic ways in are `ssh -n` and a
# `< /dev/null`, both of which are things people add on purpose to stop ssh consuming
# stdin in a loop.
#
# Check 3 covers rsh's own wiring; check 7 covers the ssh flags, which check 3 cannot
# see because it substitutes a local stand-in for ssh. That split was found by
# mutation: `ssh -n` and a backgrounded ssh both sailed past checks 1-6.
#
# Recorded because it is a live piece of folklore: backgrounding the ssh call does NOT
# break the piped sites on bash. `printf x | { cat > f & wait $!; }` writes x on 5.2 —
# the POSIX "asynchronous commands read from /dev/null" rule does not apply when stdin
# is an explicit redirection. ssh stays in the foreground for two other reasons (the
# passphrase prompt and Ctrl-C), which tools/deploy.sh states where it matters.
set -uo pipefail

DEPLOY_SH=${1:-$(dirname "$0")/../../tools/deploy.sh}
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

ERR=$WORK/stderr
OUT=$WORK/stdout

# --- extract ---------------------------------------------------------------
awk '/^# >>> remote-call machinery/{f=1} f{print} /^# <<< remote-call machinery/{exit}' \
    "$DEPLOY_SH" > "$WORK/machinery.sh"
if ! grep -q '^rsh() {' "$WORK/machinery.sh"; then
    echo "FAIL: could not extract the remote-call machinery from $DEPLOY_SH" >&2
    echo "      (the >>> / <<< markers moved, or rsh() left the block)" >&2
    exit 1
fi

C_DIM=''; C_OFF=''
warn() { printf 'WARN: %s\n' "$1" >&2; }
# shellcheck source=/dev/null
source "$WORK/machinery.sh"

# ssh, locally. `rsh "cmd"` becomes `bash -c "cmd"`, which keeps the same argument
# shape, the same stdin/stdout wiring and the same exit status.
SSH=(bash -c)
RSH_HEARTBEAT_AFTER=2
RSH_HEARTBEAT_EVERY=2

FAILURES=0
check() {
    if [ "$2" = PASS ]; then
        printf '  \033[32mok\033[0m    %s\n' "$1"
    else
        printf '  \033[1;31mFAIL\033[0m  %s\n' "$1"
        FAILURES=$((FAILURES + 1))
    fi
}

# 1. The common case: sub-second call, exit 0, output captured, nothing on stderr.
result=$(rsh "echo hi" 2>"$ERR"); status=$?
if [ "$status" = 0 ] && [ "$result" = hi ] && [ ! -s "$ERR" ]; then
    check "a fast call returns its output and says nothing" PASS
else
    check "a fast call (exit=$status out='$result' stderr='$(cat "$ERR")')" FAIL
fi

# 2. A slow call still returns clean stdout — the heartbeat must not land in the
#    capture. Six call sites read rsh through $( ), so this is not hypothetical.
result=$(rsh "sleep 5; echo payload" 2>"$ERR"); status=$?
beats=$(grep -c 'still waiting' "$ERR")
if [ "$status" = 0 ] && [ "$result" = payload ] && [ "$beats" -ge 1 ]; then
    check "a slow call beats on stderr and keeps stdout clean ($beats beats)" PASS
else
    check "a slow call (exit=$status out='$result' beats=$beats)" FAIL
fi

# 3. Piped stdin survives rsh's own wiring. See the header for what this does and
#    does not cover.
printf 'revision-sha' | rsh "cat > $OUT"; status=$?
written=$(cat "$OUT" 2>/dev/null || echo MISSING)
if [ "$status" = 0 ] && [ "$written" = revision-sha ]; then
    check "piped stdin reaches the remote command (.revision write)" PASS
else
    check "piped stdin (exit=$status wrote='$written') — has a redirect or -n crept into rsh?" FAIL
fi

# 4. A command that never returns is killed at its budget and reported as such.
status=0
RSH_TIMEOUT=3 RSH_LABEL="a wedged step" rsh "sleep 30" 2>"$ERR" || status=$?
if [ "$status" = 124 ] && grep -q 'no response from the server after 3s' "$ERR"; then
    check "a hung call is killed at its budget and named" PASS
else
    check "a hung call (exit=$status, expected 124)" FAIL
fi

# 5. A remote command's own failure passes through untouched. Conflating this with a
#    timeout would send the reader looking for a network problem that is not there.
status=0
rsh "exit 7" 2>"$ERR" || status=$?
if [ "$status" = 7 ] && ! grep -q 'no response' "$ERR"; then
    check "a remote failure keeps its exit status and is not called a timeout" PASS
else
    check "a remote failure (exit=$status, expected 7)" FAIL
fi

# 6. No heartbeat outlives its call. One that did would talk over every later step.
sleep 1
leftover=$(pgrep -P $$ sleep 2>/dev/null | wc -l | tr -d ' ')
if [ "$leftover" = 0 ]; then
    check "no heartbeat process is left running afterwards" PASS
else
    check "$leftover heartbeat process(es) left running" FAIL
fi

# 7. The ssh flags themselves, which check 3 cannot reach: `-n` redirects stdin from
#    /dev/null for every call, so it would silently empty the two piped ones. A static
#    read of the file is the only way to see this from here.
if grep -qE '^SSH=\(ssh( .*)? -n( |$)|^SSH=\(ssh -n ' "$DEPLOY_SH"; then
    check "the ssh invocation must not carry -n (it would empty .revision)" FAIL
else
    check "the ssh invocation does not carry -n" PASS
fi

echo
if [ "$FAILURES" = 0 ]; then
    echo "remote-call machinery: 7/7 checks passed"
else
    echo "remote-call machinery: $FAILURES check(s) FAILED"
fi
exit "$FAILURES"
