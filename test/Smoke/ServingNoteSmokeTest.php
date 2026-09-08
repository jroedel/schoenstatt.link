<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use App\View\ServingNote;

//the smoke suite does not autoload by default, and restating these strings here is
//exactly the drift this test is meant to catch
require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The footer's serving note, over real HTTP, through both renderers.
 *
 * test/Unit/ServingNoteTest covers the string; this covers the two things it cannot:
 * that the note is actually *reachable* from each layout, and that each layout reports
 * its own renderer rather than the other one. Two separate wirings produce it — a Twig
 * function in App\Twig\ChromeExtension and a laminas view helper registered in merged
 * config — so "it works on the page I looked at" proves nothing about the other half.
 *
 * The capsule serves the Symfony kernel for everything, which makes it the right place
 * for this: a ported route renders Twig and an unported one is bridged back to a
 * `.phtml`, so both halves are exercised in one run, and the difference between them is
 * the whole point of the note.
 */
class ServingNoteSmokeTest extends SmokeTestCase
{
    // No sign-in and no accounts: every subject here — a ported route, a bridged route and
    // the overruled-cookie case — renders its note for an anonymous visitor, so this class
    // dropped the MagicLinkSignIn trait when its last guarded subject (admin/import-father)
    // was ported in batch 17.

    /** A ported route: Twig, no bridge. */
    public function testAPortedRouteReportsTwig(): void
    {
        $note = $this->noteOn('/en/shrines');

        self::assertStringContainsString(ServingNote::RENDERER_TWIG, $note);
        self::assertStringContainsString('Symfony kernel', $note);
        self::assertStringNotContainsString('LegacyBridge', $note);
        //the route name comes from the Symfony router, and the .locale suffix is how the
        //prefixed twin identifies itself
        self::assertStringContainsString('route shrines.locale', $note);
        self::assertStringContainsString('App\Controller\ShrinesController', $note);
    }

    /**
     * The catch-all 404 is a plain Symfony render now — no bridge left to observe.
     *
     * This test walked the migration to its end. It observed a bridged laminas view script
     * at `/en/user/login` (batch 13), then `/admin/translations` (batch 14), then
     * `admin/import-father` (batch 17 — the last HTML page laminas served), then the
     * bridged laminas 404 (Phase A re-rendered it as Twig). Phase B step 2 (2026-09-08)
     * deleted `App\Http\LegacyBridge` entirely: the catch-all is `App\Controller\NotFoundController`
     * now, an ordinary Symfony controller, so the 404 note names route `not-found` and that
     * controller, with **no `via LegacyBridge`**. Nothing renders a laminas view any more,
     * which is what this asserts. The serving note itself is now vestigial — it always
     * reports Twig — and is a separate cleanup.
     *
     * A 404, so it passes `noteOn()`'s `$expectStatus`.
     */
    public function testTheCatchAll404IsAPlainSymfonyRender(): void
    {
        $note = $this->noteOn('/en/no-such-page-on-either-router', null, 404);

        self::assertStringContainsString(ServingNote::RENDERER_TWIG, $note);
        self::assertStringNotContainsString(ServingNote::RENDERER_PHTML, $note);
        self::assertStringNotContainsString('LegacyBridge', $note);
        self::assertStringContainsString('Symfony kernel', $note);
        self::assertStringContainsString('route not-found', $note);
        self::assertStringContainsString('App\Controller\NotFoundController', $note);
    }

    /** The note as rendered, with the surrounding markup stripped. */
    private function noteOn(string $path, ?string $jar = null, int $expectStatus = 200): string
    {
        $response = $this->get($path, false, $jar);
        self::assertSame($expectStatus, $response['status'], "GET $path should render");

        $found = preg_match(
            '#<p class="[^"]*' . preg_quote(ServingNote::CSS_CLASS, '#') . '[^"]*">(.*?)</p>#s',
            $response['body'],
            $matches
        );
        self::assertSame(
            1,
            $found,
            "GET $path rendered no ." . ServingNote::CSS_CLASS . ' paragraph. Either the layout lost it, or '
            . 'the laminas helper is unregistered — which the layout tolerates on purpose, so it fails here '
            . 'rather than on the site'
        );

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
