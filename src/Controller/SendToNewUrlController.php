<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Locale;
use Schoenstatt\Filter\SchoenstattLinkIdentifier as IdentifierFilter;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Validator\SchoenstattLinkIdentifier as IdentifierValidator;
use SionModel\Db\Model\SionTable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function is_array;
use function is_numeric;
use function is_string;
use function strlen;
use function ucwords;

/**
 * The three permanent redirects from pre-2020 URLs to the site-wide identifier form.
 *
 * - `GET /associations/{sw_id}`   — `SL100001A` under the old `/associations` prefix
 * - `GET /associations/{id}`      — the bare numeric association id
 * - `GET /literature/{id}`        — the bare numeric publication id
 *
 * All three are `sendToNewUrlAction()` on their respective laminas controllers, and all
 * three are the same seven lines: look the row up, **301** to its canonical URL, or flash
 * "not found" and 302 to the index. They are in this batch because they are the other
 * half of the show pages — a redirect that keeps working is what stops five years of
 * external links from breaking the moment the show page moves.
 *
 * ## 301, deliberately
 *
 * `$response->setStatusCode(301)` in the original, on a `redirect()->toRoute()` that
 * would otherwise be a 302. A permanent redirect is right — these URLs are never coming
 * back — and reproducing the status rather than the default is the difference between
 * search engines updating their index and re-crawling the old URL forever.
 *
 * ## They take the locale hop too, and that is not obvious
 *
 * These call `LocalePrefix::redirect()` like every other HTML route here, which means
 * `/associations/1` answers **302 to `/en/associations/1`** and only then 301s to the
 * destination. Written without it first, on the reasoning that a redirect to a redirect
 * is a wasted hop — and the baseline diff refused it: laminas sends exactly that 302,
 * because SlmLocale's strategy runs before the action does and does not care that the
 * action was itself going to redirect.
 *
 * Skipping the hop is not obviously wrong and the visitor lands in the same place. It is
 * still a difference, on a URL search engines have indexed for five years, and this batch
 * is not the place to decide it. Reproduced.
 */
final class SendToNewUrlController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly RouteUrl $urls
    ) {
    }

    /**
     * `/associations/{sw_id}` — the identifier form under the old prefix.
     *
     * The route constraint is `SL1[0-9]{4,5}A`, one digit looser than the association
     * identifier regex, and the extra digit is the point: an **eight-character**
     * identifier is the pre-April-2020 numbering, which the filter decodes differently.
     * `strlen($swId) === 8` is how the laminas action tells them apart, so
     * App\Sion\SiteWideIdentifier is deliberately *not* used here — it knows only the
     * current format, and would decode an old identifier to the wrong association rather
     * than to none at all.
     */
    public function associationBySwId(Request $request): Response
    {
        $swId = $request->attributes->get('sw_id');
        if (! is_string($swId)) {
            return $this->notFound('association', 'associations');
        }

        $redirect = LocalePrefix::redirect($request, $this->urls, 'associations/association', ['sw_id' => $swId]);
        if (null !== $redirect) {
            return $redirect;
        }

        $filter = new IdentifierFilter(IdentifierValidator::ENTITY_ASSOCIATION, 8 === strlen($swId));
        $id     = $filter->filter($swId);

        return is_numeric($id)
            ? $this->association((int) $id)
            : $this->notFound('association', 'associations');
    }

    /** `/associations/{association_id}` — the bare numeric id. */
    public function associationById(Request $request): Response
    {
        $id = $request->attributes->get('association_id');
        if (! is_numeric($id)) {
            return $this->notFound('association', 'associations');
        }

        $redirect = LocalePrefix::redirect($request, $this->urls, 'associations/old-association', [
            'association_id' => (string) $id,
        ]);
        if (null !== $redirect) {
            return $redirect;
        }

        return $this->association((int) $id);
    }

    /** `/literature/{publication_id}` — the bare numeric id. */
    public function publicationById(Request $request): Response
    {
        $id = $request->attributes->get('publication_id');
        if (! is_numeric($id)) {
            return $this->notFound('publication', 'publications');
        }

        $redirect = LocalePrefix::redirect($request, $this->urls, 'publications/publication-old', [
            'publication_id' => (string) $id,
        ]);
        if (null !== $redirect) {
            return $redirect;
        }

        /** @var SionTable $table */
        $table = $this->laminas->get(\Books\Model\PublicationsTable::class);
        /** @var mixed $object */
        $object = $table->getObject('publication', (int) $id, true);

        return $this->moved('publication', $object, 'publications');
    }

    private function association(int $id): Response
    {
        /** @var SchoenstattTable $table */
        $table = $this->laminas->get(SchoenstattTable::class);

        //getAssociation() rather than getObject(), for the reason
        //App\Sion\EntityShow::load() records: AssociationsController overrides
        //getEntityObject() to call it.
        //
        //**`slugByLocale[locale]`, not `slug`.** An association's slug is per-locale —
        //`saekularinstitut-schoenstatt-patres` in de_DE against
        //`secular-institute-of-schoenstatt-fathers` in en_US — where a publication's is a
        //single column. Reading `slug` here finds nothing, and the redirect silently
        //degrades into the not-found 302, which looks exactly like an association that
        //does not exist. Measured: /en/associations/1 answered 302 to /en/associations
        //where laminas answers 301 to the shrine's page.
        /** @var mixed $object */
        $object = $table->getAssociation($id);
        /** @var mixed $slug */
        $slug = is_array($object) ? ($object['slugByLocale'][Locale::getDefault()] ?? null) : null;

        return $this->moved('association', $object, 'associations', is_string($slug) ? $slug : null);
    }

    /**
     * The 301 to a row's canonical URL, or the not-found redirect when the row is gone.
     *
     * The original builds the target from `identifier` **and** `slug` and would emit a
     * URL missing a segment if either were absent; both are computed columns that always
     * exist, so this reproduces the same assembly and falls back rather than emitting a
     * broken link.
     */
    private function moved(string $entity, mixed $object, string $indexRoute, ?string $slug = null): Response
    {
        if (! is_array($object) || ! isset($object['identifier'])) {
            return $this->notFound($entity, $indexRoute);
        }

        //the caller supplies the slug when its entity keeps one per locale; otherwise it
        //is the row's own single column, as it is for a publication
        if (null === $slug) {
            /** @var mixed $own */
            $own  = $object['slug'] ?? null;
            $slug = is_string($own) ? $own : null;
        }
        if (null === $slug) {
            return $this->notFound($entity, $indexRoute);
        }

        return new RedirectResponse(
            $this->urls->path($entity, [
                'sw_id' => (string) $object['identifier'],
                'slug'  => $slug,
            ]),
            Response::HTTP_MOVED_PERMANENTLY
        );
    }

    /**
     * `ucwords($entity) . ' not found.'`, then 302 to the entity's index — the tail of
     * every `sendToNewUrlAction()`.
     */
    private function notFound(string $entity, string $indexRoute): Response
    {
        (new FlashMessenger())
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage(ucwords($entity) . ' not found.');

        return new RedirectResponse($this->urls->path($indexRoute), Response::HTTP_FOUND);
    }
}
