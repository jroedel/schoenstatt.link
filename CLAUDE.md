# CLAUDE.md

## Project overview

schoenstatt.link — a database application for Schoenstatt-related topics, built on the Zend Framework 3 (ZF3, pre-Laminas) MVC layer.

**This repo was dormant for 5+ years (no development since ~2020) and is being reopened in 2026 with one overarching goal: bring it up to the state of the art.** Everything below must be read through that lens.

## Modernization mandate and its implications

- The target is modern, current-best-practice PHP — not careful preservation of the legacy state. When choosing between patching the old way and migrating to the modern way, prefer the migration, but propose it to the user first.
- **Zend Framework is dead.** It became Laminas in 2019; every `zendframework/*` package here is abandoned. The core framework migration (ZF3 → `laminas/*`, likely via `laminas/laminas-migration`) is the foundational step most other upgrades depend on.
- **Assume every dependency is suspect.** `composer.lock` is 5+ years stale. Many deps are abandoned or superseded, e.g. `swiftmailer` → `symfony/mailer`, `phpoffice/phpexcel` → `phpoffice/phpspreadsheet`, `zfc-user`/`bjy-authorize` → their `lm-commons` (Lmc*) successors, `zfr-cors` → LmcCors. `roave/security-advisories` will likely refuse to resolve until vulnerable pins are lifted. Before building on any dependency, check whether it is still maintained.
- The `repositories` section pins several personal VCS forks (`jroedel/*`, `boesing/zfr-cors`, etc.). Each fork needs a decision: still necessary, upstreamed since, or replaceable.
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

## Production environment (verified 2026-08-01 via SSH)

- Web-served PHP: **8.4.24** as of rung 4b, deployed 2026-08-04 (8.3.33 before that, 7.4.33 originally). **The capsule now runs 8.5.9, i.e. ahead of production** — deliberately, so 8.5 is continuously tested. The cost is that it no longer reproduces production exactly, so `PHP_VERSION=8.4` in `.env` plus a rebuild is the first step before trusting a local reproduction of a live bug. See [docs/php-85.md](docs/php-85.md).
- **OPcache is on** (enabled 2026-08-04, `validate_timestamps=On` / `revalidate_freq=2`, 128 MB, 10000 slots): deploys pick up changed files within seconds, so no pool restart is needed. Should `validate_timestamps` ever be set to 0, every deploy then requires `pkill -u ourlink -f php`. `/en/sm/cache-status` reports **both** caches (see [docs/caching.md](docs/caching.md)); `tools/smoke-prod.sh` warns on saturation and on any OPcache restart.
- **ICU is 72.1 on the 8.4 build, down from 76.1 on the 8.3 build** (Unicode 15.0, CLDR-era TZData 2022e). The hoster's newer PHP ships the *older* ICU. Nothing crashes, but 27 `IntlDateFormatter` call sites format dates against older locale data, so a rendering difference is possible. PHP's own timezone database is separate and current (2026.3).
- Extensions verified present against every `config.platform` pin. Redis and OAuth were **disabled** 2026-08-04 as unused — nothing in `composer.json` requires them.
- `apc.shm_size` is still **32M** with `apc.ttl=0` on the 8.4 ini, so the oversized-item-wipes-cache hazard persists; the konsoleH ticket now needs to name `php84-ini`.
- Database server: **MariaDB 10.11** — already modern; no DB migration pressure.
- Hosting: Hetzner (`dedi2934.your-server.de`), app deployed at `~/public_html/schoenstatt.link/`, docroot `public/`.
- Notable loaded extensions: `apcu`, `redis`, `intl` (required by composer), `pdo_mysql`, `soap`, `tidy`, `zip`.

## Architecture

