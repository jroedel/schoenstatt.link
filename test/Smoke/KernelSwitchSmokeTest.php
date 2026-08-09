<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use App\Http\KernelCanary;

use function count;
use function explode;
use function trim;

//the smoke suite does not autoload by default; KernelCanary is the one definition of
//the cookie name this test must not restate
require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The administrator's kernel toggle: /kernel-switch.
 *
 * A menu item that moves an admin between the site default and the other front
 * controller, and back. It is a laminas route on purpose — the Symfony kernel bridges
 * every unported path back to laminas, so one action switches in both directions, where
 * a ported route could only ever switch away from Symfony.
 *
 * What is worth asserting, given the capsule serves Symfony for everything:
 *
 * - **it is administrators only.** The canary is not a privilege (both front
 *   controllers consult the same ACL) but the toggle is still guarded, and a guard that
 *   silently admitted everyone would look identical to one that works.
 * - **it sets the value for the kernel the visitor is *not* on**, and clears it again.
 *   The cookie has three states — absent, `1` for App\Kernel, `0` for laminas — and the
 *   direction is read from `SYMFONY_KERNEL`, not assumed, which is what lets the same
 *   action keep working when production's default flips. So the value asserted here is
 *   derived the same way: the capsule serves Symfony, therefore the toggle must offer
 *   laminas. A test that accepted either value would pass against an action that always
 *   handed out the kernel you were already on.
 * - **the exact cookie name and values the `SetEnvIf` lines in public/.htaccess match
 *   on.** A typo there is invisible: the page redirects, the flash appears, and nothing
 *   happens.
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
     * The whole point: an administrator's click puts the override cookie on their jar
     * with the value for the *other* kernel, and a second click takes it off again.
     *
     * The cookie jar is the assertion. A flash message would prove only that the action
     * ran; what has to be true is that the browser ends up holding — or not holding — the
     * exact cookie and value one of the Apache directives matches on.
     */
    public function testAnAdministratorCanSwitchToTheOtherKernelAndBackAgain(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_administrator']);

        self::assertFalse($this->jarHasCanary($jar), 'the override should start absent');

        $expected = $this->symfonyServesThisCapsule()
            ? KernelCanary::FORCE_LAMINAS
            : KernelCanary::FORCE_SYMFONY;

        $away = $this->get(self::PATH, false, $jar);
        self::assertSame(302, $away['status'], 'the toggle redirects back to where you were');
        self::assertTrue(
            $this->jarHasCanary($jar),
            'no ' . KernelCanary::COOKIE . ' cookie after the first click — if this name ever stops '
            . 'matching the SetEnvIf lines in public/.htaccess, the toggle redirects and does nothing'
        );
        self::assertSame(
            $expected,
            $this->canaryValue($jar),
            'the toggle handed out the kernel this visitor is already on, so clicking it changes '
            . 'nothing while reporting success'
        );

        $back = $this->get(self::PATH, false, $jar);
        self::assertSame(302, $back['status']);
        self::assertFalse(
            $this->jarHasCanary($jar),
            'the second click should clear the override and return the admin to the site default'
        );
    }

    /**
     * Which front controller this capsule serves, asked rather than assumed: /_health
     * exists only in the Symfony route table.
     *
     * docker/apache-vhost.conf sets SYMFONY_KERNEL=1 today, so this is Symfony in
     * practice — but the vhost is a local decision that a bisect or an A/B may have
     * changed, and reading it from the running app is what keeps the direction assertion
     * above honest either way.
     */
    private function symfonyServesThisCapsule(): bool
    {
        return 200 === $this->get('/_health')['status'];
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
        return null !== $this->canaryValue($jar);
    }

    /**
     * The value curl's jar ended up holding, or null if the cookie is not there.
     *
     * A Netscape cookie jar is tab-separated with the value last, and curl drops an
     * expired cookie from the file rather than writing it with a past date — which is why
     * "cleared" reads here as absent.
     */
    private function canaryValue(string $jar): ?string
    {
        foreach (explode("\n", (string) @file_get_contents($jar)) as $line) {
            $fields = explode("\t", trim($line));
            if (count($fields) >= 7 && KernelCanary::COOKIE === $fields[count($fields) - 2]) {
                return $fields[count($fields) - 1];
            }
        }

        return null;
    }

    protected function tearDown(): void
    {
        $this->purgeMail();
        $this->purgeAccounts();
        parent::tearDown();
    }
}
