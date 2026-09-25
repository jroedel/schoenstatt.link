#!/usr/bin/env bash
#
# The program that takes the pre-migration snapshot ON THE SERVER, run here.
#
# WHY THIS EXISTS. The snapshot used to be dumped through the tunnel and uploaded back to
# the machine it came from; for sch_visits that was measured at 150KiB/s and ninety
# minutes, with a deploy waiting on it. It now runs on the server, with the server's own
# read credentials, and the bytes never move. That buys speed at the price of a program
# this machine cannot easily watch: it runs over ssh, on a host no test may touch, and its
# single line of output is the only thing the caller ever sees.
#
# So the program takes all four of its inputs as arguments and reads no environment. That
# makes it runnable here, which is what this file does — the shipped text, lifted out of
# tools/migrate.sh by its markers, not a copy that can drift.
#
# What must hold, and what each case pins:
#   - a server that CANNOT take the snapshot says so and exits 10, because 10 is what
#     sends the caller back to the tunnel. A deploy must never abort over this;
#   - a dump that RAN and produced something untrustworthy exits 11 or more, because that
#     must abort: a truncated snapshot is worse than a slow one, and a valid gzip of the
#     first half of a table looks exactly like a good backup;
#   - no secret is printed, and no defaults file is left behind.
set -uo pipefail

cd "$(dirname "$0")/../.."
ROOT=$(pwd)

FAILED=0 SKIPPED=0 PASSED=0
check() {
    if [ "$1" = 0 ]; then
        PASSED=$((PASSED + 1))
        printf '  ok    %s\n' "$2"
    else
        printf '  FAIL  %s\n' "$2"
        printf '        expected: %s\n' "${3:-}"
        printf '        actual:   %s\n' "${4:-}"
        FAILED=$((FAILED + 1))
    fi
}
skip() { printf '  skip  %s\n' "$1"; SKIPPED=$((SKIPPED + 1)); }

# The shipped text, by its markers. An `eval` of the whole marked region defines
# REMOTE_SNAPSHOT_PROG exactly as migrate.sh defines it; the marker lines are comments.
eval "$(awk '/^# >>> remote snapshot program$/,/^# <<< remote snapshot program$/' tools/migrate.sh)"
[ -n "${REMOTE_SNAPSHOT_PROG:-}" ] \
    || { echo "could not lift the remote snapshot program out of tools/migrate.sh" >&2; exit 1; }

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

# Run the program the way ssh runs it: text on stdin, everything else in argv.
# $1 PATH to use, $2 dir, $3 config, $4 name, $5 tables. Sets OUT and RC.
run_prog() {
    local path=$1
    OUT=$(printf '%s\n' "$REMOTE_SNAPSHOT_PROG" \
        | env -i PATH="$path" HOME="$WORK" TMPDIR="$WORK/tmp" \
            "$BASH" -s -- "$2" "$3" "$4" "$5" 2>&1)
    RC=$?
    # The caller classifies on the LAST line and shows the rest only when it fails, so
    # that anything a login shell prints ahead of the verdict cannot be mistaken for it.
    # Judge the same line here, or the test is not testing what production reads.
    VERDICT=${OUT##*$'\n'}
}

FULL_PATH=$PATH
# By absolute path, so that a PATH under test says nothing about which bash runs the
# program — only about what the program can find once it is running.
BASH=$(command -v bash)
mkdir -p "$WORK/tmp"

config_file() {   # $1 path, $2 password, $3 extra php inside the db array
    cat > "$1" <<PHP
<?php
return ['db' => [
    'hostname' => '127.0.0.1',
    'port' => ${CAPSULE_PORT:-33306},
    'database' => 'ourlink_db1',
    'username' => 'schoenstatt',
    'password' => '$2',
    ${3:-}
]];
PHP
}

# ================================================= exit 10: fall back, do not abort =====

# 1. No mysqldump. An empty PATH is enough: it is the first thing the program looks for,
#    and every later step would fail for a different reason if the order were wrong.
run_prog /nonexistent "$WORK/out" "$WORK/cfg.php" "s.sql.gz" "t"
case "$VERDICT|$RC" in
    "PRECHECK there is no mysqldump on the server|10") check 0 "no mysqldump: PRECHECK, exit 10" ;;
    *) check 1 "no mysqldump: PRECHECK, exit 10" "PRECHECK … |10" "$OUT|$RC" ;;
