<?php

/**
 * Rewrite `test/Form/form-markup.php` from the application as it stands.
 *
 *     docker compose exec -T -u www-data app php -d memory_limit=1G \
 *         test/Form/regenerate-form-markup.php
 *
 * The file is a **specification**, not a convenience. Iteration A replaces the laminas
 * form model, and this is what says the replacement renders the same bytes — regenerating
 * it to make a test pass converts it into a record of whatever the code happens to do.
 * Read the diff: a line that changed is markup a browser would receive differently, and
 * being told is the whole point.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/FormData.php';
require_once __DIR__ . '/FormMarkup.php';

use SchoenstattTest\Form\FormMarkup;

$recorder = new FormMarkup();
$markup   = $recorder->record();

$header = <<<'PHP_HEADER'
<?php

/**
 * The markup every form in the application produces, recorded through
 * `SionModel\Form\BootstrapFormRenderer` over the laminas form model.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php -d memory_limit=1G \
 *         test/Form/regenerate-form-markup.php
 *
 * This is the contract iteration A's form model has to meet. Keyed
 * `Form\Class::path/to/element` => state => helper => markup, with the same paths
 * `test/Element/element-surface.php` uses so the two can be read side by side.
 *
 * Three states, and **`populated` and `invalid` record only what differs from
 * `pristine`**: a helper missing from them renders identically there. See
 * `SchoenstattTest\Form\FormMarkup` for what each state is, what is normalised and why an
 * option list longer than four collapses to a digest.
 *
 * @return array<string, array<string, array<string, string>>>
 */

declare(strict_types=1);

return
PHP_HEADER;

file_put_contents(__DIR__ . '/form-markup.php', $header . ' ' . var_export($markup, true) . ";\n");

$states = [];
foreach ($markup as $entry) {
    foreach ($entry as $state => $helpers) {
        $states[$state] = ($states[$state] ?? 0) + count($helpers);
    }
}

printf("Recorded %d rendered surfaces across the application.\n", count($markup));
foreach ($states as $state => $count) {
    $against = ['populated' => 'pristine', 'invalid' => 'pristine', 'prepared' => 'invalid'][$state] ?? null;
    printf(
        "  %-10s %d helper outputs%s\n",
        $state,
        $count,
        null === $against ? '' : ' that differ from ' . $against
    );
}
printf("Swallowed diagnostics while rendering: %s\n", json_encode($recorder->diagnostics()));
echo "Review the diff: every changed line is markup a browser would receive differently.\n";
