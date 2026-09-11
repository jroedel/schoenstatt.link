# The v3 API

The read/write API for automated agents, and the only API the site serves. Two unrelated
resources, each gated on its own role:

| resource | purpose | role |
|---|---|---|
| `/api/v3/associations` | keep the shrine database current | `sch_api_bot` |
| `/api/v3/phrases` | review and improve the site's translations | `sch_api_translator` |

A token that opens one resource is refused by the other. Neither role is named by any guard,
menu or ACL rule elsewhere, so a leaked token reaches one resource and nothing else.
`sch_api_translator` is **not** `translator` (the human role behind `/admin/translations`),
and a bot must **not** hold `sch_user` (it opens the moderator edit form for every association).

Start at `GET /api/v3/schema`; everything below is published there, generated from the same
specification the endpoints validate with.

## Endpoints

| method | path | role | notes |
|---|---|---|---|
| `GET` | `/api/v3/schema` | public | index of entities |
| `GET` | `/api/v3/schema/{entity}` | public | `association` or `phrase` |
| `GET` | `/api/v3/associations` | `sch_api_bot` | `?kind=`, `?limit=`, `?offset=` |
| `GET` | `/api/v3/associations/{identifier}` | `sch_api_bot` | e.g. `SL100319A`; returns `ETag` |
| `PATCH` | `/api/v3/associations/{identifier}` | `sch_api_bot` | partial update; honours `If-Match` |
| `GET` | `/api/v3/phrases` | `sch_api_translator` | filtered, paged collection |
| `PATCH` | `/api/v3/phrases` | `sch_api_translator` | batch, ≤200 phrases, no `If-Match` |
| `GET` | `/api/v3/phrases/{phraseId}` | `sch_api_translator` | returns `ETag` |
| `PATCH` | `/api/v3/phrases/{phraseId}` | `sch_api_translator` | honours `If-Match` |
| `GET` | `/api/v3/phrases/{phraseId}/history` | `sch_api_translator` | `?language=`; no `ETag` |
| `POST` | `/api/v3/phrases/{phraseId}/retire` | `sch_api_translator` | body `{"_note": …}`, required |
| `POST` | `/api/v3/phrases/{phraseId}/unretire` | `sch_api_translator` | same |

No create, no delete, on either resource. No locale prefix: `/api/v3/…` answers directly,
never redirects to `/en/…`. Any other verb on these paths is a `405` whose `Allow` header and
`error.allow` list the accepted ones. Any other `/api/…` path is a JSON `404` — except
**v1 and v2, which are gone**: every `/api/v1/…` and `/api/v2/…` URL (locale prefix or not)
answers `410 Gone` with `Link: </api/v3/schema>; rel="successor-version"` and the envelope
`{"status":"NOK","result":{"error":"This API version has been retired. Use /api/v3; see
/api/v3/schema."}}`. The `Link` names the schema because `/api/v3` itself is not a route.

## Authentication

Every non-public endpoint needs `Authorization: Bearer <jwt>`.

### Getting a token

An agent is an ordinary account with one extra role. Administrators only:

1. Create the account at `/en/users/create`. In *Roles* select the one role the agent needs
   and **deselect `sch_user`** (pre-ticked). One role, not both, unless one agent does both jobs.
2. Issue a token at `/en/users/{id}/api-tokens`: label it, press *Issue token*, copy the JWT
   from the message — shown once, not recoverable (only its `jti` is stored). Lifetime
   `juser.api_jwt_lifetime`, default `P6M`.

The button appears only for accounts holding a role listed in `juser.api_token_roles`
(`config/autoload/juser.global.php`). This is the only way a token is issued.

### Revoking

Same screen (`/users/{id}/api-tokens/{tokenId}/revoke`). Per token, effective on its next
request. Every issued token is a row in `user_api_token`; a `jti` missing from that table is
refused exactly like a revoked one (fail closed). Expired rows are cleared a day after lapse.

### What a request must satisfy

