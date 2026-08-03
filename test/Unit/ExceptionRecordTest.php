<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SionModel\Error\ExceptionRecord;
use SionModel\Error\Fingerprinter;
use SionModel\Error\Redactor;
use Throwable;

/**
 * Pins SionModel\Error\ExceptionRecord, the flattened, dependency-free
 * snapshot of one occurrence that feeds the store's write-up files, its JSON
 * metadata, and the notification email body (deliberately one format for all
 * three, per the class docblock).
 *
 * The class files are required directly: test/bootstrap.php deliberately
 * avoids vendor/autoload.php so the suite stays valid even when vendor/ is
 * mid-migration.
 */
class ExceptionRecordTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../module/SionModel/src/Error/Fingerprinter.php';
        require_once __DIR__ . '/../../module/SionModel/src/Error/ExceptionRecord.php';
        require_once __DIR__ . '/../../module/SionModel/src/Error/Redactor.php';
    }

    /** Throws for real, inside a real method call, so file/line/trace are genuine. */
    private function throwInner(string $message): void
    {
        throw new RuntimeException($message);
    }

    private function throwWrapped(string $innerMessage, string $outerMessage): Throwable
    {
        try {
            $this->throwInner($innerMessage);
        } catch (Throwable $inner) {
            return new RuntimeException($outerMessage, 0, $inner);
        }
    }

    public function testToMetaCarriesAllExpectedFields(): void
    {
        $fingerprinter = new Fingerprinter(__DIR__);
        $e = $this->throwWrapped('inner boom', 'outer boom');

        $record = ExceptionRecord::fromThrowable($e, $fingerprinter, [
            'route'       => 'library.show',
            'controller'  => 'Books\\Controller\\LibraryController',
            'action'      => 'show',
            'revision'    => 'abc123',
            'occurred_at' => '2026-01-01T00:00:00+00:00',
        ]);

        $meta = $record->toMeta();

        $this->assertSame($record->getFingerprint(), $meta['fingerprint']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $meta['fingerprint']);
        $this->assertSame(RuntimeException::class, $meta['class']);
        $this->assertSame([RuntimeException::class, RuntimeException::class], $meta['chain']);
        $this->assertSame('outer boom', $meta['message']);
        $this->assertSame('library.show', $meta['route']);
        $this->assertSame('Books\\Controller\\LibraryController', $meta['controller']);
        $this->assertSame('show', $meta['action']);
        $this->assertSame(self::class . '->throwInner', $meta['origin']);
        $this->assertSame('abc123', $meta['revision']);
        $this->assertSame(PHP_VERSION, $meta['php']);
        $this->assertArrayHasKey('thrown_at', $meta);
    }

    /**
     * thrown_at must refer to the ROOT CAUSE's file:line, not the outermost
     * wrapper's — the wrapper was constructed one line below the catch, not
     * where the failure actually happened.
     */
    public function testThrownAtRefersToRootCauseFileAndLineNotOutermostWrapper(): void
    {
        $fingerprinter = new Fingerprinter(__DIR__);

        try {
            $this->throwInner('inner boom');
            $this->fail('expected throwInner to throw');
        } catch (Throwable $inner) {
            $innerLine = $inner->getLine();
        }

        $outer = new RuntimeException('outer boom', 0, $inner);
        $outerLine = $outer->getLine();
        $this->assertNotSame($innerLine, $outerLine, 'the fixture must produce two distinct line numbers');

        $record = ExceptionRecord::fromThrowable($outer, $fingerprinter, ['route' => 'r']);
        $meta = $record->toMeta();

        $this->assertSame(basename(__FILE__) . ':' . $innerLine, $meta['thrown_at']);
    }

    /**
     * Messages and traces get the app root stripped but must keep their
     * backslashes — those are namespace separators, not path separators.
     */
    public function testMessagesAndTracesStripAppRootButKeepBackslashes(): void
    {
        $fingerprinter = new Fingerprinter(__DIR__);
        $message = __DIR__ . '/secret/path.php failed inside SionModel\\Error\\ErrorListener';
        $e = new RuntimeException($message);

        $record = ExceptionRecord::fromThrowable($e, $fingerprinter, ['route' => 'r']);
        $chain = $record->getChain();

        $this->assertStringNotContainsString(__DIR__, $chain[0]['message']);
        $this->assertStringContainsString('SionModel\\Error\\ErrorListener', $chain[0]['message']);

        // The real trace of this exception includes this very test file's
        // absolute path (under the app root) and this test class, which uses
        // backslashes as its namespace separator.
        $this->assertStringNotContainsString(__DIR__, $chain[0]['trace']);
        $this->assertStringContainsString(self::class, $chain[0]['trace']);
    }

    public function testGetClassChainListsEveryClassOutermostFirst(): void
    {
        $fingerprinter = new Fingerprinter(__DIR__);
        $e = $this->throwWrapped('inner', 'outer');

        $record = ExceptionRecord::fromThrowable($e, $fingerprinter, ['route' => 'r']);

        $this->assertSame([RuntimeException::class, RuntimeException::class], $record->getClassChain());
    }

    public function testToWriteUpIncludesFingerprintRouteChainAndRedactedContextButNotTheAppRoot(): void
    {
        $fingerprinter = new Fingerprinter(__DIR__);
        $e = $this->throwWrapped('inner boom ' . __DIR__ . '/leaked-path.php', 'outer boom');

        $context = Redactor::params(['password' => 'super-secret-value', 'q' => 'abcd'], 'keys');

        $record = ExceptionRecord::fromThrowable($e, $fingerprinter, [
            'route'   => 'library.show',
            'context' => $context,
        ]);

        $writeUp = $record->toWriteUp();

        $this->assertStringContainsString($record->getFingerprint(), $writeUp);
        $this->assertStringContainsString('library.show', $writeUp);
        foreach ($record->getClassChain() as $class) {
            $this->assertStringContainsString($class, $writeUp);
        }
        $this->assertStringContainsString('Password', $writeUp);
        $this->assertStringNotContainsString('super-secret-value', $writeUp);
        $this->assertStringNotContainsString(__DIR__, $writeUp);
    }

    public function testMissingOccurredAtDefaultsRatherThanErroring(): void
    {
        $fingerprinter = new Fingerprinter(__DIR__);
        $e = new RuntimeException('boom');

        $record = ExceptionRecord::fromThrowable($e, $fingerprinter, ['route' => 'r']);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $record->getOccurredAt()
        );
    }

    public function testExplicitOccurredAtIsUsedVerbatimForDeterministicAssertions(): void
    {
        $fingerprinter = new Fingerprinter(__DIR__);
        $e = new RuntimeException('boom');

        $record = ExceptionRecord::fromThrowable($e, $fingerprinter, [
            'route'       => 'r',
            'occurred_at' => '2026-01-01T00:00:00+00:00',
        ]);

        $this->assertSame('2026-01-01T00:00:00+00:00', $record->getOccurredAt());
        $this->assertStringContainsString('Occurred:    2026-01-01T00:00:00+00:00', $record->toWriteUp());
    }

    public function testExplicitOriginAttributeOverridesDerivedOrigin(): void
    {
        $fingerprinter = new Fingerprinter(__DIR__);
        $e = new RuntimeException('boom');

        $record = ExceptionRecord::fromThrowable($e, $fingerprinter, [
            'route'  => 'r',
            'origin' => 'Custom\\Path::customOrigin',
        ]);

        $this->assertSame('Custom\\Path::customOrigin', $record->toMeta()['origin']);
    }
}
