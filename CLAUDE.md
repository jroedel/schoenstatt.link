# CLAUDE.md

## Project

schoenstatt.link: a database application for Schoenstatt-related topics (shrines,
associations, people, a literature catalogue, lending libraries, a translation GUI and a
v3 API). Live production site, dormant 2020–2026, being brought to the state of the art.

**Direction (decided 2026-09-09): remove every `laminas/*` package, no exceptions, and
minimise dependencies everywhere.** The plan, its rules and the dependency inventory are
[docs/laminas-exit.md](docs/laminas-exit.md). Prefer eliminating a dependency over
rescuing it; before adding one, ask whether twenty lines of our own code would do. Propose
a migration before a patch, but propose it first.

**Direction (decided 2026-09-11): a fully automated CI/CD pipeline.** The blocker is the
gate, not the automation: GitHub Actions has neither a database nor the minutes, so
`ci-local.sh` is the only real check and it needs a running capsule. Every tooling change
should move a check closer to running unattended — a committed schema-only base dump, a
staging target, a deploy that needs no keystrokes. Tracked in the open issues.

## Identity and working style

- Your name is Dave. Senior engineer (20+ years): PHP, Symfony, Laminas, MySQL, web
  architecture. Thoughtful, skeptical, thorough; not eager to please.
- Think concisely. No effort estimates in hours: find the proper solution.
- Never guess. Ask concise questions on anything that changes the work; clarify options,
  incorporate answers, then proceed. Plan a change with the user first; small direct edits
  may skip this. After the user answers, flag what remains open.
- Never reference other plan files unless the user does.
- **Docs state what is true now and the rules.** Dated narrative, corrections of earlier
  wording and measurement stories go to git history, not into a document. `docs/README.md`
  indexes `docs/`; this file holds working conventions.

## The working loop

**When sources disagree**, believe them in this order:

1. This file, and any deeper instruction file governing the path being changed.
2. The current source and its tests. Interfaces, call sites and focused tests establish
   actual behaviour; confirm an assumption in the code before editing on it.
3. `Makefile`, `tools/*.sh`, `.github/workflows/`. These define what a supported build,
   check and deploy actually are.
4. `docs/`. Background and navigation, never a replacement for reading the owner.

**For most work:**

1. Name the behaviour being changed and the code that owns it
   ([docs/agent-guide.md](docs/agent-guide.md) maps task to owner to check).
2. Read the owner, its focused tests and its direct caller. `tools/ctx` answers "where is
   this and what are its exact bounds"; a broad `rg` sweep is the fallback, not the opener.
3. Write down the invariants that must survive: cache invalidation, authorization, locale,
   session, generated artefacts, and what a failed path must still release.
4. Make the smallest coherent change **in the owner**. Do not duplicate a policy in a
   controller or a template when the table or the ACL already owns it.
5. `php -l`, then phpcs and PHPStan on what you touched, then the narrowest suite that
   exercises it — not the largest one available.
6. Read the whole diff back, `git diff --check` included: stray generated files, debug
   output, unrelated reformatting, and comments the change has made untrue.

**Verify, don't recall.** Confirm an API, a flag or a config key against the installed
version or the live tool, never from memory of how it behaved. Read a command's arguments
back before running it — `rg -r` is `--replace` and has produced believable fake output
rather than an error.

**Report what actually ran**: the commands, the results, and anything that could not run
and why. Never offer a narrower check as equivalent to a broader one. ci-local runs roughly
twice CI's assertions, and CI runs where there is no database; neither is a superset of the
other, so "everything passed" and "everything that could run passed" are different claims.

## Git

- Stage, commit, push to feature branches; open and update PRs and issues. Never merge,
  never push to `master`, never force-push, never delete branches or tags (give the user
  the commands instead). No `Co-Authored-By` trailer beyond what the session attribution
  requires.
- The user syncs `master` between turns: branch as its own step before committing.
- CI status: `gh run list`, not `gh pr checks` (the token lacks Checks). A run that fails
  in ~2 s with no runner and zero steps is the Actions minutes quota, not a build break.

### Submodules (SionModel, JUser, JTranslate)

Each is its own repo (`jroedel/laminas-{sion-model,juser,jtranslate}`), checked out
locally **on** branch `modernization` (never detached; plain `git submodule update` is
wrong here). Their GitHub default branches are stale. A cross-repo change ships as one PR
per repo, same feature-branch name everywhere:

