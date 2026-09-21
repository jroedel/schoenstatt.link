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
- **Routes** are declared in `config/symfony/routes.php` (through the
  `$ported`/`$edit`/`$create`/`$delete`/`$libraryPage` helpers, plus JUser's and
  JTranslate's route closures), and **only** there: laminas-router went at step 6
  (2026-09-09) with the `router` config key and laminas-http, and every URL the application
  generates assembles from Symfony's `UrlGenerator` through `App\Laminas\RouteUrl`. HTML
  renders with **Twig** from `templates/` and `module/{JUser,JTranslate}/templates/`.
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
  `App\Laminas\ContainerFactory`, which also constructs the view helpers
  (`App\Laminas\ViewHelpers`) and the translator; messages live in
  `SionModel\Messaging` behind `App\Laminas\HostMessages`; mail renders through Twig.
  `composer.json` has no `repositories` fork entry left but the chordpro one.
- **Form validation runs on our own engine** (step 5, done 2026-09-11). Every form extends
  `SionModel\Form\Form`, whose `isValid()`/`getData()` run
  `SionModel\Form\Validation\InputFilter` over the specification
  `SionModel\Form\Validation\FormSpecification` assembles — the form's
  `getInputFilterSpecification()`, an entry per element, and a nested entry per fieldset or
  collection, as plain data. **The specification is the whole of it**: a field it does not
  name is filtered by nothing and validated by nothing. The four non-form callers
  (`App\Schoenstatt\Association\AssociationValidator`, `JTranslate\Form\PhraseValidator`,
  `Schoenstatt\Service\PatresGateway`, `Books\InputFilter\DriveFileFilter`) build the same
  engine through `InputFilter::withRules()`, dropping the CSRF rule by unsetting its key
  rather than by removing an input. The parity tests that ran laminas' assembled filter
  beside it went with the packages; `test/Form/engine-surface.php` records the engine's own
  verdict, values and messages for every form and replaces all three.
- **Every element is ours** (step 5, element model, 2026-09-11). `SionModel\Form\Element`
  holds `Element` plus fifteen classes — `Text`, `Textarea`, `Hidden`, `Submit`, `Button`,
  `File`, `Url`, `Email`, `Number`, `AbstractDateTime`, `Date`, `Checkbox`, `Select`,
  `Csrf`, `DateSelect`, and `Phone` beside them — none supplying an input specification,
  all implementing `SionModel\Form\ElementInterface` since the form model landed.
  `SionModel\Form\Element\Registry` is the single list that swaps them in, read by
  `SionModel\Form\Factory` — which every fieldset reaches through `getFormFactory()`, so a
  form built with `new`, one built by a service factory and the associations API all build
  the same classes with nothing to configure.
  Measured across all 41 forms: **441 elements**, of which **432 changed class and nothing
  else**. `test/Element/element-surface.php` records what each one answers to every question
  the application asks of it; regenerating it after the swap changed 432 lines, every one of
  them the class name, and **0 other lines**. Of the nine that did not change, seven are
  `Phone`, which was already ours and answers the same after being rewritten to extend
  `Element` rather than laminas' `@final` `Tel`; one is a fieldset; and one is the single
  collection in the application, which changed class with the form model instead.
  `MultiCheckbox`, `Radio` and `MonthSelect` are deliberately not reproduced — the census
  found none in any form — and neither is laminas' `hasValue` flag, which nothing reads.
  `test/Unit/ElementModelBehaviourTest` pins the edges no definition exercises;
  `ElementModelParityTest`, which built the laminas twin of every element and compared, went
  with laminas' elements.
  A rule that lives on an element and not in a specification is a rule the engine cannot
  see. `test/Fuzz/known-form-gaps.php` tracked two categories for it —
  `validationSuppliedOnlyByElement` and `filteringSuppliedOnlyByElement` — which reached 0
  at the element swap and were retired with the form model: both were read off laminas'
  assembled filter, and an element that supplies no input specification cannot hide a rule.
  `SionModel\Form\{CsrfSpec,ChoiceDomain,CheckboxDomain,InputTypeRules}` are how a
  specification restates one; three option keys are documentation only now —
  `disable_inarray_validator` (63 uses), `required` (124) and `allow_empty`, read by the
  input-filter half, which no longer looks at elements.
- **The form model is ours** (2026-09-11). `SionModel\Form\{Form, Fieldset, Collection,
  Factory}` over `ElementInterface`, `FieldsetInterface`, `FormInterface` and
  `PrepareAwareInterface`, with no object binding, no hydrator, no priorities, no
  `wrapElements()` and no input-filter assembly — each measured absent across every call
  site first. `Laminas\Form` left `config/modules.config.php` with the `form_elements` key
  and the `FormElementManager` it configured. Rendered-HTML parity was the contract and it
  held: `test/Form/form-markup.php` records 491 surfaces in four states and **not one byte
  moved**; `test/Form/engine-surface.php` records every form's verdict, values and messages
  and none moved either; `test/Element/element-surface.php` changed one line, the
  collection's class name.
- **The rule library is ours** (2026-09-11). `SionModel\Validator\*` is 23 classes and
  `SionModel\Filter\*` is 21, each named by a specification and built by a flat `Registry`
  — a map, not a plugin manager, so resolving a rule needs no container, no module loading
  and no merged config. `SionModel\Uri\Http` is what `SionTable::filterUrl()` rewrites
  stored URLs with. Two classes trade laminas' tables for shape checks and are therefore
  strictly more permissive: `EmailAddress` and `Uri` accept any TLD. `config/modules.config.php`
  lost its last laminas module entries with them.
- **The module system is ours** (2026-09-21). `App\Modules\ModuleConfig` collects each
  enabled module's `getConfig()`, layers `config/autoload/` over it and caches the result —
  thirty lines, which is all `laminas-modulemanager` was doing here. Of everything
  `DefaultListenerAggregate` attached, only `ConfigListener` had an effect: no module class
  in this application declares `getAutoloaderConfig()`, `init()`, `onBootstrap()` or
  `getModuleDependencies()`, and composer's PSR-4 map resolves every one of them, so the
  class-map cache the loader maintained held an empty array.
- **What laminas still does**, and therefore what this plan removes: the service
  container (`laminas-servicemanager`, `laminas-eventmanager` — direct requirements since
  step 0), the database layer
  (`laminas-db`, under `SionModel\Db\Model\SionTable`), the session, and the
  `Laminas\Translator\TranslatorInterface` our translator and our validators share.
  laminas-i18n went at step 3, laminas-view at step 4, and the whole form stack at
  iteration A.
- `App\Laminas\ServiceBridge` is the seam: a lazily built laminas `ServiceManager` a
  Symfony controller asks for laminas-side services. It disappears at step 7.

## 2. The dependency picture (first measured 2026-09-09 after step 0; counts re-measured 2026-09-11)

**6** `laminas/*` packages are installed, down from 37, and every one is a direct line of
ours: `db`, `eventmanager`, `servicemanager`, `session`, `stdlib`, `translator`. Nothing
here is transitive any more — the last two that were, `config` and `loader`, left on
2026-09-21 with `laminas-modulemanager`.

The two facts that shape the order, and step 0 confirmed both:

**Removing laminas-mvc was necessary, not sufficient, for anything but the PHP pin.**
`php composer.phar why-not laminas/laminas-servicemanager 4.0.0` named fourteen cappers
before step 0 and ten after it, every one a current laminas component **at its latest
release** requiring `laminas-servicemanager ^3.x` only — laminas-cache, filter, form, i18n,
inputfilter, router, session, text, validator, view. Nine of those ten have since been
removed. Measured 2026-09-11, one is left:

| package | latest | servicemanager constraint |
|---|---|---|
| laminas-session | 2.27.0 | `^3.23.1` |

plus our own direct `^3.24` line, which iteration B removes with the container.

So servicemanager 4, laminas-cache 4 (and with it `psr/cache` 2/3 and FrameworkBundle)
are unreachable by upgrading. They become reachable only by **removing** the packages,
which is this plan — and by the time they are gone there is no servicemanager left to
upgrade, which is the point. The PHP pin moved for the same reason: eleven packages capped
PHP below 8.5, step 0 removed six and steps 1 and 2 the other five, so
`config.platform.php` is **8.5.9** since 2026-09-11 and states the runtime rather than a
ceiling.

**Measure, never assume.** Before and after every step: `why-not php 8.5.0`,
`why-not laminas/laminas-servicemanager 4.0.0`, and `composer show --locked | grep laminas`
— the count of the last is the progress metric. Step 0 took it 37 → 32, step 1a 32 → 28,
step 1b 28 → 27; steps 1c, 2, 3 and 4 took it to 17, step 6 to 16, and iteration A — the
seven packages of the form stack at once — to **9**.

**Most of what step 1 listed was blocked behind later steps, and the measurement said so.**
`laminas-uri` was required by `laminas-http` *and* `laminas-router`; `laminas-http` was what
`App\JUser\Host\RouteResolver` and `tools/acl-table.php` handed to the laminas router to
match against, so it left with the router at step 6. `laminas-json` was required by
`laminas-view` and `laminas-serializer`, and `laminas-serializer` *is* the laminas-cache
serializer — step 4 and step 2. What was actually removable in step 1 was the four
packages of 1a and `laminas-navigation`.

**`laminas-uri` was the one left of that list, and it was the most leveraged package in
the tree**: nothing required it, we did, and it was the only remaining requirer of
`laminas-validator` once laminas-form went — and, with laminas-form, of `laminas-escaper`.
So those three had to leave together, which is why iteration A removed seven packages in
one release. `SionModel\Uri\Http` replaces it.

**A `laminas/*` a submodule uses is invisible to `tools/dependency-audit.php` run without `--root`.** The audit
scans this application's roots only — SionModel, JUser and JTranslate are separate composer
packages with their own requirements — so a package can read as unused here and still be
load-bearing there. Removing `laminas-json` in step 4 broke `JTranslate\Service\CountriesFactory`
exactly that way, caught by grepping all four repositories rather than by the audit. Grep
every repository before dropping a package, and check the submodules' `composer.json` too.

**Declare what you use, or removal takes something with it.** Dropping `laminas-captcha`
in step 1a also dropped `laminas-session`, because captcha was the only package requiring
it and this application never declared it — `App\Http\SessionListener` and
`SionModel\Messaging\FlashMessages` use it on every request. Eight packages were in that
state and are now direct requirements (cache, escaper, filter, inputfilter, session,
stdlib, uri, validator). `tools/dependency-audit.php` is the check: for every installed
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
| 1c ✅ | json ✅ (with laminas-view at step 4), serializer ✅ (with laminas-cache at step 2), http ✅ (with laminas-router at step 6), uri ✅ (with the form stack at iteration A) | 8 files, 18 references | **done 2026-09-11.** `SionModel\Uri\Http`, which `SionTable::filterUrl()` rewrites stored URLs with. Fourteen of the 18 references were a `uriHandler` element option nothing read, and they left with the package |
| 2 ◐ | laminas-cache + 3 adapters + serializer ✅, laminas-authentication ✅, laminas-session (**blocked**, see below) | ~48 files | **cache and authentication done 2026-09-09.** `SionModel\Cache\Storage` on APCu and the filesystem, ours; `JUser\Authentication\SessionIdentity` behind the `Host\IdentityInterface` the module already declared. The `psr/cache` 1 pin is lifted |
| 3 ✅ | laminas-i18n | 25 files | **done 2026-09-09.** `JTranslate\I18n\Translator\Translator`, ours, implementing `Laminas\Translator\TranslatorInterface`. symfony/translation was the plan and was rejected on measurement — see docs/translation.md. The `.lang.php` catalog format is unchanged |
| 4 ✅ | laminas-view and laminas-json (which only laminas-view required) | 28 helpers + the `HelperPluginManager`/`PhpRenderer` machinery | **done 2026-09-09.** Every helper is a plain class constructed by `App\Laminas\ViewHelpers`; `url` is `$router->assemble()`, escaping is `SionModel\View\Escape`. laminas-escaper could not leave here — laminas-form (`^2`) and laminas-uri (`^2.9`) required it — and left with both at iteration A |
| 5 ✅ | laminas-form, inputfilter, filter, validator, hydrator, escaper — and laminas-uri with them | form 77 files, inputfilter 60, validator 65, filter 49; 42 forms, 441 elements | **done 2026-09-11** as iteration A: engine, element model, form model and rule library, all ours. Not Symfony Form: `SionModel\Form\Validation\InputFilter` over `FormSpecification`, `SionModel\Form\Element\*`, `SionModel\{Validator,Filter}\*`. The fuzz harness (`test/Fuzz`) and `ConstrainedChoiceFieldsFitTheirDataTest` are the safety net; `AssociationValidationParityTest` keeps web and API validation identical |
| 6 ✅ | laminas-router, and laminas-http with it | 1,640 lines of `router` config across six files | **done 2026-09-09.** Symfony router only; `laminas_path()` → `path()`; ACL resources keep the route names. The ACL baseline was byte-identical after the deletion and that proved nothing — five tests read `$config['router']['routes']` directly and every one broke |
| 7 | laminas-servicemanager, modulemanager, eventmanager, config, loader — and laminas-session with them | servicemanager 83 files and 58 factory classes (56 `FactoryInterface`, 2 `DelegatorFactoryInterface`); session 15; eventmanager 4; modulemanager 3 | **Our own PSR-11 container** (§6), not Symfony DI. `stdlib` does *not* leave here: laminas-db holds it |
| 8 | laminas-db, and `stdlib` and `translator` with it | db 99 files, `SionTable` 2,412 lines over `TableGateway`/`Sql`; translator 32 files; stdlib 9 references | **A thin PDO wrapper of ours** (§6). Verify by diffing MariaDB's general log across a full smoke run, before and after |

**Step 4 was not gated on step 5, though it looked it.** Every `Laminas\Form\View\Helper\*`
class extended `Laminas\I18n\View\Helper\AbstractTranslatorHelper`, so it was tempting to
conclude the forms held laminas-view down. They did not: laminas-form did not *require*
laminas-view in composer, and since step 3 nothing here resolved a laminas-form view helper
— `SionModel\Form\BootstrapFormRenderer` renders every form by hand. What held laminas-view
was our own 28 helpers, and that was step 4's actual content. Measured 2026-09-09.

**Order by packages removed, not by the numbering.** Step 4 took two (view, json) — not
the three first planned: `laminas-escaper` was required by `laminas-form` (`^2`) and
`laminas-uri` (`^2.9`), so it could not leave before both of those did. Step 6 was planned
as four (router, uri, http, loader) and took **two**: router and http. Iteration A then
took seven at once, because the graph left no smaller cut. `laminas-loader` outlived that
step for a reason no plan predicted — laminas-modulemanager used `Laminas\Loader\*` without
requiring it, so our own direct line was the only thing installing it — and left on
2026-09-21 when its consumer did. All three were measured from the `require` blocks in
`vendor/laminas/*/composer.json` rather than from the plan.

**laminas-session does not leave at step 2.** No package requires it — the direct line is
ours — and what holds it is the session itself: `App\Http\SessionListener` starts a `SessionManager` per request and
`SionModel\Messaging\FlashMessages` stores flashes in a `Container`, which is what makes
`Laminas_Auth` the identity key and a flash survive a redirect. **14 files** name the
namespace. What step 2 removed is the *authentication* use of it: the identity no longer
goes through a laminas storage adapter, only through `JUser\Host\SessionInterface`, whose
one implementation is the host's. Measured 2026-09-09, re-measured 2026-09-11.

`laminas-translator` is not glue: it is a zero-dependency interface
package we chose, named in **32 files**, and it
leaves only when we declare the interface ourselves — the cheapest package in the tree and
the last one nothing forces. Steps 2 to 8 rewrite code in the **shared submodules**
(SionModel, JUser, JTranslate); see §6 before starting any of them.

### What is left, and in what order it ships

Steps 0, 1a, 1b, 1c, 3, 4, 5 and 6 are done; 2 has only `session` left, the module system
went on 2026-09-21, and **6 packages remain**. The numbering above is the order the work was *planned* in, which is not the order
it can be released in, so the remainder ships as two iterations — the session and the
container, then the database.

**[laminas-exit-iterations.md](laminas-exit-iterations.md) is that plan**: what each
iteration removes, which orderings the dependency graph forces and which one is chosen, and
the rule they share — every iteration deletes the parity tests that prove it, so every
iteration opens by recording laminas' answers as data first. Iteration A (the form stack,
seven packages, 2026-09-11) is recorded there as done, with what its five recordings said.

