#!/usr/bin/env bash
# Delete recorded exception fingerprints from the SERVER's
# data/exceptions/. This is the "I fixed this, tell me if it comes back"
# button: the PHP exception recorder's email throttling keys off a
# fingerprint's on-disk record, so removing a fingerprint re-arms its
# notification threshold — the next occurrence is treated as brand new.
#
# Targets the SERVER's current directory listing directly (via SSH),
# never the local data/exceptions-prod/ mirror: that mirror never gets
# entries deleted from it (see fetch-exceptions.sh) and would otherwise
# drift out of sync with what is actually still on the server.
set -euo pipefail
cd "$(dirname "$0")/.."

APP_PATH=public_html/schoenstatt.link
REMOTE_EXC_DIR="$APP_PATH/data/exceptions"

YES=0
NO_ARCHIVE=0
OLDER_THAN=""
REMOTE="ourlink@dedi2934.your-server.de"
PORT=222
FPS=()

FP_RE='^[0-9a-f]{8}$'

while [ "$#" -gt 0 ]; do
    case "$1" in
        --yes)
            YES=1
            shift
            ;;
        --no-archive)
            NO_ARCHIVE=1
            shift
            ;;
        --older-than)
            if [ "$#" -lt 2 ]; then
                echo "ERROR: --older-than requires a DAYS argument" >&2
                exit 1
            fi
            OLDER_THAN=$2
            if [[ ! "$OLDER_THAN" =~ ^[0-9]+$ ]]; then
                echo "ERROR: --older-than expects a non-negative integer number of days, got '$OLDER_THAN'" >&2
                exit 1
            fi
            shift 2
            ;;
        --remote)
            if [ "$#" -lt 2 ]; then
                echo "ERROR: --remote requires an argument" >&2
                exit 1
            fi
            REMOTE=$2
            shift 2
            ;;
        --port)
            if [ "$#" -lt 2 ]; then
                echo "ERROR: --port requires an argument" >&2
                exit 1
            fi
            PORT=$2
            shift 2
            ;;
        -*)
            echo "ERROR: unknown option '$1'" >&2
            exit 1
            ;;
        *)
            # A bare fingerprint argument. Validate immediately: every
            # fingerprint that could ever reach a remote command must be
            # checked against FP_RE before it is trusted anywhere.
            if [[ ! "$1" =~ $FP_RE ]]; then
                echo "ERROR: '$1' is not a valid fingerprint (expected exactly 8 lowercase hex chars)" >&2
                exit 1
            fi
            FPS+=("$1")
            shift
            ;;
    esac
done

# "Clearing all" (no explicit fingerprints AND no --older-than filter) is
# the only case that also wipes the .emails ledger — a partial clear must
# leave other fingerprints' send-rate bookkeeping alone.
CLEAR_ALL=0
if [ "${#FPS[@]}" -eq 0 ] && [ -z "$OLDER_THAN" ]; then
    CLEAR_ALL=1
fi

CLEANUP_FILES=()
cleanup() {
    local f
    for f in ${CLEANUP_FILES[@]+"${CLEANUP_FILES[@]}"}; do
        rm -f "$f"
    done
}
trap cleanup EXIT

if [ "$NO_ARCHIVE" -eq 0 ]; then
    echo "Archiving before clearing (tools/fetch-exceptions.sh $REMOTE $PORT)..."
    if ! bash "$(dirname "$0")/fetch-exceptions.sh" "$REMOTE" "$PORT"; then
        echo "ERROR: archive fetch failed — aborting clear so nothing is lost. Use --no-archive to skip archiving (not recommended)." >&2
        exit 1
    fi
    echo
fi

# List the server's current fingerprints directly — never trust the local
# mirror for this, it can be stale (see header comment). Each line is
# "<fp>\t<base64 meta.json or empty>"; base64 keeps embedded newlines from
# a JSON file out of the line-oriented protocol.
REMOTE_LIST_SCRIPT='set -eu
app="$1"
dir="$app/data/exceptions"
if [ ! -d "$dir" ]; then
    echo "__NO_DIR__"
    exit 0
