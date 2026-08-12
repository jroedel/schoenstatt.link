# The Symfony strangler

How Symfony and laminas-mvc share this application while routes move across, and
what you need to know before touching either side.

Background on the decision itself is in [BACKLOG.md](BACKLOG.md) under
"Strategic direction". This file is about the mechanism now that it exists.

## The shape

```
public/index.php
  │
  ├─ SYMFONY_KERNEL unset or "0" ──> Laminas\Mvc\Application::init()->run()
  │                                  (the front controller that has always
  │                                   been here, unchanged)
  │
  └─ SYMFONY_KERNEL = "1" ─────────> App\Kernel
                                       ├─ GET /_health   → App\Controller\HealthController
                                       ├─ [/{_locale}]/sm/cache-status
                                       │                 → App\Controller\CacheStatusController
                                       ├─ [/{_locale}]/sm/clear-persistent-cache
                                       │                 → App\Controller\ClearPersistentCacheController
                                       ├─ [/{_locale}]/shrines
                                       │                 → App\Controller\ShrinesController
                                       │                       └─ Twig: templates/layout.html.twig
                                       ├─ [/{_locale}]/admin
                                       │                 → App\Controller\AdminController
                                       │                       (first *restricted* route)
                                       ├─ [/{_locale}]/wayside-shrines
                                       │                 → App\Controller\WaysideShrinesController
                                       │                       └─ shares _shrine-index.html.twig
                                       │                          with ShrinesController
                                       ├─ [/{_locale}]/                  ⎫
                                       ├─ [/{_locale}]/developers        ⎪
                                       ├─ [/{_locale}]/acknowledgements  ⎬ App\Controller\
                                       ├─ [/{_locale}]/privacy           ⎪ ContentPageController
                                       ├─ [/{_locale}]/shrines/submitting-photos
                                       │                                 ⎭ templates/content/
                                       ├─ [/{_locale}]/api/v1/associations/shrines.json
                                       ├─ [/{_locale}]/api/v2/associations/shrines.json
                                       │                 → App\Controller\ShrinesGeoJsonController
                                       │                       (one controller, both versions)
                                       ├─ [/{_locale}]/sm/phpinfo
                                       │                 → App\Controller\PhpInfoController
                                       ├─ [/{_locale}]/sm/data-problems
                                       │                 → App\Controller\DataProblemsController
                                       │                       └─ App\Laminas\EntityFormatter
                                       ├─ [/{_locale}]/sm/view-changes
                                       │                 → App\Controller\ViewChangesController
                                       │                       └─ _changes-table.html.twig
                                       ├─ [/{_locale}]/timeline
                                       │                 → App\Controller\TimelineController
                                       ├─ [/{_locale}]/music
                                       │                 → App\Controller\MusicController
                                       ├─ [/{_locale}]/dictionary        ⎫ App\Controller\
                                       ├─ [/{_locale}]/dictionary/{inLanguage}
                                       │                                 ⎭ DictionaryController
                                       ├─ [/{_locale}]/literature/150-preguntas-sobre-schoenstatt
                                       │                 → App\Controller\OneFiftyPreguntasController
                                       ├─ [/{_locale}]/associations
                                       │                 → App\Controller\AssociationsController
                                       │                       └─ shares _associations-table.html.twig
                                       │                          with both shrine indexes
                                       ├─ [/{_locale}]/roles
                                       │                 → App\Controller\RolesController
                                       ├─ [/{_locale}]/libraries
                                       │                 → App\Controller\LibrariesController
                                       └─ /{path} .*     → App\Http\LegacyBridge
                                                              └─ Laminas\Mvc\Application
```

Six kernel listeners run on a Symfony-served route and on **no** bridged one,
because on a bridged one laminas-mvc or `LaminasResponseConverter` has already done
the equivalent. `App\Http\SymfonyRoute::isPorted()` is how each of them tells,
by asking whether `_route` is anything other than `legacy`:

| listener | event | what it restores |
|---|---|---|
| `LocaleListener` | request | `\Locale::setDefault()` from `_locale`, else negotiated — SlmLocale's job |
| `SessionListener` | request | starts the session through `Laminas\Session\ManagerInterface` and prunes pre-Laminas values — `JUser\Module::onBootstrap()`'s job. Only when a session cookie is present |
| `AuthorizationListener` | request | the route guard — `BjyAuthorize\Guard\Route`. See "Authorization" below |
| `CspListener` | response | the `Content-Security-Policy` + nonce `SionModel\Mvc\CspListener` sends |
| `GdprCookieListener` | response | strips cookies without consent — `Application\View\GdprStrategy::onFinish()` |
| `InventedCacheControlListener` | response | drops the `no-cache, private` `ResponseHeaderBag` adds unasked |

As of batch 4, **47 of the 187 laminas routes are served by Symfony** (21 distinct paths,
each declared twice for its locale prefix); `docs/acl-rules.md` carries the count and the
guard each one is checked against.

`config/symfony/routes.php` **is** the migration status of the site, read top to
bottom: `UrlMatcher` takes the first route that matches, so everything declared
above `legacy` belongs to Symfony and everything else still belongs to
laminas-mvc. Porting a route means moving one line up past the catch-all. When
the last one moves, `LegacyBridge` is deleted.

## Which front controller is live

`SYMFONY_KERNEL` is an Apache environment variable, read by `public/index.php`
before any configuration is loaded — which is why it is an env var and not a
config key.

| where | set in | value now |
|---|---|---|
| capsule | `docker/apache-vhost.conf` (committed, baked into the image) | `1`, with `SetEnv` — so **no cookie can move a capsule request off it** |
| production | `public/.htaccess` (**tracked and deployed**) | no site-wide default → `0`, plus a cookie override each way |

So **the capsule runs the Symfony front controller and production does not.**
That is the whole point of the gate: reverting production is an `.htaccess` edit and
nothing else, which matters while phploy still has its mid-deploy broken window. To A/B
locally, edit `docker/apache-vhost.conf`, then
`docker compose build && docker compose up -d` (the vhost is `COPY`d into the image, not
mounted).

**`public/.htaccess` is tracked and phploy deploys it** — this file said "untracked,
server-side" until 2026-08-08 and that was simply wrong. The pre-deploy hook copies the
server's version into `data/htaccess-backups/` on every run *because* the deploy
overwrites it, so a hand-edit on the server survives exactly until the next deploy. Edit
the repo copy.

### The three states, and the two ways to write the flip wrongly

`public/.htaccess` has room for a site-wide default and carries two per-visitor
overrides:

```apache
SetEnvIf Request_URI ".*"                 SYMFONY_KERNEL=1   <- the flip, committed 2026-08-10
SetEnvIf Cookie "sl_symfony_canary=1"     SYMFONY_KERNEL=1
SetEnvIf Cookie "sl_symfony_canary=0"     SYMFONY_KERNEL=0
```

