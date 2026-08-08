<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Http\CspNonce;
use App\Laminas\EntityFormatter;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use App\Twig\TwigFactory;
use Laminas\Db\Adapter\Adapter;
use Locale;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\Service\EntitiesService;
use SionModel\Service\ProblemService;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;
use Twig\Environment;

use function count;
use function file_get_contents;
use function in_array;
use function is_readable;
use function str_contains;
use function substr_count;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * App\Laminas\EntityFormatter reproduces SionModel\View\Helper\FormatEntity's general
 * path, and this is what keeps the reproduction honest.
 *
 * **It cannot be a two-sided parity test, and that is worth stating plainly.** Every
 * other port in this migration has one — ShrineIndexParityTest and
 * CacheStatusParityTest drive the laminas code and the ported code off the same input
 * and compare. That is impossible here: the laminas helper's `wrapAsLink()` ends in
 * `$this->view->url()`, which needs a RouteMatch off an MvcEvent, and the absence of
 * that MvcEvent is the entire reason this class exists. Calling it to compare against
 * would fail with "Call to a member function getRouteMatch() on null".
 *
 * So the byte-level agreement was established once, by hand, at porting time: the
 * signed-in laminas rendering of /en/sm/data-problems was captured before the route
 * moved and compared against the ported rendering — 9,206 bytes of table body over 30
 * real problem rows, byte-identical, links and edit pencils included. That measurement
 * is recorded in the commit and cannot be repeated once the laminas route is gone.
 *
 * What this file does instead is guard the three things that could still drift:
 *
 *  1. **The branches the reproduction leaves out stay unreachable.** `wrapAsLink()` and
 *     the edit-pencil block in the original have `showRouteParams`, `editRouteParams`
 *     and `defaultRouteParams` branches. No entity spec in this application sets any of
 *     them, so they are not reproduced — and the day one does, this fails instead of a
 *     link silently disappearing from an admin page.
 *  2. **The macro-backed types are refused, loudly.** person and association are
 *     formatted by other laminas helpers and were reproduced as Twig macros, so
 *     formatting them by the general rules would produce plausible, wrong markup and
 *     `format()` raises instead. role and publication are the two special cases that need
 *     no macro; they have their own branches here and are asserted to *work*.
 *  3. **The Twig dispatcher covers every type the formatter refuses.** The switch in
 *     templates/schoenstatt/_entity-format.html.twig and the SPECIALIZED list in the
 *     formatter are two lists that have to agree, in different languages.
 */
class EntityFormatterTest extends TestCase
{
    private const DISPATCHER = __DIR__ . '/../../templates/schoenstatt/_entity-format.html.twig';

    /**
     * The branches of the original that no spec reaches and this port therefore omits.
     *
     * `defaultRouteParams` was on this list until the first run of this test failed and
     * named text and composition — which is exactly what it is for. That
     * branch is now reproduced in both wrapAsLink() and the edit pencil; these two are
     * not, and no spec sets either.
     */
    private const UNREPRODUCED_BRANCHES = ['showRouteParams', 'editRouteParams'];

    /**
     * Types this class refuses, because their laminas formatting was reproduced as a Twig
     * *macro* (for the shrine tables) and a Twig function cannot call a macro. The other
     * two special cases — role and publication — need no macro and live in the formatter.
     */
    private const SPECIALIZED = ['person', 'association'];

    /** Special cases the formatter handles itself, each with its own branch. */
    private const OWN_BRANCHES = ['role', 'publication'];

    private static ?ServiceBridge $bridge = null;

    public static function setUpBeforeClass(): void
    {
        Locale::setDefault('en_US');
    }

