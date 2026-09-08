<?php

declare(strict_types=1);

namespace SchoenstattTest\Fuzz;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/harness.php';

/**
 * Structural guard over every form's input filter: the half of the harness that
 * reads the wiring rather than driving data through it.
 *
 * ## What this exists to stop
 *
 * The hardening effort this suite protects is aimed at one sentence: garbage must
 * not reach the database. In a laminas-form application, almost every way garbage
 * gets in is a *wiring* mistake rather than a wrong validator, and every one of
 * them is silent. Four shapes, all present in this codebase today:
 *
 *  1. **Validators written where nothing reads them.** `Laminas\Form\Factory::
 *     configureElement()` consumes only `name`, `type`, `options` and `attributes`
 *     from an element definition; a `filters` or `validators` key sitting beside
 *     them is discarded without a word. The field is naked and the source says it
 *     is protected. Seven of these exist.
 *  2. **A field the spec never mentions.** `Form::attachInputFilterDefaults()`
 *     builds element-derived inputs and then overwrites them by name from the spec.
 *     An element the spec never names keeps whatever it provides for itself — and a
 *     plain `Text`/`Textarea`/`Hidden` provides `['required' => false]` and nothing
 *     else. No filter, no validator, no bound.
 *  3. **A spec key that names nothing.** The same `add($input, $name)` call creates
 *     the input regardless, so the typo is doubly silent: the field it was meant to
 *     guard is unguarded, and `getData()` gains a key holding `null`.
 *     `CommentForm` specs `text` for an element called `comment`; `UploadForm`
 *     specced `fileupload` for `fileUpload` until it was deleted on 2026-09-08.
 *  4. **A dropdown that stopped being a dropdown.** Because the spec *replaces*
 *     the element's input, naming a `Select` in the spec without repeating an
 *     `InArray` destroys its domain check. The field is now free text pointed at an
 *     enum-ish varchar — and since the values are short, nothing downstream
 *     complains. This is the quiet corruption that motivated the whole effort, so
 *     it gets its own category.
 *
 * ## Discovery is a filesystem walk, deliberately
 *
 * Nothing here holds a list of form classes. A form added next month is checked
 * without anyone remembering, which is the only version of this test worth having —
 * see `FormRepository`. `testTheWalkFindsSomethingToCheck()` guards the inverse
 * failure, where a moved directory makes every assertion below pass vacuously.
 *
 * ## Baselined, on purpose
 *
 * Every assertion compares against `known-form-gaps.php` with subset semantics: a
 * *new* gap fails, a fixed one does not. `GapBaseline` explains why that differs
 * from `AclGuardRouteDriftTest`'s exact-match approach, and how to regenerate.
 *
 * Needs vendor/ and the capsule's database (form factories query it for value
 * options) but never bootstraps the MVC application and makes no HTTP request. It
 * writes nothing: `testTheHarnessNeverWritesToTheDatabase()` proves it from the
 * adapter's own statement log.
 */
final class FormValidationContractTest extends TestCase
{
    private static ?FormRepository $repository = null;
    private static ?FormGapCollector $collector = null;

    /** @var array<string, list<string>>|null */
    private static ?array $gaps = null;

    private static function collector(): FormGapCollector
    {
        self::$repository ??= FormRepository::instance();

        return self::$collector ??= new FormGapCollector(self::$repository);
    }

    /** @return array<string, list<string>> */
    private static function gaps(): array
    {
        return self::$gaps ??= self::collector()->gaps();
    }

    /**
     * Assert a category against the baseline: new entries fail, stale entries are
     * reported. Returns nothing but always makes exactly one assertion, so no test
     * here can be counted as risky.
     *
     * @param list<string> $actual
     */
    private function assertNoNewGaps(string $category, array $actual, string $why): void
    {
        $baseline = GapBaseline::load();
        $stale    = $baseline->staleIn($category, $actual);

        if ([] !== $stale) {
            printf(
                "\n[%s] baseline lists %d gap(s) that no longer occur — fixed, and safe to drop.\n"
                . "  Tighten with: docker compose exec -T app php test/Fuzz/regenerate-baseline.php\n",
                $category,
                count($stale)
            );
            foreach (array_slice($stale, 0, 10) as $entry) {
                printf("    FIXED %s\n", $entry);
            }
        }

        self::assertSame(
            [],
            $baseline->newIn($category, $actual),
            sprintf(
                "New validation gap(s) in '%s'.\n\n%s\n\n"
                . 'Fix the finding, or — if it is genuinely acceptable — accept it deliberately by '
                . 'running: docker compose exec -T app php test/Fuzz/regenerate-baseline.php',
                $category,
                $why
            )
        );
    }

