<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The three restricted index pages ported on 2026-08-08, measured through the guard.
 *
 * AdminAuthorizationSmokeTest proves App\Authorization\RouteGuard works at all. This
 * file exists because the three guards in this batch have *different shapes*, and a
 * status-code test on one of them says nothing about the others:
 *
 * | route | guard | a freshly registered account |
 * |---|---|---|
 * | `route/associations` | sch_basic, sch_user | **allowed** — registration grants sch_user |
 * | `route/roles` | sch_moderator (+2 descendants) | denied |
 * | `route/libraries` | lib_administrator alone | denied — registration grants lib_*user* |
 *
 * The first row is the one worth having. Every other authorization test in this suite
 * asserts that a signed-in-but-unprivileged visitor is *refused*, so all of them would
 * still pass against a guard that refused everybody. `/associations` is the case where
 * an ordinary account is supposed to get in, and it is what distinguishes "the guard
 * runs" from "the guard runs and says yes to the right people".
 *
 * Roles are granted by INSERT before the magic link is redeemed; see MagicLinkSignIn
 * for why that ordering is convenience rather than a requirement.
 */
class RestrictedIndexAuthorizationSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from every other user of the trait, or one tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'restricted-index-smoke-';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    // ------------------------------------------------------------ anonymous

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function restrictedPaths(): iterable
    {
        yield 'associations' => ['/en/associations'];
        yield 'roles'        => ['/en/roles'];
        yield 'libraries'    => ['/en/libraries'];
    }

    #[DataProvider('restrictedPaths')]
    public function testAnonymousVisitorIsRedirectedToSignIn(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(302, $response['status'], $path);
        $this->assertStringEndsWith('/en/user/login?redirect=' . $path, $response['redirect'], $path);
    }

    /**
     * And the refusal must not leak the table. Each page's most distinctive column
     * header is the probe — a guard that set the status and let the body through would
     * satisfy the assertion above and fail at its job.
     */
    public function testTheRefusalCarriesNoneOfThePage(): void
    {
        $this->assertStringNotContainsString('Create new association', $this->get('/en/associations')['body']);
        $this->assertStringNotContainsString('Create new role', $this->get('/en/roles')['body']);
        $this->assertStringNotContainsString(
            'Schoenstatt Library System',
            $this->get('/en/libraries')['body']
        );
    }

    // --------------------------------------------- signed in, ordinary account

    /**
     * The positive case, and the reason this file exists. Registration assigns
     * `sch_user` by itself, and `route/associations` names it — so an account that has
     * done nothing but sign in reaches this page, and sees the real table.
     */
    public function testAnOrdinaryAccountReachesTheAssociationsIndex(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get('/en/associations', false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('<th>Association</th>', $response['body']);
        $this->assertStringContainsString('<th>Primary contact</th>', $response['body']);
    }

    #[DataProvider('deniedForOrdinaryAccounts')]
    public function testAnOrdinaryAccountIsForbiddenElsewhere(string $path, string $resource): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get($path, false, $jar);

        $this->assertSame(403, $response['status'], $path . ': a signed-in visitor must not be sent to sign in');
        $this->assertStringContainsString('<h1>403 Forbidden</h1>', $response['body'], $path);
        $this->assertStringContainsString(
            'You are not authorized to access ' . $resource . '.',
            $response['body'],
            $path
        );
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function deniedForOrdinaryAccounts(): iterable
    {
        yield 'roles'     => ['/en/roles', 'roles'];
        yield 'libraries' => ['/en/libraries', 'libraries'];
    }

    // ------------------------------------------------ signed in, holds the role

    /**
     * `sch_moderator` gets into /roles, and what comes back is the table — six headers
     * and at least one formatted role. The second assertion is the one that exercises
     * App\Laminas\EntityFormatter's role branch against real rows, which is most of why
     * this page was worth porting in this batch.
     */
    public function testAModeratorSeesTheRolesTable(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $response = $this->get('/en/roles', false, $jar);

        $this->assertSame(200, $response['status']);
        foreach (['Association', 'Role', 'Main role', 'Main contact', 'Single position', 'Sort'] as $header) {
            $this->assertStringContainsString('<th>' . $header . '</th>', $response['body']);
        }
        $this->assertStringContainsString('glyphicon-pencil', $response['body'], 'no edit pencil rendered');
    }

    /**
     * `lib_administrator` gets into /libraries. The list is filtered a *second* time,
     * per row, against each library's own `resourceId` — the `library` entity is the
     * only one in this batch whose spec sets `aclResourceIdField` — so reaching the
     * page and seeing a library are again two different permissions.
     */
    public function testALibraryAdministratorSeesTheLibraryList(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get('/en/libraries', false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Schoenstatt Library System', $response['body']);
        $this->assertStringContainsString('list-group-item', $response['body'], 'the library list is empty');
    }
}
