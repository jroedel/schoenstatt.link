<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\TestCase;
use Schoenstatt\Service\PatresGateway;
use Schoenstatt\Service\PatresLookupFailed;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function file_get_contents;
use function str_contains;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Every way patres can fail to answer arrives as one catchable type.
 *
 * ## The incident
 *
 * On 2026-09-10 a librarian checking a book out of the Bellavista library met an error
 * page twice in twenty-one seconds. Patres served the person *list* perfectly — so the
 * picker offered the father they chose — and answered **HTTP 500** for that one person.
 * `PatresGateway::getRemotePerson()` threw a bare `\Exception`, which sailed past the
 * branch in `App\Controller\LibraryCheckoutController` that had been written for exactly
 * this moment, and became a 500.
 *
 * That controller's own comment says why it matters: *"the person really can be absent —
 * the remote database is another application — and a 500 tells the librarian nothing"*.
 * It handled the gateway returning `false`, which is what happens when patres returns a
 * record this application rejects. It could not handle the throw, because nothing
 * distinguished "patres refused this record" from "patres could not be asked".
 *
 * ## What is asserted, and why the client is injected to do it
 *
 * `HttpClient::create()` was called inline, so no test could reach these paths without a
 * live patres. The gateway now takes a client, which makes all three failures ordinary
 * unit assertions:
 *
 *   1. a non-2xx status — the shape that actually happened;
 *   2. a 200 carrying no record;
 *   3. **the transport failing** — connection refused, DNS, timeout. This one was never
 *      handled at all. Symfony's client is lazy, so a dead patres surfaces as a
 *      `TransportException` from `getStatusCode()` rather than as a status, and an outage
 *      is a likelier failure than a 500 on one record. `getPersonList()` has guarded it
 *      since it was written; `getRemotePerson()` had not.
 *
 * A fourth assertion is the one the incident is really about: the two controllers on this
 * path name `PatresLookupFailed` in a `catch`, narrowly. `catch (Exception)` there would
 * swallow every bug underneath and apologise for patres instead.
 */
final class PatresLookupFailureTest extends TestCase
{
    private const PERSON_ID = 532;

    private function gateway(MockHttpClient $client): PatresGateway
    {
        $gateway = new PatresGateway();
        $gateway->setSchoenstattConfig([
            'patres_api_key'             => 'test-key',
            'patres_api_person_list_uri' => 'https://patres.invalid/api/persons',
            'patres_api_get_person_uri'  => 'https://patres.invalid/api/persons/%s',
        ]);
        $gateway->setHttpClient($client);

        return $gateway;
    }

    public function testANonSuccessStatusIsATypedFailureNamingThePerson(): void
    {
        $gateway = $this->gateway(new MockHttpClient(new MockResponse('', ['http_code' => 500])));

        $this->expectException(PatresLookupFailed::class);
        $this->expectExceptionMessageMatches('/532.*500/');

        $gateway->getRemotePerson(self::PERSON_ID);
    }

    public function testA200WithNoRecordIsATypedFailure(): void
    {
        $gateway = $this->gateway(new MockHttpClient(new MockResponse('{"meta":{}}', ['http_code' => 200])));

        $this->expectException(PatresLookupFailed::class);

        $gateway->getRemotePerson(self::PERSON_ID);
    }

    /**
     * The path that was not handled at all. `MockHttpClient` raises the transport error
     * when the response is read, which is precisely where the real client raises it.
     */
    public function testAnUnreachablePatresIsATypedFailureAndKeepsTheCause(): void
    {
        $gateway = $this->gateway(new MockHttpClient(static function (): MockResponse {
            throw new TransportException('Could not resolve host: patres.invalid');
        }));

        try {
            $gateway->getRemotePerson(self::PERSON_ID);
            self::fail('an unreachable patres did not raise PatresLookupFailed');
        } catch (PatresLookupFailed $e) {
            self::assertSame(self::PERSON_ID, $e->personId);
            self::assertInstanceOf(
                TransportException::class,
                $e->getPrevious(),
                'the socket error is dropped, so the exception report cannot say what actually failed'
            );
        }
    }

    /**
     * Both entry points that ask patres about one person catch the type, and catch only it.
     *
     * Reading the source is a poor test of behaviour and a good test of *narrowness*,
     * which is the property at risk here: the tempting repair for this incident is
     * `catch (Exception)` around the gateway call, and that would turn every future bug on
     * the import path into a message blaming patres.
     */
    public function testTheControllersOnThisPathCatchTheTypeAndNotEverything(): void
    {
        foreach ([
            'src/Controller/LibraryCheckoutController.php',
            'src/Controller/ImportFatherController.php',
        ] as $file) {
            $source = (string) file_get_contents(__DIR__ . '/../../' . $file);

            self::assertTrue(
                str_contains($source, 'catch (PatresLookupFailed'),
                $file . ' no longer catches PatresLookupFailed, so a patres failure is a 500 again'
            );
            self::assertFalse(
                str_contains($source, 'catch (Exception $e) {' . "\n" . '            //patres'),
                $file . ' widened the catch to Exception'
            );
        }
    }
}
