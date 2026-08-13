<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function array_values;
use function dirname;
use function file_exists;
use function is_array;
use function is_numeric;
use function sprintf;

/**
 * GET /persons/{person_id} — one person's contact and personal details.
 *
 * Guarded `sch_moderator`. **Not** `App\Sion\EntityShow`: `PersonsController::showAction()`
 * overrides `SionController::showAction()` completely rather than extending it — no
 * change panel, no comment form, no per-row ACL resource — and reusing the shared
 * reproduction would render four things this page does not have. The three steps it does
 * take are reproduced here: `getPerson()`, the URL-map merge below, and `registerVisit()`.
 *
 * ## The assignments panel is unreachable and is not reproduced
 *
 * `show.phtml` has a whole `assignmentsPanel` behind `! empty($object['assignments'])`,
 * and that condition can never be true. `SchoenstattTable::getPersons()` initialises the
 * key and the line that would fill it —
 * `$this->connectEntityRolesAndAssignments('person', $entities)` — **is commented out**.
 * Measured across all 325 people in the capsule: the key is present on every row and
 * empty on every row. That is a property of the code rather than of the data, so it holds
 * on production too.
 *
 * It also explains something that would otherwise look alarming. The panel's markup calls
 * `formatPersonAssignment()`, whose default branch calls `$this->view->formatScope(...)`
 * — and **no `formatScope` helper is registered anywhere in this application**. If that
 * panel could render for an assignment whose scope is not `Community`, the person page
 * would be a fatal error. It cannot, so it is not; and reproducing dead markup whose
 * helper does not exist would mean inventing the helper too.
 *
 * ## The URL map
 *
 * `addUserNamesToUrlList()` merges `schoenstatt.url_map` into the person's `urls` twice
 * over: first filling in a `logo` on any existing URL whose label matches a configured
 * one, then appending a URL per configured service the person has a username for
 * (`facebookUrl`, `twitterUser`, `skypeUser`, …). Reproduced rather than shared, because
 * sharing would mean editing the laminas controller. It is a pure array transform over
 * config and one row, so the copy is small and total.
 */
final class PersonController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->attributes->get('person_id');
        if (! is_numeric($id)) {
            return $this->notFound();
        }
        $id = (int) $id;

        $redirect = LocalePrefix::redirect($request, $this->urls, 'persons/person', ['person_id' => $id]);
        if (null !== $redirect) {
            return $redirect;
        }

        /** @var SchoenstattTable $table */
        $table = $this->laminas->get(SchoenstattTable::class);

        /** @var array<string, mixed>|null $person */
        $person = $table->getPerson($id);
        if (! is_array($person) || [] === $person) {
            return $this->notFound();
        }

        $person['urls'] = $this->urlsWithUserNames($person);

        //exactly where showAction() does it: after the row is known good, before the
        //view is built. SionTable::registerVisit() INSERTs a row per request, which is
        //why tools/port-baseline.php normalizes visit counters.
        $table->registerVisit('person', $person['personId']);

        return new Response($this->twig->render('schoenstatt/person.html.twig', [
            //`headTitle()->setTranslatorEnabled(false)` then the assembled name — so the
            //title is *data* and must not go through translate(), which would file a
            //phrase row per person
            'page_title'           => $this->pageTitle($person),
            'page_title_translate' => false,
            'person'               => $person,
            //`file_exists('public/persons/photos/…')` in the .phtml, relative to the
            //working directory the front controller sets. Resolved from the project root
            //here so it does not depend on where PHP happens to be chdir'd.
            'photo'                => $this->photoPath((int) $person['personId']),
        ]));
    }

    /**
     * The `<title>`, assembled as show.phtml assembles it: a dagger for the deceased,
     * then the translated title, then the *friendly* name — while the `<h1>` uses the
     * full name. The two differ and the template keeps them apart.
     *
     * Escaping is Twig's here rather than the helper's, because this is passed as a
     * variable rather than as markup.
     *
     * @param array<string, mixed> $person
     */
    private function pageTitle(array $person): string
    {
        $title = '';
        if (! ($person['isLiving'] ?? true)) {
            $title .= '✝';
        }
        if (! empty($person['title'])) {
            $title .= $this->translate((string) $person['title']) . ' ';
        }

        return $title . (string) ($person['fullFriendlyName'] ?? '');
    }

    /**
     * `PersonsController::addUserNamesToUrlList()`, reproduced.
     *
     * @param array<string, mixed> $person
     * @return list<array<string, mixed>>
     */
    private function urlsWithUserNames(array $person): array
    {
        $config = $this->laminas->config();
        /** @var array<int|string, array<string, mixed>> $map */
        $map = $config['schoenstatt']['url_map'] ?? [];

        /** @var list<array<string, mixed>> $urls */
        $urls = is_array($person['urls'] ?? null) ? array_values($person['urls']) : [];

        //pass one: fill in a logo on any URL the person already has whose label matches
        foreach ($urls as $key => $row) {
            if (isset($row['logo'])) {
                continue;
            }
            foreach ($map as $configured) {
                if (($row['label'] ?? null) === ($configured['label'] ?? null) && isset($configured['logo'])) {
                    $urls[$key]['logo'] = $configured['logo'];
                    break;
                }
            }
        }

        //pass two: append one URL per configured service this person has a username for.
        //`default` is the .phtml's `$deviceType`, which is never anything else — the
        //parameter exists in the original and every call site passes 'default'.
        foreach ($map as $configured) {
            $userKey = $configured['userKey'] ?? null;
            if (! isset($configured['default'], $userKey) || ! isset($person[$userKey])) {
                continue;
            }
            $url = [
                'label' => $configured['label'] ?? '',
                'url'   => sprintf((string) $configured['default'], (string) $person[$userKey]),
            ];
            if (isset($configured['logo'])) {
                $url['logo'] = $configured['logo'];
            }
            $urls[] = $url;
        }

        return $urls;
    }

    /**
     * The photo's public path, or null when there is no file. The .phtml tests
     * `file_exists('public/persons/photos/%s.jpg')` and renders the `<img>` only then.
     */
    private function photoPath(int $personId): ?string
    {
        $relative = sprintf('/persons/photos/%s.jpg', $personId);

        return file_exists(dirname(__DIR__, 2) . '/public' . $relative) ? $relative : null;
    }

    private function translate(string $message): string
    {
        /** @var \Laminas\I18n\Translator\TranslatorInterface $translator */
        $translator = $this->laminas->get('MvcTranslator');

        return $translator->translate($message);
    }

    /**
     * `showAction()`'s own not-found path: a flash and a redirect to the index, rather
     * than a 404. Reproduced because a moderator following a stale link gets the search
     * page and an explanation on laminas, and a bare 404 here would be a behaviour change
     * dressed as a port.
     */
    private function notFound(): RedirectResponse
    {
        $this->laminas->get('ControllerPluginManager')->get('flashMessenger')
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Person not found.');

        return new RedirectResponse($this->urls->path('persons'), Response::HTTP_FOUND);
    }
}
