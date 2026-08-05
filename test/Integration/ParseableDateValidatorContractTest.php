<?php

namespace SchoenstattTest\Integration;

use Laminas\Filter\FilterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorPluginManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\Filter\ToDateTime;
use SionModel\Validator\ParseableDate;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Pins SionModel\Validator\ParseableDate, the half of the date fix that makes a
 * bad date visible.
 *
 * ToDateTime may not throw, so it hands unparseable input back rather than
 * nulling it (with NUL bytes stripped, which its own test explains). On its own
 * that trades a 500 for silent data loss — the row is written without the date
 * and nobody is told. This validator is what turns it into a field error,
 * and the two are useless apart, so the last test here drives the real pair in
 * the order an InputFilter runs them.
 *
 * What it must *not* do is judge plausibility: 'tomorrow', '+500 years' and the
 * year 9999 all parse and are all accepted. Bounding a date is a per-field
 * decision about what this database records.
 *
 * Needs vendor/ (laminas-validator, for AbstractValidator), so it lives outside
 * the vendor-free unit suite, like PatternValidatorContractTest. Run in the
 * capsule: php composer.phar integration
 */
class ParseableDateValidatorContractTest extends TestCase
{
    private function validator(): ParseableDate
    {
        return new ParseableDate();
    }

    /**
     * The success path in production: the filter already converted the value, so
     * what the validator sees is a date object rather than a string.
     */
    public function testDateTimeInstancesAreValid(): void
    {
        self::assertTrue($this->validator()->isValid(new \DateTime('2020-03-15')));
        self::assertTrue($this->validator()->isValid(new \DateTimeImmutable('2020-03-15')));
    }

