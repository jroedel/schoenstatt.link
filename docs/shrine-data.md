# Keeping the shrine data current

Goal: a shrine and association dataset good enough to **distribute** (schoenstatt.com,
schoenstatt-fathers.org, anyone else who would otherwise maintain a worse copy). Three
workstreams get there — the column set, correctness and completeness (provenance +
suggestions), and autonomous agents — then distribution. Provenance is built and deployed;
everything else is plan. The review/comment feature is a separate system and out of scope
([reviews.md](reviews.md)).

## The data facts that drive the plan

Measured against the capsule (a current production export). 250 shrines: 207 `sch-shrine`
+ 43 `sch-wayside-shrine`, on `sch_associations` (498 rows, 23 kinds).

- `association` is the most-read entity: 3,878 page visits since 2026-05-01, ahead of
  `publication` (3,702).
- **216 of 250 shrines were last written in 2019**; 17 earlier, 16 in 2020–2022, 1 in 2023,
  0 since 2023-10-19. No shrine's opening hours have been edited since 2022-04-15.
- Populated, of 250: geo point 247, `Url1` 219, time zone 216, foundation date 213, postal
  address 210, phone 85, Google place id 80, `EventsHuman` 74, `OpeningHoursHuman` 71,
  **email 63**, `OpeningHoursSpecification` 42 (renders nowhere), `EventsJson` 4 (renders
  nowhere, unwritable).
- **64 of 250 shrines are reachable by email** — 63 association addresses plus one via
  `sch_roles` → `sch_assignments` → `sch_persons`.
- The authority chain is modelled and empty: `user.PersID` populated on 31 of 292 real
  accounts (`multi_person_user` on 3); 265 assignments over 212 persons site-wide;
  **1 of 250 shrines has any role assignment**. (So there *is* a user-to-person link;
  the narrower true statement is that *borrowers* are not accounts — [libraries.md](libraries.md).)
- `Latitude`/`Longitude` `varchar(15)` and `Location geometry` hold **the same fact** on
  247 rows each, with nothing keeping them equal. The config maps only `Location`; the
  varchar pair is `@deprecated` and unmapped.

Conclusion: the constraint is **verification throughput, not field capacity**. The schema
already holds more than we know; what is missing is a way to find out what is true, record
who said so, and ask again later.

## What already exists — do not rebuild

- **v3 API reads and writes associations**: `GET /api/v3/associations`,
  `GET`/`PATCH /api/v3/associations/{identifier}`, `ETag`/`If-Match`, gated on
  `sch_api_bot`, every change in `sch_changes` under the bot's user id ([api-v3.md](api-v3.md)).
- **One rule set**: `App\Schoenstatt\Association\AssociationInputFilterSpec`; the form
  delegates to it, `AssociationValidator` hands the API the same filter,
  `test/Integration/AssociationValidationParityTest` fails on divergence. Add a field there once.
- **Editing one record without an account**: `Books\Model\BorrowerTokenTable`
  (`lib_borrower_tokens`) — scoped, sha256-hashed, expiring, deliberately not single-use,
  on a Symfony page so authorization is code not a role, no person id in the URL. Shrine
  verification uses the same shape.
- **Licence is declared**: `App\Schoenstatt\ShrineDatasets` publishes the shrine index as a
  schema.org `Dataset` under CC BY-SA 3.0 (en, es).
- **Provenance store** — see Workstream 2.

## Gaps

1. **Confirmation was unrecordable** — a no-op `PATCH` answered `200 changed: []` and wrote
   nothing. Closed by the provenance store for API writes; still open for moderator-form saves.
2. **No provenance for human writes** — a rector's value and a scraped one are byte-identical.
3. **Structured fields are write-only**: `OpeningHoursSpecification`/`EventsJson` feed only the
   JSON-LD; `templates/schoenstatt/association.html.twig` renders only the markdown free text.
4. **`eventsJson` is unwritable by anything**: form element commented out
   (`AssociationForm:245`) and deliberately absent from the spec (a spec key naming no element
   becomes an input that emits `null` and erased the four stored rows). The API's writable set
   is derived from the spec, so it cannot write it either.
