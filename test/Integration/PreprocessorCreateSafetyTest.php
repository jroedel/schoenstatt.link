<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Service\EntitiesService;
use Throwable;

use function array_pop;
use function count;
use function file_get_contents;
use function implode;
use function in_array;
use function is_array;
use function method_exists;
use function sprintf;
use function strtolower;
use function token_get_all;
use function trim;

use const T_COALESCE;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_COALESCE_EQUAL;
use const T_EMPTY;
use const T_ISSET;
use const T_STRING;
use const T_VARIABLE;
use const T_WHITESPACE;
use const T_COMMENT;
use const T_DOC_COMMENT;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * A `database_bound_data_preprocessor` may not read `$entityData` unguarded.
 *
 * ## The bug this generalises
 *
 * `/texts/create` wrote the text and then showed a 154-byte blank page. `preprocessText()`
 * read `$entityData['kind']` — the kind of the row being written — and on a create there is
 * no such row: `SionTable::createEntity()` passes `[]` where `updateEntity()` passes the
 * current record. PHP emitted `Undefined array key "kind"`, and because a create happens
 * before its redirect is sent, the warning reached the page ahead of the `Location` header.
 *
 * The visible consequence was worse than a 500 would have been. A 500 says something went
 * wrong; a blank page says nothing, so the moderator had no reason to think the text had been
 * saved and pressing submit again made a second one.
 *
 * ## Why this is a class of bug and not one mistake
 *
 * The two arguments look symmetric and are not. `$data` is what was submitted and is always
 * populated; `$entityData` is what is already stored and is empty on exactly one of the two
 * paths the same method serves. Nothing in the signature says so — it is `array $entityData`
 * in both cases — and the create path is the rarer one to exercise by hand, so a preprocessor
 * can be wrong for years while every edit works.
 *
 * Nine preprocessors are registered. Two others read `$entityData` and both happen to use
 * `isset()`, so this test passes today with nothing to grandfather; that is the reason it can
 * assert a flat zero.
 *
 * ## Why static and not behavioural
 *
 * Calling each preprocessor with `$entityData = []` would be the more direct proof, and it was
 * rejected: several of them reach the database — `associationPreprocessor()` nulls out slugs —
 * so a behavioural version would have to guarantee it never writes, which is exactly the
 * property the fuzz harness spends a hundred lines establishing. Reading the tokens costs
 * nothing and cannot have side effects.
 *
 * ## What "guarded" means here, and what it does not prove
 *
 * A key counts as guarded when the method mentions it in an `isset()`, an `empty()` or an
 * `array_key_exists()` anywhere, or reads it with `??`. Per key, per method — not per
 * occurrence — and that is a deliberate weakening from the first version of this file, which
 * asked whether each read sat lexically inside a guard and reported both of the application's
 * *correct* preprocessors as findings. Real guards do not look like that. They look like
 *
 *     if (isset($data['x']) && isset($entityData['y']) && $data['x'] == $entityData['y'])
 *
 * where the read is a sibling clause relying on `&&` short-circuiting, and like
 *
 *     if (isset($entityData['y'])) { $this->doSomething($entityData['y']); }
 *
 * where it is in the block the guard opens. Recognising either properly is flow analysis, and
 * a test that reports two false findings out of three is a test that gets deleted.
 *
 * So this does not prove the guard dominates the read — a method could guard a key on one
 * branch and read it on another and pass. It proves the author knew the key can be absent,
 * which is precisely what was missing from `preprocessText()`: `kind` appeared once in the
 * whole method, bare. A key read with no acknowledgement anywhere that it might not be there
 * is the shape of this bug, and it is worth catching even though a determined author can
 * still write past it.
 *
 * Tokens rather than a regular expression, because a regex would have to guess at nesting and
 * string contents, and the failure mode of guessing is a test that quietly stops checking.
 *
 * Postprocessors are deliberately out of scope: `createEntity()` re-reads the row and hands
 * the postprocessor real data, so there is no empty-array path to protect against.
 */
final class PreprocessorCreateSafetyTest extends TestCase
{
    /** Reading `$entityData['x']` inside one of these is a guarded read. */
    private const GUARDS = ['isset', 'empty', 'array_key_exists'];

    /**
     * A floor for the same reason `EmptyStringToTypedColumnTest` has one: the silent failure
     * is a rename that makes discovery find nothing. Nine registered on 2026-08-15.
     */
    private const PREPROCESSOR_FLOOR = 7;

