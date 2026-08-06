<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * The wayside-shrine index, ported to the Symfony kernel 2026-08-07.
 *
 * SchoenstattSmokeTest already asserts that /en/wayside-shrines renders, and it
 * passed before this port as well — the catch-all would satisfy it either way,
 * which is exactly its limit. What is asserted here is what only the ported route
 * can get wrong.
 *
 * ShrinesSymfonySmokeTest carries the mechanism assertions that are not specific to
 * a page — the GDPR cookie, the invented Cache-Control, each locale prefix, the
 * unknown-prefix fall-through — and they are not repeated here, because they test
 * App\Kernel's listeners rather than this route. What is repeated is the pair that
 * cannot be inherited: whether *this* path is served by Symfony at all, and whether
 * its unprefixed form redirects the way SlmLocale would.
 *
 * The rest is about the shared template. schoenstatt/_shrine-index.html.twig is one
 * body rendered by two pages, so the failure this suite must be able to see is the
 * two pages bleeding into each other: the wayside page growing the licence
 * paragraph, or the shrines page losing its link here.
 */
class WaysideShrinesSymfonySmokeTest extends SmokeTestCase
{
    /**
     * The discriminator between the two front controllers, as in
     * ShrinesSymfonySmokeTest: laminas sends `Set-Cookie: slm_locale=en_US` on every
     * response because SlmLocale's cookie strategy sets it at MvcEvent::FINISH.
     * SlmLocale does not run for a ported route, so a locale cookie coming back means
     * the request went through App\Http\LegacyBridge and everything below is
     * measuring the laminas page.
     */
    public function testWaysideShrinesIsServedBySymfonyAndNotBridgedToLaminas(): void
    {
        $response = $this->request('GET', '/en/wayside-shrines');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('text/html', $response['contentType']);
        $this->assertStringNotContainsString(
            'slm_locale=en_US',
            $response['headers']['set-cookie'] ?? '',
            'a slm_locale cookie means SlmLocale ran, i.e. laminas-mvc served this — '
            . 'is SYMFONY_KERNEL=1 on this instance, and is the route still above the catch-all?'
        );
        $this->assertStringContainsString('<h1>Schoenstatt Wayside Shrines</h1>', $response['body']);
    }

    /**
     * The page it shares a template with is a different page. Both halves matter: the
     * header block is what each page supplies for itself, so a mistake in the
     * inheritance shows up as one page wearing the other's introduction.
     */
    public function testTheSharedTemplateDoesNotLeakTheShrinesIntroOntoThisPage(): void
    {
        $wayside = $this->assertRendersOk('/en/wayside-shrines')['body'];
        $shrines = $this->assertRendersOk('/en/shrines')['body'];

        //the *link*, not merely the string: `kind=sch-shrine` also appears on this
        //page inside the schema.org datasets, which are the shrine ones on both pages
        //because waysideShrinesAction() publishes exactly those. See ShrineDatasets.
        $this->assertStringContainsString(
            'findByKind?kind=sch-wayside-shrine" target="_blank">REST API',
            $wayside
        );
        $this->assertStringNotContainsString(
            'findByKind?kind=sch-shrine" target="_blank">REST API',
            $wayside,
            'the API link points at the shrine kind rather than the wayside one'
        );
        $this->assertStringNotContainsString('Creative Commons BY-SA', $wayside, 'the shrines licence paragraph');
        $this->assertStringNotContainsString(
            'btn btn-default">Wayside shrines',
            $wayside,
            'the button linking here belongs on /shrines, not on this page'
        );

        $this->assertStringContainsString('Creative Commons BY-SA', $shrines);
        $this->assertStringContainsString('href="/en/wayside-shrines"', $shrines);
        $this->assertStringContainsString('findByKind?kind=sch-shrine" target="_blank">REST API', $shrines);
    }

    /**
     * And the body they *do* share is present on both, which is the other way the
     * inheritance can fail: a block defined in the child would silently replace it.
     */
    public function testTheSharedBodyRendersOnBothPages(): void
    {
        foreach (['/en/wayside-shrines', '/en/shrines'] as $path) {
            $body = $this->assertRendersOk($path)['body'];

            $this->assertStringContainsString('Vínculo Magazine', $body, "$path lost the courtesy line");
            $this->assertStringContainsString('"@type":"Dataset"', $body, "$path lost its schema.org datasets");
            $this->assertStringContainsString("$('#total-progress-bar')", $body, "$path lost the sizing script");
        }
    }

    /**
     * A region heading and a table per region, and a row per association inside
     * them — the numbers being the 2021 dump's, which is what the capsule serves.
     * Counted rather than merely "something rendered", because the failure mode of a
     * grouping loop is a region quietly going missing while the page still looks
     * fine.
     */
    public function testEveryWaysideShrineIsListedUnderARegionHeading(): void
    {
        $body = $this->assertRendersOk('/en/wayside-shrines')['body'];

        $this->assertStringContainsString('<h3 id="Americas">', $body);
        $this->assertStringContainsString('<h3 id="Europe">', $body);

        $tables = substr_count($body, '<table class="table">');
        $this->assertSame(2, $tables, 'one table per region');
        $this->assertSame(
            43 + $tables,
            substr_count($body, '<tr>'),
            'one row per wayside shrine in the dump, plus a header row per region'
        );
    }

    /**
     * The page carries the same inline progress-bar script as /shrines, so it needs
     * the same policy and the same nonce. A ported route gets neither for free:
     * SionModel\Mvc\CspListener is an MVC listener and App\Http\CspListener is its
     * counterpart.
     */
    public function testWaysideShrinesStillCarriesItsContentSecurityPolicyAndNonce(): void
    {
        $response = $this->request('GET', '/en/wayside-shrines');

        $csp = $response['headers']['content-security-policy'] ?? '';
        $this->assertSame(
            1,
            preg_match("/script-src 'self' 'nonce-([^']+)'/", $csp, $matches),
            'no CSP nonce on the ported page: the inline progress-bar script would be blocked'
        );
        $this->assertStringContainsString(
            'nonce="' . $matches[1] . '"',
            $response['body'],
            'the header nonce and the script nonce must be the same value'
        );
    }

    /**
     * The unprefixed form redirects rather than serving a second copy, which is what
     * SlmLocale's `redirect_when_found` does today. The route name in
     * App\Controller\WaysideShrinesController is what makes it land here rather than
     * on /shrines — the mistake this catches is copying that line and forgetting it.
     */
    public function testTheUnprefixedPathRedirectsToItsOwnLocalisedForm(): void
    {
        $response = $this->get('/wayside-shrines');

        $this->assertSame(302, $response['status']);
        $this->assertStringEndsWith('/en/wayside-shrines', $response['redirect']);
    }

    /**
     * The navbar has no wayside item of its own: `Wayside shrines` sits under
     * `Shrines` in the `navigation` config, and laminas marks an ancestor of the
     * current page active. App\View\SiteChrome descends the static config for exactly
     * this, and the laminas rendering of this URL was checked to agree before the
     * port.
     */
    public function testTheNavbarMarksShrinesActiveOnItsChildPage(): void
    {
        $body = $this->assertRendersOk('/en/wayside-shrines')['body'];

        $this->assertStringContainsString('<a href="/en/shrines" aria-current="page">Shrines</a>', $body);
        $this->assertStringContainsString('<a href="/en/shrines">Shrines</a>', $body, 'the breadcrumb');
        $this->assertStringContainsString('Wayside shrines', $body, 'the breadcrumb leaf');
    }
}
