<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The two SionModel maintenance pages ported 2026-08-07, /sm/phpinfo and
 * /sm/data-problems, and the three access outcomes each.
 *
 * AdminAuthorizationSmokeTest established that App\Authorization\RouteGuard works at
 * all, on a route admitting 4 effective roles of 43. These two are the sharper cases:
 *
 *   route/sion-model/phpinfo        sch_administrator          — **1** role, no descendants
 *   route/sion-model/data-problems  sch_general_moderator + 1  — 2 roles
 *
 * `sch_administrator` having no descendants is what makes phpinfo worth testing
 * separately: on every route ported before this one, a wrongly-permissive guard could
 * still have looked correct because the admitted set was large. Here a signed-in user
 * holding *any other* role must be refused, and there are 42 of them.
 *
 * The third outcome — signed in *with* the role — is the one a status-code-only test
 * cannot fake, so each page asserts something only its real body contains.
 */
class MaintenancePagesAuthorizationSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    private const EMAIL_PREFIX = 'maintenance-authz';

    /** Granted to prove refusal: a real role that is not the one the page wants. */
    private const WRONG_ROLE = 'sch_moderator';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    /**
     * path, the role that opens it, and a string only the rendered page contains.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function pages(): array
    {
        return [
            'phpinfo'       => ['/en/sm/phpinfo', 'sch_administrator', 'PHP Version'],
            'data-problems' => ['/en/sm/data-problems', 'sch_general_moderator', 'Total problems'],
        ];
    }

    // ------------------------------------------------------ branch 1: anonymous

    /** What JUser\View\RedirectionStrategy sends, reproduced by App\Authorization\Denial. */
    #[DataProvider('pages')]
    public function testAnonymousVisitorIsRedirectedToSignIn(string $path, string $role, string $marker): void
    {
        $response = $this->get($path);

        self::assertSame(302, $response['status']);
        self::assertStringEndsWith('/en/user/login?redirect=' . $path, $response['redirect']);
        self::assertStringNotContainsString($marker, $response['body'], 'the refusal leaked the page');
    }

    // ------------------------------ branch 2: signed in, holding the wrong role

    /**
     * 403 rather than another redirect: signing in again would change nothing about
     * this visitor's roles, so bouncing them to the sign-in page would loop them. The
     * identity, not the failed check, picks the branch.
     */
    #[DataProvider('pages')]
    public function testASignedInVisitorWithoutTheRoleGetsForbidden(
        string $path,
        string $role,
        string $marker
    ): void {
        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::WRONG_ROLE]);

        $response = $this->get($path, false, $jar);

        self::assertSame(403, $response['status']);
        self::assertStringNotContainsString($marker, $response['body'], 'a 403 that still served the page');
        self::assertStringContainsString('403', $response['body'], 'expected the Twig 403 template');
    }

    // ---------------------------------- branch 3: signed in, holding the role

    /**
     * The outcome a status-code-only test cannot distinguish from a guard that never
     * ran: the page has to actually render, with its own content.
     */
    #[DataProvider('pages')]
    public function testASignedInVisitorWithTheRoleGetsThePage(string $path, string $role, string $marker): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, [$role]);

        $response = $this->get($path, false, $jar);

        self::assertSame(200, $response['status']);
        self::assertStringContainsString($marker, $response['body']);
    }

    /**
     * And Symfony served it, not the catch-all. Without this the three outcomes above
     * would pass just as well against the laminas route, which has its own working
     * guard — the whole point is that the *ported* route is the one being measured.
     *
     * laminas sends `Set-Cookie: slm_locale=en_US` on every response and a ported route
     * never does.
     */
    #[DataProvider('pages')]
    public function testThePageIsServedBySymfony(string $path, string $role, string $marker): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, [$role]);

        $response = $this->request('GET', $path, [], false, $jar);

        self::assertSame(200, $response['status']);
        self::assertStringNotContainsString(
            'slm_locale=en_US',
            $response['headers']['set-cookie'] ?? '',
            'a slm_locale cookie means laminas-mvc served this, so the ported guard was never exercised'
        );
    }

    /**
     * phpinfo's guard is the tightest on the site — one role, no descendants — so the
     * neighbouring page's role must not open it. This is the assertion that a guard
     * reading the wrong resource would fail.
     */
    public function testTheDataProblemsRoleDoesNotOpenPhpinfo(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_general_moderator']);

        self::assertSame(200, $this->get('/en/sm/data-problems', false, $jar)['status']);
        self::assertSame(403, $this->get('/en/sm/phpinfo', false, $jar)['status']);
    }

    /** The unprefixed form redirects the way SlmLocale does, before the guard is reached. */
    #[DataProvider('pages')]
    public function testTheUnprefixedFormRedirectsToThePrefixedOne(
        string $path,
        string $role,
        string $marker
    ): void {
        $jar = $this->newCookieJar();
        $this->signIn($jar, [$role]);

        $unprefixed = (string) preg_replace('#^/en#', '', $path);
        $response   = $this->get($unprefixed, false, $jar);

        self::assertSame(302, $response['status']);
        self::assertStringEndsWith($path, $response['redirect']);
    }

    protected function tearDown(): void
    {
        $this->purgeMail();
        $this->purgeAccounts();
        parent::tearDown();
    }
}
