#!/usr/bin/env bash
#
# The handover between tools/prod-deploy.sh and tools/deploy.sh, without a server.
#
# Since 2026-09-11 a deploy is built in a checkout of its own
# (${XDG_CACHE_HOME:-~/.cache}/schoenstatt.link-deploy by default) rather than in the
# developer's working tree, so that a deploy and a working day can share a machine. Two
# properties of that arrangement are worth a test, because both fail silently:
#
#  1. **Whether the build was verified.** ci-local needs the capsule, and
#     docker-compose.yml bind-mounts the WORKING TREE into the capsule — so ci-local
#     cannot run in the deploy checkout at all. prod-deploy.sh runs it in the tree the
#     capsule serves, and only when that tree is on the very revision being shipped,
#     then tells deploy.sh which happened through DEPLOY_VERIFIED_SHA /
#     DEPLOY_UNVERIFIED_REASON. Get that contract wrong in the lenient direction and an
#     unverified build ships without the confirmation prompt that exists for exactly it.
#     Nothing else would notice: the deploy succeeds either way.
#
#  2. **That prod-deploy.sh really does leave the working tree alone.** It is one `git
#     checkout` away from being what it used to be, and the symptom of that regression
#     is not a failed deploy — it is a developer's branch disappearing mid-edit.
#
# The block under test is delimited by `>>> verification handover` markers in
# tools/deploy.sh and sourced here with the helpers stubbed, so this exercises the real
# text of the real script rather than a copy of its logic.
set -uo pipefail

DIR=$(cd "$(dirname "$0")/../.." && pwd)
DEPLOY_SH=${1:-$DIR/tools/deploy.sh}
PROD_DEPLOY_SH=${2:-$DIR/tools/prod-deploy.sh}
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

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

# ---------------------------------------------------------------------------
# 1. the verification handover
# ---------------------------------------------------------------------------

awk '/^# >>> verification handover/{f=1} f{print} /^# <<< verification handover/{exit}' \
    "$DEPLOY_SH" > "$WORK/handover.sh"
if ! grep -q 'VERIFIED_ELSEWHERE=0' "$WORK/handover.sh"; then
    echo "FAIL: could not extract the verification handover from $DEPLOY_SH" >&2
    echo "      (the >>> / <<< markers moved, or the block left them)" >&2
    exit 1
fi

# The block's world: the two variables it reads about this deploy, the array it appends
# to, and the reporting helpers. `ci_stub` stands in for the one line that would really
# run the suite, so "did it run ci-local" is observable.
cat > "$WORK/harness.sh" <<'HARNESS'
set -uo pipefail
SHA=1111111111111111111111111111111111111111
SHORT=1111111
UNUSUAL=()
step() { :; }
ok()   { :; }
warn() { :; }
dim()  { :; }
fail() { printf 'FAILED:%s\n' "$1"; exit 1; }
ci_stub() { RAN_CI=1; }
RAN_CI=0
source <(sed 's|bash tools/ci-local.sh --ci .*|ci_stub|' "$BLOCK")
printf 'skip=%s verified=%s ran_ci=%s unusual=%s\n' \
    "$SKIP_TESTS" "$VERIFIED_ELSEWHERE" "$RAN_CI" "${#UNUSUAL[@]}"
HARNESS

handover() { env BLOCK="$WORK/handover.sh" "$@" bash "$WORK/harness.sh"; }

# The whole point of the redesign: prod-deploy.sh proved THIS revision green in the
# capsule's tree, so deploy.sh must neither repeat the run nor call it unusual.
is "a revision verified by prod-deploy.sh is not re-verified and is not unusual" \
    "$(handover SKIP_TESTS=0 DRY_RUN=0 DEPLOY_VERIFIED_SHA=1111111111111111111111111111111111111111)" \
    "skip=1 verified=1 ran_ci=0 unusual=0"

# master moving between prod-deploy.sh's fetch and this one. What was proven green is
# then not what is being shipped, and that is a human's call, not a script's.
is "a verified revision that is not this one is unusual" \
    "$(handover SKIP_TESTS=0 DRY_RUN=0 DEPLOY_VERIFIED_SHA=2222222222222222222222222222222222222222)" \
    "skip=1 verified=0 ran_ci=0 unusual=1"

