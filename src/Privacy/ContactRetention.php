<?php

declare(strict_types=1);

namespace App\Privacy;

use DateTimeImmutable;
use DateTimeZone;
use SionModel\Db\Connection;

use function array_fill;
use function array_keys;
use function count;
use function implode;
use function sprintf;

/**
 * The privacy policy's retention promise for a person's contact data, applied.
 *
 * **The rule.** A person's contact data is erased once five years have passed since their
 * last activity, and nothing about them is still current. Last activity is the latest of:
 * the end of their last assignment, their last library loan (out, renewed or returned),
 * and the last time their contact data was edited — or, if it never was, when the record
 * was created. "Still current" is an assignment without an end date or ending in the
 * future, or a book not yet returned; either keeps everything. docs/privacy.md has the
 * rule next to every other store's.
 *
 * **What is erased**: every column in {@see CONTACT_COLUMNS}, on the person row and — as
 * {@see CONTACT_FIELDS} — the old and new values of the same fields in `sch_changes`. The
 * change-log rows stay: the entity, field, date and editor are what the sitemap's
 * `<lastmod>` and the edit history need, and none of them is contact data. Change-log
 * values of a contact field older than the retention period are blanked for everyone,
 * because a superseded address or number has no purpose once it is that old.
 *
 * **Why SQL here and not SchoenstattTable::updateEntity().** An update through the table
 * reports every changed field to `sch_changes` with its old value — so erasing an email
 * address that way would write the address into the change log a second time. The
 * persistent cache this bypasses is flushed by the caller, over HTTP, because a console
 * process cannot reach the web server's APCu segment (docs/caching.md, rule zero).
 */
final class ContactRetention
{
    public const YEARS = 5;

    /**
     * Change-log field name => `sch_persons` column. A column that holds a way to reach a
     * person belongs here; `ContactRetentionTest` fails on a `sch_persons` column that is
     * neither here nor on its list of columns kept on purpose.
     */
    public const CONTACT_FIELDS = [
        'email'                => 'Email',
        'email2'               => 'Email2',
        'cellPhone'            => 'CellPhone',
        'cellPhoneHasWhatsApp' => 'CellPhoneHasWhatsApp',
        'phone1'               => 'Phone1',
        'phone1Label'          => 'Phone1Label',
        'phone2'               => 'Phone2',
        'phone2Label'          => 'Phone2Label',
        'phone3'               => 'Phone3',
        'phone3Label'          => 'Phone3Label',
        'url1'                 => 'Url1',
        'url1Label'            => 'Url1Label',
        'url2'                 => 'Url2',
        'url2Label'            => 'Url2Label',
        'url3'                 => 'Url3',
        'url3Label'            => 'Url3Label',
        'facebookUrl'          => 'FacebookUrl',
        'skypeUser'            => 'SkypeUser',
        'twitterUser'          => 'TwitterUser',
        'instagramUser'        => 'InstagramUser',
        'slackUser'            => 'SlackUser',
        'postStreet1'          => 'PostStreet1',
        'postStreet2'          => 'PostStreet2',
        'postCityState'        => 'PostCityState',
        'postZip'              => 'PostZip',
        'postCountry'          => 'PostCountry',
        'contactNotes'         => 'ContactNotes',
    ];

