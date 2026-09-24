#!/usr/bin/env bash
# Apply database/*.sql migrations against a tracked ledger.
#
#   ./tools/migrate.sh status                    what is applied, what is pending
#   ./tools/migrate.sh plan                      the pending files, with their headers
#   ./tools/migrate.sh apply --phase=pre         run the pending pre-deploy migrations
#   ./tools/migrate.sh apply --phase=post        …and the post-deploy ones
#   ./tools/migrate.sh backfill --through=db7.7  mark historical files applied, run nothing
#   ./tools/migrate.sh reseal db8.1.sql          re-record an applied file whose COMMENTS changed
#
#   --env=capsule      the local Docker database (default: production)
#   --dry-run          print what would run; execute nothing
#   -y, --yes          no confirmation prompt
#
# tools/deploy.sh calls `apply` at both phases. Everything here also runs
# standalone, which is how a migration is rehearsed against the capsule first.
#
# HOW A MIGRATION IS MADE SAFE, in the order the guarantees matter:
#
#   1. For a `dml` migration the ledger row is written INSIDE the same
#      transaction as the change. Applied-but-unrecorded and recorded-but-
#      unapplied are both impossible, and a failure part-way rolls the whole
#      file back. Every table this application touches has been InnoDB since
#      db7.0/db7.1, so this is real rather than aspirational.
#   2. `ddl` cannot have that. MariaDB commits implicitly on DDL and no
#      transaction can protect it, so a `ddl` migration must declare
#      `@idempotent: yes` — an assertion by its author that re-running it is a
#      no-op. That is a forcing function, not a proof. Write the guards.
#   3. The tables named in `@tables` are dumped BEFORE anything runs: on the
#      server, into shared/migration-snapshots/, for production; into
#      data/deploy/backups/ for the capsule. "Can we undo it" becomes a file
#      rather than a hope.
#   4. `mysql` aborts on the first error and exits non-zero; --force is never
#      used. Combined with (1) a failure in statement 3 of 7 leaves nothing.
#   5. Row counts are captured per statement and stored in the ledger, so the
#      numbers that docs/DEPLOY.md has been recording by hand are recorded by
#      the thing that did the work.
#   6. `@verify`, if present, must return ZERO ROWS afterwards. Write it as
#      "select what is still wrong"; anything it returns fails the migration.
#   7. An already-applied file whose bytes changed aborts, rather than being
#      silently skipped.
#
# None of that can tell you a migration is CORRECT. Rehearse against the
# capsule, whose data is days old and representative, and read the counts.
set -euo pipefail

cd "$(dirname "$0")/.."
REPO_ROOT=$(pwd)
# Overridable so the failure paths can be exercised against throwaway migrations
# and a scratch database, rather than being reasoned about. See the PR that added
# this file for the transcript.
MIGRATION_DIR=${SCH_MIGRATION_DIR:-$REPO_ROOT/database}
BACKUP_DIR=$REPO_ROOT/data/deploy/backups

# ---------------------------------------------------------------- output ----

if [ -t 1 ]; then
    C_STEP=$'\033[1;36m'; C_OK=$'\033[32m'; C_WARN=$'\033[33m'
    C_ERR=$'\033[1;31m'; C_DIM=$'\033[2m'; C_OFF=$'\033[0m'
else
    C_STEP=''; C_OK=''; C_WARN=''; C_ERR=''; C_DIM=''; C_OFF=''
fi
step() { printf '%s== %s%s\n' "$C_STEP" "$1" "$C_OFF"; }
info() { printf '   %s\n' "$1"; }
dim()  { printf '   %s%s%s\n' "$C_DIM" "$1" "$C_OFF"; }
ok()   { printf '   %s✓ %s%s\n' "$C_OK" "$1" "$C_OFF"; }
warn() { printf '   %s! %s%s\n' "$C_WARN" "$1" "$C_OFF" >&2; }
fail() { printf '\n%sABORT: %s%s\n' "$C_ERR" "$1" "$C_OFF" >&2; exit 1; }

# ----------------------------------------------------------------- flags ----

ACTION=${1:-status}
case $ACTION in status|plan|apply|backfill|reseal|lint) shift ;; *) ACTION=status ;; esac

ENV_NAME=production PHASE='' DRY_RUN=0 ASSUME_YES=0 THROUGH='' TARGET_FILE=''
while [ $# -gt 0 ]; do
    case $1 in
        --env=*)     ENV_NAME=${1#*=} ;;
        --phase=*)   PHASE=${1#*=} ;;
        --through=*) THROUGH=${1#*=} ;;
        --dry-run)   DRY_RUN=1 ;;
        -y|--yes)    ASSUME_YES=1 ;;
        -h|--help)   sed -n '2,/^set -euo/p' "$0" | sed 's/^#\{1,\} \{0,1\}//; $d'; exit 0 ;;
        -*)          fail "unknown option: $1" ;;
        *)           TARGET_FILE=$1 ;;
    esac
    shift
done
case $ENV_NAME in production|capsule) ;; *) fail "--env must be production or capsule" ;; esac
if [ "$ACTION" = apply ]; then
    case $PHASE in pre|post) ;; *) fail "apply needs --phase=pre or --phase=post" ;; esac
fi

# ---------------------------------------------------------------- config ----

# `lint` reads files and connects to nothing, so it must not require credentials —
# it runs in CI, where there is no .deploy.local and never will be.
#
# DEPLOY_CONFIG, because tools/deploy.sh honours it and shells out to this script at both
# migration phases. A runner writes the credentials outside the workspace and exports the
# path; without this line that export reaches deploy.sh and stops there, and the deploy
# dies at the pre-migration step with `.deploy.local is missing` — which is what happened
# on the first real deploy from Actions, 2026-09-23.
CONFIG=${DEPLOY_CONFIG:-$REPO_ROOT/.deploy.local}
if [ "$ACTION" != lint ]; then
    [ -f "$CONFIG" ] || fail "$CONFIG is missing. Run ./config.sh, then fill in the TODOs."
    # shellcheck disable=SC1090
    . "$CONFIG"
fi

