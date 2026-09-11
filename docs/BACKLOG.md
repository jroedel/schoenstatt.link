# Backlog

What is still open, and the rules that decide how to act on it. One bullet per item: what,
where, why, and the number that matters. Closed work is deleted, not annotated; git history
holds the narrative. `(verify)` marks an item not re-checked against the repo when compacted.

## Direction

- **The goal is a laminas-free application**: every `laminas/*` package removed, no
  exceptions, via a second strangler. Plan, order, per-step rules and open decisions are in
  [laminas-exit.md](laminas-exit.md); this file does not repeat them.
- **Removing laminas-mvc did not unlock servicemanager 4 or FrameworkBundle.** Nine of the
  ten components that capped servicemanager at 3.x are gone; `laminas-session` is the last,
  and our own direct `^3.24` line goes with the container. FrameworkBundle's own blocker is
  already gone: laminas-cache held `psr/cache` at `^1` and left with step 2.
- Progress metric: `composer show --locked | grep laminas` (37 on 2026-09-09, **9** on
  2026-09-11); also run `why-not php 8.5.0` and
  `why-not laminas/laminas-servicemanager 4.0.0` around each step.
- The parked ~102-factory Interop→Psr sweep stays parked; the factories leave with step 7.

## Now

- **konsoleH ticket, second half**: `apc.ttl` is still 0, so a failed APCu allocation
  expunges the whole segment instead of evicting (`apc.shm_size` 256M did land). One-line
  ask against `/home/httpd/php85-ini/ourlink/php.ini`; that path is per PHP version, so a
  konsoleH version flip silently reverts every tuned value.
- **An admin-created account is never told it exists.** `/users/create` sends nothing; the
  person can still sign in by address and inherit the roles. Decide rather than leave it.
- **17,207 active books can never get a sort text**; what they sort by is a librarian's
  call. `getSortTextFilter()` needs both a `CallNumberRegex` and a `SortTextFormat`. Stuck:
  PUC 16,383 (nothing configured), Bellavista 514 (a format but no regex, so it never
  fired), Austin Fathers 183 (nothing), Austin University Men 126 (next item), Colegio Mayor 1.
- **Library 7's `SortTextFormat` `%1{author}{title}` does not parse** (three printf
  parameters, one capture group). Reported by the diagnostic; `refresh-sort` on library 7
  still throws when POSTed until the row is corrected.
- **`PhrasesApiV3SmokeTest::testAnOverwriteLeavesTheTextItDestroyedInTheHistory` failed
  once, undiagnosed.** Not a timestamp race (history orders by autoincrement); it PATCHes a
  fixed phrase id other tests also write. It is what justifies letting the API overwrite.
- **The PUC library has not been deleted.** `/libraries/4/delete` was built for it (16,383
  books, inactive, zero `sch_changes` rows) but it is irreversible and an operational act
  for the catalogue's owner; `lib_administrator`, name typed exactly.
- **Italian shrine names render in English**: phrase `Schoenstatt Shrine` (`Schoenstatt`
  domain, duplicate rows 6526 and 7458) has no `it_IT`. Data fix; sweep the other `it_IT`
  gaps too. `BreadcrumbDataLabelsSmokeTest` currently expects the English rendering.
- **Phrase 7469 is blank with a blank `en_US` translation** (`trans_translations` 12876);
  renders as nothing and counts as done. One `DELETE` via `jtranslate:retire` or the API.
- **`error_log()` writes nothing in the capsule** (`log_errors` Off). Five sites remain
  (`src/Controller/LibraryDeleteController.php`, `src/Controller/Api/PhrasesV3Controller.php`,
  `src/Kernel.php`, `src/Twig/ForgivingCache.php`, `JTranslate\Model\TranslationsTable`). Turn
  `log_errors` on in `docker/php-limits.ini` and/or move them to `Psr\Log\LoggerInterface`.
