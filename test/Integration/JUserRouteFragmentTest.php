<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use JUser\Controller\ApiTokensController;
use JUser\Controller\LogoutController;
use JUser\Controller\SignInController;
use JUser\Controller\UserCreateController;
use JUser\Controller\UserDeleteController;
use JUser\Controller\UserEditController;
use JUser\Controller\UsersController;
use JUser\Controller\VerifyController;
use JUser\Routing\RouteAudience;
use JUser\Routing\Routes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `module/JUser/config/symfony-routes.php` — the eleven routes the module asks a host to
 * declare, and the four facts about them that nothing else checks.
 *
 * The fragment is a closure that calls back into the host's own route registration rather
 * than a `RouteCollection`, so it has no shape a type can pin: what it declares is only
 * visible by *running* it. This drives it with a recording callback, which is also the
 * cheapest possible demonstration of how a host is meant to consume it.
 *
 * What each test is for:
 *
 * - **the audiences**, because `Anyone` and `SignedIn` are one line apart here and the
 *   difference between them is whether signing out is a public URL. Of the site's 191
 *   routes, 149 restrict access, and a route ported without its guard published itself
 *   while continuing to look perfectly healthy — that is the hazard this file exists to
 *   keep out of the module.
 * - **the constraints**, because `/users/roles/create` and `/users/{user_id}/api-tokens`
 *   are both three segments and only `[0-9]{1,5}` keeps `roles` out of `user_id`.
 * - **the names**, because a route name is what a host's guard entry addresses. A literal
 *   in the fragment that drifts from `JUser\Routing\Routes` is a dead link on one side and
 *   an unguarded page on the other.
 * - **the create defaults**, because one controller serves two routes and the default is
 *   the only thing telling it which record it is making. Without it the controller throws.
 */
final class JUserRouteFragmentTest extends TestCase
{
    /** @return list<array{string, string, string|array<int, string>, RouteAudience, array<string, mixed>, array<string, string>}> */
    private function declared(): array
    {
        $fragment = require __DIR__ . '/../../module/JUser/config/symfony-routes.php';

        $declared = [];
        $fragment(function (
            string $name,
            string $path,
            string|array $controller,
            RouteAudience $audience,
            array $defaults = [],
            array $requirements = []
        ) use (&$declared): void {
            $declared[] = [$name, $path, $controller, $audience, $defaults, $requirements];
        });

        return $declared;
    }

    public function testItDeclaresElevenRoutesInOrder(): void
    {
        $names = array_column($this->declared(), 0);

        $this->assertSame([
            Routes::INDEX,
            Routes::LOGIN,
            Routes::VERIFY,
            Routes::LOGOUT,
            Routes::USERS,
            Routes::USER_CREATE,
            Routes::ROLE_CREATE,
            Routes::USER_EDIT,
            Routes::USER_DELETE,
            Routes::API_TOKENS,
            Routes::API_TOKEN_REVOKE,
        ], $names);
    }

    public function testEveryPathAndControllerIsWhatItShouldBe(): void
    {
        $byName = [];
        foreach ($this->declared() as [$name, $path, $controller]) {
            $byName[$name] = [$path, $controller];
        }

        $this->assertSame(['/user', [SignInController::class, 'index']], $byName[Routes::INDEX]);
        $this->assertSame(['/user/login', [SignInController::class, 'form']], $byName[Routes::LOGIN]);
        $this->assertSame(['/user/verify', VerifyController::class], $byName[Routes::VERIFY]);
        $this->assertSame(['/user/logout', LogoutController::class], $byName[Routes::LOGOUT]);
        $this->assertSame(['/users', UsersController::class], $byName[Routes::USERS]);
        $this->assertSame(['/users/create', UserCreateController::class], $byName[Routes::USER_CREATE]);
        $this->assertSame(['/users/roles/create', UserCreateController::class], $byName[Routes::ROLE_CREATE]);
        $this->assertSame(['/users/{user_id}/edit', UserEditController::class], $byName[Routes::USER_EDIT]);
        $this->assertSame(['/users/{user_id}/delete', UserDeleteController::class], $byName[Routes::USER_DELETE]);
        $this->assertSame(
            ['/users/{user_id}/api-tokens', [ApiTokensController::class, 'screen']],
            $byName[Routes::API_TOKENS]
        );
        $this->assertSame(
            ['/users/{user_id}/api-tokens/{token_id}/revoke', [ApiTokensController::class, 'revoke']],
            $byName[Routes::API_TOKEN_REVOKE]
        );
    }