So the cookie has **three** states: absent means "whatever everyone else gets", `1`
forces `App\Kernel`, `0` forces `Laminas\Mvc\Application`. Before the flip the opt-in was
the canary that made ported routes testable against production's real data, real ICU
(**72.1** there against the capsule's 76.1 — 27 `IntlDateFormatter` call sites) and real
session store, without a window in which all traffic was on them. Now that the default is
`1`, the opt-*out* is the one that matters: it is how one person gets back to laminas
without a deploy. `App\Http\KernelCanary` is the single PHP-side definition of all of it.

**Both overrides are deployed before the flip on purpose**, so that the flip is one
*added* line and the escape hatch has been exercised before it is the only one left. The
two ways to add that line and silently kill the escape hatch, both measured:

- **Below an override.** mod_setenvif evaluates top to bottom and the *last* match wins,
  so a default below an override overwrites it. Probed 2026-08-09: with the order
  reversed, a request carrying `sl_symfony_canary=0` still arrived with
  `SYMFONY_KERNEL=1`.
- **With `SetEnv`.** mod_env runs *after* all of mod_setenvif, so `SetEnv
  SYMFONY_KERNEL 1` beats every line in the block whatever the file order. Measured
  2026-08-08, after making that exact mistake with `SetEnv SYMFONY_KERNEL 0`.

Neither has any symptom beyond a cookie that stops doing anything, which is why
`test/Integration/KernelCanaryTest` pins both against the file itself — before the flip
is written rather than after. `docs/DEPLOY.md` carries the flip runbook and the three
rollbacks.

Two further things to know:

- **These are toggles, not secrets.** Anyone can set either. That is safe because both
  front controllers consult the same ACL — `App\Authorization\RouteGuard` asks about the
  same `route/<name>` resources `BjyAuthorize\Guard\Route` does, from the same config —
  so an opted-in visitor reaches nothing they could not already reach.
  `docs/acl-rules.md` is what guarantees that, not the cookie.
- **The pre-flip state fails closed.** Anything wrong with the cookie, the header or the
  file leaves the visitor on laminas. That inverts at the flip, which is the honest
  reason the rollback list in DEPLOY.md is three items long rather than one.

### Switching it from a browser

Administrators get a **Switch kernel** link on the admin landing page (`/en/admin`),
which drops them back on the page they came from with a flash saying what changed.
`sch_administrator` only — the canary is not a privilege, but a control that changes how
the site renders is not something to offer visitors.

It was a navbar item until 2026-08-11 and is now one of the admin page's links, filtered
by the same `route/kernel-switch` resource the route is guarded by, so the two still
cannot disagree. What the move costs: "the page they came from" is now almost always
`/en/admin`, since that is where the Referer points, so inspecting a *particular* page
under the other kernel means toggling first and navigating second. The action is
unchanged — it still offers whichever kernel you are not on, and still says which one
you landed on.

It **offers whichever kernel you are not on**, and the way it decides is the part worth
keeping: `Application\Controller\IndexController::kernelSwitchAction()` asks two
questions it can actually answer — "am I overriding anything?" from the cookie, and
"which kernel is serving me?" from `getenv('SYMFONY_KERNEL')`, which Apache exports to a
bridged request too. Holding an override clears it; holding none sets the opposite of the
live kernel. The site *default* appears nowhere in that logic, because PHP cannot read
`.htaccess` and inferring it would be a guess — which is why the action needs no edit on
flip day, and why its message for the clear branch says "the same front controller as
every other visitor" rather than naming one.

It is a **laminas** route (`kernel-switch`), and that is the design decision worth
keeping: the Symfony kernel bridges every unported path back to laminas, so one action
switches the canary *both* ways. A ported route could only ever switch it off — you would
have no way to turn it on, because you are on laminas when you want to.

Two things it has to handle, both in
`Application\Controller\IndexController::kernelSwitchAction()`:

- **Consent, first.** Both GDPR strategies call `header_remove('Set-Cookie')` for a
  visitor who has not accepted cookies, so without consent the toggle *cannot* work. It
  says so rather than redirecting with a success message and changing nothing.
- **The Referer, carefully.** The admin is sent back where they came from, and that
  header is attacker-controlled — so it is followed only when its scheme and host match
  the current request, and falls back to the home page otherwise.

The cookie is written with **no expiry**, i.e. a session cookie: closing the browser
drops the override, so nobody leaves themselves off the site default for weeks without
noticing.

`test/Integration/KernelCanaryTest` is what stops the definitions drifting — the PHP
constants and the Apache directives — because nothing in PHP reads `.htaccess` and a
rename on either side has *no symptom at all*: the toggle sets a cookie, the admin is
redirected, a success message appears, and the front controller never changes. It also
pins the two ordering rules above, and that the variable is named the same thing in
`.htaccess` and `public/index.php`.

`tools/smoke-prod.sh` runs a front-controller pass when `SMOKE_PROD_CANARY_COOKIE` is
set. It **probes which kernel is the site default** and then asserts the pair: that the
default really serves ordinary traffic, and that the override really reaches the other
one. A default that has silently reverted and an override that has silently stopped
working are both failures, and only checking both directions tells either apart from
success. The discriminators are `/_health` (a route only Symfony has) and
`slm_locale=en_US` (a cookie only SlmLocale sets).

Probing rather than assuming is deliberate: the script used to hard-code "production is
laminas, the cookie is the exception", which turns into a false failure on flip day — the
one day the deploy's own smoke run most needs to be believed. As of 2026-08-09 it also
covers every public ported route rather than the first seven, the v3 API's public schema
and its JSON 401, and three of `LaminasResponseConverter`'s four rules on a *bridged*
page: real gzip under `Content-Encoding: gzip`, no duplicated `Set-Cookie`, and no
invented `Cache-Control`. Those last three are the whole site's path after the flip, and
production is the only place they can be checked behind a TLS-terminating proxy. Pointing
the script at the capsule fails the two opt-out checks by design, because the vhost's
`SetEnv` outranks any cookie there.

What it cannot reach is a signed-in page: the script does no sign-in, so the guarded
routes are checked for their *refusal* — the 302 to the sign-in page that
`App\Authorization\RouteGuard` reproduces from `JUser\View\RedirectionStrategy`. That is
worth more than it sounds: a ported guarded route that admitted everyone would look
perfectly healthy, and this is the check that sees it. Verifying the pages themselves
means browsing them with the cookie set and a moderator session, which is the manual
step.

### The footer says how the page was served

Every HTML page carries one muted line in its footer, visible to everyone, e.g.

```
Served by the Symfony kernel → Twig template · route shrines.locale ·
App\Controller\ShrinesController · SYMFONY_KERNEL=1, the site default

Served by the Symfony kernel → .phtml view script via LegacyBridge · route persons ·
Schoenstatt\Controller\PersonsController::index · SYMFONY_KERNEL=1 from the
sl_symfony_canary=1 cookie
```

It exists because the answer is genuinely unreadable from the page. Rows 1 and 3 of the
table below render *identical markup* — that is what the bridge is for — and the cookie
that decides which one you got is invisible:

| front controller | renderer | when |
|---|---|---|
| `Laminas\Mvc\Application` | `.phtml` | `SYMFONY_KERNEL` off for this visitor |
| `App\Kernel` | Twig | a ported route |
| `App\Kernel` → `LegacyBridge` | `.phtml` | an unported route — still most of the site |

Five fields, and the last is the one to read: `SYMFONY_KERNEL=1, the site default`,
`… from the sl_symfony_canary=1 cookie`, or `… from server config; the
sl_symfony_canary=0 cookie is being ignored`. That third state is real, not defensive —
it is what the **capsule** always reports for a cookie, because `docker/apache-vhost.conf`
uses `SetEnv` and mod_env beats all of mod_setenvif. Without it, "my cookie did nothing"
reads as a broken toggle rather than the server overruling it.

Three things to know before touching it:

- **`App\View\ServingNote` is the only place the string is built.** Two wirings reach it —
  a `serving_note()` Twig function in `ChromeExtension` and a `servingNote` laminas view
  helper — because two layouts render it, and two descriptions of three states would
  eventually disagree. Each field is reported independently rather than derived from a
  conclusion, so a state this code does not anticipate shows up as a contradiction rather
  than a plausible sentence.
- **The `.phtml` side is guarded on the helper being registered**, and that guard is
  load-bearing. The helper lives in merged config, production caches merged config in
  `data/config/`, and the deploy empties that cache *after* uploading files — so in that
  window the new layout meets the old service map. An unresolved helper inside a layout
  throws after the response is assembled, i.e. a blank HTTP 200 on every laminas-rendered
  page. Measured by unregistering it: the page renders in full, minus the note.
- **`tools/port-baseline.php` strips it** (rule 8). It is the one normalization rule that
  erases a real difference between the two front controllers, because that difference is
  the entire feature; comparing it would report drift on every path in every locale.

It is a migration instrument and should die with the migration. Removing it is one
commit: the two `serving-note` paragraphs, `App\View\ServingNote`, its two callers, its
two tests, the `servingNote` config entries, and rule 8.

### What flipping the default changes that the canary never showed

Everything a ported route does is already exercised in production through the cookie. What
the flip changes is *who* and *how much*, and three consequences only appear at that
scale:

- **Failures on ported routes now notify.** A ported route runs no module's
  `onBootstrap`, so `SionModel\Module` never upgraded `FatalErrorHandler` past its
  container-free fallback: a record in `data/exceptions`, default capture settings, and
  no email. `App\Kernel::handle()` installs the configured resolver itself (2026-08-09),
  lazily, and returns `[null, null]` rather than throwing if the container is what broke
  — because `FatalErrorHandler::report()` wraps resolve *and* report in one try/catch, so
  a throwing resolver would cost the whole record. Pinned by
  `test/Integration/PortedRouteErrorReportingTest`.
- ~~**Missing translation phrases stop being recorded for ported pages, for everyone.**~~
  **Fixed 2026-08-11.** It was true, and it was the flip's sharpest edge: `JTranslate`'s
  `MvcEvent::FINISH` listener is what writes a discovered phrase, no ported route runs it,
  and a queued-but-unwritten phrase renders exactly like a written one — so nothing failed
  and nothing logged. `App\Laminas\PhraseFlush` plus `App\Http\PhraseFlushListener` now
  write them on `KernelEvents::TERMINATE`. Measured: one smoke run records 170 phrases that
  were being discovered and discarded. See the FINISH-listener note below for why the
  overflow hazard that blocked this no longer applies.
- **The bridge is in front of every unported request.** Measured 2026-08-09 in the capsule
  on `/en/user/login`, sequential warm requests: median 175 ms bridged against 169 ms
  direct, with an A-B-A control drifting by the same 2–6 ms. So the kernel, the route
  match and the response conversion together are inside the noise of a page that spends
  ~170 ms in laminas. Not a reason to hesitate; recorded so nobody has to re-derive it.

**Beware `APP_ENV` when reasoning about either.** `public/.htaccess` sets
`APP_ENV=production`, and because `AllowOverride All` is on, that wins over the
vhost's `SetEnv APP_ENV "development"` **inside the capsule too** — measured
2026-08-05. The vhost line is dead config. Nothing in the kernel keys off
`APP_ENV` for this reason.

## What the bridge does and does not touch

Inbound needs no translation. Symfony's `Request` is built *from* the
superglobals and never mutates them, so `Laminas\Http\PhpEnvironment\Request`
still reads the real `$_SERVER`, `$_GET`, `$_POST` and `php://input`. The bridge
accepts a `Request` and ignores it.

Outbound is where the care went. The bridge detaches `SendResponseListener` — and
only that — after `Application::init()`, so routing, dispatch, the view and every
`MvcEvent::FINISH` listener still run exactly as before, but the response is
handed back instead of echoed. `App\Http\LaminasResponseConverter` then turns it
into a Symfony response. Four things that class exists to get right, each a live
bug before it was a comment:

1. **`getContent()`, never `getBody()`.** `getBody()` de-chunks and gunzips
   according to the response's own `Content-Encoding`, so on the sitemap route —
   which gzips its payload — it hands back plaintext still labelled
   `Content-Encoding: gzip`. `getContent()` is also exactly what
   `HttpResponseSender::sendContent()` echoes today.
2. **`Headers::toArray()`, not a `foreach` over `Headers`.** That reproduces what
   `PhpEnvironment\Response::sendHeaders()` does: append only
   `MultipleHeaderInterface` headers (`header($line, false)` — Set-Cookie and
   friends), replace everything else. A hand-rolled loop emits duplicates the
   current sender collapses, which is a behaviour change dressed as a conversion.
3. **Drop `ResponseHeaderBag`'s invented `Cache-Control`.** The bag adds
   `no-cache, private` to any response carrying none. Laminas sends none, PHP's
   session cache limiter already sends a stricter one at the SAPI level, and
   Symfony's `sendHeaders()` appends rather than replaces — so keeping it would
   add a weaker second directive to every authenticated page.
4. **Protocol version.** Symfony's `Response` defaults to HTTP/1.0 and writes
   that into the status line; Apache honours it and closes the connection.
   `App\Http\ProtocolVersionListener` copies the request's version on
   `kernel.response`, which is the one thing `Response::prepare()` would have
   done for us that we actually needed. Calling `prepare()` itself is not an
   option — it also rewrites `Content-Type`, strips bodies from 304s and
   normalises `Content-Length`, all on responses laminas-mvc already finished.

Headers that laminas never puts on the response object at all are unaffected by
any of this. `GdprStrategy::onFinish()` (`header_remove('Set-Cookie')`,
`setcookie()`) and `SionModel\Mvc\CspListener` (`header_remove()` + `header()`)
work directly on PHP's SAPI header list, and they run inside
`Application::run()`. Symfony's `sendHeaders()` appends to that same list rather
than resetting it, so both survive untouched. Verified: nothing in the
application sets a cookie on a laminas response object, so the conversion cannot
smuggle a `Set-Cookie` past the consent gate.

## Why there is no FrameworkBundle

`symfony/framework-bundle` cannot be installed here today, and the reason is
laminas-mvc itself — **not** bjy-authorize, which is what this section said
before 2026-08-05:

```
symfony/framework-bundle           → symfony/cache → psr/cache ^2|^3
laminas/laminas-cache 3.14                         → psr/cache ^1      ← the pin
laminas/laminas-cache 4.3                          → psr/cache ^2|^3   ← lifts it
laminas/laminas-cache 4.3          → laminas-servicemanager ^4.5
laminas/laminas-mvc 3.8            → laminas-servicemanager ^3.20.0    ← the gate
kokspflanze/bjy-authorize 2.4.4    → laminas-cache ^2.13.2 || ^3.1.0   ← also caps it
```

bjy-authorize really does cap `laminas-cache`, and 2.4.4 really is the last
release of that line — but it is one of *two* caps. Retiring it leaves laminas-mvc
forbidding servicemanager 4, so `laminas-cache 4` stays uninstallable, `psr/cache`
stays at 1, and the bundle stays out. laminas-mvc requires `^3.20.0` in every
version, including 3.9.x-dev and 4.0.x-dev.

So the bundle is gated on **finishing this migration**, not on a step before it.
Retiring bjy-authorize is still worth doing on its own merits — it is abandoned
and all site authorization runs through it — but it should not be sequenced as
the thing that opens this gate. Full measurement in [php-85.md](php-85.md),
which records the same conclusion for PHP 8.5.

`symfony/http-kernel` and `symfony/routing` need no `psr/cache` at all, which is
why the kernel is hand-wired from components instead.

When that gate opens, `App\Kernel` is what FrameworkBundle replaces. The routes
and everything behind them carry over unchanged, because `LegacyBridge` and
`HealthController` are plain callables and `App\Container` is only a PSR-11
container — which is all `ContainerControllerResolver` ever asks for.

## What every `onBootstrap` does, and who reproduces it

A ported route runs no module's `onBootstrap`, and that is the single most productive
place to look for a defect in this migration: work that lives there is invisibly absent,
and the page still renders. Audited in full 2026-08-08 — four modules define one:

| module | what `onBootstrap` does | reproduced by |
|---|---|---|
| `Application` | attaches `GdprStrategy` | `App\Http\GdprCookieListener` |
| | builds the DB-derived navigation branches and caches them in APCu | **declared per route** since 2026-08-08 — `App\View\SiteChrome` reads the raw `navigation` config, so a page whose laminas twin lights up an ancestor names it with `SiteChrome::NAV_ROUTE`. `/dictionary/{lang}` and `/literature/150-…` both declare `publications`. Not derivable from route names — `dictionary/inLanguage` hangs under `publications` |
| `Schoenstatt` | attaches `ModuleRouteListener` | **nothing, and nothing needed** — it rewrites laminas-mvc route matches, which a Symfony-served route does not have |
| `JUser` | starts the session, prunes pre-Laminas values | `App\Http\SessionListener` |
| | `GlobalAdapterFeature::setStaticAdapter()` | **nothing** — used only by `CreateRoleForm`, `EditUserForm`, `DeleteUserForm` and `EditPhraseForm`, and no ported route renders a form. **A prerequisite for the first form route ported**, which would otherwise get a null adapter from its `NoRecordExists` validator |
| `JTranslate` | configures the translator: locale, fallback, the DB-report listener, and the file patterns that *are* the translations | `App\Laminas\TranslatorConfigurator` |
| | `TranslationsTable::flush()` on `MvcEvent::FINISH`, which **writes the collected missing phrases to the database** | `App\Http\PhraseFlushListener` on `KernelEvents::TERMINATE` (2026-08-11), armed by `App\Laminas\TranslatorConfigurator` so a route that never translates pays nothing. `TERMINATE` and not `RESPONSE` on purpose — see the FINISH-listener note below |
| | sets the `translate`/`formLabel`/… helper text domains per controller module | the `_text_domain` route default, read by `App\Twig\LaminasExtension::translate()` |

Two rows there are still "nothing", and both are deliberate rather than pending: the
navigation branches, and the static adapter. Neither is reachable from a route ported so
far; the static adapter becomes a blocker the moment a form is.

## What a ported route loses

A Symfony-served route never boots laminas-mvc — `LegacyBridge` is what calls
`Application::init()` — so everything laminas' MVC listeners provide is simply
absent: **no BjyAuthorize route guard, no SlmLocale, no laminas-view layout, and
none of the response headers the MVC listeners add** (the
`Content-Security-Policy` from `SionModel\Mvc\CspListener`, the session cookie,
the GDPR strategy's cookie stripping). What *is* still there comes from Apache:
HSTS, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.

**The route guard is the item on that list that has been replaced**, as of
2026-08-06 — see "Authorization" below. Until then a ported guarded route would
have admitted everyone, which is why the first three ports were a pair of
maintenance endpoints whose protection already lived in the controller and one
page whose guard was public.

`tools/acl-table.php` remains the check, and now checks the stronger thing: it
lists Symfony-served routes with **which ACL resource each is verified against**,
reports which laminas routes they shadow, and warns when a route declares nothing
at all, when it names a resource the ACL does not define, or when a restricted
laminas guard has been replaced by something other than itself.

**"No identity" needs one correction**, learned porting `shrines`. The *guard* is
gone, but the identity is not. Asking `isAllowed()` makes BjyAuthorize ask JUser
for the identity, JUser reads it from the session, and reading the session calls
`session_start()` — measured: `session_status()` goes NONE → ACTIVE across a single
`isAllowed()` call. So permission-gated *markup* still works on a ported page: a
signed-in moderator gets the moderator table and the progress bars, an anonymous
visitor gets the plain one, exactly as under laminas. Two consequences:

- The session cookie `session_start()` writes lands in PHP's SAPI header list before
  any listener can object, which is why `App\Http\GdprCookieListener` exists. Without
  it a ported HTML page is the only page on the site that sets a cookie on an
  unconsented visitor.
- Only the route *guard* has to be replaced when porting a protected route, not the
  whole identity story.

So the list of things a ported route loses, restated: SlmLocale, the laminas-view
layout, and (until a listener restores them) the response headers the MVC listeners
add. Not the ACL, not the identity, not the session — and, since 2026-08-06, not
the route guard either.

Two consequences worth knowing before porting anything:

- **The locale prefix has to be declared.** Every caller uses `/en/…`, and under
  laminas `SlmLocale\Strategy\UriPathStrategy` strips that segment before routing.
  Symfony sees the literal path, so a ported route declares both forms — see the
  `$ported()` helper in `config/symfony/routes.php`, which adds
  `/{_locale}<path>` alongside `<path>` with `_locale` constrained to the five
  configured aliases. Constrained, not open, so that `/anything-else/…` keeps
  falling through to `legacy`.
- **Failure modes may improve, and that counts as a behaviour change.** The
  maintenance endpoints used to answer a bad key with `302 → /en/user/login`
  (JUser's `RedirectionStrategy` handling `UnAuthorizedException`) — a deploy hook
  follows it and reads an HTML login page as success. The ported controllers
  answer `401` with a JSON body instead. Correct callers see no difference; the
  smoke suite asserts the new contract, and `bin/console cache:flush-persistent`
  handles both shapes because during the migration it talks to hosts of both
  kinds.

## Authorization

Built 2026-08-06, and it is the gate the rest of the migration was waiting behind:
of 191 routes, 149 restrict access, and none of them was portable while a
Symfony-served route silently admitted everyone.

### Where the check runs, and why it cannot be skipped

`App\Http\AuthorizationListener` on `kernel.request`, priority `-16`. Three
properties, each deliberate:

- **It listens to the event, not to a route**, so nothing declared in
  `config/symfony/routes.php` can opt out. A route added next month is checked
  whether or not its author thought about it.
- **Below `RouterListener`'s 32**, because it reads the matched route's own
  declaration and there is nothing to read before routing.
- **Below `LocaleListener`'s 0**, because the 403 page renders through Twig and the
  locale trap in [view-scripts.md](view-scripts.md) would otherwise translate the
  whole layout against `en_US_POSIX`.

`kernel.controller` would also have been un-skippable, and was rejected for one
reason: it fires *after* `ContainerControllerResolver` has constructed the
controller, so a refused request would still run its factory. The laminas side has
already been bitten by exactly that shape — the "eager controller_services trap" —
and a denial with no side effects is worth more than the later hook.

The listener resolves its guard through a closure rather than holding one, because
listeners are registered before the request exists and `App\Kernel::routeUrl()` reads
the request's base URL. Building it eagerly would memoize an empty base URL and strip
the locale prefix off every link on every ported page.

### Reusing the ACL rather than duplicating it

Each route declares an `App\Authorization\RouteAccess` in its own defaults, and the
declaration names **a resource the laminas guards already define**:

```php
$ported('admin', '/admin', AdminController::class, RouteAccess::guardedBy('route/admin'));
```

`BjyAuthorize\Guard\Route` keys on `route/<laminas-route-name>`, so that string is
the same guard entry in `config/autoload/acl.global.php` that governs the laminas
route. One source of truth: tighten the entry and both front controllers tighten, in
one commit, with the `docs/acl-baseline.json` diff to show it. There is deliberately
**no** parallel `symfony_acl` config block — two authorization configurations for one
site is the state where a page is protected on one front controller and open on the
other and nothing fails.

`RouteAccess::openToEveryone('<why>')` is the other option and requires a reason,
which `tools/acl-table.php` prints. It is for a route with nothing to consult
(`/_health` shadows no laminas route) or one whose real gate is in the controller and
whose shadowed guard is public anyway (the two maintenance endpoints — checking the
ACL there would put a session and the role/resource queries behind it on the deploy
path for a foregone answer).

**A route that declares neither raises `UndeclaredRouteAccess`**, naming the route.
Not a silent 403: a route nobody decided about is a programming mistake, and a
mistake that presents as "forbidden" sends its author to `acl.global.php` instead of
to the line they forgot. Two things catch it before a request can:
`test/Integration/SymfonyRouteAuthorizationTest` walks the whole `RouteCollection`,
and `tools/acl-table.php` reports it as `SILENT BYPASS RISK`.

### The two denial branches

Reproduced from `JUser\View\RedirectionStrategy::onDispatchError()`, and measured
against the live laminas rendering of `/en/admin` before the route was ported rather
than read off the source:

| who | laminas | ported |
|---|---|---|
| anonymous | `302 → /en/user/login?redirect=/en/admin` | identical |
| signed in, not allowed | `403` + `error/403`, inside the layout | `403` + `templates/error/403.html.twig`, inside the Twig layout |

Both live in `App\Authorization\Denial`. Four things worth knowing:

1. **The identity, not the failed check, picks the branch.** Both an anonymous
   visitor and a signed-in one without the role fail `isAllowed()`; redirecting the
   second to a sign-in page would loop them, since signing in again changes nothing
   about their roles. The identity is read from `JUser\AuthService` — the same
   question `RedirectionStrategy` asks. Not from `Authorize::getIdentity()`, which
   returns the literal string `bjyauthorize-identity` and is therefore always truthy.
2. **The return path is the request's path**, not a re-assembly of the matched route.
   Laminas has to re-assemble and wraps it in try/catch because a numeric route name
   reaches the router's `explode()` as an int; reading `getBaseUrl() . getPathInfo()`
   cannot fail that way, so the fallback branch has nothing left to guard and is not
   reproduced. The value is the same string, locale prefix included.
3. **`templates/error/403.html.twig` is a reproduction, not a reuse.** `error/403`
   resolves today to `vendor/kokspflanze/bjy-authorize/view/error/403.phtml` — inside
   the abandoned package this migration intends to retire. The wording and markup of
   its `Route::ERROR` branch are copied verbatim; the one difference is the page
   title, which laminas leaves as "Schoenstatt Link" and this sets to
   "403 Forbidden".
4. **JSON routes deny as JSON**, declared per route through `DenialStyle` and never
   sniffed from `Accept` — the deploy hooks and `tools/smoke-prod.sh` send no
   `Accept` header at all, and a machine caller that follows a 302 reads an HTML
   sign-in page as success. No route needs it yet (both maintenance endpoints are
   open), so `test/Integration/RouteDenialShapeTest` is what keeps the branch honest
   until the first one does.

### What porting a restricted route now takes

`/admin` is the worked example, and there is nothing to it beyond one line of route
declaration: `AdminController` contains no authorization code at all. Read
`App\Controller\AdminController` and the `admin` entry in
`config/symfony/routes.php` together — the second is the whole of the first's
security.

One difference from laminas that survives, and is a behaviour change rather than a
bug: on the **unprefixed** form of a restricted path the two front controllers
redirect in a different order. Laminas answers `/admin` with SlmLocale's `302 →
/en/admin` and only then denies, so an anonymous visitor takes two hops and arrives
at `?redirect=/en/admin`. The Symfony guard runs before the controller that would
issue the locale redirect, so it answers `/admin` with one hop to
`?redirect=/admin`. Nobody's access changes and the visitor lands in the same place
one redirect later; every real caller uses the prefixed form. Fixing it properly
means moving the unprefixed-to-prefixed redirect out of the controllers and into a
listener above the guard, which is a separate change and needs a per-route
declaration of its own (the maintenance endpoints must *not* redirect). As of batch 4
there are **eleven** routes doing it: the three original hand-written copies
(`ShrinesController`, `AdminController`, `WaysideShrinesController`) plus eight going
through `App\Http\LocalePrefix`, which was extracted so the batch did not add eight more
hand-written ones. The helper is not the fix — it only makes the rule exist once — and
the listener still wants doing. Consolidating the three originals belongs with it.

## The Twig layer

HTML pages on the Symfony side render with Twig, from `templates/`. It exists
because rendering the existing `.phtml` from a Symfony-served route is not merely
inadvisable, it is impossible — see the helper limitation below. What is there now,
and what a later port should reuse rather than reinvent:

| file | what it is |
|---|---|
| `templates/layout.html.twig` | the site chrome. `{% extends %}` it, define `page_title`, `breadcrumbs`, `block content`, `block inline_scripts` |
| `templates/schoenstatt/_shrine-index.html.twig` | the shrine index body, shared by `shrines` and `wayside-shrines`; each supplies only `block shrine_header` |
| `templates/schoenstatt/_entity-format.html.twig` | macros for an association or person link, replacing `formatAssociation`/`formatPerson` |
| `src/Twig/TwigFactory.php` | builds the Environment. `App\Kernel` and the integration tests both call it, so a test renders the real thing |
| `src/Twig/LaminasExtension.php` | `laminas_path`, `translate`, `is_allowed`, `flag`, `email_link`, `telephone_link`, `url_object_link`, `edit_pencil`, `flash_messages` |
| `src/Twig/ChromeExtension.php` | `current_route`, `current_locale`, `navigation_items`, `language_options`, `canonical_links`, `search_box`, `display_name`, `csp_nonce`, `server_url`, `json_ld` |
| `src/Laminas/RouteUrl.php` | assembles laminas URLs, locale prefix included. **The reason any of this works** |
| `src/Laminas/ViewHelpers.php` | the only door to a laminas view helper, one typed method per allowed helper |
| `src/View/SiteChrome.php` | the chrome's decisions: ACL-filtered navigation, language chooser, search box |
| `templates/books/_library-list.html.twig` | the library list, written as a partial now because the literature home page will need it |
| `src/Http/LocalePrefix.php` | the 302 an HTML route owes its own unprefixed form, in one place |
| `src/Books/EventTimeline.php` | the timeline's grouping, pinned against the laminas action by a parity test |

**Every `{% set %}` in `layout.html.twig` is prefixed `chrome_`, and it must stay that
way.** A `set` at the top level of a layout is not scoped to the layout: `{% block
content %}` is called *from there*, with the context as it stands at that line, so any
name the layout sets above the block shadows the identically-named variable the
controller passed — silently, in every page that extends the file. It happened and it
reached production: the layout set `languages` for the locale chooser, `/literature`
passes `languages` (the ISO-639 map), and every catalogue row rendered "Catálogo de livros
em fr" — the template's own `is defined` fallback to the raw code — on a page that was
otherwise perfectly translated. A page variable never needs the prefix; a new chrome local
always does.

Three settings in `TwigFactory` are decisions, each with its reasoning in the
class docblock: `strict_variables` is **on** (the opposite of `PhpRenderer`),
`autoescape` is on with markup-returning functions declared `is_safe: html`, and the
compile cache is used only when its directory is writable, with `auto_reload` on
because Twig keys a compiled file by the template's *name*, not its contents — with
`auto_reload` off, an edited or deployed template is never recompiled.

### Translations, and the text domain

`translate()` in a template goes through `App\Twig\LaminasExtension`, and two things
about it are not obvious:

1. **The translator is configured by a delegator, not by the extension.**
   `App\Laminas\TranslatorConfigurator` decorates the translator service and does what
   `JTranslate\Module::onBootstrap()` does — locale, fallback, the missing-translation
   reporter, and the `addTranslationFilePattern()` calls that are the only reason any
   translation exists. Lazy, so `/_health` and the maintenance endpoints pay nothing; and
   it covers *models* as well as templates, which matters because
   `SchoenstattTable::nameByLocale` is itself built by calling `translate()`.
   Register it on the **canonical** `Laminas\Mvc\I18n\Translator`, never on the
   `MvcTranslator` alias — aliases are resolved before delegators are looked up, so one
   attached to the alias never runs and fails completely silently.
2. **A phrase's text domain is per page, and the phrases are genuinely scattered.**
   laminas sets the `translate` helper's domain to the controller's module namespace, so
   each ported route declares the equivalent as a `_text_domain` default and
   `translate()` tries it before `default`. Measured in es_ES: `Shrines` lives *only* in
   `default`, `Wayside shrines` and `Fr.` *only* in `Schoenstatt`. No single default
   domain can render a page correctly, which is why the lookup consults two.

**Three places take a domain other than the page's, and all three were wrong until
2026-08-08.** Each was found by comparing a *signed-in* rendering in a non-English locale,
which is why they survived batch 3:

- **`<title>`** is translated by the layout, because laminas' `headTitle()` helper
  translates by default and every ported page hands it a raw English string. Until this
  batch the layout printed it verbatim, so `/es/developers` said "Developers Center" where
  laminas says "Centro de desarrolladores". A page that has *already* translated its title
  sets `page_title_translate = false`; only the dictionary does, mirroring its
  `setTranslatorEnabled(false)`.
- **Navigation labels** take the **`default`** domain, not the page's. Both halves are
  measured: `Literature` → "Literatura" in es, so labels are translated; `Admin` → "Admin"
  in es even though `Schoenstatt` holds "Administración", so it is not the page's domain.
- **Breadcrumb labels are translated** (2026-08-11), on both sides. They were not, and it
  was the one place a fully translated page contradicted itself: `/it/shrines` said
  "Santuari" in the navbar and "Shrines" in the breadcrumb directly beneath it. The laminas
  partial looks a label up in the navigation helper's domain and falls back to `default`;
  the layout does the same through `translate()`. A crumb whose label is *data* passes
  `'translate': false` — an association's name on the edit page, the dictionary page's own
  title — because translating those would put record content in the phrase table, one row
  per record.

`translate()`'s two-domain lookup is a **superset** of laminas' behaviour, not a mirror
of it — laminas has no cross-domain fallback. It cannot lose a translation laminas finds;
in principle it could find one laminas misses, which would show as the ported page being
*more* translated. Verified equal across all five locales; see the both-front-controllers
procedure below.

**Only the page's own domain discovers a phrase, and the second lookup is a read.** A
`Translator::translate()` that misses fires `EVENT_MISSING_TRANSLATION`, and JTranslate's
listener is the only path by which a row enters `trans_phrases` — so while the fallback
was a second `translate()` call, every string the page's domain could not translate was
*also filed in `default`*, where nothing had ever asked for it. Measured 2026-08-12:
`Books/Francese` filed in 2019, `default/Francese` filed by this method; 360 rows added to
`default` since 2026-08-01 duplicating a row that already existed in a module domain, 284
of them from Symfony-served routes. That is the pathology the phrase-integrity work
removed from the other direction — 7,801 rows down to 2,693 — coming back through the Twig
layer, and it is invisible from any rendered page. The page's domain is still asked with
`translate()`, because that call is what files an unknown phrase *where it belongs*;
`default` is read out of its compiled catalog with `getAllMessages()`, which fires nothing.
So: **a new fallback, of any kind, must be a read.** `test/Integration/PortedRouteTranslationTest`
spies on the event and asserts the set of domains discovered in is exactly the page's own.

The rows already filed are cleaned up with the command that exists for it —
`bin/console jtranslate:retire --origin-route='%.locale' --text-domain=default
--note='…'` — and `--dry-run` first. Retiring is the right verb rather than deleting:
retired phrases still compile into the catalogs, so nothing a visitor sees changes; only
the translator's worklist does. Check before running that every row in the selection
duplicates a non-`default` one, which in the capsule was 231 of 231:

```sql
SELECT COUNT(*) FROM trans_phrases d
 WHERE d.text_domain = 'default' AND d.origin_route LIKE '%.locale'
   AND NOT EXISTS (SELECT 1 FROM trans_phrases o
                    WHERE o.phrase_hash = d.phrase_hash AND o.project = d.project
                      AND o.text_domain <> 'default');   -- must be 0
```

### The MvcEvent helper limitation

`Router` assembles URLs with no MvcEvent, which is the fact the whole layer rests
on: `$bridge->get('Router')->assemble([], ['name' => 'shrines'])` works. The `url`
**view helper** does not — it wants a RouteMatch off the MvcEvent, and a
Symfony-served route has neither. So five helpers, and everything that calls them,
are simply unavailable:

```
url  routeName  localeUrl  libraryInfo  zfcUserDisplayName*
```

`* zfcUserDisplayName` is the exception that proves the rule: it needs only the
authentication service and does work, so it is on the `ViewHelpers` allowlist. The
other four do not, and neither does anything that reaches them — `formatEntity`,
`formatAssociation`, `formatPerson`, `editPencil`, the whole `navigation` family.
The failure is not a clean exception either: it is `Call to a member function
getRouteMatch() on null` from deep inside laminas-view, on a page that is otherwise
rendering fine.

`Laminas\Navigation\Navigation` deserves its own line, because it looks available
and is not: `AbstractNavigationFactory::preparePages()` asks
`$application->getMvcEvent()->getRouteMatch()`, so the *service* cannot be built at
all. `SiteChrome` reads the raw `navigation` config instead, which loses nothing for
the navbar — it renders `minDepth(0)/maxDepth(0)`, so the branches
`Application\Module::onBootstrap()` builds from the database and caches in APCu never
appear there anyway. What it does lose is a top-level item lighting up because a
database-derived descendant is the current page, and the breadcrumb trail, which a
ported page passes in explicitly.

Replacing one of these is a small, mechanical job — reproduce the helper's decisions
against `RouteUrl`, keep its markup byte-identical so a real difference cannot hide
in whitespace noise. `LaminasExtension::editPencil()` is the smallest worked example;
**`formatEntity` is the largest, and it is done** — see below.

### formatEntity, and why its reproduction is split in two

`formatEntity` is the most-reused of the unavailable helpers, so it gated most of the
remaining admin pages. Reproducing it needed one fact that is easy to miss: the name
resolves to **`Schoenstatt\View\Helper\FormatEntity`**, not SionModel's — the
Schoenstatt module registers the same helper name and wins the merge — and that
subclass switches on the entity type *before* deferring:

```
person       -> formatPerson       ─┐ reproduced as macros in
association  -> formatAssociation  ─┘ templates/schoenstatt/_entity-format.html.twig
role         -> its own inline markup            ─┐ reproduced in
publication  -> formatPublication (via SionModel) ─┘ App\Laminas\EntityFormatter
everything else -> SionModel\View\Helper\FormatEntity::__invoke()
                     -> App\Laminas\EntityFormatter
```

**All four special cases are reproduced now** (2026-08-08), which is what made
/sm/view-changes portable. The split is by *whether a Twig macro is involved*, not by
importance: person and association were already macros for the shrine tables, so their
dispatch has to be in Twig; role and publication need no macro and live in the formatter.

The reproduction is therefore in two layers, and the split is forced rather than
chosen: the general path is PHP (`App\Laminas\EntityFormatter`, reachable as the
`format_entity` Twig function), and the *dispatch* is the `entity()` macro in
`_entity-format.html.twig`, because two of its branches are macros and a Twig function
cannot call a macro. `formats_entity_generally()` is what stops the two lists drifting.

Three things to know before using it:

- **`route_permission_checking_enabled` is `true`** in this application, so
  `isActionAllowed()` really runs: a link is suppressed when the viewer may not reach
  its route, and again when the row's `aclResourceIdField` denies them. Both are
  reproduced. Not optional detail — dropping it would show every viewer links to pages
  they cannot open.
- **Two branches of the original are deliberately absent** (`showRouteParams`,
  `editRouteParams`), because no entity spec sets them.
  `test/Integration/EntityFormatterTest` walks all 24 specs and fails the day one does
  — which is how `defaultRouteParams` came to be reproduced: it was on that omitted
  list until the test's first run named text and composition.
- **`role` and `publication` are gated differently on a deleted row**, and this is the
  one place the two originals disagree. `Schoenstatt\View\Helper\FormatEntity` switches
  on the type at the very top of `__invoke()`, before anything reads `isDeleted` — so a
  deleted *role* still gets the role branch, pencil included. SionModel's `__invoke()`
  puts `! $isDeleted` on its `formatViewHelper` deferral — so a deleted *publication*
  falls through to the general path. Getting that backwards left exactly one row of 500
  different from the laminas rendering.
- **The role branch reads different option names.** `editPencil` and `showLabel`, not
  `displayEditPencil`. So `changes-table.phtml`, which passes `displayEditPencil`, does
  **not** turn a role's pencil off. Looks like a typo; is not.
- **Only `display => title` of FormatPublication is reproduced.** The other four modes
  are chosen by an explicit option no page this side passes, and raise rather than
  silently returning a title.

This is also the first port with **no two-sided parity test**, and the reason is worth
remembering: the laminas helper cannot be driven at all without an MvcEvent, so there
is nothing to compare against in-process. The agreement was established once, by hand
— the signed-in laminas rendering of `/en/sm/data-problems` captured before the route
moved, then compared with the ported rendering: 9,206 bytes of table body over 30 real
problem rows, byte-identical. Do that before deleting a guarded page's laminas twin;
after the route moves, the baseline is unobtainable.

## Adding a Symfony route

0. Look up what governs it. `docs/acl-rules.md` gives the guard entry for the laminas
   route; whatever it says, the Symfony route has to declare it (step 3b). A
   restricted guard is no longer a reason not to port — that was true until
   2026-08-06 and is the one line of this file most likely to be remembered wrongly.
1. Write the controller under `src/`, namespace `App\`. `declare(strict_types=1)`
   — `src/` is greenfield and holds itself to a higher standard than the legacy
   baseline: it must pass PHPStan **level 8**, not the committed level 0.
2. Give it a factory in `App\Kernel::container()` if it has dependencies. If it
   needs laminas services or the merged config, inject `App\Laminas\ServiceBridge`
   — it builds a ServiceManager and loads the modules the way `bin/console` does,
   lazily and without `bootstrap()`, so a request that does not ask for it (like
   `/_health`) still pays nothing.
3. Declare the route in `config/symfony/routes.php` **above** `legacy`, through the
   `$ported()` helper, and **name it after the laminas route it shadows**. That name
   is load-bearing: `App\Http\SymfonyRoute::routeName()` strips the `.locale` suffix
   off the prefixed twin, and the layout compares the result against the `navigation`
   config to mark an item active. Get the name wrong and the navbar silently stops
   highlighting the current page.
   An HTML route also has to decide what its *unprefixed* form does. SlmLocale
   redirects `/shrines` to the negotiated language rather than serving the page twice;
   `ShrinesController` reproduces that, and the absence of the `_locale` attribute is
   how it knows.

   Pass the helper an `App\Authorization\RouteAccess` as well — a required argument,
   so this cannot be skipped by accident. Normally
   `RouteAccess::guardedBy('route/<the laminas route this shadows>')`, which makes the
   *same* guard entry govern both front controllers; `openToEveryone('<why>')` only
   where there is genuinely nothing to consult, and the reason goes into
   `docs/acl-rules.md` for review. See "Authorization" above. If the route answers
   JSON, pass `DenialStyle::Json` too, or a refused machine caller gets an HTML
   sign-in page and a 302.
4. Add a smoke test. The suite's other paths all run through the bridge, so they
   will not notice a Symfony-side mistake. Assert something that distinguishes the
   two front controllers, not just a 200 — for the maintenance endpoints that is
   the 401, since the successful payload is identical by design.
   For a restricted route, assert all three outcomes: anonymous, signed in without
   the role, signed in with it. `test/Smoke/MagicLinkSignIn` is the trait that gets
   you a real session; `test/Smoke/AdminAuthorizationSmokeTest` is the worked example.
   A status-code-only test would pass just as well against a guard that never ran.
5. If a laminas route now answers from two places, make them share the code that
   builds the response rather than trusting two copies to stay equal.
   `SionModel\Cache\CacheStatusPayload` exists for exactly that, and
   `test/Integration/CacheStatusParityTest.php` pins the agreement.
   Where sharing would mean *editing* the laminas action — and so putting production
   at risk for the sake of the port — copy it instead and pin the copy with a parity
   test that drives both. `App\Schoenstatt\ShrineIndex` +
   `test/Integration/ShrineIndexParityTest.php` is that pattern; the copy is deleted
   along with the laminas route.

   That licence to copy stops at the laminas boundary. Between two *ported* routes,
   share — the wayside-shrine port (2026-08-07) is the case that established it. Its
   laminas action is a verbatim duplicate of `shrinesAction()` and its `.phtml` a
   verbatim duplicate of `shrines.phtml`, and reproducing that on the Symfony side
   would have been porting the defect along with the feature. Instead
   `App\Schoenstatt\ShrineIndex` already served both, and the template became
   `_shrine-index.html.twig` plus a `shrine_header` block per page. The refactor was
   verified by diffing the rendered `/en/shrines` before and after: byte-identical.
   Do that diff — Twig strips the first newline after every tag, which makes
   whitespace control easy to get wrong in a way no test would notice.
6. Regenerate `docs/acl-rules.md` and `docs/acl-baseline.json`
   (`tools/acl-table.php`) and read the diff.

## Verifying a port against production, across every locale

The technique that found the translation defect, and the one to reach for before
trusting any port. It exists because two cheaper checks are both insufficient:

- **The capsule cannot compare front controllers.** Its vhost sets
  `SYMFONY_KERNEL=1` unconditionally, and `SetEnv` beats `SetEnvIf`, so everything there
  is Symfony-served. A capsule-only "before and after" compares Symfony with Symfony.
- **English proves almost nothing.** The batch-3 ports were diffed byte-for-byte on
  `/en/…` and passed, while all four other languages were rendering English source text.
  In English a missing translation *is* the source string, so the defect showed up as a
  capitalisation — `Schoenstatt Shrine` → `Schoenstatt shrine` — that nobody would look
  at twice.

So: drive **both** front controllers over **all five locales**.

Locally, `public/.htaccess` overrides the vhost (`AllowOverride All`), and `SetEnv` beats
`SetEnvIf`, so appending one line forces laminas:

```apache
SetEnv SYMFONY_KERNEL 0     # temporary; capture the baseline, then remove
```

Capture every ported path × `en es de pt it`, remove the line, capture again, and diff.
Two classes of difference are expected and benign, and a comparison that does not
normalise them will drown in noise:

- **Guarded routes' 302 bodies.** laminas renders the *entire sign-in page* into the body
  of its 302 (8,957 bytes); Symfony's `RedirectResponse` sends a 378-byte meta-refresh
  stub. Same status, same `Location`, and nothing reads a 302 body. Compare status and
  `Location`, not the body.
- **HTML entities in translated text.** Twig escapes a `"` inside a translated string to
  `&quot;`; the laminas `.phtml` echoes it raw. Identical in a browser, and Twig's is the
  safer of the two. Unescape before comparing.

### `tools/port-baseline.php` does all of that

Since 2026-08-08 the procedure is a tool rather than a set of instructions:

```
# append `SetEnv SYMFONY_KERNEL 0` to public/.htaccess
docker compose exec -T app php tools/port-baseline.php capture laminas
# remove that line again
docker compose exec -T app php tools/port-baseline.php capture symfony
docker compose exec -T app php tools/port-baseline.php compare laminas symfony
```

It fetches every path in `PATHS` × 6 locale forms × 2 identities — **anonymous and an
account holding every role** — and stores the raw responses; normalization happens at
compare time, so changing a rule never costs another capture (and taking the laminas one
means editing `public/.htaccess`). Read its docblock before trusting a result: the seven
normalization rules are listed there with the reason each is not cheating.

**It corrects the claim this file used to make.** "65 of 65 responses identical" was
wrong as stated: whole documents were never identical and cannot be, because
`templates/layout.html.twig` is a *reproduction* of `layout.phtml` and not a byte copy —
different indentation, different `<head>` order, a different JSON-LD encoder, an added
`aria-current`. Measured 2026-08-08 on batch-3 pages before this batch touched anything.
Two further things differ between any two runs of the *same* front controller and have
to go before anything can be compared at all: the language chooser draws its flag at
**random**, and `SionTable::registerVisit()` bumps a counter the page then prints.

So the comparison is scoped to what porting a page owns — `<title>`, the breadcrumb
trail, the navbar (including which item is active), the flash region and the whole page
body — and is whitespace-insensitive between tags. On that basis batch 4 came to **257 of
300 responses identical** while it still included the blog; the differences that remain
are itemized under "Known differences" below.

On production the same comparison is available without any file edit, through the cookie
canary above — and that is where it should be repeated, because production has
translations, ICU 72.1 and five years more data than the capsule dump.

### Known differences, and why each one stays

Every remaining difference from the batch-4 run falls into one of five groups. None is a
defect in a page ported by this batch; three are improvements and two are older.

| what | where | why |
|---|---|---|
| a commented-out `<td>`, a stray space before a `<p>`, and a missing `//<!-- -->` script wrapper | `/shrines`, `/wayside-shrines` (20 responses) | batch-2 template nits, invisible in a browser. Left alone: they are shipped code and this batch has no business editing it |
| `?redirect=/roles` vs `?redirect=/en/roles` | unprefixed form of a guarded path (5) | the redirect-order divergence already documented above, now visible on five routes |
| — | | |

#### A note on that FINISH listener

The hazard it used to carry: `TranslationsTable::flush()` writes collected missing phrases
on `MvcEvent::FINISH`, and the `phrase` column used to be a `varchar(2000)` that silently
truncated. A laminas page passing a long value through `translate()` could therefore throw
*after* its response was assembled, and the visitor got a 200 with an empty body — which is
what blanked every blog post in four locales. The blog is gone (see history.md), and the
column became `text` with the phrase-integrity work deployed 2026-08-10, so the write now
needs a >64 KB string to fail rather than a >2 KB one.

That is why the Symfony side could finally be wired up, and it is wired **on
`KernelEvents::TERMINATE`, not `RESPONSE`**: terminate runs after the response has been
sent, so a throw there cannot take the page with it. The laminas listener still has the
original placement and the original exposure. Bounding the write is still the real fix.

## What the baseline diff catches that nothing else does

Batch 5 (the reading surface, 2026-08-12) is the sharpest evidence so far for running
`tools/port-baseline.php` rather than trusting a page that renders. **Six defects came out
of that diff and not one of them would have failed a status-code test:**

| what was wrong | how it looked |
|---|---|
| `EntityShow` called `getObject()` where the laminas controller overrides it | association page missing four panels — 7 KB smaller, one `panel-title` short |
| the comment form's `redirect` field was never populated | thirteen missing bytes in a hidden input; every posted comment would have followed the referer instead |
| `FormatField` had no text domain | the whole bibliographic panel in English on `/es/…`, identical to laminas on `/en/…` |
| a breadcrumb leaf passed without `href` | **every** `/literature/{lang}` page an empty 200 |
| `SiteChrome::NAV_ROUTE` declared on two show pages | a stray ` class="active"` — 15 bytes |
| a multiple `<select>` rendered without `[]` on its name | the search box would have submitted one language where the visitor picked three |

Two of those are the "English proves almost nothing" trap this document already records
from batch 3, and one is the fatal-200 wedge. The tool grew a rule in the process: the
**CSRF token** is per-run by construction and had never been normalized, because no ported
page carried a form until `association-edit` and no ported page carried one a *signed-in*
visitor sees until this batch.

**And it caught a deviation that was defensible and still wrong to make.** The three
pre-2020 redirects were written to skip SlmLocale's locale hop — `/associations/1`
answering 301 straight to the destination rather than 302 to `/en/associations/1` first —
on the reasoning that a redirect to a redirect is wasted. Same destination, one hop fewer,
and a change to URLs search engines have indexed for five years. A port is not where that
decision belongs.

## Routes that are not portable yet, and why

A route can be blocked by something that has nothing to do with the strangler. Recording
those here saves the next porter the rediscovery.

### `sion-model/view-changes` (`/sm/view-changes`) — fixed 2026-08-07, ported 2026-08-08

**The page was dead** until 2026-08-07 and nobody noticed, because it sits behind
`sch_general_moderator`:

```
Fatal error: Allowed memory size of 536870912 bytes exhausted
  in vendor/laminas/laminas-db/src/Adapter/Driver/Pdo/Result.php on line 175
```

Two bugs in `SionModel`, both now fixed:

1. **`SionTable::getChanges()` filtered by the wrong name.** It asked for the changed
   entities by their database column (`TextId`) where `queryObjects()` matches entity
   *field* names (`textId`) — a mismatch it answers by `continue`-ing past the
   predicate, so the query came back **unfiltered**. It loaded all 2,757 rows of a
   table averaging 85 KB a row instead of the 250 that had changed. That single hop
   was 520 MB; it is 66 MB now. `SionTable::entityFieldForTableKey()` is the fix.
2. **`ChangesCollector::getAllChanges()` took no limit at all**, so `changes_max_rows`
   bounded only the display, and `viewChangesAction()`'s single-table branch passed the
   `changes_show_all` *flag* where an int row count belonged — `limit(false)`. One
   number governs fetch and display now, set explicitly as
   `sion_model.changes_max_rows` (500).

Measured after the fix: 200, 211 KB, 500 rows, 1.96 s, and a 214 MB peak for the
collector at 500 rows per table — against a 512 MB limit. 1000 per table would be
352 MB, so 500 is the number with headroom rather than an arbitrary one.
`test/Smoke/ViewChangesSmokeTest` guards all of it.

**Then it was ported**, once `App\Laminas\EntityFormatter` stopped refusing
`publication` and `role`: the entity column formats whatever type each change row names,
and `sch_changes` holds 18,243 and 1,317 of them.

Verifying it needed a trick worth reusing. The newest 500 changes in the capsule dump are
*all* `book` rows, so the default view exercises only the general path and would have
proved nothing about the two new branches. Pointing `changes_model` at
`PublicationsTable` and then `SchoenstattTable` — with `changes_show_all` off — makes the
page render those types instead, and all three renderings were captured from laminas
before the route moved and compared byte for byte against the ported ones:

| view | laminas bytes | result |
|---|---|---|
| default, all tables (500 rows, 13 date groups) | 202,411 | identical |
| `changes_model = PublicationsTable` (453 hand-checked, 88 data-source, 86 merged icons) | — | identical |
| `changes_model = SchoenstattTable` (roles with labels, associations, persons) | — | identical |

Remember to put `changes_show_all`/`changes_model` back, and to clear `data/config/`
either way — the merged config is cached there and an edit looks like it did nothing.

`sion-model/auto-fix-data-problems` remains unported: it is POST-and-CSRF. The form layer
it was waiting for now exists (`src/Form/BootstrapFormRenderer`), so it is a candidate
for the next batch rather than a blocked one.

### `sitemap` (`/sitemap.xml`) — blocked on `Laminas\Navigation`

`IndexController::sitemapAction()` walks the **Navigation service** with a
`RecursiveIteratorIterator` and asks the `navigation()->sitemap()` view helper for each
page's URL. Both are on the unavailable list for the same reason: the service's factory
calls `$application->getMvcEvent()->getRouteMatch()`, so it cannot be *built* on a
Symfony-served route, let alone rendered.

Porting it therefore means reproducing the navigation tree itself, including the six
database-derived branches `Application\Module::onBootstrap()` builds and caches in APCu —
which is the same work as replacing `SiteChrome`'s config-only navigation with a real
one. Worth doing once, for both; not worth doing for one route.

### The form routes — one obstacle, not two, and the first one is gone

This section used to say the form routes were blocked on two things. Both claims needed
correcting when `association-edit` was ported on 2026-08-09.

**The missing form layer was real, and is now `src/Form/BootstrapFormRenderer`.** It
reproduces `SionModel\Form\View\Helper\SionFormRow` — i.e. TwbBundle's Bootstrap 3
markup — closely enough that the ported `/en/SL100319A/edit` and the laminas rendering of
the same URL are identical except for whitespace between tags. That was verified by
capturing the live page with `tools/form-regression.php probe` before the port and
diffing after; five things had to be reproduced exactly, and each is documented in that
class: attribute order, `FormSelect`'s attribute whitelist, TwbBundle's
escape-only-if-no-tags help-block rule, per-select option translation, and the submit
button's classes.

**The static-adapter claim was too broad.** `GlobalAdapterFeature::setStaticAdapter()` is
read by `CreateRoleForm`, `EditUserForm`, `DeleteUserForm` and `EditPhraseForm`, through a
`NoRecordExists` validator — and by nothing else. `AssociationForm` and `SionForm` never
touch it. So it blocks the **user and translation** forms and does not block the rest;
`association-edit` moved without it being dealt with.

What a form route still needs, and what `association-edit` establishes the pattern for:
the form comes from the laminas container through the `ServiceBridge` (so its value
options and its validation are the application's, not a copy), CSRF works because
`App\Http\SessionListener` has already started the laminas session — a token minted by
either front controller is accepted by the other — and the write goes through
`SionTable::updateEntity()` exactly as `SionController` does it.

Still on laminas: `/movement`, `/persons`, `/texts`, the user and translation forms (the
static adapter), and every other create/edit/delete page. `/literature`'s search form is
ported — see below.

### The comment form — unblocked 2026-08-12, and it was blocking one entity more than this said

This section used to say `composition` and `text` were blocked on the comment form:
`SionController::showAction()` builds a `CommentForm` for any entity with a comment
predicate, and the template renders it for a signed-in visitor who may comment.

**That list was one entity short.** Which entities take comments is *data*, not
configuration — `SELECT PredicateKind FROM predicates WHERE SubjectEntityKind='comment'`
answers five rows, and one of them is `comment-reviews-publication`. A port written from
this document rather than from the table would have shipped a publication page that
silently stopped accepting reviews. `App\Sion\CommentPredicates` asks the table.

`App\Controller\CommentCreateController` is the port. Two things about it are worth
knowing before touching it:

- **It is POST-only, and a GET is still a 500.** The laminas route has no method
  constraint, so a GET reaches a view whose template does not exist —
  `sion-model/comment/create` singular against a `comments/create` partial — and throws.
  Measured before porting: 11,953 bytes of exception page. The Symfony route claims POST
  alone, and a GET then falls through the catch-all to laminas and stays exactly as broken
  as it was. It is **not** a 405: `UrlMatcher` always finds `legacy`, so MethodNotAllowed
  is never raised.
- **The open redirect is reproduced, not introduced.** `redirectAfterCreate()` redirects
  to an unvalidated hidden field, with its own `@todo` over it. CSRF and the `user` guard
  narrow it; it is recorded in BACKLOG.

### `composition`, `text`, `publication`, `association` — ported 2026-08-12

All four show pages now run on `App\Sion\EntityShow`, one reproduction of
`SionController::showAction()`. The thing that reproduction got wrong, and that no test
would have caught, is worth repeating here: **two of the twelve controllers inheriting
`showAction()` override `getEntityObject()`**, and `AssociationsController` is one of
them. `getObject('association', …)` falls through to `tryGettingObject()`, a single
unlinked `SELECT`, because the entity spec's `get_object_function` is commented out;
`getAssociation()` goes through `linkAssociations()`, which is what attaches
`childAssociations`, `parent`, `roles` and `assignments`. A shared reproduction calling
`getObject()` renders an association page missing four panels and renders it happily.
`load()` takes a loader hook for exactly this.

**A note on the fetch/display ratio, for whoever tunes this next.** With
`changes_show_all` on, the limit applies *per table* — 6 tables × 500 = 3,000 rows
hydrated so the view can show the newest 500. That is correct rather than wasteful in
the strict sense (which table's rows win is unknown until they are merged), but a
two-pass collector — merge the change rows first, hydrate only the survivors — would cut
it by ~6×. Not done: 214 MB is comfortable, and the refactor touches `SionTable`.

## Verifying

- `php composer.phar test` — **916 tests** (measured 2026-08-09, after the flip
  preparation; 791 after the eight routes of
  batch 4 and the removal of the blog; 746 after the translator fix, 729 after batch 3 plus the view-changes repair,
  631 after the wayside-shrine port, 618 after the authorization bridge, 551 before it,
  527 before the shrines port).

  That script now passes `-d memory_limit=1G`, as `composer fuzz` always has. Without
  it the *combined* run exhausts 512M in `Books\Form\Publication`'s factory, which
  loads every publication row to build its keyword options — the earlier suites' memory
  is still held by then. It failed identically on master before this batch; the fuzz
  suite alone passes either way. The smoke suite
  runs against the capsule, i.e. through the Symfony front controller, so it is the
  bridge's regression test. `test/Smoke/SymfonyKernelSmokeTest.php` covers what the
  catch-all would hide: that Symfony served anything itself.
- `test/Smoke/AdminAuthorizationSmokeTest.php` is the authorization bridge's proof:
  the three access outcomes on `/en/admin`, measured against the laminas rendering of
  the same URL first. It also re-asserts that the four earlier ports still answer as
  they did, since every one of them now passes through the new check.
- `test/Integration/SymfonyRouteAuthorizationTest.php` walks
  `config/symfony/routes.php` and fails on a route that declares no authorization, on
  a declared resource no guard entry defines, and on a route declared open whose
  laminas guard restricts access. It is the un-skippable half: the runtime exception
  only fires when someone requests the route.
- `test/Integration/RouteDenialShapeTest.php` pins the four refusal shapes without a
  container — which is the only coverage the JSON pair has until a guarded JSON route
  exists.
- `test/Smoke/ShrinesSymfonySmokeTest.php` does the same job for the first HTML
  route, and its discriminator is worth reusing: laminas sends
  `Set-Cookie: slm_locale=en_US` on every response and a ported route never does, so
  its absence proves Symfony served the page rather than bridging it.
- `test/Smoke/WaysideShrinesSymfonySmokeTest.php` covers the second HTML route, and
  most of it is about the *shared* template: that neither page wears the other's
  introduction and that both still render the body they have in common. The
  mechanism assertions are not repeated there — they belong to the kernel's
  listeners, not to a route.
- `test/Integration/ShrineTemplateTest.php` renders the templates against real rows
  with no HTTP, which is the only way the *moderator* markup is exercised — the smoke
  suite has no authenticated session. Both indexes, both tables, since the wayside
  rows are a different 43 associations through the same projection and
  `strict_variables` turns an absent column into an exception.
- `test/Integration/SymfonyLocaleAliasTest.php` guards `App\Locale\Locales` against
  drifting from `slm_locale`. The alias table is duplicated on purpose: reading the
  merged config from `config/symfony/routes.php` would load every laminas module
  before the first route existed, and `/_health` would start paying for it.
- `test/Integration/LaminasResponseConverterTest.php` pins the four conversion
  rules above. None of them are visible to a status-code assertion. Three of the four are
  additionally checked against production on a bridged page by `tools/smoke-prod.sh`,
  which is where the TLS-terminating proxy the capsule lacks is in the path.
- `test/Integration/PortedRouteErrorReportingTest.php` invokes the resolver
  `App\Kernel::handle()` installs and asserts it produces the *real* `ErrorHandling` and
  `RequestContext`, not null. It exists because the failure it guards is doubly silent:
  a ported route whose failures are recorded but never notified looks exactly like one
  whose failures never happen, and the resolver swallows its own exceptions by design.
  It caught a plausible-looking `SionModel\Error\ErrorHandling` import — the class lives
  in `SionModel\Service` — that had degraded the whole thing back to the container-free
  path.
- `test/Integration/CacheStatusParityTest.php` pins that the ported
  `/sm/cache-status` and the laminas action it coexists with describe the same JSON
  document, and that the two JSON encoders involved agree on the bytes. It compares
  the two *controllers* in one process, not two live front controllers: the capsule
  serves only the Symfony kernel, so no single URL can exercise both in one run —
  the test's docblock is explicit about that limit rather than implying a stronger
  claim.
- `test/Smoke/Batch4SymfonySmokeTest.php` covers the seven public routes of batch 4 —
  each one served by Symfony rather than bridged, each unprefixed form redirecting, and
  the dictionary's two edge cases.
- `test/Smoke/RestrictedIndexAuthorizationSmokeTest.php` covers the three restricted
  indexes, and is the first authorization test in the suite with a *positive* case for an
  ordinary account: registration grants `sch_user`, which `route/associations` names, so
  that page is where "the guard runs" and "the guard says yes to the right people" can be
  told apart. Every other one asserts a refusal and would pass against a guard that
  refused everybody.
- `test/Integration/EventTimelineParityTest.php` drives
  `EventsController::groupEventsByEpochAndYear()` and `App\Books\EventTimeline::group()`
  over the same rows and compares the *shape* — period order, year order, event keys —
  because comparing the rows themselves compares object identity and proves nothing.
- Audit new code at a real level:
  `phpstan analyse src --level 8` (config in `phpstan.neon.dist` stays at 0 for
  the legacy tree — see the level-ladder measurement in BACKLOG.md).
