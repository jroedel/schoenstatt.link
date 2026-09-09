<?php

namespace SchoenstattTest\Integration;

use App\Http\SymfonyRoutes;
use App\Laminas\ContainerFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Structural guard over bjyauthorize's route guards.
 *
 * A `BjyAuthorize\Guard\Route` entry naming a route that does not exist is not
 * inert config rot. Each entry also declares an ACL *resource* (`route/<name>`),
 * and views ask about those resources by hand:
 *
 *     <?php elseif ($this->isAllowed('route/association/suggest')) : ?>
 *         ... $this->url('association/suggest', ...) ...
 *
 * So a phantom entry can make `isAllowed()` answer **true** for a route the
 * router cannot assemble, and the very next line throws
 * `Route with name "association" does not have child routes` — a 500 on a page
 * that works fine for everyone whose roles happen to miss that branch. That is
 * exactly what shipped: `associations/show.phtml` fataled for any role holding
 * `user` but not `sch_user`/`sch_moderator`, which is why it went unnoticed
 * (one such account in the capsule's data).
 *
 * Route names are also easy to get wrong silently, because they are *names*,
 * not paths, and nesting makes them `parent/child` — `libraries/checkouts` was
 * carried for years alongside the real `checkouts/library`.
 *
 * Needs vendor/ to merge the module configuration, but deliberately stops short
 * of bootstrapping the application: no database, no running app. Runs anywhere
 * composer install has happened — php composer.phar integration, and in CI.
 */
class AclGuardRouteDriftTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * The merged module configuration, obtained the way bin/console does it:
     * build the ServiceManager and load modules, but never call bootstrap().
     *
     * Two things matter here. Bootstrapping would instantiate table services
     * and need a database, which this suite deliberately does not have; and the
     * config cache must be off, or the module listener tries to write
     * data/config/ — which fails outright on a bare CI runner and, worse, could
     * leave a cache file owned by the wrong user next to a real deployment.
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        if (null !== self::$config) {
            return self::$config;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';

        $serviceManager = ContainerFactory::build($appConfig);

        /** @var array<string, mixed> $config */
        $config = $serviceManager->get('config');

        return self::$config = $config;
    }

    /**
     * Every `route/...` ACL resource the configuration declares.
     *
     * bjyauthorize builds these from two places, and reading only the guards
     * reports false positives: the Route guard turns each of its entries into a
     * resource, and BjyAuthorize\Provider\Resource\Config (acl.global.php)
     * declares more by hand. Derived from config rather than from a built ACL
     * because assembling the ACL needs the database.
     *
     * @return array<string, true>
     */
    private function declaredRouteResources(): array
    {
        $resources = [];

        foreach ($this->guardedRouteNames() as $name) {
            $resources['route/' . $name] = true;
        }

        $providers = $this->config()['bjyauthorize']['resource_providers'] ?? [];
        foreach ($providers['BjyAuthorize\Provider\Resource\Config'] ?? [] as $key => $value) {
            // The provider accepts both a flat list of names and a
            // name => children map.
            $resources[is_int($key) ? (string) $value : (string) $key] = true;
            if (is_array($value)) {
                foreach ($value as $child) {
                    $resources[(string) $child] = true;
                }
            }
        }

        return $resources;
    }

    /**
     * Every route name the router knows, including nested children, keyed by
     * the `parent/child` name bjyauthorize matches on.
     *
     * @return array<string, true>
     */
    private function routeNames(): array
    {
        //Walked the laminas `router` config's nested child_routes until step 6 removed it.
        //The names a guard can name are now the Symfony route names, with `.locale` twins
        //collapsed and the two renamed pages mapped back to what the ACL calls them —
        //see App\Http\SymfonyRoutes::aclNames(), which tools/acl-table.php also uses so the
        //table and this test cannot disagree about what exists.
        return SymfonyRoutes::aclNames();
    }

    /**
     * @return list<string>
     */
    private function guardedRouteNames(): array
    {
        $entries = $this->config()['bjyauthorize']['guards']['BjyAuthorize\Guard\Route'] ?? [];

        $names = [];
        foreach ($entries as $entry) {
            if (isset($entry['route'])) {
                $names[] = (string) $entry['route'];
            }
        }

        return $names;
    }

    public function testTheWalkFindsSomethingToCheck(): void
    {
        // Guards against the failure mode where a config key is renamed and
        // every assertion below passes vacuously.
        self::assertGreaterThan(100, count($this->routeNames()), 'route walk found almost nothing');
        self::assertGreaterThan(100, count($this->guardedRouteNames()), 'no route guards found');
    }

    public function testEveryRouteGuardNamesARouteThatExists(): void
    {
        $routes  = $this->routeNames();
        $phantom = [];

        foreach ($this->guardedRouteNames() as $name) {
            if (! isset($routes[$name])) {
                $phantom[$name] = true;
            }
        }

        self::assertSame(
            [],
            array_keys($phantom),
            "these bjyauthorize route guards name routes that do not exist. Each one still declares an ACL "
            . "resource 'route/<name>', so an isAllowed() call in a view can answer true for a route the "
            . 'router cannot assemble — see this test\'s docblock. Delete the guard entry, or fix the name '
            . '(remember names nest as parent/child, and are not URL paths).'
        );
    }

    /**
     * A route named by two guard entries with the *same* roles is pure
     * redundancy, and worth failing on because the duplication is invisible in
     * its effect: BjyAuthorize keys rules by resource and assigns rather than
     * merges (`AbstractGuard::__construct`), so for a repeated route the last
     * entry in the merged config wins outright and the earlier one is
     * discarded silently.
     *
     * Entries that repeat a route with *different* roles are allowed, because
     * that is the only way to override a shared module's default — JUser
     * declares zfcuser/logout for ['guest', 'user'] and this application narrows
     * it to ['user']. That works because config/autoload/ merges after module
     * config, so it is load order rather than precedence; the override carries a
     * comment saying so.
     *
     * (This example named zfcuser/register until 2026-08-20, when that route was
     * retired with the rest of the password era.)
     */
    public function testNoRouteIsGuardedTwiceWithIdenticalRoles(): void
    {
        $entries = $this->config()['bjyauthorize']['guards']['BjyAuthorize\Guard\Route'] ?? [];

        $byRoute = [];
        foreach ($entries as $entry) {
            if (isset($entry['route'])) {
                $roles = array_map(static fn ($r) => var_export($r, true), (array) ($entry['roles'] ?? []));
                sort($roles);
                $byRoute[(string) $entry['route']][] = implode(',', $roles);
            }
        }

        $redundant = [];
        foreach ($byRoute as $route => $roleSets) {
            if (count($roleSets) !== count(array_unique($roleSets))) {
                $redundant[] = $route;
            }
        }

        self::assertSame(
            [],
            $redundant,
            'these routes are guarded more than once with identical roles. One of each pair is silently '
            . 'discarded, so the duplication is dead weight — delete the copy that is not the intended owner.'
        );
    }

    /**
     * The quiet half of the same problem, and the reason removing a guard entry
     * is not automatically safe.
     *
     * `BjyAuthorize\View\Helper\IsAllowed` answers **false** for a resource the
     * ACL has never heard of — it does not throw. So a template asking about an
     * undeclared `route/...` resource does not fail; the button it guards simply
     * never renders, for anyone, forever. `admin/moderate` is the clearest case:
     * the route exists, no guard declares it, so the route is blocked *and* its
     * "deny" button is invisible — the whole moderation affordance is
     * unreachable rather than merely restricted.
     *
     * Fixing these means deciding which roles should hold each permission, so
     * they are recorded here rather than guessed at (docs/BACKLOG.md). The
     * contract is the same as phpstan-baseline.neon's: no *new* drift.
     */
    public function testNoNewSilentlyDeadPermissionChecks(): void
    {
        $declared = $this->declaredRouteResources();
        $missing  = [];

        foreach ($this->viewFiles() as $file) {
            $source = file_get_contents($file);
            if (false === $source) {
                continue;
            }

            if (! preg_match_all('/isAllowed\(\s*[\'"]route\/([^\'"]+)[\'"]/', $source, $matches)) {
                continue;
            }

            foreach ($matches[1] as $route) {
                if (! isset($declared['route/' . $route])) {
                    $missing[] = sprintf('%s asks about route/%s', basename($file), $route);
                }
            }
        }

        sort($missing);

        self::assertSame(
            self::KNOWN_DEAD_PERMISSION_CHECKS,
            $missing,
            'the set of templates asking about an undeclared route resource has changed. Adding one means a '
            . 'button that will never render for anyone; removing one is a fix, so delete it from '
            . 'KNOWN_DEAD_PERMISSION_CHECKS.'
        );
    }

    /**
     * Empty since the app modules' view scripts were deleted (laminas-exit.md, step 0);
     * the four entries recorded here on 2026-08-04 all lived in them. The walk now covers
     * the view scripts the shared libraries still carry. Do not add to this list.
     */
    private const KNOWN_DEAD_PERMISSION_CHECKS = [];

    /**
     * @return list<string>
     */
    private function viewFiles(): array
    {
        $root  = dirname(__DIR__, 2) . '/module';
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'phtml') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
