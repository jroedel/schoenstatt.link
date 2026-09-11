<?php

namespace SchoenstattTest\Integration;

use SionModel\Form\Form;
use Laminas\InputFilter\Factory as InputFilterFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\Form\DatePrecision;
use SionModel\I18n\View\Helper\DatePrecisionFormat;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Pins the date-precision feature: the renderer and the form field that feeds it.
 *
 * Half the dates in this database are not known to the day. The convention was
 * always to store an unknown day as the 1st and an unknown month as January —
 * 41% of assignment start dates are 1 January against the 0.27% chance would
 * produce — and until db6.5 nothing recorded which values those were, so
 * "sometime in 1952" and "1 January 1952" were the same row and both rendered as
 * a precise date the source never claimed.
 *
 * Two properties here are the ones that would break quietly, so they are the ones
 * asserted hardest:
 *
 * **Day precision must not change what the site already renders.** Eight call
 * sites moved from `dateFormat(...)` to `datePrecisionFormat(...)`, and only the
 * year-only and month-only dates are supposed to look different. If a full date
 * shifts format, or shifts *day* because of a timezone, that is a regression
 * across every person and association page and no status-code test would see it.
 *
 * **The precision field must keep exactly one domain check.** In laminas-form a
 * Select's automatic InArray and a form specification's validators are separate,
 * the specification overwrites the element-derived input by name, and the two
 * merge in one construction path but not the other. Getting that wrong either
 * removes the only thing stopping an arbitrary string reaching a NOT NULL column
 * — which is how 93 other choice fields in this application lost theirs — or
 * reports the same failure twice.
 *
 * Needs vendor/ (laminas-form, laminas-i18n), so it lives outside the vendor-free
 * unit suite, like ParseableDateValidatorContractTest. Run in the capsule:
 * php composer.phar integration
 */
class DatePrecisionContractTest extends TestCase
{
    private const LOCALES = ['en_US', 'es_ES', 'de_DE', 'pt_BR'];

    /** @var string */
    private $originalLocale;

