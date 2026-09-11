<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acl\IsAllowed;
use App\Authorization\Denial;
use App\Laminas\HostMessages;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Sion\EntityCreate;
use App\Sion\FormViewVariables;
use SionModel\Filter\StripTags;
use SionModel\Form\Element\Select;
use SionModel\Form\FormInterface;
use Locale;
use RuntimeException;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Model\SchoenstattTable;
use SionModel\Messaging\FlashMessages;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function array_key_exists;
use function ctype_digit;
use function is_array;
use function is_int;
use function is_string;
use function method_exists;
use function str_ends_with;
use function substr;

/**
 * Every entity create form on the Symfony side: eleven routes behind one class — nine from
 * batch 9, and `persons/create` and `texts/create` from batch 10, which waited on the laminas
 * bugs that made them unportable.
 *
 * The counterpart of `EntityEditController`, over `App\Sion\EntityCreate`, and the same
 * argument for one class rather than eleven: on laminas these pages are *one* method,
 * `SionController::createAction()`, reached through eleven controllers that mostly add
 * nothing to it. What differs per page is declared in `config/symfony/routes.php`.
 *
 * ## What differs from the edit surface
 *
 * - **No row.** There is nothing to load, so there is no not-found branch, and the form is
 *   rendered empty on a GET rather than populated from a record.
 * - **No per-row ACL check** — `createAction()` has none, for the reason `EntityCreate`
 *   documents. Two routes carry a **library-scoped** check instead, declared as
 *   `LIBRARY_PERMISSION`; see below.
 * - **A query-parameter prefill** on three routes, which the edit surface has no analogue
 *   for: a moderator arrives at a create form from a link that already knows some of the
 *   answers.
 *
 * ## The library-scoped permission, and why it is declared rather than derived
 *
 * `BooksController::createAction()` and `CollectionsController::createAction()` both open
 * with `isAllowed('library_' . $library_id, 'administrate')` and throw
 * `UnAuthorizedException` before calling their parent. That is the only authorization a
 * create page has beyond its route guard, and it is *not* something to infer from "the
 * route has a `library_id` parameter": `library-imports/library/create` has one too and is
 * not in this batch, and a future create route could carry one for an entirely different
 * reason. So it is a route default, and a route that omits it gets no check — the same
 * rule, and the same reasoning, as `EntityEditController::DELETE_ROUTE`, which was derived
 * cleverly once and took three working pages down with it.
 */
final class EntityCreateController
{
    public const ENTITY      = '_create_entity';
    public const TEMPLATE    = '_create_template';
    public const PAGE_TITLE  = '_create_page_title';
    public const BREADCRUMBS = '_create_breadcrumbs';
    /**
     * The route parameter naming the library this record is created in, for the two
     * library-scoped forms. Absent means the form needs no library.
     */
    public const LIBRARY_PARAM = '_create_library_param';
    /**
     * The privilege to require on `library_<id>` before the page may be reached at all.
     * Absent means no such check — see the class docblock.
     */
    public const LIBRARY_PERMISSION = '_create_library_permission';
    /** Names a provider on App\Sion\FormViewVariables, e.g. `publicationValueOptions`. */
    public const EXTRA_VARIABLES = '_create_extra_variables';
    /** Names a private prefill method below, for the three routes that accept query hints. */
    public const PREFILL = '_create_prefill';
    /**
     * Names a private method below, for the entities whose laminas controllers override
     * `redirectAfterCreate()`. Only `dictionary-entry` does, among this batch's nine.
     */
    public const REDIRECT_TARGET = '_create_redirect_target';
    /**
     * Names a private rule below that runs after the form validates and before anything is
     * written, for the entities whose spec declares a `create_action_valid_data_handler`.
     * Only `person` does.
     */
    public const VALID_DATA_RULE = '_create_valid_data_rule';

