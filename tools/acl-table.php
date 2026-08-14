<?php

/**
 * Authorization baseline extractor — the parity oracle for retiring BjyAuthorize.
 *
 * Emits the complete, reviewable authorization picture of the application:
 * every role and its place in the hierarchy, every route guard and the set of
 * roles that actually get through it, every route that no guard mentions, every
 * guard that names a route the router does not have, plus the non-route
 * resources and rules.
 *
 * Two outputs, same facts:
 *   --format=markdown  (default) human review
 *   --format=json      deterministic, fully sorted, for `diff`-ing before/after
 *                      the first-party ACL layer lands
 *
 * Runs INSIDE the app container (the host PHP lacks the needed extensions):
 *   docker compose exec -T app php tools/acl-table.php
 *   docker compose exec -T app php tools/acl-table.php --format=json
 *
 * It reads the *merged* module configuration without bootstrapping the
 * application (technique lifted from test/Integration/AclGuardRouteDriftTest.php)
 * and reads the role hierarchy straight out of MySQL with PDO, because the
 * hierarchy is data, not config. No app services are instantiated, so this is
 * safe to run against a half-migrated vendor/ tree.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// CLI
// ---------------------------------------------------------------------------

$format = 'markdown';
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--format=(markdown|json)$/', $arg, $m) === 1) {
        $format = $m[1];
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "usage: php tools/acl-table.php [--format=markdown|json]\n");
        exit(0);
    }
    fwrite(STDERR, "unknown argument: $arg\n");
    exit(2);
}

/** @var list<string> $warnings collected here, surfaced in both output formats */
$warnings = [];

// ---------------------------------------------------------------------------
// Merged configuration
// ---------------------------------------------------------------------------

/**
 * The merged module configuration, obtained the way bin/console does it: build
 * the ServiceManager and load modules, but never call bootstrap().
 *
 * Bootstrapping would instantiate table services and need a live application;
 * the config cache must be off or the module listener writes data/config/ and a
 * later run reads a stale merge (or leaves a file owned by the wrong user).
 *
 * @return array<string, mixed>
 */
function loadMergedConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    $appConfig = require __DIR__ . '/../config/application.config.php';
    $appConfig['module_listener_options']['config_cache_enabled']     = false;
    $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

    $serviceManager = new Laminas\ServiceManager\ServiceManager();
    (new Laminas\Mvc\Service\ServiceManagerConfig($appConfig['service_manager'] ?? []))
        ->configureServiceManager($serviceManager);
    $serviceManager->setService('ApplicationConfig', $appConfig);
    $serviceManager->get('ModuleManager')->loadModules();

    /** @var array<string, mixed> $merged */
    $merged = $serviceManager->get('config');

    return $config = $merged;
}

/**
 * Every route name the router knows, including nested children, keyed by the
 * `parent/child` name bjyauthorize matches on. Guards name routes, not paths.
 *
 * The value records whether the name can actually be *matched*. A route with
 * `child_routes` becomes a Part route, and TreeRouteStack::routeFromArray sets
 * `may_terminate` to false unless the config says otherwise, so such a parent
 * name is never the matched route name — it is a namespace, not an endpoint.
 * Reporting one as "guarded by nobody" would be noise, so they are separated.
 *
 * @param array<string, mixed> $config
 * @return array<string, bool> route name => is a matchable endpoint
 */
function routeNames(array $config): array
{
    $names = [];

    $walk = static function (array $definitions, string $prefix) use (&$walk, &$names): void {
        foreach ($definitions as $name => $definition) {
            $full     = $prefix === '' ? (string) $name : $prefix . '/' . $name;
            $hasChild = isset($definition['child_routes']) && is_array($definition['child_routes']);

            $names[$full] = ! $hasChild || ($definition['may_terminate'] ?? false) === true;

            if ($hasChild) {
                $walk($definition['child_routes'], $full);
            }
        }
    };

    $walk($config['router']['routes'] ?? [], '');

    return $names;
}

/**
 * Compose each laminas route name's URL *pattern*, with the constraints that
 * govern its parameters, so it can be compared with the Symfony router's.
 *
 * Only needed because of the strangler migration, and only approximate on
 * purpose: `Literal` and `Segment` both carry their piece of the path in
 * `options.route`, a `Method` child constrains the verb and contributes nothing,
 * and `Regex` has no literal path at all — those get null. A null simply means
 * "cannot be compared", which is reported rather than skipped (see
 * shadowedBySymfony), because a route nobody could compare is exactly the kind of
 * thing this tool used to be silent about.
 *
 * Constraints accumulate down the tree: a child's `:sw_id` may well be constrained
 * by an ancestor's `constraints` array rather than its own, and a probe URL needs
 * every parameter in the composed pattern, not just the ones this node named. A
 * child that re-declares a name wins, which is what laminas does too.
 *
 * The composed pattern has no locale prefix, because there is none in the config:
 * SlmLocale\Strategy\UriPathStrategy strips `/en` before the router ever sees the
 * request. The Symfony side declares both forms for exactly that reason, so
 * matching the unprefixed path is the right comparison.
 *
 * @param array<string, mixed> $config
 * @return array<string, array{pattern: string|null, constraints: array<string, string>}>
 */
function laminasRoutePaths(array $config): array
{
    $paths = [];

    /** @var callable(array<string, mixed>, string|null, string, array<string, string>): void $walk */
    $walk = static function (
        array $definitions,
        ?string $prefix,
        string $parentName,
        array $inherited
    ) use (&$walk, &$paths): void {
        foreach ($definitions as $name => $definition) {
            $full = $parentName === '' ? (string) $name : $parentName . '/' . $name;

            $segment = $definition['options']['route'] ?? null;
            $type    = isset($definition['type']) ? (string) $definition['type'] : '';
            if ($prefix === null) {
                // Once an ancestor could not be composed, nothing below it can be.
                $path = null;
            } elseif (is_string($segment)) {
                $path = $prefix . $segment;
            } else {
                // A Method route (verb only) inherits its parent's path; anything
                // else with no literal `route` option cannot be composed.
                $path = str_contains($type, 'Method') ? $prefix : null;
            }

            $own = [];
            foreach ((array) ($definition['options']['constraints'] ?? []) as $param => $regex) {
                if (is_string($regex)) {
                    $own[(string) $param] = $regex;
                }
            }
            $constraints = $own + $inherited;

            $paths[$full] = ['pattern' => $path, 'constraints' => $constraints];

            if (isset($definition['child_routes']) && is_array($definition['child_routes'])) {
                $walk($definition['child_routes'], $path, $full, $constraints);
            }
        }
    };

    $walk($config['router']['routes'] ?? [], '', '', []);

    return $paths;
}

/**
 * Every concrete path a laminas pattern can produce, once its optional groups are
 * taken and left.
 *
 * `/associations/:sw_id[/:slug]` is two different URLs, and they need not resolve
 * to the same Symfony route — the slug form is precisely what batch 5's show
 * routes swallowed. Expanding innermost-first means a nested group is resolved
 * before the group containing it, and the bracket-free results feed straight into
 * parameter substitution.
 *
 * Capped at 16 variants. Nothing in this config comes close (the most any route
 * has is two optional groups), and an uncapped 2^n on a malformed pattern is not
 * a failure mode worth having.
 *
 * @return list<string> empty if the pattern is malformed or too branchy
 */
function expandOptionalSegments(string $pattern): array
{
    $variants = [$pattern];

    while (true) {
        $next     = [];
        $expanded = false;

        foreach ($variants as $variant) {
            $close = strpos($variant, ']');
            if ($close === false) {
                if (str_contains($variant, '[')) {
                    return [];
                }
                $next[] = $variant;
                continue;
            }
            $head = substr($variant, 0, $close);
            $open = strrpos($head, '[');
            if ($open === false) {
                return [];
            }
            $inner    = substr($variant, $open + 1, $close - $open - 1);
            $tail     = substr($variant, $close + 1);
            $next[]   = substr($variant, 0, $open) . $inner . $tail;
            $next[]   = substr($variant, 0, $open) . $tail;
            $expanded = true;
        }

        $variants = $next;
        if (! $expanded) {
            return array_values(array_unique($variants));
        }
        if (count($variants) > 16) {
            return [];
        }
    }
}

/**
 * Concrete URLs a laminas route pattern can produce, for feeding to the Symfony
 * matcher.
 *
 * @param array<string, string> $constraints
 * @return array{urls: list<string>, reason: string|null}
 */
