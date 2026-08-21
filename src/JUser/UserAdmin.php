<?php

declare(strict_types=1);

namespace App\JUser;

use App\Laminas\ServiceBridge;
use JTranslate\Controller\Plugin\NowMessenger;
use JTranslate\I18n\TranslatableMessage;
use JUser\Model\PersonValueOptionsProviderInterface;
use JUser\Model\UserTable;
use JUser\Service\ApiTokenService;
use Laminas\Db\Adapter\Adapter;
use Laminas\Form\FormInterface;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\Session\ManagerInterface as SessionManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SionModel\Service\ActingUserProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

use function ctype_digit;
use function get_class;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;
use function ucfirst;

/**
 * What all five controllers of the user-administration surface share.
 *
 * The counterpart of `App\Books\LibraryPage` for the `juser/*` routes, and deliberately
 * smaller than it: on the library surface the route guard admits every signed-in visitor
 * (`lib_user` is `is_default = 1`) so the per-library check inside the page is the real
 * protection, and `LibraryPage` exists to carry it. Here the guard is `administrator`,
 * which is **not** a default role — 2 of 292 accounts hold it — so `route/juser…` really
 * does gate the page and there is nothing left for a controller to check. What is shared
 * is therefore plumbing, not authorization: the table, the person list, the acting user,
 * the two messengers and one log-and-say-so helper.
 *
 * That asymmetry is worth stating rather than leaving implied, because "the guard is the
 * protection" is the *unusual* case in this application and a reader who has just come
 * from the library surface will expect a `refuse()` here and wonder what happened to it.
 *
 * ## Why the JUser services are reached through the bridge and not injected
 *
 * `UserTable` and the person provider both come out of the laminas container at call
 * time. That is not laziness about wiring — `JUser\Form\EditUserForm::class` is
 * registered in `service_manager`, which **shares**, and `setValidatorsForCreate()`
 * mutates the instance it is called on. A form captured in a Symfony factory would be one
 * object for the life of the kernel, and the create controller would hand the edit
 * controller a form still carrying the create-only uniqueness validators. Fetching per
 * call reproduces exactly what a laminas dispatch does: one container, built and
 * discarded per request. docs/strangler.md records the same hazard from the other
 * direction.
 */
final class UserAdmin
{
    /** Shared across every call to {@see flash()} — see that method on why it must be. */
    private ?FlashMessenger $flashMessenger = null;

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    public function table(): UserTable
    {
        /** @var UserTable $table */
        $table = $this->laminas->get(UserTable::class);

        return $table;
    }

    /**
     * One of JUser's form services, out of the laminas container.
     *
     * A method rather than a `get()` at each call site so that the shared-instance hazard
     * has one place to be documented, and one place to change if it ever needs
     * `ServiceManager::build()` instead: `EditUserForm::class` is shared and both
     * `setValidatorsForCreate()` and `prepareForEdit()` mutate the instance they are called
     * on. Safe today because one request dispatches one route — see
     * `App\Controller\UserCreateController`.
     *
     * `string` rather than `class-string`: the two callers read the id out of a route-keyed
     * table, which PHPStan sees as a plain string, and asserting the narrower type at the
     * call site would be a claim the table cannot make.
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function laminasForm(string $formId): FormInterface
    {
        /** @var FormInterface<array<string, mixed>> $form */
        $form = $this->laminas->get($formId);

