<?php

declare(strict_types=1);

namespace App\JTranslate\Phrase;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JTranslate\I18n\LanguageMap;

use function count;
use function is_scalar;
use function is_string;
use function json_encode;
use function ksort;
use function md5;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * One translation phrase as `/api/v3/phrases` shows it, and as it accepts it back.
 *
 * ## The phrase is not writable; its translations are
 *
 * `trans_phrases.phrase` is the English source string, and it is not editorial
 * content — it is a *key*. It is discovered by the translator listener at the moment
 * the site renders text it has not seen before, and the row exists to hang
 * translations off. Editing it would not change what the site renders; it would
 * orphan the row, because the next render would look up the original string, miss,
 * and insert a second phrase with the same translations missing. The web form knows
 * this and marks the field readonly. So the writable set here is exactly the locales,
 * and `phrase` is offered read-only for context.
 *
 * ## Shape
 *
 * ```jsonc
 * {
 *   "phraseId": 6198,
 *   "textDomain": "Application",
 *   "phrase": "Are you a developer?",
 *   "translations": {
 *     "de_DE": { "text": "Bist du ein Entwickler?", "modifiedOn": "…", "modifiedBy": 11 },
 *     "es_ES": { "text": null, "modifiedOn": null, "modifiedBy": null }
 *   },
 *   "context": { "originRoute": "welcome", "url": "https://schoenstatt.link/en/welcome" },
 *   "meta":    { "addedOn": "…", "etag": "W/\"…\"", "url": "…/api/v3/phrases/6198" }
 * }
 * ```
 *
 * Every key of `translations` is a key a PATCH may send, with a string for a value.
 * That is the round-trip guarantee, and it is why the locales are a map of objects
 * rather than a flat map of strings: an agent choosing what to work on wants to know
 * a translation is stale as much as it wants to know one is absent, and `modifiedOn`
 * is the only thing that says so.
 *
 * A locale with no row is present with `"text": null` rather than absent. An agent
 * that has to distinguish "this key is missing" from "this key is null" to find its
 * work will get it wrong, and the set of locales is a fixed four.
 *
 * ## Context is a route name, and often only a route name
 *
 * `origin_route` records where a phrase was first seen, which is the only clue anyone
 * gets about how it is used. It is a laminas *route name*, not a path, and many of
 * this database's routes take parameters: measured here after the 2026-08-10 cleanup,
 * the largest sources are `text` (219 of 1,783 phrases), `associations/association`
 * (152) and `associations/association/edit` (151), none of which can be turned into a
 * URL without knowing *which* text or association.
 *
 * So `context.url` is filled in only where the route assembles with no parameters,
 * and is null otherwise. Guessing — substituting some arbitrary id, or dropping the
 * parameter segment — would hand an agent a URL that 404s or, worse, shows a
 * different page than the one the phrase came from, and the agent has no way to tell.
 * A null says "you cannot see this one in situ", which is true and actionable; a
 * wrong URL says nothing and is believed.
 */
