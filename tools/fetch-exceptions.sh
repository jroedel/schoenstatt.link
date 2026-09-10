#!/usr/bin/env bash
# Mirror the server's data/exceptions/ (written by the PHP exception
# recorder) into the local data/exceptions-prod/, then print a summary.
#
# Deliberately NOT --delete: clear-exceptions.sh (or a future recorder
# cleanup) emptying the server must never wipe the local archive — this
# is meant to accumulate a durable history of what production has seen,
# even after the server-side copy is cleared to re-arm notifications.
#
# The managed server's port 22 is a restricted SFTP jail (no exec —
# rsync dies with "exec request failed on channel 0"); the full shell
# lives on port 222. Prefer rsync over 222; fall back to tar-over-SFTP
# + local extract if rsync is unavailable on the server (same pattern as
# the retired deploy-submodules.sh, direction reversed: we tar remotely and pull).
set -euo pipefail
cd "$(dirname "$0")/.."

# The store path and the SSH endpoint, shared with clear-exceptions.sh so the two
# cannot drift apart again — which is exactly how this script came to be reading a
# directory that had not existed since August. See tools/exception-store.sh.
# shellcheck source=tools/exception-store.sh
. "$(dirname "$0")/exception-store.sh"

LOCAL_DIR=data/exceptions-prod

SUMMARY_ONLY=0
POSITIONAL=()
for arg in "$@"; do
    case "$arg" in
        --summary-only) SUMMARY_ONLY=1 ;;
        *) POSITIONAL+=("$arg") ;;
    esac
done
REMOTE=${POSITIONAL[0]:-$EXC_REMOTE}
SSH_PORT=${POSITIONAL[1]:-$EXC_PORT}

CLEANUP_FILES=()
cleanup() {
    local f
    for f in ${CLEANUP_FILES[@]+"${CLEANUP_FILES[@]}"}; do
        rm -f "$f"
    done
}
trap cleanup EXIT

# summarize — print a table of everything currently in $LOCAL_DIR, newest
# last_seen first. Parses meta.json with plain `php -r`-style code (the
# only JSON parser guaranteed present on both dev and prod hosts) rather
# than depending on jq.
summarize() {
    if [ ! -d "$LOCAL_DIR" ]; then
        echo "No local mirror at $LOCAL_DIR yet — nothing to summarize."
        return 0
    fi

    local php_script
    php_script=$(mktemp -t exceptions-summary-XXXXXX.php)
    CLEANUP_FILES+=("$php_script")
    cat >"$php_script" <<'PHP'
<?php
// argv[1] = directory to scan. Emits tab-separated rows, one per
// fingerprint, sorted by last_seen descending. Missing/unreadable
// meta.json never aborts the scan — fields just fall back to placeholders.
$dir = $argv[1];
$rows = [];
foreach (glob($dir . '/*', GLOB_ONLYDIR) as $sub) {
    $fp = basename($sub);
    if (!preg_match('/^[0-9a-f]{8}$/', $fp)) {
        continue;
    }
    $meta = [];
    $metaFile = $sub . '/meta.json';
    if (is_file($metaFile)) {
        $raw = @file_get_contents($metaFile);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
    }
    $class = $meta['class'] ?? '';
    $classShort = $class !== '' ? preg_replace('/^.*\\\\/', '', $class) : '(unknown)';
    $rows[] = [
        $fp,
        (string) ($meta['count'] ?? '?'),
        (string) ($meta['last_seen'] ?? '(unknown)'),
        (string) ($meta['first_seen'] ?? '(unknown)'),
        // FAILED matters: the recorder marks a fingerprint notified *before*
        // it attempts the send, so a mail failure is only ever visible here.
        !empty($meta['notify_error'])
            ? 'FAILED'
            : (!empty($meta['notified_at']) ? 'yes' : 'no'),
        $classShort,
        (string) ($meta['route'] ?? '(none)'),
    ];
}
usort($rows, static fn ($a, $b) => strcmp($b[2], $a[2]));
foreach ($rows as $r) {
    echo implode("\t", $r), "\n";
}
PHP

    local rows=()
    mapfile -t rows < <(php "$php_script" "$LOCAL_DIR")

    if [ "${#rows[@]}" -eq 0 ]; then
        echo "No exception fingerprints found in $LOCAL_DIR."
        return 0
    fi

    printf '%-10s %6s %-25s %-25s %-8s %-30s %-30s\n' \
        FP COUNT LAST_SEEN FIRST_SEEN NOTIFIED CLASS ROUTE
    local row fp count last first notified class route
    for row in "${rows[@]}"; do
        IFS=$'\t' read -r fp count last first notified class route <<<"$row"
        printf '%-10s %6s %-25s %-25s %-8s %-30.30s %-30.30s\n' \
            "$fp" "$count" "$last" "$first" "$notified" "$class" "$route"
    done
    echo
    echo "${#rows[@]} distinct fingerprint(s) in $LOCAL_DIR."
    echo "Full write-ups: $LOCAL_DIR/<fp>/first.txt, last.txt, recent/1.txt..3.txt"
}