`App\Api\BotIdentity::resolve()` checks, in order: a well-formed bearer JWT; a live `jti` in
`user_api_token`; **`user.state = 1`** (a deactivated account's tokens are refused — a bearer
token is a sign-in that skips the sign-in page); the endpoint's `requiredRole()`. Any failure
is one uniform `401` naming only the role the endpoint wanted:

```jsonc
// 401, WWW-Authenticate: Bearer realm="schoenstatt.link api v3"
{ "error": { "status": 401,
             "message": "This endpoint needs a bearer token belonging to an account that holds the `sch_api_bot` role." } }
```

### Adding a resource

All three, or no agent will ever reach it: a migration creating its role (pattern:
`database/db6.6.sql`, `db6.8.sql`); the role appended to `juser.api_token_roles` (otherwise no
screen issues a token for it); a `requiredRole()` on its controller extending `AbstractApiController`.

## Conventions

- **Errors** are `{"error": {"status": N, "message": "…", …extra}}`; `422` carries specifics
  (`messages`, `unknownFields`, `writableFields`, `unknownParameters`, …). A body that is not
  a JSON object is `400`.
- **Unknown is refused, never ignored**: unknown field, unknown query parameter, unknown
  language, or a locale where a language is expected — all `422`.
- **Paging**: `limit` (default 100, max 500), `offset` (default 0); out-of-range is clamped,
  non-numeric falls back to the default.
- **Concurrency**: single-resource `GET`s carry an `ETag` (a hash of the document);
  single-resource `PATCH`es honour `If-Match`, `412` with the current `etag` when stale;
  omitting it is a blind write. Apache appends `-gzip` to compressed tags — both sides are
  normalized, echo back what you received. Batch and retire/unretire ignore `If-Match`; the
  history endpoint has no `ETag`.
- **No-op writes** are `200` with `"changed": []` and write nothing (associations still
  record a confirmation, below).
- **Languages, not locales**: `de`, `es`, `pt`, `it`, `en`. `de_DE` is a `422` naming `de`.
  The list is configuration read at request time — take it from `writable.languages`.
- Every write is attributed to the bot's user id (`sch_changes`; `trans_translations.modified_by`).

## Schema

### `GET /api/v3/schema`

```jsonc
{ "version": 3,
  "entities": {
    "association": { "schema": "/api/v3/schema/association", "collection": "/api/v3/associations", "requiredRole": "sch_api_bot" },
    "phrase":      { "schema": "/api/v3/schema/phrase",      "collection": "/api/v3/phrases",      "requiredRole": "sch_api_translator" } } }
```

### `GET /api/v3/schema/association`

`fields` — one entry per writable field with `required`, `maxLength`, `enum`, `constraints`,
`filters`, generated from `AssociationInputFilterSpec`; enumerations are live (`kind` lists
the kinds in the database, `parentId` every association id including inactive). Plus
`sideEffects.publicNotes`. The provenance keys are documented below and in
`App\Provenance\ApiEnvelope`, not in the schema *(verify)*.

### `GET /api/v3/schema/phrase`

`writable` (`languages`, `maxLength`, `note`, `retract`, `notes`), `endpoints`, `retirement`,
`history`, `filters` (all eight query parameters), `sideEffects`. Generate a client from
`endpoints`; a subresource is not discoverable from a field list.

## Associations

### `GET /api/v3/associations`

`?kind=sch-shrine` / `?kind=sch-wayside-shrine`, `limit`, `offset`. Answers
`{ "total", "offset", "limit", "items": [ …association documents… ] }`.

### `GET /api/v3/associations/{identifier}`

```jsonc
{ "identifier": "SL100319A", "kind": "sch-shrine",
  "fields": { "name": "Original Schoenstatt Shrine", "country": "DE", "…": "…" },
  "meta": { "updatedOn": "…", "updatedBy": 5, "etag": "W/\"639df94…\"", "url": "…" } }
```

Every key of `fields` is a key you may send back.

### `PATCH /api/v3/associations/{identifier}`

Body: a JSON object of any subset of the writable fields, plus optional provenance keys.

