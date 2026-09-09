<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Http\SymfonyRoutes;
use App\Laminas\ContainerFactory;
use Laminas\Router\RouteStackInterface;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Throwable;

use function count;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_ends_with;
use function substr;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Every URL the Symfony generator produces is byte-identical to the one the laminas router
 * produced for the same route and parameters.
 *
 * ## Why this test is the whole safety net for step 6
 *
 * `App\Laminas\RouteUrl::path()` is the single seam every link on the site comes through —
 * 150 PHP call sites and 47 template calls, measured 2026-09-09. Step 6 replaces its engine
 * (`TreeRouteStack::assemble()` → `Symfony\...\UrlGenerator`) without touching one of those
 * call sites, which is only safe if the two engines agree on every route. So this compares
 * them directly rather than checking a sample of rendered pages.
 *
 * The comparison is generated from the route collection itself, not from a hand-written
 * list: a route added later is compared automatically, and a route whose Symfony and
 * laminas declarations drift apart fails here rather than on the page that links to it.
 *
 * ## The mapping being asserted
 *
 * laminas produced the locale prefix by setting one base URL on the router. Symfony
 * declares it as a **separate route** — `shrines` serves `/shrines`, `shrines.locale`
 * serves `/{_locale}/shrines` — so `path('shrines')` must resolve to `shrines.locale`.
 * That rule is what this pins.
 *
 * Routes the laminas router does not know are skipped: some Symfony routes have no laminas
 * counterpart (the `/api` refusals, the catch-all). {@see testEnoughRoutesWereCompared}
 * keeps that from quietly becoming "skip everything".
 */
class SymfonyUrlParityTest extends TestCase
{
    private const LOCALES = ['en', 'es'];

    /**
     * Candidate placeholder values, tried in order against each parameter's own
     * requirement regex.
     *
     * A single value per name cannot work: `sw_id` is a different pattern on every entity
     * route (`SL1…A` for an association, `SL2…L` for a publication, `SL4…T`, `SL5…C`), so
     * the sample has to be chosen per route rather than per parameter name. Picking the
     * first candidate the requirement accepts keeps this list short and means a route that
     * tightens its regex fails loudly here instead of being silently skipped.
     */
    private const CANDIDATES = [
        '1',
        '42',
        'SL110000A',
        'SL210000L',
        'SL410000T',
        'SL510000C',
        'SL100000A',
        'SL10000A',
        'SL20000L',
        'SL40000T',
        'SL50000C',
        'es',
        'en',
        'comment',
        'current',
    ];

    private static ?ServiceManager $services = null;

    /** @var array{compared: int, skipped: int} */
    private static array $tally = ['compared' => 0, 'skipped' => 0];

    public function testGeneratedUrlsMatchTheLaminasRouter(): void
    {
        $routes    = SymfonyRoutes::collection();
        $laminas   = $this->router();
        $mismatch  = [];

        foreach (self::LOCALES as $locale) {
            $generator = new UrlGenerator($routes, new RequestContext());

            foreach ($routes->all() as $name => $route) {
                if (! str_ends_with($name, '.locale')) {
                    continue;
                }
                $laminasName = substr($name, 0, -strlen('.locale'));

                $params = $this->sampleParams($route);
                unset($params['_locale']);

                try {
                    $expected = $laminas->assemble(
                        $params,
                        ['name' => $laminasName]
                    );
                } catch (Throwable) {
                    //no laminas counterpart, or it needs parameters this cannot guess
                    self::$tally['skipped']++;
                    continue;
                }

                try {
                    $actual = $generator->generate($name, $params + ['_locale' => $locale]);
                } catch (Throwable $e) {
                    $mismatch[] = sprintf('%s [%s]: symfony threw %s', $name, $locale, $e->getMessage());
                    continue;
                }

                //laminas assembles without the prefix here (the base URL is not set on this
                //bare router), so compare against the prefixed expectation explicitly
                $expected = '/' . $locale . ($expected === '/' ? '/' : $expected);
                self::$tally['compared']++;

                if ($expected !== $actual) {
                    $mismatch[] = sprintf('%s [%s]: laminas %s, symfony %s', $name, $locale, $expected, $actual);
                }
            }
        }

        self::assertSame([], $mismatch, sprintf(
            "%d route/locale pairs disagree (compared %d, skipped %d):\n%s",
            count($mismatch),
            self::$tally['compared'],
            self::$tally['skipped'],
            implode("\n", array_slice($mismatch, 0, 25))
        ));
    }

    /** @depends testGeneratedUrlsMatchTheLaminasRouter */
    public function testEnoughRoutesWereCompared(): void
    {
        self::assertGreaterThan(100, self::$tally['compared'], sprintf(
            'only %d comparisons ran (%d skipped) — the parity check has gone hollow',
            self::$tally['compared'],
            self::$tally['skipped']
        ));
    }

    /**
     * @param \Symfony\Component\Routing\Route $route
     * @return array<string, string>
     */
    private function sampleParams($route): array
    {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $route->getPath(), $m);
        $params = [];
        foreach ($m[1] as $name) {
            $params[$name] = $this->sampleFor($route->getRequirement($name));
        }

        return $params;
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

    private function router(): RouteStackInterface
    {
        /** @var RouteStackInterface $router */
        $router = self::services()->get('Router');

        return $router;
    }

    private static function services(): ServiceManager
    {
        if (null !== self::$services) {
            return self::$services;
        }

        /** @var array<string, mixed> $appConfig */
        $appConfig = require __DIR__ . '/../../config/application.config.php';

        return self::$services = ContainerFactory::build($appConfig);
    }
}
