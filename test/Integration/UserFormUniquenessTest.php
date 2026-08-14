<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use JUser\Form\CreateRoleForm;
use JUser\Form\DeleteUserForm;
use JUser\Form\EditUserForm;
use JUser\Service\EditUserFormFactory;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Throwable;

use function is_readable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The three JUser forms whose validators need a db adapter actually run them.
 *
 * All three used to reach the adapter through
 * `Laminas\Db\TableGateway\Feature\GlobalAdapterFeature`'s static registry, populated
 * only by `JUser\Module::onBootstrap()`. `test/Unit/NoStaticDbAdapterTest` is what keeps
 * the registry out of the code; this file is the other half — proof that removing it did
 * not remove the checks with it, measured against the real tables rather than against a
 * specification.
 *
 * ## `EditUserForm` is the reason this file exists
 *
 * The other two threw when the registry was empty, so their breakage was loud. This one
 * wrapped its two static reads in `try { … } catch (\Exception $e) {}` and *swallowed*
 * the failure, which had a consequence nobody had measured: on any surface that had not
 * booted laminas-mvc, `setValidatorsForCreate()` added neither uniqueness validator, and
 * a create-user submission carrying an existing username or display name validated
 * clean. The two `testCreate*` methods below fail if that returns.
 *
 * ## Why it validates against a row read from the database
 *
 * `NoRecordExists` asks the live table. A hardcoded username would make this file fail
 * whenever the dump changes, for a reason that has nothing to do with the rule under
 * test — the same argument PhraseValidationParityTest makes for phrase ids.
 */
final class UserFormUniquenessTest extends TestCase
{
    private static ?ServiceManager $services = null;

    /**
     * A create-user submission is refused for a username that already exists.
     *
     * The claim is the *message key*, not whole-form validity: `security` is a CSRF
     * element and `rolesList` is required, so `isValid()` is false here for three
     * reasons and asserting it would pass even with no uniqueness check at all.
     */
    public function testCreateRefusesAnExistingUsername(): void
    {
        self::requireDatabase();
        $existing = self::anExistingUser();

        $form = self::editUserForm();
        $form->setValidatorsForCreate();
        $form->setData([
            'username'    => $existing['username'],
            'displayName' => 'A Display Name No User Has',
            'email'       => 'nobody@example.com',
        ]);
        $form->isValid();

        self::assertArrayHasKey(
            'recordFound',
            $form->getMessages()['username'] ?? [],
            'an existing username was accepted, so the NoRecordExists validator is not running'
        );
    }

    /** The same for display names, which is the second validator setValidatorsForCreate() adds. */
    public function testCreateRefusesAnExistingDisplayName(): void
    {
        self::requireDatabase();
        $existing = self::anExistingUser();

        $form = self::editUserForm();
        $form->setValidatorsForCreate();
        $form->setData([
            'username'    => 'a-username-no-user-has',
            'displayName' => $existing['displayName'],
            'email'       => 'nobody@example.com',
        ]);
        $form->isValid();

        self::assertArrayHasKey(
            'recordFound',
            $form->getMessages()['displayName'] ?? [],
            'an existing display name was accepted, so the NoRecordExists validator is not running'
        );
    }

    /**
     * The *edit* form does not carry the uniqueness checks, and that is deliberate.
     *
     * `setValidatorsForCreate()` adds them; a plain edit of an existing user would
     * otherwise be refused for keeping its own username. Pinned because the natural
     * "fix" for the bug this file is about — moving the two validators into
     * `getInputFilterSpecification()` so they cannot be forgotten — would make every
     * existing user unsaveable, and nothing else would notice.
     */
    public function testEditDoesNotRefuseAUserKeepingItsOwnUsername(): void
    {
        self::requireDatabase();
        $existing = self::anExistingUser();

        $form = self::editUserForm();
        $form->setData([
            'userId'      => $existing['userId'],
            'username'    => $existing['username'],
            'displayName' => $existing['displayName'],
            'email'       => 'nobody@example.com',
        ]);
        $form->isValid();

        self::assertArrayNotHasKey(
            'recordFound',
            $form->getMessages()['username'] ?? [],
            'the edit form refuses a user for keeping its own username, so no user can be saved'
        );
        self::assertArrayNotHasKey(
            'recordFound',
            $form->getMessages()['displayName'] ?? [],
            'the edit form refuses a user for keeping its own display name'
        );
    }

