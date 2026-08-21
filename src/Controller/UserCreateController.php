<?php

declare(strict_types=1);

namespace App\Controller;

use App\JUser\UserAdmin;
use App\Laminas\RouteUrl;
use JTranslate\Controller\Plugin\NowMessenger;
use JUser\Form\CreateRoleForm;
use JUser\Form\EditUserForm;
use Laminas\Form\FormInterface;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twig\Environment;
use Twig\Error\Error as TwigError;

use function array_key_exists;
use function array_keys;
use function is_string;

/**
 * The two "new record" forms of the user-administration surface.
 *
 * | route | form | writes | on success |
 * |---|---|---|---|
 * | `juser/create` | `EditUserForm` | `createEntity('user', …)` | `juser` |
 * | `juser/create-role` | `CreateRoleForm` | `createEntity('user-role', …)` | `juser` |
 *
 * ## Why these two are not in batch 9
 *
 * Every other create route in the application (nine of them) is `SionController::createAction()`
 * behind `App\Controller\EntityCreateController`. These two are not: `createAction()` and
 * `createRoleAction()` are hand-written in `JUser\Controller\UsersController`, they take
 * their form from the container rather than from the entity spec, and neither uses the
 * spec's `create_action_redirect_route` — both go to the index unconditionally. So they
 * came with the `juser/*` bloc, exactly as `config/symfony/routes.php` said they would in
 * batch 9's preamble.
 *
 * ## Two differences between the two actions, both reproduced
 *
 * 1. **The user form checks `createEntity()`'s return value; the role form does not.**
 *    `createEntity()` answers the new id, or something falsy when the write reported
 *    failure without throwing. `createAction()` branches on that and reports it;
 *    `createRoleAction()` discards it and flashes success either way. Left as it is: the
 *    honest fix is to make `createEntity()` throw, not to add a check on one of two call
 *    sites and leave the surface inconsistent in a new way.
 * 2. **Only the user form pre-selects anything.** A GET of `juser/create` sets
 *    `rolesList` to `getDefaultRoles()` — `lib_user`, `pub_user`, `sch_user`, `bib_user`,
 *    the four `is_default = 1` rows — so a new account starts with what registration would
 *    have given it, **and ticks Active**, without which an administrator can create an
 *    account that is silently unable to ever sign in. Nothing pre-fills the role form.
 *
 * ## The shared form instance, and why fetching it here is correct
 *
 * `EditUserForm::class` is a **shared** service and `setValidatorsForCreate()` mutates it,
 * adding the two `NoRecordExists` validators that make `username` and `displayName`
 * unique. `App\Controller\UserEditController` fetches the same id and calls
 * `prepareForEdit()` on it. That is safe for the same reason it is safe on laminas — one
 * request dispatches one route, and the laminas container is rebuilt per request — but it
 * is safe by circumstance rather than by construction, so neither controller holds the
 * form as a constructor dependency. docs/strangler.md records the hazard.
 */
final class UserCreateController
{
    /** Route default naming which of the two records this route creates. */
    public const KIND = '_juser_create';

    public const USER = 'user';
    public const ROLE = 'user-role';

    /** kind => [form service, template, heading, gerund, id field for the log line] */
    private const PAGES = [
        self::USER => [
            EditUserForm::class,
            'juser/user-create.html.twig',
            'Create new user',
            'creating a user',
            'username',
        ],
        self::ROLE => [
            CreateRoleForm::class,
            'juser/role-create.html.twig',
            'Create new role',
            'creating a role',
            'roleId',
        ],
    ];

