<?php

declare(strict_types=1);

namespace SchoenstattTest\Fuzz;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/harness.php';

/**
 * Pushes a documented corpus of hostile input through every form and asserts the
 * two invariants that have to hold before "does it validate correctly?" is even a
 * meaningful question.
 *
 *   1. **`isValid()` returns a boolean and never throws.**
 *   2. **A value the form accepted honours the bound that same form declares for it.**
 *
 * A throw is worse than a wrong `true`. A wrong `true` writes one bad row; a throw
 * out of `isValid()` is an uncaught exception inside a controller action, which
 * means a 500 (and in this application, sometimes an HTTP 200 with an empty body),
 * the user's entire submission gone, and no indication which field did it.
 * `SionModel\Filter\ToDateTime` calls `new \DateTime($value)` with no try/catch, so
 * `'asdf'` typed into any date field does exactly that — and every finding below
 * was produced by running the code, not by reading it.
 *
 * See `HostileInputCorpus` for what is sent and why each entry earns its place, and
 * `HostileInputDriver` for the three-phase strategy, the attribution logic and the
 * two laminas behaviours (sticky element messages; spec-replaces-element) that make
 * naive versions of this test silently vacuous.
 *
 * **Deterministic.** The corpus is a constant, iteration order is sorted, and the
 * one phase that draws combinations seeds `mt_srand()` from
 * `HostileInputCorpus::SEED`, which is printed on every run. The same run on any
 * machine produces the same findings in the same order. A flaky fuzzer gets deleted.
 *
 * **Read-only and offline.** No HTTP at all, and the only database traffic is the
 * SELECTs the form factories issue for value options —
 * `FormValidationContractTest::testTheHarnessNeverWritesToTheDatabase()` proves that
 * from the adapter's statement log.
 */
final class HostileInputFuzzTest extends TestCase
{
    private static ?HostileInputDriver $driver = null;

    private static function driver(): HostileInputDriver
    {
        if (null !== self::$driver) {
            return self::$driver;
        }

        $repository = FormRepository::instance();

        return self::$driver = new HostileInputDriver($repository, new FormGapCollector($repository));
    }

    /**
     * @param list<string> $actual
     */
    private function assertNoNewFindings(string $category, array $actual, string $why): void
    {
        $baseline = GapBaseline::load();
        $stale    = $baseline->staleIn($category, $actual);

        if ([] !== $stale) {
            printf(
                "\n[%s] baseline lists %d finding(s) that no longer reproduce — fixed, and safe to drop.\n"
                . "  Tighten with: docker compose exec -T app php test/Fuzz/regenerate-baseline.php\n",
                $category,
                count($stale)
            );
            foreach ($stale as $entry) {
                printf("    FIXED %s\n", $entry);
            }
        }

        $new = $baseline->newIn($category, $actual);

        // Print the corpus values behind each *new* finding. Kept out of the
        // baseline entries themselves (they would make it churn every time the
        // corpus grows) but shown exactly when someone needs to reproduce one.
        $detail = self::driver()->throwingDetail();
        foreach ($new as $finding) {
            printf("\n  NEW %s\n", $finding);
            foreach ($detail[$finding] ?? [] as $label) {
                printf("      triggered by: %s\n", $label);
            }
        }

        self::assertSame([], $new, sprintf("New fuzz finding(s) in '%s'.\n\n%s", $category, $why));
    }

    /**
     * The invariant. Every entry is a place where hostile — sometimes merely
     * unusual — input takes the request down instead of being rejected.
     *
     * Baselined so the suite arrives green while the fixes land; the contract is
     * `phpstan-baseline.neon`'s, no *new* throws. Each entry is
     * `Class::field throws ExceptionClass`; `<combination>` means only the seeded
     * multi-field round reproduced it, `<cross-field>` that no single field does,
     * and `<any input>` that even a benign submission throws.
     */
    public function testIsValidNeverThrows(): void
    {
        $this->assertNoNewFindings(
            'throwingInputs',
            self::driver()->throwingInputs(),
            'isValid() must answer, not throw. A throw here is an uncaught exception in a controller '
            . "action: a 500, the user's whole submission lost, and no clue which field caused it. The "
            . 'usual cause is a filter or validator that assumes its input is well-formed — '
            . 'SionModel\\Filter\\ToDateTime calls new \\DateTime($value) with no try/catch, and '
            . 'Laminas\\Form\\Element\\DateSelect::setValue() throws out of setData() before isValid() '
            . 'is even reached. Fix by making the filter total (return null on unparseable input) and '
            . 'letting a validator reject the value.'
        );
    }

    /**
     * The second invariant: a `StringLength` max the form declares must actually
     * hold. An entry here means the bound is decoration — the value was accepted at
     * a length the form itself says is too long, usually because a spec entry
     * declares the validator somewhere the input filter does not read.
     */
    public function testAcceptedValuesHonourTheFormsOwnDeclaredBounds(): void
    {
        $this->assertNoNewFindings(
            'boundViolations',
            self::driver()->boundViolations(),
            'The form accepted a value longer than the StringLength max it declares for that field, '
            . 'which means the declared bound is not wired into the input filter at all. Check that '
            . 'the validator is in getInputFilterSpecification() under the exact element name, and not '
            . 'in the element definition (where it is discarded).'
        );
    }

    /**
     * Guards the failure mode where the bound assertion above passes because nothing
     * was ever measured.
     *
     * This is not hypothetical. Three iterations of `HostileInputDriver` reported
     * zero violations while performing zero checks: laminas element messages are
     * sticky across `isValid()` calls, so after the first sweep every field on a
     * shared form instance looked rejected and was skipped. The count is asserted
     * here because "green" and "vacuous" were indistinguishable without it.
     */
    public function testTheBoundCheckActuallyMeasuresSomething(): void
    {
        $performed = self::driver()->boundChecksPerformed();

        printf(
            "\nBound checks performed: %d (an accepted field measured against a declared max).\n",
            $performed
        );

        self::assertGreaterThan(
            500,
            $performed,
            'the bound check examined almost nothing, so testAcceptedValuesHonourTheFormsOwnDeclaredBounds '
            . 'is passing vacuously. Most likely the per-field "was this accepted?" signal has broken '
            . 'again — see HostileInputDriver::attempt() on sticky messages and '
            . 'HostileInputDriver::unwrap() on lost field names.'
        );
    }

    /**
     * Coverage and determinism report. Prints the seed, because a fuzzer whose seed
     * is not visible cannot be reproduced by the person reading its failure.
     */
    public function testFuzzCoverageSummary(): void
    {
        $driver     = self::driver();
        $repository = FormRepository::instance();

        printf(
            "\nSeed 0x%X (HostileInputCorpus::SEED), %d combination round(s) per form — every run of "
            . "this suite drives the same values in the same order.\n",
            HostileInputCorpus::SEED,
            HostileInputCorpus::COMBINATION_ROUNDS
        );
        printf(
            "Drove %d form(s) through %d corpus value(s) plus boundary and combination phases: "
            . "%d isValid() calls.\n",
            $driver->formsDriven(),
            count(HostileInputCorpus::values()),
            $driver->isValidCalls()
        );
        printf(
            "Findings: %d throwing input(s), %d bound violation(s).\n",
            count($driver->throwingInputs()),
            count($driver->boundViolations())
        );

        self::assertSame(
            count($repository->forms()),
            $driver->formsDriven(),
            'the fuzzer skipped a form that was successfully built'
        );
        self::assertGreaterThan(
            1000,
            $driver->isValidCalls(),
            'far fewer isValid() calls than the corpus implies — a phase is being skipped'
        );
    }
}
