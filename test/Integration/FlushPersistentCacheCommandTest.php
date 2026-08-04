<?php

namespace SchoenstattTest\Integration;

use Laminas\Http\Client;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
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
 * loudly: the endpoint answers an unauthenticated request with a 302 to the
 * sign-in page, so a client that follows redirects sees a cheerful HTTP 200 and
 * reports a flush that never happened.
 *
 * Needs vendor/ (laminas-http, symfony/console) but no running app: the HTTP
 * exchange is stubbed through laminas-http's Test adapter.
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

        $rawRequest = (string) $client->getLastRawRequest();

        $this->assertStringContainsString('X-Api-Key: ' . self::CONFIGURED_KEY, $rawRequest);
        //the request line must carry no query string at all
        $this->assertStringNotContainsString('?', explode("\r\n", $rawRequest)[0]);
        $this->assertStringContainsString(FlushPersistentCacheCommand::DEFAULT_PATH, $rawRequest);
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
        $this->assertNull($client->getLastRawRequest(), 'no request should be attempted without a key');
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

        $this->assertStringContainsString('X-Api-Key: env-key', (string) $client->getLastRawRequest());
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
        $this->assertStringContainsString('X-Api-Key: super-secret-value', (string) $client->getLastRawRequest());
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
    private function command(Client $client, array $apiKeys = [self::CONFIGURED_KEY]): FlushPersistentCacheCommand
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

    private function clientReturning(string $rawResponse): Client
    {
        $adapter = new TestAdapter();
        $adapter->setResponse($rawResponse);
        $client = new Client();
        $client->setAdapter($adapter);

        return $client;
    }
}
