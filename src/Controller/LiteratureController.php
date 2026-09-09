<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acl\IsAllowed;
use App\Laminas\HostMessages;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use Books\Form\PublicationsSearchForm;
use Books\Model\DictionaryTable;
use Books\Model\LibraryTable;
use Books\Model\PublicationsTable;
use Books\Service\DriveGateway;
use Laminas\Form\FormInterface;
use Locale;
use RuntimeException;
use SionModel\Messaging\FlashMessages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twig\Environment;

use function array_key_exists;
use function array_keys;
use function count;
use function file_exists;
use function is_array;
use function is_string;
use function preg_replace;
use function sprintf;
use function strtolower;
use function trim;

/**
 * The literature browse surface: `/literature`, `/literature/{inLanguage}` and
 * `/literature/search`.
 *
 * One controller for three routes because they are one page in three states — each
 * renders the same `PublicationsSearchForm` above its own body, and two of them render
 * the same publication table.
 *
 * ## Memory, measured before porting
 *
 * These are the pages docs/BACKLOG.md records as building
 * `query-objects-publication` — 10,166 rows × 80 fields, about 29.2 MiB — uncached.
 * Measured through the ServiceBridge before any of this was written: **192 MiB on the
 * first call and 210 MiB peak**, against production's 512M limit, with everything after
 * the first call free because `getObjects()` memoizes in-process. That is the same order
 * as `/sm/view-changes` (214 MiB), which was dead for years on exactly this failure
 * mode, so it was worth measuring rather than assuming. `/literature/de` is the largest
 * page on the site: 2,889 rows, ~1.0 MB of HTML.
 *
 * ## Three things resolved here that the .phtml did inline
 *
 * 1. **The cover lookups.** `publication-list.phtml` calls `file_exists()` once per row —
 *    2,889 stat calls on the German catalogue — to decide whether a row has a thumbnail.
 *    Still one stat per row, but done here, where it reads as the I/O it is.
 * 2. **The downloadable-files icon**, whose test is "this publication has Drive files, or
 *    any of its sub-editions does" — a nested loop per row, reduced to a set.
 * 3. **The advanced-search link.** The .phtml asks
 *    `isAllowed('route/publications/advanced-search')`, and that resource appears nowhere
 *    in `docs/acl-baseline.json`: there is no such route and no guard entry defining it.
 *    Asking the ACL about an unregistered resource is how you get an exception on a
 *    public page, so the question is asked once, here, and defensively.
 */