    /**
     * Emptiness belongs to `required`/NotEmpty. Saying otherwise here would make
     * every one of the twelve optional date fields mandatory — birthDate,
     * deathDate and the rest are routinely unknown in this data.
     *
     * @param mixed $value
     */
    #[DataProvider('emptyValues')]
    public function testEmptyValuesAreValid($value): void
    {
        $validator = $this->validator();

        self::assertTrue($validator->isValid($value));
        self::assertSame([], $validator->getMessages());
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
     * Used standalone (no ToDateTime in front) it still answers correctly, which
     * is why it re-attempts the parse rather than assuming "still a string means
     * the filter failed".
     */
    #[DataProvider('parseableValues')]
    public function testParseableStringsAreValid(string $value): void
    {
        self::assertTrue($this->validator()->isValid($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function parseableValues(): array
    {
        return [
            'iso date'          => ['2020-03-15'],
            'day first'         => ['31-12-2020'],
            'relative word'     => ['tomorrow'],
            'relative interval' => ['+500 years'],
            'far future'        => ['9999-12-31'],
            // Overflow rather than rejection: becomes 1 March. Contrast
            // 0000-00-00, which overflows to year -1 and is rejected below
            // because no DATE column can hold it.
            'impossible day'    => ['2020-02-30'],
            'timestamp'         => ['@99999999999'],
        ];
    }

    #[DataProvider('unparseableValues')]
    public function testUnparseableStringsAreInvalid(string $value): void
    {
        $validator = $this->validator();

        self::assertFalse($validator->isValid($value));
        self::assertArrayHasKey(ParseableDate::NOT_PARSEABLE, $validator->getMessages());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unparseableValues(): array
    {
        return [
            'gibberish'      => ['asdf'],
            'sql tautology'  => ["' OR 1=1"],
            'emoji'          => ['🙂'],
            'bare zero'      => ['0'],
        ];
    }

    /**
     * An array reaches a scalar field whenever a client sends `name[]=x`; it used
     * to be a TypeError inside the filter. It is reported under its own key
     * because "wrong type" and "not a date" are different things to a
     * translator.
     *
     * @param mixed $value
     */
    #[DataProvider('nonScalarValues')]
    public function testNonScalarValuesAreInvalid($value): void
    {
        $validator = $this->validator();

        self::assertFalse($validator->isValid($value));
        self::assertArrayHasKey(ParseableDate::INVALID, $validator->getMessages());
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
     * A NUL-bearing value is not a date. This validator and ToDateTime now share
     * one decision (SionModel\Filter\DateTimeParser) precisely so that they
     * cannot disagree about it: when each parsed the value itself they both
     * accepted this, because \DateTime's parser stops at the NUL and reads the
     * empty remainder as *now*.
     *
     * A lone NUL is a separate case and is *valid*, because trim() removes it and
     * the value then means "nothing entered" — emptiness is required/NotEmpty's
     * business, not this validator's. ToDateTimeFilterContractTest covers the
     * filter side of both.
     */
    public function testANulAmongOtherCharactersIsNotADate(): void
    {
        self::assertFalse($this->validator()->isValid("a\0b"));
    }

    public function testALoneNulByteIsEmptinessRatherThanAnError(): void
    {
        self::assertTrue($this->validator()->isValid("\0"));
    }

    /**
     * MySQL's zero date parses — to 30 November of year -1 — but cannot be
     * stored in a DATE column, whose range starts at 1000-01-01. Rejected on the
     * year bound rather than by the parse, which is why it needs its own test.
     */
    public function testTheMysqlZeroDateIsNotAStorableDate(): void
    {
        self::assertFalse($this->validator()->isValid('0000-00-00'));
    }

    /**
     * Neither message echoes the rejected value. The value is hostile by
     * definition and the form already shows the user what they typed, so
     * interpolating it buys nothing and risks 100kB of it in an error message.
     */
    public function testMessagesDoNotInterpolateTheRejectedValue(): void
    {
        $validator = $this->validator();
        $validator->isValid(str_repeat('z', 400));

        $message = $validator->getMessages()[ParseableDate::NOT_PARSEABLE];

        self::assertStringNotContainsString('zzz', $message);
        self::assertStringNotContainsString('%value%', $message);
    }

    /**
     * The input filter specifications name this validator by string, so a missing
     * or misspelled entry in SionModel's `validators` config is a
     * ServiceNotFoundException at request time on twelve date fields. Both the
     * FQCN (how the forms spell it) and the short alias (how the config
     * registers it) have to resolve.
     */
    #[DataProvider('registeredNames')]
    public function testResolvesThroughTheValidatorPluginManager(string $name): void
    {
        $config  = include __DIR__ . '/../../module/SionModel/config/module.config.php';
        $manager = new ValidatorPluginManager(new ServiceManager(), $config['validators']);

        self::assertInstanceOf(ParseableDate::class, $manager->get($name));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function registeredNames(): array
    {
        return [
            'fully qualified' => [ParseableDate::class],
            'short alias'     => ['ParseableDate'],
        ];
    }

    /**
     * The pair, in the order Laminas\InputFilter\Input runs them: filters first,
     * then validators against the filtered value. This is the assertion that
     * would fail if either half were reverted — a filter returning null would
     * make the bad date validate, and a filter that throws would never reach the
     * validator at all.
     *
     * @param mixed $value
     */
    #[DataProvider('filterThenValidateCases')]
    public function testFilteredValueIsAcceptedOrRejectedAsExpected($value, bool $expected): void
    {
        $filter    = (new FilterPluginManager(new ServiceManager()))->get(ToDateTime::class);
        $validator = $this->validator();

        self::assertSame($expected, $validator->isValid($filter->filter($value)));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function filterThenValidateCases(): array
    {
        return [
            'a real date is kept'          => ['2020-03-15', true],
            'blank stays optional'         => ['', true],
            'null stays optional'          => [null, true],
            'gibberish is reported'        => ['asdf', false],
            'a hex literal is reported'    => ['0x0', false],
            'an array is reported'         => [['x'], false],
        ];
    }
}