    public function testNoPreprocessorReadsEntityDataUnguarded(): void
    {
        try {
            $entities = FormRepository::instance()
                ->container()
                ->get(EntitiesService::class)
                ->getEntities();
        } catch (Throwable $e) {
            self::markTestSkipped('cannot build the laminas container: ' . $e->getMessage());
        }

        $inspected = [];
        $findings  = [];

        foreach ($entities as $name => $spec) {
            $method = $spec->databaseBoundDataPreprocessor ?? null;
            $class  = $spec->sionModelClass ?? null;
            if (null === $method || null === $class || ! method_exists($class, $method)) {
                continue;
            }

            $key = $class . '::' . $method;
            if (isset($inspected[$key])) {
                continue; //two entities can share a preprocessor; read it once
            }
            $inspected[$key] = true;

            foreach ($this->unguardedReads(new ReflectionMethod($class, $method)) as [$readKey, $line]) {
                $findings[] = sprintf(
                    '%s (entity `%s`) reads $entityData[\'%s\'] at line %d, and never asks whether it is there',
                    $key,
                    $name,
                    $readKey,
                    $line
                );
            }
        }

        self::assertGreaterThanOrEqual(
            self::PREPROCESSOR_FLOOR,
            count($inspected),
            'far fewer preprocessors were found than are registered — discovery is broken'
        );

        self::assertSame(
            [],
            $findings,
            "a preprocessor reads \$entityData without a guard, so creating one of these entities"
                . " emits a warning where the redirect should be:\n  " . implode("\n  ", $findings)
                . "\n\nOn a create SionTable::createEntity() passes an empty array. Guard the read"
                . ' with isset()/array_key_exists(), or `??`, and decide what the absent value means'
                . ' for a record that does not exist yet.'
        );
    }

    /**
     * Keys this method subscripts off `$entityData` and never acknowledges may be absent.
     *
     * One walk collects two sets — the keys the method guards and the keys it reads bare —
     * and the findings are the difference. Order within the method is therefore irrelevant,
     * which is the point: a guard three lines below its read still counts.
     * Non-literal subscripts (`$entityData[$field]`) are skipped — there is no key to compare
     * and none of the nine preprocessors uses one.
     *
     * @return list<array{string, int}> key and line
     */
    private function unguardedReads(ReflectionMethod $method): array
    {
        $file = $method->getFileName();
        if (false === $file) {
            return [];
        }

        $source = file_get_contents($file);
        if (false === $source) {
            return [];
        }

        $tokens = token_get_all($source);
        $from   = $method->getStartLine();
        $to     = $method->getEndLine();

        //Significant tokens only, with their original line numbers, so "the next token" means
        //the next one that matters rather than the next space.
        $significant = [];
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $significant[] = [$token[0], $token[1], $token[2]];
                continue;
            }
            //A single-character token carries no line of its own; inherit the last one seen.
            $significant[] = [null, $token, $significant ? $significant[count($significant) - 1][2] : 0];
        }

        $guarded = [];
        $reads   = [];

        /** @var list<bool> $parenIsGuard whether each open paren belongs to a guard call */
        $parenIsGuard = [];
        $guardDepth   = 0;

        foreach ($significant as $i => [$id, $text, $line]) {
            if ('(' === $text) {
                $previous = $significant[$i - 1] ?? null;
                $isGuard  = null !== $previous
                    && in_array($previous[0], [T_STRING, T_ISSET, T_EMPTY], true)
                    && in_array(strtolower((string) $previous[1]), self::GUARDS, true);
                $parenIsGuard[] = $isGuard;
                if ($isGuard) {
                    $guardDepth++;
                    //`array_key_exists('key', $entityData)` names the key before the array, so
                    //the subscript rule below would never see it. Take the first argument.
                    $argument = $significant[$i + 1] ?? null;
                    if (
                        'array_key_exists' === strtolower((string) $previous[1])
                        && null !== $argument
                        && T_CONSTANT_ENCAPSED_STRING === $argument[0]
                    ) {
                        $guarded[trim((string) $argument[1], "'\"")] = true;
                    }
                }
                continue;
            }

            if (')' === $text) {
                if (array_pop($parenIsGuard)) {
                    $guardDepth--;
                }
                continue;
            }

            if (T_VARIABLE !== $id || '$entityData' !== $text || $line < $from || $line > $to) {
                continue;
            }
            if (($significant[$i + 1][1] ?? null) !== '[') {
                continue; //passed whole, not subscripted
            }

            $subscript = $significant[$i + 2] ?? null;
            if (null === $subscript || T_CONSTANT_ENCAPSED_STRING !== $subscript[0]) {
                continue; //a variable key: nothing to compare
            }
            $key = trim((string) $subscript[1], "'\"");

            if ($guardDepth > 0) {
                $guarded[$key] = true;
                continue;
            }

            //`$entityData['x'] ?? …` guards itself.
            if (in_array(($significant[$i + 4][0] ?? null), [T_COALESCE, T_COALESCE_EQUAL], true)) {
                $guarded[$key] = true;
                continue;
            }

            $reads[] = [$key, $line];
        }

        $findings = [];
        foreach ($reads as [$key, $line]) {
            if (! isset($guarded[$key])) {
                $findings[] = [$key, $line];
            }
        }

        return $findings;
    }
}
