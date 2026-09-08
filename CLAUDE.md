# CLAUDE.md

## Project overview

schoenstatt.link — a database application for Schoenstatt-related topics, built on the Zend Framework 3 (ZF3, pre-Laminas) MVC layer.

**This repo was dormant for 5+ years (no development since ~2020) and is being reopened in 2026 with one overarching goal: bring it up to the state of the art.** Everything below must be read through that lens.

## Modernization mandate and its implications

- The target is modern, current-best-practice PHP — not careful preservation of the legacy state. When choosing between patching the old way and migrating to the modern way, prefer the migration, but propose it to the user first.
- **Zend Framework is dead.** It became Laminas in 2019; every `zendframework/*` package here is abandoned. The core framework migration (ZF3 → `laminas/*`, likely via `laminas/laminas-migration`) is the foundational step most other upgrades depend on.
- **Assume every dependency is suspect.** `composer.lock` is 5+ years stale. Many deps are abandoned or superseded, e.g. `swiftmailer` → `symfony/mailer`, `phpoffice/phpexcel` → `phpoffice/phpspreadsheet`, `bjy-authorize` → its `lm-commons` (Lmc*) successor. (**`zfc-user` is not one of them**: it is not installed and has not been for some time — JUser replaced it with its own passwordless controller, and only the `zfcuser/*` route names and `ZfcUser*` class names survive. There is nothing to migrate, which is why the eventual answer for authentication is Symfony Security's LoginLink authenticator rather than LmcUser.) (`zfr-cors` → LmcCors is **no longer a migration to make**: zfr-cors was replaced by a local listener, and that listener was deleted 2026-08-14 with the v1 API it served — nothing on the site sends CORS headers now.) `roave/security-advisories` will likely refuse to resolve until vulnerable pins are lifted. Before building on any dependency, check whether it is still maintained.
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
- **OPcache is on** (enabled 2026-08-04, `validate_timestamps=On` / `revalidate_freq=2`, 128 MB, 10000 slots): deploys pick up changed files within seconds, so no pool restart is needed. Should `validate_timestamps` ever be set to 0, every deploy then requires `pkill -u ourlink -f php`. `/en/sm/cache-status` reports **both** caches (see [docs/caching.md](docs/caching.md)); `tools/smoke-prod.sh` warns on saturation and on any OPcache restart. **The interned-strings buffer is no longer at its ceiling: the konsoleH raise landed** — 32 MB, confirmed 2026-08-21 from a live `/en/sm/cache-status` (`internedBufferConfiguredMb: 32`, 33,554,432 bytes, 39.9% used, 68,328 strings), against 8 MB at 89–100% before. Worth noting what the new figure proves rather than just that it is comfortable: **13.4 MB of interned strings on a cache that had only just been reset is already more than the whole old buffer**, so the 8 MB ceiling really was truncating — strings past it were never interned at all. The buffer is append-only, so a reading taken after a deploy's reset is cold and always looks healthy; `tools/deploy.sh` prints each pool's warm value at the instant it wipes it, and `tools/opcache-sample.sh` groups polls by segment when you need to know which of the three pools answered. Confirm a `PHP_INI_SYSTEM` change from `/en/sm/phpinfo`, never from CLI `php -i` — different SAPI, different ini.
- **ICU is 72.1 on the 8.5 build — unchanged from the 8.4 one** (ICU data 72.1, ICU TZData 2022e), measured 2026-08-11. It dropped from 76.1 at the 8.3 → 8.4 flip and did *not* come back with 8.5, so the `lib-icu` platform pin is still accurate and the 27 `IntlDateFormatter` call sites render exactly as they did before the move. Do not assume the next flip behaves: this hoster's builds have already shipped ICU backwards once. PHP's own timezone database is separate and current (internal "Olson" 2026.3; the loaded `timezonedb` extension carries an *older* 2025.2 and is not the one in use — the date section reports `internal`).
- Extensions verified present against every `config.platform` pin, re-checked 2026-08-11 against the live module list. Redis and OAuth were **disabled in the hosting console on 2026-08-04 to reduce attack surface** — nothing in `composer.json` requires them — and are still absent. Treat re-enabling either as a security decision, not a config tweak: the Redis caching option in [docs/caching.md](docs/caching.md) is priced accordingly.
- **`apc.shm_size` is now 256M** (was 32M), APCu 5.1.27, `apc.entries_hint=4096` — the konsoleH ticket landed, so the oversized-item-wipes-cache hazard is much reduced: both historical offenders (45.7 MiB, 29.2 MiB) would now fit. **`apc.ttl` is still 0**, which is the half that did not land, so a genuinely failed allocation still expunges the whole segment rather than evicting. The per-account ini is `/home/httpd/php85-ini/ourlink/php.ini` — **a new path per PHP version**, which is why a konsoleH version flip silently reverts every tuned value. It is root-owned and unwritable by the account, so every change is a konsoleH ticket. [docs/php-85.md](docs/php-85.md) § The per-account `php.ini` holds the full table of current-vs-wanted values — diff against it after any version switch.
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
  Form routes are no longer blocked: `SionModel\Form\BootstrapFormRenderer` reproduces
  TwbBundle's markup and `association-edit` is ported (byte-identical to the laminas
  rendering apart from inter-tag whitespace). **That renderer and its Twig binding
  (`SionModel\Twig\FormExtension`) live in the SionModel submodule since 2026-08-21**,
  not in `src/` — they are the reusable half of the form layer, and the package they
  reproduce (`SionModel\Form\View\Helper\SionFormRow`) was already there. They are
  still Symfony-side code and still hold themselves to level 8 and PSR-12, which no
  longer follows from their path: SionModel's own `phpcs.xml` covers them, and a level-8
  audit must name `module/SionModel/src/Form/BootstrapFormRenderer.php` and
  `module/SionModel/src/Twig/` explicitly alongside `src`.
  **The library circulation surface is Symfony-served since 2026-08-18** — the library
  page, its admin menu, the lending and check-in forms, the checkout lists, the book and
  borrower pages and the imports. `App\Books\LibraryPage` holds what every one of those
  opens with (the row, the per-library ACL check, the breadcrumb), and the per-library
  check is the real protection: every route guard on that surface names `lib_user`, which
  is `is_default = 1`. Read [docs/libraries.md](docs/libraries.md) before changing any of
  it: the permissive `checkout` rule is deliberate, and `refresh-sort` answers GET with a
  confirmation because the laminas action rewrote every book in the library on one.
  **Two corrections to the sentence above, both from 2026-09-08.** `administrate` is *not*
  per-library: `LibraryTable::getRules()` emits it for `lib_administrator` against **every**
  library row unconditionally (its own `@todo` wants a per-library administrator table and
  there is none), so for that permission the per-row check distinguishes nobody. `show` and
  `checkout` are the genuinely per-row ones. And `libraries/library/delete` is now the one
  guard on the surface that does *not* name `lib_user` — it names `lib_administrator`, which
  also makes it the first entry whose `is_allowed('route/' ~ route)` filter in the admin menu
  template does anything.
  **Deleting a library is Symfony-only, and it is the first page here that never had a
  laminas rendering** — the route name existed from 2020, refused twice over (no guard entry,
  and the entity's `enable_delete_action` commented out). `App\Books\LibraryDelete` is why it
  had to be written rather than ported: `SionTable::deleteEntity()` is a single-row `DELETE`,
  and of the four tables carrying a library id only `lib_imports` has a foreign key, so the
  generic delete would have left PUC's **16,383 books** pointing at a library that no longer
  exists — on no page, in no catalogue, and nothing would have errored. The cascade is
  explicit, in one transaction, children first; **checkouts must be deleted before books**
  (they are reached by joining `lib_books`) and **the cache invalidation must follow the
  commit**. The confirmation asks for the library's name typed exactly, the change log gets
  an aggregate rather than 16,383 rows, and the laminas generic delete stays **disabled** on
  purpose, because under `SYMFONY_KERNEL=0` it would delete the row without any of that.
  One consequence that is not specific to this page: a library created by SQL rather than
  through the app has no `library_<id>` ACL resource until the persistent cache is flushed,
  so it answers 403 to everyone.
  **The four event write routes are gone as of 2026-09-08** — `event` (show), `event-edit`,
  `event-delete` and `events/create`, plus the nine `event` entity-spec keys that named them.
  They had existed since 2020, reachable by nobody, with no form, no template and no create
  handler behind them, so a guard entry alone would have produced a 500. `/timeline` and its
  Symfony controller are untouched, the 527 rows are untouched, and Part 2 of
  [docs/timeline-and-corpus.md](docs/timeline-and-corpus.md) always meant to declare its
  routes Symfony-side. Do not re-add them as a stepping stone.
  **`admin/import-father` is Symfony-served since 2026-09-08 (batch 17), and it was the last
  HTML page laminas served** — every page a person can open on this site is Symfony-served
  now. `App\Controller\ImportFatherController` keeps the laminas form (its select is a Patres
  API call the form factory makes) via the bridge and reproduces the three import outcomes;
  the empty-picker case that was a 500 on laminas (`importRemotePerson(null)`) is a form
  error here. The laminas action and `.phtml` stay as the canary rollback path, as batch 16
  left the publication actions. `test/Smoke/ServingNoteSmokeTest` had used this page to
  observe the bridge; with no unported page left it now points at the bridged **404 page**,
  the last `.phtml` LegacyBridge renders.
  **The last four publication actions are Symfony-served since 2026-09-08 (batch 16)** —
  `publications/export`, `publications/prime-authors`, `publication-copy-to-main-corpus` and
  `publication-create-new-edition`, behind `App\Controller\PublicationReportsController` and
  `App\Controller\PublicationDuplicateController`. Every `Books` route a person can open is
  Symfony-served now, and `publication-upload-cover` — reachable by nobody since 2020, no
  guard entry, inverted success test, nonexistent target column — was **deleted** the same
  evening along with `sion-model/auto-fix-data-problems` (redundant with `refresh-sort`) and
  SionModel's `ProblemTable` (read a table that does not exist). Two things to know. **`export` is an HTML
  table of all 10,166 rows, not a spreadsheet** — docs said otherwise until the port, inferred
  from the name. And **`create-new-edition` wrote on a plain GET until this batch**, the same
  hazard the copy action had until 2026-08-14; the field list is
  `PublicationsTable::createNewEdition()` now and *both* front controllers confirm through
  `Books\Form\CreateNewEditionForm`, so the rollback path carries the fix. `EntityShow::row()`
  is the half of `load()` that resolves a row and asks its `show` permission without
  registering a visit — use it for anything that opens with a row and is not the show page.
  **The whole JUser surface is served by the module's own controllers since 2026-08-21** —
  `/user`, `/user/login`, `/user/verify`, `/user/logout`, `/users`, the two create forms, the
  account form, the delete confirmation and the API-token screen with its revoke twin.
  `JUser\Controller\*` over `JUser\Page\{SignIn,RedirectTarget,UserAdmin,CookieExplainer}`,
  templates in `module/JUser/templates/` addressed as `@juser/…`, and eleven routes declared
  by `module/JUser/config/symfony-routes.php` — a closure this application calls back into
  from `config/symfony/routes.php`, so that file still reads as the migration status top to
  bottom. The application's own copies (eight controllers, four `App\JUser\*` classes,
  eleven templates) were **deleted** with the switch: there is one copy again and it is the
  module's, which is the point — JUser 3.0.0 is meant to drop into patres.
  **What is left here is six adapters in `src/JUser/Host/`**, implementing the contract in
  `module/JUser/src/Host/`, and they are where every laminas-shaped fact about this
  application now lives. Three are worth knowing before touching anything on this surface,
  and each fails silently rather than loudly. `Flash` **must stay one instance per request**:
  a second `FlashMessenger` moves the first's messages out of the session and drops them —
  measured on redemption, which reports "you are signed in" and "but not there" together and
  showed only the second. `RouteResolver` holds the details that make a `?redirect=`
  resolvable at all — strip the locale prefix, and match on a **clone** of the router with an
  empty base URL, because `RouteUrl` mutates the shared one's base and the answer would
  otherwise depend on whether a template rendered a link first; get either wrong and *every*
  destination on the site is refused while nothing errors anywhere. And `UrlBuilder::url()`
  **primes the router's request URI**, because nothing under a Symfony dispatch ever sets one
  and `force_canonical` throws without it — that is the emailed sign-in link, the one thing
  here whose breakage is invisible on every page.
  `App\JUser\Host\Access` has the other half of that reasoning: `userMayReachRoute()` asks
  about the account's own role *names* (off `linkUser()`, **not** `rolesList`, which holds
  numeric ids) rather than calling `isAllowed()`, because `Authorize::load()` bakes the
  identity's roles into the ACL once per request and the guard already ran while the visitor
  was anonymous. `visitorMayReachRoute()` is the ordinary question and does call it.
  Two things did not move: `App\JUser\MisconfiguredPersonProvider` (the module takes a typed
  provider or null, so the config check stays with the config that names it) and
  `templates/juser/_person-cell.html.twig`, which JUser includes through the
  `juser_person_template` Twig global because it has no person model.
  **`JUser\Controller\LoginController` is gone as of 2026-08-21, and with it the no-deploy
  rollback.** Removing the module's route fragment from `config/symfony/routes.php` used to
  hand `/user/login` back to laminas; there is nothing to hand it back to now. Rolling any of
  this back is a deploy, on both halves of the surface. **JUser registers nothing with
  laminas-mvc any more** — no controllers, no controller plugins, no view manager, and
  `JUser\Module` is `getConfig()` and nothing else.
  Two things moved here as a result. **The session is started by
  `Application\Session\SessionBootstrap`**, attached through
  `config/application.config.php`'s `listeners` key at priority 10000 — *not* from a module
  `onBootstrap()`, and that is the whole point of the class. Module hooks run in
  `config/modules.config.php` order at one priority; `JUser` sat at 42 and `Application` sits
  at 47, and **`SionModel` at 43 calls `Authorize::getIdentity()` in its own hook**, which
  triggers `Authorize::load()` and bakes the identity's roles into the ACL for the whole
  request. Started later than that, it bakes `guest` and every `isAllowed()` on the request
  answers anonymous — and it would not show up in testing, because under the Symfony front
  controller `App\Http\SessionListener` starts the session before `LegacyBridge` builds the
  laminas application at all. The path this protects is `SYMFONY_KERNEL=0`.
  And **`zfcUserAuthentication()` is gone**; its two call sites in
  `module/Schoenstatt/src/Controller/` use `identity()` from `laminas-mvc-plugin-identity`,
  whose factory resolves `Laminas\Authentication\AuthenticationService` — which JUser
  aliases to its own service, so the storage is still `SessionUser` and `null` still means
  anonymous. Both of those controllers are rollback-path code: `/movement` and
  `/associations` are Symfony-served, so neither action dispatches today.
  **The module's laminas-shaped code lives in `JUser\Bridge\Laminas\` since 2026-08-21** —
  fifteen classes, all of which this site still uses and a Symfony-only host would not: the
  session storage and auth-service factory behind `zfcuser_auth_service`, the historical
  `zfcuser_user_service`, SionModel's acting-user provider, the two `.phtml` view helpers a
  bridged layout still calls, BjyAuthorize's identity and role providers, its role entity, and
  `RedirectionStrategy`. Class names did not change, only namespaces, so `git log --follow`
  and a grep from an old incident note both still work — but three config keys did move, and
  they are in `docs/acl-baseline.json`: `identity_provider`, `role_providers` and
  `unauthorized_strategy`. That diff was three lines and no rule, role or resource changed,
  which is the check to repeat if any of them moves again.
  Nothing in `JUser\Page\*`, `JUser\Controller\*` or `JUser\Host\*` reaches into the
  bridge: the dependency runs one way, this application wiring those classes *as*
  implementations of the contract. Two app-side references had to follow the move —
  `src/Laminas/ViewHelpers.php` and `test/Integration/PhraseDiscoveryTest.php` — plus a path
  in `phpstan-baseline.neon`.
  **`GdprStrategy` is `onFinish()` only.** `onRoute()`, the route-match swap that showed the
  cookie explainer, went with `LoginController`, along with
  `IndexController::signInNoCookiesAction()` and its `.phtml`. The gate is
  `JUser\Page\SignIn` plus `JUser\Page\CookieExplainer`, which answer at the requested URL
  with a 200 exactly as the swap did — and note what the swap *was*, because it explains why
  its page never needed a route: priority -5000, i.e. after the guard had already approved a
  *different* route, with a `RouteMatch` built by hand.
  **`sign-in-no-cookies` never was reachable and is now gone entirely** — the route was
  deleted from both front controllers 2026-08-21, and its laminas action, `.phtml` and the
  swap that showed it followed the same day. It had no guard entry, so default deny made the
  URL unreachable from the day it was written. The page survives as
  `module/JUser/templates/sign-in-no-cookies.html.twig`, rendered by
  `JUser\Page\CookieExplainer` at whatever URL asked for it, with a 200 rather than a
  redirect — so an emailed link still works once the visitor consents.
  **The contract itself is verified by `test/Integration/JUserHostContractTest`**, which
  pins the three properties that fail with no symptom: `Severity` matching the laminas flash
  namespaces (a flash crosses a redirect *in the session*, so both front controllers must
  agree on the string or a message is silently never rendered), `juser_layout` resolving
  inside `{% extends %}`, and no framework type reachable from the contract's **code** —
  that last one deliberately allows the prose, since every docblock there names the laminas
  class it replaces, and a blanket string search reported four of six files as violations
  for documenting their own purpose. Like SionModel's two files, the module's new code holds
  level 8 and PSR-12 without its path saying so: a level-8 audit must name
  `module/JUser/src/{Host,Page,Controller,Routing,Twig}` alongside `src`. This line warned
  until 2026-09-08 that naming `module/JUser/src/Controller` as a *directory* would sweep in
  the legacy `LoginController` and its 17 level-0 findings — **that stopped being true on
  2026-08-21**, when that controller was deleted along with the no-deploy rollback, and all
  eight controllers there are new code now. The trap is real one module over:
  `module/JTranslate/src/Controller` still holds `LazyControllerFactory` and a `Plugin/`
  tree, which is why the three `Phrase*Controller.php` are named individually.
  **The PSR-12 half of that is now enforced rather than asserted.** The path list lives in
  `tools/phpcs-clean-paths.txt` — 16 entries, read by both the `coding-standard` job in
  `.github/workflows/ci.yml` and the matching step in `tools/ci-local.sh`, so the two cannot
  drift — and it exists as an explicit list because `composer cs-check`'s own scope reports
  425 errors and always will.
  **The translation GUI is served by JTranslate's own controllers since 2026-09-08** —
  `/admin/translations`, the phrase form and the delete confirmation, the last three
  reachable `jtranslate/*` routes (`jtranslate/phrase` is a `may_terminate => false` parent
  and not a page). Same shape as the JUser surface one size down:
  `JTranslate\Controller\Phrase{Index,Edit,Delete}Controller` over
  `JTranslate\Page\PhraseAdmin`, a three-interface host contract in
  `module/JTranslate/src/Host/`, templates in `module/JTranslate/templates/` addressed as
  `@jtranslate/…`, and three routes declared by `module/JTranslate/config/symfony-routes.php`.
  The laminas controller and its three `.phtml` were **deleted with the switch**, so rolling
  it back is a deploy. Its adapters are `src/JTranslate/Host/{Flash,UrlBuilder}.php`, and
  both are two lines because the implementations are shared: `App\Laminas\HostMessages` and
  `App\Laminas\HostUrls`, extracted from the JUser adapters in the same pass. **The
  FlashMessenger memo had to move there**, because one instance per *module* is one too many —
  a second `FlashMessenger` moves the first's messages out of the session and drops them, so
  the requirement is one per **request**. `JTranslate\Host\Severity` is a deliberate second
  copy of JUser's (JUser depends on JTranslate, so JTranslate must not depend back);
  `test/Integration/JTranslateHostContractTest` pins both against the laminas flash namespaces
  and against each other. Unlike JUser 3.0.0 this takes no package out of the module's
  `require`: `JTranslate\Module::onBootstrap()` stays, because it is the translation layer
  itself — the listener, the validator translator, the view helpers, `nowMessenger` — and not
  GUI wiring. A level-8 audit must name `module/JTranslate/src/{Host,Page,Routing,Twig}` and
  the three `Phrase*Controller.php` files by name, since that directory also holds the legacy
  `LazyControllerFactory`.
  **The port found a bug older than itself, and it was not in the GUI.**
  `TranslationsTable::setUserModules()` decides whether a text domain's exported catalog goes
  to `module/<M>/language/` or to `language/<M>/`, and on the Symfony front controller the
  only thing calling it was `App\Laminas\TranslatorConfigurator` — which runs when something
  asks for a *translator*. Every write that succeeds **redirects**, so nothing renders and the
  map was empty: since the v3 API shipped, every module domain's catalog had been written to
  the wrong directory. It looks harmless because both are registered as read paths and
  `language/*` wins, so the damage is the *pair* — the console export rewrites one copy, a GUI
  or API write the other, and a translation deleted through one goes on being served from the
  other. `App\Laminas\TranslationsTableConfigurator` fixes it as a delegator on the table, so
  no caller has to know; `PhrasesApiV3SmokeTest::catalogFor()` had asserted the wrong location
  faithfully. Two capsule facts came out of the same hunt: **`log_errors` is `Off`**, so every
  `error_log()` in the tree is discarded (the ported code logs through `LoggerInterface`
  instead), and **`docker compose exec` runs as root**, so a console export leaves root-owned
  catalog directories that make every web-served export fail with `Permission denied` — use
  `-u www-data`. See [docs/translation.md](docs/translation.md).
  **Two guard facts about this surface.** The seven `juser/*` routes all name `administrator`,
  which is *not* `is_default = 1`, so unusually for this site the route guard really is the
  whole protection and `UserAdmin` deliberately has no `refuse()`. And **`/users/roles/create`
  had never once created a role** until the port — the `user-role` spec's
  `required_columns_for_creation` was copy-pasted from the `user` entity, so every submission
  threw; the same block still carries three more copy-pasted fields that are dead only
  because nothing dispatches them (docs/BACKLOG.md).
  **The spreadsheet import is Symfony-only since 2026-08-18** — `/library-imports` is the
  first route tree here with **no laminas controller at all**, its routes kept solely so
  `laminas_path()` and the BjyAuthorize guards can name them. The engine is
  `App\Books\Import\LibraryImporter` (`plan()` / `apply()`), the column vocabulary is
  `App\Books\Import\ImportColumns` and is the only such list, and
  `bin/console books:import` runs the same thing without a 240-second request. Read
  [docs/library-imports.md](docs/library-imports.md) before touching any of it: a blank
  cell **erases** the stored value, a complete import **inactivates** every active book
  the sheet omits, and a row naming a literature record takes eight of its fields from
  that record rather than from the spreadsheet. The **v3 API** for automated agents
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
- Authorization is BjyAuthorize + zend-permissions-acl (`config/autoload/acl.global.php`).
  **The assembled ACL is cached in APCu since 2026-08-22** (`bjyauthorize:acl`, 300s TTL),
  which took the layer from 6.0 to 3.2 ms per request. Two things follow. A missed
  invalidation is a **500**, not a stale page — `Acl::addRole()` throws on a parent role it
  does not know and the identity's roles are added as exactly that — so invalidation hangs
  off `SionCacheTrait::removeDependentCacheItems()` via `App\Acl\AclCacheInvalidator`, on
  `user-role`, `library` and `text` only. And the 294 `user_<id>` roles are **gone**: nothing
  ever wrote a rule naming one, and they made the ACL depend on the `user` table, which an
  account joins on every first-time sign-in. Read [docs/caching.md](docs/caching.md) §
  The other APCu tenant before touching any of it.
  **Authentication is JUser alone, and it is passwordless** — ZfcUser is not installed and
  has not been for some time; the surviving `zfcuser/*` route names and `ZfcUser*` class
  names are names, kept because renaming a route breaks every `url()` call and guard entry
  that names it. Signing in is a magic link; registering is the *same request*, since an
  unknown address becomes an account (`UserTable::createUserFromEmail()`), and redeeming
  the link is also what verifies the address. So there is no password, no separate
  confirmation step and, since 2026-08-20, no `/user/register`, `/users/thanks` or
  `/users/verify-email` either — `user.password` and `user.must_change_password` went with
  them (`database/db8.4.sql`, `db8.5.sql`). The live token store is
  `user.verification_token` + `verification_expiration`, which look like password-era
  artefacts and are the opposite: see `JUser\Service\LoginTokenService`.
  **`user.state` and `user.email_verified` mean two different things, and only since
  2026-08-21.** `state` — the Active checkbox on `/users/{id}/edit` — means *may sign in*,
  and it is now enforced in **four** places: no link is mailed to a deactivated account, a
  link already in flight is refused with a 403, `App\Api\BotIdentity` refuses its API
  tokens (a bearer token is a sign-in that skips the sign-in page, so it answers to the
  same switch), and `JUser\Authentication\Storage\SessionUser::read()` refuses to resolve
  it — which is the one that reaches a session already open, so revoking access takes
  effect on the next request rather than at some invisible session timeout. That fourth one
  needed no new machinery: only the user id is in the session, so the row is re-read every
  request, and `isEmpty()` already clears the storage when a read comes back null. Since
  2026-08-22 that re-read is served from the `all-linked-users` cache when it is warm, which
  changes nothing for a revocation made in the application — unticking Active invalidates the
  key — but does mean a `state` written straight to the database (a migration, a DBA) is
  invisible to an open session until something writes to a user through the app. See
  [docs/caching.md](docs/caching.md). The
  create form ticks Active for the same reason it all hangs together —
  `EditUserForm` declares `'value' => 0`, so without
  `App\Controller\UserCreateController` setting it an administrator would create accounts
  that can never sign in and are never told why. `email_verified` means *someone has proved they read mail here*, and
  redeeming a link is what sets it, in `clearVerificationToken()`. Before this, `state`
  could not express anything: new accounts were created inactive and `verifyAction()`
  activated whatever it redeemed, so every deactivated account reactivated itself on its
  next link — 32 of 292 real accounts sat at `state = 0` and could all sign in.
  `database/db8.6.sql` moved those 32 to `state = 1` **before** the code shipped, because
  they were stalled registrations rather than bans and would otherwise have been
  retroactively locked out. A new account is therefore created **active and unverified**;
  creating it inactive would have the deactivation check refuse the very first magic link
  and break open registration entirely. Guarded by `test/Smoke/AuthSmokeTest` (six of its
  twelve tests) and `ApiV3SmokeTest::testADeactivatedBotAccountIsRefused`.
