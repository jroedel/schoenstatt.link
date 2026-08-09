# Modernization roadmap

Current truth only — no journal. Closed work moves to [history.md](history.md)
(or lives in git); when an item here is done, delete it and record anything
reusable there instead of accumulating DONE narratives.

State as of 2026-08-07, on branch `symfony-upgrade` (not yet merged): the
**capsule runs PHP 8.5.9** and production runs 8.4.24 — deliberately different,
see [php-85.md](php-85.md). `composer audit --locked` reports zero advisories.
**631 tests across four suites** (119 unit, 376 integration, 18 fuzz, 118
smoke), green on 8.5; PHPStan clean at level 0
(baseline 26 entries); one-command deploy with hooks. First-party code no longer
calls `getServiceLocator()`, has no `throw Foo()` missing its `new`, creates no
dynamic properties, and emits **no deprecation of its own on 8.5** (the four
that remain are laminas-cache's).

New since the last state line, and the reason several items below moved or
closed:

- **A form input-validation fuzz harness exists** (`test/Fuzz`,
  `php composer.phar fuzz`) — the safety net that had to precede any form work.
  It discovers all 43 forms from the filesystem and drives thousands of
  `isValid()` calls. Baseline of accepted gaps: **180**, down from 259 at first
  run, every change since a removal. Throwing inputs — each a 500 with the
  user's whole submission lost, most needing no more than `field[]=x` —
  **48 → 0**. The invariant the harness exists for now holds for every form and
  field it drives.
- **Authorization is diffable**: `tools/acl-table.php` plus committed snapshots
  `docs/acl-rules.md` and `docs/acl-baseline.json`. Regenerate and diff after
  touching any route, guard or role; a rule that stops matching makes a page
  work for *more* people and no test fails.
- **Dates now record how precisely they are known.** `events.StartDatePrecision`
  was a working model no other table had; db6.5 gives the other seven dates the
  same column and `SionModel\I18n\View\Helper\DatePrecisionFormat` renders to
  it, so "sometime in 1952" no longer displays as 1 January. Every field is also
  range-bounded, and each bound was verified to reject **zero** existing rows.
- **The Bible module and the suggest/moderate and touch features are gone**;
  routes 214 → 191, guarded 184 → 166. Every removed ACL row belongs to one of
  those; nothing changed for a route that stayed.
- **A live authorization hole was closed in JTranslate** — `updatePhrase()` took
  the row to write from a hidden form field, so a translator authorized for one
  phrase could rewrite any other. Pinned by
  `test/Integration/TranslationUpdateScopeTest`.

**A Symfony kernel now sits in front of laminas-mvc in the capsule**, with a
catch-all route delegating every unported path back to it — see
[strangler.md](strangler.md) for the mechanism, the response-conversion rules and
how to switch front controllers. Production still runs the laminas front
controller until `SYMFONY_KERNEL=1` is added to its `.htaccess`.

**Six routes now answer from the Symfony kernel**, three of them HTML: `shrines`,
`admin`, and as of 2026-08-07 `wayside-shrines`. That last one is the first port
whose value was not the route itself — the page is `shrines` with a different
heading over a different association kind, and laminas answers it from a verbatim
copy of both the action and the template. The Symfony side has one of each
instead: `App\Schoenstatt\ShrineIndex` already served both, and
`templates/schoenstatt/_shrine-index.html.twig` now does too, with each page
supplying only its own `shrine_header` block. `/en/shrines` renders
byte-identically before and after that refactor, which is the check that made it
safe to touch a route already in service.

**The first HTML route has moved and renders with Twig** (`shrines`,
2026-08-05): `templates/layout.html.twig` reproduces the site chrome —
navigation, language chooser, search box, flash messages, canonical links,
schema blocks — and is the layout every later HTML port extends. Both renderings
of the page were compared row by row and agree exactly, signed in and out. Two
things learned that change earlier assumptions, both written up in
[strangler.md](strangler.md): a ported route does **not** lose the identity or
the session (only the route guard), and `Laminas\Navigation\Navigation` cannot be
resolved without an MvcEvent at all, so the navbar is built from the raw
`navigation` config. (The third thing that paragraph used to say — that only
*public* routes can move — stopped being true on 2026-08-06, when the
authorization bridge landed. `docs/acl-rules.md` still names which routes are
which.)

## Strategic direction: Symfony, via strangler (decided 2026-08-04)

laminas-mvc is security-only until 2028-12-31 and will never support PHP 8.5
(reverified on Packagist 2026-08-04); its module ecosystem is already eroding
(bjy-authorize's MVC line ended at 2.4.4, TwbBundle survives only as community
forks). Weighed against the criteria — migration cost, support, speed,
readability — the destination is **Symfony**, reached gradually:

- **Components first, kernel last** — done as of 2026-08-05, and the kernel came
  earlier than "last" implied because it turned out to be cheap: Symfony runs in
  front with a catch-all delegating unmigrated paths to the laminas application,
  so routes port one at a time. See [strangler.md](strangler.md). What made it
  cheap was that the whole Symfony stack here is 7.4 LTS components with no
  FrameworkBundle — and what makes the bundle unreachable is recorded below,
  because it is a gate on several other things too.
- **FrameworkBundle is blocked by laminas-mvc — corrected 2026-08-05.** This
  item previously named bjy-authorize as the gate. That was wrong, and the
  correction matters because it changes what the authorization work buys.
  bjy-authorize does cap `laminas-cache` at `^2.13.2 || ^3.1.0`, but it is one
  of two caps: `laminas-cache 4.3` lifts the `psr/cache ^1` pin and requires
  `laminas-servicemanager ^4.5`, while **laminas-mvc requires `^3.20.0` in every
  version — 3.8.0 stable, 3.9.x-dev and 4.0.x-dev alike.** So retiring
  bjy-authorize leaves the bundle exactly as uninstallable as before.
  Consequences: the parked ~102-factory SM4 migration is *unreachable* rather
  than deferred, and Symfony's DI compilation, config conventions and
  Twig/Security/Form bundles all wait on removing laminas-mvc, i.e. on finishing
  the strangler. Retiring bjy-authorize remains worth doing — abandoned package,
  all authorization runs through it, prerequisite for Security — just not as a
  gate-opener. Measurements in [php-85.md](php-85.md).
  - **How authorization now works across the split, and every trap in it, is
    [authorization-migration.md](authorization-migration.md).** Read it before
    porting any route that is not public. It also records why the bridge makes the
    eventual Security migration incremental rather than all-or-nothing: both
    enforcers consult the same `route/<name>` resources, so they can move to
    voters a few at a time with the ACL table as the before/after oracle.
- **PHP 8.5 has the same gate.** No laminas-mvc version admits 8.5 (4.0.x-dev
  caps at `~8.3.0`, *lower* than stable). The capsule runs 8.5.9 today only
  because `config.platform.php` is a resolution fiction pinned to production's
  version; raising it breaks `composer install` against 14 packages. See
  [php-85.md](php-85.md) before touching the pin.
- **No deadline panic.** laminas-mvc security support and PHP 8.4's security
  window both run to December 2028. Rung 4b (8.4) lands regardless.
- **Decoupling is the first real work**: SionModel/JUser's ServiceManager
  habits (string-keyed lazy resolution inside domain classes) must become
  explicit wiring. This pays off under any future and must be sequenced with
  the patres convergence plan below — patres consumes the same libraries.
- **Known concern — server-side form validation parity** (flagged
  2026-08-04): symfony/form validates via symfony/validator constraints; the
  part of laminas-form with no direct equivalent is the *filter* chain
  (`StringTrim`/`StripTags` pre-validation normalization), which becomes data
  transformers or explicit normalization. Port forms one at a time, and note that
  **`test/Fuzz` is the safety net, not `tools/form-regression.php`** — the latter
  is a GET-only, byte-exact HTML differ that never submits a form, so it asserts
  nothing whatever about input handling. The fuzz harness drives hostile input
  through every form's filter and validator chain and is the thing that can say
  whether a port loosened something. Unported laminas forms keep working under
  the strangler.
- **What this decision supersedes**: the ~102-factory Interop→Psr sweep
  ("SM4 prep") is parked — hand-written factories largely evaporate under
  Symfony DI. Long-term answers now on file: TwbBundle → Symfony's built-in
  Bootstrap form themes; BjyAuthorize → Security component (`access_control`
  + voters); JUser magic-link → Security's LoginLink authenticator;
  laminas-view/.phtml → Twig. None of these move before their rung.

## Now

- [ ] Watch for 401 fallout from the API authorization fix (live since
  2026-08-03): clients of `GET /api/v1/libraries/:id` and
  `…/pending-labels` that never sent a JWT now get 401s. The label-printing
  workflow is the first candidate; tokens come from `POST /api/v1/login`.
- [ ] Hetzner/konsoleH support ticket (pending): raise `apc.shm_size` 32M →
  256M and set `apc.ttl` > 0 so a failed allocation evicts instead of wiping the
  segment. **The ini to name is now `/home/httpd/php84-ini/ourlink/php.ini`** —
  verified still 32M / ttl=0 after the 8.4 flip, since the settings were copied
  across unchanged. Downgraded from blocking by the
  cache-size work ([caching.md](caching.md)), but two expunges were observed
  within hours on deploy day — still worth the one ticket.
- [ ] Announce passwordless sign-in to users if confused-user replies arrive.
- [ ] **Flip `SYMFONY_KERNEL=1` in production's `.htaccess`** once the capsule has
  soaked. The code is deployed either way; the flag is what activates it, and
  reverting is removing the line plus an Apache reload — no deploy. Watch for:
  doubled or missing `Set-Cookie` on sign-in, `Cache-Control` on authenticated
  pages, and the sitemap route's gzip. All three are covered by tests, but the
  capsule is not behind a TLS-terminating proxy and production is.
  - While the flag is off, **production and the capsule run different front
    controllers**. That is deliberate, and it is also the one thing to remember
    before concluding anything from a local reproduction.
- [ ] **Move the unprefixed-to-prefixed locale redirect out of the ported
  controllers and into a `kernel.request` listener above the authorization
  check.** **Now due**: the wayside-shrine port (2026-08-07) made it the third
  copy, which is the threshold this item set for itself. Since the authorization
  bridge landed (2026-08-06) the two front
  controllers disagree about the *unprefixed* form of a restricted path: laminas
  answers `/admin` with SlmLocale's `302 → /en/admin` and denies on the second
  hop, while the Symfony guard runs before the controller that would issue that
  redirect and so denies at once, with `?redirect=/admin` rather than
  `?redirect=/en/admin`. Nobody's access changes, the visitor arrives in the same
  place, and every real caller uses the prefixed form — so this is tidiness, not
  a bug. The reason it is not already done: the redirect is a per-route decision
  (`ShrinesController` and `AdminController` do it, the maintenance endpoints must
  **not**, `/_health` has no prefixed form at all), so a listener needs a
  declaration of its own alongside `RouteAccess`.
- [ ] **Two consoles now exist in principle.** `bin/console` builds the *laminas*
  container and is the deploy's command host; a Symfony console would want the
  kernel. Nothing needs converging yet — `App\Kernel` contributes no commands —
  but the moment it does, decide rather than accumulate: most likely `bin/console`
  registers both, laminas commands from the ServiceManager and Symfony ones from
  the kernel.
- [ ] **Next component adoption.** symfony/console and monolog both landed
  before rung 4b closed; `bin/console` is the strangler seam and `laminas-log`
  is gone. Pick the next one deliberately rather than by momentum.
  - **Now also gated by the FrameworkBundle blocker above** for anything that
    pulls `symfony/cache` — which is most bundles. Standalone components are
    still free (that is how http-kernel and routing got in).
  - **Not validator or translation yet**, despite reading as low-coupling:
    `Laminas\Validator` is in 41 files and `Laminas\InputFilter` in 48, and
    laminas-form *requires* laminas-validator regardless — adopting
    symfony/validator now adds a second validation system while removing
    nothing. It belongs to the form-by-form port, where each form brings its
    constraints along. Translation touches only 12 files but is wired
    through laminas-mvc-i18n into the router and view helpers, and JTranslate
    exists to manage those files; it lands naturally with Twig.

## Next

- [ ] **Flip `SYMFONY_KERNEL` globally** — now the gate on the v3 API being usable at
  all, since agents will not send the canary cookie. v3 and `association-edit` are
  deployed and verified through the canary (2026-08-09); everything else about the flip
  is unchanged from the strangler plan.

- [ ] **More form routes, now that the form layer exists.** `src/Form/BootstrapFormRenderer`
  landed with `association-edit` (2026-08-09) and reproduces TwbBundle's markup
  byte-for-byte. What that unblocks, roughly in order of ease:
  - `sion-model/auto-fix-data-problems` — POST + CSRF, no new element types, and its
    template is already ported for the read-only sibling.
  - `associations/create`, `association-delete` — same form, same renderer; create needs
    the query-parameter prefill `AssociationsController::createAction()` does.
  - `/persons`, `/movement`, `/literature` search forms — new element types likely.
  - **Still blocked:** the user and translation forms (`CreateRoleForm`, `EditUserForm`,
    `DeleteUserForm`, `EditPhraseForm`), which read JUser's unreproduced
    `GlobalAdapterFeature::setStaticAdapter()` through a `NoRecordExists` validator.
    That is the real remaining blocker, and it is narrower than docs/strangler.md used
    to claim.
  - Method that worked and should be reused: capture the live page with
    `tools/form-regression.php probe` **before** porting, then diff after. Five
    non-obvious TwbBundle behaviours only showed up that way.

- [ ] **Retire the two dependency forks when upstream releases.** `slm/locale`
  and `diablomedia/laminas-twb-bundle` resolve from `jroedel/*` branch
  `feat/php-8.4` via `repositories` entries in `composer.json` (each carries a
  `comment` key stating the exit condition). Upstream PRs: diablomedia#27 and
  the SlmLocale one, both open. When either ships a release including the 8.4
  constraint, delete its entry and restore a version constraint.
- [ ] **Consider raising `opcache.interned_strings_buffer`** — the one OPcache
  number actually close to its limit. First live reading after enabling it:
  **73% of the 8 MB buffer used** (74,545 strings), against ~29% memory and ~15%
  of the key table. When the interned buffer fills, strings simply stop being
  interned: no restart, no error, just a quiet loss of the saving the buffer
  exists to provide. `/sm/cache-status` now reports `internedPercentUsed` and
  `smoke-prod.sh` warns at 90%, so this can wait for a real warning rather than
  a guess.
  - **The slot-count worry recorded here earlier was wrong, and the correction
    is worth keeping**: `opcache.max_accelerated_files` is 10000, but PHP rounds
    the script hash table up to the next prime, so the real ceiling is
    `max_cached_keys` = **16229**. Against ~9700 deployed files (2427 keys in
    practice, since keys run ~1.9× scripts) that is ~15% used — not the tight fit
    first reported from the configured number. Measure `maxCachedKeys`, never
    `max_accelerated_files`.
- [ ] **Watch for date-format drift from the ICU downgrade.** The 8.4 build
  ships **ICU 72.1**, older than the 8.3 build's 76.1 (Unicode 15.0, TZData
  2022e). 27 `IntlDateFormatter` call sites now format against older locale
  data. Production smoke passed, so nothing is broken; but if a date or a
  locale display name looks wrong in a non-English locale, this is the cause and
  it is not our code. PHP's own timezone database is separate and current
  (2026.3).
