<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use function json_decode;

/**
 * `/user/my-data`: an anonymous visitor is sent to sign in, and a signed-in account gets its
 * own data as a download that nothing may cache. The second half is what a status code alone
 * cannot show — that the export is of *this* account.
 */
class MyDataSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from the other suites' prefixes, or one class's tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'mydata-smoke-';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    public function testAnAnonymousVisitorIsSentToSignIn(): void
    {
        $response = $this->get('/en/user/my-data');

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/en/user/login?redirect=', $response['redirect']);
    }

    public function testASignedInAccountDownloadsItsOwnData(): void
    {
        $jar = $this->newCookieJar();
        $email = $this->signIn($jar);

        $response = $this->get('/en/user/my-data', false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringStartsWith('application/json', $response['contentType']);
        $this->assertStringStartsWith('attachment;', $response['headers']['content-disposition'] ?? '');
        $this->assertStringContainsString('no-store', $response['headers']['cache-control'] ?? '');

        $export = json_decode($response['body'], true);
        $this->assertIsArray($export);
        $this->assertSame($email, $export['accounts'][0]['account']['email'] ?? null, 'the export is not this account\'s');
        $this->assertArrayNotHasKey('verification_token', $export['accounts'][0]['account']);
    }

    public function testTheAccountMenuLinksToIt(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get('/en/', false, $jar);

        $this->assertStringContainsString('href="/en/user/my-data"', $response['body']);
    }
}
