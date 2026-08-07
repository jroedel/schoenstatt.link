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
 *  2. **The specialized types are refused, loudly.** person, association, role and
 *     publication are formatted by other helpers in laminas. Two are reproduced as Twig
 *     macros; two are not reproduced at all. Formatting any of them by the general
 *     rules would produce plausible, wrong markup, so `format()` raises.
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
     * named blog-post, text and composition — which is exactly what it is for. That
     * branch is now reproduced in both wrapAsLink() and the edit pencil; these two are
     * not, and no spec sets either.
     */
    private const UNREPRODUCED_BRANCHES = ['showRouteParams', 'editRouteParams'];

    /** Types laminas formats with a helper other than SionModel's general path. */
    private const SPECIALIZED = ['person', 'association', 'role', 'publication'];

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
     * it. blog-post, text and composition carry no showRouteKey, so their link can only
     * come from `defaultRouteParams` — if that branch regressed, the name would render as
     * bare text and no other assertion here would see it.
     *
     * **Both directions, because the link is ACL-dependent** and this test runs with no
     * identity. `route/blog/blog-post` and `route/composition` admit an anonymous visitor;
     * `route/text` does not (measured in docs/acl-rules.md). So the expected outcome is
     * read from the ACL rather than assumed — which makes this a test of isActionAllowed()
     * as well: a formatter that ignored permissions would link `text` and fail here.
     */
    public function testTheDefaultRouteParamsBranchLinksTheSpecsThatNeedIt(): void
    {
        $formatter = $this->formatter();
        $entities  = $this->entities();

        foreach (['blog-post', 'text', 'composition'] as $type) {
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

    /** Each specialized type is refused rather than formatted by the wrong rules. */
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

        //the two closures the formatter takes: in production they come from
        //App\Twig\LaminasExtension, and here they are only reached by tests that render
        return new EntityFormatter(
            $bridge,
            $helpers,
            $urls,
            static fn (string $type, int|string|null $id): string => '',
            static fn (string $route, array $params): string => '',
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
