<?php

namespace SchoenstattTest\Integration;

use SionModel\Form\BootstrapFormRenderer;
use Books\View\Helper\FormSelectWithoutOptions;
use Laminas\Form\Element\Select;
use Laminas\I18n\Translator\Loader\PhpMemoryArray;
use Laminas\View\Renderer\PhpRenderer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Characterization test for Books\View\Helper\FormSelectWithoutOptions, which
 * used to extend Laminas\Form\View\Helper\FormSelect (now `@final`) purely to
 * intercept renderOptions().
 *
 * Its job: render a <select> containing only the options that are currently
 * selected — used for the huge author/editor/publication pickers in
 * books/publications/fields-partial.phtml, where shipping all options would be
 * enormous and the remaining choices are fetched over AJAX.
 *
 * Written against the pre-change code and passing there.
 *
 * Needs vendor/ (laminas-form, laminas-view), so it runs in the capsule:
 * php composer.phar integration
 */
class FormSelectWithoutOptionsContractTest extends TestCase
{
    private function helper(): FormSelectWithoutOptions
    {
        $helper = new FormSelectWithoutOptions();
        $helper->setView(new PhpRenderer());

        return $helper;
    }

    private function multiSelect(): Select
    {
        $select = new Select('authorsAll');
        $select->setValueOptions([1 => 'Kentenich', 2 => 'Schoenstatt', 3 => 'Unused']);
        $select->setAttribute('multiple', true);
        $select->setValue([1, 3]);

        return $select;
    }

    /**
     * The whole point: unselected options are dropped from the markup.
     */
    public function testRendersOnlySelectedOptions(): void
    {
        $html = ($this->helper())($this->multiSelect());

        self::assertStringContainsString('>Kentenich<', $html);
        self::assertStringContainsString('>Unused<', $html);
        self::assertStringNotContainsString('>Schoenstatt<', $html);
    }

    public function testSelectedOptionsAreMarkedSelected(): void
    {
        $html = ($this->helper())($this->multiSelect());

        self::assertSame(2, substr_count($html, 'selected="selected"'));
    }

    /**
     * Byte-for-byte output of the current implementation, so any rendering
     * drift from the base-class swap is caught rather than eyeballed.
     */
    public function testRenderedMarkupIsUnchanged(): void
    {
        $expected = '<select name="authorsAll&#x5B;&#x5D;" multiple="multiple">'
            . '<option value="1" selected="selected">Kentenich</option>' . "\n"
            . '<option value="3" selected="selected">Unused</option>'
            . '</select>';

        self::assertSame($expected, ($this->helper())($this->multiSelect()));
    }

    /**
     * renderOptions() is public API on this helper and filters independently of
     * the element, so it is pinned separately.
     */
    public function testRenderOptionsFiltersToTheSelectedSet(): void
    {
        $html = $this->helper()->renderOptions([1 => 'A', 2 => 'B', 3 => 'C'], [2]);

        self::assertSame('<option value="2" selected="selected">B</option>', $html);
    }

    public function testRenderOptionsWithNothingSelectedRendersNothing(): void
    {
        self::assertSame('', $this->helper()->renderOptions([1 => 'A', 2 => 'B'], []));
    }

    /**
     * fields-partial.phtml calls setTranslatorEnabled(false) on this helper
     * before use, so that method has to keep existing.
     */
    public function testTranslatorCanBeDisabled(): void
    {
        $helper = $this->helper();
        $helper->setTranslatorEnabled(false);

        self::assertFalse($helper->isTranslatorEnabled());
    }

    /**
     * ...and disabling it has to actually stop option labels being translated.
     * Rendering is delegated now, so the translator state must travel with the
     * delegation — otherwise setTranslatorEnabled(false) would silently become
     * a no-op.
     */
    public function testDisablingTheTranslatorSuppressesLabelTranslation(): void
    {
        $translator = new \Laminas\I18n\Translator\Translator();
        $translator->getPluginManager()->setService('PhpMemoryArray', new PhpMemoryArray([
            'default' => ['en_US' => ['Kentenich' => 'TRANSLATED']],
        ]));
        $translator->addRemoteTranslations('PhpMemoryArray', 'default');
        $translator->setLocale('en_US');

        $enabled = $this->helper();
        $enabled->setTranslator($translator);
        self::assertStringContainsString('>TRANSLATED<', ($enabled)($this->multiSelect()));

        $disabled = $this->helper();
        $disabled->setTranslator($translator);
        $disabled->setTranslatorEnabled(false);
        self::assertStringContainsString('>Kentenich<', ($disabled)($this->multiSelect()));
    }

    /**
     * An empty option that is not itself selected was dropped by the old
     * implementation, because FormSelect::render() prepends it only after the
     * point where renderOptions() filtered.
     */
    public function testUnselectedEmptyOptionIsDropped(): void
    {
        $select = $this->multiSelect();
        $select->setEmptyOption('Choose...');

        $html = ($this->helper())($select);

        self::assertStringNotContainsString('Choose...', $html);
    }

    // -- the Symfony-side reproduction ---------------------------------------

