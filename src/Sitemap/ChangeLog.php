<?php

declare(strict_types=1);

namespace App\Sitemap;

use App\Laminas\ServiceBridge;
use DateTimeImmutable;
use DateTimeZone;
use Laminas\Db\Adapter\Adapter;
use Throwable;

use function array_fill;
use function count;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;

/**
 * When each record last changed, read from SionModel's audit table.
 *
 * ## Why not the entity's own `UpdatedOn` column
 *
 * Because it is not maintained, and it looks like it is. Association 319 was edited three
 * times on 2026-08-13; every one of its eleven `*UpdatedOn` columns still reads 2019-08-15.
 * A `<lastmod>` built from that column would tell Google the page had not changed in seven
 * years, which is worse than sending no `<lastmod>` at all: Google says it uses the value
 * only when it is "consistently and verifiably accurate", and a site caught lying about it
 * gets the field ignored across the board.
 *
 * `sch_changes` is the record that *is* kept — `SionTable::reportChange()` writes a row per
 * changed field on every create, update and delete. Measured 2026-08-13, it covers **every**
 * record the sitemap publishes: 498 of 498 associations, 10,166 of 10,166 publications, 335
 * of 335 compositions. So the fallback below (omit `<lastmod>`) is a guard, not a common
 * path, and there is no need to read the entity tables at all.
 *
 * ## Two queries, and why the second one is the cheap one
 *
 * `newest()` is what the staleness check calls every fifteen minutes: one `MAX()` over an
 * indexed column, answered without touching a row of the sitemap itself. `perRecord()` is
 * the expensive one and only runs when a rebuild is actually going to happen.
 */
final class ChangeLog
{
    private const TABLE = 'sch_changes';

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * The most recent change to anything the sitemap publishes, or null if unknown.
     *
     * Null means "cannot tell", and every caller treats that as stale: a sitemap we cannot
     * prove is current is one we would rather rebuild. That is the safe direction — the
     * cost is a rebuild nobody needed, and the alternative is serving a sitemap that never
     * updates again because a query started failing.
     */
    public function newest(): ?DateTimeImmutable
    {
        $entities = [];
        foreach (SitemapSection::cases() as $section) {
            $entity = $section->entity();
            if (null !== $entity) {
                $entities[] = $entity;
            }
        }

        $sql = 'SELECT MAX(UpdatedOn) AS newest FROM ' . self::TABLE . ' WHERE ChangedEntity IN ('
            . implode(', ', array_fill(0, count($entities), '?')) . ')';

        try {
            $rows = $this->query($sql, $entities);
        } catch (Throwable) {
            return null;
        }

        $value = $rows[0]['newest'] ?? null;

        return is_string($value) ? $this->toDateTime($value) : null;
    }

    /**
     * Last-changed time per record id for one entity.
     *
     * @return array<int, DateTimeImmutable>
     */
    public function perRecord(string $entity): array
    {
        $sql = 'SELECT ChangedIDValue AS id, MAX(UpdatedOn) AS newest FROM ' . self::TABLE
            . ' WHERE ChangedEntity = ? GROUP BY ChangedIDValue';

        try {
            $rows = $this->query($sql, [$entity]);
        } catch (Throwable) {
            return [];
        }

        $stamps = [];
        foreach ($rows as $row) {
            $id    = $row['id'] ?? null;
            $value = $row['newest'] ?? null;
            if (! is_numeric($id) || ! is_string($value)) {
                continue;
            }
            $when = $this->toDateTime($value);
            if (null !== $when) {
                $stamps[(int) $id] = $when;
            }
        }

        return $stamps;
    }

    /**
     * @param list<string> $parameters
     * @return list<array<string, mixed>>
     */
    private function query(string $sql, array $parameters): array
    {
        /** @var Adapter $adapter */
        $adapter = $this->laminas->get(Adapter::class);

        $result = $adapter->createStatement($sql, $parameters)->execute();

        $rows = [];
        foreach ($result as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * A `datetime` column value as a UTC instant.
     *
     * The column carries no zone and the rows are UTC — the same convention the rest of the
     * database follows, and the reason a log search by a row's timestamp has come up empty
     * here before: the logs are local time.
     */
    private function toDateTime(string $value): ?DateTimeImmutable
    {
        try {
            $when = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }

        //A stamp in the future is a clock or import artefact, not information. Clamping
        //rather than dropping keeps the entry dated while refusing to publish a date Google
        //would be right to distrust.
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $when > $now ? $now : $when;
    }
}
