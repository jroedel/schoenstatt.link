<?php

/**
 * Regenerate `test/Db/sql-builder-surface.php`.
 *
 *     docker compose exec -T -u www-data app php test/Db/regenerate-sql-builder-surface.php
 *
 * Read the diff. A changed statement is a statement the server would receive differently,
 * and a changed value list is a different value bound in a different position.
 */

declare(strict_types=1);

chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
require __DIR__ . '/SqlBuilderCases.php';

use SchoenstattTest\Db\SqlBuilderCases;

$surface = [];
foreach (SqlBuilderCases::all() as $name => $build) {
    [$sql, $values]  = $build()->render();
    $surface[$name] = ['sql' => $sql, 'values' => $values];
}

$header = <<<'PHP_HEADER'
<?php

/**
 * What the SQL builder writes, for every statement shape the four repositories assemble.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php test/Db/regenerate-sql-builder-surface.php
 *
 * Keyed case => ['sql' => the statement, 'values' => what its placeholders bind, in order].
 *
 * This is the builder's contract, and it is a different one from `test/Db/sql-surface.txt`:
 * that recording is of the statements the *server* received during a full pass over the
 * site, normalised, and it says nothing about which values were bound or in what order.
 * Here nothing is normalised and the values are part of the answer, so a rendering rule
 * that moves a placeholder without changing the statement's shape shows up as a diff.
 *
 * `SchoenstattTest\Db\SqlBuilderCases` is where the cases come from and why each is here.
 *
 * @return array<string, array{sql: string, values: list<mixed>}>
 */

declare(strict_types=1);

return
PHP_HEADER;

$export = var_export($surface, true);

file_put_contents(__DIR__ . '/sql-builder-surface.php', $header . ' ' . $export . ";\n");

printf("%d cases written to test/Db/sql-builder-surface.php\n", count($surface));