final class LiteratureController
{
    /** `PublicationsController::MAX_SEARCH_RESULTS`. */
    private const MAX_SEARCH_RESULTS = 300;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly ViewHelpers $helpers,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly HostMessages $messages
    ) {
    }

    /** GET /literature — the catalogue index. */
    public function home(Request $request): Response
    {
        $table = $this->publications();

        //The three flags are the visitor's own corpus permissions, so the counts a
        //visitor sees are the counts they could actually browse.
        //
        //getPublicationLanguageCounts() annotates all three parameters `string` and uses
        //them as booleans; `PublicationsController::literatureHomeAction()` passes
        //`isAllowed()` results, i.e. bools, and has since the method was written. The
        //annotation is what is wrong, so the ignore is on the call rather than a cast
        //that would change what the method receives.
        $user      = $this->isAllowed('publication_user', 'show');
        $institute = $this->isAllowed('publication_institute', 'show');
        $patres    = $this->isAllowed('publication_patres', 'show');

        /** @phpstan-ignore argument.type, argument.type, argument.type */
        $counts = $table->getPublicationLanguageCounts($user, $institute, $patres);

        /** @var DictionaryTable $dictionaries */
        $dictionaries = $this->laminas->get(DictionaryTable::class);
        /** @var LibraryTable $libraries */
        $libraries = $this->laminas->get(LibraryTable::class);

        return new Response($this->twig->render('books/literature-home.html.twig', $this->chrome() + [
            //translated once, here, and then rendered with translation off — the .phtml
            //does exactly that with headTitle()->setTranslatorEnabled(false)
            'page_title'           => $this->translate('Schoenstatt Literature Tools'),
            'page_title_translate' => false,
            'meta_description'     => $this->translate(
                'Literature relating to Schoenstatt. This index has been compiled from several libraries '
                . 'of the Schoenstatt Fathers and will continue to grow as we review the information of '
                . 'further books.'
            ),
            'breadcrumbs'  => $this->breadcrumbs(),
            'objects'      => $counts,
            'languages'    => $table->getLanguageNames(Locale::getPrimaryLanguage(Locale::getDefault())),
            'dictionaries' => $dictionaries->getAvailableDictionaryLanguages(),
            'libraries'    => $libraries->getObjects('library'),
        ]));
    }

    /** GET /literature/{inLanguage} — every publication in one language. */
    public function index(Request $request): Response
    {
        $language = $request->attributes->get('inLanguage');
        if (! is_string($language)) {
            return $this->notFound();
        }

        /** @var array<mixed> $objects */
        $objects = $this->publications()->searchPublications(
            ['inLanguage' => [$language]],
            ['noSubEditions' => true]
        );

        return new Response($this->twig->render('books/literature-index.html.twig', $this->chrome() + [
            'page_title'           => $this->indexTitle($language),
            'page_title_translate' => false,
            'breadcrumbs'          => $this->breadcrumbs(
                $this->indexTitle($language),
                $this->urls->path('publications/index', ['inLanguage' => $language])
            ),
            'objects'              => $this->groupByCategory($objects),
            'covers'               => $this->covers($objects),
            'downloads'            => $this->downloads($objects),
        ]));
    }

    /** GET /literature/search — the result table for a query string. */
    public function search(Request $request): Response
    {
        $form = $this->form();
        $form->setData($request->query->all());

        $entities = null;
        if ($form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            if ([] !== $data) {
                $options = ['maxResults' => self::MAX_SEARCH_RESULTS];
                //the original's tests, and both are string comparisons against '1'
                //rather than truthiness — an unchecked box arrives as '0'
                if (! isset($data['showEditionsSeparately']) || '1' !== (string) $data['showEditionsSeparately']) {
                    $options['noSubEditions'] = true;
                }
                if (isset($data['includeDataSources']) && '1' === (string) $data['includeDataSources']) {
                    $options['includeDataSources'] = true;
                }

                /** @var array<mixed> $entities */
                $entities = $this->publications()->searchPublications($data, $options);

                if (self::MAX_SEARCH_RESULTS === count($entities)) {
                    $this->nowMessage(
                        FlashMessages::NAMESPACE_INFO,
                        'More than the max number of publications match your search. '
                        . 'Only the first 300 results shown.'
                    );
                }
            }
        }

        if (is_array($entities) && [] === $entities) {
            $this->nowMessage(FlashMessages::NAMESPACE_INFO, 'No results found.');
        }

        return new Response($this->twig->render('books/literature-search.html.twig', $this->chrome($form) + [
            //`headTitle($title)` with the translator left **on**, unlike the other two
            //literature pages — so no page_title_translate here
            //**No breadcrumbs.** The laminas search page renders an empty
            //`<div class="row"></div>` where the trail would go — it is not in the
            //`navigation` config, so the Navigation service produces nothing for it. Adding
            //one looked like an improvement and is a difference the baseline diff caught.
            'page_title'  => 'Publications search',
            'entities'   => $entities,
            'covers'     => null === $entities ? [] : $this->covers($entities),
        ]));
    }

    /**
     * The variables all three pages share: the search form and the three URLs around it.
     *
     * @param FormInterface<array<string, mixed>>|null $form the search page's own,
     *        already populated with the query; the other two get a fresh one
     * @return array<string, mixed>
     */
    private function chrome(?FormInterface $form = null): array
    {
        return [
            'form'                => $form ?? $this->form(),
            'search_url'          => $this->urls->path('publications/search'),
            'create_url'          => $this->urls->path('publications/create'),
            'advanced_search_url' => $this->advancedSearchUrl(),
        ];
    }

    /**
     * The breadcrumb trail, which a ported page states rather than derives.
     *
     * The laminas trail comes from the Navigation service, which no Symfony route can
     * build — its factory needs an MvcEvent — so every ported page has passed its own
     * since batch 3. Where the navigation's label is DB-derived the two differ, and that
     * is a known, accepted divergence rather than a new one: `/dictionary/es` has shown
     * "German to Spanish Dictionary" against the ported page's "Fr. Kentenich dictionary
     * German to Spanish" since it was ported, and docs/BACKLOG.md records those two
     * labels as untranslatable-by-construction anyway.
     *
     * What *is* reproduced is the shape: `Literature` first, linked, then the page. The
     * laminas trail for `/literature` itself ends in a second crumb reading
     * "Dictionaries" — the navigation tree's last matching node, because this page has a
     * `#dictionaries` anchor — which is an artifact rather than a trail anyone meant, and
     * is not reproduced.
     *
     * @param string|null $leafHref the leaf's own URL. Required, not optional: the
     *        layout reads `crumb.href` unconditionally — both to compare against the
     *        current path and to build the JSON-LD trail — and Twig's strict_variables
     *        makes a missing key a RuntimeError, i.e. an empty 200 on the page. Measured:
     *        a leaf passed without one took every `/literature/{lang}` page down.
     * @return list<array{label: string, href: string, translate?: bool}>
     */
    private function breadcrumbs(?string $leaf = null, ?string $leafHref = null): array
    {
        $trail = [['label' => 'Literature', 'href' => $this->urls->path('publications')]];
        if (null !== $leaf && null !== $leafHref) {
            //`translate => false`: the leaf is this page's own already-translated title,
            //and running it through the translator again would file a phrase per language
            $trail[] = ['label' => $leaf, 'href' => $leafHref, 'translate' => false];
        }

        return $trail;
    }

    /**
     * The advanced-search link, or null.
     *
     * `route/publications/advanced-search` is not a resource this application defines —
     * it appears in no guard entry and `docs/acl-baseline.json` has no row for it — and
     * `Laminas\Permissions\Acl::isAllowed()` throws on an unregistered resource. The
     * laminas page gets away with asking because BjyAuthorize's `Config` resource
     * provider registers whatever the guards name and the ACL happens to tolerate the
     * miss; rather than depend on that, the exception is caught and read as "no".
     *
     * The honest fix is to define the resource or delete the link, and it is recorded in
     * docs/BACKLOG.md alongside the other misnamed guards.
     */
    private function advancedSearchUrl(): ?string
    {
        try {
            if (! $this->isAllowed('route/publications/advanced-search')) {
                return null;
            }

            return $this->urls->path('publications/advanced-search');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * `PublicationsController::groupPublicationsByCategory()`, reproduced.
     *
     * The bookmark is the category name slugified — lowercased, spaces to hyphens, then
     * everything that is not `[a-z-]` removed — and it is what the sidebar anchors point
     * at. "Uncategorized" is appended unconditionally, so it exists even when empty; both
     * loops in the template then skip it, which is why an empty category renders nowhere.
     *
     * The original's own warning is worth carrying: this only works if child categories
     * are sorted directly after their parents.
     *
     * @param array<mixed> $objects
     * @return array<string, array<string, mixed>>
     */
    private function groupByCategory(array $objects): array
    {
        $categories  = [];
        $uncatalogued = [];

        foreach ($objects as $publicationId => $object) {
            if (! is_array($object)) {
                continue;
            }
            $name = $object['categoryName'] ?? null;
            if (! is_string($name)) {
                $uncatalogued[$publicationId] = $object;
                continue;
            }

            if (! isset($categories[$name])) {
                $bookmark = (string) preg_replace('/\s+/', '-', strtolower(trim($name)));
                $bookmark = (string) preg_replace('/[^a-z-]+/', '', $bookmark);
                $categories[$name] = [
                    'id'       => $object['categoryId'] ?? null,
                    'name'     => $name,
                    'sort'     => $object['categorySort'] ?? null,
                    'parentId' => $object['categoryParentId'] ?? null,
                    'bookmark' => trim($bookmark, '- '),
                    'objects'  => [],
                ];
            }
            $categories[$name]['objects'][$publicationId] = $object;
        }

        $categories['Uncategorized'] = [
            'id'       => null,
            'name'     => 'Uncategorized',
            'sort'     => 1000,
            'parentId' => null,
            'bookmark' => 'uncategorized',
            'objects'  => $uncatalogued,
        ];

        return $categories;
    }

    /**
     * The heading, which is three different sentences.
     *
     * `xx` is the literal language code for "no language recorded" and gets its own
     * wording rather than "Schoenstatt Literature in xx"; a code ext/intl cannot name
     * falls through to the bare title. Translated here because the .phtml translates and
     * then disables the translator.
     */
    private function indexTitle(string $language): string
    {
        if ('xx' === $language) {
            return $this->translate('Schoenstatt Literature without language');
        }

        $name = $this->helpers->languageName()->__invoke($language);
        if ('' === $name) {
            return 'Schoenstatt Literature';
        }

        return sprintf($this->translate('Schoenstatt Literature in %s'), $name);
    }

    /**
     * publicationId => 80px cover URL, for the rows that have a thumbnail on disk.
     *
     * @param array<mixed> $objects
     * @return array<int|string, string>
     */
    private function covers(array $objects): array
    {
        $covers = [];
        foreach ($objects as $object) {
            $id = is_array($object) ? ($object['publicationId'] ?? null) : null;
            if (null === $id) {
                continue;
            }
            if (file_exists(sprintf('public/covers/%s-80px.jpg', $id))) {
                $covers[$id] = sprintf('/covers/%s-80px.jpg', $id);
            }
        }

        return $covers;
    }

    /**
     * The set of publicationIds that get the "downloadable files" icon: those with Drive
     * files of their own, or with a sub-edition that has some.
     *
     * Empty when the visitor lacks `publication_drive` or the gateway is unreachable —
     * the original wraps the same call in `try {} catch (\Exception) {}` and passes null
     * through to the template, which then shows no icons at all.
     *
     * @param array<mixed> $objects
     * @return array<int|string, true>
     */
    private function downloads(array $objects): array
    {
        if (! $this->isAllowed('publication_drive')) {
            return [];
        }

        try {
            /** @var DriveGateway $gateway */
            $gateway = $this->laminas->get(DriveGateway::class);
            $files   = $gateway->getPublicationFiles();
        } catch (Throwable) {
            return [];
        }

        if (! is_array($files) || [] === $files) {
            return [];
        }

        $marked = [];
        foreach ($objects as $object) {
            if (! is_array($object)) {
                continue;
            }
            $id = $object['publicationId'] ?? null;
            if (null === $id) {
                continue;
            }

            if (array_key_exists($id, $files)) {
                $marked[$id] = true;
                continue;
            }

            $subEditions = $object['subEditions'] ?? null;
            if (! is_array($subEditions)) {
                continue;
            }
            foreach (array_keys($subEditions) as $key) {
                if (array_key_exists($key, $files)) {
                    $marked[$id] = true;
                    break;
                }
            }
        }

        return $marked;
    }

    /** @return FormInterface<array<string, mixed>> */
    private function form(): FormInterface
    {
        $form = $this->laminas->get(PublicationsSearchForm::class);
        if (! $form instanceof FormInterface) {
            throw new RuntimeException('The container did not return a PublicationsSearchForm.');
        }

        return $form;
    }

    private function publications(): PublicationsTable
    {
        /** @var PublicationsTable $table */
        $table = $this->laminas->get(PublicationsTable::class);

        return $table;
    }

    /**
     * A message for the page being rendered — not the next one. It goes into the request's
     * HostMessages, which is what the layout's `now_messages()` renders.
     */
    private function nowMessage(string $namespace, string $message): void
    {
        $this->messages->now($namespace, $message);
    }

    private function isAllowed(string $resource, ?string $privilege = null): bool
    {
        /** @var IsAllowed $helper */
        $helper = $this->laminas->get(IsAllowed::class);

        return (bool) $helper->__invoke($resource, $privilege);
    }

    private function translate(string $message): string
    {
        return $this->laminas->get('MvcTranslator')->translate($message, 'Books');
    }

    private function notFound(): Response
    {
        return new Response(
            'Not found.',
            Response::HTTP_NOT_FOUND,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}