    /**
     * `CreateRoleForm` refuses a role name that is already a `role_id`.
     *
     * With an empty static registry this form threw
     * `RuntimeException: No database adapter was found in the static registry` from
     * `getInputFilterSpecification()`, for every input including benign ones. So the
     * first thing this asserts is simply that `isValid()` answers; the second is that
     * the answer comes from `NoRecordExists` and not from the `Regex` beside it.
     *
     * Whole-form validity is not asserted: `security` is a CSRF element and there is no
     * browser session here, which is the same seam `PhraseValidator::SESSION_ONLY_INPUT`
     * exists for. The message key on `name` is the claim.
     */
    public function testCreateRoleFormRefusesAnExistingRoleName(): void
    {
        self::requireDatabase();

        $form = new CreateRoleForm(self::adapter());
        $form->setData(['name' => self::anExistingRoleId()]);
        $form->isValid();

        self::assertArrayHasKey(
            'recordFound',
            $form->getMessages()['name'] ?? [],
            'an existing role name was accepted, so the uniqueness check is not running'
        );
    }

    /** The other direction: an unused name is not refused by that validator. */
    public function testCreateRoleFormAcceptsAnUnusedRoleName(): void
    {
        self::requireDatabase();

        $form = new CreateRoleForm(self::adapter());
        $form->setData(['name' => 'a_role_name_no_row_has']);
        $form->isValid();

        self::assertArrayNotHasKey(
            'name',
            $form->getMessages(),
            'an unused, well-formed role name was refused'
        );
    }

    /** The same for `DeleteUserForm`, whose `RecordExists` asks the user table. */
    public function testDeleteUserFormChecksTheUserExists(): void
    {
        self::requireDatabase();

        $form = new DeleteUserForm(self::adapter());
        $form->setData(['userId' => 999999999]);

        self::assertFalse($form->isValid(), 'the delete form accepts a user id that does not exist');
        self::assertArrayHasKey('userId', $form->getMessages());
    }

    /**
     * A role id that exists in this database.
     *
     * `CreateRoleForm` validates the submitted *name* against `user_role.role_id` — the
     * column, not a display name — which is worth knowing before reading the assertion
     * that uses it.
     */
    private static function anExistingRoleId(): string
    {
        $sql    = new Sql(self::adapter());
        $select = $sql->select('user_role')->columns(['role_id'])->limit(1);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();

        if (! $row) {
            self::markTestSkipped('this database holds no roles');
        }

        return (string) $row['role_id'];
    }

    /**
     * A **fresh** form from JUser's own factory, not from the container.
     *
     * `EditUserForm::class` is registered in `service_manager`, which shares by default,
     * and `setValidatorsForCreate()` mutates the instance it is called on — it calls
     * `setInputFilterSpecification()`. So two `$container->get()` calls in one process
     * return one form still carrying whatever the last caller did to it, which is how
     * the first draft of this file "proved" that the edit surface enforces uniqueness:
     * it was reading the create test's leftovers.
     *
     * That is a real hazard and not only a test artefact — `UsersController::editAction()`
     * and `createAction()` fetch the same service, and they are one dispatch apart. It
     * happens to be harmless today because a request runs one action. Invoking the
     * factory keeps this file honest either way.
     */
    private static function editUserForm(): EditUserForm
    {
        /** @var EditUserForm $form */
        $form = (new EditUserFormFactory())(self::services(), EditUserForm::class);

        return $form;
    }

    private static function adapter(): Adapter
    {
        /** @var Adapter $adapter */
        $adapter = self::services()->get(Adapter::class);

        return $adapter;
    }

    /**
     * One real user row, as `['userId' => int, 'username' => string, 'displayName' => string]`.
     *
     * Read with plain SQL rather than through `UserTable`: the projection there hydrates
     * a `User` object and consults roles and person data, none of which this test needs,
     * and all of which could fail for reasons unrelated to a form validator.
     *
     * @return array{userId: int, username: string, displayName: string}
     */
    private static function anExistingUser(): array
    {
        $sql    = new Sql(self::adapter());
        $select = $sql->select('user')
            ->columns(['user_id', 'username', 'display_name'])
            ->limit(1);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();

        if (! $row) {
            self::markTestSkipped('this database holds no users');
        }

        return [
            'userId'      => (int) $row['user_id'],
            'username'    => (string) $row['username'],
            'displayName' => (string) $row['display_name'],
        ];
    }

    /**
     * The application container, built the way bin/console builds it: modules loaded,
     * never bootstrapped — which is precisely the state in which these three forms used
     * to fail, since `onBootstrap()` is what filled the static registry.
     *
     * Config and module-map caches off, as in every integration test here: leaving them
     * on makes module loading write `data/config`, which a CI runner does not have.
     */
    private static function services(): ServiceManager
    {
        if (null !== self::$services) {
            return self::$services;
        }

        /** @var array<string, mixed> $appConfig */
        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        $services = new ServiceManager();
        (new ServiceManagerConfig($appConfig['service_manager'] ?? []))->configureServiceManager($services);
        $services->setService('ApplicationConfig', $appConfig);
        $services->get('ModuleManager')->loadModules();

        return self::$services = $services;
    }

    /** Skip rather than fail where there is no database; every assertion here needs one. */
    private static function requireDatabase(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }

        try {
            self::adapter()->getDriver()->getConnection()->connect();
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }
}
