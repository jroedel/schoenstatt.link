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
#   ./tools/ci-local.sh          # the seven CI jobs, then smoke, fuzz + smoke-prod.sh
#   ./tools/ci-local.sh --ci     # only the seven CI jobs (faster; skips the ~4min smoke)
#
# Everything runs through `docker compose exec app` except the syntax lint, which needs
# no extensions and so is fine on the host. The smoke suite MUST NOT be parallelised —
# concurrent runs against a wedged app exhausted the host's RAM twice on 2026-08-02.
set -uo pipefail
cd "$(dirname "$0")/.."

CI_ONLY=0
[ "${1:-}" = "--ci" ] && CI_ONLY=1

FAILURES=0
WARNINGS=0
step() { printf '\n\033[1m=== %s\033[0m\n' "$1"; }
ok()   { printf '  \033[32mok\033[0m    %s\n' "$1"; }
bad()  { printf '  \033[31mFAIL\033[0m  %s\n' "$1"; FAILURES=$((FAILURES + 1)); }
# A check that could not run, as distinct from one that ran and failed. Kept out of
# FAILURES on purpose — the exit status must mean "something is wrong with the code", or
# nobody will trust it — but counted and reprinted at the end so it cannot pass unnoticed.
warn() { printf '  \033[33mSKIP\033[0m  %s\n' "$1"; WARNINGS=$((WARNINGS + 1)); }

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

if ! curl -sf -o /dev/null http://localhost:8080/_health; then
    printf '\033[31mThe capsule is not answering on :8080.\033[0m Start it with `docker compose up -d`.\n' >&2
    exit 2
fi

# --- ci.yml job 1: PHP 8.5 syntax lint -------------------------------------
# The find expression is copied from the workflow verbatim. If ci.yml's changes, change
# it here in the same commit — a lint that covers less than CI's is worse than none.
step "PHP 8.5 syntax lint  (ci.yml: lint)"
LINT_FAILED=0
while IFS= read -r -d '' f; do
    php -l "$f" > /dev/null 2>&1 || { echo "    $f"; LINT_FAILED=1; }
done < <(find module config public/index.php test -name '*.php' -print0)
[ "$LINT_FAILED" -eq 0 ] && ok "every .php file parses" || bad "syntax errors above"

# --- ci.yml job 2: composer install --no-dev (deploy rehearsal) ------------
# --dry-run, unlike CI: the capsule's vendor/ is the working tree everything else in this
# script needs, and a real --no-dev install would strip phpunit out from under the suites
# below. So this catches an unresolvable or stale lock, NOT a missing production package.
step "composer --no-dev deploy rehearsal  (ci.yml: composer-install)"
OUT=$(in_capsule php composer.phar validate --no-check-publish 2>&1)
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
        *'security vulnerability advisor'*)             echo found ;;   # "Found N security vulnerability advisories"
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
case $VERDICT in
    clean) ok "no advisories against locked versions" ;;
    found) bad "composer audit --locked — advisories against locked versions" ;;
    *)
        warn "advisories NOT checked — the advisory database could not be read (twice)"
        printf '%s\n' "$OUT" | sed 's/^/        /'
        printf '        retry, or run `php composer.phar audit --locked` on the host\n'
        ;;
esac
# The autoload sanity check, verbatim from ci.yml: five classes spanning the Symfony side,
# the app modules and all three submodules, so a PSR-4 break or an unpushed submodule
# pointer fails loudly. `App\Kernel` was `Laminas\Mvc\Application` until that package left.
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
if says "$OUT" 'autoload ok'; then
    ok "autoload sanity across the app and all three submodules"
else
    bad "autoload sanity — $OUT"
fi

# --- ci.yml job 3: PHPStan level 0 ----------------------------------------
step "PHPStan level 0, no new errors  (ci.yml: static-analysis)"
OUT=$(in_capsule php -d memory_limit=1G tools/phpstan.phar analyse --no-progress 2>&1)
if says "$OUT" 'No errors'; then
    ok "no errors against phpstan-baseline.neon"
else
    bad "PHPStan — run it directly to see the errors"
fi

