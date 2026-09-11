<?php

/**
 * Two-front-controller, five-locale rendering diff for a route being ported.
 *
 * **Retired in practice since 2026-09-08.** The SYMFONY_KERNEL canary is gone, so the
 * `SetEnv SYMFONY_KERNEL 0` step below no longer forces the laminas front controller —
 * `public/index.php` runs App\Kernel unconditionally — and there are no unported pages
 * left to diff anyway. Kept for its normalization rules and as the record of how the port
 * was verified; a laminas capture is no longer obtainable.
 *
 * This is the procedure docs/laminas-exit.md calls "Verifying a port against
 * production, across every locale", turned into a tool. Batch 3 ran it by hand and
 * it is what found the translation defect: the ported pages were diffed
 * byte-for-byte on `/en/…` and passed, while all four other languages rendered
 * English source text. In English a missing translation *is* the source string, so
 * the defect showed up only as a capitalisation nobody would look at twice.
 *
 * Two cheaper checks are both insufficient and that is why this exists:
 *
 * - The capsule cannot compare front controllers by itself. Its vhost sets
 *   SYMFONY_KERNEL=1 unconditionally, so a capsule-only "before and after"
 *   compares Symfony with Symfony. Appending `SetEnv SYMFONY_KERNEL 0` to
 *   public/.htaccess forces laminas (AllowOverride All, and SetEnv beats the
 *   canary's SetEnvIf) — that is the *only* edit the procedure needs, and it is
 *   removed again before the second capture.
 * - English proves almost nothing, per above.
 *
 * Usage, inside the app container:
 *
 *     # 1. on the default front controller, sign in once and keep the session
 *     docker compose exec -T app php tools/port-baseline.php session
 *     # 2. append `SetEnv SYMFONY_KERNEL 0` to public/.htaccess
 *     docker compose exec -T app php tools/port-baseline.php capture laminas
 *     # 3. remove that line again
 *     docker compose exec -T app php tools/port-baseline.php capture symfony
 *     docker compose exec -T app php tools/port-baseline.php compare laminas symfony
 *
 * Step 1 is **not optional for the laminas capture**, and that is new as of 2026-09-08:
 * the sign-in surface is Symfony-only since batch 13, so under `SYMFONY_KERNEL=0` the
 * login form this tool used to POST answers 404 and the capture dies in setup. See
 * {@see storeSession()}.
 *
 * Exit 0 = every response identical once normalized, 1 = drift, 2 = setup failure.
 *
 * ## Both identities, deliberately
 *
 * Every URL is fetched twice: once anonymously and once as an account holding every
 * role. A ported page renders permission-gated markup — the moderator table, the
 * edit pencils — and an anonymous-only comparison would prove nothing about any of
 * it. The signed-in half is also the only way a guarded route is compared at all
 * rather than as its 302.
 *
 * ## What is normalized, and why each one is not cheating
 *
 * Each rule below erases something that differs between two runs of the *same*
 * front controller. Nothing here erases a difference between the two renderings.
 *
 * 1. **302 bodies.** laminas renders the entire sign-in page into the body of its
 *    302 (~9 KB); Symfony's RedirectResponse sends a 378-byte meta-refresh stub.
 *    Same status, same Location, and nothing reads a 302 body. So a non-200 is
 *    compared on status and Location only.
 * 2. **HTML entities.** Twig escapes a `"` inside a translated string to `&quot;`;
 *    the laminas .phtml echoes it raw. Identical in a browser, and Twig's is the
 *    safer of the two.
 * 3. **Visit counters.** SionTable::registerVisit() INSERTs a row on every request,
 *    so /dictionary/es reports a higher "Total views" on the second capture than on
 *    the first purely because the first happened. The digits go; the markup around
 *    them stays, which is what would catch the counter disappearing.
 * 4. **The CSP nonce**, **the throwaway account's address** and **the CSRF token**, all
 *    three of which are per-run by construction. The token was missing until batch 5 put
 *    a comment form on three ported pages, and its absence is worth recording: the form
 *    renders only for a signed-in visitor, so the symptom was "the six signed-in captures
 *    of every commentable page differ" rather than anything pointing at a token.
 * 5. **The language chooser's flags.** `flag-icon-gb` or `flag-icon-us` for English,
 *    `ar`/`cl`/`mx`/`es` for Spanish: the chooser picks at random among the countries
 *    that speak each language, on **both** front controllers, so two fetches of one URL
 *    from one front controller already disagree. Measured 2026-08-08 — the anonymous
 *    and signed-in captures of /en/developers came back with different flags.
 * 6. **Whitespace between markup.** Runs of space/tab/newline collapse to one space.
 * 7. **Four fixed chrome differences** that exist on *every* ported page and have
 *    nothing to do with the page: `<head>` in full, the language chooser, every
 *    `application/ld+json` block, `<body >`'s stray space, and Twig's added
 *    `aria-current="page"`.
 * 8. **The footer's serving note** (App\View\ServingNote), which is the one rule here
 *    that erases a difference between the two renderings — and does so because that
 *    difference *is the feature*: the note says "Twig template" on one side and
 *    ".phtml view script via LegacyBridge" on the other, on purpose, so that a human
 *    can tell at a glance what served the page. Comparing it would report drift on
 *    every path in every locale and drown everything else. What is lost is nothing this
 *    tool was ever checking: the note is pinned by test/Unit/ServingNoteTest and
 *    test/Smoke/ServingNoteSmokeTest instead. Goes away when the note does.
 *
 * ## Why rules 6 and 7, and what they cost
 *
 * `templates/layout.html.twig` is a *reproduction* of
 * `module/Application/view/layout/layout.phtml`, not a byte copy of it. Its
 * indentation, its `<head>` ordering, its JSON-LD encoder and its navbar markup were
 * all written afresh, so the two front controllers have never emitted the same bytes
 * for the chrome, on any ported route. Measured on 2026-08-08, before this batch
 * touched anything: /en/developers, /en/privacy and /en/shrines all differed from their
 * laminas renderings, and *only* in the ways rules 5, 6 and 7 name. **That corrects
 * docs/laminas-exit.md**, which claims batch 3 came to "65 of 65 responses identical" —
 * as whole documents they were not, and no later batch can make them so.
 *
 * So the comparison is deliberately scoped to what porting a page actually owns:
 *
 *     kept      <title>, the breadcrumb trail, the navbar (including which item is
 *               active — a ported route feeds that from its own name), the flash
 *               region, and the entire page body
 *     dropped   <head>, the language chooser, the JSON-LD blocks, indentation
 *
 * The navbar is deliberately *not* dropped even though it is chrome: `current_route()`
 * reads the ported route's name, so getting that name wrong silently stops the current
 * page lighting up, and this is the check that sees it.
 *
 * `&nbsp;` survives all of it — it decodes to U+00A0 under rule 2, which is why rule 6
 * spells out its character class instead of writing `\s`, and the role labels on /roles
 * are built out of it.
 */

