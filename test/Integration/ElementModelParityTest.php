<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Form\Element as LaminasElement;
use Laminas\Form\ElementInterface;
use Laminas\Form\Fieldset;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Element\ElementSurface;
use SionModel\Form\Element as Ours;
use SionModel\Form\Element\Registry;

use function array_diff;
use function array_flip;
use function array_values;
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
 * Every element on the site still answers what the laminas element it replaced would.
 *
 * ## Why this outlives the swap
 *
 * `test/Element/element-surface.php` records what the application's elements answer, and
 * `ElementSurfaceTest` compares against it — but that baseline was regenerated *from this
 * code*. It is what catches a later change; it cannot, on its own, say that the replacement
 * was faithful in the first place. That was a one-time measurement: 432 elements changed
 * class and not one other recorded answer moved.
 *
 * This is the repeatable version of it, and it runs in the opposite direction from the way
 * it did before the swap. For each of the 432 real leaf elements — all of them ours now —
 * it builds the **laminas element it replaced** from the same definition, the same name,
 * the same options array and the same attributes in the order `Laminas\Form\Factory`
 * applies them, and asks both the same questions through {@see ElementSurface::describe()}
 * rather than a second list that could drift from the one the baseline was taken with.
 *
 * It is a real parallel comparison and not the self-agreeing kind: two different
 * implementations reading the same input. It lives exactly as long as laminas-form is
 * installed, and goes when the package does.
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
     * Ours => the laminas class it replaced, which is {@see Registry::REPLACEMENTS} read
     * backwards. Taken from that constant rather than restated, so a replacement registered
     * for production and forgotten here cannot go unmeasured.
     *
     * @return array<class-string, class-string>
     */
    private static function twins(): array
    {
        return array_flip(Registry::REPLACEMENTS);
    }

    /**
     * Classes this step deliberately leaves where they are, each with the step that takes
     * them.
     *
     * @var array<class-string, string>
     */
    private const NOT_COMPARED = [
        //A fieldset, and fieldsets belong to the form model: Collection composes a target
        //element and a count, and `Laminas\Form\Fieldset::add()` is what holds it together.
        //Still a laminas class, so it is not in the twins map either.
        LaminasElement\Collection::class => 'the form model replaces Fieldset and Collection together',

        //Ours, and never had a laminas twin to compare against: it replaced
        //`Laminas\Form\Element\Tel`, which laminas marked `@final` and which contributed
        //nothing but a type attribute. `PhoneElementContractTest` is what holds it.
        Ours\Phone::class => 'replaced a final laminas element years ago; no twin to build',
    ];

    /**
     * A floor on comparisons. Every assertion is inside two nested loops over discovered
     * sets, so a mapping that quietly matched nothing would pass in silence. 439 elements
     * times the ten-odd questions `describe()` asks.
     */
    private const COMPARISON_FLOOR = 2400;

    public function testEveryElementAnswersWhatTheLaminasElementItReplacedWould(): void
    {
        $twins       = self::twins();
        $differences = [];
        $comparisons = 0;
        $covered     = [];

        foreach (ElementSurface::elements() as $path => $element) {
            if ($element instanceof Fieldset) {
                continue;
            }

            $class = $element::class;
            if (array_key_exists($class, self::NOT_COMPARED)) {
                continue;
            }

            self::assertArrayHasKey(
                $class,
                $twins,
                sprintf(
                    '%s is a %s, which no entry of Registry::REPLACEMENTS accounts for and '
                    . 'NOT_COMPARED does not excuse. A silently unmeasured element type is '
                    . 'the one that breaks a form.',
                    $path,
                    $class
                )
            );

            $covered[$class] = true;
            $ours            = ElementSurface::describe($element);
            $theirs          = ElementSurface::describe(self::twin($element, $twins[$class]));

            foreach ($ours as $field => $got) {
                //Guaranteed to differ, and the whole point: this is the field that says
                //which of ours took which of theirs.
                if ('class' === $field) {
                    continue;
                }

                $comparisons++;
                $expected = array_key_exists($field, $theirs) ? $theirs[$field] : '<<absent>>';
                if ($got === $expected) {
                    continue;
                }

                $differences[] = sprintf(
                    '%s (%s): %s is %s, laminas says %s',
                    $path,
                    $class,
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
            "An element answers differently from the laminas element it replaced:\n  "
            . implode("\n  ", array_slice($differences, 0, 30))
        );

        //A replacement nothing exercised is a replacement nothing checked. Registry lists
        //what production resolves to, so an entry with no element behind it is either a
        //dead registration or a form that stopped using a type.
        $unexercised = array_diff(array_values(Registry::REPLACEMENTS), array_keys($covered));
        self::assertSame(
            [],
            array_values($unexercised),
            'These replacements were never compared against a real element: '
            . implode(', ', $unexercised)
        );
    }

    /**
     * The same definition, built by the laminas class this element replaced.
     *
     * @param class-string $theirs
     */
    private static function twin(ElementInterface $element, string $theirs): ElementInterface
    {
        $options = $element->getOptions();

        /** @var ElementInterface $twin */
        $twin = new $theirs($element->getName(), $options);
        $twin->setAttributes($element->getAttributes());

        //Replayed only where the element's options are not the ones its definition
        //declared — i.e. exactly where a form factory wrote them at runtime. `RoleForm`
        //declares `'value_options' => []` and `RoleFormFactory` fills in 496 associations,
        //so comparing the declaration against the element is what tells the two apart.
        //Replaying unconditionally would hide a broken `value_options` promotion; never
        //replaying leaves 19 selects comparing two empty lists.
        if ($element instanceof Ours\Select && $twin instanceof LaminasElement\Select) {
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
            && ! $element instanceof Ours\Csrf
            && ! $element instanceof Ours\DateSelect
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
