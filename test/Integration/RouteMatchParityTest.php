<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Acl\AclProvider;
use App\Http\SymfonyRoutes;
use App\JUser\Host\RouteResolver;
use App\Laminas\ContainerFactory;
use App\Laminas\ContainerServices;
use Laminas\Http\Request as LaminasRequest;
use Laminas\Router\RouteMatch;
use Laminas\Router\RouteStackInterface;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Throwable;

use function count;
use function implode;
use function in_array;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_ends_with;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `App\JUser\Host\RouteResolver` answers the same route name the laminas router did.
 *
 * ## What depends on the answer
 *
 * Two things, and only one of them cares about the *name*. `JUser\Page\RedirectTarget::valid()`
 * asks only whether a `?redirect=` path resolves to anything at all; `refusedRoute()` passes
 * the name to `Access::userMayReachRoute()`, which looks up the ACL resource `route/<name>`
 * and **denies when it does not exist**. So a name that changed would not fail loudly — it
 * would tell a signed-in user they may not reach a page they can.
 *
 * ## The three known differences, all checked rather than assumed
 *
 * `sm-cache-status` and `sm-clear-persistent-cache` are named differently by the two routers
 * (laminas calls them `sion-model/…`). That is inert here: **neither name is an ACL resource**
 * — both routes are `openToEveryone` behind a maintenance key — so `userMayReachRoute()`
 * denies identically either way. Asserted below rather than left as a note, because the day
 * one of them becomes ACL-guarded the divergence stops being inert.
 *
 * `comments/create` is POST-only. Symfony declines it for a GET; laminas named it, having no
 * method constraint to consult. A browser cannot be redirected to a POST endpoint, so
 * refusing it as a destination is the better answer, and it is asserted so that it is a
 * decision rather than a surprise.
 */
class RouteMatchParityTest extends TestCase
{
    /** Tried in order against each parameter's own requirement. */
    private const CANDIDATES = [
        '1', '42',
        'SL110000A', 'SL210000L', 'SL410000T', 'SL510000C',
        'SL10000A', 'SL20000L', 'SL40000T', 'SL50000C',
        'es', 'en', 'comment', 'current',
    ];

    /**
     * Names the two routers spell differently. The resolver maps these back to the name the
     * ACL knows, so parity below is exact; this list exists to assert *why* that mapping is
     * required — see {@see testTheRenamedRoutesAreOnlyAclAddressableUnderTheLaminasName}.
     */
    private const KNOWN_RENAMES = [
        'sm-cache-status'            => 'sion-model/cache-status',
        'sm-clear-persistent-cache'  => 'sion-model/clear-persistent-cache',
    ];

    /** Matched by laminas, deliberately not a destination here. */
    private const KNOWN_REFUSALS = ['comments/create'];

    private static ?ServiceManager $services = null;

    public function testTheResolverAgreesWithTheLaminasRouter(): void
    {
        $routes    = SymfonyRoutes::collection();
        $generator = new UrlGenerator($routes, new RequestContext());
        $laminas   = clone $this->laminasRouter();
        $laminas->setBaseUrl('');
        $resolver  = new RouteResolver();

        $mismatch = [];
        $compared = 0;

        foreach ($routes->all() as $name => $route) {
            if (str_ends_with($name, '.locale') || 'not-found' === $name) {
                continue;
            }

            $params = [];
            preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $route->getPath(), $m);
            foreach ($m[1] as $p) {
                $params[$p] = $this->sampleFor($route->getRequirement($p));
            }

            try {
                $path = $generator->generate($name, $params);
            } catch (Throwable) {
                continue;
            }

            $expected = $this->laminasName($laminas, $path);
            if (null === $expected) {
                //no laminas counterpart; nothing to compare against
                continue;
            }

            $compared++;
            $actual = $resolver->routeFor($path);

            if (in_array($expected, self::KNOWN_REFUSALS, true)) {
                if (null !== $actual) {
                    $mismatch[] = sprintf('%s: expected refusal, got %s', $path, $actual);
                }
                continue;
            }

            if ($expected !== $actual) {
                $mismatch[] = sprintf('%s: laminas %s, resolver %s', $path, $expected, $actual ?? 'NULL');
            }
        }

