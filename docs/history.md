# Modernization history

Closed work, moved out of [BACKLOG.md](BACKLOG.md) on 2026-08-04 so the backlog
can stay a forward-only roadmap. This file preserves outcomes, corrections, and
the techniques worth reusing. The journal as originally written — every DONE
narrative in full — is preserved in git: `git log --follow docs/BACKLOG.md`
(the last full-journal version is the one this restructure replaced).

State reached by 2026-08-04: production runs PHP 8.3.33 on a current Laminas
stack, master is fully deployed, `composer audit --locked` reports zero
advisories, and 167 tests run across three suites (smoke 73 / unit 82 /
integration 12).

## Phase 1–2: time capsule and safety net (2026-08-01/02)

- Docker "time capsule" reproduces production: Apache + PHP (switchable
  7.4/8.3) + MariaDB 10.11 + Mailpit + APCu, seeded from a production dump.
  Fresh 2026-08-01 dump imported (junk tables excluded; `bib_*`, `b_*`,
  `csp_reports`, `sch_visits` schema-only — Bible/bibliography render empty
  locally).
- Three test suites, all run inside the capsule (host PHP lacks the extensions
  PHPUnit needs): HTTP smoke/characterization (`test/Smoke`, against the
  running capsule), unit (`test/Unit`, vendor-free by design — each test
  requires its class directly, so it stays valid mid-migration), integration
  (`test/Integration`, vendor-dependent, capsule-only).
- PHPStan level 0 green over a 56-error baseline; phars pinned via
  `tools/get-phars.sh` (PHPUnit 9.6 → 12.5 on 2026-08-03; CI and capsule run
  the identical phar).
- CI: PHP syntax lint (submodules included), then a continuous deploy
  rehearsal — `composer install --no-dev` from the lock + validate + autoload
  sanity + `composer audit --locked`. Moved to PHP 8.3 with a unit-suite job
  after rung 4a.

## Phase 3, rungs 1–2: latest ZF3, then Laminas (2026-08-02)