if [ "$SUMMARY_ONLY" -eq 1 ]; then
    summarize
    exit 0
fi

# Where is the store, and is the application even there? A missing store means the
# recorder has never fired — but only if we looked in the right place, and for five
# weeks this script did not.
exc_resolve_store "$REMOTE" "$SSH_PORT"
case $? in
    0) ;;
    2)
        echo "No $REMOTE_EXC_DIR on $REMOTE (and no legacy $LEGACY_EXC_DIR)."
        echo "The application is there, so this really is 'nothing has been recorded'."
        exit 0
        ;;
    *) exit 1 ;;
esac

mkdir -p "$LOCAL_DIR"

if ssh -p "$SSH_PORT" "$REMOTE" 'command -v rsync' >/dev/null 2>&1; then
    rsync -rlptz --exclude='.emails' -e "ssh -p $SSH_PORT" \
        "$REMOTE:$REMOTE_EXC_DIR/" "$LOCAL_DIR/"
    echo "Synced $REMOTE_EXC_DIR/ -> $LOCAL_DIR/ (rsync)."
else
    echo "rsync unavailable on the server; falling back to tar over SFTP." >&2
    REMOTE_TARBALL=exceptions-sync.tar.gz
    TAR_CMD="cd $REMOTE_EXC_DIR && tar --exclude='.emails' -czf ~/$REMOTE_TARBALL . && echo TARRED-OK"
    if ssh -t -p "$SSH_PORT" "$REMOTE" "$TAR_CMD"; then
        LOCAL_TARBALL=$(mktemp -t exceptions-sync-XXXXXX).tar.gz
        CLEANUP_FILES+=("$LOCAL_TARBALL")
        echo "get $REMOTE_TARBALL $LOCAL_TARBALL" | sftp -P "$SSH_PORT" "$REMOTE"
        tar -xzf "$LOCAL_TARBALL" -C "$LOCAL_DIR"
        ssh -p "$SSH_PORT" "$REMOTE" "rm -f ~/$REMOTE_TARBALL"
        echo "Synced $REMOTE_EXC_DIR/ -> $LOCAL_DIR/ (tar)."
    else
        cat >&2 <<MSG

Remote exec failed. Finish by pasting this into an interactive SSH session
(ssh -p $SSH_PORT $REMOTE):

  cd $REMOTE_EXC_DIR && tar --exclude='.emails' -czf ~/$REMOTE_TARBALL .

Then, from this machine:

  mkdir -p $LOCAL_DIR
  echo "get $REMOTE_TARBALL <local-tarball-path>" | sftp -P $SSH_PORT $REMOTE
  tar -xzf <local-tarball-path> -C $LOCAL_DIR
  ssh -p $SSH_PORT $REMOTE "rm -f ~/$REMOTE_TARBALL"

MSG
        exit 1
    fi
fi

echo
summarize
