# Removing laminas-mvc (Phase C)

Specification, written 2026-09-09 against `master` at `3dccab0` (the day after the
bjy-authorize / laminas-permissions-acl removal deployed). Every count below was
measured in this working tree; the composer facts were measured with
`php composer.phar why`, `why-not` and, in the capsule, `show -a`. Nothing here is
implemented yet. Read [strangler.md](strangler.md) first for Phases A and B.

## 1. What this phase is, and what it is not

**Is:** removing `laminas/laminas-mvc` and the six packages that exist only because of
it (`laminas-mvc-i18n`, the three `laminas-mvc-plugin-*` packages,
`diablomedia/laminas-twb-bundle`, `slm/locale`) from `composer.json`; deleting the
laminas controllers, view scripts, module bootstraps and controller plugins that nothing
dispatches any more; and replacing the handful of laminas-mvc *services* the Symfony
side still asks the `ServiceBridge` for.

**Is not:** removing laminas altogether. `laminas-servicemanager`, `laminas-modulemanager`
(module loading and config merging), `laminas-router` (URL generation and the ACL's
route resources), `laminas-form`/`inputfilter`/`validator`, `laminas-db`, `laminas-cache`,
`laminas-session`, `laminas-i18n`, `laminas-view` (the helper classes Twig bridges) and
`laminas-navigation` all stay. Each is a later, separate decision — see §9.

### 1.1 Correction to the record: what removing laminas-mvc buys