- [ ] **Passkeys (WebAuthn)** — decided 2026-08-02: web-auth/webauthn-lib
  current major, credential table, enrollment inside an authenticated
  session, magic link remains the fallback.
- [ ] **PHPStan: raise from level 0** (34-error / 26-entry baseline). Measured
  2026-08-05 — **do not raise the level before fixing the controller-plugin
  false positives**, or the baseline balloons for no signal. New errors beyond
  the current baseline, per level:

  | level | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 |
  |---|---|---|---|---|---|---|---|---|---|
  | errors | 234 | 673 | 762 | 1094 | 1202 | 2586 | 2745 | 2809 | 3647 |
  | files | 67 | 138 | 152 | 172 | 185 | 264 | 267 | 268 | 316 |

  **Level 1's 234 errors are not 234 problems.** Breakdown: **115 are false
  positives** — `flashMessenger()` (53), `nowMessenger()` (35), `isAllowed()`
  (24), `zfcUserAuthentication()` (2) resolved through
  `AbstractController::__call` against the controller-plugin manager, which
  PHPStan cannot see. **82 are `isset()` on a variable that always exists** —
  harmless dead defensive code. That leaves ~37 worth reading, and the real
  finds are listed under "Bugs" below.
  - **So the sequence is: teach PHPStan the plugin managers first.** Either a
    `DynamicMethodReturnTypeExtension` per plugin, or `@method` annotations on
    the controller base classes. Until then level 1 costs ~200 baseline
    entries to buy ~37 findings, and the "no new errors" contract loses its
    signal. Note this whole class of false positive **disappears under
    Symfony**, where plugins become explicit dependencies — so weigh a PHPStan
    extension against just waiting for the strangler.
  - Harvesting the findings does **not** require raising the level: run
    `phpstan analyse --level 1` ad hoc and read the output. That is how the
    2026-08-05 fixes were found.
  - Getting the *smoke* suite into CI is still open (needs a capsule + dump
    strategy).