## 4. Step 0 in detail: removing laminas-mvc

### 4.1 What laminas-mvc still provides

- **Nothing, at runtime.** Every container is built by `App\Laminas\ContainerFactory`
  (event managers, the merged module configuration from `App\Modules\ModuleConfig`, and the
  `service_manager` key applied from it); it defines `MvcTranslator` (since 2026-09 an alias of
  `JTranslate\I18n\Translator\Translator`, the one translator) under the ids ported code
  asks for, and registers the two delegators. Nothing asks for `Application`, `ControllerPluginManager` or
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
- `App\Laminas\ViewHelperManagerFactory` under the id `ViewHelperManager`: built
  `HelperPluginManager` from `view_helpers` config, wired the router into the `url` helper,
  and attached a bare `PhpRenderer` so `$this->view` existed. **Removed at step 4** — every
  helper is a plain class `App\Laminas\ViewHelpers` constructs, and there is no
  `view_helpers` config left.
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
- What stays until later steps: the **`router` config** (step 6). Application's
  `view_manager.doctype` went at step 4 with the helper manager that read it.
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

Settled since:

- **Forms: an own minimal layer, not Symfony Form + Validator.** Decided by measurement
  during the step 5 cutover — `SionModel\Form\Validation\InputFilter` reads a
  specification that is plain data, `FormSpecification::of($form)` assembles it, and
  `SionModel\Form\Element` replaced the elements. Symfony Form would have meant rewriting
  41 forms and their rendering against a different model to reach the same place.