    /**
     * Three of the four sign-in routes admit anonymous visitors, and they have to: a page
     * you visit *in order to* sign in cannot require an identity. Signing out is the one
     * that does. Everything on the administration surface is `Administrator`, which on this
     * surface is the whole protection — no controller there carries a second check.
     */
    public function testTheAudiencesAreWhatEachPageNeeds(): void
    {
        $audiences = [];
        foreach ($this->declared() as [$name, , , $audience]) {
            $audiences[$name] = $audience;
        }

        $this->assertSame(RouteAudience::Anyone, $audiences[Routes::INDEX]);
        $this->assertSame(RouteAudience::Anyone, $audiences[Routes::LOGIN]);
        $this->assertSame(RouteAudience::Anyone, $audiences[Routes::VERIFY]);
        $this->assertSame(RouteAudience::SignedIn, $audiences[Routes::LOGOUT]);

        foreach ([
            Routes::USERS,
            Routes::USER_CREATE,
            Routes::ROLE_CREATE,
            Routes::USER_EDIT,
            Routes::USER_DELETE,
            Routes::API_TOKENS,
            Routes::API_TOKEN_REVOKE,
        ] as $name) {
            $this->assertSame(RouteAudience::Administrator, $audiences[$name], $name);
        }
    }

    /**
     * Every parameterised route constrains its parameters, and no other route declares any.
     *
     * `user_id` is `[0-9]{1,5}` on all four, which is what keeps `/users/roles/create` from
     * matching `/users/{user_id}/...`-shaped patterns and what lets the controllers treat
     * the value as an integer without re-validating it.
     */
    public function testEveryParameterIsConstrained(): void
    {
        $expected = [
            Routes::USER_EDIT        => ['user_id' => '[0-9]{1,5}'],
            Routes::USER_DELETE      => ['user_id' => '[0-9]{1,5}'],
            Routes::API_TOKENS       => ['user_id' => '[0-9]{1,5}'],
            Routes::API_TOKEN_REVOKE => ['user_id' => '[0-9]{1,5}', 'token_id' => '[0-9]+'],
        ];

        foreach ($this->declared() as [$name, $path, , , , $requirements]) {
            $this->assertSame($expected[$name] ?? [], $requirements, $name);

            //and the converse: a path with a placeholder must appear above
            if (str_contains($path, '{')) {
                $this->assertArrayHasKey($name, $expected, $name . ' has parameters but no constraints');
            }
        }
    }

    public function testTheTwoCreateRoutesCarryTheRecordKind(): void
    {
        $defaults = [];
        foreach ($this->declared() as [$name, , , , $routeDefaults]) {
            $defaults[$name] = $routeDefaults;
        }

        $this->assertSame(
            [UserCreateController::KIND => UserCreateController::USER],
            $defaults[Routes::USER_CREATE]
        );
        $this->assertSame(
            [UserCreateController::KIND => UserCreateController::ROLE],
            $defaults[Routes::ROLE_CREATE]
        );
        $this->assertSame([], $defaults[Routes::USERS], 'nothing else needs a default');
    }

    /**
     * The literal `/users/...` paths are declared before the parameterised ones.
     *
     * The constraint is what actually keeps them apart, so this is belt and braces — but a
     * constraint is something a host can widen, and a matcher that takes the first match
     * should not depend on one to tell `/users/roles/create` from an account id.
     */
    public function testLiteralPathsComeBeforeParameterisedOnes(): void
    {
        $paths = array_column($this->declared(), 1);
        $users = array_values(array_filter($paths, static fn(string $p): bool => str_starts_with($p, '/users')));

        $firstParameterised = null;
        foreach ($users as $index => $path) {
            if (str_contains($path, '{')) {
                $firstParameterised = $index;
                break;
            }
        }

        $this->assertNotNull($firstParameterised);
        foreach (array_slice($users, $firstParameterised) as $path) {
            $this->assertStringContainsString('{', $path, 'a literal path after a parameterised one');
        }
    }

    /**
     * Nothing in the fragment names a route with a literal string.
     *
     * A route name is what a host's guard entry addresses, so a literal that drifts from
     * `Routes` is a dead link on one side and an unguarded page on the other — and neither
     * end fails, which is what makes it worth a test rather than a convention.
     */
    public function testEveryDeclaredNameIsAConstant(): void
    {
        $constants = (new ReflectionClass(Routes::class))->getConstants();

        foreach (array_column($this->declared(), 0) as $name) {
            $this->assertContains($name, $constants, $name . ' is not declared in JUser\Routing\Routes');
        }
        $this->assertCount(11, $constants, 'a constant with no route, or a route with no constant');
    }
}
