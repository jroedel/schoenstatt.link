<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use App\Provenance\Assertion;
use App\Provenance\FieldGroups;
use App\Provenance\Outcome;
use App\Provenance\ProvenanceStore;
use App\Provenance\Recorder;
use App\Provenance\SourceClass;
use App\Provenance\WriteGate;
use DateTimeImmutable;
use DateTimeZone;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * `sch_provenance` round trips, and the derived field groups match the real configuration.
 *
 * Three things here are worth a database rather than a mock:
 *
 *  - **`latestFor()` must not answer a hit on a miss.** laminas-db returns `false` for an
 *    empty result set rather than null, so the natural `!== null` test inverts — the same
 *    trap `App\Api\BotIdentity` documents. Only a real empty table proves the guard.
 *  - **"Newest" must be deterministic.** Two assertions filed in the same second are normal
 *    (one PATCH touching two groups is one request), and an `ORDER BY` with no tiebreaker
 *    leaves which one wins to the storage engine.
 *  - **The group derivation reads merged config**, so a config change that breaks it should
 *    break this rather than surface as provenance quietly filed under the wrong group.
 *
 * Every row this writes uses a reserved entity name and is deleted in tearDown, so it never
 * mixes with real association provenance. That matters more than usual here: CLAUDE.md
 * records `user` and `trans_phrases` being polluted by suites that did not clean up, and
 * `sch_associations` joined that list on 2026-08-23 — a fixture row with one fresh timestamp
 * read as "somebody maintains this data" and cost a wrong conclusion.
 */
class ProvenanceStoreTest extends TestCase
{
    /** Not `association`: nothing real can collide with it, so a leak cannot skew a reading. */
    private const TEST_ENTITY = 'test-provenance-entity';

    private static ?ServiceBridge $bridge = null;

