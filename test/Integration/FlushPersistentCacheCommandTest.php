<?php

namespace SchoenstattTest\Integration;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use PHPUnit\Framework\TestCase;
use SionModel\Console\Command\FlushPersistentCacheCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Covers the command that replaced the deploy's
 * `wget /sm/clear-persistent-cache?key=…`.
 *
 * Two things need pinning. First, the key must travel in the X-Api-Key header
 * and must not appear in the command's own output — moving it out of the query
 * string is the entire security point of the change, and a stray echo would
 * hand it straight back to the deploy log. Second, a rejected key has to fail
 * loudly, in either shape the site can refuse in: the laminas front controller
 * answers a keyless request with a 302 to the sign-in page — so a client that
 * follows redirects sees a cheerful HTTP 200 and reports a flush that never
 * happened — while the Symfony one, which serves this route wherever
 * SYMFONY_KERNEL=1, answers a JSON 401. Both are tested because during the
 * migration the same command talks to hosts of both kinds.
 *
 * Needs vendor/ (laminas-http, symfony/console) but no running app: the HTTP
 * exchange is stubbed through Symfony's MockHttpClient.
 * php composer.phar integration
 */
class FlushPersistentCacheCommandTest extends TestCase
{
    private const CONFIGURED_KEY = 'configured-key';
    private const BASE_URL = 'https://example.test';

    protected function setUp(): void
    {
        //the command prefers the environment over configured keys, so a value
        //leaking in from the shell would silently invalidate these assertions
        putenv(FlushPersistentCacheCommand::KEY_ENV_VAR);
    }

    protected function tearDown(): void
    {
        putenv(FlushPersistentCacheCommand::KEY_ENV_VAR);
    }

    public function testFlushesUsingTheConfiguredKeyAndReportsSuccess(): void
    {
        $client = $this->clientReturning("HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n"
            . '{"message":"Success"}');
        $tester = new CommandTester($this->command($client));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('flushed', $this->display($tester));
    }

    public function testSendsTheKeyAsAHeaderAndNotInTheUrl(): void
    {
        $client = $this->clientReturning("HTTP/1.1 200 OK\r\n\r\n" . '{"message":"Success"}');
        $tester = new CommandTester($this->command($client));
        $tester->execute([]);

        $this->assertContains('X-Api-Key: ' . self::CONFIGURED_KEY, $this->sentHeaders());
        //the URL must carry no query string at all — the key belongs in a header, and a
        //key in a URL ends up in access logs and Referer headers
        $url = (string) $this->lastResponse?->getRequestUrl();
        $this->assertStringNotContainsString('?', $url);
        $this->assertStringContainsString(FlushPersistentCacheCommand::DEFAULT_PATH, $url);
    }

    /**
     * The path is locale-prefixed precisely so no redirect is involved; `/sm/…`
     * answers 302 to `/en/sm/…`, and a custom header surviving that is not
     * something to depend on.
     */
    public function testRequestsTheLocalePrefixedPath(): void
    {
        $this->assertStringStartsWith('/en/', FlushPersistentCacheCommand::DEFAULT_PATH);
    }

    public function testTreatsARedirectAsARejectedKey(): void
    {
        $client = $this->clientReturning("HTTP/1.1 302 Found\r\nLocation: /en/user/login\r\n\r\n");
        $tester = new CommandTester($this->command($client));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('rejects an unknown key', $this->display($tester));
    }

    /**
     * The same rejection from the Symfony front controller: no redirect to
     * mistake for success, but still a failure the deploy has to notice.
     */
    public function testTreatsA401AsARejectedKey(): void
    {
        $client = $this->clientReturning("HTTP/1.1 401 Unauthorized\r\nContent-Type: application/json\r\n\r\n"
            . '{"message":"Unauthorized: this endpoint requires a maintenance key in the X-Api-Key header"}');
        $tester = new CommandTester($this->command($client));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('refused the key', $this->display($tester));
    }

    public function testFailsOnAnErrorStatus(): void
    {
        $client = $this->clientReturning("HTTP/1.1 500 Internal Server Error\r\n\r\nboom");
        $tester = new CommandTester($this->command($client));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('HTTP 500', $this->display($tester));
    }

    /**
     * The action answers 200 with "Unsuccessful flush" when the storage adapter
     * refuses, so the status code alone does not mean the cache is gone.
     */
    public function testFailsWhenTheEndpointAnswers200WithoutSuccess(): void
    {
        $client = $this->clientReturning("HTTP/1.1 200 OK\r\n\r\n" . '{"message":"Unsuccessful flush"}');
        $tester = new CommandTester($this->command($client));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('did not report success', $this->display($tester));
    }

