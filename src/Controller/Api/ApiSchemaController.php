<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BotIdentity;
use App\Laminas\ServiceBridge;
use JTranslate\Form\PhraseValidator;
use JTranslate\Model\TranslationsTable;
use App\Schoenstatt\Association\AssociationFieldDomains;
use App\Schoenstatt\Association\AssociationInputFilterSpec;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_values;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function strrpos;
use function substr;

/**
 * `GET /api/v3/schema` and `/api/v3/schema/{entity}` — what an agent may write, and
 * what will be accepted.
 *
 * These are the endpoints that make v3 "more straightforward" in the way the brief
 * asked for. Without them an agent author reads PHP source, or guesses, or discovers
 * the rules one 422 at a time; the last of those is what actually happens, and it
 * means the rules are learned by making bad writes against production.
 *
 * ## It is generated, never written
 *
 * Every field, bound and enumeration below is read out of the same object the
 * corresponding web form and PATCH endpoint validate with —
 * {@see AssociationInputFilterSpec} for associations,
 * {@see PhraseValidator} for phrases. A hand-maintained schema document is a second
 * source of truth that starts correct and drifts, and the failure is silent in the
 * worst direction: an agent trusts a documented constraint that no longer holds, or
 * stops sending a field that is now required.
 *
 * The enumerations are the live ones — `kind` lists the association kinds this
 * database actually has, `country` the countries `CountriesInfo` knows, `parentId`
 * every association id including inactive ones, and the phrase schema's locales are
 * the ones `updatePhrase()` will actually iterate. That is why the association
 * response can be large, and why it is worth having: an agent can validate a parent
 * locally instead of discovering by rejection.
 *
 * ## Two levels, because there are two entities
 *
 * The index at `/api/v3/schema` names them and says which role each needs; the
 * contract lives one level down. It was a single document while associations were the
 * only resource, and the shape did not survive the second one — see index().
 *
 * ## Open on purpose
 *
 * No bearer token, on either level. They describe the shape of the data and reveal no
 * data — the kinds and countries are already public through
 * `/api/v1/associations/findByKind` and the shrine index, and the phrase schema names
 * four locale codes — and requiring a credential to *learn how to use a credential* is
 * the kind of friction that produces agents built against guesses.
 *
 * Note what this means for the phrase schema specifically: it lists the locales and
 * the length bound, and it does not list text domains or any phrase. The phrases
 * themselves are behind the token, because `trans_phrases` is shared with other
 * projects and its rows are whatever those projects render.
 */
final class ApiSchemaController
{
    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * `GET /api/v3/schema` — the index, naming every entity v3 exposes.
     *
     * Added when phrases became the second entity. Before that the path answered the
     * association contract directly, which was right while there was one of them and
     * a dead end afterwards: an agent that had learned "the schema lives at
     * /api/v3/schema" would have had no way to discover that a second one existed.
     * Rather than bolt the phrase fields into the same document under a second key —
     * which would have made `entity: "association"` a lie — the path became a
     * directory and the contracts moved one level down.
     */
    public function index(): Response
    {
        return new JsonResponse([
            'version'     => 3,
            'description' => 'The resources /api/v3 exposes. Fetch an entity\'s schema before writing to it; '
                . 'each is generated from the same specification its endpoints validate with, so it '
                . 'cannot drift from what is actually enforced.',
            'entities'    => [
                'association' => [
                    'schema'       => '/api/v3/schema/association',
                    'collection'   => '/api/v3/associations',
                    'requiredRole' => BotIdentity::REQUIRED_ROLE,
                    'description'  => 'Shrines, wayside shrines and the other associations in the database.',
                ],
                'phrase'      => [
                    'schema'       => '/api/v3/schema/phrase',
                    'collection'   => '/api/v3/phrases',
                    'requiredRole' => BotIdentity::TRANSLATOR_ROLE,
                    'description'  => 'The translatable phrases the site renders, and their translations.',
                ],
            ],
        ]);
    }

    /** `GET /api/v3/schema/{entity}`. */
    public function entity(Request $request): Response
    {
        return match ($request->attributes->get('entity')) {
            'association' => $this->association(),
            'phrase'      => $this->phrase(),
            default       => new JsonResponse([
                'error' => [
                    'status'  => Response::HTTP_NOT_FOUND,
                    'message' => 'No such entity. GET /api/v3/schema lists them.',
                ],
            ], Response::HTTP_NOT_FOUND),
        };
    }

