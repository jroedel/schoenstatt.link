<?php

/**
 * Rewrite `test/Rules/uri-surface.php` from the application as it stands.
 *
 *     docker compose exec -T -u www-data app php test/Rules/regenerate-uri-surface.php
 *
 * The `stored` half is the one to read carefully: it is what `SionTable::filterUrl()` puts
 * in a URL column, so a changed line there is a changed row.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/UriSurface.php';

use SchoenstattTest\Rules\UriSurface;

$surface = UriSurface::collect();

$header = <<<'PHP_HEADER'
<?php

/**
 * What `Laminas\Uri\Http` answers, and what `SionTable::filterUrl()` stores because of it.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php test/Rules/regenerate-uri-surface.php
 *
 * `uri` records the six questions the application asks a URI object; `stored` records the
 * row that reaches a URL column, which is the half no form recording can see.
 *
 * See `SchoenstattTest\Rules\UriSurface` for where the corpus comes from and why the 749
 * stored URLs are not in it.
 *
 * @return array<string, array<string, string>>
 */

declare(strict_types=1);

return
PHP_HEADER;

file_put_contents(__DIR__ . '/uri-surface.php', $header . ' ' . var_export($surface, true) . ";\n");

printf("Recorded %d URL shapes.\n", count($surface['uri']));
printf("  %d of them are stored, %d refused.\n",
    count(array_filter($surface['stored'], static fn(string $a): bool => 'null' !== $a)),
    count(array_filter($surface['stored'], static fn(string $a): bool => 'null' === $a))
);
