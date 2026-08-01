<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * Books module: publications, blog, music, timeline and the members-only
 * text, library and borrower areas.
 *
 * Not covered (see the smoke suite backlog):
 *  - /en/dictionary returns HTTP 404 on the baseline. Its route declares a
 *    controller but no default action, so dispatch falls through to the
 *    not-found handler. Asserting the 404 would enshrine the misconfiguration.
 */
class BooksSmokeTest extends SmokeTestCase
{
    public function testLiteratureIndexRenders(): void
    {
        $response = $this->assertRendersOk('/en/literature');

        $this->assertStringContainsString('Schoenstatt Literature Tools', $response['body']);
    }

    public function testPublicationsSearchPageRenders(): void
    {
        $response = $this->assertRendersOk('/en/literature/search');

        $this->assertStringContainsString('Publications search', $response['body']);
    }

    public function testBlogIndexRenders(): void
    {
        $response = $this->assertRendersOk('/en/blog');

        $this->assertStringContainsString('Posted', $response['body']);
    }

    public function testMusicIndexRenders(): void
    {
        $response = $this->assertRendersOk('/en/music');

        $this->assertStringContainsString('Add new composition', $response['body']);
    }

    public function testTimelineRenders(): void
    {
        $response = $this->assertRendersOk('/en/timeline');

        $this->assertStringContainsString('Kentenich Timeline', $response['body']);
    }

    /**
     * @dataProvider protectedPathProvider
     */
    public function testProtectedPathRequiresLogin(string $path): void
    {
        $this->assertRequiresLogin($path);
    }

    /** @return array<string, array{0: string}> */
    public function protectedPathProvider(): array
    {
        return [
            'texts'     => ['/en/texts'],
            'libraries' => ['/en/libraries'],
            'borrowers' => ['/en/borrowers'],
        ];
    }
}