esac

# 2. No php. mysqldump present, nothing else — the check must be reached, not short-circuited.
mkdir -p "$WORK/bin-dump-only"
printf '#!/bin/sh\nexit 0\n' > "$WORK/bin-dump-only/mysqldump"
chmod +x "$WORK/bin-dump-only/mysqldump"
run_prog "$WORK/bin-dump-only" "$WORK/out" "$WORK/cfg.php" "s.sql.gz" "t"
case "$VERDICT|$RC" in
    "PRECHECK there is no php on the server|10") check 0 "no php: PRECHECK, exit 10" ;;
    *) check 1 "no php: PRECHECK, exit 10" "PRECHECK there is no php …|10" "$OUT|$RC" ;;
esac

# 3. No application config. This is the likely shape of a first run against a server whose
#    layout differs, and it must be a fallback rather than a failed deploy.
run_prog "$FULL_PATH" "$WORK/out" "$WORK/definitely-absent.php" "s.sql.gz" "t"
case "$RC:$VERDICT" in
    10:PRECHECK*"no application config"*) check 0 "absent config: PRECHECK, exit 10" ;;
    *) check 1 "absent config: PRECHECK, exit 10" "10:PRECHECK … no application config" "$RC:$OUT" ;;
esac

# 4. A config PHP can read but that says nothing useful. Each of these is a different
#    misconfiguration and none of them may abort a deploy.
printf '<?php\nreturn ["not_db" => []];\n' > "$WORK/cfg-nodb.php"
run_prog "$FULL_PATH" "$WORK/out" "$WORK/cfg-nodb.php" "s.sql.gz" "t"
case "$RC:$VERDICT" in
    10:PRECHECK*"no db section"*) check 0 "config without a db section: PRECHECK, exit 10" ;;
    *) check 1 "config without a db section: PRECHECK, exit 10" "10:PRECHECK … no db section" "$RC:$OUT" ;;
esac

config_file "$WORK/cfg-nopass.php" ""
run_prog "$FULL_PATH" "$WORK/out" "$WORK/cfg-nopass.php" "s.sql.gz" "t"
case "$RC:$VERDICT" in
    10:PRECHECK*"db.password is empty"*) check 0 "config with an empty password: PRECHECK, exit 10" ;;
    *) check 1 "config with an empty password: PRECHECK, exit 10" "10:PRECHECK … db.password is empty" "$RC:$OUT" ;;
esac

# 5. A nearly full filesystem, with df stubbed. The floor exists so the program refuses to
#    start rather than write a plausible truncated file into a disk that is about to fill.
mkdir -p "$WORK/bin-lowdisk"
for t in mysqldump php; do ln -sf "$(command -v "$t")" "$WORK/bin-lowdisk/$t"; done
for t in mkdir awk mktemp chmod; do ln -sf "$(command -v "$t")" "$WORK/bin-lowdisk/$t"; done
cat > "$WORK/bin-lowdisk/df" <<'STUB'
#!/bin/sh
echo "Filesystem 1024-blocks Used Available Capacity Mounted on"
echo "/dev/stub      1000000  900000     40960       96% /"
STUB
chmod +x "$WORK/bin-lowdisk/df"
config_file "$WORK/cfg.php" "unused"
run_prog "$WORK/bin-lowdisk" "$WORK/out" "$WORK/cfg.php" "s.sql.gz" "t"
case "$RC:$VERDICT" in
    10:PRECHECK*"40MiB free"*"2GiB is the floor"*) check 0 "a nearly full disk: PRECHECK, exit 10" ;;
    *) check 1 "a nearly full disk: PRECHECK, exit 10" "10:PRECHECK … 40MiB free … 2GiB is the floor" "$RC:$OUT" ;;
esac