    /**
     * `SionModel\Form\BootstrapFormRenderer::selectWithoutOptions()` renders the same choices.
     *
     * The publication edit form is served by the Symfony kernel, where none of the
     * laminas view helpers can be reached — each ends in `$this->view->…`, which wants an
     * MvcEvent a ported route does not have. So the helper above is reproduced, and these
     * assertions are what keep the two from drifting.
     *
     * **Not asserted byte-for-byte, and deliberately so.** The reproduction emits
     * `multiple` and `selected` as bare boolean attributes where laminas emits
     * `multiple="multiple"` and `selected="selected"`; that difference is systematic
     * across every element this renderer produces, is already accounted for by
     * `tools/port-baseline.php`'s normalization, and is not what this helper is for. What
     * *is* specific to this helper — which options survive, and in what order — is
     * asserted exactly.
     */
    public function testTheSymfonyReproductionSelectsTheSameOptions(): void
    {
        $html = $this->reproduction()->selectWithoutOptions($this->multiSelect(), false);

        self::assertStringContainsString('>Kentenich<', $html);
        self::assertStringContainsString('>Unused<', $html);
        self::assertStringNotContainsString('>Schoenstatt<', $html);
        self::assertSame(2, substr_count($html, ' selected'));
        self::assertStringContainsString('name="authorsAll&#x5B;&#x5D;"', $html);
    }

    /**
     * The narrowed list follows the *selected* order, not the option order.
     *
     * `onlySelected()` walks `$selectedOptions` and looks each one up, so a publication
     * whose authors were chosen out of table order keeps the order they were chosen in.
     * Reproducing the option order instead would silently reorder every author list on
     * the page, which is the kind of difference nothing fails on.
     */
    public function testBothImplementationsFollowTheSelectedOrder(): void
    {
        $select = $this->multiSelect();
        $select->setValue([3, 1]);

        $laminas = ($this->helper())($select);
        self::assertLessThan(
            strpos($laminas, '>Kentenich<'),
            strpos($laminas, '>Unused<'),
            'precondition: the laminas helper orders by the selected values'
        );

        $ported = $this->reproduction()->selectWithoutOptions($select, false);
        self::assertLessThan(strpos($ported, '>Kentenich<'), strpos($ported, '>Unused<'));
    }

    /**
     * The unselected empty option is dropped on both sides.
     *
     * In laminas that falls out of where the old subclass filtered rather than from a
     * decision, so it is exactly the sort of behaviour a reproduction written from the
     * *description* of the helper would miss.
     */
    public function testTheSymfonyReproductionAlsoDropsAnUnselectedEmptyOption(): void
    {
        $select = $this->multiSelect();
        $select->setEmptyOption('Choose...');

        self::assertStringNotContainsString(
            'Choose...',
            $this->reproduction()->selectWithoutOptions($select, false)
        );
    }

    /**
     * A selected empty option survives, which is the branch that makes the rule a rule
     * rather than "always drop it".
     */
    public function testASelectedEmptyOptionSurvivesOnBothSides(): void
    {
        $select = new Select('mainPublicationId');
        $select->setValueOptions([1 => 'Kentenich']);
        $select->setEmptyOption('Choose...');
        $select->setValue('');

        self::assertStringContainsString('Choose...', ($this->helper())($select));
        self::assertStringContainsString(
            'Choose...',
            $this->reproduction()->selectWithoutOptions($select, false)
        );
    }

    /**
     * No `form-control` class, because none of the five call sites is a row.
     *
     * TwbBundle adds that class in `formRow`; `fields-partial.phtml` builds the
     * `form-group` by hand and calls this helper directly, so laminas emits a bare
     * `<select>`. The reproduction defaulted to adding it and put a class on all five
     * pickers — a difference no assertion here saw, because every test above checks
     * *which options* are rendered and none checked the element's own attributes. Found
     * by `tools/port-baseline.php`, which is the answer to "why keep running that when
     * the tests pass".
     */
    public function testTheSymfonyReproductionAddsNoBootstrapClass(): void
    {
        $laminas = ($this->helper())($this->multiSelect());
        self::assertStringNotContainsString('form-control', $laminas, 'precondition: laminas adds no class');

        self::assertStringNotContainsString(
            'form-control',
            $this->reproduction()->selectWithoutOptions($this->multiSelect(), false)
        );
    }

    /**
     * A picker that has never been set drops its empty option, on both sides.
     *
     * The distinction this pins is `(array) null` — the empty array — against `[null]`.
     * The helper casts, so `in_array('', $selected)` is false and the empty option goes;
     * building the array by hand as `[$raw]` makes it `[null]`, and `null == ''` under
     * the loose comparison the original uses, so the option stayed. Both `mainPublicationId`
     * and `translatedFromPublicationId` are exactly this case on a publication that names
     * no main edition, which is most of them.
     */
    public function testAnUnsetPickerDropsItsEmptyOptionOnBothSides(): void
    {
        $select = new Select('mainPublicationId');
        $select->setValueOptions([1 => 'Kentenich']);
        $select->setEmptyOption('Choose...');
        //no setValue() at all: getValue() answers null, as it does for a publication
        //whose main edition has never been chosen

        self::assertStringNotContainsString('Choose...', ($this->helper())($select));
        self::assertStringNotContainsString(
            'Choose...',
            $this->reproduction()->selectWithoutOptions($select, false)
        );
    }

    private function reproduction(): BootstrapFormRenderer
    {
        //The translator is the identity function: what these tests check is which options
        //are rendered, and fields-partial.phtml disables translation on this helper
        //anyway.
        return new BootstrapFormRenderer(static fn (string $message): string => $message);
    }
}
