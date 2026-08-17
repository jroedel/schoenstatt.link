<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\MaintenanceKey;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use function is_file;
use function is_string;
use function trim;

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
 * **With a maintenance key it also reports which release answered**, and that is
 * the one field a deploy cannot do without. A release symlink swap is invisible to
 * OPcache (`opcache.revalidate_path` defaults to 0), so "the symlink points at the
 * new release" and "the new release is executing" are different facts, and on
 * 2026-08-17 they were different for 30 minutes across three independent pools
 * while a destructive migration ran against code that had already been replaced.
 * `tools/deploy.sh` now polls this until every pool agrees, before anything
 * irreversible happens. See docs/incident-2026-08-17-stale-opcache.md.
 *
 * The key is required for that field and only that field. Without one the answer
 * is exactly what it always was — liveness, nothing more. Framework and PHP
 * versions were considered for the public payload and rejected, because a version
 * on a public URL is a free gift to anyone scanning for a known CVE; a deploy
 * revision is a weaker leak but the same kind, and the caller who needs it is
 * holding the key anyway.
 */
final class HealthController
{
    public function __construct(
        private readonly MaintenanceKey $key,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = [
            'status' => 'ok',
            'kernel' => 'symfony',
        ];

        //grants() rather than refuse(): a wrong key must not turn a liveness probe
        //into a 401. Whatever is watching this URL is entitled to an answer; the
        //key buys one extra field, it does not gate the endpoint.
        if ($this->key->grants($request)) {
            $payload['revision'] = $this->revision();
        }

        return new JsonResponse($payload);
    }

    /**
     * The release's own git sha, written by tools/deploy.sh into the release root.
     *
     * Absent in the capsule and on a dev checkout, which is not an error: those are
     * not releases. Reported as null so a caller can tell "not a deployment" from
     * "a deployment of something I did not expect" — the deploy treats only the
     * second as a reason to stop.
     */
    private function revision(): ?string
    {
        $file = $this->projectDir . '/.revision';
        if (! is_file($file)) {
            return null;
        }

        $contents = @file_get_contents($file);

        return is_string($contents) && '' !== trim($contents) ? trim($contents) : null;
    }
}
