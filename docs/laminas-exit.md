# The laminas exit

The plan, and the rules, for taking schoenstatt.link from "laminas modules under a Symfony
kernel" to an application with **no `laminas/*` package at all**. Decided 2026-09-09: the
goal is to minimise dependencies; laminas's team is dwindling; there are **no exceptions**
for individual laminas packages. This document replaces `strangler.md` (the first
strangler, laminas-mvc → Symfony kernel, finished 2026-09-08) and the Phase C spec.

Read this before touching `src/`, `config/symfony/routes.php`, `App\Kernel`, or anything
under `module/*/src` that a Symfony-served request reaches.

## 1. Where we are

- **One front controller.** `public/index.php` runs `App\Kernel` (symfony/http-kernel,
  hand-wired, no FrameworkBundle) unconditionally. Every page, form, API endpoint and the
  404 is Symfony-served. `LegacyBridge`, the `SYMFONY_KERNEL` canary and laminas-mvc's
  dispatch are gone; rolling back to a laminas front controller is not possible.
  `curl https://schoenstatt.link/_health` → `{"status":"ok","kernel":"symfony"}`.
- **Routes** are declared in `config/symfony/routes.php` (106 declarations through the
  `$ported`/`$edit`/`$create`/`$delete`/`$libraryPage` helpers, plus JUser's and
  JTranslate's route closures). HTML renders with **Twig** from `templates/` and
  `module/{JUser,JTranslate}/templates/`.
- **Authorization** is `symfony/security-core` (`App\Acl\AclVoter`, `Authorizer`,
  `AclAssembler`) reading the roles, guards and rules still declared in `bjyauthorize`
  config keys (`config/autoload/acl.global.php`, module configs). bjy-authorize and
  laminas-permissions-acl were removed 2026-09-08. `docs/acl-baseline.json` is the diff
  oracle; regenerate with `tools/acl-table.php --format=json` after any change.
- **Authentication** is JUser's passwordless magic link over
  `JUser\Authentication\SessionIdentity`, which keeps the user id in the session and
  re-reads the account every request. laminas-authentication was removed 2026-09-09.
- **laminas-mvc is gone** (step 0, 2026-09-09), with mvc-i18n, the three mvc plugins,
  `diablomedia/laminas-twb-bundle` and `slm/locale`. The container is built by
  `App\Laminas\ContainerFactory`, the view helpers by `App\Laminas\ViewHelperManagerFactory`,
  `MvcTranslator` by `App\Laminas\TranslatorFactory`; messages live in
  `SionModel\Messaging` behind `App\Laminas\HostMessages`; mail renders through Twig.
  `composer.json` has no `repositories` fork entry left but the chordpro one.
- **What laminas still does**, and therefore what this plan removes: the service
  container and module/config loading (`laminas-servicemanager`, `laminas-modulemanager`,
  `laminas-eventmanager` — direct requirements since step 0), the database layer
  (`laminas-db`, under `SionModel\Db\Model\SionTable`), forms and validation
  (`laminas-form`, `inputfilter`, `validator`, `filter`), translation (`laminas-i18n`,
  under JTranslate), session, cache, the view-helper classes Twig bridges (`laminas-view`),
  and URL generation from the laminas router config.
- `App\Laminas\ServiceBridge` is the seam: a lazily built laminas `ServiceManager` a
  Symfony controller asks for laminas-side services. It disappears at step 7.

## 2. The dependency picture (measured 2026-09-09, after step 0)

**32** `laminas/*` packages are installed, down from 37. The two facts that shape the
order, and step 0 confirmed both:

**Removing laminas-mvc was necessary, not sufficient, for anything but the PHP pin.**
`php composer.phar why-not laminas/laminas-servicemanager 4.0.0` named fourteen cappers
before step 0 and names ten after it, plus our own direct `^3.24` line. They are current
laminas components **at their latest releases**, each requiring `laminas-servicemanager
^3.x` only:

| package | latest | servicemanager constraint |
|---|---|---|
| laminas-cache 3.x | 3.14 (4.3 needs SM ^4.1) | `^3.21` |
| laminas-filter | 3.4.0 | `^3.21` |
| laminas-form | 3.24.2 | `^3.22.1` |
| laminas-i18n | 2.33.0 | `^3.21`, **and `laminas-cache ^3.13`** |
| laminas-inputfilter | 2.35.0 | `^3.21` |
| laminas-router | 3.19.0 | `^3.14` |
| laminas-session | 2.27.0 | `^3.23.1`, **and `laminas-cache ^3.13`** |
| laminas-text | 2.13.0 | `^3.22` |
| laminas-validator | 3.18.0 | `^3.21` |
| laminas-view | 3.1.0 | `^3.21` |

So servicemanager 4, laminas-cache 4 (and with it `psr/cache` 2/3 and FrameworkBundle)
are unreachable by upgrading. They become reachable only by **removing** the packages,
which is this plan. The PHP 8.5 pin (`config.platform.php` 8.4.24) had eleven blockers;
step 0 removed six and **five remain** — `laminas-math`, `laminas-serializer` and the
three cache adapters — so the platform stays 8.4.24 until steps 1 and 2.

**Measure, never assume.** Before and after every step: `why-not php 8.5.0`,
`why-not laminas/laminas-servicemanager 4.0.0`, and `composer show --locked | grep laminas`
— the count of the last is the progress metric. Step 0 took it 37 → 32, step 1a 32 → 28,
step 1b 28 → 27.

**Most of what step 1 listed is blocked behind later steps, and the measurement says so.**
`laminas-uri` is required by `laminas-http` *and* `laminas-router`; `laminas-http` is what
`App\JUser\Host\RouteResolver` and `tools/acl-table.php` hand to the laminas router to
match against. So both leave at step 6, not here. `laminas-json` is required by
`laminas-view` and `laminas-serializer`, and `laminas-serializer` *is* the laminas-cache
serializer — step 4 and step 2. What was actually removable in step 1 was the four
packages of 1a and `laminas-navigation`.

**Declare what you use, or removal takes something with it.** Dropping `laminas-captcha`
in step 1a also dropped `laminas-session`, because captcha was the only package requiring
it and this application never declared it — `App\Http\SessionListener` and
`SionModel\Messaging\FlashMessages` use it on every request. Eight packages were in that
state and are now direct requirements (cache, escaper, filter, inputfilter, session,
stdlib, uri, validator). `tools/laminas-audit.php` is the check: for every installed
`laminas/*`, does our own code use its namespace, and does `composer.json` say so.

## 3. The order

Steps are ordered so each is deployable alone and the least-coupled packages go first.
Footprints are file counts outside `.phtml` and outside the laminas controllers step 0
deletes.

| step | removes | footprint | replacement |
|---|---|---|---|
| 0 ✅ | laminas-mvc, mvc-i18n, mvc-plugin-{identity,flashmessenger,prg}, diablomedia/laminas-twb-bundle, slm/locale | see §4 | **done 2026-09-09.** Own container bootstrap; own view-helper manager; `Laminas\Validator\Translator\Translator`; session-backed flash store; Twig mail templates |
| 1a ✅ | laminas-captcha, recaptcha, text (**0 uses**), math (6 `Rand`); our direct `laminas-json` line (8 call sites → `App\Json`) | 12 files | **done 2026-09-09.** `random_int`/`random_bytes`; `App\Json` reproduces the two behaviours that were load-bearing |
| 1b ✅ | navigation (config-only: nothing resolved the service) | 3 config files | **done 2026-09-09.** `App\View\NavigationTree` already built the tree from the `navigation` config key, which stays |
| 1c | json (the package itself — laminas-view and laminas-serializer still pull it), serializer (it is the laminas-cache serializer), uri (7 files, but laminas-http **and laminas-router** require it), http (`Client` in two gateways and a console command; `Request` only to feed the laminas router) | ~15 files | `symfony/http-client` or ~20 lines of our own; the rest unblock at steps 2 and 6 |
| 2 ◐ | laminas-cache + 3 adapters + serializer ✅, laminas-authentication ✅, laminas-session (**blocked**, see below) | ~48 files | **cache and authentication done 2026-09-09.** `SionModel\Cache\Storage` on APCu and the filesystem, ours; `JUser\Authentication\SessionIdentity` behind the `Host\IdentityInterface` the module already declared. The `psr/cache` 1 pin is lifted |
| 3 | laminas-i18n (23) | JTranslate is the layer | `symfony/translation` (6.4 already installed transitively); the precompiled PHP-array catalog format stays |
| 4 | laminas-view (32 `AbstractHelper` subclasses), laminas-escaper | ~46 files | Twig extensions; `App\Laminas\EntityFormatter` already wraps the biggest helper |
| 5 | laminas-form, inputfilter, validator, filter | 36 forms, ~170 files | Symfony Form + Validator. The fuzz harness (`test/Fuzz`) and `ConstrainedChoiceFieldsFitTheirDataTest` are the safety net; `AssociationValidationParityTest` keeps web and API validation identical |
| 6 | laminas-router (27) | every route is declared twice today | Symfony router only; `laminas_path()` → `path()`; ACL resources keep the route names |
| 7 | laminas-servicemanager (76 `FactoryInterface` factories), modulemanager, eventmanager, stdlib | ~120 files | Symfony DI; FrameworkBundle is installable after steps 2 and 3, and `App\Kernel` is what it replaces |
| 8 | laminas-db (96 files; `SionTable` is 2,413 lines over `TableGateway`/`Sql`) | the largest | Doctrine DBAL (decision pending, §6) |

**laminas-session does not leave at step 2.** `Laminas\Validator\Csrf` reads a
`Laminas\Session\Container`, and every form here carries a CSRF element, so the session
package is held by the forms and leaves with them at step 5. What step 2 removed is the
*authentication* use of it: the identity no longer goes through a laminas storage adapter,
only through `JUser\Host\SessionInterface`, whose one implementation is the host's.
Measured 2026-09-09, the same way step 1's blockers were.

`laminas-hydrator`, `config`, `loader`, `translator` are transitive glue and leave with
their parents. Steps 2 to 8 rewrite code in the **shared submodules** (SionModel, JUser,
JTranslate); see §6 before starting any of them.

## 4. Step 0 in detail: removing laminas-mvc

### 4.1 What laminas-mvc still provides

- **Nothing, at runtime.** Every container is built by `App\Laminas\ContainerFactory`
  (event managers, `ModuleManager` with the default listeners and a `ServiceListener` for
  `service_manager` and `view_helpers`); it defines `ViewHelperManager`
  (`App\Laminas\ViewHelperManagerFactory`) and `MvcTranslator` (a
  `Laminas\Validator\Translator\Translator` over the canonical laminas-i18n translator,
  `App\Laminas\TranslatorFactory`) under the ids ported code asks for, and registers the
  two delegators. Nothing asks for `Application`, `ControllerPluginManager` or
  `ViewRenderer`. `Router` survives — laminas-router's own ConfigProvider provides it. The
  packages are still installed and their modules still listed in `modules.config.php`;
  removing both is batch 4.
- **No `.phtml` is rendered.** The two mail templates are Twig
  (`module/SionModel/templates/mailing/`, `module/Books/templates/mailing/`), rendered by
  `SionModel\Mailing\Mailer` through `TemplateRendererInterface` in a mail-only Twig
  environment (`SionModel\Service\TemplateRendererFactory`, paths from
  `sion_model.mail_template_paths`). The only `.phtml` left in the tree are JUser's bridge
  helpers, unused here.
- **No controller, no `onBootstrap()` hook** is left in any module except the translator
  half of JTranslate's, which runs only under a laminas ModuleManager bootstrap and is kept
  for patres until it adopts a configurator of its own.
- **The flash/now layer** is `SionModel\Messaging\FlashMessages` (session container
  `FlashMessenger`, one `SplQueue` per namespace, one hop — the plugin's own layout, so a
  flash survives the deploy in either direction) and `NowMessages`, behind one
  `App\Laminas\HostMessages` per request that every ported controller takes;
  `JTranslate\I18n\MessageRenderer` renders both from `LaminasExtension`.
- **TwbBundle** is reached from nothing: `App\View\Label` renders the label. **SlmLocale**:
  no Symfony-side code reads its config; `App\Http\LocaleListener` reproduces it and keeps
  the cookie *name* `slm_locale`.
- `laminas-mvc-plugin-prg` and `-identity` have zero call sites.

### 4.2 Design

- `App\Laminas\ContainerFactory::build(array $appConfig, ContainerOptions)` — registers
  `EventManager`, `SharedEventManager`, `ModuleManager` (DefaultListenerAggregate +
  ServiceListener, as laminas-mvc's factory does minus the MVC services), loads modules.
  Options: config caches on/off; pre-built `PhraseFlush`/`CacheFlushQueue` instances.
  Used by `ServiceBridge`, `bin/console`, `tools/acl-table.php`, `FormRepository` and a
  `test/Integration/LaminasContainer.php` helper.
- `App\Laminas\ViewHelperManagerFactory` under the id `ViewHelperManager`: builds
  `HelperPluginManager` from `view_helpers` config, wires the router into the `url` helper
  (`FormatEntity`, `EditPencil`, `EditPencilNew` call `$this->view->url()`), attaches a
  bare `PhpRenderer` so `$this->view` exists. Translator injection is automatic while the
  `MvcTranslator` id exists — keep that id through step 0.
- `Laminas\Validator\Translator\Translator` registered as `MvcTranslator` (and as
  `Laminas\Mvc\I18n\Translator` for SionModel's factory map) around the container's
  `Laminas\I18n\Translator\TranslatorInterface`. `TranslatorConfigurator`'s delegator moves
  to that canonical interface — delegate the canonical id, never an alias (aliases resolve
  before delegators are looked up; a delegator on an alias silently never runs).
- `SionModel\Messaging\FlashMessages`: a `laminas-session` `Container` under the **same**
  key `FlashMessenger`, the same five namespace strings, so a flash written by the release
  being replaced renders in the release that replaces it. One instance per request
  (`HostMessages` memoises; a second instance drains the first's messages). `NowMessenger`
  becomes a plain per-request service. Rendering moves into `JTranslate\Twig\JTranslateExtension`.
  `JUser\Host\Severity` and `JTranslate\Host\Severity` keep the strings; the two
  host-contract tests pin them as literals.
- Mail templates: port the two `.phtml` to Twig behind a two-method
  `TemplateRendererInterface` on `SionModel\Mailing\Mailer` (recommended; JUser's mailer is
  Twig already), byte-compared with `books:send-notices --dry-run`. Fallback: a 40-line
  PhpRenderer factory.
- `App\View\Label` replaces `TwbBundleLabel`; `SionFormRow` is deleted.
- What stays until later steps: Application's `view_manager.doctype` (read by our
  `ViewHelperManagerFactory` for the bridged form helpers; goes with laminas-view, step 4)
  and the **`router` config** (step 6).
- `TranslatorConfigurator` attaches JTranslate's missing-translation listener **on the
  first miss**, not at construction: the table it needs is built through JUser's user
  table, whose factory builds JUser's mailer, whose factory asks for the translator —
  resolving it inside the translator's own delegator recurses until memory runs out.
- `public/index.php`: the install check becomes `class_exists(App\Kernel::class)`.
- Shared libraries: JTranslate drops `laminas-mvc` and `laminas-mvc-plugin-flashmessenger`
  from `require`, deletes its plugin, rendering helpers, `LazyControllerFactory` and
  `wireEndOfRequestFlush()`, keeps the translator half of `onBootstrap()`. JUser drops
  `laminas-mvc` from `require-dev`. SionModel: `SionCacheTrait` attaches to the literal
  `'finish'`; `MailerFactory` takes the renderer interface; `SionFormRow` and the
  `neilime/zf2-twb-bundle` requirement go; its three controllers, `ErrorListener`,
  `RequestContext::attributesFor()`, `Mvc\CspListener`, `RouteNameFactory`,
  `Module::onBootstrap()` and the `Application` fallback in `SionTableWiring` are
  **deleted** — patres follows this line, so nothing is kept for a laminas host.

### 4.3 Batches

1. **Delete what nothing dispatches** (app modules only, no dependency change): done.
   The ACL diff showed exactly one change (the `api-route-not-found` guard);
   `docs/acl-baseline.json` carries it. The mail body rendered before and after was
   byte-identical. Deploy.
2. **Replace the runtime uses** in `src/` and tests (everything in §4.2 that is new code):
   done, together with batch 3, as one superproject PR over three submodule PRs. Our
   factories shadow laminas-mvc's under the same ids, so this deploys with the package
   still installed. The mail body rendered before and after was byte-identical
   (`--dry-run` returns before rendering, so the comparison drove
   `Mailer::renderTemplate()` directly). Deploy.
3. **Submodule PRs** (JTranslate, SionModel, JUser): done with batch 2.
4. **Composer**: done. The seven packages and the two `repositories` fork entries are out,
   and the five that stop being transitive are direct: `laminas-servicemanager`,
   `laminas-modulemanager`, `laminas-eventmanager`, `laminas-http` (`App\JUser\Host\RouteResolver`
   matches a `Laminas\Http\Request`) and `laminas-view`. Re-locked with a **partial**
   update naming exactly those twelve — `composer update --lock` only rewrites the hash and
   would not have re-resolved — which reported *7 removals, 0 installs, 0 updates*, so
   nothing else moved. The four `Laminas\Mvc*` module entries, `SlmLocale` and `TwbBundle`
   left `config/modules.config.php`; the `formElement` alias left Application's config.
   `--no-dev` rehearsal and `composer audit --locked` clean. Deploy.
5. **Docs**: this file, `docs/BACKLOG.md`'s fork item, and the two autoload probes.

### 4.4 Verification specific to step 0

- Grep `has('Application')` and `getMvcEvent` to zero after batch 2; the failure mode of
  everything here is silence, not an error.
- Flash continuity across the batch-2 deploy: write a flash on the old release, deploy,
  see it render.
- `bin/console` builds its container through `ContainerFactory` with caches **off**; no
  `data/config/*` file may appear owned by the deploy user after a console run.
- PHPStan level 0 gains no `class.notFound`; level 8 on the Symfony-side paths stays clean.
- `why-not php 8.5.0` lists five packages afterwards; `why-not laminas-servicemanager 4.0.0`
  lists ten. **Both confirmed.**
- **Two hardcoded probes name a class from a removed package and fail loudly only when it
  goes**: `public/index.php`'s install check and the autoload sanity check in
  `.github/workflows/ci.yml` *and* `tools/ci-local.sh`. All three named
  `Laminas\Mvc\Application` and now name `App\Kernel`. The index.php one is the dangerous
  shape: it throws before the kernel is built, which is a fatal under HTTP 200 on every
  page — a 441-byte 200 is what it looked like.

## 5. Rules for every step

- **Bootable at every commit; deployable at every batch.** Production has no test suite
  of its own; `./tools/ci-local.sh` is the stricter check (it runs smoke, fuzz and the
  smoke-prod script, which CI cannot), and the PR body says so.
- **Replace behind the same id first, remove the package last.** A new implementation
  shadows the laminas one for at least one deploy before the package leaves
  `composer.json`.
- **One PR per repository**, submodules first, base `modernization`, superproject last
  with the pointer bumps (workflow in `CLAUDE.md`).
- **Parity before deletion.** Where a replacement must produce the same bytes (rendered
  HTML, a mail body, a URL, an ACL answer), pin it with a parity test that drives both,
  delete the old side, and then delete the parity test's old half.
- **Authorization changes are diffed, not just tested**: `tools/acl-table.php --format=json`
  against `docs/acl-baseline.json`. A rule that quietly stops matching lets *more* people
  in and nothing fails.
- **Forms**: `php composer.phar fuzz` — contract "no new gaps"; regenerate the baseline
  only with the diff read line by line.
- **The failure mode is silence.** Every laminas-shaped fallback (`has('Application')`,
  an MvcEvent read, a helper's early return) fails by doing nothing. Grep for the shape,
  and measure with the general log or a rendered page in a non-English locale, signed in.
- **Docs**: a doc states what is true now and the rules. Dated narrative goes to git.

## 6. Decisions

Taken 2026-09-09:

- **patres follows schoenstatt.link.** The shared libraries (SionModel, JUser, JTranslate)
  drop laminas **in place** on `modernization`; no bridge namespace is kept for a laminas
  host, dead laminas-only code is deleted, and patres upgrades against tagged releases at
  its own pace. Do what is best for this application; it will be best for patres too.
- **No exceptions** for individual laminas packages.
- **Dependencies are minimised everywhere**, not only laminas: see §8.
- `slm/locale` and `laminas-twb-bundle` go in step 0; the `RestApi` module is deleted.

Still open:

1. **Database layer**: Doctrine DBAL (recommended) or a thin PDO wrapper of our own.
2. **Forms**: Symfony Form + Validator (recommended) or an own minimal layer.
3. **Mail templates in step 0**: Twig (recommended) or a PhpRenderer factory.

## 7. How the Symfony side is built (reference)

### Adding a route

1. Controller under `src/`, namespace `App\`, `declare(strict_types=1)`, PHPStan **level 8**,
   PSR-12 (`tools/phpcs-clean-paths.txt`). Factory in `App\Kernel::container()`. Inject
   `App\Laminas\ServiceBridge` only if it needs a laminas-side service or the merged
   config; `/_health` must keep paying nothing.
2. Declare it in `config/symfony/routes.php` through `$ported()` (or `$edit`/`$create`/
   `$delete`/`$libraryPage`). The **name is load-bearing**: `App\Http\SymfonyRoute::routeName()`
   strips the `.locale` suffix and the layout compares it with the `navigation` config to
   mark the active item. Pass an `App\Authorization\RouteAccess` — required:
   `RouteAccess::guardedBy('route/<name>')` names a guard resource in the ACL config;
   `openToEveryone('<why>')` only when there is nothing to consult. JSON routes pass
   `DenialStyle::Json`. Declare `_text_domain` if the page's phrases live in a module
   domain. `App\Http\LocalePrefix` decides whether the unprefixed twin 302s (default yes;
   the opt-out set is pinned by name).
3. A route that declares no `RouteAccess` raises `UndeclaredRouteAccess`;
   `test/Integration/SymfonyRouteAuthorizationTest` walks the whole collection and
   `tools/acl-table.php` reports `SILENT BYPASS RISK`.
4. Smoke test the three outcomes of a restricted route (anonymous, signed in without the
   role, with it) — `test/Smoke/MagicLinkSignIn` gives a real session; a status-code-only
   test passes against a guard that never ran.
5. Regenerate `docs/acl-baseline.json` and read the diff.

### Authorization at request time

`App\Http\AuthorizationListener` on `kernel.request` at priority **-16**: below
`RouterListener` (32), below `LocaleListener` (0, so the 403 renders in the negotiated
locale), and below `App\Http\LocalePrefixListener` (-8, so the unprefixed form redirects
to `/en/...` *before* the denial records the return path). The listener resolves its guard
through a closure because `App\Kernel::routeUrl()` reads the request's base URL. Denial
(`App\Authorization\Denial`): anonymous → `302 /en/user/login?redirect=<path incl. query>`;
signed in without the role → `403` from `templates/error/403.html.twig`. The **identity**
picks the branch, never the failed check. The redirect carries the query string
percent-encoded and the path literal; JUser's `RouteResolver` accepts only what the
laminas router matches (locale prefix stripped, matched on a **clone** with an empty base
URL — `RouteUrl` mutates the shared router's base). Default roles `lib_user`, `pub_user`,
`sch_user`, `bib_user` are `is_default = 1`: a guard naming one means "signed in", and the
per-row check (`show`/`checkout` on libraries) is the real protection; `administrate` is
granted on every library. The identity's roles are read from `linkUser()` (names), not
`rolesList` (ids).

### Twig

- `templates/layout.html.twig` is the chrome: extend it, define `page_title`,
  `breadcrumbs`, `block content`, `block inline_scripts`. **Every top-level `{% set %}` in
  the layout is prefixed `chrome_`** — an unprefixed one shadows the controller's variable
  in every page (it happened: `languages`).
- `strict_variables` is on: a reproduced helper must reproduce its **guards**, not only its
  output (deleted-entity placeholders carry only `{isDeleted, key, name}`). `autoescape` is
  on; markup-returning functions are `is_safe: html`. Compile cache only when
  `data/cache/twig` is writable, `auto_reload` on.
- `translate()` (`App\Twig\LaminasExtension`): asks the page's `_text_domain` with
  `translate()` — the call that **files** an unknown phrase — then reads `default` from the
  compiled catalog (`catalogValue()`, fires no event). Discovery is single-domain on
  purpose; a second `translate()` call is a second write. `<title>` is translated by the
  layout (`page_title_translate = false` opts out); navigation labels use `default`;
  breadcrumb labels are translated unless `'translate': false` (record data). A bridged
  laminas helper that translates needs its domain set in the same commit
  (`ViewHelpers::useTextDomain()`).
- Extensions: `LaminasExtension` (`laminas_path`, `translate`, `is_allowed`, formatting
  functions, `flash_messages`, `now_messages`), `ChromeExtension` (`current_route`,
  `navigation_items`, `language_options`, `canonical_links`, `search_box`, `display_name`,
  `csp_nonce`, `json_ld`). `App\View\SiteChrome` makes the chrome's decisions;
  `App\View\PreferredUrls` answers a record's canonical URL; `App\Laminas\RouteUrl`
  assembles laminas URLs with the locale prefix.
- Forms render through `SionModel\Form\BootstrapFormRenderer` (`form_row`, `form_open`…
  via `SionModel\Twig\FormExtension`), byte-compatible with the old TwbBundle markup.

### Per-request laminas bridging (until step 7)

`ServiceBridge` builds the laminas container lazily and never bootstraps it. Two things
that used to happen on `MvcEvent::EVENT_FINISH` are Symfony listeners on
`kernel.terminate`: `App\Http\PhraseFlushListener` (missing phrases → `trans_phrases`,
armed by `TranslatorConfigurator`) and `App\Http\SionCacheFlushListener` (draining
`SionModel\Cache\CacheFlushQueue`). `App\Laminas\TranslationsTableConfigurator` sets the
module → catalog-directory map on the table itself, because a successful write redirects
and never builds a translator. The session starts in `App\Http\SessionListener`.

## 8. Beyond laminas: every other dependency

Direct, non-laminas requirements across the application and the three submodules,
measured 2026-09-09 (files using the namespace, outside `vendor/` and `.phtml`):

| package | own deps | files | verdict |
|---|---|---|---|
| symfony/{http-kernel, http-foundation, routing, event-dispatcher, console, mailer, security-core}, twig/twig | — | the platform | keep |
| symfony/error-handler | 2 | **0** direct uses; http-kernel requires it | drop the direct line |
| monolog/monolog | 0 | 2 (SionModel logging) | keep; PSR-3 with no deps |
| firebase/php-jwt | 0 | 4 (API tokens, JUser) | keep, or replace with `hash_hmac` over a signed id (tokens are opaque to callers) |
| spatie/schema-org | 0 | 13 (JSON-LD on books, shrines, persons) | candidate: the JSON-LD emitted is a handful of fixed shapes; a typed array builder replaces a 900-class package |
| erusev/parsedown + parsedown-extra | 0 + 1 | 4 | keep one Markdown parser; parsedown is unmaintained since 2019 — replace with `league/commonmark` (maintained, 3 deps) **or** drop Markdown where it is only rendering plain notes. Decide per use |
| nesbot/carbon | 4 | 4 | replace with `DateTimeImmutable` + `IntlDateFormatter` (`DiffForHumans` is the one non-trivial call) |
| giggsey/libphonenumber-for-php | 2 | 1 (`Telephone` helper) | replace with a formatting-only fallback, or drop formatting: one helper, display only |
| voku/html2text, tijsverkoyen/css-to-inline-styles | 1 + 1 | 3 + 1 | mail only (text alternative, inlined styles); fold into the Twig mail port with a 40-line inliner or accept plain-text mails |
| neitanod/forceutf8 | 0 | 1 | replace with `mb_convert_encoding`/`iconv` |
| tedivm/jshrink | 0 | 2 | drop: minify at build time or serve the source; not a runtime concern |
| matriphe/iso-639 | 0 | 1 | replace with a 200-line array of the languages this site uses |
| cocur/slugify | 0 | 2 | replace with `Symfony\Component\String\Slugger` (already installed via symfony/string) |
| scottconnerly/timezone | 0 | 1 | replace with `DateTimeZone::listIdentifiers()` + Intl |
| spatie/opening-hours | 0 | 2 (shrine opening hours) | keep for now; revisit with the shrine dataset work |
| nicolaswurtz/chordpro-php (jroedel fork) | 0 | 1 (`CompositionController`) | the last personal fork after step 0; either upstream the fork or vendor the ~300 lines this site uses |
| neilime/zf2-twb-bundle (SionModel `composer.json`) | — | not installed here | remove the requirement in step 0 |

Rule: before adding a package, ask whether twenty lines of our own code would do; when
touching code that uses one of the candidates above, replace it in the same PR.