    protected function setUp(): void
    {
        $this->originalLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->originalLocale);
    }

    private function helper(): DatePrecisionFormat
    {
        return new DatePrecisionFormat();
    }

    private function date(string $iso): \DateTime
    {
        return new \DateTime($iso, new \DateTimeZone('UTC'));
    }

    /**
     * The regression that matters most: a date known to the day must render
     * byte-for-byte what the plain date helper renders, in every locale the site serves.
     * The call sites that moved passed MEDIUM, except the association page's foundation
     * date which passed LONG — which is why the day format is a parameter rather than a
     * constant.
     *
     * The oracle was `Laminas\I18n\View\Helper\DateFormat` until 2026-09; it is
     * `App\View\Helper\DateFormat` now, which is that class transcribed when laminas-i18n
     * was removed. Comparing two helpers rather than freezing ICU's output keeps this test
     * silent on an ICU upgrade, which changes month abbreviations and would otherwise read
     * as a regression in our code.
     */
    #[DataProvider('dayPrecisionParityCases')]
    public function testDayPrecisionRendersExactlyWhatDateFormatDid(string $locale, string $iso, int $dayFormat): void
    {
        \Locale::setDefault($locale);

        $plain = new \App\View\Helper\DateFormat();
        $plain->setLocale($locale);

        $expected = $plain($this->date($iso), $dayFormat, \IntlDateFormatter::NONE);
        $actual   = ($this->helper())($this->date($iso), DatePrecisionFormat::DAY, $dayFormat);

        self::assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function dayPrecisionParityCases(): array
    {
        $cases = [];
        foreach (self::LOCALES as $locale) {
            foreach (['1952-06-15', '1914-10-18', '1885-11-14', '2020-01-01'] as $iso) {
                foreach (['MEDIUM' => \IntlDateFormatter::MEDIUM, 'LONG' => \IntlDateFormatter::LONG] as $n => $fmt) {
                    $cases["$locale $iso $n"] = [$locale, $iso, $fmt];
                }
            }
        }
        return $cases;
    }

    /**
     * Year precision shows the year and nothing else — that is the whole point of
     * the feature. Asserted for every locale because a stray pattern character
     * would only show up in one of them.
     */
    #[DataProvider('localeProvider')]
    public function testYearPrecisionRendersTheYearAlone(string $locale): void
    {
        \Locale::setDefault($locale);

        self::assertSame('1952', ($this->helper())($this->date('1952-06-15'), DatePrecisionFormat::YEAR));
    }

    /**
     * Month precision names the month in the viewer's language and omits the day.
     * The assertion is deliberately structural rather than a hardcoded string per
     * locale: what matters is that the day is gone, the year is there, and the
     * result is not the bare ISO fallback.
     */
    #[DataProvider('localeProvider')]
    public function testMonthPrecisionKeepsMonthAndYearAndDropsTheDay(string $locale): void
    {
        \Locale::setDefault($locale);

        $rendered = ($this->helper())($this->date('1952-06-15'), DatePrecisionFormat::MONTH);

        self::assertStringContainsString('1952', $rendered);
        self::assertStringNotContainsString('15', $rendered);
        self::assertNotSame('1952-06-15', $rendered);
        //A month name, not a number: 'June', 'junio', 'Juni', 'junho'.
        self::assertMatchesRegularExpression('/\p{L}{3,}/u', $rendered);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localeProvider(): array
    {
        $out = [];
        foreach (self::LOCALES as $l) {
            $out[$l] = [$l];
        }
        return $out;
    }

    /**
     * These are date-only values that SionModel\Filter\ToDateTime built at
     * midnight UTC. Formatting midnight in a timezone behind UTC moves it to the
     * previous day, so a birthday of 15 June would render as 14 June for a viewer
     * in New York. The formatter is pinned to UTC for that reason, and this test
     * is what stops someone "fixing" it to the viewer's timezone.
     */
    public function testTheRenderedDayDoesNotShiftWithTheAmbientTimezone(): void
    {
        $original = date_default_timezone_get();
        try {
            \Locale::setDefault('en_US');
            foreach (['UTC', 'America/New_York', 'Pacific/Kiritimati'] as $tz) {
                date_default_timezone_set($tz);
                self::assertSame(
                    'Jun 15, 1952',
                    ($this->helper())($this->date('1952-06-15'), DatePrecisionFormat::DAY),
                    "shifted under $tz"
                );
            }
        } finally {
            date_default_timezone_set($original);
        }
    }

    /**
     * A stored precision is a varchar(20) and therefore untrusted like any other
     * stored value. It selects a hardcoded pattern through a whitelist; anything
     * unrecognised falls back to 'day' rather than reaching IntlDateFormatter,
     * where it would be able to choose the format.
     *
     * @param mixed $precision
     */
    #[DataProvider('unrecognisedPrecisions')]
    public function testAnUnrecognisedPrecisionFallsBackToDay($precision): void
    {
        \Locale::setDefault('en_US');

        self::assertSame('Jun 15, 1952', ($this->helper())($this->date('1952-06-15'), $precision));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unrecognisedPrecisions(): array
    {
        return [
            'null'        => [null],
            'empty'       => [''],
            'nonsense'    => ['decade'],
            'wrong case'  => ['YEAR'],
            'a pattern'   => ['yyyy-MM-dd'],
            'an array'    => [['year']],
            'an int'      => [1],
        ];
    }

    /**
     * Every caller is a template, most already behind an is_object() guard. A
     * helper that throws while rendering would take a whole person page down to
     * report that the person has no death date — which is the normal case for
     * every person in this data.
     *
     * @param mixed $value
     */
    #[DataProvider('nonDateValues')]
    public function testANonDateRendersAsNothingRatherThanThrowing($value): void
    {
        \Locale::setDefault('en_US');

        self::assertSame('', ($this->helper())($value, DatePrecisionFormat::DAY));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonDateValues(): array
    {
        return [
            'null'   => [null],
            'empty'  => [''],
            'string' => ['1952-06-15'],
            'array'  => [[]],
            'object' => [new \stdClass()],
            'int'    => [0],
        ];
    }

    public function testAnImmutableDateRendersTheSameAsAMutableOne(): void
    {
        \Locale::setDefault('en_US');
        $helper = $this->helper();

        self::assertSame(
            $helper($this->date('1952-06-15'), DatePrecisionFormat::DAY),
            $helper(new \DateTimeImmutable('1952-06-15', new \DateTimeZone('UTC')), DatePrecisionFormat::DAY)
        );
    }

    /**
     * The renderer and the form field must agree on the vocabulary. If the form
     * could write a value the renderer does not recognise, that date would
     * silently start displaying to the wrong precision — the exact failure this
     * feature exists to remove.
     */
    public function testTheFormOffersExactlyTheValuesTheRendererUnderstands(): void
    {
        $offered    = array_keys(DatePrecision::valueOptions());
        $understood = DatePrecisionFormat::PRECISIONS;

        sort($offered);
        sort($understood);

        self::assertSame($understood, $offered);
    }

    public function testTheDefaultPrecisionMatchesTheColumnDefault(): void
    {
        //db6.5 declares every precision column NOT NULL DEFAULT 'day', and
        //events.StartDatePrecision has always defaulted the same way.
        self::assertSame(DatePrecisionFormat::DAY, DatePrecision::DEFAULT_PRECISION);
    }

    /**
     * One InArray, from the specification. Not zero — that would let any string
     * into the column — and not two, which is what happens when the element's
     * automatic one is left on and the two inputs merge.
     *
     * @param mixed $value
     */
    #[DataProvider('precisionFieldCases')]
    public function testThePrecisionFieldAcceptsOnlyTheThreeValues($value, bool $expected): void
    {
        $form = new Form('t');
        $form->add([
            'name' => 'p',
            'type' => 'Select',
            'options' => [
                'label' => 'p',
                'value_options' => DatePrecision::valueOptions(),
                'disable_inarray_validator' => true,
            ],
        ]);

        $filter = (new InputFilterFactory())->createInputFilter(['p' => DatePrecision::filterSpec()]);
        $validators = $filter->get('p')->getValidatorChain()->getValidators();
        self::assertCount(1, $validators, 'exactly one domain check');
        self::assertInstanceOf(\Laminas\Validator\InArray::class, $validators[0]['instance']);

        $filter->setData(['p' => $value]);
        self::assertSame($expected, $filter->isValid());
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function precisionFieldCases(): array
    {
        return [
            'day'          => ['day', true],
            'month'        => ['month', true],
            'year'         => ['year', true],
            'nonsense'     => ['decade', false],
            'wrong case'   => ['Year', false],
            'script tag'   => ['<script>', false],
            'array'        => [[['day']], false],
            'sql'          => ["day' OR 1=1", false],
        ];
    }
}
