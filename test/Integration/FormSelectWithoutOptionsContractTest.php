<?php

namespace SchoenstattTest\Integration;

use Books\View\Helper\FormSelectWithoutOptions;
use Laminas\Form\Element\Select;
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
}
