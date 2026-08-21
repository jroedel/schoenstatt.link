<?php

declare(strict_types=1);

namespace App\Controller;

use App\JUser\CookieExplainer;
use App\JUser\RedirectTarget;
use App\JUser\SignIn;
use App\Laminas\RouteUrl;
use JUser\Form\LoginForm;
use JUser\Model\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twig\Environment;
use Twig\Error\Error as TwigError;

use function is_array;
use function is_string;

/**
 * `/user` and `/user/login` — the front door.
 *
 * `LoginController::indexAction()` and `loginAction()`/`handleEmailRequest()`, ported.
 * Two routes and one class, because `/user` exists only to bounce a visitor to the form
 * and answering "where does the front door go" twice would be worse than one branch.
 *
 * ## The response is identical whether or not the address is known
 *
 * The single most important property of this page, and it is achieved by
 * `issueAndSend()` swallowing every outcome rather than by anything here: an unknown
 * address is registered, a known one gets a link, a deactivated one gets nothing, a
 * throttled one gets nothing, and the mailer throwing is logged and dropped. All five
 * render the same "check your email" page. Anything added to this method that reports what
 * happened turns the form into an account oracle —
 * `test/Smoke/AuthSmokeTest::testUniformResponseForUnknownAndKnownEmail` and
 * `testADeactivatedAccountIsSentNoLinkAndIsNotToldSo` are what hold that line.
 *
 * ## The destination travels twice, deliberately
 *
 * In the session *and* in the emailed link. The session is the better channel when it
 * survives — nothing about the destination is then visible or editable — but it only
 * survives when the link is opened in the browser that asked for it, and the ordinary case
 * is asking on a desktop and clicking on a phone. So the session is the optimisation and
 * the link is the guarantee. `App\Controller\VerifyController` prefers whichever arrived.
 *
 * ## The consent gate
 *
 * Checked before anything else, and it renders the explainer **at this URL with a 200**
 * rather than redirecting — see `App\JUser\SignIn::wantsCookiesFirst()` for why that
 * matters to an emailed link. Reproduces `Application\View\GdprStrategy::onRoute()`, which
 * does not run for a ported route.
 */
final class SignInController
{
    public function __construct(
        private readonly SignIn $signIn,
        private readonly RedirectTarget $targets,
        private readonly CookieExplainer $explainer,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    /** GET /user — nothing lives here. */
    public function index(): RedirectResponse
    {
        return $this->signIn->authService()->hasIdentity()
            ? $this->toRoute($this->signIn->loginRedirectRoute())
            : $this->toRoute('zfcuser/login');
    }

    /**
     * GET /user/login renders the form; POST issues and mails a link.
     *
     * @throws TwigError
     */
    public function form(Request $request): Response
    {
        if ($this->signIn->wantsCookiesFirst($request)) {
            return $this->explainer->response();
        }

        if ($this->signIn->authService()->hasIdentity()) {
            return $this->toRoute($this->signIn->loginRedirectRoute());
        }

        $form = new LoginForm();

        $redirect = $this->targets->valid($request->query->get('redirect'));
        if (null !== $redirect) {
            $form->get('redirect')->setValue($redirect);
        }

        if (! $request->isMethod('POST')) {
            return $this->renderForm($form);
        }

        /** @var array<string, mixed> $posted */
        $posted = $request->request->all();
        $form->setData($posted);
        if (! $form->isValid()) {
            return $this->renderForm($form);
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData();
        /** @var mixed $email */
        $email = $data['email'] ?? null;
        if (! is_string($email) || '' === $email) {
            //unreachable through the input filter, which requires the field and validates
            //it as an address; belt and braces because everything below assumes a string
            return $this->renderForm($form);
        }

        $redirect = $this->targets->valid($data['redirect'] ?? null);
        if (null !== $redirect) {
            $this->rememberDestination($redirect);
        }

        $this->issueAndSend($email, $redirect);

        return new Response($this->twig->render('juser/check-email.html.twig', [
            'page_title'         => 'Check your email',
            'email'              => $email,
            'expiration_minutes' => $this->signIn->tokens()->getWebTokenExpirationMinutes(),
        ]));
    }

    /** @throws TwigError */
    private function renderForm(LoginForm $form): Response
    {
        return new Response($this->twig->render('juser/login.html.twig', [
            //the raw string: `headTitle()` translates what it holds when it renders, so
            //the .phtml passing an already-translated value made the page translate its
            //own output and file the result as a phrase — "Registrati" and "Registrar"
            //became keys in their own right. The layout translates this.
            'page_title' => 'Sign in',
            'form'       => $form,
        ]));
    }

    /**
     * Look the address up (registering it if new) and mail a link.
     *
     * **Failures are swallowed on purpose**: the caller must not learn anything. Every
     * `return` in here produces the same page as success.
     */
    private function issueAndSend(string $email, ?string $redirect): void
    {
        $logger = $this->signIn->logger();
        try {
            $user = $this->signIn->table()->findByEmail($email);
            if (! $user instanceof User) {
                //open registration: an unknown address simply becomes an account, created
                //active and unverified — see UserTable::createUserFromEmail()
                $created = $this->signIn->table()->createUserFromEmail($email);
                if (! is_array($created)) {
                    return;
                }
                $user = new User($created);
            }

            //A deactivated account is sent nothing, silently. `state` means "may sign in"
            //and the answer here must not distinguish it from any other outcome.
            if (1 != $user->getState()) {
                $logger?->info(
                    'JUser: Declined to issue a sign-in link for a deactivated account.',
                    ['userId' => $user->getId()]
                );

                return;
            }

            if (! $this->signIn->tokens()->mayIssueToken($user)) {
                $logger?->info('JUser: Throttled a sign-in link request.', ['userId' => $user->getId()]);

                return;
            }

            $token = $this->signIn->tokens()->issueWebToken($user);
            $this->signIn->mailer()->sendLoginLinkEmail(
                $user,
                $token,
                $this->signIn->tokens()->getWebTokenExpirationMinutes(),
                $redirect
            );
        } catch (Throwable $e) {
            $logger?->error('JUser: Failed to issue a sign-in link.', ['exception' => $e]);
        }
    }

    /**
     * Put the destination in the session as well as in the link.
     *
     * `Laminas\Session\Container` rather than the Symfony session, because
     * `App\Controller\VerifyController` and the unported laminas controller must read the
     * same slot — this is the one piece of state the two front controllers share on this
     * surface, and it is why the laminas `LoginController` can go on answering these paths
     * if the Symfony routes are ever removed.
     */
    private function rememberDestination(string $redirect): void
    {
        //array access, not `->redirect`: Laminas\Session\Container resolves properties
        //through ArrayObject's magic, which PHPStan level 8 reads as an undefined property.
        //Same treatment as App\Controller\ApiTokensController's one-shot token slot.
        VerifyController::destinationContainer($this->signIn)[VerifyController::DESTINATION] = $redirect;
    }

    private function toRoute(string $route): RedirectResponse
    {
        return new RedirectResponse($this->urls->path($route), Response::HTTP_FOUND);
    }
}