    // ---------------------------------------------------------- sanity guards

    public function testTheWalkFindsSomethingToCheck(): void
    {
        $repository = FormRepository::instance();

        self::assertGreaterThanOrEqual(
            FormRepository::COUNT_SANITY_FLOOR,
            count($repository->discover()),
            'the form discovery walk found almost nothing — module/*/src/Form/ has probably moved, '
            . 'which would make every other assertion in this file pass vacuously'
        );
    }

    /**
     * A form nobody can construct is a form nobody has checked, so this is a
     * finding rather than a harness excuse — and it is asserted against the
     * baseline like everything else, which means adding a form with an
     * unsatisfiable factory fails here rather than quietly shrinking coverage.
     */
    public function testEveryDiscoveredFormCanBeConstructed(): void
    {
        $repository = FormRepository::instance();
        $failures   = $repository->constructionFailures();

        foreach ($failures as $class => $reason) {
            printf("\n  UNCONSTRUCTABLE %s\n      %s\n", $class, $reason);
        }

        $this->assertNoNewGaps(
            'unconstructableForms',
            self::gaps()['unconstructableForms'],
            'A form that cannot be instantiated cannot be checked by any test in this suite. Either '
            . 'give it a factory the harness can resolve, or make its constructor satisfiable — see '
            . 'FormRepository::constructDirectly() for the shapes it already handles.'
        );
    }

    /**
     * The scanner claims to see into every `add()` call because every one of them
     * currently passes a literal array. If that stops being true the claim has to
     * stop being made, so it is asserted rather than commented.
     */
    public function testEveryElementDefinitionIsStaticallyAnalyzable(): void
    {
        $this->assertNoNewGaps(
            'unanalyzableAddCalls',
            self::gaps()['unanalyzableAddCalls'],
            'ElementDefinitionScanner cannot see into an add() call that is passed a variable instead '
            . 'of an array literal, so the dead-key check below silently stops covering it. Inline the '
            . 'array, or teach the scanner to follow the variable.'
        );
    }

    // ------------------------------------------------------------- (a) dead keys

    /**
     * (a) `filters`/`validators` written into an element definition, where
     * laminas-form throws them away.
     *
     * The most dangerous category in this file, because it is the only one where the
     * source code actively misleads: the author wrote a bound, a reviewer read a
     * bound, and there is no bound. Detected statically — by the time the form
     * object exists the keys are gone (see ElementDefinitionScanner).
     */
    public function testNoElementDefinitionCarriesDeadFilterOrValidatorKeys(): void
    {
        $this->assertNoNewGaps(
            'deadElementKeys',
            self::gaps()['deadElementKeys'],
            "Laminas\\Form\\Factory::configureElement() reads only name/type/options/attributes. A "
            . "'filters' or 'validators' key in an element definition is silently discarded, so the "
            . 'field it appears to protect is completely unvalidated. Move it into the form\'s '
            . 'getInputFilterSpecification(), keyed by the element name.'
        );
    }

    // -------------------------------------------------- (b) elements vs the spec

    /**
     * (b) Every data-bearing element is named in the input filter specification.
     *
     * Buttons, submits, CSRF and captcha are exempt, and exempt **by element type**
     * rather than by a list of names — a name list would wave through a real field
     * that happened to be called `submit` and would miss the button called `deny`.
     * The one wrinkle is the form that writes `['name' => 'submit', 'attributes' =>
     * ['type' => 'submit']]` with no PHP `type` at all, producing a base
     * `Laminas\Form\Element`: a button to the browser, a text input to the server.
     * Those are reported separately by
     * `testNoButtonIsDeclaredOnlyByItsHtmlAttribute()`.
     */
    public function testEveryDataElementIsNamedInTheInputFilterSpecification(): void
    {
        $this->assertNoNewGaps(
            'elementsMissingFromSpec',
            self::gaps()['elementsMissingFromSpec'],
            'An element the spec does not name keeps only what it provides for itself. For a plain '
            . 'Text/Textarea/Hidden that is ["required" => false] — no filter, no validator, no length '
            . 'bound, straight through to getData(). Add a spec entry for it.'
        );
    }

