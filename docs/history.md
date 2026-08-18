# Modernization history

Closed work, moved out of [BACKLOG.md](BACKLOG.md) on 2026-08-04 so the backlog
can stay a forward-only roadmap. This file preserves outcomes, corrections, and
the techniques worth reusing. The journal as originally written — every DONE
narrative in full — is preserved in git: `git log --follow docs/BACKLOG.md`
(the last full-journal version is the one this restructure replaced).

State reached by 2026-08-04: production runs **PHP 8.4.24** on a current
Laminas stack with OPcache enabled, master is fully deployed, `composer audit
--locked` reports zero advisories, and 198 tests run across three suites.

State reached by 2026-08-18: **161 Symfony-served routes shadowing 70 of the 142
laminas ones**, 72 left — measured by `tools/acl-table.php`, which is the number to
cite; prose counts in these files have gone stale twice, and this line was itself
wrong on both figures before it was checked against the tool. Production runs
`SYMFONY_KERNEL=1`. The library surface (32 routes) is the next batch.

State reached by 2026-08-08: **eighteen routes ported to the Symfony kernel**, ten of them
deployed dormant — `SYMFONY_KERNEL` is still unset in production, so every one of them
continues to render through laminas and turning them on is a `SetEnv` in
`public/.htaccess`, not a deploy. 802 tests across four suites. See
[strangler.md](strangler.md) for the mechanism and the route table.

## The navbar's library search box, ported ahead of its routes (2026-08-18)

The first piece of strangler batch 11b, done before any route: the layout branch that
swaps the navbar search from "Search contacts" to the *current library's* search. It
reads `libraryInfo()`, an MvcEvent-dependent view helper, so the Symfony layout never had
it — and because the **layout** calls it, the gap landed on every ported page under
`libraries/library/`, `books/`, `checkouts/` or `library-imports/`. Three of them, since
2026-08-09: a librarian on `/books/{id}/edit` got a box that searched contacts.

`App\Books\CurrentLibrary` answers the question from the route parameters instead —
`library_id`, else `book_id`, else `import_id` — which a Symfony request has and an
MvcEvent is not needed for. The helper stays unavailable; what changed is that the branch
depending on it no longer is.

Four things worth reusing.

**An accepted regression is a debt with interest, and the interest is the thing to
measure.** This one was accepted twice — batch 7, then batch 8 — each time for a good
local reason. What made it wrong to accept a third time was not that it got worse but
that batch 11b would have multiplied it by thirty. The general form: when the same
deferral is about to be made again, price the *next* thirty, not the one in front of you.

**Two of the three reasons given for deferring it were wrong, and only measuring showed
it.** `docs/strangler.md` said the fix "touches every ported page's layout path" and that
two pages were affected. `tools/port-baseline.php` A/B'd all 936 responses before and
after: **exactly 20 changed** — 4 paths × 5 locales, signed-in only — and each normalized
diff is two lines. It was three pages, not two, because `books/create` matches `books/`
and the count predated batch 7. A deferral's stated cost ages exactly as badly as any
other prose in these files, and nothing re-checks it, because a deferral is not something
anyone re-opens to verify.

**A before/after capture on the *same* front controller isolates a change; a
laminas-vs-symfony capture does not.** Comparing the two front controllers over the whole
corpus reports 341 differences, almost all of them pre-existing — the `//<!-- -->` script
wrapper, the bare-path redirect, and rows added by the capture harness registering its own
account. Stashing the change and capturing `symfony-before` reduced the signal to 20 files
and made the review trivial. Worth doing whenever the baseline is not already green,
which after eleven batches it is not.

**A scoping test that never exercises the scope passes anyway.** The first version of
`LibrarySearchBoxSmokeTest` proved "a non-library page still shows the contacts search"
using `/roles/create` — which has no `library_id`, so it would pass with the prefix rule
mutated to match every route on the site. Mutation testing caught it; the fix was to pick
`/collections/create/1`, a page that **names a library and is still not library-scoped**,
which is the only shape that tests the rule. Same lesson as the `form_submit` docblock
that made `PortedFormsAreSubmittableTest` pass with the fix deleted: a negative assertion
needs a case where the two candidate rules actually disagree.

One claim was withdrawn rather than kept: the Spanish assertion in that suite does *not*
detect naming the wrong text domain, because `module/Books/language/*.lang.php` carries
the same `Search %s` phrase as `Application`'s, so the swap is byte-invisible. It does
detect no translation at all, which is the failure that would otherwise ship unseen —
in English a missing translation is the source string.

## Library-surface groundwork, and a deploy that could hang forever (2026-08-17/18, DEPLOYED)

Three merged changes clearing the ground before the library routes port, plus one
that came out of a live incident. What is worth keeping is mostly the method.

**Eight admin routes retired rather than ported.** `/admin/literature-maintenance`
and its six children were the 2020 data-source migration; `/admin/maintenance` was an
unrelated person sweep. The question that dissolved them is the same one that
dissolved `/en/associations/do-work` — *what would this do if I ran it today?* — and
it goes unasked precisely because a simulate-by-default page has never hurt anybody.
The answers: 0 rows, 0 rows, 1 row, 2 rows, and 20 cover files. All three database
rows traced to a single publication (1426 → 10249), repaired by `database/db8.2.sql`,
written as a join on the merge map rather than against the three ids measured that
day, because the capsule is days behind production and a moderator can merge another
row at any time.

`/admin/maintenance` was the one worth stopping: it stripped the `priest` tag from
anyone without a `PriestDate`, on a 2020 comment's premise that *"so far, all priests
should have priestDate"*. **15 people carry the tag and 5 have no date.** A premise
stated in a comment is not a measurement, and this one had been false for years.

**A tool that documents its own blind spot still has the blind spot.** The
per-library ACL rules — the ones that decide who may borrow at each library — are
built from `lib_libraries` rows at request time, so `tools/acl-table.php` could not
list them, *and said so in its output*. That sentence was accurate and was treated as
the end of the matter. The consequence: a baseline covering every route guard and
none of the rules actually gating the library pages, with three libraries granting
`checkout` to all 34 effective roles and no diff ever showing it.
`App\Books\LibraryAclRules` reproduces the mapping now, pinned to the real provider
by a drift test. **A baseline that is confidently silent reads exactly like one that
is confidently complete.**

**Granting a role is the step that reveals whether anything is behind the door.**
Six guard entries had been "decided" in the backlog on sibling agreement — every
neighbouring route in the tree already said who should get in. Five of the six turned
out to be dead config: templates and forms commented out of the entity spec,
`enable_delete_action` commented out, and in one case (`publication-upload-cover`) a
column that does not exist in the table. A route with no guard entry is *silently*
denied, so nothing behind it has ever executed and nothing has ever failed. Sibling
agreement says who should be let in; it says nothing about whether there is a page to
see.

### Techniques worth reusing

**Mutation-test your own tests.** Used twice, and it changed the outcome both times.
`LibraryAclRuleDriftTest` survived it and is therefore trustworthy. The deploy's
`rsh-behaviour-test.sh` did not: the check that supposedly guarded piped stdin sailed
straight past both `ssh -n` and a backgrounded ssh, because it substitutes a local
stand-in for ssh and cannot see the flags at all. That split the test into two checks
and rewrote its rationale. A test written and never falsified is a hypothesis.

**When the rationale survives the mutation but the fact does not, fix the rationale.**
The same test was justified by a piece of near-universal folklore — *"a background
command in a non-interactive shell has its stdin reassigned to `/dev/null`"* — which
would have meant a backgrounded ssh silently writing an empty `.revision`. Measured on
bash 5.2: `printf x | { cat > f & wait $!; }` writes `x`. The POSIX rule does not bite
when stdin is an explicit redirection. The design was right and the reason was wrong,
which is the combination that survives review indefinitely. Both the comment and the
test now name what actually breaks those call sites: `ssh -n` and `< /dev/null`.

**Ask production, not the merge log, whether an out-of-repo repair happened.** The
cover fix was a one-off script; `public/covers` is gitignored and lives in
`shared/`, where no release reaches it. "Merged and deployed" was true and irrelevant
— the script had never run. One `curl` for the file settled it. Deleting the script
first would not have lost the script, which git keeps; it would have lost the
knowledge that it still needed running.

**A deploy could hang forever, and nothing said so.** There was no timeout of any kind
on any of the ~40 remote calls, no keepalive, and no output while one was in flight —
so a stalled step and a slow step were indistinguishable, and the only signal was a
cursor that stopped moving. It surfaced on "Warming the release", on a redeploy of a
commit that had warmed fine nine minutes earlier, in a step whose two commands take
1.64s between them. Three defences now, because they catch different failures: ssh
keepalives for a dead path, `timeout` for a healthy connection whose remote command is
stuck, and a heartbeat that prevents nothing and is the only one that would have
answered the question on the night. See [DEPLOY.md](DEPLOY.md) § When a step stops
answering.

## Symfony strangler, batch 4: eight routes, and the blog retired (2026-08-08, not yet deployed)

The public browse surface — `/timeline`, `/music`, `/dictionary`,
`/dictionary/{inLanguage}`, `/literature/150-preguntas-sobre-schoenstatt` — plus three
restricted index pages, `/associations`, `/roles` and `/libraries`. Eight routes, every
one a read-only GET; no form moved, because none can yet.

