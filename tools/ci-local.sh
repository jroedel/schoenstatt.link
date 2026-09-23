#!/usr/bin/env bash
# Run everything .github/workflows/ci.yml runs, locally, in the capsule.
#
# WHY THIS EXISTS: it is the stricter check, and it stayed useful for a reason nobody
# planned. The account's 2,000 GitHub Actions minutes/month allowance was exhausted on
# 2026-08-14 and every job then failed in ~2 seconds with no runner assigned and no steps
# executed — which looks alarming and is not about your code. That lasted until the
# 2026-09-01 reset, and the first real run afterwards immediately found two breakages
# that had accumulated in the gap. So this script is not a stand-in for CI being down;
# it is what should be run before pushing, whether or not CI is healthy.
#
# It mirrors ci.yml's seven jobs in the same order, plus the three things CI CANNOT run
# because they need a live application: the smoke suite, the fuzz harness, and
# tools/smoke-prod.sh itself. That makes a green run here a STRICTER check than a green
# run on GitHub, not a weaker one — worth saying out loud, because the reflex is to
# treat a local pass as second best.
#
# Quantified 2026-09-08: CI's integration job runs 3,085 assertions with 195 skipped;
# the same suite here runs 7,214 with 14 skipped. CI has no database and cannot get one
# (see .github/workflows/ci.yml's header), so roughly 57% of the assertions only ever
# execute here.
#
# The last of those was added on 2026-08-19 and is a different kind of coverage from the
# other two. smoke-prod.sh is the bash script the DEPLOY runs against production as its
# final step, and nothing had ever executed it except a deploy — so its own bugs could
# only be discovered by shipping them, which is how a run died on `77\n838 / 60: syntax
# error` after an otherwise successful deploy. Pointing it at the capsule exercises the
# script, not just the site.
#
#   ./tools/ci-local.sh              everything: the seven CI jobs, smoke, fuzz, smoke-prod
#   ./tools/ci-local.sh --ci         only the seven CI jobs (skips the ~4min smoke)
#   ./tools/ci-local.sh qa           phpcs + phpstan + unit — the fast pre-push triad
#   ./tools/ci-local.sh phpstan unit one or more named stages, run in canonical order
#   ./tools/ci-local.sh --last       the full output of the last run, whatever it printed
#   ./tools/ci-local.sh --last FAIL  the last run's output, filtered
#   ./tools/ci-local.sh --list       the stage names
#
# WHY THE LOG AND THE SELECTOR EXIST: measured across 36 local sessions, 319 of 462
# invocations of expensive verification were piped through tail/head/grep and the rest of
# the output discarded, and 283 were re-run within five calls with ONLY the filter
# changed. A median run is 122s and a p90 is 431s, so guessing `tail -20` wrong used to
# cost minutes. Every run now writes its complete output to .ci-local/last.log; `--last`
# re-slices it for free. Nothing is ever printed and then lost.
#
# WHY THE CACHE EXISTS, AND WHY YOU CAN TRUST IT: 29% of back-to-back runs happened with
# zero file edits in between — 106 minutes re-verifying an unchanged tree. A stage that
# passed is therefore skipped while the tree it passed against is unchanged. The key is
# the part that has to be right: `sha256(git diff HEAD)` is the obvious choice and is
# WRONG here, because it omits untracked files entirely — a new class plus its factory
# hashes the same as a clean tree, which was reproduced on master before this was written.
# The key below is instead a content hash of every tracked AND untracked file, plus the
# installed-package set — because vendor/ is gitignored build output that survives a
# branch switch — and the merged config cache, because adding a service factory changes
# what integration and smoke see without touching the tree. It costs ~130ms over 1,911
# files. `--no-cache` forces a full run; a deploy always passes it.
set -uo pipefail
cd "$(dirname "$0")/.."

STATE_DIR=.ci-local
LOG="$STATE_DIR/last.log"
CACHE_DIR="$STATE_DIR/cache"

# Canonical order. A stage selector never reorders them: PHPStan before the suites is not
# an accident, and `ci-local.sh unit phpstan` should still be the cheaper one first.
ALL_STAGES="lint composer phpstan phpcs deploy schema unit integration smoke fuzz smoke-prod"
CI_STAGES="lint composer phpstan phpcs deploy unit integration"
QA_STAGES="phpcs phpstan unit"
# Stages that need `docker compose exec app`. The other two are plain bash on the host.
CAPSULE_STAGES="composer phpstan phpcs deploy schema unit integration smoke fuzz smoke-prod"

