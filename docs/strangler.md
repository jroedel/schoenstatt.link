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
                                       └─ /{path} .*     → App\Http\LegacyBridge
                                                              └─ Laminas\Mvc\Application
```

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

`symfony/framework-bundle` cannot be installed here today:

```
symfony/framework-bundle          → symfony/cache → psr/cache ^2|^3
laminas/laminas-cache 3.14        → psr/cache ^1          ← the pin
laminas/laminas-cache 4.3         → psr/cache ^2|^3       ← lifts it
kokspflanze/bjy-authorize 2.4.4   → laminas-cache ^2.13.2 || ^3.1.0   ← caps it back
```

2.4.4 is the final release of that line, so no upstream fix is coming. The bundle
is gated behind retiring bjy-authorize (the Security-component migration) or
forking its `composer.json`. `symfony/http-kernel` and `symfony/routing` need no
`psr/cache` at all, which is why the kernel is hand-wired from components
instead.

When that gate opens, `App\Kernel` is what FrameworkBundle replaces. The routes
and everything behind them carry over unchanged, because `LegacyBridge` and
`HealthController` are plain callables and `App\Container` is only a PSR-11
container — which is all `ContainerControllerResolver` ever asks for.

## Adding a Symfony route

1. Write the controller under `src/`, namespace `App\`. `declare(strict_types=1)`
   — `src/` is greenfield and holds itself to a higher standard than the legacy
   baseline: it must pass PHPStan **level 8**, not the committed level 0.
2. Give it a factory in `App\Kernel::container()` if it has dependencies.
3. Declare the route in `config/symfony/routes.php` **above** `legacy`.
4. Add a smoke test. The suite's other 77 paths all run through the bridge, so
   they will not notice a Symfony-side mistake.

## Verifying

- `php composer.phar test` — 368 tests. The smoke suite runs against the capsule,
  i.e. through the Symfony front controller, so it is the bridge's regression
  test. `test/Smoke/SymfonyKernelSmokeTest.php` covers what the catch-all would
  hide: that Symfony served anything itself.
- `test/Integration/LaminasResponseConverterTest.php` pins the four conversion
  rules above. None of them are visible to a status-code assertion.
- Audit new code at a real level:
  `phpstan analyse src --level 8` (config in `phpstan.neon.dist` stays at 0 for
  the legacy tree — see the level-ladder measurement in BACKLOG.md).
