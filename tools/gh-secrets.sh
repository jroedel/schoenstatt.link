#!/usr/bin/env bash
#
# Upload the deploy section of secrets.env to the GitHub `production` environment, as
# ONE secret — DEPLOY_SECRETS_ENV — that .github/workflows/deploy.yml writes to a file
# on the runner and hands to tools/deploy.sh.
#
#     make gh-secrets                        # set DEPLOY_SECRETS_ENV from secrets.env
#     ./tools/gh-secrets.sh --list           # what is set there now, names and dates only
#     ./tools/gh-secrets.sh --delete-legacy  # remove the 13 per-field secrets it replaced
#
# ONE SECRET, NOT THIRTEEN. Each field used to be its own secret, set by a loop that
# had to agree with a list in deploy.yml, and the one the loop did not know —
# DEPLOY_KNOWN_HOSTS — was set by hand and drifted unseen. A bundle is the file itself:
# a name dropped from secrets.env is gone from the runner at the next run, and nothing
# needs deleting to keep the two in step.
#
# WHAT IS IN IT: the DEPLOY_* names the runner needs, including the CI deploy key and
# the pinned host key, both base64. NEVER an APP_* name — production's local.php is
# rendered on the server by `make prod-config` and the runner has no use for it — and
# never DEPLOY_API_KEY_PREVIOUS, which exists only on the server mid-rotation. The
# retention knobs are left out too: they are policy, and an unattended run keeps the
# scripts' own defaults so a runner cannot prune differently from a laptop.
#
# It prints names, never values, and the bundle goes to `gh secret set` on stdin: a
# --body argument is visible in `ps` and lands in shell history.
#
# WHAT IT DELIBERATELY DOES NOT DO: create the environment, set the branch restriction, or
# add a required reviewer. Those are the protections, and a protection that a script can
# turn on is a protection a script can turn off. They are yours, in the web UI, once.
set -uo pipefail

cd "$(dirname "$0")/.."
# shellcheck source=tools/secrets-env.sh
. tools/secrets-env.sh

ENVIRONMENT=production
BUNDLE=DEPLOY_SECRETS_ENV

# The per-field secrets the bundle replaced, for --delete-legacy.
LEGACY=(
    DEPLOY_SSH_USER DEPLOY_SSH_HOST DEPLOY_SSH_PORT DEPLOY_APP_PATH DEPLOY_BASE_URL
    DEPLOY_API_KEY DEPLOY_CANARY_COOKIE
    DEPLOY_DB_NAME DEPLOY_DB_USER DEPLOY_DB_PASS DEPLOY_DB_TUNNEL_PORT
    DEPLOY_KNOWN_HOSTS DEPLOY_SSH_KEY
)

# Required, then optional: what the runner can be given empty, it is not given at all.
REQUIRED=(
    DEPLOY_SSH_USER DEPLOY_SSH_HOST DEPLOY_APP_PATH DEPLOY_BASE_URL DEPLOY_API_KEY
    DEPLOY_CI_SSH_KEY_B64 DEPLOY_KNOWN_HOSTS_B64
)
OPTIONAL=(
    DEPLOY_SSH_PORT DEPLOY_CANARY_COOKIE
    DEPLOY_DB_NAME DEPLOY_DB_USER DEPLOY_DB_PASS DEPLOY_DB_TUNNEL_PORT
)

C_OK=$'\033[32m'; C_WARN=$'\033[33m'; C_DIM=$'\033[2m'; C_OFF=$'\033[0m'
ok()   { printf '  %sok%s    %s\n' "$C_OK" "$C_OFF" "$1"; }
warn() { printf '  %swarn%s  %s\n' "$C_WARN" "$C_OFF" "$1"; }
dim()  { printf '%s      %s%s\n' "$C_DIM" "$1" "$C_OFF"; }
fail() { printf '\n  %sFAIL%s  %s\n\n' $'\033[31m' "$C_OFF" "$1" >&2; exit 1; }

command -v gh >/dev/null || fail "gh is not installed."
gh auth status >/dev/null 2>&1 || fail "gh is not authenticated. Run: gh auth login"

case ${1:-} in
    --list)
        gh secret list --env "$ENVIRONMENT"
        exit 0
        ;;
    --delete-legacy)
        SET=$(gh secret list --env "$ENVIRONMENT" --json name --jq '.[].name') \
            || fail "could not list the $ENVIRONMENT environment's secrets."
        grep -qx "$BUNDLE" <<< "$SET" \
            || fail "$BUNDLE is not set. Run make gh-secrets, and let a deploy pass with it, first."
        PRESENT=()
        for name in "${LEGACY[@]}"; do
            grep -qx "$name" <<< "$SET" && PRESENT+=("$name")
        done
        [ ${#PRESENT[@]} -gt 0 ] || { ok "none of the per-field secrets is left"; exit 0; }
        printf 'Delete %d per-field secret(s) from %s: %s\n' "${#PRESENT[@]}" "$ENVIRONMENT" "${PRESENT[*]}"
        if [ -t 0 ]; then
            printf 'Only once a deploy has passed with %s. Delete? [y/N] ' "$BUNDLE"
            read -r ANSWER < /dev/tty
            case $ANSWER in y|Y|yes) ;; *) fail "nothing deleted." ;; esac
        fi
        for name in "${PRESENT[@]}"; do
            gh secret delete "$name" --env "$ENVIRONMENT" || fail "could not delete $name"
            ok "deleted $name"
        done
        exit 0
        ;;
    '') ;;
    *) printf 'usage: %s [--list | --delete-legacy]\n' "$0" >&2; exit 2 ;;
