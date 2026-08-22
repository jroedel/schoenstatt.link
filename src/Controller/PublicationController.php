<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Laminas\ViewHelpers;
use App\Laminas\ServiceBridge;
use App\Sion\EntityShow;
use App\View\PreferredUrls;
use App\Sion\SiteWideIdentifier;
use Books\Model\LibraryTable;
use Books\Service\DriveGateway;
use BjyAuthorize\View\Helper\IsAllowed;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Validator\SchoenstattLinkIdentifier as IdentifierValidator;
use SionModel\Db\Model\FilesTable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twig\Environment;

use function array_keys;
use function array_merge;
use function file_exists;
use function is_array;
use function is_string;
use function sprintf;

/**
 * GET /{sw_id}[/{slug}] for a publication — the bibliographic page.
 *
 * The heaviest of the four show pages, and the one that needed the most groundwork:
 * six view helpers bridged into App\Laminas\ViewHelpers and four `FormatPublication`
 * display modes added to App\Laminas\EntityFormatter, which until this page reproduced
 * `title` alone and raised on the rest.
 *
 * ## Four things `PublicationsController::showAction()` adds, all reproduced here
 *
 * 1. **The merged-edition 301.** A publication carrying `mergedIntoPublicationId` is a
 *    duplicate that has been folded into a surviving row, and a visitor without
 *    `publication_user` is redirected to that row permanently. Signed-in editors keep
 *    seeing the merged row, which is what lets them audit the merge. Measured:
 *    `/en/SL200417L` is a 301 to `/en/SL207340L/de-las-reducciones-a-la-nacion-de-dios`
 *    anonymously and a 200 signed in.
 * 2. **The cover file**, when `bookCoverFileId` names one.
 * 3. **The Drive files**, gated on `publication_drive` and wrapped in the original's own
 *    `catch (\Exception)` — the gateway is an HTTP call to an external files API, and a
 *    publication page must not 500 because that API is slow. Sub-edition files are merged
 *    in, which is how a visitor finds the PDF attached to a different printing.
 * 4. **The library copies**, filtered to the catalogues the visitor may see *before*
 *    searching them. That ordering is the access control: `searchBooks()` is asked only
 *    about libraries that passed `isAllowed($library['resourceId'], 'show')`, so a
 *    private catalogue's holdings never enter the result set at all.
 *
 * ## The ACL check on the page itself is App\Sion\EntityShow's
 *
 * `publication` is the one entity in this batch declaring `acl_resource_id_field`, so
 * whether this row is visible at all is decided there — with the `show` privilege, which
 * is the detail that makes `publication_public` readable by `guest`.
 */
final class PublicationController
{
    private const ENTITY = 'publication';

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly EntityShow $show,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly ViewHelpers $helpers,
        private readonly PreferredUrls $preferredUrls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $swId = $request->attributes->get('sw_id');
        $slug = $request->attributes->get('slug');
        if (! is_string($swId)) {
            return $this->notFound();
        }

        $params = ['sw_id' => $swId] + (is_string($slug) ? ['slug' => $slug] : []);

        $id = SiteWideIdentifier::toId(IdentifierValidator::ENTITY_PUBLICATION, $swId);
        if (null === $id) {
            return $this->notFound();
        }

        //the request path is `url(null, [], [], true)` — the page the visitor is on,
        //which is where a posted comment returns them
        $selfUrl = $request->getPathInfo();
        $data    = $this->show->load(self::ENTITY, $id, null, $selfUrl);
        if (null === $data) {
            $this->flash($this->show->deniedMessage(self::ENTITY, $id));

            return new RedirectResponse($this->urls->path('publications'), Response::HTTP_FOUND);
        }

        $entity = $data->entity;

        $merged = $this->mergedRedirect($entity);
        if (null !== $merged) {
            return $merged;
        }

        $entity['bookCoverFile'] = $this->coverFile($entity);
        $entity['files']         = $this->driveFiles($entity);

        [$libraries, $libraryBooks] = $this->libraryCopies($entity);

        $publicationId = $entity['publicationId'] ?? $id;