declare(strict_types=1);

const BASE_URL       = 'http://localhost';
const MAILPIT_URL    = 'http://mailpit:8025';
/**
 * The GDPR consent cookie, name **and value**, as Application\View\GdprStrategy
 * reads it: `'true' === $_COOKIE['EU_COOKIE_LAW_CONSENT']`.
 *
 * The value is the part worth stating. tools/form-regression.php sends this cookie
 * with the value `1`, which is not `'true'`, so the strategy sees an unconsented
 * visitor and rewrites every auth route to the cookie explainer — a page
 * with no form on it. That tool's sign-in therefore cannot work, and its failure
 * mode is the misleading "no CSRF token on the login form". Measured 2026-08-08,
 * after making the same mistake here.
 */
const CONSENT_COOKIE = 'EU_COOKIE_LAW_CONSENT';
const CONSENT_VALUE  = 'true';
const EMAIL_PREFIX   = 'port-baseline-';
/**
 * **One account, reused across every run — not a fresh `time()`-suffixed one.**
 *
 * This was `EMAIL_PREFIX . time()` until 2026-08-21, and the reason for the change is
 * `/users`: the user-administration index renders one table row per account, carrying
 * the account's own `user_id` in two links. A throwaway account per run therefore puts
 * a row in the second capture that is not in the first, with an id no normalization
 * rule can match without also blanking the 292 real ids the page exists to show. The
 * page could not be compared at all.
 *
 * A fixed local part fixes that by construction: the passwordless flow treats a known
 * address as a sign-in rather than a registration (`UserTable::createUserFromEmail()`
 * is reached only for an unknown one), so run 1 creates the account and every run after
 * it signs the same one in — same id, same username, same row. It also stops this tool
 * leaking an all-roles account into the capsule on every run; there were 61 by the time
 * anyone counted.
 *
 * Rule 4 still erases the address, the username and the display name, all three of
 * which are derived from this constant.
 */
const EMAIL_ACCOUNT  = EMAIL_PREFIX . 'account';
const EMAIL_DOMAIN   = '@example.com';
const OUT_ROOT       = __DIR__ . '/../data/port-baseline';

/**
 * Where `session` keeps its jar, and therefore a capture name nobody may use.
 *
 * A directory under OUT_ROOT rather than a temp file, because the whole point is that it
 * survives between two invocations of this tool with a front-controller flip in between.
 */
const SESSION_NAME = 'session';

/**
 * The URL {@see assertSignedIn()} probes, and it has to satisfy three things at once:
 * guarded (so an anonymous visitor gets a 302 rather than the page), served by **both**
 * front controllers, and not data-dependent.
 *
 * `/en/admin` is the site's first restricted route and both sides still render it — the
 * laminas action stays until the .phtml does. Most of the obvious alternatives no longer
 * qualify: `/en/users` and `/en/user/login` are Symfony-only since batch 12/13, and any
 * entity path depends on a row the capsule may not have.
 */
const SESSION_PROBE = '/en/admin';

/** The five configured aliases, i.e. every prefix a visitor can actually be on. */
const LOCALES = ['en', 'es', 'de', 'pt', 'it'];

/**
 * Paths under test, without a locale prefix. Each is fetched once per locale and
 * once bare — the bare form is not decoration: SlmLocale answers it with a redirect
 * to the negotiated language rather than serving the page twice, and a ported
 * controller has to reproduce that from the absence of the `_locale` attribute.
 * Getting it wrong serves the same body at two URLs, which no prefixed-only
 * comparison would notice.
 */
