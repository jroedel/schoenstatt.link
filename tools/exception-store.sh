#!/usr/bin/env bash
# Where the production exception store lives, and how to reach it.
#
# SOURCED, not run: `. "$(dirname "$0")/exception-store.sh"`.
#
# WHY THIS FILE EXISTS: the path was written out twice — once in
# fetch-exceptions.sh, once in clear-exceptions.sh — and when the atomic deploy
# moved the store in August 2026 neither copy was updated. fetch-exceptions.sh
# then reported "no exceptions have been recorded" on every run for five weeks,
# because a missing directory and an empty one give the same answer, while the
# email notifications kept arriving and hid it. Two copies of a fact, one of them
# silently stale, is what this removes.
#
# THE LAYOUT: tools/deploy.sh creates shared/data/* and symlinks each entry into
# releases/<rel>/data/. So the store is
#
#     $APP_PATH/shared/data/exceptions            <- the real directory
#     $APP_PATH/releases/<rel>/data/exceptions    <- a symlink to it
#     $APP_PATH/data/exceptions                   <- DOES NOT EXIST
#
# The last of those is what both scripts used to read.

# The same .deploy.local tools/deploy.sh reads, so the app path cannot drift from
# the thing that created the layout. Sourced, never printed: it holds the
# database password.
_EXC_CONFIG="${DEPLOY_CONFIG:-$(dirname "${BASH_SOURCE[0]}")/../.deploy.local}"
if [ -f "$_EXC_CONFIG" ]; then
    # shellcheck disable=SC1090
    . "$_EXC_CONFIG"
fi

APP_PATH=${DEPLOY_APP_PATH:-public_html/schoenstatt.link}
REMOTE_EXC_DIR="$APP_PATH/shared/data/exceptions"
LEGACY_EXC_DIR="$APP_PATH/data/exceptions"

# Port 22 on this host is a restricted SFTP jail with no exec; the full shell is
# on 222.
EXC_REMOTE=${DEPLOY_SSH_USER:+$DEPLOY_SSH_USER@}${DEPLOY_SSH_HOST:-dedi2934.your-server.de}
[ -n "${DEPLOY_SSH_USER:-}" ] || EXC_REMOTE="ourlink@${DEPLOY_SSH_HOST:-dedi2934.your-server.de}"
EXC_PORT=${DEPLOY_SSH_PORT:-222}

# Resolve which of the two directories is actually there, on the server, and fail
# loudly when the application itself is not where we think it is — "no exceptions"
# must never be the answer to a wrong path.
#
# Usage: exc_resolve_store <remote> <port>; sets REMOTE_EXC_DIR, or returns 1 with
# a message on stderr. Return 2 means the app is there and the store is not, which
# is the healthy case and the caller's to report.
exc_resolve_store() {
    local remote=$1 port=$2 probe
    if ! probe=$(ssh -p "$port" "$remote" \
        "[ -d '$APP_PATH' ] && echo APP-OK || echo APP-MISSING; \
         [ -d '$REMOTE_EXC_DIR' ] && echo EXISTS || echo MISSING; \
         [ -d '$LEGACY_EXC_DIR' ] && echo LEGACY || true" 2>&1); then
        echo "ERROR: could not reach $remote on port $port." >&2
        echo "$probe" >&2
        return 1
    fi

    case "$probe" in
        *APP-MISSING*)
            echo "ERROR: $APP_PATH does not exist on $remote." >&2
            echo "That is a wrong path, not an absence of exceptions. DEPLOY_APP_PATH in" >&2
            echo ".deploy.local is what this reads; tools/deploy.sh reads the same value." >&2
            return 1
            ;;
    esac

    case "$probe" in
        *EXISTS*) return 0 ;;
        *LEGACY*)
            echo "Note: no $REMOTE_EXC_DIR, but the pre-atomic $LEGACY_EXC_DIR exists. Using it."
            REMOTE_EXC_DIR="$LEGACY_EXC_DIR"
            return 0
            ;;
        *MISSING*) return 2 ;;
        *)
            echo "ERROR: unexpected response probing for $REMOTE_EXC_DIR: $probe" >&2
            return 1
            ;;
    esac
}
