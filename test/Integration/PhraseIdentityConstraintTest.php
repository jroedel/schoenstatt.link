<?php

namespace SchoenstattTest\Integration;

use App\Laminas\ContainerFactory;
use JTranslate\Model\PhraseIdentity;
use JTranslate\Model\TranslationsTable;
use Laminas\Db\Adapter\Adapter;
use App\Services\Container;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The three properties that make a phrase row trustworthy, against a real database.
 *
 * None of them can be tested without one. Two are enforced by the schema rather than
 * by PHP, and the third — un-retiring on render — is a collaboration between an
 * `ON DUPLICATE KEY UPDATE` clause and a cached index, which is exactly the kind of
 * thing that passes in a unit test with a fake and fails against MySQL.
 *
 * ## What is being guarded, and why each one is worth the cost
 *
 * 1. **A phrase cannot be stored twice.** `trans_phrases` had no index but its primary
 *    key, and `phrase` was `varchar(2000)`, so a phrase longer than that was silently
 *    truncated on insert and could never be matched again: every render inserted
 *    another row. That produced 5,088 rows holding two distinct strings — 74% of this
 *    project's phrase table. See JTranslate's M003 and M004.
 * 2. **Retirement is reversible.** Nothing was ever removed from these tables because
 *    `deletePhrase()` cascades translations away with no history, so a wrong deletion
 *    destroys human work permanently. `retired_on` replaces that with a decision that
 *    only has to be roughly right — but only if a retired phrase really does come back
 *    when a page renders it, with its translations. If that branch silently stops
 *    working, retirement becomes the one-way door it was meant to replace, and nothing
 *    else in the suite would notice.
 * 3. **A read cannot cross the project boundary.** `trans_phrases` is shared by three
 *    applications here. `getPhraseIndex()` spent years passing a `Sql` object to
 *    `TableGateway::select()`, which takes a *predicate* — the gateway ignored it and
 *    returned the whole table, so the project filter never applied and this project's
 *    phrase index contained every other project's phrases. That is a data leak in one
 *    direction and, because the index gates inserts, a permanent
 *    "this phrase can never be added here" in the other.
 *
 * **The database is left exactly as it was found.** Every write happens inside one
 * transaction on the shared adapter, rolled back in a `finally`. The identity of that
 * adapter with the one the table writes through is asserted by
 * TranslationUpdateScopeTest, which is the test that owns that concern; this one
 * relies on it rather than repeating it.
 *
 * The in-process phrase index is *not* transactional, so every method here that
 * changes rows calls the table's own invalidation by going through its public API
 * (retire()/unretire() clear the caches themselves) and re-reads afterwards.
 *
 * Needs a running capsule's database, and skips rather than fails without one.
 * docker compose exec -T app php tools/phpunit.phar --testsuite integration
 */
class PhraseIdentityConstraintTest extends TestCase
{
    private Container $container;

    private Adapter $adapter;

    private TranslationsTable $table;

    private string $project;

    private string $textDomain = 'IntegrationTestDomain';

    protected function setUp(): void
    {
        //Without config/autoload/local.php there is no `db` key, so building the
        //adapter raises warnings that phpunit.xml.dist turns into failures — which is
        //why this has to be checked before the container is touched, not in a catch.
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';

        $container = ContainerFactory::build($appConfig);
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
            self::markTestSkipped('TranslationsTable is not constructible here: ' . $e->getMessage());
        }
        $this->table = $table;

        $config        = $container->get('JTranslate\Config');
        $this->project = (string) $config['project_name'];

