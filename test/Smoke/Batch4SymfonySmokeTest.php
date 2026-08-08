<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The nine public routes ported to the Symfony kernel on 2026-08-08.
 *
 * BooksSmokeTest and friends already ask these paths for a 200, and they passed
 * before this port as well: the catch-all would have satisfied them either way, which
 * is exactly the limit docs/strangler.md warns about. What is asserted here is only
 * what the ported route can get wrong on its own.
 *
 * The mechanism assertions — the GDPR cookie, the invented Cache-Control, the
 * unknown-prefix fall-through — belong to App\Kernel's listeners and live in
 * ShrinesSymfonySmokeTest. They are not repeated per route.
 *
 * **The real verification of this batch is not here.** These pages were compared
 * against their laminas renderings across all five locales and both identities with
 * `tools/port-baseline.php`, which is the only check that can see a wrong
 * translation, a missing table column or a breadcrumb that stopped matching. This
 * file is the regression net that runs in CI, where a second front controller is not
 * available: it pins the handful of facts that would tell you the port had come
 * undone.
 */
class Batch4SymfonySmokeTest extends SmokeTestCase
{
    /**
     * The discriminator between the two front controllers: laminas sends
     * `Set-Cookie: slm_locale=en_US` on every response, because SlmLocale's cookie
     * strategy sets it at MvcEvent::FINISH. A ported route never runs SlmLocale, so a
     * locale cookie coming back means the request went through App\Http\LegacyBridge
     * and every other assertion here is measuring the laminas page instead.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function portedPaths(): iterable
    {
        yield 'timeline'      => ['/en/timeline', '<h1>Fr. Kentenich Timeline</h1>'];
        yield 'music'         => ['/en/music', 'Add new composition'];
        yield 'blog'          => ['/en/blog', '/en/blog/posts/'];
        yield 'blog post'     => [
            '/en/blog/posts/SL402801T/upcoming-lauch-december-18th',
            '<h1>What is Schoenstatt Link?</h1>',
        ];
        yield 'dictionary'    => ['/en/dictionary', '</html>'];
        yield 'dictionary es' => ['/en/dictionary/es', 'Fr. Kentenich dictionary German to Spanish'];
        yield '150 preguntas' => [
            '/en/literature/150-preguntas-sobre-schoenstatt',
            '150 preguntas sobre Schoenstatt',
        ];
    }

    #[DataProvider('portedPaths')]
    public function testThePathIsServedBySymfonyAndNotBridgedToLaminas(string $path, string $needle): void
    {
        $response = $this->request('GET', $path);

        $this->assertSame(200, $response['status'], $path);
        $this->assertStringContainsString('text/html', $response['contentType'], $path);
        $this->assertStringNotContainsString(
            'slm_locale=en_US',
            $response['headers']['set-cookie'] ?? '',
            $path . ': a slm_locale cookie means SlmLocale ran, i.e. laminas-mvc served this — '
            . 'is SYMFONY_KERNEL=1 on this instance, and is the route still above the catch-all?'
        );
        $this->assertStringContainsString($needle, $response['body'], $path);
    }

    /**
     * Every ported HTML route owes its unprefixed form a redirect, because SlmLocale
     * answers that form with one rather than serving the page at two URLs. The
     * absence of the `_locale` attribute is how the controller knows, so getting it
     * wrong does not fail — it silently duplicates the page.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function unprefixedPaths(): iterable
    {
        yield 'timeline'   => ['/timeline', '/en/timeline'];
        yield 'music'      => ['/music', '/en/music'];
        yield 'blog'       => ['/blog', '/en/blog'];
        yield 'dictionary' => ['/dictionary', '/en/dictionary'];
        yield '150'        => [
            '/literature/150-preguntas-sobre-schoenstatt',
            '/en/literature/150-preguntas-sobre-schoenstatt',
        ];
    }

    #[DataProvider('unprefixedPaths')]
    public function testTheUnprefixedFormRedirectsToTheNegotiatedLanguage(string $path, string $target): void
    {
        $response = $this->request('GET', $path);

        $this->assertSame(302, $response['status'], $path);
        $this->assertStringEndsWith($target, $response['redirect'], $path);
    }

    /**
     * A post reached without its slug is canonicalised rather than served.
     *
     * This is the one place the ported route deliberately does something laminas does
     * not: `Books\Controller\BlogController::showAction()` assembles that redirect with
     * `text_id`, which is not a parameter of the `blog/blog-post` route, so laminas
     * answers **500**. Measured with tools/port-baseline.php before the route moved.
     */
    public function testAPostWithoutItsSlugIsRedirectedToTheCanonicalUrl(): void
    {
        $response = $this->request('GET', '/en/blog/posts/SL402801T');

        $this->assertSame(302, $response['status']);
        $this->assertStringEndsWith(
            '/en/blog/posts/SL402801T/upcoming-lauch-december-18th',
            $response['redirect']
        );
    }

    /**
     * A well-formed site-wide id naming no row goes to the **home page**, not to the
     * blog index — because the `blog-post` entity spec sets no `index_route` and
     * SionController::showAction() falls back to `sion_model.default_redirect_route`.
     * Measured; it is the kind of detail a reasonable rewrite gets wrong.
     */
    public function testAMissingPostRedirectsToTheDefaultRoute(): void
    {
        $response = $this->request('GET', '/en/blog/posts/SL409999T/nothing-here');

        $this->assertSame(302, $response['status']);
        $this->assertStringEndsWith('/en/', $response['redirect']);
    }

    /** An unknown dictionary language goes back to the dictionary index, as laminas does. */
    public function testAnUnknownDictionaryLanguageRedirectsToTheIndex(): void
    {
        $response = $this->request('GET', '/en/dictionary/xx');

        $this->assertSame(302, $response['status']);
        $this->assertStringEndsWith('/en/dictionary', $response['redirect']);
    }

    /**
     * The blog post page renders in every locale.
     *
     * Not a formality: on **laminas** this page answers `200` with a *zero-byte body*
     * in all four non-English locales, because show.phtml passes the whole post through
     * translate(), JTranslate records the miss, and writing it back overflows
     * `phrase` — a MvcEvent::FINISH exception that discards the response. See
     * docs/strangler.md. So this assertion is the one that says the ported page does
     * not inherit that, and it would have been meaningless in English alone.
     */
    public function testTheBlogPostRendersInEveryLocale(): void
    {
        foreach (['en', 'es', 'de', 'pt', 'it'] as $locale) {
            $path     = "/$locale/blog/posts/SL402801T/upcoming-lauch-december-18th";
            $response = $this->request('GET', $path);

            $this->assertSame(200, $response['status'], $path);
            $this->assertGreaterThan(
                2000,
                strlen($response['body']),
                $path . ': an empty or near-empty 200 is the fatal-200 shape, not a rendering'
            );
            $this->assertStringContainsString('<h1>', $response['body'], $path);
        }
    }

    /**
     * The dictionary index really is an empty body inside the layout — that is what
     * `books/dictionary/index.phtml` (a six-byte file containing `<?php`) produces, and
     * a port that invented content for it would be adding a feature.
     */
    public function testTheDictionaryIndexRendersTheLayoutAroundNothing(): void
    {
        $response = $this->assertRendersOk('/en/dictionary');

        $this->assertStringContainsString('</html>', $response['body']);
        $this->assertLessThan(
            9000,
            strlen($response['body']),
            'the dictionary index should be chrome only; this looks like it grew a body'
        );
    }
}
