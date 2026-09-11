<?php

namespace SchoenstattTest\Integration;

use SionModel\Form\Element\Select;
use PHPUnit\Framework\TestCase;
use SionModel\Form\BootstrapFormRenderer;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Golden master for the narrowed-<select> rendering that
 * `SionModel\Form\BootstrapFormRenderer::selectWithoutOptions()` performs.
 *
 * Its job: render a <select> containing only the options that are currently selected —
 * the author/editor/publication pickers on the publication edit form, where shipping the
 * full option list would be enormous and the remaining choices are fetched over AJAX
 * (`templates/books/_publication-fields.html.twig`, five call sites, all passing
 * `false`).
 *
 * **This was a parity harness and is now a golden master.** It used to render every case
 * twice, through `Books\View\Helper\FormSelectWithoutOptions` and through the
 * reproduction, and assert the two agreed. The laminas helper extended
 * `Laminas\Form\View\Helper\AbstractHelper` — hence `Laminas\I18n\View\Helper\
 * AbstractTranslatorHelper` — so it could not survive the removal of laminas-i18n and was
 * deleted with it in 2026-09. Every string frozen below was captured from the running
 * code before that deletion: what the laminas helper rendered is recorded in
 * `testRenderedMarkupIsUnchanged`, and what the reproduction renders is what each case
 * now asserts. The reproduction must keep matching them.
 *
 * The two are not byte-identical and never were: the reproduction emits `multiple` and
 * `selected` as bare boolean attributes where laminas emitted `multiple="multiple"` and
 * `selected="selected"`, and terminates its last <option> with a newline. That difference
 * is systematic across every element this renderer produces and is already accounted for
 * by `tools/port-baseline.php`'s normalization; `testRenderedMarkupIsUnchanged` spells it
 * out as a substitution so that it stays reviewable and so that any *other* drift —
 * a lost `[]` on a multiple name, a reordered attribute — fails.
 *
 * Method names that speak of "both implementations" or "both sides" are kept verbatim so
 * the history stays greppable. The second side is now the frozen literal.
 *
 * Removed with the helper, having no counterpart on the reproduction:
 * - `testRenderOptionsFiltersToTheSelectedSet` and
 *   `testRenderOptionsWithNothingSelectedRendersNothing` pinned the helper's public
 *   `renderOptions()`, a seam that existed because the pre-2026 subclass overrode it.
 *   The reproduction narrows the element and renders it in one pass; there is no options
 *   list to hand in and nothing left to call.
 * - `testTranslatorCanBeDisabled` asserted `isTranslatorEnabled()` after
 *   `setTranslatorEnabled(false)`, i.e. laminas translator-aware-helper state. The
 *   reproduction holds no such state — translation is the `$translateOptions` argument —
 *   so the assertion has no subject. The *behaviour* that method guarded is still covered
 *   by `testDisablingTheTranslatorSuppressesLabelTranslation` below.
 * - `testTheSymfonyReproductionSelectsTheSameOptions` and
 *   `testTheSymfonyReproductionAddsNoBootstrapClass` were the reproduction's halves of
 *   `testRendersOnlySelectedOptions` / `testRenderedMarkupIsUnchanged`; with the laminas
 *   halves gone they assert nothing the frozen markup does not already assert exactly,
 *   including the absence of `form-control`.
 *
 * Needs vendor/ (laminas-form, for the Select element the renderer takes), so it runs in
 * the capsule: php composer.phar integration
 */
class FormSelectWithoutOptionsContractTest extends TestCase
{
    /**
     * What the deleted laminas helper rendered for {@see multiSelect()}, captured
     * 2026-09 from the running code. Kept as the reference point of the port.
     */
    private const LAMINAS_MULTI_SELECT = '<select name="authorsAll&#x5B;&#x5D;" multiple="multiple">'
        . '<option value="1" selected="selected">Kentenich</option>' . "\n"
        . '<option value="3" selected="selected">Unused</option>'
        . '</select>';

    /**
     * The reproduction's own output for the same element: the same markup in the boolean
     * attribute spelling, with the trailing newline the option loop always emits.
     */
    private const MULTI_SELECT = '<select name="authorsAll&#x5B;&#x5D;" multiple>'
        . '<option value="1" selected>Kentenich</option>' . "\n"
        . '<option value="3" selected>Unused</option>' . "\n"
        . '</select>';

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
        $html = $this->reproduction()->selectWithoutOptions($this->multiSelect(), false);

