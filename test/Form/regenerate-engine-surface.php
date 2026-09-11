<?php

/**
 * Rewrite `test/Form/engine-surface.php` from the application as it stands.
 *
 *     docker compose exec -T -u www-data app php -d memory_limit=1G \
 *         test/Form/regenerate-engine-surface.php
 *
 * The file is a **specification**, not a convenience: it is what says the rules underneath
 * the engine still decide the same way once laminas' validators and filters are gone.
 * Regenerating it to make a test pass converts it into a record of whatever the code
 * happens to do. Read the diff — a changed line is a verdict, a stored value or a message
 * that moved.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/EngineSurface.php';

use SchoenstattTest\Form\EngineSurface;

$surface = EngineSurface::collect();

$header = <<<'PHP_HEADER'
<?php

/**
 * What `SionModel\Form\Validation\InputFilter` answers for every form in the application.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php -d memory_limit=1G \
 *         test/Form/regenerate-engine-surface.php
 *
 * Keyed `Form\Class` => dataset => verdict, returned values and messages, over the two
 * datasets `SchoenstattTest\Form\FormData` produces. The values are the array a controller
 * hands to `SionTable::updateEntity()`, which is the half of the contract a rendered-markup
 * baseline cannot see: `value=""` is what both `''` and `null` produce.
 *
 * See `SchoenstattTest\Form\EngineSurface` for what is normalised and why this replaces the
 * three parity tests that die with `Laminas\Form\Form`.
 *
 * @return array<string, array<string, array<string, mixed>>>
 */

declare(strict_types=1);

return
PHP_HEADER;

file_put_contents(__DIR__ . '/engine-surface.php', $header . ' ' . var_export($surface, true) . ";\n");

$accepted = 0;
foreach ($surface as $states) {
    $accepted += true === ($states['accepted']['valid'] ?? null) ? 1 : 0;
}

printf("Recorded %d forms.\n", count($surface));
printf("  %d of them accept the valid dataset outright.\n", $accepted);
echo "Review the diff: every changed line is a verdict, a stored value or a message that moved.\n";
