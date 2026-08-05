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
     * hand it back a value it already converted. Widened from \DateTime to
     * \DateTimeInterface: a DateTimeImmutable used to reach
     * `new \DateTime($value)` and raise a TypeError.
     */
    public function testDateTimeValuesPassStraightThrough(): void
    {
        $mutable   = new \DateTime('2001-09-11', new \DateTimeZone('UTC'));
        $immutable = new \DateTimeImmutable('2001-09-11', new \DateTimeZone('UTC'));

        self::assertSame($mutable, $this->filter()->filter($mutable));
        self::assertSame($immutable, $this->filter()->filter($immutable));
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
     * Characterization of the values most likely to be *assumed* unparseable.
     * \DateTime parses all three, two of them by overflowing: '2020-02-30'
     * becomes 1 March and MySQL's zero date '0000-00-00' becomes 30 November of
     * year -1. So these never threw and they are not what the validator catches;
     * they are stored, wrong. Rejecting them means deciding what a plausible date
     * is for this data, which is a product decision made elsewhere. Pinned so
     * that decision starts from the real behaviour.
     *
     * @param string $value
     */
    #[DataProvider('overflowingValues')]
    public function testValuesThatLookInvalidAreParsedByOverflow(string $value, string $expected): void
    {
        $filtered = $this->filter()->filter($value);

        self::assertInstanceOf(\DateTime::class, $filtered);
        self::assertSame($expected, $filtered->format('Y-m-d'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function overflowingValues(): array
    {
        return [
            'day first'      => ['31-12-2020', '2020-12-31'],
            'impossible day' => ['2020-02-30', '2020-03-01'],
            'mysql zero date' => ['0000-00-00', '-0001-11-30'],
        ];
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
     * Characterization, not endorsement. \DateTime's parser stops at the NUL byte
     * and returns *now*, so a NUL-bearing value is accepted and silently stored
     * as today — the same class of problem as 'tomorrow' and '+500 years', which
     * parse too. Fixing that means bounding what a plausible date is per field,
     * which is a product decision and deliberately not made here. Pinned so
     * whoever makes it can see this behaviour change.
     */
    #[DataProvider('nulBearingValues')]
    public function testNulByteValuesParseRatherThanFailing(string $value): void
    {
        self::assertInstanceOf(\DateTime::class, $this->filter()->filter($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nulBearingValues(): array
    {
        return [
            'lone NUL'      => ["\0"],
            'NUL in middle' => ["a\0b"],
        ];
    }
}