CTRL_PATH=''
TUNNEL_UP=0
cleanup() {
    if [ "$TUNNEL_UP" = 1 ] && [ -n "$CTRL_PATH" ]; then
        ssh -S "$CTRL_PATH" -O exit "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

# MYSQL_ARGS is assembled once. The password reaches mysql through an env-file
# on a file descriptor, never argv: an argument is visible in `ps` to every
# other account on a shared host, which is the whole reason these credentials
# are not kept on the server in the first place.
DEFAULTS_FILE=''
connect() {
    if [ "$ENV_NAME" = capsule ]; then
        DB_HOST=127.0.0.1
        DB_PORT=${CAPSULE_DB_PORT:-33306}
        DB_NAME=${CAPSULE_DB_NAME:-ourlink_db1}
        DB_USER=${CAPSULE_DB_USER:-schoenstatt}
        DB_PASS=${CAPSULE_DB_PASS:-schoenstatt}
        dim "environment: capsule ($DB_HOST:$DB_PORT/$DB_NAME)"
    else
        : "${DEPLOY_DB_NAME:?not set in .deploy.local}"
        : "${DEPLOY_DB_USER:?not set in .deploy.local}"
        : "${DEPLOY_DB_PASS:?not set in .deploy.local}"
        : "${DEPLOY_DB_TUNNEL_PORT:=13306}"
        case ${DEPLOY_DB_USER}${DEPLOY_DB_PASS} in
            *TODO*) fail "DEPLOY_DB_USER/DEPLOY_DB_PASS in .deploy.local are still placeholders." ;;
        esac

        # The credentials never travel to the server. The tunnel makes the
        # server's own 127.0.0.1:3306 reachable from here, so a full-DDL account
        # can exist on this machine alone; a compromise of the web application
        # cannot reach a password it has never seen.
        #
        # Finding a free port is not tidiness. If the configured one is already
        # taken — and on the first real run it was, by an unrelated WordPress
        # container publishing 13306 — then the forward fails while `mysql`
        # connects to 127.0.0.1:13306 perfectly happily, reaching WHATEVER IS
        # ACTUALLY THERE. That run was saved only by the credentials not
        # matching. Had they matched, production migrations would have been
        # applied to a stranger's database and reported success.
        port_free() {
            if command -v ss >/dev/null 2>&1; then
                ! ss -ltn "sport = :$1" 2>/dev/null | grep -q LISTEN
            else
                ! (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null
            fi
        }
        PORT=$DEPLOY_DB_TUNNEL_PORT
        for _ in $(seq 1 20); do
            port_free "$PORT" && break
            PORT=$((PORT + 1))
        done
        port_free "$PORT" || fail "no free local port near $DEPLOY_DB_TUNNEL_PORT for the tunnel."
        if [ "$PORT" != "$DEPLOY_DB_TUNNEL_PORT" ]; then
            warn "local port $DEPLOY_DB_TUNNEL_PORT is in use by something else; tunnelling on $PORT instead."
        fi

        CTRL_PATH=$(mktemp -u "${TMPDIR:-/tmp}/migrate-ssh-XXXXXX")
        # ExitOnForwardFailure is what makes the exit status truthful. Without
        # it `ssh -f` forks before the bind is attempted, returns 0, and a failed
        # forward is indistinguishable from a working one.
        ssh -M -S "$CTRL_PATH" -f -N -o ExitOnForwardFailure=yes \
            -L "127.0.0.1:${PORT}:127.0.0.1:3306" \
            -p "${DEPLOY_SSH_PORT:-222}" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" \
            || fail "could not open the SSH tunnel to $DEPLOY_SSH_HOST (local port $PORT)."
        TUNNEL_UP=1
        DEPLOY_DB_TUNNEL_PORT=$PORT
        DB_HOST=127.0.0.1
        DB_PORT=$DEPLOY_DB_TUNNEL_PORT
        DB_NAME=$DEPLOY_DB_NAME
        DB_USER=$DEPLOY_DB_USER
        DB_PASS=$DEPLOY_DB_PASS
        dim "environment: production, over an SSH tunnel on 127.0.0.1:$DB_PORT"
    fi

    DEFAULTS_FILE=$(mktemp "${TMPDIR:-/tmp}/migrate-my-XXXXXX")
    chmod 600 "$DEFAULTS_FILE"
    printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' \
        "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" > "$DEFAULTS_FILE"
    # shellcheck disable=SC2064
    trap "rm -f '$DEFAULTS_FILE'; cleanup" EXIT

    mysql --defaults-extra-file="$DEFAULTS_FILE" "$DB_NAME" -N -B -e 'SELECT 1' >/dev/null 2>&1 \
        || fail "cannot reach the $ENV_NAME database '$DB_NAME' on $DB_HOST:$DB_PORT.

If this is production, the two likely causes are different and the message cannot tell
them apart on its own:
  - the account is not granted for 127.0.0.1. Through a tunnel that is where the
    connection appears to come from, so a grant for 'localhost' alone may not match;
  - the tunnel is not actually carrying traffic to the server."

    # Assert we are talking to THIS application's database and not to whatever
    # else answers on that port. A port collision plus a credential coincidence
    # would otherwise apply schoenstatt's migrations to a stranger's schema and
    # report success — see the note above about the WordPress container that was
    # listening on 13306 the first time this ran.
    local found
    found=$(mysql --defaults-extra-file="$DEFAULTS_FILE" "$DB_NAME" -N -B -e \
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('sch_changes','trans_phrases');" 2>/dev/null)
    [ "$found" = 2 ] || fail "connected to '$DB_NAME' on $DB_HOST:$DB_PORT, but it does not look like
schoenstatt's database — sch_changes and trans_phrases are not both there ($found of 2).

Refusing to go further. Something else is almost certainly answering on that port: the
tunnel may have failed to bind while another service happily accepted the connection.
Check with:  ss -ltnp | grep $DB_PORT"
}

sql()  { mysql --defaults-extra-file="$DEFAULTS_FILE" "$DB_NAME" -N -B -e "$1"; }
sqlv() { mysql --defaults-extra-file="$DEFAULTS_FILE" "$DB_NAME" -vv; }

ensure_ledger() {
    # Not itself a migration: a ledger that needed a migration to exist could
    # not record the migration that created it.
    sql "CREATE TABLE IF NOT EXISTS \`sch_migration\` (
            \`filename\`    VARCHAR(190) NOT NULL,
            \`sha256\`      CHAR(64)     NOT NULL,
            \`phase\`       VARCHAR(8)   NOT NULL,
            \`kind\`        VARCHAR(8)   NOT NULL,
            \`applied_on\`  DATETIME     NOT NULL,
            \`applied_by\`  VARCHAR(64)  NOT NULL,
            \`duration_ms\` INT UNSIGNED NULL,
            \`row_counts\`  TEXT         NULL,
            \`note\`        VARCHAR(255) NULL,
            PRIMARY KEY (\`filename\`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;"
}

# --------------------------------------------------------------- headers ----

# header <file> <name> — the value of `-- @name: value`, or empty.
header() {
    sed -n "s/^[[:space:]]*--[[:space:]]*@$2:[[:space:]]*\(.*\)[[:space:]]*$/\1/p" "$1" | head -1
}

escape() { printf '%s' "$1" | sed "s/'/''/g; s/\\\\/\\\\\\\\/g"; }

# Static checks a migration must pass before it is allowed anywhere near a
# database. Every one of these is a mistake that is silent at runtime.
validate() {
    local file=$1 base phase kind tables idem
    base=$(basename "$file")
    phase=$(header "$file" phase)
    kind=$(header "$file" kind)
    tables=$(header "$file" tables)
    idem=$(header "$file" idempotent)

    case $phase in
        pre|post) ;;
        '') fail "$base has no '-- @phase: pre|post' header.

There is no default on purpose. Both orders are real in this project's history and
guessing wrong is silent:
  pre  — the schema must move before the code. JTranslate 003/004: the new code
         SELECTs columns the old schema lacks, so deploying first breaks the site.
  post — the code must land before the data. Every db7.x retirement: run it first
         and the next page view re-files the rows and clears retired_on, because
         phrase discovery un-retires whatever the site still looks up." ;;
        *) fail "$base has '-- @phase: $phase'; it must be pre or post." ;;
    esac

    case $kind in
        dml)
            # The wrapper puts this file inside a transaction. DDL would commit
            # implicitly and silently break that, leaving a half-applied file
            # that the ledger nevertheless records as done.
            if grep -qiE '^[[:space:]]*(CREATE|ALTER|DROP|TRUNCATE|RENAME)[[:space:]]' "$file"; then
                fail "$base is '-- @kind: dml' but contains DDL. DDL commits implicitly, which
would break the transaction this file is wrapped in and could leave it half applied
while the ledger records it as done. Mark it '-- @kind: ddl' and make it idempotent."
            fi
            if grep -qiE '^[[:space:]]*(START[[:space:]]+TRANSACTION|BEGIN|COMMIT|ROLLBACK)' "$file"; then
                fail "$base manages its own transaction. Remove it — the runner wraps dml files,
and a nested COMMIT would end the wrapper's transaction early, committing part of the
file and writing the ledger row outside it."
            fi
            ;;
        ddl)
            [ "$idem" = yes ] || fail "$base is '-- @kind: ddl' and must declare '-- @idempotent: yes'.

MariaDB commits implicitly on DDL, so no transaction can roll this back and a failure
part-way is permanent. The only protection is that re-running is a no-op — CREATE TABLE
IF NOT EXISTS, ALTER guarded on information_schema, and so on. The header is you
asserting you have written those guards. It is a forcing function, not a proof."
            ;;
        '') fail "$base has no '-- @kind: dml|ddl' header." ;;
        *)  fail "$base has '-- @kind: $kind'; it must be dml or ddl." ;;
    esac

    [ -n "$tables" ] || fail "$base has no '-- @tables:' header. List the tables it changes so
they can be dumped before it runs, or write '-- @tables: none' if it changes none
(a report-only migration). Saying 'none' is a claim; make it a true one."

    # The list reaches mysqldump as arguments, and on production it does so inside a
    # command this machine builds for a shell on the server. A table name is
    # [A-Za-z0-9_]; anything else in this header is a typo at best, and at worst it is
    # whatever the author wrote arriving at a remote shell. Caught by `lint`, with no
    # database and no credentials, rather than mid-deploy.
    case $tables in
        *[!A-Za-z0-9_,\ ]*) fail "$base has '-- @tables: $tables', which is not a list of table names.
Table names are letters, digits and underscores, separated by commas." ;;
    esac

    # Optional, and only meaningful as 'yes': it declares that code from before this
    # migration cannot run after it. tools/deploy.sh reads it to refuse a rollback
    # into a release that predates the file. Unset means "older code still works",
    # which is the common case and the safe default to get wrong in that direction —
    # an unmarked destructive migration costs an outage, a wrongly marked one costs
    # a flag.
    case $(header "$file" destructive) in
        ''|yes|no) ;;
        *) fail "$base has '-- @destructive: $(header "$file" destructive)'; it must be yes or no.
A DROP COLUMN, DROP TABLE or NOT NULL tightening that older code cannot survive is
'yes'. Anything additive is 'no' or absent." ;;
    esac
}

# ------------------------------------------------------- snapshot target ----
#
# A production snapshot goes to the SERVER, not to $BACKUP_DIR.
#
# $BACKUP_DIR is $REPO_ROOT/data/deploy/backups — the checkout of whatever machine is
# running the deploy. That is right for `make prod-deploy`, where it lands in the
# operator's tree and stays. On a GitHub runner it is /home/runner/work/..., destroyed
# when the job ends: the dump was taken, reported `ok` with its size, and deleted minutes
# later. The log looked more protected than the deploy was (#297).
#
# It is a sibling of shared/data/ and shared/public/ on purpose. tools/deploy.sh symlinks
# everything under those two into every release, and a database dump has no business
# inside the application tree — not because it is reachable (the docroot is public/) but
# because nothing in the app should be able to open it by walking data/.
SNAPSHOT_DIR_REMOTE="${DEPLOY_APP_PATH:-}/shared/migration-snapshots"

# Run a command on the deploy host, reusing the control socket connect() already opened
# for the tunnel. No second authentication, and it cannot be called before connect().
remote() {
    [ -n "$CTRL_PATH" ] || fail "remote() called with no ssh control socket — connect() must run first."
    ssh -S "$CTRL_PATH" -p "${DEPLOY_SSH_PORT:-222}" \
        "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" "$@"
}

# The application's own configuration on the server, which is where the server finds
# database credentials of its own. See snapshot_on_server().
REMOTE_APP_CONFIG="${DEPLOY_APP_PATH:-}/shared/config-autoload/local.php"

# ------------------------------------------------------- remote program ----
#
# Sent to the server on stdin and run by `bash -s`, so the whole of it is legible here
# rather than assembled from fragments at the call site. It takes its four inputs as
# positional arguments and prints ONE line: `OK <size> <sha256>`, or a line beginning
# `PRECHECK` or `FAIL` naming what stopped it.
#
# `set -e` is deliberately absent and every step is checked by hand, because an exit
# status on its own cannot say which of two very different things happened:
#
#   exit 10  this server cannot take the snapshot at all — no mysqldump, no config, no
#            grant, no disk. Nothing was attempted; the caller falls back to the tunnel.
#   exit 11+ it ran, and what it produced cannot be trusted. The caller aborts, and no
#            migration runs.
#
# The markers are read by test/Deploy/server-snapshot-test.sh, which extracts this exact
# text and runs it against the capsule. Keep them.
# >>> remote snapshot program
REMOTE_SNAPSHOT_PROG=$(cat <<'PROG'
set -uo pipefail
DIR=$1 CFG=$2 NAME=$3
# An @tables header spells its list with commas; mysqldump wants arguments. Expanded by
# the shell rather than piped through tr, so that the checks below are genuinely the first
# thing that runs and a server missing its coreutils still gets to say so.
TABLES=${4//,/ }
OUT=$DIR/$NAME

command -v mysqldump >/dev/null 2>&1 || { echo "PRECHECK there is no mysqldump on the server"; exit 10; }
command -v php       >/dev/null 2>&1 || { echo "PRECHECK there is no php on the server"; exit 10; }
[ -f "$CFG" ]        || { echo "PRECHECK there is no application config at $CFG"; exit 10; }
mkdir -p "$DIR"      || { echo "PRECHECK $DIR cannot be created"; exit 10; }

# The gzip is what lands here, and it is far smaller than the tables — but refuse to
# start on a nearly full filesystem rather than write a plausible truncated file into one.
AVAIL=$(df -Pk "$DIR" 2>/dev/null | awk 'NR==2 {print $4}')
case ${AVAIL:-} in
    ''|*[!0-9]*) echo "PRECHECK the free space on $DIR cannot be read"; exit 10 ;;
esac
[ "$AVAIL" -ge 2097152 ] || { echo "PRECHECK only $((AVAIL / 1024))MiB free on $DIR, and 2GiB is the floor"; exit 10; }

MYCNF=$(mktemp) || { echo "PRECHECK no writable temporary directory"; exit 10; }
chmod 600 "$MYCNF"
ERRF=$(mktemp) || { rm -f "$MYCNF"; echo "PRECHECK no writable temporary directory"; exit 10; }
trap 'rm -f "$MYCNF" "$ERRF"' EXIT INT TERM HUP

# PHP reads the application's own config and writes the password straight into the 0600
# file; only the database NAME comes back on stdout. No secret is printed, and none is
# put in argv, where `ps` would show it to every other account on a shared host.
DB=$(php -r '
    $config = require $argv[1];
    $db = is_array($config) && isset($config["db"]) && is_array($config["db"]) ? $config["db"] : null;
    if (null === $db) { fwrite(STDERR, "the config has no db section"); exit(1); }
    foreach (["hostname", "database", "username", "password"] as $key) {
        if (! isset($db[$key]) || "" === (string) $db[$key]) {
            fwrite(STDERR, "db." . $key . " is empty"); exit(1);
        }
    }
    $written = file_put_contents($argv[2], sprintf(
        "[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n",
        $db["hostname"], $db["port"] ?? 3306, $db["username"], $db["password"]
    ));
    if (false === $written) { fwrite(STDERR, "the defaults file could not be written"); exit(1); }
    echo $db["database"];
' "$CFG" "$MYCNF" 2>"$ERRF") \
    || { echo "PRECHECK the application config is unusable: $(tr -d '\n' < "$ERRF")"; exit 10; }

# Prove the capability before committing to it: the same command, the same tables, the
# same flags, with no rows. A missing grant or a renamed table fails here in a second and
# sends the caller back to the tunnel, rather than an hour into a real dump with a deploy
# waiting on it.
# shellcheck disable=SC2086
mysqldump --defaults-extra-file="$MYCNF" --single-transaction --quick --no-data \
        "$DB" $TABLES >/dev/null 2>"$ERRF" \
    || { echo "PRECHECK the server's own account cannot dump these tables: $(tail -1 "$ERRF" | tr -d '\r\n')"; exit 10; }

# shellcheck disable=SC2086
mysqldump --defaults-extra-file="$MYCNF" --single-transaction --quick \
        "$DB" $TABLES 2>"$ERRF" | gzip > "$OUT" \
    || { echo "FAIL the dump did not complete: $(tail -1 "$ERRF" | tr -d '\r\n')"; exit 11; }

gzip -t "$OUT" 2>/dev/null || { echo "FAIL $NAME is not a readable gzip"; exit 12; }
# mysqldump's own last line. A dump cut short — out of disk, out of patience, killed by
# the host — is a valid gzip of a valid prefix, and nothing else here would notice.
#
# `case`, not `grep -q`: grep stops reading at the first match, gzip takes SIGPIPE for it,
# and pipefail then reports the whole pipeline as failed. That turns a perfectly good
# snapshot into a FAIL, and only sometimes — whether grep exits before gzip finishes is a
# race, which is how it passed here on a small file and would not have on a real one.
TAIL=$(gzip -dc "$OUT" 2>/dev/null | tail -c 256)
case $TAIL in
    *'Dump completed'*) ;;
    *) echo "FAIL $NAME stops short of mysqldump's end marker"; exit 13 ;;
esac

echo "OK $(du -h "$OUT" | cut -f1) $(sha256sum "$OUT" | cut -d' ' -f1)"
PROG
)
# <<< remote snapshot program

# Dump on the server, using credentials the server already has.
#
# It used to dump from here: mysqldump through the tunnel, gzip locally, then upload the
# result back to the machine it came from. For the small tables that is a second. For
# sch_visits — 8.7 million rows, 1.9GB — it was measured at 150KiB/s on 2026-09-24 and
# abandoned after eighty-five minutes, having reached the halfway mark, with the upload
# still to come. The failure mode is total, too: a tunnel that drops at minute eighty
# starts again from nothing, and no migration runs until it finishes.
#
# THE CREDENTIALS STILL DO NOT TRAVEL. The account in .deploy.local is a full-DDL account
# and stays on this machine; that is what the tunnel is for and none of it changes. But a
# snapshot is a read, and the server already holds an account with SELECT on these tables
# — the one the application itself uses, in the config file it has always had. So the
# server dumps with that, and we send it nothing.
#
# Returns 0 with the snapshot in place, 1 when this server cannot take it and the caller
# should fall back, and does not return at all when a dump ran and produced something
# that cannot be trusted.
#
# NOTE: the caller tolerates a non-zero status from this function, and bash turns errexit
# off for the whole body of a function called that way. Every command below is checked by
# hand. Nothing here may rely on `set -e`.
snapshot_on_server() {
    local tables=$1 name=$2
    local out err errf verdict size sum rc=0

    if [ -z "${DEPLOY_APP_PATH:-}" ]; then
        warn "DEPLOY_APP_PATH is not set in .deploy.local, so the server has nowhere to put a snapshot."
        return 1
    fi

    errf=$(mktemp "${TMPDIR:-/tmp}/migrate-snap-err-XXXXXX") || errf=/dev/null
    out=$(printf '%s\n' "$REMOTE_SNAPSHOT_PROG" \
        | remote "bash -s -- '$SNAPSHOT_DIR_REMOTE' '$REMOTE_APP_CONFIG' '$name' '$tables'" \
            2>"$errf") || rc=$?
    err=$(cat "$errf" 2>/dev/null || true)
    [ "$errf" = /dev/null ] || rm -f "$errf"

    # The program prints one line. Classify on the last one regardless, so that anything
    # a login shell adds ahead of it is shown by the failure rather than mistaken for it.
    verdict=${out##*$'\n'}
    case $verdict in
        'OK '*)
            read -r _ size sum <<<"$verdict"
            ok "snapshot $name ($size) taken on the server, sha256 ${sum:0:16}…"
            return 0
            ;;
        'PRECHECK '*)
            warn "the server cannot dump for itself: ${verdict#PRECHECK }"
            return 1
            ;;
    esac

    fail "the snapshot of [$tables] failed on the server (exit $rc). Nothing was applied.
${out:-(no output)}${err:+
stderr: $err}"
}

# Dump from here, through the connection we already have, and do not return until the
# snapshot exists somewhere that outlives this process. This is the capsule's only path,
# and production's fallback.
snapshot_over_the_wire() {
    local f=$1 tables=$2 name=$3
    local tmp sum_local sum_remote size

    tmp=$(mktemp "${TMPDIR:-/tmp}/migrate-snap-XXXXXX")
    # shellcheck disable=SC2086
    if ! mysqldump --defaults-extra-file="$DEFAULTS_FILE" --single-transaction --quick \
            "$DB_NAME" $(printf '%s' "$tables" | tr ',' ' ') 2>/dev/null | gzip > "$tmp"; then
        rm -f "$tmp"
        fail "could not snapshot [$tables] before $f. Nothing was applied."
    fi
    sum_local=$(sha256sum "$tmp" | cut -d' ' -f1)
    size=$(du -h "$tmp" | cut -f1)

    if [ "$ENV_NAME" != production ]; then
        mkdir -p "$BACKUP_DIR"
        mv "$tmp" "$BACKUP_DIR/$name"
        ok "snapshot $name ($size)"
        return 0
    fi

    remote "mkdir -p '$SNAPSHOT_DIR_REMOTE'" >/dev/null 2>&1 \
        || { rm -f "$tmp"; fail "could not create $SNAPSHOT_DIR_REMOTE on the server. Nothing was applied."; }
    if ! remote "cat > '$SNAPSHOT_DIR_REMOTE/$name'" < "$tmp"; then
        rm -f "$tmp"
        fail "could not upload the snapshot of [$tables] to the server. Nothing was applied."
    fi
    # Verified, not assumed. A truncated upload over a dropped connection is a plausible
    # file of the wrong length, and `cat >` reports nothing about it.
    sum_remote=$(remote "sha256sum '$SNAPSHOT_DIR_REMOTE/$name' 2>/dev/null | cut -d' ' -f1" || true)
    rm -f "$tmp"
    [ "$sum_remote" = "$sum_local" ] || fail "the snapshot on the server does not match what was sent
(local $sum_local, server ${sum_remote:-absent}). Nothing was applied."
    ok "snapshot $name ($size) on the server, sha256 verified"
}

# Take the pre-migration snapshot. Every failure path aborts BEFORE the migration runs,
# which is the whole point: a snapshot that might not be there is not a snapshot.
take_snapshot() {
    local f=$1 tables=$2 name=$3 rc=0

    if [ "$ENV_NAME" = production ]; then
        snapshot_on_server "$tables" "$name" || rc=$?
        if [ "$rc" = 0 ]; then
            return 0
        fi
        warn "dumping [$tables] over the tunnel instead. On a large table that is slow
enough to be worth watching, and the reason it came to that is above."
    fi
    snapshot_over_the_wire "$f" "$tables" "$name"
}

# ------------------------------------------------------------ retention ----

# Two floors and a ceiling.
#
# The floors: a snapshot survives if it is among the last N runs OR younger than
# the day limit, so a quiet month cannot leave you with nothing and a busy
# afternoon of migrations cannot age out yesterday's.
#
# The ceiling overrides both. Without it the run floor has no time limit at all,
# and that is not a corner case: migrations arrive in bursts — fourteen between
# 11 and 23 August 2026, then none for a month — so "the last two runs" routinely
# means "the last two months", and if migration work stops it means forever. A
# snapshot of data we deliberately erased must not have its lifetime decided by
# whether anyone happens to write another migration.
#
# Losing the last snapshot at the ceiling costs less than it looks, because a
# snapshot's value decays on its own: the table keeps changing, so restoring a
# month-old one wholesale would destroy a month of edits. Past a week or so it is
# something you mine for particular rows, not something you restore. And it was
# never the thing that rolls a release back — that is releases/ and
# DEPLOY_KEEP_RELEASES, which a @destructive migration makes deploy.sh refuse
# anyway, precisely because the database cannot follow the code.
#
# Runs are counted per environment. Otherwise a couple of capsule rehearsals
# would push the last production snapshot out of the window, which is the one
# that actually matters.
#
# Deletions are always named. A retention policy that prunes silently reads, a
# year later, as "we never had a backup of that".
# Which snapshots to drop. Pure: names on stdin, names to delete on stdout, no filesystem
# and no server. That is what lets test/Deploy/snapshot-retention-test.sh exercise the
# policy on synthetic input instead of on a real backup directory — and the policy now has
# two callers, local and remote, which is exactly when a duplicated rule starts to rot.
#
# Age comes from the filename's run stamp, not from mtime. The stamp is when the snapshot
# was TAKEN; an mtime is when the file was last written, which a copy, a restore or an
# rsync changes. On the server there is no mtime worth trusting at all.
prunable() {
    local keep_runs=$1 cutoff=$2 max_cutoff=${3:-}
    local input protected name run
    input=$(cat)
    [ -n "$input" ] || return 0

    protected=$(printf '%s\n' "$input" \
        | sed -n 's/^\([0-9]\{8\}-[0-9]\{6\}\)-.*$/\1/p' | sort -ru | head -n "$keep_runs")

    while IFS= read -r name; do
        [ -n "$name" ] || continue
        run=$(printf '%s' "$name" | sed -n 's/^\([0-9]\{8\}-[0-9]\{6\}\)-.*$/\1/p')
        [ -n "$run" ] || continue
        # The ceiling is checked FIRST and answers on its own: past it, neither
        # floor saves a snapshot. An empty max_cutoff disables it.
        if [ -n "$max_cutoff" ] && [[ "$run" < "$max_cutoff" ]]; then
            printf '%s\n' "$name"
            continue
        fi
        # floor 1: among the most recent runs for this environment
        if printf '%s\n' "$protected" | grep -qxF "$run"; then continue; fi
        # floor 2: younger than the day limit
        if [[ ! "$run" < "$cutoff" ]]; then continue; fi
        printf '%s\n' "$name"
    done <<EOF
$input
EOF
}

# The cutoff as a run stamp, so the comparison above is a string compare and needs no
# epoch arithmetic on either side of the ssh connection.
prune_cutoff() {
    date -d "${1} days ago" +%Y%m%d-%H%M%S 2>/dev/null \
        || date -v "-${1}d" +%Y%m%d-%H%M%S   # BSD/macOS
}

# A snapshot's disappearance is always named. A retention policy that prunes silently
# reads, a year later, as "we never had a backup of that".
prune_backups() {
    local keep_runs=${DEPLOY_KEEP_BACKUP_RUNS:-3}
    local keep_days=${DEPLOY_KEEP_BACKUP_DAYS:-7}
    local max_days=${DEPLOY_MAX_BACKUP_DAYS:-30}
    local cutoff max_cutoff doomed name deleted=0
    cutoff=$(prune_cutoff "$keep_days")
    max_cutoff=$(prune_cutoff "$max_days")

    if [ "$ENV_NAME" = production ]; then
        doomed=$(remote "ls -1 '$SNAPSHOT_DIR_REMOTE' 2>/dev/null" 2>/dev/null \
            | grep -E "^[0-9]{8}-[0-9]{6}-$ENV_NAME-.*\.sql\.gz$" \
            | prunable "$keep_runs" "$cutoff" "$max_cutoff" || true)
        while IFS= read -r name; do
            [ -n "$name" ] || continue
            remote "rm -f '$SNAPSHOT_DIR_REMOTE/$name'" >/dev/null 2>&1 \
                && deleted=$((deleted + 1)) && info "pruned $name (server)"
        done <<EOF
$doomed
EOF
    else
        [ -d "$BACKUP_DIR" ] || return 0
        doomed=$(find "$BACKUP_DIR" -maxdepth 1 -type f -name "*-$ENV_NAME-*.sql.gz" -printf '%f\n' 2>/dev/null \
            | prunable "$keep_runs" "$cutoff" "$max_cutoff" || true)
        while IFS= read -r name; do
            [ -n "$name" ] || continue
            rm -f "$BACKUP_DIR/$name" && deleted=$((deleted + 1)) && info "pruned $name"
        done <<EOF
$doomed
EOF
    fi

    if [ "$deleted" -gt 0 ]; then
        dim "$deleted snapshot(s) removed (keeping the last $keep_runs runs and everything
       under $keep_days days, but nothing at all past $max_days days)"
    fi
}

# ------------------------------------------------------------- inventory ----

migration_files() {
    find "$MIGRATION_DIR" -maxdepth 1 -name '*.sql' -type f -printf '%f\n' | sort -V
}

applied_set() { sql "SELECT \`filename\` FROM \`sch_migration\`;" | tr -d '\r'; }

# ================================================================= lint =====
#
# What belongs in database/, checked with no database and no credentials.
#
# `migration_files()` is a glob: ANY .sql at the top of database/ is a migration,
# and validate() runs over every unapplied one BEFORE the phase filter narrows to
# the ones this run will apply. So a file that is not a migration at all — a
# schema recording, a seed, something dropped there to look at — aborts `apply`,
# and therefore aborts the deploy, at the pre-migration step, on the server, with
# the release already built. It fails safe, before the swap, but it fails late
# and for a reason that has nothing to do with the release. database/ci/ exists
# so such files have somewhere to live; -maxdepth 1 does not reach it.
#
# The test is the FILENAME, not the headers. 73 of the 86 migrations — everything
# through db7.7 — carry no headers at all: they predate the convention and were
# backfilled into the ledger, so validate() never sees them and never should.
# What every real migration does have is the name db<major>.<minor>.sql, and that
# is what a stray file fails.
#
# A file that IS named like a migration and carries SOME header is checked in
# full, which catches a new migration whose headers are half written — the case
# that otherwise surfaces as an abort mid-deploy.
if [ "$ACTION" = lint ]; then
    step "database/ holds migrations and nothing else"
    NAMED=0 CHECKED=0 STRAY=0
    while read -r f; do
        [ -n "$f" ] || continue
        case $f in
            db[0-9]*.[0-9]*.sql) NAMED=$((NAMED + 1)) ;;
            *)
                STRAY=$((STRAY + 1))
                warn "$f is not named like a migration (db<major>.<minor>.sql)"
                continue
                ;;
        esac
        # Some header means somebody meant this to be a modern migration, so hold
        # it to the whole convention. None means a historical file; leave it be.
        if grep -q '^-- @' "$MIGRATION_DIR/$f"; then
            validate "$MIGRATION_DIR/$f"
            CHECKED=$((CHECKED + 1))
        fi
    done < <(migration_files)

    if [ "$STRAY" -gt 0 ]; then
        fail "$STRAY file(s) in database/ are not migrations, and tools/deploy.sh will
abort its pre-migration step on the first one it reaches.

Anything that is not a migration belongs in database/ci/ — the schema recording and
the CI seed live there for exactly this reason."
    fi
    ok "$NAMED migration(s) named correctly; $CHECKED with headers fully checked"
    exit 0
fi

# =============================================================== status =====

if [ "$ACTION" = status ] || [ "$ACTION" = plan ]; then
    connect
    ensure_ledger
    APPLIED=$(applied_set)
    PENDING=0
    step "Migrations on $ENV_NAME"
    while read -r f; do
        [ -n "$f" ] || continue
        if printf '%s\n' "$APPLIED" | grep -qxF "$f"; then
            if [ "$ACTION" = plan ]; then continue; fi
            RECORDED=$(sql "SELECT \`sha256\` FROM \`sch_migration\` WHERE \`filename\`='$(escape "$f")';")
            ACTUAL=$(sha256sum "$MIGRATION_DIR/$f" | cut -d' ' -f1)
            if [ "$RECORDED" != "$ACTUAL" ]; then
                printf '   %sCHANGED%s  %s  (applied, but its bytes differ from the ledger)\n' "$C_ERR" "$C_OFF" "$f"
            else
                printf '   %sapplied%s  %s\n' "$C_DIM" "$C_OFF" "$f"
            fi
        else
            PHASE_H=$(header "$MIGRATION_DIR/$f" phase)
            KIND_H=$(header "$MIGRATION_DIR/$f" kind)
            printf '   %sPENDING%s  %s  %s\n' "$C_WARN" "$C_OFF" "$f" \
                "${PHASE_H:+[phase=$PHASE_H kind=$KIND_H]}"
            PENDING=$((PENDING + 1))
        fi
    done < <(migration_files)
    printf '\n'
    if [ "$PENDING" = 0 ]; then ok "nothing pending"; else info "$PENDING pending"; fi
    exit 0
fi

# =============================================================== reseal =====
#
# An applied migration's bytes are frozen, and rightly so — guarantee 7 exists
# because the same filename meaning two different things across environments is
# how a ledger becomes fiction. But the ledger's real claim is about the
# STATEMENTS that ran, and a `-- @header:` line is a comment. As written, a typo
# in a header could never be fixed, and — the case that forced this — a header
# invented later can never be applied to the migration that needs it. `db8.1` is
# exactly that: it is destructive, `@destructive` did not exist when it ran, and
# without the marker tools/deploy.sh cannot refuse the rollback that turned
# 2026-08-17 into an outage.
#
# So: re-record the hash, but only after PROVING nothing executable changed. The
# old bytes are not gone — they are in git, identified by the hash the ledger
# still holds. Find that revision, strip comments and blank lines from both
# versions, and require them to be identical. If the SQL moved by one character
# this refuses, and the answer is a new migration.
if [ "$ACTION" = reseal ]; then
    [ -n "$TARGET_FILE" ] || fail "reseal needs a filename, e.g. ./tools/migrate.sh reseal db8.1.sql"
    BASE=$(basename "$TARGET_FILE")
    case $BASE in *.sql) ;; *) BASE=$BASE.sql ;; esac
    [ -f "$MIGRATION_DIR/$BASE" ] || fail "$BASE does not exist in database/."

    connect
    ensure_ledger

    RECORDED=$(sql "SELECT \`sha256\` FROM \`sch_migration\` WHERE \`filename\`='$(escape "$BASE")';")
    [ -n "$RECORDED" ] || fail "$BASE is not recorded as applied on $ENV_NAME; there is nothing to reseal.
An unapplied migration can simply be edited."
    ACTUAL=$(sha256sum "$MIGRATION_DIR/$BASE" | cut -d' ' -f1)
    if [ "$RECORDED" = "$ACTUAL" ]; then
        ok "$BASE already matches the ledger on $ENV_NAME; nothing to do."
        exit 0
    fi

    # statements only: no comment lines, no blank lines, leading/trailing space off
    strip_comments() { sed 's/[[:space:]]*$//' | grep -vE '^[[:space:]]*(--.*)?$'; }

    step "Looking for the applied bytes of $BASE in git history"
    OLD_REV=''
    for rev in $(git log --format=%H -- "database/$BASE"); do
        if [ "$(git show "$rev:database/$BASE" 2>/dev/null | sha256sum | cut -d' ' -f1)" = "$RECORDED" ]; then
            OLD_REV=$rev
            break
        fi
    done
    [ -n "$OLD_REV" ] || fail "no commit contains a version of database/$BASE hashing to $RECORDED.
Without the applied bytes there is no way to prove only comments changed, so this
refuses. If the file was applied from an uncommitted state, write a new migration."
    ok "applied bytes are ${OLD_REV:0:12}"

    if ! diff -q <(git show "$OLD_REV:database/$BASE" | strip_comments) \
                 <(strip_comments < "$MIGRATION_DIR/$BASE") >/dev/null; then
        printf '\n'
        diff -u <(git show "$OLD_REV:database/$BASE" | strip_comments) \
                <(strip_comments < "$MIGRATION_DIR/$BASE") | head -40
        fail "the SQL changed, not just the comments. Resealing would make the ledger a liar.
Write a new migration instead."
    fi
    ok "statements are byte-identical; only comments differ"

    info "$BASE on $ENV_NAME: ${RECORDED:0:12} -> ${ACTUAL:0:12}"
    if [ "$DRY_RUN" = 1 ]; then
        dim "--dry-run: the ledger was not touched"
        exit 0
    fi
    if [ "$ASSUME_YES" != 1 ]; then
        [ -t 0 ] || fail "not a tty and --yes was not given."
        printf '\n%s? reseal %s on %s [y/N] %s' "$C_WARN" "$BASE" "$ENV_NAME" "$C_OFF"
        read -r answer
        case $answer in [yY]*) ;; *) fail "cancelled." ;; esac
    fi
    sql "UPDATE \`sch_migration\` SET \`sha256\`='$(escape "$ACTUAL")' WHERE \`filename\`='$(escape "$BASE")';"
    ok "resealed on $ENV_NAME"
    exit 0
fi

# ============================================================= backfill =====

if [ "$ACTION" = backfill ]; then
    [ -n "$THROUGH" ] || fail "backfill needs --through=<basename>, e.g. --through=db7.7"
    case $THROUGH in *.sql) ;; *) THROUGH=$THROUGH.sql ;; esac
    [ -f "$MIGRATION_DIR/$THROUGH" ] || fail "$THROUGH does not exist in database/."
    connect
    ensure_ledger
    APPLIED=$(applied_set)
    step "Backfill on $ENV_NAME, through $THROUGH"
    dim "records these as applied WITHOUT running them — they predate the ledger"
    TO_MARK=()
    while read -r f; do
        [ -n "$f" ] || continue
        printf '%s\n' "$APPLIED" | grep -qxF "$f" && continue
        TO_MARK+=("$f")
        [ "$f" = "$THROUGH" ] && break
    done < <(migration_files)
    [ "${#TO_MARK[@]}" -gt 0 ] || { ok "nothing to backfill"; exit 0; }
    for f in "${TO_MARK[@]}"; do info "$f"; done
    printf '\n'
    info "${#TO_MARK[@]} file(s) would be marked applied, unexecuted."
    warn "Everything AFTER $THROUGH stays pending and will be RUN by the next apply."
    if [ "$DRY_RUN" = 1 ]; then dim "--dry-run: nothing written"; exit 0; fi
    if [ "$ASSUME_YES" != 1 ]; then
        printf '\n%s? mark %d file(s) applied on %s [y/N] %s' "$C_WARN" "${#TO_MARK[@]}" "$ENV_NAME" "$C_OFF"
        read -r a; case $a in [yY]*) ;; *) fail "cancelled." ;; esac
    fi
    for f in "${TO_MARK[@]}"; do
        SUM=$(sha256sum "$MIGRATION_DIR/$f" | cut -d' ' -f1)
        PHASE_H=$(header "$MIGRATION_DIR/$f" phase); KIND_H=$(header "$MIGRATION_DIR/$f" kind)
        sql "INSERT INTO \`sch_migration\`
             (\`filename\`,\`sha256\`,\`phase\`,\`kind\`,\`applied_on\`,\`applied_by\`,\`note\`)
             VALUES ('$(escape "$f")','$SUM','${PHASE_H:-post}','${KIND_H:-dml}',
                     UTC_TIMESTAMP(),'backfill','predates the ledger; recorded, not executed');"
    done
    ok "${#TO_MARK[@]} recorded"
    exit 0
fi

# ================================================================ apply =====

connect
ensure_ledger
APPLIED=$(applied_set)

TODO=()
while read -r f; do
    [ -n "$f" ] || continue
    if printf '%s\n' "$APPLIED" | grep -qxF "$f"; then
        RECORDED=$(sql "SELECT \`sha256\` FROM \`sch_migration\` WHERE \`filename\`='$(escape "$f")';")
        ACTUAL=$(sha256sum "$MIGRATION_DIR/$f" | cut -d' ' -f1)
        [ "$RECORDED" = "$ACTUAL" ] || fail "$f was applied on $ENV_NAME but its bytes have changed since.
An applied migration is history and editing it makes the ledger a liar — the same
filename now means two different things across environments. Write a new migration."
        continue
    fi
    validate "$MIGRATION_DIR/$f"
    [ "$(header "$MIGRATION_DIR/$f" phase)" = "$PHASE" ] || continue
    TODO+=("$f")
done < <(migration_files)

if [ "${#TODO[@]}" -eq 0 ]; then
    dim "no pending $PHASE-deploy migrations on $ENV_NAME"
    # Retention still runs. Until this call existed, pruning was reachable only by
    # APPLYING a migration — the exit below skipped it — so the ceiling could not
    # expire anything during exactly the quiet stretch it exists for. Every deploy
    # calls this twice, pre and post, and both usually land here.
    prune_backups
    exit 0
fi

step "${#TODO[@]} pending $PHASE-deploy migration(s) on $ENV_NAME"
for f in "${TODO[@]}"; do
    info "$f  [kind=$(header "$MIGRATION_DIR/$f" kind)] tables: $(header "$MIGRATION_DIR/$f" tables)"
done

if [ "$DRY_RUN" = 1 ]; then
    printf '\n'
    dim "--dry-run: nothing executed. Rehearse for real against the capsule:"
    dim "  ./tools/migrate.sh apply --phase=$PHASE --env=capsule"
    exit 0
fi

if [ "$ASSUME_YES" != 1 ]; then
    printf '\n%s? apply %d migration(s) to %s [y/N] %s' "$C_WARN" "${#TODO[@]}" "$ENV_NAME" "$C_OFF"
    read -r a; case $a in [yY]*) ;; *) fail "cancelled." ;; esac
fi

# One timestamp for the whole run, not one per file. It is what makes a "deploy"
# a groupable thing in data/deploy/backups/ — with per-file stamps the two
# snapshots of a single apply differed by seconds and nothing could tell which
# run they belonged to.
RUN_TS=$(date +%Y%m%d-%H%M%S)

for f in "${TODO[@]}"; do
    FILE=$MIGRATION_DIR/$f
    KIND=$(header "$FILE" kind)
    TABLES=$(header "$FILE" tables)
    VERIFY=$(header "$FILE" verify)
    SUM=$(sha256sum "$FILE" | cut -d' ' -f1)

    step "$f"

    # ---- snapshot ----------------------------------------------------------
    if [ "$TABLES" != none ]; then
        take_snapshot "$f" "$TABLES" "$RUN_TS-$ENV_NAME-${f%.sql}.sql.gz"
    else
        dim "declares '@tables: none' — no snapshot taken"
    fi

    # ---- run ---------------------------------------------------------------
    LEDGER_INSERT="INSERT INTO \`sch_migration\`
        (\`filename\`,\`sha256\`,\`phase\`,\`kind\`,\`applied_on\`,\`applied_by\`)
        VALUES ('$(escape "$f")','$SUM','$PHASE','$KIND',UTC_TIMESTAMP(),'migrate.sh');"

    OUT_FILE=$(mktemp "${TMPDIR:-/tmp}/migrate-out-XXXXXX")
    START_MS=$(date +%s%3N)
    if [ "$KIND" = dml ]; then
        # The ledger row commits WITH the change. Neither can exist without the
        # other; a failure part-way rolls back both.
        {
            printf 'START TRANSACTION;\n'
            cat "$FILE"
            printf '\n%s\nCOMMIT;\n' "$LEDGER_INSERT"
        } | sqlv > "$OUT_FILE" 2>&1 || {
            printf '%s\n' "$C_ERR"; sed 's/^/      /' "$OUT_FILE" >&2; printf '%s\n' "$C_OFF"
            rm -f "$OUT_FILE"
            fail "$f failed. The transaction rolled back: nothing was changed and the ledger
was not written. The snapshot above is untouched and unneeded."
        }
    else
        sqlv < "$FILE" > "$OUT_FILE" 2>&1 || {
            printf '%s\n' "$C_ERR"; sed 's/^/      /' "$OUT_FILE" >&2; printf '%s\n' "$C_OFF"
            rm -f "$OUT_FILE"
            fail "$f failed. It is DDL, so MariaDB committed whatever ran before the error and
NOTHING WAS ROLLED BACK. The ledger was not written, so a re-run will attempt the whole
file again — which is safe only if its @idempotent claim is true. Read the error, then
the snapshot in data/deploy/backups/."
        }
        sql "$LEDGER_INSERT"
    fi
    END_MS=$(date +%s%3N)

    sed 's/^/      /' "$OUT_FILE"
    # The migration's OWN statements only. For dml the wrapper contributes three
    # more "Query OK" lines — START TRANSACTION first, then the ledger INSERT and
    # COMMIT last — and leaving them in makes the recorded counts misread: a
    # trailing 1 from the ledger row looks like the migration changed something.
    mapfile -t COUNT_ARR < <(grep -oE 'Query OK, [0-9]+ rows? affected' "$OUT_FILE" | grep -oE '[0-9]+')
    if [ "$KIND" = dml ] && [ "${#COUNT_ARR[@]}" -ge 3 ]; then
        COUNT_ARR=("${COUNT_ARR[@]:1:${#COUNT_ARR[@]}-3}")
    fi
    COUNTS=$(printf '%s\n' ${COUNT_ARR[@]+"${COUNT_ARR[@]}"} | paste -sd, -)
    rm -f "$OUT_FILE"

    sql "UPDATE \`sch_migration\` SET \`duration_ms\`=$((END_MS - START_MS)),
         \`row_counts\`='$(escape "$COUNTS")' WHERE \`filename\`='$(escape "$f")';"
    ok "applied in $((END_MS - START_MS)) ms; rows affected per statement: ${COUNTS:-none}"

    # ---- verify ------------------------------------------------------------
    if [ -n "$VERIFY" ]; then
        LEFT=$(sql "$VERIFY" | grep -c . || true)
        if [ "$LEFT" != 0 ]; then
            printf '%s' "$C_ERR"; sql "$VERIFY" | sed 's/^/      /'; printf '%s' "$C_OFF"
            fail "$f ran, but its @verify query still returns $LEFT row(s), and it must return none.
The change is applied and recorded — this is not a rollback situation, it is a
'the migration did not achieve what it claimed' situation. Read the rows above."
        fi
        ok "@verify returns no rows"
    fi
done

printf '\n'
ok "all $PHASE-deploy migrations applied on $ENV_NAME"

prune_backups
