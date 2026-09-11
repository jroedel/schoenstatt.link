<?php

/**
 * Rewrite `test/Rules/rule-surface.php` from the application as it stands.
 *
 *     docker compose exec -T -u www-data app php -d memory_limit=1G \
 *         test/Rules/regenerate-rule-surface.php
 *
 * This file is the oracle for the laminas validator and filter classes. Regenerating it to
 * make a test pass turns it into a record of whatever the code happens to do; read the diff
 * instead — a changed line is a filtered value, a verdict or a message that moved, and the
 * filtered value is what reaches the database.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/RuleSurface.php';

use SchoenstattTest\Rules\RuleSurface;

$surface = RuleSurface::collect();

$header = <<<'PHP_HEADER'
<?php

/**
 * What every filter and validator the specifications name answers, over a fixed corpus.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php -d memory_limit=1G \
 *         test/Rules/regenerate-rule-surface.php
 *
 * Keyed kind => rule case => input label => answer. A filter's answer is the value it
 * returns; a validator's is its verdict and its messages. The case labels carry no class
 * name on purpose, so that replacing the rules moves answers and not keys.
 *
 * See `SchoenstattTest\Rules\RuleCases` for where the cases come from and which three are
 * deliberately absent, and `SchoenstattTest\Rules\RuleSurface` for why each rule is driven
 * through a real input filter rather than constructed.
 *
 * @return array<string, array<string, mixed>>
 */

declare(strict_types=1);

return
PHP_HEADER;

file_put_contents(__DIR__ . '/rule-surface.php', $header . ' ' . var_export($surface, true) . ";\n");

printf("Recorded %d filters and %d validators over %d inputs.\n",
    count($surface['filter']),
    count($surface['validator']),
    count(SchoenstattTest\Rules\RuleCases::inputs())
);
printf("  %s\n", implode("\n  ", array_map(
    static fn(string $k, string $v): string => $k . ': ' . $v,
    array_keys($surface['database-query']),
    $surface['database-query']
)));
printf("Review the diff: a changed line is a filtered value, a verdict or a message.\n");
