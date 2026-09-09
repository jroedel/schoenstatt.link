<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Http\CspNonce;
use App\Laminas\HostMessages;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use App\Twig\TwigFactory;
use Laminas\Db\Adapter\Adapter;
use Locale;
use PHPUnit\Framework\TestCase;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;
use Twig\Environment;

use function array_filter;
use function array_slice;
use function count;
use function is_readable;
use function reset;
use function sprintf;
use function substr_count;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `templates/schoenstatt/_assignments-table.html.twig` against real assignment rows,
 * with no HTTP — the same arrangement as ShrineTemplateTest and for the same reason:
 * the markup this file is about only renders for a viewer who may edit people, and the
 * smoke suite has no such session.
 *
 * It exists because of a defect the smoke suite could never have caught and the route
 * tests would not either. The partial passes `{'displayEditPencil': false}` for the
 * person cell — the row already carries the *assignment's* pencil immediately after the
 * name, and two pencils side by side pointing at different records is the thing it is
 * avoiding. Until 2026-08-13 the `person()` macro in `_entity-format.html.twig` took no
 * options at all, so that one was accepted and dropped, and every assignment row on
 * every ported association page carried a `…/persons/{id}/edit` link the laminas page
 * does not have. Measured on /en/SL100001A against the laminas rendering of the same
 * URL; nothing failed, the page merely offered a link the original does not.
 *
 * **The pencils themselves are asserted in the smoke suite, not here**, and that is a
 * limitation of this arrangement rather than a preference. `edit_pencil()` renders
 * nothing unless the viewer may reach the edit route, the identity comes from the
 * laminas session, and a CLI process has none — so headlessly *every* pencil is absent
 * and "no person pencil" would pass against a macro that had simply lost the ability to
 * render one. Only a one-sided assertion is available here, so the two-sided one lives
 * in AssignmentsSearchSymfonySmokeTest, which signs in as a real sch_moderator.
 *
 * What this file *can* prove is the half that needs no identity: the column branches,
 * the active/inactive filter, and the empty-table gate.
 */
class AssignmentsTableTest extends TestCase
{
    private const TEMPLATE = 'schoenstatt/_assignments-table.html.twig';

    /** Enough rows to be representative, few enough that a failure is readable. */
    private const SAMPLE = 25;

    private static ?ServiceBridge $bridge = null;

    public static function setUpBeforeClass(): void
    {
        Locale::setDefault('en_US');
    }

