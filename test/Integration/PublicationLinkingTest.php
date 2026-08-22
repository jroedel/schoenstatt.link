<?php

declare(strict_types=1);

namespace BooksTest\Integration;

use App\Laminas\ServiceBridge;
use Books\Model\PublicationsTable;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * What `PublicationsTable` attaches to a publication, and how deep.
 *
 * ## Why this looks different from AssociationLinkingTest
 *
 * The association side stopped querying for its related rows because the caller already
 * held every one of them. That argument was checked here and **does not hold**: measured
 * 2026-08-22, the related query returned 71 of 73 rows new on the German literature index,
 * 56 of 56 on the English one and 138 of 196 on a search. A publication result set is
 * filtered — `noSubEditions` is the default on the index, so a row's sub-editions are by
 * definition outside it — and `getPublication()` starts from a single record. The query
 * stays; what changed is that `sch_publications` now has indexes on the two columns it
 * searches (`database/db8.7.sql`), and that the second, nested query is gone.
 *
 * So this pins behaviour, not the absence of a query:
 *
 *  1. the relations are attached at all, in both directions;
 *  2. an attached row is **not itself linked** — the `noLink` on the inner
 *     `searchPublications()` call. Nothing rendered ever read those nested links
 *     (`FormatPublication` reads none of the four keys), and leaving them off is what
 *     keeps the structure two levels deep rather than open-ended.
 */
class PublicationLinkingTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    /** Module loading the way bin/console does, config caches off — CI has no writable data/config. */
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

    private function table(): PublicationsTable
    {
        try {
            /** @var PublicationsTable $table */
            $table = $this->bridge()->get(PublicationsTable::class);
            $table->searchPublications(['publicationId' => 1], ['noLink' => true]);
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }

        return $table;
    }

    /**
     * A publication that has sub-editions, found from the data rather than hardcoded —
     * the capsule's dump is refreshed from production and an id would rot.
     *
     * @return array{0: PublicationsTable, 1: int}
     */
    private function publicationWithSubEditions(): array
    {
        $table = $this->table();

        /** @var array<int, array<string, mixed>> $rows */
        $rows   = $table->searchPublications([], ['noLink' => true, 'includeDataSources' => true]);
        $counts = [];
        foreach ($rows as $row) {
            $main = $row['mainPublicationId'] ?? null;
            if (null !== $main && $main != $row['publicationId']) {
                $counts[(int) $main] = ($counts[(int) $main] ?? 0) + 1;
            }
        }
        arsort($counts);
        $id = (int) array_key_first($counts);

        if (0 === $id) {
            self::markTestSkipped('no publication in this database has sub-editions');
        }

        return [$table, $id];
    }

    public function testAPublicationCarriesItsSubEditions(): void
    {
        [$table, $id] = $this->publicationWithSubEditions();

        /** @var array<string, mixed>|null $object */
        $object = $table->getPublication($id);

        self::assertIsArray($object, sprintf('publication %d did not load', $id));
        self::assertNotEmpty(
            $object['subEditions'] ?? [],
            sprintf('publication %d has sub-editions in the table but none attached', $id)
        );
        foreach (array_keys($object['subEditions']) as $subId) {
            self::assertNotSame($id, $subId, 'a publication was attached as its own sub-edition');
        }
    }

    /**
     * The rows attached to a publication are unlinked.
     *
     * This is what the `noLink` on the inner `searchPublications()` call buys, and the
     * whole of the third database query the publication page used to issue. Written as a
     * property of the returned structure rather than as a statement count, because a
     * statement count would also fail for reasons that are nobody's fault.
     */
    public function testAnAttachedEditionIsNotItselfLinked(): void
    {
        [$table, $id] = $this->publicationWithSubEditions();

        /** @var array<string, mixed> $object */
        $object   = $table->getPublication($id);
        $attached = array_merge(
            array_values($object['subEditions'] ?? []),
            array_values($object['translations'] ?? []),
            null !== ($object['mainPublication'] ?? null) ? [$object['mainPublication']] : [],
            null !== ($object['translatedFromPublication'] ?? null) ? [$object['translatedFromPublication']] : []
        );

        self::assertNotEmpty($attached, 'sanity: nothing was attached, so nothing was checked');

        foreach ($attached as $row) {
            self::assertIsArray($row);
            foreach (['subEditions', 'translations'] as $key) {
                self::assertSame(
                    [],
                    $row[$key] ?? [],
                    sprintf(
                        'an edition attached to publication %d carries its own %s — the inner '
                        . 'searchPublications() call has lost its noLink and is issuing a third query',
                        $id,
                        $key
                    )
                );
            }
            foreach (['mainPublication', 'translatedFromPublication'] as $key) {
                self::assertNull(
                    $row[$key] ?? null,
                    sprintf('an edition attached to publication %d carries its own %s', $id, $key)
                );
            }
        }
    }
}