        if (! $this->hasIdentityIndex()) {
            self::markTestSkipped(
                'trans_phrases has no `phrase_identity` index, so JTranslate migrations 003 and 004 have '
                . 'not been applied to this database. Run `php bin/console jtranslate:migrate`.'
            );
        }
    }

    /**
     * The constraint refuses a second copy, and refuses it on *byte* identity.
     *
     * The second half is the point. A `UNIQUE (project, text_domain, phrase(255))`
     * index would satisfy the first half and get the second wrong in both directions:
     * it would refuse `Inglés` after `Inglês` (they fold together under
     * `utf8mb4_unicode_520_ci`) and accept a 3,000-character phrase whose first 255
     * characters match one already stored. Both failures are silent and permanent.
     */
    public function testTheConstraintRefusesADuplicateAndOnlyAnExactOne(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $this->insertPhrase('Book checkout');

            $duplicate = null;
            try {
                $this->insertPhrase('Book checkout');
            } catch (\Throwable $e) {
                $duplicate = $e;
            }
            self::assertNotNull(
                $duplicate,
                'the same phrase was stored twice. `UNIQUE (project, text_domain, phrase_hash)` is missing '
                . 'or is not being populated — which is the state that produced 5,088 duplicate rows.'
            );

            //Same phrase under case folding, distinct bytes: must be accepted.
            $this->insertPhrase('Book Checkout');
            //Same first 255 characters, distinct beyond them: must be accepted.
            $prefix = str_repeat('x', 255);
            $this->insertPhrase($prefix . ' first');
            $this->insertPhrase($prefix . ' second');

            self::assertSame(
                4,
                $this->countTestPhrases(),
                'a phrase differing only in case, or only beyond the first 255 characters, was refused. '
                . 'The constraint is folding or truncating, so it is not the translator\'s identity '
                . 'relation and it will silently make real phrases untranslatable.'
            );
        } finally {
            $connection->rollback();
        }
    }

    /**
     * A retired phrase disappears from the worklist, keeps rendering, and comes back.
     *
     * All three in one test on purpose: each is meaningless without the others. Hiding
     * it without keeping it in the catalogs would mean retiring a phrase instantly
     * reverts every page still using it to English. Keeping it in the catalogs without
     * hiding it would make `retired_on` decorative. And doing both without the return
     * path would make retirement the irreversible operation it exists to replace.
     */
    public function testRetirementHidesAPhraseWithoutSilencingItOrLosingIt(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $phrase = 'Retirement round trip ' . bin2hex(random_bytes(6));
            $id     = $this->insertPhrase($phrase);
            $this->insertTranslation($id, 'es_ES', 'Ida y vuelta');

            $criteria = ['textDomain' => $this->textDomain];
            self::assertArrayHasKey($id, $this->table->getPhrasePage($criteria, 100, 0));

            self::assertSame(1, $this->table->retire([$id]));

            self::assertArrayNotHasKey(
                $id,
                $this->table->getPhrasePage($criteria, 100, 0),
                'a retired phrase is still being offered to translators as work'
            );
            self::assertSame(
                0,
                $this->table->countPhrases($criteria),
                'countPhrases() disagrees with getPhrasePage() about retirement, so a paginated listing '
                . 'would report a total it cannot show'
            );
            self::assertArrayHasKey(
                $id,
                $this->table->getPhrasePage($criteria + ['includeRetired' => true], 100, 0),
                'includeRetired is not reaching the query, so nothing can ever review what was retired'
            );

            //The catalogs are what the site renders from. Retiring a phrase is a
            //statement about the worklist, never about what visitors see.
            $tree = $this->table->getTranslatedText();
            self::assertSame(
                'Ida y vuelta',
                $tree[$this->textDomain]['es_ES'][$phrase] ?? null,
                'a retired phrase dropped out of the compiled catalog, so retiring it reverts every page '
                . 'still rendering it to English — the exact irreversible damage retired_on exists to avoid'
            );

            //Now the return path: the render path reports it missing, and the
            //end-of-request flush un-retires it rather than inserting a second row.
            $this->table->reportMissingTranslation([
                'text_domain' => $this->textDomain,
                'message'     => $phrase,
                'locale'      => 'de_DE',
            ]);
            $this->table->flush('integration-test');

            self::assertSame(
                1,
                $this->countPhraseRows($phrase),
                'rendering a retired phrase inserted a second row instead of waking the first. The '
                . 'ON DUPLICATE KEY UPDATE clause in writeMissingPhrasesToDb() is not doing its job.'
            );
            $revived = $this->table->getPhraseById($id);
            self::assertNotNull($revived, 'the original row is gone');
            self::assertNull($revived['retiredOn'], 'retired_on was not cleared, so the phrase never came back');
            self::assertSame(
                'Ida y vuelta',
                $revived['es_ES'] ?? null,
                'the phrase came back without its translations, which makes retirement lossy after all'
            );
        } finally {
            $connection->rollback();
        }
    }

    /**
     * Re-reporting a live phrase must not write anything.
     *
     * This is the case a stale or evicted cache produces, and before the constraint
     * existed it inserted a duplicate every time. It is now expected to be a no-op at
     * the database, which is the whole reason the phrase index was demoted from a
     * correctness mechanism to an optimization.
     */
    public function testReinsertingALivePhraseIsANoOp(): void
    {
        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $phrase = 'Idempotent insert ' . bin2hex(random_bytes(6));
            $id     = $this->insertPhrase($phrase);

            //Bypass the index the way an eviction would: force the queue directly.
            $reflection = new \ReflectionProperty(TranslationsTable::class, 'newMissingPhrases');
            $reflection->setValue($this->table, [$this->textDomain => [$phrase]]);
            $this->table->flush('integration-test');

            self::assertSame(1, $this->countPhraseRows($phrase));
            self::assertSame(
                $id,
                $this->phraseIdOf($phrase),
                'the surviving row is not the original one, so the insert replaced rather than ignored it '
                . '— any translation hanging off the old id would have been cascaded away'
            );
        } finally {
            $connection->rollback();
        }
    }

    /**
     * The phrase index must contain this project and nothing else.
     *
     * Regression guard for `fetchSome($select)`: `TableGateway::select()` takes a
     * predicate, and handed a `Sql` object it ignored the argument and returned
     * `SELECT * FROM trans_phrases`. Nothing failed — the method still answered
     * plausibly — so the project filter was absent for years and this project's index
     * held every other project's phrases. Since the index gates inserts, that made any
     * phrase another application had already recorded permanently unable to exist here.
     */
    public function testThePhraseIndexIsScopedToThisProject(): void
    {
        $other = $this->aPhraseBelongingToAnotherProject();
        if (null === $other) {
            self::markTestSkipped('this database holds only one project, so scoping cannot be observed');
        }
        [$otherDomain, $otherPhrase] = $other;

        $index = $this->table->getPhraseIndex();

        self::assertArrayNotHasKey(
            PhraseIdentity::hex($otherPhrase),
            $index[$otherDomain] ?? [],
            'the phrase index contains a phrase belonging to another project. The project predicate is '
            . 'not reaching the query — check that getPhraseIndex() executes its Select through '
            . 'Sql::prepareStatementForSqlObject() and not through fetchSome().'
        );

        self::assertSame(
            $this->countProjectPhrases(),
            array_sum(array_map('count', $index)),
            'the phrase index holds a different number of phrases than this project has'
        );
    }

    /**
     * Deleting cannot reach across the project boundary.
     *
     * `existsPhrase()` fetched by bare id, and `deletePhrase()` is its only caller, so
     * an administrator of one application could destroy another application's phrase
     * and cascade its translations away by putting an integer in the URL. The route
     * constraint on `phrase_id` covers the whole live id range, so this was reachable,
     * not theoretical.
     */
    public function testAnotherProjectsPhraseCannotBeDeleted(): void
    {
        $foreignId = $this->anIdBelongingToAnotherProject();
        if (null === $foreignId) {
            self::markTestSkipped('this database holds only one project, so scoping cannot be observed');
        }

        self::assertFalse(
            $this->table->existsPhrase($foreignId),
            'existsPhrase() answers for a phrase belonging to another project, so deletePhrase() will '
            . 'happily destroy it and its translations'
        );

        $connection = $this->adapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $threw = false;
            try {
                $this->table->deletePhrase($foreignId);
            } catch (\Throwable) {
                $threw = true;
            }
            self::assertTrue($threw, 'deletePhrase() accepted another project\'s id');
            self::assertSame(1, $this->countRowsById($foreignId), 'the foreign phrase was deleted anyway');
        } finally {
            $connection->rollback();
        }
    }

    // ---------------------------------------------------------------- helpers

    private function insertPhrase(string $phrase): int
    {
        $this->adapter->query(
            'INSERT INTO `trans_phrases` (`project`, `text_domain`, `phrase`, `phrase_hash`, `added_on`) '
            . 'VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$this->project, $this->textDomain, $phrase, PhraseIdentity::raw($phrase)]
        );

        return (int) $this->phraseIdOf($phrase);
    }

    private function insertTranslation(int $phraseId, string $locale, string $translation): void
    {
        $this->adapter->query(
            'INSERT INTO `trans_translations` (`translation_phrase_id`, `locale`, `translation`, `modified_on`) '
            . 'VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [$phraseId, $locale, $translation]
        );
    }

    private function phraseIdOf(string $phrase): ?int
    {
        return $this->scalar(
            'SELECT `translation_phrase_id` FROM `trans_phrases` '
            . 'WHERE `project` = ? AND `text_domain` = ? AND `phrase_hash` = ?',
            [$this->project, $this->textDomain, PhraseIdentity::raw($phrase)]
        );
    }

    private function countPhraseRows(string $phrase): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM `trans_phrases` WHERE `project` = ? AND `phrase_hash` = ?',
            [$this->project, PhraseIdentity::raw($phrase)]
        );
    }

    private function countTestPhrases(): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM `trans_phrases` WHERE `project` = ? AND `text_domain` = ?',
            [$this->project, $this->textDomain]
        );
    }

    private function countProjectPhrases(): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM `trans_phrases` WHERE `project` = ?',
            [$this->project]
        );
    }

    private function countRowsById(int $id): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM `trans_phrases` WHERE `translation_phrase_id` = ?',
            [$id]
        );
    }

    /**
     * A (text domain, phrase) pair recorded under a different project and **not** under
     * this one, or null.
     *
     * The exclusion is the whole subtlety of this fixture. Applications sharing this
     * table share a vocabulary — `Text Domain`, `Associations`, `Save` — so most
     * foreign phrases are also legitimately this project's own, under their own row.
     * Asserting that such a hash is absent from the index would fail against perfectly
     * correct code. What must be absent is a phrase only the *other* project has.
     *
     * Preference is given to a text domain this project also uses, because that is
     * where the leak actually bit: the index is keyed by text domain alone, so a
     * foreign phrase in a domain nobody here uses could never have suppressed anything.
     *
     * @return array{string, string}|null
     */
    private function aPhraseBelongingToAnotherProject(): ?array
    {
        $result = $this->adapter->query(
            'SELECT o.`text_domain`, o.`phrase` FROM `trans_phrases` o '
            . 'WHERE o.`project` <> ? '
            . '  AND NOT EXISTS (SELECT 1 FROM `trans_phrases` mine '
            . '    WHERE mine.`project` = ? AND mine.`text_domain` = o.`text_domain` '
            . '      AND mine.`phrase_hash` = o.`phrase_hash`) '
            . 'ORDER BY EXISTS (SELECT 1 FROM `trans_phrases` m '
            . '  WHERE m.`project` = ? AND m.`text_domain` = o.`text_domain`) DESC, '
            . 'o.`translation_phrase_id` ASC LIMIT 1',
            [$this->project, $this->project, $this->project]
        );
        foreach ($result as $row) {
            return [(string) $row['text_domain'], (string) $row['phrase']];
        }

        return null;
    }

    private function anIdBelongingToAnotherProject(): ?int
    {
        return $this->scalar(
            'SELECT `translation_phrase_id` FROM `trans_phrases` WHERE `project` <> ? LIMIT 1',
            [$this->project]
        );
    }

    private function hasIdentityIndex(): bool
    {
        return 0 < (int) $this->scalar(
            'SELECT COUNT(*) FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['trans_phrases', 'phrase_identity']
        );
    }

    /**
     * @param list<mixed> $parameters
     */
    private function scalar(string $sql, array $parameters): int|string|null
    {
        foreach ($this->adapter->query($sql, $parameters) as $row) {
            $value = current((array) $row);

            return null === $value ? null : (is_numeric($value) ? (int) $value : (string) $value);
        }

        return null;
    }
}
