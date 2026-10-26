# shellcheck shell=bash
#
# The one reader of secrets.env. SOURCED, not run:
#
#     . "$(dirname "$0")/secrets-env.sh"
#     secrets_env_load "$SECRETS_ENV_FILE" || exit 1
#
# secrets.env is every credential this application has — the deploy's (DEPLOY_*) and
# production local.php's (APP_*) — in one file that lives beside the repository and in
# the password vault, never in git. secrets.env.example is its tracked template and says
# where each name goes.
#
# PARSED, NEVER SOURCED. Until 2026-10 the deploy configuration was `.deploy.local`,
# and eight scripts ran it with `.`. A credentials file that is executed is a file that
# can be made to run somebody else's commands, and one that holds a DDL password, the
# CI deploy key and the JWT signing key is worth that effort. A value here is data:
# `$(…)`, backticks and `$NAME` are kept as written.
#
# THE FORMAT, which is the subset of dotenv a person writes by hand:
#
#   NAME=value          surrounding whitespace dropped; a trailing ` # comment` too
#   NAME='value'        taken exactly as written between the quotes
#   NAME="value"        likewise; no escapes, no expansion
#   # comment, or blank
#
# NAME is [A-Z][A-Z0-9_]*. Any other line is an error rather than skipped: a typo'd
# name that silently vanished would surface as a deploy failing somewhere else. The
# file wins over the environment, as it did when it was sourced.

# Where the file is, unless SECRETS_ENV names another. A CI runner writes its copy
# outside the workspace and exports the path, so nothing later in the job can archive
# it; see .github/workflows/deploy.yml.
SECRETS_ENV_FILE=${SECRETS_ENV:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/secrets.env}

secrets_env_load() {
    local file=$1 mode line key value n=0
    local dq="^\"(.*)\"[[:space:]]*(#.*)?$"
    local sq="^'(.*)'[[:space:]]*(#.*)?$"

    if [ ! -f "$file" ]; then
        printf '%s is missing. Copy secrets.env.example to it (./config.sh does), or take it from the vault.\n' \
            "$file" >&2
        return 1
    fi

    # Refused, not warned about: on a shared machine the warning is read after the leak.
    mode=$(stat -L -c '%a' "$file" 2>/dev/null || stat -L -f '%Lp' "$file") || return 1
    if (( 8#$mode & 8#077 )); then
        printf '%s is mode %s; only its owner may read it: chmod 600 %s\n' "$file" "$mode" "$file" >&2
        return 1
    fi

    while IFS= read -r line || [ -n "$line" ]; do
        n=$((n + 1))
        # A password manager that writes CRLF ends every pasted line in a carriage return.
        line=${line%$'\r'}
        case $line in ''|'#'*) continue ;; esac
        if [[ ! $line =~ ^([A-Z][A-Z0-9_]*)=(.*)$ ]]; then
            # The line number only. The line itself may be a value.
            printf '%s:%d is not NAME=value (names are upper case); fix or comment it.\n' "$file" "$n" >&2
            return 1
        fi
        key=${BASH_REMATCH[1]}
        value=${BASH_REMATCH[2]}
        if [[ $value =~ $dq ]] || [[ $value =~ $sq ]]; then
            value=${BASH_REMATCH[1]}
        else
            value=${value%%[[:space:]]#*}
            value=${value#"${value%%[![:space:]]*}"}
            value=${value%"${value##*[![:space:]]}"}
        fi
        # Global, and NOT exported — what `.` did. An exported DDL password would reach
        # every child of the deploy: rsync, composer and its scripts, curl.
        printf -v "$key" '%s' "$value"
    done < "$file"
}

# NAME='value' (or NAME="value" when the value holds a single quote): a line
# secrets_env_load reads back exactly. Fails for a value no quoting can carry.
secrets_env_line() {
    local name=$1 value=$2
    case $value in
        *"'"*'"'*|*'"'*"'"*|*$'\n'*)
            printf '%s cannot be written on one quoted line: it holds both quote characters or a newline.\n' \
                "$name" >&2
            return 1
            ;;
        *"'"*) printf '%s="%s"\n' "$name" "$value" ;;
        *)     printf "%s='%s'\n" "$name" "$value" ;;
    esac
}
