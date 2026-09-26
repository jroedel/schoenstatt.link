<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ContainerFactory;
use App\Privacy\Erasure;
use App\Privacy\PersonalData;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SionModel\Db\Connection;
use Throwable;

use function array_column;
use function array_map;
use function is_readable;
use function str_pad;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Access and erasure (docs/privacy.md), against the real schema.
 *
 * Each test builds a person with an account and a little of everything linked to them,
 * inside a transaction that is rolled back — Erasure::apply() joins the caller's — so no
 * fixture outlives the test and no real row is touched.
 */
final class PrivacyRightsTest extends TestCase
{
    private const PROJECT = 'Schoenstatt';

    private Connection $db;

    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $container = ContainerFactory::build($appConfig);
        try {
            /** @var Connection $db */
            $db = $container->get(Connection::class);
            $db->select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }
        $this->db = $db;
        $this->db->beginTransaction();
        //the predicates the fixtures use, in case this database's vocabulary lacks them
        $this->db->execute(
            "INSERT IGNORE INTO predicates (PredicateKind, SubjectEntityKind, ObjectEntityKind, PredicateText)
             VALUES ('event-involves-person', 'event', 'person', 'Event involves person'),
                    ('person-authored-publication', 'person', 'publication', 'Person authored publication')"
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testTheExportHoldsTheRecordTheAccountAndWhatLinksToThem(): void
    {
        [$personId, $userId] = $this->fixture();

        $export = (new PersonalData($this->db))->forAccount($userId);

        self::assertNotNull($export);
        self::assertSame('fixture@example.com', $export['person']['Email']);
        self::assertSame(['Keeper of the fixtures'], array_column($export['assignments'], 'RoleTitle'));
        self::assertCount(1, $export['loans']);
        self::assertCount(1, $export['relationships']);
        self::assertSame(['phone1'], array_column($export['recordHistory'], 'ChangedField'));
        self::assertCount(1, $export['sources']);
        self::assertCount(1, $export['accounts']);
        self::assertSame($userId, (int) $export['accounts'][0]['account']['user_id']);
        self::assertSame(['a fixture comment'], array_column($export['accounts'][0]['comments'], 'Comment'));
        self::assertSame(
            [['ChangedEntity' => 'association', 'edits' => 1], ['ChangedEntity' => 'person', 'edits' => 1]],
            array_map(
                static fn (array $row): array => [
                    'ChangedEntity' => $row['ChangedEntity'],
                    'edits'         => (int) $row['edits'],
                ],
                $export['accounts'][0]['editsMade']
            )
        );
        self::assertArrayNotHasKey('verification_token', $export['accounts'][0]['account'], 'a live credential');

        $byPerson = (new PersonalData($this->db))->forPerson($personId);
        self::assertNotNull($byPerson);
        unset($export['exportedOn'], $byPerson['exportedOn']); //a second can pass between the two
        self::assertSame($export, $byPerson, 'two ways in, one answer');
    }

    public function testErasingSomeoneWhoIsNotAnAuthorLeavesNothingOfThem(): void
    {
        [$personId, $userId, $associationId, $spouseId] = $this->fixture();
        $erasure = new Erasure($this->db, self::PROJECT);

        $plan = $erasure->plan($personId);
        self::assertSame('delete', $plan['person']['action'] ?? null);
        self::assertSame([$userId], $plan['accounts']);
        self::assertContains(
            ['table' => 'sch_associations', 'column' => 'PublicNotes', 'id' => $associationId],
            $this->normalised($plan['mentions']),
            'free text naming them is reported'
        );

        $erasure->apply($personId);

        foreach (
            [
                'SELECT COUNT(*) AS n FROM sch_persons WHERE PersonId = ?',
                'SELECT COUNT(*) AS n FROM sch_assignments WHERE PersonId = ?',
                'SELECT COUNT(*) AS n FROM lib_checkouts WHERE PersonId = ?',
                'SELECT COUNT(*) AS n FROM lib_borrower_tokens WHERE PersonId = ?',
                "SELECT COUNT(*) AS n FROM relationships WHERE PredicateKind = 'event-involves-person'"
                . ' AND ObjectEntityId = ?',
                "SELECT COUNT(*) AS n FROM sch_changes WHERE ChangedEntity = 'person' AND ChangedIDValue = ?",
                "SELECT COUNT(*) AS n FROM sch_provenance WHERE Entity = 'person' AND EntityId = ?",
                'SELECT COUNT(*) AS n FROM sch_persons WHERE SpousePersonId = ?',
            ] as $sql
        ) {
            self::assertSame(0, $this->rowCount($sql, [$personId]), $sql);
        }
        foreach (
            [
                'SELECT COUNT(*) AS n FROM user WHERE user_id = ?',
                'SELECT COUNT(*) AS n FROM user_role_linker WHERE user_id = ?',
                'SELECT COUNT(*) AS n FROM user_api_token WHERE user_id = ?',
                'SELECT COUNT(*) AS n FROM comments WHERE CreatedBy = ?',
                'SELECT COUNT(*) AS n FROM sch_changes WHERE UpdatedBy = ?',
                'SELECT COUNT(*) AS n FROM sch_associations WHERE UpdatedBy = ?',
            ] as $sql
        ) {
            self::assertSame(0, $this->rowCount($sql, [$userId]), $sql);
        }

        self::assertSame(
            1,
            $this->rowCount('SELECT COUNT(*) AS n FROM sch_persons WHERE PersonId = ?', [$spouseId]),
            'the spouse stays'
        );
        self::assertSame(
            1,
            $this->rowCount(
                "SELECT COUNT(*) AS n FROM sch_changes WHERE ChangedEntity = 'association'" . ' AND ChangedIDValue = ?',
                [$associationId]
            ),
            'the edit to someone else\'s record stays, unattributed'
        );
    }

    /**
     * The translation tables hold patres' rows too, with patres' own user ids, so an id that
     * is this account's here can be somebody else's there.
     */
    public function testAnotherProjectsTranslationsKeepTheirAttribution(): void
    {
        [$personId, $userId] = $this->fixture();
        $translations = [];
        foreach ([self::PROJECT, 'patres'] as $project) {
            $this->db->execute(
                "INSERT INTO trans_phrases (project, text_domain, phrase, phrase_hash, added_on)
                 VALUES (?, 'default', ?, MD5(?), UTC_TIMESTAMP())",
                [$project, 'fixture phrase ' . $project, 'fixture phrase ' . $project]
            );
            $phraseId = $this->db->lastInsertId();
            $this->db->execute(
                "INSERT INTO trans_translations (translation_phrase_id, locale, translation, modified_by, modified_on)
                 VALUES (?, 'de_DE', 'x', ?, UTC_TIMESTAMP())",
                [$phraseId, $userId]
            );
            $translations[$project] = $this->db->lastInsertId();
        }

        (new Erasure($this->db, self::PROJECT))->apply($personId);

        $modifiedBy = static function (Connection $db, int $id): mixed {
            $row = $db->select('SELECT modified_by FROM trans_translations WHERE translation_id = ?', [$id])->current();
            self::assertNotNull($row, 'the translation itself must survive');

            return $row['modified_by'];
        };
        self::assertNull($modifiedBy($this->db, $translations[self::PROJECT]), 'this project\'s attribution');
        self::assertSame(
            $userId,
            (int) $modifiedBy($this->db, $translations['patres']),
            'patres\' is not ours to touch'
        );
    }

    public function testAnAuthorKeepsTheirNameAndLosesTheRest(): void
    {
        [$personId] = $this->fixture();
        $this->db->execute(
            "INSERT INTO relationships (SubjectEntityId, ObjectEntityId, PredicateKind)
             VALUES (?, 1, 'person-authored-publication')",
            [$personId]
        );
        $erasure = new Erasure($this->db, self::PROJECT);

        self::assertSame('keep the name as an author', $erasure->plan($personId)['person']['action'] ?? null);
        $erasure->apply($personId);

        $row = $this->db->select('SELECT * FROM sch_persons WHERE PersonId = ?', [$personId])->current();
        self::assertNotNull($row);
        self::assertSame(['Fixture', 'Privacy'], [$row['FirstName'], $row['LastName']]);
        foreach (['Email', 'Phone1', 'PublicNotes', 'AdminNotes', 'SpousePersonId', 'Country'] as $column) {
            self::assertNull($row[$column], $column . ' survived on an author');
        }
        self::assertSame(1, $this->rowCount(
            "SELECT COUNT(*) AS n FROM relationships WHERE PredicateKind = 'person-authored-publication'"
            . ' AND SubjectEntityId = ?',
            [$personId]
        ), 'the authorship stays');
        self::assertSame(0, $this->rowCount(
            "SELECT COUNT(*) AS n FROM relationships WHERE PredicateKind = 'event-involves-person'"
            . ' AND ObjectEntityId = ?',
            [$personId]
        ));
        self::assertSame(0, $this->rowCount(
            "SELECT COUNT(*) AS n FROM sch_changes WHERE ChangedEntity = 'person' AND ChangedIDValue = ?
             AND (OldValue IS NOT NULL OR NewValue IS NOT NULL)",
            [$personId]
        ), 'the history of their contact data');
    }

    public function testABookStillOutIsRefused(): void
    {
        [$personId] = $this->fixture();
        $this->db->execute(
            'INSERT INTO lib_checkouts (PersonId, BookId, CheckedOutOn)' . ' VALUES (?, 0, UTC_TIMESTAMP())',
            [$personId]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('still out');
        (new Erasure($this->db, self::PROJECT))->plan($personId);
    }

    /**
     * A person with an account and one of each thing linked to them.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} person, account, association, spouse
     */
    private function fixture(): array
    {
        $this->db->execute(
            "INSERT INTO sch_persons (FirstName, LastName, Email, Phone1, Country, PublicNotes)
             VALUES ('Fixture', 'Privacy', 'fixture@example.com', '555-0100', 'CL', 'public')"
        );
        $personId = $this->db->lastInsertId();
        $this->db->execute(
            "INSERT INTO sch_persons (FirstName, LastName, SpousePersonId) VALUES ('Spouse', 'Privacy', ?)",
            [$personId]
        );
        $spouseId = $this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO user (email, username, create_datetime, update_datetime, state, PersID)
             VALUES ('fixture-privacy@example.com', 'fixture-privacy', UTC_TIMESTAMP(), UTC_TIMESTAMP(), 1, ?)",
            [$personId]
        );
        $userId = $this->db->lastInsertId();
        //a role of the fixture's own: user_role_linker has a foreign key, and the CI seed
        //need not hold any particular role row
        $this->db->execute("INSERT INTO user_role (role_id, is_default) VALUES ('fixture-privacy', 0)");
        $roleRowId = $this->db->lastInsertId();
        $this->db->execute('INSERT INTO user_role_linker (user_id, role_id)' . ' VALUES (?, ?)', [$userId, $roleRowId]);
        $this->db->execute(
            "INSERT INTO user_api_token (jti, user_id, label, issued_on, expires_on)
             VALUES (?, ?, 'fixture', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [str_pad((string) $userId, 43, 'x'), $userId]
        );

        $this->db->execute(
            "INSERT INTO sch_associations (AssociationName, Kind, PublicNotes, UpdatedBy)
             VALUES ('Fixture association', 'group', 'led by Fixture Privacy for years', ?)",
            [$userId]
        );
        $associationId = $this->db->lastInsertId();
        $this->db->execute(
            "INSERT INTO sch_roles (RoleTitle, AssociationId) VALUES ('Keeper of the fixtures', ?)",
            [$associationId]
        );
        $roleId = $this->db->lastInsertId();
        $this->db->execute('INSERT INTO sch_assignments (RoleId, PersonId)' . ' VALUES (?, ?)', [$roleId, $personId]);
        $this->db->execute(
            'INSERT INTO lib_checkouts (PersonId, BookId, CheckedOutOn, CheckedInOn)'
            . ' VALUES (?, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$personId]
        );
        $this->db->execute(
            "INSERT INTO lib_borrower_tokens (TokenHash, PersonId, LibraryId, CreatedOn, ExpiresOn)
             VALUES (SHA2(?, 256), ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            ['fixture-' . $personId, $personId]
        );
        $this->db->execute(
            "INSERT INTO relationships (SubjectEntityId, ObjectEntityId, PredicateKind)
             VALUES (1, ?, 'event-involves-person')",
            [$personId]
        );
        $this->db->execute(
            'INSERT INTO sch_changes (ChangedEntity, ChangedField, ChangedIDValue, OldValue, NewValue, UpdatedOn, '
            . "UpdatedBy) VALUES ('person', 'phone1', ?, NULL, '555-0100', UTC_TIMESTAMP(), ?)",
            [$personId, $userId]
        );
        $this->db->execute(
            "INSERT INTO sch_provenance (Entity, EntityId, FieldGroup, SourceClass, Outcome, AssertedOn, RecordedOn)
             VALUES ('person', ?, 'contact', 'self', 'accepted', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$personId]
        );
        $this->db->execute(
            "INSERT INTO comments (CommentKind, Comment, Status, CreatedOn, CreatedBy)
             VALUES ('comment', 'a fixture comment', 'new', UTC_TIMESTAMP(), ?)",
            [$userId]
        );

        //someone else's record, edited by this account and naming the person in its notes
        $this->db->execute(
            'INSERT INTO sch_changes (ChangedEntity, ChangedField, ChangedIDValue, OldValue, NewValue, UpdatedOn, '
            . "UpdatedBy) VALUES ('association', 'publicNotes', ?, NULL, 'led by Fixture Privacy for years',"
            . ' UTC_TIMESTAMP(), ?)',
            [$associationId, $userId]
        );

        return [$personId, $userId, $associationId, $spouseId];
    }

    /**
     * @param list<array{table: string, column: string, id: int|string}> $mentions
     * @return list<array{table: string, column: string, id: int}>
     */
    private function normalised(array $mentions): array
    {
        return array_map(
            static fn (array $m): array => ['table' => $m['table'], 'column' => $m['column'], 'id' => (int) $m['id']],
            $mentions
        );
    }

    /**
     * @param list<int|string> $values
     */
    private function rowCount(string $sql, array $values): int
    {
        $row = $this->db->select($sql, $values)->current();

        return (int) ($row['n'] ?? 0);
    }
}