- Rung 1: Composer 1.9 → 2.10; every dependency at its latest ZF3-compatible
  release. Eliminated rather than upgraded (per "prefer dependency
  elimination"): zfr-cors → `Application\Listener\CorsListener`,
  multidots/zf3-rest-api → `module/RestApi`, talis → `Books\OpenUrl`,
  mledoze countries data vendored into JTranslate, libkml (unused),
  bjy-profiler (dev-only); roave/security-advisories superseded by Composer's
  native advisory blocking.
- Rung 2: `laminas/laminas-migration` rewrote the tree. The
  ZendFrameworkBridge module carried the not-yet-migrated third-party modules
  until rung 4a removed the last reason for it.
- Also eliminated along the way: zfc-datagrid, zf2-mobile-detect (neither ran
  since the ZF3 migration; both cap at PHP 7.x), beaucal-invalid-session
  (abandoned 2016; its 30 lines moved into `JUser::onBootstrap`),
  ocramius/proxy-manager (see "Service-graph cycles" below).

## Rung 3: abandoned-package replacement (2026-08-02/03)

- **ZfcUser eliminated ("rung 3a")**: passwordless magic-link auth implemented
  in JUser itself (deliberately not LmcUser — its password-centric surface
  would have been dead weight). Password auth removed app-wide;
  sha256-at-rest tokens; email verified on every token redemption; Mailpit
  E2E round-trip in the smoke suite.
- **IP geolocation eliminated ("rung 3b")** by product decision (carries over
  to patres): zf-snap-geoip and the discontinued GeoLiteCity.dat are gone; the
  Bible special-countries guest carve-out (CL/AR/PY) was withdrawn. Payoff:
  hydrator 4.x, form 2.17.1 (XSS advisory cleared), router 3.4.5, the real
  abandoned zend-router out of the lock.
- **PHPExcel → PhpSpreadsheet 5.9** — see "Golden-master technique" below.
- **firebase/php-jwt 6 → 7.1** (CVE-2025-45769, the last advisory ignore).
  The v7 break is config-dependent: HMAC keys shorter than the digest size
  (32 bytes for HS256) throw on every sign *and* verify. Trap worth
  remembering: php-jwt raises `DomainException` for **both** a too-short key
  and a caller's malformed token, so classifying on exception class turns
  every garbage token into a 500 — `ApiController::assertUsableCypherKey()`
  checks our own key up front instead, keeping client faults at 400. Also
  fixed in passing: `?token[]=x` reached `JWT::decode()` as an array →
  anonymous-triggerable TypeError 500.
- **All mail consolidated onto symfony/mailer 7.4** (PR #17 + submodule PRs,
  merged and deployed 2026-08-03). Replaced two and a half stacks: JUser's
  SwiftMailer (abandoned 2021), the exception notifier's laminas-mail
  (abandoned; was the last laminas-side PHP 8.4 cap), and
  `SionModel\Mailing\Mailer`'s dependency on AcMailer — a service that was
  configured but **never installed** here, so the book-notices path had been
  dead for years. One shared transport (`SionModel\MailTransport`, built from
  the top-level `smtp_options`, Sendmail fallback) now feeds sign-in mail, the
  exception notifier, and the resurrected book notices (first `Success` rows
  in `mailings` since 2018). Latent bug fixed: `inlineEmailStyles()`'s default
  CSS path never existed — now `public/css/email-default.css`, throws if
  unreadable (patres must supply that file when it converges).

## Rung 4a: PHP 8.3 + the big stack bump (2026-08-02/03, DEPLOYED)

The app ran green on 8.3 before any composer work — `config.platform` pinned
7.4, so support was *undeclared, not absent*. Exactly one runtime blocker
existed app-wide: a vestigial PDO bound param in JTranslate that 7.4's
emulated prepares ignored and 8.0+ rejects (HY093) — in
`Application::onBootstrap`, so it fataled every route. An `E_ALL` audit found
zero deprecations from our own modules.

The real work was resolution: 45 packages pinned at 7.4-era releases moved in
one pass (`require.php ~8.3.0`, platform pin 8.3.33/ICU 76.1/APCu 5.1.24).
Corrections discovered en route, worth keeping:

- **`kokspflanze/bjy-authorize` 3.x is Mezzio-only** — composer resolves it
  but an MVC app can never boot it. The MVC line ends at 2.4.4, which is what
  installed (retiring our fork). It caps laminas-cache at 3.x.
- **SM 4 / view 3 / validator 3 / filter 3 were unreachable** in this
  dependency set (mvc 3.8 + bjy 2.4.4 + slm/locale 1.2 pin them down), so the
  feared ~102-factory signature sweep was never needed.
- **`laminas/laminas-dependency-plugin` was a hard dead end** (no installable
  version under Composer 2.10), which forced TwbBundle into 4a: neilime's
  bundle requires fourteen `zendframework/*` packages the plugin used to
  rewrite. Adopted `diablomedia/laminas-twb-bundle` ^5.0 (community fork,
  keeps the `TwbBundle\` namespace, targets form 3); insurance fork at
  github.com/jroedel/laminas-twb-bundle. With it, **the ZendFrameworkBridge
  dropped a rung early** — nothing needed it anymore.
- laminas-cache 3.14: `SionModel\Cache\LegacyCacheConfig::translate()`
  (unit-tested) accepts the old StorageFactory config shape, so production's
  untracked cache.local.php needed no deploy-time rewrite.
- laminas-form 3's only app-wide rendering change was minimized boolean
  attributes — proven by the form-regression harness (below). `formRow` now
  genuinely resolves to `SionFormRow` (it was accidentally dead code under
  neilime's bundle); zero markup drift observed.
- laminas-db 2.22 rejects `new TableGateway('')` — SionTable's generic
  gateway removed.

Deployed to production 2026-08-03; konsoleH flipped to PHP 8.3.33 in the same
deploy. Deploy-ops layer landed the same day: `tools/smoke-prod.sh` (runs as
the last phploy hook and standalone), keyed `/sm/cache-status` JSON endpoint,
local `deploy/<timestamp>` tags.

## The API authorization bypass (found and fixed 2026-08-03, deployed)

`Books\Controller\LibrariesApiController` and `PublicationsApiController`
extended `AbstractRestfulController` directly instead of
`RestApi\Controller\ApiController` — the only class that attaches
`checkAuthorization()` to dispatch. Their routes'
`'isAuthorizationRequired' => true` was read by **nothing**, and BjyAuthorize
whitelists `api-v1/*` for guest by design (the JWT layer *is* the gate), so
`GET /api/v1/libraries/:id` (adminNotes, contactEmail) and
`…/pending-labels` (84 KB of book records) answered anonymous callers. Both
were reads: the unfiltered `getList()` was unreachable (route requires
`:library_id`) and the DELETE was not a reachable write (see the
`finish-pending-labels` item in the backlog). Fix: both controllers extend
`ApiController`; `/api/v1/literature` stays public and now says so explicitly.
`test/Integration/ApiAuthorizationFlagTest` walks every module's route tree
and fails if a route requiring authorization is served by a controller that
cannot enforce it.

## The fatal-under-HTTP-200 family (2026-08-02, deployed)

Three independent bugs produced ~800-byte HTTP 200 responses carrying PHP
fatals — a *symptom*, not a diagnosis:

1. **Cold-page cache wedge**: SionCacheTrait's failed-write handler did
   `unset($this->memoryCache)`, destroying the declared property; every later
   request fataled until APCu was cleared. Trigger: APCu exhaustion at the
   32M default. (The patres `1.0.x` line has the same bug — worse under PHP 8
   typed properties; fix branch `fix/cache-write-failure-wedge` pushed.)
2. **Every unknown URL since ~2020**: the multidots `/:*` catch-all route +
   default-deny guard + a RedirectionStrategy `explode()` TypeError. Catch-all
   scoped to `/api`; unknown URLs now render the normal 404.
3. **The service-graph recursion** below (never reached production).

Related hardening: `public/index.php` no longer forces `display_errors`
outside development; the blog's parsedown version mismatch (notices rendered
into post bodies) fixed.

The APCu follow-up (2026-08-03, docs/caching.md): with `apc.ttl=0`, one
oversized write wipes the *entire* cache. Two keys each exceeded the whole
32M segment. Fixed with `max_cached_item_size` refusal + narrow projections;
warmed occupancy measured 10.4 MiB.

## Service-graph cycles (2026-08-02)

Dropping proxy-manager/`lazy_services` exposed a four-way constructor cycle
the lazy proxies had been deferring past for years
(`UserTable → ProblemService → ProblemTable → AuthService → UserTable`);
every route recursed to the 512M limit. Cut losslessly, then the root pattern
retired app-wide: **thirteen** factories resolved `JUser\AuthService` at
construction just to freeze an acting-user id. All now inject
`SionModel\Service\ActingUserProviderInterface` (one method,
`getActingUserId(): ?int`), consulted at write time. Consequences: mid-request
logins are observed (magic-link redemption no longer stamps `createdBy =
null`), user-role inserts finally stamp `create_by` (null since 2014), and
`SionTable::setActingUserId()` survives as the explicit override for JWT
contexts where no session identity exists.

**Technique worth reusing**: temporarily patch the ServiceManager so `get()`
pushes each name onto a per-container stack and dumps it on the first repeat —
it named the cycle in a single request, and a smoke run under it proved no
cycle survived. Key the stack by `spl_object_id($this)`, not globally: a
plugin manager delegating a name upward is not a cycle.

## Golden-master technique (PhpSpreadsheet migration, 2026-08-03)

The pattern: when replacing a library whose behavior nobody remembers, diff
the old and new implementations over *real historical inputs* before trusting
the swap.

- All 17 historical production imports copied off the server; each file dumped
  through PHPExcel 1.8.1 on a one-off PHP 7.4 container and through the new
  `Books\Service\SpreadsheetReader` on the 8.3 capsule. **2.59M cells
  identical, zero real diffs**; 126k cells in the predicted
  numeric→formatted-string class (all consumers go through `is_numeric` +
  `(int)`). One benign structural difference (formatting-residue tail rows).
- The one behavior that could have broken silently: empty cells returning `''`
  instead of `null` would defeat the empty-row skip and create blank books —
  the reader's contract pins "empty is `null`, never `''`", enforced by
  `test/Integration/SpreadsheetReaderTest`.
- Same pattern earlier, smaller: the cakephp/utility elimination ran both
  implementations over 33 cases (accented Spanish, CJK, regex metacharacters)
  with **0 mismatches**, then baked Cake's output into unit-test fixtures.
- Gotcha for the record: production phpinfo exported as PDF renders "fi" as
  the ﬁ ligature — ASCII greps for `fileinfo`/`filter` come up falsely empty.

## Form-regression harness (2026-08-03)

`tools/form-regression.php` — capture/compare/probe over 27 pages with an
all-roles magic-link account, CSRF-normalized; baseline in gitignored
`data/form-regression/`. Built for rung 4a's laminas-form 3 bump, where it
proved the only markup change app-wide was boolean-attribute minimization.
**This is the safety net for the Symfony form migration** — rebase the
baseline before each form is ported.

## First modernization deploy (2026-08-02) and deploy-day lessons

PRs #1–#3 went to production on the same PHP (7.4.33). That evening's full
prod smoke pass ended the fatal-200 era: blog clean, unknown URLs → real
404s, magic-link sign-in working, random cold sitemap pages (of ~14,640)
zero fatals. Passwords stopped working anywhere; sign-in is email magic
links. Lessons that shaped DEPLOY.md (the living procedure):

- phploy must run as `php8.0 phploy.phar` (host php8.5 lacks mbstring).
- phploy's `-m` submodule mode has a fatal directory-purge bug — it
  recursively deleted `module/JUser/src` *after* uploading it. Never again;
  submodules deploy via `tools/deploy-submodules.sh` in post-deploy hooks.
- `public/.htaccess` is untracked since 2017 and forced
  `SetEnv APP_ENV development` since 2016 — production ran development mode
  for years. Hand-edited server-side at deploy time.
- A stale `data/config/` cache produces "Unable to resolve service X" for a
  service plainly present in config — the deploy hooks purge it every time.

## Retired en route

- bjy-profiler query profiling (pick a maintained tool if missed).
- The countries dataset is static now
  (`module/JTranslate/data/countries.json`, vendored from mledoze/countries
  1.8); refresh from upstream if country data matters.