    private function association(): Response
    {
        $domains = AssociationFieldDomains::fromServices($this->laminas->get(...));
        $spec    = new AssociationInputFilterSpec($domains);

        $fields = [];
        foreach ($spec->toArray() as $name => $rules) {
            if ('associationId' === $name || 'security' === $name) {
                //Not a caller's to set: the identifier addresses the record, and the
                //CSRF token belongs to the browser form. See AssociationResource.
                continue;
            }
            $fields[(string) $name] = self::describe(is_array($rules) ? $rules : []);
        }

        return new JsonResponse([
            'version'      => 3,
            'entity'       => 'association',
            'requiredRole' => BotIdentity::REQUIRED_ROLE,
            'description'  => 'Fields an agent may read and write on an association. '
                . 'PATCH /api/v3/associations/{identifier} with a JSON object of any subset of these; '
                . 'the patch is merged onto the stored record and the result validated as a whole, '
                . 'by the same rules the moderator web form uses.',
            'concurrency'  => [
                'etag'    => 'GET returns a weak ETag over the field document.',
                'ifMatch' => 'PATCH honours If-Match and answers 412 when the record has moved. '
                    . 'Omitting it is permitted and means a blind write.',
            ],
            //One field of this resource reaches into another, which nothing about a
            //field's own bounds and filters could tell you.
            'sideEffects'  => [
                'publicNotes' => 'This field is translated: the site runs it through the '
                    . 'translator once per locale, which is how a pilgrim reading in Italian gets '
                    . 'the description of a shrine in Italian. Two consequences of replacing it. '
                    . 'The new text is a different phrase, so it renders as itself in every '
                    . 'language until somebody translates it — nothing shows stale text, but the '
                    . 'description is untranslated again. And the *old* text\'s phrase is retired '
                    . 'automatically, because nothing renders it any more; that is reversible, its '
                    . 'translations are kept, and it is recorded in the phrase\'s history as a '
                    . '`retire` naming this association. See GET /api/v3/schema/phrase.',
                'names'       => 'An association\'s name and internal name are translated too, but '
                    . 'only when the record says they should be — the `isNameTranslateable` and '
                    . '`isInternalNameTranslateable` flags. The other free-text fields '
                    . '(`openingHoursHuman`, `eventsHuman`, `adminNotes`, the JSON specifications) '
                    . 'are never translated.',
            ],
            'fields'       => $fields,
        ]);
    }

