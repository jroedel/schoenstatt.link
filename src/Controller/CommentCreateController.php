<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\ServiceBridge;
use App\Sion\CommentPredicates;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use SionModel\Db\Model\PredicatesTable;
use SionModel\Form\CommentForm;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function is_string;
use function str_starts_with;
use function ucwords;

/**
 * POST /comments/create/{entity}/{entity_id}[/{kind}] — leaving a comment.
 *
 * **The unblocker of this batch.** Three of the four entity show pages ported
 * alongside it render a comment list and a `CommentForm`, and a form whose action
 * still fell through to `legacy` would have been a page that looks ported and posts
 * somewhere else. The predicates that decide which entities have comments are *data*,
 * not config — `SELECT PredicateKind FROM predicates WHERE SubjectEntityKind='comment'`
 * answers `comment-comments-composition`, `comment-comments-event`,
 * `comment-comments-file`, `comment-comments-text` and `comment-reviews-publication` —
 * which is why docs/strangler.md's claim that only composition and text were blocked
 * was one entity short.
 *
 * ## POST only, and why that is a reproduction rather than a narrowing
 *
 * The laminas route carries no method constraint, so a GET reaches
 * `SionController::createAction()`, which renders a view whose template is
 * `sion-model/comment/create`. **That template does not exist** — the partial is
 * `sion-model/comments/create`, plural, and no `template_map` entry bridges the two —
 * so laminas throws and the page is a 500 today. Measured on the capsule against a
 * signed-in session: `GET /en/comments/create/composition/1` → 500, 11,953 bytes of
 * exception page.
 *
 * Reproducing *that* would mean porting a five-year-old broken page. Answering 405
 * with an `Allow: POST` header says the same thing accurately, and matches how the v3
 * API already answers a verb it does not serve. The route declares the constraint, so
 * Symfony emits the 405 before this class is reached; what is here is the POST.
 *
 * ## The write is the application's, not a copy
 *
 * `PredicatesTable::createEntity('comment', ...)` is what
 * `SionController::createEntityPostFormValidation()` calls, and it is called here with
 * the same four route-derived fields `CommentController` adds — `kind`, `status`,
 * `entity`, `entityId`. That is what writes the `comments` row *and* the
 * `relationships` row linking it to the entity, through the entity spec's
 * `postprocessComment` handler. Nothing about the write is reimplemented.
 *
 * ## The open redirect is reproduced, not introduced
 *
 * `CommentController::redirectAfterCreate()` redirects to `$data['redirect']` — a
 * hidden form field — with a `@todo confirm that redirect is a valid route` sitting
 * over it. An attacker who can get a signed-in visitor to submit a crafted form can
 * therefore bounce them anywhere. Two things already narrow it: the form carries a CSRF
 * token, so the POST has to come from a page this site rendered, and `route/comments/create`
 * is guarded `user`, so the visitor is signed in. It is still an open redirect, and this
 * port does not fix it — the batch's contract is identical behaviour — but it does not
 * widen it either: `sameOrigin()` below refuses only what laminas would also have
 * refused, an absent or empty value. The fix is recorded in docs/BACKLOG.md.
 */
final class CommentCreateController
{
    /**
     * The `kind` the route falls back to, from the laminas route's own defaults.
     * `PredicatesTable::COMMENT_KINDS` is the closed set the route constraint allows.
     */
    private const DEFAULT_KIND = PredicatesTable::COMMENT_KIND_COMMENT;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly CommentPredicates $predicates
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $entity   = $request->attributes->get('entity');
        $entityId = $request->attributes->get('entity_id');
        $kind     = $request->attributes->get('kind') ?? self::DEFAULT_KIND;

        if (! is_string($entity) || ! is_string($entityId) || ! is_string($kind)) {
            return $this->badRequest();
        }

        //The entity has to *have* a comment predicate, or createEntity writes a
        //comments row that nothing will ever link to or display. laminas never checks:
        //SionController reads the predicate map on the show page and simply does not
        //render a form where there is none, so the check only ever mattered for a
        //hand-made POST. Here it is explicit, because a Symfony route is reachable
        //without having rendered the page that would have hidden the form.
        if (null === $this->predicates->forEntity($entity)) {
            return $this->badRequest();
        }

        $form = $this->form();
        $form->setData($request->request->all());

