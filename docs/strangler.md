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
                                       └─ /{path} .*     → App\Http\LegacyBridge
                                                              └─ Laminas\Mvc\Application
```

Four kernel listeners run on a Symfony-served route and on **no** bridged one,
because on a bridged one laminas-mvc or `LaminasResponseConverter` has already done
the equivalent. `App\Http\SymfonyRoute::isPorted()` is how each of them tells,
by asking whether `_route` is anything other than `legacy`:

| listener | event | what it restores |
|---|---|---|
| `LocaleListener` | request | `\Locale::setDefault()` from `_locale`, else negotiated — SlmLocale's job |
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
absent: **no BjyAuthorize route guard, no ACL, no identity, no SlmLocale, no
session, no laminas-view layout, and none of the response headers the MVC
listeners add** (the `Content-Security-Policy` from `SionModel\Mvc\CspListener`,
the session cookie, the GDPR strategy's cookie stripping). What *is* still there
comes from Apache: HSTS, `X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`.

That is why the first two routes ported were maintenance endpoints whose
protection already lived in the controller rather than in the guard. Porting a
route that relies on the guard means moving the check with it — and
`tools/acl-table.php` says so out loud: it lists Symfony-served routes in their
own section, reports which laminas routes they shadow, and *warns* when a
shadowed route's guard restricted anything, because that is authorization
silently ceasing to apply with nothing else to notice it.

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

So the list of things a ported route loses, restated: the **route guard**, SlmLocale,
the laminas-view layout, and (until a listener restores them) the response headers
the MVC listeners add. Not the ACL, not the identity, not the session.

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

## The Twig layer

HTML pages on the Symfony side render with Twig, from `templates/`. It exists
because rendering the existing `.phtml` from a Symfony-served route is not merely
inadvisable, it is impossible — see the helper limitation below. What is there now,
and what a later port should reuse rather than reinvent:

| file | what it is |
|---|---|
| `templates/layout.html.twig` | the site chrome. `{% extends %}` it, define `page_title`, `breadcrumbs`, `block content`, `block inline_scripts` |
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
in whitespace noise. `LaminasExtension::editPencil()` is the worked example.

## Adding a Symfony route

0. Check the route is portable at all. `docs/acl-rules.md` says whether its guard is
   public; if it is not, the check has to move with it, and nothing here does that
   yet.
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
4. Add a smoke test. The suite's other paths all run through the bridge, so they
   will not notice a Symfony-side mistake. Assert something that distinguishes the
   two front controllers, not just a 200 — for the maintenance endpoints that is
   the 401, since the successful payload is identical by design.
5. If a laminas route now answers from two places, make them share the code that
   builds the response rather than trusting two copies to stay equal.
   `SionModel\Cache\CacheStatusPayload` exists for exactly that, and
   `test/Integration/CacheStatusParityTest.php` pins the agreement.
   Where sharing would mean *editing* the laminas action — and so putting production
   at risk for the sake of the port — copy it instead and pin the copy with a parity
   test that drives both. `App\Schoenstatt\ShrineIndex` +
   `test/Integration/ShrineIndexParityTest.php` is that pattern; the copy is deleted
   along with the laminas route.
6. Regenerate `docs/acl-rules.md` and `docs/acl-baseline.json`
   (`tools/acl-table.php`) and read the diff.

## Verifying

- `php composer.phar test` — 551 tests (measured 2026-08-05, after the shrines port;
  527 before it). The smoke suite runs against the capsule, i.e.
  through the Symfony front controller, so it is the bridge's regression test.
  `test/Smoke/SymfonyKernelSmokeTest.php` covers what the catch-all would hide:
  that Symfony served anything itself.
- `test/Smoke/ShrinesSymfonySmokeTest.php` does the same job for the first HTML
  route, and its discriminator is worth reusing: laminas sends
  `Set-Cookie: slm_locale=en_US` on every response and a ported route never does, so
  its absence proves Symfony served the page rather than bridging it.
- `test/Integration/ShrineTemplateTest.php` renders the templates against real rows
  with no HTTP, which is the only way the *moderator* markup is exercised — the smoke
  suite has no authenticated session.
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
