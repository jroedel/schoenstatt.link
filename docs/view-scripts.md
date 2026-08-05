# Migrating view scripts

A working record for moving `.phtml` view scripts off laminas-view and onto Twig
under the Symfony kernel. Everything here was measured in the capsule rather than
reasoned about, and the point of the file is to keep it measured: **add what you
learn to the log at the bottom as you port pages**, especially the things that
cost you an hour.

Companion to [strangler.md](strangler.md), which covers the front-controller
split and how a Symfony *route* is added. This file is about the *view*, which is
the harder half and moves on its own schedule.

## Porting is two independent moves

A route and its view script are separate migrations, and conflating them is the
main way this work goes wrong:

1. **The route** moves when its path is declared above the `legacy` catch-all in
   `config/symfony/routes.php` and a controller under `src/` answers it.
2. **The view** moves when its `.phtml` becomes a Twig template.

You cannot do (2) without (1) — laminas-view is what renders inside
laminas-mvc. But you *can* do (1) without (2) for anything that returns JSON,
which is why the maintenance endpoints ported before any HTML page did.

## The thing to internalise first

**A Symfony-served route never boots laminas-mvc.** `App\Http\LegacyBridge` is
what calls `Application::init()`, so a path matched above the catch-all skips it
entirely — and with it every `MvcEvent` listener the application relies on:

| Not running | Consequence for a ported page |
|---|---|
| `BjyAuthorize\Guard\Route` | **No authorization whatsoever.** See "What can move today". |
| `SlmLocale\Strategy\UriPathStrategy` | No locale detection, no `/en` stripping, no redirect. See "The locale trap". |
| `Laminas\Mvc\Application`'s route/dispatch events | Five view helpers stop working. See the inventory. |
| `JUser\Module::onBootstrap()` session start | No session, so no identity |
| `Application\View\GdprStrategy`, `SionModel\Mvc\CspListener` | Cookie-consent and CSP header handling do not happen |
| laminas-view layout | No site chrome unless the Twig layout provides it |

None of this is a defect in the bridge. It is what "leaving laminas-mvc" means,
and the work of porting a page is largely the work of deciding, per item, whether
to reproduce it, replace it, or do without.

## What can move today

**Only public routes.** Because the guard does not run, porting a guarded route
silently removes its protection — the worst possible failure, since the page keeps
working and simply admits everyone.

`tools/acl-table.php` is the check. It reports Symfony-served routes in their own
section ("nothing in this file applies to them") and lists laminas routes now
shadowed by one, with the guard that is no longer enforced. A shadowed route whose
guard was **public** is a no-change; anything else is a real authorization change
and the tool warns. Regenerate `docs/acl-rules.md` and `docs/acl-baseline.json`
after every port and read the diff.

Of 191 routes, **166 carry a guard entry and 149 of those actually restrict
access** — the other 17 are guarded but public (a `null` role means everyone), and
those are the ones that can move. So the ceiling is a hard 149 pages, not a
formality. Lifting it means building an authorization bridge, which is the gate on
any port beyond the public pages. Recompute rather than trusting these numbers
once a few more routes have moved:

```sh
docker compose exec -T app php tools/acl-table.php --format=json \
  | python3 -c "import json,sys; g=json.load(sys.stdin)['guarded_routes']; \
p=sum(1 for r in g.values() if r.get('public')); print(len(g),'guarded,',p,'public,',len(g)-p,'restricting')"
```

## The view-helper inventory

Measured against a container built the way `App\Laminas\ServiceBridge` builds
one: modules loaded, `bootstrap()` never called.

**Available** — these resolve and work with no MvcEvent:

`basePath` · `doctype` · `escapeHtml` · `flashMessenger` · `headLink` ·
`headMeta` · `headScript` · `headTitle` · `inlineScript` · `isAllowed` ·
`navigation` · `nowMessenger` · `partial` · `requestUri` · `translate`

Also resolvable as services: `ViewRenderer`, `ViewHelperManager`,
`MvcTranslator`, `Navigation`, `BjyAuthorize\Service\Authorize`, `Router`.

**Unavailable** — these reach `getMvcEvent()->getRouteMatch()` and die with
`Call to a member function getRouteMatch() on null`:

`url` · `routeName` · `localeUrl` · `libraryInfo` · `zfcUserDisplayName`

That list is why an existing `.phtml` cannot simply be handed to the renderer
from the Symfony side: `url()` appears in nearly every template.

## URL generation is the thing that makes this possible

The laminas **Router assembles URLs with no MvcEvent at all**:

```php
$bridge->get('Router')->assemble([], ['name' => 'shrines']);   // → /shrines
```

This is the load-bearing fact of the whole approach. A ported page still has to
link to the ~190 pages that have *not* moved, and those are laminas routes that
Symfony's router knows nothing about. Assembling them directly is what lets a
Twig template link correctly into the un-ported site.

Two things to remember when you use it:

- Missing route parameters throw — `associations/association` needs `sw_id`, so
  assemble with the params the route actually declares, not the ones the page
  happens to have.
- Laminas route paths carry **no locale prefix**, because `UriPathStrategy`
  strips it before routing. `assemble()` therefore returns `/shrines`, not
  `/en/shrines`. If a link needs the prefix, add it deliberately.