        if (! $form->isValid()) {
            //laminas re-renders the form with a nowMessenger error. It cannot here —
            //this controller owns no page; the form lives on whichever show page
            //rendered it. So the visitor goes back to that page, which re-renders an
            //empty form. What is lost is the typed text on a rejected submission, and
            //the only way to reject one today is a bad or expired CSRF token: the
            //`comment` field has no validator at all (recorded in
            //test/Fuzz/known-form-gaps.php, three entries) and `redirect` has none
            //either. An expired token is a page left open past the 900-second timeout,
            //where re-rendering the stale form would fail again on the same token.
            return $this->back($request);
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData();

        //the four fields CommentController::createEntityPostFormValidation() adds from
        //the route before handing the data to the table
        $data['kind']     = $kind;
        $data['status']   = PredicatesTable::COMMENT_STATUS_PUBLISHED;
        $data['entity']   = $entity;
        $data['entityId'] = $entityId;

        /** @var PredicatesTable $table */
        $table = $this->laminas->get(PredicatesTable::class);
        $newId = $table->createEntity('comment', $data);

        if (! $newId) {
            //SionController sets a nowMessenger error and re-renders. Same reasoning as
            //above: there is no page here to re-render, so the visitor returns to the
            //one they came from, and gets a flash instead of a now-message.
            $this->flash(FlashMessenger::NAMESPACE_ERROR, 'Error in form submission, please review.');

            return $this->back($request);
        }

        //`ucwords($entity) . ' successfully created.'` is what SionController builds,
        //so the phrase really is per-entity — "Composition successfully created.",
        //"Text successfully created." — and reproducing it means reproducing that.
        $this->flash(FlashMessenger::NAMESPACE_SUCCESS, ucwords($entity) . ' successfully created.');

        return $this->back($request);
    }

    /**
     * Where `redirectAfterCreate()` sends the visitor: the form's own `redirect`
     * field, which every show page fills with the URL of itself.
     */
    private function back(Request $request): RedirectResponse
    {
        $redirect = $request->request->get('redirect');
        $target   = is_string($redirect) && $this->sameOrigin($redirect)
            ? $redirect
            //laminas' fallback is SionController::redirectAfterCreate()'s, which needs
            //an entity spec this route has no page for. The referring page is the
            //nearest honest answer, and '/' is where a POST with no context belongs.
            : ($request->headers->get('referer') ?? '/');

        return new RedirectResponse($target, Response::HTTP_FOUND);
    }

    /**
     * Whether a redirect target is one laminas would also have followed.
     *
     * This is **not** the open-redirect fix — see the class docblock. A path-absolute
     * value (`/en/SL500001C`) is what every rendering of this form produces and is
     * accepted; anything else falls through to the referer. That is a narrowing of the
     * laminas behaviour in the one case nobody relies on and a reproduction in the one
     * everybody does, which is as far as a port should go on its own authority.
     * `//evil.example` is refused because a protocol-relative URL is path-absolute to a
     * naive check and off-site to a browser.
     */
    private function sameOrigin(string $target): bool
    {
        return str_starts_with($target, '/') && ! str_starts_with($target, '//');
    }

    /**
     * `new CommentForm()`, and that is the application's own answer rather than a
     * shortcut taken here.
     *
     * `SionModel\Controller\SionControllerFactory` resolves an entity's
     * `create_action_form` out of the container *if it is registered there* and calls
     * `new` otherwise. `CommentForm::class` appears exactly once in the merged config —
     * as the `comment` entity's `create_action_form` — and in no `factories` or
     * `invokables` list, so laminas takes the `new` branch too. So does
     * `sion-model/comments/create.phtml`, which builds one directly.
     *
     * Constructing it needs nothing: `SionForm::__construct()` adds the CSRF element
     * and the phone filter specs, and none of that reaches
     * `GlobalAdapterFeature::setStaticAdapter()` — the unreproduced JUser static
     * adapter that still blocks the user and translation forms. `CommentForm` is the
     * second form in this application, after `AssociationForm`, that turns out not to
     * need it.
     */
    private function form(): CommentForm
    {
        return new CommentForm();
    }

    /**
     * A flash message in the laminas session, so the show page redirected *to* finds
     * it where its layout looks — whichever front controller renders that page.
     */
    private function flash(string $namespace, string $message): void
    {
        $messenger = new FlashMessenger();
        $messenger->setNamespace($namespace)->addMessage($message);
    }

    private function badRequest(): Response
    {
        return new Response(
            'Not a commentable entity.',
            Response::HTTP_BAD_REQUEST,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}
