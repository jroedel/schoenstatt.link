<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * JUser module: the passwordless (email magic-link) entry points and the
 * user directory.
 *
 * There is no password anywhere any more, and since 2026-08-20 there is no
 * /user/register either: registering and signing in are one request, so the second
 * route was a duplicate URL for the same one-field email form. The three tests that
 * pinned it and its two password-era neighbours (/users/thanks, /users/verify-email)
 * are replaced by testTheRetiredPasswordEraRoutesAreGone below.
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
    use MagicLinkSignIn;

    /** Distinct from every other user of the trait, or one tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'user-smoke-';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

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

    /**
     * The three password-era routes retired on 2026-08-20, asserted gone rather than
     * simply deleted from this file — a route that answers 404 and a route nobody tests
     * look identical from here, and only one of them stays that way.
     *
     * - `/user/register` was a second URL for the sign-in form with different wording.
     * - `/users/verify-email` forwarded a token to `zfcuser/verify`; redeeming a link is
     *   what verifies an address, so there was nothing left to confirm separately.
     * - `/users/thanks` had an empty action and a template still promising a
     *   confirmation email.
     *
     * 404 and not 302: these are gone, not moved. A visitor with a five-year-old
     * bookmark should be told the page does not exist rather than be silently landed
     * somewhere else.
     */
    #[DataProvider('retiredRoutes')]
    public function testTheRetiredPasswordEraRoutesAreGone(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(404, $response['status'], "$path should be gone");
    }

    /** @return iterable<string, array{string}> */
    public static function retiredRoutes(): iterable
    {
        yield 'register'     => ['/en/user/register'];
        yield 'verify-email' => ['/en/users/verify-email'];
        yield 'thanks'       => ['/en/users/thanks'];
    }

    /**
     * One button in the navbar, not two.
     *
     * The Register button lived beside Sign in on every page of the site; retiring the
     * route without retiring the button would have left a link to a 404 in the site
     * chrome. Checked on two pages rather than one because the chrome is assembled per
     * layout and this is exactly the kind of change that lands in one place — see the
     * provider on why both are Symfony-served now and where the laminas half went.
     */
    #[DataProvider('pagesFromBothFrontControllers')]
    public function testTheNavbarOffersSignInAndNotRegister(string $path): void
    {
        $response = $this->assertRendersOk($path);

        $this->assertStringContainsString('/en/user/login', $response['body'], 'Sign in should still be offered');
        $this->assertStringNotContainsString(
            '/en/user/register',
            $response['body'],
            'the Register button should be gone: the route it pointed at is a 404'
        );
    }

    /**
     * Both entries must be pages an anonymous visitor really gets a 200 from, and that is
     * not a detail: `assertRendersOk()` follows redirects, so a guarded page would land on
     * the sign-in form — which contains `/en/user/login` and no Register button, and
     * therefore satisfies both assertions while testing nothing. `/en/movement` was the
     * first choice here and is restricted to moderators, so it did exactly that.
     *
     * ## The name is now aspirational, and saying so is the point
     *
     * The second entry was `/en/user/verify`, chosen because
     * `Application\View\GdprStrategy` swapped an unconsented request for the cookie
     * explainer, which rendered through LegacyBridge from
     * `module/Application/view/layout/layout.phtml`. Batch 13 ported that route
     * (2026-08-21), so **both entries are Symfony-served now** and this provider stopped
     * covering the laminas layout without failing — the silent kind of rot.
     *
     * It cannot be fixed by picking another path, because there is no
     * anonymous-reachable unported HTML page left: of 118 guarded laminas routes 105 are
     * shadowed by a Symfony route, and of the remaining 13 the only one an anonymous
     * visitor may reach renders no layout. Signing in to reach one would test the
     * *signed-in* navbar, which is a different assertion — the Register button question is
     * about what an anonymous visitor is offered.
     *
     * So the two entries are two ported pages with different controllers, which is what is
     * left to vary, and the laminas half of this check now lives in
     * `test/Smoke/ServingNoteSmokeTest::testAnUnportedRouteReportsTheBridge` — the one
     * place that does sign in to observe the bridge at all.
     *
     * @return iterable<string, array{string}>
     */
    public static function pagesFromBothFrontControllers(): iterable
    {
        //both ported: templates/layout.html.twig, two different controllers
        yield 'symfony html route' => ['/en/shrines'];
        yield 'symfony static page' => ['/en/acknowledgements'];
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

    // ------------------------------------------------ the return trip in the link itself

    /**
     * The destination travels in the emailed link, not only in the session.
     *
     * The session was the only channel until 2026-08-20, and it works exactly when the
     * link is opened in the browser that asked for it. The ordinary case is asking on a
     * desktop and clicking on a phone, where there is no session to read and the visitor
     * silently landed on the welcome page instead of where they were going.
     */
    public function testTheEmailedLinkCarriesTheDestination(): void
    {
        $jar   = $this->newCookieJar();
        $email = $this->uniqueEmail();

        $this->requestSignInLink($jar, $email, '/en/user/login?redirect=/en/shrines');
        $link = $this->extractVerifyUrl($this->awaitMessageFor($email)['Text']);

        $this->assertStringContainsString(
            'redirect=',
            $link,
            'the sign-in link should carry the destination, so it survives being opened elsewhere'
        );
        $this->assertStringContainsString('/en/shrines', urldecode($link));
    }

    /**
     * The case the session cannot serve: the link opened somewhere else entirely.
     *
     * Two cookie jars, which is what "asked on the desktop, clicked on the phone" is when
     * written down. The second has never seen this site, so there is no session container
     * to read the destination out of — and before the link carried it, this landed on the
     * welcome page with no indication that anything had been lost.
     */
    public function testTheLinkWorksInABrowserThatNeverAskedForIt(): void
    {
        $asked   = $this->newCookieJar();
        $clicked = $this->newCookieJar();
        $email   = $this->uniqueEmail();

        //A *public* destination, deliberately: this test is about which channel carries
        //the value, and a restricted one would be refused by the check in verifyAction and
        //land on the welcome page for a completely different reason.
        $this->requestSignInLink($asked, $email, '/en/user/login?redirect=/en/shrines');
        $verifyPath = $this->toLocalPath($this->extractVerifyUrl($this->awaitMessageFor($email)['Text']));

        $verify = $this->get($verifyPath, false, $clicked);

        $this->assertSame(302, $verify['status'], 'redeeming the link should authenticate');
        $this->assertStringEndsWith(
            '/en/shrines',
            (string) $verify['redirect'],
            'the destination should come from the link, since this browser has no session to hold it'
        );
    }

    // ------------------------------------------------ and the destinations it refuses

    /**
     * A hostile `?redirect=` is dropped, and the three shapes are tested separately
     * because `LoginController::validRedirect()` refuses them with three different rules.
     *
     * ## Why this is worth its own test rather than being obvious
     *
     * The destination is attacker-controllable by construction: it arrives as a query
     * parameter on a public page, it is put into a form, it travels in an email, and it
     * ends as an argument to `$this->redirect()->toUrl()`. That is the exact shape of an
     * open redirect, and an open redirect on the *sign-in* URL is the good one to have —
     * the link is from the site, it is the page a visitor expects to be asked for
     * credentials on, and the hop happens after a successful sign-in when nobody is
     * suspicious any more.
     *
     * Nothing tested any of the three rules until 2026-08-21. A dozen suites assert that
     * the route guard *emits* `?redirect=…` and two assert the happy round trip, so a
     * reimplementation that accepted anything would have passed the whole suite.
     *
     * The three rules, and the reason each one is not redundant:
     *
     *  - **must start with `/`** — refuses `https://host/`, the obvious form;
     *  - **must not start with `//`** — refuses `//host/`, which *does* start with a
     *    slash and is a protocol-relative URL, i.e. an absolute one. This is the rule a
     *    reimplementation forgets, because `str_starts_with($url, '/')` reads like it
     *    already covers it;
     *  - **must match a route** — refuses `/en/anything-at-all`, which is neither of the
     *    above and would otherwise let the parameter address something the router does
     *    not serve.
     *
     * ## Asserted on the form, not on the redirect
     *
     * The GET is where `validRedirect()` runs first, and a refused value produces an empty
     * hidden field. Driving it all the way through a sign-in would test the same call
     * through more machinery, and would need a live token per case; the field is the
     * decision, rendered.
     */
    #[DataProvider('hostileRedirectProvider')]
    public function testAHostileRedirectIsNotCarriedIntoTheForm(string $redirect, string $why): void
    {
        $response = $this->get('/en/user/login?redirect=' . urlencode($redirect), false, $this->newCookieJar());

        $this->assertSame(200, $response['status'], 'the sign-in form should still render');
        $this->assertSame(
            '',
            $this->extractRedirectField($response['body']),
            $why . ' — validRedirect() must drop it, or the sign-in page is an open redirect'
        );
    }

    /** @return array<string, array{string, string}> */
    public static function hostileRedirectProvider(): array
    {
        return [
            'protocol-relative' => ['//evil.example.com/', 'a protocol-relative URL is an absolute one'],
            'absolute https'    => ['https://evil.example.com/', 'an absolute URL is not a local path'],
            'absolute http'     => ['http://evil.example.com/', 'an absolute URL is not a local path'],
            'backslash host'    => ['/\\evil.example.com', 'some agents read a backslash as a slash'],
            'matches no route'  => ['/en/no-such-page-exists', 'a local path still has to be a route'],
        ];
    }

    /**
     * The counterpart, so the test above cannot pass by refusing everything.
     *
     * A refusal-only assertion is satisfied by `return null;`, which would break sign-in
     * for every guarded page on the site and fail nothing here.
     */
    public function testALegitimateRedirectIsCarriedIntoTheForm(): void
    {
        $response = $this->get('/en/user/login?redirect=/en/shrines', false, $this->newCookieJar());

        $this->assertSame('/en/shrines', $this->extractRedirectField($response['body']));
    }

    /**
     * Signing in with a destination the account may not reach.
     *
     * `/en/users` is administrator-only and registration grants the four default roles,
     * so this is the ordinary shape of the problem: the link works, the visitor is
     * genuinely signed in, and the page they were heading for is still not theirs.
     *
     * Before 2026-08-20 they were simply sent to `/en/users` and met the guard, which
     * answers a bare 403 — correct, and unhelpful in this one situation, because nothing
     * on that page says the sign-in itself succeeded. Now the destination is checked
     * while there is still somewhere to say it, and they land on the post-login page with
     * **both** facts: signed in, and not allowed there.
     */
    public function testSigningInTowardsARefusedPageLandsHomeSayingBoth(): void
    {
        $jar = $this->newCookieJar();

        $denied = $this->get('/en/users', false, $jar);
        $this->assertSame(302, $denied['status'], 'the user directory should be guarded');

        $loginPath = (string) parse_url($denied['redirect'], PHP_URL_PATH)
            . '?' . (string) parse_url($denied['redirect'], PHP_URL_QUERY);
        $this->signIn($jar, [], $loginPath);

        $this->assertNotNull($this->lastSignInRedirect);
        $this->assertStringEndsWith(
            '/en/',
            (string) $this->lastSignInRedirect,
            'a refused destination should land on the post-login page, not on a 403'
        );

        //the two messages are read one request later, which is what a flash is
        $landed = $this->get('/en/', false, $jar);
        $this->assertSame(200, $landed['status']);
        $this->assertStringContainsString(
            'You are signed in.',
            $landed['body'],
            'the good half: they must not think the link failed'
        );
        $this->assertStringContainsString(
            'does not have access to',
            $landed['body'],
            'the other half: why they are here and not where they asked to be'
        );
        $this->assertStringContainsString(
            '/en/users',
            $landed['body'],
            'the message should name the page that was refused'
        );
    }
}
