<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\MaintenanceKey;
use SionModel\Cache\CacheStatusPayload;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * GET /sm/cache-status (and /{_locale}/sm/cache-status) — APCu and OPcache
 * occupancy, read-only, for tools/smoke-prod.sh and the deploy hooks.
 *
 * The first laminas route ported to the Symfony kernel, and chosen for a specific
 * reason: its protection lives in the controller, not in the route guard. A
 * Symfony-served route never boots laminas-mvc, so it gets no BjyAuthorize guard,
 * no SlmLocale, no session and no laminas-view layout — for this endpoint that
 * costs nothing, because a maintenance key it checks itself is the whole gate and
 * JSON is the whole response. Nothing else about the port is subtle, which is the
 * point of starting here.
 *
 * SionModelController::cacheStatusAction() still answers the same URL under the
 * laminas front controller, which is what production runs today
 * (SYMFONY_KERNEL unset — see docs/strangler.md). Both therefore have to emit the
 * same document, and neither builds it: SionModel\Cache\CacheStatusPayload does.
 *
 * The locale prefix is accepted and ignored. It exists because every caller uses
 * the /en/… form, which under laminas is SlmLocale\Strategy\UriPathStrategy
 * stripping the segment before routing; Symfony has no such listener, so the
 * literal path has to be matched. Nothing in this answer is localized, so which
 * locale was asked for makes no difference to the output.
 */
final class CacheStatusController
{
    public function __construct(private readonly MaintenanceKey $key)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $refusal = $this->key->refuse($request);
        if (null !== $refusal) {
            return $refusal;
        }

        return new JsonResponse(CacheStatusPayload::build(), 200, [
            //the laminas JsonStrategy sends a charset on this response, so this
            //one does too: the two front controllers should be indistinguishable
            //to a caller, headers included
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }
}