- **Borrowers reach their own books without an account.** An overdue notice carries a scoped link to `/library/my-books?t=…`; the token (`Books\Model\BorrowerTokenTable`, table `lib_borrower_tokens`) authorises exactly one person at one library, is stored as a sha256 digest, expires, and is deliberately **not** single-use. It exists instead of giving borrowers accounts because this database has **no user-to-person link at all** and every account inherits `lib_user`, which is `is_default = 1`. The page is Symfony-side precisely so its authorization is ordinary code rather than a role: no `person_id` may ever appear in that URL. Renewal is `LibraryTable::renewBook()` — it persists, counts, and enforces `lib_libraries.MaximumBookRenewals` (default 3); overdue books renew from *today*. Notices go out via `bin/console books:send-notices --library=N [--dry-run]`, which needs no API key.
- **`SchoenstattTable::linkAssociations()` takes `$objectsAreEveryAssociation`**, and only
  `getAssociations()` may pass `true`. It skips the related-associations query, which asked
  the database for the parents and children of rows the caller already held — 431 of 498,
  none of them absent or different. That query was the whole of the shrine page's
  non-database time: `getAssociation()` went **344–399 ms → 21–36 ms**, peak memory 40.6 →
  25.4 MB, on 2026-08-22. The subset callers (`searchAssociations()`, `getShrines()`,
  `getWaysideShrines()`) still query, because a filtered match's parent can lie outside the
  set. The related rows stay a **separate snapshot** even when copied, because linking
  `$objects` to rows that are themselves being linked would make the graph cyclic.
  **The order inside that method is load-bearing and was wrong until 2026-08-22:**
  `connectEntityRolesAndAssignments()` now runs *first*, because what the linking attaches
  are copies of that snapshot, and a copy taken before it carries `roles => []`,
  `assignments => []` and `mainPerson => null`. The keys are declared in
  `processAssociationRow()`, so nothing was missing and nothing errored — 791 of 792 linked
  rows simply held empty values, and the leader column of "Associated organizations" was
  blank for every child that has one. The links themselves are **plain copies, not PHP
  references**; the reference saved no live memory (array assignment is copy-on-write) and
  only aliased rows reachable by two paths. It does shrink `serialize()`, which is why the
  one in `LibraryTable::getCheckouts()` stays — see docs/BACKLOG.md.
  Guarded by `test/Integration/AssociationLinkingTest`, whose two-path comparison could not
  see the ordering bug (both paths were wrong the same way) and which now also compares an
  attached row against the same row under its own id. The same `orCombination` shape, and
  the same references, were live in `PublicationsTable` until 2026-08-22.