# --- ci.yml job 6: PSR-12 on the paths that must stay clean ----------------
# NOT `composer cs-check`, whose scope reports 425 errors and always will —
# see tools/phpcs-clean-paths.txt for why "clean" has to be an explicit list,
# and note that this step and the ci.yml job read that same file so the two
# cannot drift.
step "PSR-12 on the clean paths  (ci.yml: coding-standard)"
mapfile -t CS_PATHS < <(sed 's/#.*//' tools/phpcs-clean-paths.txt | grep -v '^[[:space:]]*$')
OUT=$(in_capsule php vendor/bin/phpcs -q --report=summary "${CS_PATHS[@]}" 2>&1)
CS_STATUS=$?
if [ "$CS_STATUS" -eq 0 ]; then
    ok "${#CS_PATHS[@]} paths clean"
else
    quiet <<< "$OUT" | tail -20
    bad "phpcs on the clean paths (exit $CS_STATUS)"
fi

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
    quiet < "$log" | tail -"$lines"
    [ "$status" -eq 0 ] && ok "$name" || bad "$name (phpunit exited $status; full output: $log)"
    [ "$status" -eq 0 ] && rm -f "$log"
}

# --- ci.yml job 7: the deploy's own machinery ------------------------------
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
        ok "$(tail -1 "$rsh_log")"
        rm -f "$rsh_log"
    else
        sed 's/^/    /' "$rsh_log"
        bad "$(basename "$deploy_check") (full output: $rsh_log)"
    fi
done

# --- ci.yml job 4: unit ----------------------------------------------------
step "Unit suite  (ci.yml: unit)"
suite unit 3

# --- ci.yml job 5: integration --------------------------------------------
step "Integration suite  (ci.yml: integration)"
suite integration 2 "-d memory_limit=1G"

if [ "$CI_ONLY" -eq 0 ]; then
    # --- Beyond CI: the suites that need a live application ----------------
    # CI cannot run these at all — no Apache, no MariaDB, no APCu — which is exactly why
    # a local run is the stronger check. ONE process at a time; never fan this out.
    step "Smoke suite  (NOT in ci.yml — needs the running capsule)"
    suite smoke 3

    step "Form fuzz harness  (NOT in ci.yml)"
    suite fuzz 3

    # --- Beyond CI: the post-deploy smoke script, against the capsule ----------
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
    # honest rather than teaching it to accept a foreign host.
    #
    # The cache key is the capsule's own, from docker/local.docker.php. With it set,
    # the APCu and OPcache blocks run — which is precisely the code that broke.
    # SMOKE_PROD_CANARY_COOKIE is deliberately NOT set: the vhost's SetEnv masks the
    # .htaccess kernel lines, so neither canary works here (see CLAUDE.md).
    step "Post-deploy smoke script  (NOT in ci.yml — tools/smoke-prod.sh vs the capsule)"
    sm_log=$(mktemp)
    if in_capsule php bin/console sitemap:build --force --url=http://localhost:8080 > "$sm_log" 2>&1; then
        if SMOKE_PROD_BASE_URL=http://localhost:8080 \
           SMOKE_PROD_CACHE_KEY=local-dev-api-key \
           bash tools/smoke-prod.sh > "$sm_log" 2>&1; then
            ok "smoke-prod.sh: $(grep -c '^  ok  ' "$sm_log") checks passed against the capsule"
            rm -f "$sm_log"
        else
            grep -E '^(FAIL|WARN)' "$sm_log" | sed 's/^/    /'
            bad "smoke-prod.sh against the capsule (full output: $sm_log)"
        fi
    else
        sed 's/^/    /' "$sm_log"
        warn "could not build the capsule sitemap, so smoke-prod.sh was not run"
    fi
fi

printf '\n'
if [ "$WARNINGS" -gt 0 ]; then
    printf '\033[33m%d check(s) could not run\033[0m — see SKIP above. Say so in the PR body rather than\n' "$WARNINGS"
    printf 'omitting it: "everything passed" and "everything that could run passed" are different claims.\n\n'
fi
if [ "$FAILURES" -eq 0 ]; then
    printf '\033[32mAll checks that ran passed.\033[0m'
    [ "$CI_ONLY" -eq 0 ] && printf ' This covers everything CI runs, plus smoke, fuzz and the\npost-deploy smoke script, none of which it can.'
    printf '\n'
    exit 0
fi
printf '\033[31m%d check(s) failed.\033[0m\n' "$FAILURES"
exit 1