```bash
curl -X PATCH https://schoenstatt.link/api/v3/associations/SL100319A \
  -H "Authorization: Bearer $JWT" -H "Content-Type: application/json" -H "If-Match: $ETAG" \
  -d '{"openingHoursHuman": "Sun-Sat 9-11am, 12-8pm", "_source": "website",
       "_sourceUrl": "https://example.org/hours", "_assertedOn": "2026-08-01"}'
```

```jsonc
{ "changed": ["openingHoursHuman"], "association": { …new document… } }
```

| status | meaning |
|---|---|
| `200` | applied, confirmed, or partly withheld (see `changed`, `confirmed`, `withheld`) |
| `404` | no association has that identifier |
| `412` | `If-Match` stale — re-read and re-apply |
| `422` | unknown field, invalid result, or bad `_source`/`_sourceUrl`/`_sourceNote`/`_assertedOn` |

#### Validation is the moderator form's

The patch is **merged onto the stored record and the whole record validated** with the edit
form's own engine (`App\Schoenstatt\Association\AssociationValidator`, rules in
`AssociationInputFilterSpec`): an agent is refused exactly what a moderator is refused, with
the same messages; only the CSRF rule is skipped (`test/Integration/AssociationValidationParityTest`).

```jsonc
{ "error": { "status": 422, "message": "The association would not be valid after this change.",
             "messages": { "kind": { "notInArray": "The input was not found in the haystack" } } } }
```

#### Provenance keys

Four reserved keys, all optional, none colliding with a field (no field starts with `_`):

| key | |
|---|---|
| `_source` | `shrine` > `office` > `website` > `directory` > `inference`. Default `inference` (lowest) |
| `_sourceUrl` | evidence URL, ≤1000 chars |
| `_sourceNote` | evidence in words, ≤255 chars |
| `_assertedOn` | when the claim was **true**, not when sent (a March bulletin filed in August is a March observation; the freshness window is measured from it). Default now; a future date is `422` |

**Confirmation.** A patch whose values all match what is stored writes nothing to the
association but records that you checked each field group you named:

```jsonc
{ "changed": [], "confirmed": ["contactInfo", "openingHoursHuman"], "association": { … } }
```

**Withheld.** A field is recorded as a competing assertion but **not applied** when a
standing claim on the same field group comes from a *strictly* higher-ranked source, vouched
for the stored value, and is younger than **180 days** (`App\Provenance\WriteGate`). The
rest of the write proceeds; the response is `200`, not `409`:

```jsonc
{ "changed": ["openingHoursHuman"],
  "withheld": { "fields": ["phone1"], "groups": ["contactInfo"],
                "reason": "A better-sourced and more recent claim covers these. Your finding was recorded as a competing assertion; the stored value did not change." },
  "association": { … } }
```

Equal ranks never block (`website` can always correct `website`); groups are decided
independently; identity fields `name`, `kind`, `country`, `parentId`, `geoPoint` belong to no
group and are never withheld. Groups derive from the entity config
(`many_to_one_update_columns`, else a field with its own `<field>UpdatedOn` column); e.g.
`contactInfo` covers phones, emails, URLs and postal fields.

#### Side effect: `publicNotes` is translated

The site runs `publicNotes` through the translator once per locale — **that free text in the
phrase table is the per-locale description feature, by design; do not retire or retract it.**
Replacing the text makes it a new phrase (untranslated until someone translates it — never
stale) and **automatically retires** the old text's phrase, recorded in its history as a
`retire` naming the association. `openingHoursHuman` and `eventsHuman` are never translated.

Not possible: create (`POST`), delete, or reach anything outside this resource.

## Phrases

A row in `trans_phrases` is a **key**, created when the site first renders a string; `phrase`
and `textDomain` are read-only (editing them would orphan the row). **The writable set is the
languages, and nothing else.** Every read is scoped to this project; another project's id is `404`.
Not possible: create a phrase (they are discovered by rendering), delete one (retire instead;
genuine deletion is a human with database access), blank a translation by value, address by locale.

### `GET /api/v3/phrases`

