<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\Routing\RouteCollection;

use function dirname;

/**
 * The application's Symfony route collection, built once per process.
 *
 * `config/symfony/routes.php` declares 256 routes and is a plain `require`, so building it
 * is not free and doing it twice produces two collections that are equal but not identical.
 * Both things that need it — {@see \App\Kernel} for matching and
 * {@see \App\Laminas\RouteUrl} for generation — come through here, which is what makes
 * "the routes Symfony matches" and "the routes we generate links for" the same object by
 * construction rather than by agreement.
 *
 * Memoized in a static because the collection is immutable once built and a request builds
 * it at most once; `test/Integration/ReservedVerbsTest` still `require`s the file directly,
 * which is unaffected.
 */
final class SymfonyRoutes
{
    /**
     * Symfony route name → the name the **ACL** knows, where the two differ.
     *
     * Two pages were renamed when they were ported, and the ACL still keys on the laminas
     * names: `route/sion-model/cache-status` is a resource, `route/sm-cache-status` is not.
     * That matters wherever a route name is turned into an ACL question —
     * `Access::userMayReachRoute()` **denies when the resource is missing**, so answering
     * with Symfony's name would tell a signed-in user they may not reach a page they can.
     *
     * It lives here rather than in either caller because both
     * {@see \App\JUser\Host\RouteResolver} and `tools/acl-table.php` need it and a second
     * copy is a second thing to keep in step. `test/Integration/RouteMatchParityTest`
     * asserts the mapping is still required — the day both names become ACL resources this
     * is dead weight and should go.
     */
    public const ACL_NAME = [
        'sm-cache-status'           => 'sion-model/cache-status',
        'sm-clear-persistent-cache' => 'sion-model/clear-persistent-cache',
    ];

    private static ?RouteCollection $collection = null;

    public static function collection(): RouteCollection
    {
        if (null === self::$collection) {
            /** @var RouteCollection $routes */
            $routes           = require dirname(__DIR__, 2) . '/config/symfony/routes.php';
            self::$collection = $routes;
        }

        return self::$collection;
    }
}
