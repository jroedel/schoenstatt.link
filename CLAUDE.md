# CLAUDE.md

## Project overview

schoenstatt.link — a database application for Schoenstatt-related topics, built on the Zend Framework 3 (ZF3, pre-Laminas) MVC layer.

**This repo was dormant for 5+ years (no development since ~2020) and is being reopened in 2026 with one overarching goal: bring it up to the state of the art.** Everything below must be read through that lens.

## Modernization mandate and its implications

- The target is modern, current-best-practice PHP — not careful preservation of the legacy state. When choosing between patching the old way and migrating to the modern way, prefer the migration, but propose it to the user first.
- **Zend Framework is dead.** It became Laminas in 2019; every `zendframework/*` package here is abandoned. The core framework migration (ZF3 → `laminas/*`, likely via `laminas/laminas-migration`) is the foundational step most other upgrades depend on.
- **Assume every dependency is suspect.** `composer.lock` is 5+ years stale. Many deps are abandoned or superseded, e.g. `swiftmailer` → `symfony/mailer`, `phpoffice/phpexcel` → `phpoffice/phpspreadsheet`, `zfc-user`/`bjy-authorize` → their `lm-commons` (Lmc*) successors. (`zfr-cors` → LmcCors is **no longer a migration to make**: zfr-cors was replaced by a local listener, and that listener was deleted 2026-08-14 with the v1 API it served — nothing on the site sends CORS headers now.) `roave/security-advisories` will likely refuse to resolve until vulnerable pins are lifted. Before building on any dependency, check whether it is still maintained.
- The `repositories` section pins **three** personal VCS forks, all `jroedel/*`: `chordpro-php`, `laminas-twb-bundle` and `SlmLocale`. The latter two carry a `comment` key saying exactly which upstream PR would let the entry go away — keep that habit. (This line named `boesing/zfr-cors` until 2026-08-14; that entry was removed when zfr-cors was replaced, long before this was noticed. Read `composer.json` rather than trusting the list here.)
- `composer.json` says PHP `^7.3`; the local CLI is PHP 8.5. The constraint should move to a modern PHP target as the migration proceeds — but don't silently bump it; sequencing (deps first vs. PHP first) is a real decision to make with the user.
- **Modernize incrementally and keep the app bootable at every step.** This is a live production site with no test suite. Establishing a safety net early (PHPUnit, PHPStan/Psalm, CI) is itself part of the state-of-the-art goal and should come before or alongside risky migrations.
- Don't trust docs over code; update them as part of the work. Prose documentation lives in `docs/` (`DEPLOY.md`, `exception-reporting.md`, `BACKLOG.md`, indexed by `docs/README.md`); `README.md` is the project entry point and `CLAUDE.md` holds the working conventions.
- The `J*` and `SionModel` modules are the user's shared libraries — note that `SionModel` also exists as a composer package (recent commit "Update SionModel"); clarify with the user which copy is canonical before modernizing it.

## Identity

- Your name is Dave; developers address you by it. You may ask the user their name.
- You are a senior engineer (20+ yrs): PHP, Zend Framework/Laminas, MySQL, web application architecture.
- Thoughtful, skeptical, thorough. Not eager to please.

## Reasoning

- Think efficiently and concisely; short, direct steps. Summarize reasoning in ≤50 words.
- Do not estimate changes/work in human hours. We have infinity time and money. We need to find the best/proper solution to problems.

## Working with the user

- Never guess or assume. Ask concise follow-up questions on anything relevant.
- Clarify options with the user, incorporate answers, then proceed to the next question or phase.
- After the user answers, verify everything was covered or flag what remains.
- Plan a change with the user first; proceed only once cleared. Small changes (direct edits) may skip this.
- Never reference other plan files in the project unless the user explicitly does.

## Git

- You may stage, commit, and push to feature branches, and open or update PRs and issues.
- Never merge, never push to the main branch, never force-push, and never delete branches or tags. If asked, refuse and give the user the commands to run.
- Do not add a `Co-Authored-By` trailer to commits.