# 6. The account cannot dump these tables. This is the grant question, and it is asked with
#    --no-data BEFORE the real dump precisely so that its answer is still a fallback.
mkdir -p "$WORK/bin-refuse"
cat > "$WORK/bin-refuse/mysqldump" <<'STUB'
#!/bin/sh
echo "mysqldump: Got error: 1142: SELECT command denied to user 'web'@'localhost' for table 'sch_visits'" >&2
exit 2
STUB
chmod +x "$WORK/bin-refuse/mysqldump"
run_prog "$WORK/bin-refuse:$FULL_PATH" "$WORK/out" "$WORK/cfg.php" "s.sql.gz" "sch_visits,sch_changes"
case "$RC:$VERDICT" in
    10:PRECHECK*"cannot dump these tables"*1142*) check 0 "a missing grant: PRECHECK, exit 10" ;;
    *) check 1 "a missing grant: PRECHECK, exit 10" "10:PRECHECK … cannot dump these tables … 1142" "$RC:$OUT" ;;
esac

# ============================================== exit 11+: abort, the snapshot is a lie =====

# A mysqldump that answers --no-data happily and then fails, or lies, on the real dump.
# $1 = what the real dump does.
dump_stub() {
    mkdir -p "$WORK/bin-$1"
    cat > "$WORK/bin-$1/mysqldump" <<STUB
#!/bin/sh
case " \$* " in
    *" --no-data "*) echo "-- schema only"; exit 0 ;;
esac
$2
STUB
    chmod +x "$WORK/bin-$1/mysqldump"
}

# 7. The dump fails part way. `mysqldump | gzip` hides this without pipefail — the pipeline's
#    status would be gzip's, which is 0, and the program would call a half table a backup.
dump_stub failing 'echo "-- MariaDB dump"; echo "INSERT INTO t VALUES (1);"; echo "mysqldump: Error 2013: Lost connection during table sch_visits" >&2; exit 2'
run_prog "$WORK/bin-failing:$FULL_PATH" "$WORK/out" "$WORK/cfg.php" "s7.sql.gz" "t"
case "$RC:$VERDICT" in
    11:FAIL*"did not complete"*2013*) check 0 "a dump that fails part way: FAIL, exit 11" ;;
    *) check 1 "a dump that fails part way: FAIL, exit 11" "11:FAIL … did not complete … 2013" "$RC:$OUT" ;;
esac

# 8. The dump is cut short but exits 0 — killed by the host, or the disk filled under it.
#    A valid gzip of a valid prefix is what a good backup looks like from the outside, and
#    the end marker is the only thing that tells them apart.
dump_stub truncated 'echo "-- MariaDB dump"; echo "INSERT INTO t VALUES (1);"; exit 0'
run_prog "$WORK/bin-truncated:$FULL_PATH" "$WORK/out" "$WORK/cfg.php" "s8.sql.gz" "t"
case "$RC:$VERDICT" in
    13:FAIL*"stops short of mysqldump's end marker"*) check 0 "a dump cut short: FAIL, exit 13" ;;
    *) check 1 "a dump cut short: FAIL, exit 13" "13:FAIL … stops short …" "$RC:$OUT" ;;
esac

# ============================================================== exit 0: the happy path =====

# 9. A complete dump. The size and the sha256 come back on one line, and the file is where
#    the caller will later prune it from.
dump_stub complete 'echo "-- MariaDB dump"; echo "INSERT INTO t VALUES (1);"; echo "-- Dump completed on 2026-09-24 12:00:00"; exit 0'
config_file "$WORK/cfg-secret.php" "hunter2-NOT-FOR-STDOUT"
run_prog "$WORK/bin-complete:$FULL_PATH" "$WORK/snapdir" "$WORK/cfg-secret.php" "s9.sql.gz" "t"
case "$RC:$VERDICT" in
    0:OK\ *) check 0 "a complete dump: OK with a size and a digest, exit 0" ;;
    *) check 1 "a complete dump: OK with a size and a digest, exit 0" "0:OK <size> <sha256>" "$RC:$OUT" ;;
esac