    public function testRefusesToRunWithNoKeyAvailable(): void
    {
        $client = $this->clientReturning("HTTP/1.1 200 OK\r\n\r\n" . '{"message":"Success"}');
        $tester = new CommandTester($this->command($client, []));

        $this->assertSame(Command::INVALID, $tester->execute([]));
        //MockHttpClient counts what it was actually asked for; laminas-http reported this
        //as a null raw request. Either way the property is "nothing was sent".
        $this->assertSame(0, $client->getRequestsCount(), 'no request should be attempted without a key');
    }

    public function testRefusesToRunWithNoBaseUrl(): void
    {
        $client = $this->clientReturning("HTTP/1.1 200 OK\r\n\r\n" . '{"message":"Success"}');
        $command = new FlushPersistentCacheCommand($client, '', [self::CONFIGURED_KEY]);
        $tester = new CommandTester($command);

        $this->assertSame(Command::INVALID, $tester->execute([]));
        $this->assertStringContainsString('pass --url', $this->display($tester));
    }

    public function testTheEnvironmentKeyWinsOverTheConfiguredOne(): void
    {
        putenv(FlushPersistentCacheCommand::KEY_ENV_VAR . '=env-key');
        $client = $this->clientReturning("HTTP/1.1 200 OK\r\n\r\n" . '{"message":"Success"}');
        $tester = new CommandTester($this->command($client));
        $tester->execute([]);

        $this->assertContains('X-Api-Key: env-key', $this->sentHeaders());
    }

    /**
     * --key exists for the case where nothing else is available, but the secret
     * must not be echoed: this output goes to deploy logs.
     */
    public function testNeverEchoesTheKeyItWasGiven(): void
    {
        $client = $this->clientReturning("HTTP/1.1 200 OK\r\n\r\n" . '{"message":"Success"}');
        $tester = new CommandTester($this->command($client, []));
        $tester->execute(['--key' => 'super-secret-value']);

        $this->assertStringNotContainsString('super-secret-value', $this->display($tester));
        $this->assertContains('X-Api-Key: super-secret-value', $this->sentHeaders());
    }

    public function testHonoursAnExplicitUrl(): void
    {
        $client = $this->clientReturning("HTTP/1.1 200 OK\r\n\r\n" . '{"message":"Success"}');
        $tester = new CommandTester($this->command($client));
        $tester->execute(['--url' => 'https://other.test/']);

        //the trailing slash must not survive into a doubled separator
        $this->assertStringContainsString(
            'https://other.test' . FlushPersistentCacheCommand::DEFAULT_PATH,
            $this->display($tester)
        );
    }

    /**
     * @param list<string> $apiKeys
     */
    private function command(
        HttpClientInterface $client,
        array $apiKeys = [self::CONFIGURED_KEY]
    ): FlushPersistentCacheCommand
    {
        return new FlushPersistentCacheCommand($client, self::BASE_URL, $apiKeys);
    }

    /**
     * SymfonyStyle hard-wraps its blocks to the terminal width, so a message
     * asserted verbatim can fail purely on where the line broke.
     */
    private function display(CommandTester $tester): string
    {
        return trim(preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
    }

    /**
     * A client answering with one stubbed exchange.
     *
     * The fixtures are kept as **raw HTTP** rather than as (body, status) pairs, which is
     * what laminas-http's Test adapter took: a test that says
     * `HTTP/1.1 302 Found\r\nLocation: /en/user/login` shows the shape being reproduced,
     * and the 302 is the case this command exists to explain. Parsing them here keeps every
     * call site and its literal unchanged across the move off laminas-http.
     */
    private ?MockResponse $lastResponse = null;

    private function clientReturning(string $rawResponse): HttpClientInterface
    {
        [$head, $body] = array_pad(explode("\r\n\r\n", $rawResponse, 2), 2, '');
        $status        = 200;
        $headers       = [];
        foreach (explode("\r\n", $head) as $i => $line) {
            if (0 === $i) {
                if (1 === preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                }
                continue;
            }
            if ('' !== trim($line) && str_contains($line, ':')) {
                [$name, $value]        = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }

        $this->lastResponse = new MockResponse($body, [
            'http_code'        => $status,
            'response_headers' => $headers,
        ]);

        return new MockHttpClient($this->lastResponse);
    }

    /**
     * The headers actually sent, which laminas-http exposed as `getLastRawRequest()`.
     *
     * MockResponse records the options the client was called with, so the assertion stays
     * about the outgoing request rather than being weakened to "the command did not crash"
     * — these two tests are what keep the API key in a header and out of the deploy log.
     *
     * @return list<string>
     */
    private function sentHeaders(): array
    {
        /** @var list<string> $headers */
        $headers = $this->lastResponse?->getRequestOptions()['headers'] ?? [];

        return $headers;
    }
}