    /**
     * A browser button that the server treats as an ordinary text input. Low
     * severity on its own — it lands in `getData()` and is usually discarded by
     * `updateColumns` — but it is the same wiring mistake as the rest, and giving it
     * a `'type' => Submit::class` costs nothing.
     */
    public function testNoButtonIsDeclaredOnlyByItsHtmlAttribute(): void
    {
        $this->assertNoNewGaps(
            'buttonsDeclaredOnlyByAttribute',
            self::gaps()['buttonsDeclaredOnlyByAttribute'],
            "Declaring only attributes.type => 'submit' produces a base Laminas\\Form\\Element, which "
            . 'is a data input as far as the input filter is concerned. Give the element '
            . "'type' => Laminas\\Form\\Element\\Submit::class so it is exempt for the right reason."
        );
    }

    // ------------------------------------------------- (c) spec keys vs elements

    /**
     * (c) Every spec key corresponds to a real element.
     *
     * A typo here disables validation for the field it was meant to protect *and*
     * adds a phantom `null` to `getData()`, with no error either way. Both known
     * instances — `CommentForm`'s `text` for `comment`, and `UploadForm`'s `fileupload`
     * for `fileUpload` until that form was deleted — are one-character-class mistakes
     * that survived years.
     */
    public function testEverySpecificationKeyNamesARealElement(): void
    {
        $this->assertNoNewGaps(
            'specKeysWithoutElement',
            self::gaps()['specKeysWithoutElement'],
            'A spec key naming no element still becomes an input: the field the entry was written for '
            . 'stays unvalidated, and getData() gains a key holding null. Fix the spelling (watch the '
            . 'camelCase) or delete the entry.'
        );
    }

    // ------------------------------------------------------- (d) length bounds

    /**
     * (d) Every free-text field has some bound on how long it can be.
     *
     * `StringLength` is the usual answer, but a `Regex`, `Between`, `InArray` or
     * `Digits` bounds the length too and counts — the test must not reject a correct
     * fix for using the right tool. Classification of "free text" is by exclusion
     * (see FormGapCollector::NON_TEXT_ELEMENT_TYPES) so that an element type nobody
     * anticipated is treated as needing a bound rather than slipping through.
     */
    public function testEveryTextFieldHasALengthBound(): void
    {
        $this->assertNoNewGaps(
            'unboundedTextFields',
            self::gaps()['unboundedTextFields'],
            'An unbounded text field accepts as much as PHP will hold. Against a length-limited column '
            . 'that is SQLSTATE 22001 under STRICT_TRANS_TABLES — a 500 — and against a TEXT column it '
            . 'is a permanent 100 KB row nobody meant to store. Add a StringLength with an explicit '
            . "max and 'encoding' => 'UTF-8'."
        );
    }

    /**
     * A `Select` named in the spec without an `InArray` no longer constrains
     * anything, because the spec entry replaced the element's own input outright.
     * This is the category that maps most directly onto silent corruption: the
     * values are short, so every length check passes and the wrong-domain string
     * lands in the column and stays.
     */
    public function testEveryChoiceFieldStillChecksItsDomain(): void
    {
        $this->assertNoNewGaps(
            'choiceFieldsWithoutDomain',
            self::gaps()['choiceFieldsWithoutDomain'],
            'Naming a Select/Radio/MultiCheckbox in the input filter spec destroys the InArray that '
            . 'laminas built from its value options, because the spec entry replaces the input rather '
            . 'than merging with it. Restate it with SionModel\Form\ChoiceDomain::validators($this->'
            . 'get($name)), which takes the haystack from the element itself — or, if the field is '
            . 'meant to accept values its list does not offer, declare it in '
            . 'test/Fuzz/open-ended-choice-fields.php with the reason.'
        );
    }