const PATHS = [
    // Phase A of the laminas-mvc removal, 2026-09-08: the pre-April-2020 identifier
    // redirect, the last anonymous-reachable route the bridge served. An eight-character
    // old id 301s to the record's current URL. Anonymous only (guest+user), and a redirect
    // — it renders no layout, so what the compare checks is the Location, which the tool
    // normalizes and diffs like any other. `SL10001A` is association 1 (old start 10000).
    '/SL10001A',
    // batch 17 — `admin/import-father`, the last HTML page laminas served. One GET, signed
    // in as `sch_administrator`; anonymously it is the sign-in redirect. The select's
    // option list is a Patres API call made by the form factory, and the capsule's key is a
    // dummy, so **both** captures render an empty picker — the diff proves the page, not
    // the list. Nothing here writes: the import is a POST, and this tool never posts.
    '/admin/import-father',
    // batch 16 — the last four publication actions: the two whole-corpus listings and the
    // two "make a new row from this one" confirmations.
    //
    // **All four are safe GETs on both front controllers now, and one of them was not until
    // this batch.** `/…/create-new-edition` created the new edition on the GET under
    // laminas — the same write-on-GET the copy action had until 2026-08-14 — so listing it
    // here before the laminas action was changed would have created twelve publications
    // per capture. The action confirms now (module/Books, same batch), which is what makes
    // the path listable; its `.phtml` was an empty file until then, so this is the first
    // laminas rendering of that page as well as the first Symfony one.
    //
    // SL201727L is data-sourced and unmerged (CopyToMainCorpusSmokeTest's fixture), which
    // is the precondition the copy confirmation checks; a publication without a data source
    // answers a flash and a redirect instead, and a flash would decorate the next path.
    // SL202186L is an ordinary first-class publication for the new-edition confirmation.
    //
    // `/literature/export` is the whole corpus as one table — 10,166 rows, the largest
    // response in this list by some distance — and `prime-authors` is `pub_administrator`
    // only, so its anonymous half is a redirect and its signed-in half is the report.
    '/literature/export',
    '/literature/prime-authors',
    '/SL201727L/copy-to-main-corpus',
    '/SL202186L/create-new-edition',
    // batch 14 — the translation administration surface: the three reachable routes of the
    // JTranslate GUI. `jtranslate/phrase` is their parent and is `may_terminate => false`,
    // so there is no fourth path to fetch.
    //
    // **GET only, like batches 7 and 8**, and it matters more here than on either of them:
    // a POST to the delete confirmation destroys every translation of the phrase in every
    // language *and* rewrites the compiled catalogs, so the site stops serving those
    // strings. The harness only ever POSTs the sign-in form.
    //
    // The listing is fetched in both of its states because the filter is the page's whole
    // feature: without `showAll` it renders only phrases some locale is still missing —
    // computed row by row in the .phtml, which is one of the things this port moves — and
    // with it, all 3,331 phrases of this project. `project` is why that number is not 4,237:
    // the phrase table is shared, and the 875 `patres` rows must never appear on either
    // rendering.
    //
    // Three phrase ids, each for a branch rather than for coverage's sake:
    //   10028  has real `trans_translations_history` rows, so the edit screen renders the
    //          "Previous versions" panel — the only place a replaced translation is
    //          recoverable from.
    //   6192   has five translations and no history at all, so the same screen renders
    //          without the panel. Both, because a panel rendered unconditionally passes any
    //          test that only looks for it.
    //   99999  names nothing. Both actions answer it with a flash and a 302 to the listing,
    //          which is the branch a stale link or a double submit takes. Within the
    //          laminas route's `[0-9]{1,5}` on purpose — see the constraint note in
    //          module/JTranslate/config/symfony-routes.php.
    // **Compared on 2026-09-08 and then removed from this list**, for the reason the JUser
    // paths below are gone: the port deleted `JTranslateController` and its three `.phtml`,
    // so there is no laminas rendering left to diff against. The result is recorded here
    // because the list is where the next porter will look for it:
    //
    //   - the two `edit` renderings (a phrase with history, one without) and both `delete`
    //     confirmations came out **identical** across all five locales and both identities,
    //     as did the two not-found redirects;
    //   - the listing came out identical on all **3,287** rows the two captures had in
    //     common, in both its filtered and its `showAll` state. The Symfony capture had 25
    //     rows the laminas one did not, all of them phrases *discovered while the captures
    //     ran* — new ids above 14,434 plus one unretired by a page view. Nothing was in the
    //     laminas listing and missing from the Symfony one;
    //   - two differences were found and fixed rather than accepted, both whitespace either
    //     side of a `&nbsp;`: it decodes to U+00A0, which rule 6 below deliberately does not
    //     collapse, so a newline next to one renders as a space laminas does not emit.
    //
    //   - one difference is **not** the port's and is worth knowing before reading a future
    //     diff: an anonymous request to a guarded ported route redirects to
    //     `?redirect=/en/admin/translations%3FshowAll%3Dtrue`, where laminas drops the query
    //     string entirely. That is `App\Authorization\RouteGuard` versus BjyAuthorize's
    //     strategy, it applies to every ported guarded route, and the Symfony behaviour is
    //     the better one — `App\JUser\Host\RouteResolver` keeps the query when it resolves
    //     the destination, so signing in returns the visitor to the filter they asked for.
    // batch 11b — the library circulation surface: 24 routes across five controllers.
    //
    // **Two of the batch's routes are deliberately absent, and both would do real work if
    // fetched.** `/libraries/{id}/refresh-sort` issues one UPDATE per book on a bare GET —
    // 16,383 of them for library 4 — which this harness would trigger twelve times per
    // capture; and `/libraries/{id}/send-book-notices` hands a real library to the mailer.
    // Neither belongs in a tool whose contract is "GET everything twice". They are covered
    // by test/Smoke instead, against a library small enough to assert on.
    //
    // Library 1 (Bellavista, 4,604 books) is the subject wherever a library id is needed.
    //
    // **The five `/library-imports/…` paths this batch carried are gone from the list, and
    // will not come back.** They were removed on 2026-08-18 with
    // `Books\Controller\LibraryImportsController` itself: that tree is Symfony-only now,
    // its laminas routes exist solely so `laminas_path()` and the guards can name them, and
    // a laminas capture of any of them dispatches to nothing. A path with no laminas side
    // cannot be diffed against one, so leaving them here would turn every future capture
    // into five failures that mean "this was ported hard" rather than "this drifted".
    // They are covered by test/Smoke/LibraryImportSmokeTest instead.
    '/books/18370',
    '/borrowers/514',
    '/checkouts/library/1',
    '/checkouts/library/1/current',
    '/checkouts/library/1/overdue',
    '/libraries/1',
    '/libraries/1/admin',
    '/libraries/1/batch-operations',
    '/libraries/1/book-list',
    '/libraries/1/book-list-json',
    '/libraries/1/checkin',
    '/libraries/1/checkout',
    '/libraries/1/collections',
    '/libraries/1/data-problems',
    '/libraries/1/inactivate-books',
    '/libraries/1/label-management',
    '/libraries/1/mass-checkout',
    '/libraries/1/sort-debugging',
    // batch 8 — the delete surface: the seven entity delete confirmations that share
    // SionModel\Controller\SionController::deleteAction().
    //
    // **GET only, and here that is a stronger claim than it was for batch 7.** These URLs
    // delete records when they are POSTed to; the harness only ever POSTs the sign-in form
    // (see httpRequest below), so every fetch here renders a confirmation and destroys
    // nothing. The destructive half is tested in test/Smoke, against fixtures, never here.
    //
    // Twelve routes contain `delete` and only these seven are in the batch. The other five
    // were unreachable rather than skipped: `event-delete`, `libraries/library/delete` and
    // `sion-model/delete-entity` had no guard entry at all, and the Route guard is
    // default-deny, so all three answered **403 to an account holding every role** —
    // measured 2026-08-14, not inferred. `juser/user/delete` and `jtranslate/phrase/delete`
    // have their own controllers and forms.
    //
    // Two of those three moved on 2026-09-08 and **neither becomes a subject here**.
    // `event-delete` was deleted outright. `libraries/library/delete` was built as a
    // Symfony page — and it has no laminas rendering to compare against, having never had
    // one, so there is nothing for this harness to diff. It is covered by
    // test/Smoke/LibraryDeleteSmokeTest instead, against fixtures it creates itself.
    //
    // Ids are deliberately the same records batch 7 edits, so one row covers a show page,
    // an edit form and a delete confirmation.
    '/SL100319A/delete',
    '/SL202186L/delete',
    '/SL400003T/delete',
    '/SL500001C/delete',
    '/persons/494/delete',
    '/assignments/77/delete',
    '/roles/255/delete',
    //The not-found branch of two of them, which is `existsEntity()` answering false and
    //`redirectAfterDelete()` sending the visitor to the entity index with a flash. Role 1
    //is the same row that makes `/roles/1/edit` a not-found: it hangs off association 2,
    //which the projection filters. SL499999T names nothing at all.
    //
    //`/SL499999T/delete` is here because it is the path the `text` spec's redirect fix
    //repaired. Its `delete_action_redirect_route` was `text-delete` — the delete route
    //itself, which needs an `sw_id` — so assembling it threw and *every* exit from this
    //action for a text was a 500, the successful one included, after the row was gone.
    '/roles/1/delete',
    '/SL499999T/delete',
    // batch 7 — the edit surface: the ten entity edit forms that share
    // SionModel\Controller\SionController::editAction().
    //
    // **Every one of these is fetched by GET only**, which is what makes them safe to
    // put in this list at all: the capture harness never POSTs anything but the sign-in
    // form (see httpRequest below), so no row is written and no `sch_changes` entry is
    // filed. A batch of *edit* paths is the first time that property has mattered, and
    // it is a property of the harness rather than of these paths — a future POST
    // capture needs its own answer for the writes.
    //
    // Anonymously every one of them is a 302 to the sign-in page, so the signed-in
    // half of each pair is the only one that compares a rendered form. That is the
    // reverse of batch 5, where most paths rendered for everyone.
    //
    // The three `/{sw_id}/edit` paths are the interesting ordering case: they share one
    // path shape with the already-ported `association-edit` and are told apart by the
    // identifier regex alone, exactly as the four show pages are.
    '/SL400003T/edit',
    '/SL500001C/edit',
    '/SL202186L/edit',
    //Person 494 and assignment 77 are the same rows batch 6 compares on their show
    //pages, so one id covers a show page and its edit form.
    '/persons/494/edit',
    '/assignments/77/edit',
    //**Both role ids on purpose.** `getRole()` answers null for 142 of the 1,468 rows in
    //`sch_roles` — role 1 hangs off association 2, which the projection filters — so
    ///roles/1/edit is the *not-found* branch: a 302 to /en/roles with a flash, even for
    //an account holding every role. Measured, not assumed. Role 255 (a Sisters of Mary
    //role on the general presidium) renders the form. One of them alone would compare
    //only half of editAction()'s opening.
    '/roles/1/edit',
    '/roles/255/edit',
    //The four Books entities. `library`, `book` and `collection` all declare
    //`acl_resource_id_field => resourceId` with `acl_edit_permission => administrate`,
    //so each carries a *per-row* check against the dynamic `library_<id>` resource
    //Books\Model\LibraryTable contributes — the same shape as `publication`'s per-row
    //show gating that App\Sion\EntityShow already reproduces.
    '/libraries/1/edit',
    '/books/18370/edit',
    '/collections/1/edit',
    '/dictionary/1/edit',
    //The already-ported edit form, re-compared because this batch folds it onto the
    //shared reproduction and the whole point of that refactor is that its rendering
    //does not move.
    '/SL100319A/edit',
    // batch 6 — the contact/search surface. Every one of these is a GET whose input
    // is the query string, so each is fetched **twice**: once empty and once with a
    // term that matches rows in the capsule dump. The empty form is not the boring
    // half — three of the four controllers only run their query when `isValid()`
    // passes on the submitted data, so the empty fetch is the one that exercises the
    // "no results yet" branch and the flash message that goes with it.
    //
    // `/assignments/search` is first because it is the destination of the navbar
    // search box on every page of the site (App\View\SiteChrome::searchBox), so it is
    // the highest-traffic path in this batch by a wide margin.
    '/assignments/search',
    '/assignments/search?search=Walter',
    '/assignments/advanced-search',
    //Assignment 77 hangs off role 259 of association 71 — the general presidium — so
    //this row is also one of the rows /movement renders. One id therefore compares the
    //show page and a table cell of another page in this batch.
    '/assignments/77',
    '/persons',
    '/persons/search?search=Walter',
    '/persons/494',
    '/movement',
    '/texts',
    '/texts?search=Bund',
    // batch 5 — the reading surface: the four entity show pages, the literature
    // browse pages and the three sendToNewUrl redirects.
    //
    // Two associations on purpose. AssociationsController::showAction() redirects an
    // anonymous visitor to `welcome` for every association whose kind is not
    // `sch-shrine` or `sch-wayside-shrine` — a rule that lives in the controller and
    // not in the ACL, so no guard entry hints at it. SL100319A is a shrine and renders
    // for everyone; SL100001A is the Secular Institute and renders only for the
    // signed-in identity. One of them alone would compare only half the branch.
    '/SL100319A',
    '/SL100001A',
    // A composition and a text: both carry a comment predicate, so both render the
    // comment list and the CommentForm. The text is the batch's restricted show page
    // (`texts_user`), i.e. a 302 anonymously and a full page signed in.
    '/SL500001C',
    '/SL400003T',
    // Two publications. 2186 has a `comment-reviews-publication` row — the third
    // comment predicate, and the only one whose kind is not `comment`. 417 carries
    // MergedIntoPublicationId, which is the 301-to-the-surviving-edition branch that
    // fires only for a visitor without `publication_user`.
    '/SL202186L',
    '/SL200417L',
    // Neither of the two above has a related edition, so nothing in this set touched
    // `linkPublication()`'s output until 2026-08-23 — two bugs in it shipped past a clean
    // 1,272-of-1,272. These two cover it in both directions, and it takes two because on
    // any one publication the fixes can cancel:
    //   1501 — all three of its sub-editions carry a `DataSource`, so parenthesising the
    //     OR group empties "Other editions" entirely. Its one sub-edition with a library
    //     copy is a data-source row too, so the availability panel does not move.
    //   1928 — ten copies across sub-editions with no `DataSource`, so "Physical copy
    //     availability" goes from 2 rows to 12 once the sub-edition *ids*, rather than the
    //     rows themselves, reach `searchBooks()`.
    '/SL201501L',
    '/SL201928L',
    // The literature browse surface. /literature/de is the largest page on the site
    // (2,889 rows, ~1.0 MB of HTML) and the one that pays the 192 MiB
    // `getObjects('publication')` hydration; /literature/es is a tenth of it, and the
    // pair is what would catch a projection that only works at one size.
    '/literature',
    '/literature/de',
    '/literature/es',
    '/literature/search',
    // The three sendToNewUrl redirects, all 301s to the sw_id form.
    '/literature/1',
    '/associations/SL100001A',
    '/associations/1',
    // batch 4 — the public browse surface
    '/music',
    '/timeline',
    '/dictionary',
    '/dictionary/es',
    '/dictionary/pt',
    '/literature/150-preguntas-sobre-schoenstatt',
    // batch 4 — restricted indexes
    '/associations',
    '/roles',
    '/libraries',
    // batch 9 — the create surface. Nine of the sixteen `*/create` routes; the seven left
    // out are named in config/symfony/routes.php, and two of those were left out because
    // they were broken on laminas rather than because they were hard. Batch 10 fixed those
    // two and ported them; they are at the end of this block.
    //
    // These are the first paths here that render a form with **no record behind it**, which
    // is worth stating because it changes what a difference means: an edit page differing
    // could be the row, and a create page differing can only be the page.
    '/associations/create',
    '/assignments/create',
    '/roles/create',
    '/books/create/1',
    '/collections/create/1',
    '/libraries/create',
    '/music/create-composition',
    '/literature/create',
    '/dictionary/create',
    // The prefills, which are query-string branches no other path here exercises. Each one
    // is the link a moderator actually follows — from a role to add an assignment, from a
    // book to copy it, from a shrine list to add one in the same country.
    '/associations/create?country=CL&kind=shrine',
    '/assignments/create?roleId=1&personId=494',
    '/books/create/1?copyBook=18370',
    // batch 10 — the two the create surface left behind.
    //
    // `/texts/create` differs only by the inline-script wrapper every ported page differs by.
    // `/persons/create` differs by the same two things its already-ported edit twin does —
    // the `nameDay` selects rendered as hidden inputs, and the four patres date fields that
    // no laminas partial renders at all — and there **the Symfony side is the correct one**:
    // without those hidden inputs a save writes NULL to two NOT NULL columns. Both are
    // recorded in docs/laminas-exit.md's known-differences table.
    '/persons/create',
    '/texts/create',

    // batch 12 — the JUser user-administration surface: seven routes, all guarded
    // `administrator`, all of them `JUser\Controller\UsersController`'s own actions rather
    // than any shared SionModel one.
    //
    // **The index is the reason EMAIL_ACCOUNT above is a constant and not a `time()`.** It
    // renders a row per account with the account's `user_id` in two links, so a per-run
    // throwaway account is a row the second capture has and the first does not.
    //
    // Two ids, deliberately: user 5 carries a `PersID` and user 6 does not, which is the
    // branch `formatPerson` sits behind in the index and the branch that decides whether
    // the edit form renders its `personId` select at all.
    //
    // `/users/5/delete` is a **GET** here, and a GET on that route renders a confirmation
    // page rather than deleting anything — the POST branch is what deletes, and this tool
    // never posts. `/users/5/api-tokens/1/revoke` is the same shape: a non-POST returns a
    // redirect before it reads the token id, which is why naming a token that does not
    // exist is safe.
    //
    // The not-found branches (`/users/9999999/edit` and friends) are **not** here, for the
    // reason `/dictionary/xx` is last in this list: each sets a flash message, and a flash
    // is read by the next page rendered in the same session. They are covered by
    // test/Smoke/JUserAdminSmokeTest instead.
    //
    // **The eight paths are gone from this list, and will not come back** (2026-09-08).
    // Batch 12 deleted the laminas controller that served them along with its `.phtml`, so
    // `capture laminas` answers **404 in 124 bytes** for every one — measured, not
    // assumed — and a path with no laminas side cannot be diffed against one. Leaving them
    // here turned every run into forty phantom differences that read as "this drifted"
    // when they mean "this was ported hard". Same reasoning, and same wording, as the
    // `/library-imports/…` removal above.
    //
    // What covers them instead: test/Smoke/JUserAdminSmokeTest, UserSmokeTest,
    // UserCreateSmokeTest and ApiTokenAdminSmokeTest.

    // The auth surface — batch 13's subject, listed here ahead of it so its capture has a
    // before as well as an after. **Two of the four routes cannot be here, and neither is
    // an oversight:**
    //
    //   - `/user/verify` needs a live single-use token, which this harness has no way to
    //     mint and would burn on the first of its twelve fetches;
    //   - `/user/logout` would destroy the signed-in session *mid-capture*, so every path
    //     listed after it would be captured anonymously and the diff would read as a
    //     hundred authorization regressions.
    //
    // Both are characterized end to end by test/Smoke/AuthSmokeTest, which mints real
    // tokens through Mailpit and owns its own session — the same division of labour the
    // library `refresh-sort` and `send-book-notices` routes have above.
    //
    // What is left is worth having: `/user/login` is the form itself, and its rendering is
    // where a locale bug would show, while `/user` is the redirect that decides where a
    // signed-in visitor asking for the sign-in page ends up. Both are idempotent GETs.
    //
    // Note the signed-in half of these two is a redirect, not a page. That is the
    // behaviour under test, not a gap in the capture.
    //
    // **Both are gone from this list too** (2026-09-08), for the reason the eight above
    // are: batch 13 deleted `JUser\Controller\LoginController`, so there is no laminas
    // rendering left to compare against. This one cost more than a phantom diff — it broke
    // the tool outright, because `signInWithEveryRole()` POSTs `/en/user/login` and a 404
    // there kills `capture laminas` in setup. See {@see storeSession()}.
    //
    // test/Smoke/AuthSmokeTest is what covers this surface, end to end and with real
    // tokens.

    // earlier batches, re-compared because every port re-enters the same layout,
    // the same translator and the same authorization listener
    '/',
    '/shrines',
    '/wayside-shrines',
    '/developers',
    '/acknowledgements',
    '/privacy',
    '/shrines/submitting-photos',
    '/admin',
    '/sm/data-problems',
    '/sm/view-changes',
    //Edge cases, and **last on purpose**: an unknown dictionary language is a branch a
    //ported controller has to reproduce. Its laminas twin sets a flash message, and a
    //flash is read and cleared by the *next* page rendered with the same session — so
    //anywhere but last, it decorates an unrelated page with a message on one front
    //controller and not the other. Diagnosed after exactly that.
    '/dictionary/xx',
];