        self::assertSame([], $mismatch, sprintf(
            "%d of %d paths resolve differently:\n%s",
            count($mismatch),
            $compared,
            implode("\n", $mismatch)
        ));
        self::assertGreaterThan(80, $compared, 'the corpus has gone hollow');
    }

    /**
     * The renames stay inert only while neither name is an ACL resource. If one becomes
     * guarded, `Access::userMayReachRoute()` starts answering differently for the same page
     * and this must be revisited — so it fails here first.
     */
    /**
     * The reason RouteResolver::ACL_NAME has to exist: for these two pages the ACL knows the
     * laminas name and not the Symfony one, so returning Symfony's would deny a user a page
     * they can reach. If that ever stops being true the mapping is dead weight and should go.
     */
    public function testTheRenamedRoutesAreOnlyAclAddressableUnderTheLaminasName(): void
    {
        $authorizer = (new AclProvider(new ContainerServices($this->services())))->authorizer();

        foreach (self::KNOWN_RENAMES as $symfonyName => $laminasName) {
            self::assertTrue(
                $authorizer->hasResource('route/' . $laminasName),
                sprintf('route/%s is no longer an ACL resource', $laminasName)
            );
            self::assertFalse(
                $authorizer->hasResource('route/' . $symfonyName),
                sprintf(
                    'route/%s is now an ACL resource too, so RouteResolver no longer needs to '
                    . 'rewrite it; drop the entry from ACL_NAME.',
                    $symfonyName
                )
            );
        }
    }

    /**
     * A `?redirect=` almost always carries a query — that is the point of returning someone
     * to a search — and the generated corpus above has none, so it did not catch this.
     *
     * The laminas router was handed a `Laminas\Http\Request` and parsed the query off
     * itself. Symfony's matcher matches the string it is given, so passing the whole URI
     * refused every destination with a query string. Two sign-in smoke tests failed; this
     * asserts it directly, where the cause is legible.
     */
    public function testAPathCarryingAQueryStringStillResolves(): void
    {
        $resolver = new RouteResolver();

        self::assertSame(
            'assignments/search',
            $resolver->routeFor('/en/assignments/search?search=Walter'),
            'a redirect target carrying a query must still resolve to its route'
        );
        self::assertSame(
            'assignments/search',
            $resolver->routeFor('/assignments/search?search=Walter&page=2')
        );
        self::assertSame('shrines', $resolver->routeFor('/en/shrines'));
    }

    public function testTheCatchAllIsNeverADestination(): void
    {
        $resolver = new RouteResolver();

        self::assertNull(
            $resolver->routeFor('/no/such/path/anywhere'),
            'the Symfony catch-all matches every path; it must not be reported as a destination'
        );
        self::assertNull($resolver->routeFor('/api/v1/whatever'));
    }

    private function laminasName(RouteStackInterface $router, string $path): ?string
    {
        try {
            $request = new LaminasRequest();
            $request->setUri($path);
            $match = $router->match($request);
        } catch (Throwable) {
            return null;
        }

        if (! $match instanceof RouteMatch) {
            return null;
        }
        $name = $match->getMatchedRouteName();

        return '' !== (string) $name ? (string) $name : null;
    }

    private function sampleFor(?string $requirement): string
    {
        if (null === $requirement || '' === $requirement) {
            return '1';
        }
        foreach (self::CANDIDATES as $candidate) {
            if (1 === preg_match('{^(?:' . $requirement . ')$}', $candidate)) {
                return $candidate;
            }
        }

        return '1';
    }

    private function laminasRouter(): RouteStackInterface
    {
        /** @var RouteStackInterface $router */
        $router = $this->services()->get('Router');

        return $router;
    }

    private function services(): ServiceManager
    {
        if (null !== self::$services) {
            return self::$services;
        }

        /** @var array<string, mixed> $appConfig */
        $appConfig = require __DIR__ . '/../../config/application.config.php';

        return self::$services = ContainerFactory::build($appConfig);
    }
}