    /** The one contact column that is NOT NULL: a flag, erased to its default. */
    private const FLAG_COLUMN = 'CellPhoneHasWhatsApp';

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Persons whose contact data is due for erasure at `$now`, oldest activity first.
     *
     * @return list<array{personId: int, lastActivity: string}>
     */
    public function due(DateTimeImmutable $now): array
    {
        $holdsContact = [];
        foreach (self::CONTACT_FIELDS as $column) {
            $holdsContact[] = self::FLAG_COLUMN === $column
                ? sprintf('p.`%s` <> 0', $column)
                : sprintf("COALESCE(p.`%s`, '') <> ''", $column);
        }

        $rows = $this->db->select(
            'SELECT p.PersonId,
                    GREATEST(
                        COALESCE(a.LastEnd, \'1000-01-01\'),
                        COALESCE(c.LastLoan, \'1000-01-01\'),
                        COALESCE(p.ContactInfoUpdatedOn, p.CreatedOn, \'1000-01-01\')
                    ) AS LastActivity
             FROM sch_persons p
             LEFT JOIN (
                 SELECT PersonId, MAX(EndDate) AS LastEnd,
                        SUM(EndDate IS NULL OR EndDate > ?) AS Current
                 FROM sch_assignments GROUP BY PersonId
             ) a ON a.PersonId = p.PersonId
             LEFT JOIN (
                 SELECT PersonId,
                        MAX(GREATEST(CheckedOutOn,
                                     COALESCE(LastRenewedOn, CheckedOutOn),
                                     COALESCE(CheckedInOn, CheckedOutOn))) AS LastLoan,
                        SUM(CheckedInOn IS NULL) AS Current
                 FROM lib_checkouts GROUP BY PersonId
             ) c ON c.PersonId = p.PersonId
             WHERE COALESCE(a.Current, 0) = 0
               AND COALESCE(c.Current, 0) = 0
               AND (' . implode(' OR ', $holdsContact) . ')
             HAVING LastActivity < ?
             ORDER BY LastActivity, p.PersonId',
            [self::utc($now), self::utc($this->cutoff($now))]
        );

        $due = [];
        foreach ($rows as $row) {
            $due[] = [
                'personId'     => (int) $row['PersonId'],
                'lastActivity' => (string) $row['LastActivity'],
            ];
        }

        return $due;
    }

    /**
     * Change-log rows holding a contact value that {@see apply()} would blank.
     *
     * @param list<int> $personIds
     */
    public function dueChangeRows(DateTimeImmutable $now, array $personIds): int
    {
        [$where, $values] = $this->changeRowScope($now, $personIds);
        $row = $this->db->select('SELECT COUNT(*) AS n FROM sch_changes WHERE ' . $where, $values)->current();

        return (int) ($row['n'] ?? 0);
    }

    /**
     * Erase the contact data of `$personIds` (as returned by {@see due()}) and every
     * change-log contact value in scope, in one transaction.
     *
     * @param list<int> $personIds
     * @return array{persons: int, changeRows: int}
     */
    public function apply(DateTimeImmutable $now, array $personIds): array
    {
        $ownTransaction = ! $this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $persons = 0;
            if ([] !== $personIds) {
                $set = [];
                foreach (self::CONTACT_FIELDS as $column) {
                    $set[] = sprintf(self::FLAG_COLUMN === $column ? '`%s` = 0' : '`%s` = NULL', $column);
                }
                //Stamped as a change to the contact data, by nobody: it is one, and it
                //restarts the clock, so a record edited again later is judged afresh.
                $set[] = '`ContactInfoUpdatedOn` = ?';
                $set[] = '`ContactInfoUpdatedBy` = NULL';
                $persons = $this->db->execute(
                    'UPDATE sch_persons SET ' . implode(', ', $set)
                    . ' WHERE PersonId IN (' . self::placeholders($personIds) . ')',
                    [self::utc($now), ...$personIds]
                );
            }

            [$where, $values] = $this->changeRowScope($now, $personIds);
            $changeRows = $this->db->execute(
                'UPDATE sch_changes SET OldValue = NULL, NewValue = NULL WHERE ' . $where,
                $values
            );

            if ($ownTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return ['persons' => $persons, 'changeRows' => $changeRows];
    }

    public function cutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify(sprintf('-%d years', self::YEARS));
    }

    /**
     * A person's contact field with a value in it, either because the person is being
     * erased or because the row is older than the retention period.
     *
     * @param list<int> $personIds
     * @return array{0: string, 1: list<int|string>}
     */
    private function changeRowScope(DateTimeImmutable $now, array $personIds): array
    {
        $fields = array_keys(self::CONTACT_FIELDS);
        $scope = 'UpdatedOn < ?';
        $values = [self::utc($this->cutoff($now))];
        if ([] !== $personIds) {
            $scope = '(' . $scope . ' OR ChangedIDValue IN (' . self::placeholders($personIds) . '))';
            $values = [...$values, ...$personIds];
        }

        return [
            "ChangedEntity = 'person'"
            . ' AND ChangedField IN (' . self::placeholders($fields) . ')'
            . ' AND (OldValue IS NOT NULL OR NewValue IS NOT NULL)'
            . ' AND ' . $scope,
            [...$fields, ...$values],
        ];
    }

    /**
     * @param list<mixed> $values
     */
    private static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    /** The database's timestamps are UTC (CLAUDE.md, Production). */
    private static function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
