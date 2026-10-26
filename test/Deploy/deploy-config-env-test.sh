#!/usr/bin/env bash
#
# Every script that reads the deploy credentials honours $SECRETS_ENV — and reads them
# through tools/secrets-env.sh, never by sourcing a file.
#
# WHAT THIS PREVENTS, and it is not hypothetical. tools/deploy.sh learned DEPLOY_CONFIG (the
# variable's name until secrets.env replaced .deploy.local in 2026-10) so
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
#
# Since secrets.env, the path is resolved in ONE place — tools/secrets-env.sh, from
# $SECRETS_ENV — so the rule a reader must keep is to take the path from there:
# `secrets_env_load "$SECRETS_ENV_FILE"`, directly or through a variable assigned from it.
READERS=$(grep -lE 'secrets_env_load' tools/*.sh 2>/dev/null | grep -v 'tools/secrets-env.sh' | sort)

[ -n "$READERS" ] || { echo "found no scripts calling secrets_env_load — the discovery pattern has rotted" >&2; exit 1; }

COUNT=0
for script in $READERS; do
    COUNT=$((COUNT + 1))
    if grep -qE '\$\{?SECRETS_ENV_FILE' "$script"; then
        check 0 "$(basename "$script") takes the path from \$SECRETS_ENV_FILE"
    else
        check 1 "$(basename "$script") loads credentials from a path of its own"
    fi
done

# Sourcing is what secrets.env exists to end: a credentials file that is executed can be
# made to run somebody else's commands. A `. "$VAR"` of anything but tools/*.sh is that.
SOURCERS=$(grep -nE '^\s*(\.|source)\s+"?\$\{?[A-Za-z_]+' tools/*.sh 2>/dev/null \
    | grep -vE 'secrets-env\.sh|exception-store\.sh|REPO_ROOT/tools/' || true)
if [ -z "$SOURCERS" ]; then
    check 0 "no tools/*.sh sources a file named by a variable"
else
    check 1 "a script sources a file named by a variable: $(printf '%s' "$SOURCERS" | head -1)"
fi

# The one that actually broke, checked by behaviour rather than by grep: point it at a file
# that is not there and it must name THAT path, proving it read the variable rather than
# merely mentioning it.
#
# --env=capsule, and that matters. migrate.sh cds to the repo root before resolving its
# fallback, so a workstation running this WITH the bug present would source the real
# credentials and connect — and with --env=production that connection is an SSH tunnel to
# the live database, opened by a test. The capsule is local, harmless, and distinguishes
# the two paths just as well.
OUT=$(SECRETS_ENV=/nonexistent/secrets.env bash tools/migrate.sh status --env=capsule 2>&1)
case "$OUT" in
    *'/nonexistent/secrets.env'*) check 0 "migrate.sh reports the SECRETS_ENV path it was given" ;;
    *) check 1 "migrate.sh ignored SECRETS_ENV; it said: $(printf '%s' "$OUT" | head -2 | tr '\n' ' ')" ;;
esac

if [ "$FAILED" = 0 ]; then
    echo "deploy config env: all checks passed ($COUNT script(s) inspected)"
    exit 0
fi
echo "deploy config env: $FAILED check(s) failed" >&2
exit 1
