<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Throwable;

use function array_column;
use function array_key_exists;
use function count;
use function in_array;
use function is_readable;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Pins the state of the timeline / Kentenich-corpus feature so that "unfinished" stays
 * legible and every step out of it is deliberate.
 *
 * The feature is half-built and has been since mid-2020: 527 events and 2,753 texts are
 * loaded, one public read-only page renders them, and the entire write surface plus the
 * link between the two halves is unbuilt. The two-part plan for finishing it is in
 * docs/timeline-and-corpus.md.
 *
 * The problem this test exists for is that **none of that state fails**. A route with no
 * guard entry is silently denied and looks exactly like an oversight; an empty foreign-key
 * column looks exactly like a column nobody got round to using; and a spec key naming a
 * route that does not exist cannot be caught by anything, because every route that would
 * exercise it is denied. Five keys in the `event` entity spec had described the April 2020
 * *draft* of the schema rather than what db6.1 shipped, and nothing noticed for six years.
 *
 * So each assertion here is a tripwire on a transition, not a claim that the current state
 * is good:
 *
 *  - opening one of the four write routes must update this test;
 *  - the first `LegacyEventId` written must delete `testTheCorpusIsNotYetLinkedToEvents()`,
 *    and that deletion is the signal that Part 2 has started;
 *  - a spec key that stops naming a real route fails immediately rather than in six years.
 *
 * Two of the three need no database — they read the merged module configuration the way
 * AclGuardRouteDriftTest does, which is also what lets them run on a bare CI runner.
 */
class EventFeatureStateTest extends TestCase
{
    /**
     * The four event routes that carry no guard entry, and therefore are reachable by
     * nobody: `BjyAuthorize\Guard\Route` default-denies.
     *
     * `events` — the /timeline index — is deliberately absent from this list. It is
     * guarded `['user', 'guest']` and is the one part of the feature that works.
     */
    private const DENIED_ROUTES = [
        'event',
        'event-edit',
        'event-delete',
        'events/create',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * The merged module configuration, obtained the way bin/console does it: build the
     * ServiceManager and load modules, but never bootstrap(). Config caching is turned off
     * for the reason AclGuardRouteDriftTest gives — a bare CI runner cannot write
     * data/config/, and a cache file left owned by the wrong user next to a real
     * deployment is worse than a slow test.
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        if (null !== self::$config) {
            return self::$config;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        $serviceManager = new ServiceManager();
        (new ServiceManagerConfig($appConfig['service_manager'] ?? []))
            ->configureServiceManager($serviceManager);
        $serviceManager->setService('ApplicationConfig', $appConfig);
        $serviceManager->get('ModuleManager')->loadModules();

        /** @var array<string, mixed> $config */
        $config = $serviceManager->get('config');

        return self::$config = $config;
    }

    /**
     * @return list<string>
     */
    private function guardedRouteNames(): array
    {
        $config = $this->config();
        $guards = $config['bjyauthorize']['guards']['BjyAuthorize\Guard\Route'] ?? [];

        return array_column($guards, 'route');
    }

    /**
     * The events write surface is denied, and that is on purpose.
     *
     * Three things are missing before any of the four could be opened, and a guard entry
     * is none of them: there is no form (EventForm was deleted in batch 12 because it
     * matched a schema draft that never shipped), no show/edit/create template, and no
     * registered ACL resource — all 527 rows carry `evt_public`, which the config provider
     * does not declare and `EventTextTable::getResources()` does not emit, because that
     * method reads `SELECT DISTINCT AclResourceId FROM texts` and nothing from `events`.
     *
     * Adding a guard entry alone converts a clean default-deny into a 500. If you are here
     * because this test failed, read docs/timeline-and-corpus.md before deleting the line.
     */
    public function testTheEventWriteSurfaceHasNoGuardEntry(): void
    {
        $guarded = $this->guardedRouteNames();

        self::assertContains(
            'events',
            $guarded,
            'the /timeline index should stay guarded `user, guest` — it is the reachable half'
        );

        foreach (self::DENIED_ROUTES as $route) {
            self::assertNotContains(
                $route,
                $guarded,
                sprintf(
                    'route `%s` gained a guard entry. That opens a route with no form, no template '
                    . 'and no registered ACL resource (`evt_public` is declared nowhere), which is a '
                    . '500 rather than a page. See docs/timeline-and-corpus.md.',
                    $route
                )
            );
        }
    }

    /**
     * Every route the `event` entity spec names is a route that exists.
     *
     * This is the assertion that would have caught the drift. The spec said
     * `show_route => 'events/event'` and `edit_route => 'events/event/edit'`; neither has
     * ever existed, because `events` has exactly one child route (`create`) and the real
     * routes are top-level `event` / `event-edit` / `event-delete`. Nothing failed, because
     * SionController only assembles those URLs from actions that are all denied.
     *
     * Route *names* are checked, not paths, and nesting makes a name `parent/child` — the
     * same trap AclGuardRouteDriftTest documents for guard entries.
     */
    public function testTheEventEntitySpecNamesRoutesThatExist(): void
    {
        $config = $this->config();
        $entity = $config['sion_model']['entities']['event'] ?? null;

        self::assertIsArray($entity, 'the `event` entity spec has disappeared from the merged config');

        $routes = $config['router']['routes'] ?? [];

        foreach (['index_route', 'show_route', 'edit_route', 'create_action_redirect_route', 'delete_action_redirect_route'] as $key) {
            if (! array_key_exists($key, $entity)) {
                continue;
            }

            self::assertTrue(
                $this->routeExists((string) $entity[$key], $routes),
                sprintf('the event entity spec\'s `%s` names `%s`, which is not a route', $key, $entity[$key])
            );
        }
    }

    /**
     * @param array<string, mixed> $routes
     */
    private function routeExists(string $name, array $routes): bool
    {
        $segments = explode('/', $name);
        $current  = $routes;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return false;
            }
            $node    = $current[$segment];
            $current = $node['child_routes'] ?? [];
        }

