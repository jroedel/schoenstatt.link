<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Drops the Cache-Control Symfony writes for a response that never asked for one.
 *
 * ResponseHeaderBag's constructor puts `no-cache, private` on any response
 * carrying no cache directives at all, and Response::sendHeaders() *appends* to
 * PHP's header list rather than replacing it. So on a page whose session cache
 * limiter has already sent `no-store, no-cache, must-revalidate` at the SAPI
 * level, the visitor receives two Cache-Control headers and the second one is
 * weaker than the first.
 *
 * App\Http\LaminasResponseConverter already refuses to do this to a bridged
 * response, and test/Integration/LaminasResponseConverterTest pins that. This is
 * the same rule for a Symfony-native one — the two front controllers should not
 * disagree about the headers on the same page, which is exactly what the shrines
 * port made visible: the laminas rendering sent one Cache-Control and the Twig
 * rendering sent two.
 *
 * "Never asked for one" is detected by comparing against what an empty
 * ResponseHeaderBag computes, rather than by reaching for the private
 * `computedCacheControl` flag that actually knows. A controller that deliberately
 * sets exactly `no-cache, private` therefore also loses it — and loses nothing,
 * because that is the value it would have got for free.
 */
final class InventedCacheControlListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (! $event->isMainRequest() || ! SymfonyRoute::isPorted($event->getRequest())) {
            return;
        }

        $headers = $event->getResponse()->headers;
        if ($headers->get('Cache-Control') === (new ResponseHeaderBag())->get('Cache-Control')) {
            $headers->remove('Cache-Control');
        }
    }
}
