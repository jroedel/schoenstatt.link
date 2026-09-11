<?php

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\Filter\ToDateTime;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Pins the one property SionModel\Filter\ToDateTime has to have: it never
 * throws.
 *
 * It used to end with an unguarded `new \DateTime($value)`, so 'asdf' threw
 * DateMalformedStringException and a POSTed array ('name[]=x') a TypeError —
 * out of `InputFilter::isValid()`, which means an uncaught exception in a
 * controller action: HTTP 500 and the user's whole submission lost. The fuzz
 * harness found 22 field-level instances of this across six forms.
 *
 * The second property, and the reason ToDateTime and ParseableDate had to land
 * together: an unparseable value comes back **unchanged**, not as null.
 * Validators see the filtered value, so null would be indistinguishable from a
 * blank field and the bad input would vanish silently instead of being
 * reported. SionModel\Validator\ParseableDate is what turns that into a field
 * error; see ParseableDateValidatorContractTest.
 *
 * Needs vendor/ (laminas-filter, for AbstractFilter), so it lives outside the
 * vendor-free unit suite — the same reason SortTextFilterContractTest and
 * PatternValidatorContractTest do. Run in the capsule:
 * php composer.phar integration
 */
class ToDateTimeFilterContractTest extends TestCase
{
    private function filter(): ToDateTime
    {
        return new ToDateTime();
    }

    public function testParseableStringBecomesAUtcDateTime(): void
    {
        $filtered = $this->filter()->filter('2020-03-15');

        self::assertInstanceOf(\DateTime::class, $filtered);
        self::assertSame('2020-03-15 00:00:00', $filtered->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $filtered->getTimezone()->getName());
    }

    /**
     * The filter runs on every isValid(), and a form's own repopulation path can
     * hand it back a value it already converted, so an already-converted value
     * has to survive a second pass. A \DateTime comes back as the same instance.
     *
     * A \DateTimeImmutable is *converted* rather than passed through, and that
     * asymmetry is deliberate. It used to reach `new \DateTime($value)` and raise
     * a TypeError, so something had to change; returning it unchanged would have
     * been the smaller edit and the wrong one. Better than twenty places in the
     * application ask `$value instanceof \DateTime` — LibraryTable's checkedOutOn
     * handling, FormatPerson, ShortDateRange, the API controllers' serialization
     * — and \DateTimeImmutable does not satisfy that test. Letting one through
     * would flip all of them to false and quietly change what gets rendered and
     * written.
     */
    public function testDateTimeValuesSurviveASecondPass(): void
    {
        $mutable   = new \DateTime('2001-09-11', new \DateTimeZone('UTC'));
        $immutable = new \DateTimeImmutable('2001-09-11', new \DateTimeZone('UTC'));

        self::assertSame($mutable, $this->filter()->filter($mutable));

        $converted = $this->filter()->filter($immutable);
        self::assertInstanceOf(\DateTime::class, $converted);
        self::assertNotInstanceOf(\DateTimeImmutable::class, $converted);
        self::assertSame('2001-09-11', $converted->format('Y-m-d'));
    }

