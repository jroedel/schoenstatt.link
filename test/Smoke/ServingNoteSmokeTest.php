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
    use MagicLinkSignIn;

    /** Distinct from every other class's, or one tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'serving-note-smoke-';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

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
     * An unported route: the same front controller, a laminas rendering. This is the
     * combination the note exists to make visible, because the page itself looks exactly
     * like a laminas-served one.
     *
     * ## This test has to sign in now, and the reason is a milestone rather than a nuisance
     *
     * It used `/en/user/login` until batch 13 ported it (2026-08-21). Finding a replacement
     * turned up that **there is no anonymous-reachable unported HTML page left**: of the 118
     * guarded laminas routes, 106 are now shadowed by a Symfony route, and of the 13 that
     * are not, the only one an anonymous visitor may reach is
     * `redirect-pre-april-2020-sl-id` — which is a redirect and renders no layout at all.
     *
     * So the bridge can only be observed from behind a guard, and `jtranslate`
     * (`/admin/translations`, `sch_general_moderator` or `translator`) is the clearest of
     * the twelve: a real HTML page with the shared layout, no write, and cheap to render.
     * When it ports too, this test needs another of the twelve — and when the twelve run
     * out, `App\Http\LegacyBridge` has nothing left to bridge and this half of the note is
     * dead code, which is the point of the migration and should be deleted rather than
     * patched.
     */
    public function testAnUnportedRouteReportsTheBridge(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['translator']);

        $note = $this->noteOn('/en/admin/translations', $jar);

        self::assertStringContainsString(ServingNote::RENDERER_PHTML, $note);
        self::assertStringContainsString('via LegacyBridge', $note);
        self::assertStringContainsString('Symfony kernel', $note);
        //the laminas side reports controller::action, resolved by the dispatcher
        self::assertStringContainsString('Controller', $note);
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
    private function noteOn(string $path, ?string $jar = null): string
    {
        $response = $this->get($path, false, $jar);
        self::assertSame(200, $response['status'], "GET $path should render");

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
