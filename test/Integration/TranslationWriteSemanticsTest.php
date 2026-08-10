<?php

namespace SchoenstattTest\Integration;

use JTranslate\Model\PhraseIdentity;
use JTranslate\Model\TranslationsTable;
use Laminas\Db\Adapter\Adapter;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * What a submitted locale value means, and that a failed discovery leaves nothing behind.
 *
 * ## The three meanings, and why the difference is dangerous to get wrong
 *
 * `updatePhrase()` has two callers with opposite conventions. The web form renders every
 * locale as a textarea on every edit, so an untouched form posts `''` for every language
 * the translator did not fill in — `''` therefore has to mean "leave this alone", or
 * saving one language would wipe the other three. An HTTP API can distinguish a JSON
 * `null` from `""`, so `null` is what means "retract this translation".
 *
 * The old code collapsed both into a single falsy test, which had two consequences: there
 * was no way to retract a translation at all, and a translation of literally `'0'` could
 * not be saved because `'0'` is falsy in PHP.
 *
 * Every assertion here is about a destructive operation, which is why they are worth
 * pinning rather than eyeballing: a regression that made `''` clear a row would silently
 * destroy translator work on the next save, and nothing else in the suite would notice.
 *
 * ## The database is left exactly as it was found
 *
 * Every write happens inside one transaction on the shared adapter, rolled back in a
 * `finally`. TranslationUpdateScopeTest owns the assertion that the adapter this test
 * opens its transaction on is the one the table writes through; this relies on it.
 *
 * Needs a running capsule's database, and skips rather than fails without one.
 */
class TranslationWriteSemanticsTest extends TestCase
{
    private ServiceManager $container;

    private Adapter $adapter;

    private TranslationsTable $table;

    private string $project;

    private string $textDomain = 'IntegrationTestDomain';

    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        $container = new ServiceManager();
        (new ServiceManagerConfig($appConfig['service_manager'] ?? []))->configureServiceManager($container);
        $container->setService('ApplicationConfig', $appConfig);
        $container->get('ModuleManager')->loadModules();
        $this->container = $container;

        try {
            /** @var Adapter $adapter */
            $adapter = $container->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
        } catch (\Throwable $e) {
            self::markTestSkipped(
                'no reachable database: ' . $e->getMessage() . ' — needs the capsule up (docker compose up -d)'
            );
        }
        $this->adapter = $adapter;

        try {
            /** @var TranslationsTable $table */
            $table = $container->get(TranslationsTable::class);
        } catch (\Throwable $e) {
            self::markTestSkipped('TranslationsTable is not constructible here: ' . $e->getMessage());
        }
        $this->table = $table;
        //No session in this process; without this the acting-user provider answers null,
        //which is fine, but pinning it keeps modified_by deterministic.
        $this->table->setActingUserId(null);