    public function __construct(
        private readonly UserAdmin $admin,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    /** @throws TwigError */
    public function __invoke(Request $request): Response
    {
        $kind = $request->attributes->get(self::KIND);
        if (! is_string($kind) || ! array_key_exists($kind, self::PAGES)) {
            throw new RuntimeException('A juser create route declared no known record kind.');
        }
        [$formId, $template, $heading, $gerund, $idField] = self::PAGES[$kind];

        $form = $this->form($formId, $kind);

        if ($request->isMethod('POST')) {
            //->all(), to match SionController's `getPost()->toArray()`.
            /** @var array<string, mixed> $posted */
            $posted = $request->request->all();
            $form->setData($posted);

            if ($form->isValid()) {
                /** @var array<string, mixed> $data */
                $data = $form->getData();
                $done = $this->write($kind, $data, $gerund, $idField);
                if (null !== $done) {
                    return $done;
                }
            } else {
                //`nowMessenger`, not a flash: the form is re-rendered on *this* response.
                $this->admin->now(NowMessenger::NAMESPACE_ERROR, 'Error in form submission, please review.');
            }
        } elseif (self::USER === $kind) {
            $form->get('rolesList')->setValue(array_keys($this->admin->table()->getDefaultRoles()));
            /*
             * **Active, ticked.** The element declares `'value' => 0`, so without this the
             * box renders unchecked and an administrator who does not notice it creates an
             * account at `state = 0`.
             *
             * That was harmless until 2026-08-21 and is a permanent silent lockout now:
             * `state = 0` means "may not sign in", so the account is refused a magic link,
             * refused a link already in flight, and told nothing either time — the page
             * still says "check your email", because it must not reveal which accounts are
             * disabled. Nothing would ever surface it, since the previous behaviour was for
             * the first redemption to activate the account, and that is exactly what was
             * removed.
             *
             * So the default has to match what creating an account means. Unticking it is
             * still available and is now an explicit choice: create it, do not let them in
             * yet.
             */
            $form->get('active')->setValue(1);
        }

        //**No `action` attribute**, which is why the templates call `form_open(form, '')`.
        //Neither laminas view calls `setAttribute('action', …)`, so `openTag()` emits none
        //and the browser posts to the current URL — and `action=""` is not the same thing
        //to a browser resolving a relative reference, which is what an empty string passed
        //to the renderer would otherwise become. See SionModel\Form\BootstrapFormRenderer::open().
        return new Response($this->twig->render($template, [
            'page_title' => $heading,
            'form'       => $form,
        ]));
    }

    /**
     * The write. A RedirectResponse on success, null to re-render the form with a message.
     *
     * @param array<string, mixed> $data
     */
    private function write(string $kind, array $data, string $gerund, string $idField): ?RedirectResponse
    {
        $table = $this->admin->table();

        try {
            $created = $table->createEntity($kind, $data);
        } catch (Throwable $e) {
            $this->admin->now(NowMessenger::NAMESPACE_ERROR, $this->admin->writeFailure($gerund, $e, [
                $idField => $data[$idField] ?? null,
            ]));

            return null;
        }

        //Only the user branch reads the return value — see the class docblock.
        if (self::USER === $kind && ! $created) {
            $this->admin->now(NowMessenger::NAMESPACE_ERROR, $this->admin->writeFailure($gerund, null, [
                $idField => $data[$idField] ?? null,
            ]));

            return null;
        }

        $this->admin->flash(
            FlashMessenger::NAMESPACE_SUCCESS,
            self::USER === $kind ? 'User successfully created.' : 'Role successfully created.'
        );

        return new RedirectResponse($this->urls->path('juser'), Response::HTTP_FOUND);
    }

    /**
     * The form, primed the way the laminas action primes it.
     *
     * `setValidatorsForCreate()` then `setName('create_user')` for the user form, in that
     * order and both on the instance the container hands back. The rename is not cosmetic:
     * it is what puts `name="create_user" id="create_user"` on the `<form>` instead of
     * `user_edit`, which is the only thing distinguishing the create form's markup from the
     * edit form's.
     *
     * @return FormInterface<array<string, mixed>>
     */
    private function form(string $formId, string $kind): FormInterface
    {
        /** @var FormInterface<array<string, mixed>> $form */
        $form = $this->admin->laminasForm($formId);

        if (self::USER === $kind) {
            /** @var EditUserForm $form */
            $form->setValidatorsForCreate();
            $form->setName('create_user');
        }

        return $form;
    }
}
