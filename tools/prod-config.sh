#!/usr/bin/env bash
#
# Production's config/autoload/local.php, from secrets.env.
#
#     make prod-config-diff      # what would change, by key path; prints no value
#     make prod-config           # the diff, a [y/N], then install it
#     make prod-config-import    # the server's values as APP_* lines, for secrets.env
#
# The server's shared/config-autoload/local.php, which every release links to, was a
# hand-edited file that existed nowhere else: lose it and the application's database
# account, mail password, Drive key, library keys and JWT key were gone with it. Now
# secrets.env holds the values, config/autoload/local.production.php.dist on
# origin/master holds the shape, and this renders one into the other ON THE SERVER:
# tools/prod-config-remote.php travels in the ssh command line, and the values on
# its stdin — never in an argument, the environment, or a local file.
#
# Only the APP_* names, DEPLOY_API_KEY and DEPLOY_API_KEY_PREVIOUS are sent. The DDL
# password, the CI key and the rest of the deploy section never leave this machine.
#
# origin/master, freshly fetched, and never the working tree: the configuration
# installed is the one the deployed code expects, not one a branch is still writing.
# PROD_CONFIG_REF names another revision; `.` reads the working tree, which is for
# test/Deploy/prod-config-test.sh.
#
# After an install it clears the merged config cache in every release — production
# boots with config caching on, so a new local.php is otherwise ignored until the next
# deploy — and checks the live site accepts DEPLOY_API_KEY (and, mid-rotation,
# DEPLOY_API_KEY_PREVIOUS). A failure there names the one command that restores the
# previous file.
#
# For a person at a terminal: install asks first, and refuses without a terminal
# unless PROD_CONFIG_YES=1.
set -uo pipefail

cd "$(dirname "$0")/.."
# shellcheck source=tools/secrets-env.sh
. tools/secrets-env.sh

MODE=${1:-}
case $MODE in
    diff|install|import) ;;
    *) printf 'usage: %s diff|install|import\n' "$0" >&2; exit 2 ;;
esac

C_OK=$'\033[32m'; C_WARN=$'\033[33m'; C_ERR=$'\033[31m'; C_DIM=$'\033[2m'; C_B=$'\033[1m'; C_OFF=$'\033[0m'
step() { printf '\n%s=== %s%s\n' "$C_B" "$1" "$C_OFF"; }
ok()   { printf '  %sok%s    %s\n' "$C_OK" "$C_OFF" "$1"; }
warn() { printf '  %swarn%s  %s\n' "$C_WARN" "$C_OFF" "$1"; }
dim()  { printf '%s      %s%s\n' "$C_DIM" "$1" "$C_OFF"; }
fail() { printf '\n  %sFAIL%s  %s\n\n' "$C_ERR" "$C_OFF" "$1" >&2; exit 1; }

secrets_env_load "$SECRETS_ENV_FILE" || exit 1
for v in DEPLOY_SSH_USER DEPLOY_SSH_HOST DEPLOY_APP_PATH DEPLOY_BASE_URL; do
    [ -n "${!v:-}" ] || fail "$v is not set in $SECRETS_ENV_FILE."
    case ${!v} in *TODO*) fail "$v in $SECRETS_ENV_FILE is still the template placeholder." ;; esac
done
: "${DEPLOY_SSH_PORT:=222}"

TARGET="$DEPLOY_APP_PATH/shared/config-autoload/local.php"

# --- what is rendered, and from which revision ----------------------------------

REF=${PROD_CONFIG_REF:-origin/master}
if [ "$REF" = . ]; then
    TEMPLATE=$(cat config/autoload/local.production.php.dist) || fail "no template in the working tree"
    PROGRAM=$(cat tools/prod-config-remote.php) || fail "no tools/prod-config-remote.php"
    REV=working-tree
else
    if [ "$REF" = origin/master ]; then
        git fetch -q origin master || fail "could not fetch origin/master"
    fi
    TEMPLATE=$(git show "$REF:config/autoload/local.production.php.dist" 2>/dev/null) \
        || fail "$REF has no config/autoload/local.production.php.dist — has it been merged?"
    PROGRAM=$(git show "$REF:tools/prod-config-remote.php" 2>/dev/null) \
        || fail "$REF has no tools/prod-config-remote.php"
    REV=$(git rev-parse --short "$REF")
fi
PROGRAM_B64=$(printf '%s' "$PROGRAM" | base64 | tr -d '\n')

# The names sent: those secrets.env actually sets, not whatever the shell happens to
# export, so a stray APP_* in the environment cannot become production configuration.
mapfile -t NAMES < <(grep -oE '^(APP_[A-Z0-9_]*|DEPLOY_API_KEY|DEPLOY_API_KEY_PREVIOUS)=' "$SECRETS_ENV_FILE" \
    | sed 's/=$//' | sort -u)

payload() {
    printf '%s\0' "$1" "$TARGET" "${ALLOW_DB_CHANGE:-0}" "$REV" "$TEMPLATE"
    [ "$1" = import ] && return 0
    local n
    for n in "${NAMES[@]}"; do
        printf '%s\0%s\0' "$n" "${!n}"
    done
}

# --- the server -----------------------------------------------------------------

