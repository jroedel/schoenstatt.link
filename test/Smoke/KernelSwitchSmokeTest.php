<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use App\Http\KernelCanary;

use function str_contains;

//the smoke suite does not autoload by default; KernelCanary is the one definition of
//the cookie name this test must not restate
require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The administrator's kernel toggle: /kernel-switch.
 *
 * A menu item that sets or clears the canary cookie, so an admin can put *themselves*
 * on the Symfony kernel from a browser and put themselves back. It is a laminas route
 * on purpose — the Symfony kernel bridges every unported path back to laminas, so one
 * action switches the canary both ways, where a ported route could only ever switch it
 * off.
 *
 * What is worth asserting, given the capsule serves Symfony for everything:
 *
 * - **it is administrators only.** The canary is not a privilege (both front
 *   controllers consult the same ACL) but the toggle is still guarded, and a guard that
 *   silently admitted everyone would look identical to one that works.
 * - **it actually sets and clears the cookie**, with the exact name the `SetEnvIf` in
 *   public/.htaccess matches on. A typo there is invisible: the page redirects, the
 *   flash appears, and nothing happens.
 * - **it refuses to redirect off-site.** The return URL comes from the Referer, which
 *   is attacker-controlled, and this route is one administrators are told to click.
 *
 * The unconsented branch is **not** reachable from here and is covered by
 * test/Integration/KernelCanaryTest instead: without consent there is no session cookie,
 * so the sign-in this route's guard requires cannot complete in the first place. That is
 * itself the proof that an unconsented admin could never have used the toggle — which is
 * why the action tells them rather than failing silently.
 */
class KernelSwitchSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    private const PATH = '/en/kernel-switch';

    protected function emailPrefix(): string
    {
        return 'kernel-switch-smoke';
    }

    public function testAnonymousVisitorsAreRedirectedToSignIn(): void
    {
        $response = $this->get(self::PATH);

        self::assertSame(302, $response['status']);
        self::assertStringEndsWith('/en/user/login?redirect=' . self::PATH, $response['redirect']);
    }

    /** sch_moderator is a real role, and not the one this route wants. */
    public function testASignedInNonAdministratorIsForbidden(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        self::assertSame(403, $this->get(self::PATH, false, $jar)['status']);
    }

    /**
     * The whole point: an administrator's click puts the canary cookie on their jar,
     * and a second click takes it off again.
     *
     * The cookie jar is the assertion. A flash message would prove only that the action
     * ran; what has to be true is that the browser ends up holding — or not holding —
     * the exact cookie the Apache directive matches on.
     */
    public function testAnAdministratorCanSwitchTheKernelOnAndOffAgain(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_administrator']);

        self::assertFalse($this->jarHasCanary($jar), 'the canary should start absent');

        $on = $this->get(self::PATH, false, $jar);
        self::assertSame(302, $on['status'], 'the toggle redirects back to where you were');
        self::assertTrue(
            $this->jarHasCanary($jar),
            'no ' . KernelCanary::COOKIE . ' cookie after the first click — if this name ever stops '
            . 'matching the SetEnvIf in public/.htaccess, the toggle redirects and does nothing'
        );

        $off = $this->get(self::PATH, false, $jar);
        self::assertSame(302, $off['status']);
        self::assertFalse($this->jarHasCanary($jar), 'the second click should clear it again');
    }

    /**
     * The Referer decides where the admin lands, so it must be checked. An off-site
     * Referer falls back to the home page rather than being followed.
     */
    public function testAnOffsiteRefererIsNotFollowed(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_administrator']);

        $response = $this->request(
            'GET',
            self::PATH,
            ['Referer: https://evil.example.com/phish'],
            false,
            $jar
        );

        self::assertSame(302, $response['status']);
        self::assertStringNotContainsString(
            'evil.example.com',
            $response['redirect'],
            'the toggle followed an off-site Referer — that is an open redirect on a route '
            . 'administrators are told to click'
        );
    }

    private function jarHasCanary(string $jar): bool
    {
        return str_contains((string) @file_get_contents($jar), KernelCanary::COOKIE);
    }

    protected function tearDown(): void
    {
        $this->purgeMail();
        $this->purgeAccounts();
        parent::tearDown();
    }
}
