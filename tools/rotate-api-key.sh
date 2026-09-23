#!/usr/bin/env bash
#
# Copy the maintenance key in .deploy.local to the two other places it has to be.
#
#     make prod-rotate-sion-model-api-keys            # distribute
#     make prod-rotate-sion-model-api-keys DRY_RUN=1  # report, change nothing
#
# .deploy.local IS THE SOURCE. This script never invents a key — you generate one and
# paste it in, which is what the comment above DEPLOY_API_KEY in .deploy.local.dist says:
#
#     openssl rand -base64 32 | tr -d '=+/'
#
# Then this copies it to:
#
#   2. the server  — `sion_model.api_keys` in shared/config-autoload/local.php, which is
#                    what App\Http\MaintenanceKey actually checks
#   3. GitHub      — the `production` environment's DEPLOY_API_KEY secret, which
#                    deploy.yml rebuilds .deploy.local from on a runner
#
# (3) is the one that gets forgotten, and forgetting it fails silently: nothing breaks
# today, and then the next deploy from Actions sends the old key, its post-deploy cache
# flush answers 401, and the release serves stale code out of a warm OPcache. So this
# calls tools/gh-secrets.sh rather than telling you to.
#
# WHY IT WIDENS BEFORE IT NARROWS. `api_keys` is a list, so both keys can be valid at
# once, and this order means there is never a moment when neither works:
#
#     add the new key     -> old and new both work
#     verify the new key  -> against the live site, before anything depends on it
#     update GitHub       -> the runner now rebuilds .deploy.local with the new key
#     keep only the new   -> the old key stops working
#     verify it is refused
#
# Any failure before the narrowing leaves the old key working, so a half-finished run is
# an inconvenience rather than an outage. It is also safe to re-run: if the server
# already holds only the key in .deploy.local, it says so and does nothing.
#
# WHAT IT WILL NOT DO: create a key where the server has none (it edits a configuration,
# it does not invent one), touch `schoenstatt.api_keys` — a different secret, read by
# LibraryNoticesController — or run a deploy.
set -uo pipefail

cd "$(dirname "$0")/.."

CONFIG=${DEPLOY_CONFIG:-.deploy.local}
# DRY_RUN=1 as well as --dry-run, because that is the variable the Makefile's other
# production targets take and one convention is better than two.
DRY_RUN="${DRY_RUN:-0}"
[ "${1:-}" = "--dry-run" ] && DRY_RUN=1

C_OK=$'\033[32m'; C_WARN=$'\033[33m'; C_ERR=$'\033[31m'; C_DIM=$'\033[2m'; C_B=$'\033[1m'; C_OFF=$'\033[0m'
step() { printf '\n%s=== %s%s\n' "$C_B" "$1" "$C_OFF"; }
ok()   { printf '  %sok%s    %s\n' "$C_OK" "$C_OFF" "$1"; }
warn() { printf '  %swarn%s  %s\n' "$C_WARN" "$C_OFF" "$1"; }
dim()  { printf '%s      %s%s\n' "$C_DIM" "$1" "$C_OFF"; }
fail() { printf '\n  %sFAIL%s  %s\n\n' "$C_ERR" "$C_OFF" "$1" >&2; exit 1; }

# Never printed in full, never passed as an argument (which `ps` would show). The last
# four characters are enough to tell two keys apart in a transcript and not enough to be
# one.
tail4() { printf '…%s' "${1: -4}"; }

# --- preflight ---------------------------------------------------------------

step "Preflight"

[ -f "$CONFIG" ] || fail "$CONFIG is missing. Run ./config.sh, then fill in the TODOs."
# shellcheck disable=SC1090
. "$CONFIG"

for v in DEPLOY_SSH_USER DEPLOY_SSH_HOST DEPLOY_APP_PATH DEPLOY_BASE_URL DEPLOY_API_KEY; do
    [ -n "${!v:-}" ] || fail "$v is not set in $CONFIG."
    case "${!v}" in *TODO*) fail "$v still contains a TODO in $CONFIG. Generate a key first: openssl rand -base64 32 | tr -d '=+/'" ;; esac
done
ok "$CONFIG"