function probeUrls(?string $pattern, array $constraints): array
{
    if ($pattern === null) {
        return ['urls' => [], 'reason' => 'its path could not be composed from the config'];
    }
    if ($pattern === '') {
        return ['urls' => [], 'reason' => 'it contributes no path of its own'];
    }

    $variants = expandOptionalSegments($pattern);
    if ($variants === []) {
        return ['urls' => [], 'reason' => 'its optional segments could not be expanded'];
    }

    $urls    = [];
    $unnamed = [];
    foreach ($variants as $variant) {
        $failed = null;
        $url    = preg_replace_callback(
            '/:([a-zA-Z_][a-zA-Z0-9_]*)/',
            static function (array $m) use ($constraints, &$failed): string {
                // Laminas' default constraint for an unconstrained segment
                // parameter is "anything but a slash".
                $constraint = $constraints[$m[1]] ?? '[^/]+';
                $sample     = App\Routing\RegexSampler::sample($constraint);
                if ($sample === null) {
                    $failed = $m[1];
                    return $m[0];
                }
                return $sample;
            },
            $variant
        );

        if ($failed !== null) {
            $unnamed[$failed] = $constraints[$failed] ?? '[^/]+';
            continue;
        }
        if ($url === null || preg_match('#[\[\]:*{}]#', $url) === 1) {
            return ['urls' => [], 'reason' => 'its pattern uses syntax this tool does not model'];
        }
        $urls[] = $url;
    }

    if ($urls === []) {
        $described = [];
        foreach ($unnamed as $param => $constraint) {
            $described[] = sprintf('`:%s` (`%s`)', $param, $constraint);
        }
        return [
            'urls'   => [],
            'reason' => 'no value could be generated for ' . implode(', ', $described),
        ];
    }

    return ['urls' => array_values(array_unique($urls)), 'reason' => null];
}

/**
 * The routes the Symfony kernel serves itself, read from config/symfony/routes.php,
 * each with the authorization it declares.
 *
 * This tool exists to be the authorization oracle, and a ported route would
 * otherwise make it go blind at the worst possible moment: the route vanishes
 * from the laminas config, so it stops appearing here at all — not even as
 * "unguarded". Reading the Symfony collection keeps every path accounted for by
 * one of the two front controllers.
 *
 * Since 2026-08-06 a Symfony-served route is not merely *listed* here but
 * *checked*: App\Authorization\RouteGuard runs on kernel.request and asks the ACL
 * about a resource the route names in its own defaults, so the same guard entry can
 * govern both front controllers. Reading that declaration is what turns this section
 * from an inventory into part of the authorization picture — and it is why a route
 * that declares nothing has to be reported loudly rather than quietly listed. That
 * is the silent-bypass shape: the page keeps working and simply admits everyone.
 *
 * The catch-all (App\Http\LegacyBridge) is excluded from the matcher: it claims
 * every path by design, so leaving it in would report the entire site as ported. It
 * also, correctly, declares no RouteAccess — laminas-mvc runs its own guard behind
 * it — so excluding it here also keeps it out of the undeclared warning.
 *
 * @return array{
 *     routes: array<string, array{
 *         path: string,
 *         controller: string,
 *         access: array{kind: string, resource: string|null, reason: string|null, denial_style: string}
 *     }>,
 *     matcher: Symfony\Component\Routing\Matcher\UrlMatcher|null,
 *     collection: Symfony\Component\Routing\RouteCollection|null,
 *     available: bool
 * }
 */
