<?php

/**
 * Wiring for the fuzz harness: vendor autoload plus the harness's own classes.
 *
 * There is no autoloader for `test/`, so the existing suites require what they
 * need at the top of each file (`test/bootstrap.php` requires SmokeTestCase.php;
 * every integration test requires vendor/autoload.php itself). This file follows
 * that convention rather than introducing a PSR-4 mapping for tests, which would
 * mean touching composer.json's autoload block for no functional gain.
 *
 * Both test classes and `regenerate-baseline.php` require this, which is also what
 * guarantees they compute gaps with the same code — see FormGapCollector.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/SchemaColumnWidths.php';
require_once __DIR__ . '/ElementDefinitionScanner.php';
require_once __DIR__ . '/FormRepository.php';
require_once __DIR__ . '/FormGapCollector.php';
require_once __DIR__ . '/HostileInputCorpus.php';
require_once __DIR__ . '/HostileInputDriver.php';
require_once __DIR__ . '/GapBaseline.php';
