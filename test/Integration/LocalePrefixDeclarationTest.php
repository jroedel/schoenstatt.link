<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Http\LocalePrefix;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouteCollection;

use function preg_match_all;
use function sort;
use function str_ends_with;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Structural guard over which Symfony-served routes answer their own *unprefixed* path
 * and which take SlmLocale's 302 to the prefixed one.
 *
 * `$ported()` defaults to redirecting, because that is what every ported HTML page wants
 * and what all forty-two controllers that used to hand-write the hop did. A default is
 * the right shape here — the alternative was sixty-four hand-written declarations, of
 * which sixty would have said the same thing — but a default is also exactly how a *new*
 * machine endpoint would acquire a redirect nobody intended: declare a JSON route with
 * `$ported()`, omit the argument, and its caller starts following a 302 to a language.
 *
 * So the opt-out set is pinned by name below rather than merely being available. Adding a
 * route to it is a deliberate line in a test; acquiring it by accident is a failure.
 *
 * The reverse direction matters just as much and is the same assertion read backwards: a
 * route dropping *out* of this list has started redirecting, which for `sm-cache-status`
 * or `sm-clear-persistent-cache` means the deploy's own cache gate is now following a
 * redirect it was never written to follow.
 *
 * Nothing here needs a database, a session or a running app: it reads the route
 * collection as a data structure, which is also why it survives a bare CI runner.
 */
class LocalePrefixDeclarationTest extends TestCase
{
    /**
     * Every route that does *not* redirect its unprefixed form, and why.
     *
     * Four carry an explicit `LocalePrefix::servedHere()`; the rest are declared with
     * `$routes->add()` rather than `$ported()`, so they have no locale-prefixed twin to
     * be sent to and no declaration at all. Both kinds belong here, because what this
     * pins is the *behaviour*, and a reader asking "does /foo redirect" should not have
     * to know which helper declared it.
     *
     * @return array<string, string>
     */
    private const NO_REDIRECT = [
        // Declared: machine endpoints the deploy and the monitor call at the bare path.
        'sm-cache-status'                       => 'deploy hook and production monitor call it directly',
        'sm-cache-status.locale'                => 'the prefixed twin of the above',
        'sm-clear-persistent-cache'             => 'deploy hook calls it directly',
        'sm-clear-persistent-cache.locale'      => 'the prefixed twin of the above',
        // Declared: a file Apache serves, whose content is all five languages at once.
        'sitemap'                               => 'a sitemap has no locale; Apache serves the file',
        'sitemap.locale'                        => 'the prefixed twin of the above',
        // Declared: the one library page that is not a page.
        'libraries/library/book-list-json'      => 'fetched as JSON by the admin page JavaScript',
        'libraries/library/book-list-json.locale' => 'the prefixed twin of the above',
        // Undeclared: POST-only, so a 302 would discard the body.
        'comments/create'                       => 'POST only; a 302 drops the submission',
        'comments/create.locale'                => 'POST only; a 302 drops the submission',
        // Undeclared: no prefixed form exists.
        'health'                                => 'shadows no laminas route and has no locale form',
        // Undeclared: the bridge. laminas-mvc and SlmLocale do their own redirecting.
        'legacy'                                => 'bridged to laminas-mvc, which runs SlmLocale itself',
        // Undeclared: reached from an emailed link carrying a token in the query.
        'library/my-books'                      => 'borrower self-service, authorised by an emailed token',
        // Undeclared: the v3 API. A program does not follow a redirect to a language.
        'api-v3/association'                    => 'JSON API',
        'api-v3/association-method'             => 'JSON API',
        'api-v3/association-patch'              => 'JSON API',
        'api-v3/associations'                   => 'JSON API',
        'api-v3/associations-method'            => 'JSON API',
        'api-v3/phrase'                         => 'JSON API',
        'api-v3/phrase-history'                 => 'JSON API',
        'api-v3/phrase-history-method'          => 'JSON API',
        'api-v3/phrase-method'                  => 'JSON API',
        'api-v3/phrase-patch'                   => 'JSON API',
        'api-v3/phrase-retire'                  => 'JSON API',
        'api-v3/phrase-retire-method'           => 'JSON API',
        'api-v3/phrase-unretire'                => 'JSON API',
        'api-v3/phrase-unretire-method'         => 'JSON API',
        'api-v3/phrases'                        => 'JSON API',
        'api-v3/phrases-method'                 => 'JSON API',
        'api-v3/phrases-patch'                  => 'JSON API',
        'api-v3/schema'                         => 'JSON API',
        'api-v3/schema-entity'                  => 'JSON API',
        'api-v3/schema-entity-method'           => 'JSON API',
        'api-v3/schema-method'                  => 'JSON API',
        // Undeclared: the /api refusal, ported off LegacyBridge 2026-09-08. Like the v3
        // API it answers its own path rather than redirecting to a language — a machine
        // caller has no Accept-Language preference worth a hop. The prefixed twins exist
        // only because crawlers indexed /{locale}/api/v1/… and those must still 410.
        'api-not-found'                         => 'JSON refusal for unmatched /api paths',
        'api-not-found.locale'                  => 'the prefixed twin of the above',
        'api-not-found/rest'                    => 'JSON refusal for unmatched /api paths',
        'api-not-found/rest.locale'             => 'the prefixed twin of the above',
    ];

