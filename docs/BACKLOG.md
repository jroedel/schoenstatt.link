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

- [x] **Every deploy has a window in which visitors get fatals.** **Fixed
  2026-08-15 by `tools/deploy.sh`** — deferred on 2026-08-14 as "mostly protects
  Googlebot's opinion of us", then taken up the next day because the user wanted a
  single-command deploy anyway and atomicity came with the redesign rather than
  costing extra. Four of the five shapes below are now structurally impossible: the
  tree is complete before anything points at it, and `data/config` is per-release so
  a new tree can never meet an old merged-config cache.

  Worth keeping the deferral reasoning, because it was sound and the outcome does not
  retroactively make it wrong: the cost of a deploy-window fatal is paid by whoever is
  mid-request, and that was overwhelmingly crawlers — the `51c0cb27` fingerprint
  alone, one scraper replaying a URL list it built while a button was public, produced
  **3,478 requests in eleven days** against a handful of identifiable human sessions.
  What changed was not the ratio but the price: bundled into work already being done,
  the fix stopped needing its own justification.

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

  **This was a pipeline design decision, not a patch**, and it was taken:
  upload-to-a-release-directory then swap, which phploy over SFTP could not do at all,
  so phploy went with it. The cheaper alternative considered here — a maintenance flag
  that 503s for the duration, trading fatals for downtime — was not needed.

  One prediction in this item was wrong and is worth correcting rather than deleting:
  the fix did **not** require the user to hand-edit a credentials file. The credential
  file shrank instead (`phploy.ini` + `.phploy` → `.deploy.local`), and every hook that
  used to live inside it is now ordinary code in `tools/deploy.sh`, under review like
  anything else. The old arrangement — deploy logic in a gitignored file no commit
  could reach — is why the `jtranslate:export-catalogs` and `sitemap:build` steps each
  had to be documented as "mirror this into `phploy.ini` by hand".
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
- [x] ~~**Retire the password era.**~~ **Done 2026-08-20.** Four routes, one dead mail
  chain, two columns and 290 stored password hashes.

  What was measured before anything was deleted, because most of it did not *look* dead:

  - **`user.password` had no reader at all.** No `password_verify`, no `Bcrypt`, no
    `CredentialTreatment` anywhere in `module/` or `src/`. Its only two writers were
    literal empty strings, each with a comment explaining that the column is NOT NULL
    and unused. 290 of 5,116 rows still held pre-2020 hashes — credential material for
    an authentication method the application no longer has, so a liability and nothing
    else. `must_change_password` was worse in a small way: still on the create *and*
    edit forms, so an administrator could set it, and setting it did nothing. 0 rows.
  - **The registration mail chain was unreachable through a `debug_backtrace()`.**
    `UserTable::insertUser()` decided whether to send a "confirm your email address"
    mail by inspecting *the name of its calling function* and comparing it to
    `'register'` — 2016-era design that worked while ZfcUser had a method called
    `register`. `insertUser()` has **no callers at all** today; the passwordless path is
    `createUserFromEmail()`, which never goes near it. So `onRegister()`,
    `onInactiveUser()`, `sendVerificationEmail()` and the `juser/verify-email` route it
    built links for were dead as a set, not one at a time.
  - **`juser/thanks`** had an empty action and a template still promising a confirmation
    email; **`juser/user/show`** was guarded `administrator` and routed to a
    `showAction()` that does not exist, with no template.
  - **`/user/register`** differed from `/user/login` only in wording — same
    `handleEmailRequest()` — so it was a second URL for one page, plus a Register button
    on every page of the site in **both** layouts.

  Two migrations rather than one, and the pair is the interesting part:
  `user.password` is NOT NULL with no default and `@@sql_mode` carries
  `STRICT_TRANS_TABLES`, so the release that stops writing the column cannot be the
  release that drops it — between the symlink swap and the post phase there is a window
  where nothing supplies it. `db8.4` (pre) gives the column a default; `db8.5` (post)
  drops both columns and is `@destructive: yes`, because the previous release's
  `processUserRow()` names them on *every* read of the `user` table, identity lookups
  included. Rolling back past it does not degrade a page; it takes the site down.

  Also landed with it, and each has its own entry above or below: the sign-in link now
  carries its destination, a refused destination is explained rather than 403'd, and a
  freshly issued API token is shown in a well with a copy button instead of a flash
  message. JUser's `composer.json` and README were rewritten to name what the module
  actually uses (the ZfcUser/ZfcBase/GoalioRememberMe list was five years wrong), and its
  release plan is recorded there: **2.0.0** is this line, **3.0.0** is the one with no
  `laminas-mvc`/`router`/`view`/`http` and no `bjy-authorize`.

- [x] ~~**The sign-in link now carries where the visitor was going.**~~ **Done
  2026-08-20.** The destination lived only in a session container, which works exactly
  when the link is opened in the browser that asked for it — and the ordinary case is
  asking on a desktop and clicking on a phone. There it was silently lost and the visitor
  landed on the welcome page with nothing saying so. It travels as a second query
  parameter now, re-validated on arrival through the same `validRedirect()`, with the
  session kept as the better channel when it survives. `test/Smoke/UserSmokeTest` proves
  it with **two cookie jars**, which is what "another device" is when written down.

  A test-harness bug fell out of this and is worth knowing: `MagicLinkSignIn::
  extractVerifyUrl()` matched `?token=[0-9a-f]+` and stopped, so the moment the link grew
  a second parameter the harness silently truncated it at the `&` and went on following a
  URL no mail client would produce. Every sign-in test in the suite was exercising only
  the session channel.

