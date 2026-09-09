<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\HostMessages;
use App\Laminas\RouteUrl;
use App\Sion\Entities;
use App\Sion\EntityEdit;
use App\Sion\FormViewVariables;
use App\Sion\SiteWideIdentifier;
use Laminas\Form\FormInterface;
use Locale;
use RuntimeException;
use SionModel\Messaging\FlashMessages;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use function property_exists;

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
 *    their laminas controllers and so declare `REDIRECT_TARGET` here instead.
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
    /**
     * Names a private method below, for the two entities whose laminas controllers
     * override `redirectAfterEdit()`. A method rather than a declarative field map,
     * because one of the two is not a field map: `DictionaryController` redirects to
     * `Locale::getPrimaryLanguage($data['locale'])`, so `es_ES` has to become `es`, and a
     * map would have sent the moderator to `/dictionary/es_ES`.
     */
    public const REDIRECT_TARGET = '_edit_redirect_target';
    /** Names a provider on App\Sion\FormViewVariables, e.g. `publicationValueOptions`. */
    public const EXTRA_VARIABLES = '_edit_extra_variables';
    /**
     * The laminas delete route the confirmation modal posts to, for the two entities whose
     * templates render one. Absent everywhere else, and absent means no modal.
     */
    public const DELETE_ROUTE    = '_edit_delete_route';

    public function __construct(
        private readonly EntityEdit $edit,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly Entities $entities,
        private readonly FormViewVariables $viewVariables,
        private readonly HostMessages $messages
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

        $id = $this->resolveId($request, $rawId);
        if (null === $id) {
            return $this->notFound($entity, 0);
        }

        $object = $this->edit->load($entity, $id);
        if (null === $object) {
            return $this->notFound($entity, $id);
        }

        $form = $this->edit->form($entity, $object);

        if ($request->isMethod('POST')) {
            //->all() rather than the request object, to match
            //SionController::getPostDataForEditAction(), which hands the form
            //`getPost()->toArray()`.
            $form->setData($request->request->all());

            if ($form->isValid()) {
                /** @var array<string, mixed> $data */
                $data    = $form->getData();
                $updated = $this->edit->update($entity, $id, $data);

                $this->flash(FlashMessages::NAMESPACE_SUCCESS, $this->edit->updatedMessage($entity));

                return new RedirectResponse(
                    $this->successTarget($request, $entity, $object, $updated),
                    Response::HTTP_FOUND
                );
            }

            //`nowMessenger` on laminas, which renders into the same flash region on this
            //request rather than the next. The Twig layout reads it from the same place.
            $this->flash(FlashMessages::NAMESPACE_ERROR, 'Error in form submission, please review.');
        } else {
            $form->setData($object);
        }

        return new Response($this->twig->render($this->attribute($request, self::TEMPLATE), [
            'page_title'  => $this->attribute($request, self::PAGE_TITLE),
            'breadcrumbs' => $this->breadcrumbs($request, $entity, $object, $rawId),
            'form'        => $form,
            'form_action' => $this->urls->path($laminasRoute, [$idParam => $rawId]),
            'entity'      => $object,
            'entity_id'   => $id,
            //Every view model editAction() builds carries one; two of the ten templates
            //render it, inside a modal gated on the delete route's own permission. Its
            //action is the *laminas* delete route, which this batch does not port.
            'delete_form'   => $this->edit->deleteForm(),
            'delete_action' => $this->deleteAction($request, $idParam, $rawId),
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
     * `REDIRECT_TARGET` first, for the two entities whose laminas controllers override
     * `redirectAfterEdit()`; otherwise the spec-driven priority chain.
     *
     * @param array<string, mixed> $object  the row as it was loaded, pre-write
     * @param array<string, mixed> $updated the row `updateEntity()` returned
     */
    private function successTarget(Request $request, string $entity, array $object, array $updated): string
    {
        /** @var mixed $named */
        $named = $request->attributes->get(self::REDIRECT_TARGET);
        if (is_string($named) && '' !== $named) {
            return match ($named) {
                'text'            => $this->redirectToText($updated, $object),
                'dictionaryEntry' => $this->redirectToDictionaryLanguage($updated, $object),
                default           => throw new RuntimeException(
                    "Route declares unknown redirect target '$named' for entity '$entity'."
                ),
            };
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
     * Where the delete-confirmation modal posts, for the two routes that render one.
     *
     * **Declared, not derived.** The first version of this took the edit route's name and
     * swapped `/edit` for `/delete`, on the reasoning that both modal-rendering routes
     * follow that shape. They do — and the other eight do not have a delete twin at all, so
     * `App\Laminas\RouteUrl` threw assembling `books/book/delete`, *after* the response was
     * assembled. That is the empty-200 wedge again, on three routes that had been working,
     * caught by the smoke suite one commit after the same failure shape cost
     * `collections/collection/edit`.
     *
     * The lesson is worth more than the fix: deriving a route name from another route name
     * is a guess that fails silently, and this file now has two constants where it had one
     * clever rule.
     */
    private function deleteAction(Request $request, string $idParam, string $rawId): string
    {
        /** @var mixed $route */
        $route = $request->attributes->get(self::DELETE_ROUTE);
        if (! is_string($route) || '' === $route) {
            return '';
        }

        return $this->urls->path($route, [$idParam => $rawId]);
    }

    /**
     * `TextsController::redirectAfterEdit()`: straight to the text itself, identifier and
     * slug both taken from the updated row rather than from the URL that was posted to —
     * a retitled text gets a new slug, and the original redirects to the new one.
     *
     * @param array<string, mixed> $updated
     * @param array<string, mixed> $loaded
     */
    private function redirectToText(array $updated, array $loaded): string
    {
        return $this->urls->path(
            'text',
            $this->fill(['sw_id' => 'identifier', 'slug' => 'slug'], $updated, $loaded)
        );
    }

    /**
     * `DictionaryController::redirectAfterEdit()`: to the dictionary of the entry's own
     * language, by **primary language subtag** — `es_ES` becomes `es`, because
     * `dictionary/inLanguage` is keyed by the subtag and not by the locale.
     *
     * Falls back to the undifferentiated `/dictionary` when the locale yields no subtag,
     * which is the original's `if (isset($inLanguage))` branch — and that branch really is
     * reachable: `Locale::getPrimaryLanguage()` answers null when it cannot parse the
     * value, which `isset()` catches. It does *not* answer an empty string, so there is no
     * `'' === $subtag` case to guard; PHPStan says so from the stub's return type and it
     * is right.
     *
     * @param array<string, mixed> $updated
     * @param array<string, mixed> $loaded
     */
    private function redirectToDictionaryLanguage(array $updated, array $loaded): string
    {
        /** @var mixed $locale */
        $locale = $updated['locale'] ?? $loaded['locale'] ?? null;
        if (! is_string($locale) || '' === $locale) {
            return $this->urls->path('dictionary');
        }

        /** @var mixed $subtag */
        $subtag = Locale::getPrimaryLanguage($locale);
        if (! is_string($subtag)) {
            return $this->urls->path('dictionary');
        }

        return $this->urls->path('dictionary/inLanguage', ['inLanguage' => $subtag]);
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
    private function breadcrumbs(Request $request, string $entity, array $object, string $rawId): array
    {
        /** @var mixed $declared */
        $declared = $request->attributes->get(self::BREADCRUMBS);
        if (! is_array($declared)) {
            return [];
        }

        $name = $this->recordName($entity, $object);
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
     * **Asked of the entity spec, not guessed.** `name_field` is what the spec calls it and
     * every one of the ten declares it — `title` for a book, `fullName` for a person,
     * `formattedRoleTitle` for a role — and it is what `SionModel\View\Helper\FormatEntity`
     * reads. This method's docblock said so while its body walked a fixed list of candidate
     * keys in a fixed order, which is the same thing only by coincidence: for `person` the
     * list reached `title` first, and `title` on a person is the **honorific**. So the
     * heading of `/persons/494/edit` read "Sr." rather than "M. Aleja Slaughter". Found by
     * the port baseline, which is also how it became clear the field was never populated on
     * the laminas side at all (see the note on `personName` in docs/BACKLOG.md).
     *
     * The list survives as a fallback for an entity whose spec declares no `name_field`.
     * Ordered so that the more specific keys come first, since a spec-less entity has no
     * authority to consult.
     *
     * @param array<string, mixed> $object
     */
    private function recordName(string $entity, array $object): string
    {
        $declared = $this->entities->stringField($entity, 'nameField');
        if (null !== $declared && isset($object[$declared]) && is_string($object[$declared])) {
            return $object[$declared];
        }

        foreach (['fullName', 'formattedRoleTitle', 'roleTitle', 'name', 'title', 'key'] as $field) {
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
            'personName'              => ['person_name' => $this->recordName($entity, $object)],
            'publicationValueOptions' => $this->viewVariables->publicationValueOptions($object, $form),
            'nextWithinLibraryId'     => $this->viewVariables->nextWithinLibraryId($form),
            default                   => throw new RuntimeException(
                "Route declares unknown extra-variable provider '$named' for entity '$entity'."
            ),
        };
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
        $this->flash(FlashMessages::NAMESPACE_ERROR, $this->edit->deniedMessage($entity, $id));

        $index = $this->edit->indexRoute($entity);
        if (null === $index) {
            //`text` and `dictionary-entry` declare no index_route, so laminas falls back
            //to sion_model.default_redirect_route. Both of them do have a sensible list
            //page, and the route declaration names it — see REDIRECT_TARGET.
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
        $this->messages->flash($namespace, $message);
    }
}