    /**
     * The phrase contract.
     *
     * Shaped differently from the association one because the resource is: an
     * association has thirty-odd heterogeneous fields, each with its own rules, and a
     * phrase has one rule repeated across four locales. Describing the locales as
     * `fields` would have been a template applied to the wrong thing — what an agent
     * actually needs to know here is which locales are writable, that the source
     * phrase is not, and how to ask for the subset of 6,874 phrases it should work on.
     *
     * Built from PhraseValidator, i.e. from the translator form's own rules, so the bound
     * below is the bound enforced.
     */
    private function phrase(): Response
    {
        /** @var PhraseValidator $validator */
        $validator = $this->laminas->get(PhraseValidator::class);
        $languages = $validator->languages();
        $locales   = $validator->writableLocales();

        //Asked of the validator rather than dug out of an assembled filter's validator
        //chain, which is what this did until the input filter became a specification.
        //That the bound is a StringLength, and that translations are keyed by locale
        //rather than by language, are JTranslate's business and not this endpoint's.
        $maxLength = $validator->maxTranslationLength();

        return new JsonResponse([
            'version'      => 3,
            'entity'       => 'phrase',
            'requiredRole' => BotIdentity::TRANSLATOR_ROLE,
            'description'  => 'The phrases this site renders, and their translations. '
                . 'PATCH /api/v3/phrases/{phraseId} with a JSON object of language code to translation, '
                . 'or PATCH /api/v3/phrases with a `phrases` object to write many at once. '
                . 'Translations are validated by the same rules the translator web form uses.',
            'writable'     => [
                'languages' => $languages->languages(),
                'maxLength' => is_numeric($maxLength) ? (int) $maxLength : null,
                //The one key of a PATCH body that is not a language. Named from the
                //constant the controller strips it by, so the two cannot drift.
                'note'      => [
                    'key'       => PhrasesV3Controller::NOTE_KEY,
                    'maxLength' => TranslationsTable::NOTE_LENGTH,
                    'purpose'   => 'Why you are replacing a translation. Attached to the history '
                        . 'entries this write produces — one per translation it destroys, none if '
                        . 'it only fills gaps, since a note on a write that overwrites nothing has '
                        . 'no version to explain. Longer than the limit is trimmed rather than '
                        . 'refused; a non-string is a 422.',
                ],
                //The destructive key, published with the same shape as the note so a caller
                //reading `writable` finds it without having to be refused first.
                'retract'   => [
                    'key'     => PhrasesV3Controller::RETRACT_KEY,
                    'value'   => 'a list of language codes, e.g. ["de", "pt"]',
                    'purpose' => 'Remove a translation. The only way to do it, and reachable only '
                        . 'from this API — no browser can post one. Recorded in the history as a '
                        . '`retract`, so the text is recoverable afterwards. A language cannot be '
                        . 'written and retracted in the same request; that is a 422 rather than a '
                        . 'guess at which half was meant. Sending it as "" is not writing it, so a '
                        . 'caller that posts every language every time can still retract one.',
                    //Published on the key itself, because this is where a caller looking for
                    //"how do I get rid of this row" arrives, and it is the wrong answer.
                    'notRetirement' => 'This deletes one language\'s text and leaves the phrase on '
                        . 'the worklist — with one more gap than before. If the intent is that nobody '
                        . 'should be asked to translate the phrase at all, see `retirement` below: it '
                        . 'destroys nothing and takes the row off the list.',
                ],
                'notes'     => [
                    'The source `phrase` is read-only: it is the key the site looks itself up by, '
                        . 'not editable content, so changing it would orphan the row rather than '
                        . 'change what any page renders.',
                    'An empty string means "leave this language alone", not "blank it" — the web '
                        . 'form behaves the same way, because an untouched textarea posts one for '
                        . 'every language the translator skipped.',
                    'A JSON `null` keyed by a language is a 422, not a deletion. It used to delete '
                        . 'the translation, which made a serializer\'s default value for an absent '
                        . 'field a destructive operation nobody had asked for — so removal now takes '
                        . 'the explicit `' . PhrasesV3Controller::RETRACT_KEY . '` above.',
                    'A translation of literally "0" is a legitimate value and is written. It is '
                        . 'neither an empty string nor a null, and nothing here treats it as either.',
                    'Languages are ISO 639-1 codes: `de`, not `de_DE`. The region subtag is an '
                        . 'artefact of how catalogs are keyed internally and is never part of this '
                        . 'API. A code not listed here is refused rather than ignored, and that '
                        . 'includes the locale form of a language that is listed.',
                    'The list is read from the merged configuration at request time, so it is what '
                        . 'this site actually writes rather than what any one config file says.',
                ],
            ],
            //Listed because a subresource is not discoverable from the collection the way
            //a field is: an agent reading `writable` learns everything it may send and
            //nothing about where else it may look.
            'endpoints'    => [
                'collection' => 'GET /api/v3/phrases — filtered, paged; see `filters`.',
                'item'       => 'GET /api/v3/phrases/{phraseId} — carries a link to its history at '
                    . '`meta.history`.',
                'patch'      => 'PATCH /api/v3/phrases/{phraseId} — a JSON object of language code '
                    . 'to translation, plus the optional `' . PhrasesV3Controller::NOTE_KEY . '` and '
                    . '`' . PhrasesV3Controller::RETRACT_KEY . '`.',
                'batch'      => 'PATCH /api/v3/phrases — a `phrases` object of phrase id to that '
                    . 'same body, up to ' . PhrasesV3Controller::MAX_BATCH . ' at a time.',
                'retire'     => 'POST /api/v3/phrases/{phraseId}/retire — take the phrase off the '
                    . 'worklist; `' . PhrasesV3Controller::NOTE_KEY . '` required. See `retirement`.',
                'unretire'   => 'POST /api/v3/phrases/{phraseId}/unretire — put it back; same body.',
                'history'    => 'GET /api/v3/phrases/{phraseId}/history — what writing to this '
                    . 'phrase has replaced, newest first. `?language=de` narrows to one language\'s '
                    . 'thread; a locale like `de_DE` is a 422 there too. See `history` below.',
            ],
            //A capability rather than a field, and the one place the two destructive-sounding
            //verbs are set against each other. An agent that has read only `writable` has no
            //way to discover it, and would reach for `_retract` instead — which is worse than
            //not knowing, because it loses translations and does not achieve the thing.
            'retirement'   => [
                'what'        => 'Retirement is about the phrase, not a language. It takes a row off '
                    . 'the translator\'s worklist and destroys nothing at all: every translation stays, '
                    . 'the compiled catalogs still carry it, and the site renders exactly what it '
                    . 'rendered before.',
                'retire'      => 'POST /api/v3/phrases/{phraseId}/retire — body {"'
                    . PhrasesV3Controller::NOTE_KEY . '": "why"}',
                'unretire'    => 'POST /api/v3/phrases/{phraseId}/unretire — same body',
                'noteIsRequired' => 'Unlike on a write, where it is optional. A retirement\'s note is '
                    . 'the entire record of a judgement, and it is read at the one moment it matters: '
                    . 'when the phrase is back on the worklist and somebody has to work out whether the '
                    . 'retirement was wrong or the code that files the phrase is. No note, no '
                    . 'retirement — 422.',
                'idempotent'  => 'Retiring an already-retired phrase is a 200 with `changed: false` and '
                    . 'writes no second history entry: one event per state change of *that row*, so a '
                    . 'retry cannot forge a second judgement.',
                'siblings'    => 'The same string in two text domains is two rows sharing one hash, '
                    . 'which is routine. Retiring one does NOT retire the other — each row is its own '
                    . 'place on the worklist, so clear every row of a string or it keeps asking for '
                    . 'work through the one you left. And because a thread is keyed on the hash, a '
                    . 'history read on either id returns both retirements: two entries with two notes '
                    . 'is the correct answer for a string that existed twice, not a double-record. '
                    . '`textDomain` and `phraseId` on each entry say which row it was about.',
                'selfHealing' => 'A phrase the site still renders un-retires itself on the next missed '
                    . 'lookup, keeping its translations, and that path writes no history. So a row that '
                    . 'is live again with a `retire` entry and no `unretire` after it is the interesting '
                    . 'case: the retirement was wrong, and its note says what was believed.',
                'visibility'  => 'The collection hides retired rows. `?onlyRetired=1` lists them and '
                    . '`?includeRetired=1` lists both; `meta.retiredOn` on the document is the flag.',
                'notRetraction' => 'Not a stronger `' . PhrasesV3Controller::RETRACT_KEY . '`. That '
                    . 'deletes a translation and keeps the phrase; this keeps every translation and '
                    . 'removes the phrase from the worklist. Retracting every language to clear a row '
                    . 'achieves the opposite while destroying five translations.',
                'notDeletion' => 'The row is not deleted and cannot be through this API. A string that '
                    . 'must cease to exist — a leaked secret — is a task for a human with database '
                    . 'access.',
            ],
            'history'      => [
                'url'        => 'GET /api/v3/phrases/{phraseId}/history',
                'operations' => [
                    TranslationsTable::OPERATION_UPDATE  => 'Something replaced the text. '
                        . '`previous` is what it replaced.',
                    TranslationsTable::OPERATION_RETRACT => 'Something deleted it; the language is '
                        . 'empty now. `previous` is what was deleted.',
                    TranslationsTable::OPERATION_UNRETIRE => 'The phrase was put back on the worklist '
                        . 'by hand, through the un-retire endpoint. Shaped like `retire` — no language, '
                        . 'no previous text. Its absence is informative: a phrase that is live again '
                        . 'with no `unretire` after its `retire` came back because a render missed on '
                        . 'it, which means the retirement was wrong.',
                    TranslationsTable::OPERATION_RETIRE  => 'The *phrase* left the translator\'s '
                        . 'worklist, because the application knows nothing renders it any more — an '
                        . 'association description replaced by a moderator, say. It destroys '
                        . 'nothing, so `language` is null and `previous` is empty, and `note` is the '
                        . 'whole content. It appears in every language\'s thread, `?language=` '
                        . 'included.',
                ],
                'notes'      => [
                    'One entry per event that changes what a translator would see, never one per '
                        . 'write. Filling a language that was empty appears here not at all, so an '
                        . 'empty list means "nothing has been lost or withdrawn here" rather than '
                        . '"no records kept".',
                    'Keyed on the phrase rather than on the row, so a thread survives a merge or a '
                        . 'delete-and-rediscover. The id in the URL only has to name a live row of '
                        . 'the string; each entry carries its own `phraseId`, which can differ.',
                    'Entries span every text domain the string appears in. The same string in two '
                        . 'domains is one translation problem, and a thread split by domain would '
                        . 'show half the argument. This is also why a string that exists as two rows '
                        . 'shows two `retire` entries here — one per row, each naming its own '
                        . '`textDomain` and `phraseId`. See `retirement.siblings`.',
                    'This is why a broad overwrite is recoverable now. Reverting is a PATCH with '
                        . 'the text this endpoint gives back.',
                ],
            ],
            'filters'      => [
                'textDomain'     => 'exact match on the phrase\'s text domain',
                'originRoute'    => 'exact match on the route the phrase was first seen on',
                'search'         => 'substring of the source phrase',
                'untranslatedIn' => 'a language code; phrases with no usable translation in it',
                'translatedIn'   => 'a language code; phrases that do have one',
                'onlyRetired'    => 'list *only* retired phrases — how you audit your own '
                    . 'retirements and notice one that came back',
                'includeRetired' => 'list retired phrases alongside live ones. Retired rows are hidden '
                    . 'by default, because the point of retiring one is to stop asking for work on it',
                'originRouteLike' => 'like `originRoute`, but the caller\'s `%` and `_` are wildcards. '
                    . '`search` escapes them; this does not',
                'limit'          => 'page size, default 100, maximum 500',
                'offset'         => 'page offset',
            ],
            'concurrency'  => [
                'etag'    => 'GET returns a weak ETag over the translations, and only the translations.',
                'ifMatch' => 'PATCH /api/v3/phrases/{phraseId} honours If-Match and answers 412 when the '
                    . 'translations have moved. The batch endpoint does not: a conditional request is '
                    . 'defined over one resource.',
            ],
            'sideEffects'  => [
                'catalogs' => 'A write recompiles the .lang.php catalogs the site renders from. '
                    . 'Until that happens the page still shows the old text, so a response carrying '
                    . 'a `warning` key means the row was saved and the site is stale.',
                'batching' => 'The batch endpoint recompiles once at the end rather than once per '
                    . 'phrase. Use it for more than a handful of writes.',
            ],
        ]);
    }