- **The publications side got the opposite answer, and that is the point.**
  `PublicationsTable`'s related query looks identical to the association one and is **not**
  redundant: measured before changing anything, it returned 71 of 73 rows new on the German
  literature index, 56 of 56 on the English one and 138 of 196 on a search, because a
  publication result set is filtered and `getPublication()` starts from one record. What was
  wrong is that **`sch_publications` had only its primary key** across 10,166 rows, so both
  of the page's related queries were full scans. `database/db8.7.sql` indexes
  `MainPublicationId` and `TranslatedFromPublicationId` (198 and 257 non-null rows) and
  `getPublication()` went **54–71 ms → 5.0–5.7 ms**, four statements to three — the third
  was `linkPublication()` calling `searchPublications()` without `noLink`, so that call
  linked its own results and nothing read them (`FormatPublication` reads none of the four
  link keys). `PublicationsTable::getPublications()` was **deleted** in the same pass: no
  callers, and it linked `$entities` to *itself* by reference and cached the result.
  Guarded by `test/Integration/PublicationLinkingTest`. **Nine of the nineteen tables over
  200 rows still have no index but their primary key** — the application survives it by
  caching whole tables, so it only bites where a table is too big to cache and is queried by
  a non-key column (docs/BACKLOG.md).
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
  - **This line said "a 2021-06-24 production dump" until 2026-08-13 and that was five years wrong.** The 2021 dump was only the first import, on the day the repo was reopened; a fresh export replaced it within days. The stale claim is worth flagging rather than just deleting, because it does not read as a bug — it reads as a reason to distrust the capsule, and it was repeated as fact in `docs/strangler.md` and in a dozen test comments. It cost a wrong risk assessment on the batch-6 deploy: "production has five years more data" was offered as a caveat when the capsule's data is days old. **The capsule's row counts and payload sizes are representative of production**; when they are not, say which table and measure it. **`user` is the one table where they are not, and the exception is the test suites' own doing:** on 2026-08-21, 6,011 of its 6,303 rows were `@example.com` accounts left behind by nine smoke classes that never purged, against 292 real ones — enough to make `getUsers()` cache 14 MiB instead of 0.55 MiB and `/users` render 6,303 rows instead of ~292. The leak is fixed (`MagicLinkSignIn` purges from an `#[After]` method, which a class's own `tearDown()` cannot silently override) but the lesson outlives it: **exclude `%@example.com` before taking a count off `user`**, and check the other end too — `trans_phrases` grows the same way, from ported pages filing missing phrases during a baseline capture.
