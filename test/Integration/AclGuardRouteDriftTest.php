<?php

namespace SchoenstattTest\Integration;

use Laminas\Mvc\Application;
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
 * (one such account in the 2021 dump).
 *
 * Route names are also easy to get wrong silently, because they are *names*,
 * not paths, and nesting makes them `parent/child` — `libraries/checkouts` was
 * carried for years alongside the real `checkouts/library`.
 *
 * Needs vendor/ and a full module bootstrap to read the merged config, so it
 * lives in the integration suite: php composer.phar integration
 */
class AclGuardRouteDriftTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    private static ?Application $app = null;

    private function app(): Application
    {
        if (null === self::$app) {
            self::$app = Application::init(require __DIR__ . '/../../config/application.config.php');
        }

        return self::$app;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        if (null === self::$config) {
            /** @var array<string, mixed> $config */
            $config       = $this->app()->getServiceManager()->get('config');
            self::$config = $config;
        }

        return self::$config;
    }

    private function acl(): \Laminas\Permissions\Acl\AclInterface
    {
        return $this->app()->getServiceManager()->get('BjyAuthorize\Service\Authorize')->getAcl();
    }

    /**
     * Every route name the router knows, including nested children, keyed by
     * the `parent/child` name bjyauthorize matches on.
     *
     * @return array<string, true>
     */
    private function routeNames(): array
    {
        $names = [];

        $walk = static function (array $definitions, string $prefix) use (&$walk, &$names): void {
            foreach ($definitions as $name => $definition) {
                $full         = $prefix === '' ? (string) $name : $prefix . '/' . $name;
                $names[$full] = true;

                if (isset($definition['child_routes']) && is_array($definition['child_routes'])) {
                    $walk($definition['child_routes'], $full);
                }
            }
        };

        $walk($this->config()['router']['routes'] ?? [], '');

        return $names;
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
        // Ask the assembled ACL, not the guard list: resources also arrive via
        // BjyAuthorize\Provider\Resource\Config in acl.global.php, so deriving
        // them from guards alone reports false positives.
        $acl     = $this->acl();
        $missing = [];

        foreach ($this->viewFiles() as $file) {
            $source = file_get_contents($file);
            if (false === $source) {
                continue;
            }

            if (! preg_match_all('/isAllowed\(\s*[\'"]route\/([^\'"]+)[\'"]/', $source, $matches)) {
                continue;
            }

            foreach ($matches[1] as $route) {
                if (! $acl->hasResource('route/' . $route)) {
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
     * Pre-existing, recorded 2026-08-04. Each is a UI affordance that can never
     * appear because the ACL has no such resource. Do not add to this list.
     */
    private const KNOWN_DEAD_PERMISSION_CHECKS = [
        'deny-button-partial.phtml asks about route/admin/moderate',
        'persons_partial.phtml asks about route/persons/person/edit-contact-info',
        'review-phone-numbers.phtml asks about route/fathers/father/edit-contact-info',
        'search-bar.phtml asks about route/publications/advanced-search',
        'show.phtml asks about route/persons/person/edit-contact-info',
        'show.phtml asks about route/persons/person/edit-contact-info',
        'show.phtml asks about route/persons/person/edit-private-info',
        'show.phtml asks about route/suggest',
    ];

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
