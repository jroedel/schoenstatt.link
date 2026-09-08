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
                                       ├─ [/{_locale}]/assignments/search          ⎫ App\Controller\
                                       ├─ [/{_locale}]/assignments/advanced-search ⎭ AssignmentSearchController
                                       │                       (the navbar search box's destination)
                                       ├─ [/{_locale}]/movement
                                       │                 → App\Controller\MovementController
                                       ├─ [/{_locale}]/persons          ⎫ App\Controller\
                                       ├─ [/{_locale}]/persons/search   ⎭ PersonsController
                                       ├─ [/{_locale}]/persons/{person_id}
                                       │                 → App\Controller\PersonController
                                       ├─ [/{_locale}]/texts
                                       │                 → App\Controller\TextsController
                                       └─ /{path} .*     → App\Http\LegacyBridge
                                                              └─ Laminas\Mvc\Application

(the four entity show pages, the literature surface, the sitemap and the v3 API are
omitted from this sketch — `config/symfony/routes.php` is the authoritative list)
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

**Run `tools/acl-table.php` for the count; do not cite the number below.** As of
2026-09-08, after the library-delete batch, it was **239 Symfony-served routes** (212
ACL-checked, 27 declared open — most paths are declared twice, once with a locale prefix
and once without) shadowing **109 of the 132 laminas routes**, leaving 23.

Of those 23, seven were pages: thirteen are structural parents with no action of their own
(`may_terminate => false`, or a guard entry on a route that is not a page), one was
matchable with no guard entry at all — `publication-upload-cover`, where default deny made
it reachable by nobody, **deleted later the same day** — and `kernel-switch` is laminas on
purpose, being the canary toggle that has to work from both sides.

**That "matchable but unguarded" group went from six to one on 2026-09-08**, and in two
different ways, which is the distinction to carry forward. Four of them —
`event`, `event-edit`, `event-delete`, `events/create` — were **deleted**, because behind
them there was no form, no template and no create handler, so opening a guard would have
produced a 500 rather than a page (docs/timeline-and-corpus.md). The fifth,
`libraries/library/delete`, was **built**: it got a guard entry, a Symfony controller and
the cascade that makes deleting a library mean something (docs/libraries.md). Both moves
shrink the same number and they are opposites — an unguarded route is a question, and the
answer is sometimes "retire it" and sometimes "this was never finished".

That figure has been wrong twice in two days, which is why the instruction comes before
it. It read "110 of the 188 … as of batch 6" for four batches — and the two numerators
were not even counting the same thing, 110 being Symfony routes and 188 laminas ones. It
was corrected on 2026-08-17 and was stale again within a day, because three routes were
deleted. Any commit that ports or retires a route invalidates it, which is most commits
that touch this file's subject. `docs/acl-rules.md` is regenerated from the tool and
carries both the counts and the guard each route is checked against.

`config/symfony/routes.php` **is** the migration status of the site, read top to
bottom: `UrlMatcher` takes the first route that matches, so everything declared
above `legacy` belongs to Symfony and everything else still belongs to
laminas-mvc. Porting a route means moving one line up past the catch-all. When
the last one moves, `LegacyBridge` is deleted.

## What is left, and in what order

Written down on 2026-09-08 because "what's next on the strangler?" has been answered from
scratch twice, and the answer is not obvious from the counts: **17 laminas routes remain,
and only one of them is a page a person can open** (23 and six on the morning of
2026-09-08; batch 16 took the four publication actions that afternoon, and the two
"decisions" below were settled by deletion that evening). Regenerate with
`tools/acl-table.php` before trusting the list; the classification is what has value, not
the names.

### The twelve that are not pages

`assignments`, `books`, `borrowers`, `checkouts`, `collections`,
`collections/collection`, `comments`, `dictionary/entry`, `jtranslate/phrase`,
`juser/user`, `library-imports`, `sion-model` — structural parents with no action of their
own. Plus `roles/role`, which *is* guarded (`sch_moderator`) but declares
`may_terminate => false` with the comment "no show action", so it is a parent too.

**None of these needs porting.** They exist so their children can be named, and they
evaporate with `LegacyBridge`. Do not count them as work.

### The one real page

| route | guard | what it needs |
|---|---|---|
| `admin/import-father` | `sch_administrator` | a real form; also the subject `ServingNoteSmokeTest` currently uses to observe the bridge |

`sion-model/auto-fix-data-problems` sat in this table too, with "settle the guard first".
Settling it turned into retiring it — see "Two decisions, both answered by deletion" below.

Four more sat in this table until the afternoon of 2026-09-08 — `publication-create-new-edition`,
`publication-copy-to-main-corpus`, `publications/export` and `publications/prime-authors` —
and batch 16 took them the same day; see "The last four publication actions" below. One row
of that table was wrong and is worth correcting here rather than deleting silently:
`publications/export` does **not** return a spreadsheet. It is `parent::indexAction()` over
the whole corpus rendered as one HTML table of four columns, and the description was inferred
from the name.

**`admin/import-father` has a second cost.** It is the last unported HTML page of any kind,
and `test/Smoke/ServingNoteSmokeTest::testAnUnportedRouteReportsTheBridge` uses it to prove
that a bridged `.phtml` reports itself as bridged. When it ports there is **no other
candidate** — the auto-fix page was the last one and it was retired — so the test loses its
subject for good. What the bridge still renders after that is the laminas **404 page** for
any URL neither router knows, which is a `.phtml` through the bridge too; whether that is a
worthy subject or the signal that this half of the note is dead code is the decision to make
in that batch, and the test's own docblock says which way it leans.

### Two decisions, both answered by deletion — 2026-09-08

Neither was a porting question, and both went the way of the four event routes rather than
the way of `libraries/library/delete`.

- **`sion-model/auto-fix-data-problems`** was guarded `lib_administrator` while its
  read-only sibling is `sch_general_moderator`, which looked like a copy-paste. Measuring
  what the page could do settled it the other way and then made the question moot: of the
  two `ProblemProviderInterface` implementors, `SchoenstattTable::autoFixProblems()` returns
  `[]` and `LibraryTable`'s does exactly one thing — fill `lib_books.sort_text` where it is
  null, **9,764 books, all in Colegio Mayor**. So the guard fitted what it wrote. But
  `LibraryTable::refreshLibrarySort()`, behind the ported and `administrate`-gated
  `refresh-sort` confirmation, recomputes every book's sort text for a library, which is a
  strict superset. **Retired** — route, action, `ConfirmForm`, the `.phtml` branch, the
  guard entry, and two badge branches that had been dead since the admin-menu entry was
  commented out. `ProblemTable` went with it: it read `a_data_problems`, a table that does
  not exist in this database, and `ProblemService` took it as a dependency and never called
  it — it was the reason the UserTable/ProblemService/ProblemTable/AuthService cycle existed.
  The interface keeps `autoFixProblems()`, because patres implements it and
  `test/Integration/SortTextCoverageTest` uses `LibraryTable`'s as its oracle. The wider
  question — whether the Problem architecture earns its place here at all, with 35 problems
  in six kinds on the whole site — is filed in [BACKLOG.md](BACKLOG.md) with the numbers.
- **`publication-upload-cover`** was the last matchable route with no guard entry, and it
  was dead three ways over: default-denied, an inverted success test, and a target column
  that does not exist. **Deleted** — route, action, `Books\Form\UploadForm`, template and
  its `ReservedVerbs` entry, so `/SL…L/upload-cover` is an ordinary slug again. The
  "upload a book cover from the site" item in BACKLOG.md is a feature to design, not a
  route to port, and it is untouched.

### The two that are not going anywhere yet

- **`redirect-pre-april-2020-sl-id`** (`guest, user`) is a 301 for pre-2020 identifiers. It
  renders no layout, so there is nothing to compare and little to gain; it is the cheapest
  route left and the least valuable.
- **`kernel-switch`** (`sch_administrator`) is laminas **on purpose** — it is the canary
  toggle and has to answer from both front controllers.

### The endgame is a decision, not a port

This is the part the counts hide. `LegacyBridge` cannot be deleted while `kernel-switch`
has a job, and `kernel-switch`'s job is to make reverting production to laminas a cookie
away. So finishing the strangler ends with **retiring the canary**, which is a judgement
about how much confidence the Symfony front controller has earned — not a batch of work.
It has been the site-wide default since the 2026-08-11 deploy.

`assignments/assignment` deserves one line so nobody counts it as a page: it is guarded and
`may_terminate => true` with an `action => show`, but the `assignment` entity's `show_route`
is **`association`** — an assignment's canonical URL is its association's page — and no
assignment show template exists. So the route resolves to `SionController::showAction()`
looking for a view script that was never written. It is dead by configuration, and the fix
is to delete it, not to port it.

## Which front controller is live

`SYMFONY_KERNEL` is an Apache environment variable, read by `public/index.php`
before any configuration is loaded — which is why it is an env var and not a
config key.

| where | set in | value now |
|---|---|---|
| capsule | `docker/apache-vhost.conf` (committed, baked into the image) | `1`, with `SetEnv` — so **no cookie can move a capsule request off it** |
| production | `public/.htaccess` (**tracked and deployed**) | `1` site-wide, plus a cookie override each way |

> **This block said "the capsule runs the Symfony front controller and production does
> not" until 2026-09-08, and had been wrong for four weeks.** Production's site-wide
> default was committed 2026-08-10 and went live with the **2026-08-11 deploy**; the
> paragraph below still described the pre-cutover state, and mentioned phploy's mid-deploy
> broken window, which stopped existing when phploy was deleted on 2026-08-16. Left as a
> correction rather than a silent edit because a stale claim about *which front controller
> is live* is the most expensive kind in this file — it inverts the reading of every
> other section.
>
> **Both are `1`.** The cheapest way to confirm, and the one to use rather than trusting
> any document: `curl https://schoenstatt.link/_health` answers
> `{"status":"ok","kernel":"symfony"}`.

So **both run the Symfony front controller.** That is still the point of the gate:
reverting production is an `.htaccess` edit and nothing else — no deploy — for everything
except the surfaces whose laminas half has since been deleted (the JUser sign-in surface
since 2026-08-21, the JTranslate GUI since 2026-09-08, `/library-imports`, and
`libraries/library/delete`, which never had a laminas page at all). Rolling any of those
back is a deploy. To A/B
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
- ~~**The persistent cache stops storing anything on ported pages, for everyone.**~~
  **Fixed 2026-08-22.** The same shape as the phrase bug above, in a different subsystem,
  and it survived eleven days after the flip because it made the site look *faster*. A
  `SionTable` queues a cached item and writes the queue on `MvcEvent::FINISH`; a ported
  route never gets there, so every page read, cached into memory, queued, and dropped the
  queue. Within the request that queued them the items come back out of `$memoryCache`, so
  queued and written are indistinguishable to the code that queued them — only the *next*
  request could tell, and it had no way to say so. Measured over 49 requests on nine pages:
  348 reads of data keys, **0 hits, 0 writes**, against 83% hits on the same pages under
  `SYMFONY_KERNEL=0`. Nobody noticed because not booting laminas-mvc saves more per request
  than the cache was returning, so wall time went *down*. `SionModel\Cache\CacheFlushQueue`
  plus `App\Http\SionCacheFlushListener` now drain it on `KernelEvents::TERMINATE`;
  statements per request went 11.4 → 6.3 and the measured page set 2,567 ms → 1,581 ms. See
  [caching-performance.md](caching-performance.md).
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
   according to the response's own `Content-Encoding`, handing back plaintext
   still labelled `Content-Encoding: gzip`. The sitemap route was what found
   this, by gzipping its own payload; it no longer does — the files are plain XML
   now — but the distinction is a property of the two methods, not of that route,
   and any laminas action that sets `Content-Encoding` hits it. `getContent()` is
   also exactly what `HttpResponseSender::sendContent()` echoes today.
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
| | sets the text domain on **twelve** view helpers per controller module, from a `dispatch` listener | the `_text_domain` route default, read by `App\Twig\LaminasExtension::translate()` and pushed onto the two helpers that need it — `App\Laminas\ViewHelpers::useTextDomain()` (`translate`) and `useFlashMessengerTextDomain()` (`flashMessenger`, 2026-08-13). The other ten are unreachable from Twig today; bridging one means setting its domain in the same commit |

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

   **And since 2026-08-13 it carries the query string, which laminas does not.** This is
   the one place the guard deliberately improves on the original rather than reproducing
   it, and it was batch 6 that made it matter: before then no ported guarded route took
   its input from the query, so losing it changed nothing anyone could see. Now a member
   who follows a bookmarked search, signs in, and lands on `/en/assignments/search` with
   no query gets **every entity in the database** — a 595 KB page — instead of their
   results.

   Both sides used to drop it, and the reason is worth knowing before "fixing" it
   somewhere else too: `JUser\View\RedirectionStrategy` assembles the return trip from
   `$routeMatch->getParams()` with only `name` in its options, and there is no `query`
   option in that call. Verified on both front controllers before changing anything —
   anonymous `/en/assignments/search?search=Walter` answered
   `?redirect=/en/assignments/search` on each.

   Two details, both measured rather than reasoned:

   - **The path stays literal and only the query is percent-encoded.** Encoding is
     *necessary* for the query, because an unencoded `&` ends the `redirect` parameter and
     truncates the return trip at the first one. Encoding the path as well would be
     harmless and would rewrite `?redirect=/en/admin` into `?redirect=%2Fen%2Fadmin` on
     every guarded route on the site, for nothing.
   - **The encoding has to be the outer layer PHP strips**, not something baked into the
     value. `JUser\Controller\LoginController::validRedirect()` refuses anything
     `$router->match()` rejects, and it reads the value already decoded — so
     `/en/texts?search=Bund` matches route `texts` and is accepted, while a literal
     `/en/texts%3Fsearch%3DBund` matches nothing and would be silently discarded.
     (That router match is also why `/en/…` works at all: `UriPathStrategy` sets the
     router's base URL to `/en` on a laminas-served request, so the login page — which is
     unported — matches the prefixed path.)

   The whole chain is only proved end to end by
   `test/Smoke/AssignmentsSearchSymfonySmokeTest::testAVisitorSignsInAndLandsBackOnTheirSearch`,
   because four separate things have to hold together: the guard encoding, PHP decoding,
   the sign-in form carrying the value through a POST as a hidden field, and
   `validRedirect()` accepting it. `MagicLinkSignIn::requestSignInLink()` posted a
   hardcoded empty `redirect` until this change, which is why no earlier test could have
   caught any of it.
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

### The locale hop, and the redirect order — fixed 2026-08-20

This section used to record a surviving difference from laminas: on the **unprefixed**
form of a restricted path the two front controllers redirected in a different order.
Laminas answered `/admin` with SlmLocale's `302 → /en/admin` and denied on the second
hop, arriving at `?redirect=/en/admin`; the Symfony guard ran before the controller that
would issue the locale redirect, so it denied on the first hop and produced
`?redirect=/admin`. Nobody's access differed, so it read as tidiness.

It stopped being tidiness once the return trip began travelling in a **login link**
rather than a session: the value in that query string is what the visitor actually lands
on after clicking a magic link, possibly on a different device. A wrong value there is a
wrong destination, not a cosmetic difference.

`App\Http\LocalePrefixListener` now issues the hop on `kernel.request` at priority
**-8** — below `LocaleListener`'s 0, because the target needs the negotiated locale, and
above `AuthorizationListener`'s -16, which is the entire point. Both bounds are asserted
by nothing but that arithmetic, so they are stated as constants rather than left to
registration order.

Three things about the shape are worth carrying forward:

- **The declaration is a policy, not a target.** `App\Http\LocalePrefix` says only
  whether the unprefixed twin redirects; the route name comes from
  `SymfonyRoute::routeName()` and the parameters from the path's own placeholders, read
  off the path string by `$ported()` at declaration time. That was measured against all
  42 hand-written call sites before they were deleted: every one passed exactly its own
  path's placeholders and targeted its own route name, so there was nothing per-route
  left to state. Deriving it also removes the failure mode those call sites had — a
  target assembled by hand from the wrong parameters is a 302 to a URL that 404s, and
  nothing about it shows until somebody follows the link.
- **`$ported()` defaults to redirecting, and the opt-out set is pinned by name.** Sixty
  of the sixty-four call sites would have said the same thing, so a required argument
  would have been ceremony; but a default is also how a *new* JSON endpoint would
  silently acquire a redirect its caller has to follow.
  `test/Integration/LocalePrefixDeclarationTest::NO_REDIRECT` therefore lists all 34
  non-redirecting routes and fails on any change in either direction — a route acquiring
  the hop, or `sm-cache-status` losing it and putting a 302 in front of the deploy's own
  cache gate.
- **Four routes opt out explicitly and the rest have no twin to be sent to.** The
  explicit four are `sm-cache-status`, `sm-clear-persistent-cache` (a deploy hook and the
  production monitor call them at the bare path), `sitemap` (Apache serves the file, and
  its content is all five languages at once) and `libraries/library/book-list-json`
  (fetched by JavaScript). Their sibling `sion-model/phpinfo` *does* redirect, because it
  is only ever reached from a browser — which is exactly why this could not be inferred
  from the `/sm/` prefix. The other 26 are declared with `$routes->add()` rather than
  `$ported()`: `/api/v3` ×21, `comments/create` ×2 (POST only, and a 302 discards the
  body), `health` (no prefixed form exists), `library/my-books` and `legacy` (bridged, so
  SlmLocale does its own).

Net effect on the tree: **42 controllers lost the four-line block**, 34 lost an import,
and twelve lost the `RouteUrl` constructor dependency they had only for this — 517 lines
deleted against 201 added. `LibraryPageController::PAGES` and
`CheckoutsController::ROUTES` both carried laminas route names for no other purpose and
are gone with it.

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
| `src/Http/LocalePrefix.php` | whether a route's unprefixed form redirects; declared per route in `routes.php` |
| `src/Http/LocalePrefixListener.php` | issues that 302, above the authorization check. **No controller does this any more** |
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

**`strict_variables` is why a reproduced helper must reproduce its guards, not just its
output.** A laminas view helper written around PHP's "absent key reads as null" gets that
behaviour for free; the Twig reproduction of it raises instead, and raises *after* the
response is assembled, which is the fatal-200 wedge. The case to keep in mind is the
deleted-entity placeholder: when a change log row names a record that no longer exists,
`SionTable` substitutes `{isDeleted, <keyField>, <nameField>}` and nothing else, so every
other field the formatting reads is simply gone. `_entity-format.html.twig` read
`entity.country` off it and wedged `/en/sm/view-changes` 28 times before anyone connected
the two — it took repairing `association-delete` on 2026-08-14 to make such a row
producible on purpose. When porting a helper, look for what its **early returns** protect
against, not only for what its happy path emits.

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

`translate()`'s two-domain lookup is a **superset** of laminas' behaviour for *rendering*,
not a mirror of it — laminas has no cross-domain fallback. It cannot lose a translation
laminas finds; in principle it could find one laminas misses, which would show as the
ported page being *more* translated. Verified equal across all five locales; see the
both-front-controllers procedure below.

**Discovery, unlike rendering, is single-domain, and the difference is the whole point.**
`Translator::translate()` fires `EVENT_MISSING_TRANSLATION` on a miss, and JTranslate's
listener is the only path by which a row enters `trans_phrases` — so a second `translate()`
call is not a second read, it is a second *write*. Until 2026-08-12 the fallback was one,
and every string the page's domain could not translate was also filed in `default`: 232
duplicate rows. The page's domain is still asked with `translate()`, because that call is
what files an unknown phrase where it belongs; `default` is now read out of its compiled
catalog by `LaminasExtension::catalogValue()`, which fires no event.

**A bridged view helper that translates needs its domain set explicitly, in the commit that
bridges it.** `JTranslate\Module` sets one on twelve helpers from a listener attached to
`AbstractActionController::dispatch`, which a Symfony request never reaches. Two are
reproduced — `App\Laminas\ViewHelpers::useTextDomain()` for `translate` and
`useFlashMessengerTextDomain()` for the flash messenger. The other ten are unreachable from
Twig *today*, which is a fact about today's templates rather than a guarantee. The flash
messenger was the one that got missed, and the symptom was not an untranslated page — it
was the phrase table growing one row per flash message. See
[translation.md](translation.md) for the full mechanism.

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
canary above — and that is where it should be repeated, because production has real
translations and **ICU 72.1 against the capsule's 76.1**, which is what moves the 27
`IntlDateFormatter` call sites.

**It is not because production has more data.** This sentence used to end "and five years
more data than the capsule dump", which was wrong: `database/dumps/` holds a *current*
production export — 2026-08-01 as of this writing — and the 2021 dump it names was only the
first import, on the day the repo was reopened. So capsule row counts and page sizes are
representative, and a measurement taken here does not need discounting for scale. See
CLAUDE.md, which is where the stale claim originated.

**Flush the persistent cache before each capture when the change touches cached data**,
or the diff proves less than it appears to. The navigation extraction on 2026-08-13
compared 396 of 396 laminas responses identical — against a cache whose navigation
branches had been *written by the code being replaced*. The new builder had not run at
all on the paths that mattered. Flushing before each of the two captures re-ran it cold
and gave the honest number, 394 of 396, the remaining two being a tie-broken row order in
an assignments table that differs between any two runs. A warm cache does not make a diff
wrong; it makes it a diff of the cache.

**Do not run `composer test` between two captures**, for two independent reasons.

The second, added in batch 7: `test/Smoke/Batch7EditWriteSmokeTest` **saves forms**, and
`SionTable::updateEntity()` files rows in `sch_changes` — about seventeen per run, which it
deliberately does not clean up because a change log is an audit trail. `/sm/view-changes`
renders the newest 500 changes and is one of the captured paths, so a suite run between the
two captures shows up as drift on a page the batch never touched. The write tests restore
every *row* they edit; the change log is the part that cannot be put back.

The first: the translation suites rewrite the
compiled `.lang.php` catalogs, so a phrase that was untranslated during the first capture
can be translated during the second and the diff reports it as drift. Measured
2026-08-13 on the reserved-verb fix: six responses differed by 3–6 bytes, all of them
`placeholder="ex. …"` against `"z.B. …"` — the German for "ex." — on `de`, `es` and `pt`
but not `en` or `it`. `language/Books/de_DE.lang.php` had been rewritten at 22:17:34,
between the 20:18 capture and the 22:21 one, by the full-suite run in between. Nothing
about the two front controllers differed at all; the catalogs did. Either capture both
sides before running the suite, or re-capture both afterwards — and when a residual
difference is a handful of bytes inside a translated string, check the catalog mtimes
before looking for a defect.

**Trust the harness over an ad-hoc `curl`.** Twice in that same session a hand-rolled
`curl … | grep` reported a regression the captures did not: a breadcrumb whose leaf had
apparently vanished (the markup carries hundreds of spaces between tags, so `head -c 250`
never reached it) and a navbar item that had apparently stopped being active. The
captures — including two taken cold, minutes apart — disagreed both times, and they were
right both times. Collapse whitespace *before* truncating, or better, use
`port-baseline.php show` and diff.

### Known differences, and why each one stays

**Read the number the right way.** A cross-front-controller run has never been at zero and
cannot be — `templates/layout.html.twig` is a *reproduction* of `layout.phtml`, not a byte
copy — so the useful measurement is not "how many differ" but "which paths differ, and did
this batch add any". Batch 6 was run that way: the whole set was captured *before* the
batch touched anything (126 of 516 differing, on 18 already-ported paths and **zero** on
any path the batch would touch), and again after (169 of 516). Every one of the 43 new
entries falls into a group below, and none of them is a defect.

Batch 6's 43:

| n | what | where | why |
|---|---|---|---|
| 15 | `?redirect=` carries the query | the prefixed anonymous form of the 3 new *search* paths × 5 locales | **an intentional improvement over laminas**, which drops it — see "The two denial branches" above. This is the group to expect to grow: every future guarded route whose input is a query string joins it |
| 10 | a results table and a search box appear | `/persons`, `/persons/search` × 5 locales | **the intentional repair.** The laminas pages render an "Add person" link and nothing else, for every query — see below |
| 9 | ~~the guard answers before the locale hop~~ — **fixed 2026-08-20** | the unprefixed form of each of the 9 new guarded paths | was the redirect-order divergence, on nine more routes: laminas sent `/texts` → `/en/texts` and then denied, the Symfony guard ran first and sent `/en/user/login?redirect=/texts`. `App\Http\LocalePrefixListener` now hops before the guard, so both front controllers answer `?redirect=/en/texts` |
| 5 | `936` vs `938` in a badge | `/admin` × 5 locales | the count of untranslated phrases, which grows as pages are rendered — so it moved between the two captures. Capture drift of the same kind rule 3 normalizes for visit counters, and a candidate for a rule 9 |
| 4 | assignment rows in a different order | `/movement`, 4 locales of 5 | the same 62 assignments and the **same byte length**; `getAssignments()` orders by `AssociationId, IsActive DESC, IsMainRole DESC, Sort` and ties are broken arbitrarily, so two runs of *either* front controller disagree. That it was 5 locales on the previous run and 4 on this one, with no code change between them, is the demonstration |

Batch 7's, after three defects the capture found were fixed (see below):

| what | where | why |
|---|---|---|
| the navbar search box points at contacts, not at the library | `/books/{id}/edit`, `/libraries/{id}/edit` (12 responses) | **the one accepted regression of this batch**, and it is a real one — **fixed 2026-08-18**, see the section below |
| the `//<!-- -->` inline-script wrapper | every ported edit form with a selectize block | the batch-2 chrome nit below, now on more pages: `inlineScript()` wraps its content and `layout.html.twig` does not. Invisible in a browser |
| delete-button attribute order, and a space around a `&nbsp;` | `/assignments/{id}/edit` | the button carries the same attributes in a different order, and the template puts `&nbsp;` on its own line. No rendered difference |

**`/collections/{id}/edit` is byte-identical**, which is the useful control: it is the one
library-scoped form whose route name is not in the search-box list below, so nothing else
about the batch's shared machinery differs from laminas at all.

Batch 8's, and it is the cleanest of the three:

| what | where | why |
|---|---|---|
| ~~the guard answers before the locale hop~~ — **fixed 2026-08-20** | the **unprefixed** form of all 9 new delete paths | was the same divergence as batch 6's nine, and the only entry this batch added. laminas answered `/SL100319A/delete` with SlmLocale's `302 → /en/SL100319A/delete` and denied on the second hop; the Symfony guard ran first and answered `302 → /en/user/login?redirect=/SL100319A/delete`. Both now agree — see § The locale hop, and the redirect order |

Batch 9's, on the twelve new create paths (nine routes plus three query-string variants),
signed in, across five locales — **176 differing responses and not one defect**. Every one
falls into a group that already existed:

| n | what | where | why |
|---|---|---|---|
| 101 | Symfony renders the translated help block, placeholder or option label where laminas renders English | the four non-English locales of every create page carrying one | **an intentional improvement, and pre-existing rather than new**: the same difference is already on `/es/SL100319A/edit`, which batch 5 ported. laminas leaves a form's help text in the source language; the ported page runs it through the same translator the rest of the page uses. It reaches more paths now because the create pages include the same field partials |
| 65 | the `//<!-- -->` inline-script wrapper | every create page with a selectize block | the batch-2 chrome nit, unchanged and invisible in a browser |
| 10 | the navbar search box points at contacts, not at the library | `/books/create/{library_id}` × 5 locales × 2 variants | **batch 7's one accepted regression**, now on the book create page for exactly the same reason — **fixed 2026-08-18**, see the section below it |

Batch 10's, on the two create paths batch 9 left behind, signed in, across five locales —
**19 differing responses, no new group and no defect**:

| n | what | where | why |
|---|---|---|---|
| 12 | Symfony renders the translated help block where laminas renders English | the four non-English locales of `/persons/create` | batch 9's group above, on one more page. The string is `Please begin with a '+' followed by the country code`, from the phone fields |
| 10 | the `//<!-- -->` inline-script wrapper | both pages | the batch-2 chrome nit, unchanged |
| 5 | the `nameDay` selects are hidden inputs, and four patres fields exist that laminas does not render at all | `/persons/create` × 5 locales | **the Symfony side is the correct one**, and this is the same difference `/persons/{id}/edit` has carried since batch 7 — see the person-form section below. Without those hidden inputs a save writes NULL to two `NOT NULL` columns and 500s |

`/texts/create` differs *only* by the script wrapper, in all five locales — no field, no
attribute, no ordering. That is the useful control for the batch: the text template overrides
nothing at all, so anything else differing would have been the shared create machinery rather
than the page.

Batch 11b's, on the twenty-two new library paths, signed in, across five locales —
**37 differing responses, none of them a defect.** Four groups, and only the last two are
new:

| n | what | where | why |
|---|---|---|---|
| 15 | the `//<!-- -->` inline-script wrapper | `/libraries/{id}/checkout`, `/libraries/{id}/mass-checkout`, `/borrowers/{id}` | the batch-2 chrome nit, unchanged |
| 9 | Symfony renders the translated help block or label where laminas renders English | the non-English locales of `/libraries/{id}/checkin`, `/inactivate-books`, `/label-management`, `/books/{id}`, `/library-imports/library/{id}/create` | batch 9's group, on five more pages. The strings are `One barcode per line`, `Export` and `Change log` |
| 5 | a 500 becomes a rendered empty list | `/library-imports/library/1` × 5 locales | **the fix, not a side effect.** `SionController::indexAction()` throws `InvalidArgumentException: No entity provided.` when a library has no imports, so this answered 500 for five of the six libraries. Pinned by `LibrarySurfaceSmokeTest` |
| 5 | a link becomes a confirmation form | `/libraries/{id}/sort-debugging` × 5 locales | **the one contract this batch changed** — the "Refresh all library sort text" link pointed at a route that rewrote every book in the library on the resulting GET. See App\Books\RefreshSortForm |
| 10 | the page is gone | `/libraries/{id}/batch-operations` × 5 locales × 2 identities | retired: its template was the four characters `<?php` |

Eleven of the twenty-two paths are **byte-identical in all five locales**, including the
library page itself with its 884 category rows, all three checkout views, the printable
book list, the status JSON, the data-problems page and two of the three import pages.

**A capture of a page that introduces a new phrase is not reproducible, and this batch is
where that was noticed.** `/es/libraries/1/label-management` matched laminas in one capture
and rendered `Exportar` where laminas renders `Export` in the next, with no code change
between them. The cause is the discovery loop working as designed: the ported page asks the
translator for `Export` in the `Books` domain, the miss files a `trans_phrases` row —
`origin_route = libraries/library/label-management`, timestamped mid-run — and the phrase
then acquires translations, so the *second* render of the same code answers differently.
Six phrases were filed by this batch's captures. So: when a baseline diff moves between two
runs of unchanged code, check `trans_phrases` by `added_on` before looking for a bug, and
expect the row to be filed under the **page's own** text domain rather than whichever domain
laminas' dispatch listener had set for that helper.

**`/timeline` differs on purpose, from 2026-08-15 — the first entry here that is not a
side effect of porting.** Every previous row in this section is something the port did
incidentally and the question was whether to accept it. This one is a change made
deliberately *after* the port, to a page that was already transcribed byte-for-byte:

| n | what | where | why |
|---|---|---|---|
| 4 | event titles are in the reader's language, not English | `/timeline` in `es`, `de`, `pt`, `it`, `fr`; `/en/timeline` is unchanged | `events` carries a title in all six languages — 527 rows for En/Es/De/Pt/It, 526 for Fr — and `index.phtml` renders `titleEn` in every locale, so four fifths of the audience read an English timeline off data that was already in the table. `TimelineController::titleField()` passes the locale's own column and the template reads it through `attribute()`. Asserted by `BooksSmokeTest::testTimelineTitlesFollowTheLocale()` rather than left to this table |

The distinction matters for how you read a future `tools/port-baseline.php` run on this
path: a difference here is now *expected*, and the .phtml is the stale copy rather than the
reference. It stays only because production can still fall back to it. When the laminas
`events` route goes, `index.phtml` goes with it and this row can be deleted.

The introductory paragraph on that page is a **literal in both renderings**, so it is
English in all five locales in Symfony and laminas alike — that is not a difference, and it
is deliberately not fixed here. Making it translatable files a new phrase row per locale
and belongs with the translation pass in
[timeline-and-corpus.md](timeline-and-corpus.md).

Batch 12's, on the eight new `/users…` paths, signed in, across five locales — **15
differing responses of 96, all three groups deliberate, and no group that was not decided
on purpose**:

| n | what | where | why |
|---|---|---|---|
| 5 | the confirmation names the account | `/users/5/delete` × 5 locales | **a defect not reproduced.** `delete.phtml` filled its `%s` from `$this->user->username` — a property read on the array `UserTable::getUser()` returns — so the page asked whether to permanently delete `''`, with a PHP warning where the name should be. Invisible in production only because `display_errors` is off. A destructive confirmation that names nobody is a safety defect, not a cosmetic one, and it is the entire content of the page |
| 5 | the `<form>` is closed | `/users/create` × 5 locales | neither `create.phtml` nor `create-role.phtml` calls `closeTag()`, so laminas serves an unclosed form element that the browser closes at `</body>`, taking the footer in with it. Every field and the submit button are already above the tag and nothing after it is an input |
| 5 | the same | `/users/roles/create` × 5 locales | as above |

The other **81 of 96 are byte-identical**, including the index with all 293 of its rows and
both permission-gated icons, the edit form in all five locales for an account with a person
reference and one without, the API-token screen, and the revoke route's non-POST redirect.
Also worth stating: the same run reported the delete page's id-not-found branch as a
redirect on both sides, which is the *second* half of that first row — an id naming no
account now goes to the index with 'User not found.' instead of rendering a confirmation for
an account that does not exist.

**Three defects in the shared form renderer were found by this diff and fixed rather than
listed**, and one of them was losing data:

| what | consequence | why no earlier form found it |
|---|---|---|
| the `[]` a multiple select's name needs was appended only when `multiple` was the boolean `true` | `<select name="rolesList">`, so a browser posts `rolesList=1&rolesList=41&…`, **PHP keeps only the last**, and pressing Submit on an unchanged account form cuts it to one role. Nothing fails and the page says "User successfully updated." | the literature search box, which first found the missing `[]`, declares `'multiple' => true`; `EditUserForm` declares `'multiple' => 'multiple'` — the string, which is what the HTML attribute's value actually is |
| an HTML boolean attribute was rendered from its PHP type rather than its name | `multiple="multiple"` where laminas emits a bare `multiple`. Cosmetic, but the same code path is what `disabled`, `readonly` and `required` would take | `is_bool()` is right for the `checked` a checkbox row synthesises, and every earlier form's boolean attributes were synthesised rather than declared |
| `type` and `name` were rendered *before* the element's own attributes instead of assigned into them | `<input type="text" name="username" size="30">` where laminas emits `name="username" type="text" size="30"`. Equivalent to a browser, unequal to a diff — and "the bytes match" is the only claim this renderer can make | an element declared as `'type' => 'Text'` gets `['type' => 'text']` seeded by its own class, so `type` really was first. `EditUserForm` is the first ported form to declare the type *inside* `attributes`, which leaves a plain `Laminas\Form\Element` whose array starts at `name` |

Correcting the third exposed a fourth: `Laminas\Form\Element\Textarea` seeds `type` too, and
the old ordering code had been unsetting it as a side effect — so three
`<textarea type="textarea">` appeared on the collection and library forms the moment the
order was right. `BootstrapFormRenderer::textarea()` now filters by `FormTextarea`'s own
valid-attribute list, transcribed rather than inferred, as `select()` already did.

The net is the measurement worth keeping: **903 → 913 identical responses** while adding 96
new comparisons, with **zero** previously-matching response regressed. A renderer fix that
made ten already-ported form pages match is a stronger result than the batch it came from.

The capture is also what found batch 9's one real defect, which is fixed rather than
listed: `/associations/create` rendered **two empty options** on its time-zone select where
laminas renders one. `FormSelect::render()` merges the empty option into the value options
with `['' => $emptyOption] + $options` — a union, so an element declaring both an
`empty_option` and a literal `''` value option gets exactly one — and
`SionModel\Form\BootstrapFormRenderer` emitted it as separate markup and then iterated the value
options. `AssociationForm::timeZoneId` is the only element in the application that declares
both, which is why four batches of edit forms went past without showing it, and it took a
*create* page because the second half of the bug needs a null value:
`FormSelect::validateMultiValue()` returns `[]` for null and the reproduction cast it to
`''`. `test/Integration/SelectRenderingParityTest` now runs both renderers side by side —
one of the few places in this port where a two-sided parity test is possible at all, because
a view helper needs no MvcEvent.

**Every locale-prefixed delete response is identical**, signed in and anonymous — 45 rendered
confirmations across five locales, plus both branch paths (`/roles/1/delete` at 200 on both,
`/SL499999T/delete` redirecting to `/en/texts` on both). Nothing about the shared machinery
differs, which is what one template for seven routes ought to buy.

This batch is also where the **symfony-vs-symfony control** was first run, and it is worth
adopting: capturing twice from the *same* front controller answered 768 of 768 identical,
which is what makes "233 differ" readable as 36 bare-form entries plus 197 pre-existing
divergences on paths the batch never touched, rather than as drift of unknown size. It costs
one capture and it converts a scary number into a decided one.

##### The navbar search box on library-scoped pages — **fixed 2026-08-18**

`module/Application/view/layout/layout.phtml` switches the navbar search from "Search
contacts" to the *current library's* search whenever the route name contains
`libraries/library/`, `books/`, `checkouts/` or `library-imports/`. It gets the name and
the resource id from **`libraryInfo()`**, which is on the unavailable-helper list above —
it needs an MvcEvent — so a Symfony-served route could not call it, and
`App\View\SiteChrome` fell through to the contacts search. A librarian got a box that
searched contacts where laminas gives them one that searches their library: a functional
regression, not a cosmetic one, accepted in batch 7 and again in batch 8.

`App\Books\CurrentLibrary` closes it. The helper's rule is small — `library_id`, else
`book_id`, else `import_id`, each resolved to a library row — and none of it needs an
MvcEvent once the route parameters are handed in, which `App\Twig\ChromeExtension` does
from the request attributes. `SiteChrome::searchBox()` then reproduces the layout's
condition, both ACL checks included.

Three corrections to what this section said while it was open, each of which mattered:

- **It was three pages, not two.** `books/create` matches `books/` as squarely as
  `books/book/edit` and `libraries/library/edit` do; the count here was written before
  batch 7 added it and never revised. Nothing in the sentence looked stale.
- **Fixing it did not touch every ported page's layout path**, which was the stated reason
  for deferring. `port-baseline.php` A/B'd the whole 936-response corpus before and after:
  exactly 20 responses changed — 4 paths × 5 locales, all signed-in — and the normalized
  diff on each is the two lines of the search form. The cost was over-estimated by the
  page count of the whole site.
- **The library branch is now byte-identical to laminas in all five locales**, including
  the translated placeholder (`Buscar en Bellavista`, `Suche in Bellavista`). What remains
  between the two renderings on those pages is the pre-existing `//<!-- -->` script
  wrapper, which is chrome and predates the batch.

Guarded by `test/Smoke/LibrarySearchBoxSmokeTest` — which compares a ported library page
against an unported one in the same cluster rather than against a fixed string, so it
fails if either side moves — and by `test/Unit/LibraryRoutePrefixesTest`, which pins the
four route prefixes against the `strpos` calls in `layout.phtml` they were copied from.

**This was the prerequisite for the rest of the circulation surface.** Every remaining
`libraries/library/*`, `books/*`, `checkouts/*` and `library-imports/*` route hits the same
branch, so batch 11b now inherits a working navbar instead of thirty more acceptances.

Carried over from earlier batches, unchanged:

| what | where | why |
|---|---|---|
| a commented-out `<td>`, a stray space before a `<p>`, and a missing `//<!-- -->` script wrapper | `/shrines`, `/wayside-shrines` (20 responses) | batch-2 template nits, invisible in a browser. Left alone: they are shipped code and a porting batch has no business editing it |
| a breadcrumb where laminas renders none, and other layout reproductions | the entity show pages, `/literature`, `/dictionary`, `/sm/view-changes` (~90) | the layout is a reproduction; see "Why rules 6 and 7, and what they cost" above |

#### What porting `/persons` fixed, and why that is in the diff

`PersonsController::searchAction()` builds a `SearchForm`, runs `searchPersons()` and
passes the rows to the view as `persons`. `persons/search.phtml` opens with
`$areResults = isset($this->entities) && …` and never mentions `$form` at all. Under
`PhpRenderer` an unset variable is a silent null, so the condition is always false, the
`<table>` below it is dead markup and the search box is never drawn. Measured before
porting, signed in as an account holding every role:

```
/en/persons                       200   9,327 bytes   0 tables
/en/persons/search?search=Walter  200   9,237 bytes   0 tables
```

— the entire body between the navbar and the JSON-LD being
`<a href="/en/persons/create">Add person</a>`. There is not even a "No results found."
message, which is how you can tell the query ran and matched: the rows were fetched and
thrown away. `AssignmentsController::searchAction()` passes `entities` and its template
reads `entities`, so this looks like a copy whose controller variable was renamed and
whose template was not.

The ported pages render both. That is a deliberate deviation from "a port changes nothing",
taken as a decision rather than by accident, and it is what those ten diff entries are.

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

#### The other FINISH listener

`SionCacheTrait::onFinishWriteCache()` is on the same event for the same reason — writing a
serialized result set mid-render charges the visitor for it — and was invisible on ported
routes in exactly the same way until 2026-08-22. It is now drained by
`App\Http\SionCacheFlushListener`, also on `TERMINATE`, and the two listeners are
independent: `TranslationsTable` is not a `SionTable`.

The registry is worth understanding before adding a third of these.
`SionModel\Cache\CacheFlushQueue` holds the tables to drain, and tables **enrol
themselves from their factory** — the alternative, a listener that resolves tables by
service name, would *build* all ten of them, each with a database adapter, on a request
that asked for `/_health`. There is no way to ask a `ServiceManager` whether a service was
instantiated, so "only if it was used" has to be recorded at the moment of use.

One consequence in the wiring: when a queue is present, `SionTableWiring::wireFlushPoint()`
deliberately does **not** also attach the MVC listener. `$container->has('Application')`
answers true under the Symfony front controller — laminas-mvc's own module config defines
the service whether or not anything ever bootstraps it — so the old unconditional code
built an MVC application per table per request, to take an event manager whose event that
request would never fire. A **bridged** request is unaffected: `LegacyBridge` builds its
own laminas application with its own ServiceManager, which holds no queue, so those tables
take the `MvcEvent::FINISH` path exactly as before.

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

**Batch 6 found three more, and two of them were in code already shipped**, which is the
argument for running this before a batch as well as after. Capturing the "before" set — the
whole comparison against unmodified code — is what made them visible, and it costs one run:

| what was wrong | since | how it looked |
|---|---|---|
| `format.entity('person', …)` dropped its options, so `displayEditPencil: false` did nothing | batch 5 | an extra `…/persons/{id}/edit` anchor on **every assignment row of every ported association page**. Nothing failed; the page offered a link the original does not |
| `show_active\|default(true)` turned an explicit `false` back into `true` — Twig's `default` fires on an *empty* value | batch 5 | the association page renders that partial twice with complementary filters, so both columns showed the same rows: "Past contacts" listed current contacts and vice versa. Visible on `/en/SL100001A`, whose six assignments have all ended — laminas leaves the first column empty and the ported page filled it |
| the locale hop rebuilt its target from the route name and dropped the query string (then `LocalePrefix::redirect()`, now the listener, which carries the query for this reason) | batch 4 | `/assignments/search?search=Walter` landed on `/en/assignments/search` with the search silently gone — and, since a blank query there returns everything, on a 595 KB page rather than an error. Invisible until a route whose input *is* the query string was ported |

**Batch 7 found three, and one of them destroys data.** Every one passed the batch's own
smoke suite — nineteen tests asserting three access outcomes per route and a field marker in
the body — which is the sharpest statement yet of what this comparison is for:

| what was wrong | how it looked |
|---|---|
| **a multiple select marked nothing `selected`** | `Select::getValue()` returns an *array*, the renderer compared it with `is_scalar()`, and the comparison fell back to `''`. The field renders, the page is a 200, the form validates and saves — and the browser posts no values for `tags[]`, so `getData()` contributes an empty array and `updateEntity()` **writes it over the stored tags**. `tags`, `composersAll`, `lyricistsAll`, `links`, `authors`, `inLanguage`, `keywords`, `adminTags` — one word of markup between working and silent data loss |
| every checkbox in the wrong wrapper | `<div class="form-group ">` where TwbBundle emits `<div class="checkbox">`. Bootstrap 3 styles the two differently, so it was visible misalignment on every checkbox of all eight forms |
| the wrong flash on an unloadable record | "Access to entity denied." where laminas says "Role not found." — telling a moderator they lack a permission when the record is simply unreachable. Only visible on the *next* page, since a flash is read one request later |

Two of the three share a cause worth naming: **`AssociationForm` has no multiple select and
renders its checkboxes outside a row**, so the only form ported before this batch exercised
neither path. A renderer that has served one form well is not a renderer that has been
tested. The multiple-select name suffix — the `[]` batch 5 found missing — was the third
piece of the same element type, and all three were latent from the day the class was
written.

#### A Twig syntax error is an empty HTTP 200

Worth knowing before trusting a status assertion on a ported route. While
`movement.html.twig` had a Twig comment inside a hash literal, `/en/movement` answered
**200 with a zero-length body** — the smoke test asserting `200` passed, and only the test
asserting page *content* caught it. That is the fatal-200 wedge this document records for
laminas, reproduced on the Symfony side: the throw happens inside `twig->render()`, before
a Response exists, and `display_errors` is off.

So a ported route's smoke test must assert something from the body. "Assert something that
distinguishes the two front controllers, not just a 200" is already step 4 of "Adding a
Symfony route"; this is the second, independent reason for it.

**The suite now checks this for you, on every request.**
`SmokeTestCase::assertNotWedged()` runs inside `request()`, so a truncated HTML 200 fails
the request that made it whatever the caller went on to assert — the same reason
`App\Http\AuthorizationListener` listens to the event rather than to a route. The probe is
the absence of `</html>`, not a byte count: a threshold has to guess, and both measured
wedges (~800 bytes on laminas, 0 on Symfony) stop well before the closing tag while every
legitimate page on the site reaches it. It applies only to a 200 whose content type is
HTML, since a 302 carries laminas' whole sign-in page in a body nobody reads and JSON and
the sitemap are not documents.

**Its first run found a pre-existing one.** `GET /api/v1/libraries/3/books` with a valid
bearer token answers 200 `text/html` with **zero bytes**, and
`ApiAuthSmokeTest::testGatedRouteAcceptsAValidToken` had been passing against it since it
was written — it asserts `200` and the absence of the string 'Fatal error', and an empty
body satisfies both. Confirmed present on `master` before batch 6, so not a regression:
`BooksApiController::getList()` JSON-encodes its rows through php-jwt and library 3 holds a
row that is not valid UTF-8, which the 2026-08-03 exception record for that exact route
names as `DomainException: Malformed UTF-8 characters`.

It is listed in `test/Smoke/known-wedged-responses.php` on the **"no new gaps"** contract
`test/Fuzz/known-form-gaps.php` uses: the check has to be un-skippable to be worth
anything, and an inventory of one broken endpoint beats a deleted check. Every line in that
file is a bug; deleting one is how it gets guarded.

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

`sion-model/auto-fix-data-problems` remained unported after this, and was retired rather
than ported on 2026-09-08 — see "Two decisions, both answered by deletion" above.

### `assignments/assignment` (`/assignments/{id}`) — not a page at all

It looks like the missing third route of batch 6 and it is dead by configuration. The
`assignment` entity spec sets `show_route => 'association'` with
`show_route_key => 'sw_id'`, because an assignment is meant to be read inside its
association's page — but `SionController::getEntityIdParam('show')` resolves the id by
reading `showRouteKey` **off the current route**, and `/assignments/{assignment_id}` has
no `sw_id`. So it always gets null, and `showAction()` takes its first branch: flash
"Assignment not found." and 302 back to `assignments/search`.

Measured for an account holding every role, on assignment 77 — a row `getAssignment()`
returns perfectly well, checked directly through a `ServiceBridge`. The route survives as
the parent of `/edit` and `/delete`, which do work; porting it would mean porting an
unconditional redirect.

Fixing it is a change to `SionModel`, not to the strangler: either the spec stops pointing
`show_route_key` at another route's parameter, or `getEntityIdParam()` falls back to
`default_route_key` when the named one is absent. Neither belongs inside a porting batch.

### `sitemap` (`/sitemap.xml`) — ported 2026-08-13

This section used to say the route was blocked on `Laminas\Navigation`. Half of that was
right and the half that mattered was not.

The **service** really cannot be built here, and it is worth stating as a measurement
rather than a belief: `AbstractNavigationFactory::preparePages()` calls
`$container->get('Application')->getMvcEvent()->getRouteMatch()`, and asking a
ServiceBridge for `Laminas\Navigation\Navigation` answers `Error: Call to a member function
getRouteMatch() on null`. What was wrong was the conclusion that the route therefore
needed the service. A navigation container is three things and the Symfony side has all
three by other means: the static tree is `navigation.default` in the merged config, the
five database-derived branches are `Application\Navigation\PageBuilder`'s — the *same*
builder `onBootstrap()` calls, extracted so the two front controllers cannot disagree —
and a URL per page is `App\Laminas\RouteUrl`. `Page\Mvc` would add `isActive()`, which
nothing wants: the Twig layout compares hrefs.

`App\View\NavigationTree` is that composition, and it was verified against laminas rather
than by inspection. `/sitemap.xml` *is* the laminas rendering of the same container, so its
URL list is the answer key: **10,974 pages from the tree against 10,974 from the sitemap,
identical set, identical order, zero diff.**

**Who may walk it.** Cold, the branches are 0.48 s and 51 MiB of table rows; warm,
`publication-pages` alone is 2.24 MB in APCu — the largest single entry in the persistent
cache — and unserializing that costs 8-10 ms. So the tree is for the sitemap, which needs
all of it at once. Everything else states what it needs: a show page's middle breadcrumb
comes from the record (`PublicationController::breadcrumbs()`), and the navbar's active
ancestor stays a `SiteChrome::NAV_ROUTE` route default. A page that renders on every
request and reaches for `NavigationTree` is a mistake worth catching in review.

**The port fixed the endpoint, then the endpoint was rewritten the same day.** The port's
own fix was real: `samdark\sitemap\Sitemap` splits at 10 MB and had written four files, the
action served back only the first, and nothing wrote an index, so crawlers saw 3,022 of
10,974 pages — every association, the first 2,500 publications, and not one of the 335
compositions.

What the port did *not* fix, because nobody had checked the sitemap against Google's rules,
is that a sitemap may only list URLs at or below its own directory. Serving the parts from
`/sitemap/` therefore put all 36,730 URLs out of scope, and `public/robots.txt` naming
`/en/sitemap.xml` broke the same rule again for the index. A structurally perfect sitemap
was being ignored end to end.

So the sitemap is no longer a route in any meaningful sense. `bin/console sitemap:build`
writes `public/sitemap.xml` plus one `public/sitemap-<kind>.xml` per entity kind, Apache
serves them through the `-s` guard in `public/.htaccess`, and `SitemapController` survives
only to build the files if they are missing. samdark/sitemap is gone from `composer.json`
along with the gzip-header handling that made `App\Http\GzipListener` necessary for this
route — the files are plain XML and `mod_deflate` compresses them. **[docs/sitemap.md](sitemap.md)
is the reference**; it also records the canonical/hreflang rework that followed, which is
the one part of this that touches **both** layouts — every locale is now canonical for
itself and the record pages canonicalise their preferred URL rather than the requested
one, so `templates/layout.html.twig` and `module/Application/view/layout/layout.phtml`
have to stay in step. A fix applied to one and not the other is invisible until a crawler
reaches the wrong kind of page, which is why `test/Smoke/CanonicalLinkSmokeTest` runs its
assertions against a Twig route and a bridged one.

`App\View\NavigationTree` is unchanged and still the reason any of this works, but note
that the walk now happens in a **console process**, so it is `BuildSitemapCommandFactory`
that has to keep the JTranslate flush disarmed.

### The form routes — both obstacles are gone

This section used to say the form routes were blocked on two things. Both claims needed
correcting when `association-edit` was ported on 2026-08-09, and both are now closed —
the form layer on 2026-08-09, the static adapter on 2026-08-14.

**The missing form layer was real, and is now `src/Form/BootstrapFormRenderer`.** It
reproduces `SionModel\Form\View\Helper\SionFormRow` — i.e. TwbBundle's Bootstrap 3
markup — closely enough that the ported `/en/SL100319A/edit` and the laminas rendering of
the same URL are identical except for whitespace between tags. That was verified by
capturing the live page with `tools/form-regression.php probe` before the port and
diffing after; five things had to be reproduced exactly, and each is documented in that
class: attribute order, `FormSelect`'s attribute whitelist, TwbBundle's
escape-only-if-no-tags help-block rule, per-select option translation, and the submit
button's classes.

**The static-adapter claim was too broad, and then it was removed — 2026-08-14.**
`GlobalAdapterFeature::setStaticAdapter()` was read by `CreateRoleForm`, `EditUserForm`,
`DeleteUserForm` and `EditPhraseForm`, through a `NoRecordExists`/`RecordExists`
validator, and by nothing else — `AssociationForm` and `SionForm` never touched it, which
is why `association-edit` moved without it being dealt with. All four now take a
`Laminas\Db\Adapter\Adapter` as a constructor argument
(`JUser\Service\DbAdapterResolver` resolves it in the factories), and the registry write
is gone from `JUser\Module::onBootstrap()`, which had been its only source.

Four things about that change are worth carrying forward:

- **The registry had exactly one purpose.** One write, five reads, all five inside form
  classes; no `TableGateway` in this application uses the feature. That is what made
  deleting it safe rather than hopeful — there was no second consumer to regress.
- **Three of the four forms threw; the fourth was worse.** `CreateRoleForm`,
  `DeleteUserForm` and `EditPhraseForm` raised `RuntimeException: No database adapter was
  found in the static registry` from `getInputFilterSpecification()` for *every* input,
  benign included. `EditUserForm`'s two reads sat inside a
  `try { … } catch (\Exception $e) {}`, so instead of failing it **silently dropped the
  uniqueness checks on `username` and `display_name`** — a create-user POST carrying an
  existing username would have validated clean on any Symfony-served route. Nothing had
  measured that, because nothing had reached the un-seeded path: `JUser\Module` filled the
  registry under laminas and `test/Fuzz/FormRepository` reproduced that step deliberately.
- **`JTranslate\Form\PhraseValidator` had been papering over it** by writing its own
  injected adapter into the registry before building the form, under a docblock section
  headed "The static adapter, honestly" that said plainly this was the wrong answer. Both
  the write and the section are gone.
- **What guards it now**: `test/Unit/NoStaticDbAdapterTest` tokenizes all first-party PHP
  and fails if `GlobalAdapterFeature` is referenced in code again — a source scan rather
  than a behavioural test, because re-introducing the read would keep every other test
  green (the forms are always built by a factory that has an adapter to hand, and
  `onBootstrap()` would be there again under laminas). `test/Integration/UserFormUniquenessTest`
  is the other half: it proves the validators actually run, against the real tables.

One hazard found while writing that test, unrelated to the adapter but worth knowing
before porting `juser/*`: **`EditUserForm::class` is registered in `service_manager`,
which shares by default, and `setValidatorsForCreate()` mutates the instance** it is
called on. Two `$container->get()` calls in one process hand back one form still carrying
whatever the last caller did to it. Harmless today because a request dispatches one
action, and `UsersController::editAction()` and `createAction()` are one dispatch apart —
but a Symfony controller serving both verbs would not be.

What a form route still needs, and what `association-edit` establishes the pattern for:
the form comes from the laminas container through the `ServiceBridge` (so its value
options and its validation are the application's, not a copy), CSRF works because
`App\Http\SessionListener` has already started the laminas session — a token minted by
either front controller is accepted by the other — and the write goes through
`SionTable::updateEntity()` exactly as `SionController` does it.

**A GET form is a form too, and batch 6 is what proved the renderer only knew edit forms.**
`/assignments/advanced-search` is the first ported form that is not an edit form, and it
found five gaps in `SionModel\Form\BootstrapFormRenderer` — every one invisible until a form
declared the thing that triggers it, and every one a difference in the bytes rather than a
failure:

| what was wrong | why nobody had seen it |
|---|---|
| `open()` hardcoded `method="POST"` | the only ported form was an edit form. A GET search form would have posted to a route with no POST handling |
| `open()` always emitted `action` | laminas emits none for a form nobody called `setAttribute('action', …)` on, and `action=""` is not the same thing to a browser resolving a relative reference |
| `submit()` read `value` out of `getAttributes()` | `Laminas\Form\Element::setAttribute()` **diverts** the `value` key to `setValue()` and keeps it out of the attribute list, so that lookup never found anything and always fell back to the literal `'Submit'` — which is exactly what `AssociationForm` declares, so it was right by coincidence |
| the `<select>` whitelist held `FormSelect`'s own valid tag attributes and not `AbstractHelper`'s globals | so `id` was dropped along with `maxlength`. That cost the role field its id, its label's `for`, and the selectize widget `gen-schoenstatt-advanced-search.js` hooks to it |
| `<input>` attributes were not filtered by input type | `FormText` has no `min`, so laminas drops the `'min' => 3` the form declares and this rendered it |
| `column-size` never reached the row class, and a row label never carried `for` | no association-form element declares either |

Two of TwbBundle's rules are now transcribed from its source rather than inferred — the
button class rule (`btn` unless already present, then `btn-default` unless a known option
is), and the row class, whose **double space** (`<div class="form-group  col-md-4">`) is
real and was first misread because the extraction collapsed whitespace runs.

Still on laminas: every create page, and the user and translation forms — the latter no
longer for any reason in the form layer, only because nobody has ported the routes yet.
`/movement`, `/persons`, `/texts` and the two contact searches moved in batch 6;
`/literature`'s search form is ported — see below; the **edit** verb moved in batch 7 and
the **delete** verb in batch 8 — see the next two sections.

#### A third obstacle, found in batch 7: a form factory that reads the route match

The two obstacles above are about the form *layer*. This one is about the *factory*, it
was not anticipated anywhere in this document, and it presents as a page that answers
**HTTP 200 with zero bytes**.

`Books\Service\BookFormFactory`, `CollectionFormFactory` and `LibraryFormFactory` each
open with

```php
$routeMatch = $container->get('Application')->getMvcEvent()->getRouteMatch();
$libraryId  = $routeMatch->getParam('library_id');
```

to discover which library the form belongs to. A Symfony-served route has no MvcEvent, so
`getMvcEvent()` answers null, and the factory dies with `Call to a member function
getRouteMatch() on null` — *after* the response has been assembled, which is the
fatal-200 wedge this document already records for Twig syntax errors.

**It shipped.** `collections/collection/edit` was merged in that state, because the smoke
test covering it asserted only the anonymous 302 and never a successful render. That is
the second independent demonstration of "assert something from the body, not just a
status" in this file, and the first where the rule was quoted in the same commit that
broke it.

`App\Books\LibraryScopedForms` is the answer, and its shape is the part to copy: it
constructs the **same form classes**, so elements, labels and
`getInputFilterSpecification()` stay the application's own and cannot drift — only the
factory's *wiring* is reproduced, each `setValueOptions()` reading the same table method
its factory reads. What it costs is that the wiring now exists twice with no test able to
compare the two, because the laminas factory cannot be built at all without an MvcEvent.
The baseline capture is the guarantee.

**Expect this again.** Any laminas factory may reach for the route match, and three of
the ~40 in this application do. Before porting a route, ask what builds its form as well
as what renders it — `grep -l 'getMvcEvent\|getRouteMatch' module/*/src/Service/*.php`
answers it in one line, and `CheckoutFormFactory` is on that list for whoever ports the
circulation surface.

### The edit surface — batch 7, 2026-08-13

**All ten** entity edit forms now run on `App\Sion\EntityEdit`, one reproduction of
`SionController::editAction()` — the counterpart of what `App\Sion\EntityShow` is for the
show pages — behind a single `App\Controller\EntityEditController` parameterized per route,
the way `ContentPageController` serves the five static pages.

| ported | still on laminas |
|---|---|
| all ten: `text-edit`, `composition-edit`, `roles/role/edit`, `assignments/assignment/edit`, `books/book/edit`, `collections/collection/edit`, `libraries/library/edit`, `dictionary/entry/edit`, `publication-edit`, `persons/person/edit` | — |

#### The field rows are shared partials now (2026-08-15)

Each ported edit template used to carry its entity's field list inline. They are now
`templates/{books,schoenstatt}/_<entity>-fields.html.twig`, included by the edit template
and — from batch 9 — by the create template, which mirrors laminas exactly: `edit.phtml`
and `create.phtml` have always shared one `fields-partial.phtml`.

**The reason is not tidiness.** 13 of the 15 create routes use the *same form class* as
their already-ported edit route, and both laminas templates render the same partial, so
without this the create surface would have arrived as a second copy of every field list —
and the copies would drift the first time a form gained an element. The batch-7 defect
above is what that drift looks like in practice, one entity at a time.

Three things worth carrying forward:

- **The cut follows laminas, and it is not the same cut for every entity.** The partial
  holds what `fields-partial.phtml` holds; whatever `edit.phtml` puts around it stays in
  the page template. So `submit` is *inside* the partial for `collection`,
  `dictionary-entry` and `text`, and *outside* it for the other eight — and
  `publication`'s Delete link and the `assignment`/`person` delete modals stay outside
  too, because a create page has none of them. Getting this boundary from the laminas
  source rather than from a convention is what makes the create templates fall out
  correctly in batch 9.
- **Verified as byte-identical, not as equivalent.** All eleven pages were captured
  signed-in, in all five locales — 55 responses — before and after, normalizing only the
  four values that differ between any two requests (CSP nonce, CSRF token, the throwaway
  account's address, the visit counters). Zero difference. That is a stronger claim than
  `tools/port-baseline.php` makes, because its `normalize()` deliberately erases chrome
  and collapses whitespace to compare *two front controllers*; here both sides are Symfony
  and there is no reason to accept any difference at all. Note that `port-baseline.php`
  does serve as a before/after harness — `capture before`, `capture after`,
  `compare before after` — since the capture name is arbitrary; it just answers the
  weaker question.
- **The whitespace control on each partial is load-bearing.** The stripping comment
  delimiters and the trailing dash on the final row make the partial render with no
  leading or trailing newline, so a plain include emits exactly the bytes the inline rows
  emitted. Without them every form gains a blank line inside it — invisible, harmless, and
  enough to turn "identical" into "identical apart from whitespace", which is a claim that
  has to be re-argued every time instead of checked once.

And one hazard that cost a wedged page during the refactor itself: **do not spell a Twig
delimiter out in a template docblock.** `_collection-fields.html.twig` was written with a
docblock explaining its own whitespace control, containing the literal comment-closing
sequence; Twig has no escape for it, so the comment ended mid-sentence and the rest became
template code. The page went from 13,716 bytes to 0 — a fatal-200 with a healthy status
line. `test/Integration/TemplatesCompileTest` was added in the same commit and is worth
reading for what it does *not* catch: the leftover prose happened to compile (an ellipsis
lexes as a variable name) and died at render under `strict_variables`. The before/after
capture is what caught it.

#### `books/book/edit` shipped with no submit button (2026-08-13 → 2026-08-15)

**The ported library-book edit form could not be saved for two days**, and it was live in
production for the whole of it. A moderator could open `/books/{id}/edit`, change any field,
and find no control to write it back: the only `type="submit"` in the 589 KB response was the
navbar's search button.

The cause is a boundary that only this entity has. Nine of the ten laminas templates in this
batch render the submit as a **row inside `fields-partial.phtml`**, so it came across with the
field list and nobody had to think about it. `books/edit.phtml` renders it **outside** the
partial — `echo $this->formSubmit($form->get('submit'));` between the partial and the closing
tag — and the port reproduced the partial and stopped.

Three things generalise, and the third is the uncomfortable one:

- **Ask what the laminas template does *around* its partial, not only inside it.** The field
  list is the obvious half of a form port and the easy half to check. `books/edit.phtml` is
  thirteen lines; the line that matters is not in the partial it includes.
- **A 200 is not a rendered page, and this is a third distinct way to learn it.** The other
  two in this file are the fatal-200 wedge (a Twig syntax error, and later
  `CollectionFormFactory` reaching for the route match) and the batch-6 renderer gaps. Here
  nothing failed at all: 589 KB of correct markup with one control absent. The smoke test
  covering the route asserted the anonymous 302, so it could not have seen it either way.
- **An accepted difference on a page is a reason to read that page's diff more closely, not
  less.** `tools/port-baseline.php` had `/books/18370/edit` in `PATHS` throughout and nothing
  in its `normalize()` erases a submit button — so the difference *was* in the diff. What it
  was not is alone in it: this page carries the batch's one accepted regression, the navbar
  search box pointing at contacts rather than the library (see the known-differences table
  above). A page already expected to differ is where a second, unrelated difference hides,
  and at 589 KB per response × five locales × two identities it did.

`test/Unit/PortedFormsAreSubmittableTest` is the guard: every template that calls `form_open`
must also render the submit, by any of the three spellings the ported templates legitimately
use. It is a source scan for the same reason `NoStaticDbAdapterTest` is — an HTTP test that
could see this needs a signed-in account holding a per-library `administrate` grant, which is
worth the Mailpit magic-link dance for a rendering diff and disproportionate for "is there a
button". Writing it also reproduced that test's own hazard in miniature: the first version
passed with the fix deleted, because it was matching the word `form_submit` in the docblock
that explains the bug. It strips `{# … #}` first now.

An audit of all eleven ported edit templates against their laminas sources, element by
element, found this and nothing else. The four extra elements on `person-edit` are the
deliberate hidden-input round-trip described below, not an omission.

**`persons/person/edit` completed the surface on 2026-08-14**, and porting it found that
**saving a person had been broken on every front controller** since db6.5. `priestDate`,
`priestDatePrecision`, `bishopDate` and `bishopDatePrecision` are elements on `PersonForm`
and columns in the person spec's `update_columns`, but no partial on this site renders them
— they belong to the patres application, which shares the form. So `getData()` answers null
for all four, `updateEntity()` writes those nulls, and the two `NOT NULL` precision columns
reject the write: **laminas answers 500, Symfony wedges as a fatal-200.** Measured against
both.

The crash was the lucky half. It aborted before the same POST's `PriestDate => null` landed,
which would have erased the ordination dates of the ten persons who have one. **The obvious
fix is the dangerous one**: defaulting the precision columns to `'day'` lets the save through
and turns a loud failure into a silent deletion. Fixed by round-tripping all four through
hidden inputs, which is a no-op write.

Two more things that generalise past this page:

- **A field on the input filter but absent from the template is not "left alone".**
  `getData()` returns a key for every input the filter knows, so an unrendered field
  contributes `null` and `updateEntity()` writes it. That is the mechanism behind both the
  patres crash above and `nameDay`, which is deliberately not rendered here (its column is
  being retired) and therefore carried through three hidden inputs — 130 of 325 persons have
  a name day and every one would have been erased on first save. **Before dropping a field
  from a ported template, check whether its element is still on the form.**
- **`recordName()` asked a guess-list instead of the entity spec.** Its docblock said
  `name_field`; its body walked candidate keys in a fixed order, and for a person that order
  reached `title` — the honorific — first. `/persons/494/edit` was headed "Sr.". It now reads
  the spec, like `FormatEntity` does.

The ported page also **fills in a title laminas leaves empty**: `edit.phtml` reads
`$this->personName`, which no controller ever sets, so every person's page is titled
`Edit ` with an empty lead paragraph. That is recorded in `docs/BACKLOG.md`; the port renders
the name, which is what the template plainly intends.

**`publication-edit` joined them on 2026-08-14** and is the one whose view is not a list of
rows. `fields-partial.phtml` builds five `form-group`s by hand around
`formSelectWithoutOptions`, a helper that renders a `<select>` containing **only the options
already selected** — their full lists are the person and publication tables, and shipping
them would take the page from 526 KB to megabytes. Selectize fetches the rest from three
JSON blobs the controller provides. `SionModel\Form\BootstrapFormRenderer::selectWithoutOptions()`
reproduces it, and three of its four differences from the original were found by the
baseline rather than by any test:

- a `form-control` class on all five pickers, because the reproduction defaulted to adding
  one and none of the call sites is a row;
- an empty option that laminas drops, because `(array) null` is the empty array where
  `[$raw]` is `[null]` — and `null == ''` under the loose `in_array` the original uses;
- `{"i":"2154"}` for `{"i":2154}`, because the option key was cast to string. 7,801 bytes of
  quotation marks, and a real hazard: selectize matches its `valueField` against the option
  value, so a type mismatch is how a picker silently fails to preselect.

`test/Integration/FormSelectWithoutOptionsContractTest` now asserts all of them against the
real helper. **Every one passed the smoke suite first** — the recurring lesson of this
migration, and the reason the capture is worth its forty minutes.

One bug of the original is reproduced rather than fixed: the partial's script says
`authorPersons.concat(authorAssociations);` and discards the result, so the author-association
list is fetched, rendered into the page and offered by no picker. Fixing it would add options
to three fields that have never had them, which is a content change; it is in `docs/BACKLOG.md`.

Five things worth knowing before touching any of it:

- **`library-imports/library-import/edit` is not an edit form and was not in the batch.**
  `LibraryImportsController::editAction()` read a spreadsheet off disk and ran a full
  import simulation on GET, then performed the real import when the POST carried `import`.
  It was ported on its own two weeks later, with the engine extracted first — see § The
  spreadsheet import below.
- **`EntityEdit`'s `$loader` hook is needed by `association` alone.** `getObject()` already
  dispatches to an entity's `get_object_function`, so `getPublication`, `getPerson`,
  `getRole` and `getAssignment` are reached by the plain call; `association`'s is commented
  out, which is what batch 5 found.
- **`redirectAfterEdit()` has two asymmetries, both reproduced.** Its param-map branches
  fall through on a missing field while its key/keyField branch throws, and it reads the
  row as it was *loaded* rather than as updated, because laminas memoizes
  `getEntityObject()`. Every key field here is derived from a primary key, so the two agree
  today.
- **Three of the ten declare a per-row ACL check** on top of their route guard, and for the
  Books entities that check is substantially the whole of the protection: their guard names
  `lib_user`, which `user_role` marks `is_default = 1`, so every registered account holds
  it. Same shape as `sch_user` on `association-edit` and `composition-edit`.
- **Two pages render a second form** — a delete-confirmation modal gated on the entity's
  *delete* permission, which is a different one from the permission that let the visitor
  reach the page. Its action is the laminas delete route, declared per route as
  `DELETE_ROUTE` rather than derived: deriving `<route>/delete` broke three working pages,
  because six of the eight have no delete twin and `RouteUrl` throws on an unknown route —
  the empty-200 wedge again, one commit after the last one.

### The delete surface — batch 8, 2026-08-14

The destructive twin of batch 7: seven delete confirmations on one
`App\Sion\EntityDelete` — a reproduction of `SionController::deleteAction()` — behind one
`App\Controller\EntityDeleteController`, with **one** Twig template for all seven, because
the laminas page is one view script whose whole body is an `<h1>` and a three-element form.
Three route defaults per route rather than the edit controller's nine.

| ported | not ported |
|---|---|
| `association-delete`, `publication-delete`, `text-delete`, `composition-delete`, `persons/person/delete`, `assignments/assignment/delete`, `roles/role/delete` | `event-delete`, `libraries/library/delete`, `sion-model/delete-entity` (all unreachable), `juser/user/delete`, `jtranslate/phrase/delete` (own controllers) |

**Twelve routes contain `delete` and only seven are portable, because three are reachable by
nobody.** `event-delete`, `libraries/library/delete` and `sion-model/delete-entity` have no
entry in the route guard, and BjyAuthorize's Route guard is default-deny — all three answer
**403 to an account holding every role**, measured rather than deduced. Two are dead twice
over: `library` declares no `enable_delete_action`, and `sion-model/delete-entity` names a
`deleteEntity` action `SionModelController` does not define. **This is the shape to check
first on any future batch**: an unguarded route is not an unprotected route here, it is an
unreachable one, and porting it would be work with no user.

> **Two of those three were resolved on 2026-09-08, in opposite directions**, and the
> paragraph above is kept as written because its reasoning is what led to both.
> `event-delete` was **deleted** along with the other three event routes: there was nothing
> behind it to port. `libraries/library/delete` was **built** — a guard entry, its own
> Symfony controller, and a cascade, because "porting it would be work with no user" turned
> out to be only half true. There *was* no user, and the reason was that the page had never
> been finished; a library nobody could delete is a real gap once you have a 16,383-book
> collection to retire. `sion-model/delete-entity` is still unreachable and still names an
> action that does not exist. See docs/libraries.md § Deleting a library.

#### The Cancel button deleted the record, on both front controllers

The find that stopped this from being an ordinary port. `SionModel\Form\DeleteEntityForm`
added its cancel element with the line `// 'type' => 'Submit',` commented out — which does
not make the button inert, it makes it a plain `Laminas\Form\Element`, and
`View\Helper\FormButton` renders an element with no `type` attribute as `type="submit"`. And
`deleteAction()` validated the CSRF token without ever looking at *which* button was pressed.

So clicking **Cancel** on a delete confirmation deleted the record. Measured against a
fixture on 2026-08-14: POST with `cancel=Cancel` and no `submit` answered 302 to
`/en/associations` with the row gone. Live for `sch_general_moderator`, `pub_moderator` and
`texts_moderator`, on the confirmation pages and in the two delete modals batch 7 already
ported. `JTranslate\Form\DeletePhraseForm` carried the identical bug on
`jtranslate/phrase/delete`, where it is worse: deleting a phrase destroys every translation
of it and rewrites the catalogs.

Fixed in both libraries, with two locks: the element is a `Button` now, whose own
`$attributes` carry `type="button"`, so no browser submits it; and both the laminas action
and `EntityDeleteController` refuse a POST naming `cancel` before validating anything, for a
hand-crafted request or a page cached from before the fix. `JUser\Form\DeleteUserForm`
already did it the right way, which is the precedent rather than an invention.

The consequence to know: **Cancel is now inert on the standalone confirmation page.** In a
modal `data-dismiss` gives it its job back; outside one it needs a cancel URL the shared form
has no way to know. Filed, and a better trade than the alternative.

#### `text-delete` threw on every exit, and the throw came after the delete

`text`'s spec set `delete_action_redirect_route` to `text-delete` — the delete route itself,
a Segment route on `/:sw_id/delete` — so `redirectAfterDelete()` asked the router to assemble
it with no parameters and got `Missing parameter "sw_id"`. Every branch of the action for a
text therefore threw: not-found, both permission refusals, and the **successful** one, after
the row was deleted and its `sch_changes` entry filed. A moderator who deleted a text saw an
error page and would reasonably conclude it had failed. Corrected to `texts`, which fixes
both front controllers at once; `/SL499999T/delete` is in the port baseline because it is the
path that proves it.

Two smaller things worth carrying forward:

- **`deleteAction()` checks existence *last*, where `editAction()` checks it first**, and the
  difference is observable twice over. A visitor without the per-row permission hears about
  permission even for a record that does not exist; and `existsEntity()` is a direct `SELECT`
  where `getObject()` goes through the projection, so a row that exists but cannot hydrate —
  142 of the 1,468 rows in `sch_roles` — gets a **confirmation** from `/roles/1/delete` while
  `/roles/1/edit` says "Role not found." Reproduced, and asserted so the two cannot drift.
- **Two wrong status codes, one of them dead.** The not-found branch sets 401 and then
  returns a redirect, which replaces it, so the observable answer is a plain 302 — that is
  what the port reproduces, not the dead line. The 401 on a failed CSRF *is* observable
  because that branch renders, and is reproduced verbatim. Both are filed rather than
  corrected inside a port.

### The library circulation surface — batch 11b, 2026-08-18

Twenty-two routes across five laminas controllers, and three more retired rather than
ported. It is the largest single batch, the first with real daily users behind it, and the
first whose *audit* changed the plan more than the porting did.

#### The audit came first, and it found six things

Every one of the thirty-one routes was fetched signed in as a library administrator before
any of them was ported. Four of the six findings are live defects that predate the
migration:

| route | what it does today |
|---|---|
| `/libraries/{id}/refresh-sort` | **writes on a bare GET** — one `UPDATE` per book, 16,383 for library 4 — with no confirmation, no token and no per-library check |
| `/borrowers` | **500**: declares an `index` action `BorrowersController` does not have, rendering `books/borrowers/index`, which does not exist |
| `/library-imports/{id}/cancel` | **404**: no `cancelAction` on the controller or anywhere in the `SionController` chain |
| `/library-imports/library/{id}` | **500 for five of the six libraries** — every one with no imports. All 22 belong to Colegio Mayor |
| `/libraries/{id}/batch-operations` | a blank page. Its `.phtml` is the four characters `<?php` |
| `/libraries/{id}/label-management` | a mockup: five `href=""` buttons and `Confirm change of 3 call numbers` with the 3 hardcoded |

**The refresh-sort finding was proven, not read off the code**, and the distinction is the
point: a book's `sort_text` was set to `ZZZ-PROBE` in the capsule and the URL fetched with
an empty body; the row came back restored. The sweep is *idempotent* — every row is
rewritten to what the current algorithm says it should be — so this was never data loss.
What it was is an unauthenticated writer and a load amplifier reachable by any registered
account, because its guard is `lib_user` and that role is `is_default = 1`. A link
prefetcher was enough.

Three routes were retired: `/borrowers` (the parent keeps serving `/borrowers/{id}`),
`library-imports/.../cancel`, and `batch-operations` with its template, its action and its
commented-out `admin_pages` entry. 140 routes, down from 142.

#### Four deliberate differences, and why each one is not a transcription error

- **`refresh-sort` answers GET with a confirmation and does the work on POST**, with the
  `administrate` check its fourteen sibling actions make and it alone skipped. The one
  contract this batch changed.
- **`data-problems` gains the per-library `show` check** its thirteen siblings make. Its
  guard is `lib_user`, so today every signed-in account can read any library's problem list.
- **The imports list renders an empty list** where laminas throws.
- **The sort diagnostic survives a sort-text format that does not parse.** Library 7's is
  `%1{author}{title}`, which expands to three printf parameters against one capture group,
  and building its filter throws — so the laminas page answers 500 on precisely the library
  whose configuration a developer would come here to diagnose. The message takes the
  filter dump's place.

Everything else is reproduced, including three things that look like defects and are:
`label-management`'s dead links, the borrower page's countdown script (`var timerText =
This page will redirect in {0} seconds.;` — a JavaScript syntax error, so the script never
parses and the hardcoded `/libraries/3` redirect never fires), and mass-checkout's borrower
list, which is the Schoenstatt Fathers at every library rather than the library's own person
provider.

#### One route stayed on laminas, and it is not "not done yet"

`library-imports/library-import/edit` is not a page with a form on it; it is the import
engine. It opens a spreadsheet, walks it against a column map and either simulates or
**performs** the import — creating, updating and inactivating books — through
`importSpreadsheetFile()`, three hundred lines living inside the laminas controller and
reachable only through it. Porting it means extracting that into a service both front
controllers call, which is a worthwhile refactor and is not a port: it moves destructive,
untested code that writes to `lib_books` in bulk. Doing it as the tail of a batch of
twenty-two routes is how a library gets silently re-imported.

**Done 2026-08-18, on its own.** See § The spreadsheet import.

#### What the batch taught the shared code

Four fixes landed in code older than this batch, each found by a page that happened to
exercise it first:

- **`App\Controller\EntityCreateController` rendered a blank 200 instead of a 403.** Its
  per-library refusal rendered `error/403.html.twig` without the `subject` the template
  requires, and Twig's `strict_variables` throws *after* the response is assembled — the
  fatal-200 wedge. Shipped since batch 9, on every create route whose visitor held the
  route guard but not the library's `administrate`. All three hand-rolled 403 renders now
  go through `App\Authorization\Denial::forbiddenPage()`.
- **`BootstrapFormRenderer::button()` moved `class` to the end** of the attribute list.
  laminas renders an element's attributes in declaration order, so `CheckoutForm`'s submit
  comes out `id`, `class`, `tabindex` and this emitted `id`, `tabindex`, `class`. It takes a
  button declaring both a class and a later attribute, and the checkout form is the first.
- **`_publication-info.html.twig` had dropped two options as unreachable.** "Nothing passes
  `showCover` or `panelTitle`" was true of the four routes ported when it was written; the
  book page passes both. Restored.
- **`formText()` had no counterpart.** `form_element()` dispatches on the element's type, so
  a `Date` element rendered `type="date"` where mass-checkout.phtml asks for `formText()`
  and gets a text box — a date picker where a barcode scanner is meant to tab through.

Two more general lessons, both about verification rather than about code:

- **`prepare()` matters on a `Collection` and on nothing else so far.** No ported controller
  had needed it; mass-checkout renders zero rows without it, because `prepare()` is what
  materialises the `count => 4` fieldsets.
- **A page that introduces a phrase does not capture reproducibly.** See the note in the
  known-differences section above.

### The spreadsheet import — 2026-08-18

The one route batch 11b left behind, ported on its own because the engine had to come out
of the controller first. It is also the first route tree here whose laminas side is
**deleted** rather than shadowed: `Books\Controller\LibraryImportsController` and its six
view scripts are gone, the entity spec's `sion_controllers` and `controller_services` keys
with them, and the `library-imports` route tree no longer names a `controller` at all. The
routes stay because `laminas_path()` is how a Twig template addresses a route and
BjyAuthorize's guards are keyed by route name.

That deletion is a departure from the rule the other 92 ported routes follow — laminas
code stays, Symfony shadows it — and the reason is what the code was. Keeping it would
mean maintaining a second copy of an engine that creates, updates and inactivates books in
bulk, reachable by flipping one environment variable, against a front controller
production has not used since 2026-08-11. The precedent is the v1/v2 API, deleted rather
than ported for the same kind of reason.

#### How a port of destructive code was verified without a rendering diff

`tools/port-baseline.php` is the usual oracle and it is the wrong one here: the page is
deliberately different, so a byte diff would be 5 MB of intended change with any real
regression buried in it. The check that replaced it compares **plans, not pages**.

The old engine's output was recovered by fetching the old edit page for the two imports
whose spreadsheets are still on disk and parsing its table back into rows — 11,381 for
import 3, 11,919 for import 1 — then the new engine was asked to plan the same files and
the two were compared action by action, barcode by barcode:

| | import 3 | import 1 |
|---|---|---|
| create | 1 = 1 | 8 vs **9** |
| update | 10,091 = 10,091 | 8,417 = 8,417 |
| inactivate | 1,281 = 1,281 | 3,492 = 3,492 |
| error | 8 = 8 | 2 vs **1** |

Identical everywhere except one row of import 1, and that row is the point: the old engine
planned a **new book with barcode 0** for a row whose barcode cell was blank, because
`(int) $cell` ran before the `is_numeric()` meant to catch it. The new engine calls it an
error and imports the rest of the file.

Neither of those spreadsheets can be a committed fixture — `data/import/` is gitignored —
so this ran by hand and its result is recorded here. The committed tests use small
fixtures under `test/Integration/fixtures/import/`.

#### Round-tripping a library is the strongest test available

Export Colegio Mayor's 10,874 active books through the new template writer, upload the
file back, and the plan should be empty. It was not: **879 changes**, in two groups.

285 were trailing whitespace — the export trims cells, 285 stored titles and authors have
a trailing space. `LibraryImporter::same()` now compares trimmed, because a difference
invisible in both the spreadsheet and the catalogue is not one worth showing or making.

The remaining 326 are the real finding, and they are not a defect: a row carrying a
**Literature ID** takes its title, author, year, publisher, place, pages, language and
ISBN from the linked literature record rather than from the spreadsheet. 459 of Colegio
Mayor's books are linked and 326 differ from their record. That is what linking means and
it had never been written down anywhere a librarian would see it; the review page now
counts those rows and says so. Nothing but a round trip would have surfaced it.

#### What it cost the shared code

- **`form_open()` emitted no `enctype`.** No form had ever needed one, so a browser would
  have posted the upload as `application/x-www-form-urlencoded`, PHP would have populated
  no `$_FILES`, and the file would simply not be there — with no error anywhere, on the
  one form whose entire purpose is the file. The attribute is now emitted when the form
  declares one.
- **`BootstrapFormRenderer` had no `file` input type.** Unknown types pass their
  attributes through unfiltered, which happened to be harmless, but `value` was set on
  every input and `FormFile` emits none. Transcribed from `FormFile::$validTagAttributes`
  and pinned by `BootstrapFormRendererTest` like the other five.
- **Two PHP 8.5 deprecations** in code this exercises: `unserialize(null)` in
  `getLibraryImports()` (one of the fourteen rows has no column mapping) and
  `isset($array[null])` in `processPublicationRow()` (most publications have no format),
  the second firing once per publication row. Invisible in production, which narrows
  `error_reporting`, and noisy in every console run.

### The authentication surface — batch 13, 2026-08-21

Four routes: `/user`, `/user/login`, `/user/verify`, `/user/logout`. The flow every human
uses, and the only place on this site where being wrong is not recoverable from a browser.

**`JUser\Controller\LoginController` is kept**, unlike every previous batch. The laminas
routes stay declared either way, so deleting the four declarations in
`config/symfony/routes.php` puts laminas back in charge of the sign-in flow — a one-line
rollback that needs no deploy of new code. Deleting the controller is a follow-up once
production has run on this, and it is what unblocks JUser 3.0.0.

**Done 2026-08-21, and it removed the rollback: `JUser\Controller\LoginController` is
deleted.** With it went its factory, four view scripts, JUser's whole `view/` tree, and the
module's `controllers`, `controller_plugins` and `view_manager` config — JUser registers
nothing with laminas-mvc any more. Every previous batch could be undone by deleting route
declarations; from here, undoing any of this surface is a deploy.

Two things landed here as a consequence, and one of them is the kind of ordering bug this
migration keeps producing.

**`Application\Session\SessionBootstrap`** starts the session, attached through
`config/application.config.php`'s `listeners` key at priority 10000. The obvious home was
`Application\Module::onBootstrap()` and it is wrong: module hooks run in
`config/modules.config.php` order at one priority, `JUser` sat at 42 and `Application` sits
at 47, and **`SionModel` at 43 calls `Authorize::getIdentity()` in its own hook** — which
triggers `Authorize::load()` and bakes the identity's roles into the ACL for the whole
request. Started after that, it bakes `guest`, and every `isAllowed()` on the request answers
anonymous while nothing fails. It would also not have shown up in any test: under the Symfony
front controller `App\Http\SessionListener` starts the session before `LegacyBridge` builds
the laminas application, so module order is invisible there. The path it protects is
`SYMFONY_KERNEL=0` — the documented rollback, i.e. the path that has to work when something
else has already gone wrong.

**`zfcUserAuthentication()` became `identity()`** at its two call sites in
`module/Schoenstatt/src/Controller/`. Measured rather than assumed: the plugin resolves an
`AuthenticationService` whose storage is `JUser\Authentication\Storage\SessionUser`, the
same object the old plugin wrapped. Both of those actions are rollback-path code anyway —
`/movement` and `/associations` are Symfony-served.

**`GdprStrategy` is `onFinish()` only.** The `onRoute()` route-match swap went with
`LoginController`, along with `IndexController::signInNoCookiesAction()` and its `.phtml`.

**Done 2026-08-21: the switch happened.** All eleven routes of the JUser surface are served
by `JUser\Controller\*` out of the module, and this application's eight controllers, four
`App\JUser\*` support classes and eleven templates were deleted. What is left here is six
adapters in `src/JUser/Host/` implementing `JUser\Host\*`, one exception class and one
`_person-cell` template. The routes are declared by the module's own fragment, included from
`config/symfony/routes.php` in the position the eleven explicit declarations occupied, so
this file still reads as the migration status top to bottom.

The evidence that authorization did not move: `docs/acl-baseline.json` changed on **44 lines
and every one of them is a controller class name** — not one resource, role, guard or denial
style differs. And all 628 smoke tests pass against the new controllers, including the
thirteen of `AuthSmokeTest`, which drive single use, expiry, digest-at-rest, session-id
regeneration, the resend throttle, the uniform response and all four deactivation doors over
real HTTP.

Three things now live in an adapter that used to live in a controller, and each fails
silently rather than loudly: `Flash` must be one instance per request (a second
FlashMessenger drops the first's messages), `RouteResolver` must strip the locale prefix and
match on a clone with an empty base URL (or every `?redirect=` on the site is refused), and
`UrlBuilder::url()` must prime the router's request URI (or the emailed link throws — nothing
under a Symfony dispatch sets one, which is why `MailerFactory` had always done it by hand).

**The rollback is asymmetric.** Removing the fragment include puts laminas back in charge of
the four sign-in routes with no new code, because `JUser\Controller\LoginController` still
exists and the laminas routes are still declared. The seven administration routes have no
laminas controller — it was deleted when they were ported — so rolling those back is a
deploy.

**That follow-up started 2026-08-21 and runs in the opposite direction from every batch
so far.** JUser 3.0.0 is meant to drop into another application — patres, as it moves to
Symfony — so the ported code does not stay here: the eight controllers, the four
`App\JUser\*` support classes and the ten templates move *into* the module, rewritten
against six interfaces it now declares in `module/JUser/src/Host/`. That is why this
batch's port is worth reading as a specification rather than as a destination. Nothing is
wired yet and no URL is affected; JUser's own README carries the contract, and
`test/Integration/JUserHostContractTest` is its only verification while nothing
implements it.

**The safety net came first, for the first time in this migration.** The 13 tests of
`test/Smoke/AuthSmokeTest` — single use, expiry, digest-at-rest, session-id regeneration,
the resend throttle, the uniform response, and all four deactivation doors — plus the five
open-redirect cases in `UserSmokeTest` were written in the *previous* PR, before any of this
existed. Three of them failed on the first run of the port and each was a real defect.

#### Three things a transcription got wrong

**`?redirect=` refused every destination on the site.** `validRedirect()` ends in a
laminas-router match, and through `App\Laminas\ServiceBridge` that router has never seen
SlmLocale — so it rejects every locale-prefixed path. Measured: `/en/shrines` no match,
`/shrines` matches. Every `?redirect=` the guards emit carries the prefix, so a faithful
port would have signed everyone in and landed them on the home page, silently, looking
exactly like the defect fixed on 2026-08-20. `App\JUser\RedirectTarget` strips the prefix
and matches on a **clone** of the router with an empty base URL — a clone because
`App\Laminas\RouteUrl` sets the shared router's base to `/en` so that assembled links carry
the prefix, and `TreeRouteStack::match()` uses `strlen($baseUrl)` as a *path offset*. Left
shared, the answer would depend on whether a template had rendered a link first.

The same probe settled something about the open-redirect defence: `matchUrl()` accepts
`//evil.example.com/` and `https://evil.example.com/`, because `Request::setUri()` parses the
URL and the router matches its **path**, discarding the host. The router is no defence at
all; the leading-slash and `//` checks are the whole of it, and their *order* — string tests
before the match — is load-bearing.

**The emailed link had no locale prefix.** `JUser\Service\Mailer` assembles it itself with
`force_canonical`, on whatever router its factory handed it, and under a Symfony dispatch
that is the raw container router. It produced `http://host/user/verify?token=…` — the
*unprefixed twin* of a ported route, which answers a 302. A browser survives that; a mail
client that pre-fetches, a scanner, or anything that does not follow the hop does not, and
the token is single-use. `App\JUser\SignIn::mailer()` pushes `RouteUrl::router()` in
explicitly, rather than relying on something else having primed it earlier in the request.

**Two flash messages became one.** `FlashMessenger::addMessage()` calls
`getMessagesFromContainer()` the first time an *instance* is used, which moves every
namespace out of the session container into that instance and unsets it from the container.
A fresh `new FlashMessenger()` per message therefore takes the previous message out of the
session and holds it in an object discarded at the end of the request. Redemption reports
"You are signed in." and "but not there" together, so it lost the first. The laminas
controller never had this problem because `$this->flashMessenger()` is a *shared* controller
plugin. `App\JUser\SignIn` and `App\JUser\UserAdmin` both memoize one instance now — the
admin surface had the same latent bug and no path that flashed twice.

#### The consent gate, which has no listener

`Application\View\GdprStrategy::onRoute()` swapped the route match of `zfcuser/login` and
`zfcuser/verify` for the cookie explainer when the visitor had not consented. It runs on
`MvcEvent::EVENT_ROUTE`, which a ported route never reaches. Both controllers ask
`App\JUser\SignIn::wantsCookiesFirst()` first and answer with `App\JUser\CookieExplainer` —
**a 200 at the requested URL, not a redirect**, because a redirect would rewrite the address
bar of someone who had just clicked an emailed link, and the point of the gate is that the
link survives. The strategy's other half, `onFinish()`, was already reproduced by
`App\Http\GdprCookieListener`.

#### `sign-in-no-cookies` was ported, withdrawn, and then deleted outright

It had **no guard entry**, so default deny applied and it was never reachable as a URL. The
only thing that ever rendered it is the route-match swap, which works because it runs at
priority -5000 — *after* the guard has approved a different route — and which builds its
`RouteMatch` by hand rather than naming a route.

Declaring a Symfony route for it makes `tools/acl-table.php` write "no such resource —
denies everyone" into the committed snapshot permanently, so batch 13 withdrew the
declaration and filed the question "should this be public?". **Answered the next day by
deleting the laminas route too** (2026-08-21): nothing links to the URL, no visitor has ever
reached it, and granting it a public entry would have added a route to defend for no gain.
`docs/acl-rules.md`'s "reachable by nobody" list went 7 → 6 and `total routes` 137 → 136;
nothing else in the ACL moved.

The page is `templates/content/sign-in-no-cookies.html.twig`, rendered by
`App\JUser\CookieExplainer`. `IndexController::signInNoCookiesAction()`, its `.phtml` and
`GdprStrategy::onRoute()` survive for the rollback path alone — remove the four Symfony
declarations and laminas needs all three again — and they go with `LoginController`.

#### Two measurements worth keeping

**No anonymous-reachable unported HTML page is left.** Of 118 guarded laminas routes, 105
are now shadowed by a Symfony route; of the 13 that are not, the only one an anonymous
visitor may reach is `redirect-pre-april-2020-sl-id`, which renders no layout.
`ServingNoteSmokeTest` had to start signing in to observe the bridge at all, and when the
remaining twelve port, `App\Http\LegacyBridge` has nothing left to bridge.

> Numbers as measured at batch 13 and left as written, because the claim they support has
> only got stronger. As of 2026-09-08 it is **109 of 119 shadowed, with 10 unshadowed
> guarded routes**, and `redirect-pre-april-2020-sl-id` is still the only one an anonymous
> visitor may reach. See [What is left, and in what order](#what-is-left-and-in-what-order)
> for the current classification — and note that "when the remaining twelve port" was
> already too optimistic when written: `kernel-switch` is deliberately never porting, so
> deleting `LegacyBridge` ends with a decision about the canary rather than a last port.

**The smoke suite runs in half the time**: 627 tests in 2:24, against 4:19 for the same 627
before the port. Every suite that signs in was booting laminas-mvc to do it.

#### The baseline diff

`tools/port-baseline.php` carries `/user` and `/user/login` — 24 captures across five
locales and both identities, **all byte-identical** before and after. `/user/verify` and
`/user/logout` cannot be in that list: verify needs a live single-use token the harness
cannot mint and would burn on the first of twelve fetches, and logout would destroy the
signed-in session mid-capture, making every later path read as an authorization regression.
Both are characterized end to end by `AuthSmokeTest`, which mints real tokens through
Mailpit and owns its own session.

Fifty of the 1,272 captures differ and none is an auth path: ten pages × five locales, all
of them signed-in pages reading data the suites wrote between the two captures (`users` grew
test accounts, `admin`'s translation badge went 2,546 → 2,549 as the new templates filed
their phrases, `users-create` gained the eight bytes of `checked` from the previous PR).

### The last four publication actions — batch 16, 2026-09-08

`publications/export`, `publications/prime-authors`, `publication-copy-to-main-corpus` and
`publication-create-new-edition`: the four `PublicationsController` actions still on laminas
after batches 5, 7, 8 and 9 took the publication show, edit, delete and create pages. With
them gone, every route in the `Books` module that a person can open is Symfony-served.
`publication-upload-cover` was the one publication route left on laminas — unguarded, and
reachable by nobody on either front controller — and was deleted the same evening.

Two controllers, because the four are two shapes. `App\Controller\PublicationReportsController`
renders the two whole-corpus listings — `export` through the shared `_publication-list`
partial with the four columns `export.phtml` asks for, `primeAuthors` as one row per author.
`App\Controller\PublicationDuplicateController` serves the two "make a new row from this
one" actions, each a two-element confirmation form over the same row resolution: GET renders,
a POST carrying a valid token writes and redirects, a POST without one re-renders with a 400.

#### `create-new-edition` wrote on a plain GET, and the port is what found it

`createNewEditionAction()` was `createEntity()` and a redirect to the new row's edit form —
no method check, no token, no confirmation. That is exactly the shape the copy action had
until 2026-08-14 (see "The action behind it copied a publication on a GET" in
[history.md](history.md)), and the same hazard: nine effective roles hold `pub_moderator`,
and a browser prefetching the "Add another edition" link a moderator had merely hovered over
was enough to file a publication. Nothing measured it because nothing pointed a crawler at it
— the button is gated on the route resource — so it sat there from 2020 until the port read
the action.

**Fixed on both front controllers, not just the ported one.** The field list moved to
`PublicationsTable::createNewEdition()`, the laminas action became a confirmation over
`Books\Form\CreateNewEditionForm` (the same two elements and 900-second token as
`CopyToMainCorpusForm`), and `create-new-edition.phtml` — **an empty file since 2020**,
because the action never rendered — got its first content. This is the "editing the laminas
action" the porting rules say to avoid, and it is done here on purpose: a write-on-GET on the
rollback path is a security fix, not a reproduction, and the copy action set the precedent.
`test/Smoke/PublicationActionsSymfonySmokeTest` takes the publication count before and after
the GET, because "the confirmation rendered" and "the GET wrote nothing" are different claims.

#### Two things the Symfony side does differently, both on purpose

- **Both actions ask the row's `show` permission.** The copy action always did, by way of
  `parent::showAction()`; the new-edition action never did — `getEntityObject()` makes no
  ACL check — so a moderator refused a publication's own page could clone it into an edit
  form and read every field there. `App\Sion\EntityShow::row()` is the half of `load()` that
  answers existence, projection and the per-row check, split out for this batch; `load()`
  now calls it.
- **The confirmation registers no visit.** The copy action's did, as a by-product of reusing
  `showAction()`, and built a comment form nothing rendered. A confirmation is not a page
  view of the publication. The baseline diff cannot see this one — visit counts are one of
  the two things it normalizes away — so it is stated here.

#### Verifying it

`tools/port-baseline.php` compared the four paths across five locales, the unprefixed form
and both identities — **48 responses, all identical** once normalized. The anonymous half is
the sign-in redirect on every one; the signed-in half is the four pages, and two of them are
the largest responses the tool has ever compared: `/literature/export` is **5.2 MB** per
locale (10,166 rows through the `_publication-list` partial) and `prime-authors` is 3.6 MB.
Both confirmations came out identical too, `&nbsp;` included — the whitespace trap batch 14
hit was avoided by writing the control in from the start.

The four paths are in the tool's list now, at the top, and they could not have been listed
before this batch: a laminas capture of `/…/create-new-edition` would have created twelve
publications. That is stated in the list itself, because the next person to add a path there
should ask the same question of it.

The whole-site run that produced those 48 also reported 355 differences among the other
1,176 responses. None of them is on a path this batch touched — the 355 are on the earlier
batches' pages, which this branch changed nothing in (the `_publication-list` partial the
export reuses is untouched, and the `EntityShow::load()` refactor is a pure extraction) — and
the whole-site total was already noisy when batch 14 ran the tool on 2026-09-08 for reasons
that predate this branch. That total was **not** re-measured against `master` here, so it is
recorded as an observation and not as a baseline. Read a run by path, not by the total.

### The translation-administration surface — batch 14, 2026-09-08

The three reachable `jtranslate/*` routes: the worklist at `/admin/translations`, the phrase
form, and the delete confirmation. `jtranslate/phrase` is their parent, `may_terminate =>
false`, and not a page — so there is no fourth.

**Declared by the module, like JUser's eleven.** `module/JTranslate/config/symfony-routes.php`
returns a closure that `config/symfony/routes.php` calls back into, so this file still reads
as the migration status top to bottom while the paths and controllers live with the code that
serves them. What the application adds on the way through is the half only it knows: the ACL
resource each guard entry lives under, and the `JTranslate` text domain.

The shape is batch 13's, one size smaller: `JTranslate\Page\PhraseAdmin` for what all three
open with, `JTranslate\Host\{FlashInterface,Severity,UrlBuilderInterface}` for what the module
needs from a host, `JTranslate\Twig\JTranslateExtension` for `@jtranslate/…` and
`jtranslate_layout`, and three templates. The laminas controller and its three `.phtml` were
**deleted with the switch**, so rolling this back is a deploy.

Three things are worth knowing before touching any of it, and the first two are the module's
own decisions rather than this application's.

**`JTranslate` stays a laminas module, and its `onBootstrap` stays.** Unlike JUser 3.0.0, the
contract here takes no package out of `require`: the translator listener, the validator
translator, the view helpers and `nowMessenger` are all still laminas and all still needed —
the *reader* side of translation is laminas' on both front controllers. What the port buys is
that the GUI renders under a front controller that has none of them.

**Its `Severity` enum is a second copy of JUser's, deliberately.** JUser depends on JTranslate
(for `TranslatableMessage`, among other things), so JTranslate cannot depend back, and a
shared enum would have to live somewhere neither owns. `test/Integration/JTranslateHostContractTest`
pins both against the laminas flash namespaces *and* against each other, because the failure
mode is silent: a flash crosses a redirect in the session, and every successful write on this
surface redirects.

**One `FlashMessenger` per request now means per request and not per module.** Both modules'
`Flash` adapters share `App\Laminas\HostMessages`, which holds the memo that used to live in
`App\JUser\Host\Flash`. Two instances lose one of two messages — measured on redemption in
batch 13 — and one-per-module is one too many the moment a second module serves pages here.
`App\Laminas\HostUrls` was extracted in the same pass and for the same reason: the
`force_canonical` request-URI priming is a fact about this application, and it should be
written once.

#### What the port found, and none of it was in the GUI

**The catalog export was writing to the wrong directory on every Symfony-served write, and
had been since the v3 API shipped.** `TranslationsTable::setUserModules()` decides between
`module/<M>/language/` and `language/<M>/`, and the only thing calling it on this front
controller was `App\Laminas\TranslatorConfigurator` — which runs when something asks for a
*translator*. A write that succeeds **redirects**, so nothing renders, nothing translates, and
the map was empty: every module text domain's catalog went to `language/<M>/`.

It reads as harmless, which is why it survived: both directories are registered as *read*
paths and `language/*` is registered last, so the misplaced file even wins. The damage is the
**pair** — `bin/console jtranslate:export-catalogs` rewrites the module copy, a GUI or API
write rewrote the other, and a translation edited or deleted through one goes on being served
from the copy the other did not touch. `App\Laminas\TranslationsTableConfigurator` is the fix:
a delegator on the table, so the guarantee no longer depends on what happened to be built
first. `PhrasesApiV3SmokeTest::catalogFor()` had been written against the wrong location and
asserted it faithfully.

**A fossil catalog was translating a page the database cannot.** Removing the stray
`language/Schoenstatt/it_IT.lang.php` from the capsule turned
`BreadcrumbDataLabelsSmokeTest`'s Italian shrine case red: it expected "Santuario di
Schoenstatt Mont Sion Gikungu", and that translation is in **no** database row — the kind
label `Schoenstatt Shrine` carries de/en/es/pt and no `it_IT` in either of its two duplicate
phrase rows. The file was a snapshot from an era when it did. So Italian shrine names render
in English, on production too, and the capsule had been lying about it for as long as the
fossil sat there.

**`error_log()` writes nothing in the capsule.** `log_errors` is `Off`, so the module's
"could not compile translation files" line — and every other `error_log()` in the tree — is
discarded. That cost half an hour of this port: the export was throwing, the controller
reported it correctly, and the reason was invisible. The ported code logs through
`Psr\Log\LoggerInterface` instead, which lands in `data/logs/`.

**The capsule's `module/*/language/` directories can end up root-owned**, because
`docker compose exec` runs as root and `jtranslate:export-catalogs` writes as whoever ran it.
`www-data` is remapped to the host uid, so a root-owned catalog directory makes every
web-served export fail with `Permission denied` — the same family as the root-owned compiled
Twig in CLAUDE.md. Run it as `docker compose exec -u www-data`.

#### Verifying it

`tools/port-baseline.php` compared all seven URL/state combinations across five locales and
both identities. The two `edit` renderings, both `delete` confirmations and both not-found
redirects came out **identical**; the listing was identical on all **3,287** rows the two
captures shared, in both its filtered and its `showAll` state. The Symfony capture had 25 rows
the laminas one did not, every one a phrase *discovered while the captures ran*.

Two differences were found and fixed rather than accepted, both whitespace beside a `&nbsp;` —
it decodes to U+00A0, which the harness deliberately does not collapse, so a newline next to
one renders as a space laminas does not emit. One difference stays and is not the port's: an
anonymous request to a guarded ported route keeps the query string in `?redirect=`, where
BjyAuthorize dropped it. That is better, and it works because `App\JUser\Host\RouteResolver`
resolves a path with a query string — `test/Smoke/TranslationSmokeTest` pins it.

**The tool itself could not run when this batch started**, and had not been able to since
batch 13: `capture laminas` signs in by POSTing `/en/user/login`, which is Symfony-only now
and answers 404 under `SYMFONY_KERNEL=0`. It has a `session` mode as of this batch — sign in
once on the default front controller, then flip — which works because the session is laminas'
on both sides, one cookie holding only a user id. Ten paths with no laminas side left were
removed from its list at the same time.

### The user-administration surface — batch 12, 2026-08-21

The seven `juser/*` routes: the index, the two create forms, the account form, the delete
confirmation, and the API-token screen with its revoke twin. Five controllers over one
`App\JUser\UserAdmin`, and **the first surface whose port deleted its laminas controller in
the same change** — `JUser\Controller\UsersController` and its six view scripts are gone,
its routes kept declared for the reason the `library-imports` tree keeps its own.

**Five controllers, not one, and that is the departure.** Batches 7 to 9 put ten, seven and
nine routes behind one class each, because on the laminas side they were one *method* reached
through many controllers. Here it is the opposite: seven routes, seven hand-written actions,
sharing almost nothing. `createAction()` checks `createEntity()`'s return value and
`createRoleAction()` discards it; `editAction()` reports a validation failure with a flash
and `createAction()` with a now-message; the delete action calls `deleteUser()` rather than
`deleteEntity()`. A route default distinguishing seven behaviours would have been a dispatch
table pretending to be a policy. The two that genuinely *are* the same shape — the two create
forms — do share a class.

**The guard is the whole protection, which is unusual here.** Every one of the seven is
`administrator`, and `administrator` is not `is_default = 1`: 2 of 292 accounts hold it. So
unlike the library surface, where `lib_user` means "signed in" and the per-row check inside
`App\Books\LibraryPage` is the real gate, there is nothing left for a controller to check.
`UserAdmin` has no `refuse()` and the absence is deliberate.

#### What the port found

Three of these were pre-existing and are fixed; the fourth is the port's own and was caught
before it shipped.

- **`/users/roles/create` had never created a role.** The `user-role` entity spec's
  `required_columns_for_creation` said `username`, `email`, `displayName` — copy-pasted from
  the `user` spec above it — so `createEntity('user-role', …)` threw
  `InvalidArgumentException: … Missing \`username\`` on every submission. Verified identical
  on **both** front controllers before changing anything, which is the part worth keeping:
  the failure predates the port and the port is only what made it testable. Fixed in the
  submodule to the role's own required column, `name`.
- **The delete confirmation named nobody**, because `delete.phtml` read `username` as a
  property off an array. See the known-differences table.
- **Two create pages served an unclosed `<form>`.** Same table.
- **The roles select would have saved one role instead of all of them**, because the renderer
  keyed the `name="…[]"` suffix on `multiple` being the boolean `true` and this form declares
  the string. Three renderer defects in that family, all in the same table, all fixed —
  and fixing them made ten *already-ported* form pages match laminas that had not before.

#### Two things reproduced that are wrong — both fixed the next day

Batch 12 reproduced both deliberately, because its only evidence that nothing *else* moved
was a byte-for-byte diff against the laminas rendering, and either change would have altered
both sides of it. The `.phtml` they reproduced are deleted, so on **2026-08-21** there was
nothing left to be faithful to and both were corrected — in their own commit, which is the
sequence worth keeping rather than the two lines it touched.

- **`editAction()` reported a validation failure with a flash and then re-rendered the form.**
  A flash is read by the *next* page, so the administrator saw a clean form with no
  explanation and then found "Error in form submission, please review." decorating whatever
  they opened next. `App\Controller\UserEditController`'s two re-rendering branches now use
  `now()`; the two that redirect still flash, which is the whole distinction — a message
  belongs in the flash bag exactly when the response carrying it is a redirect.
- **The `<h1>` was untranslated on three of these pages while the `<title>` was translated.**
  `create.phtml`, `create-role.phtml` and `edit.phtml` all did `escapeHtml($title)` with no
  `translate()` next to a `headTitle($title)` that the layout does translate, so a Spanish
  administrator read "Crear nuevo usuario" in the browser tab and "Create new user" on the
  page. Now `{{ translate(page_title) }}` in all three, and measured rather than assumed:
  **nine of the twelve non-English headings changed.** The one that did not is
  `/users/{id}/edit` in Italian — "Edit User" is a separate phrase from "Edit user" and
  carries de/es/pt but no it_IT — so asking for the translation is what puts that one on the
  worklist rather than what fills it. A BACKLOG entry had claimed "Edit User" was
  untranslated in all five locales; it was translated in four, and the difference only shows
  up if you look at the rendering instead of the entry.

#### The entity spec batch 12 did not read, and the live bug in the next one over

The `user` entity spec named three of another entity's routes: `show_route => 'juser/user'`,
`edit_route => 'association-edit'`, `create_action_redirect_route => 'association'`. Batch 12
filed them as dead-for-this-entity rather than fixing them. Reading them properly on
2026-08-21 turned up something the filing had got wrong and something worse next door.

**A `may_terminate => false` part route does not assemble to a URL nothing serves — it
throws.** `Laminas\Router\Http\Part::assemble()` raises `Part route may not terminate`. So
"assembling `juser/user` produces `/users/5`, which matches no route" was wrong in the
direction that matters: anything formatting a `user` entity as a link was a **500**, not a
dead link. Two accidents hid it — nothing formats a `user` (its `report_changes` is off and
`sch_changes` holds no `user` row), and `route/juser/user` *is* an ACL resource, so
`isActionAllowed('show')` refused for an anonymous caller before the assemble could run. An
administrator, who is allowed, would have met the exception.

**`dictionary-entry` has the identical defect and is not protected by either accident.** Its
`show_route` was also `dictionary/entry`, also `may_terminate => false`; `route/dictionary/entry`
is *not* an ACL resource, so the ACL raises on the unknown resource and the catch in
`isActionAllowed()` assumes "allow"; and `report_changes` is on, so `/sm/view-changes` formats
its 2,711 change rows. Only arithmetic kept the page up: it shows `changes_max_rows` = 500 and
the newest dictionary-entry change was **878th by recency**. One edit to any entry would have
taken `/sm/view-changes` down for every moderator holding `view_changes`.

Six fields across three specs failed to assemble; all six are fixed and
`test/Integration/EntitySpecRoutesAreAssemblableTest` is the guard. It assembles rather than
checking that a route name exists, because **all six names existed** — what was wrong was the
pairing of the name with the key the spec offers it, and `assemble()` is the only thing that
knows about that pairing.

#### One trap, and it cost a debugging session

`layout.html.twig` reads `page_title` **without an `is defined` guard**, and Twig runs with
`strict_variables`. The delete page has no `headTitle()` at all on the laminas side, so the
obvious reproduction is to omit `page_title` — which renders a **200 with an empty body** and
nothing in any log. That is the fatal-200 wedge CLAUDE.md warns about, reached from the one
direction the warning does not describe: not a wedged app, just a page that declined to set a
variable the chrome requires. Pass `''`.

### The create surface — batch 9, 2026-08-15

**Nine of the sixteen `*/create` routes**, on `App\Sion\EntityCreate` behind
`App\Controller\EntityCreateController` — the third verb of the same shared action, after
edit (batch 7) and delete (batch 8).

| ported | left on laminas |
|---|---|
| `associations/create`, `assignments/create`, `roles/create`, `books/create`, `collections/create`, `libraries/create`, `music/create-composition`, `publications/create`, `dictionary/create` | ~~`persons/create`~~, ~~`texts/create`~~ (both ported by batch 10), `juser/create`, `juser/create-role`, `library-imports/library/create`, `publication-create-new-edition`, `events/create` |

#### Two of the seven are left out because they are broken, not because they are hard

> **Both were fixed and ported the same day** — see "The last two create routes — batch 10"
> below. What follows is how they were found and what they were, which is why the section
> stays rather than being edited away.

Both measured against the capsule on 2026-08-15 by posting a scraped, valid form. Porting
either as they stood would have meant reproducing the breakage in new code, which is not what
a porting batch is for.

- **`/persons/create` answers 500.** `SpousePersonId` receives `''` from a form where no
  spouse was chosen and MariaDB refuses the integer column. The *edit* form posts the
  identical value and saves, which is the whole finding: `updateEntity()` writes only the
  columns that changed, and `createEntity()` writes them all. So a person without a spouse —
  most people — cannot be created at all, and the edit surface gives no hint of it.
- **`/texts/create` creates the row and then loses the redirect.** `EventTextTable` reads
  `$entityData['kind']` off the *existing* row, and on a create there is no existing row; the
  resulting warning reaches the page before the `Location` header can, so the moderator sees
  a 154-byte blank page and the text is silently saved. Two clicks make two texts.

The other five are shape, not breakage: `juser/create` and `juser/create-role` never call
`createAction()` at all, `library-imports/library/create` is an import simulation with two
overridden hooks, `publication-create-new-edition` is its own action, and `events/create` is
unreachable three times over — `create_action_form` commented out, a
`create_action_valid_data_handler` naming a method that exists nowhere, no template, and no
guard entry.

#### What the create verb does that the other two do not

- **There is no ACL check in `createAction()`.** `showAction()`, `editAction()` and
  `deleteAction()` each open with `isActionAllowed(...)` against the row's own
  `acl_resource_id_field`; the create action has no such call and `Entity::$isActionAllowedPermissionProperties`
  has no `create` entry, because there is no row yet to carry a resource id. A create page is
  guarded by its **route guard alone** — plus a hand-written `isAllowed('library_' . $id,
  'administrate')` in `BooksController` and `CollectionsController`, which is declared per
  route here as `LIBRARY_PERMISSION` rather than inferred from the presence of a
  `library_id` parameter. `library-imports/library/create` has such a parameter too and is
  not in this batch, which is exactly why inferring would have been wrong.
- **`libraries/create` is library-scoped and carries no library.** The record being created
  *is* the library, so `LibraryScopedForms::formForLibrary()` takes a nullable id and skips
  the collection lookup — which is what `LibraryFormFactory` does with `if (isset($libraryId))`.
  Its guard also admits `guest`, and `guest` is not one of the four default roles, so that
  page really is reachable signed out. Unchanged by the port and asserted rather than assumed
  in `test/Smoke/CreateSurfaceSmokeTest`.
- **Three routes prefill from the query string**, which the edit surface has no analogue for.
  `association` takes five hints, `assignment` two and `book` one; the interesting one is
  `?roleId=`, which has to find the role's association first, set that, narrow the role
  select's options to it and only then set the role — because in a browser that select is
  populated by JavaScript from the association.
- **The create templates `extends` their edit twins.** On laminas the two view scripts share
  one `fields-partial.phtml` and take their assets from it, so inheriting the assets and the
  inline scripts is the faithful arrangement rather than a shortcut; each create template
  overrides only `content`. `assignment` is the exception and overrides three blocks, because
  its create page really does load three assets its edit page does not and runs a different
  script — minified, through `jshrink`, the only page in the application that does.
  `App\Twig\ScriptExtension` reproduces that as `minify_js`.

A consequence worth knowing: a broken `extends` target is a fatal-200 that **compiles
perfectly**, because Twig resolves a parent at render time. `TemplatesCompileTest` grew a
second assertion for it in the same commit.

### The last two create routes — batch 10, 2026-08-15

**`persons/create` and `texts/create`, the two batch 9 left behind because they were broken
on laminas.** Eleven of the sixteen now. The port itself was the small half; what took the
work was the three defects underneath, which are worth reading as three *shapes* rather than
three bugs, because each has a guard now and each was a singleton in the application when
the guard was written.

| shape | the instance | what fixed it | what guards it |
|---|---|---|---|
| `''` into a column that cannot hold one | `person.spousePersonId` — a `Select` with an `empty_option` and an input filter of `['required' => false]` and nothing else | `ToInt` then `ToNull` in `PersonForm`, the pair every other nullable-id select already carried | `test/Integration/EmptyStringToTypedColumnTest` |
| an unguarded `$entityData` read in a preprocessor | `EventTextTable::preprocessText()` reading `$entityData['kind']`, which does not exist on a create | keying the clause on `legacyFile` instead | `test/Integration/PreprocessorCreateSafetyTest` |
| `null` into a `NOT NULL` column, because the template never renders the field | the four patres date fields on `PersonForm` | already fixed in batch 7, on the Symfony side only | `test/Integration/PortedTemplatesRenderEveryNotNullFieldTest` |

#### Each fix was audited across the whole application before it was made

That is the part worth repeating rather than the fixes. The first one looked like it might
need a change inside `SionTable::createEntity()` — the broad fix, shared with patres — and
the audit is what made that unnecessary: **74 elements in the application can put an empty
string somewhere non-string, and `spousePersonId` was the only one that let it through.**
Thirty-eight of the rest are checkboxes, which render a hidden companion input and post
`'0'` or `'1'`, never `''`; every other select and every free input already carried `ToNull`,
`ToInt`, `ToDateTime` or a validator that refuses. A one-line form fix, not a change to
shared infrastructure.

The second was audited the same way — `preprocessText()` is the only one of the nine
registered preprocessors that read `$entityData` without a guard; the other two that read it
at all use `isset()`.

#### Why the text fix keys on `legacyFile` and not on `kind`

Because guarding the array read would have fixed the blank page and left the worse half in
place. The clause said "do not render markdown to HTML for a jk-text", and **every text is a
jk-text now that the blog is gone** — so editing a text's markdown never regenerated its
HTML, and the show page renders `htmlText` and nothing else. The form's main field had no
visible effect at all, for 2,753 of the 2,757 rows.

Dropping the clause was measured before it was rejected: regenerating from the stored
markdown would change the visible text of **2,740 of the 2,756** rows that have any, several
to twice the length. The imported HTML is a genuinely different document, which is the point
of `importJkTexts()` reading paired `.md` and `.html` files.

`legacyFile` separates the two cases exactly, and the data says so rather than the naming:
**all 2,753 imported rows carry one and all 4 rows authored in the application carry none.**
So an imported text keeps the HTML it was imported with — byte-identical behaviour for every
row that exists — while a text written here renders its markdown, on create and on every
later edit.

#### The laminas person form is still broken, deliberately

`fields-partial.phtml` does not render the four patres date fields and `_person-fields.html.twig`
does. Batch 7 fixed the Twig side and left the `.phtml` alone on the grounds that production
serves Symfony and a laminas view script for a ported route is dead code; porting
`/persons/create` applies that same precedent a second time rather than inventing a new one.
The `spousePersonId` fix is in the *form*, so that half is fixed on both front controllers —
which matters, because the person edit page is reached through both in principle.

#### Two things the port had to reproduce that no earlier create route needed

- **A `create_action_valid_data_handler`.** `person` is the only entity that declares one.
  On laminas such a handler *replaces* `createEntityPostFormValidation()` wholesale, but
  `PersonsController::createPerson()` is a copy of the method it replaces plus one rule —
  a first name or a last name is required, and neither field is required on its own — so
  `EntityCreateController::VALID_DATA_RULE` declares the rule and keeps the shared write path.
  Reading the original is instructive: it calls `redirectAfterCreate()` **without returning
  it**, and `createAction()` discards the handler's return value on purpose. The redirect
  works anyway because laminas's `redirect()` plugin mutates the shared response object, so
  the create page renders its own body underneath a 302 nobody returned.
- **A redirect built from the submitted data.** `TextsController::redirectAfterCreate()`
  derives the identifier from the insert id and the slug from `$data['title']`, never loading
  the row it just wrote — where its `redirectAfterEdit()` sibling reads the updated row. Both
  are reproduced as separate branches.

#### A capsule trap this batch hit, which costs an hour if you have not seen it

`/persons/create` answered a **0-byte 200** after a template edit that was correct. The
compiled Twig in `data/cache/twig/` was **owned by root** — left by an earlier
`docker compose exec` without `-u` — and Apache runs as `www-data`, so `auto_reload` detected
the change, tried to rewrite the compiled file, failed silently, and served the stale one.
The reported error even named the *old* line number, which is the tell. `chown -R
www-data:www-data data/cache/twig` inside the container. `TwigFactory` documents the case
where the cache *directory* is unwritable and falls back cleanly; an unwritable individual
file inside a writable directory is the case it does not cover.

### The v1 and v2 API — retired, not ported, 2026-08-14

All 26 `/api/v1` and `/api/v2` routes were deleted. This is the first batch that removed
a surface instead of moving it, so the interesting part is not how it was ported — it is
how the decision was made, because "is anything still calling this?" is the question every
remaining laminas route eventually poses.

**The evidence was eight years of Apache access logs**, not reasoning about the code.
`~/logs/access.log*` on the production host goes back to 2016-04-17, which is what makes
absence of traffic mean something. Extracted with a `grep -F '/api/v'` over the whole
rotated set — 10,449 lines — and read per client rather than per endpoint, because the
volume is overwhelmingly scanner noise: Kubernetes secrets, GitLab `/api/v4`, GraphQL
probes, `/api/v1/.env`. What survived that filter was the complete list of programmatic
callers the API has ever had:

| client | endpoints | last seen |
|---|---|---|
| Google Apps Script | `v1/dictionary` PUT+POST, `dictionary/slugify-terms`, `v1/libraries/{id}/books` GET+PATCH, `v1/books`, `v1/literature` | **2022-11-05** |
| an Android app (`Dalvik/2.1.0 … SM-G9650`) | `v1/associations/findByKind` | **2019, that year only** |
| Insomnia / Postman | `users/login` | last success 2022; a 401 on 2025-08-28 |
| `curl/8.5.0`, one IP, 27 minutes | shrines.json + findByKind, 5 locales × 2 versions | 2026-08-08 — **ours**, a `tools/port-baseline.php` capture |
| `tools/smoke-prod.sh` | the six endpoints it checked | ongoing — ours |
| `aiohttp/3.14.x` | enumerated v1 paths *from our own published `v1.yaml`*, `%5C` variants included | July 2026 — a scanner |
| crawlers (Thinkbot, Barkrowler, AhrefsBot, SEOkicks, YandexBot) | `findByKind` only | ongoing |

Two of those rows are the lesson. **Our own tooling was the loudest "client" left**, which
is how an endpoint keeps looking alive for years after everyone stopped using it — the
smoke script was the only thing that had touched `findByKindMd5` or v2's `findByKind` in
twelve months. And **the traffic that did keep arriving was crawlers following a link we
published ourselves**: `findByKind` was advertised in a translated `api_note` paragraph on
`/shrines` and `/wayside-shrines` *and* as a schema.org `Dataset` `contentUrl`, so ~270
hits a year were the web reading our own advertisement back to us. Deleting the routes
without the advertisement would have left both layouts pointing at a 404 and Google
holding an indexed dataset distribution that no longer resolves. The advertisement went
in the same commit: two Twig templates, two `.phtml` originals, and the `distribution()`
call in both `App\Schoenstatt\ShrineDatasets` and
`Schoenstatt\Controller\SchoenstattController::getShrineDatasets()`. The `Dataset` entries
themselves stay — the pages they describe still exist — they simply no longer offer a
machine-readable download.

**What answers those URLs now is a JSON 410 Gone**, from `RestApi`'s
`api-route-not-found` catch-all at priority -1000, carrying
`Link: </api/v3/schema>; rel="successor-version"` (RFC 5829). That module was kept for
exactly this: an HTML error page would be the wrong answer for a withdrawn API and for
any unknown `/api/v3` path too.

**The Link names the schema document because the first version named a 404.** It shipped
as `</api/v3>`, which reads as the obvious successor and is not a route: `/api/v3` 302s
to `/en/api/v3` and arrives at this same handler's 404 branch. The advertisement was
therefore self-defeating in the one situation it exists for — a caller that followed it
learned nothing, and a caller that could not follow it would conclude the API was gone
entirely. `/api/v3/schema` is public, answers 200, and enumerates both resources with
their endpoints and required roles. The lesson generalises past this header: **an
assertion that a Link header is well-formed does not assert that it works**, so both
`smoke-prod.sh` and `ApplicationSmokeTest::testRetiredApiPathIsAJsonGone` now parse the
URL out and fetch it.

**410 rather than 404, and scoped rather than global.** A 404 says "no such thing here";
a 410 says the resource existed and is permanently removed, which is the signal that gets
an indexed URL *dropped* rather than merely demoted — and two of these URLs were
published as a schema.org `Dataset` distribution that Google holds today. But the 410
matches `#^(/(en|de|es|pt|it))?/api/v[12]([/.]|$)#` and nothing else, because the
alternative is worse than the problem: an unknown path in a *live* API is a typo, and
telling a caller its endpoint is permanently gone when it has merely misspelled one is a
lie the caller acts on. So `/api/v3/phrasez` and `/api/v9/associations` keep their 404,
and `test/Smoke/ApplicationSmokeTest` asserts both halves — the 410 set *and* the 404 set
— because the scoping is the part that can silently widen. The `[/.]` alternative is what
catches `/api/v1.yaml`, the OpenAPI document, which was a static file rather than a route.

The 404 body is byte-identical to what it was before the change, measured with a
four-second wait on each side to clear the OPcache revalidate window — worth doing,
because that window makes a stash/capture/pop A/B measure OPcache rather than the change.
The 410 body is the same envelope with the error text swapped, so a caller that parsed
the old shape still parses this one.

Three things came out with the routes because nothing else used them, and each was
verified orphaned rather than assumed:

- **`RestApi\Controller\ApiController`**, the base class every v1 controller extended.
  `RouteNotFoundController` extended it too, which is why it now has a factory: it used to
  inherit the response format through `$this->getEvent()->getParam('config')`, populated by
  ApiController's dispatch listener.
- **`Application\Listener\CorsListener`** and `jmikola/geojson`. The listener added
  `Access-Control-Allow-Origin` only to routes carrying a `'cors' => true` default, and
  every one of those was in the Books `api-v1` tree — so its `onFinish()` half could never
  fire again, while its preflight half would have answered a cheerful 204 to a browser
  about to receive a 404. The library was reachable only from
  `SchoenstattTable::getShrineGeoJson()`, whose two callers were the deleted shrines.json
  actions.
- **JUser's `LoginV1ApiController`** (its own PR) and the API-code half of
  `LoginTokenService` — `issueApiCode()`, `redeemTokenForUser()`,
  `getApiCodeExpirationMinutes()`. v3 token issuance is unaffected: it runs through
  `ApiTokenService` from the users screen, and `UsersController` is that path. What is gone
  is the password-for-token exchange.

**One doc claim did not survive contact with the logs.** `App\Api\BotIdentity`'s docblock
said "the mobile apps already use them" of these JWTs, and `config/symfony/routes.php`
called shrines.json "an endpoint the mobile apps poll". Neither is true: no mobile user
agent has ever posted to `/api/v1/users/login`, and the one mobile client in eight years of
logs read `findByKind` in 2019 and never returned. A plausible sentence in a docblock is
not evidence about traffic; the access log is.

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

#### Porting a route also claims its siblings' paths — the two routers disagree on ties

**Found 2026-08-13, live in production for two days, and the most transferable lesson in
this file.** These four routes are declared `/{sw_id}/{slug}` with the slug constrained
`[a-z0-9-]{1,200}`, copied from the laminas route. That pattern matches `edit`. So
`/en/SL500001C/edit` was answered by route `composition.locale` with the composition
**show page**, and the laminas `composition-edit` route was unreachable — along with eight
more:

```
composition-edit   composition-delete   text-edit   text-delete   publication-edit
publication-delete   publication-upload-cover   publication-create-new-edition
publication-copy-to-main-corpus
```

Nine guarded routes, and **none of their guards ran**. Nothing failed and nothing logged:
the page answered 200 and rendered a real entity page, which is why neither the smoke
suite nor `tools/port-baseline.php` flagged it — the baseline compares a *ported* path
against its laminas twin, and these paths were nobody's idea of ported. It surfaced only
because batch 7 added `/{sw_id}/edit` to `PATHS` and the pre-batch capture showed the
Symfony body matching the *show* page's byte count.

**The mechanism is a tie-break difference nobody had written down.** Both routers see the
same ambiguity — the show pattern and the verb pattern both match — and they resolve it in
opposite directions:

| router | equal-priority tie goes to |
|---|---|
| `Laminas\Router\SimpleRouteStack` (a `Laminas\Stdlib\PriorityList`) | the **last**-registered route |
| `Symfony\Component\Routing\Matcher\UrlMatcher` | the **first** declared route |

Proved against the real config rather than read off the internals: `composition` is
declared at `module/Books/config/module.config.php:1436` and `composition-edit` at 1453,
both patterns match `/SL500001C/edit`, and the laminas router answers `composition-edit`.
So laminas' *config order* protects the verb route and Symfony's protects the show route,
and porting the parent without its children silently inverts which one answers.

`App\Sion\ReservedVerbs` is the fix — the show routes' slug carries an anchored negative
lookahead over the five verbs any laminas `/:sw_id/<verb>` route claims — and it is
duplicated from the laminas config the way `App\Locale\Locales` duplicates the locale
aliases, with `test/Integration/ReservedVerbsTest` as the drift guard.
`test/Smoke/ReservedVerbRoutingSmokeTest` asserts the consequence over HTTP.

Three things to carry forward:

- **Before porting a route with a placeholder, enumerate its siblings.** Anything sharing
  the parent's path shape is a candidate for being swallowed, in whichever direction the
  ported route is declared. This applies to any laminas parent/child pair a batch splits
  across front controllers, not just to these five verbs.
- **`tools/acl-table.php` catches it now, and did not when this bug shipped.**
  `shadowedBySymfony()` used to pass the composed laminas *pattern* to
  `UrlMatcher::match()` — the literal `/:sw_id/edit` — so no parameterized laminas route
  could ever be reported as shadowed, and 0 of its 31 shadowed rows had a parameterized
  path. Fixed 2026-08-14: patterns are instantiated into concrete probe URLs, and the
  check also runs in reverse, resolving every URL Symfony owns through the real
  `TreeRouteStack`. 52 rows now, 19 parameterized. **Its silence about a route means
  something again** — a route it cannot decide is listed as uncomparable rather than
  skipped, and that list is asserted empty by
  `test/Integration/AclShadowCompletenessTest`.
- **`association-delete` looked like a tenth case and was not.** It answered the show page
  on *both* front controllers, because its own constraint asked for four digits where an
  identifier has six. Repaired separately on 2026-08-14 — a delete confirmation reachable
  for the first time wanted the delete path exercised, not a digit changed, so
  `test/Smoke/AssociationDeleteSmokeTest` covers the guard, the CSRF token, a real delete
  and what it orphans. It is now an ordinary member of the reserved-verb list, and staying
  reserved matters more than before: a show route swallowing `/{sw_id}/delete` would now
  hide a destructive page rather than an edit form.

**A note on the fetch/display ratio, for whoever tunes this next.** With
`changes_show_all` on, the limit applies *per table* — 6 tables × 500 = 3,000 rows
hydrated so the view can show the newest 500. That is correct rather than wasteful in
the strict sense (which table's rows win is unknown until they are merged), but a
two-pass collector — merge the change rows first, hydrate only the survivors — would cut
it by ~6×. Not done: 214 MB is comfortable, and the refactor touches `SionTable`.

## Verifying

- `php composer.phar test` — **1,323 tests, 77,565 assertions** (measured 2026-08-13 on the
  reserved-verb fix, which adds 50 of them; 19 deprecations, all of them
  `SplObjectStorage::contains()`/`detach()` inside `vendor/laminas/laminas-cache` under
  PHP 8.5, and 14 skips. The count between here and the 1,222 below is the sitemap,
  canonical/hreflang and flash-messenger work that followed batch 6; 1,222 was never
  re-measured after those. Read the exit code, not the summary word: PHPUnit prints
  "OK, but there were issues!" for a deprecation, and piping the run through `tail`
  discards the summary *and* replaces the exit code with `tail`'s, which is how a
  green-looking unverified run happened here first.) Earlier: 1,222 after batch 6; 916
  after the flip preparation on 2026-08-09; 791 after the eight routes of
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
- `test/Smoke/AssignmentsSearchSymfonySmokeTest.php` and
  `test/Smoke/Batch6SymfonySmokeTest.php` cover batch 6. The first is the larger because
  the form renderer grew five things for the advanced search, and it holds the two-sided
  pencil assertion: **`edit_pencil()` renders nothing without a session identity**, so
  headlessly every pencil is absent and "no person pencil here" would pass against a macro
  that had lost the ability to render one. Only a real `sch_moderator` session tells the
  two apart.
- `test/Integration/AssignmentsTableTest.php` covers the half of that partial which needs
  no identity — the five column branches, the active/inactive partition, and the
  empty-table gate. The partition assertion is the one that catches
  `show_active|default(true)`.
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
