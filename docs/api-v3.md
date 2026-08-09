# The v3 API

The read/write API for automated agents. It exposes two unrelated resources:

| resource | what an agent does with it | role |
|---|---|---|
| [`/api/v3/associations`](#associations) | keeps the shrine database current | `sch_api_bot` |
| [`/api/v3/phrases`](#phrases) | reviews and improves the site's translations | `sch_api_translator` |

**The two roles are two boundaries, not one.** A token that can PATCH shrines is
refused by the phrase endpoints and vice versa, and an account holding both is an
account somebody deliberately granted both to. That was not true of the first version
of this API — `BotIdentity` named one role in a constant, so every endpoint asked the
same question and the reach of every credential would have widened silently each time
v3 grew. Fixing it before the second resource shipped cost one migration; afterwards it
would have been a re-issue for every agent in existence. See
[database/db6.8.sql](../database/db6.8.sql).

`/api/v3` exists because v1 and v2 cannot be written to and could not be made
writable. Both are still served, unchanged, for the map and mobile consumers that
read them.

## Why not extend v1 or v2

| | v1 / v2 | v3 |
|---|---|---|
| verbs | `GET` only — every write inherits `AbstractRestfulController`'s 405 | `GET`, `PATCH` |
| authentication | none; `authenticateApiKey()` exists with every call site commented out, and the guards admit `null` | bearer JWT, account must hold `sch_api_bot` |
| identity | none, so a write would record `UpdatedBy = NULL` | the bot's user id, into `sch_changes` |
| representation | schema.org `CatholicChurch` — a projection for drawing maps | the editable field set, the same names the moderator form uses |
| locale prefix | `/en/api/v1/…` (SlmLocale strips it) | none; the API emits no localized prose |

The representation is the deciding one. Nothing in a `CatholicChurch` maps back onto
the columns an edit writes, so an agent could not read a shrine, change a field and
write it back. Adding writes to v2 would have meant a second, contradictory vocabulary
inside the same document.

## Getting a token

An agent is a normal account with one extra role.

1. **Create the account** at `/en/users/create` (administrators only). Fill in the
   address, then in **Roles select the one role this agent needs — `sch_api_bot` for
   shrines, `sch_api_translator` for translations — and deselect `sch_user`.** The form
   pre-selects the default roles, and `sch_user` opens the moderator edit form for
   every association. Leaving it ticked is the one mistake that undoes the whole
   separation below.

   Grant one role, not both, unless one agent genuinely does both jobs. An agent that
   holds both is an agent whose leaked token reaches everything, which is the state
   this arrangement exists to avoid.
2. **Issue a token** at `/en/users/:id/api-tokens`. Give it a label saying what the
   agent is for, press *Issue token*, and copy the JWT out of the message: it is shown
   once and is not recoverable, because only its identifier is stored. Six months by
   default (`juser.api_jwt_lifetime`).
3. Hand it to the agent as `Authorization: Bearer <jwt>`.

The button appears only for accounts holding a role named in
`juser.api_token_roles`, which at this site is `sch_api_bot` and `sch_api_translator`
and nothing else. **A role added to `/api/v3` has to be added to that list too** —
otherwise the account is refused everywhere until it has a token, and no screen will
issue one. Nothing errors; the button simply never appears. That restriction is the
point: unrestricted, the screen would mint six-month bearer tokens
for *any* account including another administrator's — credentials that outlive the
session that made them and that no role or password change revokes.

### Revoking

Same screen. Revocation takes effect on the token's **next request** and is per
token, not per account, so one compromised agent credential does not mean cutting off
every agent that account runs. Revoked tokens stay listed as a record; expired ones
are cleared a day after they lapse.

This works because since `db6.7` every issued token has a row in `user_api_token`
keyed by its `jti` claim, and the API refuses any token whose `jti` is missing from
that table or marked revoked. **Fail closed, not fail open** — "we have no record of
issuing this" and "this was revoked" are the same answer, which is what makes the
registry worth having. Only the identifier is stored, never the token.

### The email flow still works

An agent that can read its own mailbox can sign itself in, and the mobile apps
already do:

```
POST /api/v1/users/request-verification-token   identity=<email>
POST /api/v1/users/login-with-verification-token identity=<email>&token=<code>
   → { "jwt": "...", "expiration": "2027-02-09T12:00:00Z" }
```

Tokens issued this way are registered and revocable exactly like minted ones; they
show as *(self, by email)* on the token screen. It is no longer the recommended path
for a bot, because it forces you to run a real deliverable mailbox for an account
that has nobody to read it — and that mailbox is then a permanent credential-recovery
path into the bot account.

### The role is the security boundary

Two things gate a request: the token must be one we still vouch for (above), and the
account must hold **the role that endpoint's resource is gated on**. Neither role is
named by anything else on this site — no ACL guard, no route, no menu. Three
consequences, all deliberate:

- A leaked bot token reaches one resource of `/api/v3` and nothing else. Not "the API"
  — the resource. `sch_api_bot` opens the associations, `sch_api_translator` opens the
  phrases, and neither opens the other.
- A *human's* token cannot write through the API. This matters more than it looks:
  registration grants `sch_user`, and `route/association-edit` names `sch_user`, so
  **every registered account can already edit every association through the web
  form**. Reusing `sch_user` for bots would have made an automation credential a
  general-purpose site credential.

Note what the second one now means for translations specifically: `translator` — the
role `config/autoload/acl.global.php` names on `route/jtranslate` — opens the admin
translation GUI, and `sch_api_translator` deliberately is not it. An automation
credential that also opens an admin screen is the thing this whole arrangement exists
to avoid, and reusing the human role would have been the easy version of it.

Refusals are uninformative on purpose: missing, malformed, expired, revoked,
unregistered, unknown user and wrong role are all `401` with one message. The message
does name the role that endpoint wanted, which is the one thing an agent operator
genuinely cannot look up — it tells you nothing an attacker did not already know from
this document.

## Endpoints

### `GET /api/v3/schema`

Public — no token. The index of entities: read it first, and it tells you where
everything else is.

```jsonc
{
  "version": 3,
  "entities": {
    "association": { "schema": "/api/v3/schema/association", "collection": "/api/v3/associations",
                     "requiredRole": "sch_api_bot" },
    "phrase":      { "schema": "/api/v3/schema/phrase",      "collection": "/api/v3/phrases",
                     "requiredRole": "sch_api_translator" }
  }
}
```

This path answered the association contract directly until phrases became the second
entity. It is a directory now, and the contracts live one level down — an agent that
had learned "the schema is at `/api/v3/schema`" would otherwise have had no way to
discover a second one existed.

### `GET /api/v3/schema/{entity}`

Public — no token. The contract for one entity, **generated from the same
specification the web form and the `PATCH` endpoint validate with**, so it cannot
drift from what is actually enforced. It is the reason you should not need to read
anything else.

```jsonc
// GET /api/v3/schema/association
{
  "version": 3,
  "entity": "association",
  "requiredRole": "sch_api_bot",
  "fields": {
    "openingHoursHuman": { "required": false, "maxLength": 500, "filters": ["ToNull"] },
    "kind":              { "required": true,  "enum": ["sch-shrine", "sch-wayside-shrine", ...] },
    "country":           { "required": false, "maxLength": 6, "enum": ["AF", "AL", ...] },
    "geoPoint":          { "required": false, "constraints": ["GpsPoint"], "filters": ["ToGeoPoint"] }
  }
}
```

The enumerations are live: `kind` lists the kinds this database has, `parentId` every
association id including inactive ones. Validate locally against them rather than
discovering by rejection.

```jsonc
// GET /api/v3/schema/phrase — shaped differently, because the resource is
{
  "version": 3,
  "entity": "phrase",
  "requiredRole": "sch_api_translator",
  "writable": { "languages": ["es", "de", "pt", "it", "en"], "maxLength": 2000 },
  "filters":  { "textDomain": "…", "originRoute": "…", "search": "…",
                "untranslatedIn": "…", "translatedIn": "…" },
  "sideEffects": { "catalogs": "…", "batching": "…" }
}
```

An association has thirty-odd heterogeneous fields each with its own rules; a phrase
has one rule repeated across five languages. Describing the languages as `fields` would
have been this document's template applied to the wrong thing.

**The list is the merged configuration's, read at request time.** JTranslate's module
config names three locales and `config/autoload/jtranslate.global.php` appends `it_IT`,
so the real answer on this site is five including the key locale. Anything that
hardcodes it — including an agent — is wrong here today.

### Languages, not locales

This API addresses translations by **language code**: `de`, never `de_DE`. The region
subtag is an artefact of how catalogs are keyed internally — the column really does
hold `de_DE`, and the compiled catalog really is `de_DE.lang.php` — and none of that is
your problem. It is also what the site's own URLs have always used (`/de/shrines`), and
`test/Integration/SymfonyLocaleAliasTest` fails if the two ever name a language
differently.

**The locale form is refused, not accepted as a synonym.** `{"de_DE": "…"}` is a `422`
naming `de` as what would have worked. That is deliberate: accepting both would make
the key a translation is stored under depend on which spelling you happened to send,
and the two spellings would drift apart in the table with nothing to notice.

One consequence worth stating: a language code identifies a translation only while no
two configured locales share a primary subtag. If this site ever needs `pt_BR` *and*
`pt_PT`, `JTranslate\I18n\LanguageMap` throws rather than guessing, and this part of
the contract has to be redesigned. That is the intended failure — silently picking one
of them is the alternative.

## Associations

### `GET /api/v3/associations`

The collection. `?kind=sch-shrine` for shrines, `?kind=sch-wayside-shrine` for wayside
shrines. `?limit=` (default 100, max 500) and `?offset=` page it.

### `GET /api/v3/associations/{identifier}`

One association, e.g. `/api/v3/associations/SL100319A`. Returns an `ETag`.

```jsonc
{
  "identifier": "SL100319A",
  "kind": "sch-shrine",
  "fields": { "name": "Original Schoenstatt Shrine", "country": "DE", ... },
  "meta": { "updatedOn": "...", "updatedBy": 5, "etag": "W/\"639df94…\"", "url": "..." }
}
```

Every key of `fields` is a key you may send back. That is the round-trip guarantee.

### `PATCH /api/v3/associations/{identifier}`

A JSON object of any subset of the writable fields.

```bash
curl -X PATCH https://schoenstatt.link/api/v3/associations/SL100319A \
  -H "Authorization: Bearer $JWT" \
  -H "Content-Type: application/json" \
  -H "If-Match: $ETAG" \
  -d '{"openingHoursHuman": "Sun-Sat 9-11am, 12-8pm"}'
```

```jsonc
{ "changed": ["openingHoursHuman"], "association": { ...the new representation... } }
```

`changed` lists the fields that actually moved. Sending a value that is already stored
is a `200` with `"changed": []` and no write — so a polling agent does not fill
`sch_changes` with noise.

| status | meaning |
|---|---|
| `200` | applied (or nothing to apply) |
| `401` | no usable token — missing, expired, **revoked**, or the account lacks `sch_api_bot` |
| `404` | no association has that identifier |
| `412` | `If-Match` no longer matches — re-read and re-apply |
| `422` | the change would leave the association invalid, or names an unknown field |
| `405` | wrong verb; the `Allow` header lists the right ones |


### Validation is the moderator form's, exactly

A patch is **merged onto the stored record and the whole record validated**, by the
edit form's own `InputFilter` — not a copy of its rules, the same object graph. An
agent is refused precisely what a moderator is refused, with the same messages:

```jsonc
{ "error": { "status": 422, "message": "The association would not be valid after this change.",
             "messages": { "kind": { "notInArray": "The input was not found in the haystack" } } } }
```

Merging first is what makes a partial update possible at all — `name` and `kind` are
required, so a bare `{"openingHoursHuman": "…"}` would otherwise fail on two fields you
never mentioned.

The one rule an agent is *not* held to is the CSRF token, which presupposes a browser
session. `test/Integration/AssociationValidationParityTest` asserts that this is the
only difference, field by field and validator by validator.

An unknown field name is a `422` rather than being ignored: an agent that misspells a
field and gets a `200` will keep misspelling it forever, and the shrine it believes it
is maintaining silently never changes.

### What agents cannot do to associations

- **Create.** `POST` is not implemented. Creating an association decides its kind, its
  parent and its associated roles, and those are human decisions for now.
- **Delete.** Same.
- **Reach anything else.** The role opens no other door.

### Auditing association writes

Every field an agent changes is a row in `sch_changes` under the bot's user id, so
`/sm/view-changes` shows agent edits beside human ones and each is attributable and
revertible. That is the safety model: agent writes are not reviewed *before* they land,
they are recorded so they can be found afterwards.

## Phrases

The site's translatable strings and their translations. An agent reads a phrase, looks
at the page it came from, and writes a better translation.

### What a phrase is, and what is writable

A row in `trans_phrases` is not editorial content — it is a **key**. The translator
listener creates it the moment the site renders a string it has not seen before, and
the row exists to hang translations off. So `phrase` is read-only, and editing it
would not change what any page renders; it would orphan the row, because the next
render looks up the original string, misses, and inserts a second phrase with the
translations missing all over again. The web form marks the field readonly for the
same reason.

**The writable set is the locales.** Nothing else.

### `GET /api/v3/phrases`

The collection, and the reason this API is worth having over the admin GUI: the
filters reach the database rather than being applied after the paging decision.

| parameter | |
|---|---|
| `textDomain` | exact match, e.g. `Schoenstatt` |
| `originRoute` | exact match on the route the phrase was first seen on |
| `search` | substring of the source phrase (`%` and `_` are literal) |
| `untranslatedIn` | a language code — phrases with no usable translation in it |
| `translatedIn` | a language code — phrases that do have one |
| `limit`, `offset` | default 100, maximum 500 |

`?untranslatedIn=de&textDomain=Application` is the query an agent actually wants,
and it is a `WHERE` clause. An unknown parameter is a `422` rather than being ignored,
for the same reason an unknown field is: `?language=de` would otherwise return the
*unfiltered* collection with a `200`, and the agent would work through the wrong list
believing it was the right one.

"No usable translation" means no row **or** a row holding the empty string. Both exist
in this table and both mean the same thing to a translator.

### `GET /api/v3/phrases/{phraseId}`

One phrase. Returns an `ETag`.

```jsonc
{
  "phraseId": 10028,
  "textDomain": "Application",
  "phrase": "Access to entity denied.",
  "translations": {
    "de": { "text": null,                       "modifiedOn": null,  "modifiedBy": null },
    "en": { "text": "Access to entity denied.", "modifiedOn": "2023-06-18T11:01:15+00:00",
            "modifiedBy": 18 }
    // …one entry per writable language, always, whether or not it has a row
  },
  "context": { "originRoute": "sign-in-no-cookies",
               "url": "https://schoenstatt.link/en/sign-in-no-cookies" },
  "meta":    { "addedOn": "…", "etag": "W/\"5730983…\"", "url": "…/api/v3/phrases/10028" }
}
```

Every key of `translations` is a key you may send back. A language with no row is
present with `"text": null` rather than absent — an agent that has to distinguish "key
missing" from "key null" to find its work will get it wrong.

`modifiedOn` is there because staleness matters as much as absence: a translation
written in 2017 against a source string edited in 2023 is a different problem from a
missing one, and nothing else in the document says so.

### Context: browsing the page a phrase came from

`context.originRoute` is the laminas route name the phrase was first rendered on, and
`context.url` is the page — absolute, and on the host you just called, so a capsule
answers capsule URLs. Fetch it over ordinary HTTP and read it as a visitor would.

**`context.url` is often `null`, and that is the honest answer.** `origin_route` is a
route *name*, and most of this site's routes take parameters: measured on this
database, 5,111 of 6,874 phrases come from `blog/blog-post`, which cannot be turned
into a URL without knowing which post. Guessing — substituting an arbitrary id, or
dropping the parameter segment — would hand you a URL that 404s or, worse, shows a
different page than the phrase came from, with no way for you to tell. A null says
"you cannot see this one in situ", which is true and actionable.

The URL is always the **English** one. The phrase is the English source string, so the
English page shows it in the words you are reading; any other prefix would show the
page already translated, which is the one thing you must not mistake for the source.

### `PATCH /api/v3/phrases/{phraseId}`

A JSON object of language code to translation.

```bash
curl -X PATCH https://schoenstatt.link/api/v3/phrases/10028 \
  -H "Authorization: Bearer $JWT" \
  -H "Content-Type: application/json" \
  -H "If-Match: $ETAG" \
  -d '{"de": "Zugriff auf Objekt verweigert."}'
```

```jsonc
{ "changed": ["de"], "phrase": { ...the new representation... } }
```

Same contract as an association PATCH: `changed` lists the languages that actually
moved, re-sending stored text is a `200` with `"changed": []` and no write, an unknown
language is a `422` naming the writable ones, and `If-Match` is honoured with a `412`.

Two differences worth knowing:

- **No merge step.** An association patch is merged onto the stored record before
  validation because `name` and `kind` are required there. Nothing here is required
  except the phrase id, which comes from the URL, so a patch naming one locale is
  already a complete submission.
- **An empty string means "leave this language alone", not "blank it".**
  `TranslationsTable::updatePhrase()` skips falsy values, and the web form behaves
  identically — an empty textarea is how a translator says "not my language". There is
  therefore **no way to remove a translation** through either surface. That is the
  form's behaviour, inherited deliberately rather than diverged from; if it should
  change, it should change for both.

### `PATCH /api/v3/phrases` — the batch

```jsonc
{ "phrases": { "10028": { "de": "…" }, "6197": { "es": "…", "pt": "…" } } }
```

```jsonc
{
  "results": {
    "10028": { "ok": true,  "changed": ["de"] },
    "6197":  { "ok": false, "status": 422, "message": "The translation would not be valid.", ... }
  },
  "summary": { "received": 2, "applied": 1, "failed": 1 }
}
```

**Use this for more than a handful of writes.** Every single-phrase write recompiles
the whole set of translation catalogs (below); the batch recompiles once at the end.
Measured in the capsule: **200 phrases in 1.25 s** as one batch, against ~0.25 s each
one at a time — five individual PATCHes take 1.26 s where the same five as a batch
take 0.24 s. At most 200 phrases per batch, so a batch cannot run into
`max_execution_time` half written.

Every id is reported separately because a batch's interesting outcome is the partial
one. One bad entry fails that entry and nothing else; refusing the whole batch would
mean an agent working through a text domain is stopped by its own worst guess and has
to bisect to find it.

There is no `If-Match`. A conditional request is defined over one resource and a batch
is not one; if you need lost-update protection for a particular phrase, PATCH that
phrase.

### A write is not finished when the row is written

The site renders from compiled `language/<TextDomain>/<locale>.lang.php` catalogs, not
from the table, so a translation that is only in the database is invisible. Every
write here recompiles them, exactly as the translator's own edit screen does.

When the files cannot be written the response carries a `warning` and the write still
succeeded:

```jsonc
{ "changed": ["de"], "phrase": { ... },
  "warning": "The translations were saved, but the compiled catalogs could not be written, so the site will keep showing the old text until that is fixed." }
```

That is not a failure to retry. The row is committed; retrying writes nothing and
changes nothing. It means somebody has to fix the filesystem.

### Validation is the translator form's, exactly

Same guarantee as associations, and the same mechanism: the API takes
`JTranslate\Form\EditPhraseForm`'s own `InputFilter`, not a copy of its rules.
`test/Integration/PhraseValidationParityTest` fails if that stops being true.

The rules are thin — each locale is optional, trimmed, and bounded at 2000 characters
to match the column — and that thinness is pinned deliberately. There is no rule about
placeholders, markup, or length relative to the source. An agent that sends a
2001-character translation is refused with the translator's own
`stringLengthTooLong`; an agent that sends a plausible mistranslation is not, and no
validator was ever going to catch that. What catches it is that the write is
attributable.

### Auditing translation writes

`trans_translations.modified_by` carries the bot's user id, so `/admin/translations`
shows agent edits beside human ones with a name against each.

Note what this is **not**: unlike associations, translations have no `sch_changes`
history, so a write here is not revertible from the UI and the previous text is gone.
That is a property of `trans_translations`, not of this API — the web form has always
worked the same way — but it is the reason to prefer a filtered read and a considered
batch over a broad speculative rewrite.

### What agents cannot do to phrases

- **Create a phrase.** Phrases are discovered by the site rendering them; inventing one
  would create a key nothing looks up.
- **Delete a phrase.** `deletePhrase()` cascades its translations away and there is no
  history to recover them from.
- **Blank a translation.** See above — empty means "skip", on both surfaces.
- **Address a translation by locale.** `de_DE` is a `422`; the API speaks `de`.
- **Read another project's phrases.** `trans_phrases` is shared with two other
  projects, and every read here is scoped to this one in the `WHERE` clause. A phrase
  id belonging to `patres` is a `404`, not a leak.


## Concurrency

Both resources work the same way. `GET` returns an `ETag`; a single-resource `PATCH`
honours `If-Match` and answers `412` when the record has moved. Omitting `If-Match` is
permitted and means a blind write. The batch endpoint does not honour it at all — a
conditional request is defined over one resource.

**Send it.** Two agents editing the same shrine within one second is exactly the case
the tag exists for, and `UpdatedOn` has only second resolution — the tag is a hash of
the document, so it moves when and only when something you can see changed. A phrase's
tag covers its translations and nothing else, deliberately: `context.url` is derived
from the route tree, so including it would let a routing change invalidate every
agent's in-flight `If-Match` for a reason no agent could see or act on.

One caveat that will bite anyone comparing tags by hand: Apache's `mod_deflate`
appends `-gzip` to the ETag of a compressed response, so the tag you receive is
`W/"639df94…-gzip"` while the tag the server computed is `W/"639df94…"`. The API
normalizes both sides — echo back whatever you were given and it will match.

## Where the code is

| file | what |
|---|---|
| `config/symfony/routes.php` | the routes, and why they declare themselves open |
| `src/Api/BotIdentity.php` | token → user id → role check; the role is a parameter, not a constant |
| `src/Http/AuthorizationHeader.php` | why the `Authorization` header needs finding |
| `src/Controller/Api/AbstractApiController.php` | what every endpoint does identically: the 401, the ETag, the paging bounds |
| `src/Controller/Api/AssociationsV3Controller.php` | the association endpoints |
| `src/Controller/Api/PhrasesV3Controller.php` | the phrase endpoints, single and batch |
| `src/Controller/Api/ApiSchemaController.php` | `/api/v3/schema` and `/api/v3/schema/{entity}` |
| `src/Schoenstatt/Association/AssociationResource.php` | the representation, merge and ETag |
| `src/Schoenstatt/Association/AssociationValidator.php` | the form's filter, headless |
| `src/Schoenstatt/Association/AssociationInputFilterSpec.php` | the rules themselves |
| `src/JTranslate/Phrase/PhraseResource.php` | the phrase representation, ETag and change detection — and the language↔locale conversion on both edges |
| `module/JTranslate/src/I18n/LanguageMap.php` | the mapping itself, and why an ambiguous configuration throws |
| `src/JTranslate/Phrase/PhraseValidator.php` | the translator form's filter, headless — and the static-adapter seam |
| `module/JTranslate/src/Model/TranslationsTable.php` | `getPhrasePage()`, `countPhrases()`, `getPhraseById()`; the project scoping |
| `config/autoload/juser.global.php` | `api_token_roles` — which accounts a token can be issued *for* |
| `database/db6.6.sql` | the `sch_api_bot` role |
| `database/db6.7.sql` | `user_api_token`, and why a token is refused unless vouched for |
| `database/db6.8.sql` | the `sch_api_translator` role, and why one role for the whole API stopped working |
| `module/JUser/src/Service/ApiTokenService.php` | the one place a JWT is minted and recorded |
| `module/JUser/src/Model/ApiTokenTable.php` | the registry; uncached, on purpose |
| `module/JUser/src/Controller/UsersController.php` | `apiTokensAction`, `revokeApiTokenAction` |
| `test/Smoke/ApiV3SmokeTest.php` | the association surface over HTTP |
| `test/Smoke/PhrasesApiV3SmokeTest.php` | the phrase surface, and both roles refused on each other's resource |
| `test/Smoke/ApiTokenAdminSmokeTest.php` | issue in the browser → use against the API → revoke → refused |
| `test/Integration/AssociationValidationParityTest.php` | the association rules are the form's |
| `test/Integration/PhraseValidationParityTest.php` | the translation rules are the form's |

## Is it live?

The v3 routes are served by the **Symfony** front controller. Until 2026-08-10 production
ran the laminas one for ordinary traffic, so v3 was reachable only behind
`sl_symfony_canary=1`. The site-wide flip is now committed in `public/.htaccess`, which
means v3 becomes generally reachable **on the next deploy** — an agent sends no cookie
and does not have to. Until that deploy the canary is still the only way in. See
[DEPLOY.md](DEPLOY.md#flipping-the-symfony-kernel-on-globally).

Verified on production 2026-08-09:

```bash
# 200, the real schema
curl -H 'Cookie: sl_symfony_canary=1' https://schoenstatt.link/api/v3/schema

# 401 — the endpoint is reachable and the token gate works
curl -H 'Cookie: sl_symfony_canary=1' https://schoenstatt.link/api/v3/associations

# without the cookie: 302 to /en/… and then laminas' 404, which is correct
curl -i https://schoenstatt.link/api/v3/schema
```

The phrase endpoints have **not** been verified against production; they were added
afterwards and exist only in the capsule so far. The same three checks apply to
`/api/v3/schema/phrase` and `/api/v3/phrases`, and `database/db6.8.sql` has to be
applied first or the role the endpoints ask for does not exist and every request 401s
— exactly as `db6.6.sql` had to precede the association endpoints.

**An agent could not use v3 until `SYMFONY_KERNEL` was flipped globally**, because an
agent will not send a canary cookie — and should not be asked to, since the cookie is a
staging device, not an API contract.

That flip is one line in `public/.htaccess` and it is committed. Its procedure, two
checklists and three rollbacks are in
[DEPLOY.md](DEPLOY.md#flipping-the-symfony-kernel-on-globally). Note the v3 prerequisite
it names — `database/db6.6.sql` and `database/db6.8.sql` applied and at least one bot
account holding the relevant role, or **every agent request 401s the moment the endpoints
become reachable**. That is the one ordering mistake this change makes possible.