`/blog` and `/blog/posts/{sw_id}/{slug}` were ported too and then removed in the same
branch: the blog was on the chopping block and porting it was a wasted step. **The whole
feature came out** — see below — so the net change never adds it.

**The verification became a tool.** `tools/port-baseline.php` is the
both-front-controllers, five-locale, two-identity diff that batch 3 ran by hand: 300
responses per capture, raw on disk, normalized at compare time. Batch 4 finished at **257
of 300 identical**, and every one of the 43 that differ is itemized in strangler.md.

It also corrected the record. "65 of 65 responses identical" was not true as stated —
whole documents were never identical, because the Twig layout is a reproduction of
`layout.phtml` rather than a byte copy, and because two things differ between two runs of
the *same* front controller: the language chooser draws its flag at random, and
`registerVisit()` bumps a counter the page prints.

**Four defects it found, three of them pre-existing and one live in production:**

- **Every blog post was blank in Spanish, German, Portuguese and Italian.**
  `show.phtml` passed the whole post body through `translate()`; JTranslate tried to
  record the miss; `Data too long for column 'phrase'`; the exception fired on
  `MvcEvent::FINISH` and the assembled body was discarded, leaving a 200 with zero bytes.
  Moot now that the blog is gone, but the *mechanism* is not: any laminas page that
  translates a long value can do the same. A ported route cannot, because `finishUp()` is
  an MVC listener — which is also why a ported route records no missing phrases at all.
- **Every ported page's `<title>` was English in all five locales.** `headTitle()`
  translates by default and the Twig layout printed the string verbatim. Fixed in the
  layout; the dictionary page opts out because it pre-translates, exactly as its `.phtml`
  disables the translator.
- **Navigation labels were translated in the wrong domain**, so a signed-in Spanish
  visitor saw "Administración" where laminas says "Admin". The navigation helper uses
  `default`, not the page's domain. Only visible signed in — the item is ACL-gated.
- **`SionTable::registerVisit()` reads `$_SERVER['HTTP_USER_AGENT']` unguarded**, so a
  request with no `User-Agent` gets three PHP warnings printed above the doctype and loses
  its `Content-Security-Policy` to "headers already sent". Not fixed — it is a SionModel
  change — but it is why the capture tool sets a User-Agent.

Two smaller things worth keeping: `SionTable::existsEntity()` is annotated `@return
boolean` in a file that imports `Laminas\Filter\Boolean`, so static analysis reads it as
returning that *class*; and `tools/form-regression.php` sends its consent cookie with the
value `1` where the strategy wants `'true'`, so its sign-in cannot work.

### Retiring the blog

Five routes (`blog`, `blog/create`, `blog/blog-post`, `blog/blog-post/edit`,
`blog/blog-post/delete`), their controller, four view scripts, the `blog-post` entity
spec, `BlogPostUserIdFilter`, the navigation item and its database-derived branch, and
the blog logic inside `EventTextTable` — the three `TEXT_KIND_BLOG*` constants, the
`blog-post` select branch, the per-author `resourceId` generation, the draft→published
date reset, `getBlogPostSchema()` and the whole of `getRules()`.

Unlike the Bible module this was **not** self-contained, and three couplings had to be
decided rather than deleted:

- **`Books\Form\TextForm` is shared with the Fr. Kentenich texts feature** and hardcoded
  `kind = blog`, with an `Identical` validator enforcing it — so `/texts/create` stamped
  every new text as a blog post, while 2,753 of the 2,757 rows in `texts` are `jk-text`.
  Switched to `TEXT_KIND_JK_TEXT`; a behaviour change to a live form, taken deliberately.
- **`EventTextTable` stopped being a rule provider** and stayed a resource provider. Every
  rule it returned was a blog grant, but its *resources* (`txt_institute`, `txt_public`)
  are what gate the texts. Its `blog_post_*` bucketing turned out never to have fired:
  no row in `texts` has ever carried such an `AclResourceId`.
- **`Application\Controller\IndexController` lost its `EventTextTable` dependency**,
  which existed only for a five-row query the front page never rendered.

`'blog'` in the SionModel and Schoenstatt configs is a *social-link type* — an
association's blog URL, `img/blogger.png` — and was left alone.

Verified the way the Bible removal was: regenerate the authorization table and diff.
192 routes → 187, 167 guarded → 162, and **every removed row is a blog route**; the only
other line to move is `rule_providers` losing `EventTextTable`. Suite 802 → 791, still
green, PHPStan clean at level 0 across the tree and level 8 on `src`.

The `blog_administrator` and `blog_contributor` roles remain in `user_role` and are now
unused, and the three `kind = blog` rows remain in `texts`, as do their `sch_changes`
entries. Data was not touched. The consequence worth knowing is that `/blog` and each
post URL now **404** rather than redirect.

The changes log is unaffected, which was worth checking rather than assuming: every edit
to a `texts` row is logged as `text`, not `blog-post` — 11,041 rows, none of them
`blog-post` — and the `text` entity spec stays, so `formatEntity` still resolves them.

## Symfony strangler, batch 3: ten routes (2026-08-07/08, DEPLOYED dormant)

`/`, /developers, /acknowledgements, /privacy, /shrines/submitting-photos, both
`api/v{1,2}/associations/shrines.json`, /sm/phpinfo, /sm/data-problems and
/sm/view-changes. Deployed 2026-08-08; production still serves them through
laminas.

Every port was verified the same way — capture the laminas rendering *before* the
route moves, then compare byte for byte — and the technique is the reusable part:

- the five content pages' generated HTML, Spanish variant and Portuguese fallback
  included;
- both GeoJSON payloads (25,585 bytes);
- /sm/data-problems over 30 real problem rows (9,206 bytes of table body);
- /sm/view-changes in **three** configurations, because the newest 500 changes in
  the capsule dump are all `book` rows and would have proved nothing about the new
  `publication` and `role` branches. Pointing `changes_model` at PublicationsTable
  and then SchoenstattTable forces those types onto the page.

**Four pre-existing bugs surfaced, none introduced by the ports.**

- *Every ported HTML route answered 200 with an empty body.* Two
  `data/cache/twig` subdirectories were root-owned; Twig's write threw mid-render
  and the fatal handler emitted nothing. `TwigFactory` promised to degrade
  gracefully but probed only the *parent* directory — Twig creates per-template
  subdirectories itself, so one render as root poisons one permanently.
  `ForgivingCache` makes the promise true, and Twig supports it directly:
  `loadTemplate()` falls back to `eval()` and names a no-op cache as a case it
  covers.
- *A pre-Laminas session emptied every ported page.* `flash_messages()` throws on
  a `__PHP_Incomplete_Class`, and only `JUser\Module::onBootstrap()` ever pruned
  it — which a Symfony route never runs. `/en/shrines` and `/en/wayside-shrines`
  had been broken for such a visitor since the day they were ported;
  StaleSessionSmokeTest missed it because the one path it asked for was still
  laminas-served. `App\Http\SessionListener` fixes it, gated on the request
  actually carrying a session cookie so the machine endpoints still start none.
- *`RouteUrl::localized()` dropped a trailing slash*, so the front page's canonical
  and every hreflang pointed at `/en` instead of `/en/`. Latent until a ported path
  was nothing but the locale prefix.
- *`composer test` exhausted 512 MB* in the combined single-process run — on master
  too. Now passes `-d memory_limit=1G`, as `composer fuzz` always had.

**/sm/view-changes was dead and nobody knew**, because it sits behind
`sch_general_moderator`. Two bugs in SionModel: `getChanges()` filtered the entity
rows by their *database column* where `queryObjects()` matches entity *field*
names — a mismatch it answers by silently dropping the predicate, so the query
came back unfiltered and loaded 2,757 rows of an 85 KB-per-row table instead of
250 (520 MB → 66 MB); and `getAllChanges()` took no limit at all, so
`changes_max_rows` bounded only the display. Fixed first, *then* ported: a port is
verified against a laminas rendering, and there was none while the page fataled.

**formatEntity is fully reproduced** — the largest of the five view helpers a
Symfony-served route cannot call, and the one gating most remaining admin pages.
Two subtleties worth keeping:

- The name resolves to `Schoenstatt\View\Helper\FormatEntity`, not SionModel's,
  and that subclass switches on entity type *above* any `isDeleted` test while
  SionModel's `formatViewHelper` deferral sits *below* one. So a deleted role keeps
  its branch and its pencil; a deleted publication does not. Getting it backwards
  left exactly one row of 500 different from laminas.
- Its `role` branch reads `editPencil`/`showLabel`, not `displayEditPencil` — so
  `changes-table.phtml` does not turn a role's pencil off. Looks like a typo.

**The shrine GeoJSON feed is deprecated** (`Deprecation: true`, no `Sunset` —
no removal date is decided). Set in three places, because production serves the
two laminas actions and the capsule the ported controller; the retirement question
is open in BACKLOG.md and needs usage data nobody currently collects.

## Phase 1–2: time capsule and safety net (2026-08-01/02)

- Docker "time capsule" reproduces production: Apache + PHP (switchable
  7.4/8.3) + MariaDB 10.11 + Mailpit + APCu, seeded from a production dump.
  Fresh 2026-08-01 dump imported (junk tables excluded; `bib_*`, `b_*`,
  `csp_reports`, `sch_visits` schema-only — Bible/bibliography render empty
  locally).