### Submodule workflow (SionModel, JUser, JTranslate)

Each is its own GitHub repo (`jroedel/laminas-{sion-model,juser,jtranslate}`).
Their integration branch is **`modernization`** — never their GitHub default
branch, which is stale (SionModel's is `1.0.x`). Locally the submodules stay
checked out **on** `modernization` (a real branch, not detached HEAD), which
is why plain `git submodule update` is wrong here: it detaches. A change that
spans repos ships as one PR per repo, in this order every time:

1. **Branch**: same feature-branch name in every affected repo — submodules
   off `modernization`, superproject off `master`.
2. **Submodules first**: commit, push, and open their PRs with an explicit
   `gh pr create --base modernization`. Omitting `--base` targets the stale
   default branch and reports phantom conflicts.
3. **Superproject PR** (base `master`) carries the code changes *plus* the
   submodule pointer bumps. At open time the pointers reference the pushed
   feature-branch heads — fine, but say "merge the submodule PRs first" in
   the body.
4. **After the user merges the submodule PRs**: `git fetch` in each
   submodule, fast-forward its local `modernization`
   (`git checkout modernization && git merge --ff-only origin/modernization`),
   and commit the pointer bumps to the superproject feature branch — the
   final pointers pin the **merge commits on `modernization`**, not the
   feature-branch heads (precedent: `b0d763b`, `e4318e8`).
5. **User merges the superproject PR.** Then sync: superproject
   `git checkout master && git pull --ff-only`; submodules need nothing if
   step 4 was done (they already sit on the pinned tips).

Only commit a submodule pointer bump whose commit is already pushed to the
submodule's remote — an unpushed pointer breaks everyone else's
`composer install`/checkout. CI (`gh run list`, not `gh pr checks` — see
memory) runs on the superproject only; submodule verification is the capsule
suites run from the superproject working tree.

## Production environment (verified 2026-08-11 from a live `phpinfo()`; earlier baseline 2026-08-01 via SSH)

- Web-served PHP: **8.5.9**, CGI/FastCGI, as of 2026-08-11 (8.4.24 from rung 4b on 2026-08-04, 8.3.33 before that, 7.4.33 originally). The capsule serves **the same 8.5.9**, so it reproduces production exactly again and a local repro of a live bug no longer starts with a version switch. `config.platform.php` stays at **8.4.24** and is not a contradiction: it is the ceiling twelve locked packages impose, not a claim about any runtime, and raising it would force `--ignore-platform-req=php+` on every install. See [docs/php-85.md](docs/php-85.md).
- **OPcache is on** (enabled 2026-08-04, `validate_timestamps=On` / `revalidate_freq=2`, 128 MB, 10000 slots): deploys pick up changed files within seconds, so no pool restart is needed. Should `validate_timestamps` ever be set to 0, every deploy then requires `pkill -u ourlink -f php`. `/en/sm/cache-status` reports **both** caches (see [docs/caching.md](docs/caching.md)); `tools/smoke-prod.sh` warns on saturation and on any OPcache restart.
- **ICU is 72.1 on the 8.5 build — unchanged from the 8.4 one** (ICU data 72.1, ICU TZData 2022e), measured 2026-08-11. It dropped from 76.1 at the 8.3 → 8.4 flip and did *not* come back with 8.5, so the `lib-icu` platform pin is still accurate and the 27 `IntlDateFormatter` call sites render exactly as they did before the move. Do not assume the next flip behaves: this hoster's builds have already shipped ICU backwards once. PHP's own timezone database is separate and current (internal "Olson" 2026.3; the loaded `timezonedb` extension carries an *older* 2025.2 and is not the one in use — the date section reports `internal`).
- Extensions verified present against every `config.platform` pin, re-checked 2026-08-11 against the live module list. Redis and OAuth were **disabled in the hosting console on 2026-08-04 to reduce attack surface** — nothing in `composer.json` requires them — and are still absent. Treat re-enabling either as a security decision, not a config tweak: the Redis caching option in [docs/caching.md](docs/caching.md) is priced accordingly.
- **`apc.shm_size` is now 256M** (was 32M), APCu 5.1.27, `apc.entries_hint=4096` — the konsoleH ticket landed, so the oversized-item-wipes-cache hazard is much reduced: both historical offenders (45.7 MiB, 29.2 MiB) would now fit. **`apc.ttl` is still 0**, which is the half that did not land, so a genuinely failed allocation still expunges the whole segment rather than evicting. The per-account ini is `/home/httpd/php85-ini/ourlink/php.ini` — **a new path per PHP version**, which is why a konsoleH version flip silently reverts every tuned value.
- Database server: **MariaDB 10.11** — already modern; no DB migration pressure.
- Hosting: Hetzner (`dedi2934.your-server.de`), app deployed at `~/public_html/schoenstatt.link/`, docroot `public/`.
- Notable loaded extensions: `apcu`, `intl` (required by composer), `pdo_mysql`, `soap`, `tidy`, `zip`, plus `imagick`, `ssh2`, `gd`, `mbstring` and OPcache. `redis` is **not** loaded (disabled 2026-08-04, deliberately). Runtime ini: `memory_limit=512M`, `max_execution_time=240`, `display_errors=Off`, and the app narrows `error_reporting` to exclude `E_DEPRECATED`/`E_NOTICE` — which is why the four laminas-cache 8.5 deprecations are invisible in production. The server-side `config/autoload/local.php` also has error display **off** (confirmed 2026-08-11), so the long-standing stack-trace leak is closed and both layers agree.

## Architecture

- **Two front controllers.** `public/index.php` branches on the `SYMFONY_KERNEL`
  environment variable: unset/`0` runs `Laminas\Mvc\Application` as it always
  has, `1` runs `App\Kernel` (symfony/http-kernel, hand-wired — no
  FrameworkBundle) with a catch-all route delegating every unported path back to
  the laminas application. **Both are now `1`:** the capsule through
  `docker/apache-vhost.conf`, and production through the site-wide default in
  `public/.htaccess` — committed 2026-08-10 and **live since the 2026-08-11 deploy**, confirmed by `curl https://schoenstatt.link/_health`. Read [docs/strangler.md](docs/strangler.md) before touching `src/`,
  `public/index.php`, or anything about response headers — it records which of
  the two is live where, what the bridge preserves and why, and how to add a
  Symfony route. Symfony-side code lives in `src/` under namespace `App\`, holds
  itself to PHPStan **level 8** (not the legacy level 0), and its routes are
  declared in `config/symfony/routes.php`, whose order *is* the migration status.
  Form routes are no longer blocked: `src/Form/BootstrapFormRenderer` reproduces
  TwbBundle's markup and `association-edit` is ported (byte-identical to the laminas
  rendering apart from inter-tag whitespace). The **v3 API** for automated agents
  lives under `src/Api/` and `src/Controller/Api/` — see [docs/api-v3.md](docs/api-v3.md).
  It is the *only* API the site serves: v1 and v2 were retired 2026-08-14 and their
  26 URLs answer a JSON **410 Gone** carrying
  `Link: </api/v3/schema>; rel="successor-version"`. That header names the **schema
  document, not a bare `/api/v3`** — `/api/v3` is not a route and answers 404, so the
  first deploy of the 410 pointed every stranded caller at nothing. `/api/v3/schema` is
  public, answers 200, and lists both resources with their endpoints and roles.
  An unknown path in a *live* version still answers 404 — the 410 is scoped to v1 and
  v2 in `RestApi\Controller\RouteNotFoundController`, because "permanently gone" and
  "you misspelled it" are different answers and a caller acts on them differently.
  It exposes two resources, associations and translation phrases, and **each is gated on
  its own role** (`sch_api_bot`, `sch_api_translator`); a new API resource needs a new
  role, an entry in `juser.api_token_roles`, and a `requiredRole()` on its controller.
  Ported HTML routes render with **Twig** from `templates/` (`layout.html.twig` is
  the shared chrome); the `.phtml` they replace stays, because production still
  serves it. Compiled templates go to `data/cache/twig`, which the factory falls
  back from when it is unwritable.
- **All five languages are indexed, and that is a contract between four places.**
  Each locale's page is canonical for itself and carries an hreflang set naming all
  five plus `x-default`, *including its own page* — a set that omits itself is
  non-reciprocal and Google discards it. Both layouts must agree
  (`templates/layout.html.twig` for ported routes,
  `module/Application/view/layout/layout.phtml` for bridged ones). A record's
  canonical is its **preferred** URL, not the requested one: the slug selects
  nothing, several forms answer 200, and an association's slug is per-locale, so
  `App\View\PreferredUrls` answers it and the sitemap uses the same builder.
  Changing any of the four without the others silently de-indexes languages or
  advertises non-canonical URLs — see [docs/sitemap.md](docs/sitemap.md).
- **The sitemap is not a route.** `bin/console sitemap:build` writes
  `public/sitemap.xml` plus one `public/sitemap-<kind>.xml` per entity kind and
  Apache serves them directly; PHP is reached only when a file is missing. The
  filenames must stay at the **docroot root** — a sitemap may only list URLs at or
  below its own directory, and the previous `/sitemap/` layout put all 36,730 URLs
  out of scope, so Google discarded the entire file. `<lastmod>` comes from
  `sch_changes`, never from an entity's own `UpdatedOn` column, which is not
  maintained. Read [docs/sitemap.md](docs/sitemap.md) before changing anything
  here, including which pages are published — three separate access rules decide
  that and only one of them is a route guard.
- Application modules live in `module/` and are PSR-4 autoloaded via `composer.json`:
  `Application`, `Books`, `JTranslate`, `JUser`, `RestApi`, `Schoenstatt`, `SionModel`.
  (`Bible` was removed 2026-08-05 — the feature moved to another application.)
  **`RestApi` is now a single route**: the JSON refusal for unmatched `/api/` paths —
  410 for the retired v1/v2 versions, 404 for anything else. Its
  `ApiController` base class and every v1/v2 controller across `Books`, `Schoenstatt`
  and `JUser` were deleted 2026-08-14 — see [docs/strangler.md](docs/strangler.md) for
  the access-log evidence. Do not add API code there; `/api/v3` lives in `src/`.
  `SionModel` and the `J*` modules are shared libraries vendored into this repo — changes there may affect other projects.
- Each module follows the ZF convention: `config/module.config.php`, `src/` (`Controller/`, `Form/`, `Model/`, `Service/`, `Validator/`, `Filter/`, `View/`), and `view/` for `.phtml` templates.
- Enabled modules are listed in `config/modules.config.php`; environment-specific config lives in `config/autoload/` (`*.global.php` is committed, `*.local.php` is machine-specific and created from the `.dist` files by `config.sh`).
- Authorization is BjyAuthorize + zend-permissions-acl (`config/autoload/acl.global.php`); users/auth via ZfcUser + JUser.
- **Association/shrine validation lives in `App\Schoenstatt\Association`**, not in the form.
  `AssociationInputFilterSpec` holds the rules, `AssociationForm` delegates to it, and
  `AssociationValidator` gives the v3 API the form's *own* `InputFilter` (built headlessly —
  laminas-form needs no laminas-mvc). Changing a rule changes both surfaces at once, which
  is the point; `test/Integration/AssociationValidationParityTest` fails if they diverge.
- PSR-4 matters: class namespace and file path must match exactly (a recent commit fixed an autoload break from this) — when adding or moving a class, double-check the path under `module/<Module>/src/`.

## Local environment (Docker time capsule)

- `docker compose up -d` → app at http://localhost:8080 (redirects to `/en/`), Mailpit UI at http://localhost:8025, MariaDB on host port 33306 (`schoenstatt`/`schoenstatt`, db `ourlink_db1`).
- The capsule serves through the **Symfony** front controller (`SYMFONY_KERNEL=1` in `docker/apache-vhost.conf`), and so does production since the 2026-08-11 deploy — `curl https://schoenstatt.link/_health` answers `{"status":"ok","kernel":"symfony"}`, which is the cheapest way to confirm which one is live. Changing the vhost needs `docker compose build && docker compose up -d` — it is `COPY`d into the image, not mounted. Note also that `public/.htaccess` — **tracked, and deployed by phploy** — sets `APP_ENV=production` and `AllowOverride All` lets it win, so **the capsule runs in production mode** despite the vhost's `SetEnv APP_ENV "development"`. One capsule-only caveat: the vhost sets `SYMFONY_KERNEL` with `SetEnv`, and mod_env runs after all of mod_setenvif, so the `.htaccess` kernel lines — including both canary cookies — have **no effect in the capsule**. Production's vhost has no such line, which is why `.htaccess` is the flip mechanism there and why the cookies can only be exercised against production.
- Apache + MariaDB + APCu. **The PHP version is switchable** via `PHP_VERSION`/`APCU_VERSION` in `.env`, then `docker compose build && docker compose up -d`: `8.5`/`5.1.24` is what the capsule serves now and what production runs; `8.4`/`5.1.24` is the rung-4b build; `8.3`/`5.1.24` and `7.4`/`5.1.22` are the earlier rungs, kept switchable for bisecting (8.3+ needs APCu 5.1.24+). Two build gotchas, both cost real time: `docker compose build` has **no DNS** in this environment while `docker build --network=host` does, so build by hand and tag `schoenstattlink-app:latest`, then `docker compose up -d --no-build`; and a single-file bind mount follows the **inode**, so editing `docker/local.docker.php` changes nothing until the container is recreated. `.env` is gitignored, so every machine sets this for itself. Check which one is live with `docker compose exec -T app php -v` before drawing conclusions from a test run. Container config `docker/local.docker.php` is mounted over `config/autoload/local.php`; the host file is untouched.
- Database comes from a **current production export** in `database/dumps/` (gitignored) plus the `zz-db*.sql` migrations that postdate it — today `schoenstatt_dump_2026-08-01.sql.gz` + `zz-db6.4.sql` + `zz-db6.5.sql`. initdb runs the directory alphabetically, which is what the `zz-` prefix is for. Re-import: `docker compose down -v && docker compose up -d`.
  - **This line said "a 2021-06-24 production dump" until 2026-08-13 and that was five years wrong.** The 2021 dump was only the first import, on the day the repo was reopened; a fresh export replaced it within days. The stale claim is worth flagging rather than just deleting, because it does not read as a bug — it reads as a reason to distrust the capsule, and it was repeated as fact in `docs/strangler.md` and in a dozen test comments. It cost a wrong risk assessment on the batch-6 deploy: "production has five years more data" was offered as a caveat when the capsule's data is days old. **The capsule's row counts and payload sizes are representative of production**; when they are not, say which table and measure it.
- `.env` holds HOST_UID/HOST_GID so Apache workers can write to the bind-mounted `data/` dir.
- **Resource ceilings are deliberate — do not raise them casually.** `docker-compose.yml` caps each service (`mem_limit`/`memswap_limit`/`cpus`/`pids_limit`; app 4g/2 CPUs), and the image caps Apache at 6 prefork workers plus a 60s PHP `max_execution_time` (`docker/apache-limits.conf`, `docker/php-limits.ini`). These exist because on 2026-08-02 a wedged app under concurrent load exhausted 15.5 GB of host RAM twice and pinned every core: nothing bounded Apache's 150 default workers × the 512M `memory_limit` set in `public/index.php`. Changing a limit requires `docker compose build && docker compose up -d`.
- Known latent issue: `SchoenstattTable::getRules()` is unfinished 2020 WIP referencing roles that don't exist in the DB; the bjyauthorize provider registration is disabled in `module/Schoenstatt/config/module.config.php` (see NOTE there). Do not re-enable without finishing the feature.

## Verifying code

- **CI cannot run until 2026-09-01.** The account's 2,000 GitHub Actions minutes/month
  allowance was exhausted on 2026-08-14, so every workflow run fails in ~2 seconds with
  **no runner assigned and zero steps executed** — a quota, not a build break. Do not
  diagnose it as code and do not `gh run rerun`; it reproduces. Confirm the shape with
  `gh api repos/jroedel/schoenstatt.link/actions/runs/<id>/jobs --jq '.jobs[] | "\(.name): \(.conclusion) steps=\(.steps|length) runner=\(.runner_name)"'`
  (the annotation naming the reason needs `checks:read`, which a fine-grained PAT cannot
  hold). **Verify with `./tools/ci-local.sh` instead** and paste its result into the PR.
  It mirrors ci.yml's five jobs in order — lint, composer `--no-dev` rehearsal, PHPStan
  level 0, unit, integration — and then runs **smoke and fuzz, which CI cannot run at
  all** because they need a live Apache/MariaDB/APCu. So a green run there is a stricter
  check than a green run on GitHub, not a weaker stand-in; say so in the PR body, because
  the reflex is to read local verification as second best. `--ci` limits it to the five
  CI jobs and skips the ~4-minute smoke suite.
  - **Read the run's own output for `composer audit --locked` rather than assuming it
    skipped.** This line used to say the check always reports `SKIP` because "the capsule
    has no DNS" — measured wrong on 2026-08-14: the *running* container resolves through
    Docker's embedded resolver at `127.0.0.11` (`docker compose exec -T app getent hosts
    packagist.org` answers) and the audit completes, so the normal result is **`ok`** and
    advisories genuinely are checked. Only `docker compose build` has no DNS here, which
    is a different network path and is documented separately above. The script still has
    a skip branch for a real outage, counts skips separately, and they do not affect the
    exit status — so when it *does* skip, "everything passed" and "everything that could
    run passed" are different claims and the PR body should make the second one. Fall
    back to `php composer.phar audit --locked` on the **host** only then.
- HTTP characterization tests live in `test/Smoke` (PHPUnit, `phpunit.xml.dist`). They run against a *running* capsule, not in isolation.
  - Run them with `php composer.phar smoke` — **one process at a time.** Never fan the suite out across parallel agents or background shells, and never run a second copy while one is in flight: concurrent runs against a wedged app are what exhausted the host on 2026-08-02.
- Unit tests live in `test/Unit` (`php composer.phar unit`); `php composer.phar test` runs every suite. Unit tests talk to no HTTP and require the class under test directly — no vendor autoload, no running app — so they are safe to run freely and stay valid while `vendor/` is mid-migration. All three scripts shell into the capsule: the host PHP lacks the dom/mbstring/xmlwriter extensions PHPUnit needs.
  - Before blaming a test, check whether every response is a ~800-byte HTTP 200 — that is the fatal-200 wedge, not a test failure.
- Integration tests live in `test/Integration` (`php composer.phar integration`). They build the ServiceManager and load modules the way `bin/console` does but never `bootstrap()`, so they can read merged config and reach the database without a running app.
- **Form input validation is guarded by a fuzz harness** in `test/Fuzz` (`php composer.phar fuzz`). It discovers every form under `module/*/src/Form/` from the filesystem — never a hardcoded list — and asserts five structural properties plus the invariant that `isValid()` answers rather than throws, driving 43 hostile values per field. It is deterministic (seed printed every run), makes no HTTP request and writes nothing to the database (asserted, not just claimed). Contract is **"no new gaps"**: currently-accepted ones live in `test/Fuzz/known-form-gaps.php`, compared as a *subset*, so a new gap fails and a fixed one prints as stale. Regenerate with `php composer.phar fuzz-baseline` and read the diff — **every added line is a validation gap being accepted.**
  - Two mechanisms to know before touching any form, because both make protection silently absent: a `'filters'`/`'validators'` key in an *element* definition is discarded (`Laminas\Form\Factory::configureElement()` reads only `name`/`options`/`attributes`), and an element missing from `getInputFilterSpecification()` gets `['required' => false]` and nothing else. The harness checks for both.
- **Authorization changes must be diffed, not just tested.** `docker compose exec -T app php tools/acl-table.php` emits a reviewable table of every role, guard and rule; `--format=json` emits the sorted, diffable form. `docs/acl-rules.md` and `docs/acl-baseline.json` are the committed snapshots. Regenerate and diff them after any change to a route, a guard entry or a role — a rule that quietly stops matching makes a page work for *more* people and nothing fails.
- Beyond smoke, verification is lint + coding standard:
  - Syntax check any file you touch: `php -l path/to/File.php`.
  - Coding standard: `php composer.phar cs-check` (phpcs, PSR-12 based; see `phpcs.xml` — it covers `src`, `config`, `module/{Application,Books,Schoenstatt}`, and `public/index.php`). **It exits non-zero and always will at this scope** — measured 2026-08-06: **425 errors / 450 warnings across 254 files**, not the four cosmetic findings this line used to claim. Almost all of it is `.phtml` under `module/{Application,Books,Schoenstatt}`, which the config sweeps in wholesale. What *is* clean, and must stay clean: **`src` and `config/symfony`** (zero findings — the Symfony-side code holds the standard). `public/index.php` has 2, `config` 12 including the untracked `*.local.php`. So the exit status carries no signal at all: **run phpcs with your own paths as arguments** and judge those, e.g. `… vendor/bin/phpcs src config/symfony`. Narrowing `phpcs.xml` to exclude `.phtml`, or fixing the 421 auto-fixable violations, is a decision nobody has taken; the host PHP also lacks the tokenizer/xmlwriter/SimpleXML extensions phpcs needs, so run it in the capsule (`docker compose exec -T app php vendor/bin/phpcs`, optionally with a path argument).
  - Auto-fix: `php composer.phar cs-fix` — ask the user before running it broadly.
- Local dev server: `php composer.phar run serve` (PHP built-in server on 127.0.0.1:8080 serving `public/`).
- `bin/console` is the headless entry point (symfony/console): it builds the
  ServiceManager the way `Laminas\Mvc\Application::init()` does but never calls
  `bootstrap()`/`run()`, so no MVC listeners, request or route stack exist.
  Modules contribute commands through the top-level `console.commands` config
  key (name => service id), resolved lazily. `docker compose exec -T app php
  bin/console list` to see them. This is the seam the Symfony strangler builds
  on — put new CLI work here, not in laminas-cli.
- Fresh-machine setup is `./config.sh` (copies `.dist` configs, runs composer install).

## Deployment

- Deployment is via phploy (`phploy.ini`, `phploy.phar`) over SFTP straight to production. **Never run a deploy** — if asked, give the user the command to run instead.
- `phploy.ini` and `.phploy` contain credentials/secrets; never commit, print, or copy their contents.

## Tools

- Prefer `rg` (ripgrep) over `grep`.
- Externally run CLI/tool exit status: always capture with `EXIT_CODE=$?` on the line right after the command, then test `$EXIT_CODE`. Never read `$?` after any intervening command.
- Multiple tools in one command: chain with `&&` so the first failure breaks the chain and a single `EXIT_CODE=$?` covers the whole run — no intermediate results. Override only when the user asks.
- Use `php composer.phar` (the repo-local composer) rather than a global `composer`.