- [ ] **Real database migrations** (Phinx or doctrine/migrations) instead of
  hand-run dumps in `database/`. Constraint: the web app's DB user lacks DDL
  rights, so migrations need separate credentials stored only on the server,
  never in the repo.

## Bugs (characterized, fix pending)

- [ ] **The shrine table's "Opening hours?" column tests a column that does not
  exist.** `schoenstatt/associations/shrines-table.phtml` and its Twig port both
  read `openingHoursJson`; the association projection has
  `openingHoursSpecificationJson`. So the column reflects only
  `openingHoursHuman` and the structured hours never count. Found porting
  `shrines` (2026-08-05) and reproduced verbatim in
  `templates/schoenstatt/_shrines-table.html.twig` rather than fixed, because
  the port's contract was identical output. Fix is a one-word rename in both
  templates — but check first whether "has structured hours" is what the
  progress score is meant to reward, since 71 shrines have the human field and
  4 have the JSON one. Still two templates, not four, after the wayside-shrine
  port: both index pages render this same partial on each side of the split.
- [ ] **`shrinesAction()` divides by zero on an empty shrine list.**
  The per-region percentage guards its denominator and the total does not
  (`floor($totalScore / $totalMaxScore * 100)`), so a database with no
  `sch-shrine` rows is a `DivisionByZeroError` rather than 0%. Present in the
  laminas action, in `waysideShrinesAction()`, and reproduced in
  `App\Schoenstatt\ShrineIndex` for parity — which now backs both ported pages,
  so fixing it there fixes both at once. Only reachable if every shrine of a
  kind were deleted, which is why it has never fired; fix the laminas copies
  together with it, or fix it when they are deleted. Note the wayside side is
  the likelier of the two to reach zero rows: 43 associations against 207.
