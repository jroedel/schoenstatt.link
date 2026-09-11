<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Form\Form;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Form\Engine;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Form\CommentForm;

use function array_key_exists;
use function array_keys;
use function count;
use function implode;
use function sprintf;
use function str_repeat;
use function strlen;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';
require_once __DIR__ . '/../Form/Engine.php';

/**
 * A form's write surface is its elements, and nothing else.
 *
 * ## The rule
 *
 * `SionModel\Form\Validation\FormSpecification::of()` writes a rule set for **every
 * element** and for **every key of `getInputFilterSpecification()`**, and the engine's
 * `getValues()` then returns a value for every one of them — present, not absent — and
 * controllers hand that array straight to `SionTable::createEntity()`/`updateEntity()`,
 * which writes whatever the entity's `updateColumns` map recognises. (laminas'
 * `getInputFilter()` assembled the same two halves, and was what this file read until the
 * engine became the thing that runs.)
 *
 * So a specification key naming no element is not the harmless dead weight it looks
 * like. It is a column the form writes and no visitor can see.
 *
 * ## What that cost, before this file existed
 *
 * `Books\Form\PublicationForm` named `isAccessibleForFree` in its specification and had
 * no element for it — the row is deliberately not rendered, and has been commented out of
 * the laminas partial for years. `ToBit` turned the absent value into `0`, `getValues()`
 * carried it, and `publication.updateColumns` maps it to `IsAccessableForFree`. **Every
 * save of a publication therefore wrote that column to zero.** One row in 10,166 still
 * held a 1 when this was found.
 *
 * `Schoenstatt\Form\PersonForm` had six such keys. Four (`street1`, `street2`,
 * `cityState`, `zip`) map to nothing on the person entity — they are the *association*
 * entity's columns — so they were inert. The other two, `skypeUser` and `slackUser`, map
 * to `SkypeUser` and `SlackUser`, which is 63 and 8 live values respectively: fully
 * validated, written on save, and offered by no form. They now have elements.
 *
 * `SionModel\Form\CommentForm` had the mirror-image fault and is pinned separately below.
 *
 * ## Why this repeats what test/Fuzz/known-form-gaps.php measures
 *
 * That baseline is regenerable on purpose — `fuzz-baseline` rewrites it and the contract
 * is "no new gaps". That is the right shape for the 42 unbounded text fields, which are a
 * work list. It is the wrong shape for this: a regeneration would silently re-accept a
 * form writing an invisible column, and the failure is not a validation message but a
 * column quietly set to zero on production data. `specKeysWithoutElement` reached zero on
 * 2026-09-10 and there is no reason it should ever be non-zero again, so the assertion
 * here is flat.
 */
final class FormWriteSurfaceTest extends TestCase
{
    /**
     * A floor on the forms actually compared, not on the forms discovered: a
     * `Fieldset` is skipped below, so a change that turned every subject into one
     * would leave this test iterating an empty list and passing.
     */
    private const COMPARED_FLOOR = 30;

    public function testEveryInputBelongsToAnElement(): void
    {
        $forms = FormRepository::instance()->forms();

        self::assertGreaterThanOrEqual(
            FormRepository::COUNT_SANITY_FLOOR,
            count($forms),
            'FormRepository found almost nothing, so this suite proves nothing'
        );

        $offenders = [];
        $compared  = 0;
        foreach ($forms as $class => $form) {
            if (! $form instanceof Form) {
                //A bare Fieldset has no input filter of its own to compare against.
                continue;
            }
            $compared++;

            //`has()`, not `getElements()`: the latter excludes fieldsets and collections,
            //and a Collection's specification key is exactly as legitimate as an element's.
            //Books\Form\MassCheckoutForm's `checkout` collection is what proved it.
            foreach (array_keys(Engine::specificationOf($form)) as $name) {
                if (! $form->has((string) $name)) {
                    $offenders[] = sprintf('%s: %s', $class, $name);
                }
            }
        }

        self::assertGreaterThanOrEqual(self::COMPARED_FLOOR, $compared, 'almost nothing was compared');

        self::assertSame(
            [],
            $offenders,
            "These input filter keys name no element on their form. Each one is a field the form "
            . "writes and nobody can see; delete the key, or add the element:\n  "
            . implode("\n  ", $offenders)
        );
    }