- **`SionModel\Filter\ToBit`'s `null_defaults_to` option has never done anything.** The
  constructor takes no argument; 32 specifications pass the option. Recorded as-is in
  `test/Rules/rule-surface.php` rather than fixed, because making it work changes what
  those 32 fields store. Decide what the columns should hold, then change both together.
- **A `messageTemplates` option replaces the message set rather than merging into it**, so
  the two `Regex` specifications that pass one delete `regexInvalid` and let arrays and
  booleans through those fields silently. Reproduced exactly in
  `SionModel\Validator\AbstractValidator::setMessageTemplates()` because it was laminas'
  behaviour and the recordings pin it; it is still a hole.
- **The cookie explainer's paragraph is untranslated**
  (`module/JUser/templates/sign-in-no-cookies.html.twig`, raw English literal beside a
  translated `<h1>`). One `translate()`; the phrase is not yet in the table.
- **`RequiresApcu` and the `bjyauthorize.cache_*` config outlived their reason (verify).**
  Both describe a `BjyAuthorize\Cache` adapter that failed container construction without
  APCu; bjy-authorize is gone and `App\Acl\Authorizer` assembles from plain arrays. Test
  with `php -d apc.enable_cli=0`: if the container builds, delete the trait, the inert
  `cache_*` block in `config/autoload/acl.global.php` and the three `use`s; else the
  no-APCu fallback is still owed.
- **26 integration test files skip on `is_readable('config/autoload/local.php')` as a proxy
  for "there is a database"**; one uses `test/Integration/RequiresDatabase`. Convert the
  rest; the proxy cannot just go (no `local.php`, no `db` key), and copying `local.php.dist`
  onto CI turns 14 skips into 5 errors. `tools/check-ci-skips.php --bare` pins the skipping
  classes against `test/known-ci-skips.txt`. A MariaDB service container is not the answer
  (no base schema in `database/`; tests assert against real rows); do not propose one.
- **Tag JUser 2.0.0 (verify).** The user's call. `1.0.0` is on the abandoned `1.0.x`
  branch, so `git describe` reads `0.1.0-…`; number forward, do not move a published tag.
- Announce passwordless sign-in to users if confused-user replies arrive.
- **Two consoles in principle.** `bin/console` builds the laminas container; `App\Kernel`
  contributes no commands yet. When it does, decide (likely `bin/console` registers both);
  step 0's `ContainerFactory` is the moment.

## Next

- **Import spreadsheets are never pruned**: `shared/data/import/` on the server, kept on
  purpose (an import row names its file), no budget, no expiry. 2 MB today; unbounded.
- **An import is not transactional.** A failure halfway leaves half applied. One
  transaction over 16,000 writes locks the whole library meanwhile; nobody has decided the
  trade-off. `sch_changes` holds every old value, so an undo is possible; nothing does it.
- **`Subtítulo` is not importable.** Four Colegio Mayor sheets carry it; `lib_books` has no
  subtitle column, `sch_publications` does. Add one or say why the literature record is home.
- **Library label printing** (`libraries/library/label-management`) is a mockup: five
  buttons `href=""`, count hardcoded to 3, `admin_pages` entry commented out. The template
  is the specification.
- **The borrower page's countdown is a JS syntax error and its target is hardcoded**
  (`templates/books/borrower.html.twig:14` emits `var timerText = $timerText;` unquoted;
  line 45 is `var redirect = "/libraries/3"`). A working version would send every borrower
  to Colegio Mayor. Decide what the page is for before repairing it.
- **Mass checkout offers the wrong people at four of six libraries**:
  `src/Books/CheckoutForms.php` uses `Schoenstatt\FathersValueOptions` unconditionally where
  single checkout honours `checkoutPersonListKind`; the Austin libraries lend to anyone.
  "Who may this library lend to" has no written answer; see [libraries.md](libraries.md).