exit(main($argv));

/** @param list<string> $argv */
function main(array $argv): int
{
    $mode = $argv[1] ?? '';

    if ($mode === 'session') {
        return storeSession();
    }

    if ($mode === 'capture') {
        $name = $argv[2] ?? '';
        if ($name === '' || ! preg_match('/^[a-z0-9-]+$/', $name)) {
            fwrite(STDERR, "capture needs a name, e.g. `capture laminas`\n");
            return 2;
        }
        if ($name === SESSION_NAME) {
            fwrite(STDERR, "`" . SESSION_NAME . "` is reserved for the stored sign-in; pick another name\n");
            return 2;
        }
        return capture($name);
    }

    if ($mode === 'compare') {
        $left  = $argv[2] ?? '';
        $right = $argv[3] ?? '';
        if ($left === '' || $right === '') {
            fwrite(STDERR, "compare needs two capture names, e.g. `compare laminas symfony`\n");
            return 2;
        }
        return compareCaptures($left, $right);
    }

    if ($mode === 'show') {
        return show($argv[2] ?? '', $argv[3] ?? '');
    }

    fwrite(
        STDERR,
        "Usage: php tools/port-baseline.php session | capture <name> | compare <name> <name> "
        . "| show <name> <file>\n"
    );
    return 2;
}

