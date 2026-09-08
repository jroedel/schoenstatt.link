<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * The Symfony-rendered 404 — Phase A of the laminas-mvc removal (2026-09-08).
 *
 * An unknown URL still reaches `App\Http\LegacyBridge` (the catch-all), but the bridge now
 * re-renders the HTML 404 laminas produced through `error/404.html.twig` instead of handing
 * back the `.phtml`. After this the bridge renders no laminas view to any visitor — only the
 * admin-only `kernel-switch` redirect and the JSON API refusal remain behind it — which is
 * what turns retiring the canary (Phase B) into a deletion nobody sees.
 *
 * ## The guard is a deny-list, and both halves are tested here
 *
 * The substitution fires on a 404 whose content type is not JSON. That has to catch the
 * laminas HTML error page — which carries **no** content type until PHP's SAPI default adds
 * one at send time, i.e. after the bridge has run — while leaving `RestApi`'s JSON 404 and
 * its 410 for retired versions alone. Both are asserted: the HTML 404 is Twig, the API 404
 * stays JSON, the API 410 is untouched.
 */
class NotFoundSymfonySmokeTest extends SmokeTestCase
{
    public function testAnUnknownHtmlPathIsANotFoundRenderedByTwig(): void
    {
        $response = $this->get('/en/no-such-page-in-this-suite-xyz');

        $this->assertSame(404, $response['status']);
        $this->assertStringContainsString('text/html', $response['contentType']);
        //the laminas 404 wording, reproduced in error/404.html.twig so both front
        //controllers say the same thing
        $this->assertStringContainsString('A 404 error occurred', $response['body']);
        $this->assertStringContainsString('Page not found.', $response['body']);
        //rendered inside the shared layout — the navbar and footer prove it is the site's
        //404 and not a bare string
        $this->assertStringContainsString('<title>Schoenstatt Link</title>', $response['body']);
        $this->assertStringContainsString('navbar', $response['body']);
        //and it is Twig, not the bridged .phtml — the whole point of Phase A
        $this->assertStringContainsString('Twig template', $response['body']);
        $this->assertStringNotContainsString('.phtml view script', $response['body']);
    }

    public function testTheUnprefixedUnknownPathTakesTheLocaleHopFirst(): void
    {
        //an unknown path with no locale prefix is still negotiated to one before it 404s,
        //exactly as every other route on the site is — the 404 itself is localised
        $response = $this->get('/no-such-page-unprefixed-xyz');

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/no-such-page-unprefixed-xyz', $response['redirect']);
    }

    public function testAnUnknownApiPathStaysJson(): void
    {
        $response = $this->get('/en/api/v9/there-is-no-such-version');

        $this->assertSame(404, $response['status']);
        $this->assertStringContainsString('json', $response['contentType']);
        //the 404 substitution must not have turned the machine answer into an HTML page
        $this->assertStringNotContainsString('A 404 error occurred', $response['body']);
    }

    public function testARetiredApiVersionStillAnswers410Json(): void
    {
        $response = $this->get('/en/api/v1/associations');

        $this->assertSame(410, $response['status'], 'a retired version is Gone, not Not Found');
        $this->assertStringContainsString('json', $response['contentType']);
    }
}