| parameter | |
|---|---|
| `textDomain` | exact match, e.g. `Schoenstatt` |
| `originRoute` | exact match on the route the phrase was first seen on |
| `originRouteLike` | same, with `%` and `_` as wildcards |
| `search` | substring of the source phrase (`%`, `_` literal) |
| `untranslatedIn` | language code; phrases with no usable translation in it (no row **or** `""`) |
| `translatedIn` | language code; phrases that have one |
| `onlyRetired` | only retired phrases |
| `includeRetired` | retired alongside live; `meta.retiredOn` tells them apart |
| `limit`, `offset` | default 100, max 500 |

Filters are `WHERE` clauses, applied before paging; `?untranslatedIn=de&textDomain=Application`
is the worklist query. **Retired phrases are excluded by default** (the collection is the
worklist); `GET /api/v3/phrases/{id}` still answers for one.

### `GET /api/v3/phrases/{phraseId}`

```jsonc
{ "phraseId": 10028, "textDomain": "Application", "phrase": "Access to entity denied.",
  "translations": {
    "de": { "text": null, "modifiedOn": null, "modifiedBy": null },
    "en": { "text": "Access to entity denied.", "modifiedOn": "2023-06-18T11:01:15+00:00", "modifiedBy": 18 }
  },
  "context": { "originRoute": "sign-in-no-cookies", "url": "https://schoenstatt.link/en/sign-in-no-cookies" },
  "meta": { "addedOn": "…", "retiredOn": null, "etag": "W/\"5730983…\"",
            "url": "…/api/v3/phrases/10028", "history": "…/api/v3/phrases/10028/history" } }
```

- A language with no row is present with `"text": null`, never absent; `""` reads as `null`.
  `modifiedOn` matters: a 2017 translation of a 2023 source string is stale, not missing.
- `context.url` is the **English** page the phrase was first rendered on, on the host you
  called; `null` whenever the origin route takes parameters (`text`, `associations/association`,
  …) — "not viewable in situ", not an error.
- The `ETag` covers `translations` only; `retiredOn` and `context` are outside it.

### `GET /api/v3/phrases/{phraseId}/history`

What writing to this phrase has **destroyed or withdrawn**, newest first. `?language=de`
narrows to one language's thread (unknown language: `422`). No `ETag`.

```jsonc
{ "phraseId": 10028, "language": null,
  "history": [
    { "language": "de", "previous": "Zugriff verweigert.", "operation": "update",
      "note": "Zugriff is the noun; the UI needs the imperative here.",
      "textDomain": "Application", "phraseId": 10028,
      "writtenBy": 18, "writtenOn": "2023-06-18T11:01:15+00:00",
      "replacedBy": 42, "replacedOn": "2026-08-11T09:12:44+00:00" } ],
  "meta": { "count": 1, "url": "…/api/v3/phrases/10028" } }
```

| `operation` | meaning | `language` | `previous` |
|---|---|---|---|
| `update` | text replaced | the language | the replaced text |
| `retract` | text deleted via `_retract` | the language | the deleted text |
| `retire` | phrase left the worklist (automatic or by hand) | `null` | `""` |
| `unretire` | phrase put back **by hand** (a render that un-retires writes nothing) | `null` | `""` |

- Filling an empty language writes **no** entry; an empty list means nothing was lost.
- `writtenBy`/`writtenOn` describe the row that was lost; `replacedBy`/`replacedOn` the
  change that ended it. `note` is that change's `_note`; `null` for GUI writes.
- **Keyed on `(project, phrase hash, locale)`, not on the row id**: the thread survives merges
  and delete-and-rediscover and spans every text domain the string appears in, so each entry
  carries its own `phraseId`/`textDomain`. `retire`/`unretire` appear in every language's thread.
- A phrase live again with a `retire` and no `unretire` after it was un-retired by a render:
  the retirement was wrong, and the note says what was believed.
- Append-only, no foreign key to the phrase; records only what was overwritten or withdrawn.

### `PATCH /api/v3/phrases/{phraseId}`

