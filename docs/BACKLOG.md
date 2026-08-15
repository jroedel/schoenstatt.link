# Modernization roadmap

Current truth only — no journal. Closed work moves to [history.md](history.md)
(or lives in git); when an item here is done, delete it and record anything
reusable there instead of accumulating DONE narratives.

State as of 2026-08-07, on branch `symfony-upgrade` (not yet merged): the
**capsule runs PHP 8.5.9**, and since 2026-08-11 so does production — they had
been deliberately different for a week; see [php-85.md](php-85.md), which also
explains why `config.platform.php` stays at 8.4.24 regardless. `composer audit --locked` reports zero advisories.
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

**The Symfony kernel sits in front of laminas-mvc everywhere**, with a catch-all
route delegating every unported path back to it — see
[strangler.md](strangler.md) for the mechanism, the response-conversion rules and
how to switch front controllers. **Production has served through it since the
2026-08-11 deploy**; `curl https://schoenstatt.link/_health` answering
`{"status":"ok","kernel":"symfony"}` is the cheapest way to confirm which one is
live. The capsule and production no longer differ, so a local reproduction is
once again a reproduction.

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

## Deferred indefinitely

Not abandoned and not retracted — the evidence below stands as measured. What changed is
the priority, and the reason is recorded so the decision can be re-examined rather than
rediscovered.

- [~] **Every deploy has a window in which visitors get fatals.** **Deferred
  indefinitely 2026-08-14 by the user: the audience is currently almost entirely bots.**

  The reasoning inverts the usual one, which is why it is worth stating. The cost of a
  deploy-window fatal is paid by whoever happens to be mid-request, and today that is
  overwhelmingly crawlers — the `51c0cb27` fingerprint alone, one scraper replaying a URL
  list it built while a button was public, produced **3,478 requests in eleven days**
  against a handful of identifiable human sessions. Fixing atomicity would mostly protect
  Googlebot's opinion of us. That is real, but it is not urgent, and the fix costs a
  pipeline redesign plus edits to a credentials file only the user can touch.

  **Revisit when that ratio changes** — a launch, an announcement, or any sustained human
  traffic. The measurement does not go stale; the priority does.

  The evidence, kept intact:

  Measured 2026-08-14 by pulling the store down with `tools/fetch-exceptions.sh`.

  Of 38 fingerprints, 21 are real failures (the rest are the deny-listed
  `UnAuthorizedException` noise) — and **17 of those 21 are deploy artifacts**, not bugs.
  They arrive in **nine tight bursts, each at a distinct deployed revision**, in five
  recognisable shapes:

  | shape | count | what the request saw |
  | --- | --- | --- |
  | `ParseError: Unclosed '[' / '('` | 2 | a `.php` file read while half-uploaded |
  | `Class "App\Kernel" / "SionModel\Form\DatePrecision" / "…\AbstractPatternValidator" not found` | 7 | the referenced file not uploaded yet |
  | `A plugin by the name "requestUri" was not found` | 4 | stale merged-config cache |
  | `Too few arguments … 5 passed … 6 expected`; `routes(): … int returned` | 2 | new call site against old signature; truncated `routes.php`, so `require` returned `1` |
  | `Module (Application) could not be initialized`; `addRuleProvider(): … EventTextTable given` | 2 | mid-upload module tree; config cache still naming a provider removed in `f19a5ca` |

  **What makes this conclusive rather than inferred**: the 2026-08-04 22:09 burst spans
  **two revisions in 22 seconds** (`c682f9135b31` and `0dbe2ae6513a`), so `.revision` was
  being rewritten while requests were in flight. Real traffic was hit — a Chrome visitor
  on `/pt/books/40860` got `Class "App\Kernel" not found`, Googlebot and PetalBot got
  others.

  **Cause**: phploy writes file-by-file over SFTP straight into the live docroot. Nothing
  about it is atomic, and no amount of care in the code prevents it.

  **This is a pipeline design decision, not a patch**, which is why it is recorded rather
  than done. The shape of the fix is upload-to-a-release-directory then swap — which
  phploy over SFTP cannot do by itself, so it needs the port-222 shell hooks — or a
  maintenance flag that 503s for the duration, which trades fatals for downtime and is
  much cheaper. Either touches `phploy.ini`, which holds credentials and must be edited
  by the user.
  - Of the remaining 4: two are a host wobble on 2026-08-07 ~00:28 (`MySQL server has
    gone away`, and an SMTP `535` in the same minute — which is why one exception email
    never arrived, and shows as `FAILED` in the summary table), and two are a
    `laminas-session` FlashMessenger type mismatch from 08-03 on PHP 8.3 that has not
    recurred in eleven days.
  - **Zero steady-state code bugs.** Worth stating plainly, because it is the good news
    and it is easy to lose behind 38 rows of table.

## Now

- [x] ~~Watch for 401 fallout from the API authorization fix (live since
  2026-08-03)~~ — **moot 2026-08-14: the whole of `/api/v1` and `/api/v2` was
  deleted.** Nothing can 401 there any more; every one of the 26 URLs answers a
  JSON 410 Gone. The label-printing workflow is still the thing to watch if a report
  arrives, and it is now a 404 rather than a 401 that would arrive — but eight
  years of access logs say the last non-scanner caller of any of these endpoints
  was a Google Apps Script on 2022-11-05. See docs/strangler.md.
- [ ] Hetzner/konsoleH support ticket, **half landed**: `apc.shm_size` is now
  **256M** (verified 2026-08-11 from a live phpinfo, on the 8.5 build), so the
  size half of the ask is done and both historical offenders — 45.7 MiB and
  29.2 MiB — would now fit in the segment. **`apc.ttl` is still 0**, which is
  the half that did not: a failed allocation still expunges the entire cache
  instead of evicting selectively, so the failure *mode* is unchanged and only
  its likelihood dropped. Remaining ask is one line, and the ini to name is
  now `/home/httpd/php85-ini/ourlink/php.ini` — the path moves with every
  konsoleH PHP version, which is the trap that makes tuned values silently
  revert on a flip.
- [ ] Announce passwordless sign-in to users if confused-user replies arrive.
- [ ] **Remove the footer's serving note when the migration ends.** Every HTML page
  carries one muted line saying which front controller and which renderer produced it,
  because rows 1 and 3 of that table render identical markup and the deciding cookie is
  invisible (see [strangler.md](strangler.md)). It is deliberately temporary. Deleting it
  is one commit: the two `serving-note` paragraphs in `templates/layout.html.twig` and
  `module/Application/view/layout/layout.phtml`, `App\View\ServingNote`, the
  `serving_note` Twig function, the `servingNote` view helper plus its factory and its two
  config entries, `test/Unit/ServingNoteTest`, `test/Smoke/ServingNoteSmokeTest`, and rule
  8 in `tools/port-baseline.php`.
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