        return new Response($this->twig->render('books/publication.html.twig', [
            /*
             * The five URLs this record should be indexed under, for the layout's canonical
             * and hreflang links. A publication has a single `Slug` column, so the five differ
             * only in the locale prefix — but this is still needed, because a request that
             * omits the slug would otherwise declare the slugless URL canonical while the
             * sitemap and every menu link advertise the slugged one. See App\View\PreferredUrls.
             */
            'locale_paths'         => $this->preferredUrls->forRecord(
                self::ENTITY,
                ['sw_id' => $swId, 'slug' => $entity['slug'] ?? null]
            ),
            //headTitle()->setTranslatorEnabled(false) then the title: a bibliographic
            //title is data, and translating it files a phrase per publication
            'page_title'           => (string) ($entity['title'] ?? ''),
            'page_title_translate' => false,
            'meta_description'     => sprintf(
                $this->translate('Bibliographical information about "%s".'),
                (string) ($entity['title'] ?? '')
            ),
            'breadcrumbs'    => $this->breadcrumbs($entity, $selfUrl),
            'entity'         => $entity,
            'sw_id'          => $swId,
            'other_editions' => array_merge(
                is_array($entity['subEditions'] ?? null) ? $entity['subEditions'] : [],
                is_array($entity['translations'] ?? null) ? $entity['translations'] : []
            ),
            'cover_url'       => $this->coverUrl($publicationId, false),
            'cover_thumb_url' => $this->coverUrl($publicationId, true),
            'edit_url'        => $this->urls->path('publication-edit', ['sw_id' => $swId]),
            'new_edition_url' => $this->urls->path('publication-create-new-edition', ['sw_id' => $swId]),
            'merged_into_url' => $this->mergedIntoUrl($entity),
            'copy_to_corpus_url' => $this->copyToCorpusUrl($entity),
            'libraries'      => $libraries,
            'library_books'  => $libraryBooks,
            'changes'        => $data->changes,
            'comments'       => $data->comments,
            'comment_form'   => $data->commentForm,
            'comment_action' => $this->urls->path('comments/create', [
                'entity'    => self::ENTITY,
                'entity_id' => $id,
            ]),
            'visits'         => $data->visits,
        ]));
    }

    /**
     * The permanent redirect to the surviving edition, or null when this row is not a
     * merged duplicate or the visitor may see merged rows.
     *
     * The original resolves the target with `queryObjects()` and only redirects when
     * exactly one row comes back *and* it carries both an identifier and a slug —
     * three conditions, each of which silently declines to redirect. Reproduced: a
     * merge pointing at a missing row leaves the visitor on the duplicate, which is
     * better than a redirect loop or a 404.
     *
     * @param array<string, mixed> $entity
     */
    private function mergedRedirect(array $entity): ?RedirectResponse
    {
        $mergedInto = $entity['mergedIntoPublicationId'] ?? null;
        if (null === $mergedInto || $this->isAllowed('publication_user', 'show')) {
            return null;
        }

        $table   = $this->laminas->get(\Books\Model\PublicationsTable::class);
        $results = $table->queryObjects(self::ENTITY, ['publicationId' => $mergedInto]);
        if (! is_array($results) || 1 !== count($results)) {
            return null;
        }

        $target = current($results);
        if (! is_array($target) || ! isset($target['identifier'], $target['slug'])) {
            return null;
        }

        return new RedirectResponse(
            $this->urls->path(self::ENTITY, [
                'sw_id' => (string) $target['identifier'],
                'slug'  => (string) $target['slug'],
            ]),
            Response::HTTP_MOVED_PERMANENTLY
        );
    }

    /**
     * The cover image URLs, or null when no file is on disk.
     *
     * `file_exists('public/covers/%s-400px.jpg')` is the original's test, relative to the
     * working directory — which is the docroot's parent under both front controllers.
     * The *thumbnail's* existence gates both links there, and does here: a full-size
     * cover with no thumbnail renders nothing, which is the pre-existing behaviour.
     */
    private function coverUrl(mixed $publicationId, bool $thumbnail): ?string
    {
        if (! is_int($publicationId) && ! is_string($publicationId)) {
            return null;
        }

        if (! file_exists(sprintf('public/covers/%s-400px.jpg', $publicationId))) {
            return null;
        }

        return $thumbnail
            ? sprintf('/covers/%s-400px.jpg', $publicationId)
            : sprintf('/covers/%s.jpg', $publicationId);
    }

    /**
     * The stored cover-file row, when `bookCoverFileId` names one.
     *
     * @param array<string, mixed> $entity
     */
    private function coverFile(array $entity): mixed
    {
        $fileId = $entity['bookCoverFileId'] ?? null;
        if (null === $fileId) {
            return null;
        }

        /** @var FilesTable $files */
        $files = $this->laminas->get(FilesTable::class);

        return $files->getFile($fileId);
    }

    /**
     * This publication's Drive files, including its sub-editions', or an empty list.
     *
     * The whole thing is inside the original's `try {} catch (\Exception) {}`, and the
     * catch is widened to Throwable here for the reason the composition parser's is: the
     * gateway is an outbound HTTP call and a publication page is public.
     *
     * @param array<string, mixed> $entity
     * @return array<mixed>
     */
    private function driveFiles(array $entity): array
    {
        if (! $this->isAllowed('publication_drive')) {
            return [];
        }

        try {
            /** @var DriveGateway $gateway */
            $gateway = $this->laminas->get(DriveGateway::class);
            $all     = $gateway->getPublicationFiles();
        } catch (Throwable) {
            return [];
        }

        if (! is_array($all)) {
            return [];
        }

        $publicationId = $entity['publicationId'] ?? null;
        $files         = is_array($all[$publicationId] ?? null) ? $all[$publicationId] : [];

        $subEditions = $entity['subEditions'] ?? null;
        if (is_array($subEditions)) {
            foreach (array_keys($subEditions) as $key) {
                if (is_array($all[$key] ?? null)) {
                    $files = array_merge($files, $all[$key]);
                }
            }
        }

        return $files;
    }

    /**
     * The visible library catalogues and this publication's copies in them.
     *
     * The filtering happens **before** the search, which is the access control rather
     * than a display choice — see the class docblock.
     *
     * @param array<string, mixed> $entity
     * @return array{0: array<mixed>, 1: array<mixed>}
     */
    private function libraryCopies(array $entity): array
    {
        /** @var LibraryTable $table */
        $table     = $this->laminas->get(LibraryTable::class);
        $libraries = $table->getObjects('library');
        if (! is_array($libraries)) {
            return [[], []];
        }

        foreach ($libraries as $libraryId => $library) {
            //the original indexes the row directly too; `is_string` below is the guard
            //that matters, because a library with no resource id must not be searched
            $resource = $library['resourceId'] ?? null;
            if (! is_string($resource) || ! $this->isAllowed($resource, 'show')) {
                unset($libraries[$libraryId]);
            }
        }

        $publicationIds = [$entity['publicationId'] ?? null];
        $subEditions    = $entity['subEditions'] ?? null;
        if (is_array($subEditions) && [] !== $subEditions) {
            //`array_keys`, not the array itself. `subEditions` is keyed by publication id
            //with whole rows as values, so merging it appended row *arrays* to a list of
            //ids. `searchBooks()` filters its `publicationId` list with `is_numeric()`, so
            //they were dropped without a word and this line has never once added a
            //sub-edition to the library search it exists to widen. 147 library copies
            //across 62 publications, measured 2026-08-22. Twenty lines up, the same method
            //does it correctly.
            $publicationIds = array_merge($publicationIds, array_keys($subEditions));
        }

        $books = $table->searchBooks([
            'libraryId'     => array_keys($libraries),
            'publicationId' => $publicationIds,
        ]);
        if (! is_array($books)) {
            return [$libraries, []];
        }

        foreach ($books as $bookId => $book) {
            $libraryId = is_array($book) ? ($book['libraryId'] ?? null) : null;
            if (isset($libraries[$libraryId])) {
                $books[$bookId]['library'] = $libraries[$libraryId];
            }
        }

        return [$libraries, $books];
    }

    /**
     * `Literature > <language catalogue> > <title>`.
     *
     * The middle crumb is the language catalogue this publication is filed under, and it
     * is **stated from the record rather than derived from the navigation tree**. laminas
     * builds it from a Navigation branch — one node per language, each holding its
     * publications — and `App\View\NavigationTree` can now reproduce that container; but
     * doing so here would unserialize the 2.24 MB `publication-pages` branch on every
     * publication page to learn a language code the entity already carries. The tree is
     * for `/sitemap.xml`, which needs all 10,974 pages at once.
     *
     * The label is `LiteratureController`'s own title for the page it links to, so the
     * crumb and its destination agree. laminas says "French Schoenstatt Literature" here
     * in every locale, because the navigation label is data-marked and never translated;
     * this says "Schoenstatt Literature in French" and translates it. That is a deliberate
     * improvement rather than an oversight: docs/BACKLOG.md records the laminas labels as
     * untranslatable by construction, and this crumb costs no phrase — the pattern is one
     * string and the language name comes from ext/intl.
     *
     * The leaf carries `translate: false` because it is a bibliographic title: see
     * test/Smoke/BreadcrumbDataLabelsSmokeTest, which asserts against `trans_phrases`
     * rather than the markup.
     *
     * @param array<string, mixed> $entity
     * @return list<array{label: string, href: string, translate?: bool}>
     */
    private function breadcrumbs(array $entity, string $selfUrl): array
    {
        $trail = [['label' => 'Literature', 'href' => $this->urls->path('publications')]];

        //`inLanguage` is a list and the navigation files a publication under its *first*
        //language, which is the one the catalogue page it links to would list it on. A
        //publication with none is filed under no catalogue at all and gets no crumb, the
        //same as laminas.
        $languages = $entity['inLanguage'] ?? null;
        $language  = is_array($languages) && isset($languages[0]) && is_string($languages[0])
            ? $languages[0]
            : null;
        if (null !== $language && '' !== $language) {
            $trail[] = [
                'label'     => $this->catalogueTitle($language),
                'href'      => $this->urls->path('publications/index', ['inLanguage' => $language]),
                //already translated, and translating it again would look it up as a
                //composed sentence that exists in no catalog
                'translate' => false,
            ];
        }

        $trail[] = ['label' => (string) ($entity['title'] ?? ''), 'href' => $selfUrl, 'translate' => false];

        return $trail;
    }

    /**
     * The language catalogue's own page title, reproduced from
     * `LiteratureController::indexTitle()`.
     *
     * Duplicated deliberately rather than shared: making one controller depend on another
     * for a string is a heavier coupling than seven lines, and the pair is pinned by
     * test/Smoke/ReadingSurfaceSmokeTest, which asserts the crumb equals the destination
     * page's heading.
     */
    private function catalogueTitle(string $language): string
    {
        if ('xx' === $language) {
            return $this->translate('Schoenstatt Literature without language');
        }

        $name = $this->helpers->languageName()->__invoke($language);

        return '' === $name
            ? 'Schoenstatt Literature'
            : sprintf($this->translate('Schoenstatt Literature in %s'), $name);
    }

    /** @param array<string, mixed> $entity */
    private function mergedIntoUrl(array $entity): ?string
    {
        $mergedInto = $entity['mergedIntoPublicationId'] ?? null;
        if (null === $mergedInto) {
            return null;
        }

        $swId = (new ToSchoenstattLinkIdentifier(self::ENTITY))->filter($mergedInto);

        return is_string($swId) ? $this->urls->path(self::ENTITY, ['sw_id' => $swId]) : null;
    }

    /**
     * The "Copy into main corpus" button's URL — shown only for a data-sourced row that
     * has *not* been merged, which is the original's `elseif`, **and only to somebody the
     * route would actually let through.**
     *
     * That last condition was missing on both front controllers until 2026-08-14, and
     * production's exception store is how it surfaced: the route accumulated **3,439**
     * `UnAuthorizedException`s in eleven days — the next-noisiest fingerprint had 64 —
     * from crawlers walking every data-sourced publication and following the button.
     * There are ~4,165 such rows and five locales, so the button was advertising a
     * `pub_moderator`-only action across roughly twenty thousand public URLs. Every hit
     * cost a full bootstrap and ACL load to answer with a redirect to a login form.
     *
     * The check names the **route resource**, not the role list, so it cannot drift from
     * the guard in `module/Books/config/module.config.php`: one place decides, and this
     * asks it. `mergedIntoUrl()` above needs no equivalent — that link goes to a page
     * every visitor may read.
     *
     * @param array<string, mixed> $entity
     */
    private function copyToCorpusUrl(array $entity): ?string
    {
        if (isset($entity['mergedIntoPublicationId']) || ! isset($entity['dataSource'])) {
            return null;
        }

        if (! $this->isAllowed('route/publication-copy-to-main-corpus')) {
            return null;
        }

        $identifier = $entity['identifier'] ?? null;
        if (! is_string($identifier)) {
            return null;
        }

        $params = ['sw_id' => $identifier];
        $slug   = $entity['slug'] ?? null;
        if (is_string($slug)) {
            $params['slug'] = $slug;
        }

        return $this->urls->path('publication-copy-to-main-corpus', $params);
    }

    private function isAllowed(string $resource, ?string $privilege = null): bool
    {
        /** @var IsAllowed $helper */
        $helper = $this->laminas->get('ViewHelperManager')->get('isAllowed');

        return (bool) $helper->__invoke($resource, $privilege);
    }

    private function translate(string $message): string
    {
        return $this->laminas->get('MvcTranslator')->translate($message, 'Books');
    }

    private function flash(string $message): void
    {
        (new FlashMessenger())->setNamespace(FlashMessenger::NAMESPACE_ERROR)->addMessage($message);
    }

    private function notFound(): Response
    {
        return new Response(
            'Publication not found.',
            Response::HTTP_NOT_FOUND,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}
