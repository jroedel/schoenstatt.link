<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use JUser\Model\PersonValueOptionsProviderInterface;
use JUser\Model\UserTable;
use JUser\Page\UserAdmin;
use JUser\Service\ApiTokenService;
use Laminas\Db\Adapter\Adapter;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/JUserHostFakes.php';

/**
 * `JUser\Page\UserAdmin` — the three things the port changed, none of which a smoke test
 * can see.
 *
 * **The one-shot token slot.** A freshly minted JWT travels from the POST that issues it to
 * the GET that displays it in a session slot, read and cleared in one act. It is not a
 * message, and the reason is an incident rather than a preference: the messengers translate
 * the finished message at render time, and a translator miss files a phrase — so a version
 * that appended the JWT to the message put four real tokens into a table any translator
 * account can read, and copied them into the English translation and the exported catalog on
 * disk. On the one screen whose own copy says the token is not stored. The namespace and key
 * are asserted here because during the switch a POST may be served by the application's own
 * controller and the GET by this one.
 *
 * **A `{user_id}` of `'0'` is refused rather than looked up.** A route constraint of
 * `[0-9]{1,5}` matches `00000`, and every caller's next line is `(int) $user['userId']`.
 *
 * **A missing person provider is an ordinary answer.** The version this was ported from
 * resolved a service id out of config and threw when it named the wrong type; a host
 * injecting a typed provider or null makes that unrepresentable, so null has to mean
 * "render the column empty" and not "something is wrong".
 *
 * Needs vendor/ and a database, because `UserTable` and `ApiTokenService` are real.
 * Nothing here writes a row.
 */
final class JUserUserAdminTest extends TestCase
{
    private static ?ServiceManager $services = null;

    private UserTable $users;
    private Adapter $adapter;
    private ApiTokenService $apiTokens;

    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            $this->users     = $this->container()->get(UserTable::class);
            $this->adapter   = $this->container()->get(Adapter::class);
            $this->apiTokens = $this->container()->get(ApiTokenService::class);
        } catch (Throwable $e) {
            self::markTestSkipped(
                'no reachable database: ' . $e->getMessage()
                . ' — this test needs the capsule up (docker compose up -d)'
            );
        }
    }

    public function testTheIssuedTokenSlotIsItsOwnNamespace(): void
    {
        $session = new ArraySession();
        $admin   = $this->admin($session);

        $admin->rememberIssuedToken(['jwt' => 'header.payload.signature', 'label' => 'a bot']);

        $this->assertSame(
            ['JUser\ApiToken' => ['issued' => ['jwt' => 'header.payload.signature', 'label' => 'a bot']]],
            $session->data,
            'a credential must not share the namespace the sign-in flow walks for a redirect'
        );
        $this->assertSame('JUser\ApiToken', UserAdmin::TOKEN_NAMESPACE);
        $this->assertNotSame(
            \JUser\Page\SignIn::SESSION_NAMESPACE,
            UserAdmin::TOKEN_NAMESPACE,
            'the token slot and the redirect slot must be different containers'
        );
    }

    public function testTakingTheTokenClearsItSoARefreshDoesNotShowItAgain(): void
    {
        $session = new ArraySession();
        $admin   = $this->admin($session);

        $admin->rememberIssuedToken(['jwt' => 'a.b.c', 'label' => null]);

        $this->assertSame(['jwt' => 'a.b.c', 'label' => null], $admin->takeIssuedToken());
        $this->assertNull($admin->takeIssuedToken(), 'the token is shown on exactly one render');
    }

    public function testAnEmptySlotIsNull(): void
    {
        $this->assertNull($this->admin(new ArraySession())->takeIssuedToken());
    }

    /** Anything but an array is treated as absent; a session has no schema. */
    public function testANonArrayInTheSlotIsTreatedAsAbsent(): void
    {
        $session                            = new ArraySession();
        $session->data['JUser\ApiToken'] = ['issued' => 'a.b.c'];

        $this->assertNull($this->admin($session)->takeIssuedToken());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function idsThatNameNoAccount(): iterable
    {
        yield 'zero'          => ['0'];
        yield 'padded zero'   => ['00000'];
        yield 'empty'         => [''];
        yield 'not a number'  => ['abc'];
        yield 'negative'      => ['-1'];
        yield 'decimal'       => ['1.5'];
        yield 'leading space' => [' 1'];
    }

    #[DataProvider('idsThatNameNoAccount')]
    public function testAUserIdThatNamesNoAccountIsRefusedWithoutALookup(string $id): void
    {
        $request = new Request();
        $request->attributes->set('user_id', $id);

        $this->assertNull($this->admin(new ArraySession())->user($request));
    }

    public function testAMissingUserIdIsRefused(): void
    {
        $this->assertNull($this->admin(new ArraySession())->user(new Request()));
    }

    public function testWithNoPersonProviderTheColumnIsEmptyRatherThanAnError(): void
    {
        $this->assertNull($this->admin(new ArraySession())->persons());
    }

    public function testWithAProviderTheRowsComeStraightThrough(): void
    {
        $rows     = [12 => ['personId' => 12, 'displayName' => 'Kentenich']];
        $provider = new class ($rows) implements PersonValueOptionsProviderInterface {
            /** @param array<int, array<string, mixed>> $rows */
            public function __construct(private readonly array $rows)
            {
            }

            public function getPersons()
            {
                return $this->rows;
            }

            public function getPersonValueOptions($includeInactive = false)
            {
                return [];
            }
        };

        $this->assertSame($rows, $this->admin(new ArraySession(), $provider)->persons());
    }

    /** `getPersons()` answering something that is not an array is "no persons", not a crash. */
    public function testAProviderThatAnswersNonsenseIsTreatedAsNoPersons(): void
    {
        $provider = new class implements PersonValueOptionsProviderInterface {
            public function getPersons()
            {
                return null;
            }

            public function getPersonValueOptions($includeInactive = false)
            {
                return [];
            }
        };

        $this->assertNull($this->admin(new ArraySession(), $provider)->persons());
    }

    /**
     * Never shared: two pages render one of these — the delete confirmation and the modal on
     * the edit page — and a form carries the data and messages of whatever was last put
     * through it.
     */
    public function testEveryDeleteFormIsAFreshOne(): void
    {
        $admin = $this->admin(new ArraySession());

        $this->assertNotSame($admin->deleteForm(), $admin->deleteForm());
    }

    private function admin(
        ArraySession $session,
        ?PersonValueOptionsProviderInterface $persons = null
    ): UserAdmin {
        return new UserAdmin(
            $this->users,
            $this->emptyLocator(),
            $this->adapter,
            $this->apiTokens,
            $session,
            new RecordingFlash(),
            $persons
        );
    }

    /** Nothing in these tests asks for a form service, so asking is a failure. */
    private function emptyLocator(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new \RuntimeException('no form should have been requested: ' . $id);
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }

    private function container(): ServiceManager
    {
        if (null !== self::$services) {
            return self::$services;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        $services = new ServiceManager();
        (new ServiceManagerConfig($appConfig['service_manager'] ?? []))->configureServiceManager($services);
        $services->setService('ApplicationConfig', $appConfig);
        $services->get('ModuleManager')->loadModules();

        return self::$services = $services;
    }
}