    public function __construct(
        private readonly EntityCreate $create,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly FormViewVariables $viewVariables,
        private readonly ServiceBridge $laminas,
        private readonly HostMessages $messages
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $entity = $this->attribute($request, self::ENTITY);
        $route  = $request->attributes->get('_route');
        $route  = is_string($route) ? $route : '';
        //the laminas route name the ported one shadows — `.locale` is Symfony's suffix
        $laminasRoute = str_ends_with($route, '.locale') ? substr($route, 0, -7) : $route;

        $libraryParam = $this->optional($request, self::LIBRARY_PARAM);
        $routeParams  = [];
        $libraryId    = null;
        if (null !== $libraryParam) {
            /** @var mixed $raw */
            $raw = $request->attributes->get($libraryParam);
            $raw = is_string($raw) || is_int($raw) ? (string) $raw : '';
            $routeParams[$libraryParam] = $raw;
            $libraryId = ctype_digit($raw) ? (int) $raw : null;
        }

        $denial = $this->libraryPermission($request, $libraryId);
        if (null !== $denial) {
            return $denial;
        }

        $form = $this->create->form($entity, $libraryId);

        if ($request->isMethod('POST')) {
            //->all(), to match SionController::getPostDataForCreateAction(), which hands
            //the form `getPost()->toArray()`.
            $form->setData($request->request->all());

            if ($form->isValid()) {
                /** @var array<string, mixed> $data */
                $data = $form->getData();

                $refusal = $this->validDataRule($request, $data);
                if (null !== $refusal) {
                    $this->flash(FlashMessages::NAMESPACE_ERROR, $refusal);

                    return $this->render($request, $entity, $form, $laminasRoute, $routeParams, $libraryId);
                }

                $newId = $this->create->create($entity, $data);

                if (0 !== $newId) {
                    $this->flash(FlashMessages::NAMESPACE_SUCCESS, $this->create->createdMessage($entity));

                    return new RedirectResponse(
                        $this->successTarget($request, $entity, $newId, $data),
                        Response::HTTP_FOUND
                    );
                }

                //`createEntityPostFormValidation()`'s failure branch: the same message the
                //invalid-form branch shows, and the form is re-rendered with what was typed.
                $this->flash(FlashMessages::NAMESPACE_ERROR, 'Error in form submission, please review.');
            } else {
                $this->flash(FlashMessages::NAMESPACE_ERROR, 'Error in form submission, please review.');
            }
        } else {
            $this->prefill($request, $form);
        }

        return $this->render($request, $entity, $form, $laminasRoute, $routeParams, $libraryId);
    }

    /**
     * The page itself. Every exit that is not a redirect comes through here.
     *
     * @param FormInterface $form
     * @param array<string, string>               $routeParams
     */
    private function render(
        Request $request,
        string $entity,
        FormInterface $form,
        string $laminasRoute,
        array $routeParams,
        ?int $libraryId
    ): Response {
        return new Response($this->twig->render($this->attribute($request, self::TEMPLATE), [
            'page_title'  => $this->attribute($request, self::PAGE_TITLE),
            'breadcrumbs' => $this->breadcrumbs($request, $routeParams),
            'form'        => $form,
            'form_action' => $this->urls->path($laminasRoute, $routeParams),
            'library_id'  => $libraryId,
        ] + $this->extraVariables($request, $entity, $form)));
    }

