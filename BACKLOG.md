# Modernization backlog

Working items for the state-of-the-art overhaul (see CLAUDE.md, "Modernization
mandate"). Ordered roughly by intended sequence, not priority.

## Finish SchoenstattTable ACL providers (dormant 2020 WIP)

`Schoenstatt\Model\SchoenstattTable` implements the BjyAuthorize resource and
rule provider interfaces (`getResources()`/`getRules()`), but the feature was
never finished: a duplicate `'bjyauthorize'` config key silently discarded the
provider registration since 2020, and `getRules()` grants access to roles
(`sch_international_leader`, `sch_institute_member`) that don't exist in the
production database — activating it fatals at boot (see commit 988c24d and its
follow-up disabling the registration in
`module/Schoenstatt/config/module.config.php`).

To finish, someone must decide the intended semantics: which real roles should
be allowed on person/association resources, create any missing roles in
`user_role`, re-enable the registration, and cover the behavior with tests.
Also consider the boot-time cost: both methods query all persons/associations
on every ACL build.

## Dead/orphaned ACL guard entries (config rot found 2026-08-01)

Route-config analysis found BjyAuthorize guard entries referencing route names
that don't exist (no-ops today, confusing tomorrow):

- `new-home` (module/Application config)
- `home` (module/Books config)
- `change-password` in `config/autoload/juser.global.php` — the real zfcuser
  child route is `zfcuser/changepassword`, so the change-password page may be
  unintentionally blocked (BjyAuthorize is default-deny). Verify intent before
  fixing.
- `sign-in-no-cookies` route (module/Application) exists but is whitelisted in
  no guard — blocked for everyone under default-deny. Dead feature or bug?
- Bare `checkouts` route (module/Books) is likewise not whitelisted (only its
  `checkouts/library` child is).

## Broken pages found during smoke-suite characterization (2026-08-01)

- `/en/dictionary` → 404: the route in `module/Books/config/module.config.php`
  (~line 1280) declares `DictionaryController` with no `'action'` default, so
  dispatch falls to a nonexistent `indexAction`. One-line config fix; verify
  against production intent first (production may 404 identically).
- [x] `/en/blog` rendered PHP notices into post bodies — fixed 2026-08-02:
  parsedown pinned ~1.7.4 + parsedown-extra ^0.8.1 (both halves of a
  version mismatch); blog smoke test now asserts no leaked notices.

## Phase 2: safety net

- [x] HTTP smoke/characterization suite (38 tests at the time, **52/193
  assertions today**) green against the time capsule; excluded:
  `/en/associations/do-work` (side effects), `/en/dictionary` (broken, see
  above). Run it with `php composer.phar smoke`, one process at a time — see
  "Local capsule: resource ceilings" below for why that matters.
- [x] PHPUnit 9.6 + PHPStan 1.12 phars pinned via `tools/fetch.sh`.
- [x] PHPStan level 0 green with `phpstan-baseline.neon` (56 legacy errors).
- [x] CI v1: PHP 7.4 syntax lint (GitHub Actions).
- [x] Expand CI (2026-08-02): `composer install --no-dev` from the lock on
  PHP 7.4 as a continuous deploy rehearsal (validate + install + autoload
  sanity + `composer audit --locked`); lint job now checks out submodules
  so SionModel/JUser/JTranslate are actually linted. Still local-only:
  PHPStan job (needs a strategy for vendor + baseline in CI) and the
  smoke suite (needs the docker capsule + production dump).
- [x] Fresh production dump (2026-08-01) imported and smoke suite re-verified
  green. Excluded as junk: `bib_*_temp`, `sch_visits_rollover_*`; schema-only
  (no data): `bib_*`, `b_*`, `csp_reports`, `sch_visits` — see
  database/dumps/. The Bible/bibliography sections therefore render empty
  locally; if work ever touches them, revisit what data they need.

## Phase 3: migration ladder

Each rung verified by the Phase 2 suite:

1. [x] Latest ZF3-compatible dependency versions (2026-08-02, commit
   "Phase 3 rung 1"). Composer 1.9 → 2.10; six dead/abandoned packages
   eliminated (zfr-cors → CorsListener, multidots → module/RestApi, talis →
   Books\OpenUrl, mledoze data vendored into JTranslate, libkml unused,
   bjy-profiler); roave/security-advisories superseded by Composer's native
   advisory blocking. 5 VCS forks remain: SlmLocale, BjyAuthorize,
   ZfSnapGeoip (since eliminated), chordpro-php, ZfcUser (since eliminated).
2. [x] ZF → Laminas via `laminas/laminas-migration` (2026-08-02, commit
   "Phase 3 rung 2"). The ZendFrameworkBridge module + dependency-plugin
   carried seven not-yet-migrated third-party modules; four have since been
   eliminated outright (ZfcUser, ZfSnapGeoip, BeaucalInvalidSession,
   zfc-datagrid), leaving **BjyAuthorize, SlmLocale and TwbBundle** as the
   only reason the bridge is still loaded. Dropping the bridge is the
   natural close-out once those three are dealt with at rung 3.
   Temporary pins to revisit at rung 3: laminas-form ~2.14.3,
   laminas-router ~3.3.0 (both lifted at rung 3b, see below).
3. Replace abandoned packages (SwiftMailer → symfony/mailer, PHPExcel →
   PhpSpreadsheet, BjyAuthorize → LM-Commons successor, …) and re-evaluate
   each pinned personal VCS fork in composer.json (3 left: SlmLocale,
   BjyAuthorize, chordpro-php).
   - [x] ZfcUser eliminated (2026-08-02, "Rung 3a"): passwordless
     magic-link auth implemented in JUser itself (not LmcUser — its
     password-centric surface would have been dead weight). Password auth
     is gone app-wide; the `user.password` column is now unread — drop it
     in a later deliberate migration once passwordless has soaked.
   - [x] IP geolocation eliminated entirely (2026-08-02, "Rung 3b"), by
     product decision — the decision carries over to patres, the other
     SionModel/JUser consumer. zf-snap-geoip and its discontinued
     GeoLiteCity.dat are gone (local rescue copy deleted; the .dat still
     exists in production's vendor dir and at the download URL config.sh
     used, see git history). JUser's ipPlace/userWithIp helpers survive
     but render the plain IP (patres views keep working). The Bible
     special-countries rule provider (guest access from CL/AR/PY) was
     deleted: Bible/DH routes now require a logged-in bib_user for
     everyone. Payoff: laminas-hydrator 2.4 → 4.3, laminas-form → 2.17.1
     (XSS advisory PKSA-q54w-zbs8-w6dj no longer fires — its ignore is
     removed), laminas-router → 3.4.5, the abandoned real
     zendframework/zend-router package dropped from the lock, and
     laminas-console removed.
   - [x] Three more dead dependencies eliminated 2026-08-02 (per
     "prefer elimination over rescue"): **zfc-datagrid** and
     **zf2-mobile-detect** (37a59a0) — neither had run since the ZF3
     migration and both cap at PHP 7.x; **beaucal/beaucal-invalid-session**
     (6f6259f) — abandoned since 2016, its 30 lines moved into
     `JUser::onBootstrap`. Removing zfc-datagrid also stranded the two
     "Export" links, which built a `rendererType=PHPExcel` query no PHP has
     read since 2017; if export is wanted back it lands with the
     PhpSpreadsheet migration below.
   - [x] **ocramius/proxy-manager eliminated** 2026-08-02 (8f3d49a, with
     SionModel c648cf6/038557a and JUser 092c0a3). It existed only to back
     the ServiceManager's `lazy_services`, used as a construction-cost
     optimisation in five modules. Removing it exposed a genuine dependency
     cycle those proxies had been deferring past — see "Service-graph
     cycles" below, which lists what is still open.
   - The merged runtime config still contains legacy Zend\* strings from
     un-migrated vendor modules (bridge handles the known ones); sweep
     when those modules are replaced.
   - API magic-code login does NOT auto-create accounts (web flow does);
     an allow-list validator for API registration is an open product
     decision (old @todo in LoginV1ApiController).
4. PHP 8.0 → 8.1 → … → 8.4, one version at a time. (Also upgrade
   firebase/php-jwt to ^7 at the 8.0 rung — see advisory ignore.)
5. Passkeys (WebAuthn) after the PHP 8.1 rung: web-auth/webauthn-lib
   current major, credential table, enrollment inside an authenticated
   session, magic link remains the fallback. Decided 2026-08-02.

## First modernization deploy: DONE 2026-08-02

The modernization branch went to production on 2026-08-02 (PRs #1–#3),
same PHP (7.4.33) as before. Full prod smoke pass that evening: blog with
zero notices, unknown web/API URLs → clean 404s, magic-link sign-in
working, random cold sitemap pages (of ~14,640) with zero fatals — the
fatal-under-HTTP-200 era is over in production. Passwords no longer work
anywhere; sign-in is email magic links.

The living deploy procedure is **DEPLOY.md** (single command:
`php8.0 phploy.phar`; hooks handle submodules, composer, config cache and
APCu). Lessons that shaped it are recorded there and under "Deploy ops"
below.

Leftovers from deploy day, deliberately open:

- [ ] `apc.shm_size` is still 32M in `/home/httpd/php74-ini/ourlink/php.ini`
  — Fr. Jeff cannot edit that file; needs a Hetzner/konsoleH support
  request ("please set apc.shm_size = 256M for PHP 7.4"), then one
  `pkill -u ourlink -f php`. Not urgent: the SionCacheTrait fix makes
  cache-write failures degrade gracefully now; the raise just makes them
  rarer.
- [ ] `public/.htaccess` is untracked/gitignored (since 2017) and was
  hand-edited on the server (`SetEnv APP_ENV` removed → production mode).
  Consider re-tracking a canonical `.htaccess` (or `.htaccess.dist`) so it
  stops being invisible, machine-specific state.
- [ ] Announce passwordless sign-in to users if confused-user replies
  start arriving.

## Deploy ops (post-first-deploy, 2026-08-02)

Current procedure lives in DEPLOY.md. Improvements queued, none urgent:

- **phploy upstream PRs** (banago/PHPloy — served well for years, worth
  fixing): (1) the directory-purge bug: after deleting files it deletes
  their whole parent-directory chains recursively, wiping unchanged and
  even freshly-uploaded files (took out module/JUser/src on deploy day);
  (2) `--list` mode silently skips submodules — the listing code inside
  the submodule loop is unreachable (it sits in the non-list branch).
- [x] Automate the manual steps: done 2026-08-02 — the port-22 exec
  refusal was Hetzner's restricted SFTP jail; the full shell listens on
  port 222. `phploy.ini` now carries `post-deploy[]` hooks (submodule
  rsync via tools/deploy-submodules.sh, then remote config-cache clear +
  `composer install --no-dev`, then the two wgets). Proven end-to-end on
  the PR #3 deploy. Remember: phploy.ini is untracked — on a new machine
  re-add the hooks per DEPLOY.md.
- **Console route for cache clearing**: /sm/clear-persistent-cache is a
  web endpoint gated by a long-lived API key in the URL (appears in
  shell history and access logs). Replace with a CLI command at the
  laminas-cli rung so deploys clear APCu without a web-exposed secret.
- **Real database migrations** (Phinx or doctrine/migrations) instead of
  hand-run dumps in database/. Constraint: the web app's DB user lacks
  DDL rights, so migrations need separate credentials stored only on
  the server (e.g. a config/autoload/migrations.local.php outside the
  web-app config), never in the repo.
- If phploy's limits keep chafing after the PHP 8 rung: evaluate
  Deployer (atomic release dirs + symlink switch would also kill the
  mid-deploy broken window this deploy suffered).

## Shared-library convergence plan (decided 2026-08-02)

The shared submodule repos (laminas-sion-model, laminas-juser) have a second
line of history: patres was migrated to Laminas in 2020–2022 on the `1.0.x`
branches (31/27 commits of PHP 8 typing, refactors, Mailer rework — frozen
since 2022-12, requires PHP ^8.0, JUser side still password/ZfcUser/geoip
based). Our `modernization` branches forked from the older commits
schoenstatt.link had pinned, so the lines share no recent history and cannot
be git-merged — both rewrote the same files.

Decision: **converge on `modernization`; treat `1.0.x` as a frozen review
source and port, don't merge.**

- [x] First ports done 2026-08-02: cache-dependency learning fix into
  SionModel (from 1.0.x 04fb783); "email verified on every token
  redemption" semantics into JUser (from 2020 master 8c3beda) with a
  smoke regression test. Replay-attack protection (7abae4c) was verified
  already present and stronger in the rung-3a implementation.
- At each remaining rung, diff the files being touched against `1.0.x`
  first ("Simplify cacheKeys"/SionCacheService and the typing work belong
  naturally to the PHP 8 rung; Mailer work to the symfony/mailer rung).
- Sweep the `1.0.x`/old-master language-file additions (de/en/es/pt) when
  doing an i18n pass over the new auth views/emails.
- After the PHP 8 rung stabilizes the shared libs: migrate patres onto the
  converged line (it inherits passwordless auth, geoip removal, the
  fatal-200 fix), then retire the `0.3.x`/`1.0.x` branches.
- NOTE: patres also inherits the service-graph cycle work — it shares the
  SionTable/UserTable base classes and the eager-identity factories, so it
  will hit the same recursion the moment it drops proxy-manager. Read
  "Service-graph cycles" below before starting that migration; the two open
  items there (eager identity, `$entityProblemPrototype`) need checking
  against patres's own subclasses, which are not visible from this repo.
- NOTE: patres's `1.0.x` SionCacheService still contains the fatal-200
  `unset($this->memoryCache)` bug (worse on PHP 8: typed property →
  immediate Error). Interim fix pushed as branch
  `fix/cache-write-failure-wedge` on laminas-sion-model; the PR against
  `1.0.x` must be opened by hand (the gh token lacks access there).

## Service-graph cycles (found and cut 2026-08-02, branch-only)

Dropping `ocramius/proxy-manager` (and with it `lazy_services`) exposed a
four-way cycle in the shared libraries that the lazy proxies had been quietly
deferring past, probably for years:

```
JUser\Model\UserTable          (a SionTable — its constructor asks for...)
  -> SionModel\Service\ProblemService
  -> SionModel\Problem\ProblemTable
  -> JUser\AuthService          (needs the UserTable still being built)
  -> JUser\Model\UserTable
```

Every route recursed through `ServiceManager::doCreate` until it hit the 512M
`memory_limit`, then answered with a PHP fatal under HTTP 200 — the same
*symptom* as the family below, an unrelated *cause*. It never reached
production: the whole episode lived on `feat/php-83`.

Cut in two places, both lossless because `ProblemTable` is read-only:
`ProblemTableFactory` no longer resolves `AuthService` just to derive an acting
user id it discards, and `SionTable` now resolves the UserTable lazily in
`getUserTable()` instead of during construction (038557a), which closes the
class of cycle rather than the one instance and removed the ad-hoc
`! $this instanceof X` guards.

Still open:

- [x] **Eager-identity pattern retired app-wide (2026-08-02).** The count was
  never four: thirteen factories resolved `JUser\AuthService` during
  construction (all eight app-module table factories, three in SionModel,
  `TranslationsTableFactory`, plus `UserTableFactory` passing a hardcoded
  null around its 2014 "@todo how can we get an identity if the process
  requires this very UserTable?"). All now inject
  `SionModel\Service\ActingUserProviderInterface` — one method,
  `getActingUserId(): ?int` — implemented by
  `JUser\Service\AuthServiceActingUserProvider`, which resolves AuthService
  lazily on first call and never caches the id. `SionTable` (and
  `TranslationsTable`, which had the same disease without being a SionTable —
  it froze a whole `User` object) consult it at write time via
  `getActingUserId()`. Consequences beyond the cycle class being closed:
  - a mid-request login (magic-link redemption) is now observed — the old
    scalar was frozen at whatever identity existed when the container first
    built the table, so post-redemption writes stamped `createdBy = null`;
  - user-role inserts finally stamp `create_by` (UserTable had passed null
    since 2014);
  - `SionTable::setActingUserId(?int)` survives as an explicit override that
    wins over the provider — the Books API controllers need it because they
    authenticate by JWT (`tokenPayload->sub`), not session, so the provider
    sees no identity on those requests. `getActingUserId()` stayed public for
    `SionModel\Mailing\Mailer`'s `mailingBy` stamp.
  Verified 2026-08-02: smoke 52/52, PHPStan level 0 clean, phpcs
  neutral-or-better on every touched file.
- [ ] **`SionTable::$entityProblemPrototype` is still resolved eagerly**, on
  purpose. Subclasses read it as a raw property (`clone
  $this->entityProblemPrototype` in `Books\Model\LibraryTable` and
  `Schoenstatt\Model\SchoenstattTable`), so moving it behind a lazy getter
  would silently hand them null. Any patres subclass has the same exposure —
  check both consumers before touching it.
- [ ] **patres will hit this identically** when it converges onto the
  `modernization` line: same base classes, same eager-identity factories. See
  the convergence plan above. The provider seam now ships with the shared
  libs, so patres's migration is mechanical: convert its table factories to
  inject `ActingUserProviderInterface`, and convert any subclass that read
  `$this->actingUserId` raw — the property is gone; `getActingUserId()` (now
  public) replaces it. Check patres for `setActingUserId()` callers: the
  method survives but is an override for token-auth contexts, not the primary
  channel.

Technique worth reusing: temporarily patch
`vendor/laminas/laminas-servicemanager/src/ServiceManager.php` so `get()` pushes
each name onto a per-container stack and dumps it on the first repeat. It named
the cycle in a single request, and running the smoke suite under it proved no
cycle survives anywhere. Key it by `spl_object_id($this)`, not globally — a
plugin manager delegating a name upward to the parent container is not a cycle
and a global stack reports it as one.

## Local capsule: resource ceilings (added 2026-08-02, after two host OOMs)

The time capsule could consume the whole development machine, and twice did —
15.5 GB of host RAM exhausted and all eight cores pinned. Two compounding
causes: nothing bounded the containers, and the stock `php:*-apache` image
allows **150** mpm_prefork workers, which against the 512M `memory_limit` that
`public/index.php` sets is a ~75 GB ceiling. The trigger was the smoke suite
being run as several concurrent processes against an app that was fataling on
every route (the cycle above).

Now bounded in three layers — `docker-compose.yml` (app 4g/2 CPUs, db 1g,
mailpit 256m, `memswap_limit == mem_limit` so a leak dies instead of thrashing
swap), `docker/apache-limits.conf` (6 workers, recycled every 200 connections,
60s Timeout) and `docker/php-limits.ini` (60s `max_execution_time`). Sizing is
measured, not guessed: a flat 3g was observed OOM-killing a worker at full
concurrency, so the app gets 4g and the cgroup backstops runaways rather than
ordinary requests. Verified at 40 concurrent requests: 6 children, ~200% of
800% CPU, 1.0 GB peak, no kills.

- **Run the smoke suite with `php composer.phar smoke`, one process at a
  time.** Never fan it out across parallel agents or shells. This is also in
  CLAUDE.md.
- Changing a limit needs `docker compose build && docker compose up -d`.
- Useful when diagnosing: `docker compose exec -T app cat
  /sys/fs/cgroup/memory.events` reports `oom_kill` counts, and `anon` in
  `memory.stat` separates real RSS from page cache (`docker stats` conflates
  them, which will otherwise mislead you).
- [ ] Production runs FastCGI, not prefork, so none of this applies there —
  but the same question ("what bounds concurrent PHP memory?") has never been
  asked of the Hetzner account. Worth checking at the PHP 8 rung.

## Fixed: the fatal-under-HTTP-200 family (2026-08-02, DEPLOYED)

Two independent bugs produced fatals under HTTP 200; both fixed and live
in production as of 2026-08-02. (A third cause of the same symptom turned up
later that day during the proxy-manager removal — see "Service-graph cycles"
above. It never left the branch, but it is a reminder that an ~800-byte
HTTP 200 is a *symptom*, not a diagnosis.)

1. **Cold-page cache wedge**: SionCacheTrait's failed-write handler did
   `unset($this->memoryCache)`, destroying the declared property; every
   later access fell through to AbstractTableGateway::__get() and fataled
   on every request until APCu was cleared. Fixed in SionModel (assign []
   instead). Trigger was APCu exhaustion at the 32M default — raising
   prod's apc.shm_size is still open (see deploy leftovers above); the
   patres line has the same bug, fix branch awaiting its PR.
2. **Every unknown URL** (since ~2020): the multidots '/:*' catch-all
   route named int-404 + default-deny guard + RedirectionStrategy
   explode() TypeError. Fixed in PR #2: catch-all scoped to /api as
   'api-route-not-found'; JUser's strategy hardened; unknown web URLs now
   render the normal error/404 page. Related: public/index.php no longer
   forces display_errors outside development, and the blog's parsedown
   version mismatch (leaked notices) is fixed — errors go to logs, not
   visitors.

Advisory debts consciously carried (documented in composer.json
`config.policy`), to be paid at the rung named:

- `phpoffice/phpexcel` — multiple XSS + one high XXE advisory; used by 3
  files (library import + 2 export views, all behind auth). Pay at rung 3
  with the PhpSpreadsheet migration.
- `firebase/php-jwt` 6.11 — low-severity CVE-2025-45769; the fixed v7
  requires PHP >= 8.0. Pay at rung 4.

Removed dev nicety: bjy-profiler DB query profiling (DbAdapterServiceFactory
now always returns a plain adapter). If query profiling is missed, pick a
maintained tool after the Laminas migration.

The countries dataset is now static (module/JTranslate/data/countries.json,
vendored 2026-08-02 from mledoze/countries 1.8); refresh occasionally from
upstream if country data matters.
