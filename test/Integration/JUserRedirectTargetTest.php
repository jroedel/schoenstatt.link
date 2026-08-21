<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use JUser\Host\AccessInterface;
use JUser\Host\RouteResolverInterface;
use JUser\Model\User;
use JUser\Page\RedirectTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `JUser\Page\RedirectTarget` — the class that decides where a visitor goes after signing
 * in, and therefore the one place on the auth surface where a mistake is an open redirect.
 *
 * It was rewritten for JUser 3.0.0 and lost two thirds of its size: the laminas router, the
 * ACL, the locale-prefix stripping and the role resolution all moved behind
 * {@see RouteResolverInterface} and {@see AccessInterface}, which the host implements. What
 * stayed is what the *module* must guarantee no matter which host it is dropped into, and
 * that is exactly what this test drives.
 *
 * ## Why the hostile cases matter more than they look
 *
 * **Resolving a route is no defence against an off-site destination.** A laminas router
 * parses a URL and matches its *path*, discarding the host — measured on this application
 * 2026-08-21, where both `//evil.example.com/` and `https://evil.example.com/` matched the
 * real route `welcome`. So a resolver answering "yes, that is a route" says nothing about
 * whether the browser would stay on this site. The leading-slash rule and the rejection of
 * a protocol-relative `//host` are the entire defence, and they are asserted here against
 * a resolver that says yes to *everything* — which is the worst host this module could be
 * dropped into, and the only assumption worth testing against.
 *
 * Needs vendor/ for the autoloader; no container, no database, no HTTP.
 */
final class JUserRedirectTargetTest extends TestCase
{
    /**
     * Every one of these must be refused whatever the resolver says.
     *
     * @return iterable<string, array{string}>
     */
    public static function hostileRedirects(): iterable
    {
        yield 'protocol-relative'            => ['//evil.example.com/'];
        yield 'protocol-relative with path'  => ['//evil.example.com/en/welcome'];
        yield 'absolute http'                => ['http://evil.example.com/'];
        yield 'absolute https'               => ['https://evil.example.com/'];
        yield 'scheme-only'                  => ['javascript:alert(1)'];
        yield 'relative, no leading slash'   => ['en/shrines'];
        yield 'bare host'                    => ['evil.example.com'];
        yield 'backslash pair'               => ['\\\\evil.example.com'];
        yield 'empty'                        => [''];
    }

    #[DataProvider('hostileRedirects')]
    public function testAHostileRedirectIsRefusedEvenByAPermissiveHost(string $redirect): void
    {
        $targets = new RedirectTarget($this->resolver(fn(): string => 'welcome'), $this->access(true));

        $this->assertNull($targets->valid($redirect));
    }

    /**
     * The string rules run **before** the resolver is asked, and this is the assertion
     * that they do. A host's resolver may hit a router, a database or a cache; being asked
     * about `https://evil.example.com/` at all is a defect even when the answer is refused.
     */
    public function testTheResolverIsNotEvenAskedAboutAnOffSiteDestination(): void
    {
        $asked   = [];
        $targets = new RedirectTarget(
            $this->resolver(function (string $path) use (&$asked): ?string {
                $asked[] = $path;

                return 'welcome';
            }),
            $this->access(true)
        );

        $targets->valid('//evil.example.com/');
        $targets->valid('https://evil.example.com/');
        $targets->valid('');

        $this->assertSame([], $asked);
    }

    /** A non-string is not a destination, and `?redirect=` can arrive as anything. */
    public function testANonStringIsRefused(): void
    {
        $targets = new RedirectTarget($this->resolver(fn(): string => 'welcome'), $this->access(true));

        $this->assertNull($targets->valid(null));
        $this->assertNull($targets->valid(42));
        $this->assertNull($targets->valid(['/en/shrines']));
        $this->assertNull($targets->valid(true));
    }

    public function testARootRelativePathThatResolvesIsAccepted(): void
    {
        $targets = new RedirectTarget($this->resolver(fn(): string => 'shrines'), $this->access(true));

        $this->assertSame('/en/shrines', $targets->valid('/en/shrines'));
        $this->assertSame('/en/shrines?page=2', $targets->valid('/en/shrines?page=2'));
    }

    /**
     * A path the host cannot place is not a destination.
     *
     * This is the answer that used to be given for *every* path on the site, because the
     * router was asked about `/en/shrines` while it only understood `/shrines`. Nothing
     * failed — null is legitimate — and every visitor landed on the home page. The module
     * cannot detect that; the host's resolver has to strip what it has to strip.
     */
    public function testAPathThatResolvesToNothingIsRefused(): void
    {
        $targets = new RedirectTarget($this->resolver(fn(): ?string => null), $this->access(true));

        $this->assertNull($targets->valid('/en/shrines'));
    }

