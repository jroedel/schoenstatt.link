<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use JTranslate\Controller\Plugin\NowMessenger;
use Laminas\Form\FormInterface;
use Schoenstatt\Form\AdvancedSearchForm;
use Schoenstatt\Form\SearchForm;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;

/**
 * The contact search: `GET /assignments/search` and `GET /assignments/advanced-search`.
 *
 * ## Why this route first
 *
 * `/assignments/search` is **the destination of the navbar search box on every page of
 * the site** — `App\View\SiteChrome::searchBox()` and `layout.phtml` both assemble it
 * for anyone holding `sch_basic` or `sch_user`. So until this moved, every search a
 * signed-in member ran from an already-ported page bounced straight back through
 * `LegacyBridge` into laminas. By traffic it is the largest single route in this batch.
 *
 * ## Both actions are reads of the query string
 *
 * Neither writes anything and neither has a CSRF token: `SearchForm` and
 * `AdvancedSearchForm` are `method="GET"` forms whose data is `?search=…`. That is why
 * this pair could move ahead of the create/edit forms — the static-adapter obstacle
 * docs/strangler.md records applies to `CreateRoleForm`, `EditUserForm`, `DeleteUserForm`
 * and `EditPhraseForm` through a `NoRecordExists` validator, and neither form here has
 * one.
 *
 * `SearchForm` is constructed directly rather than pulled from the container, because
 * that is what `AssignmentsController::searchAction()` does — it has no factory and no
 * dependencies. `AdvancedSearchForm` is the opposite case: it *is* a service, listed in
 * the assignment entity spec's `controller_services`, and its value options are built
 * from the database, so it comes through the ServiceBridge.
 *
 * ## The empty query is not the empty result
 *
 * `searchEntities($data, ['bypassRequiredParams' => true])` on the *simple* search
 * returns **every** entity when the query is blank — measured at 595,300 bytes for
 * `/en/assignments/search` with no `?search=`. That is the laminas behaviour and the
 * option name says so out loud, so it is reproduced rather than quietly bounded. The
 * advanced search has no such option and answers a blank form with nothing, which is
 * why the two actions differ on a line that looks like an oversight and is not.
 *
 * ## `/assignments/{id}` is deliberately not here
 *
 * It looks like the missing third route and it is not a page at all. The `assignment`
 * entity spec sets `show_route => 'association'` with `show_route_key => 'sw_id'`,
 * because an assignment is meant to be read inside its association's page — but
 * `SionController::getEntityIdParam('show')` resolves the id by reading `showRouteKey`
 * off the *current* route, and `/assignments/{assignment_id}` has no `sw_id`. So it
 * always gets null and always takes `showAction()`'s first branch: flash "Assignment
 * not found." and 302 back to `assignments/search`. Measured for an account holding
 * every role, on a row `getAssignment()` returns perfectly well. The route survives as
 * the parent of `/edit` and `/delete`; porting it would mean porting an unconditional
 * redirect. See docs/strangler.md.
 */
final class AssignmentSearchController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    /** GET /assignments/search — the navbar search box's results page. */
    public function search(Request $request): Response
    {
        $redirect = LocalePrefix::redirect($request, $this->urls, 'assignments/search');
        if (null !== $redirect) {
            return $redirect;
        }

        $form = new SearchForm();
        $form->setData($request->query->all());

        $entities = null;
        if ($form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            //**No `if ([] !== $data)` guard**, unlike the advanced action below and
            //unlike LiteratureController::search(). The laminas action has that test
            //commented out and passes `bypassRequiredParams`, which is what makes a
            //blank query answer with the whole table. Restoring the guard would look
            //like a tidy-up and would silently empty the page every caller lands on
            //by clicking the search button with nothing typed.
            /** @var array<mixed> $entities */
            $entities = $this->table()->searchEntities($data, ['bypassRequiredParams' => true]);
        }

        if (is_array($entities) && [] === $entities) {
            $this->nowMessage(NowMessenger::NAMESPACE_INFO, 'No results found.');
        }

        return new Response($this->twig->render('schoenstatt/assignments-search.html.twig', [
            //searchAction() calls no headTitle(), so laminas renders the bare
            //`<title>Schoenstatt Link</title>` and the layout reproduces that by
            //omitting the separator. No breadcrumbs either: `assignments/search` is
            //not in the `navigation` config, so the trail is an empty `<div class="row">`.
            'page_title' => '',
            'form'       => $form,
            'entities'   => $entities,
        ]));
    }

    /** GET /assignments/advanced-search — the four-field form over the same search. */
    public function advancedSearch(Request $request): Response
    {
        $redirect = LocalePrefix::redirect($request, $this->urls, 'assignments/advanced-search');
        if (null !== $redirect) {
            return $redirect;
        }

        $form = $this->advancedForm();
        $form->setData($request->query->all());

        $objects = null;
        if ($form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            if ([] !== $data) {
                /** @var array<mixed> $objects */
                $objects = $this->table()->searchEntities($data);
            }
        }

        //**No "No results found." here.** advancedSearchAction() does not set one, and
        //the simple action does; the asymmetry is in the original.
        return new Response($this->twig->render('schoenstatt/assignments-advanced-search.html.twig', [
            'page_title' => '',
            'form'       => $form,
            'objects'    => $objects,
        ]));
    }

    /**
     * The advanced form, from the laminas container — so its `roleTitle`,
     * `associationKind` and `associationCountry` options are the application's, built
     * by its factory from the database, rather than a copy that would drift.
     *
     * @return FormInterface<array<string, mixed>>
     */
    private function advancedForm(): FormInterface
    {
        /** @var FormInterface<array<string, mixed>> $form */
        $form = $this->laminas->get(AdvancedSearchForm::class);

        return $form;
    }

    private function table(): SchoenstattTable
    {
        /** @var SchoenstattTable $table */
        $table = $this->laminas->get(SchoenstattTable::class);

        return $table;
    }

    /**
     * A message for the page being rendered rather than the next one, through the same
     * shared ControllerPluginManager instance the `nowMessenger` view helper reads —
     * see LiteratureController, which established this.
     */
    private function nowMessage(string $namespace, string $message): void
    {
        /** @var NowMessenger $messenger */
        $messenger = $this->laminas->get('ControllerPluginManager')->get('nowMessenger');
        $messenger->setNamespace($namespace)->addMessage($message);
    }
}
