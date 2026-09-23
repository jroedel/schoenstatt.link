<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Services\Container;
use App\Laminas\ContainerFactory;
use SionModel\Db\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use SchoenstattTest\Container\ContainerSurface;
use SchoenstattTest\Container\RecordedContainer;
use Throwable;

use function count;
use function is_readable;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Container/RecordedContainer.php';
require_once __DIR__ . '/../Container/ContainerSurface.php';

/**
 * The container answers every name exactly as `Laminas\ServiceManager\ServiceManager` did.
 *
 * `test/Container/container-surface.php` was recorded on 2026-09-21 while that package was
 * still installed — 103 names, their types, which of them are the same object, and the state
 * the two delegators set. `App\Services\Container` replaced it in the same PR, and this is
 * what says the replacement is one.
 *
 * A moved line here is a real answer moving. The container is what builds every service in
 * the application, so the cost of a silent change is a service that is quietly a second
 * instance, an alias that quietly stopped resolving, or a delegator that quietly stopped
 * running — none of which any other test would report, because each of them still yields an
 * object of the expected class.
 */
final class ContainerSurfaceTest extends TestCase
{
    use RequiresApcu;

    private function container(): ContainerInterface
    {
        $this->requireApcu();

        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so there is no db configuration');
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';

        //Config caches off, for the reason every integration test turns them off: CI has no
        //writable data/config, and a cached merge from an earlier run is not what is under test.
        $container = ContainerFactory::build($appConfig);

        try {
            /** @var Connection $adapter */
            $adapter = $container->get(Connection::class);
            $adapter->select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }

        return $container;
    }

    public function testEveryRecordedNameAnswersAsItWasRecorded(): void
    {
        //Recorded against the capsule's corpus, so it answers a different question against
        //any other one: `authors` alone offers 1,890 options there and six here. The seed
        //exists so the rest of this suite can run without a production export, not so this
        //baseline can be re-recorded per environment — regenerating it per corpus would
        //leave it asserting nothing. See test/known-ci-skips.txt.
        if ('seed' === getenv('SCHOENSTATT_TEST_CORPUS')) {
            self::markTestSkipped('recorded against the capsule corpus; this database is the CI seed');
        }

        $recorded = RecordedContainer::load();
        self::assertNotSame([], $recorded, 'the recording is missing or empty');

        $appConfig = require __DIR__ . '/../../config/application.config.php';

        //the guards, and the price of building the container twice is one adapter
        $this->container();

        self::assertSame(
            $recorded,
            ContainerSurface::describe($appConfig),
            "the container no longer answers as it was recorded answering.\n"
            . "Read every changed line: a changed type means a different factory ran, a changed\n"
            . "`instance` means an alias or the sharing broke, a changed `probe` means a delegator\n"
            . 'stopped running. Regenerate only once the change is understood and intended.'
        );
    }

    /**
     * The container is reachable under its own class name.
     *
     * The one name that had to change with the package — ported code asked for
     * `Laminas\ServiceManager\ServiceManager` and asks for {@see Container} now — so it is
     * asserted here rather than recorded, where it would be a line guaranteed to move.
     */
    public function testTheContainerIsRegisteredUnderItsOwnClassName(): void
    {
        $container = $this->container();

        self::assertSame($container, $container->get(Container::class));
    }

    /** What the recording covers, so a shrinking name list cannot pass as a green run. */
    public function testTheRecordingCoversEveryNameTheContainerKnows(): void
    {
        $container = $this->container();

        $names    = ContainerSurface::names($container);
        $recorded = RecordedContainer::load();

        self::assertSame(count($names), count($recorded), 'names and recorded entries disagree');
        foreach ($names as $name) {
            self::assertArrayHasKey($name, $recorded);
        }
    }
}