    public function testRefusedRouteNamesTheRouteWhenTheAccountMayNotReachIt(): void
    {
        $targets = new RedirectTarget($this->resolver(fn(): string => 'admin'), $this->access(false));

        $this->assertSame('admin', $targets->refusedRoute('/en/admin', $this->user()));
    }

    public function testRefusedRouteIsNullWhenTheAccountMayReachIt(): void
    {
        $targets = new RedirectTarget($this->resolver(fn(): string => 'admin'), $this->access(true));

        $this->assertNull($targets->refusedRoute('/en/admin', $this->user()));
    }

    /**
     * "Nothing to refuse" and "nowhere to go" lead to the same page, so an unresolvable
     * destination is not a refusal — and the access layer is not consulted about a route
     * that does not exist, which would make a host answer a question about a name it never
     * declared.
     */
    public function testAnUnresolvableDestinationIsNotARefusal(): void
    {
        $asked   = 0;
        $access  = new class ($asked) implements AccessInterface {
            public function __construct(private int &$asked)
            {
            }

            public function userMayReachRoute(User $user, string $route): bool
            {
                $this->asked++;

                return true;
            }
        };
        $targets = new RedirectTarget($this->resolver(fn(): ?string => null), $access);

        $this->assertNull($targets->refusedRoute('/en/nowhere', $this->user()));
        $this->assertSame(0, $asked);
    }

    /**
     * The account is passed through untouched — the host answers about *that* account and
     * not about whoever the request currently belongs to. That distinction is the reason
     * {@see AccessInterface} takes a user at all: this is asked one line after a sign-in,
     * on a request whose identity was anonymous when the guard ran.
     */
    public function testTheAccountAskedAboutIsTheOnePassedIn(): void
    {
        $seen    = null;
        $access  = new class ($seen) implements AccessInterface {
            /** @param User|null $seen */
            public function __construct(private mixed &$seen)
            {
            }

            public function userMayReachRoute(User $user, string $route): bool
            {
                $this->seen = $user;

                return true;
            }
        };
        $user    = $this->user(99);
        $targets = new RedirectTarget($this->resolver(fn(): string => 'admin'), $access);

        $targets->refusedRoute('/en/admin', $user);

        $this->assertSame($user, $seen);
    }

    /**
     * `pathOf()` never returns a scheme or host, and the reason is a message rather than a
     * redirect: it is quoted back to the visitor, an emailed link is absolute, and a
     * sentence quoting `https://…` reads like a phishing warning instead of a place on this
     * site.
     */
    public function testPathOfKeepsOnlyThePathAndQuery(): void
    {
        $targets = new RedirectTarget($this->resolver(fn(): string => 'welcome'), $this->access(true));

        $this->assertSame('/en/admin', $targets->pathOf('https://example.test/en/admin'));
        $this->assertSame('/en/admin?tab=2', $targets->pathOf('https://example.test/en/admin?tab=2'));
        $this->assertSame('/en/admin', $targets->pathOf('/en/admin'));
        $this->assertSame('/en/admin?tab=2', $targets->pathOf('/en/admin?tab=2'));
    }

    /** A URL with no path at all comes back unchanged rather than as an empty string. */
    public function testPathOfFallsBackToTheWholeUrlWhenThereIsNoPath(): void
    {
        $targets = new RedirectTarget($this->resolver(fn(): string => 'welcome'), $this->access(true));

        $this->assertSame('https://example.test', $targets->pathOf('https://example.test'));
    }

    /** @param callable(string): ?string $answer */
    private function resolver(callable $answer): RouteResolverInterface
    {
        return new class ($answer) implements RouteResolverInterface {
            /** @param callable(string): ?string $answer */
            public function __construct(private readonly mixed $answer)
            {
            }

            public function routeFor(string $path): ?string
            {
                return ($this->answer)($path);
            }
        };
    }

    private function access(bool $allowed): AccessInterface
    {
        return new class ($allowed) implements AccessInterface {
            public function __construct(private readonly bool $allowed)
            {
            }

            public function userMayReachRoute(User $user, string $route): bool
            {
                return $this->allowed;
            }
        };
    }

    private function user(int $id = 7): User
    {
        return new User(['userId' => $id, 'email' => 'someone@example.com', 'active' => 1]);
    }
}
