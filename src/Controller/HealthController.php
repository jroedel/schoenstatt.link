<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * GET /_health — the one route the Symfony kernel serves itself.
 *
 * It exists to prove the kernel end to end: the RouteCollection, the UrlMatcher,
 * RouterListener, ContainerControllerResolver and ArgumentResolver are all
 * exercised by reaching this method, and none of them are touched by the
 * catch-all path through LegacyBridge. Without it the first real test of that
 * wiring would be whichever route gets ported next.
 *
 * The answer also distinguishes the two front controllers: under the laminas
 * path this URL is a 404 HTML page, so `"kernel":"symfony"` is a positive
 * statement about which one handled the request.
 *
 * It deliberately reports nothing else. Framework and PHP versions were the
 * obvious next field and were dropped: the URL is public, versions on a public
 * URL are a free gift to anyone scanning for a known CVE, and composer.lock
 * already pins the versions far more precisely than a smoke assertion could.
 * Operational detail that *is* worth reporting lives behind the maintenance key
 * on /sm/cache-status.
 */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'kernel' => 'symfony',
        ]);
    }
}
