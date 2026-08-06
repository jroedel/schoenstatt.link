<?php

namespace SchoenstattTest\Integration;

use Laminas\Validator\GpsPoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\Db\GeoPoint;
use SionModel\Filter\ToGeoPoint;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Pins SionModel\Filter\ToGeoPoint, which records where a shrine is.
 *
 * The filter used to return null for anything it could not parse, and that made a
 * wrong coordinate invisible rather than wrong. The chain, on the association
 * form's geoPoint field — the only user of this filter:
 *
 *   'asdf' -> null -> the input is `required => false`, so laminas treats the
 *   empty filtered value as "not submitted" and drops the key from getData()
 *   entirely -> SionTable::updateHelper() writes only keys that are present, so
 *   the *previous* coordinate stays in the database -> and no validator ever sees
 *   a value, so there is no message.
 *
 * A moderator types a new coordinate, the form reports success, and the map still
 * shows the old location. That is the property this test exists to prevent
 * returning: silent, and indistinguishable from success.
 *
 * Note what was *not* wrong, because docs/BACKLOG.md claimed it was: valid
 * coordinates were never rejected. GeoPoint::__toString() returns
 * "latitude,longitude", so GpsPoint accepts the object the filter produces just as
 * it accepts the string it came from. Only the failure path was broken.
 *
 * Needs vendor/ (laminas-filter, laminas-validator), so it lives outside the
 * vendor-free unit suite, like ToDateTimeFilterContractTest. Run in the capsule:
 * php composer.phar integration
 */
class ToGeoPointFilterContractTest extends TestCase
{
    private function filter(): ToGeoPoint
    {
        return new ToGeoPoint();
    }

    /**
     * @param string $value
     * @param string $expected the GeoPoint's own "latitude,longitude" rendering
     */
    #[DataProvider('validCoordinates')]
    public function testAValidCoordinateBecomesAGeoPoint(string $value, string $expected): void
    {
        $filtered = $this->filter()->filter($value);

        self::assertInstanceOf(GeoPoint::class, $filtered);
        self::assertSame($expected, (string) $filtered);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validCoordinates(): array
    {
        return [
            'northern hemisphere' => ['50.1234, 8.5678', '50.1234,8.5678'],
            'southern and western' => ['-33.45, -70.66', '-33.45,-70.66'],
            'no space'            => ['50.1234,8.5678', '50.1234,8.5678'],
            'surrounding space'   => ['  50.1234, 8.5678  ', '50.1234,8.5678'],
        ];
    }

    /**
     * The whole point: a value the user typed that cannot be a coordinate comes
     * back **unchanged**, so the GpsPoint validator on the same input can see it
     * and report it. Returning null here is what made the failure silent.
     *
     * @param string $value
     */
    #[DataProvider('unconvertibleValues')]
    public function testAnUnconvertibleValueIsHandedBackForTheValidatorToReject(string $value): void
    {
        self::assertSame($value, $this->filter()->filter($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unconvertibleValues(): array
    {
        return [
            'gibberish'        => ['asdf'],
            'out of range'     => ['999, 999'],
            'sql'              => ['DROP TABLE'],
            'one number only'  => ['50.1234'],
            'nul byte'         => ["a\0b"],
        ];
    }

    /**
     * Blank means "no location recorded", which is a legitimate state for most
     * associations — so it becomes null and is *not* reported. Whitespace-only
     * counts as blank; before trimming it reached the validator and would now
     * produce a spurious error on a field the user simply left alone.
     *
     * @param mixed $value
     */
    #[DataProvider('emptyValues')]
    public function testEmptinessBecomesNull($value): void
    {
        self::assertNull($this->filter()->filter($value));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function emptyValues(): array
    {
        return [
            'null'             => [null],
            'empty string'     => [''],
            'whitespace only'  => ['   '],
        ];
    }

    /**
     * A non-string is nulled rather than handed on, and the asymmetry with a bad
     * string is deliberate: Laminas\Validator\GpsPoint raises a TypeError on an
     * array, which would escape InputFilter::isValid() as a 500 — the failure the
     * rest of this filter exists to avoid. Unlike 'asdf', an array is not
     * something a person typed; only `geoPoint[]=x` in a crafted request produces
     * one, so there is no user to show a message to.
     *
     * @param mixed $value
     */
    #[DataProvider('nonStringValues')]
    public function testANonStringIsNulledRatherThanRiskingATypeError($value): void
    {
        self::assertNull($this->filter()->filter($value));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonStringValues(): array
    {
        return [
            'empty array'  => [[]],
            'flat array'   => [['50.1, 8.5']],
            'nested array' => [[['x']]],
            'object'       => [new \stdClass()],
            'int'          => [5],
        ];
    }

    /**
     * The filter and the validator have to agree, and this drives the real pair in
     * the order an Input runs them: filter first, then validate the filtered
     * value. Without this the two halves could drift and the failure would go
     * quiet again.
     *
     * @param mixed $value
     */
    #[DataProvider('pairCases')]
    public function testTheFilterAndValidatorAgree($value, bool $expectedValid): void
    {
        $filtered = $this->filter()->filter($value);

        self::assertSame($expectedValid, (new GpsPoint())->isValid($filtered));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function pairCases(): array
    {
        return [
            'valid coordinate passes'   => ['50.1234, 8.5678', true],
            'gibberish is rejected'     => ['asdf', false],
            'out of range is rejected'  => ['999, 999', false],
        ];
    }

    /**
     * A filter runs inside InputFilter::isValid(), so a throw here is an uncaught
     * exception in a controller action: a 500 with the whole submission lost. The
     * fuzz harness holds the whole application to this; asserted here too because
     * this filter reaches a validator that does throw on the wrong type.
     *
     * @param mixed $value
     */
    #[DataProvider('hostileValues')]
    public function testTheFilterNeverThrows($value): void
    {
        $this->filter()->filter($value);

        self::assertTrue(true, 'reaching this line is the assertion');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function hostileValues(): array
    {
        return [
            'nul byte'        => ["\0"],
            'invalid utf8'    => ["a\xC3"],
            'very long'       => [str_repeat('1.0, 2.0 ', 5000)],
            'array'           => [[['deep']]],
            'float'           => [1.5],
            'bool'            => [true],
            'emoji'           => ['🙂, 🙂'],
        ];
    }
}
