<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use JTranslate\I18n\TranslatableMessage;
use JUser\Host\AccessInterface;
use JUser\Host\FlashInterface;
use JUser\Host\RouteResolverInterface;
use JUser\Host\SessionInterface;
use JUser\Host\Severity;
use JUser\Host\UrlBuilderInterface;
use JUser\Model\User;
use JUser\Service\Mailer;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Stand-ins for the host contract, shared by the JUser integration tests.
 *
 * Not a `*Test.php` file, so PHPUnit does not treat it as a test class; required explicitly
 * by the tests that need it. That is the point of it being here at all — two test classes
 * use these, and declaring them inside one of the two made the other depend on PHPUnit
 * happening to have loaded that file first.
 *
 * They are deliberately dumb. A fake that validates its input is a second implementation of
 * the contract, and then a test passes because the fake agreed with the code rather than
 * because the code is right.
 */

/** Records which method was asked for, so `url()` vs `path()` is observable. */
final class RecordingUrlBuilder implements UrlBuilderInterface
{
    /** @var list<array{string, string, array<string, mixed>, array<string, string>}> */
    public array $calls = [];

    public function path(string $route, array $params = [], array $query = []): string
    {
        $this->calls[] = ['path', $route, $params, $query];

        return '/en/user/verify';
    }

    public function url(string $route, array $params = [], array $query = []): string
    {
        $this->calls[] = ['url', $route, $params, $query];

        return 'http://example.test/en/user/verify';
    }
}

/**
 * A Mailer that sends nothing.
 *
 * A subclass rather than a mock because the assertion is about which *method* is called:
 * `sendLoginLink()` takes a finished URL and `sendLoginLinkEmail()` assembles one from a
 * router. Overriding both makes a regression to the second visible instead of silent.
 */
final class RecordingMailer extends Mailer
{
    /** @var list<array{string, int}> */
    public array $sent = [];

    /** @var list<string> */
    public array $assembled = [];

    public function sendLoginLink(User $user, string $link, int $expirationMinutes = 15, ?float $start = null)
    {
        $this->sent[] = [$link, $expirationMinutes];

        return null;
    }

    public function sendLoginLinkEmail(
        User $user,
        string $plaintextToken,
        int $expirationMinutes = 15,
        ?string $redirect = null
    ) {
        $this->assembled[] = $plaintextToken;

        return null;
    }
}

final class ArraySession implements SessionInterface
{
    /** @var array<string, array<string, mixed>> */
    public array $data = [];

    public function get(string $namespace, string $key): mixed
    {
        return $this->data[$namespace][$key] ?? null;
    }

    public function set(string $namespace, string $key, mixed $value): void
    {
        $this->data[$namespace][$key] = $value;
    }

    public function remove(string $namespace, string $key): void
    {
        unset($this->data[$namespace][$key]);
    }

    public function regenerateId(bool $destroyOld = true): void
    {
    }

    public function forgetMe(): void
    {
    }
}

final class RecordingFlash implements FlashInterface
{
    /** @var list<array{Severity, string|TranslatableMessage}> */
    public array $messages = [];

    public function flash(Severity $severity, string|TranslatableMessage $message): void
    {
        $this->messages[] = [$severity, $message];
    }

    public function now(Severity $severity, string|TranslatableMessage $message): void
    {
        $this->messages[] = [$severity, $message];
    }
}

/**
 * The host's routing, as a callable.
 *
 * Deliberately answers whatever it is told to, including "yes" for an off-site URL: that is
 * the worst host `JUser\Page\RedirectTarget` could be dropped into, and the only one worth
 * testing its string rules against.
 */
final class RecordingRouteResolver implements RouteResolverInterface
{
    /** @var list<string> every path it was asked about, in order */
    public array $asked = [];

    /** @param callable(string): ?string $answer */
    public function __construct(private readonly mixed $answer)
    {
    }

    public function routeFor(string $path): ?string
    {
        $this->asked[] = $path;

        return ($this->answer)($path);
    }
}

/**
 * The host's authorization, as one flat answer.
 *
 * Records both questions separately, because the distinction between them is the whole
 * reason {@see AccessInterface} has two methods: one asks about a named account that is not
 * the request's identity yet, the other about the visitor making the request.
 */
final class RecordingAccess implements AccessInterface
{
    /** @var list<array{User, string}> */
    public array $aboutAccount = [];

    /** @var list<string> */
    public array $aboutVisitor = [];

    public function __construct(private readonly bool $allowed = true)
    {
    }

    public function userMayReachRoute(User $user, string $route): bool
    {
        $this->aboutAccount[] = [$user, $route];

        return $this->allowed;
    }

    public function visitorMayReachRoute(string $route): bool
    {
        $this->aboutVisitor[] = $route;

        return $this->allowed;
    }
}