SUM=${VERDICT##* }
ACTUAL=$(sha256sum "$WORK/snapdir/s9.sql.gz" 2>/dev/null | cut -d' ' -f1)
[ -n "$ACTUAL" ] && [ "$SUM" = "$ACTUAL" ] \
    && check 0 "the digest it reports is the digest of the file it left" \
    || check 1 "the digest it reports is the digest of the file it left" "$ACTUAL" "$SUM"

# grep -c, never grep -q: -q stops reading at the first match and gzip takes SIGPIPE for
# it, which pipefail reports as a failed pipeline. That race is the bug this file found in
# the program itself; do not reintroduce it here.
[ "$(gzip -dc "$WORK/snapdir/s9.sql.gz" 2>/dev/null | grep -c 'Dump completed')" -ge 1 ] \
    && check 0 "the file is a readable gzip of the dump" \
    || check 1 "the file is a readable gzip of the dump" "a gzip ending in the marker" "not that"

# 10. The password never appears in anything the caller can see. It reaches mysqldump
#     through a 0600 file and nowhere else — not stdout, not stderr, not argv.
case $OUT in
    *hunter2*) check 1 "the password is not printed" "no 'hunter2' anywhere in the output" "$OUT" ;;
    *) check 0 "the password is not printed" ;;
esac

# 11. And the file it wrote the password into is gone. TMPDIR is this test's own, so
#     anything left behind is the program's.
LEFT=$(find "$WORK/tmp" -type f 2>/dev/null | wc -l)
[ "$LEFT" = 0 ] \
    && check 0 "no defaults file is left behind" \
    || check 1 "no defaults file is left behind" "0 files in TMPDIR" "$LEFT: $(find "$WORK/tmp" -type f | tr '\n' ' ')"

