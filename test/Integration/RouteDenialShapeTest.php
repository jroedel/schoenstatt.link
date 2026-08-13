<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Authorization\Denial;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The four shapes a refusal can take, asserted without a container.
 *
 * Two of them — the JSON pair — are otherwise untested, because no route declares
 * `DenialStyle::Json` yet. That is exactly why they exist: the two maintenance
 * endpoints already answer a bad maintenance key with 401 and a JSON body, on purpose
 * (a deploy hook that follows a 302 is handed an HTML sign-in page and reads it as
 * success), and the first *guarded* JSON route must not have to rediscover that. An
 * untested branch waiting for its first caller is a branch that will be wrong when the
 * caller arrives.
 *
 * The HTML pair is covered end to end in test/Smoke/AdminAuthorizationSmokeTest; the
 * redirect's assembly is checked here because it is pure string work, and the 403 page
 * is not, because rendering the layout reaches the authentication service and so the
 * session — which cannot be built at all under the CLI SAPI once PHPUnit has printed
 * a dot.
 */
class RouteDenialShapeTest extends TestCase
{
    /**
     * `/en/user/login?redirect=/en/admin` — measured on the laminas side before the
     * route was ported, and reproduced exactly. **The path is not encoded**, which is
     * what keeps this string byte-identical to laminas' on every guarded route that has
     * no query.
     */
    public function testTheAnonymousHtmlRefusalRedirectsToSignInCarryingTheWantedPage(): void
    {
        $response = Denial::signIn('/en/user/login', '/en/admin');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/en/user/login?redirect=/en/admin', $response->headers->get('Location'));
    }

    /**
     * A query string is carried and **percent-encoded**, which is not decoration: an
     * unencoded `&` would end the `redirect` parameter and truncate the return trip at
     * the first one, so a two-parameter search would come back as a one-parameter search.
     *
     * The `?` is encoded for the same reason and no other: PHP decodes the value before
     * `JUser\Controller\LoginController::validRedirect()` sees it, so what that method
     * receives — and what the router has to match — is the plain `/en/texts?search=Bund`.
     * The end-to-end proof is in AssignmentsSearchSymfonySmokeTest, which is the only
     * place all four steps of the round trip meet.
     */
    public function testAQueryStringIsCarriedEncoded(): void
    {
        $response = Denial::signIn('/en/user/login', '/en/texts', 'search=Bund');

        $this->assertSame(
            '/en/user/login?redirect=/en/texts%3Fsearch%3DBund',
            $response->headers->get('Location')
        );
    }

    public function testAMultiParameterQueryEncodesItsSeparator(): void
    {
        $response = Denial::signIn('/en/user/login', '/en/assignments/search', 'search=Walter&country=DE');

        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('%26', $location, 'an unencoded & would truncate the return trip');
        $this->assertStringNotContainsString(
            '&',
            $location,
            'the redirect parameter must be the last thing in this URL'
        );
    }

    /**
     * No query means no change: the two callers that pass null and '' must both produce
     * the string laminas produces, since that is every guarded route on the site bar the
     * searches.
     */
    public function testAnAbsentOrEmptyQueryAddsNothing(): void
    {
        $this->assertSame(
            '/en/user/login?redirect=/en/admin',
            Denial::signIn('/en/user/login', '/en/admin', null)->headers->get('Location')
        );
        $this->assertSame(
            '/en/user/login?redirect=/en/admin',
            Denial::signIn('/en/user/login', '/en/admin', '')->headers->get('Location')
        );
    }

    /**
     * A machine caller must not be redirected. This is the whole reason DenialStyle is
     * declared per route instead of guessed from `Accept`: the deploy hooks and
     * tools/smoke-prod.sh send no Accept header at all.
     */
    public function testTheAnonymousJsonRefusalIs401WithNoRedirect(): void
    {
        $response = Denial::unauthenticatedJson();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($response->isRedirection());
        $this->assertNull($response->headers->get('Location'));
        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertSame(
            ['message' => 'Unauthorized: this endpoint requires an authenticated session'],
            $this->decode($response)
        );
    }

    /**
     * Signed in but not allowed is 403, never 401 and never a redirect — sending this
     * caller to a sign-in page would loop it, since signing in again changes nothing
     * about its roles.
     */
    public function testTheForbiddenJsonRefusalIs403AndNamesTheRoute(): void
    {
        $response = Denial::forbiddenJson('route/some-json-endpoint');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($response->isRedirection());
        $this->assertSame(
            ['message' => 'Forbidden: you are not authorized to access some-json-endpoint'],
            $this->decode($response),
            'the resource is named the way bjy-authorize names it: the route, not the route/ resource'
        );
    }

    /**
     * The `route/` prefix is stripped for the reader, and only when it is there —
     * a non-route ACL resource has to survive intact.
     */
    public function testANonRouteResourceIsNamedVerbatim(): void
    {
        $response = Denial::forbiddenJson('library/3');

        $this->assertSame(
            ['message' => 'Forbidden: you are not authorized to access library/3'],
            $this->decode($response)
        );
    }

    /** @return mixed decoded JSON body */
    private function decode(Response $response): mixed
    {
        return json_decode((string) $response->getContent(), true);
    }
}