// ------------------------------------------------------------------- session

/**
 * Sign in once and keep the cookie jar, so a later capture does not have to sign in itself.
 *
 * ## Why this exists: the laminas front controller can no longer sign anybody in
 *
 * Every capture needs an authenticated identity — the guarded pages are the ones worth
 * comparing — and until 2026-08-21 each one got its own by POSTing `/en/user/login`.
 * That stopped working the day `JUser\Controller\LoginController` was deleted: the whole
 * sign-in surface is served by the module's Symfony controllers now, so under
 * `SYMFONY_KERNEL=0` the login form is a **404** and `capture laminas` fails in setup
 * before it fetches anything.
 *
 * Nothing noticed for two and a half weeks because no port ran in between. The tool's
 * documented procedure was simply broken, which is worth stating plainly: a verification
 * tool that cannot run is indistinguishable from one that passes.
 *
 * ## Why carrying the session across the flip is legitimate
 *
 * The session is **laminas'** on both front controllers — one `Laminas\Session`
 * container, one cookie, and only the user id inside it, re-read every request. That is
 * exactly what `Application\Session\SessionBootstrap` exists to guarantee for
 * `SYMFONY_KERNEL=0`. So a session opened under Symfony is a session laminas resolves,
 * and reusing it changes nothing about the *rendering* being compared: the identity is
 * the same account with the same roles either way.
 *
 * It also removes a real hazard the per-capture sign-in had — two runs, two redemptions,
 * two `sch_changes` rows — without touching the fixed-local-part reasoning above.
 *
 * ## The procedure, in full
 *
 *     # 1. with the site on its default front controller (Symfony)
 *     docker compose exec -T app php tools/port-baseline.php session
 *     # 2. append `SetEnv SYMFONY_KERNEL 0` to public/.htaccess
 *     docker compose exec -T app php tools/port-baseline.php capture laminas
 *     # 3. remove that line again, then port the route
 *     docker compose exec -T app php tools/port-baseline.php capture symfony
 *     docker compose exec -T app php tools/port-baseline.php compare laminas symfony
 *
 * Step 1 is skippable only in the sense that `capture` falls back to signing in itself
 * when no session is stored — which works on Symfony and cannot work on laminas.
 */