- **Mail templates: Twig**, at step 0.

Taken 2026-09-11, and together they mean the exit adds **no dependency at all** — 16
packages out, none in:

- **The container is ours**, not Symfony DI and not FrameworkBundle. A PSR-11 container
  reading factories, aliases and invokables from the merged config; the 58
  `FactoryInterface` classes become `__invoke($container)`; the `data/config/` merge cache
  is unchanged. FrameworkBundle would also have wanted to own the kernel
  `App\Kernel` hand-wires, which is a second cost on top of the dependency.
- **The database layer is a thin PDO wrapper of ours**, not Doctrine DBAL.
  `SionModel\Db\Model\SionTable` is 2,412 lines that already hold the query logic; what
  laminas-db supplies underneath it is largely a parameter binder and a result iterator.
  DBAL is heavier than that seam needs, and 94 files would have to learn its abstractions.

Nothing is open.

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
Symfony router matches, with the locale prefix stripped first. Default roles `lib_user`, `pub_user`,
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
  `App\View\PreferredUrls` answers a record's canonical URL; `App\Laminas\RouteUrl` is
  every URL the application generates — since step 6 it assembles from Symfony's
  `UrlGenerator`, so its name and `laminas_path()`'s are both historical.
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

**56 runtime packages**, down from 74 on 2026-09-21, when the nine candidates below left
together. Direct, non-laminas requirements across the application and the three submodules;
file counts are namespace uses outside `vendor/`.

