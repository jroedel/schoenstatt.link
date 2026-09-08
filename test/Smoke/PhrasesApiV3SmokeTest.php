<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_column;
use function array_keys;
use function base64_encode;
use function count;
use function explode;
use function file_get_contents;
use function hash_hmac;
use function implode;
use function is_file;
use function is_string;
use function array_map;
use function preg_match;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function random_bytes;
use function rtrim;
use function str_contains;
use function str_repeat;
use function str_replace;
use function strlen;
use function strtr;
use function substr;
use function time;

/**
 * `/api/v3/phrases`, measured the way a translation agent will use it.
 *
 * The properties worth an HTTP test are the ones no amount of reading the controller
 * confirms:
 *
 * 1. **The two API roles are two boundaries, not one.** A `sch_api_bot` token — valid,
 *    registered, unrevoked, and able to PATCH shrines this second — is refused here,
 *    and a `sch_api_translator` token is refused on `/api/v3/associations`. Both
 *    directions are asserted, because a one-directional test passes just as happily
 *    against the old arrangement where one constant gated everything. This is the
 *    reason database/db6.8.sql exists and the only thing that keeps its claim true.
 * 2. **A write reaches the compiled catalogs.** The site renders from
 *    `language/<Domain>/<locale>.lang.php`, not from the table, so a translation that
 *    is only in the database is invisible. Asserted against the file on disk.
 * 3. **Validation is the translator form's.** A 2001-character translation is refused
 *    with `stringLengthTooLong`, the message
 *    `JTranslate\Form\EditPhraseForm::getInputFilterSpecification()` produces.
 * 4. **Attribution.** `trans_translations.modified_by` carries the bot's user id, which
 *    is what lets the admin listing show agent edits beside human ones.
 *
 * Tokens are minted directly from the configured key rather than through JUser's email
 * flow — ApiAuthSmokeTest covers issuance end to end, and repeating it here would test
 * the mailer.
 *
 * **Fixtures.** Every translation row of the phrases below is snapshotted before the
 * test and restored afterwards, rows that did not exist included: a row this test
 * inserted is deleted again, so the capsule's dump is unchanged whether the test
 * passes, fails or throws. That matters more here than in the association test,
 * because `trans_translations` has no natural "original value" to write back — a
 * missing row and an empty one are different states and the API can turn the first
 * into the second.
 */
