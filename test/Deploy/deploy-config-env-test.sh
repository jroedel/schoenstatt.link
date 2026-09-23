#!/usr/bin/env bash
#
# Every script that reads the deploy credentials honours $DEPLOY_CONFIG.
#
# WHAT THIS PREVENTS, and it is not hypothetical. tools/deploy.sh learned DEPLOY_CONFIG so
# a CI runner could write the credentials outside the workspace — nothing later can archive
# them, and `git status` stays clean, which the preflight insists on. But deploy.sh shells
# out to tools/migrate.sh at BOTH migration phases, and migrate.sh kept its own hardcoded
# `CONFIG=$REPO_ROOT/.deploy.local`. The export reached deploy.sh and stopped there.
#
# The first real deploy from Actions died on it (2026-09-23), at step 8 of 15:
#
#     [8] Pre-deploy migrations
#     ABORT: .deploy.local is missing. Run ./config.sh, then fill in the TODOs.
#
# It failed safe — pre-deploy migrations run before the symlink swap, so production stayed
# on the previous release with its data untouched. It failed after uploading a release,
# taking the server lock and running composer, for a reason that had nothing to do with the
# release. And no check could see it: the credentials file exists on a workstation, so
# every local run of every check took the fallback path and passed.
#
# That is the shape worth guarding. A variable that only matters when a file is ABSENT is
# invisible to anyone who has the file.
#
# The test is textual on purpose. Actually running these scripts means connecting to
# production, which is exactly what a check in CI must not do; what can be verified without
# a server is that each one reads the variable at all, and that is the whole of the bug.
set -uo pipefail

cd "$(dirname "$0")/../.."

FAILED=0
check() {
    if [ "$1" = 0 ]; then
        printf '  ok    %s\n' "$2"
    else
        printf '  FAIL  %s\n' "$2"
        FAILED=$((FAILED + 1))
    fi
}

# Discovered, not listed. A hand-maintained list is the thing that went stale here in the
# first place: migrate.sh was already reading the credentials when deploy.sh grew the
# variable, and nothing connected the two.
READERS=$(grep -rlE '^\s*[A-Za-z_]+=.*\.deploy\.local|\.deploy\.local"?$' tools/*.sh 2>/dev/null \
    | xargs grep -lE '^\s*\.\s+"\$\{?[A-Za-z_]+' 2>/dev/null | sort)

[ -n "$READERS" ] || { echo "found no scripts sourcing .deploy.local — the discovery pattern has rotted" >&2; exit 1; }

COUNT=0
for script in $READERS; do
    COUNT=$((COUNT + 1))
    # The variable must be USED, not merely mentioned. Grepping for the bare name passes
    # on a script that only names it in a comment — which this test demonstrated on itself:
    # with the bug reintroduced, migrate.sh still matched, because the comment explaining
    # the fix survived the revert. So: it has to appear in a parameter expansion.
    if grep -qE '\$\{DEPLOY_CONFIG(:-|\})' "$script"; then
        check 0 "$(basename "$script") honours \$DEPLOY_CONFIG"
    else
        check 1 "$(basename "$script") reads .deploy.local but ignores \$DEPLOY_CONFIG"
        printf '        %s\n' "$(grep -nE '\.deploy\.local' "$script" | grep -vE '^\s*[0-9]+:\s*#' | head -2)"
    fi
done

# The one that actually broke, checked by behaviour rather than by grep: point it at a file
# that is not there and it must name THAT path, proving it read the variable rather than
# merely mentioning it.
#
# --env=capsule, and that matters. migrate.sh cds to the repo root before resolving its
# fallback, so a workstation running this WITH the bug present would source the real
# credentials and connect — and with --env=production that connection is an SSH tunnel to
# the live database, opened by a test. The capsule is local, harmless, and distinguishes
# the two paths just as well.
OUT=$(DEPLOY_CONFIG=/nonexistent/deploy.local bash tools/migrate.sh status --env=capsule 2>&1)
case "$OUT" in
    *'/nonexistent/deploy.local'*) check 0 "migrate.sh reports the DEPLOY_CONFIG path it was given" ;;
    *) check 1 "migrate.sh ignored DEPLOY_CONFIG; it said: $(printf '%s' "$OUT" | head -2 | tr '\n' ' ')" ;;
esac

if [ "$FAILED" = 0 ]; then
    echo "deploy config env: all checks passed ($COUNT script(s) inspected)"
    exit 0
fi
echo "deploy config env: $FAILED check(s) failed" >&2
exit 1
