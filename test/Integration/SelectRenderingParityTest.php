<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use SionModel\Form\Element\Select;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\Form\BootstrapFormRenderer;

use function str_replace;
use function strlen;
use function substr;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `SionModel\Form\BootstrapFormRenderer`'s `<select>` markup against what
 * `Laminas\Form\View\Helper\FormSelect` rendered.
 *
 * ## This was a two-sided parity test and is now a golden master
 *
 * It used to run both sides in the same process: `FormSelect` needed only a `PhpRenderer`,
 * so unlike `App\Sion\EntityEdit` — pinned by a baseline capture because the laminas action
 * it reproduces cannot be driven without an MvcEvent — the original really could be run
 * beside the reproduction and the two compared directly.
 *
 * That ended in 2026-09. `FormSelect` extends `Laminas\Form\View\Helper\AbstractHelper`,
 * which extends `Laminas\I18n\View\Helper\AbstractTranslatorHelper`, so the helper became
 * unloadable the moment laminas-i18n was removed, permanently. **Every `LAMINAS_*` literal
 * below is what the helper rendered for that fixture, captured from the running code before
 * it went** (laminas-form 3.x / laminas-i18n 2.33.0, `PhpRenderer` with the HTML5 doctype
 * the application sets — an XHTML doctype would have spelled `selected="selected"`, and a
 * `PhpRenderer` built with no doctype defaults to XHTML, which is what made the first run of
 * this file report the ported renderer wrong on every selected option). The renderer must
 * keep matching them.
 *
 * ## Where the two legitimately differ
 *
 * The ported side is rendered through the public `row()`, the path a template actually
 * takes, while the laminas side was the bare helper. Three differences follow, and they are
 * systematic across every select the renderer produces:
 *
 * 1. TwbBundle's row wrapper, `<div class="form-group ">…</div>`;
 * 2. `class="form-control"` on the `<select>`, added by the row rather than by the helper;
 * 3. a newline after the *last* `<option>`, which our option loop always emits and laminas
 *    emitted only between options.
 *
 * Each case therefore asserts our exact bytes first and then, via {@see
 * inLaminasSpelling()}, that undoing exactly those three leaves the frozen laminas markup.
 * Spelling the substitution out keeps the parity claim reviewable and makes any *other*
 * drift — a lost `[]` on a multiple name, a reordered attribute, a `selected` in the wrong
 * place — fail. `tools/port-baseline.php` normalizes the same three.
 *
 * ## The defect that prompted the file
 *
 * Found by the batch-9 baseline, on `/associations/create`, in all five locales: the ported
 * page rendered **two** empty options where laminas renders one.
 *
 * `FormSelect::render()` merged rather than prepended —
 *
 *     $options = ['' => $emptyOption] + $options;
 *
 * — and `+` is a union, so an element that declares `'empty_option' => ''` *and* carries a
 * `''` key in its value options ends up with exactly one. `AssociationForm::timeZoneId` is
 * the only element in the application that declares both, which is why four batches of edit
 * forms went past without showing it.
 *
 * It took a *create* page to surface for a second reason, and the two are coupled:
 * `validateMultiValue()` returned `[]` for a null value, so laminas selected nothing on an
 * empty form, where the reproduction cast null to `''`. That cast was invisible while the
 * empty option was emitted as separate markup — nothing could match `''` — and became a
 * `selected` attribute on a real option the moment the merge was correct. An edit form's
 * select has a value; only a create form's is null.
 */
final class SelectRenderingParityTest extends TestCase
{
    /** The TwbBundle row the ported renderer wraps every element in. */
    private const ROW_OPEN = '<div class="form-group ">';

