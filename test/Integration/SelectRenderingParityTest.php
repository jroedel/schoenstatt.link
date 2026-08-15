<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Form\BootstrapFormRenderer;
use Laminas\Form\Element\Select;
use Laminas\Form\View\Helper\FormSelect;
use Laminas\View\Renderer\PhpRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function preg_replace;
use function trim;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `App\Form\BootstrapFormRenderer`'s `<select>` markup against `Laminas\Form\View\Helper\FormSelect`'s.
 *
 * ## A two-sided parity test, which most of this port cannot have
 *
 * `App\Sion\EntityEdit` and `App\Sion\EntityCreate` are copies pinned only by a baseline
 * capture, because the laminas actions they reproduce cannot be driven without an MvcEvent.
 * A view helper has no such problem: `FormSelect` needs a `PhpRenderer` and nothing else, so
 * here the original really can be run beside the reproduction and the two compared directly.
 * Where that is possible it is worth far more than a capture — it fails on a laptop in
 * milliseconds instead of after two five-minute captures and a diff nobody reads to the end.
 *
 * ## The defect that prompted it
 *
 * Found by the batch-9 baseline, on `/associations/create`, in all five locales: the ported
 * page rendered **two** empty options where laminas renders one.
 *
 * `FormSelect::render()` merges rather than prepends —
 *
 *     $options = ['' => $emptyOption] + $options;
 *
 * — and `+` is a union, so an element that declares `'empty_option' => ''` *and* carries a
 * `''` key in its value options ends up with exactly one. `AssociationForm::timeZoneId` is
 * the only element in the application that declares both, which is why four batches of edit
 * forms went past without showing it.
 *
 * It took a *create* page to surface for a second reason, and the two are coupled:
 * `validateMultiValue()` returns `[]` for a null value, so laminas selects nothing on an
 * empty form, where the reproduction cast null to `''`. That cast was invisible while the
 * empty option was emitted as separate markup — nothing could match `''` — and became a
 * `selected` attribute on a real option the moment the merge was correct. An edit form's
 * select has a value; only a create form's is null.
 */
final class SelectRenderingParityTest extends TestCase
{
    /**
     * Element shapes that have each been wrong at least once, plus the plain cases.
     *
     * @return array<string, array{array<string, mixed>, mixed}>
     */
    public static function selects(): array
    {
        return [
            'empty option and an empty value option, no value' => [
                [
                    'empty_option'  => '',
                    'value_options' => ['' => '', 'Pacific/Midway' => 'Midway', 'Europe/Berlin' => 'Berlin'],
                ],
                null,
            ],
            'empty option and an empty value option, a real value' => [
                [
                    'empty_option'  => '',
                    'value_options' => ['' => '', 'Pacific/Midway' => 'Midway', 'Europe/Berlin' => 'Berlin'],
                ],
                'Europe/Berlin',
            ],
            'empty option and an empty value option, the empty value chosen' => [
                [
                    'empty_option'  => '',
                    'value_options' => ['' => '', 'Pacific/Midway' => 'Midway'],
                ],
                '',
            ],
            'a labelled empty option' => [
                ['empty_option' => 'Choose one', 'value_options' => ['a' => 'A', 'b' => 'B']],
                null,
            ],
            'no empty option, no value' => [
                ['value_options' => ['a' => 'A', 'b' => 'B']],
                null,
            ],
            'no empty option, a value' => [
                ['value_options' => ['a' => 'A', 'b' => 'B']],
                'b',
            ],
            'a numeric-keyed list, zero chosen' => [
                ['empty_option' => '', 'value_options' => [0 => 'Zero', 1 => 'One']],
                '0',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('selects')]
    public function testTheOptionsMatchLaminas(array $options, mixed $value): void
    {
        $element = new Select('probe');
        $element->setOptions($options);
        $element->setValue($value);

        $this->assertSame(
            self::optionsOf(self::laminas()->render($element)),
            self::optionsOf(self::ported()->row($element)),
            'the ported select does not render the options laminas renders'
        );
    }

    /** The multiple case, which has its own value handling on both sides. */
    public function testAMultipleSelectMatchesLaminas(): void
    {
        $element = new Select('probe');
        $element->setOptions(['value_options' => ['a' => 'A', 'b' => 'B', 'c' => 'C']]);
        $element->setAttribute('multiple', true);
        $element->setValue(['a', 'c']);

        $this->assertSame(
            self::optionsOf(self::laminas()->render($element)),
            self::optionsOf(self::ported()->row($element)),
            'a multiple select does not mark the same options selected'
        );
    }

    /**
     * The `<option>` list alone, with whitespace between tags collapsed.
     *
     * The ported side is rendered through the public `row()`, which is the path a template
     * actually takes — it wraps the select in a form-group and a label, and the extraction
     * below reaches past both.
     *
     * The `<select>` element's own attributes are deliberately out of scope: the two
     * renderers order them differently by design and `BootstrapFormRendererTest` already
     * covers the attribute whitelist per input type. What is compared here is the part that
     * carries the data — which options exist, in what order, with which one selected.
     */
    private static function optionsOf(string $markup): string
    {
        $inner = preg_replace('#^.*?<select[^>]*>(.*)</select>.*$#s', '$1', $markup) ?? $markup;

        return trim(preg_replace('/>\s+</', '><', $inner) ?? $inner);
    }

    /**
     * The laminas helper, with the **doctype the application sets**.
     *
     * Not a detail: `AbstractHelper::createAttributesString()` asks the Doctype helper
     * whether it is rendering XHTML, and emits `selected="selected"` if so and a bare
     * `selected` if not. A `PhpRenderer` built with no doctype defaults to XHTML, so the
     * first run of this file reported the ported renderer wrong on every selected option —
     * a difference that exists only in the test's own setup. `layout.html.twig` and
     * `layout.phtml` both serve HTML5.
     */
    private static function laminas(): FormSelect
    {
        $view = new PhpRenderer();
        $view->plugin('doctype')->setDoctype('HTML5');

        $helper = new FormSelect();
        $helper->setView($view);

        return $helper;
    }

    private static function ported(): BootstrapFormRenderer
    {
        //The identity translator: option labels are translated by the caller's choice on
        //this renderer, and translation is not what this file is about.
        return new BootstrapFormRenderer(static fn (string $message): string => $message);
    }
}