Body: language code → translation, plus the reserved keys `_note` and `_retract`. Same
headers as the association PATCH (`Authorization`, `Content-Type`, `If-Match`).

```jsonc
// PATCH /api/v3/phrases/10028
{ "de": "Zugriff auf Objekt verweigert.", "_note": "Zugriff is the noun; the UI needs the imperative." }
// 200
{ "changed": ["de"], "phrase": { …new document… } }
```

No merge step (nothing is required), so one language is a complete submission. Statuses as
for associations (`400`, `404`, `412`, `422`). Validation is `JTranslate\Form\EditPhraseForm`'s
own `InputFilter` via `App\JTranslate\Phrase\PhraseValidator` (pinned by
`test/Integration/PhraseValidationParityTest`): each language optional, trimmed, ≤2000 chars.

**Values never remove a translation:**

| you send | effect |
|---|---|
| language absent | left alone |
| `""` | left alone (the web form posts `""` for untouched languages) |
| `null` | **`422`** naming `_retract` |
| any other string, incl. `"0"` | written |

#### `_retract` — remove a translation

```jsonc
{ "_retract": ["de", "pt"], "_note": "Both were machine-translated from the Spanish and read as such." }
```

Recorded in history as `retract`; the next `GET` shows `"text": null`. `422` unless the value
is a **list** of language codes (no locales) and no language is both written and retracted in
one request (`{"de": "", "_retract": ["de"]}` is fine — `""` is not a write). Retracting an
empty language is `200` with `"changed": []`. **`_retract` does not take a phrase off the
worklist** — it adds a gap. For that, retire.

#### `_note` — say why

≤255 characters (longer is trimmed; non-string is `422`). Attached to the history rows the
write produces — one per translation it destroys, none (dropped) if it only fills gaps.
**Send it on every overwrite and retraction**: the next agent and humans in the phrase edit
screen read it. Batch entries take `_note` per phrase.

### `PATCH /api/v3/phrases` — batch

```jsonc
{ "phrases": { "10028": { "de": "…", "_note": "…" }, "6197": { "es": "…", "pt": "…" } } }
```

```jsonc
{ "results": { "10028": { "ok": true, "changed": ["de"] },
               "6197":  { "ok": false, "status": 422, "message": "…", "…": "…" } },
  "summary": { "received": 2, "applied": 1, "failed": 1 } }
```

At most **200** phrases per batch (`422` with `maxBatch`/`received`). Each id is judged
separately — one bad entry fails that entry only (unknown id: per-entry `404`). No `If-Match`.
**Use it for more than a handful of writes**: every single-phrase write recompiles all catalogs,
a batch recompiles once (~0.25 s per single PATCH vs 1.25 s for 200 as a batch).

### Catalogs: a write is not finished when the row is written

The site renders from compiled `.lang.php` catalogs; every write that changed something
recompiles them. If the files cannot be written the write still **succeeded** and the response
carries a `warning` — do not retry; the filesystem needs fixing:

```jsonc
{ "changed": ["de"], "phrase": { … },
  "warning": "The translations were saved, but the compiled catalogs could not be written, so the site will keep showing the old text until that is fixed." }
```

### `POST /api/v3/phrases/{phraseId}/retire` and `…/unretire`

Take a phrase off (or back onto) the translator's worklist. Destroys nothing; the row and its
translations stay and the site renders exactly as before. No catalog recompile.

```jsonc
// POST /api/v3/phrases/13661/retire
{ "_note": "A composition name filed by the breadcrumb, not an interface string. If this is back, the data-label fix regressed." }
// 200
{ "changed": true, "phrase": { "…": "…", "meta": { "retiredOn": "2026-08-11 12:40:02", "…": "…" } } }
```

- **`_note` is required** (empty, whitespace or non-string: `422`) — it is the whole record of
  the judgement, read when the phrase reappears. **Only `_note`**: any other key is `422`
  pointing at `_retract`; a non-object body is `400`.