- `.env` holds HOST_UID/HOST_GID so Apache workers can write to the bind-mounted `data/` dir.
- **Resource ceilings are deliberate — do not raise them casually.** `docker-compose.yml` caps each service (`mem_limit`/`memswap_limit`/`cpus`/`pids_limit`; app 4g/2 CPUs), and the image caps Apache at 6 prefork workers plus a 60s PHP `max_execution_time` (`docker/apache-limits.conf`, `docker/php-limits.ini`). These exist because on 2026-08-02 a wedged app under concurrent load exhausted 15.5 GB of host RAM twice and pinned every core: nothing bounded Apache's 150 default workers × the 512M `memory_limit` set in `public/index.php`. Changing a limit requires `docker compose build && docker compose up -d`.
- **The `db` container's ceiling was never the problem; glibc was.** The db was OOM-killed
  (`exit=137`, `OOMKilled=true`) on four separate `tools/ci-local.sh` runs, each time
  part-way through the smoke suite and each time surfacing as ~300 test failures plus
  `getaddrinfo for db failed` — which reads as a network fault, so **check
  `docker inspect --format '{{.State.OOMKilled}}'` before believing a mass smoke failure**.
  The cause is not concurrency and not the buffer pool: `thread_handling` is
  `one-thread-per-connection`, a smoke run opens ~3,000 connections *in sequence*, and glibc
  gives each new thread its own malloc arena and never returns it. Measured 2026-08-21 — the
  db held **858 MB** of anonymous memory (cgroup `memory.stat`; `file` was 7 MB, so page
  cache is not the story) while serving a suite whose **peak concurrency is 2**, and
  MariaDB's own `Memory_used` accounted for only 451 MB of it. `MALLOC_ARENA_MAX=2` in the
  service's `environment` fixes it: the same workload plateaus at **267 MB**, and a second
  smoke run adds 307 KB. `--max-connections=50` sits alongside it as a guard rail, not a
  fix — and note the floor, because a cap below it fails *invisibly*: the integration suite
  peaks at **36** connections, and at a cap of 20 the 21 refused connections became 21
  **skips**, not failures, since those tests treat a connection error as "no reachable
  database". `ci-local.sh` printed `ok integration` for a run that had stopped testing 21
  things.
