<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

use function count;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function is_array;
use function json_decode;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function strtotime;
use function substr;
use function time;
use function trim;

/**
 * The two shrine-GeoJSON endpoints, ported to the Symfony kernel 2026-08-07:
 * /api/v1/associations/shrines.json and /api/v2/associations/shrines.json.
 *
 * The first ported routes that answer a *machine* caller, which changes what is worth
 * asserting. There is no layout, no locale-dependent markup and no permission-gated
 * section; what a mobile app depends on is the media type, the document shape and the
 * cache headers. So those are what this measures, and the discriminator that every
 * ported route needs: that Symfony served it at all.
 *
 * The interesting assertion is `testTheCacheHeadersAreSentExactlyOnce`. The laminas
 * response sends **contradictory pairs** — `no-store, no-cache, must-revalidate` from
 * PHP's session cache limiter *and* `max-age=1800, public` from the controller's
 * makeCacheable(), because the limiter writes into the SAPI header list before
 * sendHeaders() appends the response object's own. A cache that reads the first
 * Cache-Control it is given sees no-store, so the half-hour of caching this endpoint
 * asks for has most likely never happened. The ported route starts no session, so it
 * sends each header once. That is a behaviour change, it is the one the code always
 * intended, and this is where it is pinned.
 */
class ShrinesGeoJsonSmokeTest extends SmokeTestCase
{
    /** @return array<string, array{0: string}> */
    public static function versions(): array
    {
        return [
            'v1' => ['/en/api/v1/associations/shrines.json'],
            'v2' => ['/en/api/v2/associations/shrines.json'],
        ];
    }

    /**
     * The discriminator, as in ShrinesSymfonySmokeTest: laminas sends
     * `Set-Cookie: slm_locale=en_US` on every response because SlmLocale's cookie
     * strategy sets it at MvcEvent::FINISH, and SlmLocale never runs for a ported
     * route. A locale cookie coming back means the catch-all served this and every
     * other assertion here is measuring the laminas endpoint.
     */
    #[DataProvider('versions')]
    public function testTheEndpointIsServedBySymfonyAndNotBridgedToLaminas(string $path): void
    {
        $response = $this->request('GET', $path);

        self::assertSame(200, $response['status']);
        self::assertSame('application/json; charset=utf-8', $response['contentType']);
        self::assertStringNotContainsString(
            'slm_locale=en_US',
            $response['headers']['set-cookie'] ?? '',
            'a slm_locale cookie means SlmLocale ran, i.e. laminas-mvc served this'
        );
    }

    /** GeoJSON, with the shape a client parses: a FeatureCollection of Point features with names. */
    #[DataProvider('versions')]
    public function testTheDocumentIsAGeoJsonFeatureCollection(string $path): void
    {
        $decoded = json_decode($this->request('GET', $path)['body'], true);

        self::assertIsArray($decoded);
        self::assertSame('FeatureCollection', $decoded['type'] ?? null);
        self::assertIsArray($decoded['features'] ?? null);
        self::assertGreaterThan(100, count($decoded['features']), 'far fewer shrines than the database holds');

        $first = $decoded['features'][0] ?? null;
        self::assertIsArray($first);
        self::assertSame('Feature', $first['type'] ?? null);
        self::assertSame('Point', $first['geometry']['type'] ?? null);
        self::assertCount(2, $first['geometry']['coordinates'] ?? [], 'a Point is [longitude, latitude]');
        self::assertArrayHasKey('name', is_array($first['properties'] ?? null) ? $first['properties'] : []);
    }

    /**
     * The two versions are the same document. Both laminas actions are byte-identical
     * and one Symfony controller now serves both, so this is what would notice if a
     * future change to "v2" silently changed v1 for every existing client.
     */
    public function testBothVersionsReturnTheSameDocument(): void
    {
        self::assertSame(
            $this->request('GET', '/en/api/v1/associations/shrines.json')['body'],
            $this->request('GET', '/en/api/v2/associations/shrines.json')['body'],
            'v1 and v2 are byte-identical on the laminas side; one controller serves both here'
        );
    }

    /**
     * Once each, and with the values makeCacheable() asks for — not alongside the
     * session cache limiter's contradicting pair. See the class docblock.
     */
    #[DataProvider('versions')]
    public function testTheCacheHeadersAreSentExactlyOnce(string $path): void
    {
        $headers = $this->rawHeaders($path);

        self::assertSame(
            1,
            $this->countHeader($headers, 'cache-control'),
            "more than one Cache-Control on $path: a session was started and PHP's cache limiter "
            . 'added its own, which is the contradiction the port exists to remove'
        );
        self::assertSame(1, $this->countHeader($headers, 'expires'), "more than one Expires on $path");
        self::assertSame(
            0,
            $this->countHeader($headers, 'pragma'),
            'the laminas action emitted a valueless Pragma: to cancel the limiter\'s; with no limiter '
            . 'there is nothing to cancel and the header should be absent'
        );

        self::assertMatchesRegularExpression('#(^|\n)cache-control:\s*max-age=1800, public#i', $headers);
    }

    /**
     * No session, which is what declaring the route open buys — and the reason the
     * headers above are singular. A machine endpoint the apps poll should not be
     * handed a session cookie it will never send back.
     */
    #[DataProvider('versions')]
    public function testNoSessionIsStarted(string $path): void
    {
        self::assertSame(
            0,
            preg_match('#Set-Cookie:\s*PHPSESSID=[A-Za-z0-9]#i', $this->rawHeaders($path)),
            "a session was started on $path; the route is declared open precisely so it is not"
        );
    }

    /** The Expires instant is half an hour out, which is what max-age=1800 promises. */
    public function testExpiresIsHalfAnHourAhead(): void
    {
        $headers = $this->rawHeaders('/en/api/v2/associations/shrines.json');
        self::assertSame(1, preg_match('#(?:^|\n)expires:\s*(.+)#i', $headers, $m));

        $expires = strtotime(trim($m[1]));
        self::assertNotFalse($expires);
        //a wide window: this compares the server's clock against the test process's
        self::assertGreaterThan(time() + 1500, $expires);
        self::assertLessThan(time() + 2100, $expires);
    }

    /**
     * SlmLocale redirects an unprefixed path even for JSON — measured before the port:
     * /api/v2/associations/shrines.json answered 302 to /en/api/v2/…. A machine caller
     * already follows that hop, so removing it would be a change too.
     */
    #[DataProvider('versions')]
    public function testTheUnprefixedFormRedirectsAsSlmLocaleDoes(string $path): void
    {
        $unprefixed = (string) preg_replace('#^/en#', '', $path);

        $response = $this->request('GET', $unprefixed);

        self::assertSame(302, $response['status']);
        self::assertStringEndsWith($path, $response['redirect']);
    }

    /** A version that does not exist must still fall through to laminas, not be swallowed here. */
    public function testAnUnknownApiVersionStillFallsThroughToLaminas(): void
    {
        $response = $this->request('GET', '/en/api/v9/associations/shrines.json');

        self::assertNotSame(200, $response['status'], '/api/v9/… should not be served by the ported route');
    }

    private function rawHeaders(string $path): string
    {
        $ch = curl_init($this->baseUrl() . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Accept-Language: en'],
        ]);
        $raw  = (string) curl_exec($ch);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        //no curl_close(): deprecated in PHP 8.5, and a no-op since 8.0

        return substr($raw, 0, $size);
    }

    private function countHeader(string $headers, string $name): int
    {
        return preg_match_all('#(?:^|\n)' . preg_quote($name, '#') . ':#i', $headers);
    }
}
