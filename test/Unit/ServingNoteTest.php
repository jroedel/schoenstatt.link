<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Http\KernelCanary;
use App\View\ServingNote;
use PHPUnit\Framework\TestCase;

use function getenv;
use function putenv;

//test/bootstrap.php deliberately avoids vendor/autoload.php, so the three classes under
//test are required directly, dependency-first: KernelCanary's CONSENT_COOKIE constant is
//evaluated at class-load time and reads GdprCookieListener's.
require_once __DIR__ . '/../../src/Http/GdprCookieListener.php';
require_once __DIR__ . '/../../src/Http/KernelCanary.php';
require_once __DIR__ . '/../../src/View/ServingNote.php';

/**
 * The footer's serving note, over every state it can describe.
 *
 * Worth testing rather than eyeballing for one reason: the note is read *instead of*
 * working out what served a page, so a note that is confidently wrong is worse than no
 * note at all. The three states it distinguishes are laminas-with-.phtml,
 * Symfony-with-Twig and Symfony-bridged-to-.phtml, and the last two are the pair nothing
 * else on the page tells apart.
 *
 * The environment variable is manipulated with putenv() and restored, which is the only
 * way to reach the branch: in a real request Apache sets it and nothing in PHP can.
 */
class ServingNoteTest extends TestCase
{
    /** @var string|false */
    private $restore;

    protected function setUp(): void
    {
        $this->restore = getenv(KernelCanary::ENV_VAR);
    }

    protected function tearDown(): void
    {
        false === $this->restore
            ? putenv(KernelCanary::ENV_VAR)
            : putenv(KernelCanary::ENV_VAR . '=' . $this->restore);
    }

    /** The pre-flip default: laminas serving its own view scripts. */
    public function testLaminasServingPhtmlWithNoOverride(): void
    {
        putenv(KernelCanary::ENV_VAR);

        $note = ServingNote::describe(ServingNote::RENDERER_PHTML, 'welcome', 'Application\Controller\IndexController');

        self::assertStringContainsString('Served by Laminas\Mvc\Application', $note);
        self::assertStringContainsString('.phtml view script', $note);
        self::assertStringContainsString('route welcome', $note);
        self::assertStringContainsString('Application\Controller\IndexController', $note);
        self::assertStringContainsString('SYMFONY_KERNEL unset, the site default', $note);
        self::assertStringNotContainsString('LegacyBridge', $note);
    }

    /** A ported route. */
    public function testSymfonyServingTwig(): void
    {
        putenv(KernelCanary::ENV_VAR . '=1');

        $note = ServingNote::describe(ServingNote::RENDERER_TWIG, 'shrines.locale', 'App\Controller\ShrinesController');

        self::assertStringContainsString('Served by the Symfony kernel', $note);
        self::assertStringContainsString('Twig template', $note);
        //the .locale suffix is kept on purpose: it is how you see that the prefixed twin
        //matched, which is exactly what you want to know when a page looks subtly wrong
        self::assertStringContainsString('route shrines.locale', $note);
        self::assertStringContainsString('SYMFONY_KERNEL=1, the site default', $note);
        self::assertStringNotContainsString('LegacyBridge', $note);
    }

    /**
     * The case the whole note exists for: the Symfony kernel served the request and
     * laminas rendered it, which is indistinguishable from plain laminas by any other
     * means.
     */
    public function testSymfonyBridgingToPhtmlSaysSo(): void
    {
        putenv(KernelCanary::ENV_VAR . '=1');

        $note = ServingNote::describe(ServingNote::RENDERER_PHTML, 'persons', 'Schoenstatt\Controller\PersonsController');

        self::assertStringContainsString('Served by the Symfony kernel', $note);
        self::assertStringContainsString('.phtml view script via LegacyBridge', $note);
    }

    /** An honoured override names the cookie that did it, in both directions. */
    public function testAnHonouredCookieIsNamed(): void
    {
        putenv(KernelCanary::ENV_VAR . '=1');
        self::assertStringContainsString(
            'from the ' . KernelCanary::COOKIE . '=1 cookie',
            ServingNote::describe(
                ServingNote::RENDERER_TWIG,
                null,
                null,
                [KernelCanary::COOKIE => KernelCanary::FORCE_SYMFONY]
            )
        );

        putenv(KernelCanary::ENV_VAR . '=0');
        self::assertStringContainsString(
            'from the ' . KernelCanary::COOKIE . '=0 cookie',
            ServingNote::describe(
                ServingNote::RENDERER_PHTML,
                null,
                null,
                [KernelCanary::COOKIE => KernelCanary::FORCE_LAMINAS]
            )
        );
    }

    /**
     * And an override that is *not* being honoured says that instead of claiming credit.
     *
     * This is a real state, not a defensive hypothetical: it is what the Docker capsule
     * always reports, because docker/apache-vhost.conf sets the variable with `SetEnv`
     * and mod_env beats all of mod_setenvif. Without this branch "my cookie did nothing"
     * reads as a bug in the toggle rather than the server overruling it.
     */
    public function testAnIgnoredCookieIsReportedAsIgnored(): void
    {
        putenv(KernelCanary::ENV_VAR . '=1');
        $note = ServingNote::describe(
            ServingNote::RENDERER_TWIG,
            null,
            null,
            [KernelCanary::COOKIE => KernelCanary::FORCE_LAMINAS]
        );

        self::assertStringContainsString('SYMFONY_KERNEL=1 from server config', $note);
        self::assertStringContainsString(KernelCanary::COOKIE . '=0 cookie is being ignored', $note);

        putenv(KernelCanary::ENV_VAR . '=0');
        $reverse = ServingNote::describe(
            ServingNote::RENDERER_PHTML,
            null,
            null,
            [KernelCanary::COOKIE => KernelCanary::FORCE_SYMFONY]
        );

        self::assertStringContainsString(KernelCanary::COOKIE . '=1 cookie is being ignored', $reverse);
    }

    /**
     * A page with no route match — an error page — still gets a note, minus the fields
     * that do not exist. The one page where the note matters most is the one that is
     * already failing, so it must not need a route to render.
     */
    public function testMissingRouteAndHandlerAreOmittedRatherThanFaked(): void
    {
        putenv(KernelCanary::ENV_VAR);

        $note = ServingNote::describe(ServingNote::RENDERER_PHTML, null, '');

        self::assertStringContainsString('Served by Laminas\Mvc\Application', $note);
        self::assertStringContainsString('the site default', $note);
        self::assertStringNotContainsString('route ', $note);
        //no empty separator pairs left behind
        self::assertStringNotContainsString('·  ·', $note);
    }

    /** An unrecognised cookie value is not an override; it must not be reported as one. */
    public function testAStrayCookieValueIsNotTreatedAsAnOverride(): void
    {
        putenv(KernelCanary::ENV_VAR . '=1');

        $note = ServingNote::describe(ServingNote::RENDERER_TWIG, null, null, [KernelCanary::COOKIE => 'yes please']);

        self::assertStringContainsString('the site default', $note);
        self::assertStringNotContainsString('cookie', $note);
    }
}
