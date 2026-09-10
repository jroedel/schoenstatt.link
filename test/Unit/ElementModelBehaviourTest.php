<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use InvalidArgumentException;
use Laminas\Form\Form as LaminasForm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\Form\Element\Checkbox;
use SionModel\Form\Element\DateSelect;
use SionModel\Form\Element\Element;
use SionModel\Form\Element\File;
use SionModel\Form\Element\Select;

use function date;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The element behaviours no real form definition exercises.
 *
 * `test/Integration/ElementModelParityTest` compares every one of these classes against the
 * laminas element it replaces, on all 432 real definitions, and that is the broad check.
 * What it cannot reach is the *edges*: a value nobody posts, a guard nobody trips in the
 * capsule. Each case below is one the application would fail on rather than merely differ
 * on, and each names the caller that depends on it.
 */
final class ElementModelBehaviourTest extends TestCase
{
    /**
     * `SionModel\Form\SionForm::blankUnusableDateSelectValues()` exists solely because of
     * this throw: it blanks anything a date select cannot swallow before `setData()` reaches
     * it, so a hostile post fails validation instead of fataling. Swallowing the exception
     * here would make that guard look unnecessary and quietly accept nonsense.
     */
    public function testADateSelectRefusesAValueItCannotParse(): void
    {
        $element = new DateSelect('nameDay', ['create_empty_option' => true]);

        $this->expectException(InvalidArgumentException::class);
        $element->setValue('the feast of Saint Nobody');
    }

    /** The other half of the same contract: a parsable string splits into three selects. */
    public function testADateSelectSplitsAParsableStringIntoItsThreeSelects(): void
    {
        $element = new DateSelect('nameDay', ['create_empty_option' => true]);
        $element->setValue('1985-09-15');

        self::assertSame('1985', $element->getYearElement()->getValue());
        self::assertSame('09', $element->getMonthElement()->getValue());
        self::assertSame('15', $element->getDayElement()->getValue());
        self::assertSame('1985-09-15', $element->getValue());
    }

    /**
     * Null means *now* unless the form asked for an empty option. Surprising, laminas', and
     * the reason `PersonForm::nameDay` sets `create_empty_option`: without it, every person
     * with no name day would silently acquire today's date.
     */
    public function testANullValueMeansTodayUnlessTheFormAskedForAnEmptyOption(): void
    {
        $withEmptyOption = new DateSelect('nameDay', ['create_empty_option' => true]);
        $withEmptyOption->setValue(null);
        self::assertNull($withEmptyOption->getValue());

        $without = new DateSelect('nameDay');
        $without->setValue(null);
        self::assertSame(date('Y-m-d'), $without->getValue());
    }

    /**
     * A checkbox stores one of its two values and never what it was handed. The table is
     * what makes `'on'`, `'1'`, `1` and `true` all reach the database as `1`.
     */
    #[DataProvider('checkboxValues')]
    public function testACheckboxNormalisesAnythingToOneOfItsTwoValues(mixed $posted, string $stored): void
    {
        $element = new Checkbox('isActive');

        self::assertSame($stored, $element->setValue($posted)->getValue());
    }

    /** @return iterable<string, array{0: mixed, 1: string}> */
    public static function checkboxValues(): iterable
    {
        yield 'the checked value itself' => ['1', '1'];
        yield 'an integer one'           => [1, '1'];
        yield 'boolean true'             => [true, '1'];
        yield "a browser's \"on\""       => ['on', '0'];
        yield 'nothing posted'           => [null, '0'];
        yield 'a stranger'               => ['wat', '0'];
        yield 'the unchecked value'      => ['0', '0'];
    }

    /** A form is free to choose the pair, and two do — `Schoenstatt\Form\SearchForm`'s. */
    public function testACheckboxHonoursItsOwnPairOfValues(): void
    {
        $element = new Checkbox('exMembers', [
            'checked_value'   => 'true',
            'unchecked_value' => 'false',
        ]);

        self::assertSame('true', $element->setValue('true')->getValue());
        self::assertSame('false', $element->setValue('1')->getValue());
    }

    /**
     * 99 element definitions across 40 form files write `'attributes' => ['value' => …]`,
     * and `BootstrapFormRenderer` reads `getValue()` rather than the attribute. Storing it as
     * an attribute instead would render every one of those fields empty.
     */
    public function testAValueAttributeBecomesTheValue(): void
    {
        $element = new Element('sort');
        $element->setAttribute('value', 50);

        self::assertSame(50, $element->getValue());
        self::assertArrayNotHasKey('value', $element->getAttributes());
    }

    /**
     * An option set to null reads as absent. `BootstrapFormRenderer` is written against it:
     * `getOption('form-group')` and `getOption('help-block')` decide whether a wrapper and a
     * hint are drawn at all.
     */
    public function testAnOptionSetToNullReadsAsAbsent(): void
    {
        $element = new Element('note', ['help-block' => null, 'column-size' => 'md-6']);

        self::assertNull($element->getOption('help-block'));
        self::assertSame('md-6', $element->getOption('column-size'));
        //Still present in the array, so nothing that reads the options wholesale loses it.
        self::assertArrayHasKey('help-block', $element->getOptions());
    }

    /**
     * The same `isset()` rule decides whether a select draws an empty option at all, and an
     * empty string is a real answer there rather than a missing one — 83 selects depend on
     * the distinction.
     */
    public function testAnEmptyStringIsARealEmptyOptionAndNullIsNone(): void
    {
        self::assertSame('', (new Select('a', ['empty_option' => '']))->getEmptyOption());
        self::assertNull((new Select('b', ['empty_option' => null]))->getEmptyOption());
        self::assertNull((new Select('c'))->getEmptyOption());
    }

    /**
     * No form sets the enctype itself. Without this, the browser posts the file's name and
     * not its bytes, and the spreadsheet import silently receives nothing.
     */
    public function testAFileInputPutsTheFormIntoMultipartEncoding(): void
    {
        $form    = new LaminasForm('import');
        $element = new File('file');

        self::assertNull($form->getAttribute('enctype'));
        $element->prepareElement($form);
        self::assertSame('multipart/form-data', $form->getAttribute('enctype'));
    }
}