fi
cd "$dir"
for d in */; do
    fp=${d%/}
    case "$fp" in
        [0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f]) ;;
        *) continue ;;
    esac
    enc=""
    if [ -f "$fp/meta.json" ]; then
        enc=$(base64 "$fp/meta.json" 2>/dev/null | tr -d "\n")
    fi
    printf "%s\t%s\n" "$fp" "$enc"
done
'

if ! OUTPUT=$(ssh -p "$PORT" "$REMOTE" bash -s -- "$APP_PATH" <<REMOTE_EOF
$REMOTE_LIST_SCRIPT
REMOTE_EOF
); then
    echo "ERROR: could not list $REMOTE_EXC_DIR on $REMOTE (port $PORT)." >&2
    exit 1
fi

if [ "$OUTPUT" = "__NO_DIR__" ]; then
    echo "No $REMOTE_EXC_DIR on $REMOTE — nothing to clear."
    exit 0
fi

RAW_LINES=()
if [ -n "$OUTPUT" ]; then
    mapfile -t RAW_LINES <<<"$OUTPUT"
fi
ALL_COUNT=${#RAW_LINES[@]}

if [ "$ALL_COUNT" -eq 0 ]; then
    echo "No exception fingerprints currently on $REMOTE. Nothing to clear."
    exit 0
fi

RAW_FPS=()
for line in "${RAW_LINES[@]}"; do
    RAW_FPS+=("${line%%$'\t'*}")
done

# Warn (don't fail) about explicitly requested fingerprints that aren't
# on the server at all — distinct from ones excluded by --older-than.
if [ "${#FPS[@]}" -gt 0 ]; then
    for fp in "${FPS[@]}"; do
        found=0
        for rawfp in "${RAW_FPS[@]}"; do
            if [ "$rawfp" = "$fp" ]; then
                found=1
                break
            fi
        done
        if [ "$found" -eq 0 ]; then
            echo "note: fingerprint $fp not found on the server — skipping" >&2
        fi
    done
fi

php_script=$(mktemp -t exceptions-clear-filter-XXXXXX.php)
CLEANUP_FILES+=("$php_script")
cat >"$php_script" <<'PHP'
<?php
// argv[1] = --older-than DAYS value, or '' for no age filter.
// argv[2..] = explicit fingerprints to intersect with (empty = "all").
// stdin = "<fp>\t<base64 meta.json>\n" lines from the server listing.
// Emits tab-separated rows for the surviving (targeted) fingerprints,
// sorted by last_seen descending — same shape fetch-exceptions.sh uses.
$olderThanDays = $argv[1] !== '' ? (int) $argv[1] : null;
$explicitFps = array_slice($argv, 2);
$threshold = $olderThanDays !== null
    ? (new DateTimeImmutable())->modify("-{$olderThanDays} days")
    : null;

$rows = [];
while (($line = fgets(STDIN)) !== false) {
    $line = rtrim($line, "\n");
    if ($line === '') {
        continue;
    }
    $parts = explode("\t", $line, 2);
    $fp = $parts[0];
    $enc = $parts[1] ?? '';
    if ($explicitFps && !in_array($fp, $explicitFps, true)) {
        continue;
    }
    $meta = [];
    if ($enc !== '') {
        $decoded = json_decode(base64_decode($enc), true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
    $lastSeen = $meta['last_seen'] ?? null;
    if ($threshold !== null) {
        if ($lastSeen === null) {
            continue; // unknown age: never auto-selected by --older-than
        }
        try {
            $lastSeenDt = new DateTimeImmutable($lastSeen);
        } catch (\Throwable $e) {
            continue;
        }
        if ($lastSeenDt > $threshold) {
            continue; // not old enough yet
        }
    }
    $class = $meta['class'] ?? '';
    $classShort = $class !== '' ? preg_replace('/^.*\\\\/', '', $class) : '(unknown)';
    $rows[] = [
        $fp,
        (string) ($meta['count'] ?? '?'),
        (string) ($lastSeen ?? '(unknown)'),
        (string) ($meta['first_seen'] ?? '(unknown)'),
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

TARGET_ROWS=()
mapfile -t TARGET_ROWS < <(printf '%s' "$OUTPUT" | php "$php_script" "$OLDER_THAN" ${FPS[@]+"${FPS[@]}"})

if [ "${#TARGET_ROWS[@]}" -eq 0 ]; then
    echo "No fingerprints match the given criteria. Nothing to clear."
    exit 0
fi

TARGET_FPS=()
for row in "${TARGET_ROWS[@]}"; do
    IFS=$'\t' read -r fp _ <<<"$row"
    if [[ ! "$fp" =~ $FP_RE ]]; then
        echo "INTERNAL ERROR: refusing to act on invalid fingerprint '$fp'" >&2
        exit 1
    fi
    TARGET_FPS+=("$fp")
done

echo "The following fingerprint(s) would be deleted from $REMOTE:$REMOTE_EXC_DIR:"
echo
printf '%-10s %6s %-25s %-25s %-8s %-30s %-30s\n' \
    FP COUNT LAST_SEEN FIRST_SEEN NOTIFIED CLASS ROUTE
for row in "${TARGET_ROWS[@]}"; do
    IFS=$'\t' read -r fp count last first notified class route <<<"$row"
    printf '%-10s %6s %-25s %-25s %-8s %-30.30s %-30.30s\n' \
        "$fp" "$count" "$last" "$first" "$notified" "$class" "$route"
done
echo
if [ "$CLEAR_ALL" -eq 1 ]; then
    echo "(clearing ALL — the .emails notification ledger will also be removed)"
fi

if [ "$YES" -ne 1 ]; then
    echo >&2
    echo "Refusing to delete anything without --yes. Re-run with --yes to proceed." >&2
    exit 1
fi

# Build the remote delete command from validated fingerprints only. Every
# path is data/exceptions/<fp> under the app dir — never a bare or
# user-supplied path — and fp has already been checked against FP_RE.
RM_CMDS=""
for fp in "${TARGET_FPS[@]}"; do
    if [[ ! "$fp" =~ $FP_RE ]]; then
        echo "INTERNAL ERROR: refusing to delete invalid fingerprint '$fp'" >&2
        exit 1
    fi
    RM_CMDS+="rm -rf 'data/exceptions/${fp}'; "
done
if [ "$CLEAR_ALL" -eq 1 ]; then
    # .emails is the hourly send ledger and the transport circuit breaker;
    # .overflow is the breadcrumb the recorder leaves when the fingerprint
    # ceiling forced it to drop something. Both are only meaningful next to
    # the records being removed.
    RM_CMDS+="rm -rf 'data/exceptions/.emails' 'data/exceptions/.overflow'; "
fi

REMOTE_DELETE_CMD="cd '$APP_PATH' && { $RM_CMDS } && echo CLEARED-OK"
if ! ssh -p "$PORT" "$REMOTE" "$REMOTE_DELETE_CMD" >/dev/null; then
    echo "ERROR: remote deletion failed or was incomplete — check $REMOTE:$REMOTE_EXC_DIR by hand." >&2
    exit 1
fi

REMAINING=$((ALL_COUNT - ${#TARGET_FPS[@]}))
echo "Deleted ${#TARGET_FPS[@]} fingerprint(s) from $REMOTE:$REMOTE_EXC_DIR:"
printf '  %s\n' "${TARGET_FPS[@]}"
if [ "$CLEAR_ALL" -eq 1 ]; then
    echo "Also removed the .emails notification ledger."
fi
echo "$REMAINING fingerprint(s) remain on the server."
echo "Clearing a fingerprint re-arms its email notification threshold: if it recurs, it will be reported as new."
