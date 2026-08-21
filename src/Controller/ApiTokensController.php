<?php

declare(strict_types=1);

namespace App\Controller;

use App\JUser\UserAdmin;
use App\Laminas\RouteUrl;
use Exception;
use JTranslate\I18n\TranslatableMessage;
use JUser\Form\IssueApiTokenForm;
use JUser\Form\RevokeApiTokenForm;
use JUser\Model\User;
use JUser\Service\ApiTokenService;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\Session\Container as SessionContainer;
use Laminas\Session\ManagerInterface as SessionManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

use function is_array;
use function is_int;
use function is_string;

/**
 * The API-token screen: `/users/{user_id}/api-tokens` and its POST-only revoke twin.
 *
 * Two routes, two methods, because the second is not a view of the first: it takes a
 * `{token_id}` of its own and answers nothing but a redirect.
 *
 * This is the only screen in the application that mints a credential, and the three things
 * that make that safe are all reproduced rather than reimplemented:
 *
 * 1. **`mayIssueForUser()` is re-checked on the POST**, not merely consulted to decide
 *    whether to draw a button. The GET decides what to render; this decides whether to mint
 *    a six-month bearer token, and a hand-crafted POST never saw the view at all.
 * 2. **Revocation is scoped by account as well as token.** `revoke($tokenId, $id, …)`, so a
 *    token id belonging to another account cannot be revoked from this account's page —
 *    which is also why "already revoked" is an *info* message rather than an error: the
 *    honest reading of a second submit is a double-click.
 * 3. **The freshly issued JWT never enters the message pipeline.** It travels in a one-shot
 *    session container, read and cleared by the render that follows the POST. See
 *    {@see issued()} for what that avoids, which is not hypothetical.
 *
 * ## The token is the one value on this surface that a port could leak
 *
 * `App\Http\SessionListener` has already started the laminas session by the time a ported
 * controller runs, so `Laminas\Session\Container` works here exactly as it does under
 * laminas-mvc — same session, same namespace, and a token written by one front controller
 * is readable by the other. That matters for the deploy window rather than for correctness:
 * a POST served by one and its redirect served by the other still shows the token once.
 */
final class ApiTokensController
{
    /** The container namespace, deliberately not LoginController's. See {@see issued()}. */
    private const SESSION_NAMESPACE = 'JUser\ApiToken';

    /** The one key inside it. */
    private const SLOT = 'issued';

