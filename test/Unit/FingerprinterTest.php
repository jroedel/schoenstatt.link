<?php

namespace SchoenstattTest\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SionModel\Error\Fingerprinter;
use Throwable;

/**
 * Pins SionModel\Error\Fingerprinter, in particular the two load-bearing
 * guarantees called out in its class docblock:
 *
 * 1. the fingerprint is derived from the matched route *name*, never the
 *    request URI, so a variable URL cannot mint a fresh "first occurrence"
 *    (and a fresh notification email) on every request; and
 * 2. the origin is the root cause's enclosing function, not a line number,
 *    so unrelated edits elsewhere in the file cannot re-report old bugs.
 *
 * The class file is required directly: test/bootstrap.php deliberately avoids
 * vendor/autoload.php so the suite stays valid even when vendor/ is mid-migration.
 */
class FingerprinterTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../module/SionModel/src/Error/Fingerprinter.php';
    }

    /**
     * Throws for real, inside a real method call, so the trace is genuine
     * rather than hand-faked.
     */
    private function throwInner(): void
    {
        throw new RuntimeException('root cause');
    }

    private function throwWrapped(): Throwable
    {
        try {
            $this->throwInner();
        } catch (Throwable $inner) {
            return new RuntimeException('outer wrapper', 0, $inner);
        }
    }

    public function testSameLogicalFailureProducesTheSameFingerprintAcrossCalls(): void
    {
        $fingerprinter = new Fingerprinter('/app');

        // Two independent throws of the "same" logical failure (same class
        // chain, same origin method) must collapse to one fingerprint.
        $first = $this->throwWrapped();
        $second = $this->throwWrapped();

        $this->assertSame(
            $fingerprinter->fingerprint($first, 'library.show'),
            $fingerprinter->fingerprint($second, 'library.show')
        );
    }

    /**
     * The single most important test in the suite: fingerprint() takes a
     * route *name* and no URI at all. A different route must change the
     * fingerprint; nothing request-shaped exists to vary the fingerprint
     * for the same route, which is exactly the guarantee against a mail
     * flood on every request to a variable URL.
     */
    public function testDifferentRouteProducesDifferentFingerprintButSameRouteIsStable(): void
    {
        $fingerprinter = new Fingerprinter('/app');
        $e = $this->throwWrapped();

        $routeA1 = $fingerprinter->fingerprint($e, 'library.show');
        $routeA2 = $fingerprinter->fingerprint($e, 'library.show');
        $routeB = $fingerprinter->fingerprint($e, 'library.edit');

        // Calling fingerprint() repeatedly with the same route is the only
        // way to demonstrate stability, because the method accepts no
        // request-identifying argument (no URI, no query string) at all.
        $this->assertSame($routeA1, $routeA2, 'the same route must always yield the same fingerprint');
        $this->assertNotSame($routeA1, $routeB, 'a different route must yield a different fingerprint');
    }

    public function testNoRouteAndEmptyRouteFingerprintIdentically(): void
    {
        $fingerprinter = new Fingerprinter('/app');
        $e = $this->throwWrapped();

        $this->assertSame(
            $fingerprinter->fingerprint($e, null),
            $fingerprinter->fingerprint($e, '')
        );
    }

    /**
     * origin() must name the enclosing Class::method of the ROOT CAUSE
     * (deepest previous), not the outermost wrapper.
     */
    public function testOriginNamesRootCauseEnclosingMethodNotOutermostWrapper(): void
    {
        $fingerprinter = new Fingerprinter('/app');
        $e = $this->throwWrapped();

        $origin = $fingerprinter->origin($e);

        // Instance methods report with the '->' call type PHP records for
        // that frame; the point under test is that it names throwInner()
        // (the root cause's enclosing method), not throwWrapped().
        $this->assertSame(self::class . '->throwInner', $origin);
    }

    public function testOriginIsNotALineNumber(): void
    {
        $fingerprinter = new Fingerprinter('/app');
        $e = $this->throwWrapped();

        $origin = $fingerprinter->origin($e);

        $this->assertDoesNotMatchRegularExpression('/:\d+$/', $origin);
    }

    /**
     * An explicit $origin argument to fingerprint() must override the
     * derived one, e.g. for the fatal-error path where the synthesised
     * ErrorException's trace points at the shutdown handler.
     */
    public function testExplicitOriginArgumentOverridesDerivedOrigin(): void
    {
        $fingerprinter = new Fingerprinter('/app');
        $e = $this->throwWrapped();

        $derived  = $fingerprinter->fingerprint($e, 'library.show');
        $explicit = $fingerprinter->fingerprint($e, 'library.show', 'Custom\\Path::customOrigin');

        $this->assertNotSame($derived, $explicit);

        // Pin the exact seed construction so the override is proven, not just inferred.
        $expected = substr(hash('sha256', implode('|', [
            implode('>', $fingerprinter->classChain($e)),
            'library.show',
            'Custom\\Path::customOrigin',
        ])), 0, 8);
        $this->assertSame($expected, $explicit);
    }

    public function testClassChainIsOutermostFirstAndIncludesEveryPreviousLevel(): void
    {
        $fingerprinter = new Fingerprinter('/app');

        $root = new \InvalidArgumentException('root');
        $middle = new RuntimeException('middle', 0, $root);
        $outer = new Exception('outer', 0, $middle);

        $this->assertSame(
            [\Exception::class, RuntimeException::class, \InvalidArgumentException::class],
            $fingerprinter->classChain($outer)
        );
    }

    public function testRootCauseReturnsTheDeepestException(): void
    {
        $fingerprinter = new Fingerprinter('/app');

        $root = new \InvalidArgumentException('root');
        $middle = new RuntimeException('middle', 0, $root);
        $outer = new Exception('outer', 0, $middle);

        $this->assertSame($root, $fingerprinter->rootCause($outer));
        $this->assertSame($root, $fingerprinter->rootCause($root));
    }

    public function testRelativePathStripsAppRootAndNormalisesBackslashes(): void
    {
        $fingerprinter = new Fingerprinter('/app');

        $this->assertSame(
            'module/SionModel/src/Error/ErrorListener.php',
            $fingerprinter->relativePath('/app/module/SionModel/src/Error/ErrorListener.php')
        );

        // A path handed in with backslash separators must be normalised to
        // forward slashes before the app root is stripped.
        $this->assertSame(
            'module/Foo.php',
            $fingerprinter->relativePath('/app\\module\\Foo.php')
        );
    }

    public function testRelativePathLeavesPathsOutsideAppRootUntouched(): void
    {
        $fingerprinter = new Fingerprinter('/app');

        $this->assertSame('/usr/lib/php/Foo.php', $fingerprinter->relativePath('/usr/lib/php/Foo.php'));
    }

    /**
     * stripAppRoot() is applied to exception messages and traces and must
     * NOT normalise backslashes: doing so previously rewrote
     * SionModel\Error\ErrorListener into SionModel/Error/ErrorListener, a
     * real bug this method exists to prevent.
     */
    public function testStripAppRootStripsPathButLeavesBackslashesAlone(): void
    {
        $fingerprinter = new Fingerprinter('/opt/projects/schoenstatt.link');

        $text = '/opt/projects/schoenstatt.link/module/SionModel/src/Error/ErrorListener.php: '
            . 'SionModel\\Error\\ErrorListener failed';

        $result = $fingerprinter->stripAppRoot($text);

        $this->assertStringNotContainsString('/opt/projects/schoenstatt.link', $result);
        $this->assertStringContainsString('SionModel\\Error\\ErrorListener', $result);
        $this->assertSame(
            'module/SionModel/src/Error/ErrorListener.php: SionModel\\Error\\ErrorListener failed',
            $result
        );
    }

    public function testStripAppRootDoesNotTouchBackslashSeparatedInputAtAll(): void
    {
        $fingerprinter = new Fingerprinter('/app');

        // Contrast with relativePath(): the same backslash-separated input
        // is left completely alone by stripAppRoot(), because it only ever
        // matches the forward-slash form of the app root.
        $text = '/app\\module\\Foo.php mentions Some\\Namespace\\Klass';
        $this->assertSame($text, $fingerprinter->stripAppRoot($text));
    }

    public function testIsFirstPartyTrueForAppCodeFalseForVendorAndOutsidePaths(): void
    {
        $fingerprinter = new Fingerprinter('/app');

        $this->assertTrue($fingerprinter->isFirstParty('/app/module/Books/src/Model/LibraryTable.php'));
        $this->assertFalse($fingerprinter->isFirstParty('/app/vendor/laminas/laminas-mvc/src/Foo.php'));
        $this->assertFalse($fingerprinter->isFirstParty('/usr/lib/php/Foo.php'));
    }
}
