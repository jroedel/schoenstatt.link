<?php

/**
 * Drop-in profiling wiring. Copied into config/autoload/ by tools/perf/run.sh and removed
 * again afterwards; it is not part of any deploy and `git ls-files` never sees it there.
 *
 * Everything is a **delegator**, so no application service is replaced and no application
 * file refers to this harness. Removing the file restores the application exactly.
 *
 * The class_exists() guard matters: the harness classes live in `autoload-dev`, so a
 * `--no-dev` install cannot load them. A copy of this file left behind on such an install
 * must degrade to an empty config rather than fatal on every request.
 */

declare(strict_types=1);

use SchoenstattPerf\AdapterProfilerDelegator;
use SchoenstattPerf\CacheProbeDelegator;

if (! class_exists(AdapterProfilerDelegator::class)) {
    return [];
}

$cacheServices = [
    //Every storage the application builds. Named as strings because two of them are service
    //ids rather than class names, and because this file must not require the packages that
    //own them to be installed.
    'SionModel\PersistentCache',
    'JUser\Cache',
    'JTranslate\Cache\PhraseCache',
];

$delegators = [
    SionModel\Db\Connection::class => [AdapterProfilerDelegator::class],
];
foreach ($cacheServices as $service) {
    $delegators[$service] = [CacheProbeDelegator::class];
}

return [
    'service_manager' => [
        'invokables' => [
            AdapterProfilerDelegator::class => AdapterProfilerDelegator::class,
            CacheProbeDelegator::class      => CacheProbeDelegator::class,
        ],
        'delegators' => $delegators,
    ],
];
