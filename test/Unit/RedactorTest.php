<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use SionModel\Error\Redactor;

/**
 * Pins SionModel\Error\Redactor, the privacy filter applied to captured
 * request state before it is written to disk and emailed. See the class
 * docblock: what these methods drop is the difference between a debugging
 * aid and a personal-data spill.
 *
 * The class file is required directly: test/bootstrap.php deliberately avoids
 * vendor/autoload.php so the suite stays valid even when vendor/ is mid-migration.
 */
class RedactorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../module/SionModel/src/Error/Redactor.php';
    }

    public function testIpTruncateZeroesLastIpv4OctetAndAppendsSlash24(): void
    {
        $this->assertSame('203.0.113.0/24', Redactor::ip('203.0.113.55', 'truncate'));
    }

    public function testIpTruncateIsTheDefaultMode(): void
    {
        $this->assertSame('203.0.113.0/24', Redactor::ip('203.0.113.55'));
    }

    public function testIpTruncateKeepsFirstThreeIpv6HextetsAndAppendsSlash48(): void
    {
        $this->assertSame(
            '2001:db8:85a3::/48',
            Redactor::ip('2001:db8:85a3:0:0:8a2e:370:7334', 'truncate')
        );
    }

    public function testIpNoneModeReturnsNullRegardlessOfInput(): void
    {
        $this->assertNull(Redactor::ip('203.0.113.55', 'none'));
    }

    public function testIpNullInputReturnsNull(): void
    {
        $this->assertNull(Redactor::ip(null, 'truncate'));
    }

    public function testIpMalformedAddressReturnsNullRatherThanGuessing(): void
    {
        $this->assertNull(Redactor::ip('not-an-ip-address', 'truncate'));
        $this->assertNull(Redactor::ip('1.2.3', 'truncate'));
    }

    public function testIpFullModeReturnsInputVerbatim(): void
    {
        $this->assertSame('203.0.113.55', Redactor::ip('203.0.113.55', 'full'));
        $this->assertSame('2001:db8:85a3:0:0:8a2e:370:7334', Redactor::ip('2001:db8:85a3:0:0:8a2e:370:7334', 'full'));
    }

    /**
     * The login form posts a password; every key must survive (including
     * nested ones flattened to dotted paths) but no scalar value may.
     */
    public function testParamsKeysModePreservesEveryKeyIncludingNested(): void
    {
        $redacted = Redactor::params([
            'username' => 'frjeff',
            'credentials' => [
                'password' => 'super-secret-value',
            ],
        ], 'keys');

        $this->assertArrayHasKey('username', $redacted);
        $this->assertArrayHasKey('credentials.password', $redacted);
    }

    /**
     * Assert the strong way: the literal secret string must not appear
     * anywhere in a serialisation of the output, because this gets emailed.
     */
    public function testParamsKeysModeNeverLeaksTheOriginalScalarValue(): void
    {
        $redacted = Redactor::params([
            'username' => 'frjeff',
            'credentials' => [
                'password' => 'super-secret-value',
            ],
        ], 'keys');

        $serialised = var_export($redacted, true) . json_encode($redacted) . serialize($redacted);
        $this->assertStringNotContainsString('super-secret-value', $serialised);
        $this->assertStringNotContainsString('frjeff', $serialised);
    }

    public function testParamsFullModeKeepsValuesFlattenedToDottedPaths(): void
    {
        $full = Redactor::params([
            'username' => 'frjeff',
            'credentials' => [
                'password' => 'super-secret-value',
            ],
        ], 'full');

        $this->assertSame('frjeff', $full['username']);
        $this->assertSame('super-secret-value', $full['credentials.password']);
    }

    public function testParamsNoneModeReturnsEmptyArray(): void
    {
        $this->assertSame([], Redactor::params(['username' => 'frjeff'], 'none'));
    }

    public function testFlattenProducesDottedPathsAndMarksEmptyArraysDistinctly(): void
    {
        $flat = Redactor::flatten([
            'a' => 1,
            'b' => [
                'c' => 2,
                'd' => [],
            ],
        ]);

        $this->assertSame([
            'a' => 1,
            'b.c' => 2,
            'b.d' => '<empty array>',
        ], $flat);
    }

    public function testDescribeNull(): void
    {
        $this->assertSame('<null>', Redactor::describe(null));
    }

    public function testDescribeBool(): void
    {
        $this->assertSame('<true>', Redactor::describe(true));
        $this->assertSame('<false>', Redactor::describe(false));
    }

    public function testDescribeEmptyString(): void
    {
        $this->assertSame('<empty>', Redactor::describe(''));
    }

    public function testDescribeObject(): void
    {
        $this->assertSame('<stdClass>', Redactor::describe(new \stdClass()));
    }

    public function testDescribeArray(): void
    {
        $this->assertSame('<array>', Redactor::describe([1, 2, 3]));
    }

    public function testDescribeNormalStringIsLengthSuffixedMarker(): void
    {
        $this->assertSame(Redactor::REDACTED . ':5', Redactor::describe('hello'));
        $this->assertSame(Redactor::REDACTED . ':12', Redactor::describe('a longer one'));
    }
}
