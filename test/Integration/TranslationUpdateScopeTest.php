<?php

namespace SchoenstattTest\Integration;

use JTranslate\Model\TranslationsTable;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Driver\ConnectionInterface;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Regression guard: TranslationsTable::updatePhrase() must only ever write the
 * rows belonging to the phrase the caller was authorized for.
 *
 * The bug this pins down was an authorization hole, not a data-integrity nit.
 * `updatePhrase($id, $data)` receives $data straight from the edit form's POST,
 * and the form carries the translation row ids as hidden fields (`enId`, `itId`,
 * …) so that the method can tell an update from an insert. The UPDATE used to
 * be built as
 *
 *     ->where(['translation_id' => $data[$locale . 'Id']])
 *
 * i.e. the row to write was chosen by a value the *client* supplied, while the
 * only authorization check that had happened — the bjyauthorize route guard on
 * `jtranslate/phrase/edit` plus the controller's own lookup — concerned $id.
 * The two were never compared. Anyone permitted to edit one phrase could
 * therefore rewrite the translation of any other phrase in the table, across
 * every text domain and every project sharing the `trans_translations` table,
 * by editing one hidden field in the browser. The INSERT branch had the same
 * shape, taking `translation_phrase_id` from $data['phraseId'] rather than from
 * $id.
 *
 * The fix reads the row ids from $phrase — the record loaded from the database
 * under $id — so the submitted *Id fields can no longer steer the write at all.
 * That is a one-token change and exactly the kind of thing a later refactor
 * "tidies" back into $data, which is why it is worth a test rather than a
 * comment.
 *
 * Why this test has to be an integration test, and what it costs
 * -------------------------------------------------------------
 * updatePhrase() has no seam: it builds its own Laminas\Db\Sql objects against
 * the adapter and reads the phrase through getTranslations(), which issues raw
 * SQL. Proving the WHERE clause targets the right row means letting a real
 * database answer, so this test resolves the real service out of a real
 * application container. It deliberately stops short of bootstrap(): the
 * factory needs nothing more than the ServiceManager (its event manager comes
 * from the 'Application' service, which does not require a request or a route
 * stack), and bootstrapping would drag in the MVC listeners for no benefit.
 *
 * **The database is left exactly as it was found.** The whole write happens
 * inside a transaction on the shared Laminas DB adapter, rolled back in a
 * `finally` block, so the capsule's 2021 dump is unchanged whether this test
 * passes, fails, or throws. The rollback is only a real guarantee if the
 * transaction is on the same connection the table writes through, so that
 * identity is asserted rather than assumed (see testTheRollbackCoversTheWrite).
 *
 * Two things this test cannot and does not undo:
 *
 *  - updatePhrase() ends with removeDependentCacheItems('phrase'), which evicts
 *    the 'phrase-keys' item from the APCu-backed persistent cache. A rollback
 *    does not restore it, and nothing here tries to. It does not matter: the
 *    cache is a pure read-through of the phrase table, so the next reader
 *    repopulates it from the (rolled-back, i.e. original) rows. It also cannot
 *    reach the running site — APCu's shared segment belongs to the SAPI that
 *    created it, so a php-cli process and the Apache workers never see each
 *    other's entries.
 *  - `AUTO_INCREMENT` counters, had the INSERT branch been exercised. It is not:
 *    the phrases chosen below already have a row in the locale under test, which
 *    is the precondition for reaching the UPDATE branch at all.
 *
 * Needs a running capsule's database. Without one it skips rather than fails,
 * the way the other suites treat unavailable environment.
 * docker compose exec -T app php tools/phpunit.phar --testsuite integration
 */
class TranslationUpdateScopeTest extends TestCase
{
    /**
     * The marker written into the phrase under test. Recognizable on sight if it
     * ever escapes the rollback, and unique per run so a leaked row from an
     * earlier run cannot make an assertion pass by accident.
     */
    private string $marker;

    private ServiceManager $container;

    private Adapter $adapter;

    private TranslationsTable $table;

    protected function setUp(): void
    {
        //Before anything touches the container: without config/autoload/local.php
        //there is no `db` key, so DbAdapterServiceFactory raises four warnings
        //building the adapter — and phpunit.xml.dist sets failOnWarning, so the
        //try/catch below cannot save it. That is why this suite was red on CI, which
        //has no local config.
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }

        $this->marker = 'integration-test scope marker ' . bin2hex(random_bytes(6));

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        //writing the config cache from a test process would leave a cache file
        //owned by the wrong user next to a real deployment
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        $container = new ServiceManager();
        (new ServiceManagerConfig($appConfig['service_manager'] ?? []))
            ->configureServiceManager($container);
        $container->setService('ApplicationConfig', $appConfig);
        $container->get('ModuleManager')->loadModules();
        $this->container = $container;

        try {
            /** @var Adapter $adapter */
            $adapter = $container->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
        } catch (\Throwable $e) {
            self::markTestSkipped(
                'no reachable database: ' . $e->getMessage()
                . ' — this test needs the capsule up (docker compose up -d)'
            );
        }
        $this->adapter = $adapter;