    /** Module loading the way bin/console does, config caches off — as the sibling suites do. */
    private function bridge(): ServiceBridge
    {
        if (null !== self::$bridge) {
            return self::$bridge;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        return self::$bridge = new ServiceBridge($appConfig);
    }

    private function store(): ProvenanceStore
    {
        try {
            $store = new ProvenanceStore($this->bridge());
            //Touch the database now so an unreachable one is a skip rather than a failure,
            //matching the other integration suites: CI has no database at all.
            $store->latestFor(self::TEST_ENTITY, 0, 'contactInfo');

            return $store;
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        try {
            /** @var Adapter $adapter */
            $adapter = $this->bridge()->get(Adapter::class);
            $sql     = new Sql($adapter);
            $delete  = $sql->delete(ProvenanceStore::TABLE)->where(['Entity' => self::TEST_ENTITY]);
            $sql->prepareStatementForSqlObject($delete)->execute();
        } catch (Throwable) {
            //Nothing to clean if there was no database to write to.
        }
        parent::tearDown();
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-23 12:00:00', new DateTimeZone('UTC'));
    }

    private function assertion(
        string $group,
        SourceClass $source,
        Outcome $outcome,
        string $assertedOn,
        ?string $note = null
    ): Assertion {
        return new Assertion(
            entity: self::TEST_ENTITY,
            entityId: 4242,
            fieldGroup: $group,
            source: $source,
            outcome: $outcome,
            assertedOn: new DateTimeImmutable($assertedOn, new DateTimeZone('UTC')),
            recordedOn: $this->now(),
            sourceUrl: 'https://example.org/evidence',
            sourceNote: $note,
            recordedBy: 7,
        );
    }

    /** A miss must be null, not a truthy `false`. */
    public function testLatestForAnswersNullWhenNothingWasEverClaimed(): void
    {
        $store = $this->store();

        $this->assertNull($store->latestFor(self::TEST_ENTITY, 999999, 'contactInfo'));
    }

    public function testAnAssertionRoundTrips(): void
    {
        $store = $this->store();
        $id    = $store->record($this->assertion(
            'contactInfo',
            SourceClass::Shrine,
            Outcome::Corrected,
            '2026-07-04 00:00:00',
            'spoke to the rector'
        ));

        $this->assertGreaterThan(0, $id);

        $back = $store->latestFor(self::TEST_ENTITY, 4242, 'contactInfo');

        $this->assertInstanceOf(Assertion::class, $back);
        $this->assertSame('contactInfo', $back->fieldGroup);
        $this->assertSame(SourceClass::Shrine, $back->source);
        $this->assertSame(Outcome::Corrected, $back->outcome);
        $this->assertSame('2026-07-04', $back->assertedOn->format('Y-m-d'));
        $this->assertSame('https://example.org/evidence', $back->sourceUrl);
        $this->assertSame('spoke to the rector', $back->sourceNote);
        $this->assertSame(7, $back->recordedBy);
        $this->assertSame($id, $back->provenanceId);
    }

    /**
     * Same `RecordedOn` for both rows — the case a missing tiebreaker gets wrong, and the
     * one that actually happens, because one PATCH touching two groups files both in the
     * same second.
     */
    public function testTheNewestOfTwoRowsInTheSameSecondIsDeterministic(): void
    {
        $store = $this->store();

        $store->record($this->assertion('contactInfo', SourceClass::Directory, Outcome::Corrected, '2026-01-01 00:00:00', 'first'));
        $store->record($this->assertion('contactInfo', SourceClass::Shrine, Outcome::Confirmed, '2026-02-02 00:00:00', 'second'));

        $latest = $store->latestFor(self::TEST_ENTITY, 4242, 'contactInfo');

        $this->assertInstanceOf(Assertion::class, $latest);
        $this->assertSame('second', $latest->sourceNote, 'the higher ProvenanceId must win the tie');
    }

    public function testCurrentForReturnsTheNewestPerGroup(): void
    {
        $store = $this->store();

        $store->record($this->assertion('contactInfo', SourceClass::Website, Outcome::Corrected, '2026-01-01 00:00:00', 'old contact'));
        $store->record($this->assertion('contactInfo', SourceClass::Shrine, Outcome::Confirmed, '2026-06-01 00:00:00', 'new contact'));
        $store->record($this->assertion('openingHoursHuman', SourceClass::Office, Outcome::Corrected, '2026-05-01 00:00:00', 'hours'));

        $current = $store->currentFor(self::TEST_ENTITY, 4242);

        $this->assertSame(['contactInfo', 'openingHoursHuman'], array_keys($current));
        $this->assertSame('new contact', $current['contactInfo']->sourceNote);
        $this->assertSame('hours', $current['openingHoursHuman']->sourceNote);
    }

    /** The refused claims live here and nowhere else, so a review screen depends on it. */
    public function testHistoryKeepsCompetingClaimsNewestFirst(): void
    {
        $store = $this->store();

        $store->record($this->assertion('contactInfo', SourceClass::Shrine, Outcome::Corrected, '2026-06-01 00:00:00', 'rector'));
        $store->record($this->assertion('contactInfo', SourceClass::Website, Outcome::Competing, '2026-08-01 00:00:00', 'scrape'));

        $history = $store->historyFor(self::TEST_ENTITY, 4242, 'contactInfo');

        $this->assertCount(2, $history);
        $this->assertSame('scrape', $history[0]->sourceNote);
        $this->assertSame(Outcome::Competing, $history[0]->outcome);
        $this->assertSame('rector', $history[1]->sourceNote);
    }

    /**
     * End to end through the Recorder: a website claim is withheld while a fresh shrine
     * claim stands, and the finding is still on file afterwards.
     */
    public function testAWithheldClaimIsRecordedButDoesNotWin(): void
    {
        $store    = $this->store();
        $recorder = new Recorder($store, new WriteGate());
        $groups   = FieldGroups::forEntity($this->bridge()->config(), 'association');

        $store->record($this->assertion('contactInfo', SourceClass::Shrine, Outcome::Corrected, '2026-07-01 00:00:00', 'rector'));

        $assessment = $recorder->assess(
            self::TEST_ENTITY,
            4242,
            ['phone1', 'openingHoursHuman'],
            $groups,
            SourceClass::Website,
            $this->now()
        );

        $this->assertSame(['phone1'], $assessment->withheldFields, 'the protected group is withheld');
        $this->assertSame(['openingHoursHuman'], $assessment->writableFields, 'the unprotected group still writes');
        $this->assertSame(['contactInfo'], $assessment->competingGroups());

        $recorder->commit(
            $assessment,
            self::TEST_ENTITY,
            4242,
            SourceClass::Website,
            $this->now(),
            $this->now(),
            null,
            'the scrape',
            7
        );

        $history = $store->historyFor(self::TEST_ENTITY, 4242, 'contactInfo');
        $this->assertSame(Outcome::Competing, $history[0]->outcome);
        $this->assertSame('the scrape', $history[0]->sourceNote);

        $stillStanding = $store->latestFor(self::TEST_ENTITY, 4242, 'openingHoursHuman');
        $this->assertInstanceOf(Assertion::class, $stillStanding);
        $this->assertSame(Outcome::Corrected, $stillStanding->outcome);
    }

    /**
     * The derivation, against the real merged config rather than a fixture.
     *
     * This is the test that would have caught the defect db8.9 cleaned up: the config listed
     * seven field names twice in one array literal, `contactInfo` silently won, and two
     * declared groups were unreachable for years with no symptom but an empty column.
     */
    public function testFieldGroupsMatchTheAssociationConfiguration(): void
    {
        try {
            $groups = FieldGroups::forEntity($this->bridge()->config(), 'association');
        } catch (Throwable $e) {
            self::markTestSkipped('could not load config: ' . $e->getMessage());
        }

        //The eleven-odd contact fields collapse into one group; the prose and schedule
        //fields each form a group of one, because each owns its own stamp columns.
        $this->assertSame('contactInfo', $groups->groupFor('email'));
        $this->assertSame('contactInfo', $groups->groupFor('phone1'));
        $this->assertSame('contactInfo', $groups->groupFor('url1'));
        $this->assertSame('contactInfo', $groups->groupFor('facebookUrl'));
        $this->assertSame('openingHoursHuman', $groups->groupFor('openingHoursHuman'));
        $this->assertSame('eventsHuman', $groups->groupFor('eventsHuman'));
        $this->assertSame('publicNotes', $groups->groupFor('publicNotes'));

        //Identity rather than perishable detail: nobody verifies these on a schedule, and a
        //group for them would file rows that answer no question.
        $this->assertNull($groups->groupFor('name'));
        $this->assertNull($groups->groupFor('kind'));
        $this->assertNull($groups->groupFor('country'));
        $this->assertNull($groups->groupFor('parentId'));
        $this->assertNull($groups->groupFor('geoPoint'));

        //A stamp column is bookkeeping about a field, never a group of its own.
        $this->assertNull($groups->groupFor('openingHoursHumanUpdatedOn'));

        //The dropped groups must stay dropped: db8.9 removed the columns behind them.
        $this->assertNotContains('emails', $groups->all());
        $this->assertNotContains('phones', $groups->all());
    }

    /** A PATCH asks this: which groups did those fields just say something about? */
    public function testGroupsForCollapsesAndSortsAndDropsUngrouped(): void
    {
        $groups = FieldGroups::forEntity($this->bridge()->config(), 'association');

        $this->assertSame(
            ['contactInfo', 'openingHoursHuman'],
            $groups->groupsFor(['phone1', 'url1', 'openingHoursHuman', 'name', 'kind'])
        );
        $this->assertSame([], $groups->groupsFor(['name', 'kind', 'geoPoint']));
    }
}
