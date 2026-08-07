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
| capsule | `docker/apache-vhost.conf` (committed, baked into the image) | `1` |
| production | `public/.htaccess` (untracked, server-side) | not set → `0` |

So **the capsule runs the Symfony front controller and production does not.**
That is the whole point of the gate: reverting production is a `SetEnv` edit and
an Apache reload rather than a phploy deploy, which matters while phploy still
has its mid-deploy broken window. To A/B locally, edit
`docker/apache-vhost.conf`, then `docker compose build && docker compose up -d`
(the vhost is `COPY`d into the image, not mounted).

To flip production on, add to `public/.htaccess` next to the existing
`SetEnv "APP_ENV" "production"`:

```apache
SetEnv "SYMFONY_KERNEL" "1"
```

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
declaration of its own (the maintenance endpoints must *not* redirect). As of the
wayside-shrine port there are **three** copies of that redirect —
`ShrinesController`, `AdminController`, `WaysideShrinesController` — which is the
threshold BACKLOG.md set for doing it.

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

Three settings in `TwigFactory` are decisions, each with its reasoning in the
class docblock: `strict_variables` is **on** (the opposite of `PhpRenderer`),
`autoescape` is on with markup-returning functions declared `is_safe: html`, and the
compile cache is used only when its directory is writable, with `auto_reload` on
because Twig keys a compiled file by the template's *name*, not its contents — with
`auto_reload` off, an edited or deployed template is never recompiled.

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
role         -> its own inline markup            ─┐ not reproduced;
publication  -> formatPublication (via SionModel) ─┘ format_entity() raises for both
everything else -> SionModel\View\Helper\FormatEntity::__invoke()
                     -> App\Laminas\EntityFormatter
```

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
  list until the test's first run named blog-post, text and composition.
- **`role` and `publication` raise rather than render.** Formatting them by the general
  rules would produce plausible, wrong markup on an admin page; a `LogicException`
  naming the type sends the next porter to the helper they need. Neither is reachable
  from `/sm/data-problems` — `problem_specifications` names only book, collection,
  library, association and person.

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

## Routes that are not portable yet, and why

A route can be blocked by something that has nothing to do with the strangler. Recording
those here saves the next porter the rediscovery.

### `sion-model/view-changes` (`/sm/view-changes`) — fixed, still not ported

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

**It is still not ported, and the remaining blocker is a different one.** The page
formats each change's entity through `formatEntity`, and the entity type comes from the
data — `sch_changes` holds 18,243 `publication` rows and 1,317 `role` rows, which are
exactly the two types `App\Laminas\EntityFormatter` refuses (see above). Porting it
means reproducing `Books\View\Helper\FormatPublication` and the `role` branch of
`Schoenstatt\View\Helper\FormatEntity` first. That is a bounded, mechanical job, and it
is the next thing to do if this page is wanted on the Symfony side.

Its sibling `sion-model/auto-fix-data-problems` is a separate matter again: it is
POST-and-CSRF, and there is no form layer on the Symfony side yet.

**A note on the fetch/display ratio, for whoever tunes this next.** With
`changes_show_all` on, the limit applies *per table* — 6 tables × 500 = 3,000 rows
hydrated so the view can show the newest 500. That is correct rather than wasteful in
the strict sense (which table's rows win is unknown until they are merged), but a
two-pass collector — merge the change rows first, hydrate only the survivors — would cut
it by ~6×. Not done: 214 MB is comfortable, and the refactor touches `SionTable`.

## Verifying

- `php composer.phar test` — 729 tests (measured 2026-08-07, after this batch of nine
  ported routes plus the view-changes repair; 631 after the wayside-shrine port, 618
  after the authorization bridge, 551 before it, 527 before the shrines port). Per
  suite: unit 119, integration 426, fuzz 18, smoke 166.

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
  rules above. None of them are visible to a status-code assertion.
- `test/Integration/CacheStatusParityTest.php` pins that the ported
  `/sm/cache-status` and the laminas action it coexists with describe the same JSON
  document, and that the two JSON encoders involved agree on the bytes. It compares
  the two *controllers* in one process, not two live front controllers: the capsule
  serves only the Symfony kernel, so no single URL can exercise both in one run —
  the test's docblock is explicit about that limit rather than implying a stronger
  claim.
- Audit new code at a real level:
  `phpstan analyse src --level 8` (config in `phpstan.neon.dist` stays at 0 for
  the legacy tree — see the level-ladder measurement in BACKLOG.md).
