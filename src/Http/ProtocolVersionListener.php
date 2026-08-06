<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

use function str_starts_with;
use function substr;

/**
 * Makes a Symfony-served response answer in the protocol version it was asked in.
 *
 * Symfony's Response defaults to HTTP/1.0, and Response::sendHeaders() writes that
 * into the status line. Apache honours it: the first /_health response came back
 * `HTTP/1.0 200 OK` with `Connection: close`, so a Symfony-native route silently
 * lost keep-alive while every bridged route kept it (LaminasResponseConverter
 * copies the version from the laminas response, which took it from the request).
 *
 * FrameworkBundle would fix this inside Response::prepare(). Calling prepare()
 * here is not an option: it also rewrites Content-Type, strips bodies from 304s
 * and normalises Content-Length, all of which would be applied to bridged
 * responses that laminas-mvc already finished. This listener does the one thing
 * that was actually missing.
 */
final class ProtocolVersionListener
{
    public function __invoke(ResponseEvent $event): void
    {
        // SERVER_PROTOCOL, i.e. "HTTP/1.1". Anything unrecognised (an HTTP/2
        // front end reporting "HTTP/2.0", say) is left to Symfony's own default
        // rather than passed through into a status line. It is nullable: a
        // Request built without that server value — anything synthetic, so any
        // test — has no protocol at all, and under strict_types null would be a
        // TypeError rather than a fallback.
        $protocol = (string) $event->getRequest()->getProtocolVersion();
        if (! str_starts_with($protocol, 'HTTP/1.')) {
            return;
        }

        $event->getResponse()->setProtocolVersion(substr($protocol, 5));
    }
}
