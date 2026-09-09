<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use function preg_match_all;

/**
 * The authorization bridge, measured end to end on the first restricted route that
 * crossed it.
 *
 * `/admin` is guarded `['sch_moderator', 'translator']`, and the Symfony route
 * declares `RouteAccess::guardedBy('route/admin')` — the same ACL resource the
 * laminas guard keys on. There is no other way to test this than over HTTP: the
 * decision needs a session, a session needs the real magic-link flow, and a CLI
 * process cannot even build laminas-session once PHPUnit has printed a dot.
 *
 * Three outcomes, which are the whole contract:
 *
 * | who | what |
 * |---|---|
 * | anonymous | 302 `/en/user/login?redirect=/en/admin` |
 * | signed in, no moderator role | 403, the `error/403` page |
 * | signed in, holds `sch_moderator` | 200, the admin links their roles allow |
 *
 * All three were measured against **laminas** first, on the same URL before it was
 * ported (comment the route out of config/symfony/routes.php and re-run this file's
 * probe): 302 to `/en/user/login?redirect=/en/admin`, then 403 with
 * "You are not authorized to access admin.", then 200 with the same ten links in the
 * same order. The assertions below are that measurement, not a design — with the
 * eleventh link, the kernel toggle, added in 2026-08-11 when it left the navbar, and
 * 'Literature maintenance' removed 2026-08-17, which brings the count back to ten.
 *
 * The signed-in-but-unprivileged case is not contrived: registration assigns
 * lib_user, pub_user, sch_user and bib_user by itself, and none of them is beneath
 * sch_moderator in the role tree. So "signed in" really is a different question from
 * "allowed", which is precisely the distinction a single-branch guard would lose.
 */
class AdminAuthorizationSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from AuthSmokeTest's, or one class's tearDown deletes the other's accounts mid-run. */
    private const EMAIL_PREFIX = 'authz-smoke-';

    /** A role that gets through `route/admin`. `translator` would do as well. */
    private const MODERATOR_ROLE = 'sch_moderator';

    /**
     * The whole list, as the laminas rendering produced it for an account holding
     * every role: route order, hrefs and labels. Reproducing this list *is* the body
     * of the ported page, so it is the thing a copy could get wrong.
     *
     * The two badge counters are deliberately absent — they are live database
     * numbers (6842 outstanding translations, 30 data problems when this was written)
     * and pinning them would make the test fail on a data change rather than a code
     * change. That the right *two* rows carry a badge is asserted separately.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const EXPECTED_LINKS = [
        ['/en/persons/create', 'Add new person'],
        ['/en/associations/create', 'Add new association'],
        ['/en/assignments/create', 'Add new assignment'],
        ['/en/roles/create', 'Add new role'],
        ['/en/sm/view-changes', 'View Changes'],
        ['/en/admin/import-father', 'Import Schoenstatt Father'],
        ['/en/users', 'User Management'],
        ['/en/admin/translations', 'Manage Translations'],
        ['/en/sm/data-problems', 'Data problems'],
        //'Literature maintenance' was the eleventh entry until 2026-08-17, when the 2020
        //data-source migration behind it was measured as finished and retired.
        //'Switch kernel' (/en/kernel-switch) was the last entry until 2026-09-08, when the
        //SYMFONY_KERNEL canary was retired and its toggle deleted with it.
    ];

    /**
     * What `sch_moderator` alone sees — the first four and nothing else, because the
     * other six name resources no descendant of sch_moderator is allowed
     * (`route/juser` wants `administrator`, `route/jtranslate` wants
     * `sch_general_moderator` or `translator`, `route/kernel-switch` wants
     * `sch_administrator`, and so on: docs/acl-baseline.json).
     *
     * Worth its own assertion rather than being a smaller version of the same one.
     * Getting through the route guard and seeing the whole page are two different
     * permissions, and this page is where they differ: the guard admits a moderator,
     * and the *template* then filters every link through the ACL individually. A port
     * that dropped that per-item check would still pass every status-code assertion in
     * this file.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const MODERATOR_LINKS = [
        ['/en/persons/create', 'Add new person'],
        ['/en/associations/create', 'Add new association'],
        ['/en/assignments/create', 'Add new assignment'],
        ['/en/roles/create', 'Add new role'],
    ];

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    // ----------------------------------------------------------- branch 1: anonymous

    /**
     * Exactly what JUser\View\RedirectionStrategy sends: 302 to the sign-in page
     * with the wanted page in `?redirect=`, locale prefix included.
     *
     * The prefix is in there because laminas re-assembles the matched route and
     * SlmLocale has hooked assembly; the ported guard uses the request's own path,
     * which is the same string and cannot throw the way a re-assembly can.
     */
    public function testAnonymousVisitorIsRedirectedToSignInWithAReturnPath(): void
    {
        $response = $this->get('/en/admin');

        $this->assertSame(302, $response['status']);
        $this->assertStringEndsWith('/en/user/login?redirect=/en/admin', $response['redirect']);
    }

    /**
     * And the refusal itself must not leak the page. A guard that set the status code
     * but let the body through would pass a status assertion and fail at its job.
     */
    public function testTheRedirectCarriesNoneOfThePage(): void
    {
        $body = $this->get('/en/admin')['body'];

        $this->assertStringNotContainsString('Import Schoenstatt Father', $body);
        //A label and an href, because a redirect that leaked only one of the two would
        //still pass the other. This was '/en/admin/literature-maintenance' until
        //2026-08-17; that link no longer exists on the page, so asserting its absence
        //had stopped proving anything.
        $this->assertStringNotContainsString('/en/admin/import-father', $body);
    }

    /**
     * Reading the session to answer "who is this" starts one, and PHP writes the
     * cookie into the SAPI header list before any listener can object. On an
     * unconsented visitor that cookie has to leave again — which is what
     * App\Http\GdprCookieListener does, and a denial response is a response like any
     * other. Asserted through a real jar, because an expired cookie is still a
     * Set-Cookie header.
     */
    public function testADeniedUnconsentedVisitorKeepsNoSessionCookie(): void
    {
        $jar = $this->newCookieJar(false);
        $this->get('/en/admin', false, $jar);

        $this->assertStringNotContainsString('PHPSESSID', (string) file_get_contents($jar));
    }

    // ------------------------------------------------- branch 2: signed in, no role

    /**
     * The branch a single-check guard gets wrong. Redirecting this visitor to the
     * sign-in page would loop them: they are signed in, and signing in again changes
     * nothing about their roles.
     *
     * The wording is bjy-authorize's, reproduced in templates/error/403.html.twig so
     * that retiring the abandoned package cannot take the refusal page with it.
     */
    public function testSignedInVisitorWithoutTheRoleGetsForbidden(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get('/en/admin', false, $jar);

        $this->assertSame(403, $response['status'], 'a signed-in visitor must not be redirected to sign in');
        $this->assertStringContainsString('<h1>403 Forbidden</h1>', $response['body']);
        $this->assertStringContainsString('You are not authorized to access admin.', $response['body']);
        $this->assertStringNotContainsString('Import Schoenstatt Father', $response['body'], 'the page leaked');
    }

    /**
     * The 403 renders inside the shared layout, which is where laminas puts it too —
     * BjyAuthorize\View\UnauthorizedStrategy adds its ViewModel as a *child* of the
     * layout's. A bare `<h1>` with no chrome would mean the Twig layout was bypassed.
     */
    public function testTheForbiddenPageWearsTheSiteChrome(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $body = $this->get('/en/admin', false, $jar)['body'];

        $this->assertStringContainsString('<title>403 Forbidden - Schoenstatt Link</title>', $body);
        $this->assertStringContainsString('class="navbar-brand"', $body);
        $this->assertStringContainsString('>Logout</a>', $body, 'the visitor is signed in, so the navbar says so');
    }

    // ---------------------------------------------- branch 3: signed in, holds role

    /**
     * The page itself, and the reason the whole exercise is worth anything: the guard
     * lets the right visitor through, and what they get is the page laminas rendered.
     */
    public function testAnAccountHoldingEveryRoleSeesTheWholePage(): void
    {
        $body = $this->fullyPrivilegedPage();

        $this->assertSame(
            self::EXPECTED_LINKS,
            $this->adminLinks($body),
            'the ported page must list the same links, in the same order, as the .phtml did'
        );
    }

    /** Getting through the guard is not the same as seeing everything behind it. */
    public function testAModeratorSeesOnlyTheLinksTheAclAllowsThem(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::MODERATOR_ROLE]);

        $response = $this->get('/en/admin', false, $jar);
        $this->assertSame(200, $response['status'], 'sch_moderator gets through route/admin');

        $this->assertSame(
            self::MODERATOR_LINKS,
            $this->adminLinks($response['body']),
            'the template filters each link through the ACL, so a moderator sees four of the eleven'
        );
    }

    /** The two counters, without pinning their live values. */
    public function testTheTwoBadgeCountersAreRenderedOnTheirOwnRows(): void
    {
        $body = $this->fullyPrivilegedPage();

        $this->assertMatchesRegularExpression(
            '#<a href="/en/admin/translations">Manage Translations</a> <span class="badge">\d+</span>#',
            $body
        );
        $this->assertMatchesRegularExpression(
            '#<a href="/en/sm/data-problems">Data problems</a> <span class="badge">\d+</span>#',
            $body
        );
        $this->assertSame(2, substr_count($body, '<span class="badge">'), 'exactly two rows carry a badge');
    }

    /**
     * Symfony served it, not the catch-all. laminas sends `Set-Cookie:
     * slm_locale=en_US` on every response and a ported route never does, which is the
     * discriminator ShrinesSymfonySmokeTest established. Without this the test would
     * pass just as well against the un-ported page and prove nothing about the guard.
     */
    public function testThePageIsServedBySymfonyAndNotBridgedToLaminas(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, [self::MODERATOR_ROLE]);

        $response = $this->request('GET', '/en/admin', [], false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringNotContainsString(
            'slm_locale=en_US',
            $response['headers']['set-cookie'] ?? '',
            'a slm_locale cookie means laminas served this, so the guard under test never ran'
        );
        $this->assertStringContainsString('<a href="/en/admin" aria-current="page">Admin</a>', $response['body']);
    }

    /**
     * A role granted mid-session is in force on the very next request — no re-sign-in.
     *
     * This corrects a belief written down in tools/form-regression.php, which elevates
     * *before* redeeming the magic link because "BjyAuthorize reads the roles when the
     * session identity is established". It does not:
     * `App\Acl\IdentityRoles` selects
     * from `user_role_linker` on every request, and `bjyauthorize.cache_enabled` is
     * false, so nothing about a role is cached in the session at all. The first attempt
     * here 403s and the second 200s with the same cookie jar, which is that fact stated
     * as an assertion — and, incidentally, proof that the guard is consulting the live
     * ACL rather than something it computed once.
     */
    public function testARoleGrantedMidSessionTakesEffectOnTheNextRequest(): void
    {
        $jar = $this->newCookieJar();
        $email = $this->signIn($jar);
        $this->assertSame(403, $this->get('/en/admin', false, $jar)['status'], 'no moderator role yet');

        $this->grantRoles($email, [self::MODERATOR_ROLE]);

        $this->assertSame(
            200,
            $this->get('/en/admin', false, $jar)['status'],
            'the same session should see the new role, because the identity provider re-reads it'
        );
    }

    // -------------------------------------- the four earlier ports, now behind a check

    /**
     * Every previously ported route now passes through App\Authorization\RouteGuard,
     * so each has to still answer as it did. `/_health` and the two maintenance
     * endpoints declare themselves open; `shrines` is checked against `route/shrines`,
     * which is public.
     */
    public function testTheEarlierPortsStillAnswerAsTheyDid(): void
    {
        $health = $this->get('/_health');
        $this->assertSame(200, $health['status'], '/_health');

        //no key, so 401 from the controller's own gate — not 302, and not 403 from the
        //new guard, which must not have opinions about this endpoint
        foreach (['/en/sm/cache-status', '/en/sm/clear-persistent-cache'] as $path) {
            $response = $this->get($path);
            $this->assertSame(401, $response['status'], $path);
            $this->assertStringContainsString('maintenance key', $response['body'], $path);
        }

        $shrines = $this->get('/en/shrines', true);
        $this->assertSame(200, $shrines['status'], '/en/shrines is public and must stay reachable anonymously');
        $this->assertStringContainsString('Schoenstatt Shrine', $shrines['body']);
    }

    /**
     * And the open declarations really are cheaper, not just differently spelled. The
     * maintenance endpoints are on the deploy path; consulting the ACL would start a
     * session, which is a `Set-Cookie` a deploy hook has no use for and several
     * database queries it should not pay for. No session cookie is the observable
     * proof that the guard asked the ACL nothing here.
     */
    public function testTheMaintenanceEndpointsStartNoSession(): void
    {
        $response = $this->request('GET', '/en/sm/cache-status');

        $this->assertSame(401, $response['status']);
        $this->assertStringNotContainsString(
            'PHPSESSID',
            $response['headers']['set-cookie'] ?? '',
            'the guard consulted the ACL on a machine endpoint declared open'
        );
    }

    // ---------------------------------------------------------------------- helpers

    /**
     * A session holding every role, and the page it gets — the rendering the laminas
     * capture was taken from, so EXPECTED_LINKS is comparing like with like.
     *
     * The roles are granted after sign-in rather than before, which works for the
     * reason testARoleGrantedMidSessionTakesEffectOnTheNextRequest pins.
     */
    private function fullyPrivilegedPage(): string
    {
        $jar = $this->newCookieJar();
        $this->grantEveryRole($this->signIn($jar));

        $response = $this->get('/en/admin', false, $jar);
        $this->assertSame(200, $response['status'], 'an account holding every role should see the admin page');

        return $response['body'];
    }

    /**
     * The admin list as (href, label) pairs in document order — the page's substance,
     * with the layout's whitespace and the badge markup left out.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function adminLinks(string $body): array
    {
        preg_match_all('#<p><a href="([^"]+)">([^<]*)</a>#', $body, $matches, PREG_SET_ORDER);

        return array_map(static fn (array $m): array => [$m[1], $m[2]], $matches);
    }

    protected function tearDown(): void
    {
        try {
            $this->purgeMail();
            $this->purgeAccounts();
        } finally {
            parent::tearDown();
        }
    }
}
