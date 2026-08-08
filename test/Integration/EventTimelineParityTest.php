<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Books\EventTimeline;
use App\Laminas\ServiceBridge;
use Books\Controller\EventsController;
use Books\Model\EventTextTable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function is_readable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * /timeline is answered by two front controllers, and this pins that they group the
 * same rows the same way.
 *
 * The pattern and the reasoning are ShrineIndexParityTest's: the laminas action is left
 * untouched so that production — which still serves it — cannot be affected by the
 * port, which leaves two copies of the grouping, and this is what makes the
 * duplication safe. When the laminas route goes, `groupEventsByEpochAndYear()` and
 * this test go with it.
 *
 * Three properties of the original are easy to lose in a rewrite and each would pass a
 * "same number of events" assertion, so the comparison is a strict `assertSame` of the
 * whole nested structure:
 *
 *  - **period order** is first-seen order, not period number;
 *  - **year order** within a period is likewise;
 *  - **events are keyed by `eventId`**, not appended.
 *
 * `groupEventsByEpochAndYear()` is public and touches no controller plugin, no view
 * and no MvcEvent — it reads `$this->getSionTable()` only through the filter it
 * constructs — so it can be driven directly, with the table injected. That is why this
 * page's parity is testable at all where formatEntity's was not.
 */
class EventTimelineParityTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            $this->markTestSkipped('no local configuration; this test needs a database');
        }
    }

    private function bridge(): ServiceBridge
    {
        return self::$bridge ??= new ServiceBridge(require __DIR__ . '/../../config/application.config.php');
    }

    /**
     * The whole grouping, over every event row in the database — 1,000-odd of them
     * across eight periods, which is the only sample worth comparing: a handful of
     * hand-made rows would exercise neither the ordering nor the keying.
     */
    public function testBothFrontControllersGroupTheSameEventsIdentically(): void
    {
        /** @var EventTextTable $table */
        $table  = $this->bridge()->get(EventTextTable::class);
        $events = $table->queryObjects('event');

        $this->assertNotEmpty($events, 'no event rows; the comparison would be vacuous');

        $laminas = $this->laminasController($table)->groupEventsByEpochAndYear($events);
        $symfony = EventTimeline::group($events);

        $this->assertSame(
            $this->comparable($laminas),
            $this->comparable($symfony),
            'the ported timeline groups events differently from the laminas action'
        );
    }

    /**
     * The controller without its container: `EventsController` extends SionController,
     * whose constructor wants a service array, and none of that is reachable from here.
     * The method under test needs exactly one thing — the SionTable, for the filter it
     * builds — so the instance is created uninitialised and given that.
     *
     * If this ever stops working it is because the method grew a dependency, and the
     * right response is to look at what it grew rather than to widen this.
     */
    private function laminasController(EventTextTable $table): EventsController
    {
        $reflection = new ReflectionClass(EventsController::class);
        /** @var EventsController $controller */
        $controller = $reflection->newInstanceWithoutConstructor();
        $controller->setSionTable($table);

        return $controller;
    }

    /**
     * The rows themselves are shared objects, so comparing them compares identity and
     * proves nothing about the grouping. What matters is the *shape*: which period,
     * which year, which event ids, in which order.
     *
     * @param array<int, array<int|string, array<int|string, array<string, mixed>>>> $periods
     * @return array<int, array<int|string, list<int|string>>>
     */
    private function comparable(array $periods): array
    {
        $shape = [];
        foreach ($periods as $period => $years) {
            foreach ($years as $year => $events) {
                $shape[$period][$year] = array_keys($events);
            }
        }

        return $shape;
    }
}