- [ ] `finish-pending-labels` is an unreachable route:
  `Laminas\Router\Http\Part::match()` returns the parent match once the path
  is consumed and the parent `may_terminate`s, so its `Method(delete)` child
  is never consulted — a DELETE dispatches the parent's read action and
  `finishPendingLabelsAction()` is dead code. The label workflow's "commit
  these call numbers" step has never worked. Fix shape: `may_terminate =>
  false` + one `Method` child per verb + bjyauthorize guard entries for the
  renamed terminals. Before enabling, check what `SionTable::updateEntity()`
  records as acting user under JWT (no session identity —
  `setActingUserId()` from `tokenPayload->sub` is the channel).
  `public/api/v1.yaml` documents it as NOT CURRENTLY REACHABLE. Note: this
  route-tree bug class cannot be expressed in Symfony routing — fix now only
  if the label workflow needs the commit step before the strangler reaches
  the API.
- [ ] `GET /api/v1/libraries/:id/books` answers 200 with a zero-byte
  `text/html` body: `BooksApiController::getList()` builds a `JsonModel`
  that never renders. The JWT gate on it works; it is the one gated route
  whose payload no test asserts, for this reason. Cause not investigated.
- [ ] **Three more arity mismatches, all silently tolerated** (PHPStan level 1,
  2026-08-05). PHP discards surplus arguments to userland functions, so none of
  these crash — each is a call that has quietly stopped doing what it reads as:
  - `SionModel\Mailing\Mailer:209` calls `Rand::getString()` with **3 arguments
    for 1-2 parameters**. This is the **magic-link token generator**, so read it
    before touching: the dropped third argument is almost certainly ZF's old
    `$strong` flag, removed in laminas-math 3. Belongs with the
    `laminas/laminas-math` retirement item, which already flags this call site.
- [ ] **Four calls to methods that do not exist** (PHPStan level 1, 2026-08-05).
  Each is a guaranteed `Error` if reached, i.e. dead-or-broken code, and each
  needs a judgement about the intended method rather than a rename:
  all four are now resolved: two went with the Bible module,
  `JTranslateController::redirectAfterDelete()` was replaced with the redirect it
  meant, and `LibrariesController:192`'s `getKnownIssues()` was in a branch
  guarded by an `admin_pages` key that is commented out — dead both ways, so the
  branch is gone. (A fifth, `NowMessenger`'s `setPluginFlashMessenger()`, was
  fixed 2026-08-05 — the setter is `setPluginNowMessenger()`.)
- [ ] **Fourteen "variable might not be defined"** (PHPStan level 1,
  2026-08-05) — each is a read that is only reached on some paths, so the
  failure mode is a null/undefined-warning rather than a crash. One is already
  characterized separately below (`LibraryTable:1038`'s `$checkout` leak); the
  rest: `DhController:76` `$headerText`; `BibleTable:166,174,182,190`
  (`$chapterClause`, `$bookClause`, `$translationClause`, `$verseClause` — one
  cluster, likely the same missing initialization);
  `BooksApiController:277` `$bookId`; `LibraryTable:740` `$cacheKey`;
  `PublicationsTable:1746` `$callNumber`, `:2306` `$isScientific`;
  `FormatPublication:143,145,148,150` `$mainText`; `Telephone:63` `$tooltip`.
- [ ] `/libraries/create` and `/libraries/:id/edit` 500:
  `Books\Form\SearchForm`'s factory throws "only for a specific library"
  without library context (pre-existing, surfaced by the rung-4a audits).
- [ ] `SionForm::setData()` calls `getInputFilterSpecification()` which the
  base class doesn't define.
- [ ] `Books\Filter\SortText` mixes positional and sequential printf
  specifiers in the sort-text format, so a trailing `%-3s` consumes argument 1
  instead of the token it was written for — a `{author|%-3s}` token silently
  renders the first regex capture group. Found 2026-08-04 while characterizing
  the filter, and pinned as-is in
  `test/Integration/SortTextFilterContractTest.php` so the base-class swap
  stayed a refactor. Fixing it changes every affected library's sort order, so
  it needs a product decision plus a re-sort, not a drive-by.
- [ ] `LibraryTable::checkinBooks()`: in the branch creating checkouts for
  books with none, `$booksToCheckin[$checkout['bookId']]` reads `$checkout`
  leaking from the previous `foreach` — wrong key, or undefined variable
  when no checkouts were open. Deliberately unfixed so far (behavior
  change).

## Product decisions needed

- [ ] **Any signed-in account can edit any association.** `route/association-edit` is
  guarded `['sch_moderator', 'sch_user']` and registration grants `sch_user`, so
  "registered" and "may edit every shrine in the database" are the same thing today.
  Surfaced while porting the edit form (2026-08-09); the port neither widened nor
  narrowed it, and `test/Smoke/AssociationEditSymfonySmokeTest` records the current
  behaviour rather than endorsing it.
  - This is why the v3 API uses a dedicated `sch_api_bot` role instead of reusing
    `sch_user`: a bot token had to not be a general-purpose site credential.
  - The decision is whether moderation should be a role someone is *given*. If yes, the
    change is one guard entry plus granting `sch_moderator` to whoever should keep the
    ability — and the fuzz/ACL baselines will show exactly who loses it. Deliberately
    not done unilaterally: it takes an ability away from every existing account.

- [ ] **Should agents be able to create shrines?** v3 ships PATCH-only by decision
  (2026-08-09). Creation would have to settle what an agent-created association's
  `kind`, `parentId` and associated roles are — `SionTable::createEntity()` has side
  effects (`createAssociatedRoles()`) that a moderator makes implicitly and an agent
  cannot. Revisit once there is real traffic and a sense of what agents actually
  propose. `required_columns_for_creation` for `association` is `['name', 'kind']`.

- [ ] **Retire the shrine GeoJSON feed, or commit to it** — deprecated
  2026-08-07 at the user's direction ("I'm not sure we'll use it going
  forward"). `/api/v1/associations/shrines.json` and its v2 twin are byte-identical
  and both now answer with `Deprecation: true` (IETF draft header). They are
  **still served and still correct**: deprecation announces intent, it does not
  break callers.
  - **No `Sunset` header, deliberately.** RFC 8594 wants a date, and naming one
    would commit to a removal nobody has decided on. The moment a date exists,
    add it in both places — `App\Controller\ShrinesGeoJsonController` and
    `Schoenstatt\Controller\AssociationsApiV{1,2}Controller::shrinesJsonAction()`.
    The header is set in both because production still serves the laminas one.
  - **The decision needs usage data, and there is currently none.** Nothing logs
    calls to these endpoints, so "is anyone still using it?" can only be answered
    from the hoster's access log. The feed exists because of Alberto León's
    "Schoenstatt Shrines" app (see the submitting-photos page), so the honest
    first step is asking whether that app still reads it.
  - Retiring it is cheap when the answer arrives: two route declarations, one
    Symfony controller, two laminas actions, `SchoenstattTable::getShrineGeoJson()`,
    and `test/Integration/ShrineGeoJsonParityTest` +
    `test/Smoke/ShrinesGeoJsonSmokeTest`. `getShrines()` stays — the shrine index
    needs it.


- [ ] **Suggest and moderate: users propose corrections, moderators accept or
  deny them** — wanted, never built. This is the collaborative half of the
  site's purpose: a reader who knows a date is wrong should be able to say so
  without holding an editing role, and someone with the role should be able to
  accept it with one click. The 2020 WIP that sketched it was **deleted
  2026-08-05** — routes, two forms, two `SionForm` methods, four templates and
  two view helpers, none of it ever reachable. Two things from the wreckage
  are worth knowing before building it properly.
  - **It was never wired at all, not merely half-wired.** All seven routes
    (`admin/moderate`, `admin/data-problems`, `persons/person/suggest|moderate`,
    `assignments/assignment/suggest|moderate`) named controller actions that do
    not exist in `AdminController`, `PersonsController`, `AssignmentsController`
    or their `SionController` parent, so every one of them was a 404-by-fatal.
    `SionController::showAction()` never built the suggest form either — the
    block that would have was commented out with `//@todo enable suggest form`.
    Nothing about the feature was ever exercised, so there is no behavior to
    preserve and no data path to reverse-engineer.
  - **`SionForm::setInputFilterSpecification()` is a silent no-op for all but
    three of its 21 subclasses**, and this is the trap the old design walked
    into. Only `SuggestForm` (now deleted), `PersonForm` and `AssociationForm`
    override `getInputFilterSpecification()` to read back `$this->filterSpec`;
    for the other eighteen the setter writes a property nobody reads, because
    the subclass returns a literal array. `prepareForSuggestion()` added
    `suggestionNotes`, `suggestionByPersonId` and `suggestionByEmail` to the
    form and then set their filters through that setter — so on any form but
    those three, the suggestion fields would have accepted arbitrary input
    with zero filters and zero validators. A real implementation must either
    fix the base class so the specification is genuinely composable, or keep
    the suggestion fields in a form of their own.
