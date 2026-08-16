#!/usr/bin/env bash
# Apply database/*.sql migrations against a tracked ledger.
#
#   ./tools/migrate.sh status                    what is applied, what is pending
#   ./tools/migrate.sh plan                      the pending files, with their headers
#   ./tools/migrate.sh apply --phase=pre         run the pending pre-deploy migrations
#   ./tools/migrate.sh apply --phase=post        …and the post-deploy ones
#   ./tools/migrate.sh backfill --through=db7.7  mark historical files applied, run nothing
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
#   3. The tables named in `@tables` are dumped to data/deploy/backups/ BEFORE
#      anything runs. "Can we undo it" becomes a file rather than a hope.
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
case $ACTION in status|plan|apply|backfill) shift ;; *) ACTION=status ;; esac

ENV_NAME=production PHASE='' DRY_RUN=0 ASSUME_YES=0 THROUGH=''
while [ $# -gt 0 ]; do
    case $1 in
        --env=*)     ENV_NAME=${1#*=} ;;
        --phase=*)   PHASE=${1#*=} ;;
        --through=*) THROUGH=${1#*=} ;;
        --dry-run)   DRY_RUN=1 ;;
        -y|--yes)    ASSUME_YES=1 ;;
        -h|--help)   sed -n '2,/^set -euo/p' "$0" | sed 's/^#\{1,\} \{0,1\}//; $d'; exit 0 ;;
        *)           fail "unknown option: $1" ;;
    esac
    shift
done
case $ENV_NAME in production|capsule) ;; *) fail "--env must be production or capsule" ;; esac
if [ "$ACTION" = apply ]; then
    case $PHASE in pre|post) ;; *) fail "apply needs --phase=pre or --phase=post" ;; esac
fi

# ---------------------------------------------------------------- config ----

CONFIG=$REPO_ROOT/.deploy.local
[ -f "$CONFIG" ] || fail ".deploy.local is missing. Run ./config.sh, then fill in the TODOs."
# shellcheck disable=SC1090
. "$CONFIG"

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
}

# ------------------------------------------------------------- inventory ----

migration_files() {
    find "$MIGRATION_DIR" -maxdepth 1 -name '*.sql' -type f -printf '%f\n' | sort -V
}

applied_set() { sql "SELECT \`filename\` FROM \`sch_migration\`;" | tr -d '\r'; }

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

for f in "${TODO[@]}"; do
    FILE=$MIGRATION_DIR/$f
    KIND=$(header "$FILE" kind)
    TABLES=$(header "$FILE" tables)
    VERIFY=$(header "$FILE" verify)
    SUM=$(sha256sum "$FILE" | cut -d' ' -f1)

    step "$f"

    # ---- snapshot ----------------------------------------------------------
    if [ "$TABLES" != none ]; then
        mkdir -p "$BACKUP_DIR"
        SNAP=$BACKUP_DIR/$(date +%Y%m%d-%H%M%S)-$ENV_NAME-${f%.sql}.sql.gz
        # shellcheck disable=SC2086
        mysqldump --defaults-extra-file="$DEFAULTS_FILE" --single-transaction --quick \
            "$DB_NAME" $(printf '%s' "$TABLES" | tr ',' ' ') 2>/dev/null | gzip > "$SNAP" \
            || fail "could not snapshot [$TABLES] before $f. Nothing was applied."
        ok "snapshot $(basename "$SNAP") ($(du -h "$SNAP" | cut -f1))"
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