    /**
     * Emptiness is null, which is what the database column wants. Note '' has to
     * be caught before any parse is attempted, because `new \DateTime('')` is
     * *now* rather than an error — the same trap makes `false` mean null here.
     *
     * @param mixed $value
     */
    #[DataProvider('emptyValues')]
    public function testEmptyValuesBecomeNull($value): void
    {
        self::assertNull($this->filter()->filter($value));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function emptyValues(): array
    {
        return [
            'null'         => [null],
            'empty string' => [''],
            'false'        => [false],
        ];
    }

    /**
     * Each of these took a request down before the try/catch. The assertion is
     * both halves of the contract at once: no throw, and the original value back
     * so a validator can still see what the user typed.
     */
    #[DataProvider('unparseableValues')]
    public function testUnparseableStringsComeBackUnchanged(string $value): void
    {
        self::assertSame($value, $this->filter()->filter($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unparseableValues(): array
    {
        return [
            'gibberish'          => ['asdf'],
            'sql tautology'      => ["' OR 1=1"],
            'emoji'              => ['🙂'],
            'hex literal'        => ['0x0'],
            'float overflow'     => ['1e400'],
            'bare zero'          => ['0'],
        ];
    }

    /**
     * One value that looks like it should fail and does not: 31-12-2020 is an
     * unambiguous day-first date. The cases that *did* pass here by overflowing —
     * 30 February, and MySQL's zero date — are now refused, and have their own
     * tests below.
     *
     * @param string $value
     */
    #[DataProvider('unambiguousNonIsoValues')]
    public function testANonIsoButUnambiguousDateIsAccepted(string $value, string $expected): void
    {
        $filtered = $this->filter()->filter($value);

        self::assertInstanceOf(\DateTime::class, $filtered);
        self::assertSame($expected, $filtered->format('Y-m-d'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unambiguousNonIsoValues(): array
    {
        return [
            //Not overflow: 31-12-2020 is an unambiguous day-first date and is
            //parsed as one. Kept here because it *looks* like it should fail.
            'day first' => ['31-12-2020', '2020-12-31'],
        ];
    }

    /**
     * A day that does not exist is now refused instead of rolled forward.
     * \DateTime turns 30 February into 1 March and 29 February 2019 into 1 March
     * without complaint, so the wrong date was stored and nothing said so;
     * date_parse() reports it as "The parsed date was invalid", which is what
     * DateTimeParser now checks.
     *
     * This does not disturb the precision convention, whose year-only values are
     * 1 January and month-only values day 1 — both real dates.
     *
     * @param string $value
     */
    #[DataProvider('impossibleDays')]
    public function testAnImpossibleCalendarDayIsRejected(string $value): void
    {
        self::assertSame($value, $this->filter()->filter($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function impossibleDays(): array
    {
        return [
            'thirty february'      => ['2020-02-30'],
            'non-leap 29 february' => ['2019-02-29'],
        ];
    }

    /**
     * A value whose meaning depends on when it was submitted is not a date this
     * application will store, and neither is a bare year — `new \DateTime('1952')`
     * is *today at 19:52*, because four digits alone are read as a time. That one
     * matters most now that year precision is offered on these fields, since
     * entering just a year is exactly what someone would try.
     *
     * @param string $value
     */
    #[DataProvider('nonAbsoluteValues')]
    public function testRelativeAndBareValuesAreRejected(string $value): void
    {
        self::assertSame($value, $this->filter()->filter($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAbsoluteValues(): array
    {
        return [
            'tomorrow'          => ['tomorrow'],
            'relative interval' => ['+500 years'],
            'relative past'     => ['-1 day'],
            'next weekday'      => ['next monday'],
            'bare year'         => ['1952'],
            'bare time'         => ['19:52'],
            'unix timestamp'    => ['@99999999999'],
        ];
    }

    /**
     * MySQL's zero date is the one overflow case that is *not* merely
     * implausible: '0000-00-00' parses to 30 November of year -1, which no DATE
     * column can hold — the range is 1000-01-01 to 9999-12-31 — so it cannot
     * round trip and would either error on write or land as a zero date. That
     * makes it a storability question rather than a plausibility one, and
     * DateTimeParser rejects it on the year bound. Its siblings above are left
     * alone on purpose: overflowing 2020-02-30 to 1 March is \DateTime's
     * documented behaviour and reversing it is a product decision.
     */
    public function testTheMysqlZeroDateIsRejectedRatherThanOverflowed(): void
    {
        $filtered = $this->filter()->filter('0000-00-00');

        self::assertSame('0000-00-00', $filtered);
    }

    /**
     * The docblock has always promised this; it was never implemented, and
     * `name[]=x` in a POST was enough to reach it. An array is what the input
     * filter hands a scalar field when a client sends one.
     */
    #[DataProvider('nonScalarValues')]
    public function testNonScalarValuesRemainUnfiltered(mixed $value): void
    {
        self::assertSame($value, $this->filter()->filter($value));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonScalarValues(): array
    {
        return [
            'empty array'  => [[]],
            'flat array'   => [['2020-03-15']],
            'nested array' => [[['x']]],
            'object'       => [new \stdClass()],
        ];
    }

    /**
     * A NUL byte must never come back as a date. \DateTime's parser stops at the
     * NUL and parses the empty remainder, so `new \DateTime("a\0b")` is *now*:
     * before this was fixed, a NUL byte in a birth-date field silently stored
     * today, and the stored value then looked deliberate. That is the worst of
     * the three families of hostile date input, because nothing about the result
     * says it came from garbage.
     *
     * The two cases differ, and both are the intended outcome:
     *
     *  - A lone NUL is *emptiness*. trim()'s default character list includes
     *    "\0", so it trims away to '' and the filter returns null, exactly as it
     *    would for '' or whitespace. Nothing was entered.
     *  - A NUL among other characters is *bad input*, and comes back as a string
     *    so the validator chain can report it. It comes back with the NUL
     *    removed, which is not cosmetic: SionModel\Validator\Date, which the Date
     *    form element contributes ahead of anything a form specification adds,
     *    calls DateTime::createFromFormat() and PHP raises
     *    `ValueError: must not contain any null bytes`. Passing the raw value on
     *    merely relocated the 500 one layer down, which the fuzz harness caught.
     */
    public function testALoneNulByteIsTreatedAsEmptiness(): void
    {
        self::assertNull($this->filter()->filter("\0"));
    }

    public function testANulAmongOtherCharactersComesBackReportableAndDefused(): void
    {
        self::assertSame('ab', $this->filter()->filter("a\0b"));
    }
}
