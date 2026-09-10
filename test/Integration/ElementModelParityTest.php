<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Form\Element as LaminasElement;
use Laminas\Form\ElementInterface;
use Laminas\Form\Fieldset;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Element\ElementSurface;
use SionModel\Form\Element as Ours;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function implode;
use function array_slice;
use function preg_replace;
use function sprintf;
use function strlen;
use function substr;
use function var_export;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Element/ElementSurface.php';

/**
 * Every element of ours answers what its laminas twin answers, on the real definitions.
 *
 * ## Why a twin and not the baseline file
 *
 * `test/Element/element-surface.php` records what the application's elements answer, and
 * `ElementSurfaceTest` will be what proves the replacement kept those answers once the
 * elements are actually swapped in. Until then nothing in `SionModel\Form\Element` is
 * reached by a single form, and a class nothing constructs is a class nothing has checked.
 *
 * So this builds the twin: for 432 of the 439 leaf elements the application really has —
 * everything but the seven `Phone`s, which are re-parented with the swap — it constructs
 * ours from the **same definition**, the same name, the same options array and the same
 * attributes, and asks both the same questions through
 * {@see ElementSurface::describe()} rather than a second list that could drift from the one
 * the baseline was taken with.
 *
 * That is a legitimate parallel comparison and not the self-agreeing kind: the two sides are
 * different code reading the same input, which is exactly what `FormSpecification` could do
 * and the *form* model cannot, because a form's element definitions are `add([...])` calls
 * buried in a constructor. Here the definition is available, so it can drive both.
 *
 * ## What is replayed, and why only that
 *
 * A definition is not the whole story. A form factory writes a select's options at runtime —
 * `RoleFormFactory` hands 496 associations to `associationId` — and no options array can
 * carry that; `BookForm::forLibrary()` overrides an empty option; a controller sets a value.
 * Those runtime writes are replayed onto the twin, and **only where they actually differ
 * from what the definition declared**. Measured across the 136 selects: 55 hold options no
 * definition could state and 1 has an empty option written after construction. Replaying
 * unconditionally would hide a broken `value_options` promotion on the other 81; replaying
 * nothing would compare two empty lists 55 times.
 */
final class ElementModelParityTest extends TestCase
{
    /**
     * laminas' class => ours. Every element class the census found has to appear here or in
     * {@see NOT_YET_REPLACED}; an unrecognised one fails rather than being skipped, because
     * a silently unmeasured element type is the one that breaks a form.
     *
     * @var array<class-string, class-string>
     */
    private const TWINS = [
        LaminasElement::class                           => Ours\Element::class,
        LaminasElement\Text::class                      => Ours\Text::class,
        LaminasElement\Textarea::class                  => Ours\Textarea::class,
        LaminasElement\Hidden::class                    => Ours\Hidden::class,
        LaminasElement\Submit::class                    => Ours\Submit::class,
        LaminasElement\Button::class                    => Ours\Button::class,
        LaminasElement\File::class                      => Ours\File::class,
        LaminasElement\Url::class                       => Ours\Url::class,
        LaminasElement\Email::class                     => Ours\Email::class,
        LaminasElement\Number::class                    => Ours\Number::class,
        LaminasElement\Date::class                      => Ours\Date::class,
        LaminasElement\Checkbox::class                  => Ours\Checkbox::class,
        LaminasElement\Select::class                    => Ours\Select::class,
        LaminasElement\Csrf::class                      => Ours\Csrf::class,
        LaminasElement\DateSelect::class                => Ours\DateSelect::class,
    ];

    /**
     * Classes this step deliberately leaves where they are, each with the step that takes
     * them.
     *
     * @var array<class-string, string>
     */
    private const NOT_YET_REPLACED = [
        //A fieldset, and fieldsets belong to the form model: Collection composes a target
        //element and a count, and `Laminas\Form\Fieldset::add()` is what holds it together.
        LaminasElement\Collection::class => 'the form model replaces Fieldset and Collection together',

        //Not replaced at all: laminas has DateSelect extend MonthSelect, and the census
        //found zero MonthSelect elements in any form. `SionModel\Form\Element\DateSelect`
        //therefore stands alone and there is nothing here to compare it against.
        LaminasElement\MonthSelect::class => 'no form has one; DateSelect does not inherit from it here',

        //Ours already, but still `extends Laminas\Form\Element` and still supplying an input
        //specification. Re-parenting it is a production change and belongs with the swap,
        //not with a step that adds unreached classes.
        Ours\Phone::class => 're-parented when the elements are swapped in',
    ];

    /**
     * A floor on comparisons. Every assertion is inside two nested loops over discovered
     * sets, so a mapping that quietly matched nothing would pass in silence. 439 elements
     * times the ten-odd questions `describe()` asks.
     */
    private const COMPARISON_FLOOR = 2400;