`tools/dependency-audit.php` is the standing check — for every installed package, does our
own code use its namespace and does `composer.json` say so. Run it per repository
(`--root=module/SionModel`): a package can read as unused in the superproject and still be
load-bearing in a submodule.

**The superproject's `composer.json` is the only manifest composer reads here.** `module/`
is autoloaded through this package's own PSR-4, not installed as path repositories, so a
package used only by SionModel, JUser or JTranslate must be declared *here* — and dropping
that line uninstalls it for them. `symfony/mailer` is named nowhere in `src/` and carries
every email the site sends.

Four verdicts:

- `USED / NOT DECLARED` — add the line; it resolves today only because something else
  requires it. The ten open ones were closed on 2026-09-21, eight of them in SionModel.
- `USED*` — this root's code does not name it, but something its installed set serves does,
  and that consumer does not require it. **The line is what installs the package.**
- `PIN?` — a consumer requires it too, so composer installs it either way and the line only
  bounds the version. Not free either: dropping `symfony/error-handler`'s let it resolve
  from `^7.4` to 8.1.
- `?` — a `files` or classmap package with no prefix to search for. Check it by hand.

**Five lines here read as unused and none of them is droppable**, which is why the bare
"unused" verdict was replaced: `giggsey/libphonenumber-for-php`, `monolog/monolog` and
`symfony/mailer` are `USED*` through the submodules; `psr/simple-cache` and
`symfony/error-handler` are `PIN?`. Verified against composer itself — each line removed
from a scratch copy and re-resolved; the two `PIN?` survived, the `USED*` ones vanished.
A sixth, `laminas/laminas-loader`, was `USED*` through `laminas-modulemanager`, which
imported `Laminas\Loader\ModuleAutoloader` in a listener `DefaultListenerAggregate` always
constructs and did not require the package. Both left on 2026-09-21, and with them
`laminas-config`, `brick/varexporter` and `webimpress/safe-writer`; `nikic/php-parser`,
which `composer.json` declares only for `tools/ctx`, returned to the dev-only set.

