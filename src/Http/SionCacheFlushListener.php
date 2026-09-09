<?php

declare(strict_types=1);

namespace App\Http;

use SionModel\Cache\CacheFlushQueue;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

/**
 * Writes out the persistent-cache items a Symfony-served request queued, after the
 * response has gone out.
 *
 * A SionTable never writes to the cache at the moment it caches something: it queues
 * the item and `SionCacheTrait::onFinishWriteCache()` writes the queue at the end of
 * the request, because serializing a large result set mid-render would charge the
 * visitor for it. The only thing that ever called that method was a listener on
 * `MvcEvent::EVENT_FINISH` — which a ported route never reaches, exactly as with
 * {@see PhraseFlushListener}, and with the same shape of consequence: the work is
 * done, then discarded, and nothing anywhere says so.
 *
 * The measurement that found it (2026-08-22, 49 requests over nine pages, live
 * front controller): 348 reads of data keys, **0 hits, 0 writes**; the same pages
 * under `SYMFONY_KERNEL=0` gave 419 reads, 349 hits and 35 writes, and 6.6
 * statements per request against 11.2. See docs/caching.md.
 *
 * On `terminate` rather than `response` for the same reason as the phrase flush —
 * the write is bookkeeping and the visitor has no reason to wait for it — and
 * `public/index.php` does call `terminate()`.
 *
 * Costs nothing on a request that built no table. The queue is populated from
 * `SionModel\Service\SionTableWiring::wireFlushPoint()`, i.e. from the factory, so a
 * request that never asked for a table registers nothing and this is one empty
 * foreach. That is why the queue is a registry rather than a listener that resolves
 * tables by name: resolving them would build all ten, with their database adapter,
 * on a request that wanted `/_health`.
 *
 * Nothing here fires for a **bridged** request. LegacyBridge builds its own laminas
 * application with its own ServiceManager, which has no queue in it, so those tables
 * take the `MvcEvent::EVENT_FINISH` path and flush before this listener ever runs.
 */
final class SionCacheFlushListener
{
    public function __construct(private readonly CacheFlushQueue $queue)
    {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $this->queue->flush();
    }
}
