<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * JUser / ZfcUser module: authentication entry points and the user directory.
 *
 * Register and logout require the GD extension in the runtime image: the
 * shared ZfcUser controller factory eagerly builds "zfcuser_register_form",
 * whose image CAPTCHA fatals without GD.
 */
class UserSmokeTest extends SmokeTestCase
{
    public function testLoginPageRenders(): void
    {
        $response = $this->assertRendersOk('/en/user/login');

        $this->assertStringContainsString('Sign In', $response['body']);
    }

    /**
     * Without a verification token the controller silently bounces the visitor
     * to the localized homepage rather than rendering a form.
     */
    public function testVerifyEmailWithoutTokenRedirectsHome(): void
    {
        $response = $this->get('/en/users/verify-email');

        $this->assertSame(302, $response['status'], 'GET /en/users/verify-email should redirect');
        $this->assertStringEndsWith('/en/', $response['redirect'], 'GET /en/users/verify-email should land on /en/');
    }

    public function testRegistrationThanksPageRenders(): void
    {
        $response = $this->assertRendersOk('/en/users/thanks');

        $this->assertStringContainsString('Thanks for creating an account', $response['body']);
    }

    public function testRegisterPageRenders(): void
    {
        $response = $this->assertRendersOk('/en/user/register');

        $this->assertStringContainsString('<h1>Register</h1>', $response['body']);
    }

    public function testLogoutRedirectsToLogin(): void
    {
        $response = $this->get('/en/user/logout');

        $this->assertSame(302, $response['status'], 'GET /en/user/logout should redirect');
        $this->assertStringContainsString('/user/login', $response['redirect']);
    }

    public function testUserDirectoryRequiresLogin(): void
    {
        $this->assertRequiresLogin('/en/users');
    }
}
