<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Sion\EntityEdit;
use App\Sion\SiteWideIdentifier;
use Laminas\Form\Element\Select;
use Laminas\Form\FormInterface;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;
use function is_int;
use function is_string;

/**
 * Every entity edit form on the Symfony side: ten routes of batch 7 behind one class.
 *
 * ## Why one controller and not ten
 *
 * `ContentPageController` established the pattern for five static pages, and the argument
 * is the same here and stronger: on the laminas side these ten pages are *one* method,
 * `SionController::editAction()`, reached through ten controllers that mostly add nothing
 * to it. Ten Symfony controllers would have ported the dispatch table as duplication. What
 * genuinely differs per page is declared in `config/symfony/routes.php` — the entity, how
 * its id is spelled, the template, the title, the breadcrumb trail — and what differs
 * *behaviourally* is the three hooks below.
 *
 * `App\Sion\EntityEdit` holds the shared action; this class is the HTTP shell around it:
 * the locale redirect, the id, the POST/GET branch, the flashes and the template.
 *
 * ## The three per-entity behaviours, and where each lives
 *
 * 1. **The id's spelling.** Seven routes carry a numeric route parameter
 *    (`person_id`, `role_id`, …); three carry a site-wide identifier in `sw_id` and are
 *    the entities whose laminas controllers override `getEntityIdParam()` to translate it.
 *    `ID_KIND` says which, and `App\Sion\SiteWideIdentifier` does the translation — the
 *    same class the show pages use.
 * 2. **The redirect after a successful write.** `EntityEdit::redirectTarget()` reproduces
 *    `redirectAfterEdit()`'s four-branch priority from the entity spec, which is right for
 *    eight of the ten. `text` and `dictionary-entry` override `redirectAfterEdit()` in
 *    their laminas controllers and so declare `REDIRECT_ROUTE` here instead.
 * 3. **Extra view variables.** `publication` needs the selectize value options
 *    `PublicationsController::injectPublicationValueOptions()` adds, and `person` needs the
 *    name its template puts in the page title. `EXTRA_VARIABLES` names a method on this
 *    class rather than allowing arbitrary closures in route config, so every one of them
 *    is greppable.
 *
 * ## What it deliberately does not reproduce
 *
 * `TextsController::getEntityObject()` calls `getObject('text', $id)` **without**
 * `$failSilently`, where the base method passes `true`. So on laminas a well-formed
 * identifier for a text that does not exist throws, and `/SL499999T/edit` is a 500;
 * `EntityEdit::load()` keeps the silent form and answers the not-found flash and redirect
 * instead. That is an improvement rather than a reproduction, taken knowingly: the other
 * nine entities already behave that way, and reproducing a 500 to preserve symmetry with
 * one controller's missing argument is not a trade worth making. It is only reachable by
 * typing an identifier that matches the regex and names nothing.
 */
final class EntityEditController
{
    public const ENTITY          = '_edit_entity';
    public const ID_PARAM        = '_edit_id_param';
    /** One of `SchoenstattLinkIdentifier::ENTITY_*`, or absent for a numeric id. */
    public const ID_KIND         = '_edit_id_kind';
    public const TEMPLATE        = '_edit_template';
    public const PAGE_TITLE      = '_edit_page_title';
    public const BREADCRUMBS     = '_edit_breadcrumbs';
    /** Overrides `EntityEdit::redirectTarget()` for the two entities whose controllers do. */
    public const REDIRECT_ROUTE  = '_edit_redirect_route';
    /** Names a private method below, e.g. `publicationValueOptions`. */
    public const EXTRA_VARIABLES = '_edit_extra_variables';

    public function __construct(
        private readonly EntityEdit $edit,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
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
        $rawId = is_string($raw) || is_int($raw) ? (string) $raw : '';

        $redirect = LocalePrefix::redirect($request, $this->urls, $laminasRoute, [$idParam => $rawId]);
        if (null !== $redirect) {
            return $redirect;
        }

        $id = $this->resolveId($request, $rawId);
        if (null === $id) {
            return $this->notFound($entity, 0);
        }

        $object = $this->edit->load($entity, $id);
        if (null === $object) {
            return $this->notFound($entity, $id);
        }

        $form = $this->edit->form($entity);

        if ($request->isMethod('POST')) {
            //->all() rather than the request object, to match
            //SionController::getPostDataForEditAction(), which hands the form
            //`getPost()->toArray()`.
            $form->setData($request->request->all());

            if ($form->isValid()) {
                /** @var array<string, mixed> $data */
                $data    = $form->getData();
                $updated = $this->edit->update($entity, $id, $data);

                $this->flash(FlashMessenger::NAMESPACE_SUCCESS, $this->edit->updatedMessage($entity));

                return new RedirectResponse(
                    $this->successTarget($request, $entity, $object, $updated),
                    Response::HTTP_FOUND
                );
            }

            //`nowMessenger` on laminas, which renders into the same flash region on this
            //request rather than the next. The Twig layout reads it from the same place.
            $this->flash(FlashMessenger::NAMESPACE_ERROR, 'Error in form submission, please review.');
        } else {
            $form->setData($object);
        }

        return new Response($this->twig->render($this->attribute($request, self::TEMPLATE), [
            'page_title'  => $this->attribute($request, self::PAGE_TITLE),
            'breadcrumbs' => $this->breadcrumbs($request, $object, $rawId),
            'form'        => $form,
            'form_action' => $this->urls->path($laminasRoute, [$idParam => $rawId]),
            'entity'      => $object,
            'entity_id'   => $id,
        ] + $this->extraVariables($request, $entity, $object, $form)));
    }