final class PhraseResource
{
    /**
     * @param array<string, mixed> $phrase a record as TranslationsTable::getPhraseById() returns
     * @param LanguageMap $languages the writable set, addressed by language code
     * @param string|null $contextPath the resolved page path, or null when the route needs parameters
     * @param string $baseUrl scheme and host, from the request this is answering
     * @return array<string, mixed>
     */
    public static function represent(
        array $phrase,
        LanguageMap $languages,
        ?string $contextPath = null,
        string $baseUrl = ''
    ): array {
        $translations = self::translations($phrase, $languages);
        $base         = rtrim($baseUrl, '/');

        return [
            'phraseId'     => isset($phrase['phraseId']) ? (int) $phrase['phraseId'] : null,
            'textDomain'   => $phrase['textDomain'] ?? null,
            'phrase'       => $phrase['phrase'] ?? null,
            'translations' => $translations,
            'context'      => [
                'originRoute' => $phrase['originRoute'] ?? null,
                //Absolute, because the point of this key is that an agent fetches it —
                //a bare `/en/` is not something an HTTP client can be handed. The host
                //comes from the request rather than being written down, so a capsule
                //answers capsule URLs and production answers production ones. The
                //association resource hardcodes `https://schoenstatt.link`, which is
                //true there and would be a lie here: an agent following a hardcoded
                //context URL out of a staging response would read the live site and
                //conclude the translation it is reviewing is already deployed.
                'url'         => null === $contextPath ? null : $base . $contextPath,
            ],
            'meta'         => [
                'addedOn' => self::scalarize($phrase['addedOn'] ?? null),
                //Null for a live phrase, a UTC timestamp for a retired one. Needed because
                //the collection hides retired rows by default: as soon as a caller asks for
                //them (`?onlyRetired=1`, which is how it audits its own retirements) a
                //retired document is otherwise indistinguishable from a live one.
                //
                //In `meta` rather than at the top level because it is a fact about the row's
                //place in the *worklist*, not about the phrase or its translations — the same
                //reason it does not enter the ETag. A retirement changes nothing a
                //conditional write is protecting.
                'retiredOn' => self::scalarize($phrase['retiredOn'] ?? null),
                'etag'    => self::etag($translations),
                'url'     => isset($phrase['phraseId'])
                    ? sprintf('%s/api/v3/phrases/%d', $base, (int) $phrase['phraseId'])
                    : null,
                //A link, not the history itself. The history of a well-worked phrase is
                //longer than the phrase, most callers never want it, and a field that is
                //usually a long empty list is one every caller learns to skip. Following
                //the link is the opt-in.
                'history' => isset($phrase['phraseId'])
                    ? sprintf('%s/api/v3/phrases/%d/history', $base, (int) $phrase['phraseId'])
                    : null,
            ],
        ];
    }

