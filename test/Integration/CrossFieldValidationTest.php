<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Form\FormInterface;
use SionModel\Form\Validation\FormSpecification;
use SionModel\Validator\DateNotBefore;

use function array_keys;
use function implode;
use function is_array;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * A validator that reads its siblings is actually given them.
 *
 * ## Why this is not covered by the recordings
 *
 * `test/Form/engine-surface.php` submits two datasets to every form, and neither produces
 * a date pair in the wrong order — so both the working and the broken engine record the
 * same answer, byte for byte. The recordings say what the rules decide about **one** value;
 * nothing there can say whether a rule was given the second value it needs.
 *
 * That gap is not hypothetical. `SionModel\Form\Validation\InputFilter` called
 * `$validator->isValid($value)` where `Laminas\InputFilter\Input` calls
 * `isValid($value, $context)`, and a context-reading validator returns `true` when it has
 * no context — laminas' own signal for "not enough information to judge". So all five
 * `DateNotBefore` rules passed everything from the validation cutover until 2026-09-11: a
 * person could be recorded as dying before their birth and an assignment as ending before
 * it began, with every form saying yes.
 *
 * ## Why the pairs are discovered rather than listed
 *
 * A sixth rule added to a form next month is checked without anyone editing this file,
 * which is the same reason `test/Fuzz/FormRepository` scans the source tree. The floor
 * guards the opposite failure: a rename that makes the discovery find nothing and every
 * assertion pass vacuously.
 */
final class CrossFieldValidationTest extends TestCase
{
    /** How many ordered date pairs the application declares. Five today. */
    private const PAIR_FLOOR = 5;

    /**
     * The later field rejects a date that falls before the earlier field's.
     */
    public function testEveryOrderedDatePairIsEnforced(): void
    {
        $unenforced = [];
        $checked    = 0;

        foreach (self::orderedDatePairs() as [$class, $form, $later, $earlier]) {
            $checked++;

            $form->setData([
                $later   => '1990-01-01',
                $earlier => '2000-01-01',
            ]);
            $form->isValid();

            $messages = $form->getMessages();
            $reported = is_array($messages[$later] ?? null) ? array_keys($messages[$later]) : [];

            if (! in_array(DateNotBefore::TOO_EARLY, $reported, true)) {
                $unenforced[] = sprintf(
                    '%s.%s accepted a date before %s (reported: %s)',
                    $class,
                    $later,
                    $earlier,
                    [] === $reported ? 'nothing' : implode(', ', $reported)
                );
            }
        }

        self::assertGreaterThanOrEqual(
            self::PAIR_FLOOR,
            $checked,
            'No ordered date pair was found, so this test proved nothing.'
        );
        self::assertSame([], $unenforced, implode("\n  ", $unenforced));
    }

    /**
     * The same pair in the right order is accepted, so the rule is not simply refusing.
     */
    public function testAnOrderedDatePairInOrderIsAccepted(): void
    {
        $refused = [];

        foreach (self::orderedDatePairs() as [$class, $form, $later, $earlier]) {
            $form->setData([
                $later   => '2000-01-01',
                $earlier => '1990-01-01',
            ]);
            $form->isValid();

            $messages = $form->getMessages();
            $reported = is_array($messages[$later] ?? null) ? array_keys($messages[$later]) : [];

            if (in_array(DateNotBefore::TOO_EARLY, $reported, true)) {
                $refused[] = sprintf('%s.%s refused a date after %s', $class, $later, $earlier);
            }
        }

        self::assertSame([], $refused, implode("\n  ", $refused));
    }

    /**
     * Every `DateNotBefore` rule in the application, as
     * `[form class, form, later field, earlier field]`.
     *
     * A fresh repository per call: a form keeps the data it was last given, and the two
     * tests submit opposite orders to the same fields.
     *
     * @return list<array{0: string, 1: FormInterface, 2: string, 3: string}>
     */
    private static function orderedDatePairs(): array
    {
        $pairs = [];

        foreach (FormRepository::fresh()->forms() as $class => $form) {
            if (! $form instanceof FormInterface) {
                continue;
            }

            foreach (FormSpecification::of($form) as $field => $input) {
                if (! is_array($input)) {
                    continue;
                }

                foreach ($input['validators'] ?? [] as $rule) {
                    if (! is_array($rule) || DateNotBefore::class !== ($rule['name'] ?? null)) {
                        continue;
                    }

                    $earlier = $rule['options']['field'] ?? null;
                    if (is_string($earlier) && '' !== $earlier) {
                        $pairs[] = [$class, $form, (string) $field, $earlier];
                    }
                }
            }
        }

        return $pairs;
    }
}