function storeSession(): int
{
    $dir = OUT_ROOT . '/' . SESSION_NAME;
    resetDir($dir);

    $jar = $dir . '/JAR';
    file_put_contents($jar, netscapeJarWithConsent());

    try {
        $account = signInWithEveryRole($jar);
        assertSignedIn($jar);
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'SIGN-IN FAILED: ' . $e->getMessage() . "\n");
        return 2;
    }

    file_put_contents($dir . '/ACCOUNT', $account . "\n");

    printf("Signed in as %s; session stored in data/port-baseline/%s/\n", $account, SESSION_NAME);
    printf("Flip the front controller now — the next capture will reuse this session.\n");

    return 0;
}

/**
 * The stored jar copied to a scratch file, and the account it belongs to — or null when
 * nothing is stored.
 *
 * A copy rather than the file itself: a capture drops `slm_locale` from its jar between
 * fetches (see the comment in {@see capture()}), and curl rewrites the whole file on every
 * response. Handing it the stored one would leave the session store rewritten by whatever
 * the last request happened to set, which is a thing that works until it does not.
 *
 * @return array{jar: string, account: string}|null
 */
function storedSession(string $scratchJar): ?array
{
    $dir     = OUT_ROOT . '/' . SESSION_NAME;
    $jar     = $dir . '/JAR';
    $account = @file_get_contents($dir . '/ACCOUNT');
    if (! is_file($jar) || false === $account) {
        return null;
    }

    copy($jar, $scratchJar);

    return ['jar' => $scratchJar, 'account' => trim($account)];
}

/**
 * Fail now rather than after a hundred fetches, if the identity is not actually there.
 *
 * A stale or unresolvable session does not error: every guarded URL answers a 302 to the
 * sign-in page, so the capture *succeeds* and produces a directory of redirects. Compared
 * against another such directory it even passes. This is the check that tells the two
 * apart, and it is the reason a stored session is safe to reuse across a front-controller
 * flip: if laminas cannot resolve what Symfony opened, the very next line says so.
 */
function assertSignedIn(string $jar): void
{
    $response = httpGet(SESSION_PROBE, $jar);
    if (200 !== $response['status']) {
        throw new RuntimeException(sprintf(
            'the session does not carry an identity: GET %s answered %d, expected 200'
            . ' (a guarded page redirects an anonymous visitor)',
            SESSION_PROBE,
            $response['status']
        ));
    }
}

// ------------------------------------------------------------------- capture

function capture(string $name): int
{
    $anonymousJar = tempnam(sys_get_temp_dir(), 'port-baseline-anon-');
    $signedInJar  = tempnam(sys_get_temp_dir(), 'port-baseline-auth-');
    file_put_contents($anonymousJar, netscapeJarWithConsent());
    file_put_contents($signedInJar, netscapeJarWithConsent());

    //A stored session is used when there is one, and on laminas it is the only thing that
    //can work — see storeSession() on why the login form is a 404 there. The fallback
    //keeps a Symfony-only capture a one-liner.
    $stored = storedSession($signedInJar);
    try {
        $account = $stored['account'] ?? signInWithEveryRole($signedInJar);
        assertSignedIn($signedInJar);
    } catch (RuntimeException $e) {
        fwrite(STDERR, 'SETUP FAILED: ' . $e->getMessage() . "\n");
        @unlink($anonymousJar);
        @unlink($signedInJar);
        return 2;
    }

    $dir = OUT_ROOT . '/' . $name;
    resetDir($dir);
    //Normalization happens at *compare* time, not here, so a capture is the raw
    //response and a change to a rule below never costs another capture — which
    //matters because taking the laminas one means editing public/.htaccess. The one
    //thing compare() cannot recover on its own is which throwaway account this run
    //used, so it is recorded beside the responses.
    file_put_contents($dir . '/ACCOUNT', $account . "\n");

    $count = 0;
    foreach (urls() as $url) {
        foreach (['anonymous' => $anonymousJar, 'signed-in' => $signedInJar] as $identity => $jar) {
            //An unprefixed path is answered by a locale *negotiation*, and SlmLocale
            //negotiates from the slm_locale cookie first. That cookie is set by every
            //prefixed request, so without this the answer depends on which locale
            //happened to be fetched last — /music redirected to /it/music purely
            //because `it` came last in the loop above. Dropping the cookie first makes
            //the negotiation depend on the request alone, which is the thing the two
            //front controllers actually have to agree about. The session cookie stays,
            //so the signed-in identity survives.
            if (! isLocalePrefixed($url)) {
                dropLocaleCookie($jar);
            }
            $response = httpGet($url, $jar);
            file_put_contents(
                $dir . '/' . slugify($identity . $url) . '.txt',
                render($url, $identity, $response)
            );
            $count++;
        }
    }

    @unlink($anonymousJar);
    @unlink($signedInJar);

    printf(
        "Captured %d responses (%d paths x %d locale forms x 2 identities) to data/port-baseline/%s/\n",
        $count,
        count(PATHS),
        count(LOCALES) + 1,
        $name
    );
    printf(
        "Signed-in account: %s (%s)\n",
        $account,
        null === $stored ? 'signed in by this run' : 'reused the stored session'
    );

    return 0;
}

function isLocalePrefixed(string $url): bool
{
    return (bool) preg_match('#^/(' . implode('|', LOCALES) . ')(/|$)#', $url);
}

/** Remove slm_locale from a Netscape jar in place, leaving every other cookie. */
function dropLocaleCookie(string $jar): void
{
    $lines = file($jar, FILE_IGNORE_NEW_LINES) ?: [];
    $kept  = array_filter($lines, static fn (string $line): bool => ! str_contains($line, "\tslm_locale\t"));
    file_put_contents($jar, implode("\n", $kept) . "\n");
}

/** @return list<string> every path in every locale form, plus the bare form */
function urls(): array
{
    $urls = [];
    foreach (PATHS as $path) {
        $urls[] = $path;
        foreach (LOCALES as $locale) {
            //'/' is the one path whose prefixed form is '/en/' rather than '/en'
            $urls[] = '/' . $locale . ($path === '/' ? '/' : $path);
        }
    }
    return $urls;
}

