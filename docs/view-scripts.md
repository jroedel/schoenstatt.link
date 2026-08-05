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
| `BjyAuthorize\Guard\Route` | **The route guard is gone** — but not the ACL. See below. |
| `SlmLocale\Strategy\UriPathStrategy` | No locale detection, no `/en` stripping, no redirect. See "The locale trap". |
| `Laminas\Mvc\Application`'s route/dispatch events | Four view helpers and the whole `Navigation` service stop working. See the inventory. |
| `Application\Module::onBootstrap()` | The database-derived navigation branches are never primed or cached |
| `Application\View\GdprStrategy`, `SionModel\Mvc\CspListener` | Cookie-consent and CSP header handling do not happen unless a kernel listener redoes them |
| laminas-view layout | No site chrome unless the Twig layout provides it |

None of this is a defect in the bridge. It is what "leaving laminas-mvc" means,
and the work of porting a page is largely the work of deciding, per item, whether
to reproduce it, replace it, or do without.

**Corrected 2026-08-05, porting `shrines`:** this table used to say "no session,
so no identity", and that is wrong. Asking `isAllowed()` makes BjyAuthorize ask
JUser for the identity, JUser reads it from the session, and reading the session
calls `session_start()` — measured: `session_status()` goes NONE → ACTIVE across a
single `isAllowed()` call, with no MVC listener involved. So **permission-gated
markup still works on a ported page**: a signed-in moderator gets the moderator
table, an anonymous visitor the public one, exactly as before. What is missing is
only the *route* guard, i.e. the thing that would have refused the request
outright.

The cost is a `Set-Cookie` that PHP writes into the SAPI header list before any
listener can object, which on an unconsented visitor is precisely what
`GdprStrategy::onFinish()` exists to remove. `App\Http\GdprCookieListener` is its
counterpart, and a ported HTML page needs it.

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
`nowMessenger` · `partial` · `requestUri` · `translate` ·
`zfcUserDisplayName` · `flag` · `email` · `telephone` · `formatUrlObject`

Also resolvable as services: `ViewRenderer`, `ViewHelperManager`,
`MvcTranslator`, `BjyAuthorize\Service\Authorize`, `Router`.

**Unavailable** — these reach `getMvcEvent()->getRouteMatch()` and die with
`Call to a member function getRouteMatch() on null`:

`url` · `routeName` · `localeUrl` · `libraryInfo`

That list is why an existing `.phtml` cannot simply be handed to the renderer
from the Symfony side: `url()` appears in nearly every template. And it is not
only the four: anything that *calls* one of them fails the same way, which covers
`formatEntity`, `formatAssociation`, `formatPerson`, `editPencil` and the whole
`navigation` family. `App\Laminas\ViewHelpers` is the door to the ones that do
work, with a typed method per helper so the ones that do not cannot be asked for
by name.

Two corrections to earlier versions of this list, both found porting `shrines`:

- `zfcUserDisplayName` **works.** `JUser\View\Helper\ZfcUserDisplayName` needs
  only the authentication service, never the view. It was on the unavailable list
  by association.
- `Laminas\Navigation\Navigation` **does not resolve at all**, so it belongs on
  neither list as written. Its factory
  (`AbstractNavigationFactory::preparePages()`) asks
  `$application->getMvcEvent()->getRouteMatch()`, so the *service* cannot be
  built, and the `navigation` view helper is useless without it.
  `App\View\SiteChrome` reads the raw `navigation` config instead — which costs
  nothing for the navbar, because that renders `minDepth(0)/maxDepth(0)` and every
  branch `Application\Module::onBootstrap()` primes from the database sits below
  depth 0. Breadcrumbs are the real loss; a ported page passes those in.

And one priming detail that will bite: `HelperPluginManager` injects a renderer
into its helpers only once a renderer has claimed it, which `PhpRenderer` does in
its own constructor. Pull `ViewRenderer` out of the container **before** using any
helper, or `$this->view` is null and even `flag` fatals on `escapeHtmlAttr()`.

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
  `/en/shrines`. Do not concatenate the prefix onto each result: put it back the
  way SlmLocale does, by setting the router's base URL to `<base>/<alias>` once,
  after which everything it assembles carries it. `App\Laminas\RouteUrl` is that,
  and every ported page should go through it rather than calling `assemble()`.

## The locale trap

`Locale::getDefault()` in a Symfony-served request is **`en_US_POSIX`**, because
SlmLocale never ran. This is not cosmetic. `Schoenstatt\Model\SchoenstattTable`
indexes arrays by locale — `$v['nameByLocale'][$locale]` around line 2800 — so an
unrecognised locale produces a stream of `Undefined array key` warnings and wrong
or empty data, while still returning a page.

`App\Http\LocaleListener` now does this on `kernel.request` for every
Symfony-served route, from `App\Locale\Locales` — so a ported controller no longer
has to remember, and a `kernel.request` listener is the right place for a second
reason: `JTranslate\View\Helper\Flag` reads the locale in its *constructor* and
caches the translated country names for good, so setting it inside a controller is
already too late if anything has touched the helper manager. With `en_US` set,
`getShrines()` returns its 207 rows cleanly.