## The locale trap

`Locale::getDefault()` in a Symfony-served request is **`en_US_POSIX`**, because
SlmLocale never ran. This is not cosmetic. `Schoenstatt\Model\SchoenstattTable`
indexes arrays by locale — `$v['nameByLocale'][$locale]` around line 2800 — so an
unrecognised locale produces a stream of `Undefined array key` warnings and wrong
or empty data, while still returning a page.

**Every ported controller must set the locale before touching a table.** Map the
route's `_locale` through the aliases in `config/autoload/juser.global.php`
(`slm_locale`): `en→en_US`, `es→es_ES`, `pt→pt_BR`, `de→de_DE`, `it→it_IT`,
default `en_US`. With `en_US` set, `getShrines()` returns its 207 rows cleanly.

Declare routes so the prefix keeps working — a bare path plus a
`/{_locale}/…` variant constrained to `en|es|de|pt|it`. Constraining it matters:
it is what leaves `/xx/shrines` falling through to laminas instead of being
claimed and mishandled.

## Template naming, and a resolver gotcha worth knowing

Laminas's implicit template name is `<module>/<controller>/<action>`, all
dash-separated — so `SchoenstattController::shrinesAction()` renders
`schoenstatt/schoenstatt/shrines`, **not** the directory the file happens to sit
in. Getting this wrong costs a confusing "resolver could not resolve to a file".

Related, and the more expensive trap: a bare `partial('fields-partial')` — used
by a dozen create/edit templates — resolves against the **calling template's own
directory**, via `Laminas\View\Resolver\RelativeFallbackResolver`. That resolver
needs the renderer, so it only works *during a render*. Asking
`ViewResolver->resolve('fields-partial')` outside one returns `false`, which
looks like proof that every one of those templates is broken. They are not.
Do not "fix" them on that evidence.

When writing a Twig template that reuses a partial belonging to another
directory, name it in full rather than relying on relative resolution.

## Escaping

`.phtml` did not autoescape and called `escapeHtml()` by hand. **Twig
autoescapes.** Piping an already-escaped string through Twig double-escapes it,
which shows up as visible `&amp;lt;` in the page rather than as an error. When
you move a template, remove the manual escaping rather than keeping both, and
check any value that arrives from a laminas view helper — some return escaped
markup by design.

## Verifying a port

Compare **content, not bytes.** Byte comparison is useless here for two reasons,
both deliberate:

- The language chooser picks its flag with `Rand::getInteger()` on every request
  (`us`/`gb`, `pt`/`br`, …) — whimsy, not a bug. Keep it.
- The CSP nonce is random per request, and its HTML-escaped length varies.

So compare the substance: the set of records listed, the grouping, the computed
numbers. A 5-byte difference between two renderings of the same page has already
once been chased down to nothing but the flag and the nonce.

Then: a smoke test asserting the page renders *and* that Symfony served it (the
catch-all would otherwise hide a Symfony-side mistake), plus the same path with
and without the locale prefix, plus an unsupported prefix still falling through.
And regenerate the ACL snapshots.

## Rejected approach, and why

**Synthesizing an `MvcEvent`.** It is possible to build an `MvcEvent` with a
`RouteMatch` and Router and set it on the `Application` by reflection — the fuzz
harness already does something like this to construct forms. Every existing
`.phtml` would then render unchanged, layout included, and pages would port in
minutes.

It was considered and rejected: it re-creates laminas-mvc bootstrap state in
order to serve a route whose purpose is to have left laminas-mvc, so the "ported"
page ends up depending on *more* laminas internals than the legacy one, and Twig
gets postponed indefinitely. Fast progress that deepens the coupling the
migration exists to remove.

Recorded here so it does not get re-proposed as an obvious shortcut. It is
obvious, and it is a trap.

## Concrete wiring

`twig/twig` v3.28 is a direct dependency. It pulls **nothing** — 1 install, 0
updates, no `psr/cache` — so it sidesteps the FrameworkBundle blocker described
in [php-85.md](php-85.md) entirely.

The Twig environment, its template directory and its custom functions are
established by the first HTML port (shrines) and live in `src/` and `templates/`.
Read those before adding the second page; reuse the layout rather than writing a
second one.

## Log

Append as you go. Date each entry, and prefer the surprising over the tidy.

**2026-08-05 — maintenance endpoints (JSON, no view).** First route port.
Established `App\Laminas\ServiceBridge` (lazy ServiceManager, never bootstrapped)
and the locale-prefix route pattern. Two lessons that generalise: while both
front controllers are live, ported and legacy code must **share** the
implementation rather than run parallel copies — the payload computation was
extracted to `SionModel\Cache\CacheStatusPayload` so the two cannot drift; and
`tools/acl-table.php` had to learn about ported routes, because a route that
moves to Symfony otherwise *disappears* from the authorization table instead of
showing up as unguarded.

**2026-08-05 — container DNS.** `composer require` from inside the capsule fails
with `curl error 28 … Resolving timed out`. Run composer from the host instead;
`config.platform` is pinned, so the host's PHP version does not affect
resolution. Same class of problem as `docker build` needing `--network=host`.