- [x] ~~**Signing in towards a page you may not reach now says so.**~~ **Done
  2026-08-20.** Redeeming a link sent the visitor to their destination, where the route
  guard answered a bare 403 — correct in general, and unhelpful here, because nothing on
  that page says the sign-in itself worked. The destination is checked while there is
  still somewhere to say it, and a refusal lands on the post-login page with **two**
  messages: that they are signed in, and which page was refused.

  **The obvious implementation is wrong and fails in the dangerous direction**, which is
  the part to remember. `Authorize::isAllowed()` cannot answer this: `load()` runs once
  per request and bakes the identity's roles into the ACL as it goes, and on this request
  `BjyAuthorize\Guard\Route` already triggered that load while the visitor was anonymous
  — so the identity is `guest` no matter what is written to the auth storage afterwards.
  It refused a member their own saved search. The check asks about the account's real
  roles instead, and getting *those* took two more corrections: `getUser()` does not link
  roles on a cache miss, which is exactly the freshly-registered case, and `rolesList` is
  a list of numeric `user_role.id` values despite the name, so every one of them misses an
  ACL keyed on role names. The names are on the link rows, as `name`.

- [x] ~~**A freshly issued API token no longer arrives as a flash message.**~~ **Done
  2026-08-20.** It renders in a Bootstrap well with a copy button, from a one-shot session
  container. The visible reason is shape — a JWT is several hundred characters and an alert
  box makes the reader select it by hand. The other reason is why this is not cosmetic:
  the flash pipeline translates its messages at render time, and a translator miss is what
  writes a phrase row, so an earlier version that appended the JWT to the message filed
  four real tokens into a table any `sch_api_translator` account can read. A
  `TranslatableMessage` parameter had fixed that instance; keeping the token out of the
  message pipeline altogether removes the class. The token is written into an input
  attribute and never into JavaScript, so nothing has to escape a credential into a script
  context. `test/Smoke/ApiTokenAdminSmokeTest` asserts the well, the button, that the token
  is **not** inside any alert element, and that a refresh does not show it a second time.

- [ ] **Tag JUser 2.0.0.** The content is on `modernization`; the tag is not, and tagging
  is the user's call. Note the wrinkle recorded in JUser's README: **`1.0.0` (2022-07-19)
  is not an ancestor of `modernization`** — it belongs to the abandoned `1.0.x` branch, so
  `git describe` reports `0.1.0-…` and reads like the tags were lost. The decision taken
  2026-08-20 was to number forward anyway rather than move a published tag.

- [ ] **An admin-created account is never told it exists.** Retiring the registration mail
  removed the only notification `UsersController::createAction()` could have sent — except
  it never sent one, because the chain was unreachable. So this is a gap that was always
  there and is now visible: an administrator creates an account to pre-assign roles, and
  nothing reaches the person. It mostly does not matter, since open registration means
  they can sign in with their address regardless and inherit the roles. Worth deciding
  rather than leaving implicit.

- [x] ~~**Measure `sion-model/auto-fix-data-problems` and `admin/import-father`** — the
  two unported singles nobody had looked at.~~ **Done 2026-08-21.**

  **`admin/import-father` needs nothing.** Measured rather than assumed, because the
  three previous pages of this kind all turned out to be broken: this one is POST-gated
  and form-validated, its three outcomes were repaired on 2026-08-17, its config keys
  match what `PatresGateway` reads (`patres_api_get_person_uri`, not the
  `patres_api_person_uri` a first pass guessed at), and the remote endpoint is live —
  `https://schoenstatt-fathers.link/api/persons` answers **401** without a key rather
  than 404. It 302s to a locale-prefixed form first, and both the redirect and the `key`
  query parameter survive the hop, which `Laminas\Http\Client` follows by default. The
  one thing not checkable from here is whether production's key is still valid; that
  needs a signed-in `sch_administrator`.

  **`sion-model/auto-fix-data-problems` had one real defect and it was under-reporting.**
  The page is simulate-by-default and CSRF-gated, so there was no GET-mutation hazard —
  unlike its retired cousins. What it does is backfill `lib_books.sort_text`. Measured on
  the capsule:

  | | books |
  |---|---|
  | active books with no sort text | **27,910** |
  | the page offers to fix | 9,764 — every one in Colegio Mayor |
  | invisible on the page | **17,207** |

  PUC's entire active catalogue — all 16,383 books — was in the invisible part. The
  number on screen read as the size of the problem when it was 35% of it.

  **The vocabulary for saying so already existed and had never been wired up.**
  `PROBLEM_LIBRARY_MISSING_SORT_TEXT_FORMAT` and its collection twin were declared, given
  `problem_specifications` entries with display text, and emitted by nothing at all. They
  are emitted now, from `getLibraryProblems()` — one problem per affected library rather
  than 17,207 rows, because the action is one decision per library — and that placement
  reaches both the global report and the per-library page, which is the one a librarian
  opens.

  Fixed alongside, all measured rather than inferred:

  - **The badge cost 0.54s and a 120 MB peak.** `LibrariesController` got the number by
    running the entire simulation — 27,910 books fetched, a sort text computed for each,
    9,764 objects built — to put one integer on a menu entry. `LibraryTable::
    getSortTextCoverage()` answers with one grouped query plus eight filter builds:
    **0.076s, 10 MB**. Fixability is a property of the (library, collection) pair, not of
    the book.
  - **The auto-fix labelled its own rows wrong**, as
    `collection-invalid-call-number-format` — the wrong entity *and* the wrong fault,
    sending an administrator to look at a collection that is fine. Now
    `book-missing-sort-text`.
  - **`getCollectionProblems()` assigned where it meant to merge**, so only the last
    library's collection problems survived its loop. Five libraries' worth were being
    discarded silently; the report goes from 4 such problems to 8.
  - **Three PHP 8.5 deprecations, all invisible in production** because the app narrows
    `error_reporting` to exclude `E_DEPRECATED`. The new test surfaced **16,692**
    occurrences of `null` used as an array offset in `getBookSortText()` (a collectionless
    book has a null `collectionId`), plus a dynamic property on `LibraryOptions` and a
    `new DateTime(null)`. All three are errors in PHP 9.

  A first pass had this diagnosed as a code bug in the collection→library fallback. It is
  not: the fallback exists inside `getSortTextFilter()`, and it needs a `CallNumberRegex`
  **and** a `SortTextFormat`. The finding is recorded below as the configuration decision
  it actually is.

