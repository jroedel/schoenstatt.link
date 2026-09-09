<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\SymfonyRoute;
use App\Laminas\HostMessages;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Schoenstatt\Form\SearchForm;
use Schoenstatt\Model\SchoenstattTable;
use SionModel\Messaging\FlashMessages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;

/**
 * GET /persons and GET /persons/search — finding a person by name.
 *
 * Two laminas routes, one action: `persons` defaults to `search` and `persons/search`
 * is the same action under an explicit path. Both are guarded `sch_moderator`.
 *
 * ## This port fixes the page rather than reproducing it
 *
 * **The laminas page has never rendered a result.** `PersonsController::searchAction()`
 * builds a `SearchForm`, runs `searchPersons()` and passes the rows to the view as
 * `persons`; `persons/search.phtml` opens with
 * `$areResults = isset($this->entities) && 0 != count($this->entities)` and never
 * mentions `$form` at all. Under `PhpRenderer` an unset variable is a silent null, so
 * `$areResults` is always false, the `<table>` below it is dead markup, and the search
 * box is never drawn. Measured before porting, signed in as an account holding every
 * role: `/en/persons` and `/en/persons/search?search=Walter` are 9,327 and 9,237 bytes,
 * containing no `<table>` at all — the entire body between the navbar and the JSON-LD is
 * `<a href="/en/persons/create">Add person</a>`. There is not even a "No results found."
 * flash, which is how you can tell the query ran and matched: the rows were fetched and
 * thrown away.
 *
 * `AssignmentsController::searchAction()` passes `entities` and its template reads
 * `entities`, so this looks like a copy of that pair whose controller variable was
 * renamed and whose template was not.
 *
 * So the ported page renders the table the .phtml has always described, and the search
 * box the controller has always built. That is a deliberate deviation from the porting
 * rule, taken as a decision rather than by accident: `tools/port-baseline.php` reports
 * twelve intentional non-matches here, itemized in docs/laminas-exit.md under "Known
 * differences". Everything else on the page — the create link, its ACL check, the four
 * column headers, the empty "Community" cell — is reproduced exactly.
 *
 * ## What the empty query does
 *
 * `searchPersons()` returns **null**, not an empty array, when no search parameter is
 * given: it counts the parameters it recognises and bails unless one is present, and
 * this call site passes no `bypassRequiredParams`. So `/persons` shows the box and no
 * table and says nothing, while a query matching nothing shows "No results found." That
 * is the opposite of `/assignments/search`, whose action *does* bypass and therefore
 * answers a blank query with the whole table.
 */
final class PersonsController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly HostMessages $messages
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $form = new SearchForm();
        $form->setData($request->query->all());

        $persons = null;
        if ($form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            if ([] !== $data) {
                /** @var array<mixed>|null $persons */
                $persons = $this->table()->searchPersons($data);
            }
        }

        if (is_array($persons) && [] === $persons) {
            $this->nowMessage(FlashMessages::NAMESPACE_INFO, 'No results found.');
        }

        return new Response($this->twig->render('schoenstatt/persons-search.html.twig', [
            //searchAction() calls no headTitle(), and `persons` is not in the navigation
            //config, so there is neither a title nor a breadcrumb trail
            'page_title' => '',
            'form'       => $form,
            'persons'    => $persons,
            'action'     => $this->urls->path('persons/search'),
        ]));
    }

    private function table(): SchoenstattTable
    {
        /** @var SchoenstattTable $table */
        $table = $this->laminas->get(SchoenstattTable::class);

        return $table;
    }

    /** @see LiteratureController::nowMessage() — the same shared plugin the layout renders from. */
    private function nowMessage(string $namespace, string $message): void
    {
        $this->messages->now($namespace, $message);
    }
}