        $config        = $container->get('JTranslate\Config');
        $this->project = (string) $config['project_name'];
    }

    /**
     * The empty string leaves every other language alone.
     *
     * This is the web form's save, reproduced exactly: one language filled in, the rest
     * posted empty. If `''` ever starts meaning "clear", this is the test that fails
     * before a translator loses work.
     */
    public function testAnEmptyStringLeavesAStoredTranslationAlone(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $id = $this->insertPhrase('Empty means leave alone ' . bin2hex(random_bytes(5)));
            $this->insertTranslation($id, 'es_ES', 'No me toques');
            $this->insertTranslation($id, 'de_DE', 'Nicht anfassen');

            //what an untouched form posts for the languages the translator skipped
            $this->table->updatePhrase($id, ['es_ES' => '', 'de_DE' => '', 'pt_BR' => 'Novo']);

            $phrase = $this->table->getPhraseById($id);
            self::assertSame('No me toques', $phrase['es_ES'] ?? null, 'an empty submission wiped Spanish');
            self::assertSame('Nicht anfassen', $phrase['de_DE'] ?? null, 'an empty submission wiped German');
            self::assertSame('Novo', $phrase['pt_BR'] ?? null, 'the one language that was filled in was not written');
        } finally {
            $connection->rollback();
        }
    }

    /**
     * Whitespace-only counts as empty, because StringTrim has already run by then.
     */
    public function testWhitespaceOnlyAlsoLeavesAStoredTranslationAlone(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $id = $this->insertPhrase('Whitespace means leave alone ' . bin2hex(random_bytes(5)));
            $this->insertTranslation($id, 'es_ES', 'Intacto');

            //The form's StringTrim turns '   ' into ''. Submitting the trimmed value is
            //what updatePhrase() actually receives, and the controller is documented to
            //pass getData() rather than the raw post for exactly this reason.
            $this->table->updatePhrase($id, ['es_ES' => '']);

            self::assertSame('Intacto', $this->table->getPhraseById($id)['es_ES'] ?? null);
        } finally {
            $connection->rollback();
        }
    }

    /**
     * An explicit null deletes the row.
     *
     * Deleted rather than blanked so "untranslated" has one representation. `untranslatedIn`
     * has to test for both anyway because older code created blanks, but nothing should be
     * adding to them.
     */
    public function testAnExplicitNullRetractsTheTranslation(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $id = $this->insertPhrase('Null retracts ' . bin2hex(random_bytes(5)));
            $this->insertTranslation($id, 'es_ES', 'Bórrame');
            $this->insertTranslation($id, 'de_DE', 'Behalte mich');

            $this->table->updatePhrase($id, ['es_ES' => null]);

            $phrase = $this->table->getPhraseById($id);
            self::assertArrayNotHasKey('es_ES', $phrase, 'the retracted translation is still readable');
            self::assertSame(
                0,
                $this->countTranslationRows($id, 'es_ES'),
                'the row was blanked rather than deleted, which leaves a second representation of '
                . '"untranslated" in the table'
            );
            self::assertSame(
                'Behalte mich',
                $phrase['de_DE'] ?? null,
                'retracting one language took another with it'
            );
        } finally {
            $connection->rollback();
        }
    }

    /**
     * Retracting a language that has no row is a no-op, not an error.
     */
    public function testRetractingAnAbsentTranslationDoesNothing(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $id = $this->insertPhrase('Null on nothing ' . bin2hex(random_bytes(5)));

            $this->table->updatePhrase($id, ['es_ES' => null]);

            self::assertSame(0, $this->countTranslationRows($id, 'es_ES'));
        } finally {
            $connection->rollback();
        }
    }

    /**
     * A translation of literally "0" is storable.
     *
     * `'0'` is falsy in PHP, so the old condition discarded it along with `''`. It is a
     * plausible rendering of a label — a count, a level, a floor number — and it could
     * not be saved in any language.
     */
    public function testATranslationOfZeroIsStored(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $id = $this->insertPhrase('Zero is a translation ' . bin2hex(random_bytes(5)));

            $this->table->updatePhrase($id, ['es_ES' => '0']);

            self::assertSame('0', $this->table->getPhraseById($id)['es_ES'] ?? null);
        } finally {
            $connection->rollback();
        }
    }

    /**
     * Submitting the text that is already stored is not a write.
     */
    public function testResubmittingTheStoredTextWritesNothing(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $id = $this->insertPhrase('No-op update ' . bin2hex(random_bytes(5)));
            $this->insertTranslation($id, 'es_ES', 'Igual');

            $results = $this->table->updatePhrase($id, ['es_ES' => 'Igual']);

            self::assertSame([], $results, 'an unchanged submission still issued a statement');
        } finally {
            $connection->rollback();
        }
    }

    /**
     * A discovery that fails partway leaves nothing behind.
     *
     * The failure is injected rather than simulated: `key_locale` is overridden with a
     * value longer than `trans_translations.locale`'s `varchar(20)`, so the phrase INSERT
     * succeeds and the key-locale translation INSERT raises under `STRICT_TRANS_TABLES`.
     * That is precisely the shape of the real incident — a phrase row written, an
     * exception before its translation, and a row the phrase index reports as *present*
     * so no later render ever completes it.
     *
     * White-box, because there is no way to reach a mid-phrase failure from outside.
     */
    public function testAFailedDiscoveryLeavesNoHalfWrittenPhrase(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();

        /** @var TranslationsTable $table */
        $table  = $this->container->build(TranslationsTable::class);
        $table->setActingUserId(null);
        $phrase = 'Half written ' . bin2hex(random_bytes(6));

        $configProperty = new \ReflectionProperty(TranslationsTable::class, 'config');
        $config         = $configProperty->getValue($table);
        $config['key_locale'] = 'a_locale_far_too_long_for_the_column';
        $configProperty->setValue($table, $config);

        $connection->beginTransaction();
        try {
            $table->reportMissingTranslation([
                'text_domain' => $this->textDomain,
                'message'     => $phrase,
                'locale'      => 'es_ES',
            ]);

            $raised = null;
            try {
                $table->flush('integration-test');
            } catch (\Throwable $e) {
                $raised = $e;
            }

            self::assertNotNull($raised, 'the injected failure did not raise, so this proves nothing');
            self::assertSame(
                0,
                $this->countPhraseRows($phrase),
                'the phrase row survived a failed discovery. It has no key-locale translation and never '
                . 'will, because the phrase index reports it present — which is the exact defect the '
                . 'transaction in writeMissingPhrasesToDb() exists to prevent.'
            );
        } finally {
            //The inner rollback already discarded the ambient transaction — laminas-db's
            //nested rollback resets the counter — so rolling back again would raise
            //"Must call beginTransaction() before you can rollback".
            if ($connection->inTransaction()) {
                $connection->rollback();
            }
        }
    }

    /**
     * The thread survives the phrase row it was written against.
     *
     * This is the test the history table's key exists for, and it fails against the
     * obvious design. A phrase id is not stable: M004 merges duplicates onto the lowest
     * id and drops the rest, M005 merges again when normalization collapses two, and
     * `deletePhrase()` removes a row that the next render then *rediscovers* as a new row
     * with a new id. Keyed on the id, every one of those cuts a thread in half and leaves
     * the earlier reasoning attached to an id nothing asks for again — at exactly the
     * moment somebody is asking why a translation keeps being changed back.
     *
     * Reproduced here the cheap way: write history, then delete the phrase row and insert
     * the same string again, which is what rediscovery does and which necessarily
     * produces a different auto-increment id.
     */
    public function testAThreadSurvivesTheRediscoveryOfItsPhrase(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $text = 'Thread survives rediscovery ' . bin2hex(random_bytes(5));
            $id   = $this->insertPhrase($text);
            $this->insertTranslation($id, 'es_ES', 'Primera versión');

            $this->table->updatePhrase($id, ['es_ES' => 'Segunda versión'], 'the first one read as a noun');

            $before = $this->table->getTranslationHistory($id);
            self::assertCount(1, $before, 'the overwrite wrote no history at all');
            self::assertSame('Primera versión', $before[0]['old_translation']);

            //Rediscovery: the row goes, the same string comes back with a new id.
            $this->adapter->query(
                'DELETE FROM `trans_phrases` WHERE `translation_phrase_id` = ?',
                [$id]
            );
            $newId = $this->insertPhrase($text);
            self::assertNotSame($id, $newId, 'the fixture did not actually produce a new id, so this proves nothing');

            $after = $this->table->getTranslationHistory($newId);
            self::assertCount(
                1,
                $after,
                'the thread did not follow the phrase, so it is keyed on the id rather than the hash'
            );
            self::assertSame('Primera versión', $after[0]['old_translation']);
            self::assertSame('the first one read as a noun', $after[0]['notes']);
            //The entry still names the row it was written against, which is what anyone
            //reconstructing events needs and is not the same as the id asked for.
            self::assertSame($id, (int) $after[0]['translation_phrase_id']);
        } finally {
            $connection->rollback();
        }
    }

    /** One language's thread, which is the shape an agent deciding about German wants. */
    public function testTheThreadCanBeNarrowedToOneLanguage(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $id = $this->insertPhrase('Two languages argue ' . bin2hex(random_bytes(5)));
            $this->insertTranslation($id, 'es_ES', 'Antes');
            $this->insertTranslation($id, 'de_DE', 'Vorher');

            $this->table->updatePhrase($id, ['es_ES' => 'Después', 'de_DE' => 'Nachher']);

            self::assertCount(2, $this->table->getTranslationHistory($id));
            $spanish = $this->table->getTranslationHistory($id, 'es_ES');
            self::assertCount(1, $spanish, 'the language filter did not narrow the thread');
            self::assertSame('Antes', $spanish[0]['old_translation']);
        } finally {
            $connection->rollback();
        }
    }

    /** Another project's phrase id answers an empty thread, not that project's. */
    public function testTheThreadOfAnotherProjectsPhraseIsEmpty(): void
    {
        $foreign = $this->adapter->query(
            'SELECT `translation_phrase_id` FROM `trans_phrases` WHERE `project` <> ? LIMIT 1',
            [$this->project]
        )->current();
        if (null === $foreign) {
            self::markTestSkipped('this database holds only one project, so there is no cross-project read to try');
        }

        self::assertSame(
            [],
            $this->table->getTranslationHistory((int) ((array) $foreign)['translation_phrase_id'])
        );
    }

    /**
     * A superseded phrase leaves the worklist, and comes back on its own if it returns.
     *
     * The mechanism behind Schoenstatt\Model\SchoenstattTable::retireSupersededPhrases(),
     * which is what stops an edited shrine description leaving its old text at the top of
     * a translator's queue for ever. Both halves matter: retiring is only safe to do
     * automatically *because* a phrase that turns out to be in use un-retires itself the
     * next time a render misses on it.
     */
    public function testASupersededPhraseIsRetiredAndRediscoveryBringsItBack(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $text = 'Superseded description ' . bin2hex(random_bytes(5));
            $id   = $this->insertPhrase($text);

            self::assertTrue(
                $this->table->retirePhraseByText($text, $this->textDomain),
                'nothing was retired, so the phrase was not found by its text'
            );
            self::assertNotNull($this->retiredOn($id), 'the phrase is still on the worklist');

            //What a render does when the string turns out to still be in use. The
            //discovery insert is an ON DUPLICATE KEY UPDATE that clears retired_on, so
            //this is the self-healing half rather than a second mechanism.
            $this->table->reportMissingTranslation([
                'message'     => $text,
                'text_domain' => $this->textDomain,
                'locale'      => 'es_ES',
            ]);
            $this->table->flush('integration-test');

            self::assertNull(
                $this->retiredOn($id),
                'a phrase that came back into use stayed retired, so a wrong guess is permanent'
            );
        } finally {
            $connection->rollback();
        }
    }

    /** Retiring by text is scoped to this project, like every other write here. */
    public function testRetiringByTextDoesNotReachAnotherProject(): void
    {
        $foreign = $this->adapter->query(
            'SELECT `phrase`, `text_domain` FROM `trans_phrases` WHERE `project` <> ? AND `retired_on` IS NULL LIMIT 1',
            [$this->project]
        )->current();
        if (null === $foreign) {
            self::markTestSkipped('this database holds only one project, so there is no cross-project write to try');
        }
        $foreign = (array) $foreign;

        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            self::assertFalse(
                $this->table->retirePhraseByText($foreign['phrase'], $foreign['text_domain']),
                'another project\'s phrase was retired from here'
            );
        } finally {
            $connection->rollback();
        }
    }

    private function retiredOn(int $phraseId): ?string
    {
        foreach (
            $this->adapter->query(
                'SELECT `retired_on` FROM `trans_phrases` WHERE `translation_phrase_id` = ?',
                [$phraseId]
            ) as $row
        ) {
            $value = ((array) $row)['retired_on'] ?? null;
            return null === $value ? null : (string) $value;
        }

        return null;
    }

    // ---------------------------------------------------------------- helpers

    private function insertPhrase(string $phrase): int
    {
        $this->adapter->query(
            'INSERT INTO `trans_phrases` (`project`, `text_domain`, `phrase`, `phrase_hash`, `added_on`) '
            . 'VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$this->project, $this->textDomain, $phrase, PhraseIdentity::raw($phrase)]
        );

        foreach (
            $this->adapter->query(
                'SELECT `translation_phrase_id` FROM `trans_phrases` WHERE `project` = ? AND `phrase_hash` = ?',
                [$this->project, PhraseIdentity::raw($phrase)]
            ) as $row
        ) {
            return (int) $row['translation_phrase_id'];
        }

        self::fail('the fixture phrase was not inserted');
    }

    private function insertTranslation(int $phraseId, string $locale, string $translation): void
    {
        $this->adapter->query(
            'INSERT INTO `trans_translations` (`translation_phrase_id`, `locale`, `translation`, `modified_on`) '
            . 'VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$phraseId, $locale, $translation]
        );
    }

    private function countTranslationRows(int $phraseId, string $locale): int
    {
        foreach (
            $this->adapter->query(
                'SELECT COUNT(*) AS c FROM `trans_translations` WHERE `translation_phrase_id` = ? AND `locale` = ?',
                [$phraseId, $locale]
            ) as $row
        ) {
            return (int) $row['c'];
        }

        return 0;
    }

    private function countPhraseRows(string $phrase): int
    {
        foreach (
            $this->adapter->query(
                'SELECT COUNT(*) AS c FROM `trans_phrases` WHERE `project` = ? AND `phrase_hash` = ?',
                [$this->project, PhraseIdentity::raw($phrase)]
            ) as $row
        ) {
            return (int) $row['c'];
        }

        return 0;
    }
}