class PhrasesApiV3SmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    private const EMAIL_PREFIX = 'phrases-v3-smoke-';

    /**
     * Phrases from the capsule dump, chosen because they are short UI labels rather
     * than book bodies, and because both carry an `origin_route`.
     */
    private const PHRASE_ID  = 10028;

    /**
     * A phrase whose `origin_route` still exists **and** takes no parameters, so its
     * `context.url` is a URL an agent can actually fetch. `publications` (/literature),
     * from the Books domain.
     *
     * Separate from PHRASE_ID because that one's origin route was deleted — see
     * {@see testAPhraseCarriesItsContextAndAnEtag()}. Read-only here: nothing in this class
     * writes to it, so it needs no cleanup.
     */
    private const ROUTED_PHRASE_ID = 5375;
    private const PHRASE_TWO = 6197;

    private const COLLECTION = '/api/v3/phrases';
    private const ITEM       = self::COLLECTION . '/' . self::PHRASE_ID;

    /** @var array<int, list<array<string, mixed>>>|null the rows as they were found */
    private ?array $originalRows = null;

    /** @var array<int, string|null> phrase id => `retired_on` as it was found */
    private array $originalRetirement = [];

    private ?int $translatorUserId = null;
    private ?int $shrineBotUserId  = null;

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->rememberPhrases();
    }

    protected function tearDown(): void
    {
        $this->restorePhrases();
        $this->purgeMail();
        $this->purgeAccounts();

        parent::tearDown();
    }

    // ----------------------------------------------------------- who gets in

    /** @return iterable<string, array{0: string}> */
    public static function translatorOnlyPaths(): iterable
    {
        yield 'collection' => [self::COLLECTION];
        yield 'item'       => [self::ITEM];
    }

    #[DataProvider('translatorOnlyPaths')]
    public function testAnonymousCallersAreRefused(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(401, $response['status'], $path);
        $this->assertStringContainsString('sch_api_translator', $response['body']);
    }

    #[DataProvider('translatorOnlyPaths')]
    public function testATranslatorTokenGetsIn(string $path): void
    {
        $this->assertSame(200, $this->getWithBearer($this->translatorToken(), $path)['status'], $path);
    }

    /**
     * The test this file exists for, half one. A shrine agent's token is valid,
     * registered and unrevoked, and it opens `/api/v3/associations` right now — and it
     * is refused here. If this ever answers 200, `sch_api_bot` has silently become a
     * key to the whole API again and every shrine agent can rewrite the site's text in
     * four languages.
     */
    public function testAShrineBotTokenIsRefusedOnPhrases(): void
    {
        $token = $this->shrineBotToken();

        //Proves the token is good, so the 401 below cannot be blamed on the token.
        $this->assertSame(
            200,
            $this->getWithBearer($token, '/api/v3/associations')['status'],
            'the shrine bot token does not work at all, so this test proves nothing'
        );

        $this->assertSame(401, $this->getWithBearer($token, self::COLLECTION)['status']);
    }

    /** Half two: the boundary holds in the other direction too. */
    public function testATranslatorTokenIsRefusedOnAssociations(): void
    {
        $token = $this->translatorToken();

        $this->assertSame(200, $this->getWithBearer($token, self::COLLECTION)['status']);
        $this->assertSame(401, $this->getWithBearer($token, '/api/v3/associations')['status']);
    }

    /** A wrong verb is a 405 with an Allow header, not laminas' redirect to sign-in. */
    public function testAnUnsupportedMethodIsRefusedWithAllow(): void
    {
        $response = $this->request('DELETE', self::ITEM);

        $this->assertSame(405, $response['status']);
        $this->assertSame('GET, PATCH', $response['headers']['allow'] ?? null);
    }

    // ------------------------------------------------------------- reading

    public function testTheCollectionFiltersInTheDatabase(): void
    {
        $token = $this->translatorToken();

        $all      = $this->decode($this->getWithBearer($token, self::COLLECTION . '?limit=1'));
        $inDomain = $this->decode($this->getWithBearer($token, self::COLLECTION . '?textDomain=Schoenstatt&limit=1'));

        $this->assertGreaterThan(
            $inDomain['total'],
            $all['total'],
            'filtering by text domain did not narrow anything, so the criterion never reached the query'
        );
        $this->assertCount(1, $all['items'], 'limit was ignored');
        $this->assertSame('Schoenstatt', $inDomain['items'][0]['textDomain']);
    }

    /**
     * The filter an agent actually wants: what is missing. Asserted by its effect
     * rather than a fixed count, because the number moves with the dump.
     */
    public function testTheUntranslatedFilterExcludesTranslatedPhrases(): void
    {
        $token = $this->translatorToken();

        $missing = $this->decode($this->getWithBearer($token, self::COLLECTION . '?untranslatedIn=de&limit=25'));
        $present = $this->decode($this->getWithBearer($token, self::COLLECTION . '?translatedIn=de&limit=25'));

        $this->assertNotEmpty($missing['items']);
        $this->assertNotEmpty($present['items']);
        foreach ($missing['items'] as $item) {
            $this->assertNull($item['translations']['de']['text'], 'a translated phrase came back as missing');
        }
        foreach ($present['items'] as $item) {
            $this->assertNotNull($item['translations']['de']['text']);
        }
    }

    /**
     * A misspelled query parameter is refused rather than ignored. Ignoring it returns
     * the unfiltered collection with a 200, and an agent then works through the wrong
     * list believing it is the right one — the same reasoning as an unknown field on a
     * PATCH.
     */
    public function testAnUnknownQueryParameterIsRefused(): void
    {
        $response = $this->getWithBearer($this->translatorToken(), self::COLLECTION . '?language=de');

        $this->assertSame(422, $response['status']);
        $this->assertStringContainsString('unknownParameters', $response['body']);
    }

    public function testAnUnwritableLocaleIsRefusedAsAFilter(): void
    {
        $response = $this->getWithBearer($this->translatorToken(), self::COLLECTION . '?untranslatedIn=xx_XX');

        $this->assertSame(422, $response['status']);
    }

    /**
     * The context an agent browses with: the route the phrase was first seen on, and
     * where that route needs no parameters, a URL it can fetch.
     *
     * ## Why this one test uses a different phrase from the other thirty
     *
     * `self::PHRASE_ID`'s `origin_route` is `sign-in-no-cookies`, which was **deleted on
     * 2026-08-21** — an unreachable route that nothing linked to. The phrase was filed
     * against it by accident of the route-match swap: `GdprStrategy::onRoute()` called
     * `setMatchedRouteName('sign-in-no-cookies')`, so every phrase rendered on that page
     * recorded it, including "Access to entity denied.", which has nothing to do with
     * cookies.
     *
     * The API degrades correctly — `context.url` comes back as an empty string rather than
     * throwing, because the route cannot be assembled — and that is worth having a test
     * for on its own. What it is *not* is a phrase that can demonstrate an absolute,
     * fetchable URL, so this test names one that can. `origin_route` is a historical record
     * and nothing keeps it in step with the router; assuming otherwise is what broke here.
     */
    public function testAPhraseCarriesItsContextAndAnEtag(): void
    {
        $response = $this->getWithBearer($this->translatorToken(), self::COLLECTION . '/' . self::ROUTED_PHRASE_ID);
        $document = $this->decode($response);

        $this->assertSame(self::ROUTED_PHRASE_ID, $document['phraseId']);
        $this->assertNotEmpty($document['context']['originRoute']);
        //Absolute and on the host we just called, not a hardcoded production URL — an
        //agent following a hardcoded one out of a staging response would read the live
        //site and think the change it is reviewing is already deployed.
        $this->assertStringStartsWith($this->baseUrl(), (string) $document['context']['url']);
        $this->assertNotEmpty($document['meta']['etag']);
        //Compared with the `-gzip` suffix stripped. Apache's mod_deflate appends it to
        //the ETag of any compressed response, and curl here negotiates compression like
        //every real client — so the header and the document legitimately differ by five
        //characters. The API normalizes both sides of an If-Match for the same reason;
        //asserting raw equality here would have been asserting that mod_deflate is off.
        $this->assertSame(
            $document['meta']['etag'],
            str_replace('-gzip"', '"', (string) ($response['headers']['etag'] ?? '')),
            'the ETag header and the document disagree about more than the gzip suffix'
        );
    }

    /**
     * A phrase whose origin route no longer exists still answers, with an empty `url`.
     *
     * `origin_route` is a historical record — the route a phrase was first *seen* on — and
     * nothing keeps it in step with the router. `self::PHRASE_ID` is the live example: its
     * route was `sign-in-no-cookies`, deleted 2026-08-21, and it was filed against that
     * route only because `GdprStrategy::onRoute()` renamed the match, so the phrase
     * ("Access to entity denied.") never had anything to do with cookies.
     *
     * The route name is still reported, because it is what the record says; the URL is
     * empty, because there is nothing to assemble. Both halves are asserted: a 500 here
     * would take out the phrase an agent happened to open next, and silently dropping
     * `originRoute` would lose the only clue about where the string is used.
     */
    public function testAPhraseWhoseOriginRouteIsGoneStillAnswers(): void
    {
        $document = $this->decode($this->getWithBearer($this->translatorToken(), self::ITEM));

        $this->assertSame(self::PHRASE_ID, $document['phraseId']);
        $this->assertSame(
            'sign-in-no-cookies',
            $document['context']['originRoute'],
            'the record is reported as it stands, deleted route and all'
        );
        //**null, not an empty string**, and the difference is the API's own convention:
        //absent means "there is no answer", where '' would read as "the answer is nothing".
        //A caller checking `if (url)` treats them alike; one deserializing into a typed
        //field does not.
        $this->assertNull(
            $document['context']['url'],
            'an unassemblable route must produce a null url, not an exception and not a bad link'
        );
    }

    /** Every locale is present whether or not it has a row: absent and null are not two answers. */
    public function testEveryWritableLocaleIsPresentInTheDocument(): void
    {
        $document = $this->decode($this->getWithBearer($this->translatorToken(), self::ITEM));
        $schema   = $this->decode($this->get('/api/v3/schema/phrase'));

        $this->assertSame(
            [],
            array_diff($schema['writable']['languages'], array_keys($document['translations'])),
            'the schema promises a language the representation does not carry'
        );
    }

    public function testAPhraseOfAnotherProjectIsNotFound(): void
    {
        //Phrase 1 belongs to the `patres` project in this dump. trans_phrases is shared
        //and its rows are whatever the other project renders, so a phrase id alone must
        //never be enough to read one.
        $response = $this->getWithBearer($this->translatorToken(), self::COLLECTION . '/1');

        $this->assertSame(404, $response['status']);
    }

    // ------------------------------------------------------------- writing

    public function testAPatchWritesIsAttributedAndReachesTheCatalogs(): void
    {
        $token  = $this->translatorToken();
        $marker = 'Zugriff verweigert, Agent ' . time();

        $response = $this->patch($token, self::ITEM, ['de' => $marker]);
        $document = $this->decode($response);

        $this->assertSame(200, $response['status'], $response['body']);
        $this->assertSame(['de'], $document['changed']);
        //`phrase`, not the top level: a PATCH answers { changed, phrase }, so that an
        //agent can see both what moved and the whole record it now holds.
        $this->assertSame($marker, $document['phrase']['translations']['de']['text'] ?? null);
        $this->assertArrayNotHasKey(
            'warning',
            $document,
            'the catalogs could not be written, so the site is stale — the API said so, correctly'
        );

        //`de` went in, `de_DE` came to rest. The API speaks languages and the column
        //keeps locales, and this pair of lines is the whole reason that split is safe:
        //a language code reaching the database would key a translation nothing looks up.
        $this->assertSame($marker, $this->storedTranslation(self::PHRASE_ID, 'de_DE'));
        $this->assertNull(
            $this->storedTranslation(self::PHRASE_ID, 'de'),
            'a row was stored under the language code — the boundary conversion leaked'
        );
        $this->assertSame(
            (string) $this->translatorUserId(),
            $this->storedModifiedBy(self::PHRASE_ID, 'de_DE'),
            'modified_by is not the bot, so the admin listing cannot say who wrote this'
        );

        //The write is not finished when the row is written: the site renders from the
        //compiled catalog, and until that is rewritten the page still shows the old
        //text. This is the assertion that fails if the recompile is ever dropped for
        //being slow.
        //Also locale-named: the catalogs the site renders from are de_DE.lang.php.
        $catalog = $this->catalogFor($document['phrase']['textDomain'], 'de_DE');
        $this->assertNotNull($catalog, 'no catalog was written at all');
        $this->assertStringContainsString($marker, $catalog);
    }

    /**
     * An overwrite is recoverable now, and that is the whole point of the table.
     *
     * `trans_translations` keeps no history, so until `trans_translations_history`
     * existed a wrong edit destroyed the previous text with no record — which is why
     * the tooling on the other side of this API dry-runs by default and treats "fill
     * gaps, never overwrite" as a hard rule rather than a preference. This asserts the
     * rule can be relaxed: the text that was there is still readable afterwards.
     */
    public function testAnOverwriteLeavesTheTextItDestroyedInTheHistory(): void
    {
        $token = $this->translatorToken();
        $first = 'Erste Fassung ' . time();
        $this->patch($token, self::ITEM, ['de' => $first]);

        $second = 'Zweite Fassung ' . time();
        $this->patch($token, self::ITEM, ['de' => $second]);

        $document = $this->decode($this->getWithBearer($token, self::ITEM . '/history'));

        $this->assertSame(self::PHRASE_ID, $document['phraseId']);
        $newest = $document['history'][0] ?? [];
        $this->assertSame(
            $first,
            $newest['previous'] ?? null,
            'the overwritten text is not recoverable, so an overwrite is still destructive'
        );
        //Newest first, so a caller reading entry 0 gets the most recent loss rather than
        //the oldest one — which for a phrase edited for ten years is a different answer.
        $this->assertSame('update', $newest['operation'] ?? null);
        $this->assertSame('de', $newest['language'] ?? null, 'the history speaks locales, not languages');
        $this->assertSame(
            $this->translatorUserId(),
            $newest['replacedBy'] ?? null,
            'nothing records who destroyed it'
        );
    }

    /**
     * The reason an overwrite was made, kept against the version it removed.
     *
     * This is what makes a phrase's history a thread instead of a log: an agent that
     * reverses another's choice says why, and the next one to arrive reads that the
     * obvious rendering was already tried and abandoned rather than trying it again.
     */
    public function testAnOverwriteCarriesTheReasonItWasMade(): void
    {
        $token = $this->translatorToken();
        $this->patch($token, self::ITEM, ['de' => 'Zugriff verweigert.']);

        $reason = 'Zugriff is the noun; the UI needs the imperative here.';
        $this->patch($token, self::ITEM, ['de' => 'Zugang verweigert ' . time(), '_note' => $reason]);

        $newest = $this->decode($this->getWithBearer($token, self::ITEM . '/history'))['history'][0] ?? [];
        $this->assertSame($reason, $newest['note'] ?? null);
    }

    /** `_note` is not a language, and must not be mistaken for one in either direction. */
    public function testTheNoteKeyIsNotWrittenAsATranslation(): void
    {
        $token = $this->translatorToken();
        $this->patch($token, self::ITEM, ['de' => 'Vorher ' . time()]);

        $document = $this->decode($this->patch($token, self::ITEM, [
            'de'    => 'Nachher ' . time(),
            '_note' => 'a reason',
        ]));

        $this->assertSame(
            ['de'],
            $document['changed'],
            'the note was counted as a changed language, so the caller is told it wrote something it did not'
        );
        $this->assertNull(
            $this->storedTranslation(self::PHRASE_ID, '_note'),
            'the note was stored as a translation, keyed by something no lookup will ever ask for'
        );
    }

    /** A note longer than the column is trimmed, not a reason to refuse the translation. */
    public function testAnOverlongNoteDoesNotCostTheTranslation(): void
    {
        $token = $this->translatorToken();
        $this->patch($token, self::ITEM, ['de' => 'Erste ' . time()]);

        $text     = 'Zweite ' . time();
        $response = $this->patch($token, self::ITEM, ['de' => $text, '_note' => str_repeat('ü', 400)]);

        $this->assertSame(200, $response['status'], $response['body']);
        $newest = $this->decode($this->getWithBearer($token, self::ITEM . '/history'))['history'][0] ?? [];
        $this->assertSame(255, mb_strlen((string) ($newest['note'] ?? '')), 'the note was not trimmed to the column');
        $this->assertSame($text, $this->storedTranslation(self::PHRASE_ID, 'de_DE'));
    }

    /**
     * Filling an empty language destroys nothing, so it records nothing.
     *
     * The distinction the table exists to make: a history that grew on every write
     * would be a second copy of `trans_translations` and would say nothing about risk.
     */
    public function testFillingAGapWritesNoHistory(): void
    {
        $token = $this->translatorToken();
        //Retract first, so the language really is empty — `_retract` is the one way to
        //reach that, and no browser can send it.
        $this->patch($token, self::ITEM, ['_retract' => ['de']]);
        $before = count($this->decode($this->getWithBearer($token, self::ITEM . '/history'))['history']);

        $this->patch($token, self::ITEM, ['de' => 'Aufgefüllt ' . time()]);

        $after = $this->decode($this->getWithBearer($token, self::ITEM . '/history'))['history'];
        $this->assertCount(
            $before,
            $after,
            'filling an empty language wrote a history entry, so the history is a write log rather than a loss log'
        );
    }

    /** A retraction is a loss too, and says so. */
    public function testARetractionIsRecordedAsOne(): void
    {
        $token = $this->translatorToken();
        $text  = 'Wird zurückgezogen ' . time();
        $this->patch($token, self::ITEM, ['de' => $text]);

        $this->patch($token, self::ITEM, ['_retract' => ['de']]);

        $newest = $this->decode($this->getWithBearer($token, self::ITEM . '/history'))['history'][0] ?? [];
        $this->assertSame('retract', $newest['operation'] ?? null);
        $this->assertSame($text, $newest['previous'] ?? null);
    }

    /**
     * The history is linked from the phrase and not embedded in it.
     *
     * Opt-in was the requirement: a usually-empty list on every item of every page is a
     * field callers learn to skip, and the collection endpoint returns a hundred at a
     * time.
     */
    public function testThePhraseLinksToItsHistoryWithoutCarryingIt(): void
    {
        $document = $this->decode($this->getWithBearer($this->translatorToken(), self::ITEM));

        $this->assertArrayNotHasKey('history', $document, 'the history is embedded, so it is not opt-in');
        $this->assertStringEndsWith(
            '/api/v3/phrases/' . self::PHRASE_ID . '/history',
            $document['meta']['history'] ?? '',
            'nothing links to the history, so an agent has no way to discover it'
        );
    }

    /** One language's thread, and a language that is not one is refused rather than ignored. */
    public function testTheHistoryNarrowsToOneLanguage(): void
    {
        $token = $this->translatorToken();
        $this->patch($token, self::ITEM, ['de' => 'Vorher ' . time(), 'es' => 'Antes ' . time()]);
        $this->patch($token, self::ITEM, ['de' => 'Nachher ' . time(), 'es' => 'Después ' . time()]);

        $all     = $this->decode($this->getWithBearer($token, self::ITEM . '/history'));
        $german  = $this->decode($this->getWithBearer($token, self::ITEM . '/history?language=de'));

        $this->assertGreaterThan(count($german['history']), count($all['history']), 'the filter narrowed nothing');
        $this->assertSame('de', $german['language']);
        foreach ($german['history'] as $entry) {
            $this->assertSame('de', $entry['language']);
        }

        //A locale, not a language — the same thing a PATCH refuses, and for the same
        //reason: silently ignoring it returns every language's argument.
        $refused = $this->getWithBearer($token, self::ITEM . '/history?language=de_DE');
        $this->assertSame(422, $refused['status'], $refused['body']);
    }

    /**
     * The schema describes the write surface that actually exists.
     *
     * The schema endpoint is the one an agent author reads *instead of* the source, and
     * the consuming repository confirmed it uses it at request time to decide what to
     * send. So a schema that omits something is not a documentation gap, it is a feature
     * nobody will ever use — `_note` was invisible until this assertion existed — and a
     * schema that claims something untrue is worse. Each of these is checked against the
     * behaviour rather than against a copy of the text.
     */
    public function testTheSchemaDescribesTheNoteKeyItAccepts(): void
    {
        $schema = $this->decode($this->get('/api/v3/schema/phrase'));

        $key = $schema['writable']['note']['key'] ?? null;
        $this->assertIsString($key, 'the schema does not mention the note key, so no agent will send one');

        //The key it publishes is the key that works. A schema naming `note` while the
        //controller strips `_note` would be worse than silence: the agent's reasoning
        //would be refused as an unknown language and it would stop sending it.
        $token  = $this->translatorToken();
        $reason = 'schema says this key works ' . time();
        $this->patch($token, self::ITEM, ['de' => 'Erst ' . time()]);
        $this->patch($token, self::ITEM, ['de' => 'Dann ' . time(), $key => $reason]);

        $newest = $this->decode($this->getWithBearer($token, self::ITEM . '/history'))['history'][0] ?? [];
        $this->assertSame($reason, $newest['note'] ?? null, 'the key the schema publishes did not carry a note');
        $this->assertSame(
            255,
            $schema['writable']['note']['maxLength'] ?? null,
            'the published note length does not match the column'
        );
    }

    /** Every operation the schema names is one the history can actually contain. */
    public function testTheSchemaNamesTheHistoryOperations(): void
    {
        $schema = $this->decode($this->get('/api/v3/schema/phrase'));

        $operations = $schema['history']['operations'] ?? [];
        foreach (['update', 'retract', 'retire'] as $operation) {
            $this->assertArrayHasKey(
                $operation,
                $operations,
                "the schema does not explain the `$operation` entries an agent will meet"
            );
        }

        $this->assertStringContainsString(
            '/history',
            $schema['endpoints']['history'] ?? '',
            'the history subresource is not listed, so it is undiscoverable from the schema'
        );
    }

    /**
     * The schema publishes the retraction key, and the key it publishes is the one that works.
     *
     * The schema said the opposite until 2026-08-11 — "there is no way to remove a
     * translation through either surface" — which was stale in the dangerous direction: an
     * agent reading it would blank with `""`, a documented no-op, and believe it had removed
     * something. Pinned to behaviour rather than to a copy of its own text: the published key
     * is the one this test sends.
     */
    public function testTheSchemaPublishesTheKeyThatRetracts(): void
    {
        $schema = $this->decode($this->get('/api/v3/schema/phrase'));

        $key = $schema['writable']['retract']['key'] ?? null;
        $this->assertIsString($key, 'the schema does not publish a retraction key, so it is undiscoverable');

        $token = $this->translatorToken();
        $this->patch($token, self::ITEM, ['de' => 'Wird entfernt ' . time()]);
        $this->patch($token, self::ITEM, [$key => ['de']]);

        $this->assertNull(
            $this->storedTranslation(self::PHRASE_ID, 'de_DE'),
            'the published key did not retract, so the schema promises something that does not happen'
        );
    }

    /**
     * A null keyed by a language is refused, and the refusal names the alternative.
     *
     * Until 2026-08-11 it deleted the translation. That is the accident the consuming
     * repository asked about in §11.3 and it is easy to reach without meaning it: a
     * serializer emitting null for an absent optional field, a dictionary comprehension over
     * a language list where one lookup misses, `json.dumps` of a Python `None`. None of them
     * look like a deletion at the call site, and all of them were one.
     */
    public function testANullDoesNotRemoveATranslation(): void
    {
        $token = $this->translatorToken();
        $text  = 'Bleibt stehen ' . time();
        $this->patch($token, self::ITEM, ['de' => $text]);

        $response = $this->patch($token, self::ITEM, ['de' => null]);

        $this->assertSame(422, $response['status'], $response['body']);
        $this->assertStringContainsString(
            '_retract',
            $response['body'],
            'the refusal does not name the key that does remove a translation, so a caller learns '
            . 'only that it is wrong'
        );
        $this->assertSame(
            $text,
            $this->storedTranslation(self::PHRASE_ID, 'de_DE'),
            'a null destroyed the translation anyway'
        );
    }

    /** Writing and retracting the same language in one request is refused, not resolved. */
    public function testALanguageCannotBeWrittenAndRetractedAtOnce(): void
    {
        $token = $this->translatorToken();
        $text  = 'Widerspruch ' . time();
        $this->patch($token, self::ITEM, ['de' => $text]);

        $response = $this->patch($token, self::ITEM, ['de' => 'Neu ' . time(), '_retract' => ['de']]);

        $this->assertSame(422, $response['status'], $response['body']);
        $this->assertSame(
            $text,
            $this->storedTranslation(self::PHRASE_ID, 'de_DE'),
            'a contradictory request was carried out in one of its two directions'
        );
    }

    /**
     * `""` alongside a retraction of the same language is not a contradiction.
     *
     * A client that sends every language on every request — the shape the web form produces
     * and a generated client naturally would — must be able to retract one without also
     * having to omit it, since `""` already means "leave this language alone".
     */
    public function testAnEmptyStringDoesNotContradictARetraction(): void
    {
        $token = $this->translatorToken();
        $this->patch($token, self::ITEM, ['de' => 'Verschwindet ' . time()]);

        $response = $this->patch($token, self::ITEM, ['de' => '', 'es' => '', '_retract' => ['de']]);

        $this->assertSame(200, $response['status'], $response['body']);
        $this->assertNull(
            $this->storedTranslation(self::PHRASE_ID, 'de_DE'),
            'the retraction was refused because the same language was also present as ""'
        );
    }

    /** An unknown language in the retraction list is refused the way one in the body is. */
    public function testAnUnknownLanguageInTheRetractionListIsRefused(): void
    {
        $response = $this->patch($this->translatorToken(), self::ITEM, ['_retract' => ['de_DE']]);

        $this->assertSame(422, $response['status'], $response['body']);
        $this->assertStringContainsString('unknownLanguages', $response['body'], $response['body']);
    }

    // ------------------------------------------------------------- retirement

    /**
     * A retirement takes the phrase off the worklist and touches no translation.
     *
     * The two halves are asserted together on purpose, because the failure that matters is
     * a retirement implemented as "retract every language" — which would empty the
     * collection entry too, and pass a test that only looked at the listing.
     */
    public function testRetiringHidesThePhraseAndKeepsEveryTranslation(): void
    {
        $token = $this->translatorToken();
        $this->patch($token, self::ITEM, ['de' => 'Bleibt erhalten ' . time()]);
        $before = $this->storedTranslation(self::PHRASE_ID, 'de_DE');

        $response = $this->post($token, self::ITEM . '/retire', ['_note' => 'Capsule smoke test.']);
        $body     = $this->decode($response);

        self::assertSame(200, $response['status'], $response['body']);
        self::assertTrue($body['changed'] ?? null, 'the retirement reported no change');
        self::assertNotNull(
            $body['phrase']['meta']['retiredOn'] ?? null,
            'the document does not say the phrase is retired, so a caller auditing '
            . '?onlyRetired=1 cannot tell a retired row from a live one'
        );
        self::assertSame(
            $before,
            $this->storedTranslation(self::PHRASE_ID, 'de_DE'),
            'retiring the phrase changed a translation. A retirement is about the phrase; nothing '
            . 'about it may touch trans_translations.'
        );
    }

    /** Retired rows leave the default collection and are reachable with `onlyRetired`. */
    public function testARetiredPhraseLeavesTheCollectionAndIsAuditable(): void
    {
        $token = $this->translatorToken();
        $this->post($token, self::ITEM . '/retire', ['_note' => 'Capsule smoke test.']);

        $live = $this->decode($this->getWithBearer($token, self::COLLECTION . '?limit=500'));
        self::assertNotContains(
            self::PHRASE_ID,
            array_column($live['items'], 'phraseId'),
            'a retired phrase is still being offered as work'
        );

        $retired = $this->decode($this->getWithBearer($token, self::COLLECTION . '?onlyRetired=1&limit=500'));
        self::assertContains(
            self::PHRASE_ID,
            array_column($retired['items'], 'phraseId'),
            'onlyRetired does not list the phrase, so an agent cannot audit its own retirements or '
            . 'notice one that came back'
        );
    }

    /**
     * The note is required, and the refusal names the operation people confuse this with.
     *
     * Mandatory because it is the entire record of a judgement — read at the one moment it
     * matters, when the row is back on the worklist and somebody has to decide whether the
     * retirement was wrong or the code that files the phrase is.
     */
    public function testRetiringWithoutANoteIsRefused(): void
    {
        $token = $this->translatorToken();

        foreach ([[], ['_note' => ''], ['_note' => '   '], ['_note' => 42]] as $body) {
            $response = $this->post($token, self::ITEM . '/retire', $body);
            self::assertSame(422, $response['status'], json_encode($body) . ' => ' . $response['body']);
            self::assertStringContainsString('_retract', $response['body'], 'the refusal does not '
                . 'distinguish retiring a phrase from retracting a translation');
        }

        self::assertNull(
            $this->retiredOn(self::PHRASE_ID),
            'a refused retirement retired the phrase anyway'
        );
    }

    /** A language in the body is a caller reaching for a retraction, and is told so. */
    public function testALanguageKeyOnTheRetireEndpointIsRefused(): void
    {
        $response = $this->post(
            $this->translatorToken(),
            self::ITEM . '/retire',
            ['_note' => 'Wrong shape', 'de' => null]
        );

        self::assertSame(422, $response['status'], $response['body']);
        self::assertStringContainsString('unexpectedKeys', $response['body'], $response['body']);
        self::assertNull($this->retiredOn(self::PHRASE_ID), 'the phrase was retired despite the 422');
    }

    /**
     * One event per state change: a retry is a 200 that changed nothing and recorded nothing.
     *
     * Without this an agent whose request timed out and retried would file two judgements
     * about one row, and the second would be the one a reader sees first.
     */
    public function testRetiringTwiceRecordsOneEvent(): void
    {
        $token = $this->translatorToken();
        $note  = 'Idempotence check ' . time();

        $first  = $this->decode($this->post($token, self::ITEM . '/retire', ['_note' => $note]));
        $second = $this->post($token, self::ITEM . '/retire', ['_note' => $note]);

        self::assertTrue($first['changed'] ?? null);
        self::assertSame(200, $second['status'], $second['body']);
        self::assertFalse(
            $this->decode($second)['changed'] ?? null,
            'retiring an already-retired phrase reported a change'
        );
        self::assertSame(
            1,
            $this->countHistory(self::PHRASE_ID, 'retire', $note),
            'the retry wrote a second retire entry'
        );
    }

    /**
     * The history records the retirement as a phrase event, with the note, in every thread.
     *
     * `operation: retire`, `language: null` and an empty `previous` — the shape a reader has
     * to branch on, and the one that says nothing was destroyed. A caller that treated it as
     * a loss would report an incident that did not happen.
     */
    public function testTheRetirementIsRecordedAsAPhraseEvent(): void
    {
        $token = $this->translatorToken();
        $note  = 'Retired because the breadcrumb filed a record name ' . time();

        $this->post($token, self::ITEM . '/retire', ['_note' => $note]);

        $newest = $this->decode($this->getWithBearer($token, self::ITEM . '/history'))['history'][0] ?? [];
        self::assertSame('retire', $newest['operation'] ?? null, json_encode($newest));
        self::assertSame($note, $newest['note'] ?? null, 'the note is not in the history, so the '
            . 'judgement has no record at all');
        self::assertArrayHasKey('language', $newest, json_encode($newest));
        self::assertNull($newest['language'], 'a retirement is not about a language');
        self::assertSame('', $newest['previous'] ?? null, 'a retirement destroyed something');

        //Every language's thread, because the event is about the phrase.
        $german = $this->decode($this->getWithBearer($token, self::ITEM . '/history?language=de'));
        self::assertSame('retire', $german['history'][0]['operation'] ?? null, $german['history'][0]['operation'] ?? '');
    }

    /** Un-retirement is reversible from the API and says why, in its own operation. */
    public function testUnretiringPutsThePhraseBackAndRecordsWhy(): void
    {
        $token = $this->translatorToken();
        $this->post($token, self::ITEM . '/retire', ['_note' => 'Retired for the un-retire test.']);

        $response = $this->post($token, self::ITEM . '/unretire', ['_note' => 'Wrong call: still rendered.']);
        $body     = $this->decode($response);

        self::assertSame(200, $response['status'], $response['body']);
        self::assertTrue($body['changed'] ?? null);
        self::assertArrayHasKey('retiredOn', $body['phrase']['meta'], $response['body']);
        self::assertNull($body['phrase']['meta']['retiredOn'], 'the phrase is still retired');
        self::assertNull($this->retiredOn(self::PHRASE_ID), 'retired_on was not cleared in the table');
        self::assertSame(
            1,
            $this->countHistory(self::PHRASE_ID, 'unretire', 'Wrong call: still rendered.'),
            'the un-retirement left no record, so a thread reads as though the phrase is still retired'
        );
    }

    /** A GET of a retirement path names the verb that works rather than 404ing. */
    public function testAGetOfTheRetirePathIsRefusedWithAllow(): void
    {
        $response = $this->request('GET', self::ITEM . '/retire');

        self::assertSame(405, $response['status']);
        self::assertSame('POST', $response['headers']['allow'] ?? null);
    }

    /** Retirement is gated on the same role as the rest of the resource, and no lower. */
    public function testAnonymousAndShrineBotCallersCannotRetire(): void
    {
        $anonymous = $this->requestWithBody(
            'POST',
            self::ITEM . '/retire',
            ['Content-Type: application/json'],
            '{"_note":"nope"}'
        );
        self::assertSame(401, $anonymous['status'], $anonymous['body']);

        $shrineBot = $this->post($this->shrineBotToken(), self::ITEM . '/retire', ['_note' => 'nope']);
        self::assertSame(401, $shrineBot['status'], $shrineBot['body']);

        self::assertNull($this->retiredOn(self::PHRASE_ID), 'an unauthorized caller retired the phrase');
    }

    /**
     * The schema's retirement claims are checked against the endpoint, not against themselves.
     *
     * The property that matters: an agent that only reads the schema must be able to find
     * this capability *and* be told it is not `_retract`. Both halves are asserted, and the
     * path is taken out of the published string rather than typed here, so a schema that
     * advertises a path nobody serves fails.
     */
    public function testTheSchemaPublishesRetirementAndTheDistinction(): void
    {
        $schema     = $this->decode($this->get('/api/v3/schema/phrase'));
        $retirement = $schema['retirement'] ?? [];

        self::assertNotSame([], $retirement, 'the schema does not mention retirement, so an agent '
            . 'reading it cannot discover the capability and will reach for _retract instead');
        self::assertStringContainsString(
            '_retract',
            implode(' ', array_map('strval', $retirement)),
            'the retirement block does not name the operation it is confused with'
        );
        self::assertStringContainsString(
            'retirement',
            (string) ($schema['writable']['retract']['notRetirement'] ?? ''),
            'the retract key does not point at retirement, so a caller looking for "make this row go '
            . 'away" is told only about the destructive option'
        );

        self::assertSame(
            1,
            preg_match('#(/api/v3/phrases/\{[A-Za-z]+\}/retire)#', (string) ($retirement['retire'] ?? ''), $m),
            'no retire path is published'
        );
        $path = str_replace(['{phraseId}', '{phrase_id}', '{id}'], (string) self::PHRASE_ID, $m[1]);

        $response = $this->post($this->translatorToken(), $path, ['_note' => 'Published path check.']);
        self::assertSame(200, $response['status'], $path . ' => ' . $response['body']);
    }

    /** `retired_on` straight from the table, since the API hides retired rows by default. */
    private function retiredOn(int $phraseId): ?string
    {
        $statement = $this->pdo()->prepare(
            'SELECT retired_on FROM trans_phrases WHERE translation_phrase_id = :id'
        );
        $statement->execute(['id' => $phraseId]);
        $value = $statement->fetchColumn();

        return false === $value || null === $value ? null : (string) $value;
    }

    /** How many history rows this phrase has for one operation and note. */
    private function countHistory(int $phraseId, string $operation, string $note): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM trans_translations_history'
            . ' WHERE translation_phrase_id = :id AND operation = :op AND notes = :note'
        );
        $statement->execute(['id' => $phraseId, 'op' => $operation, 'note' => $note]);

        return (int) $statement->fetchColumn();
    }

    /** Another project's phrase is not found here either, for the reason `show` is not. */
    public function testTheHistoryOfAnotherProjectsPhraseIsNotFound(): void
    {
        $response = $this->getWithBearer(
            $this->translatorToken(),
            //Phrase 1 belongs to the `patres` project in this dump, same as the
            //`show` case above: a phrase id alone must never be enough to read one,
            //and a history is as much that project's as its translations are.
            self::COLLECTION . '/1/history'
        );

        $this->assertSame(404, $response['status'], $response['body']);
    }

    /** Re-sending what is already stored is a 200 that writes nothing. */
    public function testResendingTheStoredTextChangesNothing(): void
    {
        $token = $this->translatorToken();
        $this->patch($token, self::ITEM, ['de' => 'Unverändert.']);

        $document = $this->decode($this->patch($token, self::ITEM, ['de' => 'Unverändert.']));

        $this->assertSame([], $document['changed']);
        $this->assertSame('Unverändert.', $document['phrase']['translations']['de']['text']);
    }

    /**
     * An empty translation means "leave this locale alone", and `changed` has to say so.
     *
     * `TranslationsTable::updatePhrase()` ignores any falsy value — that is how the web
     * form lets a translator decline a language, an empty textarea being the whole of
     * the gesture. The first version of this endpoint counted such a locale as changed
     * anyway, so `{"de_DE": ""}` answered `"changed": ["de_DE"]` with the stored text
     * untouched, and the caller was billed a full catalog recompile for a write that
     * never happened. A polling agent would have done that forever.
     *
     * The assertion that matters is the second one: the text is still there. The first
     * would pass on its own if the endpoint had instead started *blanking* translations,
     * which is the opposite bug and a destructive one.
     */
    public function testAnEmptyTranslationIsANoOpAndIsReportedAsOne(): void
    {
        $token = $this->translatorToken();
        $this->patch($token, self::ITEM, ['de' => 'Vorhanden.']);

        $document = $this->decode($this->patch($token, self::ITEM, ['de' => '']));

        $this->assertSame([], $document['changed']);
        $this->assertSame('Vorhanden.', $this->storedTranslation(self::PHRASE_ID, 'de_DE'));
    }

    /**
     * Validation is the translator form's, verbatim. 2000 is
     * EditPhraseForm::TRANSLATION_MAX_LENGTH, which is `trans_translations.translation`
     * — so this is also the assertion that an agent cannot provoke a database error the
     * moderator form would have caught.
     */
    public function testAnOverlongTranslationIsRefusedWithTheFormsMessage(): void
    {
        $response = $this->patch($this->translatorToken(), self::ITEM, ['de' => str_repeat('a', 2001)]);

        $this->assertSame(422, $response['status']);
        $this->assertStringContainsString('stringLengthTooLong', $response['body']);
    }

    /**
     * @return iterable<string, array{0: string}>
     *
     * The locale form is in here deliberately. It is the mistake an agent is *most*
     * likely to make — the database uses it, the admin GUI shows it, and it looks
     * right — so accepting it would make the stored key depend on which spelling the
     * caller happened to send.
     */
    public static function refusedLanguageKeys(): iterable
    {
        yield 'locale form'      => ['de_DE'];
        yield 'hyphenated locale' => ['de-DE'];
        yield 'not a language'   => ['klingon'];
    }

    #[DataProvider('refusedLanguageKeys')]
    public function testAnythingButALanguageCodeIsRefusedRatherThanIgnored(string $key): void
    {
        $response = $this->patch($this->translatorToken(), self::ITEM, [$key => 'falsch']);
        $document = $this->decode($response);

        $this->assertSame(422, $response['status'], $key);
        $this->assertSame([$key], $document['error']['unknownLanguages']);
        //The refusal names what would have worked, so an agent can correct itself
        //without a second round trip to the schema.
        $this->assertContains('de', $document['error']['writableLanguages']);
    }

    public function testAStaleIfMatchIsRefused(): void
    {
        $response = $this->patch(
            $this->translatorToken(),
            self::ITEM,
            ['de' => 'Zu spät.'],
            'W/"0000000000000000000000000000dead"'
        );

        $this->assertSame(412, $response['status']);
    }

    /**
     * The conditional write an agent should actually be making. Also the regression
     * test for the `-gzip` suffix Apache appends to the ETag of a compressed response:
     * curl here sends `Accept-Encoding`, so the tag echoed back is the rewritten one,
     * and comparing the raw strings would refuse every correct read-modify-write.
     */
    public function testAFreshIfMatchIsHonoured(): void
    {
        $token = $this->translatorToken();
        $etag  = $this->getWithBearer($token, self::ITEM)['headers']['etag'] ?? '';
        $this->assertNotSame('', $etag);

        $response = $this->patch($token, self::ITEM, ['de' => 'Rechtzeitig.'], $etag);

        $this->assertSame(200, $response['status'], $response['body']);
        $this->assertSame(['de'], $this->decode($response)['changed']);
    }

    // --------------------------------------------------------------- batch

    /**
     * A batch's interesting outcome is the partial one: one bad entry must not stop the
     * good ones, or an agent working through a text domain is halted by its own worst
     * guess and has to bisect to find it.
     */
    public function testABatchAppliesTheGoodEntriesAndReportsTheRest(): void
    {
        $response = $this->patch($this->translatorToken(), self::COLLECTION, [
            'phrases' => [
                (string) self::PHRASE_ID  => ['de' => 'Erstens.'],
                (string) self::PHRASE_TWO => ['de' => 'Zweitens.'],
                '999999999'               => ['de' => 'Kein Satz.'],
                (string) self::PHRASE_ID . '0000' => ['de-DE' => 'Falsches Gebietsschema.'],
            ],
        ]);
        $document = $this->decode($response);

        $this->assertSame(200, $response['status'], $response['body']);
        $this->assertTrue($document['results'][(string) self::PHRASE_ID]['ok']);
        $this->assertTrue($document['results'][(string) self::PHRASE_TWO]['ok']);
        $this->assertFalse($document['results']['999999999']['ok']);
        $this->assertSame(404, $document['results']['999999999']['status']);
        $this->assertSame(2, $document['summary']['applied']);
        $this->assertSame(2, $document['summary']['failed']);

        $this->assertSame('Erstens.', $this->storedTranslation(self::PHRASE_ID, 'de_DE'));
        $this->assertSame('Zweitens.', $this->storedTranslation(self::PHRASE_TWO, 'de_DE'));
    }

    public function testABatchWithoutAPhrasesObjectIsRefused(): void
    {
        $response = $this->patch($this->translatorToken(), self::COLLECTION, ['de' => 'nope']);

        $this->assertSame(400, $response['status']);
    }

    /**
     * The ceiling exists so a batch cannot run into `max_execution_time` half written.
     * Refused before anything is applied, which is the only refusal worth having.
     */
    public function testAnOversizedBatchIsRefusedBeforeAnythingIsWritten(): void
    {
        $entries = [];
        for ($i = 0; $i < 201; $i++) {
            $entries[(string) (900000 + $i)] = ['de' => 'zu viel'];
        }

        $response = $this->patch($this->translatorToken(), self::COLLECTION, ['phrases' => $entries]);

        $this->assertSame(422, $response['status']);
        $this->assertStringContainsString('maxBatch', $response['body']);
    }

    // ------------------------------------------------------------- fixtures

    /** Every translation row of the phrases this test touches, as it was found. */
    private function rememberPhrases(): void
    {
        //`retired_on` too, because the retirement endpoints move it and a test that leaves a
        //capsule phrase retired changes what every later test's collection query returns.
        $statement = $this->pdo()->prepare(
            'SELECT translation_phrase_id, retired_on FROM trans_phrases WHERE translation_phrase_id IN (?, ?)'
        );
        $statement->execute([self::PHRASE_ID, self::PHRASE_TWO]);
        $this->originalRetirement = $statement->fetchAll(PDO::FETCH_KEY_PAIR);

        $rows = [];
        foreach ([self::PHRASE_ID, self::PHRASE_TWO] as $phraseId) {
            $statement = $this->pdo()->prepare(
                'SELECT * FROM trans_translations WHERE translation_phrase_id = :id'
            );
            $statement->execute(['id' => $phraseId]);
            $rows[$phraseId] = $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->originalRows = $rows;
    }

    /**
     * Put them back exactly, including the rows that did not exist.
     *
     * A DELETE followed by re-INSERT rather than an UPDATE, because the API can create
     * a row where there was none and no UPDATE undoes that. The primary keys are
     * restored too, so `translation_id` values referenced anywhere else stay stable.
     */
    private function restorePhrases(): void
    {
        $pdo = $this->pdo();
        foreach ($this->originalRetirement as $phraseId => $retiredOn) {
            $pdo->prepare('UPDATE trans_phrases SET retired_on = :on WHERE translation_phrase_id = :id')
                ->execute(['on' => $retiredOn, 'id' => $phraseId]);
        }
        $this->originalRetirement = [];
        //The history is append-only by design, so the rows this test's retirements produced
        //cannot be "restored" — they are deleted, the way TranslationSmokeTest deletes its
        //own. Scoped to the two fixture phrases and to phrase-level events, so a retraction
        //recorded by another test in the same run survives.
        $pdo->prepare(
            'DELETE FROM trans_translations_history WHERE translation_phrase_id IN (?, ?)'
            . " AND operation IN ('retire', 'unretire')"
        )->execute([self::PHRASE_ID, self::PHRASE_TWO]);

        if (null === $this->originalRows) {
            return;
        }

        foreach ($this->originalRows as $phraseId => $rows) {
            $pdo->prepare('DELETE FROM trans_translations WHERE translation_phrase_id = :id')
                ->execute(['id' => $phraseId]);

            foreach ($rows as $row) {
                $pdo->prepare(
                    'INSERT INTO trans_translations'
                    . ' (translation_id, translation_phrase_id, locale, translation, modified_by, modified_on)'
                    . ' VALUES (:tid, :pid, :locale, :translation, :by, :on)'
                )->execute([
                    'tid'         => $row['translation_id'],
                    'pid'         => $row['translation_phrase_id'],
                    'locale'      => $row['locale'],
                    'translation' => $row['translation'],
                    'by'          => $row['modified_by'],
                    'on'          => $row['modified_on'],
                ]);
            }
        }

        $this->originalRows = null;
    }

    private function storedTranslation(int $phraseId, string $locale): ?string
    {
        return $this->translationColumn($phraseId, $locale, 'translation');
    }

    private function storedModifiedBy(int $phraseId, string $locale): ?string
    {
        return $this->translationColumn($phraseId, $locale, 'modified_by');
    }

    private function translationColumn(int $phraseId, string $locale, string $column): ?string
    {
        $statement = $this->pdo()->prepare(
            "SELECT `$column` FROM trans_translations"
            . ' WHERE translation_phrase_id = :id AND locale = :locale'
        );
        $statement->execute(['id' => $phraseId, 'locale' => $locale]);
        $value = $statement->fetchColumn();

        return false === $value || null === $value ? null : (string) $value;
    }

    /**
     * The compiled catalog for a text domain and locale, or null.
     *
     * Read from the repository working tree, which is bind-mounted into the container,
     * so what the app wrote is what this reads. These files are gitignored and
     * generated — a fresh checkout has none, which is why the test that uses this
     * writes first.
     *
     * **Two locations, and this used to know only one of them.** A text domain that names
     * a loaded module is exported *into that module* — `module/Schoenstatt/language/` —
     * and every other domain to `language/<Domain>/`. Until 2026-09-08 this method looked
     * only in the second place and the test passed, because nothing under the Symfony
     * front controller had ever told `TranslationsTable` which domains are modules: this
     * API's own write put every module domain in the wrong directory, and the assertion
     * was written against what it found. See `App\Laminas\TranslationsTableConfigurator`.
     *
     * Both are checked rather than the module one alone, because the domain is a
     * parameter and some of them genuinely are not modules.
     */
    private function catalogFor(string $textDomain, string $locale): ?string
    {
        $candidates = [
            __DIR__ . '/../../module/' . $textDomain . '/language/' . $locale . '.lang.php',
            __DIR__ . '/../../language/' . $textDomain . '/' . $locale . '.lang.php',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return (string) file_get_contents($path);
            }
        }

        return null;
    }

    // --------------------------------------------------------------- tokens

    private function translatorToken(): string
    {
        return $this->registeredToken($this->translatorUserId(), time() + 600);
    }

    private function shrineBotToken(): string
    {
        return $this->registeredToken($this->shrineBotUserId(), time() + 600);
    }

    private function translatorUserId(): int
    {
        return $this->translatorUserId ??= $this->accountWithRole('sch_api_translator');
    }

    private function shrineBotUserId(): int
    {
        return $this->shrineBotUserId ??= $this->accountWithRole('sch_api_bot');
    }

    private function accountWithRole(string $role): int
    {
        $email = $this->uniqueEmail();
        $pdo   = $this->pdo();

        $pdo->prepare(
            'INSERT INTO user (username, email, display_name, state, create_datetime, update_datetime)'
            . " VALUES (:email, :email2, 'API bot', 1, NOW(), NOW())"
        )->execute(['email' => $email, 'email2' => $email]);
        $userId = (int) $pdo->lastInsertId();

        $linked = $pdo->prepare(
            'INSERT INTO user_role_linker (user_id, role_id) SELECT :user_id, id FROM user_role WHERE role_id = :role'
        );
        $linked->execute(['user_id' => $userId, 'role' => $role]);

        $this->assertSame(
            1,
            $linked->rowCount(),
            "the role `$role` does not exist in this database — apply database/db6.8.sql"
        );

        return $userId;
    }

    /** A token that is both correctly signed and recorded in `user_api_token`. */
    private function registeredToken(int $userId, int $expiresAt): string
    {
        $jti = substr(str_replace(['+', '/', '='], 'a', base64_encode(random_bytes(48))), 0, 43);

        $this->pdo()->prepare(
            'INSERT INTO user_api_token (jti, user_id, label, issued_on, expires_on)'
            . ' VALUES (:jti, :user_id, :label, NOW(), FROM_UNIXTIME(:expires))'
        )->execute([
            'jti'     => $jti,
            'user_id' => $userId,
            'label'   => 'phrase smoke test',
            'expires' => $expiresAt,
        ]);

        $key       = $this->cypherKey();
        $header    = $this->base64Url((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $claims    = $this->base64Url((string) json_encode(['sub' => $userId, 'exp' => $expiresAt, 'jti' => $jti]));
        $signature = $this->base64Url(hash_hmac('sha256', $header . '.' . $claims, $key, true));

        return $header . '.' . $claims . '.' . $signature;
    }

    private function cypherKey(): string
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 2) . '/config/autoload/local.php';
        $key    = $config['ApiRequest']['jwtAuth']['cypherKey'] ?? null;

        if (! is_string($key) || '' === $key) {
            $this->markTestSkipped('ApiRequest.jwtAuth.cypherKey is not configured here');
        }

        return $key;
    }

    private function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    // -------------------------------------------------------------- requests

    /**
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    private function getWithBearer(string $token, string $path = self::ITEM): array
    {
        return $this->request('GET', $path, ['Authorization: Bearer ' . $token]);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    /** @param array<string, mixed> $body */
    private function post(string $token, string $path, array $body): array
    {
        return $this->requestWithBody(
            'POST',
            $path,
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            (string) json_encode($body)
        );
    }

    private function patch(string $token, string $path, array $body, ?string $ifMatch = null): array
    {
        $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
        if (null !== $ifMatch) {
            $headers[] = 'If-Match: ' . $ifMatch;
        }

        return $this->requestWithBody('PATCH', $path, $headers, (string) json_encode($body));
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    private function requestWithBody(string $method, string $path, array $headers, string $body): array
    {
        $responseHeaders = [];
        $ch = curl_init($this->baseUrl() . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_ENCODING       => '',
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (2 === count($parts)) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);
        $responseBody = curl_exec($ch);
        $status       = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType  = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        return [
            'status'      => $status,
            'redirect'    => '',
            'body'        => is_string($responseBody) ? $responseBody : '',
            'contentType' => $contentType,
            'headers'     => $responseHeaders,
        ];
    }

    /**
     * @param array{status: int, body: string, headers?: array<string, string>} $response
     * @return array<string, mixed>
     */
    private function decode(array $response): array
    {
        $document = json_decode($response['body'], true);
        $this->assertIsArray($document, 'the response was not JSON: ' . substr($response['body'], 0, 300));

        return $document;
    }

    private function pdo(): PDO
    {
        return new PDO(
            'mysql:host=db;dbname=ourlink_db1;charset=utf8mb4',
            'schoenstatt',
            'schoenstatt',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}
