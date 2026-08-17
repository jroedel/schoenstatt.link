<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Schoenstatt module: the public shrine database plus the members-only
 * movement, association, person and administration areas.
 *
 */
class SchoenstattSmokeTest extends SmokeTestCase
{
    public function testShrinesIndexRenders(): void
    {
        $response = $this->assertRendersOk('/en/shrines');

        $this->assertStringContainsString('Schoenstatt Shrine', $response['body']);
    }

    public function testSubmittingPhotosPageRenders(): void
    {
        $response = $this->assertRendersOk('/en/shrines/submitting-photos');

        $this->assertStringContainsString('Submitting photos', $response['body']);
    }

    public function testWaysideShrinesIndexRenders(): void
    {
        $response = $this->assertRendersOk('/en/wayside-shrines');

        $this->assertStringContainsString('Schoenstatt Wayside Shrines', $response['body']);
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
            'movement'          => ['/en/movement'],
            'associations'      => ['/en/associations'],
            'persons'           => ['/en/persons'],
            'admin'             => ['/en/admin'],
            //'admin maintenance' => ['/en/admin/maintenance'] was here until 2026-08-17.
            //The route is gone, so the path now 404s for everyone rather than redirecting
            //an anonymous visitor to sign in, and this provider is about the redirect.
            'admin import father' => ['/en/admin/import-father'],
        ];
    }
}