- [ ] **Data-derived authority for persons and associations** — wanted, not
  started. The intent: someone holding an office may edit the people and
  associations beneath it, rather than authority coming from a flat global
  role. The 2020 WIP that gestured at this (`SchoenstattTable` implementing
  BjyAuthorize's resource/rule provider interfaces) was **deleted 2026-08-04**;
  it was never a foundation, and the notes below are what was worth keeping
  from it.
  - **The schema already supports the whole model.**
    `sch_associations.Parent` is the association tree (397 of 498 have a
    parent); `sch_roles` is 1468 offices, each scoped by `AssociationId`, with
    `IsMainRole`/`IsSinglePosition`; `sch_assignments` is person↔office with
    `StartDate`/`EndDate` (266 rows, 227 current). No migration needed to
    answer "who currently holds which office, and what sits beneath it".
  - **A second, older attempt at the *display* half also exists and is
    unfinished in three separate places** — characterized 2026-08-05 and left
    alone deliberately, because completing it means writing a missing view
    helper rather than repairing a break. Associations already show their
    office holders; persons never have. The chain, top to bottom:
    `schoenstatt/persons/show.phtml:218` guards the assignments panel on
    `! empty($object['assignments'])`, and a person's `assignments` key is never
    populated because `SchoenstattTable:1799`'s
    `connectEntityRolesAndAssignments('person', $entities)` is **commented out**
    (the `association` call on line 464 is live, which is why that side works);
    the panel would call `$this->formatPersonAssignment(...)`, and
    `Schoenstatt\View\Helper\FormatPersonAssignment` is **registered under no
    alias**, so it would be a `ServiceNotFoundException`; and that helper
    calls `$this->view->formatScope(...)`, for which **no class and no
    registration exist anywhere in the repo**. So it is three layers deep, and
    the panel has never rendered for anyone.
    - Worth stating because it reads like a live bug and is not: with the data
      link commented out the guard is always false, so nothing ever reaches the
      unregistered helper. Registering the helper on its own would *create* the
      500 rather than fix anything, by exposing the missing `formatScope`.
    - Also note `FormatPersonAssignment` has an `echo ' ';` mid-method where
      every other branch appends to `$finalMarkup`, so it would emit a stray
      space ahead of the panel's own output. Small, but a sign of how far from
      finished it is.
  - **It must be built with ACL assertions, not static rules.** This is the
    load-bearing constraint and the reason the old code was a dead end: a
    static `['allow' => [[roles, resource], …]]` array is evaluated once at
    ACL-build time and can only say "role R may touch resource X" — never
    "*this* user may touch *that* person, because of where they both sit in the
    tree". That relation has to be evaluated per request. (The deleted code's
    own docblock promised an `AssertionAggregate` while returning a plain
    array, which is exactly where it stopped.)
  - **Assertions also dispose of the boot-cost objection** recorded here
    earlier: the old providers loaded every person and association on every ACL
    build, whereas an assertion needs only the acting user's current offices
    plus an ancestor walk.
  - Note this is **unrelated** to the unguarded-routes item above: the old
    providers only ever emitted `person_*`/`association_*` resources, never
    `route/*` ones.
