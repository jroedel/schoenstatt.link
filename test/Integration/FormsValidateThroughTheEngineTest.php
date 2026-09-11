<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Form\CommentForm;
use SionModel\Form\Exception\DomainException;
use SionModel\Form\Form as EngineForm;
use SionModel\Form\FormInterface;
use SionModel\Form\Validation\FormSpecification;

use function array_keys;
use function implode;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * Every form validates through the engine, and the engine is what answers.
 *
 * ## Why this outlived its reason for existing
 *
 * It was written when `SionModel\Form\Form` was one line — `extends Laminas\Form\Form` —
 * away from silently reverting the whole cutover: delete that line and the site keeps
 * validating, through `Laminas\InputFilter` instead, and passes every test in the
 * repository because `WholeFormEngineParityTest` proved the two agree. The apparatus that
 * made the cutover safe is what would have made the revert invisible.
 *
 * That line is gone with the form model, so the revert it guarded against is no longer
 * spellable. What is left is worth keeping and is the same shape: every form the harness
 * can find is a `SionModel\Form\Form`, and a real instance answers the way the engine
 * answers — `getData()` returns a value for every key the specification describes, which
 * is the contract a controller reading `$data['x']` for an unsubmitted optional field
 * depends on.
 */
final class FormsValidateThroughTheEngineTest extends TestCase
{
    public function testEveryFormExtendsTheEnginesBaseClass(): void
    {
        $strangers = [];
        $checked   = 0;

        foreach (FormRepository::instance()->forms() as $class => $form) {
            if (! $form instanceof FormInterface) {
                continue; //a bare fieldset is not submitted on its own
            }

            $checked++;
            if (! $form instanceof EngineForm) {
                $strangers[] = $class;
            }
        }

        self::assertGreaterThanOrEqual(35, $checked, 'almost no form was examined');
        self::assertSame(
            [],
            $strangers,
            "These forms implement FormInterface without extending SionModel\\Form\\Form, so "
            . "nothing says they validate through SionModel\\Form\\Validation\\InputFilter:\n  "
            . implode("\n  ", $strangers)
        );
    }

    /**
     * A real instance answers the way the engine answers, not the way laminas does.
     */
    public function testValidationRunsOnTheEngine(): void
    {
        $form = FormRepository::instance()->forms()['SionModel\Form\CommentForm'];
        self::assertInstanceOf(EngineForm::class, $form);

        $form->setData([
            'comment'  => 'A comment.',
            'security' => $form->get('security')->getValue(),
        ]);

        self::assertTrue($form->isValid(), 'a signed, well-formed comment must validate');

        //Every key the assembled specification describes, and only those. This is the
        //engine's contract: `getValues()` walks the specification rather than the
        //submission, so a controller reading `$data['x']` for an unsubmitted optional
        //field finds null rather than an undefined key.
        self::assertSame(
            array_keys(FormSpecification::of($form)),
            array_keys($form->getData()),
            'getData() no longer matches the assembled specification'
        );

        //The other half of the contract: data before validation is refused rather than
        //answered with an empty array, which is what a controller reading getData() on a
        //GET would otherwise write to the database.
        $this->expectException(DomainException::class);
        (new CommentForm())->getData();
    }

    /**
     * The count, so that a form leaving the walk reads as a failure rather than a smaller
     * number nobody looks at.
     */
    public function testTheWalkStillCoversEveryForm(): void
    {
        $forms = 0;
        foreach (FormRepository::instance()->forms() as $form) {
            if ($form instanceof FormInterface) {
                $forms++;
            }
        }

        self::assertGreaterThanOrEqual(
            FormRepository::COUNT_SANITY_FLOOR - 5,
            $forms,
            sprintf('only %d forms were found; the discovery walk has probably moved', $forms)
        );
    }
}
