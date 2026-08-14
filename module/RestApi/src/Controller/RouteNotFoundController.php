<?php

declare(strict_types=1);

namespace RestApi\Controller;

use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;

use function preg_match;

/**
 * The JSON refusal for any unmatched /api/... path, in two flavours.
 *
 * It used to extend ApiController and reach the response format through
 * `$this->getEvent()->getParam('config')`, which that class's dispatch listener
 * populated. ApiController went away with /api/v1 — every subclass of it was a v1
 * controller — so the format arrives through the constructor now and the body is
 * built here.
 *
 * ## 410 for the retired versions, 404 for everything else
 *
 * `/api/v1` and `/api/v2` were withdrawn on 2026-08-14, all 26 routes. Those URLs get
 * **410 Gone**: the resources existed, they are permanently removed, and there is no
 * replacement at the same address. That distinction is not pedantry — Google treats a
 * 410 as a signal to drop a URL from the index, where a 404 is a softer "try again
 * later", and two of these URLs were published as a schema.org `Dataset` distribution
 * that is indexed today. The 410 also carries
 * `Link: </api/v3/schema>; rel="successor-version"` (RFC 5829), which is the
 * machine-readable way to say where the API went.
 *
 * **It names the schema document, not `/api/v3`.** The first deploy of this pointed at
 * `/api/v3`, which is not a route — it 302s to `/en/api/v3` and then answers this very
 * 404, so a caller that did the correct thing and followed the header arrived nowhere.
 * `/api/v3/schema` is the discovery document: public, 200, and it lists both resources
 * with their endpoints and required roles, which is exactly what a caller following a
 * successor-version link is trying to find out. A Link header pointing at a 404 is worse
 * than no Link header, because it looks like the API is gone entirely.
 *
 * Every *other* unmatched /api/ path keeps its **404** and its byte-identical body:
 *
 *     {"status":"NOK","result":{"error":"Request Not Found."}}
 *
 * That is the load-bearing half of the split. An unknown `/api/v3/phrasez` is a typo in
 * a live API, not a withdrawal, and telling a caller its endpoint is permanently gone
 * when it has merely misspelled one would be a lie the caller acts on. So the 410 is
 * matched narrowly against the two retired version prefixes and nothing else.
 */
class RouteNotFoundController extends AbstractActionController
{
    /**
     * Paths whose resources are permanently gone rather than merely absent.
     *
     * The optional locale segment is SlmLocale's: it 302s `/api/…` to `/{locale}/api/…`
     * and the locale then becomes the router's base URL, so both forms reach here. The
     * `[/.]` alternative after the version number is what catches `/api/v1.yaml` — the
     * OpenAPI document, a static file under public/api/ until it was deleted with the
     * routes it described — alongside `/api/v1/anything`. `v[12]` cannot match `v3`.
     */
    private const RETIRED_PATH = '#^(/(en|de|es|pt|it))?/api/v[12]([/.]|$)#';

    /** @param array<string, string> $responseFormat the ApiRequest.responseFormat config */
    public function __construct(private array $responseFormat)
    {
    }

    public function routenotfoundAction(): JsonModel
    {
        /** @var Response $response */
        $response = $this->getResponse();

        if ($this->isRetiredVersion()) {
            $response->setStatusCode(410);
            $response->getHeaders()->addHeaderLine('Link', '</api/v3/schema>; rel="successor-version"');

            return $this->envelope(
                $this->responseFormat['retiredVersionKey']
                    ?? 'This API version has been retired. Use /api/v3; see /api/v3/schema.'
            );
        }

        $response->setStatusCode(404);

        return $this->envelope($this->responseFormat['pageNotFoundKey'] ?? 'Request Not Found.');
    }

    private function isRetiredVersion(): bool
    {
        $request = $this->getRequest();
        if (! $request instanceof Request) {
            return false;
        }

        return 1 === preg_match(self::RETIRED_PATH, $request->getUri()->getPath() ?? '');
    }

    /** @return JsonModel the envelope every /api/v1 response used, error text swapped in */
    private function envelope(string $error): JsonModel
    {
        return new JsonModel([
            $this->responseFormat['statusKey'] ?? 'status' => $this->responseFormat['statusNokText'] ?? 'NOK',
            $this->responseFormat['resultKey'] ?? 'result' => [
                $this->responseFormat['errorKey'] ?? 'error' => $error,
            ],
        ]);
    }
}
