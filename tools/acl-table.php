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

$unknownRoles = array_keys($unknownRole);
sort($unknownRoles);
if ($unknownRoles !== []) {
    $warnings[] = 'These roles are named by guard entries or rules but do not exist in the role table: '
        . implode(', ', $unknownRoles)
        . '. Acl::setRule throws for an unknown role, so a live one of these fatals every request.';
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
    'rules_from_rule_config'   => count($ruleInfo['rules']),
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
        ],
        'phantom_guard_entries' => $jsonPhantoms,
        'roles'                 => $roleTree,
        'roles_available'       => $hierarchy['available'],
        'routes_declared_more_than_once' => $jsonDuplicates,
        'rules'                 => $ruleInfo['rules'],
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