        return $form;
    }

    /**
     * The database adapter this application configured JUser with.
     *
     * `JUser\Form\DeleteUserForm` takes one as a constructor argument — it is built per
     * call rather than fetched as a service, because a form carries the data and the
     * messages of whatever was last validated through it and the ServiceManager shares by
     * default.
     *
     * The `['juser']['db_adapter']` indirection is honoured rather than short-circuited to
     * `Adapter::class`: this application does set it to exactly that, so the two agree
     * today, and a host that pointed JUser at a second database would find the difference
     * only here. `JUser\Service\DbAdapterResolver` is the canonical reader and is not
     * called because it wants a PSR-11 container, which `ServiceBridge` deliberately is
     * not (see its docblock on why it must not be the container). Its error message is
     * reproduced verbatim so the two are greppable together.
     */
    public function adapter(): Adapter
    {
        /** @var mixed $juser */
        $juser = $this->laminas->config()['juser'] ?? [];
        /** @var mixed $service */
        $service = is_array($juser) ? ($juser['db_adapter'] ?? Adapter::class) : Adapter::class;

        if (! is_string($service) || ! $this->laminas->has($service)) {
            throw new RuntimeException(
                'Please set the [\'juser\'][\'db_adapter\'] config key for use with the JUser module.'
            );
        }

        /** @var Adapter $adapter */
        $adapter = $this->laminas->get($service);

        return $adapter;
    }

    /**
     * The row behind a `{user_id}` route parameter, or null when there is none.
     *
     * `(int) $this->params()->fromRoute('user_id')` in three of the four laminas actions,
     * and the `if (! $id)` that follows each of them is why `'0'` is rejected here rather
     * than looked up: the route constraint is `[0-9]{1,5}`, which matches `00000`.
     *
     * @return array<string, mixed>|null
     */
    public function user(Request $request): ?array
    {
        /** @var mixed $raw */
        $raw = $request->attributes->get('user_id');
        $raw = is_string($raw) || is_int($raw) ? (string) $raw : '';
        if (! ctype_digit($raw) || 0 === (int) $raw) {
            return null;
        }

        /** @var mixed $user */
        $user = $this->table()->getUser((int) $raw);

        return is_array($user) ? $user : null;
    }

    /**
     * Every person, keyed by id, for the index page's `Person` column — or null when this
     * application configures no provider.
     *
     * `getPersons()`, not `getPersonValueOptions()`: the column renders a formatted person
     * with a link and an edit pencil, so it needs the row and not a label. The two are
     * different methods on the same interface and the edit form uses the other one.
     *
     * The null is the laminas view's own `isset($this->persons[...])` guard turned into a
     * type: `JUser\Controller\UsersController::indexAction()` leaves `$persons` null when
     * `person_provider` names nothing this container has, and the column then renders
     * empty for every row rather than failing.
     *
     * @return array<int|string, mixed>|null
     */
    public function persons(): ?array
    {
        /** @var mixed $config */
        $config = $this->laminas->get('JUser\Config');
        if (! is_array($config) || ! isset($config['person_provider'])) {
            return null;
        }

        /** @var mixed $providerId */
        $providerId = $config['person_provider'];
        if (! is_string($providerId) || ! $this->laminas->has($providerId)) {
            return null;
        }

        /** @var mixed $provider */
        $provider = $this->laminas->get($providerId);
        if (! $provider instanceof PersonValueOptionsProviderInterface) {
            //The laminas action throws InvalidArgumentException here, and so does
            //JUser\Service\EditUserFormFactory. Reproduced rather than softened: a
            //`person_provider` naming a service that is not one is a configuration
            //mistake, and answering "no persons" would hide it behind an empty column.
            throw new MisconfiguredPersonProvider($providerId);
        }

        /** @var mixed $persons */
        $persons = $provider->getPersons();

        return is_array($persons) ? $persons : null;
    }

    /**
     * JUser's API-token service.
     *
     * Cheap to build — config plus the db adapter, no request and no identity — which is
     * what made it safe to construct eagerly in the laminas controller factory, and what
     * makes fetching it per call equally safe here.
     */
    public function apiTokens(): ApiTokenService
    {
        /** @var ApiTokenService $service */
        $service = $this->laminas->get(ApiTokenService::class);

        return $service;
    }

    /**
     * The laminas session manager, for the one-shot container a freshly issued JWT travels
     * in.
     *
     * `App\Http\SessionListener` has already started the session by the time a ported
     * controller runs, so a `Laminas\Session\Container` built on this manager reads and
     * writes the same session laminas-mvc would — which is what lets a POST served by one
     * front controller hand its token to a GET served by the other.
     */
    public function sessionManager(): SessionManagerInterface
    {
        /** @var SessionManagerInterface $manager */
        $manager = $this->laminas->get(SessionManagerInterface::class);

        return $manager;
    }

    /**
     * The administrator performing this request, for the provenance columns.
     *
     * Resolved at call time rather than captured in a factory — see
     * `SionModel\Service\ActingUserProviderInterface` on why identity must never be read
     * while the container is still building.
     */
    public function actingUserId(): ?int
    {
        if (! $this->laminas->has(ActingUserProviderInterface::class)) {
            return null;
        }

        /** @var ActingUserProviderInterface $provider */
        $provider = $this->laminas->get(ActingUserProviderInterface::class);

        return $provider->getActingUserId();
    }

    /**
     * Survives a redirect; read by the next page rendered in this session.
     *
     * @param string|TranslatableMessage $message
     *
     * See `App\Controller\LibraryFormController` on why this and {@see now()} are not
     * interchangeable — and note that these controllers reproduce a place where laminas
     * picks the wrong one of the two. `UsersController::editAction()` reports a failed
     * validation with a *flash* and then re-renders the form, so the message appears on
     * whatever page the administrator opens next instead of on the form they are looking
     * at. Reproduced deliberately, filed in docs/BACKLOG.md, and not quietly corrected
     * here: it is a behaviour change on a page whose port should be readable as a port.
     * (That reproduction was corrected on 2026-08-21, once the .phtml it was faithful to
     * was deleted; `App\Controller\UserEditController` uses {@see now()} there now.)
     *
     * **One instance, shared**, and the reason is not efficiency. `addMessage()` calls
     * `getMessagesFromContainer()` the first time an instance is used, which moves every
     * namespace out of the session container into that instance and unsets it from the
     * container. A *second* instance doing that after the first has written takes the
     * first's message out of the session and holds it in an object discarded at the end of
     * the request — so two messages become one, silently. Nothing on this surface flashes
     * twice today, which is why it never showed here; it showed on the sign-in flow, where
     * redemption reports "you are signed in" and "but not there" together. See
     * `App\JUser\SignIn::flash()` for the measurement.
     */
    public function flash(string $namespace, mixed $message): void
    {
        $this->flashMessenger ??= new FlashMessenger();
        /** @phpstan-ignore argument.type (see the docblock: the declared `string` is wrong) */
        $this->flashMessenger->setNamespace($namespace)->addMessage($message);
    }

    /**
     * Rendered by the response being returned now.
     *
     * Both take `string|TranslatableMessage`, for the reason
     * `App\Controller\LibraryCheckoutController::now()` gives: a `TranslatableMessage` is
     * how data reaches a message without becoming part of the phrase key, and it is
     * deliberately **not** `Stringable`, so narrowing to `string` would turn a correct call
     * into a fatal rather than into a bad translation. Both messengers *declare* `string`
     * and document otherwise, which is why the annotation is the honest signature and the
     * native type is `mixed`.
     *
     * @param string|TranslatableMessage $message
     */
    public function now(string $namespace, mixed $message): void
    {
        /** @var NowMessenger $messenger */
        $messenger = $this->laminas->get('ControllerPluginManager')->get('nowMessenger');
        /** @phpstan-ignore argument.type (see the docblock: the declared `string` is wrong) */
        $messenger->setNamespace($namespace)->addMessage($message);
    }

    /**
     * Record a write that did not happen, and say so in one sentence.
     *
     * `UsersController::writeFailureMessage()`, moved here whole. Its docblock is worth
     * keeping with it: these actions used to answer every failure — a validator
     * complaining, a NOT NULL column rejecting a checkbox that posted nothing, a table
     * that had not been migrated — with the same "Error in form submission, please
     * review." and nothing in the log. Reviewing the form could not help, because the form
     * was fine; the only way to find out what happened was to put a debugger in the catch
     * block. So the log gets the exception, the administrator gets told plainly that
     * nothing was saved, and the two are distinguishable from a form they really can fix.
     *
     * The message deliberately carries no exception text: an administrator cannot act on
     * an SQLSTATE, and a stack trace on a page is how internals leak.
     *
     * @param string $what gerund phrase, e.g. 'creating a user'
     * @param Throwable|null $e null when the call reported failure by return value
     * @param array<string, mixed> $context extra fields for the log line
     * @return string the message to show
     */
    public function writeFailure(string $what, ?Throwable $e = null, array $context = []): string
    {
        $logger = $this->logger();
        if (null !== $logger) {
            $logger->error(sprintf('JUser: Failed %s.', $what), $context + [
                'exceptionClass' => null === $e ? null : get_class($e),
                'exception'      => null === $e ? null : $e->getMessage(),
                'trace'          => null === $e ? null : $e->getTraceAsString(),
            ]);
        }

        return sprintf('%s failed — nothing was saved. The error has been logged.', ucfirst($what));
    }

    /** The module's logger, which this application aliases to the application logger. */
    public function logger(): ?LoggerInterface
    {
        if (! $this->laminas->has('JUser\Logger')) {
            return null;
        }

        /** @var mixed $logger */
        $logger = $this->laminas->get('JUser\Logger');

        return $logger instanceof LoggerInterface ? $logger : null;
    }
}