5. **74% of shrines are unreachable** (64 of 250 with any email).

## Decisions taken

1. **Provenance and confirmation, with writes staying direct — not a moderation queue.**
   This database has had roughly one moderator's attention since 2019; a queue would be where
   updates wait. `sch_changes` keeps every write attributable and revertible.
   - **Granularity is the field group, not the field** — what the page shows and what a person
     confirms ("the contact details are right"). Cost accepted: an agent cannot say "website
     current, phone stale".
   - Records the two facts a direct write loses: **source** (shrine / office / shrine website /
     third-party directory / agent inference, plus URL) with human-sourced outranking scraped,
     enforced in code; and **last confirmed**, distinct from last changed.
   - **Not in the legacy `*UpdatedOn` group columns.** Their stamps move on saves that changed
     nothing (3 shrines have `OpeningHoursHumanUpdatedOn` with no hours, 1 `EventsHumanUpdatedOn`
     with no events, 2 `ContactInfoUpdatedOn` with no contact data), and two of the ten declared
     groups (`emails`, `phones`) were never written because the config array literal mapped the
     same seven fields twice and PHP kept the last key. The "✓ Up-to-date" tooltip guarded on
     `phonesUpdatedOn` has therefore never rendered anywhere. Lesson: a duplicate key in a
     config array is invisible to every tool this repo runs.
   - **Not in `sch_changes` either.** SionModel's `updateEntity()` `$fieldsToTouch` would bump
     the entity's `UpdatedOn` — the only column that can still say "216 of 250 last edited in
     2019", and confirmations through it would erase that signal within a year.
2. **Structured times primary, free text as fallback.** Deciding argument: **translation** —
   `EventsHuman`/`OpeningHoursHuman` are untranslated, so a Brazilian shrine's Mass schedule is
   readable only in Portuguese on a five-language site. Representation: schema.org
   [`Schedule`](https://schema.org/Schedule) (`repeatFrequency`, `byDay`, `byMonth`,
   `byMonthDay`, `startTime`, `scheduleTimezone`, `exceptDate`) — RRULE-shaped, exports to
   iCalendar; the four existing `EventsJson` rows already use it. Access hours stay on
   `spatie/opening-hours` (already a dependency, validator
   `Schoenstatt\Validator\OpeningHoursSpecificationJson`). The free text is **not migrated
   away**: the 74 `EventsHuman` values carry exceptions no schedule model expresses.
3. **A shrine-focused contributor form, separate from the 46-field administrator form**, showing
   only pilgrim-relevant fields, grouped, with a purpose-built hours widget. Same `InputFilter`,
   so no second rule set. **No shrine-specific table**: one table with a `kind` discriminator
   stays; the friction is presentational.
4. **Bootstrap 5 before any shrine UI.** The front end is Bootstrap 3 (3.0.3/3.3.7 files),
   jQuery, selectize, bootstrap-markdown, 60 hand-concatenated `gen-*.js`, no build tool. So
   **the contributor form and the verification micro-form are gated**; everything server-side
   (provenance, times model, API, JSON-LD, address acquisition) proceeds. The migration is
   **not tracked in [BACKLOG.md](BACKLOG.md)** and needs its own entry; BACKLOG's
   TwbBundle → Symfony Bootstrap form themes item meets it, since
   `SionModel\Form\BootstrapFormRenderer` reproduces Bootstrap 3 markup. **No SPA framework** —
   dependency-free custom elements plus HTMX for the Mass-times editor.