    /**
     * One field's contract, from its input-filter entry.
     *
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    private static function describe(array $rules): array
    {
        $described = ['required' => (bool) ($rules['required'] ?? false)];

        $validators = is_array($rules['validators'] ?? null) ? $rules['validators'] : [];
        foreach ($validators as $validator) {
            if (! is_array($validator) || ! is_string($validator['name'] ?? null)) {
                continue;
            }

            $options = is_array($validator['options'] ?? null) ? $validator['options'] : [];

            if (StringLength::class === $validator['name'] && isset($options['max'])) {
                $described['maxLength'] = is_numeric($options['max']) ? (int) $options['max'] : null;
                continue;
            }
            if (InArray::class === $validator['name'] && is_array($options['haystack'] ?? null)) {
                $described['enum'] = array_values(array_filter(
                    $options['haystack'],
                    static fn (mixed $value): bool => is_string($value) || is_int($value)
                ));
                continue;
            }

            //Everything else is named rather than modelled. A short name is enough for
            //an agent to look up, and pretending to express `OpeningHoursSpecificationJson`
            //or `GpsPoint` as a JSON-schema fragment would be a description that is
            //wrong in the details rather than absent.
            $described['constraints'][] = self::shortName($validator['name']);
        }

        $filters = is_array($rules['filters'] ?? null) ? $rules['filters'] : [];
        foreach ($filters as $filter) {
            if (is_array($filter) && is_string($filter['name'] ?? null)) {
                $described['filters'][] = self::shortName($filter['name']);
            }
        }

        return $described;
    }

    /** The class name without its namespace: `StringTrim`, `GpsPoint`, `Twitter`. */
    private static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return false === $position ? $class : substr($class, $position + 1);
    }
}