- [ ] **17,207 active books can never be given a sort text, and that is a product
  decision.** Now visible on the data-problems report (above) rather than silently absent
  from the auto-fix page. `getSortTextFilter()` needs both a `CallNumberRegex` and a
  `SortTextFormat`, on the collection or its library, and:

  | library | books stuck | why |
  |---|---|---|
  | **4 PUC** | 16,383 | no format and no regex, and no collections either |
  | **1 Bellavista** | 514 | has a library `SortTextFormat` but **no `CallNumberRegex`**, so it does nothing; three of its four collections have neither |
  | **6 Austin Fathers** | 183 | nothing configured |
  | **7 Austin University Men** | 126 | `SortTextFormat` is `%1{author}{title}`, which does not parse — see the existing item on that |
  | **3 Colegio Mayor** | 1 | one collectionless book |

  Bellavista is the interesting one: somebody configured a format and it has never once
  been used, because the regex it needs was never filled in. Nothing anywhere said so.
  What each library's books should sort by is a librarian's call, not a code change.

- [ ] **`PhrasesApiV3SmokeTest::testAnOverwriteLeavesTheTextItDestroyedInTheHistory`
  failed once and has not failed since — mechanism undiagnosed.** Seen 2026-08-21 during
  a `ci-local` run: the assertion that `history[0].previous` holds the value the second
  PATCH destroyed. It then passed in isolation and on two subsequent full-suite runs.

  What it is **not**: a timestamp-granularity race. The history query orders by
  `history_id DESC` (`TranslationsTable:958`), an autoincrement, so two writes in the same
  second are still deterministically ordered — which was the first guess and is wrong.

  Where to look: the test patches a **fixed** phrase id that other tests in the same file
  also write to, so the state it inherits depends on what ran before it. A flake nobody
  can reproduce is worth one deliberate look before it is trusted, because the assertion
  it makes — that an overwrite is recoverable — is the one that justifies letting the API
  overwrite at all.

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
- [x] ~~**Move the unprefixed-to-prefixed locale redirect out of the ported
  controllers and into a `kernel.request` listener above the authorization
  check.**~~ **Done 2026-08-20.** `App\Http\LocalePrefixListener` at priority -8,
  between `LocaleListener` (0) and `AuthorizationListener` (-16); the declaration
  it reads is `App\Http\LocalePrefix`, alongside `RouteAccess` in
  `config/symfony/routes.php`, exactly as this item anticipated.

  What it stopped being tidiness for: the divergence this described —
  `?redirect=/admin` where laminas produces `?redirect=/en/admin` — is harmless
  while the return trip lives in a session, and is a **wrong destination** once it
  travels in a magic link that may be opened on another device. Both front
  controllers now answer `?redirect=/en/admin`, measured.

  Two things worth keeping. **The declaration turned out to be a boolean**, not a
  target: the route name comes from `SymfonyRoute::routeName()` and the parameters
  from the path's own placeholders, which was measured against all 42 hand-written
  call sites before deleting them — every one passed exactly its own path's
  placeholders and targeted its own route name. And **`$ported()` defaults to
  redirecting**, with the 34 non-redirecting routes pinned by name in
  `test/Integration/LocalePrefixDeclarationTest`, because a default is also how a
  new JSON endpoint would silently acquire a 302 its caller has to follow. Net:
  42 controllers lost the block, 12 lost the `RouteUrl` dependency they held only
  for it, 517 lines deleted against 201 added. See
  [strangler.md](strangler.md) § The locale hop, and the redirect order.
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

- [x] ~~**Strangler batch 11b: the library surface — 32 routes.**~~ **Done 2026-08-18.**
  Twenty-two ported, three retired as dead or empty, one left on laminas deliberately, five
  unmatchable Part parents unchanged, and `libraries/library/delete` still unportable. See
  [strangler.md](strangler.md) § The library circulation surface for the audit, the four
  deliberate differences and the four fixes it forced into older shared code. What it left
  behind is below.

