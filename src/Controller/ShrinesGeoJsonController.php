<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use DateTimeImmutable;
use LogicException;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function is_string;

/**
 * GET /api/v1/associations/shrines.json and /api/v2/associations/shrines.json — the
 * shrine locations as GeoJSON, for the mobile apps.
 *
 * **One controller for both versions, because the two actions are byte-identical.**
 * `AssociationsApiV1Controller::shrinesJsonAction()` and its V2 twin are the same four
 * lines over the same table method, and the two live responses were measured equal to
 * the byte. Reproducing that as two Symfony controllers would port the duplication
 * along with the feature, which is the mistake docs/strangler.md records from the
 * wayside-shrine port: between two *ported* routes, share.
 *
 * **Nothing is copied here.** Unlike App\Schoenstatt\ShrineIndex, which had to
 * duplicate the body of a laminas *action*, the work behind this endpoint lives in
 * `SchoenstattTable::getShrineGeoJson()` — a model method, callable from either front
 * controller. So there is no second copy to keep in step and no parity test needed for
 * the data. What is reproduced is only the action's wrapper: serialize, and set the
 * cache headers its `onDispatch()` adds.
 *
 * ## Two deliberate differences from the laminas response
 *
 * Both come from the same fact: this route is declared open, so it starts no session,
 * and PHP's session cache limiter therefore never fires. Measured on the laminas
 * rendering, which sends **contradictory pairs** of every cache header:
 *
 *     Cache-Control: no-store, no-cache, must-revalidate   <- session cache limiter
 *     Cache-Control: max-age=1800, public                  <- makeCacheable()
 *     Pragma: no-cache                                     <- session cache limiter
 *     Pragma:                                               <- makeCacheable()
 *     Expires: Thu, 19 Nov 1981 08:52:00 GMT               <- session cache limiter
 *     Expires: Fri, 07 Aug 2026 01:27:56 GMT               <- makeCacheable()
 *
 * `makeCacheable()` removes those headers from the laminas *response object*, but the
 * limiter has already written its own into PHP's SAPI header list and
 * `sendHeaders()` appends rather than replaces. A cache reading the first
 * `Cache-Control` it is given sees `no-store`, so the half-hour of caching this
 * endpoint asks for has most likely never happened. The ported route sends each
 * header once, which is what the code was always trying to say.
 *
 * The empty `Pragma:` is **not** reproduced. It is `new Pragma()` with no value —
 * `makeCacheable()`'s way of overriding the limiter's `Pragma: no-cache`, and with no
 * limiter there is nothing to override. Emitting a valueless header to cancel one that
 * was never sent would be noise a later reader has to decode.
 */
final class ShrinesGeoJsonController
{
    /** What makeCacheable() asks for: half an hour, publicly cacheable. */
    private const MAX_AGE = 1800;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        //SlmLocale redirects an unprefixed path even for a JSON endpoint — measured:
        ///api/v1/associations/shrines.json answers 302 to /en/api/v1/… today. So a
        //machine caller already follows one redirect here, and dropping it would be a
        //change in the other direction. Reproduced from the matched route name, which
        //is what lets both versions share this method.
        if (null === $request->attributes->get('_locale')) {
            return new RedirectResponse($this->urls->path($this->routeName($request)), Response::HTTP_FOUND);
        }

        /** @var SchoenstattTable $table */
        $table = $this->laminas->get(SchoenstattTable::class);

        //jsonSerialize() rather than passing the object to json_encode: it is what the
        //laminas action calls, and JsonModel would have received the same array
        $payload = $table->getShrineGeoJson()->jsonSerialize();

        //JsonResponse, and its *default* encoding flags are the whole reason it is the
        //right class here: JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT is
        //exactly what Laminas\Json\Json::encode() passes, which is what JsonModel
        //renders through. Measured, not assumed — the first draft of this controller
        //hand-rolled json_encode() with no flags on the belief that laminas passed
        //none, and ShrineGeoJsonParityTest failed on an apostrophe: laminas writes
        //' where bare json_encode writes '. A shrine really can be called
        //"Our Lady's", so that was a live difference in the bytes every client gets.
        $response = new JsonResponse($payload);
        //laminas' JSON renderer sends `application/json; charset=utf-8`; JsonResponse
        //sets the bare type, so the charset is put back rather than dropped
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');

        //`max-age=1800, public` — ResponseHeaderBag sorts the directives, and so does
        //laminas' addHeaderLine order here, so the two agree without coaxing
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);
        //the same absolute instant makeCacheable() computes: now plus the max-age
        $response->setExpires(new DateTimeImmutable('+' . self::MAX_AGE . ' seconds'));

        return $response;
    }

    /**
     * The laminas route this endpoint shadows. Only ever read on the unprefixed twin —
     * the prefixed one returns above — so there is no `.locale` suffix to strip.
     */
    private function routeName(Request $request): string
    {
        $route = $request->attributes->get('_route');
        if (! is_string($route) || '' === $route) {
            throw new LogicException('ShrinesGeoJsonController was reached with no matched route');
        }

        return $route;
    }
}
