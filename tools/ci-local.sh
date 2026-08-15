#!/usr/bin/env bash
# Run everything .github/workflows/ci.yml runs, locally, in the capsule.
#
# WHY THIS EXISTS: the account's 2,000 GitHub Actions minutes/month allowance was
# exhausted on 2026-08-14. Until it resets on the 1st, every job fails in ~2 seconds
# with no runner assigned and no steps executed — which looks alarming and is not about
# your code. Do not spend time re-running those; run this instead and paste the result.
#
# It mirrors ci.yml's five jobs in the same order, plus the two suites CI CANNOT run
# because they need a live application (smoke, fuzz). That makes a green run here a
# STRICTER check than a green run on GitHub, not a weaker one — worth saying out loud,
# because the reflex is to treat a local pass as second best.
#
#   ./tools/ci-local.sh          # the five CI jobs, then smoke + fuzz
#   ./tools/ci-local.sh --ci     # only the five CI jobs (faster; skips the ~4min smoke)
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
# tracked separately (docs/php-85.md). Filtering them keeps a real failure visible.
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
OUT=$(in_capsule php composer.phar audit --locked 2>&1)
if says "$OUT" 'No security vulnerability advisories found'; then
    ok "no advisories against locked versions"
elif says "$OUT" 'Could not resolve host'; then
    # NOT a failure, and reporting it as one is actively misleading: `bad` here prints
    # "FAIL composer audit --locked" into a PR body, which every reader takes to mean an
    # advisory was found. An unreachable advisory database means UNKNOWN, so say unknown
    # and say what to do.
    #
    # This branch is defensive rather than expected. The comment here used to assert the
    # container has no DNS at all, "same reason `docker compose build` cannot fetch" —
    # measured wrong on 2026-08-14: the *running* container resolves packagist.org,
    # github.com and example.com through Docker's embedded resolver at 127.0.0.11, and the
    # audit completes. Only `docker compose build` lacks DNS in this environment, which is
    # a different network path. So expect `ok` and treat this branch as a real outage.
    warn "advisories NOT checked — packagist.org unreachable from the container"
    printf '        run `php composer.phar audit --locked` on the host, or from CI once its minutes reset\n'
else
    bad "composer audit --locked"
fi
# The autoload sanity check, verbatim from ci.yml: five classes spanning the app and all
# three submodules, so a PSR-4 break or an unpushed submodule pointer fails loudly.
OUT=$(in_capsule php -r '
    require "vendor/autoload.php";
    foreach ([
        "Laminas\\Mvc\\Application",
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
fi

printf '\n'
if [ "$WARNINGS" -gt 0 ]; then
    printf '\033[33m%d check(s) could not run\033[0m — see SKIP above. Say so in the PR body rather than\n' "$WARNINGS"
    printf 'omitting it: "everything passed" and "everything that could run passed" are different claims.\n\n'
fi
if [ "$FAILURES" -eq 0 ]; then
    printf '\033[32mAll checks that ran passed.\033[0m'
    [ "$CI_ONLY" -eq 0 ] && printf ' This covers everything CI runs, plus smoke and fuzz, which it cannot.'
    printf '\n'
    exit 0
fi
printf '\033[31m%d check(s) failed.\033[0m\n' "$FAILURES"
exit 1
