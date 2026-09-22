<?php

/**
 * Regenerates test/Container/container-surface.php.
 *
 * `php composer.phar container-surface-baseline`, in the capsule. Every changed line is an
 * answer the container gives differently than it did — read them, do not regenerate them
 * away.
 */

declare(strict_types=1);

use SchoenstattTest\Container\ContainerSurface;
use SchoenstattTest\Container\RecordedContainer;

chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
//test/ is on no PSR-4 path, for the reason test/bootstrap.php records
require_once __DIR__ . '/RecordedContainer.php';
require_once __DIR__ . '/ContainerSurface.php';

/** @var array<string,mixed> $appConfig */
$appConfig = require 'config/application.config.php';

$surface = ContainerSurface::describe($appConfig);

$export = "<?php\n\n"
    . "/**\n"
    . " * Every name the application container answers to, and what it answers.\n"
    . " *\n"
    . " * Generated: `php composer.phar container-surface-baseline`. Recorded on 2026-09-21\n"
    . " * against `Laminas\\ServiceManager\\ServiceManager`, so that App\\Services\\Container\n"
    . " * could be held to it. Asserted by SchoenstattTest\\Integration\\ContainerSurfaceTest.\n"
    . " *\n"
    . " * `instance` names the sharing group: every entry carrying the same label is the same\n"
    . " * object. `probe` reads state a delegator set. `error` is the root cause a name fails\n"
    . " * with in a console process, which is part of the contract too.\n"
    . " */\n\n"
    . "declare(strict_types=1);\n\n"
    . 'return ' . var_export($surface, true) . ";\n";

file_put_contents(RecordedContainer::FILE, $export);

printf("Recorded %d names to %s\n", count($surface), RecordedContainer::FILE);