The alias table (`en→en_US`, `es→es_ES`, `pt→pt_BR`, `de→de_DE`, `it→it_IT`,
default `en_US`) is duplicated in `App\Locale\Locales` rather than read from
`config/autoload/juser.global.php`, because `config/symfony/routes.php` needs it to
declare route constraints and reading the merged config there would load every
laminas module before the first route existed. `test/Integration/SymfonyLocaleAliasTest`
is what keeps the copy honest.

The *unprefixed* form needs a decision too. SlmLocale's `redirect_when_found`
answers `/shrines` with a 302 to the negotiated language rather than serving the
page at two URLs; `ShrinesController` reproduces that, and the absence of the
`_locale` route attribute is how it knows. Negotiation order is SlmLocale's:
path, then the `slm_locale` cookie, then Accept-Language, then the default.

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
which shows up as visible `&amp;lt;` in the page rather than as an error.

Three rules, as settled by the `shrines` port:

1. **Drop the manual escaping** where the template did it and Twig can. `{{
   translate(s) }}` is safer than the `<?= $this->translate($s) ?>` it replaces,
   which escaped nothing at all in several places.
2. **A Twig function that returns markup declares `is_safe: ['html']`** — never
   `|raw` at the call site. The declaration is a claim about the helper behind it,
   so read the helper before making it; every one behind
   `App\Twig\LaminasExtension` was read, and each escapes its own inputs. Keeping
   `|raw` out of the templates matters because a reviewer cannot tell whether a
   `|raw` is load-bearing or a shrug.
3. **The one legitimate `|raw`** is the `sprintf`-a-link-into-a-translated-sentence
   pattern, which appears all over this application: `{{ translate(s)|e|format('<a
   …>')|raw }}`. That is the .phtml's `sprintf($this->escapeHtml($this->translate(
   $s)), '<a …>')` exactly — escape the translated text, then interpolate markup
   that is a literal in the template. Do not split the sentence to avoid it: the
   whole string is the translation key, and splitting it discards every existing
   translation.

Whether you got it right is cheap to check and hard to see by eye:
`test/Integration/ShrineTemplateTest::testHelperMarkupIsNotDoubleEscaped` asserts
`&lt;span class=` and `&amp;nbsp;` never appear.

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

The Twig environment, its template directory and its custom functions were
established by the first HTML port (shrines). The inventory — which file does what,
which functions exist, and the three `Environment` options that are decisions
rather than defaults — is in [strangler.md](strangler.md) under "The Twig layer";
it is kept there because it is about the mechanism, not about porting technique.

Two of those options are worth repeating here, because both cost real time:

- **`auto_reload` must be on.** Twig keys a compiled template by a hash of its
  *name*, not of its contents, so with `auto_reload` off an edited template is
  never recompiled — which reads exactly like "my change did nothing" and was
  diagnosed only by comparing a rendering that should have differed and did not.
  The deploy is a phploy file sync with no cache-warming or purging step, so
  nothing else would catch it.
- **`strict_variables` is on**, the opposite of `PhpRenderer`. Safe only because
  the data was checked first: every key the shrine templates read is present in all
  207 rows. Do the same measurement before porting a page rather than after the
  first 500.

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

**2026-08-05 — shrines (first HTML port).** Both renderings were compared row by
row, signed in and out, and agree exactly: 207 rows, 5 regions, identical scores.
Five things that generalise, beyond the corrections already folded into the
sections above:

- **A/B the two renderings for real.** Comment the Symfony route out, capture the
  laminas rendering, restore it, capture the Twig one, diff. Reading the `.phtml`
  and believing you have understood it is not the same thing, and here it was not:
  the moderator table's website column looks like "skip the row when the first url
  is a Map" and is actually "the first url that is not a Map" — the `break` sits
  inside the `if`. That single misreading blanked 94 of 207 cells and no assertion
  short of a row-by-row diff would have found it.
- **Reproduce even the ugly bytes.** `EditPencil` emits `<a href="…" >` with a
  stray space from an empty `$otherAttributes`. Reproducing it makes the two
  renderings byte-identical over the whole table, which is worth more than clean
  markup: a real difference then cannot hide in 207 lines of cosmetic noise.
- **`PhpRenderer::render()` extracts the view variables.** So `$totalPercent` in a
  `.phtml` is *not* only whatever the template assigned it — the ViewModel supplies
  it too. Reading a template as plain PHP, a variable used outside the branch that
  assigns it looks like a bug and is not.
- **Header parity matters as much as body parity.** The Twig rendering initially
  sent two `Cache-Control` headers, because `ResponseHeaderBag` invents `no-cache,
  private` and `sendHeaders()` appends. `LaminasResponseConverter` already refused
  to do that to a bridged response; `App\Http\InventedCacheControlListener` now
  refuses for a native one. Compare headers, not just markup.
- **A ported page's laminas action can often be driven directly in a test.**
  `shrinesAction()` touches no controller plugin, request or route match, so
  `ShrineIndexParityTest` constructs it and compares its ViewModel against the
  ported code. That is how a copy of the arithmetic stays safe when *editing* the
  laminas action would put production at risk — better than sharing code you had
  to modify.