# 12. …including when it aborts. The trap must fire on the failure paths too, or a run that
#     ends in FAIL leaves a readable copy of the application's password on the server.
rm -f "$WORK/tmp"/* 2>/dev/null
run_prog "$WORK/bin-truncated:$FULL_PATH" "$WORK/out" "$WORK/cfg-secret.php" "s12.sql.gz" "t"
LEFT=$(find "$WORK/tmp" -type f 2>/dev/null | wc -l)
[ "$LEFT" = 0 ] \
    && check 0 "no defaults file is left behind when it aborts either" \
    || check 1 "no defaults file is left behind when it aborts either" "0 files in TMPDIR" "$LEFT"

# ============================================ the caller: fall back, or abort? =====
#
# The remote program's exit status only matters through snapshot_on_server(), which turns
# it into one of two outcomes that could not be further apart: a warning and a slower
# route, or a dead deploy. Nothing on this machine exercises that but this, because the
# real thing needs a server.
#
# Both functions are lifted out by text, so this is the shipped implementation and not a
# description of it. Everything they touch is stubbed: `remote` is the ssh call, and it
# answers with whatever the case under test says the server said.
eval "$(awk '/^snapshot_on_server\(\) \{/,/^\}/' tools/migrate.sh)"
eval "$(awk '/^take_snapshot\(\) \{/,/^\}/' tools/migrate.sh)"
declare -f snapshot_on_server >/dev/null && declare -f take_snapshot >/dev/null \
    || { echo "could not lift the snapshot callers out of tools/migrate.sh" >&2; exit 1; }

warn() { printf 'warn:%s\n' "$1"; }
ok()   { printf 'ok:%s\n' "$1"; }
fail() { printf 'ABORT:%s\n' "$1"; exit 1; }
snapshot_over_the_wire() { printf 'tunnel:%s\n' "$2"; }
SNAPSHOT_DIR_REMOTE=/srv/snapshots
REMOTE_APP_CONFIG=/srv/local.php
# REMOTE_SNAPSHOT_PROG is left as migrate.sh defines it. The stub ignores what it is
# handed, but the real program text is still what gets piped at it — and overwriting the
# variable here would silently empty the capsule run further down.

# $1 what the server printed, $2 the exit status it came back with, $3.. the call.
# Run with migrate.sh's own shell options, in a subshell, so an abort is survivable.
#
# STUB_SAID/STUB_RC are globals with names nothing else uses, and that is not fussiness:
# bash scopes `local` dynamically, so a stub reading a plain `rc` would read the `rc` that
# snapshot_on_server declares — which is 0 at the moment the stub runs. The stub then
# reports success no matter what the case says, and every abort case passes for the wrong
# reason. It did, before this comment existed.
caller_says() {
    STUB_SAID=$1 STUB_RC=$2; shift 2
    (
        set -euo pipefail
        remote() { printf '%s' "$STUB_SAID"; return "$STUB_RC"; }
        ENV_NAME=production
        DEPLOY_APP_PATH=public_html/app
        "$@"
        printf 'returned:%s\n' "$?"
    ) 2>&1
}

# 13. The server took it. Nothing is said twice and nothing falls back.
OUT=$(caller_says "OK 171M 3f1c2b9a8d7e6f5a4b3c2d1e0f9a8b7c" 0 take_snapshot db9.1.sql sch_visits snap.gz)
case $OUT in
    ok:*"snapshot snap.gz (171M) taken on the server"*sha256*3f1c2b9a8d7e6f5a*returned:0*)
        check 0 "a server snapshot is reported with its size and digest, and nothing else runs" ;;
    *) check 1 "a server snapshot is reported with its size and digest, and nothing else runs" \
        "ok: … taken on the server … / returned:0" "$OUT" ;;
esac

# 14. THE CASE THIS IS FOR. The server cannot do it, so the tunnel does — and the deploy
#     lives. An abort here would turn a capability we did not have last week into a
#     regression that stops a release.
OUT=$(caller_says "PRECHECK there is no mysqldump on the server" 10 take_snapshot db9.1.sql sch_visits snap.gz)
case $OUT in
    *ABORT*) check 1 "a server that cannot dump falls back instead of aborting" "no ABORT" "$OUT" ;;
    *warn:*"cannot dump for itself: there is no mysqldump"*tunnel:sch_visits*)
        check 0 "a server that cannot dump falls back instead of aborting" ;;
    *) check 1 "a server that cannot dump falls back instead of aborting" \
        "warn: … / tunnel:sch_visits" "$OUT" ;;
esac

# 15. AND THE CASE IT IS EQUALLY FOR. The dump ran and cannot be trusted. Falling back
#     here would hide a broken server behind ninety slow minutes; worse, it would do it
#     every time, and the snapshot is the one thing standing between a bad migration and
#     a lost table.
OUT=$(caller_says "FAIL snap.gz stops short of mysqldump's end marker" 13 take_snapshot db9.1.sql sch_visits snap.gz)
case $OUT in
    *tunnel:*) check 1 "an untrustworthy dump aborts rather than falling back" "no fallback" "$OUT" ;;
    ABORT:*"failed on the server (exit 13)"*"Nothing was applied"*"stops short"*)
        check 0 "an untrustworthy dump aborts rather than falling back" ;;
    *) check 1 "an untrustworthy dump aborts rather than falling back" \
        "ABORT: … exit 13 … Nothing was applied" "$OUT" ;;
esac

# 16. ssh itself failed: no verdict at all, status 255. Unclassifiable is not permission
#     to continue — this is the shape of a connection that died mid-dump, and what it left
#     on the server is unknown.
OUT=$(caller_says "" 255 take_snapshot db9.1.sql sch_visits snap.gz)
case $OUT in
    ABORT:*"exit 255"*"(no output)"*) check 0 "no verdict at all aborts" ;;
    *) check 1 "no verdict at all aborts" "ABORT: … exit 255 … (no output)" "$OUT" ;;
esac

# 17. A server whose banner or MOTD lands on stdout ahead of the verdict. The last line is
#     the answer; the noise belongs in the report, not in the decision.
OUT=$(caller_says "Welcome to konsoleH
Last login: Tue
OK 2.0M deadbeefdeadbeefdeadbeefdeadbeef" 0 take_snapshot db9.1.sql sch_visits snap.gz)
case $OUT in
    ok:*"(2.0M) taken on the server"*returned:0*) check 0 "a chatty login shell does not hide the verdict" ;;
    *) check 1 "a chatty login shell does not hide the verdict" "ok: … (2.0M) …" "$OUT" ;;
esac

# 18. .deploy.local without DEPLOY_APP_PATH. The server has nowhere to put it, which is a
#     fallback and not a failure: SNAPSHOT_DIR_REMOTE would otherwise be '/shared/…', an
#     absolute path outside the account.
OUT=$(
    set -euo pipefail
    remote() { printf 'OK 1M abc\n'; }
    ENV_NAME=production
    DEPLOY_APP_PATH=''
    take_snapshot db9.1.sql sch_visits snap.gz
)
case $OUT in
    *warn:*DEPLOY_APP_PATH*"nowhere to put"*tunnel:sch_visits*)
        check 0 "no DEPLOY_APP_PATH falls back rather than dumping to an absolute path" ;;
    *) check 1 "no DEPLOY_APP_PATH falls back rather than dumping to an absolute path" \
        "warn: … DEPLOY_APP_PATH … / tunnel:sch_visits" "$OUT" ;;
esac

# 19. The capsule never asks a server anything. `remote` there would fail on the control
#     socket connect() never opened, so this is the difference between working and not.
OUT=$(
    set -euo pipefail
    remote() { printf 'this machine has no server\n'; return 1; }
    ENV_NAME=capsule
    DEPLOY_APP_PATH=public_html/app
    take_snapshot db9.1.sql sch_visits snap.gz
)
[ "$OUT" = "tunnel:sch_visits" ] \
    && check 0 "the capsule dumps locally and asks no server" \
    || check 1 "the capsule dumps locally and asks no server" "tunnel:sch_visits" "$OUT"

# ====================================================== the real thing, in the capsule =====
#
# Everything above stubs mysqldump, which proves the program's decisions and none of its
# assumptions: that PHP can read a config of this application's actual shape, and that what
# it writes is a defaults file a real mysqldump accepts. The capsule answers both. CI has
# no capsule, so this skips there rather than being weakened to fit.

if ! command -v mysqldump >/dev/null 2>&1; then
    skip "the real dump (no mysqldump on this machine)"
elif ! mysqladmin --host=127.0.0.1 --port="${CAPSULE_PORT:-33306}" \
        --user=schoenstatt --password=schoenstatt ping >/dev/null 2>&1; then
    skip "the real dump (the capsule database is not answering on ${CAPSULE_PORT:-33306})"
else
    config_file "$WORK/cfg-capsule.php" "schoenstatt"
    run_prog "$FULL_PATH" "$WORK/capsule" "$WORK/cfg-capsule.php" "real.sql.gz" "lib_checkouts"
    case "$RC:$VERDICT" in
        0:OK\ *) check 0 "the capsule dumps itself from its own config" ;;
        *) check 1 "the capsule dumps itself from its own config" "0:OK <size> <sha256>" "$RC:$OUT" ;;
    esac
    if [ "$RC" = 0 ]; then
        FOUND=$(gzip -dc "$WORK/capsule/real.sql.gz" 2>/dev/null | grep -c 'CREATE TABLE .lib_checkouts.')
        [ "${FOUND:-0}" -ge 1 ] \
            && check 0 "the dump contains the table it was asked for" \
            || check 1 "the dump contains the table it was asked for" "CREATE TABLE \`lib_checkouts\`" "absent"
    fi

    # Two tables, comma-separated, exactly as an @tables header spells them. The program
    # splits them itself; a header that reached mysqldump unsplit would dump one table
    # named "a,b" and fail, and passing a single table would never show it.
    #
    # Both are small on purpose. This runs on every ci-local, and the capsule holds a
    # production export — naming sch_visits or sch_changes here would spend a couple of
    # gigabytes to learn the same thing a hundred rows teach.
    run_prog "$FULL_PATH" "$WORK/capsule" "$WORK/cfg-capsule.php" "two.sql.gz" "lib_checkouts,lib_libraries"
    if [ "$RC" = 0 ]; then
        NTABLES=$(gzip -dc "$WORK/capsule/two.sql.gz" 2>/dev/null | grep -c '^CREATE TABLE ')
        [ "$NTABLES" = 2 ] \
            && check 0 "a comma-separated @tables list becomes two tables" \
            || check 1 "a comma-separated @tables list becomes two tables" "2 CREATE TABLE" "$NTABLES"
    else
        check 1 "a comma-separated @tables list becomes two tables" "0:OK …" "$RC:$OUT"
    fi
fi

if [ "$FAILED" = 0 ]; then
    printf 'server snapshot: %d check(s) passed%s\n' "$PASSED" \
        "$([ "$SKIPPED" = 0 ] || printf ', %d skipped' "$SKIPPED")"
    exit 0
fi
printf 'server snapshot: %d of %d check(s) FAILED\n' "$FAILED" "$((FAILED + PASSED))" >&2
exit 1
