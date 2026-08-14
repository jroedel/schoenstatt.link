<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Sion\EntityDelete;
use App\Sion\SiteWideIdentifier;
use JTranslate\Controller\Plugin\NowMessenger;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function ctype_digit;
use function is_string;

/**
 * Every entity delete confirmation on the Symfony side: batch 8's seven routes behind one
 * class, the destructive counterpart of `EntityEditController`.
 *
 * `App\Sion\EntityDelete` holds the shared action and documents what it reproduces, which
 * routes are in the batch and which three delete routes are unreachable rather than skipped.
 * This class is the HTTP shell: the locale redirect, the id, the four refusal branches, the
 * POST/GET split and the one template.
 *
 * ## Why this one needs almost no per-route declaration
 *
 * Where `EntityEditController` reads nine route defaults, this reads three — the entity, its
 * id parameter, and whether that parameter is a site-wide identifier. There is no per-route
 * template, title or breadcrumb trail because the laminas page has none: every one of the
 * seven renders `sion-model/sion-model/delete.phtml`, whose entire body is an `<h1>` and a
 * three-element form, with no breadcrumbs at all. The heading is built from the entity key,
 * so `page_title` is derived here rather than declared.
 *
 * That heading is `'Delete ' . $entity` passed through the translator — a string assembled
 * by concatenation, which is normally the antipattern that files a phrase row per record.
 * Here it is bounded: the entity *key* is one of seven, and all seven rows already exist in
 * `trans_phrases`, filed by the laminas rendering, in the module text domains this batch's
 * routes declare (`Delete association` under `Schoenstatt`, `Delete publication` under
 * `Books`, …). Getting a route's text domain wrong would file a duplicate under `default`
 * rather than reuse one, which is the failure mode docs/translation.md describes.
 *
 * ## The destructive POST, and the two locks on Cancel
 *
 * This is the first ported route whose POST *destroys* a record. Everything that makes that
 * safe was already in place for the edit batch — the form comes out of SionModel with its
 * CSRF element, `App\Http\SessionListener` has started the laminas session before the
 * controller runs, and the write goes through `SionTable::deleteEntity()` exactly as the
 * laminas action does.
 *
 * What was *not* safe, on either front controller, was the Cancel button: it rendered as
 * `type="submit"` and the action validated only the CSRF token, so clicking Cancel deleted
 * the record. Fixed in SionModel — the button is a `type="button"` now — and this controller
 * carries the same server-side second lock the laminas action grew alongside it, because a
 * hand-crafted POST or a page cached from before the fix must not be able to destroy a row
 * by naming the button that says Cancel.
 */
final class EntityDeleteController
{
    public const ENTITY   = '_delete_entity';
    public const ID_PARAM = '_delete_id_param';
    /** One of `SchoenstattLinkIdentifier::ENTITY_*`, or absent for a numeric id. */
    public const ID_KIND  = '_delete_id_kind';

    private const TEMPLATE = 'sion-model/entity-delete.html.twig';