- [ ] **The four feature ideas that used to live in `upcoming_features`.** That
  top-level config key (and its empty `known_issues` sibling) fed a
  website-status page whose route was commented out years ago, so nothing has
  read either one since; the key was deleted 2026-08-05 and its content is
  here instead, unjudged, in the author's own framing.
  - *Automatic repeat-translations* — when a new phrase is inserted for
    translation, check whether the same phrase already exists in another
    domain and carry its translations over.
  - *Automated data issue tracking* — classify missing-information problems on
    records as high/medium/low importance, to drive data completeness
    systematically rather than by noticing.
  - *New email verification system* — of the personal contact fields, email
    matters most, and the existing verification data is stale.
  - *Photo upload system* — user-uploaded photos, especially of course life,
    as the single biggest improvement for an average reader of the site.
- [ ] API registration allow-list (old @todo in `LoginV1ApiController`): API
  magic-code login does not auto-create accounts; the web flow does.
- [ ] Drop the now-unread `user.password` column once passwordless has
  soaked (deliberate migration).
- [ ] Library import form takes a hand-typed *server* path (the 2021
  Windows-desktop-path failure). Real fix is accepting an upload — a fresh
  feature on top of `Books\Service\SpreadsheetReader`.

## Config rot / small cleanups

- [ ] **Routes with no bjyauthorize guard entry**, so under default-deny they are
  unreachable for every role — not restricted, *inaccessible*. Re-measured
  2026-08-05 by `tools/acl-table.php` after the Bible removal: **29 names, of
  which only 18 are real endpoints.** The other 11 are Part-route parents with
  `may_terminate` false, which can never be the matched route name, so their
  missing guard costs nothing — an earlier count of 30 did not separate these
  and overstated the problem by more than half.
  - **7 of the 18 are pure dead config**: the route names a controller action
    that does not exist, so granting a role would only turn "reachable by
    nobody" into a fatal. Delete route and guard together:
    `admin/data-problems`, `admin/moderate`,
    `assignments/assignment/suggest`, `assignments/assignment/moderate`,
    `jtranslate/clear-cache`, `libraries/library/import`,
    `sion-model/delete-entity`. The four Schoenstatt ones belong to the
    abandoned suggest/moderate feature; see the deletion clusters below.
  - **4 answer themselves from their siblings** and need no product decision —
    every neighbouring route in the same tree already agrees:
    `checkouts`, `checkouts/checkout`, `checkouts/checkout/edit` → `lib_user`
    (as `checkouts/library`, `…/current`, `…/overdue` all are);
    `publication-upload-cover` → `pub_moderator` (as `publications/create`).
  - **`sign-in-no-cookies` is already reachable, by accident of listener
    priority** — worth writing down because the obvious reading is wrong. It
    looks like the cookieless sign-in explainer must be broken under
    default-deny, and it is not: `GdprStrategy::onRoute()` swaps the RouteMatch
    for this route at priority **-5000**, while `BjyAuthorize\Guard\Route`
    checks at **-1000**, and higher priority runs first. So the guard evaluates
    `zfcuser/login` (which is granted), approves it, and only afterwards does
    the strategy rewrite the match. The guard never sees this route's name.
    It should still get a public entry (`['guest', 'user', null]`) so that
    direct navigation works and so reachability stops depending on two
    listeners' relative priorities — but it is a robustness fix, not a live
    bug, and it should not be described as one.
  - **`libraries/library/delete` → `lib_administrator`**, deliberately *not* the
    `lib_user` its siblings carry: it is the destructive one in that tree and
    `lib_administrator` already exists for exactly this.
  - **`api-v1/libraries/books/patch-list` → `guest, user`**, matching
    `api-v1/libraries` and `api-v1/libraries/books`. The real gate on the write
    APIs is the JWT check in the controller, not the route guard.
  - **The events write routes genuinely need a decision**: `event-edit`,
    `event-delete`, `events/create` (and `event`, the show route). `events`, the
    list, is `guest, user`. There is no events moderator role in `user_role` —
    the nearest analogues are `pub_moderator` for publications and
    `texts_moderator` for texts — so picking one is a product call about who
    curates the timeline, not something to infer. `event` (show) can safely
    match `events`.
  - **Not** related to the disabled `SchoenstattTable` provider below, despite
    an earlier note here saying so: that provider only ever emitted
    `person_*`/`association_*` resources, never `route/*` ones, so it could not
    have guarded a route.