- **Idempotent**: `changed` is a boolean; a repeat is `200` with `false` and no history entry.
- `If-Match` is ignored (the ETag covers translations, which a retirement does not change).
- **Self-healing**: the first render that misses a retired phrase clears `retired_on` (no
  history entry), so a wrong retirement costs one request rendering English. A phrase
  translated in **every** language never misses and stays retired until un-retired by hand.
  Retire on suspicion; write the note for the person who will read it.

#### Retract vs retire

| | `_retract` on a PATCH | `POST …/retire` |
|---|---|---|
| acts on | one **language's translation** | the **phrase** |
| destroys | that text (recoverable from history) | **nothing** |
| worklist | phrase stays, one more gap | phrase **leaves** |
| site | that language falls back to English | unchanged |
| undoes itself | no | **yes**, on the next missed lookup |
| `history.operation` | `retract` | `retire` |

#### One string, two rows

`UNIQUE (project, text_domain, phrase_hash)`: the same string in two text domains is two rows
sharing one hash (routine: a miss is looked up in the page's domain, then `default`). Retiring
one row does **not** retire the other; a history read on either id returns **both**
retirements, each with its own `phraseId`/`textDomain` — correct, not a retry recorded twice.
There is no batch retire: each retirement carries its own reasoning.

## Working rules for agents

- **Discover from the schema** and **send `If-Match`** on single-resource writes.
- **Say where a claim came from** (`_source`, `_assertedOn`) and **why you overwrote**
  (`_note`). An undeclared source is the lowest rank, so a later, better-sourced human
  correction is protected from your next scrape.
- **"Fill gaps, never overwrite" is a preference, not a rule**: the history endpoint makes a
  bad translation correctable (revert = PATCH the text it gives back). A filtered, considered
  batch still beats a broad speculative rewrite, because a bad batch is work to undo.
- **A phrase that vanished from your queue was superseded or retired**; its history says so.
- **Association `publicNotes` rows in the phrase table are content, not a leak**, and form
  placeholders (opening-hours JSON examples, "Turn right at the first driveway…") are
  interface text in the `default` domain. Neither is yours to retire on sight.
- New phrases arrive in pairs (page domain + `default`); the second is pre-translated by copying.

## Where the code is

| file | what |
|---|---|
| `config/symfony/routes.php` | the routes, incl. the `405` and `410`/`404` refusal routes |
| `src/Api/BotIdentity.php` | token → `jti` registry → `user.state` → role |
| `src/Controller/Api/` | `AbstractApiController` (401, envelope, ETag, paging), `AssociationsV3Controller`, `PhrasesV3Controller`, `ApiSchemaController`, `ApiRouteNotFoundController` (410/404), `MethodNotAllowedController` |
| `src/Provenance/` | `SourceClass` ranks, `WriteGate` (180 days), `FieldGroups`, `ApiEnvelope`, `Recorder` |
| `src/Schoenstatt/Association/` | `AssociationResource` (document, merge, ETag), `AssociationValidator`, `AssociationInputFilterSpec` |
| `src/JTranslate/Phrase/` | `PhraseResource` (document, ETag, language↔locale), `PhraseValidator` |
| `module/JTranslate/src/Model/TranslationsTable.php`, `I18n/LanguageMap.php` | `CRITERIA`, `getPhrasePage()`, `retirePhraseById()`, `getTranslationHistory()`, `NOTE_LENGTH`; language ↔ locale (throws on an ambiguous configuration) |
| `module/JUser/src/Service/ApiTokenService.php`, `Model/ApiTokenTable.php`; `database/db6.6.sql`, `db6.7.sql`, `db6.8.sql`, `db9.0.sql` | minting and the `user_api_token` registry; the roles, the registry table, `sch_provenance` |
| `test/Smoke/{ApiV3,PhrasesApiV3,ApiTokenAdmin}SmokeTest.php`, `test/Integration/{Association,Phrase}ValidationParityTest.php` | HTTP contract, cross-role refusal, issue → use → revoke; API rules are the forms' rules |

See also [translation.md](translation.md) (phrase discovery, catalogs) and [shrine-data.md](shrine-data.md) (provenance).