    /**
     * The rule a spec's `create_action_valid_data_handler` adds beyond validation, or null
     * when the route declares none or the data satisfies it.
     *
     * On laminas these handlers *replace* `createEntityPostFormValidation()` wholesale — the
     * spec names a controller method and `createAction()` calls it instead of creating the
     * record itself. Only one exists, `PersonsController::createPerson()`, and all it adds to
     * the shared path is one rule; the rest is a copy of the method it replaced, down to the
     * `ucwords($entity) . ' successfully created.'` message. So what is reproduced here is the
     * rule, not the replacement — a route declares the rule and keeps the shared write path.
     *
     * Reading the original is worth doing before adding a second one. `createPerson()` calls
     * `$this->redirectAfterCreate()` **without returning it**, and `createAction()` discards
     * the handler's return value on purpose ("don't return here so that if the handler doesn't
     * redirect, we send them back to the form"). The redirect works anyway, because laminas's
     * `redirect()` plugin sets the status and `Location` on the shared response object rather
     * than on one it hands back — so the create page renders its own body underneath a 302
     * nobody returned. That is not a thing to reproduce.
     *
     * @param array<string, mixed> $data the validated form data
     */
    private function validDataRule(Request $request, array $data): ?string
    {
        $named = $this->optional($request, self::VALID_DATA_RULE);
        if (null === $named) {
            return null;
        }

        return match ($named) {
            //"Either a first name or a last name is required." Neither field is required on
            //its own — a person can be recorded with only a surname or only a given name —
            //so the rule is a relation between two optional fields, which is why it lives in
            //a handler and not in the input filter.
            'person' => ($data['firstName'] ?? '') || ($data['lastName'] ?? '')
                ? null
                : 'Either a first name or a last name is required.',
            default  => throw new RuntimeException("Route declares unknown valid-data rule '$named'."),
        };
    }

    /**
     * The `library_<id>` check the two library-scoped create actions do by hand, or null
     * when the route declares none.
     *
     * Answers the **403 page** rather than throwing `UnAuthorizedException`: laminas throws
     * it and its own error strategy renders the 403, and the Symfony side has
     * `App\Authorization\RouteGuard` doing the same job for route guards. Reproducing the
     * throw would reach Symfony's exception handling instead, which knows nothing about
     * this application's 403 template.
     */
    private function libraryPermission(Request $request, ?int $libraryId): ?Response
    {
        $permission = $this->optional($request, self::LIBRARY_PERMISSION);
        if (null === $permission) {
            return null;
        }

        if (null === $libraryId) {
            //A route declaring the check but carrying no library is a routing mistake, and
            //it must not fail open.
            throw new RuntimeException(
                'A create route declares a library permission but supplied no library id.'
            );
        }

        /** @var IsAllowed $isAllowed */
        $isAllowed = $this->laminas->get(IsAllowed::class);

        if ((bool) $isAllowed->__invoke('library_' . $libraryId, $permission)) {
            return null;
        }

        //Through Denial rather than rendering the template directly: `error/403.html.twig`
        //requires a `subject`, and omitting it under strict_variables throws after the
        //response is assembled — a blank HTTP 200 rather than a 403. That is what this
        //branch did from batch 9 until 2026-08-18, on every create route whose visitor
        //held the route guard but not the library's `administrate`.
        return Denial::forbiddenPage($this->twig, 'library_' . $libraryId);
    }

    /**
     * Where a successful create sends the visitor.
     *
     * `REDIRECT_TARGET` first, for the entities whose laminas controllers override
     * `redirectAfterCreate()`; otherwise the spec-driven priority chain in `EntityCreate`.
     *
     * @param array<string, mixed> $data the validated form data
     */
    private function successTarget(Request $request, string $entity, int $newId, array $data): string
    {
        $row = $this->create->row($entity, $newId);

        $named = $this->optional($request, self::REDIRECT_TARGET);
        if (null !== $named) {
            return match ($named) {
                'dictionaryEntry' => $this->redirectToDictionaryLanguage($row, $data),
                'text'            => $this->redirectToText($newId, $row, $data),
                default           => throw new RuntimeException(
                    "Route declares unknown redirect target '$named' for entity '$entity'."
                ),
            };
        }

        $target = $this->create->redirectTarget($entity, $newId, $row);
        if (null === $target) {
            //`sion_model.default_redirect_route`, which redirectAfterCreate() falls back
            //to. No entity in this batch reaches it — every one declares a create redirect
            //route — so refuse loudly rather than read a config key nothing uses.
            throw new RuntimeException(
                "Entity '$entity' declares no create_action_redirect_route to redirect to."
            );
        }

        return $this->urls->path($target[0], $target[1]);
    }

