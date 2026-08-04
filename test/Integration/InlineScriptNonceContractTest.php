<?php

namespace SchoenstattTest\Integration;

use Laminas\View\Renderer\PhpRenderer;
use PHPUnit\Framework\TestCase;
use SionModel\View\Helper\InlineScript;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Characterization test for SionModel\View\Helper\InlineScript, the CSP-nonce
 * decorator over laminas' inlineScript helper.
 *
 * This is the awkward one: laminas marked BOTH Laminas\View\Helper\InlineScript
 * and its parent HeadScript `@final`, so the whole chain is closed and the
 * helper had to be rebuilt around a wrapped stock instance rather than a
 * subclass. Templates use a narrow surface — captureStart() (26 call sites,
 * where the nonce is injected), captureEnd() (26), appendFile() (3), and
 * __toString() from layout.phtml — and all of it is pinned here.
 *
 * Written against the pre-change code and passing there.
 *
 * Needs vendor/ (laminas-view), so it runs in the capsule:
 * php composer.phar integration
 */
class InlineScriptNonceContractTest extends TestCase
{
    private function helper(?string $nonce = null): InlineScript
    {
        $helper = new InlineScript($nonce);
        $helper->setView(new PhpRenderer());

        return $helper;
    }

    private function capture(InlineScript $helper, string $script): void
    {
        $helper->captureStart();
        echo $script;
        $helper->captureEnd();
    }

    /**
     * The reason this class exists: captured inline scripts carry the nonce so
     * they survive a Content-Security-Policy that forbids unsafe-inline.
     */
    public function testCapturedScriptCarriesTheNonce(): void
    {
        $helper = $this->helper('NONCE123');
        $this->capture($helper, "alert('hi');");

        self::assertStringContainsString('nonce="NONCE123"', $helper->toString());
        self::assertStringContainsString("alert('hi');", $helper->toString());
    }

    public function testWithoutANonceNoNonceAttributeIsEmitted(): void
    {
        $helper = $this->helper();
        $this->capture($helper, 'var x=1;');

        self::assertStringNotContainsString('nonce', $helper->toString());
        self::assertStringContainsString('var x=1;', $helper->toString());
    }

    /**
     * Only captured *inline* scripts get the nonce; file references do not,
     * because a CSP nonce is not needed for an external src.
     */
    public function testAppendedFilesDoNotCarryTheNonce(): void
    {
        $helper = $this->helper('NONCE123');
        $helper->appendFile('/js/app.js');

        $html = $helper->toString();

        self::assertStringContainsString('src="&#x2F;js&#x2F;app.js"', $html);
        self::assertStringNotContainsString('nonce', $html);
    }

    /**
     * layout.phtml does `echo $this->inlineScript();`, so string conversion has
     * to render the accumulated scripts.
     */
    public function testStringConversionRendersTheScripts(): void
    {
        $helper = $this->helper('NONCE123');
        $this->capture($helper, "alert('hi');");

        self::assertSame($helper->toString(), (string) $helper);
    }

    /**
     * Byte-for-byte output, capture followed by appendFile.
     *
     * The absence of a separator between the two <script> tags is deliberate
     * and pre-existing: the old subclass overrode __construct() without calling
     * parent::__construct(), so HeadScript's `setSeparator(PHP_EOL)` never ran
     * and the container kept its default ''. The rewrite reproduces that
     * explicitly rather than silently adopting upstream's newline.
     */
    public function testRenderedMarkupIsUnchanged(): void
    {
        $helper = $this->helper('NONCE123');
        $this->capture($helper, "alert('hi');");
        $helper->appendFile('/js/app.js');

        $expected = '<script type="text&#x2F;javascript" nonce="NONCE123">' . "\n"
            . '    //<!--' . "\n"
            . "    alert('hi');" . "\n"
            . '    //-->' . "\n"
            . '</script>'
            . '<script type="text&#x2F;javascript" src="&#x2F;js&#x2F;app.js"></script>';

        self::assertSame($expected, $helper->toString());
    }

    /**
     * The factory sets the nonce after construction, once CspListener has one.
     */
    public function testNonceCanBeSetAfterConstruction(): void
    {
        $helper = $this->helper();
        self::assertNull($helper->getNonce());

        $helper->setNonce('LATER');
        self::assertSame('LATER', $helper->getNonce());

        $this->capture($helper, 'var x=1;');
        self::assertStringContainsString('nonce="LATER"', $helper->toString());
    }

    public function testSetNonceIsFluent(): void
    {
        $helper = $this->helper();

        self::assertSame($helper, $helper->setNonce('X'));
    }

    /**
     * An explicit nonce attribute passed by the caller wins over the injected
     * one.
     */
    public function testExplicitNonceAttributeIsNotOverwritten(): void
    {
        $helper = $this->helper('NONCE123');
        $helper->captureStart(
            \Laminas\View\Helper\Placeholder\Container\AbstractContainer::APPEND,
            'text/javascript',
            ['nonce' => 'EXPLICIT']
        );
        echo 'var x=1;';
        $helper->captureEnd();

        $html = $helper->toString();

        self::assertStringContainsString('nonce="EXPLICIT"', $html);
        self::assertStringNotContainsString('NONCE123', $html);
    }
}
