<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * JUser module: the passwordless (email magic-link) entry points and the
 * user directory.
 *
 * There is no password anywhere any more: /user/login and /user/register both
 * render the same one-field email form and differ only in wording.
 *
 * Every auth route depends on the GDPR consent cookie. Without it the app
 * refuses to hand out cookies at all, so instead of a form the visitor gets an
 * explainer page — note its heading is "Sign In" (capital I) while the real
 * form's heading is "Sign in", which is what tells the two pages apart here.
 *
 * The end-to-end flow (mail, token, session) lives in AuthSmokeTest.
 */
class UserSmokeTest extends SmokeTestCase
{
    public function testLoginPageWithoutConsentRendersTheCookieExplainer(): void
    {
        $response = $this->assertRendersOk('/en/user/login');

        $this->assertStringContainsString('<h1>Sign In</h1>', $response['body'], 'expected the explainer page');
        $this->assertStringNotContainsString(
            'name="email"',
            $response['body'],
            'no sign-in form should be offered while cookies are refused'
        );
    }

    public function testLoginPageWithConsentRendersTheEmailForm(): void
    {
        $response = $this->get('/en/user/login', true, $this->newCookieJar());

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('<h1>Sign in</h1>', $response['body']);
        $this->assertStringContainsString('type="email"', $response['body'], 'the form asks for an email address');
        $this->assertStringContainsString('name="email"', $response['body']);
        $this->assertStringContainsString('name="security"', $response['body'], 'the form carries a CSRF token');
        $this->assertStringNotContainsString(
            'type="password"',
            $response['body'],
            'passwords are gone; nothing may ask for one'
        );
    }

    public function testRegisterPageWithoutConsentRendersTheCookieExplainer(): void
    {
        $response = $this->assertRendersOk('/en/user/register');

        $this->assertStringContainsString('<h1>Sign In</h1>', $response['body']);
    }

    /**
     * Registration is just sign-in with different copy: same email form, and
     * an unknown address becomes an account the first time a link is redeemed.
     */
    public function testRegisterPageWithConsentRendersTheEmailForm(): void
    {
        $response = $this->get('/en/user/register', true, $this->newCookieJar());

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('<h1>Register</h1>', $response['body']);
        $this->assertStringContainsString('name="email"', $response['body']);
        $this->assertStringContainsString('name="security"', $response['body']);
        $this->assertStringNotContainsString('type="password"', $response['body']);
    }

    /**
     * /user is not a page, it only routes: anonymous visitors go to the form.
     */
    public function testUserRootRedirectsToLogin(): void
    {
        $response = $this->get('/en/user');

        $this->assertSame(302, $response['status'], 'GET /en/user should redirect');
        $this->assertStringContainsString('/user/login', $response['redirect']);
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