    public function __construct(
        private readonly UserAdmin $admin,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    /** GET|POST — the screen, and the issue button. @throws TwigError */
    public function screen(Request $request): Response
    {
        $user = $this->admin->user($request);
        if (null === $user) {
            $this->admin->flash(FlashMessenger::NAMESPACE_ERROR, 'User not found.');

            return new RedirectResponse($this->urls->path('juser'), Response::HTTP_FOUND);
        }
        $id = (int) $user['userId'];

        $tokens   = $this->admin->apiTokens();
        $mayIssue = $tokens->mayIssueForUser($id);
        $form     = new IssueApiTokenForm();

        if ($request->isMethod('POST')) {
            $this->issue($request, $tokens, $form, $user, $id, $mayIssue);

            return $this->toScreen($id);
        }

        //Read and cleared in one act: the token is shown on exactly the render that
        //follows its issuance, and a refresh of that page must not show it again.
        //
        //Array access rather than `$container->issued`, which is what the laminas action
        //uses. `Laminas\Session\Container` extends `ArrayObject` and supports both; the
        //property form is a magic `__get` that no static analysis can see, and this file
        //holds itself to PHPStan level 8. Same session slot either way.
        $container  = $this->issued();
        /** @var mixed $stored */
        $stored     = $container[self::SLOT] ?? null;
        $justIssued = is_array($stored) ? $stored : null;
        unset($container[self::SLOT]);

        return new Response($this->twig->render('juser/api-tokens.html.twig', [
            'page_title'     => 'API Tokens',
            'user_id'        => $id,
            'user'           => $user,
            'display_name'   => $this->displayName($user),
            'tokens'         => $tokens->getTokensForUser($id),
            'form'           => $form,
            'revoke_form'    => new RevokeApiTokenForm(),
            'may_issue'      => $mayIssue,
            'issuable_roles' => $tokens->getIssuableRoles(),
            'lifetime_days'  => $tokens->getLifetimeDays(),
            'just_issued'    => $justIssued,
            'self_url'       => $this->urls->path('juser/user/api-tokens', ['user_id' => $id]),
        ]));
    }

    /** POST only — revoke one token. A non-POST is answered with the redirect and nothing else. */
    public function revoke(Request $request): Response
    {
        //Deliberately *not* `$this->admin->user()`: the laminas action reads the raw route
        //parameter and never loads the row, because everything it needs is the pair of ids
        //and the service checks that they belong together. Loading the account here would
        //add a query and a second not-found path to a route that only ever redirects.
        /** @var mixed $rawUser */
        $rawUser = $request->attributes->get('user_id');
        /** @var mixed $rawToken */
        $rawToken = $request->attributes->get('token_id');
        $id       = is_string($rawUser) || is_int($rawUser) ? (int) $rawUser : 0;
        $tokenId  = is_string($rawToken) || is_int($rawToken) ? (int) $rawToken : 0;

        if (! $request->isMethod('POST')) {
            return $this->toScreen($id);
        }

        $form = new RevokeApiTokenForm();
        /** @var array<string, mixed> $posted */
        $posted = $request->request->all();
        $form->setData($posted);
        if (! $form->isValid()) {
            $this->admin->flash(FlashMessenger::NAMESPACE_ERROR, 'That request expired, please try again.');

            return $this->toScreen($id);
        }

        if ($this->admin->apiTokens()->revoke($tokenId, $id, $this->admin->actingUserId())) {
            $this->admin->flash(
                FlashMessenger::NAMESPACE_SUCCESS,
                'Token revoked. It stops working on its next request.'
            );
        } else {
            $this->admin->flash(
                FlashMessenger::NAMESPACE_INFO,
                'That token was already revoked, or does not belong to this account.'
            );
        }

        return $this->toScreen($id);
    }

    /**
     * The POST branch of the screen. Reports its outcome as a flash and returns nothing:
     * every path ends in the same redirect.
     *
     * @param array<string, mixed> $user
     */
    private function issue(
        Request $request,
        ApiTokenService $tokens,
        IssueApiTokenForm $form,
        array $user,
        int $id,
        bool $mayIssue
    ): void {
        if (! $mayIssue) {
            $this->admin->flash(
                FlashMessenger::NAMESPACE_ERROR,
                'This account may not be issued an API token.'
            );

            return;
        }

        /** @var array<string, mixed> $posted */
        $posted = $request->request->all();
        $form->setData($posted);
        if (! $form->isValid()) {
            $this->admin->flash(FlashMessenger::NAMESPACE_ERROR, 'Error in form submission, please review.');

            return;
        }

        /** @var mixed $data */
        $data = $form->getData();
        /** @var mixed $label */
        $label = is_array($data) ? ($data['label'] ?? null) : null;
        $label = is_string($label) ? $label : null;

        try {
            /** @var mixed $token */
            $token = $tokens->issue(new User($user), $label, $this->admin->actingUserId());
        } catch (Exception $e) {
            $logger = $this->admin->logger();
            if (null !== $logger) {
                $logger->error('JUser: Failed to issue an API token.', [
                    'userId'    => $id,
                    'exception' => $e,
                ]);
            }
            //A TranslatableMessage, so the exception text is a *parameter* and not part of
            //the phrase key — one row for the sentence rather than one per distinct
            //failure. Same reasoning as the token itself, one step weaker: an unbounded
            //string in a translated message is a phrase-table row per value.
            $this->admin->flash(
                FlashMessenger::NAMESPACE_ERROR,
                new TranslatableMessage('Could not issue a token: %s', [$e->getMessage()])
            );

            return;
        }

        /** @var mixed $jwt */
        $jwt = is_array($token) ? ($token['jwt'] ?? null) : null;
        if (is_string($jwt)) {
            $this->issued()[self::SLOT] = ['jwt' => $jwt, 'label' => $label];
        }

        $this->admin->flash(FlashMessenger::NAMESPACE_SUCCESS, 'Token issued.');
    }

    /**
     * The one-shot session slot a freshly minted JWT travels in, between the POST that
     * issues it and the GET that displays it.
     *
     * A session container rather than the flash messenger, and the reason is a real
     * incident rather than a preference. The messengers translate the *finished* message at
     * render time, and a translator miss is exactly what files a phrase — so an earlier
     * version that appended the JWT to the message put four real tokens into a table any
     * `sch_api_translator` account can read, and copied them again into the English
     * translation and the exported catalog on disk. On this screen, of all screens, whose
     * own copy says the token is not stored. A `TranslatableMessage` parameter fixed that
     * by keeping the token out of the key; keeping it out of the message pipeline
     * altogether removes the class of mistake rather than the instance.
     *
     * Its own namespace rather than `JUser\Controller\LoginController`'s, so that nothing
     * which walks that container looking for a post-login redirect ever sees a credential.
     *
     * @return SessionContainer<string, mixed>
     */
    private function issued(): SessionContainer
    {
        /** @var SessionManagerInterface $manager */
        $manager = $this->admin->sessionManager();

        return new SessionContainer(self::SESSION_NAMESPACE, $manager);
    }

    /**
     * `$user['displayName'] ?: ($user['username'] ?: $user['email'])` from the laminas
     * view — the first of the three that is non-empty.
     *
     * @param array<string, mixed> $user
     */
    private function displayName(array $user): string
    {
        foreach (['displayName', 'username', 'email'] as $field) {
            /** @var mixed $value */
            $value = $user[$field] ?? null;
            if (is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return '';
    }

    private function toScreen(int $id): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->path('juser/user/api-tokens', ['user_id' => $id]),
            Response::HTTP_FOUND
        );
    }
}
