<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BotIdentity;
use App\JTranslate\Phrase\PhraseResource;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use JTranslate\Form\PhraseValidator;
use JTranslate\I18n\LanguageMap;
use JTranslate\Model\TranslationsTable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function array_diff;
use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function ctype_digit;
use function error_log;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

/**
 * `/api/v3/phrases` — the read/write API automated agents use to review and improve
 * the site's translations.
 *
 * ## What an agent is here to do
 *
 * Read a phrase, look at the page it came from, and write a better translation. The
 * three things that makes possible, none of which the admin GUI offers:
 *
 * - **Filtering that reaches the database.** `?untranslatedIn=de_DE&textDomain=Application`
 *   is answered by a `WHERE` clause, so an agent asks for its work instead of paging
 *   through 6,874 phrases looking for gaps.
 * - **Context.** Every phrase carries the route it was first seen on and, where that
 *   route needs no parameters, the URL of the page — which the agent then fetches over
 *   ordinary HTTP and reads as a visitor would. See PhraseResource on why that URL is
 *   often null and why it is better null than guessed.
 * - **Attribution.** Every write records the bot's user id in
 *   `trans_translations.modified_by`, so the admin listing shows agent edits beside
 *   human ones with a name against each.
 *
 * ## The phrase is a key, not content
 *
 * Only the translations are writable. `trans_phrases.phrase` is the English source
 * string the site renders and looks itself up by; editing it orphans the row rather
 * than changing anything. See PhraseResource.
 *
 * ## Its own role
 *
 * `sch_api_translator`, not `sch_api_bot`. A translation agent can rewrite every
 * string the site shows in four languages and a shrine agent can rewrite the shrine
 * database; neither is a reason to be able to do the other, and before this endpoint
 * existed there was only one role and the question could not be asked. See
 * database/db6.8.sql.
 *
 * ## A write is not finished when the row is written
 *
 * The site renders from compiled `.lang.php` catalogs, not from the table, so a
 * translation that is only in the database is invisible. Every write here recompiles
 * them exactly as `JTranslate\Controller\JTranslateController::editAction()` does, and
 * reports the same partial success — *saved, but the site still shows the old text* —
 * when the files cannot be written. Getting that wrong in the other direction is what
 * that action used to do, and it told translators the opposite of the truth.
 *
 * Recompiling is not cheap, which is why {@see self::patchCollection()} exists: it
 * takes many phrases and recompiles once at the end. An agent working through a text
 * domain should use it.
 */
final class PhrasesV3Controller extends AbstractApiController
{
    /** A page of the collection, and the ceiling a caller can ask for. */
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT     = 500;

    /**
     * How many phrases one batch may carry.
     *
     * Bounded because the whole batch is one request against one PHP process with one
     * `max_execution_time` (60s in the capsule, see docker/php-limits.ini) and one
     * transaction-less sequence of writes. A batch that times out half way through has
     * written half its phrases and told the agent nothing, which is the worst outcome
     * available.
     *
     * Measured in the capsule against this database: **200 phrases in 1.25 s**,
     * recompile included — comfortably inside the budget, and the reason the ceiling
     * is 200 rather than 20. The same work one phrase at a time is ~0.25 s each, of
     * which almost all is the catalog recompile: five individual PATCHes take 1.26 s
     * where the identical five as one batch take 0.24 s. That ratio is the endpoint's
     * whole justification, and it gets worse linearly — 200 individual writes would be
     * around 50 s, i.e. within sight of the execution limit for work that takes one and
     * a quarter seconds done properly.
     */
    private const MAX_BATCH = 200;

