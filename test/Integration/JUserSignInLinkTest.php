<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use JTranslate\I18n\TranslatableMessage;
use JUser\Host\FlashInterface;
use JUser\Host\SessionInterface;
use JUser\Host\Severity;
use JUser\Host\UrlBuilderInterface;
use JUser\Model\User;
use JUser\Model\UserTable;
use JUser\Page\SignIn;
use JUser\Service\LoginTokenService;
use JUser\Service\Mailer;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/JUserHostFakes.php';

/**
 * The emailed sign-in link, and the session slot the destination travels in.
 *
 * Two things `JUser\Page\SignIn` does that no test could see before, and one of them has
 * already failed in production.
 *
 * ## The link
 *
 * In 2.x `JUser\Service\Mailer` assembled the link itself, from a router its own factory
 * handed it. Under a Symfony dispatch that was the raw container router, which no locale
 * listener had touched — so the link went out as `/user/verify?token=…` with **no locale
 * prefix**: the unprefixed twin of the real route, which answers a 302. A browser follows
 * that hop; a mail client that pre-fetches, a link scanner, or anything that does not
 * follow it, does not — and the token is single-use, so the visitor's one link is spent by
 * something that was never going to sign them in. Measured 2026-08-21.
 *
 * 3.0.0 asks the host's URL builder for an **absolute** URL instead, which is the same
 * builder every link on every page comes off. `testTheLinkIsAbsoluteAndNamesTheVerifyRoute`
 * is what keeps it that way, and it asserts `url()` rather than `path()` was used, because
 * a relative path in an email is not a link at all.
 *
 * ## The session slot
 *
 * `JUser` / `redirect`, and both strings must stay exactly that while
 * `JUser\Controller\LoginController` exists: it is the rollback path for this surface, so a
 * visitor can ask for a link under one front controller and click it under the other. Two
 * different slots lose the destination silently, and only for the visitors caught across
 * the flip — which is the hardest kind of defect to hear about.
 *
 * Needs vendor/ and a database, because `LoginTokenService` takes a real `UserTable`.
 * Nothing here writes a row.
 */
final class JUserSignInLinkTest extends TestCase
{
    private static ?ServiceManager $services = null;

