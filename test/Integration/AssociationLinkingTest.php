<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Schoenstatt\Model\SchoenstattTable;
use Throwable;

/**
 * `linkAssociations()` may skip its second database query when it already holds every
 * association, and the two paths must produce the same thing.
 *
 * ## What the query was for
 *
 * Linking a set of associations means attaching each one's parent and its children. Those
 * rows may lie outside the set — a filtered search can turn up a record whose parent it
 * never selected — so the method asked the database for them: one `orCombination` query
 * over `parentId IN (…) OR associationId IN (…)`, i.e. children and parents in a single
 * round trip instead of two per record. It was a batching optimisation and a good one.
 *
 * It stopped being one when the whole table started arriving from cache. `getAssociation()`
 * — one record — goes through `getAssociations()`, which loads all 498 rows out of APCu in
 * 10 ms and then re-fetched 431 of them from the database. Measured in-request on
 * 2026-08-22: **276–333 ms**, against 1.2 ms and 0.25 ms for the two loops that consume the
 * result. It is the whole cost of the slowest page on the site.
 *
 * ## What is asserted
 *
 * Not "the fast path is fast" — that is a benchmark and it would rot. The two properties
 * that make skipping the query *legal*:
 *
 * 1. every row the query returns is already in hand, and identical to the copy in hand.
 *    This is the claim the optimisation rests on, and it is a claim about the **entity
 *    spec**, not about this method: a field the query populates and the cached read does
 *    not would break it silently, and nothing else would notice;
 * 2. linking with and without the query produces the same structure — same parents, same
 *    children, same roles, same assignments, for all 498 records.
 *
 * The structure is compared after breaking the references the linking leaves behind, for a
 * reason worth stating: `$objects[$x]['parent']` is bound by reference to a row in a second
 * array, so two structures can hold equal values while `assertEquals` walks them
 * differently. Encoding both sides first compares what a template would actually read.
 */
class AssociationLinkingTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    /**
     * Module loading the way bin/console does, config caches off — CI has no writable
     * data/config, and a cache file owned by the wrong user beside a real deployment is
     * worse than a slow test.
     */
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

    private function table(): SchoenstattTable
    {
        try {
            /** @var SchoenstattTable $table */
            $table = $this->bridge()->get(SchoenstattTable::class);
            //cheapest reachable statement: the suite must skip, not fail, with no database
            $table->getObjects('association');
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }

        return $table;
    }

    /** @return array<int, array<string, mixed>> */
    private function everyAssociation(SchoenstattTable $table): array
    {
        /** @var array<int, array<string, mixed>> $objects */
        $objects = $table->getObjects('association');
        self::assertNotEmpty($objects, 'sanity: there should be associations to link');

        return $objects;
    }

    /**
     * The claim the whole optimisation rests on: the re-query tells us nothing new.
     *
     * Written as the query itself rather than by calling the method, so that it keeps
     * testing the *entity spec* — whether a row read through `queryObjects()` with a
     * predicate is the same row as one read through `getObjects()` — after the method has
     * stopped issuing it.
     */
    public function testTheRelatedQueryReturnsNothingThatIsNotAlreadyLoaded(): void
    {
        $table   = $this->table();
        $objects = $this->everyAssociation($table);

        $interesting = [];
        foreach ($objects as $entityId => $object) {
            if (isset($object['parentId']) && $object['parentId'] != $entityId) {
                $interesting[] = $object['parentId'];
            }
        }
        $query = ['parentId' => array_keys($objects)];
        if ([] !== $interesting) {
            $query['associationId'] = array_unique($interesting);
        }

        $queryObjects = new ReflectionMethod($table, 'queryObjects');
        /** @var array<int, array<string, mixed>> $results */
        $results = $queryObjects->invoke($table, 'association', $query, [
            'orCombination' => true,
            'noLink'        => true,
        ]);

        self::assertNotEmpty($results, 'sanity: the related query should match something');
        self::assertSame(
            [],
            array_values(array_diff(array_keys($results), array_keys($objects))),
            'the related query found an association the full load did not have — the two '
            . 'reads have diverged and linking from the loaded set would drop rows'
        );

        $differing = [];
        foreach ($results as $id => $row) {
            if ($row != $objects[$id]) {
                $differing[] = $id;
            }
        }
        self::assertSame(
            [],
            $differing,
            'a row read through the related query differs from the same row read through '
            . 'getObjects() — some field is populated on one path and not the other'
        );
    }

    /**
     * The two linking paths agree, for every record, on everything a template can read.
     */
    public function testLinkingWithAndWithoutTheQueryProducesTheSameStructure(): void
    {
        $table = $this->table();

        $link = new ReflectionMethod($table, 'linkAssociations');

        $withQuery = $this->everyAssociation($table);
        $link->invokeArgs($table, [&$withQuery, false]);

        $withoutQuery = $this->everyAssociation($table);
        $link->invokeArgs($table, [&$withoutQuery, true]);

        self::assertSame(
            array_keys($withQuery),
            array_keys($withoutQuery),
            'the two paths linked a different set of associations'
        );

        foreach (array_keys($withQuery) as $id) {
            self::assertSame(
                $this->comparable($withQuery[$id]),
                $this->comparable($withoutQuery[$id]),
                sprintf('association %s links differently without the related query', (string) $id)
            );
        }
    }

    /**
     * Everything the linking attaches, for one well-connected record, is still attached.
     *
     * A guard against the cheapest possible way to pass the test above: both paths
     * attaching nothing at all. Association 1 is the General Presidium, which
     * `SchoenstattController` and `MovementController` both render.
     */
    public function testLinkingStillAttachesParentsChildrenAndRoles(): void
    {
        $table   = $this->table();
        $objects = $this->everyAssociation($table);

        $link = new ReflectionMethod($table, 'linkAssociations');
        $link->invokeArgs($table, [&$objects, true]);

        $withParent   = 0;
        $withChildren = 0;
        $withRoles    = 0;
        foreach ($objects as $row) {
            $withParent   += isset($row['parent']) ? 1 : 0;
            $withChildren += [] !== ($row['childAssociations'] ?? []) ? 1 : 0;
            $withRoles    += [] !== ($row['roles'] ?? []) ? 1 : 0;
        }

        self::assertGreaterThan(0, $withParent, 'no association came back with a parent');
        self::assertGreaterThan(0, $withChildren, 'no association came back with children');
        self::assertGreaterThan(0, $withRoles, 'no association came back with roles');
    }

    /**
     * A row attached as a parent or a child is the same row the caller would find under its
     * own id — roles, assignments and leader included.
     *
     * This is the property the two-path comparison above cannot see. Until 2026-08-22
     * `connectEntityRolesAndAssignments()` ran **after** the linking and only on `$objects`,
     * so every attached row was a pre-roles copy: 791 of the 792 links on the full set
     * differed from their own row, 187 of them on `mainPerson`. Both paths did it, so both
     * paths agreed, and the comparison passed.
     *
     * The visible symptom was a blank leader column in the "Associated organizations" table
     * (`_associations-table.html.twig` reads `entity.mainPerson`, as does the `.phtml`), on
     * a page whose other tables filled the same column in.
     *
     * The link's own link keys are excluded: `parent` and `childAssociations` are empty on
     * an attached row **by design**, which is what keeps the structure two levels deep
     * instead of cyclic.
     */
    public function testAnAttachedRowCarriesTheSameDataAsItsOwnRow(): void
    {
        $table   = $this->table();
        $objects = $this->everyAssociation($table);

        $link = new ReflectionMethod($table, 'linkAssociations');
        $link->invokeArgs($table, [&$objects, true]);

        $links = 0;
        foreach ($objects as $id => $row) {
            foreach (($row['childAssociations'] ?? []) as $childId => $child) {
                $links++;
                self::assertSame(
                    $this->comparable($this->withoutLinks($objects[$childId])),
                    $this->comparable($this->withoutLinks($child)),
                    sprintf('association %s is attached to %s as a child in a different state '
                        . 'than it has under its own id', (string) $childId, (string) $id)
                );
            }
            if (! isset($row['parent'], $objects[$row['parentId']])) {
                continue;
            }
            $links++;
            self::assertSame(
                $this->comparable($this->withoutLinks($objects[$row['parentId']])),
                $this->comparable($this->withoutLinks($row['parent'])),
                sprintf('association %s is attached to %s as its parent in a different state '
                    . 'than it has under its own id', (string) $row['parentId'], (string) $id)
            );
        }

        self::assertGreaterThan(0, $links, 'sanity: nothing was linked, so nothing was checked');
    }

    /**
     * The row minus the two keys the linking itself writes.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function withoutLinks(array $row): array
    {
        unset($row['parent'], $row['childAssociations']);

        return $row;
    }

    /**
     * A value comparable across the two paths.
     *
     * `var_export` rather than `==`: the linked rows hold references into a second array,
     * and comparing two structures that are equal in value but differently referenced is
     * exactly the sort of thing that passes or fails for reasons unrelated to the data. A
     * string encoding compares what a template reads.
     *
     * @param array<string, mixed> $row
     */
    private function comparable(array $row): string
    {
        return var_export($row, true);
    }
}