- Known latent issue: `SchoenstattTable::getRules()` is unfinished 2020 WIP referencing roles that don't exist in the DB; the bjyauthorize provider registration is disabled in `module/Schoenstatt/config/module.config.php` (see NOTE there). Do not re-enable without finishing the feature.

## Verifying code

- **CI runs again since 2026-09-01, and the first real run in three and a half weeks found
  two breakages.** The account's 2,000 GitHub Actions minutes/month allowance was exhausted
  on 2026-08-14, so from then until the monthly reset every workflow run failed in ~2
  seconds with **no runner assigned and zero steps executed**. That shape is worth
  remembering rather than forgetting, because it reads exactly like a build break and is a
  quota: confirm it with
  `gh api repos/jroedel/schoenstatt.link/actions/runs/<id>/jobs --jq '.jobs[] | "\(.name): \(.conclusion) steps=\(.steps|length) runner=\(.runner_name)"'`
  (the annotation naming the reason needs `checks:read`, which a fine-grained PAT cannot
  hold). What accumulated in the gap — four `AclCacheTest` errors from an unreachable
  in-body skip, and seven `Undefined array key "db"` warnings that `failOnWarning` turns
  fatal — is the argument for the paragraph below.
- **`./tools/ci-local.sh` is still the stricter check, and the PR body should say so**,
  because the reflex is to read local verification as second best. It mirrors ci.yml's
  seven jobs in order — lint, composer `--no-dev` rehearsal, PHPStan level 0, PSR-12 on the
  clean paths, unit, integration, and every `test/Deploy/*-test.sh` — and then runs
  **smoke, fuzz, and `tools/smoke-prod.sh` itself, none of which CI can run at all**
  because they need a live Apache/MariaDB/APCu. `--ci` skips the ~4-minute smoke suite, the
  fuzz harness and the smoke-script run; the deploy-machinery checks run either way, since
  they need no server.
  - **Quantified 2026-09-08, because a green tick on GitHub overstates itself:** the
    integration job there reports `Tests: 1334, Assertions: 3085, Skipped: 195`; the same
    suite in the capsule reports `Tests: 1364, Assertions: 7214, Skipped: 14`. CI executes
    about **43% of the assertions** and skips 181 more tests. That gap is structural and is
    not a pending task — `database/` holds ninety incremental migrations and **no base
    schema**, the capsule's data is a gitignored production export, and many of these tests
    assert against real rows (527 events, the per-library ACL rules, the sort-text
    coverage), so a MariaDB service container would make them *fail*, not pass. **Do not
    propose one.**
  - What is enforced instead is that the gap stays *named*. `tools/check-ci-skips.php --bare`
    pins the **set of classes** that skip against `test/known-ci-skips.txt`, so a class that
    used to run on CI and stops fails by name rather than disappearing into the number 195;
    a baselined class that starts running prints as stale, the same contract
    `test/Fuzz/known-form-gaps.php` uses. `--bare` is mandatory and not detected: against a
    capsule log every verdict in that file inverts, and an earlier draft that guessed from
    the ratio of stale entries would have silenced a real finding whenever several classes
    were fixed at once.
  - **`tools/smoke-prod.sh` is not the `smoke` suite.** The suite is PHPUnit under
    `test/Smoke`; that script is the bash one the *deploy* runs against production as its
    last step. Until 2026-08-19 nothing but a deploy had ever executed it, so its own
    bugs could only be found by shipping them — a grep for a key that exists in **two**
    sections of one JSON document killed a run with `77\n838 / 60: syntax error` after an
    otherwise successful deploy. `ci-local` now points it at the capsule, which needs the
    sitemap rebuilt for `http://localhost:8080` first (`sitemap:build --url`, 2.4s)
    because the script filters the sitemap index by base URL, and that strictness is
    worth keeping rather than teaching it to accept a foreign host.
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
  - **The skip branch classifies on what a real finding looks like, not on what a known
    network error looks like**, and that inversion was bought the hard way: it used to
    match only `Could not resolve host`, so on 2026-08-16 a packagist **HTTP 502** — a
    different message entirely — was reported as `FAIL` and blocked a cutover while
    reading, to every human, as "a vulnerability was found". Nothing was wrong with the
    lock and the same run passed minutes later. A clean result and an advisory finding
    each have a fixed shape; **anything else is UNKNOWN**, retried once and then printed
    in full, because an unreadable database is a fact about the network and a reader who
    cannot see the text cannot tell the two apart. Do not "tighten" this back into an
    error-string allowlist.
