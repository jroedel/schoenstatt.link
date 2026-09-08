<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\View\ServingNote;
use PHPUnit\Framework\TestCase;

//test/bootstrap.php deliberately avoids vendor/autoload.php, so the class under test is
//required directly. Since the SYMFONY_KERNEL canary was retired (2026-09-08) the note no
//longer reads the environment or App\Http\KernelCanary, so this needs neither.
require_once __DIR__ . '/../../src/View/ServingNote.php';

/**
 * The footer's serving note, over every state it can still describe.
 *
 * Worth testing rather than eyeballing for one reason: the note is read *instead of*
 * working out what served a page, so a note that is confidently wrong is worse than no
 * note at all. Since the canary was retired there is one front controller, so the note's
 * only remaining job is the pair nothing else on the page tells apart — a ported Twig
 * template versus a `.phtml` bridged through App\Http\LegacyBridge — plus the route and
 * handler that produced it.
 */
class ServingNoteTest extends TestCase
{
    /** A ported route: Twig, no bridge. */
    public function testSymfonyServingTwig(): void
    {
        $note = ServingNote::describe(ServingNote::RENDERER_TWIG, 'shrines.locale', 'App\Controller\ShrinesController');

        self::assertStringContainsString('Served by the Symfony kernel', $note);
        self::assertStringContainsString('Twig template', $note);
        //the .locale suffix is kept on purpose: it is how you see that the prefixed twin
        //matched, which is exactly what you want to know when a page looks subtly wrong
        self::assertStringContainsString('route shrines.locale', $note);
        self::assertStringContainsString('App\Controller\ShrinesController', $note);
        self::assertStringNotContainsString('LegacyBridge', $note);
        //no canary reporting survives the retirement
        self::assertStringNotContainsString('SYMFONY_KERNEL', $note);
        self::assertStringNotContainsString('cookie', $note);
    }

    /**
     * The case the note still exists for: the Symfony kernel served the request and
     * laminas rendered it through the bridge, which no other signal on the page reveals.
     */
    public function testSymfonyBridgingToPhtmlSaysSo(): void
    {
        $note = ServingNote::describe(ServingNote::RENDERER_PHTML, 'persons', 'Schoenstatt\Controller\PersonsController');

        self::assertStringContainsString('Served by the Symfony kernel', $note);
        self::assertStringContainsString('.phtml view script via LegacyBridge', $note);
    }

    /**
     * A page with no route match — an error page — still gets a note, minus the fields
     * that do not exist. The one page where the note matters most is the one that is
     * already failing, so it must not need a route to render.
     */
    public function testMissingRouteAndHandlerAreOmittedRatherThanFaked(): void
    {
        $note = ServingNote::describe(ServingNote::RENDERER_PHTML, null, '');

        self::assertStringContainsString('Served by the Symfony kernel', $note);
        self::assertStringContainsString('.phtml view script via LegacyBridge', $note);
        self::assertStringNotContainsString('route ', $note);
        //no empty separator pairs left behind
        self::assertStringNotContainsString('·  ·', $note);
    }

    /**
     * The fourth parameter is accepted and ignored since the canary was retired — the
     * note no longer reports a cookie override, because there is no override to report.
     */
    public function testTheCookieArgumentIsIgnored(): void
    {
        $with    = ServingNote::describe(ServingNote::RENDERER_TWIG, 'x', 'Y', ['sl_symfony_canary' => '0']);
        $without = ServingNote::describe(ServingNote::RENDERER_TWIG, 'x', 'Y');

        self::assertSame($without, $with);
        self::assertStringNotContainsString('cookie', $with);
    }
}