- [ ] **8 template permission checks name an ACL resource that does not
  exist**, listed in `AclGuardRouteDriftTest::KNOWN_DEAD_PERMISSION_CHECKS`.
  `BjyAuthorize\View\Helper\IsAllowed` answers *false* for an unknown resource
  rather than throwing, so each is a button that never renders for anyone:
  `route/admin/moderate` (deny-button-partial),
  `route/persons/person/edit-contact-info` and `…/edit-private-info`,
  `route/fathers/father/edit-contact-info`,
  `route/publications/advanced-search`, `route/suggest`. Fixing means naming
  the right route *and* granting it, so it belongs with the item above.
- [ ] Sweep legacy `Zend\*` strings from the merged runtime config as the
  remaining vendor modules are replaced.
- [ ] Re-track `public/.htaccess` (or a `.htaccess.dist`) — untracked,
  hand-edited server-side state since 2017. Now more than tidiness: it is where
  `SYMFONY_KERNEL` gets set (see [strangler.md](strangler.md)), so the switch
  between front controllers lives in a file no commit can describe.
  - Found 2026-08-05 while wiring that flag: because `AllowOverride All` is on,
    `.htaccess`'s `SetEnv "APP_ENV" "production"` **wins inside the capsule too**,
    which makes `docker/apache-vhost.conf`'s `SetEnv APP_ENV "development"` dead
    config. So the capsule has always run in production mode — `display_errors`
    off included. Whether to change that is a real decision (the capsule's
    error-page behaviour would change), which is why it was only measured, not
    fixed.
