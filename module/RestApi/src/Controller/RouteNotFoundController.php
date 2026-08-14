<?php

declare(strict_types=1);

namespace RestApi\Controller;

use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;

/**
 * The JSON 404 for any unmatched /api/... path.
 *
 * It used to extend ApiController and reach the response format through
 * `$this->getEvent()->getParam('config')`, which that class's dispatch listener
 * populated. ApiController went away with /api/v1 — every subclass of it was a v1
 * controller — so the format arrives through the constructor now and the body is
 * built here. The JSON is byte-identical to what it was before:
 *
 *     {"status":"NOK","result":{"error":"Request Not Found."}}
 *
 * Identical on purpose. This is the only response the 26 retired v1 and v2 URLs
 * still produce, and it is also what an unknown /api/v3 path produces, so changing
 * its shape would be a silent contract change for the one caller class that reads
 * these bodies programmatically.
 */
class RouteNotFoundController extends AbstractActionController
{
    /** @param array<string, string> $responseFormat the ApiRequest.responseFormat config */
    public function __construct(private array $responseFormat)
    {
    }

    public function routenotfoundAction(): JsonModel
    {
        /** @var Response $response */
        $response = $this->getResponse();
        $response->setStatusCode(404);

        return new JsonModel([
            $this->responseFormat['statusKey'] ?? 'status'   => $this->responseFormat['statusNokText'] ?? 'NOK',
            $this->responseFormat['resultKey'] ?? 'result'   => [
                $this->responseFormat['errorKey'] ?? 'error' => $this->responseFormat['pageNotFoundKey']
                    ?? 'Request Not Found.',
            ],
        ]);
    }
}
