# Modernization roadmap

Current truth only — no journal. Closed work moves to [history.md](history.md)
(or lives in git); when an item here is done, delete it and record anything
reusable there instead of accumulating DONE narratives.

State as of 2026-08-04: production runs PHP 8.3.33 on a current Laminas stack;
master is fully deployed (`deploy/20260803-2346` = PR #17); `composer audit
--locked` reports zero advisories; 198 tests across three suites; one-command
deploy with hooks.

## Strategic direction: Symfony, via strangler (decided 2026-08-04)

laminas-mvc is security-only until 2028-12-31 and will never support PHP 8.5
(reverified on Packagist 2026-08-04); its module ecosystem is already eroding
(bjy-authorize's MVC line ended at 2.4.4, TwbBundle survives only as community
forks). Weighed against the criteria — migration cost, support, speed,
readability — the destination is **Symfony**, reached gradually:

- **Components first, kernel last.** Adopt Symfony components one PR at a time
  under laminas-mvc (symfony/mailer 7.4 is already in). When the kernel swaps,
  Symfony runs in front with a catch-all route delegating unmigrated paths to
  the laminas application, so routes port incrementally.
- **No deadline panic.** laminas-mvc security support and PHP 8.4's security
  window both run to December 2028. Rung 4b (8.4) lands regardless.
- **Decoupling is the first real work**: SionModel/JUser's ServiceManager
  habits (string-keyed lazy resolution inside domain classes) must become
  explicit wiring. This pays off under any future and must be sequenced with
  the patres convergence plan below — patres consumes the same libraries.
- **Known concern — server-side form validation parity** (flagged
  2026-08-04): symfony/form validates via symfony/validator constraints; the
  part of laminas-form with no direct equivalent is the *filter* chain
  (`StringTrim`/`StripTags` pre-validation normalization), which becomes data
  transformers or explicit normalization. Port forms one at a time with
  `tools/form-regression.php` as the safety net; unported laminas forms keep
  working under the strangler.
- **What this decision supersedes**: the ~102-factory Interop→Psr sweep
  ("SM4 prep") is parked — hand-written factories largely evaporate under
  Symfony DI. Long-term answers now on file: TwbBundle → Symfony's built-in
  Bootstrap form themes; BjyAuthorize → Security component (`access_control`
  + voters); JUser magic-link → Security's LoginLink authenticator;
  laminas-view/.phtml → Twig. None of these move before their rung.

## Now

- [ ] Watch for 401 fallout from the API authorization fix (live since
  2026-08-03): clients of `GET /api/v1/libraries/:id` and
  `…/pending-labels` that never sent a JWT now get 401s. The label-printing
  workflow is the first candidate; tokens come from `POST /api/v1/login`.
- [ ] Hetzner/konsoleH support ticket (pending): raise `apc.shm_size` 32M →
  256M for the PHP 8.3 ini, and set `apc.ttl` > 0 so a failed allocation
  evicts instead of wiping the segment. Downgraded from blocking by the
  cache-size work ([caching.md](caching.md)), but two expunges were observed
  within hours on deploy day — still worth the one ticket.
- [ ] Announce passwordless sign-in to users if confused-user replies arrive.
- [ ] **First component adoptions, in this order** (decided 2026-08-04,
  ahead of rung 4b): **symfony/console** (in progress), then **monolog**.
  Each is a normal PR verified by the existing suites.
  - *console* is purely additive — nothing laminas is replaced, so there is
    no regression surface — and it builds the seam the strangler needs
    anyway: a `bin/console` that boots the ServiceManager headless, outside
    laminas-mvc's HTTP dispatch. Same seam later serves doctrine/migrations
    and cron-style commands. Carries the maintenance-key deploy-ops item
    below.
  - *monolog* retires abandoned `laminas-log` outright and puts PSR-3
    `LoggerInterface` typehints in place of a laminas concrete — exactly what
    Symfony DI autowires later. It also unpins `psr/log`: laminas-log's
    `^1.1.2` constraint was the only thing holding the project on psr/log 1,
    so removing it frees the 3.x line that monolog 3 and symfony/mailer both
    want.
  - **Not validator or translation yet**, despite reading as low-coupling:
    `Laminas\Validator` is in 41 files and `Laminas\InputFilter` in 48, and
    laminas-form *requires* laminas-validator regardless — adopting
    symfony/validator now adds a second validation system while removing
    nothing. It belongs to the form-by-form port, where each form brings its
    constraints along. Translation touches only 12 files but is wired
    through laminas-mvc-i18n into the router and view helpers, and JTranslate
    exists to manage those files; it lands naturally with Twig.

## Next

- [ ] **Rung 4b: PHP 8.4.** The only gate left is
  `diablomedia/laminas-twb-bundle` 5.0.0 capping at `~8.3`. PR upstream to
  add `~8.4`; fallback is flipping composer to the insurance fork
  (github.com/jroedel/laminas-twb-bundle). laminas-mvc 3.8 supports 8.4 —
  which is also its ceiling. Production flips konsoleH PHP in the same
  deploy; `require.php` and `config.platform` move together.
- [ ] **Passkeys (WebAuthn)** — decided 2026-08-02: web-auth/webauthn-lib
  current major, credential table, enrollment inside an authenticated
  session, magic link remains the fallback.
- [ ] **PHPStan: raise from level 0** (56-error baseline) — the biggest
  durability lever not yet on the ladder. Burn the baseline down as levels
  rise; then get PHPStan into CI (needs a vendor + baseline strategy) and
  the smoke suite into CI (needs a capsule + dump strategy).
- [ ] **Real database migrations** (Phinx or doctrine/migrations) instead of
  hand-run dumps in `database/`. Constraint: the web app's DB user lacks DDL
  rights, so migrations need separate credentials stored only on the server,
  never in the repo.

## Bugs (characterized, fix pending)

- [ ] `finish-pending-labels` is an unreachable route:
  `Laminas\Router\Http\Part::match()` returns the parent match once the path
  is consumed and the parent `may_terminate`s, so its `Method(delete)` child
  is never consulted — a DELETE dispatches the parent's read action and
  `finishPendingLabelsAction()` is dead code. The label workflow's "commit
  these call numbers" step has never worked. Fix shape: `may_terminate =>
  false` + one `Method` child per verb + bjyauthorize guard entries for the
  renamed terminals. Before enabling, check what `SionTable::updateEntity()`
  records as acting user under JWT (no session identity —
  `setActingUserId()` from `tokenPayload->sub` is the channel).
  `public/api/v1.yaml` documents it as NOT CURRENTLY REACHABLE. Note: this
  route-tree bug class cannot be expressed in Symfony routing — fix now only
  if the label workflow needs the commit step before the strangler reaches
  the API.
- [ ] `GET /api/v1/libraries/:id/books` answers 200 with a zero-byte
  `text/html` body: `BooksApiController::getList()` builds a `JsonModel`
  that never renders. The JWT gate on it works; it is the one gated route
  whose payload no test asserts, for this reason. Cause not investigated.
- [ ] `/en/dictionary` → 404: the route (module/Books config, ~line 1280)
  declares `DictionaryController` with no `'action'` default. One-line fix;
  verify production intent first (production may 404 identically).
- [ ] `/libraries/create` and `/libraries/:id/edit` 500:
  `Books\Form\SearchForm`'s factory throws "only for a specific library"
  without library context (pre-existing, surfaced by the rung-4a audits).
- [ ] `/blog/create` 500: template `books/blog/create` missing.
- [ ] `SionModel\Controller\FilesController` calls
  `$this->getServiceLocator()`, gone from AbstractController since ZF3.
- [ ] `SionForm::setData()` calls `getInputFilterSpecification()` which the
  base class doesn't define.
- [ ] `LibraryTable::checkinBooks()`: in the branch creating checkouts for
  books with none, `$booksToCheckin[$checkout['bookId']]` reads `$checkout`
  leaking from the previous `foreach` — wrong key, or undefined variable
  when no checkouts were open. Deliberately unfixed so far (behavior
  change).
- [ ] `layout.phtml` uses `getHelperPluginManager()->getServiceLocator()` —
  fine on view 2.44, dies at view 3 (and has no Twig future).
- [ ] `change-password` guard entry in `config/autoload/juser.global.php`
  names a nonexistent route (real one is `zfcuser/changepassword`), so the
  change-password page may be unintentionally blocked under default-deny.
  Verify intent — likely moot since passwords were removed, in which case
  delete the route instead.

## Product decisions needed

- [ ] **SchoenstattTable ACL providers** (dormant 2020 WIP): `getRules()`
  grants roles that don't exist in production (`sch_international_leader`,
  `sch_institute_member`) — activating it fatals at boot; the provider
  registration is disabled in `module/Schoenstatt/config/module.config.php`.
  Decide the intended semantics (which real roles on person/association
  resources), create missing `user_role` rows, re-enable, cover with tests —
  or delete the WIP. Also weigh boot cost: both methods query all
  persons/associations on every ACL build.
- [ ] API registration allow-list (old @todo in `LoginV1ApiController`): API
  magic-code login does not auto-create accounts; the web flow does.
- [ ] Drop the now-unread `user.password` column once passwordless has
  soaked (deliberate migration).
- [ ] Library import form takes a hand-typed *server* path (the 2021
  Windows-desktop-path failure). Real fix is accepting an upload — a fresh
  feature on top of `Books\Service\SpreadsheetReader`.

## Config rot / small cleanups

- [ ] Dead ACL guard entries naming nonexistent routes: `new-home`
  (Application), `home` (Books).
- [ ] `sign-in-no-cookies` route exists but is whitelisted in no guard —
  blocked for everyone under default-deny. Dead feature or bug?
- [ ] Bare `checkouts` route (Books) not whitelisted (only
  `checkouts/library` is).
- [ ] `phpstan-baseline.neon` still carries a pre-Laminas
  `Zend\Stdlib\ResponseInterface` entry — harmless, confusing.
- [ ] Sweep legacy `Zend\*` strings from the merged runtime config as the
  remaining vendor modules are replaced.
- [ ] Re-track `public/.htaccess` (or a `.htaccess.dist`) — untracked,
  hand-edited server-side state since 2017.
- [ ] Production's server-side `local.php`: dead `acmailer_options` key
  (harmless), and it still leaks stack traces via its error-display config —
  clean both next time a deploy touches server config.
- [ ] Two Application factories still sit on the root-namespace
  `FactoryInterface` shim (deferred from rung 4a).
- [ ] The exception notifier double-reports a failure that also kills the
  error-page render: the nested report loses the RouteMatch and fingerprints
  as route `(none)` (seen 2026-08-03 — fingerprints 1615c790 + 35c198d9 were
  one incident). Dedupe, or carry the outer route into the nested report.

## Performance / caching

Background and measurements: [caching.md](caching.md).

- [ ] `query-objects-publication` (10,166 rows × 80 fields, 29.2 MiB) is
  still built by the literature routes — now refused rather than
  cache-wiping, so they run uncached. A narrow projection (as done for the
  navigation) is the next real win.
- [ ] `LibraryTable::getLibraryBooksStatuses()` hydrates every entity for a
  library (up to 12,394 books) then links checkouts — next projection
  candidate.
- [ ] Navigation cache keys written in `Application\Module::onBootstrap()`
  are never invalidated on data change (only `SionCacheTrait`-registered
  keys are) — editors see "my change didn't appear" until the 5-day TTL.
- [ ] `SionCacheTrait::getUnlinkedAssignments()` declares dependencies
  `['assignment', 'association']` but the honest list is
  `['assignment', 'role']` — harmless over-invalidation, still wrong.

## Testing & CI

- [ ] **PHPStan has been red since rung 4a** (found 2026-08-04):
  `phpstan.neon.dist` still pins `phpVersion: 70400`, which cannot parse the
  PHP-8-syntax vendor code the stack bump installed — ~497 errors, all
  "class not found" noise from vendor visibility, none from our modules'
  logic. Bump `phpVersion` to 80300, re-run, regenerate/trim the baseline;
  then fold into the "raise from level 0" item above.
- [ ] Shared-library tests live in the app repo (`test/Unit/TextTest.php`
  covers `SionModel\Text\Text`) because the submodules have no test
  infrastructure. Migrate them into laminas-sion-model/juser/jtranslate so
  patres inherits them.
- [ ] Keep the form-regression baseline (`tools/form-regression.php`)
  rebased — it is the designated safety net for the Symfony form migration.

## Deploy ops

- [ ] **Drop the `?key=` fallback** now that every endpoint also accepts an
  `X-Api-Key` header (`SionModel\Controller\MaintenanceKeyTrait`). The query
  parameter still works only because the deploy config that sends it lives in
  each machine's gitignored `phploy.ini`, which no commit here can update.
  Sequence: land the header change, update `phploy.ini` on every deploying
  machine from the new `phploy.ini.dist`, deploy once, then delete the
  fallback from the trait. Until then the leak is still reachable — a caller
  that keeps using `?key=` keeps writing the key to the access log.
  - Established 2026-08-04, worth not re-deriving: **the flush cannot become
    a pure CLI command.** The persistent cache is the APCu adapter, and an
    APCu segment belongs to the SAPI that created it, so a CLI process gets
    its own (or none, with the default `apc.enable_cli=0`). Measured: a CLI
    `apcu_clear_cache()` left all 21 web-segment entries untouched. Only an
    HTTP request into the web SAPI can flush it, which is what
    `cache:flush-persistent` does.
- [ ] Port `/en/associations/do-work` to a console command. It is the last
  deploy hook that is still a `wget` (now header-authenticated, so it is no
  longer a leak — just the odd one out). Unlike the cache flush this one is
  genuinely CLI work: `SchoenstattTable::autoFillTimeZones()` and
  `updateAssociationMd5s()` touch only the database. Needs an acting-user
  decision first — `ActingUserProviderInterface` has no session identity on
  CLI.
- [ ] phploy upstream PRs (banago/PHPloy): the directory-purge bug (deletes
  parent-directory chains recursively, took out module/JUser/src on deploy
  day) and `--list` silently skipping submodules. Alternatively, if phploy
  keeps chafing: evaluate Deployer (atomic release dirs + symlink switch
  would also kill the mid-deploy broken window).
- [ ] Production runs FastCGI — "what bounds concurrent PHP memory?" has
  never been asked of the Hetzner account (the capsule's ceilings don't
  apply there).
- [ ] Content-Security-Policy header: prerequisite is an audit of every
  inline `<script>`/`<style>`/`on*=` across the `.phtml` templates (likely
  nonce- or hash-based). Roll out via `Content-Security-Policy-Report-Only`
  first. Natural rung: alongside asset-pipeline work — or fold into the Twig
  migration, which touches every template anyway.
- [ ] Consider narrowing the application log's level. Both loggers write at
  `Level::Debug` because that is exactly what laminas-log did (a Logger with a
  Stream writer and no priority filter wrote every event), so
  `SionCacheTrait`'s per-cache-write `debug()` calls have always landed in
  `data/logs/application_*.log` — hence its size. Raising the threshold is a
  real decision about what the log is for, deliberately not folded into the
  monolog port.

## Shared libraries / patres convergence

Plan decided 2026-08-02: the `1.0.x` branches (patres's frozen 2020–2022
Laminas line) are a review source to **port from, never merge** — both lines
rewrote the same files. Converge everything on `modernization`.

- [ ] At each rung, diff the files being touched against `1.0.x` first
  (typing/SionCacheService work belongs to a PHP 8 pass; language-file
  additions to an i18n pass over the new auth views/emails).
- [ ] Migrate patres onto the converged line (now unblocked — the shared
  libs are stable on 8.3). It inherits passwordless auth, geoip removal, the
  fatal-200 fix, and the ActingUserProvider seam. Mechanical checklist:
  convert its table factories to inject `ActingUserProviderInterface`; the
  raw `$this->actingUserId` property is gone (`getActingUserId()` replaces
  it); check `setActingUserId()` callers (it survives as the token-auth
  override); check its `SionTable` subclasses against the still-eager
  `$entityProblemPrototype` (two consumers here read the raw property — a
  lazy getter would hand them null); supply `public/css/email-default.css`
  for the Mailer or pass a path.
- [ ] patres's `1.0.x` SionCacheService still has the fatal-200 `unset()`
  bug (worse on PHP 8: typed property → immediate Error). Fix branch
  `fix/cache-write-failure-wedge` is pushed on laminas-sion-model; the PR
  against `1.0.x` must be opened by hand (the gh token lacks access).
- [ ] Retire the `0.3.x`/`1.0.x` branches once patres converges.
- Parked (superseded by the Symfony decision): the ~102-factory Interop→Psr
  + `: mixed` sweep. Revisit only if the strangler stalls and SM4 becomes
  reachable after all.