- HTTP characterization tests live in `test/Smoke` (PHPUnit, `phpunit.xml.dist`). They run against a *running* capsule, not in isolation.
  - Run them with `php composer.phar smoke` — **one process at a time.** Never fan the suite out across parallel agents or background shells, and never run a second copy while one is in flight: concurrent runs against a wedged app are what exhausted the host on 2026-08-02.
- Unit tests live in `test/Unit` (`php composer.phar unit`); `php composer.phar test` runs every suite. Unit tests talk to no HTTP and require the class under test directly — no vendor autoload, no running app — so they are safe to run freely and stay valid while `vendor/` is mid-migration. All three scripts shell into the capsule: the host PHP lacks the dom/mbstring/xmlwriter extensions PHPUnit needs.
  - Before blaming a test, check whether every response is a ~800-byte HTTP 200 — that is the fatal-200 wedge, not a test failure.
- Integration tests live in `test/Integration` (`php composer.phar integration`). They build the ServiceManager and load modules the way `bin/console` does but never `bootstrap()`, so they can read merged config and reach the database without a running app.
- **Form input validation is guarded by a fuzz harness** in `test/Fuzz` (`php composer.phar fuzz`). It discovers every form under `module/*/src/Form/` from the filesystem — never a hardcoded list — and asserts five structural properties plus the invariant that `isValid()` answers rather than throws, driving 43 hostile values per field. It is deterministic (seed printed every run), makes no HTTP request and writes nothing to the database (asserted, not just claimed). Contract is **"no new gaps"**: currently-accepted ones live in `test/Fuzz/known-form-gaps.php`, compared as a *subset*, so a new gap fails and a fixed one prints as stale. Regenerate with `php composer.phar fuzz-baseline` and read the diff — **every added line is a validation gap being accepted.**
  - **`disable_inarray_validator => true` on the element is what removes a choice field's domain — not naming it in the spec.** `Laminas\InputFilter\BaseInputFilter::add()` *merges* into an existing input rather than replacing it, so a `Select` named in `getInputFilterSpecification()` keeps its own `InArray` and gains the spec's. This was documented backwards until 2026-08-15 and the harness measured the specification accordingly, reporting 88 unprotected fields when 53 were protected by their own element. **Anything reasoning about which validators apply must read the assembled `getInputFilter()`**, which is what `FormGapCollector` and `ConstrainedChoiceFieldsFitTheirDataTest` now do.
  - **A missing `InArray` on a choice field is two opposite findings**, and the baseline separates them. `choiceFieldsWithoutDomain` is a gap to close, with `SionModel\Form\ChoiceDomain::validators($this->get($name))` — it takes the haystack from the element, which works because the spec is built lazily at `isValid()` time, after the factory populated the options; pass a second argument as the widest legitimate domain when the options only arrive at request time (`AssignmentForm::roleId`). `choiceFieldsOpenByDesign` is a field the view lets a moderator type into (`selectize({create: true})` — tags, contributors, contact labels), where an `InArray` would remove a feature rather than close a hole; those are declared, with the reason, in `test/Fuzz/open-ended-choice-fields.php`, and a declaration that stops matching a field fails the suite. The `create:` flag is evidence of intent, not of enforcement — `AdvancedSearchForm::roleTitle` was declared open on it while the server rejected typed values.
  - **Check the stored data fits the option list — and that check is not optional even when you add nothing.** `getEditionValueOptions()` excludes 5,963 of 10,166 publications and 479 books point at an excluded one, so `BookForm::publicationId` refuses those books *today*. `ConstrainedChoiceFieldsFitTheirDataTest` compares every constrained field's real haystack against every stored value; four known mismatches are accepted there by name and row count, and an accepted entry that stops mismatching fails the suite.
  - Two mechanisms to know before touching any form, because both make protection silently absent: a `'filters'`/`'validators'` key in an *element* definition is discarded (`Laminas\Form\Factory::configureElement()` reads only `name`/`options`/`attributes`), and an element missing from `getInputFilterSpecification()` gets `['required' => false]` and nothing else. The harness checks for both.