- [ ] **The comment form's open redirect.** `SionModel\Controller\CommentController::
  redirectAfterCreate()` redirects to `$data['redirect']` — a hidden form field — under a
  `@todo confirm that redirect is a valid route`. Narrowed by CSRF (the POST must come
  from a page this site rendered) and by `route/comments/create` being guarded `user`, so
  a signed-in visitor can only bounce themselves. Reproduced rather than fixed when the
  route was ported on 2026-08-12: `App\Controller\CommentCreateController` refuses only
  what laminas would also refuse. Fixing it means deciding what a valid target is —
  path-absolute and same-origin is the obvious answer — and changing **both** front
  controllers at once.

- [x] ~~**`publications/admin-tasks` and `publications/trim-titles` mutate on a GET.**~~
  **This item was wrong on both counts, and the correction is worth keeping** because it was
  read and acted on as a security finding before anyone opened the code. Measured
  2026-08-14:

  - `adminTasksAction()` wrote **nothing**. `PublicationsTable::fillDatePublished()` and
    `::clearCopyrightYear()` were **empty method bodies** — declared, documented, never
    implemented, no other definition and no parent to inherit one. The action called both,
    collected two nulls and `var_dump()`ed them into a view. A dead page, not an unconfirmed
    mutation.
  - `trimTitlesAction()` **simulates by default**. The line is
    `'0' !== $this->params()->fromQuery('simulate', '1')`, so the default is *simulate*; it
    writes only on an explicit `?simulate=0`. This item claimed the inverse.
  - The route paths are under **`/literature`**, not `/publications` — the `publications`
    route's own path is `/literature`, so the URLs were `/literature/admin-tasks` and
    `/literature/import`. The route *names* here were right and would still have misled
    anyone reaching for a browser.

  **The real GET-mutation was `publications/import`, which this item never mentioned.**
  `importAction()` was a plain GET calling `importForschungs()`, which ran
  `createEntity('publication', …)` for every row of `data/import/forschungs.json` not already
  stored — no POST check, no confirmation, despite its own docblock saying *"If the user
  accepts, and POSTs the order to import, they will be imported."* Measured in the capsule:
  4,176 books in the file against 4,165 distinct stored `DataSourceId`s, so one click
  inserted about a dozen publications. It also `echo`ed raw `<pre>print_r()</pre>` into the
  middle of the page when `?id=` matched a row.

  **Retired rather than fixed, 2026-08-14**, on the decision that the Forschungsbibliothek
  import is no longer needed: the two empty methods, `adminTasksAction()`, `importAction()`,
  `importForschungs()` and its two private helpers, both routes, both guard entries and both
  view templates are gone — 660 lines. `/literature/import` and `/literature/admin-tasks`
  answer 404 on both front controllers. The **spreadsheet** import path
  (`library-imports/*`, `libraries/library/import`) is untouched, as are `trim-titles` and
  `prime-authors`. `data/import/` is gitignored, so the 12 MB JSON was never deployed and
  nothing needed removing from the server.

- [x] ~~**`trim-titles` still changes data on a GET.**~~ **Retired 2026-08-14**, after
  measuring where the titles it cleaned actually came from. The question asked was whether it
  guards against a live ingress or is archaic, and the answer is unambiguous:

  - **The live ingress is already validated.** `PublicationForm`'s `title` input carries
    `StripTags`, `StripNewlines`, `StringTrim` and `ToNull` plus a 300-character
    `StringLength`, so stray whitespace cannot reach the column through the form at all.
  - **104 rows would have been touched, and 99 trace to imports.** 44 are
    `forschungsbibliothek` (the importer retired in this same change), 17 `b_bibsek`, 1
    `b_bibprim_edition` — and of the 42 with *no* DataSource, **37 have a byte-identical
    twin that does have one**. That is `copyDataSourcedRowToFirstClassCitizen()`, which
    unsets `dataSource` *and* `createdOn` when it duplicates an imported row, so an
    import-descended record looks first-class and freshly created. Their dates say the same
    thing: 39 of the 42 created in 2021, 3 in 2019, none since.
  - **Titles have all but stopped being written**: 648 logged title writes in 2017, 71 in
    2018, 28 in 2019, 5 in 2020, 6 in 2021, 1 in 2023, 2 in 2024, **none in 2025 or 2026**.
  - **Of all 761 title writes ever logged, exactly two produced a value the sweep would
    change — and both are legitimate titles it would have corrupted.** `The family at the
    service of Life. [1953] Recollection days for couple.` ends in an ordinary sentence
    period, which the sweep strips. `Welch ein September - P. Joseph Kentenich '85 = Qué
    septiembre ...` ends in an ellipsis and survives only by accident, because the
    `$howMany < 3` guard skips a four-character trim.

  So it was a one-off cleanup for catalogue records from three ingresses, two defunct and one
  deleted, and on today's data it is the only thing in this file that both writes and gets it
  wrong. Action, route, guard entry and view removed.

  **`prime-authors` was examined at the same time and is read-only** — `getAuthors()` builds a
  report array and writes nothing — so it stays, and that is now checked rather than assumed.

- [x] ~~**`PublicationsTable::fillNoAccentsColumns()` is now unreferenced.**~~ **Removed
  2026-08-14, and the entry above it was wrong about what it did.** It does not fill anything:
  it selects Spanish rows, computes an array of proposed `*NoAccents` values and **returns it
  without ever writing**. The caller that would have consumed it was the commented-out line
  in the deleted `adminTasksAction()`, so nothing has ever written those columns through this
  path. `importAuthors($simulate)` — a body of nothing but comments — went with it.

- [ ] **`TitleNoAccents`, `SubtitleNoAccents`, `AuthorsNoAccents` and `EditorNoAccents` are
  dead columns.** All four exist in `sch_publications`; `titleNoAccents` and
  `subtitleNoAccents` are mapped in the publication entity spec and read into the projection
  row, and **nothing consumes either** — no query, no template, no search, verified across the
  repository. The only code that ever computed values for them never wrote them (see above)
  and is now gone. So they want dropping, which is DDL: the web application's database user
  has no DDL rights, so this is a server-side migration with separate credentials, filed with
  the other real-migrations work rather than done here.

- [ ] **`route/publications/advanced-search` is not a resource this application defines.**
  `search-bar.phtml` asks `isAllowed()` about it and `docs/acl-rules.md` has no row for it
  — no route, no guard entry. `App\Controller\LiteratureController::advancedSearchUrl()`
  catches the failure and reads it as "no", which is defensive rather than correct. Belongs
  with the other misnamed guards below.

- [ ] **The association page's "Up-to-date" tooltip says `jeff`.** A hardcoded literal
  where a user name belongs — `sprintf($this->translate('Updated by %s %s'), 'jeff', …)` —
  carried over verbatim by the 2026-08-12 port. A content decision, not a porting one.

- [ ] **More form routes, now that the form layer exists.** `src/Form/BootstrapFormRenderer`
  landed with `association-edit` (2026-08-09) and reproduces TwbBundle's markup
  byte-for-byte. What that unblocks, roughly in order of ease:
  - `sion-model/auto-fix-data-problems` — POST + CSRF, no new element types, and its
    template is already ported for the read-only sibling.
  - **Done:** the create surface, batch 9 (2026-08-15) — **nine of the sixteen**
    `*/create` routes. The sizing note this item used to carry was wrong in a way worth
    keeping: it predicted "much less shared leverage than the edit or delete surfaces gave,
    because each entity has its own template". In fact **13 of the 15 create routes use the
    same form class as their already-ported edit route**, and both laminas view scripts
    render the same `fields-partial.phtml` — so the leverage was the same, once the field
    lists were extracted into shared partials first. Seven routes stay on laminas and two of
    those are because they are **broken there**; see the two items at the top of "Bugs"
    above and [strangler.md](strangler.md).
  - `/persons`, `/movement`, `/literature` search forms — new element types likely.
  - **Done:** the delete surface, batch 8 (2026-08-14) — seven routes on one controller and
    one template. Five of the twelve `delete` routes are *not* portable and it is worth
    knowing why before counting them: three are reachable by nobody and two have their own
    controllers. See [strangler.md](strangler.md).
  - **No longer blocked, and this was the last blocker:** the user and translation forms
    (`CreateRoleForm`, `EditUserForm`, `DeleteUserForm`, `EditPhraseForm`). All four read
    JUser's unreproduced `GlobalAdapterFeature` static registry through a
    `NoRecordExists`/`RecordExists` validator; as of 2026-08-14 all four take a
    `Laminas\Db\Adapter\Adapter` as a constructor argument and the registry is gone from
    first-party code. **Nothing in the form layer blocks a route now** — the remaining
    work is porting, not unblocking. Two findings from that change are recorded in
    [strangler.md](strangler.md) and matter to whoever ports `juser/*`: `EditUserForm`
    was *silently* dropping its two uniqueness validators off laminas rather than
    throwing like its siblings, and `EditUserForm::class` is a **shared** service that
    `setValidatorsForCreate()` mutates.
  - **No longer blocked:** the comment form (ported 2026-08-12), which turns out not to
    read the static adapter either — `CommentForm` is the second form after
    `AssociationForm` that does not.
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

- [x] **`/persons/create` answered 500 for any person without a spouse** — fixed and ported,
  batch 10, 2026-08-15.

      Statement could not be executed (22007 - 1366 - Incorrect integer value: ''
      for column `ourlink_db1`.`sch_persons`.`SpousePersonId` at row 1)

  `spousePersonId` was a select with an empty first option and an input filter of
  `['required' => false]` and nothing else, so a form where no spouse was chosen posted `''`
  into an integer column. `ToInt` then `ToNull` in `PersonForm` fixes it, on both front
  controllers.

  **The broad fix in `createEntity()` turned out to be unnecessary**, which is the part worth
  keeping: an audit of every create form against the database's own column types found 74
  elements that can put an empty string somewhere non-string and **exactly one** that let it
  through. Thirty-eight of the rest are checkboxes, which never post `''` at all. The audit is
  now `test/Integration/EmptyStringToTypedColumnTest`.

  The same fix closes an edit-form bug nobody had reported: `updateHelper()` skips a column
  when `$value == $data[$field]`, and `494 == ''` is false in PHP 8, so *clearing* a spouse
  wrote `''` too. 82 of 325 persons have one.

- [x] **`/texts/create` created the text and then lost the redirect**, leaving a 154-byte blank
  page — fixed and ported, batch 10, 2026-08-15.

  `EventTextTable::preprocessText()` read `$entityData['kind']`, the *existing* row's kind, and
  on a create there is no existing row. The warning reached the output before
  `redirectAfterCreate()` could send its `Location` header, so the moderator saw a blank page
  over a text that had been written, and pressing submit again made a second one.

  **Guarding the array read would have been the wrong fix**, and this is the reason to keep the
  entry. That clause also meant editing a text's markdown never regenerated its HTML — and the
  show page renders `htmlText` and nothing else — so the form's main field had no visible
  effect for 2,753 of the 2,757 rows. The fix keys on `legacyFile` instead: all 2,753 imported
  rows carry one and all 4 rows authored in the application carry none, so the imported HTML is
  preserved byte-for-byte while an app-authored text renders its markdown. Dropping the clause
  outright was measured and rejected — it would have changed the visible text of 2,740 of the
  2,756 rows that have markdown.

  Guarded by `test/Integration/PreprocessorCreateSafetyTest` and
  `test/Integration/TextHtmlGenerationTest`.

- [ ] **`phpstan-baseline.neon` is hiding ~11 more call sites that cannot resolve at
  runtime.** This is the finding from the 2026-08-14 archaic sweep worth acting on, and it
  is a different kind of problem from the endpoints that sweep retired: PHPStan level 0
  *did* catch `SchoenstattTable::getRoleTitleAliases()` and `SionTable::getEmailAddress()`,
  and both were **baselined rather than fixed**, which converts a guaranteed fatal into a
  green run. Two down, and reading what is left says the pattern repeats:

  - `Books\Service\DriveGateway` — **five undefined methods across 8 call sites**
    (`getSchoenstattTable` ×4, `complementSchoenstattTablePersonDataKeysWithOptions` ×2,
    `getPersonInSchoenstattTable`, `getRemotePerson`), plus an undefined `$personInputFilter`
    property ×2 and an `unset()` of an offset the array cannot have. That is not one bug, it
    is a class that looks half-migrated. **Start here**, and start by asking whether anything
    reaches it at all — the `getMailings()` finding says "unreachable" is a live hypothesis.
  - `Books\Model\MusicTable:125` calls `$this->getAssociationSchemaV1()`, undefined. Note
    the name: `getCompositionSchemaV1()` exists and is what the compositions page uses, so
    this looks like a copy-paste from `SchoenstattTable`. Reachability unmeasured.
  - `SionModel\View\Helper\Tooltip` calls `$this->escapeHtml()`. View helpers do not
    inherit that; the idiom is `$this->getView()->plugin('escapeHtml')`.
  - `SionModel\Controller\SionController` uses an undefined `$entity`.
  - `Books\Model\LibraryOptions::$isPublicallyListed` is written or read and not declared,
    and its `getArrayCopy()` has no `return`.
  - Two `Array has 2 duplicate keys` (`Coins.php`, `SionTable.php`) — silent data loss,
    since the second key wins and the first line is simply discarded.

  **Method that found them:** PHPStan resolves `$this->foo()` but not
  `$table = $this->getSionTable(); $table->bar()`, which is how `importJkTexts` hid from it.
  The complementary scan is receiver-blind — every method **name** defined anywhere in the
  tree versus every `$var->name(` call site — and a name defined nowhere cannot resolve
  whatever the receiver turns out to be. 17,519 definitions against the call sites left 18
  candidates, 3 real. The script is not committed; it is ~60 lines and rebuilding it is
  faster than maintaining it. Watch two gotchas that cost a re-run: `function &foo()`
  (reference-returning, so the regex needs `&?`) and scoping the definition search to a
  hand-picked list of vendor packages rather than all of `vendor/` — the first pass reported
  172 candidates, nearly all of them Carbon and Reflection.

  **Do not fix these by raising the level or regenerating the baseline.** Each one needs the
  same two questions the sweep asked: is it reachable, and if so from where. A regenerated
  baseline answers neither and re-hides all of them.

- [ ] **`$this->personName` is never set, so every person's edit page is titled "Edit ".**
  `module/Schoenstatt/view/schoenstatt/persons/edit.phtml` reads it for both the `<title>`
  and the lead paragraph under the heading, but `SionController::editAction()` sets only
  `entity`, `entityId`, `form` and `deleteForm`, and `PersonsController` overrides neither
  `editAction()` nor the view model. So `sprintf('Edit %s', null)` renders `Edit ` and
  `escapeHtml(null)` renders an empty `<p class="lead"></p>` — for all 325 persons, on the
  laminas front controller.

  **The Symfony port does not reproduce this** (2026-08-14): it renders the person's name,
  which is what the template plainly intends, and the difference is recorded as accepted in
  [strangler.md](strangler.md). Left open here because the laminas view is still what serves
  this page wherever the laminas front controller runs, and because the same shape may exist
  on other `*/edit.phtml` views that read a name variable nothing provides — nobody has
  swept for that.

- [ ] **Four patres date fields ride on every person form and no partial renders them.**
  `priestDate`, `priestDatePrecision`, `bishopDate`, `bishopDatePrecision` are elements on
  `Schoenstatt\Form\PersonForm` and entries in the person spec's `update_columns`, because
  the form is shared with the patres application. On schoenstatt.link nothing renders them,
  so `getData()` answers null for all four and `updateEntity()` writes those nulls.

  Since db6.5 made `PriestDatePrecision` and `BishopDatePrecision` `NOT NULL`, that write
  **fails** — so saving a person was broken on both front controllers (laminas 500, Symfony
  fatal-200), measured 2026-08-14. The ported template works around it by round-tripping all
  four through hidden inputs, which is a no-op write and is what makes
  `/persons/{id}/edit` saveable again on the Symfony side.

  Still open because the *laminas* page remains broken, and because the workaround is in a
  template rather than in the form. The proper fix is a **validation group** on `PersonForm`
  naming only the fields the rendering application shows, so `getData()` cannot return keys
  the page never offered — `EditAssignmentForm::prepareforEdit()` already does exactly this.
  **Do not "fix" it by giving the precision columns a default**: that lets the save through
  while the same POST's `PriestDate => null` silently erases the ordination dates of the ten
  persons who have one. The crash is currently the only thing preventing that.

- [ ] **The publication form fetches an author list no picker ever offers.**
  `fields-partial.phtml` builds `authorAssociations` from
  `PublicationsTable::getAuthorAssociationValueOptions()` — the associations flagged
  `IsAuthor` — and then says `authorPersons.concat(authorAssociations);`, discarding the
  result, because `concat` returns a new array and does not mutate. So `authorsAll`,
  `editorsAll` and `translatorsAll` have never offered an association as an author, on
  either front controller, while the page pays to build and ship the list.

  Reproduced verbatim by the Symfony port on 2026-08-14 rather than fixed: the one-word
  fix (`authorPersons = authorPersons.concat(...)`) adds options to three fields that have
  never had them, which is a content decision and not a porting one. Whoever takes it
  should also decide whether *editors* and *translators* should offer associations at all
  — the original hands the same combined list to all three, which may itself be the
  accident rather than the intent.

- [x] ~~**`association-delete` matches no association that exists.**~~ **Fixed
  2026-08-14.** Its route declared `sw_id` as `SL1[0-9]{4,4}A` — the `1` plus **four**
  digits — where every valid association identifier is `SL1[0-9]{5,5}A`
  (`Schoenstatt\Validator\SchoenstattLinkIdentifier::ENTITY_REGEXS`). So the route never
  matched, and `/SL100319A/delete` fell through to the `association` show route on
  **both** front controllers.

  The constraint is now *derived* from `ENTITY_REGEXS` rather than written out, which is
  the actual repair: its four siblings (`publication-`, `text-`, `event-`,
  `composition-delete`) all derive theirs and not one of them drifted in five years. A
  five-digit identifier is not a near miss to tolerate either — it is the pre-April-2020
  form, and `redirect-pre-april-2020-sl-id` owns it, which
  `test/Smoke/ReservedVerbRoutingSmokeTest` now asserts so nobody "fixes" this by
  widening the constraint to accept both.

  `test/Smoke/AssociationDeleteSmokeTest` covers what the route had never had: the guard,
  the confirmation, the CSRF token, a real delete, and what the delete leaves behind.

  **Exercising the delete uncovered a second, latent bug and fixed it too.** A change log
  row whose entity no longer exists carries a placeholder — `{isDeleted, associationId,
  associationName}` and nothing else — and the ported
  `templates/schoenstatt/_entity-format.html.twig` read `entity.country` straight off it.
  Under laminas an absent key reads as null and `FormatAssociation`'s own `isDeleted`
  branch handles the case; under Twig's `strict_variables` it raises, *after* the response
  is assembled, so `/en/sm/view-changes` answered **HTTP 200 with zero bytes** — the
  fatal-200 wedge. Recorded 28 times from 2026-08-12 with nothing connecting it to a
  deleted record, because until this repair nothing could delete an association through
  the interface to produce such a row deliberately. The `person()` macro had the same
  exposure by the same mechanism and got the same treatment. Pinned by
  `EntityFormatterTest::testADeletedEntityPlaceholderRendersAsTheLaminasHelperRendersIt`.

- [ ] **Deleting a record orphans everything that referenced it, on all five delete
  routes.** `SionTable::deleteEntity()` is a bare `DELETE ... WHERE <key> = ?`, and
  **the schema has no foreign keys at all** — measured 2026-08-14: zero constraints
  reference `sch_associations`. So deleting a parent association leaves its children
  pointing at a row that is gone, and deleting an association leaves its roles the same
  way. Association 8 has 60 children; 1,468 roles name an association.

  This is not new and not specific to associations — the four other delete routes have
  been reachable in production for years with the same behaviour, and the capsule already
  carries **142 roles and one association whose referent no longer exists**. It is filed
  here rather than fixed alongside the constraint repair because "what should deletion do
  to dependants" is a product decision with at least three defensible answers (refuse,
  cascade, reparent), and picking one silently while fixing a routing typo would be the
  wrong way to make it.

  Current behaviour is pinned by
  `test/Smoke/AssociationDeleteSmokeTest::testDeletingAParentLeavesItsChildrenPointingAtNothing`,
  which is a characterization test: whoever takes this decision should expect it to fail
  and should update it deliberately.

- [ ] **The delete confirmation does not say *which* record it is about to destroy.**
  `sion-model/sion-model/delete.phtml` renders `Delete <entity>` and two buttons; the entity
  *key* is in the heading, the record's name is nowhere. `deleteAction()` even fetches the row
  (`$entityObject`) and puts it in the view model, where the template ignores it — the
  `@todo See if we can discern the name of the entity to show it to the user.` above it is
  the original author's note on exactly this.
  So a moderator on `/en/persons/494/delete` is asked to confirm deleting "person". The
  Symfony port (2026-08-14) reproduces this rather than improving it, since the two front
  controllers serve the same page; fixing it means both `delete.phtml` and
  `templates/sion-model/entity-delete.html.twig`, plus a `name_field` read that
  `EntityEditController::recordName()` already does.

- [ ] **Cancel on a standalone delete confirmation is now inert.** Fixing the button that
  *deleted* the record (2026-08-14 — it rendered as `type="submit"`; see
  [strangler.md](strangler.md)) made it a `type="button"`, which is correct inside the two
  delete modals, where `data-dismiss` dismisses. Outside a modal it now does nothing at all.
  A real fix is a cancel URL — the entity's index, or the record's show page — which the
  shared `DeleteEntityForm` has no way to know, so it wants a view variable and a template
  change on both renderings. An inert button is a fair trade for a destructive one; it is
  still worth one commit.

- [ ] **Three delete routes are reachable by nobody, and nobody decided that.**
  `event-delete`, `libraries/library/delete` and `sion-model/delete-entity` have no entry in
  `config/autoload/acl.global.php`'s route guard, and the guard is default-deny, so all three
  answer **403 to an account holding every role** — measured 2026-08-14. Two are dead twice
  over: `library` declares no `enable_delete_action`, so past the guard it would only reach
  "This entity cannot be deleted"; and `sion-model/delete-entity` names a `deleteEntity`
  action that `SionModelController` does not define, so it would 404 on dispatch.
  Each needs a decision rather than accumulating: finish the feature and guard it, or delete
  the route. They were deliberately left out of the batch-8 port because porting an
  unreachable route is work with no user.

- [ ] **Two wrong status codes in `deleteAction()`, one of them dead.** The not-found branch
  sets **401** and then returns a redirect, whose 302 replaces it — so it never reaches the
  client, and it is the same dead line `JTranslateController::deleteAction()` carries a
  comment about. The **401 on a failed CSRF token** is not dead: that branch renders the form,
  so a browser really does get 401 where 400 or 403 is right. Both are reproduced verbatim by
  `App\Controller\EntityDeleteController` (2026-08-14) and asserted in
  `test/Smoke/DeleteSurfaceSymfonySmokeTest`, because a port is the wrong place to change an
  observable status. Correcting the live one means editing SionModel, the ported controller
  and that assertion together.

- [ ] **`EntityEditController` puts its form-error message in the flash, where laminas puts it
  in the now-messenger.** So on a ported edit form that fails validation, "Error in form
  submission, please review." is stored and rendered on the *next* page the visitor loads
  rather than on the re-rendered form — the form comes back with no error on it, and an
  unrelated page later carries the message. laminas uses `nowMessenger`, which renders into
  the response being built. The bridge for it exists and three ported controllers use it
  (`PersonsController::nowMessage()`); `EntityDeleteController` was written with it from the
  start (2026-08-14). One-line fix in the edit controller, plus a smoke assertion that a
  failed POST re-renders *with* the message.

- [ ] **`assignments/assignment/edit` accepts a 6-digit id where laminas accepts 5.** The
  Symfony route declares `[0-9]{1,6}` against the laminas route's `[0-9]{1,5}`, so
  `/assignments/123456/edit` reaches the controller and is answered "Assignment not found."
  where laminas would have 404'd. Harmless, and worth one character. Noticed while declaring
  `assignments/assignment/delete`, which uses laminas' width.

- [x] ~~**`tools/acl-table.php` cannot see a parameterized route being shadowed.**~~
  **Fixed 2026-08-14.** `shadowedBySymfony()` passed the *composed laminas pattern* to
  `Symfony\...\UrlMatcher::match()` — the literal string `/:sw_id/edit`, placeholder and
  all — so a laminas route with any route parameter could never be reported as shadowed.
  Measured 2026-08-13: **0 of the 31** rows in the shadowed table had a parameterized
  path, and all 90-odd parameterized laminas routes were invisible to the check. That is
  the exact blind spot the reserved-verb bug lived in, which is why nothing warned while
  nine guarded routes were unreachable.

  The fix runs the check **both ways**, because neither direction is sufficient alone:

  - *Forward*: each laminas pattern is instantiated into concrete URLs — one per
    combination of its optional segments, each parameter replaced by a value from
    `App\Routing\RegexSampler` — and those are matched. The sampler's guarantee is not
    that it can sample everything but that **every value it returns is checked back
    against the constraint it came from**, so a pattern it cannot model yields `null`
    rather than a wrong URL.
  - *Reverse*: for every URL Symfony **owns**, the real `TreeRouteStack` is asked which
    laminas route would have served it. No sampling on the laminas side at all, and this
    is the direction that catches a ported route claiming a narrow slice of a broad
    laminas route — `/api/v3/associations` is claimed by Symfony while `/api/a` is not,
    so the forward probe alone would have called `api-route-not-found` unshadowed.

  Result: **31 → 52** shadowed rows, 19 of them parameterized, and every one of the
  newly-visible routes checks the resource its laminas guard used to. A route neither
  pass can decide is now reported as *uncomparable* instead of skipped, so the tool's
  silence about a route finally means something; that list is currently empty and
  `test/Integration/AclShadowCompletenessTest` fails if it stops being.

  One warning-precision change came with it: a guard naming the configured
  `default_role` (`guest`) is now treated as public, like a literal `null` already was.
  Without it every ported `/api/v3/*` route reported that it had dropped the guard on
  `api-route-not-found` — a 404 handler declared `['guest', 'user']`, i.e. reachable by
  everyone, with no restriction for porting to lose.

- [x] ~~**`TranslationsTable::flush()` cannot be called from a console process.**~~
  **Fixed 2026-08-10.** Three changes, because the session was only the trigger and
  the partial write was the actual defect:
  `JUser\Service\AuthServiceActingUserProvider` answers `null` instead of raising
  when the authentication service cannot be built, and remembers that so a command
  writing a thousand phrases does not attempt a thousand doomed container lookups;
  `writeMissingPhrasesToDb()` resolves the acting user *before* its first insert;
  and each phrase plus its key-locale translation is written in one transaction, so
  no mid-loop failure of any kind can leave the uncompletable row this entry
  described. Guarded by `test/Integration/TranslationWriteSemanticsTest`, which
  injects a real failure by overriding `key_locale` with a value too long for the
  column, and by `test/Integration/PhraseDiscoveryTest`.

- [ ] **A breadcrumb label files a junk phrase row in `Application` whenever it
  lives in `default`.** `partial/breadcrumbs.phtml` looks a label up in the
  navigation text domain and falls back to `default`, and the *first* lookup files
  a row when it misses — so `Shrines` and `Africa`, whose real translated rows are
  in `default`, each gained an untranslated `Application` twin on 2026-08-10.
  Bounded: one row per navigation label, a few dozen, and they are genuine
  interface strings rather than record content (which is what
  `Module::markDataLabels()` now keeps out — see
  `docs/api-change-requests-response.md` §12). Not fixed because the obvious fix,
  looking `default` up first, changes which domain wins for a label present in
  both: laminas renders `Admin` from `Application` where `default` holds
  "Administración", and the Twig layout already documents that measurement.
  Fixing it properly means asking the translator whether a translation exists
  without firing `missingTranslation` for the probe.
  **That probe now exists** (2026-08-12, `c5ef8f3`):
  `App\Twig\LaminasExtension::catalogValue()` reads a domain's compiled catalog
  through `Laminas\I18n\Translator\Translator::getAllMessages()`, which loads the
  catalog through the same cache the translation itself uses and fires no event.
  So the blocker is gone on the Twig side; what is left is doing the same in
  `partial/breadcrumbs.phtml`, where the helper — not the extension — is what
  looks the label up.

- [ ] **`ucwords($entity) . ' not found.'` files one phrase per entity type.** Nine
  call sites: `SionController` (×5, `ucfirst` and `ucwords` both),
  `Schoenstatt\AssociationsController`, `Books\PublicationsController`, and the two
  ported ones — `App\Sion\EntityShow` and `App\Controller\SendToNewUrlController`,
  which reproduced it deliberately because the port's contract was identical output.
  The same shape `db7.7.sql` cleaned up after, and bounded rather than unbounded:
  the vocabulary is the entity list, ~30 strings, not user input. It is still wrong —
  "Association not found." cannot be rendered into a language whose word order or
  gender agreement differs, and the translator sees N near-identical rows. Fix is
  `JTranslate\I18n\TranslatableMessage` with a `'%s not found.'` template, which
  makes it one phrase; the catch is that `%s` is then an entity name needing its own
  translation, so it wants the same decision as the navigation-label entry above.
  Spans three repos (SionModel is a submodule), which is why it was not folded into
  the db7.8 batch.
- [ ] **Two navigation labels can never be translated, because they are built by
  concatenation.** `'German Schoenstatt Literature'` and
  `'German to Spanish Dictionary'` are composed in `Application\Module` from an
  always-English language name, so the breadcrumb above a publication reads English
  in all five locales — visible on `/it/literature/de`. `db7.4.sql` retires the rows
  they filed; the labels stay English. The real fix is §3's shape — translate a
  `%s` template and interpolate — but the interpolated value is itself a language
  name needing translation, and doing it per locale means salting
  `publication-pages` in the cache, which multiplies a 10,166-page branch by five
  on a 32 MiB APCu segment. That trade is the decision nobody has taken.
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
- [x] ~~`finish-pending-labels` is an unreachable route~~ — **deleted 2026-08-14
  with the rest of `/api/v1`**, so the unreachable route and the dead
  `finishPendingLabelsAction()` behind it are both gone. The label workflow's
  "commit these call numbers" step never worked and now does not exist; the log
  agrees nobody noticed — `pending-labels` was called **4 times ever, all in
  December 2018**.
  - **Keep the diagnosis, it is not about this route.**
    `Laminas\Router\Http\Part::match()` returns the parent match once the path is
    consumed and the parent `may_terminate`s, so a `Method` child is never
    consulted: the endpoint looks like it works and silently runs the parent's
    action. That trap still exists everywhere else the pattern is used, and it is
    invisible in a response.
  - If the label workflow is ever rebuilt, build it on `/api/v3`, where the shape
    is a real route per verb and the acting user comes from `BotIdentity` rather
    than from an unread `tokenPayload->sub`.
- [x] ~~`GET /api/v1/libraries/:id/books` answers 200 with a zero-byte
  `text/html` body~~ — **deleted 2026-08-14 with the rest of `/api/v1`.**
  `BooksApiController` is gone, so the bug went with it uninvestigated. Recorded
  because the *reason* nobody found the cause still applies to whatever replaces
  it: this was the one gated route whose payload no test asserted, and a
  zero-byte 200 is exactly the shape a JSON endpoint fails in.
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

- [x] **Every non-English page declared the English URL as its canonical.** Decided and
  fixed 2026-08-13: all five languages are meant to be indexed, so each is now canonical
  for itself, the hreflang set lists all five plus `x-default` including its own page
  (a set that omits itself is non-reciprocal and Google discards it), and the record
  pages canonicalise their *preferred* URL rather than whichever decorative slug was
  requested — `App\View\PreferredUrls`, which the sitemap shares via
  `SitemapEntry::tailFor()` so the two cannot drift. Both layouts changed, because
  production serves ported routes through Twig and bridged ones through `.phtml`. See
  docs/sitemap.md, "All five languages are meant to be indexed".
  - Left open on purpose: `hreflang` is still declared for all five locales whether a
    translation of that page exists or not. The set has to match what the pages publish
    or reciprocity breaks, so narrowing it would mean narrowing both at once.

- [ ] **25 publications have no browse path — and the "dead menu link" this item used to
  describe is not rendered anywhere.** Both halves measured against production 2026-08-14.

  The data defect is real and unchanged: `PageBuilder::publicationPages()` groups
  publications by language and files the languageless ones under `null`, which becomes the
  array key `''`, then assembles `publications/index` with `inLanguage => ''` — and that is
  `/en/literature/`, which answers **404** while `/en/literature` answers 200. Laminas's
  `Segment::assemble()` does not enforce the route's own `[a-z]{2,2}` constraint, which is
  why an empty parameter produces a URL at all.

  **But nothing renders it**, which this item previously asserted and which is worth
  correcting rather than deleting, because "the menu shows a 404" is what made it look
  urgent:
  - Both navbars render **top level only** — the laminas layout calls
    `->setMinDepth(0)->setMaxDepth(0)`, the Twig layout iterates `navigation_items()`. The
    group sits at depth 2.
  - The Symfony breadcrumb **omits the group**: `/en/SL202208L` (languageless) renders
    `Literature > title`, while `/en/SL201727L` renders
    `Literature > Schoenstatt Literature in English > title`.
  - The laminas rendering of a publication page, forced with `sl_symfony_canary=0`, carries
    **no breadcrumb at all**.
  - The literature home offers twelve language links in both renderings — `cs de en es fr
    hr hu it la pl pt` plus the 150-preguntas page — and no languageless one.
  - The sitemap dropped it on 2026-08-13, via the trailing-slash rule in `isPublishable()`.

  So the user-facing consequence is the opposite of a bad link: those **25 publications are
  in the sitemap but have zero inbound internal links**. For a site whose traffic is
  overwhelmingly crawlers, orphaned pages are the part of this worth fixing.
  - Options: (a) give `publications/index` a branch that means "no language" and teach
    `searchPublications()` to match it — note **24 of the 25 are `NULL` and one is `''`**,
    so `In($field, [''])` matches one row, not 25; or (b) drop the broken node and
    re-parent its children, which removes the latent trap and leaves the orphans orphaned.
    (a) is the only one that delivers anything.

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

- [x] ~~**Retire the shrine GeoJSON feed, or commit to it**~~ — **retired
  2026-08-14**, with the rest of `/api/v1` and `/api/v2`. Deprecated 2026-08-07 at
  the user's direction ("I'm not sure we'll use it going forward"); removed once the
  usage data this item asked for actually existed.
  - **The access log answered it.** This item said "the decision needs usage data,
    and there is currently none… can only be answered from the hoster's access
    log" — correct, and that is exactly how it was settled. Production's
    `~/logs/access.log*` goes back to **2016-04-17**, and across the whole file
    `shrines.json` has **98 hits on v1 and 91 on v2, every one of them ours**:
    `tools/smoke-prod.sh` plus a 27-minute `curl/8.5.0` port-baseline capture on
    2026-08-08 from the same IP. Not one external caller in eight years.
  - **The app this existed for stopped calling in 2019.** The item named Alberto
    León's "Schoenstatt Shrines" app as the reason the feed exists. The only mobile
    user agent in the log — `Dalvik/2.1.0 … SM-G9650` — appears **40 times, all in
    2019**, and it read `findByKind`, never `shrines.json`. So the feed's stated
    consumer never used the feed.
  - **No `Sunset` header was ever added, and it turned out not to matter**: with no
    caller to warn, the deprecation runway warned nobody. Worth remembering as the
    limit of that mechanism — `Deprecation: true` is only useful if somebody is
    reading your response headers.
  - Removed as estimated, plus more than estimated: `jmikola/geojson` left
    `composer.json` too, because `SchoenstattTable::getShrineGeoJson()` was its only
    reachable caller. `getShrines()` stays — the shrine index needs it.


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
- [x] ~~API registration allow-list (old @todo in `LoginV1ApiController`)~~ —
  **moot 2026-08-14: `LoginV1ApiController` was deleted with `/api/v1`.** There is no
  API login flow left to auto-create accounts, so there is nothing to allow-list. An
  agent's account is created by an administrator like any other, which is what this
  item wanted anyway.
- [ ] Drop the now-unread `user.password` column once passwordless has
  soaked (deliberate migration).
- [ ] Library import form takes a hand-typed *server* path (the 2021
  Windows-desktop-path failure). Real fix is accepting an upload — a fresh
  feature on top of `Books\Service\SpreadsheetReader`.

## Config rot / small cleanups

- [x] ~~**Dead-code sweep**~~ — **done 2026-08-14, ~1,000 lines.** Recorded here for the
  method and the negative results, both of which are worth more than the line count.

  Removed: `AssociationsController`'s four unreachable methods (908 → 234 lines, of which
  `getChileInfo()` alone was 539 lines of hardcoded 2019 Chilean diocese data with **504 of
  them already commented out**); `BorrowersController::fixPersonIdAction()`, a 2017 one-off
  carrying the comment *"already fixed, we will disable to prevent problems"* above a
  first-statement `return;`; three routes and their guard entries; five orphaned templates;
  `Books\Filter\Printf`; three superseded mail templates and a route with no action in
  SionModel; and the now-dead `PatresGateway` dependency `BorrowersController` still asked
  its factory for.

  **Two heuristics that earned their keep and should be reused:**
  - **`return;` as the first statement of an action.** Two hits, both one-off migrations
    disabled in place rather than deleted. Cheap to scan for and it finds intent, not just
    dead code — someone wrote "do nothing" and meant it.
  - **A method whose only caller is a commented-out line.** That is how
    `fillNoAccentsColumns()` and `getChileInfo()` both survived: the call site was commented
    out, so no reference-counting tool flagged the callee.

  **The negative results, because a clean scan is easy to misread as a scan that did not
  run:** exactly **1 unreferenced class out of 340** (`Printf`); no action other than the two
  above starts with `return;`; and of 1,586 lines sitting in commented-out regions of ≥8
  lines, all but the 504-line Chile block are explanatory prose rather than disabled code.

  **A tooling caution.** The first pass of two of these scans used `fd`, which **is not
  installed in this environment** — so both reported nothing, and "no findings" was
  indistinguishable from "the tool never ran". Check that a scanner exists before believing
  its silence; `rg` is present, `fd` is not.

- [x] ~~**Archaic endpoint sweep, round 2**~~ — **done 2026-08-14.** Four endpoints and
  three methods retired; full account in [history.md](history.md#archaic-endpoints-round-2-2026-08-14).
  Kept here for the two corrections, because both were the *reason* an item was ranked
  where it was:

  - **`/en/music/import` was not a GET mutation.** It was ranked as one — same shape as the
    `publications/import` retired hours earlier. Measured instead of read: every iteration
    is gated on `disambiguatingDescription`, and **all 335 compositions have that column
    empty**, because the 2019 import nulled the field it keyed on. Plus `data/musicas/` does
    not exist. It wrote nothing and returned `0`. A dead page, not a hazard.
  - **The successor-version Link the previous deploy shipped pointed at a 404.** `</api/v3>`
    is not a route. Found by fetching the header's target against production after the
    deploy, not by reading code — and no test would ever have caught it, because all of them
    pattern-matched the header string. Both smoke suites now fetch the advertised URL.

  Still open and deliberately not folded in: the ~11 remaining baseline entries above.

- [ ] **Routes with no bjyauthorize guard entry**, so under default-deny they are
  unreachable for every role — not restricted, *inaccessible*. Re-measured
  2026-08-05 by `tools/acl-table.php` after the Bible removal: **29 names, of
  which only 18 are real endpoints.** The other 11 are Part-route parents with
  `may_terminate` false, which can never be the matched route name, so their
  missing guard costs nothing — an earlier count of 30 did not separate these
  and overstated the problem by more than half.
  - **The pure-dead-config half is now cleared.** Seven routes named a controller
    action that does not exist, so granting a role would only have turned
    "reachable by nobody" into a fatal. All seven are gone:
    `jtranslate/clear-cache` (2026-08-10), `libraries/library/import` and
    `sion-model/delete-entity` (2026-08-14), and — **four of them had already
    been removed by other work before anyone came back to this list**:
    `admin/data-problems`, `admin/moderate`, `assignments/assignment/suggest`
    and `assignments/assignment/moderate` were absent from the router when
    re-checked on 2026-08-14, so this entry spent some time naming four routes
    that no longer existed. Worth recording as a caution about the list rather
    than just deleting the names: a stale to-do reads exactly like a live one,
    and the only way to tell was to ask the router.
  - **Re-measured 2026-08-14 after the dead-code sweep: 22 names, 11 of them real
    endpoints** — down from 29/18. The eleven left all need a *guard entry*
    rather than deletion, and none of them is dead config.
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
  - ~~**`api-v1/libraries/books/patch-list` → `guest, user`**~~ — **moot
    2026-08-14: the route was deleted with the rest of `/api/v1`.** It was one of
    the two unguarded routes among the 26, which is why the ACL baseline's
    `unguarded_routes` fell 22 → 20 and not 22 → 21.
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
- [x] ~~Re-track `public/.htaccess`~~ — **done 2026-08-08**: the file is tracked and
  phploy deploys it, which is what makes the front-controller switch reviewable and the
  flip a commit rather than a hand-edit. `test/Integration/KernelCanaryTest` now reads it
  as a fixture. This entry stays only for the measurement below it.
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
  (harmless) — clean it next time a deploy touches server config. The
  stack-trace leak that used to share this item is **closed**: error display is
  off in that file (confirmed 2026-08-11), and PHP's own `display_errors` is
  `Off` too, so the two layers agree.
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
- [ ] Navigation cache keys written by `Application\Navigation\PageBuilder`
  (extracted from `Application\Module::onBootstrap()` on 2026-08-13, and now
  read by the Symfony side too) are never invalidated on data change — only
  `SionCacheTrait`-registered keys are — so editors see "my change didn't
  appear" until someone flushes. `/sitemap.xml` inherits this: its generation
  stamp lives in the same segment, so it refreshes with the branches and
  otherwise once a day.
- [ ] A merged publication is still a page in the *navigation*, and therefore in
  the laminas menus and breadcrumbs, though it only ever answers a 301 to the
  edition it was merged into — 3,627 of 10,104 public publications. The sitemap
  excludes them (`App\Sitemap\SitemapGenerator`); filtering them in
  `PageBuilder` instead would fix both surfaces at once and change the laminas
  rendering, which is why it was not done as part of a port.
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
- [ ] **The smoke suite asserts the anonymous 302 for every guarded ported route, and
  that has now missed three defects.** A guarded route's smoke test signs nobody in, so
  what it proves is that the guard denies — never that the page renders. The three it
  could not see: the `collections/collection/edit` fatal-200 (batch 7), the batch-6
  renderer gaps, and `books/book/edit` shipping with **no submit button** for two days
  (fixed 2026-08-15, see [strangler.md](strangler.md)). Each was found by something
  else, later, and the last one was live in production.
  - What it would take: the smoke suite would need the Mailpit magic-link sign-in
    `tools/port-baseline.php` already implements — `signInWithEveryRole()` plus
    `grantEveryRole()` — lifted into a shared helper so a test can assert a *signed-in*
    body. That is a real piece of work, not a tweak, and it is the thing standing
    between "every ported route has a test" and "every ported route has a test that
    could fail".
  - Cheap partial cover meanwhile, and the pattern to copy:
    `test/Unit/PortedFormsAreSubmittableTest` checks the one property that mattered here
    by reading the templates. A source scan cannot know whether a page *renders*, but it
    can know whether the markup is capable of the thing — and it runs in CI, where an
    authenticated HTTP test never will.

## Deploy ops

- [ ] **Server maintenance pass: the 19 tables `db7.0`/`db7.1` deliberately left
  alone.** The migrations converted every table this repository *mentions* to
  InnoDB + `utf8mb4_unicode_520_ci` (see
  [database-charset.md](database-charset.md)). These are not referenced by any
  code, so they are a DBA task rather than an application migration — but they are
  still sitting in the production database on old engines and charsets:
  - **Nine Bible tables** — `bib_books`, `bib_book_abbreviations`, `bib_dh_page`,
    `bib_greek_root_words`, `bib_verses`, plus `bib_jeru_en_temp`,
    `bib_jeru_es_temp`, `bib_jeru_septnt_temp`, `bib_pueblo_temp` (the last four
    exist only in production, not in the capsule dump). The Bible module was
    removed 2026-08-05 and the feature moved to another application, so the real
    question is whether these should be **dropped** rather than converted.
    `bib_verses` is 236,422 rows / 45.8 MiB and still MyISAM — the largest
    crash-unsafe table on the server.
  - **Five `b_bib*` tables** — `b_bibsek`, `b_bibprim_edition`, `b_bibprim_event`,
    `b_bibprim_epoche`, `b_bibprim_join`. Source data for a one-time import from
    the old "sion" bibliography system. `PublicationsTable::importPublications()`
    was the only reader and was deleted with `db7.0` (it called `issett()`, so it
    fatalled on every invocation and cannot have run in years). Three still carry
    FULLTEXT indexes that nothing queries.
  - **`sch_dictionary_dictionary`** (3,084 rows) and **`sch_dictionary_users`**
    (10 rows). The live dictionary is `sch_dictionary_entries`. `sch_dictionary_users`
    has a `password` column — legacy credential hashes with nothing reading them,
    which is a reason to drop rather than convert.
  - **`user_remember_me`**, **`sch_visits_rollover_2023-11-02`**,
    **`sch_visits_rollover_2025-07-17`**.
  - **The database default charset is still `latin1_swedish_ci`**, so any table
    created without an explicit charset silently inherits latin1. `ALTER DATABASE
    ourlink_db1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;` belongs to
    this same pass. It changes no existing table.

- [ ] **`PublicationsTable::whichKentenichPeriod()` is now unreferenced.** Deleting
  `importPublications()` orphaned it. It is `protected`, nothing extends
  `PublicationsTable`, and it encodes real domain data — the eight periods of Fr.
  Kentenich's life, mapped from a date, feeding the `jkPeriodId` column that still
  exists on `sch_publications`. Left in place deliberately rather than cascading the
  deletion; decide whether that mapping is wanted before removing it.

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
  deliberately — `SionModel\Mailing\Mailer` (magic-link token) and
  `JUser\Model\User` (verification token) generate secrets with it. Check
  `Rand::getString()`'s default charlist before touching the calls that omit one
  (`CspListener`). Two of the call sites this item used to name were in
  `LoginV1ApiController`, deleted 2026-08-14 — the "11 sites across 7 files" count
  above predates that and was not re-derived here, so re-count before planning the
  work. The JWT-id generator that survived is
  `JUser\Service\ApiTokenService:122`, which mints every v3 credential, so that is
  the security-relevant one to read first.
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
