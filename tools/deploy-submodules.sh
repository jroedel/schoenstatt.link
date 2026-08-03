#!/usr/bin/env bash
# Sync the three git-submodule module/ dirs to production.
#
# Why not phploy: its --submodules mode has a fatal directory-purge bug
# (recursively deletes e.g. module/JUser/src AFTER uploading into it — see
# docs/BACKLOG.md "Deploy ops").
#
# The managed server's port 22 is a restricted SFTP jail (no exec — rsync
# dies with "exec request failed on channel 0"); the full shell lives on
# port 222. Prefer rsync over 222; fall back to tar-over-SFTP + remote
# extract if rsync is unavailable.
set -euo pipefail
cd "$(dirname "$0")/.."

REMOTE=${1:-ourlink@dedi2934.your-server.de}
SSH_PORT=${2:-222}
APP_PATH=public_html/schoenstatt.link
MODULES="module/SionModel module/JUser module/JTranslate"

for m in $MODULES; do
    if [ -n "$(git -C "$m" status --porcelain)" ]; then
        echo "ABORT: $m has uncommitted changes — deploy only clean, committed state." >&2
        exit 1
    fi
done

if ssh -p "$SSH_PORT" "$REMOTE" 'command -v rsync' >/dev/null 2>&1; then
    for m in $MODULES; do
        rsync -rlptz --delete --exclude='.git' -e "ssh -p $SSH_PORT" \
            "$m/" "$REMOTE:$APP_PATH/$m/"
        echo "synced $m"
    done
    echo "Submodules deployed (rsync)."
    exit 0
fi

echo "rsync unavailable on the server; falling back to tar over SFTP." >&2
TARBALL=$(mktemp -t modules-sync-XXXXXX).tar.gz
tar --exclude='.git' -czf "$TARBALL" $MODULES
echo "put $TARBALL modules-sync.tar.gz" | sftp -P "$SSH_PORT" "$REMOTE"
rm -f "$TARBALL"

EXTRACT="cd $APP_PATH && rm -rf $MODULES && tar xzf ~/modules-sync.tar.gz && rm ~/modules-sync.tar.gz && echo EXTRACTED-OK"
if ssh -t -p "$SSH_PORT" "$REMOTE" "$EXTRACT"; then
    echo "Submodules deployed (tar)."
else
    cat >&2 <<MSG

Remote exec failed. Finish by pasting this into an interactive SSH session
(ssh -p $SSH_PORT $REMOTE):

  $EXTRACT

MSG
    exit 1
fi