    public function __construct(
        private readonly EntityDelete $delete,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly ServiceBridge $laminas
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $entity  = $this->attribute($request, self::ENTITY);
        $idParam = $this->attribute($request, self::ID_PARAM);
        $route   = $request->attributes->get('_route');
        $route   = is_string($route) ? $route : '';
        //the laminas route name the ported one shadows — `.locale` is Symfony's suffix
        $laminasRoute = str_ends_with($route, '.locale') ? substr($route, 0, -7) : $route;

        /** @var mixed $raw */
        $raw   = $request->attributes->get($idParam);
        $rawId = is_string($raw) ? $raw : '';

        $redirect = LocalePrefix::redirect($request, $this->urls, $laminasRoute, [$idParam => $rawId]);
        if (null !== $redirect) {
            return $redirect;
        }

        //The four refusal branches, in the laminas action's order. The order is observable —
        //see App\Sion\EntityDelete's docblock on why a visitor lacking the per-row
        //permission hears about permission even for a record that does not exist.
        if (! $this->delete->isDeletable($entity)) {
            return $this->refuse($entity, 'This entity cannot be deleted, please check the configuration.');
        }

        $id = $this->resolveId($request, $rawId);
        if (null === $id) {
            //Unreachable through a declared route: every one constrains its parameter to
            //digits or to an identifier regex, and SiteWideIdentifier::toId() answers null
            //only for a string neither of those admits. Answered as "does not exist"
            //because that is what laminas would say once existsEntity() saw a null id.
            return $this->refuse($entity, "The entity you're trying to delete doesn't exists.");
        }

        if (! $this->delete->isDeleteAllowed($entity, $id) || ! $this->delete->deprecatedResourceAllows($entity)) {
            return $this->refuse($entity, 'You do not have permission to delete this entity.');
        }

        if (! $this->delete->exists($entity, $id)) {
            //Laminas sets a 401 here and then returns a redirect, which replaces it, so the
            //401 never reaches the client. Reproducing the observable answer, not the dead
            //line — see App\Sion\EntityDelete.
            return $this->refuse($entity, "The entity you're trying to delete doesn't exists.");
        }

        $form = $this->delete->form();

        if ($request->isMethod('POST')) {
            //The second lock on Cancel, before the CSRF check for the same reason the
            //laminas action puts it there: a cancellation is a no-op, so a stale token on
            //one should send the visitor back rather than raise a form error about a thing
            //they asked not to do.
            if ($request->request->has('cancel')) {
                return new RedirectResponse($this->redirectTarget($entity), Response::HTTP_FOUND);
            }

            $form->setData($request->request->all());

            if ($form->isValid()) {
                $this->delete->delete($entity, $id);
                $this->flash(FlashMessenger::NAMESPACE_SUCCESS, $this->delete->deletedMessage());

                return new RedirectResponse($this->redirectTarget($entity), Response::HTTP_FOUND);
            }

            //`nowMessenger`, which renders into this response rather than the next one, and
            //reached through the shared ControllerPluginManager plugin the layout's helper
            //reads from — the arrangement PersonsController and LiteratureController use.
            //Not the flash messenger: the laminas action puts the message on the page it is
            //re-rendering, and a flash would surface it on some later page instead.
            $this->nowMessage(NowMessenger::NAMESPACE_ERROR, 'Error in form submission, please review.');
            //401 is the wrong code for a failed CSRF and it is reproduced anyway. Unlike the
            //not-found 401 above, this branch renders rather than redirects, so the status
            //does reach the client and changing it would be a behaviour change on a route
            //whose whole point is that it behaves as it always did. Filed in docs/BACKLOG.md.
            $status = Response::HTTP_UNAUTHORIZED;
        }

        return new Response(
            $this->twig->render(self::TEMPLATE, [
                //Both the <title>, through the layout, and the <h1>, through the template.
                //One string, therefore one phrase row per entity, which is what laminas
                //already filed.
                'page_title'  => 'Delete ' . $entity,
                'form'        => $form,
                'form_action' => $this->urls->path($laminasRoute, [$idParam => $rawId]),
            ]),
            $status ?? Response::HTTP_OK
        );
    }

    /**
     * The numeric id behind the route parameter, or null when it is not one.
     *
     * Same shape as `EntityEditController::resolveId()`: four of the seven routes carry a
     * site-wide identifier and three carry digits.
     */
    private function resolveId(Request $request, string $raw): ?int
    {
        /** @var mixed $kind */
        $kind = $request->attributes->get(self::ID_KIND);
        if (is_string($kind) && '' !== $kind) {
            return SiteWideIdentifier::toId($kind, $raw);
        }

        return ctype_digit($raw) && '0' !== $raw ? (int) $raw : null;
    }

    /** A refusal branch: the flash laminas sets, then its redirect. */
    private function refuse(string $entity, string $message): RedirectResponse
    {
        $this->flash(FlashMessenger::NAMESPACE_ERROR, $message);

        return new RedirectResponse($this->redirectTarget($entity), Response::HTTP_FOUND);
    }

    /**
     * Where every exit from this action goes — `redirectAfterDelete()`, which takes the same
     * target whether the delete happened or was refused. (Its `$actionWasSuccessful`
     * argument is declared and never read.)
     */
    private function redirectTarget(string $entity): string
    {
        $route = $this->delete->redirectRoute($entity);
        if (null === $route) {
            //`sion_model.default_redirect_route`, which is `welcome`. All seven entities
            //declare a delete redirect, so this is unreachable; refusing loudly rather than
            //reading a config key nothing uses is the choice EntityEditController made for
            //the same fallback.
            throw new RuntimeException(
                "Entity '$entity' declares neither a delete redirect route nor an index route."
            );
        }

        return $this->urls->path($route);
    }

    /** A flash message in the laminas session, where the page redirected *to* looks for it. */
    private function flash(string $namespace, string $message): void
    {
        (new FlashMessenger())->setNamespace($namespace)->addMessage($message);
    }

    /** @see PersonsController::nowMessage() — the same shared plugin the layout renders from. */
    private function nowMessage(string $namespace, string $message): void
    {
        /** @var NowMessenger $messenger */
        $messenger = $this->laminas->get('ControllerPluginManager')->get('nowMessenger');
        $messenger->setNamespace($namespace)->addMessage($message);
    }

    private function attribute(Request $request, string $name): string
    {
        /** @var mixed $value */
        $value = $request->attributes->get($name);
        if (! is_string($value) || '' === $value) {
            throw new RuntimeException("Route default '$name' is missing or not a string.");
        }

        return $value;
    }
}
