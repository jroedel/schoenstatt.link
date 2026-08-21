<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Laminas\ServiceBridge;
use Psr\Container\ContainerInterface;

/**
 * The PSR-11 container `JUser\Page\UserAdmin` fetches its forms from.
 *
 * A separate two-method class rather than making `App\Laminas\ServiceBridge` a
 * `ContainerInterface`, and the reason is in that class's own docblock: `App\Container` must
 * be able to resolve `LegacyBridge` — the thing that *builds* the laminas application —
 * without any laminas involvement at all, so the bridge deliberately is not the container.
 * Wrapping it here costs one object and keeps that true.
 *
 * It is only ever asked for form ids. That is not enforced, because PSR-11 has no way to say
 * it and inventing an interface to say it would be a seventh entry in a contract whose whole
 * virtue is being six; `UserAdmin::form()` is the single call site and its docblock is where
 * the constraint is written down.
 */
final class FormLocator implements ContainerInterface
{
    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * A missing id is reported by laminas' own `ServiceNotFoundException`, which already
     * implements PSR-11's `NotFoundExceptionInterface` and already names the service. Adding
     * a check here to throw something of our own would replace a correct message with a
     * worse one.
     */
    public function get(string $id): object
    {
        /** @var object $service */
        $service = $this->laminas->get($id);

        return $service;
    }

    public function has(string $id): bool
    {
        return $this->laminas->has($id);
    }
}
