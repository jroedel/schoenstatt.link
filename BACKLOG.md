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

- [x] HTTP smoke/characterization suite (38 tests at the time, **52 tests /
  193 assertions today**) green against the time capsule; excluded:
  `/en/associations/do-work` (side effects), `/en/dictionary` (broken, see
  above). Run it with `php composer.phar smoke`, one process at a time — see
  "Local capsule: resource ceilings" below for why that matters.
- [x] **Unit suite added 2026-08-02** (`test/Unit`, 7 tests / 40 assertions),
  born with the cakephp elimination in rung 4 below. It needs neither a
  running app nor `vendor/`: each test requires the class under test directly,
  so it stays valid while vendor is mid-migration — the same reasoning
  `test/bootstrap.php` already applies to the smoke suite. Unlike smoke it is
  safe to run freely (no HTTP, no capsule load). Three scripts now:
  `php composer.phar smoke` (smoke only), `unit` (unit only), `test` (both,
  59/233). All three shell into the capsule because the host PHP lacks the
  dom/mbstring/xmlwriter extensions PHPUnit needs.
  Debt: tests for shared-library classes currently live in the app repo
  (`test/Unit/TextTest.php` covers `SionModel\Text\Text`) because the
  submodules have no test infrastructure of their own. They should migrate
  into laminas-sion-model/laminas-juser/laminas-jtranslate eventually so
  patres inherits them.
