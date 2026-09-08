# The Fr. Kentenich timeline and the Schmiedl text corpus

Two features that were in active development when work stopped in mid-2020, and which
were always meant to become one. This file is the plan for finishing them, split at the
line the user drew: **what can be done now to leave the site in a known and functional
state**, and **what should wait until the strangler is finished and laminas-mvc is gone**.

Everything numeric below was measured against the capsule on 2026-08-15, whose data is a
current production export (see [the capsule note in CLAUDE.md](../CLAUDE.md)) — not the
2021 dump a stale line in that file claimed for five years. Where a number is an estimate
derived from filenames rather than a count of rows, it says so.

**Status, 2026-09-08:** Part 1 is done, and its last open item — 1.6, the guard entries —
was closed by deleting the four write routes rather than guarding them. Part 2 is
unstarted and still blocked on the two product decisions below. Nothing about the data
changed; the 527 events and 2,757 texts are exactly as described.

## Contents

- [What exists today](#what-exists-today)
- [Design decisions already taken](#design-decisions-already-taken)
- [Open decisions, and who has to make them](#open-decisions-and-who-has-to-make-them)
- [Part 1 — now: leave it known and functional](#part-1--now-leave-it-known-and-functional)
- [Part 2 — after laminas is cut out](#part-2--after-laminas-is-cut-out)
- [Why the split falls where it does](#why-the-split-falls-where-it-does)

---

## What exists today

### The corpus — a working feature with a missing pipeline

`texts` holds **2,757 rows: 2,753 `jk-text`**, all German, all ACL `txt_institute`, plus 3
`blog` rows and 1 `other` (all four English, `txt_public`). Fr. Schmiedl's quality grades
are on 1,795 of them — A(764) B(459) E(251) D(185) C(85) F(24) G(23) H(4) — leaving **962
ungraded**. Loaded between 2018-11-30 and 2020-07-03.

This half is genuinely finished and already modernized. `/texts` browse and search, show,
edit, create and delete are all live, guarded `texts_user` / `texts_moderator`, all ported
to Twig, all under smoke tests.

**The importer is gone.** `/en/texts/import` called `$table->importJkTexts(false)`, a
method defined in no class anywhere in the tree; the route was retired on 2026-08-14 as a
guaranteed fatal (see [history.md](history.md), "Archaic endpoints, round 2"). So the
corpus is in the database with no way to re-import or refresh it from source. What
survives is the mapping back: every imported row still carries `LegacyFile`,
`LegacyPathDate` and `LegacyFileDateModified`. That is enough to rebuild an importer, and
not enough to run one.

### The timeline — a bulk data load and a placeholder page

`events` holds **527 rows, every one with `CreatedOn` of 2020-04-02 22:00:00**, and
`sch_changes` contains **zero rows for the `event` entity**. Both facts point the same
way: it was loaded by SQL and no event has ever been touched through the application.

| populated | empty in all 527 rows |
| --- | --- |
| `StartDate` (1885-01-01 → 1968-09-20) | `Country`, `Place` |
| `StartDatePrecision` — year 363, month 9, day 155 | all six `Description*` |
| all six `Title*` (En/Es/De/Pt/It 527, Fr 526) | all six `Slug*` |
| `Duration` — but `0` in every row | `WikidataSubjectId`, `WikidataPropertyId` |
| `Zoom` — but `1` in every row, against a schema default of 3 | `Tags`, `AdminTags`, `AudienceText` |
| `AclResourceId` = `evt_public` | all six `Abbreviation*`, `Url1`–`Url3`, `BestTextQuality` |

So the content is a chronology of Fr. Kentenich's life against world and Church events,
titled in six languages and dated, with no other metadata at all.

### The link between them was never made

`texts.LegacyEventId` is the column meant to connect the two. **It is NULL in all 2,757
rows.** `events.BestTextQuality` — plainly a rollup of the grades of an event's texts — is
NULL in all 527. The join is not partially done; it is unstarted, and both sides left the
socket for it in the schema.

### What is reachable

`/timeline` (route name `events` — the path and the name disagree, which is the laminas
route's own doing) is public, ported to Symfony as `App\Controller\TimelineController` +
`App\Books\EventTimeline`, and renders all 527 events grouped under **7 of the 8**
`KentenichPeriodFromDate` periods — "After the Founder (1968-)" never appears, because the
latest event is 1968-09-20 and that period begins in 1969. It prints `titleEn` only: the
five other title columns are loaded and unused in every locale.

Everything else about events is unreachable, and each for its own reason:

| surface | why it is unreachable |
| --- | --- |
| `event` (show), `event-edit`, `event-delete`, `events/create` | **deleted 2026-09-08** (see 1.6). Until then: no guard entry in `acl.global.php`, so `BjyAuthorize\Guard\Route` default-denied all four, and behind them no form, no template and no create handler |
| `EventsSearchForm` + `EventsController::searchAction()` + `search.phtml` | no route at all, and the entity's `controller_services` is `[]`, so `$this->services[EventsSearchForm::class]` is an undefined key |
| `books/events/event-list.phtml` | not an event list — a copy of the publication list, calling `formatEntity('publication', …)`. Rendered by nothing |
| `Books\Form\EventForm` | deleted in batch 12 (`19d5016`). It matched a pre-`db6.1` draft: fields `durationInDays` and `accuracy` that the shipped schema does not have, no Italian title, no slugs, place, Wikidata or URLs |

### The entity spec describes a schema that does not exist

Independently of the guards, the events write surface could not work. The spec in
`module/Books/config/module.config.php` was written against the April 2020 draft and never
caught up with `database/db6.1.sql`:

| spec entry | reality |
| --- | --- |
| `acl_resource_id_field => 'aclResourcesId'` | db6.1 renamed the column to `AclResourceId`; `processEventRow()` emits `aclResourceId` |
| `required_columns_for_creation => ['startDate','durationInDays','accuracy']` | the last two are not fields — the schema shipped `Duration` + `DurationUnit` + `StartDatePrecision` |
| `create_action_valid_data_handler => 'createEvent'` | defined nowhere in the repository |
| `show_route => 'events/event'`, `show_route_key => 'event_id'` | no route by that name; the real route is top-level `event` on a `:sw_id` segment |
| `default_route_key => 'association_id'` | copy-paste from the associations entity |

And `EventTextTable::processEventRow()` lines 141–146 assign the *column name as a string*
instead of reading the row:

```php
'url1' => 'Url1',
'url1Label' => 'Url1Label',
// …four more
```

Harmless only because all six URL columns are empty. It is a live bug the moment anyone
fills one.

### What the corpus implies about the size of the timeline

The corpus filenames are structured — `1910-0508VLIM.md` is a date at day precision plus a
type code and a place code; `1912-11CHA-01.md` is month precision plus a series index.
**2,294 of the 2,753 carry a day, 248 a month, and 209 conform to neither pattern.**

Collapsing the series suffix gives an **estimate of ~2,324 distinct events**, of which 168
are multi-part: `1941-0720EX-` is a 17-conference retreat, `1951-1002PT-` 16,
`1923-MTA0901` 16. That is exactly the "a text is all or part of one event" structure the
corpus was curated for, and it is visible in the filenames.

So linking the corpus takes `events` from **527 to roughly 2,850 rows, ~82% of them German
talk titles.** The timeline page as written prints every event grouped by year. It would
become unreadable on the day the link lands — which is why Part 2 keeps those two steps
together.

### A collation trap in the corpus filenames

`SELECT DISTINCT LegacyFile` reports 2,640 distinct values; `SELECT DISTINCT BINARY
LegacyFile` reports **2,752**. The schema collates `utf8mb4_general_ci`, so
`1963-0109T015.md` and `1963-0109t015.md` fold together — **113 pairs differ only in
case.** This is the same trap that made db7.9's first `UPDATE` report success and change
nothing, three days ago.

They are not duplicates. Of the 113 pairs, exactly one has an empty member (text 695,
`1940-1225wepr.md`, NULL markdown and NULL word count — the only empty-markdown row in the
table). The other 112 hold **different documents with the same title**: text 2016 has
60,855 characters of markdown and text 2017 has 57,040, both titled "in: Desiderio
Desideravi. Milwaukee-Terziat…". That is consistent with two transcripts of one talk at
different quality — which supports the one-event model rather than complicating it, since
both witnesses belong to the same event.

**The consequence for the importer is concrete: its event key must compare `BINARY`.** A
case-insensitive key silently merges a pair into one text and drops the other, and nothing
would fail.

---

## Design decisions already taken

Recorded here so they are not re-litigated. Each was settled by a measurement or by the
curator's own account of how the corpus was prepared.

1. **One entity, one table, one `kind` discriminator — not two tables.** Four reasons, in
   the order they weigh:
   - The timeline must render both. Two tables means `/timeline` unions two queries across
     two id spaces, with two slug sets, two ACL resource families and two route trees —
     cost paid on the page that is the whole point of the feature.
   - `texts.LegacyEventId` can only be a real foreign key if it has one target table. Point
     it at "one of two" and it stops being enforceable, which is precisely the state
     `sch_roles.AssociationId` is in: **145 rows naming an association that does not
     exist**, found in batch 12. That is a measured cost in this schema, not a principle.
   - The founding events are both kinds at once. `1912-1027VGU.md` (the
     Vorgründungsurkunde) is a biography milestone *and* an event with a text; so is
     1914-10-18. Two tables forces a duplicate row or an arbitrary call on exactly the
     events that matter most.
   - The schema already merged them. `OriginalLanguage`, `AudienceText`, `Abbreviation*`
     and `BestTextQuality` are meaningful only for a talk; the six `Description*` columns
     and `Zoom` only for a curated timeline entry. They already coexist in `events`.

2. **Text → event is many-to-one. A single FK is correct and `LegacyEventId` is the right
   column.** An earlier draft of this analysis proposed a join table; the curator's account
   — texts were deliberately separated so each represents all or part of a single event —
   settles it. A join table would model a many-to-many the curation does not have.

3. **The corpus events are imported, not typed.** ~2,324 events derivable from structured
   filenames is a `bin/console` job. Nobody types that into a form.

4. **Which makes the form a correction surface, not a creation surface** — for the corpus
   side. It should show the derived date, precision, place code and series grouping *with
   their provenance*, so a moderator can fix what the filename got wrong. That is a
   different screen from the blank form a curator uses to add "Titanic sinks, 1912".

5. **Two form paths over one shared base, not two forms.** The repo already does this with
   `AssignmentForm` / `EditAssignmentForm`, and batch 12's shared input-filter spec
   inherits cleanly. Base carries what both need (dates, precision, six titles, place,
   country, tags, ACL, notes); the timeline path adds the six descriptions, `Zoom`,
   Wikidata and the URLs; the speech path adds `OriginalLanguage`, `AudienceText` and the
   six `Abbreviation*`.

6. **`BestTextQuality` is computed, never typed.** It is a rollup of the linked texts'
   grades and belongs in a write-time recalculation, shown read-only in the form.

7. **`Zoom` is the timeline's density control**, and the reason the page survives going to
   ~2,850 rows. It is `SMALLINT NOT NULL DEFAULT 3` in the schema and `1` in every row, so
   the mechanism is present and the curation is not.

8. **Two axes are being conflated when people say "separate forms", and only one is the
   discriminator.** *Does it carry text?* decides which fields the form shows — that is
   `kind`. *Is it about Kentenich's life, the Church, or the world?* decides what the
   timeline shows at a given zoom — that is `Tags` plus `Zoom`, both already in the schema
   and empty in all 527 rows. Kept apart, one table handles both.

---

## Open decisions, and who has to make them

Neither is inferable from the code, and both block Part 2 rather than Part 1.

- **What the `kind` values are.** Two (`timeline` / `speech`) is the minimum. Three or more
  is plausible if written works — letters, books, articles — are meaningfully different
  from spoken talks. The corpus filename codes (`V`, `PR`, `MTA`, `EX`, `PT`, `CHA`) look
  like they already distinguish talk types, but whether those are subtypes of one kind or
  place/occasion codes is a question about how the corpus was catalogued, not something the
  data answers.
- **Who curates the timeline.** There is no events moderator role in `user_role`; the
  nearest analogues are `pub_moderator` for publications and `texts_moderator` for texts.
  This gates the guard entries for `event`, `event-edit`, `event-delete` and
  `events/create`, and is already filed under "Product decisions needed" in
  [BACKLOG.md](BACKLOG.md).

A third question is for the corpus curator rather than the developer: **are the 113
case-variant filename pairs two transcripts of one talk, or an artifact of how the source
tree was assembled?** The answer changes what the importer does with them, and it cannot be
guessed from the rows.

---

## Part 1 — now: leave it known and functional

**Executed 2026-08-15.** Every item below is done except the two answers in 1.7 that only
the corpus curator can give, and the guard decision in 1.6 — which was resolved by
recording the deny as deliberate rather than by opening the routes, since the curator role
is still undecided. Each item keeps its full reasoning so the *why* survives the diff.

The theme is **remove the ambiguity, do not build the feature**. Everything here is safe
under laminas today, none of it depends on the strangler, and none of it commits either
open decision. The goal is that a reader six months from now can tell what works from what
does not without measuring it again.

### 1.1 Fix the six literal-string assignments in `processEventRow()`

`module/Books/src/Model/EventTextTable.php` lines 141–146. A one-line-each correction to
read `$row['Url1']` and friends. Live bug, currently masked by empty columns.

### 1.2 Make the entity spec describe reality

Correct the five wrong keys in the table above, or comment them out with the reason. The
write surface is default-denied, so this changes no behaviour — its whole value is that the
next reader stops believing the surface works. Specifically: `aclResourcesId` →
`aclResourceId`; drop `durationInDays`/`accuracy` from `required_columns_for_creation`;
comment out `create_action_valid_data_handler` with a note that `createEvent` was never
written; fix `show_route` to `event` with `show_route_key => 'sw_id'`; drop the
`association_id` copy-paste.

### 1.3 Add `identifier` to `processEventRow()`

`processTextRow()` computes one via `new ToSchoenstattLinkIdentifier('text')`;
`processEventRow()` computes none, so an event cannot be linked to at all.
`SchoenstattLinkIdentifier` already reserves the `E` suffix and the 600000 offset for
events (`/^SL(6[0-9]{5,5})E$/`). Cheap now, needed by everything in Part 2.

### 1.4 Delete the four dead artifacts

`EventsSearchForm`, `EventsController::searchAction()`, `books/events/search.phtml` (all
three already filed in [BACKLOG.md](BACKLOG.md)) and `books/events/event-list.phtml`, which
is a publication list sitting in the events directory. `EventForm` was removed in batch 12
for the same reason; this is the rest of that sweep.

### 1.5 Render the timeline in the reader's language

All six title columns are populated (En/Es/De/Pt/It 527, Fr 526) and only `titleEn` is
displayed, in every locale. Rendering `title{Locale}` with a fallback to English is a small
change entirely inside `templates/books/timeline.html.twig`, which is already the served
path on both production and the capsule.

**One caveat, and it must be recorded rather than discovered:** this is the first
deliberate divergence between a ported Twig template and the `.phtml` it transcribed.
`tools/port-baseline.php` will report it, and that report is correct — the note belongs in
[strangler.md](strangler.md)'s known-differences table, alongside the existing entries.

While in that file: the introductory paragraph is an English literal in *both* renderings,
so it shows in English in all five locales. Making it translatable is a separate change
with its own consequence — a new phrase row per locale — and belongs with Part 2's
translation pass, not here.

### 1.6 Settle the guard entries, or record why they stay denied

Four routes with no guard entry is indistinguishable from an oversight, and
[acl-rules.md](acl-rules.md) lists them among the unguarded. Two acceptable outcomes:

- If the curator role is decided, add the entries and regenerate both ACL snapshots
  (`tools/acl-table.php`, then diff `acl-rules.md` and `acl-baseline.json`).
- If it is not, leave them denied and say so in the config with a comment naming this file.
  A deliberate deny is a state; an absent entry is a question.

`event` (show) can safely take the `guest, user` that `events` already has — but only once
a show template exists, which is Part 2. Until then it should stay denied, because a
guarded route with no template is a 500 rather than a page.

**Resolved on 2026-09-08 by a third outcome this section did not consider: the four routes
were deleted.** Recording the deny as deliberate had been the choice on 2026-08-15, and it
held for three weeks before the obvious objection landed — a deliberate deny still leaves
four route definitions one config line away from opening onto a surface with no form, no
template and no create handler. Deleting them removes that, and removes the four entries
from the "matchable but unguarded" column of [acl-rules.md](acl-rules.md), which was the
other half of what 1.6 was trying to fix.

What went, exactly: the `event`, `event-edit` and `event-delete` route definitions, the
`create` child of `events`, and the nine `event` entity-spec keys that named them
(`show_route` and its two companions, the three `edit_route*`, the three
`create_action_redirect_route*`, plus `enable_delete_action`, `delete_route_key` and
`delete_action_redirect_route`). The spec keys had to go in the same commit, because
`test/Integration/EntitySpecRoutesAreAssemblableTest` asserts that every route a spec names
exists — which is the assertion 1.2 added.

`enable_delete_action => true` is the one worth pausing on. It was the only thing that made
the events write surface differ from the library one, where the same key is commented out —
so `event-delete` was a *working* generic delete sitting behind a closed guard, and had 1.6
been resolved by opening guards, it would have deleted an event with a single-row `DELETE`.

**Nothing else changed.** The 527 rows, the corrected spec, `processEventRow()`'s
`identifier`, `/timeline` and its Symfony controller, and
`test/Integration/EventFeatureStateTest` all stand. That test now pins the four routes as
*absent* rather than unguarded, which is a stronger property and the same tripwire.

### 1.7 Answer the data questions, without acting on them

Read-only investigations whose output is this register, not a migration. They are the
"metadata that can still be gleaned" — establishing what is there before anything writes to
it. **Nothing here was changed in the database; Part 1 wrote no SQL at all.**

| finding | measured | status |
| --- | --- | --- |
| **113 case-variant filename pairs** — `1963-0109T015.md` vs `…t015.md`, folded by `utf8mb4_general_ci`. 2,752 `BINARY`-distinct filenames against 2,640 case-insensitively distinct | one pair has an empty member; the other 112 hold different documents under the same title (60,855 vs 57,040 characters in the pair opened) | **open — needs the corpus curator.** Two transcripts of one talk, or an artifact of how the source tree was assembled? Either way the Part 2 importer must key `BINARY` |
| **Text 695**, `1940-1225wepr.md` — NULL markdown, NULL word count | the only empty-markdown row in 2,757 | **open — one-row decision.** Fill from source, or delete |
| **3 `blog` texts + 1 `other`** survive the blog's removal in `f19a5ca` | `getSelectPrototype('text')` filters `TextKind = 'jk-text'`, so all four are invisible to every listing | **resolved: leave them.** They are the *only* rows carrying `txt_public`, and `EventTextTable::getResources()` derives the ACL resource set from `SELECT DISTINCT AclResourceId FROM texts` — so deleting them removes a resource other rules may name. Not a cleanup to do casually |
| **962 texts with no `JkTextQuality`** | grades present on 1,795: A(764) B(459) E(251) D(185) C(85) F(24) G(23) H(4) | **open — matters at 2.4.** The `BestTextQuality` rollup has to decide whether NULL means "ungraded" or "lowest", and that is a question about Fr. Schmiedl's method, not about the column |
| **`Duration = 0` in all 527 events, `Zoom = 1` in all 527** | `Zoom`'s schema default is 3, so `1` was written by the loader, not defaulted | **resolved: both are loader artifacts, not measurements.** Nothing reads either today. `Zoom` becomes meaningful at 2.5 and will need curating from scratch |
| **`evt_public` is registered nowhere** — not by the config resource provider, not by `EventTextTable::getResources()`, which only reads the `texts` table | all 527 events carry it | **resolved as a Part 2 prerequisite**, and recorded in the config beside the corrected `acl_resource_id_field`. Fixing the field name is not the same as the per-row check working |
| **`LegacyPathDate` is year-only** — 57 distinct values, every one `YYYY-01-01` | the filename carries the day for 2,294 of 2,753 and the month for 248 more | **resolved: the importer must parse the filename, not read this column.** Recorded in 2.2 |

### 1.8 Pin the current state with tests

So that "known" survives the next person's change:

`test/Integration/EventFeatureStateTest` — three assertions, two of which need no database
so they run on a bare CI runner:

- **The four write routes have no guard entry.** Re-enabling one is then a deliberate act
  that updates a test, not a silent widening. Same reasoning as the ACL snapshots: a rule
  that stops matching makes a page work for *more* people and nothing fails.
- **Every route the entity spec names exists.** This is the assertion that would have
  caught the 2020 drift. Verified by planting: restoring `show_route => 'events/event'`
  fails it with the route name in the message.
- **`texts.LegacyEventId` is empty.** The precondition Part 2's importer inverts — *this
  test is meant to be deleted*, and its failure is the signal that Part 2 has started.
  Until then it catches the column being filled by something that is not the importer.

`test/Smoke/BooksSmokeTest` — two more against the running capsule:

- **527 events under 7 period headings.** 7, not 8, and the count is worth asserting rather
  than assuming: `KentenichPeriodFromDate` defines eight and the eighth begins in 1969,
  after the last event. The event count is a lower bound, so adding an event on the live
  site does not fail a test about page structure.
- **Titles follow the locale**, pinning 1.5 in `de`, `es` and `en`.

### 1.9 Documentation

Add this file to [README.md](README.md)'s index; replace the two standing BACKLOG entries
(the unreachable `EventsSearchForm` trio, and the events write routes under "Product
decisions needed") with pointers here so there is one account rather than three.

---

## Part 2 — after laminas is cut out

**One thing changed under this section on 2026-09-08 and it changes no step in it:** the
four laminas write routes were deleted (see 1.6). Part 2 was always going to declare its
own routes on the Symfony side — steps 2.6 and 2.7 say so — so there is nothing here to
re-plan. What it does mean is that **2.6 starts from no route at all rather than from a
denied one**, which is simpler: a new route in `config/symfony/routes.php` with a controller
and a template behind it, rather than a laminas route to be opened, ported and then removed.

Do not re-add the laminas routes as a stepping stone. A route with no template is a 500
rather than a page, and both open decisions below still block the surface.

Ordered by dependency. Steps 2.2 and 2.5 must ship together or the timeline page breaks on
the day the link lands.

### 2.1 The `kind` discriminator

A migration adding the column, plus a backfill for the 527 existing rows. Blocked on the
open decision about how many values there are. Everything else in Part 2 depends on it.

### 2.2 The corpus importer, as a `bin/console` command

Parses `LegacyFile` into date, precision, type code, place code and series index; creates
the ~2,324 speech events; sets `texts.LegacyEventId`. Requirements that come from the
measurements above rather than from taste:

- **Compares `BINARY`.** 113 filename pairs differ only in case and a `ci` key merges them.
- **Idempotent and re-runnable**, since it will be run repeatedly while the parse rules are
  tuned.
- **Reports what it could not parse.** 209 filenames match neither the day nor the month
  pattern; they need a human, and a silent skip would hide them.
- **Sets `StartDatePrecision` from the filename shape**, not from `LegacyPathDate` — that
  column is year-only in all 2,753 rows (57 distinct values, every one `YYYY-01-01`), while
  the filename carries the day for 2,294 of them. The precision column already exists and
  `SionModel\I18n\View\Helper\DatePrecisionFormat` already renders to it, from db6.5.

This is also the point at which the lost `importJkTexts()` is effectively replaced: the new
command should be able to re-read the source `.md`/`.html` pairs, not only re-derive events
from rows already loaded. Note the constraint `EventTextTable::preprocessText()` documents —
imported HTML must never be overwritten by re-rendering its markdown, which would change the
visible text of 2,740 of 2,756 rows.

### 2.3 Make the link a real foreign key

`ALTER TABLE texts ADD CONSTRAINT … FOREIGN KEY (LegacyEventId) REFERENCES events(EventId)`,
and rename the column off "Legacy" while doing it — it stops being a legacy import artifact
the moment it is the live relation. The FK is the whole point of decision (1): it is what
`sch_roles.AssociationId` does not have, and 145 dangling rows are what that costs.

### 2.4 `BestTextQuality` as a computed rollup

Recalculated on text write and on import; read-only in the form. Depends on the answer to
the 962-ungraded question.

### 2.5 The timeline page, rebuilt

The step that makes 2.2 survivable, and the reason they ship together:

- `Zoom` curated so the page opens at a readable density and drills in.
- Slugs generated for all six locales (`Slug*` is empty in all 527 rows).
- An event show page listing the event's texts — the first page where the two features are
  actually one.
- Per-locale titles, if 1.5 has not already landed it.

### 2.6 The two form paths

One shared base, two subclasses, per decision (5). Written once, in Twig, over
`App\Controller\EntityEditController` and `App\Sion\EntityCreate` — which is exactly why
this waits.

### 2.7 Guards under Symfony Security

The curator role, declared once in the target authorization system rather than added to
BjyAuthorize and migrated later. See [authorization-migration.md](authorization-migration.md).

### 2.8 Indexing

Events become linkable records with per-locale slugs, so they enter the sitemap and the
canonical/hreflang contract — **four places that must agree**, per
[sitemap.md](sitemap.md): both layouts, `App\View\PreferredUrls`, and the sitemap builder.
An hreflang set that omits its own page is non-reciprocal and Google discards it. Adding
~2,850 event URLs to a sitemap of 36,730 is not the risky part; getting the canonical wrong
is.

### 2.9 Translation

The timeline's introductory paragraph, the eight period names, and whatever the event show
page introduces. Read [translation.md](translation.md) first — in particular that record
data is never translated, and that the discovery listener is the only door into
`trans_phrases`, so a phrase that is never rendered in the key locale is never recorded.

---

## Why the split falls where it does

Part 1 is everything that makes the current state legible without committing to a design.
Part 2 is everything that would otherwise be built twice.

Four specific reasons the write surface waits:

- **Forms are the most expensive thing to port.** A new laminas form now means writing it
  again against `BootstrapFormRenderer` and `EntityEditController` later. The edit, delete
  and create surfaces already moved (batches 7–10); building a *new* laminas form is walking
  back into the part of the codebase being removed.
- **`SionController`'s generic create/edit is the machinery being deleted.** Wiring events
  onto it is building on the thing under demolition.
- **A new role added to BjyAuthorize now gets migrated to Symfony Security later.** Declaring
  it once, in the target system, is strictly cheaper — and authorization is the area where
  doing it twice is most likely to leave a rule that quietly stops matching.
- **The timeline page needs real UI**, not a transcription. `templates/books/timeline.html.twig`
  today is a faithful port of a placeholder. Zoom levels, drill-in and a show page are new
  work, and new work belongs on the Symfony side.

What Part 1 deliberately does **not** do: create any event, write any `LegacyEventId`, add
any role, or change what `/timeline` lists. The only user-visible change it proposes is
1.5 — showing the reader a title in their own language, from data already in the table.
