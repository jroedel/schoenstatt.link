<?php

/**
 * Rewrites test/Fuzz/known-form-gaps.php from the current state of the codebase.
 *
 *     docker compose exec -T app php test/Fuzz/regenerate-baseline.php
 *
 * Review the diff before committing. Every line it adds is a validation gap being
 * *accepted*; every line it removes is one that has been fixed. Removing lines is
 * routine housekeeping — the suite never fails on a stale entry, so the baseline is
 * safe to tighten at any time. Adding lines should be a decision.
 *
 * Runs the same collectors the two test classes run, so the file it writes and the
 * file they read can never drift apart.
 *
 * Read-only against the database; no HTTP.
 */

declare(strict_types=1);

require_once __DIR__ . '/harness.php';

use SchoenstattTest\Fuzz\FormGapCollector;
use SchoenstattTest\Fuzz\FormRepository;
use SchoenstattTest\Fuzz\GapBaseline;
use SchoenstattTest\Fuzz\HostileInputCorpus;
use SchoenstattTest\Fuzz\HostileInputDriver;
use SchoenstattTest\Fuzz\SchemaColumnWidths;

/** One line per category, written into the generated file so it explains itself. */
const CATEGORY_DESCRIPTIONS = [
    'deadElementKeys'                  =>
        "filters/validators keys in an element definition, which Laminas\\Form\\Factory discards "
        . 'without a word. The field looks protected in the source and is completely unvalidated.',
    'unanalyzableAddCalls'             =>
        'add() calls ElementDefinitionScanner cannot read, because the argument is not an array '
        . 'literal. Non-empty means the dead-key check above has blind spots.',
    'unconstructableForms'             =>
        'Forms no test in this suite can examine, because nothing can build them.',
    'choiceFieldsOpenByDesign'         =>
        'Choice fields declared in test/Fuzz/open-ended-choice-fields.php as having no domain to '
        . 'enforce: the view lets a moderator type a value the list does not offer, so an InArray '
        . 'would remove a feature rather than close a hole. Listed rather than hidden, because '
        . '"unvalidated on purpose" is still unvalidated.',
    'openEndedDeclarationsStale'       =>
        'Entries in open-ended-choice-fields.php that matched no field this run. Either the field '
        . 'is constrained now and the declaration hides the next regression on it, or the entry is '
        . 'a typo asserting nothing. Expected to be empty.',
    'elementsMissingFromSpec'          =>
        'Data elements the input filter spec never names. A plain Text/Textarea/Hidden in this state '
        . 'is total pass-through: no filter, no validator, no length bound.',
    'specKeysWithoutElement'           =>
        'Spec keys naming no element. The field the entry was meant to protect is unvalidated, and '
        . 'getData() gains a key holding null.',
    'unboundedTextFields'              =>
        'Free-text fields with no bound at all on their length.',
    'choiceFieldsWithoutDomain'        =>
        'Select/Radio/MultiCheckbox fields that set disable_inarray_validator and get no InArray '
        . 'back from the spec, so nothing constrains them to their own option list. That option, '
        . 'not the spec, is what removes a domain: laminas merges the spec input into the '
        . "element's rather than replacing it. The quiet corruption case: short wrong-domain "
        . 'values fit every column.',
    'boundsLooserThanColumn'           =>
        'Fields allowed to be longer than the column they are written into. SQLSTATE 22001 under '
        . 'STRICT_TRANS_TABLES.',
    'buttonsDeclaredOnlyByAttribute'   =>
        "Elements declaring their button-ness only in attributes.type, so they are a base "
        . 'Laminas\\Form\\Element and a data input as far as the input filter is concerned.',
    'parsedWidthsLooserThanLiveSchema' =>
        'Columns where database/*.sql claims more room than the live schema has, which makes the '
        . 'column-width check above too permissive for those fields.',
    'throwingInputs'                   =>
        'Proved by running it: places where isValid() throws instead of answering. A 500 in a '
        . 'controller action, not a validation failure.',
    'boundViolations'                  =>
        'Proved by running it: a value accepted at a length the form itself declares is too long.',
];

$repository = FormRepository::instance();
$collector  = new FormGapCollector($repository);
$driver     = new HostileInputDriver($repository, $collector);

fwrite(STDERR, "Building every form...\n");
$gaps = $collector->gaps();

fwrite(STDERR, sprintf(
    "  %d discovered, %d built, %d unconstructable\n",
    count($repository->discover()),
    count($repository->forms()),
    count($repository->constructionFailures())
));

fwrite(STDERR, sprintf("Fuzzing with seed 0x%X...\n", HostileInputCorpus::SEED));
$gaps['throwingInputs'] = $driver->throwingInputs();
$gaps['boundViolations'] = $driver->boundViolations();
fwrite(STDERR, sprintf("  %d isValid() calls\n", $driver->isValidCalls()));

fwrite(STDERR, "Cross-checking database/*.sql against the live schema...\n");
$gaps['parsedWidthsLooserThanLiveSchema'] = parsedWidthsLooserThanLiveSchema($repository);

$total = GapBaseline::write($gaps, CATEGORY_DESCRIPTIONS);

fwrite(STDERR, sprintf("\nWrote %s with %d accepted gap(s):\n", GapBaseline::FILE, $total));
foreach ($gaps as $category => $entries) {
    fwrite(STDERR, sprintf("  %-34s %5d\n", $category, count($entries)));
}
fwrite(STDERR, "\nReview the diff: every added line is a gap being accepted.\n");

exit(0);

/**
 * Columns the migration scripts describe as wider than they really are — the only
 * direction of drift that makes the column-width check unsafe.
 *
 * @return list<string>
 */
function parsedWidthsLooserThanLiveSchema(FormRepository $repository): array
{
    $container = $repository->container();

    /** @var array<string, array<string, int>>|null $live */
    $live = $repository->quietly(static function () use ($container): ?array {
        try {
            $adapter = $container->get(\SionModel\Db\Connection::class);
            $result  = $adapter->select(
                'SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND CHARACTER_MAXIMUM_LENGTH IS NOT NULL'
            );
        } catch (\Throwable $e) {
            fwrite(STDERR, '  skipped: ' . $e->getMessage() . "\n");
            return null;
        }

        $widths = [];
        foreach ($result as $row) {
            $widths[strtolower((string) $row['TABLE_NAME'])][(string) $row['COLUMN_NAME']]
                = (int) $row['CHARACTER_MAXIMUM_LENGTH'];
        }

        return [] === $widths ? null : $widths;
    });

    if (null === $live) {
        fwrite(STDERR, "  no database reachable — leaving this category as it was\n");

        /** @var array<string, list<string>> $existing */
        $existing = is_file(GapBaseline::FILE) ? require GapBaseline::FILE : [];

        return $existing['parsedWidthsLooserThanLiveSchema'] ?? [];
    }

    $looser = [];
    foreach ($live as $table => $columns) {
        foreach ($columns as $column => $liveWidth) {
            if (! SchemaColumnWidths::knowsColumn($table, $column)) {
                continue;
            }
            $parsed = SchemaColumnWidths::widthOf($table, $column);
            if (null !== $parsed && $parsed > $liveWidth) {
                $looser[] = sprintf(
                    '%s.%s: database/*.sql says %d, the live schema says %d',
                    $table,
                    $column,
                    $parsed,
                    $liveWidth
                );
            }
        }
    }

    sort($looser);

    return $looser;
}
