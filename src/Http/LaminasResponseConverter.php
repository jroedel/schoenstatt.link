<?php

declare(strict_types=1);

namespace App\Http;

use Laminas\Http\Response as LaminasHttpResponse;
use Laminas\Stdlib\ResponseInterface as LaminasResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns the response laminas-mvc produced into the one Symfony will send.
 *
 * Its job is fidelity, not translation: after the front-controller swap every
 * byte the visitor receives passes through here, so anything it adds or drops is
 * a site-wide behaviour change. Three details it exists to get right are
 * recorded inline below — each was a live bug before it was a comment.
 *
 * What it deliberately does *not* handle is the headers Laminas never puts on
 * the response object at all. GdprStrategy::onFinish() and CspListener both
 * work directly on PHP's SAPI header list (header_remove(), header(),
 * setcookie()), and they run inside Application::run(), i.e. before this class
 * is reached. Symfony's Response::sendHeaders() appends to that same list
 * rather than resetting it, so those headers survive untouched.
 */
final class LaminasResponseConverter
{
    public function __invoke(LaminasResponse $laminas): Response
    {
        if (! $laminas instanceof LaminasHttpResponse) {
            // Laminas\Stdlib\ResponseInterface promises only getContent().
            // Nothing in this application returns such a response today; a 200
            // with the raw content is the honest reading of one that did.
            return new Response((string) $laminas->getContent());
        }

        // toArray() rather than a foreach over Headers, and not for brevity: it
        // reproduces exactly what PhpEnvironment\Response::sendHeaders() does
        // today. That sender appends only MultipleHeaderInterface headers
        // (header($line, false) — Set-Cookie and friends) and *replaces*
        // everything else, so a repeated Vary keeps only its last value.
        // toArray() collapses on the same rule: `name => [values]` for multiple
        // headers, `name => value` for the rest. Iterating Headers by hand emits
        // duplicates the current sender drops, which would be a change rather
        // than a conversion. It also calls forceLoading() first, so no header is
        // left in its unparsed lazy state.
        /** @var array<string, string|string[]> $headers */
        $headers = $laminas->getHeaders()->toArray();

        $response = new Response(
            // getContent(), never getBody(). getBody() de-chunks and *gunzips*
            // according to the response's own Content-Encoding, so on the
            // sitemap route — which gzips its payload — it would hand us
            // plaintext still labelled `Content-Encoding: gzip`. getContent() is
            // also exactly what HttpResponseSender::sendContent() echoes today.
            (string) $laminas->getContent(),
            $laminas->getStatusCode(),
            $headers
        );

        $reasonPhrase = (string) $laminas->getReasonPhrase();
        if ('' !== $reasonPhrase) {
            $response->setStatusCode($laminas->getStatusCode(), $reasonPhrase);
        }
        $response->setProtocolVersion((string) $laminas->getVersion());

        // ResponseHeaderBag's constructor injects a Cache-Control of its own
        // ("no-cache, private") whenever the response carries none. Laminas
        // sends no Cache-Control at all, while PHP's own session cache limiter
        // already emits a stricter one (no-store, no-cache, must-revalidate) at
        // the SAPI level. Since sendHeaders() appends rather than replaces,
        // keeping Symfony's would add a second, weaker directive to every
        // authenticated page.
        if (! $laminas->getHeaders()->has('Cache-Control')) {
            $response->headers->remove('Cache-Control');
        }

        return $response;
    }
}
