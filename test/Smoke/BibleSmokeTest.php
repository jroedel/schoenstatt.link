<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * Bible module: the members-only scripture and daily-homily tools, plus the
 * public "reading audio" shortcuts that bounce to the USCCB podcast feed.
 */
class BibleSmokeTest extends SmokeTestCase
{
    /**
     * @dataProvider readingAudioPathProvider
     */
    public function testReadingAudioRedirectsToPodcastFeed(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(302, $response['status'], "GET $path should redirect to the podcast");
        // The target file name embeds the date it is generated for, so only the
        // shape of the redirect is stable.
        $this->assertStringContainsString('usccb.org', $response['redirect'], "GET $path redirect host");
        $this->assertStringEndsWith('.mp3', $response['redirect'], "GET $path should point at an audio file");
    }

    /** @return array<string, array{0: string}> */
    public function readingAudioPathProvider(): array
    {
        return [
            "today's reading"   => ['/en/todays-reading-audio'],
            "tomorrow's reading" => ['/en/tomorrows-reading-audio'],
            "sunday's reading"  => ['/en/sundays-reading-audio'],
        ];
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
            'bible'         => ['/en/bible'],
            'daily homilies' => ['/en/dh'],
        ];
    }
}