    /**
     * The locale a context URL is assembled under.
     *
     * `en_US` because that is `jtranslate.key_locale`: the phrase itself is the English
     * string, so the English page is the one that shows it in the words the agent is
     * reading. Any other prefix would show the page already translated, which is the
     * one thing an agent reviewing a translation must not mistake for the source.
     */
    private const KEY_LOCALE = 'en_US';

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly RouteUrl $routeUrl,
        BotIdentity $identity
    ) {
        parent::__construct($identity);
    }

    protected function requiredRole(): string
    {
        return BotIdentity::TRANSLATOR_ROLE;
    }

    // ------------------------------------------------------------------ read

    public function index(Request $request): Response
    {
        $actingUser = $this->requireAgent($request);
        if ($actingUser instanceof Response) {
            return $actingUser;
        }

        $criteria = $this->criteriaFrom($request);
        if ($criteria instanceof Response) {
            return $criteria;
        }

        $table     = $this->table();
        $languages = $this->validator()->languages();

        $limit  = self::boundedInt($request->query->get('limit'), self::DEFAULT_LIMIT, 1, self::MAX_LIMIT);
        $offset = self::boundedInt($request->query->get('offset'), 0, 0, PHP_INT_MAX);

        //Counted and paged by the database, not by array_slice over everything. The
        //association collection does the latter and gets away with it on a few hundred
        //rows; here it is 0.220s and 72.6 MB a request, and it could not express the
        //filters above at all. See TranslationsTable::getPhrasePage().
        $total = $table->countPhrases($criteria);
        $page  = $table->getPhrasePage($criteria, $limit, $offset);

        return new JsonResponse([
            'total'  => $total,
            'offset' => $offset,
            'limit'  => $limit,
            'items'  => array_values(array_map(
                fn (array $phrase): array => PhraseResource::represent(
                    $phrase,
                    $languages,
                    $this->contextPath($phrase),
                    $request->getSchemeAndHttpHost()
                ),
                $page
            )),
        ]);
    }

    public function show(Request $request): Response
    {
        $actingUser = $this->requireAgent($request);
        if ($actingUser instanceof Response) {
            return $actingUser;
        }

        $phrase = $this->phrase($request);
        if (null === $phrase) {
            return self::problem(Response::HTTP_NOT_FOUND, 'No phrase of this project has that id.');
        }

        $document = PhraseResource::represent(
            $phrase,
            $this->validator()->languages(),
            $this->contextPath($phrase),
            $request->getSchemeAndHttpHost()
        );

        return self::tagged(new JsonResponse($document), $document['meta']['etag']);
    }

    // ----------------------------------------------------------------- write

    /**
     * `PATCH /api/v3/phrases/{id}` — a JSON object of language code => translation.
     */
    public function patch(Request $request): Response
    {
        $actingUser = $this->requireAgent($request);
        if ($actingUser instanceof Response) {
            return $actingUser;
        }

        $phrase = $this->phrase($request);
        if (null === $phrase) {
            return self::problem(Response::HTTP_NOT_FOUND, 'No phrase of this project has that id.');
        }

        $patch = self::decodeBody($request);
        if (! is_array($patch)) {
            return self::problem(
                Response::HTTP_BAD_REQUEST,
                'The request body must be a JSON object of language code to translation.'
            );
        }

        $validator = $this->validator();
        $languages = $validator->languages();
        $current   = PhraseResource::represent(
            $phrase,
            $languages,
            $this->contextPath($phrase),
            $request->getSchemeAndHttpHost()
        );

        if (! self::ifMatchSatisfied($request, $current['meta']['etag'])) {
            return self::problem(
                Response::HTTP_PRECONDITION_FAILED,
                'The phrase changed since you read it. Re-read it and re-apply your change.',
                ['etag' => $current['meta']['etag']]
            );
        }

        $outcome = $this->apply($phrase, $patch, $languages, $validator, $actingUser);
        if (! $outcome['ok']) {
            return self::problem($outcome['status'], $outcome['message'], $outcome['detail']);
        }

        $note = [] === $outcome['changed'] ? null : $this->recompileCatalogs();

        $fresh    = $this->table()->getPhraseById((int) $phrase['phraseId']) ?? $phrase;
        $document = PhraseResource::represent(
            $fresh,
            $languages,
            $this->contextPath($fresh),
            $request->getSchemeAndHttpHost()
        );

        //Answered 200 with the current document even when nothing moved, rather than
        //204: an agent polling for drift wants to see what it would have written.
        $body = ['changed' => $outcome['changed'], 'phrase' => $document];
        if (null !== $note) {
            $body['warning'] = $note;
        }

        return self::tagged(new JsonResponse($body), $document['meta']['etag']);
    }

    /**
     * `PATCH /api/v3/phrases` — many phrases, one catalog recompile.
     *
     * ```jsonc
     * { "phrases": { "6198": { "de_DE": "…" }, "6197": { "es_ES": "…" } } }
     * ```
     *
     * The response reports every id separately, because a batch's interesting outcome
     * is the partial one. An id that does not exist or a translation that does not
     * validate fails *that entry* and nothing else — the alternative, refusing the
     * whole batch for one bad row, means an agent working through a text domain is
     * stopped by its own worst guess and has to bisect to find it.
     *
     * There is no `If-Match` here. Conditional requests are defined over one resource
     * and a batch is not one; an agent that needs lost-update protection for a
     * particular phrase should PATCH that phrase. Documented rather than silently
     * ignored: {@see self::patch()} honours it.
     */
    public function patchCollection(Request $request): Response
    {
        $actingUser = $this->requireAgent($request);
        if ($actingUser instanceof Response) {
            return $actingUser;
        }

        $body = self::decodeBody($request);
        if (! is_array($body) || ! is_array($body['phrases'] ?? null)) {
            return self::problem(
                Response::HTTP_BAD_REQUEST,
                'The request body must be a JSON object with a `phrases` object of phrase id to language map.',
                ['example' => ['phrases' => ['6198' => ['de' => 'Ein Beispiel']]]]
            );
        }

        /** @var array<array-key, mixed> $entries */
        $entries = $body['phrases'];
        if (count($entries) > self::MAX_BATCH) {
            return self::problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                sprintf('A batch carries at most %d phrases; send the rest in another request.', self::MAX_BATCH),
                ['maxBatch' => self::MAX_BATCH, 'received' => count($entries)]
            );
        }

        $table     = $this->table();
        $validator = $this->validator();
        $languages = $validator->languages();

        $results   = [];
        $anyWrites = false;

        foreach ($entries as $rawId => $patch) {
            $key = (string) $rawId;

            if (! is_int($rawId) && ! (is_string($rawId) && ctype_digit($rawId))) {
                $results[$key] = self::entryError('The phrase id must be an integer.');
                continue;
            }
            if (! is_array($patch)) {
                $results[$key] = self::entryError('The value must be an object of language code to translation.');
                continue;
            }

            $phrase = $table->getPhraseById((int) $rawId);
            if (null === $phrase) {
                $results[$key] = self::entryError('No phrase of this project has that id.', Response::HTTP_NOT_FOUND);
                continue;
            }

            $outcome = $this->apply($phrase, $patch, $languages, $validator, $actingUser);
            if (! $outcome['ok']) {
                $results[$key] = self::entryError($outcome['message'], $outcome['status']) + $outcome['detail'];
                continue;
            }

            $anyWrites     = $anyWrites || [] !== $outcome['changed'];
            $results[$key] = ['ok' => true, 'changed' => $outcome['changed']];
        }

        //Once, at the end, and only if something moved. This is the entire reason the
        //endpoint exists: the per-phrase route recompiles every catalog on every write,
        //which for a 200-phrase run is 200 full rebuilds of the same files.
        $note = $anyWrites ? $this->recompileCatalogs() : null;

        $response = [
            'results' => $results,
            'summary' => [
                'received' => count($entries),
                'applied'  => count(array_filter($results, static fn (array $r): bool => (bool) ($r['ok'] ?? false))),
                'failed'   => count(array_filter($results, static fn (array $r): bool => ! ($r['ok'] ?? false))),
            ],
        ];
        if (null !== $note) {
            $response['warning'] = $note;
        }

        return new JsonResponse($response);
    }

    // --------------------------------------------------------------- plumbing

    /**
     * Validate a patch against the translator's own rules and write it.
     *
     * Answers a plain array rather than a `Response` because both callers need the
     * outcome in a different envelope: {@see self::patch()} turns a failure into the
     * request's status code, {@see self::patchCollection()} turns it into one entry of
     * a 200 that also carries the entries that succeeded. Returning a Response and
     * re-decoding it in the batch — which is what the first draft did — meant the
     * batch's error shape was defined by JSON round-tripping.
     *
     * @param array<string, mixed> $phrase
     * @param array<string, mixed> $patch keyed by language code
     * @return array{ok: bool, changed: list<string>, status: int, message: string, detail: array<string, mixed>}
     */
    private function apply(
        array $phrase,
        array $patch,
        LanguageMap $languages,
        PhraseValidator $validator,
        int $actingUser
    ): array {
        $unknown = array_diff(array_map(strval(...), array_keys($patch)), $languages->languages());
        if ([] !== $unknown) {
            //Refused rather than ignored, for the association API's reason: an agent
            //that sends `de_DE` or `de-DE` and gets a 200 will keep sending it forever
            //and the German it believes it is maintaining never changes. The locale
            //form is refused as firmly as a misspelling — this API speaks languages,
            //and accepting both would make the *stored* key depend on which the caller
            //happened to send.
            return self::failure(
                'The request names languages this API does not accept.',
                [
                    'unknownLanguages' => array_values($unknown),
                    'writableLanguages' => $languages->languages(),
                ]
            );
        }

        $filter = $validator->inputFilter();
        //Not merged onto the stored record the way an association patch is, and it does
        //not need to be: nothing in this filter is required except phraseId, which is
        //supplied from the record, so a patch naming one locale is already a complete
        //submission. Merging would only re-submit stored text for the change detector
        //to discard.
        $filter->setData(PhraseResource::submission((int) $phrase['phraseId'], $patch, $languages));

        if (! $filter->isValid()) {
            //The messages are the translator's, verbatim — see PhraseValidator.
            return self::failure(
                'The translation would not be valid.',
                ['messages' => $filter->getMessages()]
            );
        }

        //getValues(), not $patch: the raw body has been through no filter, so writing
        //it discards the trimming and the length bound isValid() just applied — the
        //same trap JTranslateController::editAction() documents.
        //Back in locale space from here down: getValues() is the form's output, and the
        //form is keyed the way the database is.
        /** @var array<string, mixed> $values */
        $values      = $filter->getValues();
        $patchLocales = array_filter(array_map(
            static fn (string $language): ?string => $languages->localeFor($language),
            array_map(strval(...), array_keys($patch))
        ));
        $submitted   = array_intersect_key($values, array_flip($patchLocales));

        //Drop what the write is going to skip anyway, *before* deciding what changed.
        //`TranslationsTable::updatePhrase()` ignores any falsy value — that is how the
        //web form says "leave this locale alone", an empty textarea being how a
        //translator declines a language. Counting those as changes made this endpoint
        //lie in the most expensive direction available: `{"de_DE": ""}` answered
        //`"changed": ["de_DE"]` while the stored text was untouched, and the caller then
        //got a full catalog recompile for a write that never happened. Measured on
        //phrase 6197.
        //
        //`0 == ''` for this purpose, deliberately: a translation of literally "0" is
        //falsy in PHP and updatePhrase() skips it too. Mirroring its exact condition
        //keeps `changed` truthful rather than correct in principle — if that quirk is
        //ever fixed it should be fixed there, and this follows.
        $submitted = array_filter($submitted, static fn (mixed $value): bool => (bool) $value);
        if ([] === $submitted) {
            return self::applied([]);
        }

        $changed = PhraseResource::changedLanguages($phrase, $submitted, $languages);
        if ([] === $changed) {
            return self::applied([]);
        }

        $table = $this->table();
        //Attribution. The configured provider reads a session this request does not
        //have, so without this every agent edit lands in trans_translations as
        //modified_by NULL and the admin listing cannot say who wrote a translation.
        $table->setActingUserId($actingUser);
        $table->updatePhrase((int) $phrase['phraseId'], $submitted);

        return self::applied($changed);
    }

    /**
     * @param list<string> $changed
     * @return array{ok: bool, changed: list<string>, status: int, message: string, detail: array<string, mixed>}
     */
    private static function applied(array $changed): array
    {
        return ['ok' => true, 'changed' => $changed, 'status' => 200, 'message' => '', 'detail' => []];
    }

    /**
     * @param array<string, mixed> $detail
     * @return array{ok: bool, changed: list<string>, status: int, message: string, detail: array<string, mixed>}
     */
    private static function failure(string $message, array $detail = []): array
    {
        return [
            'ok'      => false,
            'changed' => [],
            'status'  => Response::HTTP_UNPROCESSABLE_ENTITY,
            'message' => $message,
            'detail'  => $detail,
        ];
    }

    /**
     * Recompile the `.lang.php` catalogs, and say so if it failed.
     *
     * @return string|null a warning for the caller, or null when the site is current
     */
    private function recompileCatalogs(): ?string
    {
        try {
            $this->table()->writePhpTranslationArrays();

            return null;
        } catch (Throwable $e) {
            //Reported, not thrown. The database write has already committed, so a 500
            //here would tell the agent its perfectly good translation failed and invite
            //it to send the same one again forever. The truth is narrower and the
            //caller can act on it: it is saved, and the site is stale until the files
            //can be written.
            error_log('JTranslate: could not compile translation files after an API write: ' . $e->getMessage());

            return 'The translations were saved, but the compiled catalogs could not be written, '
                . 'so the site will keep showing the old text until that is fixed.';
        }
    }

    /**
     * The criteria a caller asked for, or the refusal.
     *
     * An unknown query parameter is refused for the same reason an unknown field is:
     * `?language=de` when the parameter is called `untranslatedIn` would otherwise
     * return the unfiltered collection with a 200, and an agent would work through the
     * wrong list believing it was the right one.
     *
     * @return array<string, string>|Response
     */
    private function criteriaFrom(Request $request): array|Response
    {
        $paging     = ['limit', 'offset'];
        $understood = array_merge(TranslationsTable::CRITERIA, $paging);
        $unknown    = array_diff(array_keys($request->query->all()), $understood);
        if ([] !== $unknown) {
            return self::problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The request names query parameters this API does not accept.',
                ['unknownParameters' => array_values($unknown), 'accepted' => $understood]
            );
        }

        $criteria = [];
        foreach (TranslationsTable::CRITERIA as $name) {
            $value = $request->query->get($name);
            if (is_string($value) && '' !== $value) {
                $criteria[$name] = $value;
            }
        }

        //The two locale-valued criteria are given in language codes like everything
        //else on this API, and translated to locales here — TranslationsTable works in
        //locales, because the column does.
        $languages = $this->validator()->languages();
        foreach (['untranslatedIn', 'translatedIn'] as $languageCriterion) {
            if (! isset($criteria[$languageCriterion])) {
                continue;
            }

            $locale = $languages->localeFor($criteria[$languageCriterion]);
            if (null === $locale) {
                return self::problem(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    sprintf('`%s` must name a language this site translates into.', $languageCriterion),
                    ['writableLanguages' => $languages->languages()]
                );
            }

            $criteria[$languageCriterion] = $locale;
        }

        return $criteria;
    }

    /** @return array<string, mixed>|null */
    private function phrase(Request $request): ?array
    {
        $id = $request->attributes->get('phrase_id');
        if (! is_string($id) || ! ctype_digit($id)) {
            return null;
        }

        return $this->table()->getPhraseById((int) $id);
    }

    /**
     * The path of the page a phrase was first seen on, or null.
     *
     * Null in three cases and they are not worth distinguishing to a caller: the row
     * has no `origin_route`, the route no longer exists, or it needs parameters this
     * phrase does not record. See PhraseResource for why a guess would be worse than
     * a null.
     *
     * The key locale is named explicitly rather than left to `Locale::getDefault()`,
     * which on an API request is whatever php.ini last said — v3 carries no locale
     * prefix and runs no LocaleListener.
     *
     * @param array<string, mixed> $phrase
     */
    private function contextPath(array $phrase): ?string
    {
        $route = $phrase['originRoute'] ?? null;
        if (! is_string($route) || '' === $route) {
            return null;
        }

        try {
            return $this->routeUrl->path($route, [], [], self::KEY_LOCALE);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private static function entryError(string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): array
    {
        return ['ok' => false, 'status' => $status, 'message' => $message];
    }

    private function table(): TranslationsTable
    {
        /** @var TranslationsTable $table */
        $table = $this->laminas->get(TranslationsTable::class);

        return $table;
    }

    /**
     * The translator form's rules, headless.
     *
     * Resolved from JTranslate rather than rebuilt here. Building it meant knowing
     * `EditPhraseForm`'s constructor signature, that the CSRF input is called
     * `security`, and that the static database adapter has to be populated before the
     * specification is read — three internals of a module whose admin GUI is the part
     * most likely to be rewritten rather than ported. The library owns the contract
     * now; see JTranslate\Form\PhraseValidator.
     */
    private function validator(): PhraseValidator
    {
        /** @var PhraseValidator $validator */
        $validator = $this->laminas->get(PhraseValidator::class);

        return $validator;
    }
}