- **Two front controllers.** `public/index.php` branches on the `SYMFONY_KERNEL`
  environment variable: unset/`0` runs `Laminas\Mvc\Application` as it always
  has, `1` runs `App\Kernel` (symfony/http-kernel, hand-wired — no
  FrameworkBundle) with a catch-all route delegating every unported path back to
  the laminas application. **The capsule sets it to `1`; production does not
  yet.** Read [docs/strangler.md](docs/strangler.md) before touching `src/`,
  `public/index.php`, or anything about response headers — it records which of
  the two is live where, what the bridge preserves and why, and how to add a
  Symfony route. Symfony-side code lives in `src/` under namespace `App\`, holds
  itself to PHPStan **level 8** (not the legacy level 0), and its routes are
  declared in `config/symfony/routes.php`, whose order *is* the migration status.
  Ported HTML routes render with **Twig** from `templates/` (`layout.html.twig` is
  the shared chrome); the `.phtml` they replace stays, because production still
  serves it. Compiled templates go to `data/cache/twig`, which the factory falls
  back from when it is unwritable.
- Application modules live in `module/` and are PSR-4 autoloaded via `composer.json`:
  `Application`, `Books`, `JTranslate`, `JUser`, `RestApi`, `Schoenstatt`, `SionModel`.
  (`Bible` was removed 2026-08-05 — the feature moved to another application.)
  `SionModel` and the `J*` modules are shared libraries vendored into this repo — changes there may affect other projects.
- Each module follows the ZF convention: `config/module.config.php`, `src/` (`Controller/`, `Form/`, `Model/`, `Service/`, `Validator/`, `Filter/`, `View/`), and `view/` for `.phtml` templates.
- Enabled modules are listed in `config/modules.config.php`; environment-specific config lives in `config/autoload/` (`*.global.php` is committed, `*.local.php` is machine-specific and created from the `.dist` files by `config.sh`).
- Authorization is BjyAuthorize + zend-permissions-acl (`config/autoload/acl.global.php`); users/auth via ZfcUser + JUser.
- PSR-4 matters: class namespace and file path must match exactly (a recent commit fixed an autoload break from this) — when adding or moving a class, double-check the path under `module/<Module>/src/`.

## Local environment (Docker time capsule)

- `docker compose up -d` → app at http://localhost:8080 (redirects to `/en/`), Mailpit UI at http://localhost:8025, MariaDB on host port 33306 (`schoenstatt`/`schoenstatt`, db `ourlink_db1`).
- The capsule serves through the **Symfony** front controller (`SYMFONY_KERNEL=1` in `docker/apache-vhost.conf`); production still serves through the laminas one. Changing the vhost needs `docker compose build && docker compose up -d` — it is `COPY`d into the image, not mounted. Note also that `public/.htaccess` (untracked) sets `APP_ENV=production` and `AllowOverride All` lets it win, so **the capsule runs in production mode** despite the vhost's `SetEnv APP_ENV "development"`.
- Apache + MariaDB + APCu. **The PHP version is switchable** via `PHP_VERSION`/`APCU_VERSION` in `.env`, then `docker compose build && docker compose up -d`: `8.5`/`5.1.24` is what the capsule serves now; `8.4`/`5.1.24` matches production; `8.3`/`5.1.24` and `7.4`/`5.1.22` are the earlier rungs, kept switchable for bisecting (8.3+ needs APCu 5.1.24+). Two build gotchas, both cost real time: `docker compose build` has **no DNS** in this environment while `docker build --network=host` does, so build by hand and tag `schoenstattlink-app:latest`, then `docker compose up -d --no-build`; and a single-file bind mount follows the **inode**, so editing `docker/local.docker.php` changes nothing until the container is recreated. `.env` is gitignored, so every machine sets this for itself. Check which one is live with `docker compose exec -T app php -v` before drawing conclusions from a test run. Container config `docker/local.docker.php` is mounted over `config/autoload/local.php`; the host file is untouched.
- Database comes from a 2021-06-24 production dump in `database/dumps/` (gitignored) + `zz-db6.3.sql`. Re-import: `docker compose down -v && docker compose up -d`.
- `.env` holds HOST_UID/HOST_GID so Apache workers can write to the bind-mounted `data/` dir.
- **Resource ceilings are deliberate — do not raise them casually.** `docker-compose.yml` caps each service (`mem_limit`/`memswap_limit`/`cpus`/`pids_limit`; app 4g/2 CPUs), and the image caps Apache at 6 prefork workers plus a 60s PHP `max_execution_time` (`docker/apache-limits.conf`, `docker/php-limits.ini`). These exist because on 2026-08-02 a wedged app under concurrent load exhausted 15.5 GB of host RAM twice and pinned every core: nothing bounded Apache's 150 default workers × the 512M `memory_limit` set in `public/index.php`. Changing a limit requires `docker compose build && docker compose up -d`.
- Known latent issue: `SchoenstattTable::getRules()` is unfinished 2020 WIP referencing roles that don't exist in the DB; the bjyauthorize provider registration is disabled in `module/Schoenstatt/config/module.config.php` (see NOTE there). Do not re-enable without finishing the feature.

## Verifying code

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
  - Coding standard: `php composer.phar cs-check` (phpcs, PSR-12 based; see `phpcs.xml` — it covers `src`, `config`, `module/{Application,Books,Schoenstatt}`, and `public/index.php`). **It already exits non-zero on master** — four pre-existing cosmetic findings, itemized in `docs/BACKLOG.md`. Until they are cleared, check the findings against your own paths rather than trusting the exit status; the host PHP also lacks the tokenizer/xmlwriter/SimpleXML extensions phpcs needs, so run it in the capsule (`docker compose exec -T app php vendor/bin/phpcs`, optionally with a path argument).
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