    public function testTheSetOfRoutesThatDoNotRedirectIsExactlyTheDeclaredOne(): void
    {
        $actual = [];
        foreach ($this->collection() as $name => $route) {
            $declaration = $route->getDefault(LocalePrefix::ATTRIBUTE);
            if (! $declaration instanceof LocalePrefix || ! $declaration->redirects()) {
                $actual[] = (string) $name;
            }
        }

        $expected = array_keys(self::NO_REDIRECT);
        sort($expected);
        sort($actual);

        $this->assertSame(
            $expected,
            $actual,
            'A route has started or stopped answering its own unprefixed path. Adding one to '
            . 'LocalePrefixDeclarationTest::NO_REDIRECT is a deliberate act; arriving there by '
            . 'omitting the $ported() argument is the mistake this asserts against.'
        );
    }

    /**
     * An opt-out states its reason, the way RouteAccess::openToEveryone() does. The four
     * explicit ones are the only routes this can check: a route with no declaration at
     * all has nowhere to carry one, and its reason lives in the array above instead.
     */
    public function testEveryExplicitOptOutCarriesAReason(): void
    {
        $checked = 0;
        foreach ($this->collection() as $name => $route) {
            $declaration = $route->getDefault(LocalePrefix::ATTRIBUTE);
            if (! $declaration instanceof LocalePrefix || $declaration->redirects()) {
                continue;
            }

            $this->assertNotSame('', (string) $declaration->reason, "$name declares no reason");
            $checked++;
        }

        $this->assertSame(8, $checked, 'four routes and their locale twins opt out explicitly');
    }

    /**
     * The redirect target is assembled from the placeholders `$ported()` read off the
     * path, so those have to *be* the path's placeholders — a mismatch is a 302 to a URL
     * that 404s, and nothing about it is visible until somebody follows the link.
     *
     * Checked on the unprefixed twin, which is the only one the listener ever acts on.
     */
    #[DataProvider('redirectingRoutes')]
    public function testDerivedParamsAreThePathsOwnPlaceholders(string $name, string $path, array $params): void
    {
        preg_match_all('/\{(\w+)\}/', $path, $matches);

        $this->assertSame($matches[1], $params, "$name derived the wrong placeholders from $path");
    }

    /** @return iterable<string, array{string, string, list<string>}> */
    public static function redirectingRoutes(): iterable
    {
        //Deliberately not reading the container: a data provider that touches it runs
        //before the test framework is ready. See docs/BACKLOG.md on bare CI runners.
        /** @var RouteCollection $collection */
        $collection = require __DIR__ . '/../../config/symfony/routes.php';

        foreach ($collection as $name => $route) {
            $declaration = $route->getDefault(LocalePrefix::ATTRIBUTE);
            if (! $declaration instanceof LocalePrefix || ! $declaration->redirects()) {
                continue;
            }
            //the prefixed twin carries the same declaration and one extra placeholder it
            //is not responsible for
            if (str_ends_with((string) $name, '.locale')) {
                continue;
            }

            yield (string) $name => [(string) $name, $route->getPath(), $declaration->params];
        }
    }

    private function collection(): RouteCollection
    {
        /** @var RouteCollection $collection */
        $collection = require __DIR__ . '/../../config/symfony/routes.php';

        return $collection;
    }
}