USE_CACHE=1
SELECTED=""

usage() {
    sed -n '/^#   \.\/tools\/ci-local\.sh /,/^#$/p' "$0" | sed 's/^#\{0,1\} \{0,2\}//'
}

list_stages() {
    printf 'stages (canonical order): %s\n' "$ALL_STAGES"
    printf 'groups: --ci = %s\n' "$CI_STAGES"
    printf '        qa   = %s\n' "$QA_STAGES"
    printf '        all  = every stage (the default)\n'
}

# --last: re-slice the previous run instead of paying for it again. This is the whole
# reason the log exists, so it must work even when the last run failed or was interrupted.
show_last() {
    if [ ! -s "$LOG" ]; then
        printf 'No previous run recorded in %s.\n' "$LOG" >&2
        return 1
    fi
    if [ -z "${1:-}" ]; then
        cat "$LOG"
        return 0
    fi
    grep -E -- "$1" "$LOG" || {
        printf '\nNo line in %s matches %s. `--last` with no pattern prints all of it.\n' "$LOG" "$1" >&2
        return 1
    }
}

while [ $# -gt 0 ]; do
    case $1 in
        --last)     shift; show_last "${1:-}"; exit $? ;;
        --list)     list_stages; exit 0 ;;
        --no-cache) USE_CACHE=0 ;;
        -h|--help)  usage; exit 0 ;;
        --ci|ci)    SELECTED="$SELECTED $CI_STAGES" ;;
        qa)         SELECTED="$SELECTED $QA_STAGES" ;;
        all)        SELECTED="$SELECTED $ALL_STAGES" ;;
        -*)         printf 'Unknown option: %s\n\n' "$1" >&2; usage >&2; exit 2 ;;
        *)
            case " $ALL_STAGES " in
                *" $1 "*) SELECTED="$SELECTED $1" ;;
                *) printf 'Unknown stage: %s\n\n' "$1" >&2; list_stages >&2; exit 2 ;;
            esac
            ;;
    esac
    shift
done
[ -z "$SELECTED" ] && SELECTED="$ALL_STAGES"

# Canonical order, deduplicated — `ci-local.sh qa phpstan` asks for phpstan once.
STAGES=""
for candidate in $ALL_STAGES; do
    case " $SELECTED " in *" $candidate "*) STAGES="$STAGES $candidate" ;; esac
done

FAILURES=0
WARNINGS=0
CACHED=0
step() { printf '\n\033[1m=== %s\033[0m\n' "$1"; note "=== $1"; }
ok()   { printf '  \033[32mok\033[0m    %s\n' "$1"; note "  ok    $1"; }
bad()  { printf '  \033[31mFAIL\033[0m  %s\n' "$1"; note "  FAIL  $1"; FAILURES=$((FAILURES + 1)); }
# A check that could not run, as distinct from one that ran and failed. Kept out of
# FAILURES on purpose — the exit status must mean "something is wrong with the code", or
# nobody will trust it — but counted and reprinted at the end so it cannot pass unnoticed.
warn() { printf '  \033[33mSKIP\033[0m  %s\n' "$1"; note "  SKIP  $1"; WARNINGS=$((WARNINGS + 1)); }

mkdir -p "$CACHE_DIR"
: > "$LOG"          # one run per log; --last means "the last run", not "every run ever"
note()   { printf '%s\n' "$*" >> "$LOG"; }
record() { printf '\n----- %s -----\n%s\n' "$1" "$2" >> "$LOG"; }

in_capsule() { docker compose exec -T app "$@"; }

# Deprecation notices from laminas-cache under PHP 8.5 flood every PHP run here and are
# tracked separately (docs/DEPLOY.md). Filtering them keeps a real failure visible.
quiet() { grep -Ev '^Deprecated:|^$'; }

# Capture-then-match, never `cmd | grep -q`. With `set -o pipefail`, grep -q exits on the
# FIRST match and SIGPIPEs the producer if it is still writing, so the pipeline reports
# failure on a check that PASSED. That bit this script on `composer audit --locked`, whose
# "No advisories" line prints before its abandoned-packages table. Whether it fires
# depends on output timing, which is the worst kind of flake: it reads as a real finding.
says() {
    case "$1" in *"$2"*) return 0 ;; *) return 1 ;; esac
}