    /**
     * Fields declared open-ended really are open-ended.
     *
     * The declaration file is the one place in this suite where "unvalidated" is asserted to be
     * *correct*, so an entry that has stopped corresponding to anything is worse than no entry:
     * it reads as a considered decision while asserting nothing. Two ways that happens — the
     * field gained an `InArray`, so the declaration now hides the next regression on it, or the
     * entry names a form or element that no longer exists.
     *
     * Its own test method rather than a line in the one above, because a category with no
     * assertion is invisible: `openEndedDeclarationsStale` was added to the collector and the
     * baseline first, and a deliberately broken entry passed the whole suite until this existed.
     */
    public function testNoOpenEndedDeclarationIsStale(): void
    {
        $this->assertNoNewGaps(
            'openEndedDeclarationsStale',
            self::gaps()['openEndedDeclarationsStale'],
            'An entry in test/Fuzz/open-ended-choice-fields.php matched no field. Remove it if the '
            . 'field is constrained now, or correct it if the class or element name is wrong.'
        );
    }

    // ------------------------------------------------------ (e) column widths

    /**
     * (e) No field is bounded more loosely than the column it is written into.
     *
     * The chain is the application's own: a `sion_model` entity names its form and
     * its table, and `updateColumns` maps field to column — the same whitelist
     * `SionTable::updateHelper()` filters writes through, so a field missing from it
     * genuinely cannot reach the database and is correctly ignored. Widths come from
     * replaying `database/*.sql`; see SchemaColumnWidths for why that source rather
     * than information_schema.
     *
     * Where a width cannot be derived the field is skipped and the skip is
     * *printed*, per the brief: a field escaping this check must be visible rather
     * than merely absent. Fourteen do, all because `lib_libraries`, `lib_checkouts`
     * and several `lib_books` columns have no `CREATE TABLE` anywhere in the
     * migration scripts — they exist only inside the production dump.
     */
    public function testNoFieldIsBoundedMoreLooselyThanItsColumn(): void
    {
        $skips = self::collector()->columnBoundSkips();

        printf(
            "\n%d field(s) escape the column-width check because no width is derivable from "
            . "database/*.sql:\n",
            count($skips)
        );
        foreach ($skips as $skip) {
            printf("  %s\n", $skip);
        }

        $this->assertNoNewGaps(
            'boundsLooserThanColumn',
            self::gaps()['boundsLooserThanColumn'],
            'The form lets through more characters than the column can hold. Under '
            . 'STRICT_TRANS_TABLES MariaDB answers SQLSTATE 22001 and the request 500s, losing the '
            . "user's whole submission. Tighten the StringLength max to the column width (or widen "
            . 'the column, deliberately, in a new database/db*.sql).'
        );
    }