- **Authorization changes must be diffed, not just tested.** `docker compose exec -T app php tools/acl-table.php` emits a reviewable table of every role, guard and rule; `--format=json` emits the sorted, diffable form. `docs/acl-rules.md` and `docs/acl-baseline.json` are the committed snapshots. Regenerate and diff them after any change to a route, a guard entry or a role — a rule that quietly stops matching makes a page work for *more* people and nothing fails.
- Beyond smoke, verification is lint + coding standard:
  - Syntax check any file you touch: `php -l path/to/File.php`.
  - Coding standard: `php composer.phar cs-check` (phpcs, PSR-12 based; see `phpcs.xml` — it covers `src`, `config`, `module/{Application,Books,Schoenstatt}`, and `public/index.php`). **It exits non-zero and always will at this scope** — measured 2026-08-06: **425 errors / 450 warnings across 254 files**, not the four cosmetic findings this line used to claim. Almost all of it is `.phtml` under `module/{Application,Books,Schoenstatt}`, which the config sweeps in wholesale. What *is* clean, and must stay clean: **`src` and `config/symfony`** (zero findings — the Symfony-side code holds the standard), the two files that left `src` for SionModel on 2026-08-21, `module/JUser/src/{Host,Page,Controller,Routing,Twig}` and all of `module/JTranslate/{src,config,templates}` — each covered by its own submodule's `phpcs.xml`. `public/index.php` has 2, `config` 12 including the untracked `*.local.php`. So the exit status carries no signal at all: **run phpcs with your own paths as arguments** and judge those, e.g. `… vendor/bin/phpcs src config/symfony`. Narrowing `phpcs.xml` to exclude `.phtml`, or fixing the 421 auto-fixable violations, is a decision nobody has taken; the host PHP also lacks the tokenizer/xmlwriter/SimpleXML extensions phpcs needs, so run it in the capsule (`docker compose exec -T app php vendor/bin/phpcs`, optionally with a path argument).
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