    /** @see ShrineTemplateTest::setUp() — same skip conditions, same ordering, same reasons. */
    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            /** @var Adapter $adapter */
            $adapter = $this->bridge()->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
            $this->bridge()->get(SchoenstattTable::class);
        } catch (Throwable $e) {
            self::markTestSkipped(
                'no reachable database: ' . $e->getMessage()
                . ' — this test needs the capsule up (docker compose up -d)'
            );
        }
    }

    /**
     * The person is still linked, whatever happens to the pencils. The weakest of the
     * three assertions about this cell and the only one available without an identity —
     * see the class docblock.
     */
    public function testThePersonCellLinksThePerson(): void
    {
        $assignments = $this->assignments();
        $html        = $this->render(['assignments' => $assignments, 'columns' => ['role', 'person']]);

        $first = reset($assignments);
        $this->assertIsArray($first);
        $this->assertArrayHasKey('person', $first, 'the sample must include linked person rows');

        $this->assertStringContainsString(
            sprintf('/en/persons/%s"', $first['person']['personId']),
            $html
        );
    }

    /**
     * `strict_variables` is on, so every column the five branches read has to exist on
     * every row. The email and telephone branches fall back from the person to the
     * association, which means both shapes have to be present — this renders all five
     * columns over the sample and requires one `<td>` per column per row.
     */
    public function testEveryColumnRendersOnEveryRow(): void
    {
        $assignments = $this->assignments();
        $columns     = ['association', 'role', 'person', 'email', 'telephone'];

        $html = $this->render([
            'assignments'   => $assignments,
            'columns'       => $columns,
            'show_active'   => true,
            'show_inactive' => true,
        ]);

        $this->assertSame(
            count($assignments) + 1,
            substr_count($html, '<tr>'),
            'one row per assignment plus the header row'
        );
        $this->assertSame(
            count($assignments) * count($columns),
            substr_count($html, '<td>'),
            'one cell per column per row'
        );
        $this->assertStringNotContainsString('Undefined', $html);
    }

    /**
     * The filter partitions rather than being ignored, which is the assertion that
     * catches `show_active|default(true)` — Twig's default filter fires on an *empty*
     * value, so an explicit `false` became `true` and both flags were permanently on.
     * The association show page renders this partial twice with complementary flags,
     * so an ignored filter shows the same rows in both columns and neither is empty.
     *
     * Written as a partition (the two halves sum to the whole, and neither is the whole)
     * so it needs no fixture with a known active/inactive split: it fails on any sample
     * containing both kinds and skips rather than lying on one that does not.
     */
    public function testTheActiveAndInactiveFiltersPartitionTheRows(): void
    {
        $assignments = $this->assignments();
        $active      = array_filter($assignments, static fn (array $a): bool => (bool) $a['isActive']);
        $inactive    = array_filter($assignments, static fn (array $a): bool => ! $a['isActive']);

        if ([] === $active || [] === $inactive) {
            self::markTestSkipped('the sample is all-active or all-inactive, so a partition proves nothing');
        }

        $activeRows   = $this->rowCount($this->render([
            'assignments'   => $assignments,
            'show_active'   => true,
            'show_inactive' => false,
        ]));
        $inactiveRows = $this->rowCount($this->render([
            'assignments'   => $assignments,
            'show_active'   => false,
            'show_inactive' => true,
        ]));

        $this->assertSame(count($active), $activeRows, 'the active-only rendering shows the active rows');
        $this->assertSame(count($inactive), $inactiveRows, 'the inactive-only rendering shows the inactive rows');
        $this->assertSame(count($assignments), $activeRows + $inactiveRows, 'and together they are the whole');
        $this->assertNotSame(count($assignments), $activeRows, 'an ignored filter would render everything twice');
    }

    /**
     * The `count > 0` gate, which is the reason a shrine with no past contacts shows an
     * empty column rather than an empty "Past contacts" table. Asserted with a header
     * set, because the header is emitted *outside* the table and losing the gate would
     * leave it stranded on its own.
     */
    public function testTheWholeTableDisappearsWhenTheFilterMatchesNothing(): void
    {
        $html = $this->render([
            'assignments'   => $this->assignments(),
            'show_active'   => false,
            'show_inactive' => false,
            'header_text'   => 'Past contacts',
        ]);

        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('Past contacts', $html);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(array $context): string
    {
        return $this->twig()->render(self::TEMPLATE, $context);
    }

    /** Body rows: every `<tr>` bar the header's. Zero when the table did not render at all. */
    private function rowCount(string $html): int
    {
        $rows = substr_count($html, '<tr>');

        return $rows > 0 ? $rows - 1 : 0;
    }

    /**
     * Rows that actually carry a linked person, since every assertion here is about the
     * person cell. `getAssignments()` drops any row whose person or association is
     * missing, so the survivors all have one — but the filter is explicit rather than
     * assumed, and the emptiness check is what stops this whole file passing vacuously.
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function assignments(): array
    {
        /** @var SchoenstattTable $table */
        $table       = $this->bridge()->get(SchoenstattTable::class);
        $assignments = $table->getAssignments();

        $withPerson = array_filter(
            $assignments,
            static fn (array $a): bool => isset($a['person']['personId']) && isset($a['assignmentId'])
        );
        $this->assertNotEmpty($withPerson, 'no assignments with a linked person — this test would prove nothing');

        return array_slice($withPerson, 0, self::SAMPLE, true);
    }

    private function twig(): Environment
    {
        $requests = new RequestStack();
        $request  = Request::create('/en/assignments/search');
        $request->attributes->set('_route', 'assignments/search.locale');
        $request->attributes->set('_locale', 'en');
        $requests->push($request);

        return (new TwigFactory())->create(
            $this->bridge(),
            new ViewHelpers($this->bridge(), fn (): RouteUrl => new RouteUrl($this->bridge(), '')),
            new RouteUrl($this->bridge(), ''),
            $requests,
            new CspNonce(),
            new HostMessages()
        );
    }

    /** Config caches off, for the reason CacheStatusEndpointTest states: no test may write data/config/. */
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
}