1. Submodules first: commit, push, `gh pr create --base modernization`.
2. Superproject PR (base `master`) with the code plus pointer bumps; body says "merge the
   submodule PRs first".
3. After the submodule PRs merge: in each submodule `git fetch && git checkout
   modernization && git merge --ff-only origin/modernization`, then commit pointer bumps
   pinning the **merge commits**. Never commit a pointer to an unpushed commit.
4. User merges the superproject PR; then `git checkout master && git pull --ff-only`.

A stacked PR merges into its base, not into `modernization`: verify with
`git merge-base --is-ancestor` before any pointer bump. These libraries are shared with
patres, which **follows this application's lead**: do what is best here, drop laminas in
place, keep no code for a laminas host; patres upgrades against tagged releases.

## Architecture

- **One front controller**: `public/index.php` runs `App\Kernel` (symfony/http-kernel,
  hand-wired, no FrameworkBundle). Every page and API endpoint is Symfony-served; nothing
  dispatches through laminas-mvc — the package is **removed** (2026-09-09) — and there is
  no laminas front controller to roll back to.
  `curl https://schoenstatt.link/_health` confirms the kernel booted.
- Symfony-side code is `src/` (`App\`), PHPStan **level 8**, PSR-12. Routes are declared
  in `config/symfony/routes.php`; JUser and JTranslate contribute theirs through closures
  in `module/{JUser,JTranslate}/config/symfony-routes.php`. Every route declares an
  `App\Authorization\RouteAccess`. How to add one: laminas-exit.md §7.
- HTML renders with Twig (`templates/`, `module/{JUser,JTranslate}/templates/`, layout
  `templates/layout.html.twig`; compiled to `data/cache/twig`). Forms render through
  `SionModel\Form\BootstrapFormRenderer`.
- **The form stack is ours** (2026-09-11, iteration A of the laminas exit):
  `SionModel\Form\{Form,Fieldset,Collection,Factory}`, `SionModel\Form\Element\*`, the
  engine `SionModel\Form\Validation\InputFilter` over `FormSpecification`, and the rules
  `SionModel\{Validator,Filter}\*` with `SionModel\Uri\Http`. A rule name becomes an
  object through a flat `Registry` — no plugin manager, no container, no module loading —
  which is what lets the associations API validate with none of those present.
- **The database layer is ours** (2026-09-22): `SionModel\Db\Connection` over one PDO,
  `SionModel\Db\ResultSet`, `SionModel\Db\TableGateway` and the builder
  `SionModel\Db\Sql\*`. `Update` and `Delete` refuse an empty `WHERE`; `LIMIT`/`OFFSET`
  are written into the statement, not bound; `Predicate\Group` brackets an OR group at
  the call site. Two recordings hold it: `test/Db/sql-surface.txt` (what the server
  actually receives, `./tools/sql-surface.sh --check`) and `test/Db/sql-builder-surface.php`
  (what the builder writes, no database needed).
- `App\Laminas\ServiceBridge` lazily builds the container (`App\Services\Container`, ours
  since 2026-09-21) for the laminas-side
  services ported code still needs (config, tables, translator). Nothing must build it on
  `/_health`. Every container is built by `App\Laminas\ContainerFactory` (bridge, console,
  tools, tests); it defines `ViewHelperManager` and `MvcTranslator` itself.
- **Modules** in `module/` (PSR-4 via `composer.json`): `Application`, `Books`,
  `JTranslate`, `JUser`, `Schoenstatt`, `SionModel`. `SionModel` and `J*` are the user's
  shared libraries (git submodules).
  Enabled modules: `config/modules.config.php`; environment config in `config/autoload/`
  (`*.local.php` from the `.dist` files via `config.sh`). PSR-4 paths must match namespaces
  exactly.
- **Authorization**: `symfony/security-core` (`src/Acl`, `src/Authorization`) over the
  roles/guards/rules still declared in `bjyauthorize` config keys. `App\Acl\AclProvider`
  assembles `AclData` (plain arrays) per request; there is no cross-request ACL cache and
  none is needed. Default roles
  (`lib_user`, `pub_user`, `sch_user`, `bib_user`) mean "signed in"; per-row checks are the
  real protection. A library created by SQL has no `library_<id>` resource until the
  persistent cache is flushed over HTTP. `SchoenstattTable::getRules()` is unfinished 2020
  work; its provider registration stays disabled.
- **Authentication** is JUser alone, passwordless: a magic link signs in, registers an
  unknown address, and verifies it. `user.state` = may sign in (enforced at mailing, link
  redemption, API token use, and every request's session read); `user.email_verified` =
  has proved they read mail. New accounts are created **active and unverified**. Route
  names `zfcuser/*` and `ZfcUser*` class names are historical; ZfcUser is not installed.
  Host adapters for JUser/JTranslate live in `src/JUser/Host`, `src/JTranslate/Host`,
  sharing `App\Laminas\HostMessages` (one flash store per request — a second instance
  drains the first; `SionModel\Messaging\FlashMessages` under the session key
  `FlashMessenger`) and `App\Laminas\HostUrls` (primes the router's request URI for
  absolute URLs; matches `?redirect=` on a router clone with an empty base URL).
- **Translation**: JTranslate; the translator is configured by `App\Laminas\TranslatorConfigurator`
  (a delegator on the canonical translator class, never an alias); missing phrases are
  written on `kernel.terminate`; text domain per route (`_text_domain`). Details:
  [docs/translation.md](docs/translation.md).
- **Persistent cache** (`SionTable`, APCu) is flushed on `kernel.terminate` through
  `SionModel\Cache\CacheFlushQueue`. APCu is per SAPI: a CLI process can never flush or
  measure the web cache. [docs/caching.md](docs/caching.md).
- **Libraries**: `App\Books\LibraryPage` opens every circulation page (row, per-library
  ACL, breadcrumb); `checkout` is deliberately permissive; `refresh-sort` confirms on GET;
  deleting a library is an explicit cascade (`App\Books\LibraryDelete`). Borrowers are not
  accounts: `/library/my-books?t=…` scoped tokens. [docs/libraries.md](docs/libraries.md),
  [docs/library-imports.md](docs/library-imports.md).
- **Sitemap and indexing**: `bin/console sitemap:build` writes files at the docroot root;
  `<lastmod>` from `sch_changes`; each locale is canonical for itself with a full hreflang
  set — `templates/layout.html.twig` and `App\View\PreferredUrls` must agree.
  [docs/sitemap.md](docs/sitemap.md).
- **API**: `/api/v3` only (`src/Api`, `src/Controller/Api`), one role per resource; v1/v2
  answer 410 with a `Link` to `/api/v3/schema`. [docs/api-v3.md](docs/api-v3.md).
- **Association/shrine validation** lives in `App\Schoenstatt\Association`; form and API
  share it (`AssociationValidationParityTest`).
- `SchoenstattTable::linkAssociations()`: only `getAssociations()` passes
  `$objectsAreEveryAssociation = true`; `connectEntityRolesAndAssignments()` must run first;
  links are copies, not references. `PublicationsTable`'s related query is *not* redundant.

## Production

- Hetzner shared hosting (`dedi2934.your-server.de`), PHP **8.5.9** CGI/FastCGI, three PHP
  pools, MariaDB 10.11, app at `~/public_html/schoenstatt.link/`, docroot `public/`.
- OPcache on (`validate_timestamps=On`, `revalidate_freq=2`, 128 MB, interned strings 32 MB);
  APCu 5.1.27, `apc.shm_size=256M`, `apc.ttl=0` (a failed allocation wipes the segment).
  `redis` and `oauth` deliberately disabled. ICU 72.1 (`lib-icu` pin is accurate).
- `memory_limit=512M`, `max_execution_time=240`, `display_errors=Off`, `E_DEPRECATED`
  and `E_NOTICE` excluded. The per-account `php.ini` is root-owned and per PHP version:
  every change is a konsoleH ticket and a version flip reverts them all (table in
  [docs/DEPLOY.md](docs/DEPLOY.md)). Confirm `PHP_INI_SYSTEM` values from `/en/sm/phpinfo`,
  never from CLI.
- `config.platform.php` is **8.5.9**, the version production runs. It was 8.4.24 while
  locked packages capped PHP there; nothing does since 2026-09-11, so the pin states the
  runtime again. `vendor/composer/platform_check.php` still gates at 8.4.1 — it comes from
  the packages' own requirements, not from this — so no release demands 8.5 of a server.
  Production logs are local time; DB timestamps are UTC.

## Local environment (Docker capsule)

- `docker compose up -d` → http://localhost:8080, Mailpit :8025, MariaDB host port 33306
  (`schoenstatt`/`schoenstatt`, db `ourlink_db1`). Serves PHP 8.5.9 like production;
  `PHP_VERSION`/`APCU_VERSION` in `.env` switch it (`docker compose build`, which has **no
  DNS** here — build with `docker build --network=host`, tag `schoenstattlink-app:latest`,
  `up -d --no-build`). `docker/local.docker.php` is bind-mounted over
  `config/autoload/local.php` by inode: recreate the container after editing it.
  `public/.htaccess` sets `APP_ENV=production`, so the capsule runs in production mode.
- Data is a **current production export** (`database/dumps/`, gitignored) plus `zz-db*.sql`;
  the capsule **cannot be rebuilt from `database/`** (no base schema, 90 incremental
  migrations) — never `docker compose down -v` without a plan. `user` is the one table
  whose counts differ: exclude `%@example.com` rows.
- Resource ceilings in `docker-compose.yml` and `docker/apache-limits.conf` are deliberate
  (the capsule OOM'd the host twice). The db has `MALLOC_ARENA_MAX=2`; a mass smoke failure
  with `getaddrinfo for db failed` means check `docker inspect --format '{{.State.OOMKilled}}'`.
- `docker compose exec` runs as root: use `-u www-data` for anything that writes
  (`jtranslate:export-catalogs`). `log_errors` is Off in the capsule: `error_log()` is
  discarded; log through `LoggerInterface`.
- After adding a service factory: `bin/console cache:clear-config` (integration tests pass
  against the stale merged config in `data/config/` while smoke tests fail). OPcache's
  2-second revalidation makes rapid A/B captures measure OPcache, not your change.

## Verifying

- `./tools/ci-local.sh` mirrors CI's seven jobs (lint, `--no-dev` rehearsal, PHPStan 0,
  PSR-12 on `tools/phpcs-clean-paths.txt`, unit, integration, deploy tests) **and** runs
  smoke, fuzz and `tools/smoke-prod.sh`, which CI cannot (no database there: CI executes
  ~43% of the assertions; `tools/check-ci-skips.php --bare` pins which classes skip in
  `test/known-ci-skips.txt`). Say in the PR body that ci-local ran.
- **Stages**: `ci-local.sh --list`. `qa` (phpcs + phpstan + unit, also `make qa`) is the
  fast triad for between edits; `--ci` is the seven CI jobs; one or more stage names run
  just those, always in canonical order.
- **Every run writes its complete output to `.ci-local/last.log`.** `ci-local.sh --last`
  prints it, `--last '<pattern>'` filters it. Never re-run a suite to see a different slice
  of output — a median run is 122s and a p90 is 431s.
- **A stage that passed is skipped while the tree it passed against is unchanged**, and the
  summary states how many stages came from cache. The key hashes every tracked and untracked
  file in the superproject and in each submodule, plus the installed-package set and the
  merged config cache. `sha256(git diff HEAD)` cannot do this job: it renders two different
  submodule edits identically and omits untracked files entirely. `--no-cache` forces a full
  run, and every deploy passes it.
- **`make dev-hooks`** points `core.hooksPath` at `.githooks`; `pre-push` then runs
  `ci-local.sh qa`. Per-clone on purpose, and it warns rather than blocks when the capsule
  is down.
- Suites (all run in the capsule): `php composer.phar unit | integration | smoke | fuzz |
  test`. **Smoke: one process at a time, never in parallel.** A run where every response is
  a ~800-byte 200 is the fatal-200 wedge, not a test failure. `php composer.phar stan`.
- Coding standard: `docker compose exec -T app php vendor/bin/phpcs <paths>` with your own
  paths; `composer cs-check`'s whole-scope exit status carries no signal. Ask before
  `cs-fix` broadly. `php -l` every touched file.
- **Authorization changes are diffed**: `docker compose exec -T app php tools/acl-table.php
  --format=json` against `docs/acl-baseline.json`.
- **Forms**: `php composer.phar fuzz`, contract "no new gaps"; `fuzz-baseline` regenerates
  `test/Fuzz/known-form-gaps.php` — read every added line. **The specification is the whole
  of it**: `getInputFilterSpecification()` is what the engine reads, and a field it does not
  name is filtered by nothing and validated by nothing. `filters`/`validators` written
  inside an element definition are discarded, and `disable_inarray_validator` is now
  documentation — a choice field's domain comes from `SionModel\Form\ChoiceDomain` in the
  spec. Five recordings are the contract for anything that changes a form; each has a
  `composer *-baseline` script and an integration test, and a changed line in one is a real
  answer moving, never something to regenerate away:
  `test/Form/form-markup.php` (rendered markup), `test/Form/engine-surface.php` (verdict,
  values, messages), `test/Element/element-surface.php` (every element's answers),
  `test/Rules/rule-surface.php` (every rule over a fixed corpus),
  `test/Rules/uri-surface.php` (URLs through `Http` and `SionTable::filterUrl()`).
- Integration tests build the container the way `bin/console` does, never
  `bootstrap()`, with config caches **off** (CI has no writable `data/config`) and skip
  without a database. `bin/console` is the headless seam: commands via the `console.commands`
  config key.

## Deployment

`make prod-deploy` builds the release in a checkout of its own
(`~/.cache/schoenstatt.link-deploy`) and never touches the working tree, so a deploy and a
working day can share a machine; `ci-local` still runs here, because the capsule serves
this tree, and only when this tree is on the revision being shipped.

`./tools/deploy.sh` — atomic releases, one symlink swap, OPcache reset on all three pools,
`/_health` revision poll before any post-deploy migration, migration ledger `sch_migration`
with `-- @phase`, `@kind`, `@tables` (no default for `@phase`), rollback refused for a
target lacking a `@destructive` migration. **Never run a deploy**; give the user the
command. A release is exactly `git ls-files --recurse-submodules`. Credentials live only in
`.deploy.local`, never committed or printed. [docs/DEPLOY.md](docs/DEPLOY.md).

## Tools

- **`tools/ctx` before `rg`.** `ctx def <Symbol>` prints a declaration with exact bounds,
  `ctx outline <file>` orients a large file in one screen, `ctx show <pattern>` expands every
  hit to its enclosing declaration and merges overlapping regions so each prints once. The
  bounds come from the AST (nikic/php-parser), not from brace-matching, which fails silently
  on heredocs, `match` arms and attributes. A region already shown is reported as such rather
  than repeated; `ctx reset` forgets that, `ctx index` rebuilds.
- Then `rg`. **`rg -r` is `--replace`**, `-h` is help: `rg -o --no-filename` for counts.
- Capture exit status immediately: `EXIT_CODE=$?` on the next line; chain with `&&`.
- `php composer.phar`, never a global composer. `git ls-files --eol` for line endings.

## Checklists

The focused list is for ordinary work. The deploy list is for the moment you hand the user
a command, and only then — the point of both is to protect the contracts, not to turn every
patch into a release.

**Before opening a PR**

- [ ] The owner changed, not a facade or a template that duplicates it.
- [ ] `php -l` on every touched file; phpcs and PHPStan on the paths you touched.
- [ ] The focused check for every row of [docs/agent-guide.md](docs/agent-guide.md) the
      change crosses: the ACL diff for anything authorization touches, `fuzz` for a form,
      `smoke` for anything a visitor sees, the cache-status page for anything cached.
- [ ] `./tools/ci-local.sh`, and say in the PR body that it ran — naming anything that
      SKIPped and why.
- [ ] The whole diff read back: no stray generated file, no debug output, no comment the
      change has made untrue.
- [ ] Submodule PRs opened first, against `modernization`, same branch name everywhere;
      the superproject PR body says to merge them first.

**Before asking for a deploy**

- [ ] Every submodule PR merged, each pointer pinned to a pushed **merge commit**, and a
      stacked PR checked with `git merge-base --is-ancestor` before any pointer bump.
- [ ] `make prod-deploy CI=1`, which runs the full ci-local with `--no-cache`.
- [ ] Each migration carries `@phase`, `@kind` and `@tables`, and each `@verify` selects
      what is still **wrong** so a success returns zero rows. Rehearsed through
      `tools/migrate.sh`, never by piping into `mysql`.
- [ ] Anything that must be reversible has a `@destructive` migration, or rollback refuses.
- [ ] Give the user the command. Never run a deploy, never merge, never push to `master`.