- [ ] **`cs-check` is already red on master**, and was before the strangler work
  — measured 2026-08-05. Four findings, none of them in code touched recently:
  `module/Schoenstatt/view/schoenstatt/assignments/edit.phtml` (closing brace
  indent + a 132-char line), `…/assignments/fields-partial.phtml` (136-char line),
  `…/roles/fields-partial.phtml` (header blocks), and `public/index.php` (the
  file-level docblock sits after the `use` statements, which PSR-12 forbids —
  verified present on master's copy, so not caused by the new front controller).
  Three are auto-fixable; the docblock one wants a human. Until this is cleared,
  `cs-check`'s exit status carries no signal, which is worse than the four
  cosmetic errors.
- [ ] Production's server-side `local.php`: dead `acmailer_options` key
  (harmless), and it still leaks stack traces via its error-display config —
  clean both next time a deploy touches server config.
- [ ] Two Application factories still sit on the root-namespace
  `FactoryInterface` shim (deferred from rung 4a).
- [ ] The exception notifier double-reports a failure that also kills the
  error-page render: the nested report loses the RouteMatch and fingerprints
  as route `(none)` (seen 2026-08-03 — fingerprints 1615c790 + 35c198d9 were
  one incident). Dedupe, or carry the outer route into the nested report.

## Performance / caching

Background and measurements: [caching.md](caching.md).

- [ ] `query-objects-publication` (10,166 rows × 80 fields, 29.2 MiB) is
  still built by the literature routes — now refused rather than
  cache-wiping, so they run uncached. A narrow projection (as done for the
  navigation) is the next real win.
- [ ] `LibraryTable::getLibraryBooksStatuses()` hydrates every entity for a
  library (up to 12,394 books) then links checkouts — next projection
  candidate.
- [ ] Navigation cache keys written in `Application\Module::onBootstrap()`
  are never invalidated on data change (only `SionCacheTrait`-registered
  keys are) — editors see "my change didn't appear" until the 5-day TTL.
- [ ] `SionCacheTrait::getUnlinkedAssignments()` declares dependencies
  `['assignment', 'association']` but the honest list is
  `['assignment', 'role']` — harmless over-invalidation, still wrong.

## Testing & CI

- [ ] **Run-time sweep for the deprecations a static pass cannot see.** All
  three known ones are now fixed — `SionTable:255`'s dynamic
  `$changeTableName` and `CountriesInfo:89`'s `strtoupper(null)` on 2026-08-05,
  `SchoenstattLinkIdentifier::$entityType` on 2026-08-04 — so what remains is
  the *method*, not a list. These fire only when code runs, which is why rung
  4b's `E_ALL` class-load probe missed all three; booting the app under a
  handler that promotes `E_DEPRECATED` found them in seconds.
  - **Cheapest next step, worth doing before PHP 9 is close:** run the smoke
    suite with deprecations promoted to failures and see what the 77 HTTP
    paths turn up. Booting alone only reaches constructors and bootstrap
    listeners; the deprecations that matter hide in request handling.
- [ ] Shared-library tests live in the app repo (`test/Unit/TextTest.php`
  covers `SionModel\Text\Text`) because the submodules have no test
  infrastructure. Migrate them into laminas-sion-model/juser/jtranslate so
  patres inherits them.
- [ ] Keep the form-regression baseline (`tools/form-regression.php`)
  rebased — it is the designated safety net for the Symfony form migration.

## Deploy ops

- [ ] **Drop the `?key=` fallback** now that every endpoint also accepts an
  `X-Api-Key` header (`SionModel\Controller\MaintenanceKeyTrait`). The query
  parameter still works only because the deploy config that sends it lives in
  each machine's gitignored `phploy.ini`, which no commit here can update.
  Sequence: land the header change, update `phploy.ini` on every deploying
  machine from the new `phploy.ini.dist`, deploy once, then delete the
  fallback from the trait. Until then the leak is still reachable — a caller
  that keeps using `?key=` keeps writing the key to the access log.
  - Since 2026-08-05 there are **two** places to delete it from: the trait, and
    `App\Http\MaintenanceKey` for the two endpoints ported to the Symfony kernel
    (docs/strangler.md). Both accept the same two channels on purpose, so that
    flipping `SYMFONY_KERNEL` cannot break a deploy hook; the sequencing above is
    unchanged, the final step just touches both files.
  - Established 2026-08-04, worth not re-deriving: **the flush cannot become
    a pure CLI command.** The persistent cache is the APCu adapter, and an
    APCu segment belongs to the SAPI that created it, so a CLI process gets
    its own (or none, with the default `apc.enable_cli=0`). Measured: a CLI
    `apcu_clear_cache()` left all 21 web-segment entries untouched. Only an
    HTTP request into the web SAPI can flush it, which is what
    `cache:flush-persistent` does.
- [ ] Port `/en/associations/do-work` to a console command. It is the last
  deploy hook that is still a `wget` (now header-authenticated, so it is no
  longer a leak — just the odd one out). Unlike the cache flush this one is
  genuinely CLI work: `SchoenstattTable::autoFillTimeZones()` and
  `updateAssociationMd5s()` touch only the database. Needs an acting-user
  decision first — `ActingUserProviderInterface` has no session identity on
  CLI.
- [ ] phploy upstream PRs (banago/PHPloy): the directory-purge bug (deletes
  parent-directory chains recursively, took out module/JUser/src on deploy
  day) and `--list` silently skipping submodules. Alternatively, if phploy
  keeps chafing: evaluate Deployer (atomic release dirs + symlink switch
  would also kill the mid-deploy broken window).
- [ ] Production runs FastCGI — "what bounds concurrent PHP memory?" has
  never been asked of the Hetzner account (the capsule's ceilings don't
  apply there).
- [ ] Content-Security-Policy header: prerequisite is an audit of every
  inline `<script>`/`<style>`/`on*=` across the `.phtml` templates (likely
  nonce- or hash-based). Roll out via `Content-Security-Policy-Report-Only`
  first. Natural rung: alongside asset-pipeline work — or fold into the Twig
  migration, which touches every template anyway.
- [ ] **Retire `laminas/laminas-math`** (abandoned). It is *not* an 8.4 gate —
  3.8.1 allows `~8.4.0` — so it is now a direct dependency rather than a
  blocker, pulled in explicitly when `laminas-crypt` went. But it is 11
  `Laminas\Math\Rand` call sites across 7 files, and some are
  security-relevant, so it is its own piece of work and not a drive-by:
  `Rand::getInteger()` → `random_int()` is trivial (ClipboardButton,
  `layout.phtml`), while `Rand::getString()` needs its charlist preserved
  deliberately — `SionModel\Mailing\Mailer` (magic-link token),
  `JUser\Model\User` (verification token) and `LoginV1ApiController` (JWT id)
  all generate secrets with it. Check `Rand::getString()`'s default charlist
  before touching the calls that omit one (`CspListener`, `LoginV1ApiController`).
- [ ] Drop the stale `laminas/laminas-crypt` require from JUser's
  `composer.json` — nothing in JUser uses it. Cosmetic for this app (the
  submodule's composer.json is not read; the root one governs installation),
  but it misleads anyone installing JUser as a package.
- [ ] Consider narrowing the application log's level. Both loggers write at
  `Level::Debug` because that is exactly what laminas-log did (a Logger with a
  Stream writer and no priority filter wrote every event), so
  `SionCacheTrait`'s per-cache-write `debug()` calls have always landed in
  `data/logs/application_*.log` — hence its size. Raising the threshold is a
  real decision about what the log is for, deliberately not folded into the
  monolog port.

## Shared libraries / patres convergence

Plan decided 2026-08-02: the `1.0.x` branches (patres's frozen 2020–2022
Laminas line) are a review source to **port from, never merge** — both lines
rewrote the same files. Converge everything on `modernization`.

- [ ] At each rung, diff the files being touched against `1.0.x` first
  (typing/SionCacheService work belongs to a PHP 8 pass; language-file
  additions to an i18n pass over the new auth views/emails).
- [ ] Migrate patres onto the converged line (now unblocked — the shared
  libs are stable on 8.3). It inherits passwordless auth, geoip removal, the
  fatal-200 fix, and the ActingUserProvider seam. Mechanical checklist:
  convert its table factories to inject `ActingUserProviderInterface`; the
  raw `$this->actingUserId` property is gone (`getActingUserId()` replaces
  it); check `setActingUserId()` callers (it survives as the token-auth
  override); check its `SionTable` subclasses against the still-eager
  `$entityProblemPrototype` (two consumers here read the raw property — a
  lazy getter would hand them null); supply `public/css/email-default.css`
  for the Mailer or pass a path.
- [ ] patres's `1.0.x` SionCacheService still has the fatal-200 `unset()`
  bug (worse on PHP 8: typed property → immediate Error). Fix branch
  `fix/cache-write-failure-wedge` is pushed on laminas-sion-model; the PR
  against `1.0.x` must be opened by hand (the gh token lacks access).
- [ ] Retire the `0.3.x`/`1.0.x` branches once patres converges.
- Parked (superseded by the Symfony decision): the ~102-factory Interop→Psr
  + `: mixed` sweep. Revisit only if the strangler stalls and SM4 becomes
  reachable after all.