function symfonyRoutes(): array
{
    static $result = null;
    if ($result !== null) {
        return $result;
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    $file = __DIR__ . '/../config/symfony/routes.php';
    if (! is_file($file)) {
        return $result = ['routes' => [], 'matcher' => null, 'collection' => null, 'available' => false];
    }

    /** @var Symfony\Component\Routing\RouteCollection $collection */
    $collection = require $file;

    $ported = new Symfony\Component\Routing\RouteCollection();
    $routes = [];
    foreach ($collection->all() as $name => $route) {
        //`[Class::class, 'method']` since batch 4: a controller serving more than one
        //route. Rendered as `Class::method`, which is what the table wants to show and
        //what a string controller already looks like — casting the array would be an
        //"Array to string conversion" notice and the word "Array" in the column.
        $rawController = $route->getDefault('_controller');
        $controller    = is_array($rawController)
            ? implode('::', array_map('strval', $rawController))
            : (string) $rawController;
        if (str_starts_with($controller, 'App\Http\LegacyBridge')) {
            continue;
        }
        $routes[(string) $name] = [
            'path'       => $route->getPath(),
            'controller' => $controller,
            'access'     => describeRouteAccess(
                $route->getDefault(App\Authorization\RouteAccess::ATTRIBUTE)
            ),
        ];
        $ported->add((string) $name, $route);
    }
    ksort($routes);

    return $result = [
        'routes'    => $routes,
        'matcher'   => new Symfony\Component\Routing\Matcher\UrlMatcher(
            $ported,
            new Symfony\Component\Routing\RequestContext()
        ),
        // The collection itself, so the shadow check can walk the URLs Symfony
        // owns and ask the laminas router which guard each one bypasses.
        'collection' => $ported,
        'available' => true,
    ];
}

/**
 * Flatten an App\Authorization\RouteAccess into plain data.
 *
 * The three kinds are the three states a reviewer has to be able to tell apart:
 *
 *   - `acl`         the route asks the ACL about `resource`, which is the same
 *                   resource the laminas guard uses. One source of truth.
 *   - `open`        no check, declared on purpose, with `reason` recorded.
 *   - `undeclared`  nothing at all. App\Authorization\RouteGuard throws
 *                   UndeclaredRouteAccess when such a route is reached, so this
 *                   cannot reach production silently — but it must be visible here
 *                   too, because "it 500s in development" is a weaker guarantee than
 *                   "the snapshot diff shows it".
 *
 * @return array{kind: string, resource: string|null, reason: string|null, denial_style: string}
 */
function describeRouteAccess(mixed $access): array
{
    if (! $access instanceof App\Authorization\RouteAccess) {
        return ['kind' => 'undeclared', 'resource' => null, 'reason' => null, 'denial_style' => 'n/a'];
    }

    return [
        'kind'         => $access->isOpen() ? 'open' : 'acl',
        'resource'     => $access->resource,
        'reason'       => $access->openReason,
        // Declared per route rather than sniffed from Accept, so it belongs in the
        // snapshot: a machine endpoint that starts answering 302 instead of 401 is a
        // caller-visible change. An open route never denies, so reporting a style for
        // it would put a value in the snapshot that nothing reads.
        'denial_style' => $access->isOpen() ? 'n/a' : strtolower($access->denialStyle->name),
    ];
}

/**
 * The real laminas router, built from the merged config with no application
 * around it.
 *
 * `TreeRouteStack::factory()` needs no ServiceManager for the four route types
 * this config uses, and no bootstrap, so the tool stays a config reader. Note it
 * comes back with a **null base URL**, where the running application's router has
 * `/en`: SlmLocale sets the base URL to the negotiated locale at request time.
 * Matching unprefixed paths against this one is therefore the correct comparison,
 * and the same reason the Symfony side declares a bare twin for every route.
 *
 * @param array<string, mixed> $config
 */
function laminasRouter(array $config): ?Laminas\Router\Http\TreeRouteStack
{
    if (! is_array($config['router'] ?? null)) {
        return null;
    }

    try {
        return Laminas\Router\Http\TreeRouteStack::factory($config['router']);
    } catch (Throwable) {
        return null;
    }
}

/**
 * A concrete URL a Symfony route answers, for asking the laminas router what used
 * to serve it.
 *
 * The locale prefix is stripped rather than instantiated, because the laminas
 * router built above has no base URL and its config carries no locale segment.
 * That also collapses each `.locale` twin onto its bare form, which is what we
 * want: they are the same page and would otherwise be counted twice.
 */
function symfonyProbeUrl(Symfony\Component\Routing\Route $route): ?string
{
    $path = $route->getPath();
    if (str_starts_with($path, '/{_locale}')) {
        $path = substr($path, strlen('/{_locale}'));
        if ($path === '') {
            $path = '/';
        }
    }

    $failed = false;
    $url    = preg_replace_callback(
        '/\{(\w+)\}/',
        static function (array $m) use ($route, &$failed): string {
            $sample = App\Routing\RegexSampler::sample($route->getRequirement($m[1]) ?? '[^/]+');
            if ($sample === null) {
                $failed = true;
                return $m[0];
            }
            return $sample;
        },
        $path
    );

    if ($failed || $url === null || str_contains($url, '{')) {
        return null;
    }

    return $url;
}

/**
 * Which laminas routes a Symfony route now answers instead.
 *
 * Matched with the real UrlMatcher rather than by comparing strings, so the
 * answer accounts for defaults, requirements and route order exactly as a request
 * would.
 *
 * ## Why this takes probe URLs rather than patterns
 *
 * Until 2026-08-14 it passed the composed laminas *pattern* — `/:sw_id/edit` —
 * straight to the matcher, which matches URLs and not patterns, so it threw and
 * the route was skipped. Every laminas route with a parameter in it was therefore
 * unshadowable by construction: **0 of the 31** rows the table reported had one,
 * while ~90 parameterized routes went unexamined. That is the exact blindness
 * that let batch 5 make nine guarded routes unreachable without this tool saying
 * a word (see docs/BACKLOG.md and src/Sion/ReservedVerbs.php).
 *
 * Now each pattern is instantiated into concrete URLs — one per combination of
 * its optional segments, each parameter replaced by a value *verified* against
 * its own constraint — and those are matched. A route counts as shadowed if any
 * of its probes resolves to a ported route.
 *
 * ## Both directions, because neither alone is enough
 *
 * A probe is one point in a route's URL space, so a forward match is conclusive
 * and a forward non-match is merely evidence: a Symfony route claiming a narrow
 * slice of a broad laminas route would be missed. `api-route-not-found`
 * (`/api(/.*)?`) is not a hypothetical example of this — `/api/v3/associations`
 * is claimed by Symfony while `/api/a` is not.
 *
 * So the check also runs the other way, and that direction is the one that maps
 * to reality: for every URL Symfony *owns*, ask the **real laminas router** which
 * route would have served it, and record that route's guard as bypassed. There is
 * no sampling on the laminas side of that question at all — the router answers
 * it — and the set of URLs Symfony owns is exactly the set where a laminas guard
 * silently stops running.
 *
 * The two passes are unioned. A laminas route neither pass could decide is
 * reported as *uncomparable* rather than quietly treated as unshadowed, so the
 * tool's silence about a route now means something.
 *
 * @param array<string, mixed> $config
 * @param array<string, array{pattern: string|null, constraints: array<string, string>}> $laminasPaths
 * @param array<string, bool> $matchable route name => is a matchable endpoint
 * @return array{
 *     shadowed: array<string, array{
 *         path: string,
 *         probe: string,
 *         symfony_route: string,
 *         access: array{kind: string, resource: string|null, reason: string|null, denial_style: string}
 *     }>,
 *     uncomparable: array<string, array{path: string|null, reason: string}>
 * }
 */
function shadowedBySymfony(array $config, array $laminasPaths, array $matchable): array
{
    $symfony = symfonyRoutes();
    $matcher = $symfony['matcher'];
    if ($matcher === null) {
        return ['shadowed' => [], 'uncomparable' => []];
    }

    $shadowed     = [];
    $uncomparable = [];
    foreach ($laminasPaths as $name => $route) {
        $name  = (string) $name;
        $probe = probeUrls($route['pattern'], $route['constraints']);

        if ($probe['urls'] === []) {
            // A Part route that only namespaces its children can never be matched
            // by anything, so reporting that it could not be compared would be
            // noise rather than a gap.
            if (($matchable[$name] ?? false) && $probe['reason'] !== null) {
                $uncomparable[$name] = ['path' => $route['pattern'], 'reason' => $probe['reason']];
            }
            continue;
        }

        foreach ($probe['urls'] as $url) {
            try {
                $match = $matcher->match($url);
            } catch (Throwable) {
                continue;
            }
            $symfonyRoute    = (string) ($match['_route'] ?? '?');
            $shadowed[$name] = [
                'path'          => (string) $route['pattern'],
                // The URL that actually matched, so a reviewer can reproduce the
                // claim with curl instead of taking the tool's word for it.
                'probe'         => $url,
                'symfony_route' => $symfonyRoute,
                // Carried along so the warning below can ask the question that actually
                // matters: not "does the laminas guard still run" (it never does) but
                // "does the ported route check the same resource it used to".
                'access'        => $symfony['routes'][$symfonyRoute]['access']
                    ?? ['kind' => 'undeclared', 'resource' => null, 'reason' => null, 'denial_style' => 'n/a'],
            ];
            break;
        }
    }

    // Second pass, from the other end: every URL Symfony owns, resolved by the
    // real laminas router. This is what catches a ported route that claims only
    // part of a laminas route's URL space, which the forward probe cannot see.
    $legacy = laminasRouter($config);
    if ($legacy !== null) {
        foreach ($symfony['collection']?->all() ?? [] as $name => $route) {
            $url = symfonyProbeUrl($route);
            if ($url === null) {
                continue;
            }
            $request = new Laminas\Http\Request();
            $request->setUri('http://localhost' . $url);
            try {
                $match = $legacy->match($request);
            } catch (Throwable) {
                continue;
            }
            if ($match === null) {
                continue;
            }
            $laminasRoute = (string) $match->getMatchedRouteName();
            // The forward pass already recorded a probe for this route; keep it,
            // because its URL is derived from the laminas route's own pattern and
            // so reads more naturally in the table.
            if (isset($shadowed[$laminasRoute])) {
                continue;
            }
            $symfonyName             = (string) $name;
            $shadowed[$laminasRoute] = [
                'path'          => (string) ($laminasPaths[$laminasRoute]['pattern'] ?? $url),
                'probe'         => $url,
                'symfony_route' => $symfonyName,
                'access'        => $symfony['routes'][$symfonyName]['access']
                    ?? ['kind' => 'undeclared', 'resource' => null, 'reason' => null, 'denial_style' => 'n/a'],
            ];
            unset($uncomparable[$laminasRoute]);
        }
    }

    ksort($shadowed);
    ksort($uncomparable);

    return ['shadowed' => $shadowed, 'uncomparable' => $uncomparable];
}

// ---------------------------------------------------------------------------
// Role hierarchy (database)
// ---------------------------------------------------------------------------

/**
 * Read the role hierarchy out of `user_role` exactly the way
 * BjyAuthorize\Provider\Role\LaminasDb does: rows are indexed by the identifier
 * field, and a row's parent is looked up *by primary key* — `parent_id` holds an
 * `id`, not a `role_id`. Getting that backwards silently produces a flat tree.
 *
 * Plain PDO on purpose: no Laminas\Db adapter, no service manager, so this keeps
 * working while vendor/ is mid-migration.
 *
 * @param array<string, mixed> $config
 * @param list<string> $warnings
 * @return array{roles: array<string, string|null>, available: bool}
 *         role_id => parent role_id (null for a root role)
 */
function readRoleHierarchy(array $config, array &$warnings): array
{
    $db      = $config['db'] ?? [];
    $table   = 'user_role';
    $idField = 'id';
    $roleCol = 'role_id';
    $parCol  = 'parent_id';

    // The provider's own options win if they were customized.
    $options = $config['bjyauthorize']['role_providers']['BjyAuthorize\Provider\Role\LaminasDb'] ?? [];
    $table   = (string) ($options['table'] ?? $table);
    $idField = (string) ($options['identifier_field_name'] ?? $idField);
    $roleCol = (string) ($options['role_id_field'] ?? $roleCol);
    $parCol  = (string) ($options['parent_role_field'] ?? $parCol);

    if (! isset($db['database'])) {
        $warnings[] = 'No db configuration found in the merged config; the role hierarchy is unavailable. '
            . 'Everything derived from configuration alone is still reported.';
        return ['roles' => [], 'available' => false];
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        (string) ($db['hostname'] ?? $db['host'] ?? '127.0.0.1'),
        (int) ($db['port'] ?? 3306),
        (string) $db['database']
    );

    try {
        $pdo = new PDO(
            $dsn,
            (string) ($db['username'] ?? ''),
            (string) ($db['password'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sql  = sprintf(
            'SELECT `%s` AS id, `%s` AS role_id, `%s` AS parent_id FROM `%s`',
            $idField,
            $roleCol,
            $parCol,
            $table
        );
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $warnings[] = 'Could not read the role hierarchy from the database (' . $e->getMessage() . '). '
            . 'Roles, effective-role expansion and the hierarchy tree are omitted; '
            . 'everything derived from configuration alone is still reported.';
        return ['roles' => [], 'available' => false];
    }

    $byId = [];
    foreach ($rows as $row) {
        $byId[(string) $row['id']] = $row;
    }

    $roles = [];
    foreach ($byId as $row) {
        $parentPk = $row['parent_id'];
        $parent   = null;
        if ($parentPk !== null && isset($byId[(string) $parentPk])) {
            $parent = (string) $byId[(string) $parentPk]['role_id'];
        } elseif ($parentPk !== null) {
            $warnings[] = sprintf(
                'Role "%s" names parent_id %s, which is not a row in %s — treated as a root role.',
                (string) $row['role_id'],
                (string) $parentPk,
                $table
            );
        }
        $roles[(string) $row['role_id']] = $parent;
    }

    ksort($roles);

    return ['roles' => $roles, 'available' => true];
}

/**
 * @param array<string, string|null> $parents role => parent role
 * @return list<string> the role's ancestors, nearest first
 */
function ancestorsOf(string $role, array $parents): array
{
    $chain = [];
    $seen  = [$role => true];
    $cur   = $parents[$role] ?? null;
    while ($cur !== null && ! isset($seen[$cur])) {
        $chain[]    = $cur;
        $seen[$cur] = true;
        $cur        = $parents[$cur] ?? null;
    }

    return $chain;
}

/**
 * @param array<string, string|null> $parents role => parent role
 * @return array<string, list<string>> role => every role beneath it, sorted
 */
function descendantMap(array $parents): array
{
    $descendants = [];
    foreach (array_keys($parents) as $role) {
        $descendants[$role] = [];
    }
    foreach (array_keys($parents) as $role) {
        // Every ancestor of $role has $role beneath it. Walking upward once per
        // role is O(depth) and needs no recursion guard beyond ancestorsOf's.
        foreach (ancestorsOf($role, $parents) as $ancestor) {
            $descendants[$ancestor][] = $role;
        }
    }
    foreach ($descendants as $role => $_) {
        sort($descendants[$role]);
    }
    ksort($descendants);

    return $descendants;
}

// ---------------------------------------------------------------------------
// Guards
// ---------------------------------------------------------------------------

/**
 * Collapse the route guard entries the way BjyAuthorize\Guard\AbstractGuard does.
 *
 * This is the whole reason the tool exists. AbstractGuard::__construct builds
 *
 *     $this->rules[$resource] = ['roles' => ...];
 *
 * — an ASSIGNMENT, not a merge. So when two entries in the merged config name
 * the same route, the LAST one wins outright and every earlier one is discarded
 * with no warning anywhere. Reading the config files top to bottom therefore
 * gives the wrong answer for any repeated route, which is exactly why each such
 * route is flagged below.
 *
 * (Merge order: module config first, in config/modules.config.php order, then
 * config/autoload/*.global.php, then *.local.php. That is why acl.global.php can
 * narrow a guard JUser declared.)
 *
 * @param array<string, mixed> $config
 * @return array{
 *     winning: array<string, array{roles: list<string|null>, assertion: string|null}>,
 *     declarations: array<string, list<array{roles: list<string|null>, assertion: string|null}>>
 * }
 */
function routeGuards(array $config): array
{
    $entries = $config['bjyauthorize']['guards']['BjyAuthorize\Guard\Route'] ?? [];

    $winning      = [];
    $declarations = [];

    foreach ($entries as $entry) {
        if (! isset($entry['route'])) {
            continue;
        }
        $route = (string) $entry['route'];
        $rule  = [
            // (array) cast mirrors AbstractGuard; a scalar `roles` is legal.
            'roles'     => array_values((array) ($entry['roles'] ?? [])),
            'assertion' => isset($entry['assertion']) ? (string) $entry['assertion'] : null,
        ];

        $declarations[$route][] = $rule;
        $winning[$route]        = $rule; // last write wins, as in AbstractGuard
    }

    ksort($winning);
    ksort($declarations);

    return ['winning' => $winning, 'declarations' => $declarations];
}

/**
 * @param array<string, mixed> $config
 * @return list<array{controllers: list<string>, actions: list<string|null>, roles: list<string|null>,
 *                    assertion: string|null}>
 */
function controllerGuards(array $config): array
{
    $entries = $config['bjyauthorize']['guards']['BjyAuthorize\Guard\Controller'] ?? [];

    $out = [];
    foreach ($entries as $entry) {
        $out[] = [
            'controllers' => array_map('strval', array_values((array) ($entry['controller'] ?? []))),
            'actions'     => isset($entry['action']) ? array_values((array) $entry['action']) : [null],
            'roles'       => array_values((array) ($entry['roles'] ?? [])),
            'assertion'   => isset($entry['assertion']) ? (string) $entry['assertion'] : null,
        ];
    }

    return $out;
}

/**
 * Expand a guard's declared roles into every role that actually gets in.
 *
 * Two Laminas\Permissions\Acl semantics do the work:
 *
 *  1. An allow on a role applies to that role's DESCENDANTS too — a child role
 *     inherits its parent's allows (Acl::roleDFS* walks upward from the queried
 *     role). So `roles => ['user']` admits every role beneath `user`. Note the
 *     direction: user_role.parent_id points at the more-privileged ancestor, so
 *     `sch_administrator -> sch_general_moderator -> ... -> user` means allowing
 *     `user` admits sch_administrator, not the reverse.
 *  2. A `null` in the roles list is not a role. Acl::setRule maps it to the
 *     `allRoles` pseudo-parent, which Acl::isAllowed consults after the
 *     role-specific search fails — for EVERY identity. So null means public:
 *     everyone, authenticated or not.
 *
 * The identity is a meta-role (`bjyauthorize-identity`) that inherits the signed
 * -in user's roles plus a per-user `user_<id>` role, so asking "which roles get
 * in" is the right question — holding any effective role suffices.
 *
 * @param list<string|null> $declared
 * @param array<string, list<string>> $descendants
 * @param list<string> $allRoles
 * @return array{effective: list<string>, public: bool, unknown: list<string>}
 */
function effectiveRoles(array $declared, array $descendants, array $allRoles): array
{
    $isPublic = in_array(null, $declared, true);
    $unknown  = [];
    $set      = [];

    foreach ($declared as $role) {
        if ($role === null) {
            continue;
        }
        $role       = (string) $role;
        $set[$role] = true;
        if (! isset($descendants[$role])) {
            // Named in config but absent from user_role. Acl::setRule throws
            // InvalidArgumentException for an unknown role, which would take the
            // whole ACL down on every request — worth shouting about.
            $unknown[] = $role;
            continue;
        }
        foreach ($descendants[$role] as $child) {
            $set[$child] = true;
        }
    }

    if ($isPublic) {
        foreach ($allRoles as $role) {
            $set[$role] = true;
        }
    }

    $effective = array_keys($set);
    sort($effective);
    sort($unknown);

    return ['effective' => $effective, 'public' => $isPublic, 'unknown' => array_values(array_unique($unknown))];
}

// ---------------------------------------------------------------------------
// Resources and rules
// ---------------------------------------------------------------------------

/**
 * Non-route resources declared by BjyAuthorize\Provider\Resource\Config. The
 * provider accepts both a flat list of names and a `name => children` map, and
 * children become ACL child resources (an allow on the parent covers them).
 *
 * @param array<string, mixed> $config
 * @return array{resources: array<string, string|null>, other_providers: list<string>}
 */
function nonRouteResources(array $config): array
{
    $providers = $config['bjyauthorize']['resource_providers'] ?? [];

    $resources = [];
    $others    = [];

    foreach ($providers as $providerClass => $providerConfig) {
        if ((string) $providerClass !== 'BjyAuthorize\Provider\Resource\Config') {
            $others[] = (string) $providerClass;
            continue;
        }
        foreach ((array) $providerConfig as $key => $value) {
            $name             = is_int($key) ? (string) $value : (string) $key;
            $resources[$name] = null;
            if (is_array($value)) {
                foreach ($value as $childKey => $childValue) {
                    $child             = is_int($childKey) ? (string) $childValue : (string) $childKey;
                    $resources[$child] = $name;
                }
            }
        }
    }

    ksort($resources);
    sort($others);

    return ['resources' => $resources, 'other_providers' => $others];
}

/**
 * Rules from BjyAuthorize\Provider\Rule\Config. Shape is
 * [roles, resource(s), privilege(s)?, assertion?] — see Authorize::loadRule.
 * Guard-derived rules are NOT included here; they are the route table.
 *
 * @param array<string, mixed> $config
 * @return array{rules: list<array{type: string, roles: list<string|null>, resources: list<string>,
 *                                 privileges: list<string>, assertion: string|null}>,
 *               other_providers: list<string>}
 */
function configRules(array $config): array
{
    $providers = $config['bjyauthorize']['rule_providers'] ?? [];

    $rules  = [];
    $others = [];

    foreach ($providers as $providerClass => $providerConfig) {
        if ((string) $providerClass !== 'BjyAuthorize\Provider\Rule\Config') {
            $others[] = (string) $providerClass;
            continue;
        }
        foreach (['allow', 'deny'] as $type) {
            foreach ((array) ($providerConfig[$type] ?? []) as $rule) {
                $rule    = array_values((array) $rule);
                $rules[] = [
                    'type'       => $type,
                    'roles'      => array_values((array) ($rule[0] ?? [])),
                    'resources'  => array_map('strval', array_values((array) ($rule[1] ?? []))),
                    'privileges' => isset($rule[2]) && $rule[2] !== null
                        ? array_map('strval', array_values((array) $rule[2]))
                        : [],
                    'assertion'  => isset($rule[3]) ? (string) $rule[3] : null,
                ];
            }
        }
    }

    // Deterministic order independent of how the config happened to be written.
    usort($rules, static fn (array $a, array $b): int
        => [$a['type'], implode(',', $a['resources'])] <=> [$b['type'], implode(',', $b['resources'])]);
    sort($others);

    return ['rules' => $rules, 'other_providers' => $others];
}

// ---------------------------------------------------------------------------
// Assemble
// ---------------------------------------------------------------------------

/** @param list<string|null> $roles */
function formatRoles(array $roles): string
{
    if ($roles === []) {
        return '(none)';
    }
    $out = array_map(static fn ($r): string => $r === null ? '`null`' : (string) $r, $roles);
    sort($out);

    return implode(', ', $out);
}

/**
 * Sort a list of roles that may contain null; null sorts first.
 *
 * @param list<string|null> $roles
 * @return list<string|null>
 */
function sortRoleList(array $roles): array
{
    usort($roles, static fn ($a, $b): int => (string) $a <=> (string) $b);

    return array_values($roles);
}

$config = loadMergedConfig();
$bjy    = $config['bjyauthorize'] ?? [];

$routes       = routeNames($config);
$guards       = routeGuards($config);
$hierarchy    = readRoleHierarchy($config, $warnings);
$parents      = $hierarchy['roles'];
$allRoles     = array_keys($parents);
$descendants  = descendantMap($parents);
$ctrlGuards   = controllerGuards($config);
$resourceInfo = nonRouteResources($config);
$ruleInfo     = configRules($config);
$symfony      = symfonyRoutes();
$shadowInfo   = shadowedBySymfony($config, laminasRoutePaths($config), $routes);
$shadowed     = $shadowInfo['shadowed'];
$uncomparable = $shadowInfo['uncomparable'];

sort($allRoles);

$defaultRole = isset($bjy['default_role']) ? (string) $bjy['default_role'] : null;
// array_key_exists, not isset: a root role's parent is null, and isset() would
// report a perfectly good top-level role such as `guest` as missing.
if ($defaultRole !== null && $hierarchy['available'] && ! array_key_exists($defaultRole, $parents)) {
    $warnings[] = sprintf(
        'default_role "%s" does not exist in the role table; bjyauthorize cannot resolve it.',
        $defaultRole
    );
}

// One row per guarded route.
$rows        = [];
$phantoms    = [];
$duplicates  = [];
$unknownRole = [];

foreach ($guards['winning'] as $route => $rule) {
    $expanded = effectiveRoles($rule['roles'], $descendants, $allRoles);
    $declared = $guards['declarations'][$route];
    $exists   = array_key_exists((string) $route, $routes);

    if ($hierarchy['available']) {
        foreach ($expanded['unknown'] as $r) {
            $unknownRole[$r] = true;
        }
    }
    // Roles named only by a losing declaration still matter: they are what a
    // reader would wrongly believe is in force.
    foreach ($declared as $d) {
        foreach ($d['roles'] as $r) {
            if ($r !== null && ! isset($descendants[(string) $r]) && $hierarchy['available']) {
                $unknownRole[(string) $r] = true;
            }
        }
    }

    $row = [
        'route'             => (string) $route,
        'exists_in_router'  => $exists,
        'matchable'         => $routes[(string) $route] ?? false,
        'declared_roles'    => sortRoleList($rule['roles']),
        'effective_roles'   => $expanded['effective'],
        'public'            => $expanded['public'],
        'anonymous_allowed' => $expanded['public']
            || ($defaultRole !== null && in_array($defaultRole, $expanded['effective'], true)),
        'assertion'         => $rule['assertion'],
        'declaration_count' => count($declared),
        // In config order: the last one is the winner.
        'declarations'      => array_map(
            static fn (array $d): array => [
                'roles'     => sortRoleList($d['roles']),
                'assertion' => $d['assertion'],
            ],
            $declared
        ),
    ];

    $rows[(string) $route] = $row;

    if (! $exists) {
        $phantoms[(string) $route] = $row;
    }
    if (count($declared) > 1) {
        $duplicates[(string) $route] = $row;
    }
}

$unguarded          = [];
$unguardedMatchable = [];
foreach ($routes as $route => $matchable) {
    if (! isset($guards['winning'][$route])) {
        $unguarded[] = (string) $route;
        if ($matchable) {
            $unguardedMatchable[] = (string) $route;
        }
    }
}
sort($unguarded);
sort($unguardedMatchable);

$guardedExisting = array_values(array_filter($rows, static fn (array $r): bool => $r['exists_in_router']));

// Rules carry the same hazard as guards: Acl::allow() resolves every named role
// through the role registry, which throws for one it has never heard of.
if ($hierarchy['available']) {
    foreach ($ruleInfo['rules'] as $rule) {
        foreach ($rule['roles'] as $r) {
            if ($r !== null && ! array_key_exists((string) $r, $parents)) {
                $unknownRole[(string) $r] = true;
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Symfony-served routes: is each one actually checked, and against what?
// ---------------------------------------------------------------------------

// Every non-legacy route in config/symfony/routes.php must say something. Silence is
// the silent-bypass bug this whole section exists to catch, so it is reported first
// and in the strongest terms available here. App\Authorization\RouteGuard also throws
// on such a route at runtime; this makes it visible in the committed snapshot instead
// of only in a stack trace someone has to provoke.
foreach ($symfony['routes'] as $symfonyRoute => $info) {
    if ($info['access']['kind'] !== 'undeclared') {
        continue;
    }
    $warnings[] = sprintf(
        'SILENT BYPASS RISK: the Symfony route "%s" (%s) declares NO authorization. A Symfony-served '
        . 'request never boots laminas-mvc, so no bjyauthorize guard runs for it — a route with no '
        . 'declaration is one nobody has decided about. Give it '
        . 'RouteAccess::guardedBy(\'route/<laminas-route-name>\') or, deliberately, '
        . 'RouteAccess::openToEveryone(\'<why>\') in config/symfony/routes.php.',
        $symfonyRoute,
        $info['path']
    );
}

// A resource nothing defines is not a harmless typo. Authorize::isAllowed() catches
// the ACL registry's InvalidArgumentException and answers false, so the route denies
// *everyone* — including the author, who then goes looking in the wrong file. The
// direction is safe; the silence is not.
foreach ($symfony['routes'] as $symfonyRoute => $info) {
    $resource = $info['access']['resource'];
    if ($resource === null) {
        continue;
    }
    if (str_starts_with($resource, 'route/') && isset($rows[substr($resource, 6)])) {
        continue;
    }
    if (array_key_exists($resource, $resourceInfo['resources'])) {
        continue;
    }
    $warnings[] = sprintf(
        'The Symfony route "%s" (%s) is checked against the ACL resource "%s", which no guard entry and no '
        . 'configured resource defines. Authorize::isAllowed() answers false for an unknown resource, so '
        . 'this route currently denies everyone. Check the spelling against the guard entries above — or, '
        . 'if the resource comes from a provider that builds it from the database at runtime '
        . '(see dynamic_providers), this warning is the price of that provider being invisible here.',
        $symfonyRoute,
        $info['path'],
        $resource
    );
}

// The laminas guard on a shadowed route never runs — a Symfony-served request never
// boots laminas-mvc. Until 2026-08-06 that meant any restricted route was unportable
// and this loop warned about every one of them. Now the question is narrower and more
// useful: does the ported route check the *same* resource its laminas guard did? If
// it does, nothing was lost and there is nothing to say. If it declares openness, or
// names some other resource, then a page that used to be restricted is no longer
// restricted in the same way, and that is exactly the change nothing else would fail
// on.
foreach ($shadowed as $laminasRoute => $info) {
    $row = $rows[$laminasRoute] ?? null;
    if ($row === null || $row['public']) {
        continue;
    }
    // A guard naming the default role is public by another spelling: bjyauthorize
    // hands `guest` to every request without an identity, so an anonymous visitor
    // already held it and there was no restriction for porting to lose. Only a
    // literal `null` was treated as public before, which made every ported /api
    // route look like it had dropped the guard on `api-route-not-found` — a
    // 404 handler declared `['guest', 'user']`.
    if ($defaultRole !== null && in_array($defaultRole, $row['effective_roles'], true)) {
        continue;
    }
    $expected = 'route/' . $laminasRoute;
    if ($info['access']['resource'] === $expected) {
        continue;
    }
    $warnings[] = sprintf(
        'Route "%s" (%s) is now served by the Symfony route "%s", whose bjyauthorize guard restricted it '
        . 'to %s — and the ported route %s instead of checking "%s". A Symfony-served request never runs '
        . 'the laminas guard, so that restriction is not being enforced. Declare '
        . 'RouteAccess::guardedBy(\'%s\'), or unport the route.',
        $laminasRoute,
        $info['path'],
        $info['symfony_route'],
        implode(', ', $row['effective_roles']) ?: '(nobody)',
        $info['access']['kind'] === 'acl'
            ? sprintf('checks "%s"', (string) $info['access']['resource'])
            : ($info['access']['kind'] === 'open' ? 'is declared open to everyone' : 'declares nothing'),
        $expected,
        $expected
    );
}

$unknownRoles = array_keys($unknownRole);
sort($unknownRoles);
if ($unknownRoles !== []) {
    $warnings[] = 'These roles are named by guard entries or rules but do not exist in the role table: '
        . implode(', ', $unknownRoles)
        . '. Acl::setRule throws for an unknown role, so a live one of these fatals every request.';
}

// How the ported routes account for themselves. The identity that must hold is
// checked below: acl + open + undeclared = symfony_served_routes, and undeclared must
// be zero.
$symfonyByKind = ['acl' => 0, 'open' => 0, 'undeclared' => 0];
foreach ($symfony['routes'] as $info) {
    $symfonyByKind[$info['access']['kind']] = ($symfonyByKind[$info['access']['kind']] ?? 0) + 1;
}

$counts = [
    'controller_guard_entries' => count($ctrlGuards),
    'guarded_routes_existing'  => count($guardedExisting),
    'guarded_routes_total'     => count($rows),
    'non_route_resources'      => count($resourceInfo['resources']),
    'phantom_guard_entries'    => count($phantoms),
    'roles'                    => count($allRoles),
    'route_guard_entries'      => array_sum(array_map('count', $guards['declarations'])),
    'routes_declared_twice'    => count($duplicates),
    'routes_shadowed_by_symfony' => count($shadowed),
    // Matchable laminas routes the shadow check could not decide either way.
    // Should be 0: every entry is a route about which this tool's silence means
    // nothing, which is the state that let the batch-5 regression through.
    'routes_uncomparable'      => count($uncomparable),
    'rules_from_rule_config'   => count($ruleInfo['rules']),
    'symfony_served_routes'    => count($symfony['routes']),
    'symfony_routes_acl_checked' => $symfonyByKind['acl'],
    'symfony_routes_open'        => $symfonyByKind['open'],
    // Must be 0. Anything else is a route nobody has decided about.
    'symfony_routes_undeclared'  => $symfonyByKind['undeclared'],
    'total_routes'             => count($routes),
    'unguarded_routes'         => count($unguarded),
    // The subset that is a real endpoint: reachable by nobody rather than merely
    // a Part-route namespace that can never be matched anyway.
    'unguarded_routes_matchable' => count($unguardedMatchable),
];
ksort($counts);

// The identity that must hold, and is asserted rather than assumed: every route
// the router knows is either guarded or not. Phantoms are not routes, so they
// sit outside it.
if ($counts['guarded_routes_existing'] + $counts['unguarded_routes'] !== $counts['total_routes']) {
    $warnings[] = sprintf(
        'COUNT IDENTITY BROKEN: guarded(existing) %d + unguarded %d != total routes %d.',
        $counts['guarded_routes_existing'],
        $counts['unguarded_routes'],
        $counts['total_routes']
    );
}

// The same assertion for the other front controller: every Symfony-served route is
// either ACL-checked or deliberately open, and nothing falls between the two.
if (
    $counts['symfony_routes_acl_checked'] + $counts['symfony_routes_open']
    + $counts['symfony_routes_undeclared'] !== $counts['symfony_served_routes']
) {
    $warnings[] = sprintf(
        'COUNT IDENTITY BROKEN: symfony acl-checked %d + open %d + undeclared %d != symfony served %d.',
        $counts['symfony_routes_acl_checked'],
        $counts['symfony_routes_open'],
        $counts['symfony_routes_undeclared'],
        $counts['symfony_served_routes']
    );
}

// ---------------------------------------------------------------------------
// Output: JSON
// ---------------------------------------------------------------------------

if ($format === 'json') {
    $roleTree = [];
    foreach ($allRoles as $role) {
        $roleTree[$role] = [
            'ancestors'   => ancestorsOf($role, $parents), // nearest first; order is meaningful
            'descendants' => $descendants[$role] ?? [],
            'parent'      => $parents[$role],
        ];
    }
    ksort($roleTree);

    $jsonRows = $rows;
    // Sort the keys inside each row too, so the shape is independent of the
    // order this file happens to build it in.
    foreach ($jsonRows as $route => $row) {
        ksort($row);
        $jsonRows[$route] = $row;
    }
    ksort($jsonRows);
    $jsonPhantoms = array_keys($phantoms);
    sort($jsonPhantoms);
    $jsonDuplicates = array_keys($duplicates);
    sort($jsonDuplicates);

    $ctrlJson = array_map(
        static fn (array $g): array => [
            'actions'         => $g['actions'],
            'assertion'       => $g['assertion'],
            'controllers'     => $g['controllers'],
            'effective_roles' => effectiveRoles($g['roles'], $descendants, $allRoles)['effective'],
            'roles'           => sortRoleList($g['roles']),
        ],
        $ctrlGuards
    );
    usort($ctrlJson, static fn (array $a, array $b): int => implode(',', $a['controllers'])
        <=> implode(',', $b['controllers']));

    sort($warnings);

    $out = [
        'bjyauthorize' => [
            'cache_enabled'       => (bool) ($bjy['cache_enabled'] ?? false),
            'default_role'        => $defaultRole,
            'identity_provider'   => isset($bjy['identity_provider']) ? (string) $bjy['identity_provider'] : null,
            'resource_providers'  => array_values(array_map('strval', array_keys($bjy['resource_providers'] ?? []))),
            'role_providers'      => array_values(array_map('strval', array_keys($bjy['role_providers'] ?? []))),
            'rule_providers'      => array_values(array_map('strval', array_keys($bjy['rule_providers'] ?? []))),
            'unauthorized_strategy' => isset($bjy['unauthorized_strategy'])
                ? (string) $bjy['unauthorized_strategy']
                : null,
        ],
        'controller_guards' => $ctrlJson,
        'counts'            => $counts,
        // Providers that build resources/rules from the database at runtime, so
        // their contents cannot appear in a config-derived snapshot.
        'dynamic_providers' => [
            'resource' => $resourceInfo['other_providers'],
            'rule'     => $ruleInfo['other_providers'],
        ],
        'guarded_routes'    => $jsonRows,
        'laminas_routes_shadowed_by_symfony' => array_map(
            static fn (string $name, array $info): array => [
                'laminas_route' => $name,
                'path'          => $info['path'],
                'symfony_route' => $info['symfony_route'],
                // What the (now inert) laminas guard said, so a reader can see at
                // a glance whether porting the route changed who gets in. The
                // roles themselves are one lookup away in guarded_routes; copying
                // 43 of them per entry here would only bury the diff.
                'was_guarded'   => isset($rows[$name]),
                'was_public'    => $rows[$name]['public'] ?? null,
                // What the ported route checks in the laminas guard's place. Equal to
                // 'route/' . laminas_route means the restriction carried over intact;
                // anything else on a non-public route is warned about above.
                'now_checks'    => $info['access']['resource'],
                'access_kind'   => $info['access']['kind'],
                // The concrete URL that matched. Reproducible with curl, and the
                // thing to look at first when a row here surprises you.
                'probe'         => $info['probe'],
            ],
            array_keys($shadowed),
            array_values($shadowed)
        ),
        // Routes the shadow check could not decide either way. Empty is the goal;
        // a non-empty list is a known blind spot rather than a clean bill of health.
        'laminas_routes_uncomparable' => array_map(
            static fn (string $name, array $info): array => [
                'laminas_route' => $name,
                'path'          => $info['path'],
                'reason'        => $info['reason'],
            ],
            array_keys($uncomparable),
            array_values($uncomparable)
        ),
        'non_route_resources' => $resourceInfo['resources'],
        'notes'             => [
            'guard_assign_not_merge' => 'BjyAuthorize\Guard\AbstractGuard assigns $rules[$resource], so for a '
                . 'route named by more than one entry the LAST entry in the merged config wins and the others '
                . 'are discarded silently. guarded_routes[].declarations is in config order; the last element '
                . 'is the winner and is what declared_roles/effective_roles reflect.',
            'null_role'              => 'A null in a roles list is not the default role: Acl::setRule maps it to '
                . 'the allRoles pseudo-parent, which isAllowed() consults for every identity. It means public.',
            'effective_roles'        => 'declared roles plus all their descendants in the role tree, because a '
                . 'child role inherits its parent\'s allows. parent_id in user_role points at the '
                . 'more-privileged ancestor.',
            'unguarded_routes'       => 'The Route guard is default-deny: a route with no guard entry is '
                . 'reachable by nobody. unguarded_routes_matchable is the subset that is a real endpoint; '
                . 'the rest are Part-route parents with may_terminate false, which can never be matched.',
            'symfony_routes'         => 'Paths the Symfony kernel serves itself (config/symfony/routes.php, '
                . 'live only where SYMFONY_KERNEL=1 — the capsule, not production yet). BjyAuthorize\'s own '
                . 'guard cannot run for them, because a Symfony-served request never boots laminas-mvc; '
                . 'App\Authorization\RouteGuard runs on kernel.request instead and asks the ACL about the '
                . 'resource each route declares in access.resource. That resource is one of THIS file\'s — '
                . 'route/<laminas-route-name> — so a single guard entry governs both front controllers. '
                . 'access.kind is "acl" when the route is checked, "open" when it deliberately is not '
                . '(access.reason says why), and "undeclared" when nobody decided, which is a bug and is '
                . 'warned about. access.denial_style is how a refusal is shaped: html means 302 to the '
                . 'sign-in page for an anonymous visitor and 403 with the error page for a signed-in one, '
                . 'json means 401/403 with a JSON body and no redirect.',
        ],
        'phantom_guard_entries' => $jsonPhantoms,
        'roles'                 => $roleTree,
        'roles_available'       => $hierarchy['available'],
        'routes_declared_more_than_once' => $jsonDuplicates,
        'rules'                 => $ruleInfo['rules'],
        'symfony_routes'        => $symfony['routes'],
        'unguarded_routes'      => $unguarded,
        'unguarded_routes_matchable' => $unguardedMatchable,
        'unknown_roles_named_in_config' => $unknownRoles,
        'warnings'              => $warnings,
    ];

    ksort($out);
    fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    exit(0);
}

// ---------------------------------------------------------------------------
// Output: Markdown
// ---------------------------------------------------------------------------

$o = static function (string $line = ''): void {
    fwrite(STDOUT, $line . "\n");
};

$o('# Authorization baseline (BjyAuthorize + laminas-permissions-acl)');
$o();
$o('Generated by `tools/acl-table.php` from the merged module configuration plus the `user_role`');
$o('table. No timestamp on purpose: this file is meant to `diff` cleanly.');
$o();

if ($warnings !== []) {
    $o('## Warnings');
    $o();
    foreach ($warnings as $w) {
        $o('- ' . $w);
    }
    $o();
}

$o('## How to read this');
$o();
$o('- **Default deny.** `BjyAuthorize\Guard\Route` is enabled, so a route with no guard entry is');
$o('  reachable by nobody. Those routes are listed in their own section below.');
$o('- **Guards assign, they do not merge.** `AbstractGuard::__construct` does');
$o('  `$this->rules[$resource] = [...]`, so when the merged config names the same route in two');
$o('  entries the **last one wins outright** and the earlier one is discarded with no warning.');
$o('  Merge order is: module config (in `config/modules.config.php` order), then');
$o('  `config/autoload/*.global.php`, then `*.local.php`. Every multiply-declared route is flagged.');
$o('- **`null` in a roles list means public, not "guest".** `Acl::setRule` turns a null role into the');
$o('  `allRoles` pseudo-parent, which `isAllowed()` falls back to for *every* identity — signed in or');
$o('  not. So `[\'user\', \'guest\', null]` is simply "everyone"; the two named roles are decoration.');
$o('- **Effective roles = declared roles + all their descendants.** A child role inherits its');
$o('  parent\'s allows. In `user_role`, `parent_id` points at the *more privileged* ancestor');
$o('  (`sch_administrator` → … → `user`), so allowing `user` admits nearly every role.');
$o('- The identity is a meta-role (`bjyauthorize-identity`) inheriting the signed-in user\'s roles');
$o('  plus a per-user `user_<id>` role, so "which roles get in" is the right question: holding any');
$o('  one effective role is enough.');
$o();

$o('## Configuration');
$o();
$o('| key | value |');
$o('| --- | --- |');
$o('| `default_role` | ' . ($defaultRole ?? '(unset)') . ' |');
$o('| `identity_provider` | `' . (string) ($bjy['identity_provider'] ?? '(unset)') . '` |');
$o('| `unauthorized_strategy` | `' . (string) ($bjy['unauthorized_strategy'] ?? '(unset)') . '` |');
$o('| `cache_enabled` | ' . (($bjy['cache_enabled'] ?? false) ? 'true' : 'false') . ' |');
foreach (['role_providers', 'resource_providers', 'rule_providers'] as $key) {
    $names = array_map('strval', array_keys($bjy[$key] ?? []));
    sort($names);
    $o('| `' . $key . '` | ' . ($names === [] ? '(none)' : '`' . implode('`, `', $names) . '`') . ' |');
}
$guardKeys = array_map('strval', array_keys($bjy['guards'] ?? []));
sort($guardKeys);
$o('| `guards` | ' . ($guardKeys === [] ? '(none)' : '`' . implode('`, `', $guardKeys) . '`') . ' |');
$o();

$o('## Summary counts');
$o();
$o('| metric | count |');
$o('| --- | --- |');
foreach ($counts as $name => $value) {
    $o('| ' . str_replace('_', ' ', (string) $name) . ' | ' . $value . ' |');
}
$o();
$o('`guarded routes existing` + `unguarded routes` = `total routes` (' .
    $counts['guarded_routes_existing'] . ' + ' . $counts['unguarded_routes'] . ' = ' .
    $counts['total_routes'] . '). Phantom entries are excluded because they are not routes.');
$o();

// -- Symfony-served routes --------------------------------------------------

$o('## Routes served by the Symfony kernel');
$o();
$o('These paths are matched by `config/symfony/routes.php` before laminas-mvc is ever started, so');
$o('`BjyAuthorize\Guard\Route` — a listener on `MvcEvent::EVENT_ROUTE` — never runs for them.');
$o('`App\Authorization\RouteGuard` runs on `kernel.request` instead, and asks the ACL about a resource');
$o('**each route declares for itself**. That resource is one of the ones above: `route/<name>`, the');
$o('same key the laminas guard uses. So the guard entries in this file govern *both* front');
$o('controllers, and tightening one tightens both.');
$o();
$o('Three states, and the third is a bug:');
$o();
$o('- **checked** — `RouteAccess::guardedBy(\'route/…\')`. The resource column says what it asks about');
$o('  and the grant column what that resource allows, copied from the guard table above.');
$o('- **open** — `RouteAccess::openToEveryone(\'<why>\')`. No check, stated on purpose, reason shown.');
$o('- **undeclared** — nothing at all. `RouteGuard` throws `UndeclaredRouteAccess` when such a route');
$o('  is reached, and it is warned about at the top of this file. This is the silent-bypass shape the');
$o('  tool exists to catch: a route that lost its guard keeps working and simply admits everyone.');
$o();
$o('Live only where `SYMFONY_KERNEL=1`: the capsule today, production not yet (docs/strangler.md).');
$o('`App\Http\LegacyBridge`, the catch-all that hands everything else to laminas-mvc, is excluded —');
$o('it matches every path by design, and laminas-mvc runs its own guard behind it.');
$o();
if ($symfony['routes'] === []) {
    $o('_None._');
} else {
    $o('| symfony route | path | checked against | who that allows | denial | controller |');
    $o('| --- | --- | --- | --- | --- | --- |');
    foreach ($symfony['routes'] as $name => $route) {
        $access   = $route['access'];
        $resource = $access['resource'];
        if ($access['kind'] === 'undeclared') {
            $checked = '**UNDECLARED**';
            $grant   = '**nobody decided — see Warnings**';
        } elseif ($resource === null) {
            $checked = '_open_';
            $grant   = 'everyone — reason below';
        } else {
            $checked = '`' . $resource . '`';
            $guarded = str_starts_with($resource, 'route/') ? ($rows[substr($resource, 6)] ?? null) : null;
            if ($guarded === null) {
                $grant = '**no such resource — denies everyone**';
            } elseif ($guarded['public']) {
                $grant = '**public** (`null` in its roles)';
            } else {
                $grant = implode(', ', $guarded['effective_roles']) ?: '(nobody)';
            }
        }
        $o(sprintf(
            '| `%s` | `%s` | %s | %s | %s | `%s` |',
            $name,
            $route['path'],
            $checked,
            $grant,
            $access['denial_style'],
            $route['controller']
        ));
    }
}
$o();

// The reasons, out of the table so it stays readable — but in the snapshot, because a
// reason that stops being true is how an open route becomes a hole.
$openReasons = [];
foreach ($symfony['routes'] as $name => $route) {
    if ($route['access']['kind'] === 'open') {
        $openReasons[(string) $name] = (string) $route['access']['reason'];
    }
}
if ($openReasons !== []) {
    $o('#### Why the open ones are open');
    $o();
    $o('Each is the string passed to `RouteAccess::openToEveryone()`. Declaring openness is a statement,');
    $o('not a default, and this is where the statement is reviewed.');
    $o();
    foreach ($openReasons as $name => $reason) {
        $o('- `' . $name . '` — ' . $reason);
    }
    $o();
}

$o('### Laminas routes now shadowed by one of them');
$o();
$o('A laminas route whose path a Symfony route claims first. Matched with the real `UrlMatcher`, not');
$o('by comparing strings. Laminas paths carry no locale prefix here because there is none in the');
$o('config — `SlmLocale\Strategy\UriPathStrategy` strips `/en` before routing — which is why the');
$o('Symfony side declares both the bare and the prefixed form.');
$o();
$o('A parameterized route is matched by *probe*: its pattern is instantiated into a concrete URL,');
$o('each parameter replaced by a value generated from — and then checked against — its own');
$o('constraint. The probe column is that URL, so any row here can be reproduced with `curl` rather');
$o('than taken on trust. Before 2026-08-14 the pattern itself was handed to the matcher, which');
$o('matches URLs and not patterns, so it threw and every parameterized route was skipped: 0 of the');
$o('31 rows had a parameter in it and ~90 routes went unexamined. That is the blind spot that let');
$o('nine guarded routes become unreachable without a word from this tool.');
$o();
$o('The guard column is what bjyauthorize *would* have enforced here and no longer does; the last');
$o('column is what the ported route checks in its place. Those two agreeing — `route/<the same');
$o('route>` — is what "porting changed nothing about who gets in" now means. A restricted route whose');
$o('shadow checks something else, or nothing, is a real change of authorization and is reported as a');
$o('warning at the top of this file.');
$o();
if ($shadowed === []) {
    $o('_None._');
} else {
    $o('| laminas route | path | probe | shadowed by | its (now inert) guard | what the shadow checks |');
    $o('| --- | --- | --- | --- | --- | --- |');
    foreach ($shadowed as $laminasRoute => $info) {
        $row = $rows[$laminasRoute] ?? null;
        if ($row === null) {
            $guard = 'no guard entry — was reachable by nobody';
        } elseif ($row['public']) {
            $guard = '**public** (`null` in its roles)';
        } elseif ($defaultRole !== null && in_array($defaultRole, $row['effective_roles'], true)) {
            $guard = sprintf('**public** (names the default role `%s`)', $defaultRole);
        } else {
            $guard = 'restricted to ' . (implode(', ', $row['effective_roles']) ?: '(nobody)');
        }
        $resource = $info['access']['resource'];
        if ($resource === null) {
            $now = $info['access']['kind'] === 'open' ? '_open, deliberately_' : '**UNDECLARED**';
        } elseif ($resource === 'route/' . $laminasRoute) {
            $now = '`' . $resource . '` — **the same resource**';
        } else {
            $now = '`' . $resource . '` — a *different* resource';
        }
        $o(sprintf(
            '| `%s` | `%s` | `%s` | `%s` | %s | %s |',
            $laminasRoute,
            $info['path'],
            // A route with no parameters is its own probe; saying so twice is
            // noise in a table this wide.
            $info['probe'] === $info['path'] ? '—' : $info['probe'],
            $info['symfony_route'],
            $guard,
            $now
        ));
    }
}
$o();

$o('### Laminas routes the shadow check could not decide');
$o();
$o('A matchable laminas route whose path could not be turned into a concrete URL, so neither');
$o('"shadowed" nor "not shadowed" was established for it. This section exists so that this tool');
$o('being quiet about a route is distinguishable from it having *checked* the route — the two were');
$o('the same thing until 2026-08-14, and telling them apart is the whole point.');
$o();
$o('Empty is the goal. A non-empty list is a known blind spot, and a route in it wants either a');
$o('constraint this tool can sample or a note in `docs/BACKLOG.md` saying why it cannot have one.');
$o();
if ($uncomparable === []) {
    $o('_None — every matchable laminas route was compared._');
} else {
    $o('| laminas route | path | why not |');
    $o('| --- | --- | --- |');
    foreach ($uncomparable as $laminasRoute => $info) {
        $o(sprintf(
            '| `%s` | %s | %s |',
            $laminasRoute,
            $info['path'] === null ? '_uncomposable_' : '`' . $info['path'] . '`',
            $info['reason']
        ));
    }
}
$o();

// -- Role hierarchy ---------------------------------------------------------

$o('## Role hierarchy');
$o();
if (! $hierarchy['available']) {
    $o('_Unavailable: the role table could not be read (see Warnings)._');
    $o();
} else {
    $children = [];
    foreach ($parents as $role => $parent) {
        $children[$parent ?? ''][] = $role;
    }
    foreach ($children as $k => $_) {
        sort($children[$k]);
    }

    $o('```');
    $printTree = static function (string $parent, int $depth) use (&$printTree, $children, $o, $defaultRole): void {
        foreach ($children[$parent] ?? [] as $role) {
            $o(str_repeat('    ', $depth) . $role . ($role === $defaultRole ? '   <- default_role' : ''));
            $printTree($role, $depth + 1);
        }
    };
    $printTree('', 0);
    $o('```');
    $o();
    $o('Indentation is inheritance: an indented role inherits everything allowed to the role above it.');
    $o();
    $o('| role | parent | inherits from (all ancestors, nearest first) | roles beneath it |');
    $o('| --- | --- | --- | --- |');
    foreach ($allRoles as $role) {
        $anc = ancestorsOf($role, $parents);
        $o(sprintf(
            '| %s | %s | %s | %s |',
            $role,
            $parents[$role] ?? '(root)',
            $anc === [] ? '(none)' : implode(' → ', $anc),
            $descendants[$role] === [] ? '(none)' : implode(', ', $descendants[$role])
        ));
    }
    $o();
}

// -- Main table -------------------------------------------------------------

$o('## Guarded routes');
$o();
$o('One row per route named by a guard entry, showing the **winning** entry only.');
$o('`dup` marks a route declared more than once — the losing declarations are itemized after the table.');
$o();
$o('| route | in router | roles in winning entry | effective roles (count) | anon? | dup |');
$o('| --- | --- | --- | --- | --- | --- |');
ksort($rows);
foreach ($rows as $route => $row) {
    $o(sprintf(
        '| `%s` | %s | %s | %s | %s | %s |',
        $route,
        $row['exists_in_router']
            // A guard on a non-terminating Part parent can never fire, but it
            // still declares the `route/<name>` resource views ask about.
            ? ($row['matchable'] ? 'yes' : 'yes, but not an endpoint')
            : '**NO**',
        formatRoles($row['declared_roles']) . ($row['assertion'] !== null
            ? ' _(assertion: `' . $row['assertion'] . '`)_'
            : ''),
        $row['public']
            ? '**everyone (public)** — ' . count($row['effective_roles']) . ' named roles plus anonymous'
            : (implode(', ', $row['effective_roles']) ?: '(none)') . ' (' . count($row['effective_roles']) . ')',
        $row['anonymous_allowed'] ? 'yes' : 'no',
        $row['declaration_count'] > 1 ? '**dup ×' . $row['declaration_count'] . '**' : ''
    ));
}
$o();

$o('### Routes declared by more than one guard entry');
$o();
if ($duplicates === []) {
    $o('_None._');
} else {
    $o('The last declaration in merge order wins; the others are silently discarded.');
    $o();
    $o('| route | declaration order (last wins) |');
    $o('| --- | --- |');
    foreach ($duplicates as $route => $row) {
        $parts = [];
        $last  = count($row['declarations']) - 1;
        foreach ($row['declarations'] as $i => $d) {
            $parts[] = ($i === $last ? '**WINS**: ' : 'discarded: ') . formatRoles($d['roles']);
        }
        $o('| `' . $route . '` | ' . implode('<br>', $parts) . ' |');
    }
}
$o();

// -- Unguarded --------------------------------------------------------------

$o('## Routes with no guard entry (default deny — nobody can reach these)');
$o();
if ($unguarded === []) {
    $o('_None._');
} else {
    $o('`endpoint?` = no means the name is a Part-route parent with `may_terminate` false: it can never be');
    $o('the matched route name, so the missing guard costs nothing. The `yes` rows are the real finding —');
    $o('' . count($unguardedMatchable) . ' of the ' . count($unguarded) . ' are endpoints reachable by nobody.');
    $o();
    $o('| route | endpoint? |');
    $o('| --- | --- |');
    foreach ($unguarded as $route) {
        $o('| `' . $route . '` | ' . (($routes[$route] ?? false) ? '**yes**' : 'no') . ' |');
    }
}
$o();

// -- Phantoms ---------------------------------------------------------------

$o('## Phantom guard entries (guard names a route the router does not have)');
$o();
$o('Each still declares the ACL resource `route/<name>`, so `isAllowed(\'route/<name>\')` in a view can');
$o('answer **true** for a route the router cannot assemble — see');
$o('`test/Integration/AclGuardRouteDriftTest.php`.');
$o();
if ($phantoms === []) {
    $o('_None._');
} else {
    $o('| route named | roles in winning entry |');
    $o('| --- | --- |');
    foreach ($phantoms as $route => $row) {
        $o('| `' . $route . '` | ' . formatRoles($row['declared_roles']) . ' |');
    }
}
$o();

// -- Non-route resources and rules -----------------------------------------

$o('## Non-route resources');
$o();
if ($resourceInfo['resources'] === []) {
    $o('_None._');
} else {
    $o('| resource | parent resource |');
    $o('| --- | --- |');
    foreach ($resourceInfo['resources'] as $name => $parent) {
        $o('| `' . $name . '` | ' . ($parent === null ? '(root)' : '`' . $parent . '`') . ' |');
    }
}
if ($resourceInfo['other_providers'] !== []) {
    $o();
    $o('**Not in this snapshot:** these resource providers build their resources from database rows at');
    $o('request time (one resource per library / event text), so no config-derived table can list them: `'
        . implode('`, `', $resourceInfo['other_providers']) . '`.');
}
$o();

$o('## Rules from `rule_providers` (guard-derived rules are the route table above)');
$o();
if ($ruleInfo['rules'] === []) {
    $o('_None._');
} else {
    $o('| type | roles | effective roles | resource(s) | privileges | assertion |');
    $o('| --- | --- | --- | --- | --- | --- |');
    foreach ($ruleInfo['rules'] as $rule) {
        $exp = effectiveRoles($rule['roles'], $descendants, $allRoles);
        $o(sprintf(
            '| %s | %s | %s | %s | %s | %s |',
            $rule['type'],
            formatRoles($rule['roles']),
            $exp['public'] ? '**everyone (public)**' : (implode(', ', $exp['effective']) ?: '(none)'),
            '`' . implode('`, `', $rule['resources']) . '`',
            $rule['privileges'] === [] ? '(all)' : implode(', ', $rule['privileges']),
            $rule['assertion'] ?? '(none)'
        ));
    }
}
if ($ruleInfo['other_providers'] !== []) {
    $o();
    $o('**Not in this snapshot:** these rule providers generate row-level rules from the database at request');
    $o('time — a `show`/`checkout`/`administrate` allow per library row and per event text — so the parity');
    $o('check for them has to be behavioural, not textual: `'
        . implode('`, `', $ruleInfo['other_providers']) . '`.');
}
$o();

$o('## Controller guard entries');
$o();
if ($ctrlGuards === []) {
    $o('_None: `BjyAuthorize\Guard\Controller` is not configured, so all route-level authorization is');
    $o('the route guard above._');
} else {
    $o('| controller(s) | action(s) | roles | effective roles |');
    $o('| --- | --- | --- | --- |');
    foreach ($ctrlGuards as $g) {
        $exp = effectiveRoles($g['roles'], $descendants, $allRoles);
        $o(sprintf(
            '| `%s` | %s | %s | %s |',
            implode('`, `', $g['controllers']),
            implode(', ', array_map(static fn ($a): string => $a === null ? '(all)' : (string) $a, $g['actions'])),
            formatRoles($g['roles']),
            $exp['public'] ? '**everyone (public)**' : (implode(', ', $exp['effective']) ?: '(none)')
        ));
    }
}
$o();

exit(0);