5. **Dead columns dropped** — `database/db8.9.sql` (`@phase: post`, `@destructive: yes`), 20 off
   `sch_associations` (98 → 78 columns) and 4 off `sch_persons`. `post` is mandatory:
   `SchoenstattTable::getSelectPrototype()` enumerates columns from `updateColumns`, and
   `getUnlinkedPersons()` names the person stamp columns in raw SQL with 14 callers — dropping
   before the code ships is errno 1054 on every association page.
   - 15 prose columns `Ideal*`/`VisitorsInformation*`/`History*` + `SlugFr`: zero rows, only
     commented-out `@deprecated` config, and the **wrong locale set** (`fr` where the site has
     `it_IT`) — unrevivable. `sch_associations.SlugFr` only; a live `SlugFr` exists on the
     events table (`Books\Model\EventTextTable`).
   - `Emails*`/`Phones*` `UpdatedOn/By` on both tables: dead by the duplicate-key defect, dropped
     rather than repaired because the whole scheme is superseded by decision 1. The duplicate
     config lines went in the same change.
   - **Not dropped: `SuppressionDate`** — zero rows and no write path (element commented out,
     `AssociationForm:327`) but rendered on the show page. A live display field with no door,
     same category as `eventsJson`; the shrine form should give it one.
   - Sparse but used, recorded so nobody re-measures: `ContactNotes` 1, `Email2` 1 (declared
     `int(200)` on a table of `varchar` addresses), `Post2Country` 2, `Phone3`/`Phone3Label` 3,
     `EventsJson` 4, `TwitterUser` 5, `Post2Street2` 7, other `Post2*` 9, `Post1Country` 12,
     `Url3Label` 12.
6. **masstimes.org is a human conversation, not an integration.** No public API, no data
   programme (~117,000 churches, FAITH Catholic). Not a dependency of any phase; the offer may
   run the other way, since many shrines are not parishes and we are the better source. What we
   do unilaterally: emit clean schema.org `CatholicChurch` + `openingHoursSpecification` +
   `Event`/`eventSchedule`, and store third-party record URLs as external identifiers beside
   `GooglePlaceId` (also where provenance points for directory-sourced values).

## Workstream 1 — the column set

Open question: **which facts a shrine record should hold**. Principle: a column earns its
place when a contributor can be asked a clear question whose answer fits it and a dataset
consumer can act on the answer; structure over prose wherever the answer is enumerable.

Clearly earned: `AssociationName` + five `Slug*`, `Kind`, `Country`, `TimeZone`, `Location`,
`Post1*`, `Email`, `Phone1`–`Phone2` + labels, `Url1`–`Url3` + labels, `FacebookUrl`,
`InstagramUser`, `GooglePlaceId`, `PublicNotes`, `FoundationDate`, `IsActive`, `AdminTags`
(241 of 250), the four hours/events columns.

Duplicated, misdeclared or kind-irrelevant (shrines using it): `Latitude`+`Longitude` 247
(duplicates `Location` — the one clear defect); `Email2` 1 (`int(200)`); `Post2*` ≤9;
`ContactNotes` 1; `Phone3`+label 3; `TwitterUser` 5; `Post1Country` 6 vs `Post1CityState` 210;
`IsLifeCommunity` 1; `IsAuthor` 0; `OverrideNameFormat` 6; `FoundationDatePrecision` never
non-`day`; `InternalName`/`IsInternalNameTranslateable` 146/114 (used, but an organisation
fact?). Each holds real values, so each is a judgement, not a `db8.9`-style zero-row drop.

Cannot currently be held: structured Mass/adoration/confession times (decision 2); a writable
closure date (`SuppressionDate`); accessibility, parking, Mass languages — column, structured
blob or `PublicNotes` is the not-over-simplified/not-over-complicated call that sizes the form.

## Workstream 2 — correctness and completeness

### Provenance — built and deployed

`sch_provenance` (`database/db9.0.sql`, append-only) + `App\Provenance\*`:

| piece | role |
| --- | --- |
| `SourceClass` | the five sources and their ranks — policy in code, not schema |
| `Outcome` | `confirmed` / `corrected` / `competing` |
| `FieldGroups` | **derived** from the entity config (`many_to_one_update_columns`, else the field when a `<field>UpdatedOn` exists) — never hand-listed |
| `WriteGate` | ranking rule and the 180-day window |
| `Assertion`, `ProvenanceStore` | the row; `latestFor`, `currentFor`, `historyFor` |
| `Recorder`, `Assessment` | plan a write, then record what it did |
| `ApiEnvelope` | the four reserved `_source`/`_sourceUrl`/`_assertedOn`/… keys |