    public function testEveryElementOfOursAnswersWhatItsLaminasTwinAnswers(): void
    {
        $differences = [];
        $comparisons = 0;
        $covered     = [];

        foreach (ElementSurface::elements() as $path => $element) {
            if ($element instanceof Fieldset) {
                continue;
            }

            $class = $element::class;
            if (array_key_exists($class, self::NOT_YET_REPLACED)) {
                continue;
            }

            self::assertArrayHasKey(
                $class,
                self::TWINS,
                sprintf(
                    '%s is a %s, which has no replacement and is not listed as deferred. '
                    . 'Add it to TWINS or say in NOT_YET_REPLACED which step takes it.',
                    $path,
                    $class
                )
            );

            $covered[$class] = true;
            $theirs          = ElementSurface::describe($element);
            $ours            = ElementSurface::describe(self::twin($element, self::TWINS[$class]));

            foreach ($theirs as $field => $expected) {
                //Guaranteed to differ, and the whole point: this is the field that says
                //which of ours took which of theirs.
                if ('class' === $field) {
                    continue;
                }

                $comparisons++;
                $got = array_key_exists($field, $ours) ? $ours[$field] : '<<absent>>';
                if ($got === $expected) {
                    continue;
                }

                $differences[] = sprintf(
                    '%s (%s): %s is %s, laminas says %s',
                    $path,
                    self::TWINS[$class],
                    $field,
                    self::brief($got),
                    self::brief($expected)
                );
            }
        }

        self::assertGreaterThanOrEqual(
            self::COMPARISON_FLOOR,
            $comparisons,
            'almost nothing was compared, so this test proves nothing'
        );

        self::assertSame(
            [],
            $differences,
            "A replacement element answers differently from the laminas element it replaces:\n  "
            . implode("\n  ", array_slice($differences, 0, 30))
        );

        //A twin nothing exercised is a twin nothing checked. The two deferred classes are
        //not in TWINS, so this compares like with like.
        $unexercised = array_diff(array_keys(self::TWINS), array_keys($covered));
        self::assertSame(
            [],
            $unexercised,
            'These replacements were never compared against a real element: '
            . implode(', ', $unexercised)
        );
    }

    /**
     * The same definition, built by the replacement class.
     *
     * @param class-string $ours
     */
    private static function twin(ElementInterface $element, string $ours): ElementInterface
    {
        $options = $element->getOptions();

        /** @var ElementInterface $twin */
        $twin = new $ours($element->getName(), $options);
        $twin->setAttributes($element->getAttributes());

        //Replayed only where the element's options are not the ones its definition
        //declared — i.e. exactly where a form factory wrote them at runtime. `RoleForm`
        //declares `'value_options' => []` and `RoleFormFactory` fills in 496 associations,
        //so comparing the declaration against the element is what tells the two apart.
        //Replaying unconditionally would hide a broken `value_options` promotion; never
        //replaying leaves 19 selects comparing two empty lists.
        if ($element instanceof LaminasElement\Select && $twin instanceof Ours\Select) {
            if ($element->getValueOptions() !== ($options['value_options'] ?? [])) {
                $twin->setValueOptions($element->getValueOptions());
            }

            //`Books\Form\BookForm::forLibrary()` calls setEmptyOption(null) on a library
            //that uses no collections, overriding the `''` its own definition declares.
            if ($element->getEmptyOption() !== ($options['empty_option'] ?? null)) {
                $twin->setEmptyOption($element->getEmptyOption());
            }
        }

        //A value is replayed unless the element computes it rather than storing it. A Csrf
        //element mints a fresh token on every read and a month/date select assembles its
        //value out of sub-elements, so both reproduce theirs from the definition alone and
        //feeding the answer back would only ask the twin to parse its own output — which,
        //for a date select handed `0000-00-00`, throws.
        //A null value is skipped as well, and for the same reason: it means the element was
        //never given one. Handing `null` to a checkbox is not a no-op — it normalises to the
        //unchecked value — so replaying it would invent a value on the twin that the
        //original does not have.
        if (
            null !== $element->getValue()
            && ! $element instanceof LaminasElement\Csrf
            && ! $element instanceof LaminasElement\MonthSelect
        ) {
            //Through setAttribute() rather than setValue(), because that is how a form
            //states a value — `'attributes' => ['value' => 50]`, 35 of them — and the
            //diversion of that key into setValue() is behaviour worth measuring. Handing
            //it to setValue() directly would leave the diversion untested: measured by
            //breaking it, which produced a single difference that way and filled the
            //30-line report this way.
            $twin->setAttribute('value', $element->getValue());
        }

        return $twin;
    }

    private static function brief(mixed $value): string
    {
        $printed = (string) preg_replace('/\s+/', ' ', var_export($value, true));

        return strlen($printed) > 90 ? substr($printed, 0, 87) . '...' : $printed;
    }
}
