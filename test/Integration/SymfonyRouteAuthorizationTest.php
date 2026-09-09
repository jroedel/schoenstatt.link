<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Authorization\DenialStyle;
use App\Authorization\RouteAccess;
use App\Authorization\RouteGuard;
use App\Authorization\UndeclaredRouteAccess;
use App\Http\AuthorizationListener;
use App\Http\SymfonyRoute;
use App\Laminas\ContainerFactory;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;

use function is_array;
use function str_starts_with;
use function substr;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Structural guard over the *other* front controller's authorization.
 *
 * `App\Authorization\RouteGuard` refuses a Symfony-served route that declares
 * nothing, which catches the mistake at runtime. That is the last line of defence,
 * not the first: it only fires when someone requests the route, and the failure it
 * prevents — a guarded page silently open to everyone — is one whose whole character
 * is that nobody notices. So the declaration is asserted here instead, by walking
 * `config/symfony/routes.php` itself. A route added next month is covered without
 * anyone remembering to cover it, which is the same reason the fuzz harness discovers
 * forms from the filesystem rather than from a list.
 *
 * Nothing here needs a database, a session or a running app. That is not a
 * convenience: the ACL and the authentication service both reach laminas-session,
 * which cannot even be *built* under the CLI SAPI once PHPUnit has written its first
 * dot ("'session.cache_expire' is not a valid sessions-related ini setting"). The
 * guard's *decisions* are therefore measured over HTTP, in
 * test/Smoke/AdminAuthorizationSmokeTest; what is checkable here is its wiring, and
 * the wiring is where a silent hole would live.
 */
class SymfonyRouteAuthorizationTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    // -------------------------------------------------- every route declares something

    /**
     * The assertion this file exists for. `legacy` is excluded and must be: it is the
     * door back into laminas-mvc, which runs BjyAuthorize\Guard\Route itself.
     */
    public function testEveryPortedRouteDeclaresItsAuthorization(): void
    {
        $undeclared = [];
        foreach ($this->portedRoutes() as $name => $route) {
            if (! $route->getDefault(RouteAccess::ATTRIBUTE) instanceof RouteAccess) {
                $undeclared[] = $name . ' (' . $route->getPath() . ')';
            }
        }

        $this->assertSame(
            [],
            $undeclared,
            'these Symfony routes declare no RouteAccess, so nobody has decided who may reach them'
        );
    }

    /** A sanity floor, so a renamed file or an empty collection cannot make the above vacuous. */
    public function testTheCollectionStillHoldsThePortedRoutesAndTheCatchAll(): void
    {
        $routes = $this->collection();

        $this->assertNotNull($routes->get(SymfonyRoute::CATCH_ALL_ROUTE), 'the catch-all must still be there');
        $this->assertGreaterThanOrEqual(4, count($this->portedRoutes()), 'suspiciously few ported routes');
        foreach (['health', 'shrines', 'admin', 'sm-cache-status'] as $expected) {
            $this->assertNotNull($routes->get($expected), "route $expected disappeared");
        }
    }

    /** The catch-all must NOT declare one, or the guard would start second-guessing laminas-mvc. */
    public function testTheCatchAllDeclaresNothing(): void
    {
        $legacy = $this->collection()->get(SymfonyRoute::CATCH_ALL_ROUTE);

        $this->assertNotNull($legacy);
        $this->assertNull($legacy->getDefault(RouteAccess::ATTRIBUTE));
    }

    /**
     * A path and its locale-prefixed twin are one page, so they must be governed
     * identically. A check that applied to `/en/admin` and not `/admin` would be a hole
     * shaped exactly like the locale prefix.
     */
    public function testEachLocalePrefixedTwinIsGovernedIdenticallyToItsBareForm(): void
    {
        $routes = $this->collection();
        $twins  = 0;
        foreach ($routes->all() as $name => $route) {
            if (! str_ends_with((string) $name, SymfonyRoute::LOCALE_SUFFIX)) {
                continue;
            }
            $bare = $routes->get(substr((string) $name, 0, -strlen(SymfonyRoute::LOCALE_SUFFIX)));
            $this->assertNotNull($bare, "$name has no unprefixed counterpart");
            $this->assertSame(
                $bare->getDefault(RouteAccess::ATTRIBUTE),
                $route->getDefault(RouteAccess::ATTRIBUTE),
                "$name and its bare form must share one declaration"
            );
            $twins++;
        }
        $this->assertGreaterThan(0, $twins, 'no locale twins found — has the $ported() helper changed?');
    }

    // ------------------------------------------- the declared resources are real ones

    /**
     * A resource nothing defines denies everyone, because Authorize::isAllowed()
     * catches the ACL registry's InvalidArgumentException and answers false. Safe, and
     * silent — a typo would present as "the page is broken for me too", with the wrong
     * file to go looking in. Checked against the merged laminas config, which is the
     * point of the whole design: there is one set of resources, not two.
     */
    public function testEveryDeclaredResourceIsOneTheLaminasConfigDefines(): void
    {
        $known   = $this->routeResources();
        $unknown = [];
        foreach ($this->portedRoutes() as $name => $route) {
            $access = $route->getDefault(RouteAccess::ATTRIBUTE);
            if (! $access instanceof RouteAccess || null === $access->resource) {
                continue;
            }
            if (! isset($known[$access->resource])) {
                $unknown[] = $name . ' → ' . $access->resource;
            }
        }

        $this->assertSame([], $unknown, 'these declared ACL resources match no bjyauthorize guard entry');
    }

    /**
     * The invariant that makes `tools/acl-table.php` an oracle rather than half a
     * picture: a ported route named after a laminas route consults *that* route's
     * resource. Naming the route after the one it shadows is already load-bearing for
     * the navbar (App\Http\SymfonyRoute::routeName); this pins that it is load-bearing
     * for authorization too.
     */
    public function testAPortedRouteChecksTheResourceOfTheLaminasRouteItIsNamedAfter(): void
    {
        $guards = $this->routeResources();
        $wrong  = [];
        foreach ($this->portedRoutes() as $name => $route) {
            $access   = $route->getDefault(RouteAccess::ATTRIBUTE);
            $laminas  = 'route/' . SymfonyRoute::routeName($this->requestFor($name, $route));
            if (! $access instanceof RouteAccess || ! isset($guards[$laminas])) {
                //no laminas guard of that name: either /_health, which shadows nothing,
                //or a future route named after something unguarded. Nothing to compare.
                continue;
            }
            if ($access->resource !== null && $access->resource !== $laminas) {
                $wrong[] = $name . ' checks ' . $access->resource . ' but is named after ' . $laminas;
            }
        }

        $this->assertSame([], $wrong);
    }

    /**
     * Declaring a route open is allowed — but only where the laminas guard it is named
     * after admits everyone anyway. Otherwise porting the route quietly removed a
     * restriction, which is the exact failure this bridge was built to end.
     * `tools/acl-table.php` warns about the same thing; this fails the build.
     */
    public function testNoRouteIsDeclaredOpenWhileTheGuardItShadowsRestrictsAccess(): void
    {
        $guards = $this->routeGuardRoles();
        $holes  = [];
        foreach ($this->portedRoutes() as $name => $route) {
            $access = $route->getDefault(RouteAccess::ATTRIBUTE);
            if (! $access instanceof RouteAccess || ! $access->isOpen()) {
                continue;
            }
            $laminas = SymfonyRoute::routeName($this->requestFor($name, $route));
            if (! isset($guards[$laminas])) {
                continue;
            }
            //a null role is bjyauthorize's "everyone"; see docs/acl-baseline.json
            if (! in_array(null, $guards[$laminas], true)) {
                $holes[] = $name . ' is open but route/' . $laminas . ' is not public';
            }
        }

        $this->assertSame([], $holes);
    }

    // ------------------------------------------------------------- the guard's wiring

    /** An undeclared route is refused by name, so the message says where to look. */
    public function testTheGuardRefusesAnUndeclaredRouteAndNamesIt(): void
    {
        $request = Request::create('/somewhere');
        $request->attributes->set('_route', 'a-route-someone-forgot');

        $this->expectException(UndeclaredRouteAccess::class);
        $this->expectExceptionMessageMatches('/a-route-someone-forgot/');
        RouteGuard::declaredAccess($request);
    }

    /** …and not silently denied, which would look like an ACL problem and send the fix to the wrong file. */
    public function testAnUndeclaredRouteIsNotTreatedAsDeniedOrAllowed(): void
    {
        $request = Request::create('/somewhere');
        $request->attributes->set('_route', 'forgotten');

        try {
            $this->guard()->refuse($request);
            $this->fail('refuse() should have thrown for an undeclared route');
        } catch (UndeclaredRouteAccess $e) {
            $this->assertStringContainsString('RouteAccess::guardedBy', $e->getMessage());
            $this->assertStringContainsString('RouteAccess::openToEveryone', $e->getMessage());
        }
    }

    /**
     * A bridged request is laminas-mvc's business, and the guard must not touch it —
     * two guards disagreeing about one request is worse than one. Reaching *anything*
     * on the ServiceBridge here would need a database, so a null return is also the
     * proof that it reached nothing.
     */
    public function testABridgedRequestIsLeftEntirelyAlone(): void
    {
        $request = Request::create('/some/unported/page');
        $request->attributes->set('_route', SymfonyRoute::CATCH_ALL_ROUTE);

        $this->assertNull($this->guard()->refuse($request));
    }

    /** An open route short-circuits before the ACL, which is what makes /_health free. */
    public function testAnOpenRouteIsAllowedWithoutConsultingAnything(): void
    {
        $request = Request::create('/_health');
        $request->attributes->set('_route', 'health');
        $request->attributes->set(RouteAccess::ATTRIBUTE, RouteAccess::openToEveryone('because'));

        $this->assertNull($this->guard()->refuse($request));
    }

    /**
     * Priority is a correctness property here, not tuning. Above RouterListener there
     * is no matched route to read a declaration from; above LocaleListener the 403 page
     * renders against `en_US_POSIX` and its translations miss.
     */
    public function testTheListenerRunsAfterRoutingAndAfterTheLocaleIsSet(): void
    {
        //[[method, priority], …] — read from the listener rather than hardcoded, so a
        //Symfony upgrade that moves RouterListener fails here instead of silently
        //reordering the check
        $routerPriority = RouterListener::getSubscribedEvents()[KernelEvents::REQUEST][0][1];

        $this->assertLessThan(
            $routerPriority,
            AuthorizationListener::PRIORITY,
            'the check must run after RouterListener, or there is no route to check'
        );
        //LocaleListener is registered at the dispatcher's default priority
        $this->assertLessThan(0, AuthorizationListener::PRIORITY, 'the check must run after LocaleListener');
    }

    /** Every route's denial style is one of the two, and JSON never means "redirect". */
    #[DataProvider('portedRouteProvider')]
    public function testEachRouteDeclaresAKnownDenialStyle(string $name): void
    {
        $access = $this->collection()->get($name)?->getDefault(RouteAccess::ATTRIBUTE);

        $this->assertInstanceOf(RouteAccess::class, $access, "$name declares nothing");
        $this->assertContains($access->denialStyle, [DenialStyle::Html, DenialStyle::Json]);
    }

    /** @return array<string, array{0: string}> */
    public static function portedRouteProvider(): array
    {
        $names = [];
        foreach (self::loadCollection()->all() as $name => $route) {
            if (SymfonyRoute::CATCH_ALL_ROUTE !== $name) {
                $names[(string) $name] = [(string) $name];
            }
        }

        return $names;
    }

    // ---------------------------------------------------------------------- fixtures

    /**
     * A Request as RouterListener would leave it, so SymfonyRoute::routeName() can be
     * asked the same question it is asked in production instead of the suffix rule
     * being reimplemented here.
     */
    private function requestFor(string $name, Route $route): Request
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $name);
        $request->attributes->set('_route_params', $route->getDefaults());

        return $request;
    }

    /**
     * A guard wired with real collaborators that are never reached. Both the
     * ServiceBridge and the Twig closure would need a database and a session; every
     * assertion above returns or throws before either is touched, which is itself part
     * of what is being asserted.
     */
    private function guard(): RouteGuard
    {
        $bridge = new ServiceBridge([]);

        return new RouteGuard(
            $bridge,
            new RouteUrl(''),
            static fn (): Environment => self::fail('the 403 template must not be rendered here')
        );
    }

    /** @return array<string, Route> every route except the catch-all */
    private function portedRoutes(): array
    {
        $ported = [];
        foreach ($this->collection()->all() as $name => $route) {
            if (SymfonyRoute::CATCH_ALL_ROUTE !== $name) {
                $ported[(string) $name] = $route;
            }
        }

        return $ported;
    }

    private function collection(): RouteCollection
    {
        return self::loadCollection();
    }

    private static function loadCollection(): RouteCollection
    {
        /** @var RouteCollection $collection */
        $collection = require __DIR__ . '/../../config/symfony/routes.php';

        return $collection;
    }

    /**
     * Every `route/...` resource a bjyauthorize Route guard entry declares.
     *
     * @return array<string, true>
     */
    private function routeResources(): array
    {
        $resources = [];
        foreach ($this->routeGuardRoles() as $route => $_) {
            $resources['route/' . $route] = true;
        }

        return $resources;
    }

    /**
     * Route name => declared roles, from the merged bjyauthorize guard config. Later
     * entries win, exactly as AbstractGuard assigns rather than merges.
     *
     * @return array<string, list<string|null>>
     */
    private function routeGuardRoles(): array
    {
        $guards = [];
        foreach ($this->config()['bjyauthorize']['guards'] ?? [] as $type => $rules) {
            if ('BjyAuthorize\Guard\Route' !== $type || ! is_array($rules)) {
                continue;
            }
            foreach ($rules as $rule) {
                if (is_array($rule) && isset($rule['route'])) {
                    /** @var list<string|null> $roles */
                    $roles                            = is_array($rule['roles'] ?? null) ? $rule['roles'] : [];
                    $guards[(string) $rule['route']] = $roles;
                }
            }
        }

        return $guards;
    }

    /**
     * The merged module configuration the way bin/console gets it: build the
     * ServiceManager, load modules, never bootstrap(). Caches off, or a CI runner tries
     * to write data/config/ — the reasoning is spelled out in AclGuardRouteDriftTest.
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        if (null !== self::$config) {
            return self::$config;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';

        $services = ContainerFactory::build($appConfig);

        /** @var array<string, mixed> $config */
        $config = $services->get('config');

        return self::$config = $config;
    }
}
