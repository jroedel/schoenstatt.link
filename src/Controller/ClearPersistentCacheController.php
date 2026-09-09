<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\MaintenanceKey;
use App\Laminas\ServiceBridge;
use SionModel\Cache\Storage as CacheStorage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * GET /sm/clear-persistent-cache (and /{_locale}/…) — flush the web server's
 * persistent cache, which is what the post-deploy hook and
 * `bin/console cache:flush-persistent` both call.
 *
 * Why an HTTP endpoint flushes a cache at all: the persistent cache is the APCu
 * storage adapter, and an APCu segment belongs to the SAPI that created it. A CLI
 * process gets its own segment, or with apc.enable_cli=0 none, so a CLI flush
 * reports cheerful success while the web server keeps serving the entries it was
 * told to drop. Only a request landing in the web SAPI can do this (docs/caching.md).
 *
 * It flushes the `SionModel\PersistentCache` service — the same object
 * SionModelController::clearPersistentCacheAction() flushes — and not
 * apcu_clear_cache() directly. Today those amount to the same thing, and it is
 * worth saying so rather than implying otherwise: the configured adapter is
 * SionModel\Cache\ApcuStorage and `persistent_cache_config` gives it no namespace, so its
 * flush() has nothing to scope itself by and empties the whole segment (docs/DEPLOY.md
 * says as much). Going through the service
 * is about who decides that: the endpoint flushes whatever `persistent_cache_config`
 * says the cache is, so reconfiguring it — a different adapter, or a
 * namespace-scoped clear — changes this endpoint with it, and the laminas action
 * and this one cannot drift apart.
 *
 * Two differences from the laminas action, both on failure paths that no correct
 * caller reaches:
 *
 *  - a missing or non-flushable cache is reported as 500 JSON rather than thrown,
 *    because a thrown exception here reaches SionModel\Error\FatalErrorHandler and
 *    comes back as an HTML error page — unreadable to the hook that called it.
 *  - a flush that returns false is 500, not the laminas action's 401. The message
 *    is unchanged ("Unsuccessful flush") because the console command greps the
 *    body for it, but 401 now means "wrong maintenance key" on this endpoint, and
 *    a caller has to be able to tell that from "the flush failed".
 */
final class ClearPersistentCacheController
{
    private const CACHE_SERVICE = 'SionModel\PersistentCache';

    public function __construct(
        private readonly MaintenanceKey $key,
        private readonly ServiceBridge $laminas
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $refusal = $this->key->refuse($request);
        if (null !== $refusal) {
            return $refusal;
        }

        $cache = $this->laminas->has(self::CACHE_SERVICE) ? $this->laminas->get(self::CACHE_SERVICE) : null;
        if (! $cache instanceof CacheStorage) {
            return self::message(
                null === $cache
                    ? 'Please configure the persistent cache to clear the cache.'
                    : 'Configured persistent cache does not support flushing.',
                500
            );
        }

        return $cache->flush()
            ? self::message('Success', 200)
            : self::message('Unsuccessful flush', 500);
    }

    private static function message(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status, [
            //match the laminas JsonStrategy's header, charset included
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }
}