/**
 * One captured response as a comparable document.
 *
 * The body is stored **raw**; normalization is compare()'s job. A non-200 body is
 * dropped here rather than at compare time because rule 1 is about what a 302 *is*,
 * not about how two of them are compared: laminas renders a whole sign-in page into
 * one and keeping 9 KB of it per locale per identity is 40 MB of noise on disk.
 *
 * @param array{status: int, redirect: string, body: string} $response
 */
function render(string $url, string $identity, array $response): string
{
    $header = sprintf(
        "URL: %s\nIDENTITY: %s\nSTATUS: %d\nLOCATION: %s\n",
        $url,
        $identity,
        $response['status'],
        $response['redirect']
    );

    if ($response['status'] !== 200) {
        return $header . "BODY: not compared (see rule 1 in tools/port-baseline.php)\n";
    }

    return $header . "\n" . $response['body'];
}

/** Erase what differs between two runs of the same front controller. */
function normalize(string $html, string $account): string
{
    // rule 2 — Twig escapes what the .phtml echoed raw
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // rule 3 — registerVisit() INSERTs on every request, so these climb by
    // themselves. The labels stay, so the counters vanishing still diffs.
    //
    // The list is per *label*, and therefore per locale, which is why the Italian pair is
    // here: a capture run visits each page ten times (five locales x two identities), so
    // any label this list misses reports a clean +10 on the second capture and lands in
    // the diff as a difference between the two front controllers. That is exactly what it
    // did — thirteen Italian pages, all "+10" — while the same pages in the other four
    // locales were silent, because only Italian has these two phrases translated.
    // Matching structurally instead was tried and rejected: the counters are two adjacent
    // `<strong>label</strong>: number` pairs, and so are ISBN/Edition/Number of pages
    // directly above them, so a structural rule would blank real bibliographic data.
    $html = preg_replace(
        '/(Total views|Views this month|Vistas totales|Visitas totales'
        . '|Visualizzazioni totali|Visualizzazioni questo mese)(<\/strong>)?:\s*\d+/u',
        '$1$2: {{VISITS}}',
        $html
    );

    // rule 4 — per-run by construction
    $html = preg_replace('/nonce="[^"]*"/', 'nonce="{{NONCE}}"', $html);
    $html = str_replace($account, '{{ACCOUNT}}', $html);
    $html = preg_replace('/' . preg_quote(EMAIL_PREFIX, '/') . '[0-9a-z]+/', '{{ACCOUNT}}', $html);
    // ...and the CSRF token, which is the same kind of value and was missing until the
    // comment form arrived on three ported pages. SionModel\Validator\Csrf mints
    // `<hash>-<salted hash>` per session per request, so two captures of one URL never
    // agree and every page carrying a form compared as drift no matter what it rendered.
    // Anonymous pages hid this: the comment form only renders for a signed-in visitor,
    // so it first showed as "the six signed-in captures of every commentable page differ".
    $html = preg_replace(
        '/(name="security"[^>]*value=")[0-9a-f]{32}-[0-9a-f]{32}/',
        '$1{{CSRF}}',
        $html
    );

    // rule 5 — the chooser picks a random country per language, on both sides. Its
    // whole markup goes below anyway; this also covers flags elsewhere on a page.
    $html = preg_replace('/flag-icon-[a-z]{2}\b/', 'flag-icon-{{FLAG}}', $html);

    // rule 7 — the fixed chrome differences. <title> is pulled out first because it is
    // the one part of <head> a page owns, and it is compared.
    preg_match('#<title>(.*?)</title>#s', $html, $title);
    $html = preg_replace('#<head>.*?</head>#s', '', $html) ?? $html;
    $html = preg_replace('#<div class="btn-group pull-right">.*?</ul>\s*</div>#s', '', $html) ?? $html;
    $html = preg_replace('#<script type=.application/ld\+json.>.*?</script>#s', '', $html) ?? $html;
    $html = str_replace('<body >', '<body>', $html);
    $html = str_replace(' aria-current="page"', '', $html);
    $html = 'TITLE: ' . ($title[1] ?? '') . "\n" . $html;

    // rule 8 — the serving note differs between the two renderings by design; see the
    // file docblock. Matched on the class rather than the text so that rewording the
    // note never silently un-strips it.
    $html = preg_replace('#<p class="[^"]*serving-note[^"]*">.*?</p>#s', '', $html) ?? $html;

    // rule 6 — the two layouts are not whitespace-identical and never were. Two steps:
    // collapse every run to one space, then drop the space *between two tags*
    // altogether, since `</div><h3>` and `</div> <h3>` are the same document and the
    // two layouts disagree about it constantly.
    //
    // Whitespace between *text* and a tag is deliberately kept. That is where a real
    // difference would show — a missing space before a link, a run-together sentence —
    // and collapsing it too would be the point at which this stopped checking anything.
    //
    // The character class is spelled out rather than written `\s` so that U+00A0
    // (`&nbsp;`, already decoded by rule 2) is *not* collapsed: it is real content on
    // the pages that use it, and /roles is built out of it.
    $html = preg_replace('/[ \t\r\n]+/', ' ', $html) ?? $html;

    return trim(preg_replace('/> </', '><', $html) ?? '');
}

// ------------------------------------------------------------------- compare

function compareCaptures(string $left, string $right): int
{
    $leftDir  = OUT_ROOT . '/' . $left;
    $rightDir = OUT_ROOT . '/' . $right;
    foreach ([$leftDir, $rightDir] as $dir) {
        if (! is_dir($dir)) {
            fwrite(STDERR, "no such capture: $dir\n");
            return 2;
        }
    }

    $files = array_map('basename', glob($leftDir . '/*.txt') ?: []);
    sort($files);
    if ([] === $files) {
        fwrite(STDERR, "capture $left is empty\n");
        return 2;
    }

    $leftAccount  = accountOf($leftDir);
    $rightAccount = accountOf($rightDir);

    $same = 0;
    $drift = [];
    foreach ($files as $file) {
        $a = file_get_contents($leftDir . '/' . $file);
        $b = @file_get_contents($rightDir . '/' . $file);
        if ($b === false) {
            $drift[$file] = 'missing from ' . $right;
            continue;
        }
        $a = comparable($a, $leftAccount);
        $b = comparable($b, $rightAccount);
        if ($a === $b) {
            $same++;
            continue;
        }
        $drift[$file] = sprintf('%d bytes vs %d bytes', strlen($a), strlen($b));
    }

    printf("%d of %d responses identical\n", $same, count($files));
    if ([] === $drift) {
        return 0;
    }

    printf("\n%d differ:\n", count($drift));
    foreach ($drift as $file => $why) {
        printf("  %-70s %s\n", $file, $why);
    }
    printf("\ndiff -u %s/<file> %s/<file>\n", $leftDir, $rightDir);
    printf("(raw captures; run `php tools/port-baseline.php show <name> <file>` for the normalized form)\n");

    return 1;
}

/**
 * One captured file reduced to what is actually compared: the header verbatim, and
 * the body normalized. Splitting on the blank line after the header is safe because
 * render() writes exactly one.
 */
