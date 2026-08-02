#!/usr/bin/env bash
# Sync the three git-submodule module/ dirs to production.
#
# Why not phploy: its --submodules mode has a fatal directory-purge bug
# (recursively deletes e.g. module/JUser/src AFTER uploading into it — see
# BACKLOG.md "Deploy ops"). Why not rsync/scp: the managed server refuses
# non-interactive SSH exec. So: tar over SFTP, then extract — automatically
# if the server lets us exec with a PTY, otherwise paste one command.
set -euo pipefail
cd "$(dirname "$0")/.."

REMOTE=${1:-ourlink@dedi2934.your-server.de}
APP_PATH=public_html/schoenstatt.link
MODULES="module/SionModel module/JUser module/JTranslate"
TARBALL=$(mktemp -t modules-sync-XXXXXX).tar.gz

for m in $MODULES; do
    if [ -n "$(git -C "$m" status --porcelain)" ]; then
        echo "ABORT: $m has uncommitted changes — deploy only clean, committed state." >&2
        exit 1
    fi
done

tar --exclude='.git' -czf "$TARBALL" $MODULES
echo "put $TARBALL modules-sync.tar.gz" | sftp "$REMOTE"
rm -f "$TARBALL"

EXTRACT="cd $APP_PATH && rm -rf $MODULES && tar xzf ~/modules-sync.tar.gz && rm ~/modules-sync.tar.gz && echo EXTRACTED-OK"
if ssh -t "$REMOTE" "$EXTRACT"; then
    echo "Submodules deployed."
else
    cat >&2 <<MSG

Remote exec is blocked on this server. Finish by pasting this into an
interactive SSH session (ssh $REMOTE):

  $EXTRACT

MSG
    exit 1
fi
