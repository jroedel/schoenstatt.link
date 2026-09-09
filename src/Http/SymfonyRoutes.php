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