### What is left

| package | own deps | files | verdict |
|---|---|---|---|
| symfony/{http-kernel, http-foundation, routing, event-dispatcher, console, mailer, security-core, mime, http-client}, twig/twig, psr/{container, log, simple-cache} | — | the platform | keep |
| symfony/error-handler | 2 | **0** direct uses | **keep the line.** It reads as droppable and is not: http-kernel accepts `^6.4\|^7.0\|^8.0`, so removing our `^7.4` let composer resolve it to 8.1. The line is a version pin, not a use |
| monolog/monolog | 0 | 2 (SionModel logging) | keep; PSR-3 with no deps |
| firebase/php-jwt | 0 | 4 (API tokens, JUser) | keep, or replace with `hash_hmac` over a signed id (tokens are opaque to callers) |
| spatie/schema-org | 0 | 13 (JSON-LD on books, shrines, persons) | candidate, and the largest on disk at 41 MB: the JSON-LD emitted is a handful of fixed shapes; a typed array builder replaces a 900-class package |
| giggsey/libphonenumber-for-php | 2 | 1 (`Telephone` helper) | candidate, 23 MB: replace with a formatting-only fallback, or drop formatting — one helper, display only |
| erusev/parsedown + parsedown-extra | 0 + 1 | 4 | keep one Markdown parser; parsedown is unmaintained since 2019 — replace with `league/commonmark` (maintained, 3 deps) **or** drop Markdown where it is only rendering plain notes. Decide per use |
| phpoffice/phpspreadsheet | several | library import/export | keep; nothing else reads XLSX |
| spatie/opening-hours | 0 | 2 (shrine opening hours) | keep for now; revisit with the shrine dataset work |
| nicolaswurtz/chordpro-php (jroedel fork) | 0 | 1 (`CompositionController`) | the last personal fork; either upstream it or vendor the ~300 lines this site uses |