    /**
     * The numeric id behind the route parameter, or null when it is not one.
     *
     * Three routes carry a site-wide identifier and seven carry digits. The route
     * constraint has already refused anything else in both cases, so this is the second of
     * two checks — but `SiteWideIdentifier::toId()` is consulted rather than trusted,
     * because the laminas controllers it reproduces *throw* on a bad identifier and a
     * Symfony route should answer the not-found page.
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

    /**
     * Where a successful write sends the visitor.
     *
     * `REDIRECT_ROUTE` first, for the two entities whose laminas controllers override
     * `redirectAfterEdit()`; otherwise the spec-driven priority chain.
     *
     * @param array<string, mixed> $object  the row as it was loaded, pre-write
     * @param array<string, mixed> $updated the row `updateEntity()` returned
     */
    private function successTarget(Request $request, string $entity, array $object, array $updated): string
    {
        /** @var mixed $declared */
        $declared = $request->attributes->get(self::REDIRECT_ROUTE);
        if (is_array($declared)) {
            /** @var array{0: string, 1: array<string, string>} $declared */
            return $this->urls->path($declared[0], $this->fill($declared[1], $updated, $object));
        }

        $target = $this->edit->redirectTarget($entity, $object, $updated);
        if (null === $target) {
            //`sion_model.default_redirect_route`, which editAction() falls back to. No
            //entity in this batch reaches it — every one declares either a show route or
            //an index route — so rather than read a config key nothing uses, refuse
            //loudly if that ever changes.
            throw new RuntimeException(
                "Entity '$entity' has no show route and no index route to redirect to after an edit."
            );
        }

        return $this->urls->path($target[0], $target[1]);
    }

    /**
     * A declared parameter map filled from the updated row, falling back to the loaded one.
     *
     * @param array<string, string> $map     routeParam => entityField
     * @param array<string, mixed>  $updated
     * @param array<string, mixed>  $loaded
     * @return array<string, string>
     */
    private function fill(array $map, array $updated, array $loaded): array
    {
        $params = [];
        foreach ($map as $routeParam => $field) {
            /** @var mixed $value */
            $value = $updated[$field] ?? $loaded[$field] ?? null;
            $params[$routeParam] = is_scalar($value) ? (string) $value : '';
        }

        return $params;
    }

    /**
     * The breadcrumb trail, with `{name}` in any label replaced by the record's own name.
     *
     * A crumb whose label is the record's name passes `'translate' => false`, for the
     * reason `AssociationEditController` records: the layout translates crumbs, and a
     * translator miss is what files a phrase — so translating a record name would put one
     * row per record into the phrase table, permanently, and stale it the moment a
     * moderator renames the row.
     *
     * @param array<string, mixed> $object
     * @return list<array<string, mixed>>
     */
    private function breadcrumbs(Request $request, array $object, string $rawId): array
    {
        /** @var mixed $declared */
        $declared = $request->attributes->get(self::BREADCRUMBS);
        if (! is_array($declared)) {
            return [];
        }

        $name = $this->recordName($object);
        $trail = [];
        foreach ($declared as $crumb) {
            if (! is_array($crumb)) {
                continue;
            }
            /** @var array<string, mixed> $crumb */
            $label = $crumb['label'] ?? '';
            if (is_string($label) && str_contains($label, '{name}')) {
                $crumb['label']     = str_replace('{name}', $name, $label);
                $crumb['translate'] = false;
            }
            if (isset($crumb['route']) && is_string($crumb['route'])) {
                /** @var mixed $params */
                $params = $crumb['params'] ?? [];
                $crumb['href'] = $this->urls->path(
                    $crumb['route'],
                    is_array($params) ? $this->fill($this->stringMap($params), $object, $object) : []
                );
                unset($crumb['route'], $crumb['params']);
            }
            $trail[] = $crumb;
        }

        return $trail;
    }

    /**
     * The record's display name, for a breadcrumb or a page title.
     *
     * `name_field` is what the entity spec calls it and every one of the ten declares it —
     * `title` for a book, `fullName` for a person, `formattedRoleTitle` for a role.
     *
     * @param array<string, mixed> $object
     */
    private function recordName(array $object): string
    {
        foreach (['name', 'title', 'fullName', 'formattedRoleTitle', 'key', 'roleTitle'] as $field) {
            if (isset($object[$field]) && is_string($object[$field]) && '' !== $object[$field]) {
                return $object[$field];
            }
        }

        return '';
    }

