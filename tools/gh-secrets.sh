#!/usr/bin/env bash
#
# Upload the deploy configuration to the GitHub `production` environment, so
# .github/workflows/deploy.yml can rebuild .deploy.local on a runner.
#
#     ./tools/gh-secrets.sh            # set every secret from .deploy.local
#     ./tools/gh-secrets.sh --list     # what is set there now, names and dates only
#
# WHY THIS IS A SCRIPT AND NOT A PASTED COMMAND. Credentials rotate, and a rotation that
# has to be re-typed into a web form field by field is a rotation that gets half done. This
# reads the same file tools/deploy.sh reads, so the two can never disagree, and it is
# idempotent: run it again after editing .deploy.local and the environment catches up.
#
# It prints names, never values — not on success, not in an error. `gh secret set` takes
# each value on stdin for the same reason: a --body argument is visible in `ps` and lands
# in shell history.
#
# WHAT IT DELIBERATELY DOES NOT DO: create the environment, set the branch restriction, or
# add a required reviewer. Those are the protections, and a protection that a script can
# turn on is a protection a script can turn off. They are yours, in the web UI, once.
set -uo pipefail

cd "$(dirname "$0")/.."

ENVIRONMENT=production
CONFIG=${DEPLOY_CONFIG:-.deploy.local}
KEY_FILE=${DEPLOY_CI_KEY:-$HOME/.ssh/schoenstatt-deploy-ci}

C_OK=$'\033[32m'; C_WARN=$'\033[33m'; C_DIM=$'\033[2m'; C_OFF=$'\033[0m'
ok()   { printf '  %sok%s    %s\n' "$C_OK" "$C_OFF" "$1"; }
warn() { printf '  %swarn%s  %s\n' "$C_WARN" "$C_OFF" "$1"; }
dim()  { printf '%s      %s%s\n' "$C_DIM" "$1" "$C_OFF"; }
fail() { printf '\n  %sFAIL%s  %s\n\n' $'\033[31m' "$C_OFF" "$1" >&2; exit 1; }

command -v gh >/dev/null || fail "gh is not installed."
gh auth status >/dev/null 2>&1 || fail "gh is not authenticated. Run: gh auth login"

if [ "${1:-}" = "--list" ]; then
    gh secret list --env "$ENVIRONMENT"
    exit 0
fi

[ -f "$CONFIG" ] || fail "$CONFIG is missing. Run ./config.sh, then fill in the TODOs."

# Sourced in a subshell's worth of scope only because this script does nothing else; the
# file is shell assignments, which is the whole contract tools/deploy.sh relies on too.
# shellcheck disable=SC1090
. "$CONFIG"

# The eleven the workflow reads. DEPLOY_KEEP_RELEASES and the backup-retention knobs are
# deliberately absent: they are policy, not credentials, and the workflow leaves them at
# tools/deploy.sh's defaults so a runner cannot quietly prune differently from a laptop.
FIELDS=(
    DEPLOY_SSH_USER DEPLOY_SSH_HOST DEPLOY_SSH_PORT DEPLOY_APP_PATH DEPLOY_BASE_URL
    DEPLOY_API_KEY DEPLOY_CANARY_COOKIE
    DEPLOY_DB_NAME DEPLOY_DB_USER DEPLOY_DB_PASS DEPLOY_DB_TUNNEL_PORT
)

printf '\n\033[1mSecrets → %s environment\033[0m\n' "$ENVIRONMENT"

SET=0 EMPTY=0
for FIELD in "${FIELDS[@]}"; do
    VALUE=${!FIELD:-}
    case "$VALUE" in
        # An unreplaced placeholder is worse than a missing value: the deploy gets far
        # enough to fail somewhere confusing. tools/deploy.sh aborts its preflight on the
        # same test.
        *TODO*) fail "$FIELD still contains a TODO in $CONFIG." ;;
    esac
    if [ -z "$VALUE" ]; then
        # Legitimately empty for DEPLOY_CANARY_COOKIE, and for DEPLOY_DB_* when migrations
        # are left to an operator. Skipped rather than set empty, so `gh secret list` shows
        # what the runner will actually have.
        warn "$FIELD is empty — not set"
        EMPTY=$((EMPTY + 1))
        continue
    fi
    printf '%s' "$VALUE" | gh secret set "$FIELD" --env "$ENVIRONMENT" \
        || fail "could not set $FIELD"
    ok "$FIELD"
    SET=$((SET + 1))
done

# --- the deploy key -----------------------------------------------------------
#
# Its own key, not yours: revoking CD should not mean rotating the key you use daily. No
# passphrase, because there is no agent on a runner and nobody to type one — what protects
# it is that it lives in a GitHub secret and opens one account on one port.
printf '\n\033[1mDeploy key\033[0m\n'

if [ ! -f "$KEY_FILE" ]; then
    ssh-keygen -t ed25519 -N '' -C "schoenstatt.link CD $(date +%Y-%m-%d)" -f "$KEY_FILE" >/dev/null \
        || fail "could not generate a key at $KEY_FILE"
    ok "generated $KEY_FILE"
else
    ok "using the existing $KEY_FILE"
fi

gh secret set DEPLOY_SSH_KEY --env "$ENVIRONMENT" < "$KEY_FILE" \
    || fail "could not set DEPLOY_SSH_KEY"
ok "DEPLOY_SSH_KEY"
SET=$((SET + 1))

printf '\n%s%d secret(s) set, %d skipped as empty.%s\n' "$C_DIM" "$SET" "$EMPTY" "$C_OFF"

# Whether the server will accept it. Cheap, and the alternative is finding out from a
# workflow run that fails four steps in.
if ssh -i "$KEY_FILE" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=10 \
       -p "${DEPLOY_SSH_PORT:-222}" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" true 2>/dev/null; then
    ok "the server already accepts this key"
else
    printf '\n  %sThe server does not accept it yet.%s Install the public half:\n\n' "$C_WARN" "$C_OFF"
    printf '    ssh-copy-id -i %s.pub -p %s %s@%s\n\n' \
        "$KEY_FILE" "${DEPLOY_SSH_PORT:-222}" "$DEPLOY_SSH_USER" "$DEPLOY_SSH_HOST"
    dim "or append this line to ~/.ssh/authorized_keys on the server:"
    printf '\n'
    cat "$KEY_FILE.pub"
    printf '\n'
fi

printf '\nStill yours to do in the web UI, once:\n'
printf '  Settings → Environments → %s → Deployment branches and tags → master\n' "$ENVIRONMENT"
dim "workflow_dispatch runs from ANY branch, so without this a branch carrying an edited"
dim "deploy.yml can read these secrets. It is the gate until required reviewers exist."
printf '\n'