- Cosmetic: `phpstan-baseline.neon` still carries a pre-Laminas entry naming
  `Zend\Stdlib\ResponseInterface` (in the `LibraryImportsController` ignore).
  Harmless — it still matches — but it will confuse the next reader.
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
   - CORRECTION (2026-08-02, verified against packagist): two of these
     framings are wrong, and one is right for the wrong reason —
     symfony/mailer turns out to be *required* to reach PHP 8.4, because
     `laminas-mail` caps there and JUser/SionModel run two separate mail
     stacks. See rung 4 below. **BjyAuthorize needs no successor** — upstream
     `kokspflanze/bjy-authorize` 3.0.0 supports PHP 8.2–8.5, so the item is a
     fork retirement, not an LM-Commons migration. **SwiftMailer is not a
     PHP 8 blocker** — 6.3.0 declares `php >=7.0.0` and installs on 8.3, so
     symfony/mailer stays decoupled from rung 4 (still worth doing: the
     package is abandoned). `slm/locale` 1.2.0 supports 8.3, retiring that
     fork too. TwbBundle 3.3.1 declares `>=5.3.2` so it installs freely; its
     risk is runtime deprecations, not resolution — and once bjy-authorize
     and slm/locale are on Laminas-native upstreams, **TwbBundle is the only
     remaining reason the ZendFrameworkBridge is loaded.**
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
4. PHP 8.3. **The app already runs green on 8.3** (2026-08-02): capsule
   flipped via `PHP_VERSION=8.3`/`APCU_VERSION=5.1.24` in `.env`, one real
   blocker found and fixed (JTranslate `adcdd8a`: a vestigial PDO bound
   param that 7.4's emulated prepares ignored and 8.0+ rejects with HY093 —
   it ran in `Application::onBootstrap`, so it fataled *every* route), then
   smoke 52/52 on PHP 8.3.33. A full-suite run under `E_ALL` logging found
   **zero deprecations from our own modules**; all of the ~50-per-request
   noise is old vendor Laminas predating 8.1 return-type enforcement, masked
   by `index.php`'s `~E_DEPRECATED`. So the "one version at a time" plan is
   moot — the remaining work is not runtime, it is composer resolution.

   **CORRECTION (same day): this is a 45-package job, not the 8-package one
   first written here.** The first pass scanned for constraints excluding all
   of 8.x and so missed the dominant pattern — `^7.3 || ~8.0.0` caps at
   *8.0*, which excludes 8.3 just as firmly. `composer why-not php 8.3`
   reports **45** blockers, and `why-not php 8.5` also 45. Essentially the
   entire Laminas MVC stack is pinned at its 7.4-era release.

   Why the app nonetheless runs green on 8.3: `config.platform` pins
   `php 7.4.33`, so Composer resolves as though PHP were 7.4 while the
   runtime is actually 8.3. The stack *runs* fine — support is **undeclared,
   not absent**. Rung 4 is therefore about honest declaration and regaining
   security updates, not about fixing breakage.

   **The ceiling is PHP 8.4, not 8.5.** `laminas/laminas-mvc`'s newest
   release (3.8.0) declares `~8.1.0 || ~8.2.0 || ~8.3.0 || ~8.4.0`; no
   laminas-mvc release supports 8.5. That is upstream and nothing in this
   repo changes it, so 8.5 is out of reach regardless of Hetzner offering it
   (noted 2026-08-02 — recheck when laminas-mvc ships 8.5 support).

   Two further packages cap the target at 8.3 unless dealt with:
   `laminas-zendframework-bridge` (1.8.0 → `~8.1–~8.3`) and `laminas-mail`
   (2.25.1 → `~8.1–~8.3`). Both are removable rather than upgradable, which
   is what makes 8.4 reachable — see the staging below.

   Representative targets for the stack bump (all support 8.2–8.5 unless
   noted); the majors are where the actual work is:

   | package | locked | target | note |
   | --- | --- | --- | --- |
   | `laminas/laminas-mvc` | 3.2.0 | 3.8.0 | **caps at 8.4** — the ceiling |
   | `laminas/laminas-form` | 2.17.1 | 3.24.2 | major, real API change |
   | `laminas/laminas-validator` | 2.14.6 | 3.18.0 | major, real API change |
   | `laminas/laminas-filter` | 2.12.0 | 3.4.0 | major |
   | `laminas/laminas-servicemanager` | 3.7.0 | 4.5.1 | major; touches every factory |
   | `laminas/laminas-view` | 2.12.1 | 3.1.0 | major |
   | `laminas/laminas-code` | 3.5.1 | 4.17.0 | major |
   | `laminas/laminas-cache` | 2.9.0 | 4.3.0 | major; sized below |
   | `laminas/laminas-db` | 2.12.0 | 2.22.0 | minor |
   | `laminas/laminas-i18n` | 2.12.0 | 2.33.0 | minor |
   | `laminas/laminas-session` | 2.11.0 | 2.27.0 | minor |
   | `laminas/laminas-http` | 2.14.3 | 2.23.0 | minor |
   | `laminas/laminas-hydrator` | 4.3.2 | 4.19.0 | minor |
   | `laminas/laminas-navigation` | 2.15.0 | 2.23.0 | minor |
   | `laminas/laminas-permissions-acl` | 2.8.1 | 2.18.0 | minor |
   | `kokspflanze/bjy-authorize` | 1.7.1 (fork) | 3.0.0 | retires a fork |
   | `slm/locale` | 0.3.0 (fork) | 1.2.0 | retires a fork |
   | `laminas/laminas-serializer` | 2.9.1 | 3.3.0 | mechanical |
   | `laminas/laminas-log` | 2.12.0 | 2.17.1 | mechanical |
   | `laminas/laminas-developer-tools` | 1.3.2 | 2.10.0 | dev-only |
   | `spatie/schema-org` | 2.16.0 | 4.0.2 | two majors to review |
   | `firebase/php-jwt` | 6.10.0 | ^7 | clears the deferred CVE ignore |

   Because every target requires ≥8.1, none can be bumped individually under
   the 7.4 pin — the platform pin and the whole set move in a single
   `composer update` resolution pass.

   - [x] **`cakephp/core` + `cakephp/utility` eliminated** (2026-08-02). They
     declared `>=5.6.0,<8.0.0` and so blocked every PHP 8 target while being
     used for nothing but four string functions: `Cake\Utility\Text`'s
     `truncate` (×6), `highlight` (×2), `excerpt` and `truncateByWidth`,
     across 7 view scripts. (The `Text::class` in `Bible\Form\BibleSearchForm`
     is `Laminas\Form\Element\Text` — unrelated; an earlier note here was
     wrong.) Replaced by `SionModel\Text\Text`, a deliberate drop-in in the
     shared library — one of the call sites is a SionModel view, and patres
     inherits the fix — so only the `use` statements changed.
     Verified by differential testing rather than by reading: a script ran
     both implementations over 33 cases (accented Spanish, CJK full-width,
     regex metacharacters in search terms, needle arrays, `limit`,
     `exact => false`) while cakephp was still installed — **0 mismatches** —
     and Cake's own output was then baked into
     `test/Unit/fixtures/text-expectations.json`. This added the repo's first
     **unit** test suite (`test/Unit`, `php composer.phar unit`; `test` runs
     both suites). Two deliberate divergences: the `html` option is not ported
     and throws rather than silently returning differently-shaped output, and
     `removeLastWord` implements the documented intent instead of Cake's bug
     there (`mb_strrpos($text, $spacepos)` passes a position where a needle
     belongs). Blockers 45 → 43.

   **Resolution dry-runs, 2026-08-02** (`config.platform.php` temporarily set
   to 8.3.32 + `require.php` `~8.3.0`, `composer update --dry-run -W`,
   composer.json restored afterwards — the lock was never touched). Composer
   reports root-level problems one at a time, so this was iterated. Only
   **root** constraints need editing; the other ~40 blockers are transitive
   and float up on their own. In order, the dry-run demanded:
   1. `slm/locale` `^0.3` → `^1.2`, and drop the SlmLocale fork from
      `repositories`
   2. `laminas/laminas-form` `^2.17.1` → `^3.24`
   3. `kokspflanze/bjy-authorize` `^1.7` → `^3.0`, and drop the BjyAuthorize
      fork from `repositories` (leaves `chordpro-php` as the only fork)
   4. `spatie/schema-org` `^2.2` → `^4.0`
   5. **`laminas/laminas-mvc-form` must be removed, not bumped** — it is a
      metapackage whose newest release (1.2.0) pins `laminas-form ^2.17.0`, so
      it can never coexist with form 3.x. It bundled only laminas-code,
      laminas-form and laminas-i18n; require those directly.
   6. **`laminas/laminas-dependency-plugin` must be removed entirely** — a
      hard dead end, not a version bump. Releases ≤2.5 cap below PHP 8.3, and
      2.6/2.7 require `composer-plugin-api >=1.1.0 <2.3.0` while our Composer
      2.10 provides 2.9.0. There is no installable version.

   **That last one forces TwbBundle into rung 4a.** The plugin's job is
   rewriting `zendframework/*` requires to `laminas/*` at install time, and
   `neilime/zf2-twb-bundle` 3.3.1 requires **fourteen** `zendframework/*`
   packages (config, escaper, form, i18n, loader, log, modulemanager, mvc,
   serializer, servicemanager, stdlib, view, navigation). Without the plugin
   those resolve to the real abandoned zendframework packages, which cap at
   PHP 7.x. So TwbBundle is a hard blocker for declaring *any* PHP 8 version,
   not the runtime-deprecation risk assumed above — it cannot wait for 4b.
   (The fork of BjyAuthorize pulls in 8 more `zendframework/*` requires;
   upstream 3.0.0 is Laminas-native, so bumping it clears those.)
   Options for TwbBundle, to decide next session: find a Laminas-native
   successor, run laminas-migration over a fork of it, or eliminate it and
   render Bootstrap markup directly — note it renders forms app-wide, so
   elimination is the largest of the three.

   **Staging decided 2026-08-02 — rung 4a to 8.3, rung 4b to 8.4** (4a scope
   grew: TwbBundle moved in, per the dry-run finding above):
   - *4a*: the 45-package stack bump + the cakephp elimination, declaring
     8.3. Keeps `laminas-zendframework-bridge` (1.8.0) and `laminas-mail`
     (2.25.1), both of which support 8.3. Big but self-contained, and it
     brings the security-relevant stack current.
   - *4b*: drop the bridge — which requires resolving **TwbBundle**, the only
     remaining module needing it — and consolidate mail onto
     **symfony/mailer**, which retires `laminas-mail`. Then 8.4.

   **Rung 4a: DONE 2026-08-03** (feat/php-83). The stack bump landed; the app
   declares `~8.3.0` and `config.platform` pins 8.3.33 / ICU 76.1 / APCu
   5.1.24. Verified: unit 12/12, smoke 52/52 (incl. the magic-link
   round-trip), phpcs adds no new violations, and a new form-markup
   regression harness (`tools/form-regression.php` — capture/compare/probe
   over 27 pages with an all-roles account; baseline in gitignored
   `data/form-regression/`) proves the ONLY rendering change app-wide is
   laminas-form 3 emitting minimized boolean attributes (`required` vs
   `required="required"`). What actually resolved, with corrections to the
   2026-08-02 dry-run:
   - **TwbBundle**: adopted `diablomedia/laminas-twb-bundle` ^5.0 — community
     fork (lineage neilime → thomasvargiu → diablomedia) that keeps the
     `TwbBundle\` namespace and module name, targets laminas-form 3, has
     CI/PHPStan. Insurance fork under our control:
     github.com/jroedel/laminas-twb-bundle. Its `php ~8.1||~8.2||~8.3`
     constraint is a wall for the 8.4 rung — PR upstream first, else flip to
     the fork via a `repositories` entry.
   - **CORRECTION: `kokspflanze/bjy-authorize` 3.x is a Mezzio package** (no
     Module class, requires `mezzio/*`; composer resolves it but the app can
     never boot it). The MVC line ends at **2.4.4**, which is what installed.
     Config keys carry over; one rename applied (`Provider\Role\ZendDb` →
     `LaminasDb` in JUser). BjyAuthorize 2.x can cache the assembled ACL —
     explicitly disabled in acl.global.php for 1.7 parity; apcu-backed ACL
     caching is a deliberate future decision.
   - **SM 4 / view 3 / validator 3 / filter 3 are all unreachable** in this
     dependency set: mvc 3.8 pins servicemanager ^3.20 + view ^2.18, and bjy
     2.4.4 + slm/locale 1.2 pin SM ^3. Settled: SM 3.24, view 2.44,
     validator 2.65, filter 2.42 — so no factory-signature sweep was needed
     this rung. The Interop→Psr + `: mixed` sweep (~102 factories) stays
     queued as SM4 prep for whenever SM4 becomes reachable.
   - **laminas-zendframework-bridge is GONE already** (was planned for 4b):
     with TwbBundle swapped, nothing required it. `laminas-mail` 2.25.1 is
     now the main remaining 8.4 cap among laminas packages.
   - laminas-cache went to **3.14**, not 4.x (bjy 2.4.4 caps it). Instead of
     reshaping configs, `SionModel\Cache\LegacyCacheConfig::translate()`
     (unit-tested) accepts the old StorageFactory shape — production's
     untracked cache.local.php needs NO deploy-time rewrite; the deploy
     gotcha below is neutralized. Adapters added as packages: apcu,
     filesystem, memory (bjy's default cache store). `laminas-serializer`
     and `laminas-log` re-added as root requires (both were transitive
     before; log is abandoned upstream — PSR-3/monolog migration is a future
     elimination item, ~15 files).
   - laminas-db 2.22 rejects `new TableGateway('')` — SionTable's generic
     gateway removed; the raw-SQL path uses the adapter directly and the
     never-viable bare-`$where` path now throws with guidance.
   - laminas-form 3 fixes: `SionModel\Form\Element\Phone` return types,
     JUser `EditUserForm` checkbox values quoted, Schoenstatt
     `EditAssignmentForm` `setValidationGroup(array)`, Books
     `FormSelectWithoutOptions::renderOptions(): string`, three Schoenstatt
     form factories de-polyfilled. NOTE: `formRow` now genuinely resolves to
     `SionFormRow` (diablomedia registers it under `aliases`, our
     `invokables` entry wins; under neilime it was accidentally dead code) —
     help-block strings no longer pass through JTranslate; zero markup drift
     observed on the 27 regression pages.
   - Deferred micro-items: `firebase/php-jwt` ^7 (clears the CVE ignore),
     the two Application factories on the root-namespace `FactoryInterface`
     shim, `layout.phtml`'s `getHelperPluginManager()->getServiceLocator()`
     (fine on view 2.44, dies at view 3).
   - Pre-existing latent bugs surfaced by the harness/audits (not caused by
     this rung): `/libraries/create` and `/libraries/:id/edit` 500
     (`Books\Form\SearchForm` factory throws "only for a specific library"
     without library context), `/blog/create` 500 (template
     `books/blog/create` missing), `SionModel\Controller\FilesController`
     calls `$this->getServiceLocator()` which AbstractController hasn't had
     since ZF3, `module/Books/src/Filter/BlogPostUserIdFilter.php` declares
     `namespace Schoenstatt\Filter` (PSR-4 collision with the real
     Schoenstatt class), and `SionForm::setData()` calls
     `getInputFilterSpecification()` the base class doesn't define.
   - **Deploy prerequisite**: production must switch to PHP 8.3 in the same
     deploy — `require.php` and the platform pin both say 8.3 now. Sequence
     it explicitly in DEPLOY.md alongside the phploy run.

   **The mailer migration is load-bearing, not optional polish.** There are
   currently *two* mail stacks: JUser sends the magic-link mail through
   **SwiftMailer** (`SwiftMailerFactory`, `Service\Mailer`, `MailerFactory`)
   while `SionModel\Mailing\Mailer` uses **laminas-mail**
   (`Laminas\Mail\Message`, `AddressList`). symfony/mailer collapses both and
   removes the laminas-mail 8.3 cap. Urgency note: SwiftMailer 6.3.0 lints
   clean under PHP 8.5.8 (whole tree checked 2026-08-02), so unlike PHPExcel
   it is not an immediate breakage — it is abandoned-since-2021 plus half of
   a duplicated stack.

   **laminas-cache 2.9 → 4.x, sized 2026-08-02.** Smaller than feared. The
   item API (`getItem`/`setItem`/`getItems`/`removeItem`, `$success`,
   `$casToken`) and `StorageInterface` are unchanged, so SionCacheTrait's
   ~10 call sites and `Books\Service\DriveGateway` need nothing. The break is:
   - `Laminas\Cache\StorageFactory::factory()` was removed in 3.0 — **3 call
     sites**: `Application\Service\CacheFactory`,
     `JUser\Service\CacheFactory`, `JTranslate\Service\CacheFactory`. They
     inject `Laminas\Cache\Service\StorageAdapterFactoryInterface` and call
     `createFromArrayConfiguration()` instead.
   - storage adapters are separate packages now: add
     `laminas/laminas-cache-storage-adapter-apcu` and `-filesystem`.
   - the config array shape changes (`adapter.name` → `adapter` as a plain
     string, `ttl` moves into `options`, `plugins` entries become
     `['name' => …]`): `config/autoload/cache.local.php.dist`,
     `jtranslate.global.php`, `juser.global.php`,
     `module/JTranslate/config/module.config.php`.
   - **deploy gotcha**: `config/autoload/cache.local.php` is untracked,
     machine-specific state (same disease as `public/.htaccess`), so
     production's server-side copy must be reshaped *during* the deploy or
     the app fatals on boot. Plan that step explicitly in DEPLOY.md.
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

- `phpoffice/phpexcel` — multiple XSS + one high XXE advisory. **Reassessed
  2026-08-02: this is a hard PHP 8 blocker, not just an advisory debt, and
  the feature is already silently dead on 8.3.** PHPExcel 1.8.2 does not
  merely deprecate under PHP 8 — it fails to *parse*
  (`Array and string offset access syntax with curly braces is no longer
  supported`, Shared/String.php:526, removed in 8.0). The smoke suite is
  green on 8.3 only because no test autoloads a PHPExcel class. Scope is
  also far smaller than recorded here: not 3 files but **one method, six
  calls** — `importSpreadsheetFile()` in `LibraryImportsController`. The two
  export views died with zfc-datagrid. All six calls (`IOFactory::load`,
  `getSheetByName`, `getHighestDataRow`, `getHighestDataColumn`,
  `rangeToArray`, `disconnectWorksheets`) survive unchanged through
  PhpSpreadsheet 5.x.
  The feature is live and worth migrating rather than eliminating: 14 rows in
  `lib_imports`, all `.xlsx`, library 3 (Colegio Mayor), last successful
  import 2019-09 (annual *jornada de trabajo* sessions). The 2021 attempt is
  still `pending` because the form takes a hand-typed *server* path and
  someone entered a Windows desktop path — the `@todo` about accepting a real
  upload is the actual product bug here.
  Decided 2026-08-02: go straight to `^5` (needs `php ^8.2`), which means the
  rung-4 platform bump lands **before** this, not after; extract the reading
  into a testable `Books\Service\SpreadsheetReader` rather than swapping
  class names in place; verify by golden-master diff against a real
  historical production `.xlsx`. Watch one specific behavior change:
  PhpSpreadsheet 1.28 made `toFormattedString` always return a string, which
  changes `rangeToArray` output. Most consumers are safe (`copyrightYear`,
  `withinLibraryId`, `publicationId` all go through `is_numeric` + `(int)`),
  but `$foundAValue = … || isset($rowColumns[$columnIndex])` is not: if empty
  cells come back `''` instead of `null`, empty-row skipping breaks silently
  and blank books get created. Also add a null guard on `getSheetByName()` —
  a wrong worksheet name currently fatals on `null->getHighestDataRow()`.
  `config.platform` needs seven more ext pins for PhpSpreadsheet
  (`ctype`, `fileinfo`, `iconv`, `libxml`, `xmlreader`, `zip`, `zlib`); the
  capsule has all thirteen it wants. **Verify them on production first** via
  the authenticated `/sm/phpinfo` page — `config.platform` overrides real
  platform detection on the server too, so a wrong pin converts a resolution
  error into a runtime fatal.
- `firebase/php-jwt` 6.11 — low-severity CVE-2025-45769; the fixed v7
  requires PHP >= 8.0. Pay at rung 4.

Removed dev nicety: bjy-profiler DB query profiling (DbAdapterServiceFactory
now always returns a plain adapter). If query profiling is missed, pick a
maintained tool after the Laminas migration.

The countries dataset is now static (module/JTranslate/data/countries.json,
vendored 2026-08-02 from mledoze/countries 1.8); refresh occasionally from
upstream if country data matters.