# --- the cache key ----------------------------------------------------------------
#
# Everything a stage reads, hashed. See the header for the two blind spots this exists to
# avoid. Deliberately ONE key for all stages rather than per-stage input sets: a stage's
# real inputs are hard to enumerate (phpcs reads a path list, integration reads the merged
# config, smoke reads the running application), and a key that is too broad only costs a
# re-run, while a key that is too narrow reports a green that was never earned.
tree_key() {
    {
        git rev-parse HEAD
        git ls-files -co --exclude-standard -z | xargs -0 sha256sum 2>/dev/null
        # vendor/ is gitignored build output and survives a branch switch: the tree can be
        # unchanged while the installed packages are a different branch's.
        sha256sum vendor/composer/installed.json 2>/dev/null
        # data/config/ is the merged config cache. A new service factory passes integration
        # and fails smoke until `bin/console cache:clear-config` runs — invisible in a diff.
        find data/config -type f -printf '%p %s %T@\n' 2>/dev/null | sort
    } | sha256sum | cut -d' ' -f1
}

KEY=$(tree_key)
note "ci-local $(date -Iseconds)  stages:$STAGES  key:$KEY  cache:$USE_CACHE"

cached()     { [ "$USE_CACHE" -eq 1 ] && [ -f "$CACHE_DIR/$1" ] && [ "$(cat "$CACHE_DIR/$1")" = "$KEY" ]; }
# Written even under --no-cache: that flag means "do not trust the cache", not "do not
# update it". A deploy's full run should leave the next run able to skip what it proved.
mark_green() { printf '%s\n' "$KEY" > "$CACHE_DIR/$1"; }

# suite <testsuite> <lines-of-tail> — run it ONCE, show the summary, judge the exit code.
#
# The first version of this ran each suite twice: once piped to `tail` for the summary,
# once to /dev/null for the status, because a pipeline's exit code is the last command's.
# For `smoke` that is two 4½-minute runs back to back against the live app, doubling both
# wall time and the load the capsule's ceilings exist to bound — and it pushed the full
# run past the 600s tool timeout. Capture once into a file, then read both from it.
# $3, when given, is passed to php ahead of phpunit — mirror ci.yml exactly.
suite() {
    local name=$1 lines=$2 phpflag=${3:-} log status
    log=$(mktemp)
    in_capsule php ${phpflag} tools/phpunit.phar --testsuite "$name" > "$log" 2>&1
    status=$?
    record "phpunit --testsuite $name (exit $status)" "$(cat "$log")"
    quiet < "$log" | tail -"$lines"
    [ "$status" -eq 0 ] && ok "$name" || bad "$name (phpunit exited $status; ./tools/ci-local.sh --last)"
    rm -f "$log"
}

# --- stages -----------------------------------------------------------------------

stage_lint() {
    # --- ci.yml job 1: PHP 8.5 syntax lint ---------------------------------
    # The find expression is copied from the workflow verbatim. If ci.yml's changes, change
    # it here in the same commit — a lint that covers less than CI's is worse than none.
    step "PHP 8.5 syntax lint  (ci.yml: lint)"
    LINT_FAILED=0
    LINT_OUT=""
    while IFS= read -r -d '' f; do
        php -l "$f" > /dev/null 2>&1 || { echo "    $f"; LINT_OUT="$LINT_OUT$f"$'\n'; LINT_FAILED=1; }
    done < <(find module config public/index.php test -name '*.php' -print0)
    record "php -l (failures only)" "$LINT_OUT"
    [ "$LINT_FAILED" -eq 0 ] && ok "every .php file parses" || bad "syntax errors above"
}