function comparable(string $captured, string $account): string
{
    $parts = explode("\n\n", $captured, 2);
    if (! isset($parts[1])) {
        return $captured;
    }

    return $parts[0] . "\n\n" . normalize($parts[1], $account);
}

function accountOf(string $dir): string
{
    $account = @file_get_contents($dir . '/ACCOUNT');

    //an older capture has no ACCOUNT file; a name nothing can match is the safe
    //answer, since str_replace of '' would corrupt every byte of the document
    return false === $account ? '{{NO-ACCOUNT-RECORDED}}' : trim($account);
}

/** Print one captured response in its normalized form, for eyeballing a diff. */
function show(string $name, string $file): int
{
    $dir  = OUT_ROOT . '/' . $name;
    $path = $dir . '/' . basename($file);
    if (! is_file($path)) {
        fwrite(STDERR, "no such capture file: $path\n");
        return 2;
    }
    echo comparable((string) file_get_contents($path), accountOf($dir)), "\n";

    return 0;
}

// ------------------------------------------------------------------- sign-in

/**
 * A throwaway account holding every role in `user_role`, signed in over HTTP.
 *
 * Every role rather than a chosen few: this account exists to see the most
 * privileged rendering of every page at once, and picking roles per page would mean
 * maintaining a second copy of the ACL here. Roles are granted by INSERT before the
 * magic link is redeemed, which is convenience and not a requirement —
 * ZfcUserZendDbPlusSelfAsRole::getIdentityRoles() selects from user_role_linker on
 * every request and bjyauthorize.cache_enabled is false.
 *
 * @return string the account's address, needed by normalize()
 */
function signInWithEveryRole(string $jar): string
{
    $email = EMAIL_ACCOUNT . EMAIL_DOMAIN;

    $form = httpGet('/en/user/login', $jar);
    if ($form['status'] !== 200) {
        throw new RuntimeException("GET /en/user/login returned {$form['status']} — is the app up?");
    }
    if (! preg_match('/name="security"[^>]*value="([^"]+)"/', $form['body'], $m)) {
        throw new RuntimeException('no CSRF token on the login form');
    }

    $post = httpRequest('POST', '/en/user/login', $jar, [
        'email'    => $email,
        'redirect' => '',
        'security' => $m[1],
        'submit'   => 'Send me a sign-in link',
    ]);
    if ($post['status'] !== 200) {
        throw new RuntimeException("sign-in POST returned {$post['status']}");
    }

    $verifyPath = awaitVerifyPath($email);
    grantEveryRole($email);

    $verify = httpGet($verifyPath, $jar);
    if ($verify['status'] !== 302) {
        throw new RuntimeException("verify link returned {$verify['status']}, expected 302");
    }

    return $email;
}

function awaitVerifyPath(string $email): string
{
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $search = json_decode(
            rawHttp(MAILPIT_URL . '/api/v1/search?query=' . rawurlencode('to:' . $email)),
            true
        );
        if (! empty($search['messages'][0]['ID'])) {
            $message = json_decode(
                rawHttp(MAILPIT_URL . '/api/v1/message/' . rawurlencode($search['messages'][0]['ID'])),
                true
            );
            if (preg_match('#https?://\S+(/[a-z]{2}/user/verify\?token=[0-9a-f]+)#', $message['Text'] ?? '', $m)) {
                return $m[1];
            }
        }
        usleep(500000);
    }
    throw new RuntimeException("no sign-in mail arrived for $email");
}

function grantEveryRole(string $email): void
{
    $pdo = new PDO('mysql:host=db;dbname=ourlink_db1;charset=utf8mb4', 'schoenstatt', 'schoenstatt', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->prepare(
        'INSERT INTO user_role_linker (user_id, role_id, create_datetime)
         SELECT u.user_id, r.id, NOW() FROM user u JOIN user_role r
         WHERE u.email = :email
           AND NOT EXISTS (SELECT 1 FROM user_role_linker l WHERE l.user_id = u.user_id AND l.role_id = r.id)'
    )->execute(['email' => $email]);
    $pdo->prepare('UPDATE user SET state = 1 WHERE email = :email')->execute(['email' => $email]);
}

// ---------------------------------------------------------------------- http

/** @return array{status: int, redirect: string, body: string} */
function httpGet(string $url, string $jar): array
{
    return httpRequest('GET', $url, $jar);
}

/**
 * @param array<string, string>|null $post
 * @return array{status: int, redirect: string, body: string}
 */
function httpRequest(string $method, string $url, string $jar, ?array $post = null): array
{
    $ch = curl_init(BASE_URL . $url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 120,
        //Not decoration. ext/curl sends no User-Agent unless told to, and
        //SionTable::registerVisit() reads $_SERVER['HTTP_USER_AGENT'] unguarded — so a
        //capture without this one line renders every visit-registering page (the
        //dictionaries) with three PHP warnings printed above the doctype,
        //and the second and third of those are "Cannot modify header information",
        //i.e. the page also loses its Content-Security-Policy. That is a real
        //robustness bug in SionModel, not an artefact of this tool; the tool just
        //stops triggering it, because a capture is supposed to look like a browser.
        CURLOPT_USERAGENT      => 'schoenstatt-port-baseline',
    ]);
    if (null !== $post) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    //no curl_close(): a no-op since PHP 8.0 and deprecated in 8.5, which the
    //capsule runs. The handle is freed when $ch goes out of scope.
    $body     = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);

    if (false === $body) {
        throw new RuntimeException("request to $url failed");
    }

    return [
        'status'   => $status,
        //the host is the same on both captures, but strip it anyway so a capture
        //taken through a different base URL still compares
        'redirect' => str_replace(BASE_URL, '', $redirect),
        'body'     => $body,
    ];
}

function rawHttp(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $body = curl_exec($ch);

    return false === $body ? '' : $body;
}

/**
 * A cookie jar that has already accepted cookies.
 *
 * Without it both GDPR strategies call header_remove('Set-Cookie') and no session
 * can ever be established — the sign-in below would loop silently.
 */
function netscapeJarWithConsent(): string
{
    return "# Netscape HTTP Cookie File\n"
        . implode(
            "\t",
            //domain, include-subdomains, path, secure, expires, name, value
            ['localhost', 'FALSE', '/', 'FALSE', '2147483647', CONSENT_COOKIE, CONSENT_VALUE]
        ) . "\n";
}

// --------------------------------------------------------------------- files

function resetDir(string $dir): void
{
    if (is_dir($dir)) {
        foreach (glob($dir . '/*.txt') ?: [] as $file) {
            unlink($file);
        }
        return;
    }
    if (! mkdir($dir, 0o775, true) && ! is_dir($dir)) {
        throw new RuntimeException("could not create $dir");
    }
}

function slugify(string $url): string
{
    $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', $url) ?? '', '-');

    return '' === $slug ? 'root' : $slug;
}