        self::assertStringContainsString('>Kentenich<', $html);
        self::assertStringContainsString('>Unused<', $html);
        self::assertStringNotContainsString('>Schoenstatt<', $html);
    }

    public function testSelectedOptionsAreMarkedSelected(): void
    {
        $html = $this->reproduction()->selectWithoutOptions($this->multiSelect(), false);

        self::assertSame(2, substr_count($html, ' selected'));
    }

    /**
     * Byte-for-byte output, so any rendering drift is caught rather than eyeballed.
     *
     * The second assertion is the parity claim the deleted helper used to make in code:
     * spell the boolean attributes out and drop the last option's newline, and what is
     * left has to be the laminas markup exactly — including the `[]` on the name, without
     * which a browser posts only the last selected author, and the absence of any
     * `form-control` class, which none of the five call sites is a row for.
     */
    public function testRenderedMarkupIsUnchanged(): void
    {
        $html = $this->reproduction()->selectWithoutOptions($this->multiSelect(), false);

        self::assertSame(self::MULTI_SELECT, $html);

        $inLaminasSpelling = str_replace(
            [' multiple>', ' selected>', "</option>\n</select>"],
            [' multiple="multiple">', ' selected="selected">', '</option></select>'],
            $html
        );
        self::assertSame(self::LAMINAS_MULTI_SELECT, $inLaminasSpelling);
    }

    /**
     * Disabling translation has to actually stop option labels being translated.
     *
     * This was `setTranslatorEnabled(false)` on the laminas helper and is the
     * `$translateOptions` argument here; all five call sites in
     * `_publication-fields.html.twig` pass `false`, so the untranslated rendering is the
     * production path and the translated one only proves the flag is doing the work.
     */
    public function testDisablingTheTranslatorSuppressesLabelTranslation(): void
    {
        $renderer = new BootstrapFormRenderer(
            static fn (string $message): string => $message === 'Kentenich' ? 'TRANSLATED' : $message
        );

        self::assertSame(
            '<select name="authorsAll&#x5B;&#x5D;" multiple>'
                . '<option value="1" selected>TRANSLATED</option>' . "\n"
                . '<option value="3" selected>Unused</option>' . "\n"
                . '</select>',
            $renderer->selectWithoutOptions($this->multiSelect(), true)
        );

        self::assertSame(
            self::MULTI_SELECT,
            $renderer->selectWithoutOptions($this->multiSelect(), false)
        );
    }

    /**
     * An empty option that is not itself selected is dropped.
     *
     * In laminas that fell out of *where* the old subclass filtered — `FormSelect::
     * render()` prepends the empty option after the point renderOptions() had narrowed —
     * rather than from a decision, so it is exactly the sort of behaviour a reproduction
     * written from the *description* of the helper would miss.
     */
    public function testUnselectedEmptyOptionIsDropped(): void
    {
        $select = $this->multiSelect();
        $select->setEmptyOption('Choose...');

        $html = $this->reproduction()->selectWithoutOptions($select, false);

        self::assertStringNotContainsString('Choose...', $html);
        self::assertSame(self::MULTI_SELECT, $html);
    }

    /**
     * The narrowed list follows the *selected* order, not the option order.
     *
     * The narrowing walks the selected values and looks each one up, so a publication
     * whose authors were chosen out of table order keeps the order they were chosen in.
     * Following the option order instead would silently reorder every author list on the
     * page, which is the kind of difference nothing fails on.
     */
    public function testBothImplementationsFollowTheSelectedOrder(): void
    {
        $select = $this->multiSelect();
        $select->setValue([3, 1]);

        self::assertSame(
            '<select name="authorsAll&#x5B;&#x5D;" multiple>'
                . '<option value="3" selected>Unused</option>' . "\n"
                . '<option value="1" selected>Kentenich</option>' . "\n"
                . '</select>',
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

        //laminas: <select name="mainPublicationId"><option value="" selected="selected">Choose...</option></select>
        self::assertSame(
            '<select name="mainPublicationId">'
                . '<option value="" selected>Choose...</option>' . "\n"
                . '</select>',
            $this->reproduction()->selectWithoutOptions($select, false)
        );
    }

    /**
     * A picker that has never been set drops its empty option.
     *
     * The distinction this pins is `(array) null` — the empty array — against `[null]`.
     * The renderer casts, so `in_array('', $selected)` is false and the empty option goes;
     * building the array by hand as `[$raw]` makes it `[null]`, and `null == ''` under the
     * loose comparison, so the option would stay. Both `mainPublicationId` and
     * `translatedFromPublicationId` are exactly this case on a publication that names no
     * main edition, which is most of them.
     */
    public function testAnUnsetPickerDropsItsEmptyOptionOnBothSides(): void
    {
        $select = new Select('mainPublicationId');
        $select->setValueOptions([1 => 'Kentenich']);
        $select->setEmptyOption('Choose...');
        //no setValue() at all: getValue() answers null, as it does for a publication
        //whose main edition has never been chosen

        //byte-identical to laminas here, there being no option to spell an attribute on
        self::assertSame(
            '<select name="mainPublicationId"></select>',
            $this->reproduction()->selectWithoutOptions($select, false)
        );
    }

    private function reproduction(): BootstrapFormRenderer
    {
        //The translator is the identity function: what these tests check is which options
        //are rendered, and every call site disables option translation anyway.
        return new BootstrapFormRenderer(static fn (string $message): string => $message);
    }
}
