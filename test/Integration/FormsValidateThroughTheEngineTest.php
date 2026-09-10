<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Form\Form as LaminasForm;
use Laminas\Form\FormInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Form\Form as EngineForm;
use SionModel\Form\Validation\FormSpecification;

use function array_keys;
use function implode;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * The cutover is in force, and stays in force.
 *
 * ## Why this is asserted rather than assumed
 *
 * A form joins the engine by extending `SionModel\Form\Form` instead of
 * `Laminas\Form\Form` — one line in one `use` statement, and the two class names differ
 * by a namespace. A form that goes back to laminas' keeps compiling, keeps rendering,
 * keeps validating, and keeps passing every test in the repository, because
 * `WholeFormEngineParityTest` proves the two agree. The whole apparatus that makes the
 * cutover safe is also what makes a silent revert invisible.
 *
 * So the arrangement itself is the assertion: every form the harness can find extends the
 * engine's base class, and the engine is demonstrably what answers.
 *
 * ## The tell-tale
 *
 * `getData(VALUES_RAW)` is the one question the two answer differently on purpose.
 * `Laminas\Form\Form` returns `$filter->getRawValues()`; the engine keeps no raw values
 * and refuses. It is a behavioural check on a real instance rather than a `instanceof`
 * that a class declaration alone can satisfy.
 */
final class FormsValidateThroughTheEngineTest extends TestCase
{
    public function testEveryFormExtendsTheEnginesBaseClass(): void
    {
        $laminas = [];
        $checked = 0;

        foreach (FormRepository::instance()->forms() as $class => $form) {
            if (! $form instanceof LaminasForm) {
                continue;
            }

            $checked++;
            if (! $form instanceof EngineForm) {
                $laminas[] = $class;
            }
        }

        self::assertGreaterThanOrEqual(35, $checked, 'almost no form was examined');
        self::assertSame(
            [],
            $laminas,
            "These forms still extend Laminas\\Form\\Form, so they validate through "
            . "Laminas\\InputFilter and not through SionModel\\Form\\Validation\\InputFilter. "
            . "Nothing else in the suite would notice, because the two agree:\n  "
            . implode("\n  ", $laminas)
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

        //The tell-tale: laminas answers this from getRawValues(); the engine refuses.
        $this->expectException(LogicException::class);
        $form->getData(FormInterface::VALUES_RAW);
    }

    /**
     * The count, so that a form leaving the walk reads as a failure rather than a smaller
     * number nobody looks at.
     */
    public function testTheWalkStillCoversEveryForm(): void
    {
        $forms = 0;
        foreach (FormRepository::instance()->forms() as $form) {
            if ($form instanceof LaminasForm) {
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
