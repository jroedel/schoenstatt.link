# The Fr. Kentenich timeline and the Schmiedl text corpus

Two features left mid-development in 2020 that were always meant to become one. **Part 1
(leave the site in a known, functional state) is done.** Part 2 (build the feature) is
unstarted and blocked on two product decisions below. Numbers measured on the capsule (a
current production export); filename-derived figures are estimates and say so.

## What exists

### The corpus — finished, no pipeline

- `texts`: **2,757 rows — 2,753 `jk-text`** (German, ACL `txt_institute`) plus 3 `blog` and
  1 `other` (English, `txt_public`). Grades on 1,795: A 764, B 459, E 251, D 185, C 85,
  F 24, G 23, H 4 — **962 ungraded**.
- `/texts` browse/search, show, edit, create, delete are live, Twig, guarded
  `texts_user`/`texts_moderator`, under smoke tests.
- **No importer.** `/texts/import` called `importJkTexts()`, defined nowhere; retired. Every
  row keeps `LegacyFile`, `LegacyPathDate`, `LegacyFileDateModified` — enough to rebuild an
  importer, not to run one.
- The 3 `blog` + 1 `other` rows are invisible to every listing (`getSelectPrototype('text')`
  filters `jk-text`) and **must stay**: they are the only `txt_public` rows, and
  `EventTextTable::getResources()` derives the ACL resource set from
  `SELECT DISTINCT AclResourceId FROM texts`.

### The timeline — a bulk load and a placeholder page

- `events`: **527 rows**, all `CreatedOn 2020-04-02 22:00:00`, zero `sch_changes` rows —
  loaded by SQL, never touched through the app.
- Populated: `StartDate` (1885-01-01 → 1968-09-20), `StartDatePrecision` (year 363, month 9,
  day 155), six `Title*` (Fr 526, others 527), `AclResourceId = evt_public`. Loader artifacts:
  `Duration = 0` and `Zoom = 1` in every row (schema default 3). Empty in all rows: `Country`,
  `Place`, six `Description*`, six `Slug*`, Wikidata ids, `Tags`, `AdminTags`, `AudienceText`,
  six `Abbreviation*`, `Url1`–`Url3`, `BestTextQuality`.
- `/timeline` (route name `events` — path and name disagree) is public,
  `App\Controller\TimelineController` + `App\Books\EventTimeline`, renders all 527 under 7 of
  the 8 `KentenichPeriodFromDate` periods ("After the Founder (1968-)" begins 1969, after the
  last event), titles in the reader's locale with English fallback. The intro paragraph is an
  English literal (Part 2 translation pass).
- **No event write surface exists.** `event`, `event-edit`, `event-delete` and `events/create`
  were deleted along with the nine entity-spec keys naming them; there is no form, template or
  create handler. **Do not re-add them as a stepping stone** — a route with no template is a
  500. `evt_public` is registered by no resource provider (a Part 2 prerequisite, noted in the
  config beside `acl_resource_id_field`).
- `processEventRow()` emits `identifier` (`SchoenstattLinkIdentifier` reserves suffix `E`,
  offset 600000) and reads `$row['Url1']` etc. — the literal-string bug is fixed.

### The link was never made

`texts.LegacyEventId` is NULL in all 2,757 rows; `events.BestTextQuality` NULL in all 527.
Both sides left the socket; nothing was plugged in.

### What the filenames say about the timeline's eventual size

- `1910-0508VLIM.md` = day + type code + place code; `1912-11CHA-01.md` = month + series
  index. **2,294 of 2,753 carry a day, 248 a month, 209 match neither.**
- Collapsing series suffixes: **~2,324 distinct events**, 168 multi-part (`1941-0720EX-` is
  17 conferences, `1951-1002PT-` 16, `1923-MTA0901` 16).
- Linking takes `events` from 527 to **~2,850 rows, ~82% German talk titles** — the current
  page, which prints everything by year, becomes unreadable the day the link lands.
- `LegacyPathDate` is **year-only** (57 distinct values, all `YYYY-01-01`); precision must
  come from the filename.

### Collation trap