### What left on 2026-09-21, and what replaced it

Nine `require` lines, seventeen packages out of the lock, and about 8 MB of `vendor/`.
Each replacement was gated on a differential against the package it replaced, run in the
capsule while that package was still installed.

| package | replaced by | what the measurement said |
|---|---|---|
| cocur/slugify | `App\Text\Slug` — ICU transliteration plus 223 rules derived mechanically from the package's own rulesets | 0 differences over all 14,211 distinct values this application slugs, their 149 characters, and Latin-1/Latin-Extended/Greek/Cyrillic in full. **Not** `AsciiSlugger`, which answers `schonstatt` where cocur answers `schoenstatt` and would have cost two new declared requirements |
| neitanod/forceutf8 | `SionModel\Text\Utf8Repair` | 0 differences over 30,574 production values and a 532-case byte probe. A whole-string `mb_convert_encoding` was tried first and loses the valid parts of a mixed string, so the byte loop is transcribed instead — reproduced bugs included |
| voku/html2text (+4) | `SionModel\Mailing\HtmlToText` | 2,748 of 2,756 stored documents identical; both mail templates and all 44 rule probes byte-identical. The eight differ by a blank line inside two very long footnote lists |
| tijsverkoyen/css-to-inline-styles (+1) | `SionModel\Mailing\CssInliner` | identical inline declarations on every element of all 117 real mail bodies in `mailings` |
| nesbot/carbon (+5) | `SionModel\I18n\RelativeTime` | 0 differences over 175 (locale, offset) pairs. It carries the nine strings per locale `diffForHumans()` actually reached, transcribed from Carbon's own `Lang/` files |
| scottconnerly/timezone | `App\Time\TimeZoneOptions` + `src/Time/timezone-names.php` | the names come from Rails' MIT `ActiveSupport::TimeZone::MAPPING`, not from the GPL-2.0-only package. Thirteen entries had drifted since its 2018 snapshot, all corrections; no stored value was affected |
| matriphe/iso-639 | nothing — the code was dead | `getIso639()`, `getNativeLanguageNames()` and `getNativeLanguageName()` were `@deprecated` with no caller in four repositories |
| tedivm/jshrink | nothing — the reason had expired | `minify_js` existed so `tools/port-baseline.php` could diff against the laminas rendering. There are no `.phtml` left and that tool records itself as retired |

Two of the nine were also **GPL in a BSD-3-Clause tree** — `scottconnerly/timezone`
(GPL-2.0-only) and `voku/html2text` (GPL-2.0-or-later) — which matters for issue #259.

**A recording had an expiry date, and this is how it was found.** The timezone labels were
built with `timezone_offset_get($zone, new DateTime())`, so they followed daylight saving:
`test/Element/element-surface.php` held `'Europe/Berlin' => '(GMT+02:00) Bern'`, recorded in
summer, and would have failed on 2026-10-25 for a reason no commit caused. The replacement
formats the zone's **standard** offset, so the labels no longer move twice a year.

Rule: before adding a package, ask whether twenty lines of our own code would do; when
touching code that uses one of the candidates above, replace it in the same PR. And record
the outgoing package's answers as data *before* removing it — a parity test dies with its
subject, a recording outlives it.