# The case this test exists for. ci-local could not speak for this build; deploy.sh must
# NOT quietly run it in the deploy checkout (where it would test the wrong tree), and
# must ask before the swap.
is "an unverifiable build skips ci-local and is unusual" \
    "$(handover SKIP_TESTS=0 DRY_RUN=0 DEPLOY_UNVERIFIED_REASON='the capsule serves a dirty tree')" \
    "skip=1 verified=0 ran_ci=0 unusual=1"

# deploy.sh run on its own, from a working tree, still verifies the way it always did.
is "deploy.sh on its own still runs ci-local" \
    "$(handover SKIP_TESTS=0 DRY_RUN=0)" \
    "skip=0 verified=0 ran_ci=1 unusual=0"

# --skip-tests keeps its own wording and its own unusual entry.
is "--skip-tests is still unusual on its own terms" \
    "$(handover SKIP_TESTS=1 DRY_RUN=0)" \
    "skip=1 verified=0 ran_ci=0 unusual=1"

# A dry run never reaches the swap, so it has nothing to confirm.
is "a dry run is not unusual" \
    "$(handover SKIP_TESTS=1 DRY_RUN=1)" \
    "skip=1 verified=0 ran_ci=0 unusual=0"

# ---------------------------------------------------------------------------
# 2. prod-deploy.sh does not touch the working tree
# ---------------------------------------------------------------------------

# Every mutating git command in the script must name $DEPLOY_TREE on its own line —
# either as `-C "$DEPLOY_TREE"` or, for the one clone, as the destination. Read-only
# commands (`submodule status`, `merge-base`, `rev-parse`, `status`) are the script's
# business in either tree and are deliberately not listed.
#
# Lines inside a multi-line double-quoted string are skipped by quote parity, because
# the refusal messages quote commands FOR THE OPERATOR to run — advice, not action, and
# a substring match would flag it.
STRAY=$(awk '
    {
        start_in = in_str
        line = $0
        gsub(/\\"/, "", line)
        if (gsub(/"/, "\"", line) % 2) in_str = !in_str
        if (start_in) next
        if ($0 ~ /^[[:space:]]*#/) next
        if ($0 !~ /git[[:space:]]+(-C[[:space:]]+[^ ]+[[:space:]]+)?(clone|checkout|reset|pull|stash|clean|fetch|merge |remote set-url|submodule (update|sync|foreach))/) next
        if ($0 ~ /\$DEPLOY_TREE/) next
        printf "%d: %s\n", NR, $0
    }' "$PROD_DEPLOY_SH")
if [ -z "$STRAY" ]; then
    check "every mutating git command in prod-deploy.sh names \$DEPLOY_TREE" PASS
else
    check "prod-deploy.sh mutates something outside the deploy checkout:" FAIL
    printf '%s\n' "$STRAY" | sed 's/^/          /'
fi

# ---------------------------------------------------------------------------
# 3. the deploy checkout may not live inside the working tree
# ---------------------------------------------------------------------------

# Everything under the project root is either tracked, deliberately ignored, or swept by
# the test and search tooling; a second copy of the application there is found by all
# three. The refusal is the first thing the script does, before any git command, so this
# costs nothing and touches nothing.
OUT=$(DEPLOY_TREE="$DIR/inside" bash "$PROD_DEPLOY_SH" 2>&1)
STATUS=$?
if [ "$STATUS" != 0 ] && printf '%s' "$OUT" | grep -q 'inside the working tree'; then
    check "a DEPLOY_TREE inside the working tree is refused" PASS
else
    check "a DEPLOY_TREE inside the working tree is refused (exit $STATUS)" FAIL
    printf '%s\n' "$OUT" | sed 's/^/          /'
fi

echo
if [ "$FAILURES" = 0 ]; then
    echo "deploy checkout handover: all checks passed"
else
    echo "deploy checkout handover: $FAILURES check(s) FAILED"
fi
exit "$FAILURES"
