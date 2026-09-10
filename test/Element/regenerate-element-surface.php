<?php

/**
 * Rewrite `test/Element/element-surface.php` from the application as it stands.
 *
 *     docker compose exec -T -u www-data app php test/Element/regenerate-element-surface.php
 *
 * The file is a **specification**, not a convenience. It exists so the element model of
 * step 6 has something exact to be written against, and regenerating it to make a test
 * pass converts it into a record of whatever the code happens to do. Read the diff: a line
 * that changed is an element that started answering differently, and the whole point is to
 * be told.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/ElementSurface.php';

use SchoenstattTest\Element\ElementSurface;

$surface = ElementSurface::collect();

$header = <<<'PHP_HEADER'
<?php

/**
 * What every element in the application answers, recorded from `Laminas\Form\Element\*`.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php test/Element/regenerate-element-surface.php
 *
 * This is the contract step 6's element model has to meet. See
 * `SchoenstattTest\Element\ElementSurface` for what is recorded, what is normalised and
 * why value options are a digest rather than a list.
 *
 * @return array<string, array<string, mixed>>
 */

declare(strict_types=1);

return
PHP_HEADER;

$body = var_export($surface, true);

file_put_contents(__DIR__ . '/element-surface.php', $header . ' ' . $body . ";\n");

printf("Recorded %d elements across the application.\n", count($surface));
echo "Review the diff: every changed line is an element that answers differently.\n";