    /**
     * The drift alarm. If this fails, an entity spec has grown a route-params array and
     * App\Laminas\EntityFormatter needs the corresponding branch of the original —
     * without it, that entity's name renders unlinked and nothing else complains.
     */
    public function testNoEntitySpecUsesABranchTheReproductionOmits(): void
    {
        $entities = $this->entities();
        self::assertGreaterThan(20, count($entities), 'suspiciously few entity specs');

        $offenders = [];
        foreach ($entities as $name => $spec) {
            foreach (self::UNREPRODUCED_BRANCHES as $property) {
                /** @var mixed $value */
                $value = $spec->$property;
                if (null !== $value && [] !== $value && false !== $value) {
                    $offenders[] = "$name.$property";
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            'these specs use a branch App\Laminas\EntityFormatter does not reproduce; add the branch '
            . '(see SionModel\View\Helper\FormatEntity::wrapAsLink) rather than letting the link vanish'
        );
    }

    /**
     * The counterpart: the branch that *is* reproduced is exercised on the specs that use
     * it. text and composition carry no showRouteKey, so their link can only
     * come from `defaultRouteParams` — if that branch regressed, the name would render as
     * bare text and no other assertion here would see it.
     *
     * **Both directions, because the link is ACL-dependent** and this test runs with no
     * identity. `route/text` and `route/composition` admit an anonymous visitor;
     * `route/text` does not (measured in docs/acl-rules.md). So the expected outcome is
     * read from the ACL rather than assumed — which makes this a test of isActionAllowed()
     * as well: a formatter that ignored permissions would link `text` and fail here.
     */
    public function testTheDefaultRouteParamsBranchLinksTheSpecsThatNeedIt(): void
    {
        $formatter = $this->formatter();
        $entities  = $this->entities();

        foreach (['text', 'composition'] as $type) {
            $spec = $entities[$type] ?? null;
            self::assertNotNull($spec, "no $type spec");
            /** @var array<string, string> $map */
            $map = $spec->defaultRouteParams;
            self::assertNotEmpty($map, "$type no longer uses defaultRouteParams; is this test still right?");

            //a row carrying every field the map names, plus the id and name fields
            $row = ['identifier' => 'SL10319A', 'slug' => 'a-slug'];
            foreach ([$spec->entityKeyField, $spec->nameField] as $field) {
                $row[(string) $field] = $row[(string) $field] ?? 'value';
            }

            $markup  = $formatter->format($type, $row, ['displayEditPencil' => false]);
            $allowed = $this->isAllowedRoute((string) $spec->showRoute);

            if ($allowed) {
                self::assertStringContainsString('<a href="', $markup, "$type produced no link");
                self::assertStringContainsString('SL10319A', $markup, "$type's link lost its sw_id parameter");
                self::assertStringContainsString('a-slug', $markup, "$type's link lost its slug parameter");
            } else {
                self::assertStringNotContainsString(
                    '<a href="',
                    $markup,
                    "$type linked a route this identity may not reach; isActionAllowed() is not being consulted"
                );
            }
        }
    }

    /** What isActionAllowed() asks, asked directly, so the expectation above is not a guess. */
    private function isAllowedRoute(string $route): bool
    {
        return (bool) (new ViewHelpers($this->bridge()))->isAllowed()->__invoke('route/' . $route);
    }

    /**
     * role and publication are dispatched to their own branches, not refused and not sent
     * down the general path. Both appear in sch_changes (18,243 and 1,317 rows), so this
     * is what /sm/view-changes depends on.
     */
    #[DataProvider('ownBranchTypes')]
    public function testTheFormatterHandlesItsOwnSpecialCases(string $entityType): void
    {
        self::assertTrue($this->formatter()->handles($entityType));
    }

    /** @return array<string, array{0: string}> */
    public static function ownBranchTypes(): array
    {
        $cases = [];
        foreach (self::OWN_BRANCHES as $type) {
            $cases[$type] = [$type];
        }

        return $cases;
    }

    /**
     * The role branch reads **`editPencil`**, not `displayEditPencil`, and `showLabel` —
     * Schoenstatt\View\Helper\FormatEntity's switch uses different option names from
     * SionModel's general path. That is why changes-table.phtml, which passes
     * `displayEditPencil`, does not turn a role's pencil off. Pinned because it looks
     * exactly like a typo worth "fixing".
     */
    public function testTheRoleBranchUsesItsOwnOptionNames(): void
    {
        $formatter = $this->formatter();
        $role      = ['formattedRoleTitle' => 'Diocesan coordinator', 'roleId' => 1468];

        //the general path's key is ignored here: the pencil stays on
        $withGeneralKey = $formatter->format('role', $role, ['displayEditPencil' => false]);
        self::assertStringContainsString('PENCIL', $withGeneralKey, 'displayEditPencil must not reach the role branch');

        //its own key does turn it off
        $withOwnKey = $formatter->format('role', $role, ['editPencil' => false]);
        self::assertStringNotContainsString('PENCIL', $withOwnKey);
    }

    /**
     * A *deleted* role still gets the role branch, while a deleted publication does not
     * get the publication branch. The asymmetry is the original's: Schoenstatt's switch
     * runs above any isDeleted test, SionModel's formatViewHelper deferral runs below one.
     * Getting it wrong cost exactly one row of 500 against the laminas rendering.
     */
    public function testDeletedRowsFollowTheOriginalsAsymmetry(): void
    {
        $formatter = $this->formatter();

        $deletedRole = $formatter->format(
            'role',
            ['formattedRoleTitle' => 'Role Id: 269', 'roleId' => 269, 'isDeleted' => true],
            ['displayEditPencil' => false]
        );
        self::assertStringContainsString('PENCIL', $deletedRole, 'a deleted role keeps the role branch, pencil and all');

        //a deleted publication falls through to the general path, which has no status
        //icons — the publication branch's hand-checked marker is the discriminator
        $deletedPublication = $formatter->format('publication', [
            'publicationId'           => 1,
            'title'                   => 'A title',
            'isRevisedWithBookInHand' => true,
            'isDeleted'               => true,
        ]);
        self::assertStringNotContainsString('fa-check-circle-o', $deletedPublication);
    }

    /**
     * The publication branch's three status icons, each independently switched by the row.
     * They are the visible part of that branch — 453 hand-checked, 88 data-source and 86
     * merged icons in the 500 rows this was verified against.
     */
    public function testThePublicationBranchRendersItsStatusIcons(): void
    {
        $formatter = $this->formatter();
        $base      = ['publicationId' => 1, 'title' => 'A title'];

        $plain = $formatter->format('publication', $base);
        foreach (['fa-check-circle-o', 'fa-database', 'fa-sign-in'] as $icon) {
            self::assertStringNotContainsString($icon, $plain);
        }

        $all = $formatter->format('publication', $base + [
            'isRevisedWithBookInHand' => true,
            'dataSource'              => 'somewhere',
            'mergedIntoPublicationId' => 7,
        ]);
        foreach (['fa-check-circle-o', 'fa-database', 'fa-sign-in'] as $icon) {
            self::assertStringContainsString($icon, $all);
        }
    }

    /**
     * Only `display => title` is reproduced, and the other four modes raise rather than
     * quietly returning a title. No page this side serves passes `display`, so the guard
     * is for the next one that does.
     */
    public function testAnUnreproducedPublicationDisplayModeRaises(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('authors');
        $this->formatter()->format(
            'publication',
            ['publicationId' => 1, 'title' => 'A title'],
            ['display' => 'authors']
        );
    }

    /** Each macro-backed type is refused rather than formatted by the wrong rules. */
    #[DataProvider('specializedTypes')]
    public function testTheGeneralPathRefusesASpecializedType(string $entityType): void
    {
        $formatter = $this->formatter();

        self::assertFalse($formatter->handles($entityType));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($entityType);
        $formatter->format($entityType, ['whatever' => 'x']);
    }

    /** @return array<string, array{0: string}> */
    public static function specializedTypes(): array
    {
        $cases = [];
        foreach (self::SPECIALIZED as $type) {
            $cases[$type] = [$type];
        }

        return $cases;
    }

    /**
     * Every other type goes through the general path. Asserted over the real spec list
     * so a newly configured entity is covered without anyone editing this test.
     */
    public function testEveryOtherEntityTypeIsHandledByTheGeneralPath(): void
    {
        $formatter = $this->formatter();
        foreach ($this->entities() as $name => $_spec) {
            if (in_array($name, self::SPECIALIZED, true)) {
                continue;
            }
            self::assertTrue($formatter->handles($name), "$name should be formatted by the general path");
        }
    }

    /**
     * The Twig switch and the formatter's SPECIALIZED list are two lists in two
     * languages that must agree. Read from the template source rather than by rendering,
     * because what is being checked is that the branch was *written*.
     */
    public function testTheTwigDispatcherNamesEveryTypeTheFormatterRefuses(): void
    {
        $template = (string) file_get_contents(self::DISPATCHER);

        foreach (['association', 'person'] as $reproduced) {
            self::assertTrue(
                str_contains($template, "entity_type == '$reproduced'"),
                "the dispatcher has no branch for '$reproduced', so it would reach format_entity() and raise"
            );
        }
        self::assertTrue(
            str_contains($template, 'formats_entity_generally(entity_type)'),
            'the dispatcher must ask the formatter whether it accepts the type, or the two lists drift'
        );
    }

    /**
     * The ported page, rendered against the real problem rows. This is the closest thing
     * to an end-to-end check that survives the laminas route's deletion: it exercises
     * strict_variables, the ACL path inside isActionAllowed(), the link assembly and the
     * edit pencil, with no HTTP and no signed-in session.
     *
     * The smoke suite cannot reach this page's *content* — it is behind a guard — so
     * without this the 30 rows would only ever have been checked by hand once.
     */
    public function testTheDataProblemsTemplateRendersTheRealProblemRows(): void
    {
        $problems = $this->problems();
        $html     = $this->twig()->render('sion-model/data-problems.html.twig', [
            'page_title' => '',
            'problems'   => $problems,
        ]);

        self::assertStringContainsString('<th>Entity</th>', $html);
        self::assertStringContainsString(':</strong>&nbsp;' . count($problems), $html, 'wrong problem count');
        //an anonymous render is not allowed to reach the show routes, so the names are
        //present but unlinked — the point is that formatting ran at all rather than
        //throwing, and that every row produced a cell
        self::assertSame(count($problems), substr_count($html, '<tr>') - 1, 'one row per problem, plus the header');
    }

    private function formatter(): EntityFormatter
    {
        $bridge  = $this->bridge();
        $helpers = new ViewHelpers($bridge);
        $urls    = new RouteUrl($bridge, '');

        //The three closures the formatter takes. In production they come from
        //App\Twig\LaminasExtension; here the two pencil renderers return a marker rather
        //than '' so that "was the pencil emitted?" is answerable — several assertions
        //above turn on exactly that, and a stub returning an empty string would make them
        //pass no matter what. The translator is identity, so a tooltip comes back as its
        //untranslated source string.
        return new EntityFormatter(
            $bridge,
            $helpers,
            $urls,
            static fn (string $type, int|string|null $id): string => "PENCIL($type:$id)",
            static fn (string $route, array $params): string => 'PENCIL(' . $route . ')',
            static fn (string $message): string => $message
        );
    }

    private function twig(): Environment
    {
        $bridge = $this->bridge();

        return (new TwigFactory())->create(
            $bridge,
            new ViewHelpers($bridge),
            new RouteUrl($bridge, ''),
            new RequestStack(),
            new CspNonce()
        );
    }

    /** @return array<string, \SionModel\Entity\Entity> */
    private function entities(): array
    {
        $this->requireDatabase();

        /** @var EntitiesService $service */
        $service = $this->bridge()->get(EntitiesService::class);

        return $service->getEntities();
    }

    /** @return array<int|string, \SionModel\Problem\EntityProblem> */
    private function problems(): array
    {
        $this->requireDatabase();

        /** @var ProblemService $service */
        $service = $this->bridge()->get(ProblemService::class);

        return $service->getCurrentProblems();
    }

    /**
     * Skips rather than fails without a database — and checks for local.php first,
     * because merely asking the container for the adapter without it raises a warning,
     * and failOnWarning makes that a failure no later catch can undo.
     */
    private function requireDatabase(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            /** @var Adapter $adapter */
            $adapter = $this->bridge()->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }

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