stage_composer() {
    # --- ci.yml job 2: composer install --no-dev (deploy rehearsal) --------
    # --dry-run, unlike CI: the capsule's vendor/ is the working tree everything else in this
    # script needs, and a real --no-dev install would strip phpunit out from under the suites
    # below. So this catches an unresolvable or stale lock, NOT a missing production package.
    step "composer --no-dev deploy rehearsal  (ci.yml: composer-install)"
    OUT=$(in_capsule php composer.phar validate --no-check-publish 2>&1)
    record "composer validate" "$OUT"
    if says "$OUT" 'is valid'; then
        ok "composer.json and lock agree"
    else
        bad "composer validate"
    fi
    if in_capsule php composer.phar install --no-dev --no-interaction --no-progress --dry-run >/dev/null 2>&1; then
        ok "installs from the lock, production style (--dry-run)"
    else
        bad "composer install --no-dev could not resolve"
    fi

    # The third question, which neither check above can answer: is the vendor/ that
    # everything below actually runs against the vendor/ this lock describes? `validate`
    # compares composer.json with the lock, and a --dry-run exits 0 while merely REPORTING
    # the operations it would perform — so a drifted tree passes both in silence.
    #
    # WHY: on 2026-09-21 the lock said symfony/mime v7.4.19 and the capsule held v7.4.15.
    # Both checks were green. Every suite below, five recordings and a release's whole
    # verification ran against a package set that was not the one the release shipped, and
    # the component in question sat directly under the mail path that release rewrote.
    # Nothing was wrong in the end, which is the point: the gate could not have told us.
    #
    # With dev packages, unlike the rehearsal above: --no-dev must always report removing
    # phpunit and phpcs from a development tree, so it can never assert an empty plan.
    OUT=$(in_capsule php composer.phar install --no-interaction --no-progress --dry-run 2>&1)
    record "composer install --dry-run (vendor vs lock)" "$OUT"
    if says "$OUT" 'Nothing to install, update or remove'; then
        ok "vendor/ holds exactly what the lock names"
    else
        bad "vendor/ has drifted from composer.lock — composer install (./tools/ci-local.sh --last)"
    fi
    # Classify on what a REAL finding looks like, not on what a known network error looks
    # like. Matching error strings is a losing game — this branch used to test only for
    # 'Could not resolve host', so on 2026-08-16 a transient failure to fetch the advisory
    # database (a different message: the file "could not be downloaded") was reported as
    # FAIL, and it blocked a deploy while reading, to every human, as "a vulnerability was
    # found in your dependencies". Nothing was wrong with the lock; the same run passed
    # minutes later.
    #
    # An advisory finding has a fixed shape and so does a clean result. Anything else is
    # UNKNOWN, which is neither ok nor a failure — so retry once, then say unknown and show
    # the output, because an unreadable database is a fact about the network and a reader
    # who cannot see the text cannot tell the two apart.
    audit_verdict() {
        case $1 in
            *'No security vulnerability advisories found'*) echo clean ;;
            *'security vulnerability advisor'*)             echo found ;;
            *)                                              echo unknown ;;
        esac
    }
    OUT=$(in_capsule php composer.phar audit --locked 2>&1)
    VERDICT=$(audit_verdict "$OUT")
    if [ "$VERDICT" = unknown ]; then
        sleep 3
        OUT=$(in_capsule php composer.phar audit --locked 2>&1)
        VERDICT=$(audit_verdict "$OUT")
    fi
    record "composer audit --locked ($VERDICT)" "$OUT"
    case $VERDICT in
        clean) ok "no advisories against locked versions" ;;
        found) bad "composer audit --locked — advisories against locked versions" ;;
        *)
            warn "advisories NOT checked — the advisory database could not be read (twice)"
            printf '%s\n' "$OUT" | sed 's/^/        /'
            printf '        retry, or run `php composer.phar audit --locked` on the host\n'
            ;;
    esac
    # The autoload sanity check, verbatim from ci.yml: five classes spanning the Symfony side
    # and every module, so a PSR-4 break fails loudly. `App\Kernel` was
    # `Laminas\Mvc\Application` until that package left.
    OUT=$(in_capsule php -r '
        require "vendor/autoload.php";
        foreach ([
            "App\\Kernel",
            "Application\\Module",
            "SionModel\\Db\\Model\\SionTable",
            "JUser\\Service\\LoginTokenService",
            "JTranslate\\Model\\TranslationsTable",
        ] as $class) {
            if (! class_exists($class)) { fwrite(STDERR, "NOT AUTOLOADABLE: $class\n"); exit(1); }
        }
        echo "autoload ok";' 2>&1)
    record "autoload sanity" "$OUT"
    if says "$OUT" 'autoload ok'; then
        ok "autoload sanity across the app and every module"
    else
        bad "autoload sanity — $OUT"
    fi
}

