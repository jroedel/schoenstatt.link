#!/usr/bin/env bash
#
# Rotate the maintenance key (`sion_model.api_keys`) everywhere it lives.
#
#     ./tools/rotate-api-key.sh              # rotate
#     ./tools/rotate-api-key.sh --dry-run    # say what would happen, change nothing
#     make prod-rotate-sion-model-api-keys   # the same, and how it is meant to be run
#     make prod-rotate-sion-model-api-keys DRY_RUN=1
#
# THERE ARE THREE PLACES, NOT TWO, and the third is the one that gets forgotten:
#
#   1. config/autoload/local.php on the server  — `sion_model.api_keys`, what the app checks
#   2. .deploy.local on this machine            — DEPLOY_API_KEY, what deploy.sh and
#                                                 smoke-prod.sh send
#   3. the GitHub `production` environment      — the DEPLOY_API_KEY secret that
#                                                 deploy.yml rebuilds .deploy.local from
#
# Miss (3) and nothing fails today. The next deploy from Actions sends the old key, its
# post-deploy cache flush answers 401, and the release serves stale code from a warm
# OPcache. So this calls tools/gh-secrets.sh itself rather than telling you to.
#
# WHY IT WIDENS BEFORE IT NARROWS. `api_keys` is a list, so both keys can be valid at
# once, and the order here means there is never a moment when neither works:
#
#     add the new key    -> old and new both work
#     verify the new key -> against the live site, before anything depends on it
#     update (2) and (3) -> callers now send the new key
#     verify again       -> the new key still works through the real callers' path
#     keep only the new  -> the old key stops working
#     verify the old key is refused
#
# Any failure before the narrowing step leaves the old key working, so a half-finished
# rotation is an inconvenience rather than an outage. The server's previous local.php is
# kept as a timestamped .bak- beside it either way.
#
# WHAT IT WILL NOT DO: create a key where there is none (it edits, it does not invent a
# configuration), touch `schoenstatt.api_keys` — a different secret, read by
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

# Never printed, never passed as an argument (which `ps` would show) — only ever on
# stdin or inside a quoted remote command. The one exception is the last four characters,
# which is enough to tell two keys apart in a log and not enough to be one.
tail4() { printf '…%s' "${1: -4}"; }

# --- preflight ---------------------------------------------------------------

step "Preflight"

[ -f "$CONFIG" ] || fail "$CONFIG is missing. Run ./config.sh, then fill in the TODOs."
# shellcheck disable=SC1090
. "$CONFIG"

for v in DEPLOY_SSH_USER DEPLOY_SSH_HOST DEPLOY_APP_PATH DEPLOY_BASE_URL; do
    [ -n "${!v:-}" ] || fail "$v is not set in $CONFIG."
    case "${!v}" in *TODO*) fail "$v still contains a TODO in $CONFIG." ;; esac
done
ok "$CONFIG"

command -v gh >/dev/null || fail "gh is not installed, so step 3 could not run. Install it first."
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

"${SSH[@]}" -n true || fail "could not reach $DEPLOY_SSH_HOST on port ${DEPLOY_SSH_PORT:-222}."
ok "reached the server"

# shared/, not a release: the config is symlinked into each release, so editing it there
# would be edited away by the next deploy. tools/deploy.sh builds the layout this way.
"${SSH[@]}" -n "test -f '$REMOTE_LOCAL_PHP'" \
    || fail "$REMOTE_LOCAL_PHP is not there. Check DEPLOY_APP_PATH, and that the atomic layout exists."
ok "found the server's local.php"

CURRENT=$(edit "read '$REMOTE_LOCAL_PHP'" | head -1) \
    || fail "could not read sion_model.api_keys from the server."
[ -n "$CURRENT" ] || fail "sion_model.api_keys is empty on the server. Set one by hand first."
ok "current key $(tail4 "$CURRENT")"

if [ "${DEPLOY_API_KEY:-}" != "$CURRENT" ]; then
    warn "DEPLOY_API_KEY in $CONFIG does not match the server's key"
    dim "rotating will fix that; it is worth knowing it was already adrift"
fi