    private LoginTokenService $tokens;
    private UserTable $users;

    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            $this->users  = $this->container()->get(UserTable::class);
            $this->tokens = $this->container()->get(LoginTokenService::class);
        } catch (Throwable $e) {
            self::markTestSkipped(
                'no reachable database: ' . $e->getMessage()
                . ' — this test needs the capsule up (docker compose up -d)'
            );
        }
    }

    public function testTheLinkIsAbsoluteAndNamesTheVerifyRoute(): void
    {
        $urls   = new RecordingUrlBuilder();
        $mailer = new RecordingMailer();
        $signIn = $this->signIn($urls, $mailer);

        $signIn->sendLoginLink($this->user(), 'the-plaintext-token');

        $this->assertSame(
            [['url', 'zfcuser/verify', [], ['token' => 'the-plaintext-token']]],
            $urls->calls,
            'an emailed link must be absolute, so url() and not path()'
        );
        $this->assertSame(
            [['http://example.test/en/user/verify', $this->tokens->getWebTokenExpirationMinutes()]],
            $mailer->sent,
            'the finished link and the expiry go to the mailer; it assembles nothing itself'
        );
    }

    /**
     * The Mailer holds no router, and cannot assemble a link at all.
     *
     * Until 3.0.0 there was a second entry point, `sendLoginLinkEmail()`, which took a
     * plaintext token and built the URL from a `Laminas\Router\RouteStackInterface` its
     * own factory handed it. That is the code path the docblock above describes failing
     * in production, and deleting it is what makes the failure unreproducible rather
     * than merely unused: a caller reaching for it now is a fatal error at the call
     * site, not a link quietly missing its locale prefix.
     *
     * Asserted here rather than in `JUserHostContractTest` because it is a fact about
     * this class, and because the fake in `JUserHostFakes` used to carry the guard by
     * overriding the method — which stops being possible once the method is gone.
     */
    public function testTheMailerCannotAssembleALinkItself(): void
    {
        self::assertFalse(
            method_exists(Mailer::class, 'sendLoginLinkEmail'),
            'sendLoginLinkEmail() was retired in 3.0.0 along with the Mailer\'s router'
        );
        self::assertFalse(
            method_exists(Mailer::class, 'setRouter'),
            'a Mailer that can be given a router is a Mailer that can assemble a link'
        );
    }

    public function testTheDestinationTravelsInTheLinkWhenThereIsOne(): void
    {
        $urls   = new RecordingUrlBuilder();
        $signIn = $this->signIn($urls, new RecordingMailer());

        $signIn->sendLoginLink($this->user(), 'tok', '/en/admin');

        $this->assertSame(
            [['url', 'zfcuser/verify', [], ['token' => 'tok', 'redirect' => '/en/admin']]],
            $urls->calls
        );
    }

    /** An empty redirect is no redirect; it must not become `?redirect=` with nothing after it. */
    public function testAnEmptyDestinationIsNotCarried(): void
    {
        $urls   = new RecordingUrlBuilder();
        $signIn = $this->signIn($urls, new RecordingMailer());

        $signIn->sendLoginLink($this->user(), 'tok', '');

        $this->assertSame([['url', 'zfcuser/verify', [], ['token' => 'tok']]], $urls->calls);
    }

    public function testTheDestinationSlotIsTheOneLoginControllerWrites(): void
    {
        $session = new ArraySession();
        $signIn  = $this->signIn(new RecordingUrlBuilder(), new RecordingMailer(), $session);

        $signIn->rememberDestination('/en/admin');

        $this->assertSame(
            ['JUser' => ['redirect' => '/en/admin']],
            $session->data,
            'namespace and key must match JUser\Controller\LoginController while it exists'
        );
        $this->assertSame('JUser', SignIn::SESSION_NAMESPACE);
        $this->assertSame('redirect', SignIn::DESTINATION);
    }

    public function testTakingTheDestinationClearsIt(): void
    {
        $session = new ArraySession();
        $signIn  = $this->signIn(new RecordingUrlBuilder(), new RecordingMailer(), $session);

        $signIn->rememberDestination('/en/admin');

        $this->assertSame('/en/admin', $signIn->takeDestination());
        $this->assertNull($signIn->takeDestination(), 'the slot is single-use');
        $this->assertSame(['JUser' => []], $session->data);
    }

    public function testTakingNothingIsNull(): void
    {
        $signIn = $this->signIn(new RecordingUrlBuilder(), new RecordingMailer(), new ArraySession());

        $this->assertNull($signIn->takeDestination());
    }

    /**
     * Anything but a string is treated as absent. A session is not a place with a schema —
     * an older release, another module or a hand-edited store can leave anything there, and
     * a non-string reaching {@see \JUser\Page\RedirectTarget::valid()} would be refused
     * anyway. Answering null here keeps the type honest instead.
     */
    public function testANonStringInTheSlotIsTreatedAsAbsent(): void
    {
        $session               = new ArraySession();
        $session->data['JUser'] = ['redirect' => ['/en/admin']];
        $signIn                = $this->signIn(new RecordingUrlBuilder(), new RecordingMailer(), $session);

        $this->assertNull($signIn->takeDestination());
    }

    /** The gate is off unless a host names a cookie — a module must not invent site policy. */
    public function testWithoutAConsentCookieThereIsNoGate(): void
    {
        $signIn = $this->signIn(new RecordingUrlBuilder(), new RecordingMailer());

        $this->assertFalse($signIn->wantsCookiesFirst(new Request()));
    }

    public function testAConfiguredConsentCookieGatesUntilItSaysSo(): void
    {
        $signIn = new SignIn(
            $this->users,
            $this->tokens,
            new RecordingMailer(),
            new RecordingUrlBuilder(),
            new ArraySession(),
            new RecordingFlash(),
            null,
            'gdpr-consent'
        );

        $this->assertTrue($signIn->wantsCookiesFirst(new Request()));
        $this->assertFalse($signIn->wantsCookiesFirst(
            new Request([], [], [], ['gdpr-consent' => 'true'])
        ));
        $this->assertTrue($signIn->wantsCookiesFirst(
            new Request([], [], [], ['gdpr-consent' => 'false'])
        ));
    }

    private function signIn(
        RecordingUrlBuilder $urls,
        RecordingMailer $mailer,
        ?SessionInterface $session = null
    ): SignIn {
        return new SignIn(
            $this->users,
            $this->tokens,
            $mailer,
            $urls,
            $session ?? new ArraySession(),
            new RecordingFlash()
        );
    }

    private function user(): User
    {
        return new User(['userId' => 7, 'email' => 'someone@example.com', 'active' => 1]);
    }

    private function container(): ServiceManager
    {
        if (null !== self::$services) {
            return self::$services;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        $services = new ServiceManager();
        (new ServiceManagerConfig($appConfig['service_manager'] ?? []))->configureServiceManager($services);
        $services->setService('ApplicationConfig', $appConfig);
        $services->get('ModuleManager')->loadModules();

        return self::$services = $services;
    }
}