    /**
     * Element shapes that have each been wrong at least once, plus the plain cases, with
     * our current markup and the frozen laminas markup for each.
     *
     * @return array<string, array{array<string, mixed>, mixed, string, string}>
     */
    public static function selects(): array
    {
        return [
            'empty option and an empty value option, no value'                => [
                [
                    'empty_option'  => '',
                    'value_options' => ['' => '', 'Pacific/Midway' => 'Midway', 'Europe/Berlin' => 'Berlin'],
                ],
                null,
                '<div class="form-group "><select name="probe" class="form-control">'
                    . '<option value=""></option>' . "\n"
                    . '<option value="Pacific&#x2F;Midway">Midway</option>' . "\n"
                    . '<option value="Europe&#x2F;Berlin">Berlin</option>' . "\n"
                    . '</select></div>',
                '<select name="probe">'
                    . '<option value=""></option>' . "\n"
                    . '<option value="Pacific&#x2F;Midway">Midway</option>' . "\n"
                    . '<option value="Europe&#x2F;Berlin">Berlin</option>'
                    . '</select>',
            ],
            'empty option and an empty value option, a real value'            => [
                [
                    'empty_option'  => '',
                    'value_options' => ['' => '', 'Pacific/Midway' => 'Midway', 'Europe/Berlin' => 'Berlin'],
                ],
                'Europe/Berlin',
                '<div class="form-group "><select name="probe" class="form-control">'
                    . '<option value=""></option>' . "\n"
                    . '<option value="Pacific&#x2F;Midway">Midway</option>' . "\n"
                    . '<option value="Europe&#x2F;Berlin" selected>Berlin</option>' . "\n"
                    . '</select></div>',
                '<select name="probe">'
                    . '<option value=""></option>' . "\n"
                    . '<option value="Pacific&#x2F;Midway">Midway</option>' . "\n"
                    . '<option value="Europe&#x2F;Berlin" selected>Berlin</option>'
                    . '</select>',
            ],
            'empty option and an empty value option, the empty value chosen'  => [
                [
                    'empty_option'  => '',
                    'value_options' => ['' => '', 'Pacific/Midway' => 'Midway'],
                ],
                '',
                '<div class="form-group "><select name="probe" class="form-control">'
                    . '<option value="" selected></option>' . "\n"
                    . '<option value="Pacific&#x2F;Midway">Midway</option>' . "\n"
                    . '</select></div>',
                '<select name="probe">'
                    . '<option value="" selected></option>' . "\n"
                    . '<option value="Pacific&#x2F;Midway">Midway</option>'
                    . '</select>',
            ],
            'a labelled empty option'                                         => [
                ['empty_option' => 'Choose one', 'value_options' => ['a' => 'A', 'b' => 'B']],
                null,
                '<div class="form-group "><select name="probe" class="form-control">'
                    . '<option value="">Choose one</option>' . "\n"
                    . '<option value="a">A</option>' . "\n"
                    . '<option value="b">B</option>' . "\n"
                    . '</select></div>',
                '<select name="probe">'
                    . '<option value="">Choose one</option>' . "\n"
                    . '<option value="a">A</option>' . "\n"
                    . '<option value="b">B</option>'
                    . '</select>',
            ],
            'no empty option, no value'                                       => [
                ['value_options' => ['a' => 'A', 'b' => 'B']],
                null,
                '<div class="form-group "><select name="probe" class="form-control">'
                    . '<option value="a">A</option>' . "\n"
                    . '<option value="b">B</option>' . "\n"
                    . '</select></div>',
                '<select name="probe">'
                    . '<option value="a">A</option>' . "\n"
                    . '<option value="b">B</option>'
                    . '</select>',
            ],
            'no empty option, a value'                                        => [
                ['value_options' => ['a' => 'A', 'b' => 'B']],
                'b',
                '<div class="form-group "><select name="probe" class="form-control">'
                    . '<option value="a">A</option>' . "\n"
                    . '<option value="b" selected>B</option>' . "\n"
                    . '</select></div>',
                '<select name="probe">'
                    . '<option value="a">A</option>' . "\n"
                    . '<option value="b" selected>B</option>'
                    . '</select>',
            ],
            'a numeric-keyed list, zero chosen'                               => [
                ['empty_option' => '', 'value_options' => [0 => 'Zero', 1 => 'One']],
                '0',
                '<div class="form-group "><select name="probe" class="form-control">'
                    . '<option value=""></option>' . "\n"
                    . '<option value="0" selected>Zero</option>' . "\n"
                    . '<option value="1">One</option>' . "\n"
                    . '</select></div>',
                '<select name="probe">'
                    . '<option value=""></option>' . "\n"
                    . '<option value="0" selected>Zero</option>' . "\n"
                    . '<option value="1">One</option>'
                    . '</select>',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('selects')]
    public function testTheOptionsMatchLaminas(
        array $options,
        mixed $value,
        string $ours,
        string $laminas
    ): void {
        $element = new Select('probe');
        $element->setOptions($options);
        $element->setValue($value);

        $rendered = self::ported()->row($element);

        $this->assertSame($ours, $rendered, 'the ported select markup has changed');
        $this->assertSame(
            $laminas,
            self::inLaminasSpelling($rendered),
            'the ported select does not render the options laminas rendered'
        );
    }

    /** The multiple case, which had its own value handling on both sides. */
    public function testAMultipleSelectMatchesLaminas(): void
    {
        $element = new Select('probe');
        $element->setOptions(['value_options' => ['a' => 'A', 'b' => 'B', 'c' => 'C']]);
        $element->setAttribute('multiple', true);
        $element->setValue(['a', 'c']);

        $rendered = self::ported()->row($element);

        $this->assertSame(
            '<div class="form-group "><select name="probe&#x5B;&#x5D;" multiple class="form-control">'
                . '<option value="a" selected>A</option>' . "\n"
                . '<option value="b">B</option>' . "\n"
                . '<option value="c" selected>C</option>' . "\n"
                . '</select></div>',
            $rendered,
            'the ported multiple select markup has changed'
        );
        $this->assertSame(
            '<select name="probe&#x5B;&#x5D;" multiple>'
                . '<option value="a" selected>A</option>' . "\n"
                . '<option value="b">B</option>' . "\n"
                . '<option value="c" selected>C</option>'
                . '</select>',
            self::inLaminasSpelling($rendered),
            'a multiple select does not mark the same options selected'
        );
    }

    /**
     * **`multiple` spelled as the string it is in HTML, which is how a form actually
     * declares it — and the case that was broken.**
     *
     * `JUser\Form\EditUserForm` writes `'multiple' => 'multiple'`, where the literature
     * search box writes `true`. Two things keyed on the boolean and so did nothing for the
     * string: the `[]` the name needs when a select is multiple, and rendering `multiple`
     * as a bare HTML boolean attribute rather than as `multiple="multiple"`. The first is
     * not cosmetic — without the brackets a browser posts `rolesList=1&rolesList=41&…`,
     * PHP keeps only the last, and saving an account through /users/{id}/edit would have
     * cut it down to a single role. It is asserted once more on its own below, because it
     * is the byte in this literal that costs data rather than pixels.
     */
    public function testAMultipleSelectDeclaredAsAStringMatchesLaminas(): void
    {
        $element = new Select('rolesList');
        $element->setOptions(['value_options' => ['1' => 'administrator', '7' => 'lib_user']]);
        $element->setAttribute('multiple', 'multiple');
        $element->setValue(['1', '7']);

        $rendered = self::ported()->row($element);

        $this->assertSame(
            '<div class="form-group "><select name="rolesList&#x5B;&#x5D;" multiple class="form-control">'
                . '<option value="1" selected>administrator</option>' . "\n"
                . '<option value="7" selected>lib_user</option>' . "\n"
                . '</select></div>',
            $rendered,
            'the ported markup for multiple="multiple" has changed'
        );
        $this->assertSame(
            '<select name="rolesList&#x5B;&#x5D;" multiple>'
                . '<option value="1" selected>administrator</option>' . "\n"
                . '<option value="7" selected>lib_user</option>'
                . '</select>',
            self::inLaminasSpelling($rendered),
            'a select declaring multiple="multiple" does not render the attributes laminas rendered'
        );
        $this->assertStringContainsString('name="rolesList&#x5B;&#x5D;"', $rendered);
    }

    /**
     * Our markup with the three documented row-vs-helper differences undone, which has to
     * leave the laminas markup exactly.
     *
     * Anything else the renderer might change survives the substitution and fails the
     * comparison, which is the whole reason this is written out rather than compared on a
     * loosely extracted option list.
     */
    private static function inLaminasSpelling(string $ours): string
    {
        self::assertStringStartsWith(self::ROW_OPEN, $ours, 'no TwbBundle row around the select');
        self::assertStringEndsWith('</select></div>', $ours, 'the row does not close after the select');

        $bare = substr($ours, strlen(self::ROW_OPEN), -strlen('</div>'));

        return str_replace(
            [' class="form-control"', "</option>\n</select>"],
            ['', '</option></select>'],
            $bare
        );
    }

    private static function ported(): BootstrapFormRenderer
    {
        //The identity translator: option labels are translated by the caller's choice on
        //this renderer, and translation is not what this file is about.
        return new BootstrapFormRenderer(static fn (string $message): string => $message);
    }
}