Wired into `PATCH /api/v3/associations/{id}`: a no-change patch records a **confirmation**;
a group protected by a fresher, better-sourced claim is **withheld**, finding kept
([api-v3.md](api-v3.md) § Provenance). Seven groups; `contactInfo` holds 17 fields;
`name`/`kind`/`country`/`parentId`/`geoPoint` are ungrouped and never withheld.

**Not done:** provenance on the **moderator form** save (until then human edits file no
assertion and the ranking has little high-ranked material) and the shrine-page display.
Moderator-form provenance is the next piece and must land before suggestions.

### Suggestions — not built

A proposal recorded and *not* applied. `sch_provenance` already has the shape; a suggestion
is an assertion with a pending outcome plus **the proposed values** — the one thing the table
cannot hold. The missing primitive is a source class that **never wins**, so public input
always records and never overwrites. `Outcome::Competing` is already suggestion-shaped.
**Do not use the `comments` table** — that is reviews ([reviews.md](reviews.md)).

Authority "from the hierarchy" needs account → person → role → shrine, and that chain is
1 of 250 populated. So `SourceClass::Shrine`/`::Office` are **asserted by the caller** via
`_source` (default: lowest rank), not inferred from the account; populating the chain is a
data-gathering project, not a migration. Reputation ("last twenty assertions accepted →
auto-admit") and AI screening are policies over the append-only store, designed only after
the pending outcome exists.

## Workstream 3 — autonomous agents

- **Collect**: already possible through v3 (read, compare, `PATCH` with provenance keys). Most
  useful addition: a **staleness query** — shrines whose group has not been confirmed in a year.
- **Contact authorities**: blocked on reach (64 of 250). **Address acquisition is the
  highest-value agent research**, ahead of hours and Mass times. Mechanism when addresses exist:
  the `lib_borrower_tokens` pattern on a Symfony page; a national office is the same mechanism
  with wider scope. Mailings live in `bin/console` (`books:send-notices` is the precedent).

## Distribution

Nothing built. Already decided by what exists:

- **A read feed, not `/api/v3`** — v3 is per-role read/write for agents; distribution wants
  bulk anonymous cacheable reads. (v1's `CatholicChurch` projection could not round-trip.)
- **Licence**: CC BY-SA 3.0, already declared by `ShrineDatasets`.
- **Provenance is the feature**: expose `currentFor()` per record (source class, outcome, date).

Open: pull vs push; all five languages or negotiated one; whether a consumer may surface a
shrine our own access rules exclude (three rules decide, one is a route guard — [sitemap.md](sitemap.md)).

## Sequencing

- Structured times (WS1) precede redistributing or translating a schedule.
- Moderator-form provenance precedes suggestions.
- Address gathering starts now and gates nothing.
- Distribution waits for provenance to be surfaced.
- All contributor UI waits for Bootstrap 5; server-side work does not.

## Deliberately not built

Blocking moderation queue for field edits (the volume argument does not apply to reviews);
association creation by agents (`POST` stays unimplemented, [api-v3.md](api-v3.md));
the review feature; migrating free-text hours away; a JavaScript framework; anything
depending on masstimes.org.

## Open questions

- Which field groups the provenance store recognises — are emails and phones their own groups?
- Unit of a verification email: one shrine per recipient, or one message to an office holding twenty?
- Which of the twelve duplicated/kind-irrelevant columns go (only `Latitude`/`Longitude` is clear-cut)?
- Accessibility, parking, Mass languages: columns, blob, or `PublicNotes`?
- How the account → person → role → shrine chain gets populated.
- Distribution: pull or push; language set; consumers vs access rules.
- Does `SuppressionDate` get its write path in the shrine form?

## Capsule caveats

- `sch_associations` is written by the smoke suites (association 319 is their fixture), so
  "edited yesterday" readings need the test account excluded — same caveat as `user` and
  `trans_phrases`.
- The capsule cannot be rebuilt from `database/dumps/` alone: the migration order interleaves
  SQL with `jtranslate:migrate`, `db8.9` must follow `db7.0`, and `mariadb` accepts connections
  before initdb finishes. Data created after the dump is unrecoverable by replay.
