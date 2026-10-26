#!/usr/bin/env bash
#
# Write secrets.env, once, from what this machine already holds.
#
#     make secrets-init
#
# Until 2026-10 the deploy's credentials were `.deploy.local`, the CI deploy key was
# ~/.ssh/schoenstatt-deploy-ci, and the host key the runner pins existed only as a GitHub
# secret pasted by hand. This puts the three into one file, laid out as
# secrets.env.example:
#
#   - every name .deploy.local sets, read with the secrets.env parser — never sourced,
#     so this is also the check that the old file parses;
#   - DEPLOY_CI_SSH_KEY_B64 from the CI key (DEPLOY_CI_KEY names another file);
#   - DEPLOY_KNOWN_HOSTS_B64 from ssh-keyscan, kept only where a fingerprint matches one
#     ~/.ssh/known_hosts already trusts for the server. A scan answered from a hostile
#     path pins the attacker's key just as happily, so an unmatched scan is refused.
#
# The APP_* section stays as the template's TODOs: `make prod-config-import` reads
# those from the server once the template is on master.
#
# It never overwrites secrets.env, and prints no value.
set -uo pipefail

cd "$(dirname "$0")/.."
# shellcheck source=tools/secrets-env.sh
. tools/secrets-env.sh

LEGACY=${1:-.deploy.local}
KEY_FILE=${DEPLOY_CI_KEY:-$HOME/.ssh/schoenstatt-deploy-ci}
KNOWN=${SECRETS_INIT_KNOWN_HOSTS:-$HOME/.ssh/known_hosts}

C_OK=$'\033[32m'; C_WARN=$'\033[33m'; C_DIM=$'\033[2m'; C_OFF=$'\033[0m'
ok()   { printf '  %sok%s    %s\n' "$C_OK" "$C_OFF" "$1"; }
warn() { printf '  %swarn%s  %s\n' "$C_WARN" "$C_OFF" "$1"; }
dim()  { printf '%s      %s%s\n' "$C_DIM" "$1" "$C_OFF"; }
fail() { printf '\n  %sFAIL%s  %s\n\n' $'\033[31m' "$C_OFF" "$1" >&2; exit 1; }

[ -e "$SECRETS_ENV_FILE" ] && fail "$SECRETS_ENV_FILE already exists; this never overwrites it."
[ -f "$LEGACY" ] || fail "$LEGACY is missing. Copy secrets.env.example to secrets.env and fill it by hand instead."

# The same parser, the same mode check: a .deploy.local that does not parse is named
# by line number here, rather than half-copied.
secrets_env_load "$LEGACY" || fail "$LEGACY does not parse as secrets.env; fix the line named above."
ok "read $LEGACY"

WORK=$(umask 077; mktemp -d) || fail "could not create a temporary directory"
trap 'rm -rf "$WORK"' EXIT

# --- the CI key ------------------------------------------------------------------

if [ -f "$KEY_FILE" ]; then
    ssh-keygen -y -P '' -f "$KEY_FILE" >/dev/null 2>&1 \
        || fail "$KEY_FILE is not a passphrase-less private key."
    DEPLOY_CI_SSH_KEY_B64=$(base64 < "$KEY_FILE" | tr -d '\n')
    ok "DEPLOY_CI_SSH_KEY_B64 from $KEY_FILE"
else
    warn "no CI key at $KEY_FILE — DEPLOY_CI_SSH_KEY_B64 left as TODO. To make one:"
    dim "ssh-keygen -t ed25519 -N '' -C 'schoenstatt.link CD' -f $KEY_FILE"
fi

# --- the host key ----------------------------------------------------------------

PORT=${DEPLOY_SSH_PORT:-222}
if [ -n "${DEPLOY_SSH_HOST:-}" ]; then
    HOST_ENTRY="[$DEPLOY_SSH_HOST]:$PORT"
    [ "$PORT" = 22 ] && HOST_ENTRY=$DEPLOY_SSH_HOST
    ssh-keygen -F "$HOST_ENTRY" -f "$KNOWN" 2>/dev/null | grep -v '^#' > "$WORK/trusted"
    ssh-keyscan -p "$PORT" "$DEPLOY_SSH_HOST" 2>/dev/null > "$WORK/scanned"
    if [ ! -s "$WORK/trusted" ]; then
        warn "$KNOWN does not know $HOST_ENTRY — DEPLOY_KNOWN_HOSTS_B64 left as TODO."
        dim "ssh to the server once and check the fingerprint, then run this again."
    elif [ ! -s "$WORK/scanned" ]; then
        warn "ssh-keyscan got no answer from $HOST_ENTRY — DEPLOY_KNOWN_HOSTS_B64 left as TODO."
    else
        TRUSTED=$(ssh-keygen -lf "$WORK/trusted" | awk '{print $2}' | sort -u)
        : > "$WORK/pinned"
        while IFS= read -r scan_line; do
            fp=$(printf '%s\n' "$scan_line" | ssh-keygen -lf - 2>/dev/null | awk '{print $2}')
            [ -n "$fp" ] && grep -qxF "$fp" <<< "$TRUSTED" && printf '%s\n' "$scan_line" >> "$WORK/pinned"
        done < "$WORK/scanned"
        if [ -s "$WORK/pinned" ]; then
            DEPLOY_KNOWN_HOSTS_B64=$(base64 < "$WORK/pinned" | tr -d '\n')
            ok "DEPLOY_KNOWN_HOSTS_B64: $(wc -l < "$WORK/pinned") host key(s) that $KNOWN already trusts"
        else
            fail "no key $DEPLOY_SSH_HOST offered matches $KNOWN. Find out why before pinning anything."
        fi
    fi
fi

# --- the file --------------------------------------------------------------------

# The template, line by line, with each NAME= the old file or the steps above supplied
# replaced by its value. Comments and order are the template's.
FILLED=() OPEN=()
while IFS= read -r tline || [ -n "$tline" ]; do
    if [[ $tline =~ ^([A-Z][A-Z0-9_]*)= ]]; then
        name=${BASH_REMATCH[1]}
        if [ -n "${!name:-}" ]; then
            secrets_env_line "$name" "${!name}" || fail "nothing was written."
            FILLED+=("$name")
            continue
        fi
        case $tline in *TODO*) OPEN+=("$name") ;; esac
    fi
    printf '%s\n' "$tline"
done < secrets.env.example > "$WORK/secrets.env" || fail "nothing was written."

mv "$WORK/secrets.env" "$SECRETS_ENV_FILE" && chmod 600 "$SECRETS_ENV_FILE" \
    || fail "could not write $SECRETS_ENV_FILE"
ok "wrote $SECRETS_ENV_FILE (mode 0600): ${#FILLED[@]} value(s)"
[ ${#OPEN[@]} -eq 0 ] || dim "still TODO: ${OPEN[*]}"
# A name the old file set and the template does not know was left behind, not copied.
for name in $(grep -oE '^[A-Z][A-Z0-9_]*=' "$LEGACY" | sed 's/=$//'); do
    grep -qE "^$name=" secrets.env.example || warn "$name is in $LEGACY but not in the template; not copied"
done

printf '\nNext:\n'
dim "make gh-secrets            — the bundle the deploy workflow needs before this merges"
dim "make prod-config-import    — the APP_* values, once the template is on master"
dim "then put secrets.env in the vault, and delete $LEGACY"
printf '\n'