- [x] **Extract the spreadsheet import engine from its controller** — done 2026-08-18, and
  the feature around it rebuilt. The engine is `App\Books\Import\LibraryImporter`, split
  into `plan()` and `apply()` where it was one method taking a `&$simulate` flag it also
  wrote to; `library-imports/library-import/edit` is Symfony-served; and
  `Books\Controller\LibraryImportsController` with its six view scripts is deleted, making
  `/library-imports` the first route tree here with no laminas controller behind it.

  The reason it was worth more than a port: **the import was usable by exactly one of the
  six libraries.** It matched Colegio Mayor's Spanish headings and nothing else, and the
  file had to already be on the server, named by a path typed into a text box. Import 14,
  created 2021-08-10 and still pending, records what that produced —
  `C:\Users\Ramon Vergara\Desktop\intento.xlsx`. So: a downloadable template (blank, or
  the library's own books in the same layout), an upload, worksheet discovery, alias
  matching against the file's own headings with a correction screen, per-row error reasons,
  a review page that shows changes rather than rows, a POST-plus-digest confirmation, and
  `bin/console books:import`. Four silent defects fixed on the way — the discarded
  `publishedYear`, the array-vs-string comparison behind 44,095 junk `sch_changes` rows, the
  barcode-becomes-zero ordering bug, and unexplained errors. See
  [library-imports.md](library-imports.md).

- [ ] **Nothing prunes uploaded import spreadsheets.** They live in `shared/data/import/` on
  the server and are kept deliberately — an import row names its file and the detail page
  shows it — but there is no budget and no expiry. Colegio Mayor's fourteen files are 2 MB
  together, so this is not urgent; it is unbounded.

- [ ] **An import is not transactional.** It applies row by row, as it always has, so a
  failure halfway through leaves the first half applied. Wrapping 16,000 writes in one
  transaction would hold locks on the whole library for its duration, which is a real
  trade-off rather than an oversight — but nobody has decided it. `sch_changes` records
  every field change with its old value, so the information to *undo* an import exists;
  nothing reads it that way.

- [ ] **`Subtítulo` is not importable.** Four of Colegio Mayor's spreadsheets carry the
  column and the import lists it among the ones it ignores. `lib_books` has no subtitle
  column; `sch_publications` does. Either add one or say why the literature record is the
  right home for it.

- [ ] **Library label printing.** `libraries/library/label-management` is a three-panel
  mockup — select books, export labels to Excel, confirm the call-number change — with all
  five buttons `href=""` and the count hardcoded to 3. Ported as the sketch it is, because
  the workflow it describes is worth more than the page costs. It is also unreachable from
  the admin menu: its `admin_pages` entry has been commented out for years. The template is
  the specification.

- [ ] **The borrower page's countdown redirect is broken and its target is hardcoded.**
  `books/borrowers/show.phtml` builds `var timerText = $timerText;` — the translated string
  interpolated without quotes — so the emitted JavaScript is a syntax error, the whole
  script fails to parse, the timer never starts and the announced redirect never happens.
  Reproduced verbatim in `templates/books/borrower.html.twig` rather than fixed, and the
  reason is the second half: `var redirect = "/libraries/3"` is hardcoded, so a working
  version would send every borrower page on the site to Colegio Mayor after fifteen seconds.
  Decide what the page is for before repairing it.

- [ ] **Mass checkout offers the wrong people at four of the six libraries.**
  `massCheckoutAction()` populates its borrower dropdown from
  `Schoenstatt\FathersValueOptions` unconditionally, where the single-checkout form uses the
  library's own `checkoutPersonListKind` provider. So the Austin libraries, which lend to
  anyone, offer a list of Schoenstatt Fathers. Reproduced because "who may this library lend
  to" has a real answer that nobody has written down — see [libraries.md](libraries.md).

- [ ] **Library 7's `SortTextFormat` does not parse.** `%1{author}{title}` expands to three
  printf parameters against one capture group, so building the library's sort filter throws.
  The ported diagnostic now reports it instead of dying, which makes it visible; the data
  still needs correcting, and until it is, `refresh-sort` on library 7 will throw when
  POSTed.

- [ ] **`show-collections` is an offered library display with no template.**
  `LibraryTable::MAIN_SHOW_DISPLAY_VALUE_OPTIONS` lets a moderator choose it on the library
  edit form and there is no `.phtml` for it on either side, so choosing it makes the library
  page fatal. The ported page falls back to the default rather than crashing; the option
  should either get a template or leave the form.

- [ ] **SionModel's read methods are annotated as never returning null, and they do.**
  `getObject()`, `getObjects()`, `queryObjects()`, `getLibraryImport()` and neighbours are
  `@return mixed[]` and answer null for a missing row, an empty projection or an empty
  query — so every honest null check reads to PHPStan level 8 as dead code.
  `App\Laminas\SionResult` funnels them through one `mixed` parameter to keep the checks
  and the analysis both. Fixing the annotations is the right end state and is a change to a
  library other projects use.

- [ ] **The `mailings` table's retention question.** Raised again by batch 11b because
  `send-book-notices` is now Symfony-served and unchanged: the table looks like an outbound
  queue and is a post-send log. `Status`, `Attempt`, `MaxAttempts` and `QueueUntil` are
  vestigial, all 117 rows date from 2017–18, and `OpenedOn` is null in every one — open
  tracking has never worked. Re-enabling the book-notices cron starts writing full message
  bodies into it again. The governing principle from the relay work: server-generated
  content may be logged, recipient behaviour may not be observed, so open tracking goes
  regardless of what happens to the table.

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

- [x] ~~**`/admin/literature-maintenance` and its six children, and
  `/admin/maintenance`.**~~ **Retired 2026-08-17**, eight routes, after measuring what
  each one would still do. They were the 2020 migration that turned imported
  ("data-sourced") publication rows into first-class ones, plus one unrelated person
  sweep. Measured in the capsule against a production export days old:

  | sweep | rows today |
  |---|---|
  | `copy-data-sourced-row-to-first-class-citizen` | **0** |
  | `update-main-publication-ids` | **0** |
  | `update-translated-from-publication-id` | 1 |
  | `update-library-book-publication-references` | 2 |
  | `update-cover-images` | 20 files, 4 publications |
  | `list-merged-publication-id-map` | read-only report, 3,630 entries |

  - **The bulk phase was finished and the residue was one publication.** All three
    database rows pointed at 1426 → 10249 ("Wachstum im höheren Gebetsleben"). The
    2,333 publications that still carry a `DataSource` are *not* unfinished work: they
    sit at `IsAwaitingMerge = 1`, which the bulk sweep skips by design, and a human
    merges them one at a time through `publication-copy-to-main-corpus`, which stays.
    `database/db8.2.sql` follows the merge in all three reference columns, written as a
    join on the merge map rather than against the three ids measured that day, so it is
    correct on whatever production actually holds.
  - **`update-cover-images` would have quietly overwritten two publications' covers.**
    `rename()` clobbers its destination without a word, and the sweep did two opposite
    things at once. For 10249 and 10186 it moves a cover onto a publication that has
    **none** — a visible defect, since both controllers resolve covers by
    `file_exists('public/covers/<id>-400px.jpg')`, so those two pages show no picture
    while the file sits on disk under the pre-merge id. For 10203 and 10185 it would
    replace a cover that already exists with a **different image** (verified not a
    re-encoding: 1672-2000px.jpg is 484,373 bytes against 10185-2000px.jpg's 326,012).
    `tools/fix-merged-covers.sh` did the first pair and refused the second. **Applied to
    production 2026-08-18** — all ten files moved, and `/en/SL210249L` and `/en/SL210186L`
    now render `/covers/10249-400px.jpg` and `/covers/10186-400px.jpg`; the old ids answer
    404. The script was deleted in the same change, having been written as one-off.
    - Worth keeping, because it is the part that nearly went wrong: **covers are
      gitignored and live in `shared/public/covers`, so no deploy carries this** — it was
      `--apply` over SSH, a manual step. The script was very nearly deleted a day earlier
      on the reasonable-sounding grounds that the work had been "merged and deployed".
      Merging and deploying a repair that lives outside the repository does nothing at
      all, and the only way to tell was to ask production for the file.
    - **Still open, and it is a content decision:** publications **10203** and **10185**
      each have a cover *and* a different pre-merge image (`1437*`, `1672*`) still sitting
      in `shared/public/covers`. Somebody who can look at the four pairs should pick which
      image is right; until then the existing covers stand and the orphans are harmless.
  - **`/admin/maintenance` was the dangerous one.** It stripped the `priest` tag from
    any person without a `PriestDate`, on a 2020 comment's premise that "so far, all
    priests should have priestDate". False today: of 15 people carrying the tag, **5
    have no date**, so a real run removed a correct-looking tag from five named people.
    Nothing linked to it — it was absent from both admin-index lists — so it was
    reachable only by typing the URL, and only by `administrator`.
  - The lesson is the same one `/en/associations/do-work` taught: the question that
    dissolves these is *what would it do if I ran it today*, and it goes unasked
    precisely because a simulate-by-default page never hurt anybody.

- [ ] **Upload a book cover from the site.** There is no working way to do it, and this
  is the request that came out of the retirement above. Whoever picks it up starts from
  three separate defects, not one:
  - **`publication-upload-cover` exists and is dead three ways over** — see the
    unguarded-routes item under "Config rot" for the detail. No guard entry; an inverted
    success test in `uploadCoverAction()`; and `bookCoverFileId`, the column it writes,
    does not exist in `sch_publications` and is hardcoded to `null` in the row
    projection. Granting the route a role fixes none of that.
  - **Display and storage disagree about what a cover *is*.** Both readers resolve one
    by filename convention — `public/covers/<publicationId>-400px.jpg` in
    `App\Controller\PublicationController`, `-80px.jpg` in
    `App\Controller\LiteratureController` — while the upload path stores a row in
    `sch_files` and tries to point at it by id. Only one of those can be the design.
    The convention is what actually works today and what `public/cover-thumbnails.sh`
    produces, so the burden is on the file-row approach to justify itself.
  - **The five sizes are a pipeline, not an upload.** A publication's cover is
    `<id>.jpg` plus `-80px`, `-200px`, `-400px` and `-2000px` variants; an upload that
    writes one file gives the index no thumbnail. Whatever gets built has to run the
    resize, and `public/cover-thumbnails.sh` is the existing answer to that.
  - Worth doing on the **Symfony** side. It is a form route, the form layer exists, and
    a file upload is one of the few places where reproducing laminas behaviour buys
    nothing.

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
- [x] ~~**`opcache.interned_strings_buffer` raise — requested 2026-08-18.**~~
  **Landed, confirmed 2026-08-21** from a live `/en/sm/cache-status`:
  `internedBufferConfiguredMb: 32`, `internedBufferBytes: 33554432`,
  `internedPercentUsed: 39.9`, `internedStrings: 68328`. It had sat at 89–100% of
  8 MB. When the buffer fills, strings simply stop being interned: no restart, no
  error, just a quiet loss of the saving the buffer exists to provide.

  **The new number says more than "comfortable".** 13.4 MB of interned strings on a
  cache that had only just been reset by the deploy is already *more than the whole
  old buffer* — so the 8 MB ceiling was genuinely truncating, and everything past
  it had never been interned at all. The old 89–100% was not a cache comfortably
  near its limit; it was a cache that had stopped doing part of its job.

  Still open on the same konsoleH front: **`apc.ttl` is still 0**, so a failed APCu
  allocation expunges the whole segment rather than evicting. The size half of that
  ask landed on 2026-08-11 (32M → 256M); the eviction half did not.
  - **Confirming it landed is a sampling problem, not a lookup**, and the
    instrumentation for it went in on 2026-08-18: the directive is
    `PHP_INI_SYSTEM`, so a running pool keeps the old buffer after the file
    changes, and this host has at least three pools recycling independently. Use
    `./tools/opcache-sample.sh` (groups polls by `startTimeUnix`, reports a lower
    bound on the segment count) or reload `/en/sm/phpinfo` several times. See
    [php-85.md](php-85.md) § Confirming the raise.
  - **`/sm/cache-status` reported the ratio and not its denominator until
    2026-08-18**, which meant it could not answer whether a raise had taken effect
    — a ratio cannot show its own denominator changing. It now reports
    `internedBufferBytes` and `internedBufferConfiguredMb` as well.
  - **The buffer is append-only**, so a reading taken after a deploy is cold and
    will look healthy no matter how undersized it is. `tools/deploy.sh` prints each
    pool's usage at the instant its reset wipes it — the warmest reading that
    exists, and free, since the reset helper already read the status.
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

- [ ] **The cookie explainer's one paragraph is not translated.** `templates/content/sign-in-no-cookies.html.twig`
  prints it as a raw English literal, faithfully reproducing the .phtml, so a German visitor
  who has refused cookies reads English on the one page that is trying to tell them how to
  proceed. The `<h1>` next to it *is* translated. One `translate()` call, and the phrase is
  not yet in the table.

- [ ] **`Entity::$actionRouteProperties` names two properties `Entity` does not have.**
  `create => 'createRoute'` and `touch => 'touchRoute'`, in
  `module/SionModel/src/Entity/Entity.php`. `FormatEntity::isActionAllowed()` and
  `App\Laminas\EntityFormatter::isActionAllowed()` read the mapped name off the spec
  dynamically, so asking either about the `create` or `touch` action reads an undefined
  property: a warning on 8.5, which production's `error_reporting` excludes, and a *null*
  route — which means the route permission check silently passes. Nothing calls
  `isActionAllowed()` with either action today (both formatters only pass `show` and
  `edit`), so this is a landmine rather than a live bug. The real property names are
  `createActionRedirectRoute` and `touchJsonRoute`; whether the map should point at those or
  the two entries should go is the question, and the `touch` feature was removed in August
  2026, which argues for going. Found 2026-08-21 while writing
  `test/Integration/EntitySpecRoutesAreAssemblableTest`, whose own list of properties is
  guarded by a `property_exists()` assertion for exactly this reason.

- [ ] **`tools/form-regression.php` still leaks an account per run.** It signs in as
  `form-regression-<time>@example.com` and never purges; 11 such accounts existed on
  2026-08-21. `tools/port-baseline.php` had the same habit and 61 accounts, fixed the same
  day by giving it one deterministic address instead — the passwordless flow treats a known
  address as a sign-in rather than a registration, so reusing one costs nothing. The same
  one-line change would do it here, but that tool's own docblock says to throw it away when
  it stops earning rent, so deleting it may be the better answer.


- [ ] **Four selects refuse records that already exist**, because their option list is
  narrower than the data. This is not a hypothetical: open one of these records, change
  anything, press save, and the form rejects it over a field nobody touched. Each is accepted
  in `ConstrainedChoiceFieldsFitTheirDataTest::ACCEPTED_MISMATCHES` with its row count, so the
  numbers are checked and shrinking one requires editing that list.

  - **`getEditionValueOptions()` filters `DataSource IS NULL`**, offering 4,203 of the 10,166
    publications and excluding the 5,963 imported ones. **479 books** (`BookForm::publicationId`),
    **46 publications** (`mainPublicationId`) and **165 publications**
    (`translatedFromPublicationId`) point at an excluded row. Either the picker should offer
    imported publications or those records should not reference them; nobody has recorded why
    the filter is there, which is the thing to establish first.
  - **`RoleForm::associationId`** offers active associations only, and `sch_roles` has no
    foreign key: **145 roles name an association id that does not exist at all** (74 distinct
    ids) and 4 more sit under the two inactive associations. The dangling 145 are a data
    repair; the 4 are a question about whether an inactive association's roles stay editable.

- [x] ~~**`EventsSearchForm` and `EventsController::searchAction()` are unreachable.**~~ **Done
  2026-08-15**, as item 1.4 of [timeline-and-corpus.md](timeline-and-corpus.md). There was no
  `search` child route under `/timeline`, the events entity's `controller_services` is `[]` so
  `$this->services[EventsSearchForm::class]` was an undefined key, and `search.phtml` rendered
  nothing. The form settled it: it carried `libraryId` and `collectionId` and **no event field
  at all** — a copy of a library search that had been sitting in `Books\Form` since 2020. All
  three deleted, along with `books/events/event-list.phtml`, which is a copy of the *publication*
  list living in the events directory and rendered by nothing. Three stale lines left the fuzz
  baseline and none were added.

- [ ] **`selectize({create: true})` on fields the server constrains.**
  `AdvancedSearchForm::roleTitle` was declared open-by-design on the strength of that flag and
  turned out to be enforced anyway — its element never disabled its own `InArray`, so a typed
  role title is rejected today. The declaration is gone; the mismatch between what the view
  offers and what the server accepts is not. Worth a sweep: the flag is the only place this
  application states intent, and where it disagrees with the server the user sees a field
  accept their typing and then refuse it.

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
    endpoints** — down from 29/18. ~~The eleven left all need a *guard entry*
    rather than deletion, and none of them is dead config.~~
  - **That claim is wrong, measured 2026-08-17, and the way it was wrong is the
    point.** Five of the six entries below were about to be written when a spot
    check on one of them found it broken; checking the rest found four more.
    **Granting a role is not the last step — it is the step that reveals whether
    anything is behind the door.** A route with no guard entry is *silently*
    denied, so nothing downstream of the guard has ever executed and nothing has
    ever failed. Sibling agreement says who *should* be allowed in; it says
    nothing about whether there is a page to see. This is exactly the shape
    `EventFeatureStateTest` was written to protect against — "adding a guard entry
    alone converts a clean default-deny into a 500" — and the note above talks
    itself out of applying that lesson to its own list. **Verify the action, the
    template and the form before granting anything.**
  - ~~**4 answer themselves from their siblings** and need no product decision —
    every neighbouring route in the same tree already agrees:
    `checkouts`, `checkouts/checkout`, `checkouts/checkout/edit` → `lib_user`
    (as `checkouts/library`, `…/current`, `…/overdue` all are);
    `publication-upload-cover` → `pub_moderator` (as `publications/create`).~~
    **All four are dead config. Do not grant them.**
    - ~~`checkouts`, `checkouts/checkout`, `checkouts/checkout/edit` resolve to
      `SionController`'s generic `indexAction`/`showAction`/`editAction`, which
      render from the entity spec — and in the `checkout` spec `index_route`,
      `index_template`, `show_action_template`, `edit_action_form` and
      `edit_action_template` are **all commented out**. There is nothing to
      render.~~ **Deleted 2026-08-17.** `/checkouts` is now an unmatchable prefix
      (`may_terminate => false`) so `/checkouts/library/:id` keeps working, and the
      two child routes are gone along with the `show_*` and `delete_*` spec keys
      that pointed at them. See [libraries.md](libraries.md).
    - `publication-upload-cover` is dead **three** ways over, and any one of them
      is enough. (1) No guard entry. (2) `uploadCoverAction()`'s success test is
      inverted — `if (! $newId = $filesTable->createEntity('file', $data))` — so
      it redirects on failure and throws `Error uploading file.` on success. (3)
      The column it writes does not exist: `bookCoverFileId` is hardcoded to
      `null` in `PublicationsTable`'s row projection and `sch_publications` has no
      `Cover`/`File` column at all, so `updateEntity()` would discard it. Granting
      `pub_moderator` would produce a page that looks like it works and silently
      loses every upload. See the cover-upload item below.
  - ~~**`sign-in-no-cookies` is already reachable, by accident of listener
    priority**~~ — **resolved 2026-08-21 by deleting the route.** The mechanism was
    right and worth keeping: `GdprStrategy::onRoute()` swaps the RouteMatch at
    priority **-5000** while `BjyAuthorize\Guard\Route` checks at **-1000**, so the
    guard evaluated and approved `zfcuser/login` and only then did the strategy
    rewrite the match — the guard never saw this route's name, and the *page* was
    reachable while the *URL* was not. The conclusion drawn from it was wrong: this
    said the route should get a public entry so that direct navigation works. Nothing
    wanted direct navigation. Nothing links to the URL, no visitor has ever used it,
    and granting it would have added a public route to defend for no gain. The route
    is gone from both front controllers; the page is
    `templates/content/sign-in-no-cookies.html.twig`, rendered by
    `App\JUser\CookieExplainer`, and the swap never needed a route because it builds
    its RouteMatch by hand.
  - ~~**`libraries/library/delete` → `lib_administrator`**, deliberately *not* the
    `lib_user` its siblings carry: it is the destructive one in that tree and
    `lib_administrator` already exists for exactly this.~~ **Also dead config,
    measured 2026-08-17.** It resolves to `SionController::deleteAction()`, which
    is gated on the entity spec's `enable_delete_action` — commented out for the
    `library` entity, along with `delete_action_acl_resource`,
    `delete_action_acl_permission` and `delete_action_redirect_route`. The role
    choice is still the right one *if* the feature is ever finished; it is the
    "needs only a guard entry" part that was false.
  - ~~**`sign-in-no-cookies` is the one that really does need only a guard entry**~~ —
    **moot 2026-08-21: the route was deleted.** The action and its .phtml do still
    exist and still render, but only through the route-match swap, and only for the
    rollback path — both auth routes are Symfony-served, where the gate is
    `App\JUser\CookieExplainer`. They go with `JUser\Controller\LoginController`.
    So the "safe to grant because granting changes the least" reasoning inverted:
    granting changed nothing anyone wanted, and deleting changed nothing either —
    which is the better of the two.
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
    match `events` — but only once a show template exists, or the guard turns a
    default-deny into a 500. The whole feature is planned in
    [timeline-and-corpus.md](timeline-and-corpus.md); this decision is its item
    1.6, and it also gates Part 2's form work.
  - **Not** related to the disabled `SchoenstattTable` provider below, despite
    an earlier note here saying so: that provider only ever emitted
    `person_*`/`association_*` resources, never `route/*` ones, so it could not
    have guarded a route.
- [x] ~~**The per-library ACL rules are invisible to the baseline.**~~ **Fixed
  2026-08-17.** `Books\Model\LibraryTable` builds a `show`/`checkout`/`administrate`
  allow per row of `lib_libraries` at request time, so `tools/acl-table.php` — which
  reads merged config plus plain PDO, deliberately, so it survives a mid-migration
  `vendor/` — could not list them, and said so in its own output. The consequence was a
  baseline covering every route guard and **none of the rules that actually gate the
  library pages**: three libraries granted `checkout` to all 34 effective roles for years
  and no diff ever showed it. `App\Books\LibraryAclRules` now reproduces the mapping, the
  tool reports it in a new section, and `test/Integration/LibraryAclRuleDriftTest` fails
  if the copy and `getRules()` disagree — mutation-checked, not merely written.
  - The general lesson, worth more than the fix: **"this cannot appear in a
    config-derived snapshot" was stated accurately and then treated as the end of the
    matter.** A tool that documents its own blind spot still has the blind spot, and a
    baseline that is confidently silent reads exactly like one that is confidently
    complete.
  - Still not covered: `Books\Model\EventTextTable`, the other dynamic rule provider. Its
    resources are per *event text* rather than per library, so the same reproduction needs
    a row-count bound before it belongs in a diffable file.

- [ ] **`LibraryOptions` creates dynamic properties**, deprecated on PHP 8.5 and an
  `Error` in 9. `module/Books/src/Model/LibraryOptions.php:185` assigns
  `$isPublicallyListed` to an object that declares no such property; it fires once per
  library on anything that loads the library list, which `LibraryAclRuleDriftTest` made
  visible. Declare the property — but read the whole class first, because a constructor
  that assigns from an array rarely has only one.

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
- [ ] `booksmodellibrarytable-checkouts` serializes to **4,608,092 bytes**
  against the 4,194,304 `max_cached_item_size` budget, so it is refused on
  every request, permanently, and the query behind it runs every time. Found
  2026-08-22 while retiring `max_items_to_cache`; it is the size budget doing
  its job and pointing at a query that wants narrowing, the same shape as
  `query-objects-publication` above. Note this one is *just* over — raising the
  budget would buy a few months and hide it again, which is the wrong fix.
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

- [x] ~~**The smoke suite was leaking a real account per sign-in, and had been for weeks.**~~
  **Fixed 2026-08-21.** `MagicLinkSignIn`'s docblock has always said every class using it must
  purge in `tearDown()`; nine of the twenty-eight did not, and registration here is open, so
  every address any of them posted became a real account. Measured: **6,011 of the capsule's
  6,303 accounts were `@example.com` fixtures**, 2,146 of them from `Batch6SymfonySmokeTest`
  alone, growing by a few hundred per full suite run.

  Three things worth keeping from it:

  - **It corrupted a measurement before it corrupted anything else.** The `user` table was 95%
    ours, so `getUsers()` — which caches every account with its roles as one APCu item — read
    14.03 MiB where the real figure is 0.55 MiB, and `/users` rendered 6,303 rows where
    production renders about 292. Batch 12's first cost estimate for that page was wrong in
    the alarming direction, and the reason was the test suite. The capsule's *content* tracks
    production; its `user` table is the one place the suites are the majority of the rows.
  - **A docblock instruction was the wrong mechanism.** The fix is an `#[After]`-attributed
    purge on the trait itself, not a `tearDown()` — a class defining its own `tearDown()`
    silently wins over a trait's, which is exactly the failure being prevented. An attributed
    method runs in addition to `tearDown()`, so forgetting is no longer possible.
  - **The nineteen classes that did purge are unaffected**: both purges are idempotent
    DELETEs keyed on the class's own prefix, so the explicit calls stay harmless. Verified by
    running `Batch6SymfonySmokeTest` and counting: 0 accounts left behind, against ~40 before.

  `tools/form-regression.php` still leaks — see Bugs above.


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

- [x] ~~**Drop the `?key=` fallback.**~~ **Done 2026-08-17** (sion-model#26 +
  the superproject PR that bumps its pointer). `MaintenanceKeyTrait` and
  `App\Http\MaintenanceKey` now read the key from the `X-Api-Key` header and
  nowhere else, and they changed together, because a disagreement between them
  would mean flipping `SYMFONY_KERNEL` changes what a deploy hook may send.
  - The last blocker below dissolved rather than being cleared: the notices cron
    never got re-enabled with a header, because `books:send-notices` (PR #116/#117)
    made it a console command that needs no key at all. The crontab was then
    confirmed to hold no `?key=` caller — the only check that could not be made
    from the repository.
  - `test/Unit/MaintenanceKeyChannelTest` tightened with it: the sweep used to
    *exclude* the two gates, since both legitimately read the query string. It now
    covers them too, strips comments before matching (so the docblock explaining
    why a query string is unsafe does not read as a violation, and a commented-out
    reintroduction does), and adds a positive assertion that neither gate's code
    mentions `query` at all. Verified by reintroducing the read and watching both
    tests fail.
  - The historical record, kept because it explains why this took three passes:
  - ~~The deploy config that sends `?key=` lives in each machine's gitignored
    `phploy.ini`, which no commit here can update.~~ **Gone**: phploy is retired
    and `tools/deploy.sh` sends `X-Api-Key` as a header.
  - ~~Not every endpoint accepts the header.~~ **Gone**:
    `Books\Controller\LibrariesController::sendBookNoticesAction()` was the last
    query-only check and now uses `MaintenanceKeyTrait`. It was invisible to the
    plan recorded here, which named only the trait and `App\Http\MaintenanceKey`
    — so carrying that plan out would have left one endpoint leaking with nobody
    looking for it. `test/Unit/MaintenanceKeyChannelTest` now fails on any new
    bespoke implementation, in either front controller's idiom.
  - ~~**What is left:** the overdue-book-notices cron.~~ **Moot**: it became a
    console command, so there was never a header-authenticated run to confirm.
  - Worth knowing before trusting any audit of this: the key that endpoint checks
    is `schoenstatt.api_keys`, **not** the `sion_model.api_keys` everything else
    uses. Two config arrays, one of them easy to miss.
  - Established 2026-08-04, worth not re-deriving: **the flush cannot become
    a pure CLI command.** The persistent cache is the APCu adapter, and an
    APCu segment belongs to the SAPI that created it, so a CLI process gets
    its own (or none, with the default `apc.enable_cli=0`). Measured: a CLI
    `apcu_clear_cache()` left all 21 web-segment entries untouched. Only an
    HTTP request into the web SAPI can flush it, which is what
    `cache:flush-persistent` does.
- [x] ~~Port `/en/associations/do-work` to a console command — the last deploy
  hook that is still a `wget`.~~ **Deleted instead, 2026-08-17.** The port was
  scoped, an acting-user decision was worked out, and then measuring the two
  methods showed there was nothing to port:
  - `autoFillTimeZones()` fills a zone only where a country has exactly one.
    **0 of the 54** candidate associations qualify (28 multi-zone, 26 with no zone
    list). It returned `void` while the endpoint reported it as a value, so its
    result key was permanently `null`, and it `var_dump()`ed into the response —
    including a hardcoded `'CL' === $country` debug branch — on every deploy.
  - `updateAssociationMd5s()` wrote five columns **nothing read**. It was
    compensating for a bug in the association save path, which computed the digest
    with no locale and so wrote five identical values where the sweep wrote five
    distinct ones. 490 of 498 rows held the sweep's version; the 8 exceptions were
    rows edited since the last deploy. Neither side ever errored, which is why a
    disagreement this total stayed invisible.
  - Also removed: the five `SchemaOrgJsonMd5V1*` columns (`database/db8.1.sql`,
    `@phase: post` — the previous release still SELECTs them during the pre phase),
    and `getAssociationListSchemaV1()`/`V2()` plus
    `MusicTable::getCompositionListSchemaV1()`, three methods with no callers that
    returned those digests to the v1/v2 APIs retired on 2026-08-14.
  - The lesson worth keeping: this sat on the backlog as "port the last hook" for
    weeks. The question that dissolved it — *does anything read the output?* — was
    never asked, because the hook ran on every deploy and never failed.
- [x] ~~phploy upstream PRs (banago/PHPloy): the directory-purge bug… evaluate
  Deployer…~~ **Moot 2026-08-15: phploy is retired.** `tools/deploy.sh` replaces
  it with rsync into release directories and a symlink swap — so the purge bug,
  the `--list` submodule gap and the mid-deploy broken window all go away
  together, and neither upstream PR is worth writing. Deployer was weighed and
  passed over: four private repos rule out its git-clone flow, the pre/post
  migration phases would have been custom tasks anyway, and a purpose-built
  script needs no PHP locally — which is what removes the `php8.0` requirement
  phploy imposed. See docs/DEPLOY.md.
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
- [ ] Give **SionModel** the `laminas/laminas-cache` require it uses.
  `SionCacheTrait`, `PersistentCacheFactory`, `LegacyCacheConfig` and
  `SionModelController` all reference `Laminas\Cache` and its `composer.json`
  does not require it; it resolves transitively here through the storage
  adapters, so only a standalone install of the package notices. JUser had the
  same gap and it was closed 2026-08-21 (`^3.0 || ^4.0`, matching JTranslate).
  The `laminas/laminas-crypt` item that used to sit here is done — 864d08f
  removed it from JUser's require block along with the password era.
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
