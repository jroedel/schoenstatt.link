<?php

namespace SchoenstattTest\Integration;

use DomainException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Contract test for the firebase/php-jwt 6 -> 7 upgrade (docs/BACKLOG.md
 * advisory debt: CVE-2025-45769).
 *
 * The library's encode()/decode() signatures did not change, so
 * RestApi\Controller\ApiController needed no rewrite. What did change is a
 * *runtime* rule that depends on configuration rather than on code: v7.0.0
 * validates key length, so an HS256 cypherKey shorter than the 256-bit digest
 * (32 bytes) now throws on every sign *and* every verify. Nothing in the app
 * would notice until an API request arrived, hence this test.
 *
 * Needs vendor/ (php-jwt), so it lives outside the vendor-free unit suite and
 * runs in the capsule: php composer.phar integration
 */
class JwtRoundTripTest extends TestCase
{
    /** Long enough for HS256, and deliberately not the app's key. */
    private const KEY = 'test-key-of-at-least-32-bytes!!!';

    private const ALG = 'HS256';

    /**
     * The payload JUser\Controller\LoginV1ApiController::getNewJwtTokenResponse()
     * builds, shape included: Carbon's format('U') yields exp as a numeric
     * *string*. v7.1.0 added numeric-type validation of iat/nbf/exp on both
     * encode and decode, and is_numeric() accepts that string — so the tokens
     * the app already issued to clients stay valid.
     */
    public function testAppTokenShapeRoundTrips(): void
    {
        $expiration = (string) (time() + 15552000); //six months, as the app does
        $payload = [
            'sub' => 42,
            'exp' => $expiration,
            'jti' => 'a1b2c3d4e5',
        ];

        $jwt = JWT::encode($payload, self::KEY, self::ALG);
        $decoded = JWT::decode($jwt, new Key(self::KEY, self::ALG));

        $this->assertIsObject($decoded, 'checkAuthorization() gates on is_object()');
        $this->assertSame(42, $decoded->sub, 'the API controllers read tokenPayload->sub');
        $this->assertSame($expiration, $decoded->exp);
        $this->assertSame('a1b2c3d4e5', $decoded->jti);
    }

    /**
     * The v7.0.0 security fix, pinned in both directions. 31 bytes is the
     * interesting case: it worked under 6.x and fatals under 7.x.
     */
    public function testHs256RejectsAKeyShorterThanTheDigest(): void
    {
        $tooShort = str_repeat('k', 31);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Provided key is too short');
        JWT::encode(['sub' => 1], $tooShort, self::ALG);
    }

    public function testHs256AcceptsExactlyThirtyTwoBytes(): void
    {
        $exactly32 = str_repeat('k', 32);

        $jwt = JWT::encode(['sub' => 1], $exactly32, self::ALG);

        $this->assertSame(1, JWT::decode($jwt, new Key($exactly32, self::ALG))->sub);
    }

    /**
     * A short key breaks *verification* too, not just issuance — which is why
     * ApiController::decodeJwtToken() rethrows DomainException instead of
     * reporting it to the caller as a bad token.
     */
    public function testAShortKeyAlsoBreaksVerification(): void
    {
        $tooShort = str_repeat('k', 31);
        //sign with a long key so only the verify side can fail
        $jwt = JWT::encode(['sub' => 1], self::KEY, self::ALG);

        $this->expectException(DomainException::class);
        JWT::decode($jwt, new Key($tooShort, self::ALG));
    }

    /**
     * The app's own configured key must satisfy the rule. This is the check
     * that would have caught a too-short production secret before deploy —
     * see docs/DEPLOY.md for the server-side equivalent.
     */
    public function testConfiguredCypherKeySatisfiesTheKeyLengthRule(): void
    {
        $localConfig = __DIR__ . '/../../config/autoload/local.php';
        if (! is_readable($localConfig)) {
            $this->markTestSkipped('no config/autoload/local.php on this machine');
        }
        $config = include $localConfig;
        $cypherKey = $config['ApiRequest']['jwtAuth']['cypherKey'] ?? null;
        if (! is_string($cypherKey) || '' === $cypherKey) {
            $this->markTestSkipped('ApiRequest.jwtAuth.cypherKey is not configured here');
        }

        $algorithm = $config['ApiRequest']['jwtAuth']['tokenAlgorithm'] ?? 'HS256';
        $minimumBytes = ((int) substr($algorithm, 2)) / 8; //HS256 -> 32

        $this->assertGreaterThanOrEqual(
            $minimumBytes,
            strlen($cypherKey),
            sprintf(
                'ApiRequest.jwtAuth.cypherKey is %d bytes; %s needs at least %d under php-jwt 7',
                strlen($cypherKey),
                $algorithm,
                $minimumBytes
            )
        );

        //and it really does work end to end with that key
        $jwt = JWT::encode(['sub' => 1], $cypherKey, $algorithm);
        $this->assertSame(1, JWT::decode($jwt, new Key($cypherKey, $algorithm))->sub);
    }

    /**
     * Client-side failures must stay client-side failures: ApiController turns
     * these into a 400 carrying the message, and that behavior is unchanged.
     */
    public function testTamperedSignatureIsRejected(): void
    {
        $jwt = JWT::encode(['sub' => 1], self::KEY, self::ALG);
        [$header, $body] = explode('.', $jwt);
        $forged = $header . '.' . $body . '.' . strrev(explode('.', $jwt)[2]);

        $this->expectException(SignatureInvalidException::class);
        JWT::decode($forged, new Key(self::KEY, self::ALG));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $jwt = JWT::encode(['sub' => 1, 'exp' => (string) (time() - 60)], self::KEY, self::ALG);

        $this->expectException(ExpiredException::class);
        JWT::decode($jwt, new Key(self::KEY, self::ALG));
    }
}
