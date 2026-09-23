<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\Data\ArrayMerge;

require_once __DIR__ . '/../../module/SionModel/src/Data/ArrayMerge.php';

/**
 * The merge rule, which is silent when it is wrong.
 *
 * `Laminas\Stdlib\ArrayUtils::merge()` until 2026-09-22, when the package left; the rule
 * lived in `App\Modules\ModuleConfig` between 2026-09-21 and then, and moved into SionModel
 * because `SionModel\Service\ProblemService` needs the same one.
 *
 * **The clause everything turns on is the integer one** — a list under a key both sides
 * declare *appends* rather than replaces. That is what makes `SionModel`'s and `Books`'
 * entries in a shared config key combine instead of one erasing the other, and what makes
 * two problem providers reporting on the same entity both get heard. None of the three
 * built-ins does it: `array_merge` drops the first list, `array_merge_recursive` also turns
 * two *scalars* under one key into a list, and `array_replace_recursive` merges lists
 * element-wise so a shorter one leaves the longer one's tail behind.
 *
 * Runs in the vendor-free unit suite, so it loads the one class it tests by hand.
 */
final class ArrayMergeTest extends TestCase
{
    /** @return iterable<string, array{array<array-key, mixed>, array<array-key, mixed>, array<array-key, mixed>}> */
    public static function mergeCases(): iterable
    {
        yield 'a key only one side has is kept' => [
            ['a' => 1],
            ['b' => 2],
            ['a' => 1, 'b' => 2],
        ];

        yield 'a scalar under a shared string key is overwritten' => [
            ['a' => 1],
            ['a' => 2],
            ['a' => 2],
        ];

        yield 'arrays under a shared string key recurse' => [
            ['factories' => ['x' => 'X', 'y' => 'Y']],
            ['factories' => ['y' => 'Y2', 'z' => 'Z']],
            ['factories' => ['x' => 'X', 'y' => 'Y2', 'z' => 'Z']],
        ];

        //The clause everything turns on.
        yield 'lists append rather than replace' => [
            ['listeners' => ['first']],
            ['listeners' => ['second']],
            ['listeners' => ['first', 'second']],
        ];

        yield 'appending renumbers rather than colliding' => [
            [['a'], ['b']],
            [['c']],
            [['a'], ['b'], ['c']],
        ];

        yield 'an array replaces a scalar' => [
            ['a' => 1],
            ['a' => ['deep' => true]],
            ['a' => ['deep' => true]],
        ];

        yield 'a scalar replaces an array' => [
            ['a' => ['deep' => true]],
            ['a' => 1],
            ['a' => 1],
        ];

        //isset() would answer false here and treat the key as absent. It makes no
        //difference to the result, but ArrayUtils used array_key_exists and so does this.
        yield 'an explicit null overwrites' => [
            ['a' => 'set'],
            ['a' => null],
            ['a' => null],
        ];

        yield 'nesting is unbounded' => [
            ['one' => ['two' => ['three' => ['keep' => 1, 'replace' => 1]]]],
            ['one' => ['two' => ['three' => ['replace' => 2]]]],
            ['one' => ['two' => ['three' => ['keep' => 1, 'replace' => 2]]]],
        ];

        yield 'an empty right-hand side changes nothing' => [
            ['a' => ['b' => 1]],
            [],
            ['a' => ['b' => 1]],
        ];
    }

    /**
     * @param array<array-key, mixed> $a
     * @param array<array-key, mixed> $b
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('mergeCases')]
    public function testTheMergeRuleAppendsListsUnderASharedKey(array $a, array $b, array $expected): void
    {
        self::assertSame($expected, ArrayMerge::merge($a, $b));
    }

    /**
     * The two module-config shapes the rule exists for, stated as one case each so a
     * regression names which one broke rather than just "the merge changed".
     */
    public function testTwoModulesFactoriesCombineAndTheirListenersConcatenate(): void
    {
        $sionModel = [
            'service_manager' => ['factories' => ['SionTable' => 'SionTableFactory']],
            'listeners'       => ['SionModel\Listener'],
        ];
        $books = [
            'service_manager' => ['factories' => ['LibraryTable' => 'LibraryTableFactory']],
            'listeners'       => ['Books\Listener'],
        ];

        self::assertSame([
            'service_manager' => ['factories' => [
                'SionTable'    => 'SionTableFactory',
                'LibraryTable' => 'LibraryTableFactory',
            ]],
            'listeners'       => ['SionModel\Listener', 'Books\Listener'],
        ], ArrayMerge::merge($sionModel, $books));
    }
}
