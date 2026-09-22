<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use SionModel\Db\Connection;
use Throwable;

/**
 * Skip when there is no reachable database, and hand back the adapter when there is.
 *
 * ## Why this exists now
 *
 * `RequiresApcu`'s docblock ends with the warning that came true: *"a test that needs a
 * database still has to catch its own connection failure, on a runner that has APCu and
 * no database."* Installing APCu in the integration job on 2026-09-08 created exactly
 * that runner, and three `AclCacheTest` tests that had only ever skipped for want of the
 * extension started running and then failed — two with a
 * `ServiceNotCreatedException` about the missing `db` config, one with a bare assertion
 * failure against an ACL assembled from no roles.
 *
 * The two guards answer different questions and both are needed:
 *
 * - `requireApcu()` is about whether the **container can be built at all**, so it has to
 *   run before anything is resolved from it.
 * - `requireDatabase()` is about whether a service that builds fine can actually *talk*
 *   to anything, so it runs after.
 *
 * ## `connect()`, not just `get()`
 *
 * Resolving `Connection::class` proves only that credentials were readable. laminas-db
 * connects lazily, so a wrong host or a stopped server survives the `get()` and fails
 * later, inside whatever query the test was really about — where it reads as a defect in
 * the code under test. Forcing the connection here makes it a skip.
 *
 * ## The 28 files that still hand-roll this
 *
 * Every one of them opens with `is_readable(config/autoload/local.php)` and then its own
 * try/catch around a connect. That pre-check is not redundant — without the file the
 * `db` config key is absent — but it is a proxy, and a proxy is what made
 * `cp local.php.dist local.php` look like a way to give CI a database: the file became
 * readable, fourteen tests stopped skipping, and five of them errored. They are left
 * alone here deliberately. Converting them is a mechanical refactor of 28 files with no
 * behaviour change, and it does not belong in the same commit as a change to what CI
 * runs; this trait is what they should adopt when someone does it.
 */
trait RequiresDatabase
{
    /**
     * The connected adapter, or a skipped test.
     *
     * Takes the bridge rather than building one, because every test class in this suite
     * makes its own — some with the config caches disabled, some with a fixture path —
     * and a trait that built its own would be testing a different container from the one
     * the test then uses.
     */
    private function requireDatabase(ServiceBridge $bridge): Connection
    {
        try {
            /** @var Connection $adapter */
            $adapter = $bridge->get(Connection::class);
            $adapter->select('SELECT 1');

            return $adapter;
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }
    }
}