        try {
            /** @var TranslationsTable $table */
            $table = $container->get(TranslationsTable::class);
        } catch (\Throwable $e) {
            //the constructor queries the phrases table straight away, so a
            //missing/empty schema surfaces here rather than above
            self::markTestSkipped('TranslationsTable is not constructible here: ' . $e->getMessage());
        }
        $this->table = $table;
    }

    /**
     * The security property, and the proof that the method still works.
     *
     * Both halves are asserted because neither means anything alone. On its own,
     * "the other phrase is untouched" is satisfied by an updatePhrase() that
     * returns early and writes nothing at all — a broken method would pass. On
     * its own, "the target phrase was updated" says nothing about scope.
     */
    public function testATamperedTranslationIdCannotRedirectTheWriteToAnotherPhrase(): void
    {
        [$locale, $victimId, $victimRowId, $targetId, $targetRowId] = $this->twoPhrasesSharingALocale();

        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $victimBefore = $this->readTranslationRow($victimRowId);
            $targetBefore = $this->readTranslationRow($targetRowId);

            //exactly what a hostile browser would post: the phrase the caller is
            //authorized for, and a hidden *Id field pointing at somebody else's row
            $this->table->updatePhrase($targetId, [
                'phraseId'         => $targetId,
                $locale            => $this->marker,
                $locale . 'Id'     => $victimRowId,
            ]);

            $victimAfter = $this->readTranslationRow($victimRowId);
            $targetAfter = $this->readTranslationRow($targetRowId);
        } finally {
            $connection->rollback();
        }

        self::assertSame(
            $victimBefore,
            $victimAfter,
            sprintf(
                'updatePhrase(%d, …) wrote to translation row %d, which belongs to phrase %d. The submitted '
                . '%sId field steered the UPDATE\'s WHERE clause — that is the authorization hole described in '
                . 'this test\'s docblock. Every identifier deciding *which* row is written must come from the '
                . '$phrase loaded under $id, never from $data.',
                $targetId,
                $victimRowId,
                $victimId,
                $locale
            )
        );

        self::assertSame(
            $this->marker,
            $targetAfter['translation'],
            sprintf(
                'updatePhrase(%d, …) did not write the submitted translation to that phrase\'s own row (%d). '
                . 'Without this half the assertion above is vacuous: a method that writes nothing at all also '
                . 'leaves the other phrase untouched.',
                $targetId,
                $targetRowId
            )
        );
        self::assertNotSame(
            $targetBefore['translation'],
            $targetAfter['translation'],
            'the marker matched what was already stored, so nothing was proven'
        );
    }

    /**
     * The rollback in the test above is only worth anything if the transaction
     * and the writes share a connection. They do because the ServiceManager
     * hands out one shared Adapter instance and TranslationsTableFactory builds
     * both table gateways from it — but "shared" is a configuration decision
     * someone could change (a delegator, a second named adapter), and the
     * failure mode would be silent: the writes would commit and the rollback
     * would quietly apply to nothing. So pin the identity.
     */
    public function testTheRollbackCoversTheWrite(): void
    {
        $property = new \ReflectionProperty(TranslationsTable::class, 'adapter');

        self::assertSame(
            $this->adapter,
            $property->getValue($this->table),
            'TranslationsTable writes through a different adapter than this test opens its transaction on, so '
            . 'the rollback protecting the database would be a no-op. Do not silence this — find out which '
            . 'adapter service it got.'
        );
        self::assertInstanceOf(ConnectionInterface::class, $this->adapter->getDriver()->getConnection());
    }

    /**
     * Two distinct phrases that each already have a translation row in the same
     * locale, discovered from the data rather than hardcoded — the capsule's dump
     * is replaceable and phrase ids are not stable across projects.
     *
     * Locales are taken in the order getLocales(true) reports them, and the first
     * one with two candidates wins. The key locale (en_US) is included: for this
     * purpose one locale is as good as another, and it is the only one with broad
     * coverage in the current dump (17 it_IT rows against ~6800 en_US).
     *
     * @return array{0: string, 1: int, 2: int, 3: int, 4: int}
     *         locale, victim phrase id, victim row id, target phrase id, target row id
     */
    private function twoPhrasesSharingALocale(): array
    {
        $translations = $this->table->getTranslations();
        $locales      = array_keys($this->table->getLocales(true));

        foreach ($locales as $locale) {
            $candidates = [];
            foreach ($translations as $phraseId => $phrase) {
                if (! empty($phrase[$locale . 'Id'])) {
                    $candidates[] = [(int) $phraseId, (int) $phrase[$locale . 'Id']];
                }
                if (count($candidates) === 2) {
                    break;
                }
            }

            if (count($candidates) === 2) {
                return [$locale, $candidates[0][0], $candidates[0][1], $candidates[1][0], $candidates[1][1]];
            }
        }

        self::markTestSkipped(
            'no locale in this dataset has two distinct phrases with an existing translation row, so there is '
            . 'no second row for a tampered id to point at. Locales checked: ' . implode(', ', $locales)
        );
    }

    /**
     * Read one translation row with a fresh query straight through the adapter.
     *
     * Deliberately not via getTranslations()/getPhrase(). TranslationsTable
     * carries SionCacheTrait's memory + APCu read-through cache, and
     * getTranslations()'s use of it is currently commented out — an edit away
     * from returning, and its siblings getTranslatedText()/getPhraseKeysFromDb()
     * use it today. A cached read could hand back the pre-write state and make
     * this test pass while the hole is wide open, which is the one failure mode a
     * security regression test must not have. A direct SELECT cannot lie.
     *
     * @return array<string, mixed>
     */
    private function readTranslationRow(int $translationId): array
    {
        $result = $this->adapter->query(
            'SELECT `translation_id`, `translation_phrase_id`, `locale`, `translation`, `modified_by`, '
            . '`modified_on` FROM `trans_translations` WHERE `translation_id` = ?',
            [$translationId]
        );

        $rows = [];
        foreach ($result as $row) {
            $rows[] = (array) $row;
        }

        self::assertCount(1, $rows, 'expected exactly one translation row for id ' . $translationId);

        return $rows[0];
    }
}
