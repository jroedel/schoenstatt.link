<?php

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\TestCase;
use RestApi\Controller\ApiController;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Structural guard over the API's authorization flag.
 *
 * 'isAuthorizationRequired' => true in a route's defaults is read by exactly one
 * thing: RestApi\Controller\ApiController::checkAuthorization(), a dispatch
 * listener that the base class attaches to itself in setEventManager(). A
 * controller that does not descend from ApiController therefore ignores the flag
 * entirely — the route config asks for a JWT and the request is served anyway,
 * with no error anywhere to notice.
 *
 * That is not hypothetical: Books\Controller\LibrariesApiController extended
 * AbstractRestfulController directly and served /api/v1/libraries/:id and
 * .../pending-labels to anonymous callers until 2026-08-03, and the bjyauthorize
 * guards whitelist api-v1/* for 'guest' precisely because the JWT gate was meant
 * to be the check. A smoke test can only cover the routes someone thought to
 * list; this walks the route configuration itself, so a new flagged route on a
 * non-enforcing controller fails here the day it is added.
 *
 * Needs vendor/ (composer's PSR-4 map, plus the ::class constants the module
 * configs reference), so it lives in the integration suite: php composer.phar
 * integration
 */
class ApiAuthorizationFlagTest extends TestCase
{
    public function testEveryRouteRequiringAuthorizationIsOnAnEnforcingController(): void
    {
        $flagged = $this->routesRequiringAuthorization();

        $this->assertNotEmpty(
            $flagged,
            'no route declares isAuthorizationRequired => true — was the flag renamed, or the config moved?'
        );

        foreach ($flagged as $route => $controller) {
            $this->assertNotNull(
                $controller,
                sprintf(
                    "route '%s' requires authorization but names no controller; if it inherits one from a "
                    . 'parent route in another module, this walk cannot see it and needs extending',
                    $route
                )
            );
            $this->assertTrue(
                class_exists($controller),
                sprintf("route '%s' names a controller class that does not exist: %s", $route, $controller)
            );
            $this->assertTrue(
                is_a($controller, ApiController::class, true),
                sprintf(
                    "route '%s' declares isAuthorizationRequired => true, but %s does not extend %s, "
                    . 'so nothing enforces it and the route is open to anonymous callers',
                    $route,
                    $controller,
                    ApiController::class
                )
            );
        }
    }

    /**
     * The routes known to be gated today. Guards against a fix that quietly
     * drops the flag instead of honouring it.
     */
    public function testTheKnownGatedRoutesAreStillGated(): void
    {
        $flagged = $this->routesRequiringAuthorization();

        foreach (
            [
                'api-v1/libraries',
                'api-v1/libraries/books',
                'api-v1/pending-labels',
                'api-v1/dictionary',
                'api-v1/slugify-terms',
            ] as $route
        ) {
            $this->assertArrayHasKey($route, $flagged, sprintf("route '%s' no longer requires a JWT", $route));
        }
    }

    /**
     * Walk every module's route tree and return the routes that end up with
     * isAuthorizationRequired === true, mapped to the controller that would
     * serve them. Both values are inherited by child routes, as Laminas merges
     * a parent's defaults into its children's route match.
     *
     * @return array<string, string|null> route name => controller class
     */
    private function routesRequiringAuthorization(): array
    {
        $found = [];
        foreach (glob(__DIR__ . '/../../module/*/config/module.config.php') ?: [] as $configFile) {
            $config = include $configFile;
            if (! is_array($config) || ! isset($config['router']['routes'])) {
                continue;
            }
            $this->collect($config['router']['routes'], '', null, null, $found);
        }
        return $found;
    }

    /**
     * @param array<string, mixed> $routes
     * @param array<string, string|null> $found
     */
    private function collect(
        array $routes,
        string $prefix,
        ?string $controller,
        ?bool $required,
        array &$found
    ): void {
        foreach ($routes as $name => $route) {
            if (! is_array($route)) {
                continue;
            }
            $defaults = $route['options']['defaults'] ?? [];
            $inheritedController = isset($defaults['controller']) ? (string) $defaults['controller'] : $controller;
            $inheritedRequired = array_key_exists('isAuthorizationRequired', $defaults)
                ? (bool) $defaults['isAuthorizationRequired']
                : $required;
            $fullName = '' === $prefix ? (string) $name : $prefix . '/' . $name;

            if (true === $inheritedRequired) {
                $found[$fullName] = $inheritedController;
            }
            if (isset($route['child_routes']) && is_array($route['child_routes'])) {
                $this->collect($route['child_routes'], $fullName, $inheritedController, $inheritedRequired, $found);
            }
        }
    }
}