        return true;
    }

    /**
     * The two halves of the feature are not connected yet.
     *
     * `texts.LegacyEventId` is the single foreign key that will carry the relation — a text
     * represents all or part of exactly one event, so many-to-one is the right shape and a
     * join table is not. It is NULL in all 2,757 rows today.
     *
     * **This test is meant to be deleted.** The first row the Part 2 importer writes makes
     * it fail, and that failure is the signal to remove it along with the "unstarted"
     * language in docs/timeline-and-corpus.md. Until then it guards against the column
     * being filled by something that is not the importer — a stray migration, a
     * half-finished script — which would be silent and very hard to unpick later.
     */
    public function testTheCorpusIsNotYetLinkedToEvents(): void
    {
        $adapter = $this->adapterOrSkip();

        $result = $adapter->query('SELECT COUNT(*) AS `linked` FROM `texts` WHERE `LegacyEventId` IS NOT NULL', []);
        $row    = $result->current();

        self::assertSame(
            0,
            (int) $row['linked'],
            'texts.LegacyEventId is no longer empty. If the Part 2 importer has run, delete this test '
            . 'and update docs/timeline-and-corpus.md; if it has not, find out what wrote to the column.'
        );
    }

    private function adapterOrSkip(): AdapterInterface
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no local configuration; this test needs a database');
        }

        try {
            $appConfig = require __DIR__ . '/../../config/application.config.php';
            $appConfig['module_listener_options']['config_cache_enabled']     = false;
            $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

            $serviceManager = new ServiceManager();
            (new ServiceManagerConfig($appConfig['service_manager'] ?? []))
                ->configureServiceManager($serviceManager);
            $serviceManager->setService('ApplicationConfig', $appConfig);
            $serviceManager->get('ModuleManager')->loadModules();

            /** @var AdapterInterface $adapter */
            $adapter = $serviceManager->get('Laminas\Db\Adapter\Adapter');
            $adapter->query('SELECT 1', []);

            return $adapter;
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }
}
