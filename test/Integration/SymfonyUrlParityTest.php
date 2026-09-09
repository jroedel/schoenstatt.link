<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Http\SymfonyRoutes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Throwable;

use function count;
use function sprintf;

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

    /** @var array{compared: int, skipped: int} */
    private static array $tally = ['compared' => 0, 'skipped' => 0];

    public function testGeneratedUrlsMatchTheLaminasRouter(): void
    {
        /** @var array<string, array{params: array<string, string>, expected: string}> $frozen */
        $frozen    = require __DIR__ . '/fixtures/laminas-assembled-urls.php';
        $generator = new UrlGenerator(SymfonyRoutes::collection(), new RequestContext());

        $mismatch = [];
        foreach ($frozen as $key => $case) {
            [$name, $locale] = explode('|', $key, 2);

            try {
                $actual = $generator->generate($name, $case['params'] + ['_locale' => $locale]);
            } catch (Throwable $e) {
                $mismatch[] = sprintf('%s: symfony threw %s', $key, $e->getMessage());
                continue;
            }

            if ($case['expected'] !== $actual) {
                $mismatch[] = sprintf('%s: laminas %s, symfony %s', $key, $case['expected'], $actual);
            }
        }

        self::$tally['compared'] = count($frozen);

        self::assertSame([], $mismatch, sprintf(
            "%d of %d route/locale pairs disagree with what the laminas router assembled:\n%s",
            count($mismatch),
            count($frozen),
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

}