    /**
     * The migration scripts and production do not entirely agree, and it matters in
     * one direction: where the scripts report a *wider* column than reality, check
     * (e) passes a form that would still fail on insert.
     *
     * Cross-checked against `information_schema` when a database is reachable, and
     * skipped when one is not — the check is a bonus, not a dependency, because
     * `database/*.sql` is the only source that travels with the repository.
     *
     * Known at the time of writing: `sch_roles.RoleTitle` is VARCHAR(100) per
     * db0.2.sql and VARCHAR(50) in the production export, and 45 columns across
     * `lib_libraries`, `lib_checkouts`, `lib_books` and `sch_dictionary_*` exist
     * only in the dump.
     */
    public function testParsedColumnWidthsAgreeWithTheLiveSchemaWhereBothAreKnown(): void
    {
        $adapter = self::liveSchemaWidths();

        if (null === $adapter) {
            self::markTestSkipped('no database reachable; database/*.sql remains the only source');
        }

        $looser = [];
        $absent = 0;

        foreach ($adapter as $table => $columns) {
            foreach ($columns as $column => $liveWidth) {
                if (! SchemaColumnWidths::knowsColumn($table, $column)) {
                    $absent++;
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

        printf(
            "\n%d character column(s) exist only in the production dump, so no bound for them can be "
            . "derived from database/*.sql.\n",
            $absent
        );
        foreach ($looser as $entry) {
            printf("  LOOSER-THAN-REALITY %s\n", $entry);
        }

        $this->assertNoNewGaps(
            'parsedWidthsLooserThanLiveSchema',
            $looser,
            'database/*.sql reports a wider column than the database actually has, so the '
            . 'column-width check above will pass a form that still fails on insert. Add the missing '
            . 'migration to database/ so the scripts and reality agree.'
        );
    }

    /**
     * Character-column widths straight from the live schema, or null when there is
     * no database to ask. Read-only, one SELECT.
     *
     * @return array<string, array<string, int>>|null
     */
    private static function liveSchemaWidths(): ?array
    {
        $container = FormRepository::instance()->container();

        return FormRepository::instance()->quietly(static function () use ($container): ?array {
            try {
                $adapter = $container->get(\Laminas\Db\Adapter\Adapter::class);
                $result  = $adapter->query(
                    'SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH
                       FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND CHARACTER_MAXIMUM_LENGTH IS NOT NULL',
                    \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
                );
            } catch (\Throwable) {
                return null;
            }

            $widths = [];
            foreach ($result as $row) {
                $widths[strtolower((string) $row['TABLE_NAME'])][(string) $row['COLUMN_NAME']]
                    = (int) $row['CHARACTER_MAXIMUM_LENGTH'];
            }

            return [] === $widths ? null : $widths;
        });
    }

    // ------------------------------------------------------------- house-keeping

    /**
     * The harness reads the database (form factories need value options) and must
     * never write to it. Proved from the adapter's own statement log rather than
     * asserted in prose — `FormRepository` attaches a profiler that records every
     * statement the shared adapter executes.
     */
    public function testTheHarnessNeverWritesToTheDatabase(): void
    {
        // Force the whole build, so the log covers everything the harness does.
        self::gaps();

        $statements = FormRepository::instance()->sqlStatements();
        $writes     = [];

        $writePattern = '/^\s*(INSERT|UPDATE|DELETE|REPLACE|TRUNCATE|DROP|ALTER|CREATE|GRANT|LOCK)\b/i';

        foreach ($statements as $statement) {
            if (preg_match($writePattern, $statement)) {
                $writes[] = substr($statement, 0, 120);
            }
        }

        printf("\n%d SQL statement(s) executed while building every form; none of them write.\n", count($statements));

        self::assertSame([], $writes, 'the fuzz harness executed a data-modifying statement');
        self::assertNotSame([], $statements, 'no SQL was recorded at all — the profiler is not attached, '
            . 'so this test proves nothing. Check FormRepository::attachSqlRecorder().');
    }

    /**
     * Reports the pre-existing PHP deprecations and warnings the harness swallowed.
     *
     * Never asserted on. They are 2020-era module debt tracked elsewhere, they have
     * nothing to do with validation, and pinning a count here would turn any
     * unrelated fix into a failure of this suite. Suppressed rather than hidden:
     * `phpunit.xml.dist` sets `failOnWarning="true"`, so without
     * `FormRepository::quietly()` these would paint the whole suite red.
     */
    public function testDiagnosticsAreReportedNotAsserted(): void
    {
        self::gaps();

        $diagnostics = FormRepository::instance()->diagnostics();

        printf(
            "\nPHP diagnostics swallowed while building and inspecting the forms: %s\n",
            [] === $diagnostics ? 'none' : json_encode($diagnostics)
        );

        self::assertIsArray($diagnostics);
    }

    /**
     * Prints the coverage summary. Its assertions are about the harness rather than
     * the application: every discovered form was either built or reported, and each
     * gap category was actually computed.
     */
    public function testCoverageSummary(): void
    {
        $repository = FormRepository::instance();
        $gaps       = self::gaps();
        $baseline   = GapBaseline::load();

        $discovered = count($repository->discover());
        $built      = count($repository->forms());
        $failed     = count($repository->constructionFailures());

        $fromContainer = 0;
        foreach (array_keys($repository->forms()) as $class) {
            if (str_starts_with($repository->originOf($class), 'container:')) {
                $fromContainer++;
            }
        }

        printf(
            "\nForms discovered %d; built %d (%d from the container, %d by direct construction); "
            . "unconstructable %d.\n",
            $discovered,
            $built,
            $fromContainer,
            $built - $fromContainer,
            $failed
        );
        printf("Tables replayed from database/*.sql: %d.\n", count(SchemaColumnWidths::tables()));
        printf("%-34s %8s %8s\n", 'category', 'found', 'baseline');
        foreach ($gaps as $category => $entries) {
            printf("%-34s %8d %8d\n", $category, count($entries), $baseline->countIn($category));
        }

        self::assertSame($discovered, $built + $failed, 'a discovered form was neither built nor reported');
        self::assertNotSame([], $gaps, 'no gap categories were computed at all');
    }
}
