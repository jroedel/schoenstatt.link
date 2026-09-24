#!/usr/bin/env bash
#
# database/ holds migrations and nothing else.
#
# WHAT THIS PREVENTS, and it is not hypothetical. `migration_files()` in
# tools/migrate.sh is a glob: any .sql at the top of database/ is a migration. The apply
# loop runs validate() over every unapplied file BEFORE the phase filter narrows to the
# ones the run will actually apply. So a file that is not a migration at all aborts
# `migrate.sh apply --phase=pre`, which tools/deploy.sh calls — and the deploy stops on
# the server, after the release is built, for a reason with nothing to do with the
# release. It fails safe, before the symlink swap. It fails late.
#
# That is what database/base-schema.sql and database/ci-seed.sql did between 2026-09-22
# and 2026-09-23: two CI artefacts in the migration directory, invisible to every check
# the repository had, and invisible to a --dry-run because a dry run exits before
# migrations. They live in database/ci/ now, which -maxdepth 1 does not reach.
#
# Why this is a deploy test and not a unit test: it is about what a deploy will do, it is
# plain bash, and test/Deploy/*-test.sh is globbed by both tools/ci-local.sh and ci.yml's
# deploy-machinery job. It needs no database and no credentials, which is the whole
# reason `migrate.sh lint` exists as a separate action.
set -uo pipefail

cd "$(dirname "$0")/../.."

FAILED=0
check() {
    if [ "$1" = 0 ]; then
        printf '  ok    %s\n' "$2"
    else
        printf '  FAIL  %s\n' "$2"
        FAILED=$((FAILED + 1))
    fi
}

# 1. The inventory as it stands.
OUT=$(./tools/migrate.sh lint 2>&1)
STATUS=$?
check $STATUS "database/ holds only migrations"
[ $STATUS = 0 ] || printf '%s\n' "$OUT" | sed 's/^/        /'

# 2. The check can actually fail. A guard that has never been seen to reject anything is
#    a guard nobody should trust — this is the same reasoning as an @verify selecting what
#    is still wrong rather than what is right.
STRAY=database/zzz-not-a-migration.sql
cleanup() { rm -f "$STRAY"; }
trap cleanup EXIT
printf -- '-- a file that is not a migration\nSELECT 1;\n' > "$STRAY"
if ./tools/migrate.sh lint >/dev/null 2>&1; then
    check 1 "a stray .sql in database/ is rejected"
else
    check 0 "a stray .sql in database/ is rejected"
fi
cleanup
trap - EXIT

# 3. And it passes again once the stray is gone, so failure 2 was the stray and not
#    something ambient.
./tools/migrate.sh lint >/dev/null 2>&1
check $? "and passes again once it is removed"

# 4. An @tables header that is not a list of table names. This one is not about tidiness:
#    on production the list is interpolated into a command built here and run by a shell
#    on the server, so a quote or a semicolon in this header is a repository file reaching
#    a remote shell. lint is where it must be caught — before a deploy, with no database
#    and no credentials in play.
BAD=database/db99.9.sql
cleanup() { rm -f "$BAD"; }
trap cleanup EXIT
cat > "$BAD" <<'SQL'
-- @phase: post
-- @kind: dml
-- @tables: sch_changes; rm -rf ~
SELECT 1;
SQL
OUT=$(./tools/migrate.sh lint 2>&1)
if [ $? = 0 ]; then
    check 1 "an @tables header that is not table names is rejected"
else
    case $OUT in
        *"not a list of table names"*) check 0 "an @tables header that is not table names is rejected" ;;
        *) check 1 "an @tables header that is not table names is rejected (rejected for the wrong reason)"
           printf '%s\n' "$OUT" | sed 's/^/        /' ;;
    esac
fi
cleanup
trap - EXIT

# 5. …and a well-formed one with spaces after the commas, as several real migrations
#    write it, is still accepted. A guard that rejects the valid case is worse than none.
cleanup() { rm -f "$BAD"; }
trap cleanup EXIT
cat > "$BAD" <<'SQL'
-- @phase: post
-- @kind: dml
-- @tables: sch_changes, lib_checkouts
SELECT 1;
SQL
./tools/migrate.sh lint >/dev/null 2>&1
check $? "a comma-and-space @tables list is accepted"
cleanup
trap - EXIT

if [ "$FAILED" = 0 ]; then
    echo "migration inventory: all checks passed"
    exit 0
fi
echo "migration inventory: $FAILED check(s) failed" >&2
exit 1
