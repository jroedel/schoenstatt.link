<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Bible module: the members-only scripture and daily-homily tools, plus the
 * public "reading audio" shortcuts that bounce to the USCCB podcast feed.
 */
class BibleSmokeTest extends SmokeTestCase
{
    #[DataProvider('readingAudioPathProvider')]
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
    public static function readingAudioPathProvider(): array
    {
        return [
            "today's reading"   => ['/en/todays-reading-audio'],
            "tomorrow's reading" => ['/en/tomorrows-reading-audio'],
            "sunday's reading"  => ['/en/sundays-reading-audio'],
        ];
    }

    #[DataProvider('protectedPathProvider')]
    public function testProtectedPathRequiresLogin(string $path): void
    {
        $this->assertRequiresLogin($path);
    }

    /** @return array<string, array{0: string}> */
    public static function protectedPathProvider(): array
    {
        return [
            'bible'         => ['/en/bible'],
            'daily homilies' => ['/en/dh'],
        ];
    }
}