    /**
     * `TextsController::redirectAfterCreate()`: to the new text's own page.
     *
     * Built from the **submitted data**, not from the stored row, and that is the original's
     * choice rather than a shortcut — it derives the identifier from the insert id and the
     * slug from `$data['title']`, never loading what it just wrote. The two agree in practice
     * because `EventTextTable::preprocessText()` slugs the same title through the same
     * `SchoenstattTable::getSlug()`; the row is consulted here only as a fallback for a title
     * the post did not carry, which the form's `required` rule makes unreachable.
     *
     * Its sibling `redirectAfterEdit()` reads the *updated row* instead, which is why the two
     * are separate methods there and separate branches here.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $data
     */
    private function redirectToText(int $newId, array $row, array $data): string
    {
        /** @var mixed $title */
        $title = $data['title'] ?? $row['title'] ?? '';

        return $this->urls->path('text', [
            'sw_id' => (new ToSchoenstattLinkIdentifier('text'))->filter($newId),
            'slug'  => SchoenstattTable::getSlug(is_string($title) ? $title : ''),
        ]);
    }

    /**
     * `DictionaryController::redirectAfterCreate()`, which delegates to its
     * `redirectAfterEdit()`: to the dictionary of the entry's own language, by **primary
     * language subtag** — `es_ES` becomes `es`.
     *
     * The locale is read from the submitted data first and the new row second, where the
     * edit controller reads the updated row first. Same values either way; the order simply
     * follows what each original had to hand.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $data
     */
    private function redirectToDictionaryLanguage(array $row, array $data): string
    {
        /** @var mixed $locale */
        $locale = $data['locale'] ?? $row['locale'] ?? null;
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
     * The query-parameter prefill three laminas create actions do on a GET.
     *
     * Every one of them is "this link already knows part of the answer" — a moderator
     * arriving from an association page to add a role there, from a role to add an
     * assignment, or from a book to copy it. All three run on GET only, which is the
     * original's `if (! $this->getRequest()->isPost())` and matters: on a failed POST the
     * form must re-render what was typed, not what the URL suggested.
     *
     * @param FormInterface $form
     */
    private function prefill(Request $request, FormInterface $form): void
    {
        $named = $this->optional($request, self::PREFILL);
        if (null === $named) {
            return;
        }

        match ($named) {
            'association' => $this->prefillAssociation($request, $form),
            'assignment'  => $this->prefillAssignment($request, $form),
            'book'        => $this->prefillBook($request, $form),
            default       => throw new RuntimeException("Route declares unknown prefill '$named'."),
        };
    }

    /**
     * `AssociationsController::createAction()`: five query parameters, each validated in a
     * different way, and the differences are the original's.
     *
     * `country` and `parentId` must name an option the select already offers —
     * `key_exists($param, $element->getValueOptions())` — because they are ids, and an
     * unknown one would render a select with a value nothing matches. `name`, `timeZoneId`
     * and `kind` are free text passed through `StripTags`, which is what laminas does; they
     * are not checked against anything, so `?kind=nonsense` reaches the element and the
     * form refuses it on submit.
     *
     * @param FormInterface $form
     */
    private function prefillAssociation(Request $request, FormInterface $form): void
    {
        $this->setFromOptions($form, 'country', $this->query($request, 'country'));
        $this->setFromOptions($form, 'parentId', $this->query($request, 'parentId'));

        foreach (['name' => 'name', 'timeZoneId' => 'timeZoneId', 'kind' => 'kind'] as $param => $element) {
            $value = $this->query($request, $param);
            if (null !== $value && $form->has($element)) {
                $form->get($element)->setValue((new StripTags())->filter($value));
            }
        }
    }

    /**
     * `AssignmentsController::createAction()`: a role, and with it the association that role
     * belongs to.
     *
     * `roleId` is the interesting one. `AssignmentForm::getRoleTitleValueOptions()` answers
     * a map of association id => that association's roles, and the element itself is
     * populated only after an association is chosen — by JavaScript, in a browser. So a
     * link carrying `?roleId=` has to find which association owns the role, set *that*,
     * narrow the role element's options to that association's, and only then set the role.
     * Reproduced in that order.
     *
     * The `if (! $form->get('associationId')->getValue())` guard is the original's: a role
     * hint is ignored when an association is already chosen.
     *
     * @param FormInterface $form
     */
    private function prefillAssignment(Request $request, FormInterface $form): void
    {
        $roleId = $this->query($request, 'roleId');
        if (null !== $roleId && $form->has('associationId') && $form->has('roleId')) {
            $association = $form->get('associationId');
            $roles       = $this->roleTitleValueOptions($form);

            foreach ($roles as $associationId => $associationRoles) {
                if (! is_array($associationRoles) || ! array_key_exists($roleId, $associationRoles)) {
                    continue;
                }
                if (! $association->getValue()) {
                    $association->setValue((string) $associationId);
                    $role = $form->get('roleId');
                    if ($role instanceof Select) {
                        $role->setValueOptions($associationRoles);
                    }
                    $role->setValue($roleId);
                }
                break;
            }
        }

        $personId = $this->query($request, 'personId');
        if (null !== $personId) {
            $this->setFromOptions($form, 'personId', $personId);
        }
    }

    /**
     * `BooksController::createAction()`'s `?copyBook=` prefill.
     *
     * Sixteen fields copied off an existing book, and **two rules that are easy to lose**:
     * the source book may live in a different library as long as the visitor may *show*
     * that library, and `collectionId` is copied only when the two libraries are the same —
     * a collection id means nothing outside its own library.
     *
     * @param FormInterface $form
     */
    private function prefillBook(Request $request, FormInterface $form): void
    {
        $copyBook = $this->query($request, 'copyBook');
        if (null === $copyBook || ! ctype_digit($copyBook)) {
            return;
        }

        if (! $this->create->exists('book', (int) $copyBook)) {
            return;
        }

        $source = $this->create->row('book', (int) $copyBook);

        /** @var mixed $sourceLibrary */
        $sourceLibrary = $source['libraryId'] ?? null;
        /** @var IsAllowed $isAllowed */
        $isAllowed = $this->laminas->get(IsAllowed::class);
        if (! (bool) $isAllowed->__invoke('library_' . (string) $sourceLibrary, 'show')) {
            return;
        }

        $this->copy($form, $source, ['title', 'authors']);

        //The call number is copied from `newCallNumber` when the source has one, and from
        //`callNumber` otherwise — laminas' if/else, and the two are different columns.
        $this->copy($form, $source, isset($source['newCallNumber']) ? ['newCallNumber'] : ['callNumber']);

        /** @var mixed $libraryParam */
        $libraryParam = $request->attributes->get('library_id');
        if ((string) $sourceLibrary === (string) $libraryParam) {
            $this->copy($form, $source, ['collectionId']);
        }

        $this->copy($form, $source, [
            'bookEdition',
            'inLanguage',
            'publicationId',
            'category',
            'isbn',
            'numberOfPages',
            'publisher',
            'publishingPlace',
            'keywords',
            'publicNotes',
            'adminNotes',
            'adminTags',
        ]);
    }

    /**
     * `AssignmentForm::getRoleTitleValueOptions()`, or an empty map when the form does not
     * answer it. Guarded rather than assumed for the reason `valueOptions()` is: a form's
     * shape is data here.
     *
     * @param FormInterface $form
     * @return array<array-key, mixed>
     */
    private function roleTitleValueOptions(FormInterface $form): array
    {
        if (! method_exists($form, 'getRoleTitleValueOptions')) {
            return [];
        }

        /** @var mixed $options */
        $options = $form->getRoleTitleValueOptions();

        return is_array($options) ? $options : [];
    }

    /**
     * Set an element's value only when the select already offers it, which is laminas'
     * `key_exists($param, $element->getValueOptions())` guard.
     *
     * @param FormInterface $form
     */
    private function setFromOptions(FormInterface $form, string $element, ?string $value): void
    {
        if (null === $value || ! $form->has($element)) {
            return;
        }

        $select = $form->get($element);
        if (! $select instanceof Select || ! array_key_exists($value, $select->getValueOptions())) {
            return;
        }

        $select->setValue($value);
    }

    /**
     * Copy a list of fields from a source row onto the form, skipping what neither has.
     *
     * @param FormInterface $form
     * @param array<string, mixed>                $source
     * @param list<string>                        $fields
     */
    private function copy(FormInterface $form, array $source, array $fields): void
    {
        foreach ($fields as $field) {
            if ($form->has($field) && array_key_exists($field, $source)) {
                /** @var mixed $value */
                $value = $source[$field];
                $form->get($field)->setValue($value);
            }
        }
    }

    /** A query parameter as a string, or null when absent. */
    private function query(Request $request, string $name): ?string
    {
        /** @var mixed $value */
        $value = $request->query->get($name);

        return is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @param FormInterface $form
     * @return array<string, mixed>
     */
    private function extraVariables(Request $request, string $entity, FormInterface $form): array
    {
        $named = $this->optional($request, self::EXTRA_VARIABLES);
        if (null === $named) {
            return [];
        }

        return match ($named) {
            //A create has no row, so the publication has no own id to remove from the
            //"main publication" list — which is what `createAction()` passes too, since
            //the view's `entityId` is null there.
            'publicationValueOptions' => $this->viewVariables->publicationValueOptions([], $form),
            'nextWithinLibraryId'     => $this->viewVariables->nextWithinLibraryId($form),
            default                   => throw new RuntimeException(
                "Route declares unknown extra-variable provider '$named' for entity '$entity'."
            ),
        };
    }

    /**
     * The breadcrumb trail, in the same declared shape the edit routes use — a list of
     * `['label' => …, 'route' => …, 'params' => …]` maps, resolved to `href` here.
     *
     * Simpler than the edit surface's by one thing it does not need: there is no `{name}`
     * substitution, because a record being created has no name yet.
     *
     * @param array<string, string> $routeParams
     * @return list<array<string, mixed>>
     */
    private function breadcrumbs(Request $request, array $routeParams): array
    {
        /** @var mixed $declared */
        $declared = $request->attributes->get(self::BREADCRUMBS);
        if (! is_array($declared)) {
            return [];
        }

        $trail = [];
        foreach ($declared as $crumb) {
            if (! is_array($crumb)) {
                continue;
            }
            /** @var array<string, mixed> $crumb */
            if (isset($crumb['route']) && is_string($crumb['route'])) {
                /** @var mixed $params */
                $params = $crumb['params'] ?? [];
                //A crumb's params name *route* parameters this page already has — a
                //library create page's trail links back to that library.
                $filled = [];
                if (is_array($params)) {
                    foreach ($params as $name) {
                        if (is_string($name) && isset($routeParams[$name])) {
                            $filled[$name] = $routeParams[$name];
                        }
                    }
                }
                $crumb['href'] = $this->urls->path($crumb['route'], $filled);
                unset($crumb['route'], $crumb['params']);
            }
            $trail[] = $crumb;
        }

        return $trail;
    }

    /** As EntityEditController does it: the plugin writes straight into the laminas session. */
    private function flash(string $namespace, string $message): void
    {
        $this->messages->flash($namespace, $message);
    }

    /** A route default this controller cannot work without. */
    private function attribute(Request $request, string $name): string
    {
        /** @var mixed $value */
        $value = $request->attributes->get($name);
        if (! is_string($value) || '' === $value) {
            throw new RuntimeException("Route default '$name' is missing or not a string.");
        }

        return $value;
    }

    /** A route default that may legitimately be absent. */
    private function optional(Request $request, string $name): ?string
    {
        /** @var mixed $value */
        $value = $request->attributes->get($name);

        return is_string($value) && '' !== $value ? $value : null;
    }
}