- Three test suites, all run inside the capsule (host PHP lacks the extensions
  PHPUnit needs): HTTP smoke/characterization (`test/Smoke`, against the
  running capsule), unit (`test/Unit`, vendor-free by design — each test
  requires its class directly, so it stays valid mid-migration), integration
  (`test/Integration`, vendor-dependent, capsule-only).
- PHPStan level 0 green over a 56-error baseline; phars pinned via
  `tools/get-phars.sh` (PHPUnit 9.6 → 12.5 on 2026-08-03; CI and capsule run
  the identical phar).
- CI: PHP syntax lint (submodules included), then a continuous deploy
  rehearsal — `composer install --no-dev` from the lock + validate + autoload
  sanity + `composer audit --locked`. Moved to PHP 8.3 with a unit-suite job
  after rung 4a.

## Phase 3, rungs 1–2: latest ZF3, then Laminas (2026-08-02)

- Rung 1: Composer 1.9 → 2.10; every dependency at its latest ZF3-compatible
  release. Eliminated rather than upgraded (per "prefer dependency
  elimination"): zfr-cors → `Application\Listener\CorsListener`,
  multidots/zf3-rest-api → `module/RestApi`, talis → `Books\OpenUrl`,
  mledoze countries data vendored into JTranslate, libkml (unused),
  bjy-profiler (dev-only); roave/security-advisories superseded by Composer's
  native advisory blocking.
- Rung 2: `laminas/laminas-migration` rewrote the tree. The
  ZendFrameworkBridge module carried the not-yet-migrated third-party modules
  until rung 4a removed the last reason for it.
- Also eliminated along the way: zfc-datagrid, zf2-mobile-detect (neither ran
  since the ZF3 migration; both cap at PHP 7.x), beaucal-invalid-session
  (abandoned 2016; its 30 lines moved into `JUser::onBootstrap`),
  ocramius/proxy-manager (see "Service-graph cycles" below).

## Rung 3: abandoned-package replacement (2026-08-02/03)

- **ZfcUser eliminated ("rung 3a")**: passwordless magic-link auth implemented
  in JUser itself (deliberately not LmcUser — its password-centric surface
  would have been dead weight). Password auth removed app-wide;
  sha256-at-rest tokens; email verified on every token redemption; Mailpit
  E2E round-trip in the smoke suite.
- **IP geolocation eliminated ("rung 3b")** by product decision (carries over
  to patres): zf-snap-geoip and the discontinued GeoLiteCity.dat are gone; the
  Bible special-countries guest carve-out (CL/AR/PY) was withdrawn. Payoff:
  hydrator 4.x, form 2.17.1 (XSS advisory cleared), router 3.4.5, the real
  abandoned zend-router out of the lock.
- **PHPExcel → PhpSpreadsheet 5.9** — see "Golden-master technique" below.
- **firebase/php-jwt 6 → 7.1** (CVE-2025-45769, the last advisory ignore).
  The v7 break is config-dependent: HMAC keys shorter than the digest size
  (32 bytes for HS256) throw on every sign *and* verify. Trap worth
  remembering: php-jwt raises `DomainException` for **both** a too-short key
  and a caller's malformed token, so classifying on exception class turns
  every garbage token into a 500 — `ApiController::assertUsableCypherKey()`
  checks our own key up front instead, keeping client faults at 400. Also
  fixed in passing: `?token[]=x` reached `JWT::decode()` as an array →
  anonymous-triggerable TypeError 500.
- **All mail consolidated onto symfony/mailer 7.4** (PR #17 + submodule PRs,
  merged and deployed 2026-08-03). Replaced two and a half stacks: JUser's
  SwiftMailer (abandoned 2021), the exception notifier's laminas-mail
  (abandoned; was the last laminas-side PHP 8.4 cap), and
  `SionModel\Mailing\Mailer`'s dependency on AcMailer — a service that was
  configured but **never installed** here, so the book-notices path had been
  dead for years. One shared transport (`SionModel\MailTransport`, built from
  the top-level `smtp_options`, Sendmail fallback) now feeds sign-in mail, the
  exception notifier, and the resurrected book notices (first `Success` rows
  in `mailings` since 2018). Latent bug fixed: `inlineEmailStyles()`'s default
  CSS path never existed — now `public/css/email-default.css`, throws if
  unreadable (patres must supply that file when it converges).

## Rung 4a: PHP 8.3 + the big stack bump (2026-08-02/03, DEPLOYED)

The app ran green on 8.3 before any composer work — `config.platform` pinned
7.4, so support was *undeclared, not absent*. Exactly one runtime blocker
existed app-wide: a vestigial PDO bound param in JTranslate that 7.4's
emulated prepares ignored and 8.0+ rejects (HY093) — in
`Application::onBootstrap`, so it fataled every route. An `E_ALL` audit found
zero deprecations from our own modules.

The real work was resolution: 45 packages pinned at 7.4-era releases moved in
one pass (`require.php ~8.3.0`, platform pin 8.3.33/ICU 76.1/APCu 5.1.24).
Corrections discovered en route, worth keeping:

- **`kokspflanze/bjy-authorize` 3.x is Mezzio-only** — composer resolves it
  but an MVC app can never boot it. The MVC line ends at 2.4.4, which is what
  installed (retiring our fork). It caps laminas-cache at 3.x.
- **SM 4 / view 3 / validator 3 / filter 3 were unreachable** in this
  dependency set (mvc 3.8 + bjy 2.4.4 + slm/locale 1.2 pin them down), so the
  feared ~102-factory signature sweep was never needed.
- **`laminas/laminas-dependency-plugin` was a hard dead end** (no installable
  version under Composer 2.10), which forced TwbBundle into 4a: neilime's
  bundle requires fourteen `zendframework/*` packages the plugin used to
  rewrite. Adopted `diablomedia/laminas-twb-bundle` ^5.0 (community fork,
  keeps the `TwbBundle\` namespace, targets form 3); insurance fork at
  github.com/jroedel/laminas-twb-bundle. With it, **the ZendFrameworkBridge
  dropped a rung early** — nothing needed it anymore.
- laminas-cache 3.14: `SionModel\Cache\LegacyCacheConfig::translate()`
  (unit-tested) accepts the old StorageFactory config shape, so production's
  untracked cache.local.php needed no deploy-time rewrite.
- laminas-form 3's only app-wide rendering change was minimized boolean
  attributes — proven by the form-regression harness (below). `formRow` now
  genuinely resolves to `SionFormRow` (it was accidentally dead code under
  neilime's bundle); zero markup drift observed.
- laminas-db 2.22 rejects `new TableGateway('')` — SionTable's generic
  gateway removed.

Deployed to production 2026-08-03; konsoleH flipped to PHP 8.3.33 in the same
deploy. Deploy-ops layer landed the same day: `tools/smoke-prod.sh` (runs as
the last phploy hook and standalone), keyed `/sm/cache-status` JSON endpoint,
local `deploy/<timestamp>` tags.

## The API authorization bypass (found and fixed 2026-08-03, deployed)

`Books\Controller\LibrariesApiController` and `PublicationsApiController`
extended `AbstractRestfulController` directly instead of
`RestApi\Controller\ApiController` — the only class that attaches
`checkAuthorization()` to dispatch. Their routes'
`'isAuthorizationRequired' => true` was read by **nothing**, and BjyAuthorize
whitelists `api-v1/*` for guest by design (the JWT layer *is* the gate), so
`GET /api/v1/libraries/:id` (adminNotes, contactEmail) and
`…/pending-labels` (84 KB of book records) answered anonymous callers. Both
were reads: the unfiltered `getList()` was unreachable (route requires
`:library_id`) and the DELETE was not a reachable write (see the
`finish-pending-labels` item in the backlog). Fix: both controllers extend
`ApiController`; `/api/v1/literature` stays public and now says so explicitly.
`test/Integration/ApiAuthorizationFlagTest` walks every module's route tree
and fails if a route requiring authorization is served by a controller that
cannot enforce it.

## The fatal-under-HTTP-200 family (2026-08-02, deployed)

Three independent bugs produced ~800-byte HTTP 200 responses carrying PHP
fatals — a *symptom*, not a diagnosis:

1. **Cold-page cache wedge**: SionCacheTrait's failed-write handler did
   `unset($this->memoryCache)`, destroying the declared property; every later
   request fataled until APCu was cleared. Trigger: APCu exhaustion at the
   32M default. (The patres `1.0.x` line has the same bug — worse under PHP 8
   typed properties; fix branch `fix/cache-write-failure-wedge` pushed.)
2. **Every unknown URL since ~2020**: the multidots `/:*` catch-all route +
   default-deny guard + a RedirectionStrategy `explode()` TypeError. Catch-all
   scoped to `/api`; unknown URLs now render the normal 404.
3. **The service-graph recursion** below (never reached production).

Related hardening: `public/index.php` no longer forces `display_errors`
outside development; the blog's parsedown version mismatch (notices rendered
into post bodies) fixed.

The APCu follow-up (2026-08-03, docs/caching.md): with `apc.ttl=0`, one
oversized write wipes the *entire* cache. Two keys each exceeded the whole
32M segment. Fixed with `max_cached_item_size` refusal + narrow projections;
warmed occupancy measured 10.4 MiB.

## Service-graph cycles (2026-08-02)

Dropping proxy-manager/`lazy_services` exposed a four-way constructor cycle
the lazy proxies had been deferring past for years
(`UserTable → ProblemService → ProblemTable → AuthService → UserTable`);
every route recursed to the 512M limit. Cut losslessly, then the root pattern
retired app-wide: **thirteen** factories resolved `JUser\AuthService` at
construction just to freeze an acting-user id. All now inject
`SionModel\Service\ActingUserProviderInterface` (one method,
`getActingUserId(): ?int`), consulted at write time. Consequences: mid-request
logins are observed (magic-link redemption no longer stamps `createdBy =
null`), user-role inserts finally stamp `create_by` (null since 2014), and
`SionTable::setActingUserId()` survives as the explicit override for JWT
contexts where no session identity exists.

**Technique worth reusing**: temporarily patch the ServiceManager so `get()`
pushes each name onto a per-container stack and dumps it on the first repeat —
it named the cycle in a single request, and a smoke run under it proved no
cycle survived. Key the stack by `spl_object_id($this)`, not globally: a
plugin manager delegating a name upward is not a cycle.

## Golden-master technique (PhpSpreadsheet migration, 2026-08-03)

The pattern: when replacing a library whose behavior nobody remembers, diff
the old and new implementations over *real historical inputs* before trusting
the swap.

- All 17 historical production imports copied off the server; each file dumped
  through PHPExcel 1.8.1 on a one-off PHP 7.4 container and through the new
  `Books\Service\SpreadsheetReader` on the 8.3 capsule. **2.59M cells
  identical, zero real diffs**; 126k cells in the predicted
  numeric→formatted-string class (all consumers go through `is_numeric` +
  `(int)`). One benign structural difference (formatting-residue tail rows).
- The one behavior that could have broken silently: empty cells returning `''`
  instead of `null` would defeat the empty-row skip and create blank books —
  the reader's contract pins "empty is `null`, never `''`", enforced by
  `test/Integration/SpreadsheetReaderTest`.
- Same pattern earlier, smaller: the cakephp/utility elimination ran both
  implementations over 33 cases (accented Spanish, CJK, regex metacharacters)
  with **0 mismatches**, then baked Cake's output into unit-test fixtures.
- Gotcha for the record: production phpinfo exported as PDF renders "fi" as
  the ﬁ ligature — ASCII greps for `fileinfo`/`filter` come up falsely empty.

## Form-regression harness (2026-08-03)

`tools/form-regression.php` — capture/compare/probe over 27 pages with an
all-roles magic-link account, CSRF-normalized; baseline in gitignored
`data/form-regression/`. Built for rung 4a's laminas-form 3 bump, where it
proved the only markup change app-wide was boolean-attribute minimization.
**This is the safety net for the Symfony form migration** — rebase the
baseline before each form is ported.

## First modernization deploy (2026-08-02) and deploy-day lessons

PRs #1–#3 went to production on the same PHP (7.4.33). That evening's full
prod smoke pass ended the fatal-200 era: blog clean, unknown URLs → real
404s, magic-link sign-in working, random cold sitemap pages (of ~14,640)
zero fatals. Passwords stopped working anywhere; sign-in is email magic
links. Lessons that shaped DEPLOY.md (the living procedure):

- phploy must run as `php8.0 phploy.phar` (host php8.5 lacks mbstring).
- phploy's `-m` submodule mode has a fatal directory-purge bug — it
  recursively deleted `module/JUser/src` *after* uploading it. Never again;
  submodules deploy via `tools/deploy-submodules.sh` in post-deploy hooks.
- `public/.htaccess` was untracked from 2017 and forced
  `SetEnv APP_ENV development` since 2016 — production ran development mode
  for years. Hand-edited server-side at deploy time. **Superseded:** it was
  committed in `36eb75c` and phploy deploys it, so the repo copy is the one to
  edit; a hand-edit on the server survives until the next deploy, which is why
  the pre-deploy hook backs the server's copy into `data/htaccess-backups/`.
- A stale `data/config/` cache produces "Unable to resolve service X" for a
  service plainly present in config — the deploy hooks purge it every time.

## Retired en route

- bjy-profiler query profiling (pick a maintained tool if missed).
- The countries dataset is static now
  (`module/JTranslate/data/countries.json`, vendored from mledoze/countries
  1.8); refresh from upstream if country data matters.

## Rung 4b — PHP 8.4 (closed 2026-08-04, deployed same day)

Reached 8.4.24 in production. Three PRs cleared our side of the line, then one
moved the platform.

**The gate list in the backlog was wrong, and re-measuring is cheap.** It said
"the only gate left is `diablomedia/laminas-twb-bundle`". Setting
`config.platform.php` to 8.4 and running `composer update --dry-run` found
`laminas/laminas-crypt` (abandoned *and* capped at `~8.3.0`) and `slm/locale`
as well. Composer reports blockers a few at a time, so the dry-run has to be
re-run after each one is cleared rather than trusted once.

**laminas-crypt went by deletion, not replacement.** Its whole use was two calls
in `SionTable` wrapping PHP's own hash functions: `Hash::compute($a,$d)` is
`hash($a,$d,false)` (hex) and `Hash::isSupported($a)` is
`in_array(strtolower($a), hash_algos(), true)`. Verified byte-identical for
sha256/sha512/md5 *before* removing the package, because `privacyHash()` output
is stored. Removing it cascaded to `laminas-math`, which broke the app —
`CspListener` builds its CSP nonce with `Laminas\Math\Rand`, and six other
files use it too. laminas-math is now an explicit require; it is abandoned but
3.8.1 allows `~8.4.0`, so it was never a gate. Retiring its 11 `Rand` call sites
(several generating security tokens) stayed a separate item rather than a
drive-by.

**Implicit-nullable parameters: 107 in our own code, and grep cannot find them.**
PHP 8.4 deprecates `Type $x = null`; PHP 9 makes it fatal. The reliable method is
to load every class under `E_ALL` on 8.4 — the notice fires at *compile* time, so
a factory no test exercises still emits it, and a line-oriented sweep misses
multi-line signatures whose last parameter carries no trailing comma
(`SionForm::prepareForSuggestion()` was exactly that, caught only by the runtime
probe). Recipe: `docker run -v "$PWD":/app php:8.4-cli` and `include_once` every
file under `module/*/src` with an error handler collecting `E_DEPRECATED`. Almost
all 107 were the factory signature
`__invoke($container, $requestedName, array $options = null)`. Kept strictly
separate from the parked Interop→Psr / `: mixed` sweep — different driver, since
PHP forces this one.

**Upstream would not have unblocked us in time, so we own the forks.** Neither
`basz/SlmLocale` (1.2.0, Oct 2024) nor `diablomedia/laminas-twb-bundle` (5.0.0,
May 2024) had moved, and neither had an 8.4 PR. Both were *functionally* fine on
8.4 — the whole suite passed with their 8.3-resolved code running on 8.4.24 — so
the caps were conservative, not protective. Each fork branch adds the constraint
plus explicit nullables (5 and 18). `composer.json` gained `repositories` entries
whose `comment` keys state the exit condition, and the lock pins exact commits so
tracking a branch stays reproducible.

**The `gh` token cannot open PRs on third-party repos, or fork them** (403,
`createPullRequest` / `addComment` / forks). Widening it does not help: those
operations need permission on the *target* repo. Push to our own fork works, so
the last step is always a manual click. Worth knowing before promising a PR.

**Two verification techniques that earned their keep.** The form-regression
harness was run as an isolated A/B — same PHP 8.4.24 on both sides, only the
dependency swapped — giving zero drift across all 27 pages, which is what turned
"TwbBundle's view-helper signatures changed" from a worry into a fact. Its first
run reported 2 drifted pages, both `500 → 200`: the committed baseline predated
commit `70ab44c` by ~14 hours. A stale baseline reads exactly like a regression,
so re-capture it whenever a fix lands.

**Deploy order is not optional, and CI taught it cheaply.** Composer writes
`vendor/composer/platform_check.php` from `require.php` and PHP evaluates it on
*every* request, so a release requiring 8.4 on an 8.3 server hard-fatals the
whole site. `config.platform` does not suppress that check — it only affects
resolution. CI (still on 8.3) failed in exactly that file, which is the five-cent
version of the same lesson. Flip the runtime first, smoke the old release on it,
then deploy. Also: each PHP version reads its **own** `php.ini`
(`php84-ini/ourlink/php.ini`), so ini tuning does not follow a version flip.

**Post-flip reconciliation found the pins half wrong.** Production's 8.4 build
reports PHP 8.4.24 and APCu 5.1.24 (both matching), but ICU **72.1** where the
pin said 76.1 — the hoster's newer PHP ships the *older* ICU — and zip 1.22.8
against a pinned 1.22.3. Read the real values from `/en/sm/phpinfo` after any
runtime change rather than assuming the capsule's values carried over.

**Stacked PRs across submodules were a mistake.** Basing submodule PR #5 on
`feat/symfony-console` and #6 on `feat/monolog` made each diff read cleanly, but
merging them cascaded into those *feature* branches and left `modernization`
holding only the first change — needing a bookkeeping PR to bring the rest
across. Submodule PRs should each target `modernization` directly; stacking only
earns its keep in the application repo, where PRs genuinely share
`composer.lock`. When re-pinning a stack, merge each branch into the next first,
or the gitlink three-way-merges into a conflict.

## PHPStan repair, and the end of @final inheritance (2026-08-04)

PHPStan had been red since rung 4a and nobody knew, because nothing ran it.
`phpstan.neon.dist` still pinned `phpVersion: 70400`, so the analyzer parsed
the PHP-8-syntax vendor tree the stack bump installed as though it were 7.4:
**499 errors, 437 of them phantom** "class not found" noise from vendor code it
could no longer read. Setting `phpVersion: 80400` dropped it to 62.

**The lesson is the pin, not the number.** `phpVersion` decides how PHPStan
parses `vendor/`, so it has to track `config.platform.php`. A stale value does
not fail loudly — it buries real findings under noise, which is worse than
being switched off. Both PHPStan and the integration suite now run in CI
(neither needs a database or a running app), and `composer stan` runs the
analyzer the way the test suites are already run.

**Underneath the noise, every one of the 13 uncovered errors was real.** Two
were a reachable fatal: `JTranslate\Model\TranslationsTable` imported
`laminas-code`, which is not installed, so the language-file admin action died
with "class not found" whenever it was reached. Eleven were classes inheriting
from laminas classes the stack bump had marked `@final` — `Validator\Regex`,
`Filter\PregReplace`, `Form\Element\Tel`, `Form\View\Helper\FormSelect`,
`View\Helper\InlineScript`, `ValidatorChain`.

**laminas closed the concrete classes but left the abstract bases open**, so
eight of the eleven were a base-class swap rather than a rewrite: the six
pattern validators onto `AbstractValidator` (via a shared
`AbstractPatternValidator` reproducing Regex's `isValid()` and its three error
keys verbatim), `SortText` onto `AbstractFilter`, `Form\Element\Phone` onto
`Form\Element`. A ninth, `ValidatorOrChain`, turned out to be referenced
nowhere and was deleted. Only the two view helpers needed real work:
`FormSelectWithoutOptions` cannot intercept `renderOptions()` from outside a
subclass, so it narrows the element's options up front and delegates; and
`InlineScript`'s chain was closed all the way up through `HeadScript`, so it
wraps a stock instance and forwards the narrow surface templates actually use.

**Two pre-existing quirks were preserved rather than fixed**, because a
refactor that quietly improves behaviour is a refactor you cannot verify. The
old `InlineScript` overrode `__construct()` without calling
`parent::__construct()`, so `HeadScript`'s `setSeparator(PHP_EOL)` never ran and
`<script>` tags render with no separator; the wrapper sets the separator to `''`
explicitly. And `SortText`'s format mixes positional with sequential printf
specifiers, so a trailing `%-3s` consumes argument 1 rather than its token —
pinned in a test and moved to the backlog as a product decision.

**Method worth reusing: characterize first, against the code you are about to
replace.** Twenty-nine tests were written against the pre-change classes and
had to pass there before anything moved. Two of them caught genuine divergences
the rewrite would otherwise have shipped — an unselected empty option that the
old interception discarded but delegation would have kept, and a translator
whose disabled state would have stopped propagating once rendering was
delegated. The `laminas-code` replacement got a stronger check still: it
round-trips all 24 committed `*.lang.php` files byte for byte, since those files
are literally what the old generator emitted.

Final verification was the form-regression harness run as an isolated A/B —
same PHP 8.4.24 on both sides, only the code swapped — showing no drift across
all 27 form-rendering pages. Baseline 42 entries/56 errors -> 35/49, purely by
dropping stale patterns; suites 198 tests -> 348.

## Retiring `getServiceLocator()` (2026-08-04)

`ServiceManager::getServiceLocator()` has been deprecated since
laminas-servicemanager 3.0 and raises `E_USER_DEPRECATED` on every call. The
backlog carried three separate entries for it (`InlineScriptFactory`,
`FilesController`, `layout.phtml`); a grep found **twenty** call sites across
seven modules, so it was one piece of work rather than three drive-bys.

**Eighteen of them were a pure no-op, and proving that was the whole job.**
`ServiceManager::doCreate()` hands a factory `$this->creationContext`, and
`AbstractPluginManager::__construct()` sets its own `creationContext` to the
*parent* container. So a factory registered under `service_manager`,
`controllers` or `view_helpers` alike already receives the application-level
`ServiceManager` — whose `getServiceLocator()` returns `$this`. A throwaway
probe registered a factory into all six manager types the app uses and printed
identity: `identical=YES` six times, six deprecations raised. `$parentLocator`
was an alias for `$container`, so the alias was deleted rather than reassigned.

**The two real fixes were the ones the mechanical sweep could not reach.**
`FilesController` could never have run — `parent::__construct('file')` against
an eight-argument constructor — and nothing referenced it, so it was deleted
along with the two `phpstan-baseline.neon` entries that existed only to silence
it. `NowMessenger`'s `get`/`setServiceLocator()` pair had no callers on either
side; the setter was never invoked by its factory.

**`layout.phtml` needed a judgement call, and `ServerUrl` was the wrong answer.**
The template reached the request only for its scheme and host, to absolutize the
`rel=canonical` and `rel=alternate` link tags. `Laminas\View\Helper\ServerUrl`
looks like the built-in fit, but it re-detects the scheme from `$_SERVER` under
different rules than `Laminas\Http\PhpEnvironment\Request`: it wants
`HTTPS === 'on'` exactly, and ignores `X-Forwarded-Proto` unless `useProxy` is
enabled. Behind a TLS-terminating proxy it can report `http` where the request
reports `https` — silently downgrading every canonical URL on the site. Since
production's FastCGI setup could not be checked from here without touching it,
the request object stayed the source of truth via a small
`Application\View\Helper\RequestUri`, following the existing `RouteName`
precedent (injected dependency, tiny factory).

**Two measurement traps, both worth not re-learning:**

- **The capsule runs `opcache.revalidate_freq=2`, same as production.** A
  `git stash`/capture/`git stash pop` A/B inside that window compared a
  *reverted* `module.config.php` against a *stale-cached* `layout.phtml` and
  produced a 0-byte page. Any before/after capture against the capsule has to
  wait out the revalidation window, or it is measuring OPcache.
- **`layout.phtml` randomizes its language-switcher flags** (`Rand::getInteger`
  picks `us`/`gb`, `de`/`ch`, …), so two captures of the same page never match
  byte for byte. The controlled A/B differed on exactly that one line, which is
  what confirmed the change was inert.

Nothing asserted those canonical tags before, which is why the mechanism could
be swapped unnoticed — `test/Smoke/CanonicalLinkSmokeTest` now pins them, and
was mutation-checked (host forced to `mutant.example` → 2 failures, clean on
restore) rather than merely observed to pass.

## PHPStan level ladder, measured (2026-08-05)

The backlog had carried "raise PHPStan from level 0" as the biggest durability
lever for weeks. Measuring it first changed the answer.

**The numbers.** New errors beyond the existing baseline, per level: 234, 673,
762, 1094, 1202, 2586, 2745, 2809, 3647 (levels 1–9), across 67→316 files.

**Level 1's 234 errors are not 234 problems, and that is the whole finding.**
Grouping them by normalized message:

- **115 are false positives** — `flashMessenger()` (53), `nowMessenger()` (35),
  `isAllowed()` (24), `zfcUserAuthentication()` (2), all resolved at runtime
  through `AbstractController::__call` against the controller-plugin manager.
  PHPStan cannot see that indirection without an extension.
- **82 are `isset()` on a variable that always exists** — dead defensive code,
  harmless.
- **~37 are worth reading**, and they were: 3 arity mismatches, 5 calls to
  methods that do not exist, 14 possibly-undefined variables, plus unused
  constructor parameters.

So raising to level 1 would have cost ~200 baseline entries to buy ~37
findings, and diluted the "no new errors" contract that makes the baseline
useful. **The decision recorded: teach PHPStan the plugin managers first —
or skip the extension entirely, because the whole false-positive class
evaporates under Symfony, where plugins become explicit dependencies.**

**Harvesting findings does not require raising the level.** `phpstan analyse
--level 1` run ad hoc is what produced the fixes below; the committed level
stays 0. This is the reusable move: treat a higher level as an *audit tool*
first and a *gate* only once its false-positive rate is known.

### What the audit actually caught

Four of the level-1 findings were the same bug shape as things already on the
backlog, which is a good sign the tool was pointed somewhere useful:

- **`LibraryTable:1038`'s `$checkout` leak** — already characterized in the
  backlog from a manual read. Level 1 finds it independently, for free.
- **`Mailer:209` calls `Rand::getString()` with 3 arguments for 1–2
  parameters.** That is the magic-link token generator, and the
  `laminas/laminas-math` retirement item had already flagged the call site as
  needing care. PHP silently discards surplus arguments to userland functions,
  so the dropped third argument — almost certainly ZF's old `$strong` flag,
  gone in laminas-math 3 — has been ignored without a whisper.
- **`DictionaryTableFactory:29` builds a 3-parameter constructor with 4
  arguments**, in the same feature as the open `/en/dictionary` 404.

### Fixed in the same pass

**`throw \Exception('...')` with no `new`, 7 sites** across Bible (3),
JTranslate (3) and Schoenstatt (1). PHP parses that as a *function call*, so
each raised `Error: Call to undefined function \Exception()` — the wrong type,
with a message describing nothing. The reachable one was
`CountriesInfo::getTranslatedCountryNames()`, called from four places with
`Locale::getPrimaryLanguage(Locale::getDefault())`; any locale outside its
six-entry `$langMap` takes the throw path.

**The last dynamic property.** `SionTable`'s constructor assigned a *singular*
`$changeTableName` while the class declared a *plural* `$changesTableName` that
was never assigned or read. Five readers used the singular name too, so the fix
was a rename onto the dead declaration rather than a new property — six sites
moving together, no behaviour change, and one fewer PHP 9 gate.

**`getCountry(?string)`.** Both callers pass a nullable DB column and already
guard the result with `isset()`, so declaring the nullability was free. It had
been raising `strtoupper(null)` once per country-less row — eleven times on a
single association listing.

Baseline: **33 → 26 entries, 49 → 34 errors.** Regenerating it rather than
hand-editing also cleared a stale `SchoenstattLinkIdentifier::$entityType`
entry, fixed the day before but still listed — silent only because
`reportUnmatchedIgnoredErrors` is off. Worth knowing that flag hides its own
staleness.

## Symfony kernel in front (2026-08-05)

The strangler's first rung: `symfony/http-kernel` + `symfony/routing` 7.4 LTS,
hand-wired into `App\Kernel`, with a catch-all route delegating every unported
path to `Laminas\Mvc\Application`. Mechanism, switching, and the conversion rules
live in [strangler.md](strangler.md); what follows is what was learned getting
there.

**The plan inverted on a dependency check, before any code.** The intended shape
was `symfony/framework-bundle` — a real Symfony application with DI compilation
and the standard config layout. It does not resolve here:
`framework-bundle → symfony/cache → psr/cache ^2|^3`, against
`laminas-cache 3.14 → psr/cache ^1`. laminas-cache 4.3 lifts the pin, and
`kokspflanze/bjy-authorize 2.4.4` — the last release of a dead line — caps
laminas-cache at `^3`. Meanwhile `symfony/http-kernel` and `symfony/routing`
require no `psr/cache` at all and installed with **5 installs, 0 updates, 0
removals**. So the bundle is gated behind the authorization migration and the
kernel was not, which is the opposite of the assumed ordering. Worth repeating
the move that found it: `composer require --dry-run` on the *destination* package
before designing anything around it. `composer prohibits` cannot answer this —
it refuses packages not already in the project.

**Reading the sender beat reasoning about the response.** Three of the four
conversion rules came from `vendor/laminas/laminas-*` rather than from thinking
about what a response conversion needs:

- `HttpResponseSender::sendContent()` echoes `getContent()`. `getBody()` — the
  obvious-looking method — de-chunks and *gunzips* per the response's own
  `Content-Encoding`, which on the sitemap route would emit plaintext under a
  `gzip` header.
- `PhpEnvironment\Response::sendHeaders()` appends only
  `MultipleHeaderInterface` headers and replaces every other, so a repeated
  ordinary header keeps its last value. `Headers::toArray()` collapses on exactly
  that rule; the hand-rolled `foreach` written first emitted duplicates the
  current sender drops. **The faithful conversion was the library's own method,
  and the custom loop was the behaviour change.**
- `GdprStrategy::onFinish()` and `CspListener` both work on PHP's SAPI header
  list (`header_remove()`, `header()`, `setcookie()`), not on the response
  object. Symfony's `sendHeaders()` appends to that same list rather than
  resetting it, so they survive the bridge untouched — and `rg` confirmed nothing
  sets a cookie on a laminas response object, which is what makes converting the
  response's headers safe next to a consent gate that strips them.

The seam itself was cleaner than expected: `SendResponseListener extends
AbstractListenerAggregate`, so detaching it after `Application::init()` stops
laminas from emitting a byte — no output buffering, no `headers_sent()` fight —
and `run()` populates the response on every path it can take, `dispatch.error`
included.

**Two defects in newly written code, both found by PHPStan level 8 on `src/`
alone.** The committed level is 0 for the legacy tree, but there is no reason new
code should inherit that: `Request::getProtocolVersion()` is nullable, and under
`strict_types` a missing `SERVER_PROTOCOL` would have been a TypeError on every
Symfony route; and the header loop's real type was `array|HeaderInterface`, which
is what led to reading `toArray()` and finding the collapse rule above. A
per-directory audit at a high level costs one throwaway neon file and is now the
documented bar for `src/`.

**Two measurements that corrected assumptions:**

- Symfony's `Response` defaults to **HTTP/1.0** and writes that into the status
  line. Apache honours it: the first `/_health` came back `HTTP/1.0 200 OK` with
  `Connection: close`, so Symfony-native routes silently lost keep-alive while
  bridged ones kept it. Fixed with a five-line `kernel.response` listener rather
  than `Response::prepare()`, which would also have rewritten `Content-Type` and
  `Content-Length` on responses laminas had already finished.
- **`APP_ENV` is `production` inside the capsule.** `public/.htaccess` sets it and
  `AllowOverride All` lets that win over the vhost's `development`. A version
  payload on `/_health` had been gated on `APP_ENV !== 'production'` and came back
  empty, which is how this surfaced. The payload was dropped rather than
  re-gated — versions on a public URL are reconnaissance, and `composer.lock`
  pins them better than a smoke assertion — but the finding stands on its own:
  the vhost's `SetEnv APP_ENV "development"` has always been dead config.

**Verification.** 368 tests green (356 before), PHPStan clean at level 0 and at
level 8 for `src/`. The two front controllers were A/B'd by flipping the flag in
the running container: identical status, content type and **byte count** on `/`,
`/en/`, `/en/sitemap.xml` and a 404 path, with only `/_health` differing. That
byte-for-byte comparison is the actual evidence the bridge is transparent — the
test suite asserts on markers, not on lengths.

## The Symfony kernel turned on globally (2026-08-11, DEPLOYED)

`SetEnvIf Request_URI ".*" SYMFONY_KERNEL=1` in `public/.htaccess`, above the two
canary-cookie overrides so `sl_symfony_canary=0` still wins as an escape hatch. One
line, and the site has served every request through `App\Kernel` since that deploy.
`curl https://schoenstatt.link/_health` answering `{"status":"ok","kernel":"symfony"}`
is the confirmation — cheaper than reading `.htaccess` on the server, and it reports
what is *running* rather than what is configured.

What made it a one-line change rather than an event: the `sl_symfony_canary=0` escape
hatch was deployed ahead of the flip, so the way back was exercised before it was the
only way back; the navbar toggle was rewritten to offer whichever kernel you are *not*
on, so it needed no edit on the day; and the configured error-reporting pipeline was
wired into `App\Kernel` first — without which every failure on a ported route would
have been recorded and never notified, for all traffic instead of just the canary's.
`tools/smoke-prod.sh` checks the three response-header hazards on every deploy, on a
bridged page and therefore behind the TLS-terminating proxy the capsule lacks: doubled
`Set-Cookie`, invented `Cache-Control`, and the sitemap's gzip.

**Both "known and accepted" caveats the backlog carried for this item turned out to be
wrong by the time it closed**, which is the part worth keeping:

- "Ported pages stop contributing missing phrases to `/admin/translations`, because
  nothing reproduces the `MvcEvent::FINISH` listener." Not true any more.
  `LaminasExtension::translate()` *is* a write — a miss files the phrase in the page's
  own domain — and `App\Laminas\TranslatorConfigurator` sets that domain per route.
  `test/Integration/PortedRouteTranslationTest` asserts discovery happens in the page's
  domain and nowhere else, and `testTheFallbackDomainReadsButNeverDiscovers` pins the
  nuance that makes it correct rather than merely present: the `default` fallback is
  read from its compiled catalog, firing no event, which is the whole of
  `database/db7.8.sql` and the 232 duplicate rows it cleaned up. See
  [translation.md](translation.md).
- "The unprefixed form of a guarded path redirects with `?redirect=/roles` instead of
  `?redirect=/en/roles`." Still true, still tidiness rather than a bug, and it has its
  own backlog item — the `kernel.request` listener that would centralise the
  unprefixed-to-prefixed redirect. It was never a consequence *of the flip*.

The lesson is the ordinary one about caveats written in advance: both were accurate
when written and neither was re-checked when the item finally closed. A "known and
accepted" list is a claim with a shelf life.

## The v1 and v2 API, retired (2026-08-14)

All 26 `/api/v1` and `/api/v2` routes deleted, along with the `RestApi` base class
every v1 controller extended, four `*ApiController`s in `Books`, both
`AssociationsApiV{1,2}Controller`s in `Schoenstatt`, JUser's `LoginV1ApiController`,
the Symfony `ShrinesGeoJsonController` that had answered the two GeoJSON feeds since
2026-08-07, and `public/api/` — the OpenAPI document plus a 2020 Swagger UI bundle,
~6.7 MB of it.

**The decision came from the access log, not from reading the code.** Production's
`~/logs/access.log*` reaches back to 2016-04-17; `grep -F '/api/v'` over the rotated
set yields 10,449 lines, and read per *client* rather than per endpoint the picture is
unambiguous: the last non-scanner caller was a Google Apps Script on **2022-11-05**,
the one mobile client in the file read `findByKind` in 2019 and never returned, and in
the trailing twelve months `findByKindMd5` and v2's `findByKind` had been touched by
nothing but `tools/smoke-prod.sh`. Our own tooling was the loudest remaining client,
which is precisely how a dead endpoint keeps looking alive. Full table in
[strangler.md](strangler.md).

Two consequences worth carrying forward:

- **We were advertising it to ourselves.** `findByKind` still drew ~270 hits a year
  because `/shrines` and `/wayside-shrines` published a link to it *and* a schema.org
  `Dataset` `contentUrl`. Crawlers following our own advertisement read as live
  traffic. Retiring an endpoint means retiring what points at it — four templates and
  two dataset builders here.
- **Deletion cascades further than the routes.** `Application\Listener\CorsListener`
  served only routes carrying `'cors' => true`, all of which were in the Books v1 tree;
  `jmikola/geojson` was reachable only from `SchoenstattTable::getShrineGeoJson()`,
  whose only callers were the deleted shrines.json actions; the API-code half of
  JUser's `LoginTokenService` had no caller but `LoginV1ApiController`. Each was
  verified orphaned by grep before removal, not assumed.

Also corrected: `App\Api\BotIdentity`'s docblock had claimed "the mobile apps already
use them" of these JWTs, and `config/symfony/routes.php` described shrines.json as "an
endpoint the mobile apps poll". Neither was true, and both had been repeated as
justification. A plausible sentence in a docblock is not evidence about traffic.

The retired URLs answer **410 Gone**, not 404, with a
`Link: </api/v3>; rel="successor-version"` header — a 410 is what gets an indexed URL
dropped rather than demoted, and two of these were an indexed schema.org `Dataset`
distribution. The 410 is scoped to the two retired version prefixes; an unknown path in
a live version keeps its 404, because "permanently gone" and "you misspelled it" are
different answers and a caller acts on them differently.

**Verification.** 482 smoke tests green (480 before this and 473 before the batch; the delta is two
`ApplicationSmokeTest` providers: 11 retired paths that must answer a JSON 410 with a
successor link, and 3 unknown ones that must still answer 404 with none), 715 integration
(718 before, minus the three in the deleted `ShrineGeoJsonParityTest`), 286 unit, fuzz
clean. Integration deprecations fell 19 → 14 as geojson left the tree. The 404 body was
measured byte-identical before and after, with a four-second wait on each side to clear
the OPcache revalidate window. ACL snapshot: 181 routes → 155, guarded 159 → 135, exactly
the 26 removed and no others.

## Archaic endpoints, round 2 (2026-08-14)

Four dead endpoints and three unreachable methods, retired after the v1/v2 deploy. What
makes the round worth recording is not the deletions — it is that **two of the three
fatal code paths were sitting in `phpstan-baseline.neon`**, so level 0 reported "No
errors" over calls that could not resolve under any circumstances, and that the third was
invisible to PHPStan for a structural reason worth knowing.

**The scan.** PHPStan resolves `$this->foo()` and so had caught two of these — and they
were baselined rather than fixed, which converts a guaranteed fatal into a clean run. It
could not see the third: `$table = $this->getSionTable(); $table->importJkTexts(false);`
has a receiver too loosely typed to check. Finding that class of bug needed a different
question — *which method **names** are called in a controller or table and defined nowhere
in the tree, whatever the receiver's real type turns out to be* — which is a crude grep
over every `function` definition versus every `$var->name(` call site. 17,519 definitions
against the call sites left 18 candidates; 15 were receivers PHP resolves at runtime
(`ReflectionClass`, Carbon's `__call`-generated `addMonth`, laminas controller plugins),
and 3 were real. A false-positive rate of 15/18 is fine for a one-off audit; the useful
property is that a name defined nowhere cannot resolve, no matter what the receiver is.

**What was retired:**

- **`/en/texts/import`** (route name `texts/jk-import` — the path and the name disagree,
  which is the same trap that misled the `publications/admin-tasks` reading) called
  `$table->importJkTexts(false)`. No class in the application, SionModel, or any vendored
  package defines that method. Administrator-guarded, live on production, and a guaranteed
  `Error` for anyone who clicked it. It never worked: there is no
  `import-jk-texts.phtml` either, so even a successful import had no view to render.
- **`/en/music/import`** ran `importMusicasJuly2019()` — a hardcoded list of 335
  composition ids, reading ChordPro files from `data/musicas/`. It looked like the same
  GET-mutation shape as the `publications/import` retired hours earlier, and **it is not**:
  every iteration is gated on `disambiguatingDescription`, and **all 335 compositions have
  that column empty**, because the 2019 import consumed its own input by nulling the field
  it keyed on. The directory does not exist either. It wrote nothing and returned `0`. This
  correction is the point — "mutates on a GET" was the reason it was ranked where it was,
  and measuring the data rather than reading the code is what settled it.
- **`SionTable::getMailings()` / `getMailing()`** failed twice over: the SQL selected `FROM
  a_data_mailing`, a table this database has never had, and the loop called
  `$this->getEmailAddress()`, defined nowhere. The table name is the interesting half —
  `database/db2.3.sql` creates the table as `mailings`, and only its phpMyAdmin *comment
  headers* say `a_data_mailing`, because the migration was pasted in from another project's
  export. The query came from the same paste and was never adapted. Unreachable: the
  `mailing` entity's only use is `Mailer::sendMailingReport()`'s `createEntity(…)`, whose
  read-back is gated on a `databaseBoundDataPostprocessor` the spec does not define.
  Deleting the methods and the spec's `get_object_function` key leaves the entity **better
  off**: `getObject('mailing', $id)` falls through to `tryGettingObject()`, which reads
  `table_name` and `update_columns`, both correct. Reading a mailing works now, having
  never worked.
- **`SchoenstattTable::getPersonRoleTitles()`** called `getRoleTitleAliases()`, defined
  nowhere, and had **no callers at all** — dead code wrapping a fatal.

**And a bug the previous deploy created.** Every 410 carried
`Link: </api/v3>; rel="successor-version"`, and `/api/v3` is not a route: it 302s to
`/en/api/v3` and lands on the same 404 handler that emits the 410. The one affordance a
withdrawn endpoint can offer pointed at nothing. It names `/api/v3/schema` now — public,
200, and it enumerates both resources with their endpoints and roles. The generalisable
lesson is about the test, not the header: **asserting a Link header is well-formed does
not assert that it resolves**, and every check we had passed on the broken version. Both
`smoke-prod.sh` and `ApplicationSmokeTest` now parse the URL out and fetch it.

**Verification.** 486 smoke green (482 before — four new cases pinning the retired admin
paths at 404, which is the status that carries information: while a *guarded* route
exists, an anonymous request gets a 302 to the login form, so a 302 there means the route
came back), 715 integration, 286 unit, fuzz clean, PHPStan clean **with two fewer baseline
entries**. ACL snapshot: 155 routes → 153, guarded 135 → 133, and the diff is exactly the
two removed guard entries and the counts — no rule quietly stopped matching, which is the
failure mode that makes a page work for *more* people and breaks nothing.

## What production had actually been doing (2026-08-14)

`tools/fetch-exceptions.sh` had never been run against an accumulated store. Pulling it
down produced 38 fingerprints and one conclusion worth more than the individual bugs:
**there are no steady-state code failures.** 21 of the 38 are real (the rest are
deny-listed authorization noise), and **17 of those are deploy-window artifacts** — the
full account is the "Now" item in [BACKLOG.md](BACKLOG.md). The proof is not the shapes,
plausible as they are; it is that one burst spans **two revisions in 22 seconds**, so
`.revision` was being rewritten while requests were in flight.

Two things came out of it that were acted on.

### 3,439 exceptions because we advertised a moderator-only button to everybody

One fingerprint had **3,439** occurrences against 64 for the next-noisiest, still climbing
eleven days later: `publication-copy-to-main-corpus`, from varying `sw_id`s and locales, no
referer, different countries, spoofed old Chrome user agents. Crawlers.

They knew the URL because **both front controllers rendered the button to every visitor**
on every data-sourced publication — ~4,165 rows × 5 locales, roughly twenty thousand public
URLs each advertising a `pub_moderator`-only action. Every hit paid a full bootstrap and ACL
load to answer with a redirect to a login form.

The mechanism was not missing. `publication-info.phtml` guards `Admin tags` six lines above
with `displayOnlyWithPermission`, and `publication.html.twig` already gates
`publication-create-new-edition` with `is_allowed('route/…')`. This was an omission, and it
is the same shape as the `findByKind` crawl traffic found during the v1 retirement: **a
retired or restricted endpoint stays busy as long as something still points at it.**

Both copies now ask the ACL for the **route resource**, so the guard in
`module/Books/config/module.config.php` stays the only place that decides.

### The action behind it copied a publication on a GET

`copyToMainCorpusAction()` called `copyPublicationToMainCorpus()` — an INSERT — with no
method check, no CSRF token and no confirmation. The guard kept it away from the public,
but nine effective roles hold `pub_moderator` and browsers prefetch links a signed-in
moderator has merely hovered over. Same class as the `publications/import` retired hours
earlier, except this one is a feature still in use.

GET now confirms and POST copies, following `SionController::deleteAction()` rather than
inventing a pattern. `Books\Form\CopyToMainCorpusForm` deliberately has **no Cancel
element** — `SionModel\Form\DeleteEntityForm`'s docblock records that its Cancel button
rendered as `type="submit"` and therefore deleted records for years, so cancelling here is
an ordinary `<a>`, which cannot be mistaken for a submission.

### Three lessons that cost real time

**A test that mutates its own fixture looks like a broken permission check.**
`copyPublicationToMainCorpus()` does *two* writes — it inserts the duplicate and then
points the source row's `mergedIntoPublicationId` at it. The first cleanup deleted only the
insert, so each run left its fixture marked as merged (pointing at a row that no longer
existed), and a merged publication takes the other template branch and offers the button to
nobody. Symptom: `testAModeratorIsOfferedTheButton` passed once and failed every run after,
which sent the investigation through the ACL, the view-helper plumbing and the Twig
extension before anyone printed the entity array. Three publications (1, 1726, 1727) were
damaged this way and repaired; the cleanup now clears the pointer as well, and a new
assertion fails if any publication points at a merge target that does not exist.

**"Not merged" is not a question for the database.** Only 2,331 of the ~4,165 data-sourced
publications are unmerged, so a fixture chosen by data source alone is more likely wrong
than right. The fixture test asks the rendered page and **fails** rather than skips.

**`rg -c` exits 1 when it matches nothing**, which silently broke the scan written to find
a good fixture: the "unmerged" branch could never be taken, and the loop reported no
candidates when 2,331 existed. A scanner that finds nothing and a scanner that never ran
look identical — the same lesson `fd`-not-installed taught during the dead-code sweep.

### And `ci-local.sh` learned to distinguish "failed" from "could not run"

`composer audit --locked` needs packagist.org, and the capsule has no DNS, so the audit
printed `FAIL composer audit --locked` — which every reader of a PR body takes to mean an
advisory was found. It reports `SKIP` now, is counted separately, does not affect the exit
status, and prints how to check from the host (where the answer is: no advisories).

## Merged publications, checked against every row (2026-08-14)

They were already excluded, and the exclusion was already correct — verified rather than
assumed, in both places. The capsule: 6,477 publication ids published, 3,630 merged rows in
the database, **0 in common**, and the published set is *exactly* the `publication_public`
rows with `MergedIntoPublicationId IS NULL`. Production: 46 sampled sitemap URLs, all 200,
none redirecting, and `/en/SL200417L` still 301s to its surviving edition under both front
controllers.

What was worth changing is how that stays true.

### A two-id characterization test could only catch total failure

`SitemapSmokeTest::testMergedPublicationsAreExcluded()` asserted that `SL200417L` was absent
and `SL207340L` present. That catches the whole exclusion falling over — including the
fail-open path, which is the likely one — but not a partial failure, and not a merge made
after the test was written. **The set is no longer static**: `copyPublicationToMainCorpus()`
marks its source row merged, so every use of "Copy into main corpus" mints a redirect that
has to leave the sitemap, and that path became reachable again the same day.

It now compares the published ids against the database as a set, in both directions — a
missing edition is a page withdrawn from the index, so over-broad fails too. Proven to fail
in both directions before being trusted: injecting `SL200417L`'s five locale URLs into the
published file reports `1 merged publication(s) are in the sitemap` and names id 417;
deleting `SL200058L`'s five reports `1 unmerged public publication(s) are missing`.

It reads **every** file the index names, not `sitemap-publications.xml`, because misfiling
has actually happened here — a per-SAPI navigation cache once filed all 250 associations
under `sitemap-pages.xml` and `removeOrphans()` then deleted the file they belonged in. A
merged publication in the wrong file is still published.

### The production sampler had the belief backwards, in a comment

`tools/smoke-prod.sh` follows redirects on its cold-page sample, and said why:

> Follow redirects: superseded short links legitimately 301 to their successor
> (e.g. SL206282L -> SL207792L) and the sitemap lags behind.

They do 301. Publishing them is not legitimate — it is precisely the defect — so the one
symptom available to a production check was being followed and reported as a pass. Measured:
a merged URL fetched with `--location` gives `STATUS=200 HOPS=1`, indistinguishable from a
healthy page unless you look at the hop count.

It still follows, so the fatal-200 size floor lands on a real page either way, and now warns
when `%{num_redirects}` is non-zero. **WARN, not FAIL**: the sitemap is rebuilt by cron, not
by the deploy, so a redirect in it is not evidence the deploy went wrong and must not end the
deploy loop. The authoritative check is the capsule's set comparison; this is the tripwire.

### Three things checked and found not to be problems

- **`getMergedPublicationIds()` filters `ResourceId = 'publication_public'`**, which looks
  like it could miss merged rows under another resourceId — there are three. It cannot:
  `getPublicationNavigationData()` filters on the same value, so those rows were never
  candidates for the sitemap in the first place.
- **`sch_publications.HasBeenMerged` is a second, independent merge flag** and could disagree
  with `MergedIntoPublicationId`. It is `0` on all 10,166 rows — dead — and the 301 is driven
  by `MergedIntoPublicationId` alone, so the exclusion criterion and the redirect criterion
  are the same question.
- **The APCu entry behind `merged-publication-ids` cannot go stale for the built sitemap**,
  and not by luck: a segment belongs to the SAPI that created it, so the cron console process
  starts cold and reads the database every run.

## The dead menu link that nothing rendered (2026-08-14)

`PageBuilder::publicationPages()` grouped publications by language and filed the ones with
no language under `null`, which PHP stores as the array key `''`. It then assembled
`publications/index` — `/literature/:inLanguage`, constrained to `[a-z]{2,2}` — with
`inLanguage => ''`, producing **`/en/literature/`, which answers 404** while
`/en/literature` answers 200.

The mechanism is worth knowing on its own: **`Segment::assemble()` does not enforce a
route's own constraints.** They apply to `match()` only. So a value that could never have
arrived in a request can still be built into a link, and nothing reports it.

### The backlog said the menu showed it. The menu did not.

That claim had sat in BACKLOG since 2026-08-13 and was inferred from the data structure
rather than observed. Checked against production before changing anything:

| surface | what it actually does |
| --- | --- |
| laminas navbar | `->setMinDepth(0)->setMaxDepth(0)` — top level only; the group sits at depth 2 |
| Twig navbar | iterates `navigation_items()` — top level only |
| Symfony breadcrumb | omits it: `/en/SL202208L` renders `Literature > title`, while `/en/SL201727L` renders `Literature > Schoenstatt Literature in English > title` |
| laminas breadcrumb | publication pages have none at all (forced with `sl_symfony_canary=0`) |
| literature home | twelve language links in both renderings, no languageless one |
| sitemap | dropped since 2026-08-13, by the trailing-slash rule in `isPublishable()` |

So the defect was latent, and the user-facing consequence was the **opposite** of a bad
link: those **25 publications are in the sitemap with zero inbound internal links**. On a
site whose traffic is overwhelmingly crawlers, orphans are the part that costs something.

### What was done, and what was deliberately not

The group is no longer built, and its 25 children are re-parented onto the literature root
rather than dropped — they are real published publications, and losing them here would
withdraw 125 URLs from the index. Verified after the change: all 25 still in the sitemap,
no trailing-slash URL, and the literature home renders the same twelve links as production.

Giving the orphans a real index page was the other option and was **not** taken: it needs a
route branch meaning "no language" plus a predicate change, because **24 of the 25 store
`NULL` and one stores `''`**, so the existing `In($field, [''])` would match one row of 25.

The sitemap rule stays, downgraded from fix to net. It was never the right place: a
malformed page belongs refused where it is built, and a rule in another component written
for another reason was the only thing between a 404 and Google.
`test/Integration/NavigationRouteParametersTest` now fails if any branch declares an empty
route parameter — deliberately broader than the one instance, since the same `assemble()`
behaviour applies to every segment route. Proven to fail before being trusted, by disabling
the guard and watching it name the offending page.

## Deploy atomicity, deferred indefinitely (2026-08-14)

Not retracted — the evidence stands, and it is the largest finding in the exception store.
Deferred by the user on the grounds that the audience is currently almost entirely bots.

The reasoning inverts the usual one and is worth recording. A deploy-window fatal is paid
for by whoever is mid-request, and today that is overwhelmingly crawlers: the `51c0cb27`
fingerprint alone — one scraper replaying a URL list — produced **3,478 requests in eleven
days** against a handful of identifiable human sessions. Fixing atomicity would mostly
protect Googlebot's opinion of us, at the cost of a pipeline redesign and edits to a
credentials file only the user can touch. Revisit when the ratio changes.

### And the guard from PR #94 worked, while the count kept climbing

Worth separating, because the two look the same from the count alone. `51c0cb27` went
3,461 → 3,478 after the deploy, at roughly the pre-deploy rate, which reads as "the fix did
not work". It did: a live data-sourced, unmerged publication fetched anonymously carries no
button and no `copy-to-main-corpus` URL anywhere in the HTML. Removing a link stops
*discovery*, not a queue a scraper already built — the requests arrive with no referer, a
spoofed Chrome user agent and varying `sw_id`, and they drain on the scraper's schedule.