    /**
     * @param array<array-key, mixed> $params
     * @return array<string, string>
     */
    private function stringMap(array $params): array
    {
        $map = [];
        foreach ($params as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $map[$key] = $value;
            }
        }

        return $map;
    }

    /**
     * The per-entity view variables, dispatched by name so each one is greppable.
     *
     * @param array<string, mixed> $object
     * @param FormInterface<array<string, mixed>> $form
     * @return array<string, mixed>
     */
    private function extraVariables(Request $request, string $entity, array $object, FormInterface $form): array
    {
        /** @var mixed $named */
        $named = $request->attributes->get(self::EXTRA_VARIABLES);
        if (! is_string($named) || '' === $named) {
            return [];
        }

        return match ($named) {
            'personName'              => ['person_name' => $this->recordName($object)],
            'publicationValueOptions' => $this->publicationValueOptions($object, $form),
            default                   => throw new RuntimeException(
                "Route declares unknown extra-variable provider '$named' for entity '$entity'."
            ),
        };
    }

    /**
     * `PublicationsController::injectPublicationValueOptions()`, which the laminas
     * `editAction()` calls after `parent::editAction()`.
     *
     * Three lists, each handed to selectize as JSON rather than rendered as `<option>`s —
     * that is what keeps the publication edit page to 526 KB instead of several megabytes.
     * The publication's *own* id is removed from the "main publication" list, because a
     * publication cannot be its own main edition.
     *
     * @param array<string, mixed> $object
     * @param FormInterface<array<string, mixed>> $form
     * @return array<string, mixed>
     */
    private function publicationValueOptions(array $object, FormInterface $form): array
    {
        $options = $this->valueOptions($form, 'mainPublicationId');

        //A publication cannot be its own main edition, so its own id comes out of that
        //list — `injectPublicationValueOptions()` does the same, guarded the same way.
        /** @var mixed $ownId */
        $ownId = $object['publicationId'] ?? null;
        if (is_int($ownId) || is_string($ownId)) {
            unset($options[$ownId]);
        }

        return [
            'publication_value_options' => $this->selectizeOptions($options),
            'author_persons'            => $this->selectizeOptions($this->valueOptions($form, 'translatorsAll')),
        ];
    }

    /**
     * One select's value options, or an empty list when the element is absent or is not a
     * select. Absent rather than fatal because a form's element set is data here — the
     * factory builds it — and a missing element should not take the page down.
     *
     * @param FormInterface<array<string, mixed>> $form
     * @return array<array-key, mixed>
     */
    private function valueOptions(FormInterface $form, string $element): array
    {
        if (! $form->has($element)) {
            return [];
        }

        $select = $form->get($element);

        return $select instanceof Select ? $select->getValueOptions() : [];
    }

    /**
     * `transformValueOptionsObject()`: an associative map becomes a list of `{i, n}`
     * objects. The one-letter keys are the original's and the JavaScript reads them, so
     * they are not shortenable here without changing `gen-*.js`.
     *
     * @param array<array-key, mixed> $options
     * @return list<array{i: string, n: string}>
     */
    private function selectizeOptions(array $options): array
    {
        $list = [];
        foreach ($options as $key => $value) {
            $list[] = ['i' => (string) $key, 'n' => is_scalar($value) ? (string) $value : ''];
        }

        return $list;
    }

    /** A route default this controller cannot work without. */
    private function attribute(Request $request, string $name): string
    {
        /** @var mixed $value */
        $value = $request->attributes->get($name);
        if (! is_string($value) || '' === $value) {
            //An empty page title is legitimate and declared as such elsewhere, but every
            //constant read through here is structural. A route missing one is a
            //programming mistake, and naming it beats a blank page.
            throw new RuntimeException("Route default '$name' is missing or not a string.");
        }

        return $value;
    }

    /**
     * `editAction()`'s not-found and denied branches: a flash, then a redirect to the
     * entity's index route.
     */
    private function notFound(string $entity, int $id): Response
    {
        $this->flash(FlashMessenger::NAMESPACE_ERROR, $this->edit->deniedMessage($entity, $id));

        $index = $this->edit->indexRoute($entity);
        if (null === $index) {
            //`text` and `dictionary-entry` declare no index_route, so laminas falls back
            //to sion_model.default_redirect_route. Both of them do have a sensible list
            //page, and the route declaration names it — see REDIRECT_ROUTE.
            $index = 'welcome';
        }

        return new RedirectResponse($this->urls->path($index), Response::HTTP_FOUND);
    }

    /**
     * A flash message in the laminas session, so that the page redirected *to* — which is
     * still served by laminas for most of these — finds it where its layout looks.
     */
    private function flash(string $namespace, string $message): void
    {
        (new FlashMessenger())->setNamespace($namespace)->addMessage($message);
    }
}