# Not a strength requirement — the comparison is hash_equals over a shared secret, so what
# matters is that it came from a CSPRNG, which this cannot check. It catches a paste that
# went wrong.
[ ${#DEPLOY_API_KEY} -ge 20 ] \
    || fail "DEPLOY_API_KEY is only ${#DEPLOY_API_KEY} characters. Generate one: openssl rand -base64 32 | tr -d '=+/'"

command -v gh >/dev/null || fail "gh is not installed, so the GitHub secret could not be updated."
gh auth status >/dev/null 2>&1 || fail "gh is not authenticated. Run: gh auth login"
ok "gh is authenticated"

SSH=(ssh -o ControlMaster=auto -o ControlPath="$HOME/.ssh/cm-rotate-%C" -o ControlPersist=60
     -p "${DEPLOY_SSH_PORT:-222}" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST")

# shared/config-autoload/, which is where tools/deploy-bootstrap.sh puts the untracked
# machine config and where tools/deploy.sh symlinks it into each release from. Editing
# the copy inside a release would be undone by the next deploy.
REMOTE_LOCAL_PHP="$DEPLOY_APP_PATH/shared/config-autoload/local.php"

# The editor is piped to the server's php on stdin, never copied there: `php -- args`
# reads the script from stdin and still fills $argv, so a script that rewrites the site's
# configuration never exists as a file on the server and there is nothing to clean up.
edit() { "${SSH[@]}" "php -- $*" < tools/api-key-edit.php; }

# EDITING local.php IS NOT ENOUGH, and this is the step whose absence made the first real
# run fail. Production boots with config caching on: `data/config/module-config-cache.*.php`
# inside each release holds the MERGED configuration, written once and read on every
# request thereafter. Until it is gone the application keeps answering out of the old
# merge, so a new key sits in local.php and gets a 401 from the live site.
#
# Every release, not just the live one. Each has its own cache, written at its own deploy
# time, and a rollback would otherwise boot into a merge that predates this rotation — a
# 401 that appears only after a rollback is a bad way to find this out.
#
# `bin/console cache:clear-config` rather than `rm -f data/config/module-*`: the command
# takes the paths from the module listener options, so a changed cache key cannot leave it
# silently deleting nothing. It is a plain file cache, so a CLI process really can clear it
# (unlike APCu, which belongs to the web SAPI).
clear_config_cache() {
    "${SSH[@]}" -n "cd '$DEPLOY_APP_PATH' && for r in releases/*/; do
        [ -f \"\$r/bin/console\" ] || continue
        (cd \"\$r\" && php bin/console cache:clear-config >/dev/null 2>&1) \
            || echo \"could not clear \$r\" >&2
    done"
}

"${SSH[@]}" -n true || fail "could not reach the server on port ${DEPLOY_SSH_PORT:-222}."
ok "reached the server"

"${SSH[@]}" -n "test -f '$REMOTE_LOCAL_PHP'" \
    || fail "$REMOTE_LOCAL_PHP is not there. Check DEPLOY_APP_PATH, and that the atomic layout exists."
ok "found the server's local.php"

SERVER_KEYS=$(edit "read '$REMOTE_LOCAL_PHP'") \
    || fail "could not read sion_model.api_keys from the server."
[ -n "$SERVER_KEYS" ] || fail "sion_model.api_keys is empty on the server. Set one by hand first."

CURRENT=$(printf '%s\n' "$SERVER_KEYS" | head -1)
COUNT=$(printf '%s\n' "$SERVER_KEYS" | grep -c .)
ok "server holds $COUNT key(s), first is $(tail4 "$CURRENT")"

# Already done. Re-running after a half-finished attempt lands here, which is the point of
# saying it rather than editing something that does not need editing.
if [ "$COUNT" = "1" ] && [ "$CURRENT" = "$DEPLOY_API_KEY" ]; then
    ok "the server already holds exactly the key in $CONFIG"
    step "Nothing to distribute to the server"
    dim "Pushing to GitHub anyway, since that is the copy that drifts unnoticed."
    if [ "$DRY_RUN" = 1 ]; then
        dim "DRY_RUN: would run ./tools/gh-secrets.sh"
        printf '\n'
        exit 0
    fi
    ./tools/gh-secrets.sh || fail "could not update the GitHub secret."
    printf '\n'
    exit 0
fi

# Is the key already on the server beside the old one? That is where a half-finished run
# leaves things, and it is the difference between "would add" and "would resume".
ALREADY=0
printf '%s\n' "$SERVER_KEYS" | grep -qxF "$DEPLOY_API_KEY" && ALREADY=1

# What keep-only will remove: everything but the one being kept, once the widening has
# happened. $COUNT is the total on the server now, which is not the same number.
if [ "$ALREADY" = 1 ]; then RETIRING=$((COUNT - 1)); else RETIRING=$COUNT; fi

if [ "$DRY_RUN" = 1 ]; then
    step "Dry run"
    if [ "$ALREADY" = 1 ]; then
        dim "$(tail4 "$DEPLOY_API_KEY") is already on the server beside $((COUNT - 1)) other key(s)"
        dim "would resume from there — nothing to add"
    else
        dim "would add $(tail4 "$DEPLOY_API_KEY") beside $(tail4 "$CURRENT") on the server"
    fi
    dim "would clear the merged config cache in every release, then verify against"
    dim "  $DEPLOY_BASE_URL/en/sm/cache-status"
    dim "would push DEPLOY_API_KEY to the GitHub production environment"
    dim "would then remove $RETIRING key(s) and verify the old one is refused"
    printf '\n'
    exit 0
fi

# Answers 200 for a key the site accepts, 401 for one it does not. The key goes in a
# header, never a query string: a query string is written to the access log.
probe() {
    curl -s -o /dev/null -w '%{http_code}' --max-time 20 \
        -H "X-Api-Key: $1" "$DEPLOY_BASE_URL/en/sm/cache-status"
}

# --- 1. widen ----------------------------------------------------------------

step "1/3  The server"

if [ "$ALREADY" = 1 ]; then
    ok "the new key is already present alongside the old — resuming"
    BACKUP='(none — nothing to add)'
else
    # No "nothing was changed" here. The editor may fail *after* writing — its own
    # `backup:` line on stderr says whether it got that far, and claiming the file is
    # untouched when it is not is the worst thing this message could do.
    BACKUP=$(edit "add '$REMOTE_LOCAL_PHP' '$DEPLOY_API_KEY'") \
        || fail "could not add the key. Read the editor's message above: if it printed a
        backup path, local.php WAS written and that path is the previous contents."
    ok "added $(tail4 "$DEPLOY_API_KEY") beside $(tail4 "$CURRENT")"
    dim "backup: $BACKUP"
fi

clear_config_cache
ok "cleared the merged config cache in every release"

# OPcache serves local.php like any other PHP file, and validate_timestamps=On means it is
# re-read within revalidate_freq seconds rather than instantly. Wait rather than race it.
sleep 3

STATUS_NEW=$(probe "$DEPLOY_API_KEY")
[ "$STATUS_NEW" = "200" ] || fail "the new key is not accepted (got $STATUS_NEW). Restore: $BACKUP"
STATUS_OLD=$(probe "$CURRENT")
[ "$STATUS_OLD" = "200" ] || fail "the OLD key stopped working (got $STATUS_OLD) — unexpected. Restore: $BACKUP"
ok "the live site accepts both"

# --- 2. GitHub ---------------------------------------------------------------

step "2/3  The GitHub production environment"

# Its own script because it is independently useful — `make prod-send-secrets` after any
# edit to .deploy.local — and because it pushes the rest of the environment at the same
# time, which costs nothing and keeps the eleven in step.
if ! ./tools/gh-secrets.sh; then
    printf '\n  %sThe GitHub secret was NOT updated.%s\n' "$C_ERR" "$C_OFF" >&2
    dim "Both keys still work on the server, so nothing is broken and CD still deploys."
    dim "Fix gh, then re-run this — it will resume from here."
    exit 1
fi
ok "DEPLOY_API_KEY pushed"

# --- 3. narrow ---------------------------------------------------------------

step "3/3  Retiring the old key"

    BACKUP2=$(edit "keep-only '$REMOTE_LOCAL_PHP' '$DEPLOY_API_KEY'") \
    || fail "could not remove the old key. Read the editor's message above for whether
        local.php was written. Both keys still work either way; re-run to finish."
ok "removed $RETIRING key(s)"
dim "backup: $BACKUP2"

clear_config_cache
ok "cleared the merged config cache in every release"

sleep 3

STATUS_NEW=$(probe "$DEPLOY_API_KEY")
[ "$STATUS_NEW" = "200" ] || fail "the new key stopped working (got $STATUS_NEW). Restore: $BACKUP2"
STATUS_OLD=$(probe "$CURRENT")
[ "$STATUS_OLD" = "401" ] || fail "the old key is STILL accepted (got $STATUS_OLD). Check $REMOTE_LOCAL_PHP by hand."
ok "new key accepted, old key refused"

step "Done"
printf '  The key in %s is now the only one the site accepts, and GitHub has it.\n\n' "$CONFIG"
dim "The server keeps the .bak- copies beside local.php. Delete them once satisfied."
dim "Anything else holding the old key — another laptop's .deploy.local, a cron line —"
dim "now gets 401 from /sm/*. That is the point, but it is worth a moment's thought."
printf '\n'
