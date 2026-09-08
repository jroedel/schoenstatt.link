<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use App\Http\KernelCanary;
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
     * The bridge still renders a laminas `.phtml`, and this is what proves it: the same
     * Symfony front controller, a laminas view script, reported as such in the footer.
     *
     * ## The subject is now the 404 page, and that is a milestone rather than a workaround
     *
     * This test walked the migration down to its end. It used `/en/user/login` until batch
     * 13 ported it (2026-08-21); then `/admin/translations` until batch 14 (2026-09-08);
     * then `admin/import-father`, which batch 17 ported the same day — and that was the
     * **last HTML page laminas served**. There is no unported page left to point at.
     *
     * What the bridge still serves, and will until `LegacyBridge` is deleted with the
     * canary, is laminas' own **404 page** for any URL neither router matches. It is a
     * `.phtml` through the bridge like any other, so it carries the same note — minus the
     * `route …/Controller` clause, because no route matched, which is the one assertion
     * below that had to go. When the canary is retired this whole half of the note becomes
     * dead code; until then it has a real subject, and a real one is better than a deleted
     * test. See `docs/strangler.md` § "What is left, and in what order".
     *
     * A 404, so it uses `noteOn()`'s `$expectStatus` rather than the default 200.
     */
    public function testTheBridgeStillRendersALaminasViewScript(): void
    {
        $note = $this->noteOn('/en/no-such-page-on-either-router', null, 404);

        self::assertStringContainsString(ServingNote::RENDERER_PHTML, $note);
        self::assertStringContainsString('via LegacyBridge', $note);
        self::assertStringContainsString('Symfony kernel', $note);
    }

    /**
     * And a cookie that the server is overruling says so.
     *
     * The capsule is the only place this state can be produced: docker/apache-vhost.conf
     * sets SYMFONY_KERNEL with `SetEnv`, and mod_env beats all of mod_setenvif, so an
     * opt-out cookie here is accepted by the browser and ignored by Apache. In production
     * the same request would move the visitor to laminas — which is why this assertion
     * belongs to the capsule and not to tools/smoke-prod.sh.
     */
    public function testACookieTheServerOverrulesIsReportedAsIgnored(): void
    {
        $jar = $this->newCookieJar();
        //write the opt-out cookie straight into curl's jar: no sign-in needed, since the
        //note is rendered for everyone
        $this->writeCookie($jar, KernelCanary::COOKIE, KernelCanary::FORCE_LAMINAS);

        $note = $this->noteOn('/en/shrines', $jar);

        self::assertStringContainsString('from server config', $note);
        self::assertStringContainsString(KernelCanary::COOKIE . '=0 cookie is being ignored', $note);
        //and the front controller reported is the one that really served it
        self::assertStringContainsString('Symfony kernel', $note);
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

    /** A Netscape cookie-jar line curl will send back: domain, path, name, value. */
    private function writeCookie(string $jar, string $name, string $value): void
    {
        file_put_contents(
            $jar,
            "# Netscape HTTP Cookie File\nlocalhost\tFALSE\t/\tFALSE\t0\t$name\t$value\n"
        );
    }
}