    /**
     * The comment body, which was the reverse fault: an element with no specification key,
     * while the specification's only key (`text`) named nothing at all.
     *
     * Unfiltered and unbounded against `comments`.`Comment` varchar(500) under
     * `STRICT_TRANS_TABLES`, so a long comment was a 500 rather than a message; and empty
     * was *valid*, so submitting the form untouched inserted a published, empty row.
     */
    public function testTheCommentBodyIsRequiredFilteredAndBoundedByItsColumn(): void
    {
        $form = new CommentForm();
        $spec = Engine::specificationOf($form, ['security']);

        self::assertArrayNotHasKey('text', $spec, 'the phantom key is back');

        $cases = [
            //value                                    valid   expected output
            ''                                      => [false, null],
            '   '                                   => [false, null],
            '<b>hi</b> '                            => [true,  'hi'],
            str_repeat('x', CommentForm::COMMENT_MAX_LENGTH)     => [true, null],
            str_repeat('x', CommentForm::COMMENT_MAX_LENGTH + 1) => [false, null],
        ];

        foreach ($cases as $value => [$expectedValid, $expectedOutput]) {
            //A new engine per case: it holds the data, the values and the messages of one
            //validation, and reusing one would compare a case against its predecessor's
            //verdict.
            $filter = Engine::of($form, ['security']);
            $filter->setData(['comment' => (string) $value, 'redirect' => '/en/SL500001C']);

            self::assertSame(
                $expectedValid,
                $filter->isValid(),
                sprintf('comment of %d characters', strlen((string) $value))
            );

            if (null !== $expectedOutput) {
                self::assertSame($expectedOutput, $filter->getValues()['comment']);
            }
        }
    }

    /**
     * The two fields whose specification entries were complete and whose elements were
     * missing. `sch_persons.SkypeUser` holds 63 values the person page displays; until
     * these elements existed the only way to set one was a hand-made POST.
     */
    public function testThePersonFormOffersTheSocialFieldsItValidates(): void
    {
        $form = FormRepository::instance()->forms()['Schoenstatt\Form\PersonForm'];

        foreach (['twitterUser', 'instagramUser', 'facebookUrl', 'skypeUser', 'slackUser'] as $name) {
            self::assertTrue($form->has($name), sprintf('%s is not an element on PersonForm', $name));
        }

        //The association entity's columns, which this form never wrote and which its
        //specification nevertheless carried rules for.
        $spec = Engine::specificationOf($form);
        foreach (['street1', 'street2', 'cityState', 'zip'] as $name) {
            self::assertArrayNotHasKey($name, $spec, sprintf('%s is an association column, not a person one', $name));
        }
    }

    /**
     * The column a publication save used to zero on its way past.
     */
    public function testSavingAPublicationDoesNotTouchIsAccessibleForFree(): void
    {
        $form   = FormRepository::instance()->forms()['Books\Form\PublicationForm'];
        $filter = Engine::of($form);

        //**The CSRF rule is deliberately left in place.** Nothing below needs it gone:
        //getValues() returns a value for every rule whatever the verdict, and the verdict
        //is not asserted. Dropping it mattered while this read the form's own assembled
        //filter — FormRepository builds each form once and hands the same object to every
        //test, so `remove('security')` removed it for everybody, and
        //WholeFormEngineParityTest duly reported PublicationForm as the one form whose
        //inputs disagreed, in a run where it passed alone. An engine is built per call
        //from a copy of the specification, so that particular trap is gone; the reasoning
        //is kept because the trap it describes is about shared subjects, not about CSRF.
        //a save that never mentions the field, which is every save: no element renders it
        $filter->setData(['title' => 'A publication', 'resourceId' => null]);
        $filter->isValid();

        self::assertArrayNotHasKey(
            'isAccessibleForFree',
            $filter->getValues(),
            'getValues() carries the field again, so every save writes IsAccessableForFree = 0'
        );
    }
}