- **`show-collections` is an offered library display with no template**
  (`LibraryTable::MAIN_SHOW_DISPLAY_VALUE_OPTIONS`); the page falls back to the default.
  Template it or drop it from the form.
- **SionModel's read methods are annotated `@return mixed[]` and return null** (`getObject`,
  `getObjects`, `queryObjects`, `getLibraryImport`, …), so honest null checks read as dead
  code at level 8; `App\Laminas\SionResult` funnels them meanwhile. Library patres uses.
- **`mailings` retention.** A post-send log that looks like a queue (`Status`/`Attempt`/
  `MaxAttempts`/`QueueUntil` vestigial, 117 rows all 2017-18, `OpenedOn` always null);
  re-enabling book notices writes full bodies into it again. Rule: server-generated content
  may be logged, recipient behaviour may not be observed, so open tracking goes regardless.
- **The comment form's open redirect**: `SionModel\Controller\CommentController::
  redirectAfterCreate()` follows hidden field `$data['redirect']` (a `@todo` says so) and
  `App\Controller\CommentCreateController` reproduces it; bounded by CSRF and the `user`
  guard. Define a valid target (path-absolute, same-origin) and change both copies.
- **Upload a book cover from the site.** Nothing works; start from nothing, Symfony-side.
  Display resolves covers by filename (`public/covers/<publicationId>-400px.jpg` in
  `App\Controller\PublicationController`, `-80px.jpg` in `LiteratureController`), so a
  `sch_files` row design must justify itself; a cover is five files (`<id>.jpg`, `-80px`,
  `-200px`, `-400px`, `-2000px`, from `public/cover-thumbnails.sh`), so uploads must resize.
- **Publications 10203 and 10185 each have a cover and a different pre-merge image**
  (`1437*`, `1672*`) in `shared/public/covers`. Someone must look and pick. Covers are
  gitignored and outside every deploy: an SSH task, not a commit.
- **`TitleNoAccents`, `SubtitleNoAccents`, `AuthorsNoAccents`, `EditorNoAccents` are dead
  columns** on `sch_publications` (two mapped in the spec, nothing consumes any). Drop them
  in a `ddl` migration with the `.deploy.local` credentials.
- **Three template permission checks name a resource that does not exist**
  (`AclGuardRouteDriftTest::KNOWN_DEAD_PERMISSION_CHECKS`): `route/publications/
  advanced-search` (`search-bar.phtml`; `LiteratureController::advancedSearchUrl()` catches
  the failure as "no"), `route/persons/person/edit-contact-info`, `…/edit-private-info`. An
  unknown resource answers false, so each is a button nobody sees. Name a real route or
  delete the check.
- ~~Retire the two dependency forks (`slm/locale`, `diablomedia/laminas-twb-bundle`)~~ —
  **done 2026-09-09**: step 0 removed both packages and both `repositories` entries. The
  only fork left is `nicolaswurtz/chordpro-php`.
- **Delete the dead `controller_services` / `sion_controllers` entity config** (20 keys
  across Books, Schoenstatt, JUser and SionModel, plus the two `@deprecated` properties on
  `SionModel\Entity\Entity` that hold them). `SionControllerFactory` read them and was
  deleted with the laminas controllers in 2026-09; nothing has read either since. Left in
  place when laminas-navigation went, because clearing them properly spans two submodules
  and that removal needed only one line of it.
- **Watch for date-format drift from the ICU downgrade** (8.4/8.5 builds ship ICU 72.1 vs
  8.3's 76.1; 27 `IntlDateFormatter` sites). A wrong non-English date is this, not our code.
- **Passkeys (WebAuthn)**: web-auth/webauthn-lib, a credential table, enrollment inside an
  authenticated session, magic link as fallback.
- **PHPStan: raise from level 0** (baseline 20 entries), but not before step 0: of level 1's
  234 new errors, 115 are controller plugins via `AbstractController::__call` and 82 are
  `isset()` on a variable that always exists; deleting the 21 laminas controllers removes
  them for free. New errors per level 1-9: 234, 673, 762, 1094, 1202, 2586, 2745, 2809,
  3647. Harvest findings with an ad-hoc `--level 1` meanwhile. Stale baseline entry:
  `LibraryOptions::$isPublicallyListed` is declared now.

## Bugs

- **`Entity::$actionRouteProperties` maps `create => 'createRoute'`, `touch => 'touchRoute'`**
  (`module/SionModel/src/Entity/Entity.php:428,432`), properties `Entity` lacks.
  `FormatEntity::isActionAllowed()` and `App\Laminas\EntityFormatter::isActionAllowed()`
  read the mapped name dynamically: undefined property, null route, permission check
  passes. No caller passes either action today. Real names are `createActionRedirectRoute`
  and `touchJsonRoute`; touch is gone, so delete both entries.
- **`tools/form-regression.php` leaks an account per run** (`form-regression-<time>@example.com`).
  One deterministic address fixes it; its docblock says throw it away when it stops earning
  rent, and fuzz is the form safety net, so deletion is the likelier answer.
- **Four selects refuse records that already exist**, pinned by count in
  `ConstrainedChoiceFieldsFitTheirDataTest::ACCEPTED_MISMATCHES`. `getEditionValueOptions()`
  filters `DataSource IS NULL` (4,203 of 10,166 offered): 479 books, 46 `mainPublicationId`
  and 165 `translatedFromPublicationId` point at excluded rows; nobody recorded why the
  filter exists, establish that first. `RoleForm::associationId` offers active associations
  and `sch_roles` has no foreign key: 145 roles name a nonexistent association (74 ids, a
  data repair), 4 sit under the two inactive ones (a question).
- **`selectize({create: true})` on fields the server constrains** lets a user type a value
  and then refuses it; the flag is the only statement of intent in the app. Sweep it;
  `test/Fuzz/open-ended-choice-fields.php` declares the genuinely open fields.
- **`phpstan-baseline.neon` hides call sites that cannot resolve at runtime.** Ask "is it
  reachable, from where" for each; never regenerate: `Books\Service\DriveGateway` (five
  undefined methods, undefined `$personInputFilter`; ask first whether anything reaches it),
  `Books\Model\MusicTable:125` `getAssociationSchemaV1()` (copy-paste of
  `getCompositionSchemaV1()`), `SionModel\View\Helper\Tooltip` calling `$this->escapeHtml()`
  (helpers use `$this->getView()->plugin()`), `SionController` undefined `$entity`,
  `LibraryOptions::getArrayCopy()` without `return`, duplicate array keys in `Coins.php`. A
  receiver-blind scan (every defined method name vs every `$var->name(` call) finds more.
- **Four patres date fields ride on every person save and nothing renders them**
  (`priestDate`, `priestDatePrecision`, `bishopDate`, `bishopDatePrecision` on the shared
  `Schoenstatt\Form\PersonForm`; `templates/schoenstatt/_person-fields.html.twig` round-trips
  them as hidden inputs). Proper fix: a validation group naming only the fields this app
  shows (`EditAssignmentForm::prepareforEdit()` is the precedent). **Never give the
  `*Precision` columns a default**: the same POST's `PriestDate => null` would then erase
  the ordination dates of the ten persons who have one.
- **The publication form fetches an author list no picker offers**:
  `templates/books/publication-edit.html.twig:97` says `authorPersons.concat(authorAssociations);`
  and discards the result, so no `IsAuthor` association has ever been offered. One-word fix,
  but decide first whether editors and translators should offer associations at all.
- **Deleting a record orphans everything that referenced it** on every delete route:
  `SionTable::deleteEntity()` is a single-row `DELETE` and the schema has no foreign keys
  (association 8 has 60 children; 1,468 roles name an association; 142 already dangle).
  Refuse, cascade or reparent is a product decision (`App\Books\LibraryDelete` cascades
  explicitly). `AssociationDeleteSmokeTest::testDeletingAParentLeavesItsChildrenPointingAtNothing`
  characterises today; update it deliberately.
- **The delete confirmation does not name the record**
  (`templates/sion-model/entity-delete.html.twig` renders `Delete <entity>`, so one confirms
  deleting "person"). The row is fetched; copy `EntityEditController::recordName()`.
- **Cancel on a standalone delete confirmation is inert** (`type="button"`: right inside the
  modals, nothing outside). Needs a cancel URL `DeleteEntityForm` cannot know: view variable.
- **A failed CSRF token on delete renders with 401** where 400/403 belongs (the not-found
  401 is dead, replaced by its 302). `App\Controller\EntityDeleteController` reproduces it
  and `DeleteSurfaceSymfonySmokeTest` asserts it; change SionModel's controller, the ported
  one and the assertion together.
- **`EntityEditController` flashes its form-error message** (line ~152) where laminas used
  `nowMessenger`, so a failed edit re-renders with no message and a later page carries it.
  `PersonsController::nowMessage()` is the bridge; add a smoke assertion for the re-render.
- **`assignments/assignment/edit` accepts a 6-digit id** (`config/symfony/routes.php:765`,
  `[0-9]{1,6}` vs laminas `{1,5}`), answering "not found" where a 404 belongs. One character.
- **`ucwords($entity) . ' not found.'` files one phrase per entity type** (~30):
  `src/Sion/EntityShow.php:246`, `src/Controller/SendToNewUrlController.php:186`,
  `Books\Controller\PublicationsController:131`, plus the "successfully created" twins. Fix:
  `JTranslate\I18n\TranslatableMessage` with a `'%s not found.'` template; the `%s` then
  needs its own translation (same decision as the next item). Spans the SionModel submodule.
- **Two navigation labels can never be translated**: `'German Schoenstatt Literature'` and
  `'German to Spanish Dictionary'` are concatenated in `Application\Module` from an
  always-English language name (`/it/literature/de`). A per-locale `%s` template means
  salting `publication-pages` in APCu, five copies of a 10,166-page branch. Undecided.
- **The shrine table's "Opening hours?" column tests `openingHoursJson`**
  (`templates/schoenstatt/_shrines-table.html.twig`); the projection has
  `openingHoursSpecificationJson`, so structured hours never count. Before renaming, decide
  whether the score should reward them: 71 shrines have the human field, 4 the JSON.
- **`App\Schoenstatt\ShrineIndex:78` divides by zero on an empty shrine list** (the total
  does not guard its denominator; the per-region figure does). Backs `/shrines` and
  `/wayside-shrines`; the wayside side (43 rows) is the likelier to reach zero.
- **`SionModel\Mailing\Mailer:209` passes three arguments to `Rand::getString()`** (the
  dropped third is ZF's `$strong` flag) and it is the magic-link token generator. Handle
  inside the laminas-math retirement (Deploy ops), never as a drive-by.
- Ad-hoc `phpstan --level 1` still lists "variable might not be defined" in `LibraryTable`,
  `PublicationsTable`, `FormatPublication`, `Telephone`: path-dependent nulls, not crashes.
- **`SionForm::setData()` calls `getInputFilterSpecification()`**
  (`module/SionModel/src/Form/SionForm.php:98`), which neither `SionForm` nor
  `Laminas\Form\Form` defines; only subclasses implementing the provider interface do.
- **`Books\Filter\SortText` mixes positional and sequential printf specifiers**: a trailing
  `%-3s` consumes argument 1, so `{author|%-3s}` renders the first capture group. Pinned by
  `SortTextFilterContractTest`; fixing re-sorts every affected library (decision + re-sort).
- **`LibraryTable::checkinBooks()` reads `$checkout` leaking from the previous `foreach`**
  (~1046-1079) when creating checkouts for books with none: wrong key, or undefined when no
  checkouts were open. Unfixed because it is a behaviour change.
- **Every read of `texts` is hardwired to `TextKind = 'jk-text'`**
  (`EventTextTable::getSelectPrototype()`:50), single-record reads included. Four rows are
  unreachable through any page: 2801-2803 (`blog`, e.g. "What is Schoenstatt Link?") and
  2804 (`other`); content question first. `getTextTagsOptions($kind)` stacks a second
  `TextKind` predicate, so its parameter is inert for `jk-text` and empty for anything else.
- **`EditPhraseForm`'s `PHRASE_MAX_LENGTH`/`TRANSLATION_MAX_LENGTH` are 2,000, docblocks
  claiming `varchar(2000)`**; both columns are `mediumtext`, so the form refuses exactly the
  long paragraphs the migration was for. A bound is still wanted; pick it, fix the docblocks.
- **`SchoenstattTable::getUnlinkedAssignments()` declares cache dependencies
  `['assignment', 'association']`**; honest list is `['assignment', 'role']`. Harmless
  over-invalidation, still wrong.

## Product decisions

- **`hreflang` is declared for all five locales whether a translation exists or not.** The
  set must match what the pages publish or reciprocity breaks; narrowing means narrowing
  both at once. See [sitemap.md](sitemap.md).
- **25 publications have no browse path.** `PageBuilder::publicationPages()` files
  languageless ones under key `''` and assembles `/en/literature/`, which 404s; nothing
  renders that node, so the consequence is 25 pages with zero inbound links. Only a "no
  language" branch on `publications/index` plus `searchPublications()` matching it delivers
  anything; 24 rows are `NULL` and one is `''`, so `In($field, [''])` matches one.
- **Any signed-in account can edit any association**: `association-edit` is guarded
  `['sch_moderator', 'sch_user']` (`module/Schoenstatt/config/module.config.php:1772`) and
  registration grants `sch_user`. Making moderation a granted role is one guard entry plus
  granting `sch_moderator`; the ACL baseline diff shows who loses it. Not unilateral. (This
  is why the v3 API has its own `sch_api_bot` role.)
- **Should agents create shrines?** v3 is PATCH-only by decision. Creation must settle an
  agent-created association's `kind`, `parentId` and roles; `createEntity()` has side
  effects (`createAssociatedRoles()`). `required_columns_for_creation` is `['name', 'kind']`.
  Revisit with real agent traffic.
- **Who curates the timeline?** No events moderator role exists (analogues `pub_moderator`,
  `texts_moderator`); gates Part 2 of [timeline-and-corpus.md](timeline-and-corpus.md). Do
  not re-add the deleted laminas event routes as a stepping stone.
- **Is the Problem architecture worth keeping here?** 35 problems in six kinds site-wide;
  only the per-library configuration checks are acted on, the person/association checks are
  lint, `autoFixProblems()` has no non-redundant implementation. Four surfaces over
  `SionModel\Service\ProblemService`, two providers, two `problem_specifications` blocks.
  patres uses the classes, so only this app's wiring is in question; decide with convergence.
- **Suggest and moderate** (readers propose corrections, moderators accept) is the
  collaborative half of the site's purpose and was never built. Trap for a real build:
  `SionForm::setInputFilterSpecification()` is a silent no-op for 18 of 21 subclasses (only
  `PersonForm` and `AssociationForm` read `$this->filterSpec` back), so fields added that way
  get no filters and no validators. Fix the base class or use a form of their own.
- **Data-derived authority** (an office holder edits what sits beneath the office) is
  wanted, not started. The schema supports it (`sch_associations.Parent` 397 of 498,
  `sch_roles` 1,468 offices, `sch_assignments` 227 current). It must be a per-request voter
  (`symfony/security-core`), never a static rule table. The display half is unfinished three
  layers deep: `connectEntityRolesAndAssignments('person', …)` commented out in
  `SchoenstattTable`, `FormatPersonAssignment` registered under no alias, its `formatScope`
  helper nonexistent; registering the helper alone would create a 500.
- **Four feature ideas**, unjudged: automatic repeat-translations across domains; classified
  data-completeness tracking; a new email verification system; user photo uploads.
  (Import transactionality, `Subtítulo`, delete dependants, `mailings`, sort-text libraries
  and the cover pairs are decisions too; filed above where their code is.)

## Config rot / small cleanups

- Sweep legacy `Zend\*` strings from config: one each in
  `module/Books/config/module.config.php`, `module/JUser/config/acl.global.php.dist`,
  `config/autoload/acl.global.php`.
- **`cs-check`'s exit status carries no signal** (425 errors, almost all `.phtml` swept in
  by `phpcs.xml`); the clean set is enforced via `tools/phpcs-clean-paths.txt`. Excluding
  `.phtml` or auto-fixing is undecided; step 0 deletes 116 dead `.phtml` first.
- Production's server-side `local.php` has a dead `acmailer_options` key; remove when a
  deploy next touches server config.
- **The capsule runs in production mode**: tracked `public/.htaccess` sets
  `APP_ENV=production` and `AllowOverride All` beats the vhost's `development`. Changing it
  changes the capsule's error pages; measured, not decided.
- **The exception notifier double-reports** a failure that also kills the error-page
  render; the nested report loses the route and fingerprints as `(none)`. Dedupe, or carry
  the outer route into the nested report.
- `SchoenstattTable::getRules()` is unfinished 2020 WIP naming roles that do not exist; its
  provider registration (`module/Schoenstatt/config/module.config.php` ~1734) stays disabled.

## Performance / caching

Background: [caching.md](caching.md).

- **`query-objects-publication`** (10,166 × 80 fields, 29.2 MiB) is built by the literature
  routes and refused by the size budget, so they run uncached. Narrow projection next.
- **`booksmodellibrarytable-checkouts` serializes to 4,608,092 bytes against the 4,194,304
  budget**, refused on every request. Do not raise the budget. Stop embedding each book's
  whole library row (`LibraryTable.php:2043`, the one PHP reference left: 4,605,258 bytes
  with it, 7,943,438 as a copy, so removing the reference first forecloses ever fitting); a
  checkout needs only `libraryId`. Until then nothing may write through the alias.
- **`LibraryTable::getLibraryBooksStatuses()`** hydrates every entity for a library (up to
  12,394 books) then links checkouts; next projection candidate.
- **`Application\Navigation\PageBuilder`'s cache keys are never invalidated on data change**
  (only `SionCacheTrait` keys are): editors see "my change didn't appear" until a flush, and
  `/sitemap.xml`'s generation stamp shares the segment.
- **A merged publication is still a navigation page** (3,627 of 10,104) though it only 301s;
  the sitemap filters them in `App\Sitemap\SitemapGenerator`, `PageBuilder` should.
- **A migration's `@verify` is only executed for real by a deploy.** `tools/migrate.sh
  plan`/`--dry-run` should run it and print the row count, catching a verify written
  backwards (listing the end state) before production. Rehearse through `migrate.sh`, never
  by piping into `mysql`.
- **A data-source publication is unlinkable from anywhere** (`searchPublications()` keeps
  `DataSource IS NULL` on `orCombination` queries, consistent with the picker and search).
  If "Translated from" links to those rows are wanted, pass `includeDataSources => true`
  from the two internal linking callers.
- **`texts (AclResourceId)`** is the one defensible index on the PK-only tables (28x faster,
  but only on an ACL cache miss); take it only if something else touches the table.

## Testing & CI

- **Run-time sweep for deprecations a static pass cannot see**: run the smoke suite with
  `E_DEPRECATED` promoted to failures. The ones that matter hide in request handling and
  production's `error_reporting` hides them all.
- Shared-library tests live here (`test/Unit/TextTest.php` covers `SionModel\Text\Text`)
  because the submodules have no test infrastructure; move them so patres inherits them.
- **Guarded routes whose smoke test asserts only the anonymous 302** prove the guard denies,
  never that the page renders (that shape missed a fatal-200 and a form with no submit
  button). `MagicLinkSignIn::signInWithEveryRole()` exists, nine classes use it; the rest of
  the guarded surface still wants signed-in body assertions (verify which).
  `test/Unit/PortedFormsAreSubmittableTest` is the cheap CI-runnable cover to copy.

## Deploy ops

- **Server maintenance pass: 19 tables the charset migrations left alone** (unreferenced by
  code, so a DBA task; see [database-charset.md](database-charset.md)). Nine `bib_*` (feature
  moved elsewhere; `bib_verses` 236,422 rows / 45.8 MiB, still MyISAM): drop, do not convert.
  Five `b_bib*` import sources with unused FULLTEXT indexes. `sch_dictionary_dictionary`
  (3,084 rows), `sch_dictionary_users` (10 rows, legacy `password` hashes: drop).
  `user_remember_me`, two `sch_visits_rollover_*`. Same pass: `ALTER DATABASE ourlink_db1
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;` (default is still `latin1_swedish_ci`).
- **`PublicationsTable::whichKentenichPeriod()` is unreferenced** but encodes the eight
  periods of Fr. Kentenich's life feeding the live `jkPeriodId` column. Decide before deleting.
- Production runs FastCGI; "what bounds concurrent PHP memory?" has never been asked of the
  Hetzner account (the capsule's ceilings do not apply there).
- **Retire `laminas/laminas-math`** (abandoned; step 1). Sites: `SionModel\Mailing\Mailer:209`
  (magic-link token, three-argument call above), `JUser\Model\User` (verification token),
  `src/Http/CspNonce.php:29`, `src/View/SiteChrome.php:301-304` and the dead `layout.phtml`
  (`getInteger`, trivially `random_int()`), `module/SionModel/src/Mvc/CspListener.php`.
  Preserve `getString()`'s charlist where a call omits one; `ApiTokenService` already uses
  `random_int()`.
- **SionModel's `composer.json` does not require `laminas/laminas-cache`** though four of
  its classes use it (resolves transitively here, not standalone). Moot after step 2.
- **Both monolog loggers write at `Level::Debug`** (`SionModel\Service\LoggerFactory`,
  `ExceptionsLoggerFactory`), so every cache write lands in `data/logs/application_*.log`.
  Raising the threshold is a decision about what the log is for.

## Shared libraries / patres

- The `1.0.x` branches (patres's frozen 2020-22 line) are a review source to **port from,
  never merge**: both lines rewrote the same files. Converge on `modernization`; diff the
  files being touched against `1.0.x` first at each step.
- **patres follows the laminas exit** (decided 2026-09-09): libraries drop laminas in place,
  nothing is kept for a laminas host; patres converges on tagged releases.
- **Migrate patres onto the converged line.** Checklist: inject `ActingUserProviderInterface`
  into its table factories; `$this->actingUserId` is gone (`getActingUserId()`); check
  `setActingUserId()` callers and its `SionTable` subclasses against the still-eager
  `$entityProblemPrototype`; supply `public/css/email-default.css` for the Mailer. JUser 3.x
  needs four migrations and SionModel's composer autoload path fixed before `composer require`.
- **patres's `1.0.x` `SionCacheService` still has the fatal-200 `unset()` bug** (typed
  property on PHP 8: immediate Error). Branch `fix/cache-write-failure-wedge` is pushed on
  laminas-sion-model; the PR against `1.0.x` must be opened by hand (gh token lacks access).
- Retire the `0.3.x`/`1.0.x` branches once patres converges.