esac

secrets_env_load "$SECRETS_ENV_FILE" || exit 1

printf '\n\033[1m%s → %s environment\033[0m\n' "$BUNDLE" "$ENVIRONMENT"

INCLUDED=()
for name in "${REQUIRED[@]}" "${OPTIONAL[@]}"; do
    value=${!name:-}
    case $value in
        # An unreplaced placeholder is worse than a missing value: the deploy gets far
        # enough to fail somewhere confusing. tools/deploy.sh refuses the same test.
        *TODO*) fail "$name still contains a TODO in $SECRETS_ENV_FILE." ;;
    esac
    if [ -z "$value" ]; then
        case " ${REQUIRED[*]} " in
            *" $name "*) fail "$name is not set in $SECRETS_ENV_FILE." ;;
        esac
        # Legitimately empty for DEPLOY_CANARY_COOKIE, and for DEPLOY_DB_* when
        # migrations are left to an operator.
        warn "$name is empty — left out"
        continue
    fi
    INCLUDED+=("$name")
done

# Both keys are decoded and checked here rather than on the runner, where a bad paste
# would surface four steps into a deploy.
# 0600 for everything below: ssh-keygen refuses a private key anyone else can read.
umask 077
WORK=$(mktemp -d) || fail "could not create a temporary directory"
trap 'rm -rf "$WORK"' EXIT
printf '%s' "$DEPLOY_CI_SSH_KEY_B64" | base64 -d > "$WORK/key" 2>/dev/null \
    || fail "DEPLOY_CI_SSH_KEY_B64 is not base64."
ssh-keygen -y -P '' -f "$WORK/key" > "$WORK/key.pub" 2>/dev/null \
    || fail "DEPLOY_CI_SSH_KEY_B64 is not a passphrase-less private key; there is no agent on a runner."
printf '%s' "$DEPLOY_KNOWN_HOSTS_B64" | base64 -d > "$WORK/known_hosts" 2>/dev/null \
    || fail "DEPLOY_KNOWN_HOSTS_B64 is not base64."
ssh-keygen -l -f "$WORK/known_hosts" >/dev/null 2>&1 \
    || fail "DEPLOY_KNOWN_HOSTS_B64 does not decode to known_hosts lines."
ok "the CI key and the host key decode"

# Built whole before anything is sent: a line that fails inside a pipe into `gh` would
# upload the bundle without it, and the pipe would report success.
CONTENT=$(
    printf '# %s — written by tools/gh-secrets.sh, %s. The deploy section of secrets.env.\n' \
        "$BUNDLE" "$(date -u +%Y-%m-%dT%H:%MZ)"
    for name in "${INCLUDED[@]}"; do secrets_env_line "$name" "${!name}" || exit 1; done
) || fail "the bundle could not be written; nothing was sent."
printf '%s\n' "$CONTENT" | gh secret set "$BUNDLE" --env "$ENVIRONMENT" || fail "could not set $BUNDLE"
ok "$BUNDLE holds: ${INCLUDED[*]}"

# Whether the server accepts the key, against the pinned host key and nothing else.
# Cheap, and the alternative is finding out from a workflow run.
if ssh -i "$WORK/key" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=10 \
       -o UserKnownHostsFile="$WORK/known_hosts" -o StrictHostKeyChecking=yes \
       -p "${DEPLOY_SSH_PORT:-222}" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" true 2>/dev/null; then
    ok "the server accepts the CI key, and its host key is the pinned one"
else
    printf '\n  %sThe server did not accept the CI key against the pinned host key.%s\n' "$C_WARN" "$C_OFF"
    dim "If the key is new, append this line to ~/.ssh/authorized_keys on the server:"
    printf '\n'
    cat "$WORK/key.pub"
    printf '\n'
    dim "If the host key changed, check why before updating DEPLOY_KNOWN_HOSTS_B64."
fi

printf '\nStill yours to do in the web UI, once:\n'
printf '  Settings → Environments → %s → Deployment branches and tags → master\n' "$ENVIRONMENT"
dim "workflow_dispatch runs from ANY branch, so without this a branch carrying an edited"
dim "deploy.yml can read this secret."
printf '\n'
