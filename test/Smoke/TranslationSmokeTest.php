<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * JTranslate module: the translation administration area.
 */
class TranslationSmokeTest extends SmokeTestCase
{
    public function testTranslationAdminRequiresLogin(): void
    {
        $this->assertRequiresLogin('/en/admin/translations');
    }
}