    /**
     * `GET /api/v3/phrases/{id}/history` — what writing to this phrase has destroyed.
     *
     * Newest first, one entry per overwrite or retraction, never one per write: filling
     * an empty language destroys nothing and appears here not at all. An empty list is
     * therefore the normal answer and means "nothing has ever been lost here", not
     * "no records kept".
     *
     * `language`, not `locale`, for the reason {@see translations()} gives: the record
     * is keyed the way the table stores it and the document is keyed the way a caller
     * outside the application means it.
     *
     * The `phraseId` at the top is the one that was asked for. Each entry carries its
     * own, which can differ: the thread is keyed on the phrase's hash, so it survives a
     * merge or a delete-and-rediscover, and entries written before one of those name the
     * row that existed then. An agent that only wants "what happened to this string"
     * should ignore the per-entry id; one reconstructing events needs it.
     *
     * ## Several retirements in one thread is correct, not a duplicate
     *
     * The per-entry id also differs for a reason that is not churn at all: `UNIQUE
     * (project, text_domain, phrase_hash)` means the same string in two text domains is
     * **two live rows sharing one hash**, which is routine — it is what the breadcrumb's
     * two-domain lookup produces, a pair at a time. Each row is its own place on the
     * worklist, so each is retired separately and writes its own `retire` entry; and
     * because the thread is keyed on the hash, a read from *either* id returns *both*.
     *
     * So two `retire` entries carrying two different notes is the expected answer for a
     * string that existed twice, and it does not mean a retry recorded twice — that cannot
     * happen, since retiring an already-retired row writes nothing. `textDomain` and
     * `phraseId` on the entry are how a reader tells which row each judgement was about,
     * and filtering on them is how a caller narrows a thread to one row.
     *
     * The corollary matters more than the display: **retiring one row does not retire its
     * sibling.** A caller clearing a string has to retire every row of it, or the string
     * keeps asking for work through the domain it did not touch.
     *
     * @param list<array<string, mixed>> $rows from TranslationsTable::getTranslationHistory()
     * @return array<string, mixed>
     */
    public static function representHistory(
        int $phraseId,
        array $rows,
        LanguageMap $languages,
        string $baseUrl = '',
        ?string $language = null
    ): array {
        $base    = rtrim($baseUrl, '/');
        $entries = [];
        foreach ($rows as $row) {
            $locale = (string) ($row['locale'] ?? '');
            //`''` is not a locale: it marks an entry about the *phrase* rather than a
            //language, which so far means a retirement. Exposed as a null `language`
            //rather than an empty string, so a caller branching on it cannot mistake it
            //for a language it failed to recognise.
            $entries[] = [
                'language'    => '' === $locale ? null : ($languages->languageFor($locale) ?? $locale),
                //The text that was destroyed. This is the whole point of the resource:
                //`trans_translations` keeps no copy, so before this existed a wrong
                //edit was unrecoverable and the tooling on this side had to be sure
                //rather than able to correct.
                'previous'    => $row['old_translation'] ?? null,
                //'update' — something replaced it. 'retract' — something deleted it and
                //the language is empty now. 'retire' — the *phrase* left the translator's
                //worklist because the application knows nothing renders it any more; it
                //has no language and no previous text, and `note` is the whole content.
                'operation'   => $row['operation'] ?? null,
                //The domain the row belonged to, and the phrase row it was attached to
                //at the time. Neither is part of the thread key — see
                //M006CreateTranslationHistory — but both are what somebody
                //reconstructing events will ask for next.
                'textDomain'  => $row['text_domain'] ?? null,
                'phraseId'    => self::intOrNull($row['translation_phrase_id'] ?? null),
                //Why, from whoever replaced it — up to 255 characters sent as `_note`
                //on the PATCH. Null for a write that offered no reason, and for every
                //write made through the admin GUI, which has no such field. Read in
                //order these are a conversation: an agent that reverses another's choice
                //says why here, against the version it removed.
                'note'        => $row['notes'] ?? null,
                'writtenBy'   => self::intOrNull($row['written_by'] ?? null),
                'writtenOn'   => self::scalarize($row['written_on'] ?? null),
                'replacedBy'  => self::intOrNull($row['replaced_by'] ?? null),
                'replacedOn'  => self::scalarize($row['replaced_on'] ?? null),
            ];
        }

        return [
            'phraseId' => $phraseId,
            'language' => $language,
            'history'  => $entries,
            'meta'     => [
                'count' => count($entries),
                'url'   => sprintf('%s/api/v3/phrases/%d', $base, $phraseId),
            ],
        ];
    }

    private static function intOrNull(mixed $value): ?int
    {
        return null === $value || '' === $value ? null : (int) $value;
    }

    /**
     * The translations, **keyed by language code**, one entry per configured language
     * whether or not a row exists.
     *
     * `de`, not `de_DE`. The record this reads is keyed by locale because that is what
     * `trans_translations` stores; the document this writes is keyed by language because
     * that is what a caller outside the application means. The translation happens here,
     * at the boundary, and nowhere else.
     *
     * @param array<string, mixed> $phrase
     * @return array<string, array<string, mixed>>
     */
    public static function translations(array $phrase, LanguageMap $languages): array
    {
        $document = [];
        foreach ($languages->languages() as $language) {
            $locale = (string) $languages->localeFor($language);
            $text   = $phrase[$locale] ?? null;
            $document[$language] = [
                //'' and null both mean "nobody has translated this". The table holds
                //both — the column is NOT NULL, so a blanked translation is an empty
                //string while an untouched locale has no row at all — and an agent
                //should not have to know which of the two it is looking at.
                'text'       => is_scalar($text) && '' !== $text ? (string) $text : null,
                'modifiedOn' => self::scalarize($phrase[$locale . 'ModifiedOn'] ?? null),
                'modifiedBy' => isset($phrase[$locale . 'ModifiedById'])
                    ? (int) $phrase[$locale . 'ModifiedById']
                    : null,
            ];
        }

        ksort($document);

        return $document;
    }