- A remote call that stops answering no longer hangs the deploy: every ssh call is
  bounded by `timeout` (180s default, per-call overrides), ssh keepalives catch a dead
  network path, and a heartbeat prints elapsed seconds to stderr so a slow step and a
  wedged one stop looking alike. Added 2026-08-18 after a hang on "Warming the
  release"; see [docs/DEPLOY.md](docs/DEPLOY.md) § When a step stops answering.
- Deployment is `./tools/deploy.sh` — one command, rsync over the port-222 shell account, **atomic**: a release is built, composer-installed and warmed in a directory nothing is serving, and goes live when one symlink is replaced by a single `rename(2)`. **Never run a deploy** — if asked, give the user the command to run instead.
- The server layout is `releases/<ts>-<sha>/` + `shared/` + a `public` symlink. Two distinctions there are load-bearing and both fail silently if got wrong: `data/config` and `data/cache` are **per-release** (a new tree meeting an old merged-config cache is what produced a fatal burst on every deploy), and `data/publications` is tracked repo content rather than shared state. Read [docs/DEPLOY.md](docs/DEPLOY.md) § The layout before adding anything to `shared/`.
- **A symlink swap is invisible to OPcache, and this host runs three of them.** The previous release keeps executing after the swap, with nothing in any response saying so, and each of the three PHP pools has its own segment, so one reset fixes a fraction of traffic. (The mechanism is **not** `opcache.revalidate_path`, which this line claimed until 2026-08-18 and which was measured not to change the outcome; OPcache files entries under the resolved path, and the capsule's staleness expires at `realpath_cache_ttl`. Do not propose `revalidate_path=1` as a fix — `test/Deploy/opcache-swap-test.sh` is the repro.) The deploy therefore resets every opcode cache and then polls `/_health` twelve times for the release's own `.revision`, and **runs no post-deploy migration until they agree**. A migration that older code cannot survive must declare `-- @destructive: yes`; both rollback paths then refuse a target release that does not ship it. All of this was bought on 2026-08-17 — read [docs/incident-2026-08-17-stale-opcache.md](docs/incident-2026-08-17-stale-opcache.md) before changing the swap, the phases, or the rollback. **The capsule reproduces the swap hazard** (`test/Deploy/opcache-swap-test.sh`, which also proves `opcache_reset()` still clears it — the deploy's whole gate rests on that); it cannot reproduce the *multi-segment* part, which is the half that actually failed on 2026-08-18.
- **Database migrations run through a ledger**, `sch_migration`, applied by `tools/migrate.sh` and called by the deploy at both phases. An applied migration's bytes are frozen; `tools/migrate.sh reseal <file>` re-records the hash when only *comments* changed, proving via git history that no statement moved. A new `database/*.sql` must carry `-- @phase: pre|post`, `-- @kind: dml|ddl` and `-- @tables:`; `@phase` has **no default** because both orders are real here and guessing wrong is silent (schema-before-code for JTranslate 003/004, code-before-data for every `db7.x` retirement, which discovery would otherwise un-retire). A `dml` migration's ledger row commits *inside its own transaction*, so half-applied is impossible; `ddl` cannot have that and must declare `@idempotent: yes`. Full-DDL credentials live only in `.deploy.local` and reach production through an SSH tunnel — never store them on the server. Read [docs/DEPLOY.md](docs/DEPLOY.md) § Database migrations before writing one.
- **A release is exactly `git ls-files --recurse-submodules`.** Untracked files on the server inside `public/` are therefore deleted by a swap — search-engine verification files live in `shared/public/`, which is symlinked into every release. `tools/deploy-bootstrap.sh --check` lists what a swap would remove.
- **phploy is gone** — deleted from the repository 2026-08-16, after the first atomic deploy succeeded (its SFTP transport is what made the deploy window unavoidable, and `--submodules` had a tree-deleting purge bug). Do not reintroduce it or reason from it; `docs/history.md` and the dated sections of `docs/DEPLOY.md` are where it survives, as history. `.deploy.local` replaces both of its credential files; it holds the SSH target, the maintenance API key and full-DDL database credentials, and like its predecessors must never be committed, printed or copied.

## Tools

- Prefer `rg` (ripgrep) over `grep`.
- Externally run CLI/tool exit status: always capture with `EXIT_CODE=$?` on the line right after the command, then test `$EXIT_CODE`. Never read `$?` after any intervening command.
- Multiple tools in one command: chain with `&&` so the first failure breaks the chain and a single `EXIT_CODE=$?` covers the whole run — no intermediate results. Override only when the user asks.
- Use `php composer.phar` (the repo-local composer) rather than a global `composer`.