stage_phpstan() {
    # --- ci.yml job 3: PHPStan level 0 -------------------------------------
    step "PHPStan level 0, no new errors  (ci.yml: static-analysis)"
    OUT=$(in_capsule php -d memory_limit=1G tools/phpstan.phar analyse --no-progress 2>&1)
    record "phpstan analyse" "$OUT"
    if says "$OUT" 'No errors'; then
        ok "no errors against phpstan-baseline.neon"
    else
        quiet <<< "$OUT" | tail -20
        bad "PHPStan (./tools/ci-local.sh --last for all of it)"
    fi
}

stage_phpcs() {
    # --- ci.yml job 6: PSR-12 on the paths that must stay clean ------------
    # NOT `composer cs-check`, whose scope reports 425 errors and always will —
    # see tools/phpcs-clean-paths.txt for why "clean" has to be an explicit list,
    # and note that this step and the ci.yml job read that same file so the two
    # cannot drift apart.
    step "PSR-12 on the clean paths  (ci.yml: coding-standard)"
    mapfile -t CS_PATHS < <(sed 's/#.*//' tools/phpcs-clean-paths.txt | grep -v '^[[:space:]]*$')
    OUT=$(in_capsule php vendor/bin/phpcs -q --report=summary "${CS_PATHS[@]}" 2>&1)
    CS_STATUS=$?
    record "phpcs (exit $CS_STATUS)" "$OUT"
    if [ "$CS_STATUS" -eq 0 ]; then
        ok "${#CS_PATHS[@]} paths clean"
    else
        quiet <<< "$OUT" | tail -20
        bad "phpcs on the clean paths (exit $CS_STATUS)"
    fi
}

