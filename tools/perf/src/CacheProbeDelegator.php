<?php

declare(strict_types=1);

namespace SchoenstattPerf;

use Laminas\Cache\Storage\Event;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\EventManager\EventsCapableInterface;
use Psr\Container\ContainerInterface;

/**
 * Counts what a cache storage is asked for and what it answers.
 *
 * Listeners on the adapter's own event manager rather than a decorator implementing
 * `StorageInterface`: the interface is wide, several call sites type-hint concrete adapter
 * capabilities (`FlushableInterface`, `IterableInterface`), and a decorator that missed one
 * would change behaviour under measurement. Listeners cannot.
 *
 * **A hit here means APCu returned something, not that the caller used it.** SionCacheTrait
 * refuses to serve an item its dependency map does not name — see `mayServe()` — and reports
 * the refusal to its caller as a miss. That refusal happens above this layer and is invisible
 * from here, so an "apcu hit" in a report is an upper bound on cache effectiveness. The
 * writeup says so where it matters.
 */
final class CacheProbeDelegator
{
    /** Which operations are worth a row. removeItem/hasItem are noise at this granularity. */
    private const READS  = ['getItem', 'getItems'];
    private const WRITES = ['setItem', 'setItems'];

    /**
     * @param array<string, mixed>|null $options
     */
    public function __invoke(
        ContainerInterface $container,
        string $name,
        callable $callback,
        ?array $options = null
    ): mixed {
        Collector::boot();

        $storage = $callback();
        if (! $storage instanceof StorageInterface || ! $storage instanceof EventsCapableInterface) {
            return $storage;
        }

        $events = $storage->getEventManager();
        /** @var \ArrayObject<string, float> $timers */
        $timers = new \ArrayObject();

        foreach (array_merge(self::READS, self::WRITES) as $op) {
            //Priority is deliberately extreme on both sides so the timing brackets every
            //other listener, including the serializer plugin — serialization cost is part of
            //what a cache costs and excluding it would flatter the numbers.
            $events->attach($op . '.pre', static function () use ($timers, $op): void {
                $timers[$op] = microtime(true);
            }, 10000);

            $events->attach($op . '.post', static function (Event $event) use ($timers, $op, $name): void {
                $started = $timers[$op] ?? microtime(true);

                //getParams() answers an ArrayObject, not an array. `array_key_exists()`
                //rejects one with a TypeError — and because a TypeError is an Error rather
                //than an Exception, laminas-cache's own `catch (\Exception)` around getItem
                //does not stop it. The first version of this file did exactly that and the
                //harness reported a confident, wrong **zero** on every route. Hence
                //`offsetExists()` below, and hence run.sh treating an all-zero run as a
                //broken probe rather than a finding.
                $params = $event->getParams();
                $key    = (string) (self::param($params, 'key') ?? '(bulk)');

                if (in_array($op, self::WRITES, true)) {
                    $value = self::param($params, 'value');
                    Collector::recordCache(
                        $name,
                        $op,
                        $key,
                        null,
                        microtime(true) - $started,
                        null === $value ? 0 : strlen(serialize($value))
                    );
                    return;
                }

                //`success` is only present when the caller passed it by reference, which
                //SionCacheTrait does and other callers may not. Falling back to "a null
                //result is a miss" matches how every caller in this application reads it.
                $success = self::param($params, 'success');
                $hit     = null !== $success ? (bool) $success : null !== self::resultOf($event);


                Collector::recordCache($name, $op, $key, $hit, microtime(true) - $started);
            }, -10000);
        }

        return $storage;
    }

    /**
     * One reader for both shapes `getParams()` can answer with, array and ArrayObject.
     *
     * @param array<string, mixed>|\ArrayAccess<string, mixed> $params
     */
    private static function param($params, string $name): mixed
    {
        if (is_array($params)) {
            return $params[$name] ?? null;
        }

        return $params->offsetExists($name) ? $params[$name] : null;
    }

    private static function resultOf(Event $event): mixed
    {
        //PostEvent exposes the result; a plain Event does not. Guarded rather than
        //type-hinted so a laminas-cache upgrade that renames the class degrades to
        //"unknown" instead of fatalling mid-measurement.
        return method_exists($event, 'getResult') ? $event->getResult() : null;
    }
}