    /**
     * The ETag for a phrase, computed over the translations alone.
     *
     * Deliberately not over the whole representation. `context.url` is derived from
     * the route tree and `meta.addedOn` never moves, so including them would let a
     * routing change invalidate every agent's in-flight `If-Match` for no reason an
     * agent could see or act on. The tag covers exactly what a PATCH can change, which
     * is what makes 412 mean "someone else wrote here" rather than "something,
     * somewhere, is different".
     *
     * @param array<string, array<string, mixed>> $translations
     */
    public static function etag(array $translations): string
    {
        return 'W/"' . md5(json_encode($translations, JSON_THROW_ON_ERROR)) . '"';
    }

    /**
     * The languages a patch actually asks to change, ignoring those already holding the
     * submitted text.
     *
     * Takes a locale-keyed patch — the shape the write itself uses — and answers in
     * language codes, because `changed` is part of the response and the response speaks
     * languages. Reported back so an agent can tell "I changed three languages" from "I
     * sent three and two were already right", which is the difference between a useful
     * write and a catalog recompile that changed nothing.
     *
     * @param array<string, mixed> $phrase
     * @param array<string, mixed> $patch keyed by locale
     * @return list<string> language codes
     */
    public static function changedLanguages(array $phrase, array $patch, LanguageMap $languages): array
    {
        $changed = [];
        foreach ($patch as $locale => $text) {
            $current = $phrase[$locale] ?? null;
            //Strict: a translation is a string, and there is no pair of distinct
            //strings a loose comparison should call equal. '0' and 0 do not both occur
            //here the way they do on an association's checkbox columns.
            if ((string) $current !== (string) $text) {
                $changed[] = $languages->languageFor((string) $locale) ?? (string) $locale;
            }
        }

        return $changed;
    }

    /**
     * The submission `TranslationsTable::updatePhrase()` expects.
     *
     * It reads `$data[$locale]` and nothing else — every identifier that decides which
     * row is written comes from the record it loads itself, which is the security
     * property documented on that method. So this builds the locale keys and no `*Id`
     * keys at all, and an agent cannot steer a write at another phrase's row even in
     * principle.
     *
     * **The keys are translated back to locales here.** The caller sends `de`; the form
     * and `updatePhrase()` both work in `de_DE`, because that is what
     * `trans_translations.locale` holds. This is the one place that conversion happens
     * on the way in, mirroring translations() on the way out — a language code must
     * never reach the database and a locale must never reach the caller.
     *
     * Unlike the association API this does **not** merge the unpatched languages back
     * in. updatePhrase() skips any locale the submission does not carry, so a partial
     * patch is already partial, and re-sending stored text would only produce work for
     * the change detector to discard.
     *
     * @param array<string, mixed> $patch keyed by language code
     * @return array<string, mixed> keyed by locale, plus phraseId
     */
    public static function submission(int $phraseId, array $patch, LanguageMap $languages): array
    {
        $data = ['phraseId' => $phraseId];
        foreach ($patch as $language => $text) {
            $locale = $languages->localeFor((string) $language);
            if (null === $locale) {
                //Unreachable: the controller refuses an unknown language with a 422
                //before it gets here. Skipped rather than passed through, because a
                //language code arriving in the form's data would be validated as an
                //unknown field and the 422 would name the wrong thing.
                continue;
            }
            $data[$locale] = $text;
        }

        return $data;
    }

    /**
     * A stored timestamp as ISO-8601 UTC.
     *
     * Two shapes arrive: `{locale}ModifiedOn` is already a DateTime, because
     * hydratePhrases() parses it; `addedOn` is the raw `Y-m-d H:i:s` string the column
     * holds, because nothing needed it parsed before. Both are UTC — the table stores
     * UTC throughout — and both come out in the one format the rest of v3 emits, so an
     * agent comparing an association's `updatedOn` with a phrase's `modifiedOn` is
     * comparing like with like rather than discovering the difference at parse time.
     */
    private static function scalarize(mixed $value): mixed
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:sP');
        }
        if (is_string($value)) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

            return false !== $parsed ? $parsed->format('Y-m-d\TH:i:sP') : $value;
        }

        return is_scalar($value) ? $value : null;
    }
}