stage_deploy() {
    # --- ci.yml job 7: the deploy's own machinery --------------------------
    # Plain bash, no server, ~10s each. They live here because tools/deploy.sh is the
    # least-tested code that can do the most damage. The glob is deliberate — a new
    # test/Deploy/*-test.sh is picked up without editing this file, which is the
    # difference between a check that gets written and one that gets written and then
    # forgotten outside the runner.
    #
    # In ci.yml since 2026-09-08, so this is no longer the only place they run. One
    # difference remains and it is in this runner's favour: opcache-swap-test.sh needs
    # warm PHP workers to reproduce the symlink-swap hazard, so it does the real work
    # here against the capsule and reports SKIPPED on a bare runner.
    step "Deploy machinery  (ci.yml: deploy-machinery)"
    for deploy_check in test/Deploy/*-test.sh; do
        rsh_log=$(mktemp)
        if bash "$deploy_check" > "$rsh_log" 2>&1; then
            record "$(basename "$deploy_check") (ok)" "$(cat "$rsh_log")"
            ok "$(tail -1 "$rsh_log")"
        else
            record "$(basename "$deploy_check") (FAILED)" "$(cat "$rsh_log")"
            sed 's/^/    /' "$rsh_log"
            bad "$(basename "$deploy_check") (./tools/ci-local.sh --last)"
        fi
        rm -f "$rsh_log"
    done
}

stage_schema() {
    # --- NOT in ci.yml: the schema recording, which ci.yml is the point of ----
    # database/ci/base-schema.sql is what lets anything build this application's database
    # from the repository. Two questions, and the second is the one worth the seconds:
    #
    #   1. Is the recording current? `--check` re-dumps the capsule and diffs.
    #   2. Does it actually WORK? A file that is faithful to the capsule and does not
    #      load is worse than no file, because it fails in CI rather than here. So the
    #      recording is loaded into a scratch database and re-dumped: a schema built FROM
    #      the file must produce the file, byte for byte.
    #
    # The scratch database is dropped either way. It needs root, because the application's
    # user has no grants outside its own schema — which is correct and stays that way.
    step "Schema recording  (NOT in ci.yml — needs the capsule)"

    OUT=$(./tools/schema-dump.sh --check 2>&1)
    if [ $? -eq 0 ]; then
        record "schema-dump --check" "$OUT"
        ok "database/ci/base-schema.sql matches the capsule"
    else
        record "schema-dump --check (FAILED)" "$OUT"
        printf '%s\n' "$OUT" | sed 's/^/    /'
        bad "database/ci/base-schema.sql is stale — ./tools/schema-dump.sh, then read the diff"
        return
    fi

    PROBE=$(docker compose exec -T db mariadb -uroot -proot \
        -e "DROP DATABASE IF EXISTS schema_probe; CREATE DATABASE schema_probe CHARACTER SET utf8mb4;" 2>&1)
    if [ $? -ne 0 ]; then
        record "schema round-trip (could not create scratch db)" "$PROBE"
        bad "schema round-trip — could not create the scratch database"
        return
    fi

    OUT=$(docker compose exec -T db mariadb -uroot -proot schema_probe < database/ci/base-schema.sql 2>&1)
    if [ $? -ne 0 ]; then
        record "schema round-trip (load FAILED)" "$OUT"
        printf '%s\n' "$OUT" | sed 's/^/    /'
        bad "database/ci/base-schema.sql does not load into an empty database"
    else
        OUT=$(SCHEMA_DUMP_DB=schema_probe SCHEMA_DUMP_USER=root SCHEMA_DUMP_PASS=root \
            ./tools/schema-dump.sh --check 2>&1)
        if [ $? -eq 0 ]; then
            record "schema round-trip" "$OUT"
            ok "a database built from the recording re-dumps to it exactly"
        else
            record "schema round-trip (DRIFT)" "$OUT"
            printf '%s\n' "$OUT" | sed 's/^/    /'
            bad "the schema built from the recording does not re-dump to it"
        fi
    fi

    docker compose exec -T db mariadb -uroot -proot -e "DROP DATABASE IF EXISTS schema_probe;" >/dev/null 2>&1
}

stage_unit() {
    # --- ci.yml job 4: unit ------------------------------------------------
    step "Unit suite  (ci.yml: unit)"
    suite unit 3
}

stage_integration() {
    # --- ci.yml job 5: integration ----------------------------------------
    step "Integration suite  (ci.yml: integration)"
    suite integration 2 "-d memory_limit=1G"
}

# Rebuild the capsule's sitemap, at most once per run.
#
# Two stages read those files and neither writes them: SitemapSmokeTest compares the
# on-disk sitemap against the database, and tools/smoke-prod.sh filters the index by BASE
# (so a sitemap generated for another host lists nothing it will match, which is the strict
# behaviour and worth keeping). The build used to live inside stage_smoke_prod, which runs
# *after* stage_smoke — so the smoke suite read whatever the previous run had left, and a
# row created or deleted since was a failure nobody's change caused. It cost two runs.
#
# Returns non-zero when the build fails, and says so once; each caller decides what that
# means for it.
SITEMAP_BUILT=""
build_capsule_sitemap() {
    if [ -n "$SITEMAP_BUILT" ]; then
        return "$SITEMAP_BUILT"
    fi

    local log
    log=$(mktemp)
    if in_capsule php bin/console sitemap:build --force --url=http://localhost:8080 > "$log" 2>&1; then
        SITEMAP_BUILT=0
    else
        SITEMAP_BUILT=1
        record "sitemap:build (FAILED)" "$(cat "$log")"
        sed 's/^/    /' "$log"
    fi
    rm -f "$log"

    return "$SITEMAP_BUILT"
}

stage_smoke() {
    # --- Beyond CI: the suites that need a live application ----------------
    # CI cannot run these at all — no Apache, no MariaDB, no APCu — which is exactly why
    # a local run is the stronger check. ONE process at a time; never fan this out.
    step "Smoke suite  (NOT in ci.yml — needs the running capsule)"
    build_capsule_sitemap || warn "could not build the capsule sitemap, so SitemapSmokeTest is reading a stale one"
    suite smoke 3
}

stage_fuzz() {
    step "Form fuzz harness  (NOT in ci.yml)"
    suite fuzz 3
}

stage_smoke_prod() {
    # --- Beyond CI: the post-deploy smoke script, against the capsule ------
    #
    # tools/smoke-prod.sh is NOT the `smoke` suite above. That one is PHPUnit under
    # test/Smoke; this is the bash script the deploy runs as its last step, and until
    # 2026-08-19 the only thing that ever executed it was a production deploy. So its
    # own bugs could only ever be found by shipping: an unqualified grep for a key
    # that exists in two sections of one JSON document killed a run with
    # `77\n838 / 60: syntax error` after a successful deploy, and no local check
    # could have seen it.
    #
    # It is BASE-parameterised already, so pointing it at the capsule exercises the
    # whole script — its parsing, its assertions, its exit status — against a real
    # application. 38 checks pass there. On the first attempt, with nothing changed but the
    # URL, 28 of 30 already passed — both failures were the sitemap host.
    #
    # The sitemap is rebuilt first because the script filters the index by BASE, and
    # a sitemap generated for a different host lists nothing it will match. That is
    # the strict behaviour and worth keeping: in production a loc that does not start
    # with the canonical base is a real fault. `--url` costs 2.4s and keeps the check
    # honest rather than teaching it to accept a foreign host. Since 2026-09-22 the build
    # is `build_capsule_sitemap`, shared with stage_smoke and run once per invocation.
    #
    # The cache key is the capsule's own, from docker/local.docker.php. With it set,
    # the APCu and OPcache blocks run — which is precisely the code that broke.
    # SMOKE_PROD_CANARY_COOKIE is deliberately NOT set: the vhost's SetEnv masks the
    # .htaccess kernel lines, so neither canary works here (see CLAUDE.md).
    step "Post-deploy smoke script  (NOT in ci.yml — tools/smoke-prod.sh vs the capsule)"
    if ! build_capsule_sitemap; then
        warn "could not build the capsule sitemap, so smoke-prod.sh was not run"
        return
    fi

    sm_log=$(mktemp)
    if SMOKE_PROD_BASE_URL=http://localhost:8080 \
       SMOKE_PROD_CACHE_KEY=local-dev-api-key \
       bash tools/smoke-prod.sh > "$sm_log" 2>&1; then
        record "smoke-prod.sh (ok)" "$(cat "$sm_log")"
        ok "smoke-prod.sh: $(grep -c '^  ok  ' "$sm_log") checks passed against the capsule"
    else
        record "smoke-prod.sh (FAILED)" "$(cat "$sm_log")"
        grep -E '^(FAIL|WARN)' "$sm_log" | sed 's/^/    /'
        bad "smoke-prod.sh against the capsule (./tools/ci-local.sh --last)"
    fi
    rm -f "$sm_log"
}

# --- run --------------------------------------------------------------------------

needs_capsule=0
for stage in $STAGES; do
    case " $CAPSULE_STAGES " in *" $stage "*) needs_capsule=1 ;; esac
done
if [ "$needs_capsule" -eq 1 ] && ! curl -sf -o /dev/null http://localhost:8080/_health; then
    printf '\033[31mThe capsule is not answering on :8080.\033[0m Start it with `docker compose up -d`.\n' >&2
    exit 2
fi

for stage in $STAGES; do
    if cached "$stage"; then
        printf '\n\033[1m=== %s\033[0m\n  \033[32mok\033[0m    (cached) unchanged since this stage last passed\n' "$stage"
        note "=== $stage"
        note "  ok    (cached)"
        CACHED=$((CACHED + 1))
        continue
    fi

    before_f=$FAILURES
    before_w=$WARNINGS
    "stage_${stage//-/_}"
    # Only a stage that RAN and passed cleanly is cached. A stage that could not run is
    # not a stage that passed, or the SKIP would vanish on the next invocation.
    if [ "$FAILURES" -eq "$before_f" ] && [ "$WARNINGS" -eq "$before_w" ]; then
        mark_green "$stage"
    else
        rm -f "$CACHE_DIR/$stage"
    fi
done

printf '\n'
if [ "$CACHED" -gt 0 ]; then
    printf '\033[33m%d of %d stage(s) were served from cache\033[0m — unchanged since they last passed.\n' \
        "$CACHED" "$(echo "$STAGES" | wc -w)"
    printf 'Re-run with --no-cache to verify them again from scratch.\n\n'
fi
if [ "$WARNINGS" -gt 0 ]; then
    printf '\033[33m%d check(s) could not run\033[0m — see SKIP above. Say so in the PR body rather than\n' "$WARNINGS"
    printf 'omitting it: "everything passed" and "everything that could run passed" are different claims.\n\n'
fi
if [ "$FAILURES" -eq 0 ]; then
    printf '\033[32mAll checks that ran passed.\033[0m'
    [ "$STAGES" = " $ALL_STAGES" ] && printf ' This covers everything CI runs, plus smoke, fuzz and the\npost-deploy smoke script, none of which it can.'
    printf '\nFull output: ./tools/ci-local.sh --last\n'
    exit 0
fi
printf '\033[31m%d check(s) failed.\033[0m  ./tools/ci-local.sh --last  for the complete output.\n' "$FAILURES"
exit 1