`DISTINCT LegacyFile` = 2,640; `DISTINCT BINARY LegacyFile` = **2,752** — `utf8mb4_general_ci`
folds **113 pairs differing only in case**. They are not duplicates: one pair has an empty
member (text 695, `1940-1225wepr.md`, the table's only NULL-markdown row); the other 112 are
different documents under one title (60,855 vs 57,040 chars in the pair opened), consistent
with two transcripts of one talk. **The importer's event key must compare `BINARY`.**

## Design decisions taken

1. **One entity, one table, one `kind` discriminator — not two tables.** The timeline must
   render both; a single FK target keeps `LegacyEventId` enforceable (`sch_roles.AssociationId`
   has no FK and carries 145 dangling rows); founding events (1912-10-27 Vorgründungsurkunde, 1914-10-18) are both
   kinds at once; the schema already merged the talk-only and timeline-only columns.
2. **Text → event is many-to-one; a single FK on `LegacyEventId` is right.** Texts were
   deliberately cut so each is all or part of one event — no join table.
3. **Corpus events are imported, not typed** — a `bin/console` job over structured filenames.
4. **The form is a correction surface** for imported events (derived date, precision, place
   code, series, with provenance), distinct from the blank form for "Titanic sinks, 1912".
5. **Two form paths over one shared base**, as `AssignmentForm`/`EditAssignmentForm`: base =
   dates, precision, six titles, place, country, tags, ACL, notes; timeline path adds
   descriptions, `Zoom`, Wikidata, URLs; speech path adds `OriginalLanguage`, `AudienceText`,
   `Abbreviation*`.
6. **`BestTextQuality` is computed, never typed.**
7. **`Zoom` is the density control** that lets the page survive ~2,850 rows; present in schema,
   uncurated.
8. **Two axes, one discriminator.** *Carries text?* is `kind`. *Kentenich / Church / world?*
   is `Tags` + `Zoom`.

## Open decisions

- **The `kind` values.** Two (`timeline`/`speech`) is the minimum; whether filename codes
  (`V`, `PR`, `MTA`, `EX`, `PT`, `CHA`) are talk subtypes or occasion codes is a cataloguing
  question for the curator.
- **Who curates the timeline.** No events moderator role exists (`pub_moderator`,
  `texts_moderator` are the analogues). Gates any write surface; in [BACKLOG.md](BACKLOG.md)
  under product decisions.
- For the corpus curator: are the **113 case-variant pairs** two transcripts of one talk or an
  assembly artifact? **Text 695**: fill from source or delete? Does NULL grade mean "ungraded"
  or "lowest" for the `BestTextQuality` rollup (962 rows)?

## Part 1 — done

Theme: remove ambiguity, build nothing, write no SQL. All landed:

- `processEventRow()` reads its URL columns and emits `identifier`.
- The `event` entity spec describes the shipped schema (`aclResourceId`, no
  `durationInDays`/`accuracy`, no `createEvent` handler, no route keys).
- Dead artifacts deleted: `EventForm`, `EventsSearchForm`, `searchAction()`,
  `books/events/search.phtml`, `event-list.phtml` (a publication list in the events directory).
- Timeline titles follow the locale.
- The four write routes deleted rather than guarded (a deliberate deny still left them one
  config line from opening onto a surface with no form; `enable_delete_action => true` made
  `event-delete` a working single-row `DELETE` behind a closed guard).
- Pinned by `test/Integration/EventFeatureStateTest`: the write surface **does not exist**;
  every route the spec names is assemblable (the assertion that would have caught the 2020
  drift; also `EntitySpecRoutesAreAssemblableTest`); `texts.LegacyEventId` is empty — **this
  test is meant to be deleted** when Part 2's importer starts. `test/Smoke/BooksSmokeTest`
  pins 527 events under 7 headings (count is a lower bound) and per-locale titles.

## Part 2 — after laminas-mvc is gone

Ordered by dependency. **2.2 and 2.5 ship together** or the page breaks on link day. All
routes are new Symfony routes in `config/symfony/routes.php`.

1. **`kind` discriminator** — migration + backfill of 527 rows. Blocked on the values decision.
2. **Corpus importer, `bin/console`** — parses `LegacyFile` into date, precision, type, place,
   series; creates ~2,324 speech events; sets `LegacyEventId`. Must compare **`BINARY`**, be
   idempotent, **report the 209 unparseable names**, set `StartDatePrecision` from the filename
   (`DatePrecisionFormat` already renders it). Should also re-read source `.md`/`.html` pairs,
   replacing the lost `importJkTexts()`; never re-render imported HTML from markdown
   (`EventTextTable::preprocessText()` — would change 2,740 of 2,756 rows).
3. **Real FK** `texts.LegacyEventId → events.EventId`, renaming the column off "Legacy".
4. **`BestTextQuality` rollup** on text write and import; read-only in the form. Blocked on
   the NULL-grade answer.
5. **Timeline rebuilt**: `Zoom` curated, six slugs generated, an event show page listing its
   texts (the first page where the two features are one).
6. **Two form paths**, Twig, over `App\Controller\EntityEditController` / `App\Sion\EntityCreate`.
7. **Guards** — the curator role declared once in the target authorization system
   (symfony/security-core), never in the layer being removed.
8. **Indexing** — events get per-locale slugs, so they enter the four-place
   canonical/hreflang contract and the sitemap ([sitemap.md](sitemap.md)); ~2,850 URLs on
   36,730 is not the risk, a wrong canonical is.
9. **Translation** — the intro paragraph, eight period names, the show page
   ([translation.md](translation.md): record data is never translated; a phrase never
   rendered in the key locale is never recorded).

## Why the write surface waits

Forms are the most expensive thing to port; `SionController`'s generic create/edit is being
deleted; a role added to the old ACL layer would be migrated again; and the timeline needs
real UI (zoom, drill-in, show page), not a transcription of a placeholder.