Three documents and one memory note (`strangler.md` "Phase C", `php-85.md` "The
ceiling", `BACKLOG.md`, and "laminas-mvc is the real dependency ceiling") say this
phase *unblocks the PHP 8.5 platform pin, servicemanager 4, laminas-cache 4 and
FrameworkBundle*. Measured on 2026-09-09, that is **necessary but not sufficient**, and
for three of the four it is not even the main obstacle.

`php composer.phar why-not laminas/laminas-servicemanager 4.0.0` names **fourteen**
packages that cap servicemanager at 3.x. Five leave with this phase (`laminas-mvc`,
`laminas-mvc-i18n`, `laminas-mvc-plugin-identity`, `laminas-twb-bundle`, `slm/locale`).
The other ten are current laminas components, **every one at its latest release**
(checked with `composer show -a` in the capsule), and every one requires
`laminas-servicemanager ^3.x` only:

| package | installed | latest | servicemanager constraint at latest |
|---|---|---|---|
| laminas-cache | 3.14.0 | 4.3.0 | 3.x line: `^3.21`; 4.x needs `^4.1` |
| laminas-filter | 2.42.0 | 3.4.0 | `^3.21.0` |
| laminas-form | 3.24.2 | 3.24.2 | `^3.22.1` |
| laminas-i18n | 2.33.0 | 2.33.0 | `^3.21.0` (and `laminas-cache ^3.13`) |
| laminas-inputfilter | 2.35.0 | 2.35.0 | `^3.21.0` |
| laminas-router | 3.19.0 | 3.19.0 | `^3.14.0` |
| laminas-session | 2.27.0 | 2.27.0 | `^3.23.1` (and `laminas-cache ^3.13`) |
| laminas-text | 2.13.0 | 2.13.0 | `^3.22.0` |
| laminas-validator | 2.65.0 | 3.18.0 | `^3.21.0` |
| laminas-view | 2.44.0 | 3.1.0 | `^3.21.0` |
| laminas-navigation | 2.23.0 | 2.23.0 | `^3.23.1` |
| laminas-modulemanager | 2.19.0 | 2.19.0 | `^3.23.0` |

So after this phase **servicemanager 4 is still uninstallable**, therefore
**laminas-cache 4 is still uninstallable**, therefore `psr/cache` stays at 1 and
**FrameworkBundle stays out**. Worse for the bundle: `laminas-i18n` and `laminas-session`
*require* `laminas-cache ^3.13` at their latest releases, so the bundle is gated on
replacing those two (translation → `symfony/translation`, which
[translation-migration.md](translation-migration.md) already argues; session → Symfony's),
not on anything in this phase.

What this phase **does** buy:

- **PHP 8.5 platform pin: six of eleven blockers.** `why-not php 8.5.0` names eleven
  packages. `laminas-mvc`, `laminas-mvc-i18n`, the three plugins and `slm/locale` go with
  this phase (six). Left: the three `laminas-cache-storage-adapter-*` (their latest, 3.2.0,
  is still `~8.4.0`), `laminas-math` 3.8.1 (latest, `~8.4.0`, required directly — find its
  one caller) and `laminas-serializer` 2.18 (3.3.0 exists; constraint to measure). Lifting
  the pin is therefore a small follow-up, not a consequence.
- **One front controller in fact, not only in routing.** Today every Symfony-served
  request still builds a laminas `ServiceManager` through laminas-mvc's
  `ServiceManagerConfig`, and the whole class of "an MvcEvent-dependent factory dies on a
  Symfony route as a fatal-200" defects (`LibraryScopedForms`, `CurrentLibrary`,
  `NavigationTree` each exist to dodge one) stops being possible.
- **Deleting dead code with a rollback story nobody can use.** 3,134 lines of laminas
  controllers in the application modules, 6,284 lines across 118 `.phtml`, four module
  `onBootstrap()` hooks and every controller plugin — all kept as the "canary rollback
  path" for a canary retired on 2026-09-08. Rollback is a redeploy now (strangler.md), so
  the path protects nothing.
- Two personal forks (`jroedel/laminas-twb-bundle`, `jroedel/SlmLocale`) and their
  `repositories` entries go away.

## 2. Inventory: what laminas-mvc still provides here

### 2.1 The container bootstrap itself (the load-bearing part)

`Laminas\Mvc\Service\ServiceManagerConfig`, `ModuleManagerFactory` and
`ServiceListenerFactory` are laminas-**mvc** classes, not laminas-modulemanager ones.
They are what `bin/console`, `App\Laminas\ServiceBridge`, `tools/acl-table.php`,
`test/Fuzz/FormRepository` and **18 integration tests** (22 call sites) copy-paste to build
a container: configure the SM, set `ApplicationConfig`, `get('ModuleManager')->loadModules()`.
`ServiceListenerFactory` also registers ~40 default services, of which the application
still requests these by string id from Symfony-side code, factories or tests:

| id | requests outside laminas controllers | who, and what for |
|---|---|---|
| `Application` / `application` | 13 | `SionTableWiring` fallback branch; `TranslationsTableFactory::wireEndOfRequestFlush()` (both guarded by `has()`); the four route-aware Books form factories, `LibraryInfoFactory`, SionModel `RouteNameFactory` (all read `getMvcEvent()->getRouteMatch()` and die on a Symfony route); `FormRepository::seedRouteMatch()` (reflection into `Application::$event`); `NavigationTree` docblock |
| `ViewHelperManager` | 13 | `ViewHelpers::helpers()` (the 20 helpers Twig bridges); `isAllowed` helper from `EntityCreateController`, `EntityShow`, `EntityDelete`, `EntityEdit`, `LiteratureController`; `translate`/`countryName` inside `SchoenstattTableFactory`, `AssociationKindsServiceFactory`, `AdvancedSearchFormFactory`; `IndexControllerFactory` |
| `ControllerPluginManager` | 15 | `nowMessenger` (JTranslate plugin) from `HostMessages::now()` and nine `src/` controllers; `flashMessenger` from `PersonController`; JTranslate's two view-helper factories |
| `MvcTranslator` | 11 | `LaminasExtension::translate()` (111 templates), `catalogValue()`, `AssociationController`, `PersonController`, `LiteratureController`, `PublicationController`, `CheckoutsController`, `LibraryController`, `PortedRouteTranslationTest`; delegated by `TranslatorConfigurator` (keyed on the canonical class) |
| `ViewRenderer` | 4 | `ViewHelpers::helpers()` (built only so the helper manager has a renderer); `SionModel\Service\MailerFactory` and `Books\Service\BooksMailerFactory` — **the last two `.phtml` still rendered**: `sion-model/mailing/action-email` and the `books/libraries/email-book-list` partial, via `Mailer::renderTemplate()` from `BooksMailer`, i.e. `bin/console books:send-notices` |
| `Router` | 2 | `RouteUrl::router()` (behind `laminas_path()`, 26 templates, `HostUrls`, the sitemap, `PreferredUrls`); `EntitySpecRoutesAreAssemblableTest`. **Not laminas-mvc's**: laminas-router's own `ConfigProvider` aliases `Router` → `RouteStackInterface`, so this survives untouched |
| `Request` / `request` | 2 | `RequestUriFactory`, `RequestUri` helper (Application; `.phtml` only) |
| `ControllerManager` | 1 | `AdminIndexParityTest::laminasViewModel()` runs the laminas `AdminController::indexAction()` to compare against `AdminIndex` |
| `SendResponseListener`, `MvcEvent` | tests | `SionCacheWiringTest` pins the flush priority relative to laminas' `-10000` |

Everything else laminas-mvc registers (`ViewManager`, `HttpViewManager`, the strategies,
`DispatchListener`, `RouteListener`, `InjectTemplateListener`, `Response`, …) is asked for
by nothing outside laminas controllers and `Module::onBootstrap()` hooks.

### 2.2 Module bootstraps — all four are dead

`onBootstrap()` fires from `Laminas\Mvc\Application::bootstrap()`, which nothing calls
since Phase B. Audited in strangler.md § "What every onBootstrap does"; every row already
has a Symfony-side replacement or a deliberate "nothing needed":

| module | hook does | status |
|---|---|---|
| `Application` | `GdprStrategy`, DB-derived navigation branches into `Laminas\Navigation`, `FixNavigationPages` | replaced by `GdprCookieListener`, `NavigationTree`/`SiteChrome` (same `PageBuilder`) |
| `Books` | shared `dispatch` listener setting `LibraryTable::setLibraryId()` from the route match | replaced by `LibraryPage` / `CurrentLibrary` |
| `Schoenstatt` | `ModuleRouteListener` | rewrites laminas route matches; nothing needed |
| `SionModel` | `CspListener`, `ModuleRouteListener`, `ErrorListener`, `FatalErrorHandler::upgrade()` | `App\Http\CspListener`, Kernel error handling, `Kernel` calls `upgrade()` itself |
| `JTranslate` | translator configuration, twelve helper text domains, phrase flush on FINISH | `TranslatorConfigurator`, `_text_domain` route default, `PhraseFlushListener` |
| `JUser` | (none since 2026-08-21) | — |

Plus `Application\Session\SessionBootstrap`, attached through `application.config.php`'s
`listeners` key, which only `Application::init()` reads. `App\Http\SessionListener` is the
live one.

### 2.3 Laminas controllers and view scripts

21 controller classes extend `AbstractActionController`/`AbstractRestfulController`
(4,456 lines): 18 in `Application`/`Books`/`Schoenstatt`/`RestApi` (3,134 lines) and 3
in `SionModel` (`SionController` 1,117 lines, `SionModelController`, `CommentController`).
Five `LazyControllerFactory` copies, `SionControllerFactory`, `SionModelControllerFactory`,
`IndexControllerFactory`, `RouteNotFoundControllerFactory`. 118 `.phtml` (Application 9,
Books 68, Schoenstatt 30, SionModel 11; 6,284 lines) of which **two are still rendered**
(§2.1, `ViewRenderer`). `docs/view-scripts.md` is the porting log; every HTML page is
Twig since 2026-09-08.

`src/Controller/Api/AssociationsV3Controller.php` and `PhrasesV3Controller.php` match the
`AbstractRestfulController` grep only in a docblock; they are Symfony controllers.

### 2.4 Plugins and the messaging layer

- `Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger` (662 lines, `laminas-session`
  `Container` under the key `FlashMessenger`) is **instantiated directly** at 10 sites in
  `src/Controller/*` and in `HostMessages::flash()`, and its `NAMESPACE_ERROR` /
  `NAMESPACE_SUCCESS` constants are referenced 41 + 24 times. `JUser\Host\Severity` and
  `JTranslate\Host\Severity` pin the same five strings, guarded by the two host-contract
  tests, *because a flash crosses a redirect in the session*.
- `JTranslate\Controller\Plugin\NowMessenger` extends `AbstractPlugin` (same-request
  messages, not session-backed); `JTranslate\View\Helper\NowMessenger` (317 lines) and
  `JTranslate\View\Helper\FlashMessenger` (extends the plugin's view helper) render both
  as Bootstrap alerts with translation. Twig reaches them only through
  `flash_messages()` / `now_messages()` in `templates/layout.html.twig`.
- `laminas-mvc-plugin-identity`: `$this->identity()` in two rollback-path Schoenstatt
  controllers. `App\Authorization\RouteGuard::identity()` is its own method.
- `laminas-mvc-plugin-prg`: **no call sites at all.**

### 2.5 The translator

`Laminas\Mvc\I18n\Translator` (`MvcTranslator`) is a decorator that makes laminas-i18n's
translator satisfy `Laminas\Validator\Translator\TranslatorInterface`. laminas-mvc-i18n's
`TranslatorFactory` wraps the container's `Laminas\I18n\Translator\TranslatorInterface`
when one exists — and one does: `Application` registers `translator` →
`TranslatorServiceFactory`. **`laminas-validator` already ships the same adapter**,
`Laminas\Validator\Translator\Translator` (implements both interfaces, 20 lines). The
other thing laminas-mvc-i18n does — a delegator making `HttpRouter` translator-aware —
is unused: no route in any `module.config.php` has a `{translatable}` segment.
`HelperPluginManager` injects a translator into helpers by looking up `MvcTranslator`
first, then `TranslatorInterface::class`, then `Translator`.

### 2.6 TwbBundle and SlmLocale

`TwbBundle` (Bootstrap 3 form/UI helpers) is reached from Symfony-side code in exactly
two places: `ViewHelpers::label()` (`TwbBundleLabel`, used by `label()` in
`schoenstatt/roles.html.twig` and by `EntityFormatter` for Books labels) and
`SionModel\Form\View\Helper\SionFormRow extends TwbBundleFormRow`, registered as the
`formRow` helper — which no ported page uses since `BootstrapFormRenderer` (which imports
nothing from TwbBundle) reproduced its markup. `Application` also aliases `formElement`
to a TwbBundle helper; `.phtml` only. SionModel's own `composer.json` still lists
`neilime/zf2-twb-bundle ^3.0`.

`SlmLocale` is loaded as a module and its `Module::onBootstrap()` is the only file in the
package that touches `Laminas\Mvc`. No Symfony-side code reads `slm_locale` config; the
`juser.global.php` block configures it, `App\Http\LocaleListener` reproduces its strategy
order and keeps the cookie **name** `slm_locale` (a constant — visitors' cookies must
keep working). It requires `laminas-servicemanager ^3.2` and `php ~8.4.0` at the fork's
tip.

### 2.7 Shared libraries: where the hard laminas-mvc references are

Only references that fail at *load* or *execution* time matter (a `use` statement or a
docblock does not). PHPStan level 0 does report an unknown class in a type hint,
`extends`, `new` or a constant access, so every one of these needs a decision (§4.7):

- **SionModel** (`composer.json` does **not** require laminas-mvc today):
  `Module::onBootstrap(MvcEvent $e)` + `new ModuleRouteListener()`;
  `Error\ErrorListener` (`MvcEvent::EVENT_DISPATCH_ERROR`, `onError(MvcEvent)`);
  `Error\RequestContext::attributesFor(MvcEvent)`; `Mvc\CspListener::injectHeader(MvcEvent)`;
  `Db\Model\SionCacheTrait::wireOnFinishTrigger()` (`MvcEvent::EVENT_FINISH`, i.e. the
  string `'finish'`); `Controller\{SionController,SionModelController,CommentController}`;
  `Controller\{LazyControllerFactory,SionControllerFactory}`, `Service\SionModelControllerFactory`,
  `Service\RouteNameFactory` (`application`), `Service\MailerFactory` (`ViewRenderer`),
  `Service\SionTableWiring` (`Application` fallback, guarded).
- **JTranslate** (`composer.json` requires `laminas-mvc ^3.3` **and**
  `laminas-mvc-plugin-flashmessenger ^1.2`): `Controller\Plugin\NowMessenger extends
  AbstractPlugin`; `View\Helper\FlashMessenger extends` the plugin's helper;
  `View\Helper\Service\{FlashMessenger,NowMessenger}Factory` (`ControllerPluginManager`);
  `Module::onBootstrap()` (`AbstractActionController::class` shared listener, `ViewRenderer`);
  `Service\TranslationsTableFactory::wireEndOfRequestFlush()` (guarded, closure typed
  `MvcEvent`); `Controller\LazyControllerFactory`; `config/module.config.php` (the
  `controller_plugins` key and the `Laminas\Mvc\I18n\Translator` import).
- **JUser**: nothing in code. `laminas-mvc ^3.8` sits in `require-dev` only, and
  `Bridge/Laminas/README.md` already records that the module needs neither laminas-mvc
  nor bjy-authorize since 2026-09-09.

## 3. Design

### 3.1 One container bootstrap: `App\Laminas\ContainerFactory`

Replace the 22 copies of the `ServiceManagerConfig` recipe with one class that owns what
laminas-mvc's three factories did, and nothing else:

```php
final class ContainerFactory
{
    /** @param array<string,mixed> $appConfig the merged config/application.config.php */
    public static function build(array $appConfig, ContainerOptions $options = new ContainerOptions()): ServiceManager;
}
```

It registers `EventManager` (unshared), `SharedEventManager`, `ModuleManager` (the
`DefaultListenerAggregate` + `ServiceListener` wiring `Laminas\Mvc\Service\ModuleManagerFactory`
does today, ~60 lines when copied without the MVC parts), the `service_listener_options`
from config, the `EventManagerAwareInitializer`, and **no default MVC services**. Then
`loadModules()`. `ContainerOptions` carries the two things callers vary today: whether the
config/module-map caches are on (on for `ServiceBridge`, off for console, tools and
tests — see `bin/console`'s comment on why a console run must never write `data/config/`),
and the pre-built instances a request registers (`PhraseFlush`, `CacheFlushQueue`, both
already on `ServiceBridge`).

`ServiceBridge` keeps its interface and its laziness; only `services()` changes.
`bin/console`, `tools/acl-table.php`, `FormRepository` and the 18 tests call the factory.
The tests get a `test/Integration/LaminasContainer.php` helper (required from
`test/bootstrap.php`, like `RequiresDatabase`) rather than a trait per file.

The two config caches keep working unchanged: `ConfigListener` lives in
laminas-modulemanager, not laminas-mvc.

### 3.2 View helpers without laminas-mvc: `App\Laminas\ViewHelperManagerFactory`

Twig reaches 20 laminas view helpers through `ViewHelpers`, and four Symfony-side classes
fetch `isAllowed` from the manager. Keep laminas-view and register our own factory under
the id `ViewHelperManager` (so `ViewHelpers`, the three Schoenstatt factories and tests
need no change), reproducing the three things laminas-mvc's `ViewHelperManagerFactory`
does that matter here: build `HelperPluginManager` from the merged `view_helpers`
config; give the `url` helper the router (`FormatEntity`, `EditPencil`, `EditPencilNew`
call `$this->view->url()`; the route match may stay unset — `url()` assembles by name);
set `doctype` from `view_manager.doctype` (harmless, keeps `HTML5`). Translator injection
needs nothing: `HelperPluginManager` finds `MvcTranslator` on its own (§3.4).

The helpers' `$this->view` needs a renderer. Today `ViewHelpers::helpers()` gets
`ViewRenderer` first for exactly that. Either the factory attaches a bare `PhpRenderer`
(no resolver — helpers never render templates), or §3.3 provides a renderer service
anyway. Prefer the bare renderer inside this factory: it makes the helper layer
independent of the mail decision.

**Explicitly not** ported to Twig in this phase: the 20 helper classes themselves. Nine
are SionModel's and two JTranslate's (shared), and `EntityFormatter` already wraps the
big one. Their port is its own phase.

### 3.3 The two mail templates (decision needed, §7 Q2)

`books:send-notices` renders `sion-model/mailing/action-email.phtml` with the
`books/libraries/email-book-list.phtml` partial through `SionModel\Mailing\Mailer::renderTemplate()`,
which takes a laminas `RendererInterface`. Two options:

- **A — port to Twig (recommended).** Two templates; SionModel already requires Twig and
  ships `SionModel\Twig\FormExtension`; JUser's mailer is already Twig. `Mailer` takes a
  two-method `TemplateRendererInterface` (`render(string $name, array $params): string`)
  with a Twig implementation, and `MailerFactory`/`BooksMailerFactory` stop asking for
  `ViewRenderer`. After this **no `.phtml` is rendered anywhere**, so `view_manager`
  config, `laminas-view`'s resolver and the `.phtml` files can all go. Verification is a
  byte-compare of the rendered notice for one library between the two renderers, in the
  capsule, with `--dry-run`.
- **B — an `App\Laminas\PhpRendererFactory`** (~40 lines: `PhpRenderer` + `AggregateResolver`
  from `template_map`/`template_path_stack`). Cheaper, keeps two `.phtml` alive and
  `view_manager` config with them. Acceptable as a fallback if A is deferred.

### 3.4 The translator

Register `Laminas\Validator\Translator\Translator` under the id `MvcTranslator` (and under
`Laminas\Mvc\I18n\Translator` for the SionModel factory map), wrapping the container's
`Laminas\I18n\Translator\TranslatorInterface`. Move `TranslatorConfigurator`'s delegator to
`Laminas\I18n\Translator\TranslatorInterface::class` — the inner translator is the one
`TranslatorEventListener` attaches to and the one that owns the file patterns, so this is
where it always belonged. Keep the "delegate the canonical class, never the alias" note
from `ServiceBridge`: `translator` and `MvcTranslator` are aliases. `AbstractValidator::setDefaultTranslator()`
receives the adapter, exactly as it received the decorator. `PortedRouteTranslationTest`
asserts the same behaviour against the new type. Renaming the `MvcTranslator` id itself
is a follow-up (§9), not part of this phase — eleven call sites and the helper manager's
lookup order both prefer it today.

### 3.5 Flash and now messages

Replace the plugin class with `SionModel\Messaging\FlashMessages` (the shared library,
because JUser's and JTranslate's host adapters both go through `App\Laminas\HostMessages`
and SionModel is where the two modules' common ground lives): a `laminas-session`
`Container` under the **same** session key `FlashMessenger`, the same five namespace
strings, `addMessage()`, `getMessages()`/`getCurrentMessages()` semantics for one hop,
~80 lines. Same key and strings so that a flash written by the release being replaced is
still rendered by the release that replaces it (the deploy's swap is a `rename(2)`;
sessions do not restart). One instance per request stays a requirement —
`HostMessages` already memoises.

`NowMessenger` becomes a plain per-request class registered in `service_manager`, not a
controller plugin; its nine `src/` callers and `HostMessages::now()` go through the
service id instead of `ControllerPluginManager`.

Rendering: `flash_messages()` / `now_messages()` move from the two laminas view helpers
into `JTranslate\Twig\JTranslateExtension` (JTranslate owns the translation of message
text, which is what those helpers add over Bootstrap alert markup), reading the two
stores. The 41 + 24 constant references become `Severity::ERROR` / `Severity::SUCCESS`
in `src/` (the JUser/JTranslate `Severity` classes already hold the strings); the two
host-contract tests pin the strings **as literals** instead of against the laminas
constants — a stronger assertion, since the literal is now the contract.

### 3.6 Deletions in the application modules

- `module/Application`: `Controller/*`, `Service/{IndexControllerFactory,RequestUriFactory,GdprStrategyServiceFactory}`,
  `View/GdprStrategy`, `View/Helper/RequestUri`, `Navigation/FixNavigationPages`,
  `Session/SessionBootstrap`, `Module::onBootstrap()`; the `listeners` key in
  `application.config.php`; `view/**` (9 `.phtml`, `layout.phtml` included).
- `module/Books`: `Controller/*`, `Service/{BookForm,CollectionForm,LibraryForm,CheckoutForm}Factory`
  (the `LibraryScopedForms`/`CheckoutForms` replacements have been the only live path
  since 2026-08-18), `Service/LibraryInfoFactory` + the `libraryInfo` helper,
  `Module::onBootstrap()`, 68 `.phtml` (66 under option A).
- `module/Schoenstatt`: `Controller/*`, `Module::onBootstrap()`, 30 `.phtml`.
- `module/RestApi`: **the whole module** — one route shadowed by
  `App\Controller\Api\ApiRouteNotFoundController` since 2026-09-08, one controller, one
  factory, one `ViewJsonStrategy` entry, one guard. Remove from `modules.config.php`,
  `composer.json` autoload and `config/autoload/restapi.global.php`. `Module::VERSION`
  has no readers.
- Config keys everywhere: `controllers`, `controller_plugins`, `view_manager` (under
  option A; under B keep `template_map`/`template_path_stack` for the two mail templates),
  `sionmodel.global.php`'s `inject_headers_event` (a laminas `CspListener` option).
  **Keep `router`**: `laminas_path()`, the ACL's `route/<name>` resources, the sitemap
  and `EntitySpecRoutesAreAssemblableTest` all assemble from it. The `controller`/`action`
  defaults inside those routes name deleted classes as strings; harmless, prune later.
- `config/modules.config.php`: drop `Laminas\Mvc\Plugin\Identity`,
  `Laminas\Mvc\Plugin\FlashMessenger`, `Laminas\Mvc\Plugin\Prg`, `Laminas\Mvc\I18n`,
  `SlmLocale`, `RestApi`, `TwbBundle`.
- `public/index.php`: the `class_exists(Application::class)` install check becomes
  `App\Kernel`; the docblock that still describes `LegacyBridge` is corrected.
- `App\Laminas\ViewHelpers::label()` and the Twig `label()` function: a 10-line
  `App\View\Label` (escape text and class, `<span class="label …">`), verified against
  `TwbBundleLabel` output for the two call sites before the helper goes.

### 3.7 Shared-library changes (one PR per repo, `modernization` base)

- **JTranslate**: delete `Controller/LazyControllerFactory`, `Controller/Plugin/NowMessenger`
  (replaced per §3.5), `View/Helper/{FlashMessenger,NowMessenger}` and their two
  factories, the `controller_plugins` key and the `Laminas\Mvc\I18n\Translator` import in
  `module.config.php`; `TranslationsTableFactory::wireEndOfRequestFlush()` (its `has('Application')`
  guard makes it dead here, and a Symfony host flushes explicitly); `Module::onBootstrap()`'s
  twelve-helper `dispatch` listener. **Keep** the rest of `onBootstrap()` — the translator
  configuration is the library's purpose and patres runs it — but type the parameter
  `EventInterface` (it already is) and drop the `AbstractActionController::class` constant.
  `composer.json`: remove `laminas/laminas-mvc` and `laminas/laminas-mvc-plugin-flashmessenger`.
- **SionModel**: the decision in §7 Q1 decides between (a) a `SionModel\Bridge\Laminas\`
  namespace holding `Module::onBootstrap()`'s body, `ErrorListener`, `RequestContext::attributesFor()`,
  `Mvc\CspListener`, the three controllers and their factories, `RouteNameFactory`, with
  `laminas/laminas-mvc` in `suggest` and the namespace excluded from this application's
  PHPStan paths (the JUser precedent), or (b) deleting them. Either way: `SionCacheTrait::wireOnFinishTrigger()`
  attaches to the literal `'finish'` with a comment (the constant is the only laminas-mvc
  reference in a class every host loads); `MailerFactory` takes the renderer interface of
  §3.3; `SionFormRow` is deleted and `neilime/zf2-twb-bundle` leaves `composer.json`;
  `SionTableWiring`'s `Application` fallback stays (it is guarded and patres needs it)
  **only if** (a) is chosen, otherwise it goes too.
- **JUser**: `composer.json` `require-dev` drops `laminas/laminas-mvc`; nothing else.

### 3.8 Tests

- `test/Integration/LaminasContainer.php` (§3.1); the 18 tests and `FormRepository` use it.
- `FormRepository::seedRouteMatch()` goes; the four route-aware forms are built through
  `LibraryScopedForms` / `CheckoutForms` with the seeded ids instead. The harness's
  contract (every form under `module/*/src/Form/` is constructed) is unchanged, and
  `known-form-gaps.php` should not move — if it does, a form stopped being constructed.
- `AdminIndexParityTest::laminasViewModel()`: compared the Twig admin index against the
  laminas action. Replace with a fixed expectation of `AdminIndex::PAGES` or delete the
  parity half; the laminas side no longer exists to be compared with.
- `SionCacheWiringTest`: `MvcEvent::EVENT_FINISH` → `'finish'`, the `-10000` becomes a
  named constant in the test with the same comment (the fact it pins — flush after send —
  is now enforced by `KernelEvents::TERMINATE`, and the laminas path it also pinned is
  patres's).
- `MessengerDataIsNotTranslatedTest`: target the new `FlashMessages` class.
- `JUserHostContractTest` / `JTranslateHostContractTest`: literals (§3.5).
- `test/known-ci-skips.txt`: regenerate with `tools/check-ci-skips.php --bare` against a
  CI log; classes that only skipped because they built laminas-mvc may change state.
- Smoke: no page changes, so the suite is regression evidence, not new coverage. Add one
  smoke test that a flash set by a POST is rendered after the redirect (today covered only
  indirectly).
- `docs/acl-baseline.json`: expect exactly one diff — the `api-route-not-found` guard
  (RestApi module deleted). Anything else is a finding.

### 3.9 Documentation touched

`strangler.md` (Phase C section, the Phase-A/B narrative that still says "until laminas-mvc
is gone"), `php-85.md` (rewrite "The ceiling" with §1.1's table), `BACKLOG.md` (three
entries), `view-scripts.md` (close the log), `caching.md` / `caching-performance.md`
(`MvcEvent::FINISH` becomes history), `translation.md` (twelve helpers → Twig),
`DEPLOY.md` (nothing about the swap changes; one sentence that no laminas kernel exists to
roll back to), `docs/README.md` (this file), `CLAUDE.md` (Architecture bullet, the
"laminas-mvc is the remaining Phase C" sentence, the Verifying section's PHPStan paths),
`acl-rules.md` regenerated. Plus the memory note.

## 4. Sequencing — five PR batches, each bootable and deployable

Every batch leaves `laminas-mvc` installed until the last, so any one of them can ship
alone. Order chosen so the risky replacements (batch 2) are exercised in production for
a while before the package is removed (batch 5).

1. **C1 — delete what nothing dispatches** (application modules only, no dependency or
   behaviour change): the 18 controllers, factories, `onBootstrap()` hooks, `SessionBootstrap`,
   `GdprStrategy`, `FixNavigationPages`, 116 `.phtml`, the `RestApi` module, dead config
   keys. `ci-local` green, ACL diff = one guard, deploy. This is ~9,000 lines of deletion
   with zero new code, and it is the batch most worth doing on its own.
2. **C2 — replace the runtime uses** (`src/` and tests): `ContainerFactory`,
   `ViewHelperManagerFactory`, the translator adapter, `FlashMessages` + `NowMessenger`
   service + Twig rendering, mail rendering (A or B), `LaminasContainer` test helper,
   `FormRepository` change, `Label`. laminas-mvc's own factories are now shadowed by ours
   under the same ids — that is deliberate, and it is what makes this batch deployable
   with the package still installed. Deploy; watch the smoke script, `/en/sm/cache-status`,
   and send one real overdue notice with `--dry-run` first.
3. **C3 — shared-library PRs**: JTranslate, SionModel, JUser per §3.7, then the pointer
   bumps (submodule workflow in `CLAUDE.md`, base `modernization`).
4. **C4 — composer**: remove the seven packages and the two `repositories` entries, add the
   now-direct requirements (`laminas/laminas-servicemanager`, `laminas-modulemanager`,
   `laminas-eventmanager`, `laminas-http` — `App\JUser\Host\RouteResolver` matches a
   `Laminas\Http\Request` — and `laminas-view` if §3.2 keeps it), `composer update --lock`
   in the capsule, `--no-dev` rehearsal, `composer audit --locked`. `modules.config.php`
   loses its seven entries. Deploy.
5. **C5 — docs and the record** (§3.9), including the corrections in §1.1. Can ride with
   C4.

## 5. Verification beyond the suites

- **The failure mode to hunt is silence, not errors.** Every `has('Application')` fallback
  and every "MvcEvent absent" workaround fails by doing nothing. After C2, grep
  `has('Application')` and `getMvcEvent` to zero, and confirm with the general log
  (docs/caching.md "find an uncached read") that the persistent cache still writes.
- **Flash continuity across the deploy of C2**: sign in on the old release, trigger a
  redirecting write, deploy, confirm the message renders. Session key and namespace strings
  are the contract (§3.5).
- **Mail**: `books:send-notices --library=N --dry-run` before and after, diff the HTML.
- **CLI vs web**: `bin/console` builds the same container through `ContainerFactory` with
  caches off — verify `cache:clear-config` and `sitemap:build` still run, and that no
  `data/config/*` file appears owned by the deploy user after a console run.
- **PHPStan level 0** on the seven paths must not gain a `class.notFound`; level 8 on the
  Symfony-side paths named in `CLAUDE.md` § Architecture stays clean.
- **`tools/acl-table.php --format=json`** diff (§3.8).
- **`why-not` afterwards**: `php composer.phar why-not php 8.5.0` should list five
  packages; `why-not laminas/laminas-servicemanager 4.0.0` ten. Record both in `php-85.md`.

## 6. Risks

- **A guard that only ever passed because laminas-mvc registered a service.**
  `SionTableWiring::wireFlushPoint()` and `TranslationsTableFactory` already prefer the
  Symfony-side object and fall back on `Application`; after C4 the fallback is unreachable
  here but is patres's live path. Do not delete it from SionModel unless §7 Q1 chooses (b).
- **PHPStan sweeping shared-library code that references laminas-mvc after C4.** Level 0
  reports unknown classes. Either the Bridge namespace is excluded (`excludePaths`) or the
  code is gone; a baseline entry is the wrong tool (`reportUnmatchedIgnoredErrors` is off).
- **`HelperPluginManager` translator lookup order** prefers `MvcTranslator`; if the id is
  dropped before the helpers are re-pointed, every helper silently renders English. Keep
  the id through this phase.
- **Twig mail port (A)**: `inlineEmailStyles()` post-processes the rendered HTML; the
  paragraph "partial" mechanism in `action-email.phtml` is generic and SionModel-owned.
  Byte-compare before trusting it.
- **Deleting `layout.phtml`** removes the second half of the five-language hreflang
  contract (sitemap.md) — which is now the correct state, since nothing renders it. Update
  `CLAUDE.md`'s "both layouts must agree" bullet in C5.

## 7. Decisions for the user before C1 starts

1. **SionModel's laminas-mvc code — Bridge namespace or delete?** Depends on whether
   patres consumes this line of SionModel (`jroedel/zf3-sion-model`, `modernization`) and
   still runs laminas-mvc. Recommendation if it does: Bridge namespace, JUser precedent.
   If it does not: delete, and `SionController`'s 1,117 lines go with it.
2. **Mail templates: Twig port (A) or PhpRenderer factory (B)?** Recommendation: A.
3. **Drop `slm/locale` and `laminas-twb-bundle` in this phase** (recommended — both are
   personal forks, both cap servicemanager and PHP, both are reached from live code in
   one or two trivially replaceable places), or leave them for a follow-up?
4. **Delete the `RestApi` module** (recommended) or keep the empty shell?
5. **Deploy after each batch** (recommended: C1, C2 and C4 are the ones that change
   production behaviour) or accumulate?
6. **Is finding `laminas-math`'s single caller and lifting the 8.5 pin (§1.1) part of
   this phase's follow-up list, or a separate ticket?** Recommendation: separate, small.

## 8. Out of scope, deliberately

- Replacing `laminas-modulemanager` (module loading, config merge and its two caches)
  with a config aggregator. It would remove the `Module` classes and `module.config.php`
  shape entirely. Real, but it is the next phase, and it needs this one first.
- Porting the 20 laminas view helpers to Twig-native PHP (§3.2).
- `laminas-navigation`: `PageBuilder`, `SiteChrome`, `ChromeExtension` and
  `WaysideShrinesController` use its page classes as data structures. Nothing here
  needs it gone.
- Renaming the `MvcTranslator` service id (§3.4).
- Anything about servicemanager 4, laminas-cache 4 or FrameworkBundle — see §1.1 for why
  those are not consequences of this work.

## 9. Follow-ups this phase creates

- Lift the PHP platform pin: three cache adapters (wait for releases or replace
  laminas-cache — see §1.1), `laminas-math` (find the caller), `laminas-serializer` 3.x.
- Prune `controller`/`action` defaults from `router` config; rename `laminas_path()` to
  something that no longer says laminas.
- Retire the `MvcTranslator` id; then the `App\Laminas\` namespace name stops being
  descriptive and can become `App\Container\` or similar.
- The FrameworkBundle path, honestly stated: `laminas-i18n` and `laminas-session` out
  first.
