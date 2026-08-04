<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Http\LaminasResponseConverter;
use Laminas\Http\Headers;
use Laminas\Http\Response as LaminasResponse;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Contract test for the Laminas -> Symfony response conversion that every
 * bridged request passes through (see App\Http\LegacyBridge).
 *
 * Each case here is a way the conversion can be wrong *without* failing: a
 * dropped duplicate header, a decoded body, an invented Cache-Control. The smoke
 * suite would not notice any of them, because it asserts on status codes and
 * content markers rather than on the header set.
 *
 * Needs vendor/ (laminas-http and symfony/http-foundation), so it lives outside
 * the vendor-free unit suite: php composer.phar integration
 */
class LaminasResponseConverterTest extends TestCase
{
    private LaminasResponseConverter $convert;

    protected function setUp(): void
    {
        $this->convert = new LaminasResponseConverter();
    }

    public function testStatusAndContentSurvive(): void
    {
        $laminas = new LaminasResponse();
        $laminas->setStatusCode(404);
        $laminas->setContent('<html>not here</html>');

        $symfony = ($this->convert)($laminas);

        $this->assertSame(404, $symfony->getStatusCode());
        $this->assertSame('<html>not here</html>', $symfony->getContent());
    }

    /**
     * The whole point of the redirect path: a 302 whose Location is lost renders
     * as an empty page, which is a 200-shaped failure.
     */
    public function testRedirectKeepsItsLocation(): void
    {
        $laminas = new LaminasResponse();
        $laminas->setStatusCode(302);
        $laminas->getHeaders()->addHeaderLine('Location', 'https://schoenstatt.link/en/');

        $symfony = ($this->convert)($laminas);

        $this->assertSame(302, $symfony->getStatusCode());
        $this->assertSame('https://schoenstatt.link/en/', $symfony->headers->get('Location'));
    }

    /**
     * Set-Cookie is a MultipleHeaderInterface, which is how laminas marks a
     * header that may legitimately repeat. A conversion that maps each field
     * name to one value keeps only the last, which for Set-Cookie means
     * silently dropping a session.
     */
    public function testRepeatedHeaderNamesKeepEveryValue(): void
    {
        $laminas = new LaminasResponse();
        $headers = new Headers();
        $headers->addHeaderLine('Set-Cookie', 'first=1; Path=/');
        $headers->addHeaderLine('Set-Cookie', 'second=2; Path=/');
        $laminas->setHeaders($headers);

        $symfony = ($this->convert)($laminas);

        $cookies = $symfony->headers->getCookies();
        $names = [];
        foreach ($cookies as $cookie) {
            $names[] = $cookie->getName();
        }
        sort($names);
        $this->assertSame(['first', 'second'], $names, 'both Set-Cookie headers must survive');
    }

    /**
     * The other half of the same rule, and the reason the converter uses
     * Headers::toArray() instead of iterating: a header that is *not* a
     * MultipleHeaderInterface collapses to its last value, because that is what
     * PhpEnvironment\Response::sendHeaders() does today — it calls header() with
     * replace left at its default for those. Emitting both would be a behaviour
     * change dressed up as a conversion.
     */
    public function testARepeatedOrdinaryHeaderCollapsesAsTheLaminasSenderWould(): void
    {
        $laminas = new LaminasResponse();
        $laminas->getHeaders()->addHeaderLine('X-Origin-Server', 'first');
        $laminas->getHeaders()->addHeaderLine('X-Origin-Server', 'second');

        $symfony = ($this->convert)($laminas);

        $this->assertSame(['second'], $symfony->headers->all('X-Origin-Server'));
    }

    /**
     * Laminas\Http\Response::getBody() gunzips according to the response's own
     * Content-Encoding; getContent() does not. The sitemap route gzips its
     * payload, so using getBody() would emit plaintext under a gzip header —
     * which browsers reject outright.
     */
    public function testGzippedBodyIsPassedThroughUndecoded(): void
    {
        $compressed = (string) gzcompress('<urlset/>');
        $laminas = new LaminasResponse();
        $laminas->getHeaders()->addHeaderLine('Content-Encoding', 'gzip');
        $laminas->setContent($compressed);

        $symfony = ($this->convert)($laminas);

        $this->assertSame($compressed, $symfony->getContent(), 'the body must not be decoded');
        $this->assertSame('gzip', $symfony->headers->get('Content-Encoding'));
    }

    /**
     * ResponseHeaderBag invents "no-cache, private" for any response without a
     * Cache-Control. Laminas sends none, and PHP's session cache limiter already
     * emits a stricter one at the SAPI level, so the invented directive would be
     * appended to every authenticated page as a weaker second opinion.
     */
    public function testNoCacheControlIsInventedWhenLaminasSentNone(): void
    {
        $symfony = ($this->convert)(new LaminasResponse());

        $this->assertFalse(
            $symfony->headers->has('Cache-Control'),
            'the converter must not add a Cache-Control the application never sent'
        );
    }

    /**
     * A Cache-Control the application did set has to survive — but not
     * character for character: ResponseHeaderBag parses the directives and
     * re-emits them in alphabetical order, so "public, max-age=600" comes back
     * as "max-age=600, public". Directive order carries no meaning in HTTP
     * (RFC 9111 §5.2), so this is recorded rather than fought.
     */
    public function testAnExplicitCacheControlIsPreserved(): void
    {
        $laminas = new LaminasResponse();
        $laminas->getHeaders()->addHeaderLine('Cache-Control', 'public, max-age=600');

        $symfony = ($this->convert)($laminas);

        $directives = explode(', ', (string) $symfony->headers->get('Cache-Control'));
        sort($directives);
        $this->assertSame(['max-age=600', 'public'], $directives);
    }
}
