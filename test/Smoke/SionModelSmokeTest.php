<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * SionModel module: the /en/sm maintenance endpoints.
 *
 * All three are gated behind authentication on the baseline, so these tests
 * only ever exercise the access-control path — no maintenance work is
 * triggered by an anonymous request.
 */
class SionModelSmokeTest extends SmokeTestCase
{
    /**
     * The persistent-cache clear doubles as a deploy hook. It is idempotent,
     * but anonymously it never reaches the controller at all.
     */
    public function testClearPersistentCacheRequiresLogin(): void
    {
        $this->assertRequiresLogin('/en/sm/clear-persistent-cache');
    }

    public function testDataProblemsRequiresLogin(): void
    {
        $this->assertRequiresLogin('/en/sm/data-problems');
    }

    public function testPhpInfoRequiresLogin(): void
    {
        $this->assertRequiresLogin('/en/sm/phpinfo');
    }
}