CTRL_PATH=$(mktemp -u "${TMPDIR:-/tmp}/prod-config-ssh-XXXXXX")
SSH_UNATTENDED=()
[ -t 0 ] || SSH_UNATTENDED=(-o BatchMode=yes -o StrictHostKeyChecking=yes)
SSH=(ssh -o ControlMaster=auto -o ControlPath="$CTRL_PATH" -o ControlPersist=60
     -o ConnectTimeout=15 "${SSH_UNATTENDED[@]}"
     -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST")
IMPORT_TMP=''
cleanup() {
    ssh -S "$CTRL_PATH" -O exit "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" >/dev/null 2>&1 || true
    [ -n "$IMPORT_TMP" ] && rm -f "$IMPORT_TMP"
}
trap cleanup EXIT

remote() {   # $1 mode; the payload on stdin, the program in the command line
    payload "$1" | "${SSH[@]}" "php -r 'eval(\"?>\".base64_decode(\"$PROGRAM_B64\"));'"
}

# The same loop tools/deploy.sh's rollback relies on: every release has its own merged
# config in data/config, written at its own deploy, and a rollback must not boot into
# one that predates this file. `bin/console cache:clear-config` takes the paths from the
# module listener options, so a changed cache key cannot leave it deleting nothing.
clear_config_cache() {
    "${SSH[@]}" -n "cd '$DEPLOY_APP_PATH' && for r in releases/*/; do
        [ -f \"\$r/bin/console\" ] || continue
        (cd \"\$r\" && php bin/console cache:clear-config >/dev/null 2>&1) \
            || echo \"could not clear \$r\" >&2
    done"
}

# 200 for a key the site accepts, 401 for one it does not. The header comes from stdin,
# so the key is in no process's arguments.
probe() {
    printf 'X-Api-Key: %s\n' "$1" \
        | curl -s -o /dev/null -w '%{http_code}' --max-time 20 -H @- "$DEPLOY_BASE_URL/en/sm/cache-status"
}

step "prod-config $MODE  ($REV → $DEPLOY_SSH_HOST:$TARGET)"

# --- import ---------------------------------------------------------------------

if [ "$MODE" = import ]; then
    # PROD_CONFIG_IMPORT moves it, for test/Deploy/prod-config-test.sh.
    OUT=${PROD_CONFIG_IMPORT:-var/secrets.imported.env}
    OUT_DIR=$(dirname "$OUT")
    mkdir -p "$OUT_DIR" && chmod 700 "$OUT_DIR" || fail "could not create $OUT_DIR/"
    IMPORT_TMP=$(umask 077; mktemp "$OUT_DIR/.secrets.imported.XXXXXX") || fail "could not create a file in $OUT_DIR/"
    remote import > "$IMPORT_TMP" || fail "the server could not read $TARGET (its message is above)."
    mv -f "$IMPORT_TMP" "$OUT" && IMPORT_TMP=''
    ok "wrote $OUT (mode 0600)"

    # Each imported name against what secrets.env holds now, compared here and never
    # printed. The imported file is read in a subshell so it cannot replace this
    # script's own values.
    mapfile -t IMPORTED < <(grep -oE '^[A-Z][A-Z0-9_]*=' "$OUT" | sed 's/=$//')
    declare -A HAVE=()
    for n in "${IMPORTED[@]}"; do
        HAVE[$n]=${!n-__unset__}
    done
    (
        secrets_env_load "$OUT" || exit 1
        for n in "${IMPORTED[@]}"; do
            if [ "${HAVE[$n]}" = __unset__ ]; then
                printf '  %-12s %s\n' 'not in file' "$n"
            elif [ "${HAVE[$n]}" = "${!n}" ]; then
                printf '  %-12s %s\n' 'same' "$n"
            else
                printf '  %-12s %s\n' 'differs' "$n"
            fi
        done
    )
    printf '\n'
    dim "Copy the lines you want into $SECRETS_ENV_FILE, then delete $OUT."
    dim "Then: make prod-config-diff"
    printf '\n'
    exit 0
fi

# --- diff -----------------------------------------------------------------------

REPORT=$(remote diff) || fail "the server could not render the candidate (its message is above)."
printf '%s\n' "$REPORT"
CHANGES=$(printf '%s\n' "$REPORT" | sed -nE 's/^  ([0-9]+) key path\(s\) would change\.$/\1/p')
[ -n "$CHANGES" ] || fail "could not read the diff's summary line."

if [ "$MODE" = diff ]; then
    printf '\n'
    exit 0
fi

# --- install --------------------------------------------------------------------

if [ "$CHANGES" = 0 ]; then
    ok "the server already holds exactly this configuration"
    printf '\n'
    exit 0
fi

if [ -t 0 ]; then
    printf '\n  Install this on %s? [y/N] ' "$DEPLOY_SSH_HOST"
    read -r ANSWER < /dev/tty
    case $ANSWER in y|Y|yes) ;; *) fail "not installed." ;; esac
elif [ "${PROD_CONFIG_YES:-0}" != 1 ]; then
    fail "install asks first, and there is no terminal to ask. PROD_CONFIG_YES=1 if this is meant."
fi

step "Install"
remote install || fail "not installed (the server's message is above)."

clear_config_cache
ok "cleared the merged config cache in every release"

# OPcache re-reads local.php within revalidate_freq seconds, not at once.
sleep "${PROD_CONFIG_SETTLE:-3}"

RESTORE="ssh -p $DEPLOY_SSH_PORT $DEPLOY_SSH_USER@$DEPLOY_SSH_HOST \"mv '$TARGET.prev' '$TARGET'\", then clear the config cache"
for KEY_NAME in DEPLOY_API_KEY DEPLOY_API_KEY_PREVIOUS; do
    [ -n "${!KEY_NAME:-}" ] || continue
    STATUS=$(probe "${!KEY_NAME}")
    [ "$STATUS" = 200 ] || fail "the live site answers $STATUS to $KEY_NAME. To restore: $RESTORE"
    ok "the live site accepts $KEY_NAME"
done

step "Done"
dim "If secrets.env changed for this, update the vault copy today."
[ -n "${DEPLOY_API_KEY_PREVIOUS:-}" ] \
    && dim "DEPLOY_API_KEY_PREVIOUS is still set: once \`make gh-secrets\` has run, empty it and run this again."
printf '\n'
