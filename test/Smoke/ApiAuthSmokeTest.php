<?php

namespace SchoenstattTest\Smoke;

/**
 * The JWT gate on the authenticated API routes, over real HTTP.
 *
 * Added with the firebase/php-jwt 6 -> 7 upgrade, which had no test coverage at
 * all: RestApi\Controller\ApiController::checkAuthorization() runs as a dispatch
 * listener, so its failure modes are only observable through the HTTP status.
 *
 * The distinction being pinned here is client fault vs. server fault. Every
 * unusable token is the caller's problem and must stay a 4xx; a 500 means the
 * app itself broke (and, since 2026-08-03, mails an exception report). php-jwt 7
 * makes that easy to get wrong: it signals a short signing key and a caller's
 * garbage token with the same exception class.
 *
 * Tokens are signed by hand rather than through Firebase\JWT, so the suite stays
 * vendor-free and independently checks that our tokens are ordinary HS256 JWTs.
 */
class ApiAuthSmokeTest extends SmokeTestCase
{
    /**
     * A route that is genuinely gated: 'isAuthorizationRequired' => true on a
     * controller that inherits checkAuthorization() from ApiController.
     *
     * Deliberately not one of LibrariesApiController's routes — those declare
     * the same flag but extend AbstractRestfulController directly, so nothing
     * reads it (see docs/BACKLOG.md, "Unauthenticated API routes").
     *
     * Note this endpoint answers 200 with an empty body rather than JSON, a
     * pre-existing quirk of BooksApiController::getList() that has nothing to
     * do with the JWT gate. The assertions below therefore pin the gate — which
     * statuses it produces — and not the payload.
     */
    private const GUARDED_PATH = '/api/v1/libraries/3/books';

    public function testValidJwtIsAccepted(): void
    {
        $response = $this->getWithBearer($this->mintJwt(['sub' => 1, 'exp' => (string) (time() + 3600)]));

        $this->assertSame(200, $response['status'], 'a validly signed, unexpired JWT should be accepted');
        $this->assertStringNotContainsString('Fatal error', $response['body']);
    }

    public function testMissingTokenIsUnauthorized(): void
    {
        $response = $this->request('GET', self::GUARDED_PATH, [], true);

        $this->assertSame(401, $response['status'], 'no credentials should be 401');
        $this->assertStringContainsString('Authentication Required', $response['body']);
    }

    /**
     * The regression this class exists for: a token that is not even
     * base64/JSON must be a 400, not a 500. php-jwt raises DomainException
     * ('Malformed UTF-8 characters') here — the same class it uses for an
     * unusably short key — so any handler that treats DomainException as a
     * server error turns this into a 500 plus an exception email.
     */
    public function testMalformedTokenIsABadRequestNotAServerError(): void
    {
        foreach (['not.a.jwt', 'a.b', 'not-a-jwt-at-all', 'ey.ey.ey'] as $garbage) {
            $response = $this->getWithBearer($garbage);

            $this->assertSame(
                400,
                $response['status'],
                sprintf('a malformed token (%s) should be a client error', $garbage)
            );
            $this->assertStringNotContainsString('Fatal error', $response['body']);
        }
    }

    /**
     * The token may also arrive as a query parameter, where PHP will hand the
     * app an array if the caller asks for one. That used to reach php-jwt's
     * string parameter and raise an uncaught TypeError — a 500, plus an
     * exception email, on demand for anyone.
     */
    public function testArrayValuedTokenParameterIsNotAServerError(): void
    {
        $response = $this->request('GET', self::GUARDED_PATH . '?token%5B%5D=abc', [], true);

        $this->assertSame(401, $response['status'], 'an array token is no token: 401, not 500');
        $this->assertStringNotContainsString('TypeError', $response['body']);
    }

    public function testTokenSignedWithTheWrongKeyIsRejected(): void
    {
        $forged = $this->mintJwt(['sub' => 1, 'exp' => (string) (time() + 3600)], str_repeat('z', 40));

        $response = $this->getWithBearer($forged);

        $this->assertSame(400, $response['status'], 'a wrong-key signature should be refused');
        $this->assertStringContainsString('Signature verification failed', $response['body']);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $response = $this->getWithBearer($this->mintJwt(['sub' => 1, 'exp' => (string) (time() - 60)]));

        $this->assertSame(400, $response['status'], 'an expired token should be refused');
        $this->assertStringContainsString('Expired token', $response['body']);
    }

    /**
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    private function getWithBearer(string $token): array
    {
        // Follow redirects: SlmLocale bounces /api/* to /<locale>/api/* first.
        return $this->request('GET', self::GUARDED_PATH, ['Authorization: Bearer ' . $token], true);
    }

    /**
     * Sign an HS256 JWT the way the app does, with the app's configured key.
     *
     * @param array<string, mixed> $payload
     */
    private function mintJwt(array $payload, ?string $key = null): string
    {
        $key ??= $this->cypherKey();
        $segments = [
            $this->base64Url((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256'])),
            $this->base64Url((string) json_encode($payload)),
        ];
        $signingInput = implode('.', $segments);
        $segments[] = $this->base64Url(hash_hmac('sha256', $signingInput, $key, true));

        return implode('.', $segments);
    }

    private function cypherKey(): string
    {
        $localConfig = __DIR__ . '/../../config/autoload/local.php';
        if (! is_readable($localConfig)) {
            $this->markTestSkipped('no config/autoload/local.php: cannot sign a token this app will accept');
        }
        $config = include $localConfig;
        $cypherKey = $config['ApiRequest']['jwtAuth']['cypherKey'] ?? null;
        if (! is_string($cypherKey) || '' === $cypherKey) {
            $this->markTestSkipped('ApiRequest.jwtAuth.cypherKey is not configured here');
        }
        return $cypherKey;
    }

    private function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