# --- the new key -------------------------------------------------------------
#
# 32 bytes of urandom, base64url. Length is not the interesting property — the
# comparison is hash_equals over a shared secret, so what matters is that it came from a
# CSPRNG and never touches a command line or a log.
NEW=$(head -c 32 /dev/urandom | base64 | tr '+/' '-_' | tr -d '=\n')
[ ${#NEW} -ge 40 ] || fail "could not generate a key."

if [ "$DRY_RUN" = 1 ]; then
    step "Dry run"
    dim "would add a new key beside $(tail4 "$CURRENT") on the server"
    dim "would verify it against $DEPLOY_BASE_URL/en/sm/cache-status"
    dim "would set DEPLOY_API_KEY in $CONFIG and push it to the GitHub production environment"
    dim "would then remove $(tail4 "$CURRENT") and verify it is refused"
    printf '\n'
    exit 0
fi

# Answers 200 for a key the site accepts, 401 for one it does not. Sends the key in a
# header, never a query string: a query string is written to the access log.
probe() {
    curl -s -o /dev/null -w '%{http_code}' --max-time 20 \
        -H "X-Api-Key: $1" "$DEPLOY_BASE_URL/en/sm/cache-status"
}

# --- 1. widen ----------------------------------------------------------------

step "1/3  The server"

BACKUP=$(edit "add '$REMOTE_LOCAL_PHP' '$NEW'") \
    || fail "could not add the new key. Nothing was changed; the server's local.php is untouched."
ok "added $(tail4 "$NEW") beside $(tail4 "$CURRENT")"
dim "backup: $BACKUP"

# OPcache serves config/autoload/local.php like any other PHP file, and
# validate_timestamps=On means it is re-read within revalidate_freq seconds rather than
# instantly. Two seconds in production; wait rather than race it.
sleep 3

STATUS_NEW=$(probe "$NEW")
STATUS_OLD=$(probe "$CURRENT")
[ "$STATUS_NEW" = "200" ] || fail "the new key is not accepted (got $STATUS_NEW). Restore: $BACKUP"
[ "$STATUS_OLD" = "200" ] || fail "the OLD key stopped working (got $STATUS_OLD) — unexpected. Restore: $BACKUP"
ok "both keys accepted by the live site"

# --- 2. the callers ----------------------------------------------------------

step "2/3  The callers"

# In place, without printing the value and without a temporary file elsewhere: the key
# is a secret and /tmp is not the place for it. The pattern anchors on the line start so
# a commented example cannot be the one that matches.
if grep -q '^DEPLOY_API_KEY=' "$CONFIG"; then
    python3 - "$CONFIG" "$NEW" <<'PY' || fail "could not update $CONFIG."
import sys, re, pathlib
path, key = pathlib.Path(sys.argv[1]), sys.argv[2]
src = path.read_text()
out, n = re.subn(r'(?m)^DEPLOY_API_KEY=.*$', 'DEPLOY_API_KEY=' + key, src)
if n != 1:
    sys.exit(1)
path.write_text(out)
PY
    ok "DEPLOY_API_KEY in $CONFIG"
else
    printf 'DEPLOY_API_KEY=%s\n' "$NEW" >> "$CONFIG" || fail "could not append to $CONFIG."
    ok "DEPLOY_API_KEY appended to $CONFIG"
fi

# The third place. Its own script because it is independently useful — `make
# prod-send-secrets` after any edit to .deploy.local — and because it does the rest of
# the environment at the same time, which costs nothing and keeps the eleven in step.
if ! ./tools/gh-secrets.sh; then
    printf '\n  %sThe GitHub secret was NOT updated.%s\n' "$C_ERR" "$C_OFF" >&2
    dim "The old key is still valid on the server, so nothing is broken and CD still works."
    dim "Fix gh, run: make prod-send-secrets"
    dim "then re-run this to finish: it will see the new key already present."
    exit 1
fi
ok "GitHub production environment"

# --- 3. narrow ---------------------------------------------------------------

step "3/3  Retiring the old key"

BACKUP2=$(edit "keep-only '$REMOTE_LOCAL_PHP' '$NEW'") \
    || fail "could not remove the old key. Both keys still work; re-run to finish. Backup: $BACKUP"
ok "removed $(tail4 "$CURRENT")"
dim "backup: $BACKUP2"

sleep 3

STATUS_NEW=$(probe "$NEW")
STATUS_OLD=$(probe "$CURRENT")
[ "$STATUS_NEW" = "200" ] || fail "the new key stopped working (got $STATUS_NEW). Restore: $BACKUP2"
[ "$STATUS_OLD" = "401" ] || fail "the old key is STILL accepted (got $STATUS_OLD). Check $REMOTE_LOCAL_PHP by hand."
ok "new key accepted, old key refused"

step "Done"
printf '  The maintenance key is rotated in all three places.\n\n'
dim "The server keeps $BACKUP and $BACKUP2. Delete them once you are satisfied."
dim "Anything else holding the old key — another laptop's .deploy.local, a cron line —"
dim "now gets 401 from /sm/*. That is the point, but it is worth a moment's thought."
printf '\n'
