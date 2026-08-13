<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Sion\EntityShow;
use App\Sion\SiteWideIdentifier;
use Carbon\Carbon;
use DateTimeInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Locale;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Service\AssociationKindsService;
use Schoenstatt\Validator\SchoenstattLinkIdentifier as IdentifierValidator;
use Spatie\SchemaOrg\BaseType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function cos;
use function current;
use function floor;
use function in_array;
use function is_array;
use function is_string;
use function log;
use function sprintf;
use function tan;

use const M_PI;

/**
 * GET /{sw_id}[/{slug}] for an association — the page behind every shrine on the map.
 *
 * ## The rule that is not in the ACL
 *
 * `route/association` is guarded `['guest', 'sch_basic', 'sch_user', 'user']`, i.e.
 * publicly reachable. But `AssociationsController::showAction()` then redirects an
 * **anonymous** visitor to `welcome` for every association whose kind is not
 * `sch-shrine` or `sch-wayside-shrine`. So the shrines are public and the institutes,
 * movements and legal entities are not — a rule that lives in a controller, that no
 * guard entry hints at, and that `tools/acl-table.php` cannot see.
 *
 * Found by capturing the laminas baseline rather than by reading the ACL: `/en/SL100319A`
 * (the Original Shrine) answers 200 anonymously and `/en/SL100001A` (the Secular
 * Institute of Schoenstatt Fathers) answers 302 to `/en/`. Both are in
 * tools/port-baseline.php for exactly that reason — one of them alone would compare only
 * half the branch.
 *
 * ## What is computed here rather than in the template
 *
 * The .phtml does trigonometry inline to place an OpenStreetMap tile, and assembles six
 * `associations/create` URLs with differing query strings. Both move here: a template
 * that computes a Mercator projection is a template nobody will dare change.
 */
final class AssociationController
{
    private const ENTITY = 'association';

    /**
     * The two kinds an anonymous visitor may see — AssociationsController::showAction().
     *
     * Public because App\Sitemap\GuestAccess reads it: this rule is the reason 1,240 sitemap
     * entries used to point at a redirect to the home page, and the sitemap has to enforce
     * the same list rather than a copy of it. Nothing else may write to it.
     *
     * @var list<string>
     */
    public const PUBLIC_KINDS = ['sch-shrine', 'sch-wayside-shrine'];

    /** The zoom level the .phtml hardcodes for its static map tile. */
    private const MAP_ZOOM = 13;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly EntityShow $show,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
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

        $redirect = LocalePrefix::redirect($request, $this->urls, self::ENTITY, $params);
        if (null !== $redirect) {
            return $redirect;
        }

        $id = SiteWideIdentifier::toId(IdentifierValidator::ENTITY_ASSOCIATION, $swId);
        if (null === $id) {
            return $this->notFound();
        }

        //the loader, not getObject() — AssociationsController::getEntityObject() calls
        //getAssociation(), which is what links the children, the parent, the roles and
        //the assignments onto the row. See App\Sion\EntityShow::load().
        $table = $this->table();
        $data  = $this->show->load(self::ENTITY, $id, $table->getAssociation(...));
        if (null === $data) {
            $this->flash($this->show->deniedMessage(self::ENTITY, $id));

            return new RedirectResponse($this->urls->path('associations'), Response::HTTP_FOUND);
        }

        $entity = $data->entity;

        //the rule that is not in the ACL — see the class docblock. No flash: the laminas
        //branch sets none either, so an anonymous visitor simply arrives at the front page.
        if (! $this->hasIdentity() && ! in_array($entity['kind'] ?? null, self::PUBLIC_KINDS, true)) {
            return new RedirectResponse($this->urls->path('welcome'), Response::HTTP_FOUND);
        }

        //`sch-national-movement` gets the sibling list, with itself removed
        if ('sch-national-movement' === ($entity['kind'] ?? null) && isset($entity['country'])) {
            $siblings = $table->getNationalAssociations($entity['country']);
            if (is_array($siblings)) {
                unset($siblings[$entity['associationId'] ?? null]);
                $entity['nationalOrganizations'] = $siblings;
            }
        }

        $schema = $table->getAssociationSchemaV1($entity);
        $locale = Locale::getDefault();

        return new Response($this->twig->render('schoenstatt/association.html.twig', [
            //the name is data; translating it would file a phrase per association
            'page_title'           => $this->localized($entity, 'nameByLocale', $locale),
            'page_title_translate' => false,
            'name'                 => $this->localized($entity, 'nameByLocale', $locale),
            'breadcrumbs'          => $this->breadcrumbs($entity, $locale, $request->getPathInfo()),
            'internal_name_localized' => $this->localizedOrNull($entity, 'internalNameByLocale', $locale),
            'entity'               => $entity,
            'kind_labels'          => $this->kindLabels(),
            'public_notes'         => $this->localizedOrNull($entity, 'publicNotesByLocale', $locale)
                ?? ($entity['publicNotes'] ?? null),
            'map_url'              => $this->mapUrl($entity),
            'map_tile_url'         => $this->mapTileUrl($entity),
            'phones_updated_tooltip' => $this->phonesUpdatedTooltip($entity),
            'edit_url'             => $this->urls->path('association-edit', ['sw_id' => $swId]),
            'add_assignment_url'   => $this->addAssignmentUrl($entity),
            'add_national_url'     => $this->urls->path('associations/create', [], [
                'query' => ['country' => $entity['country'] ?? null],
            ]),
            'add_child_url'        => $this->addChildUrl($entity),
            'add_child_label'      => 'sch-shrine' === ($entity['kind'] ?? null)
                ? 'Add apostolate/branch'
                : 'Add associated association',
            'add_role_url'         => $this->urls->path('roles/create', [], [
                'query' => ['associationId' => $entity['associationId'] ?? null],
            ]),
            'role_assignment_urls' => $this->roleAssignmentUrls($entity),
            'changes'              => $data->changes,
            'visits'               => $data->visits,
            //getAssociationSchemaV1() is annotated as returning a schema object, but the
            //instanceof is what actually holds: it returns null for a kind with no
            //schema_type in the entity spec.
            'schema'               => $this->schemaArray($schema),
        ]));
    }

    private function table(): SchoenstattTable
    {
        /** @var SchoenstattTable $table */
        $table = $this->laminas->get(SchoenstattTable::class);

        return $table;
    }

    /**
     * The breadcrumb trail: the index this association belongs to, its region, then its
     * own name.
     *
     * **The region crumb is stated, not derived**, and that is the decision worth reading.
     * laminas gets it from the Navigation service, which hangs a region node under
     * `Shrines` and every shrine under its region; `App\View\NavigationTree` can now
     * reproduce that whole container, so deriving it here is *possible* — and it would
     * cost every association page an 8-10 ms unserialize of the 2.24 MB
     * `publication-pages` branch and its siblings, to learn one string this controller is
     * already holding in `$entity['countryRegion']`. The tree is for `/sitemap.xml`, which
     * needs all 10,974 pages; a page that needs one crumb states it.
     *
     * The href matches the navigation node's: `shrines` with the region as a URL fragment,
     * which is what `Application\Navigation\PageBuilder` builds and what the shrine index
     * anchors. The label **is** translated, unlike the leaf — region and country names are
     * the one part of that branch `markDataLabels()` deliberately leaves translatable,
     * because "Europe" is language and a shrine's name is not.
     *
     * **`translate: false` on the leaf is load-bearing.** An association name run through
     * the translator is a miss, and a miss files a phrase — one per association,
     * permanently. `test/Smoke/BreadcrumbDataLabelsSmokeTest` asserts exactly this
     * against `trans_phrases` rather than against the markup, because the crumb reads
     * correctly either way and the cost lands in a table nobody looks at.
     *
     * @param array<string, mixed> $entity
     * @return list<array{label: string, href: string, translate?: bool}>
     */
    private function breadcrumbs(array $entity, string $locale, string $selfUrl): array
    {
        $kind = $entity['kind'] ?? null;
        [$label, $route] = match ($kind) {
            'sch-shrine'         => ['Shrines', 'shrines'],
            'sch-wayside-shrine' => ['Wayside shrines', 'wayside-shrines'],
            default              => ['Associations', 'associations'],
        };

        $trail = [['label' => $label, 'href' => $this->urls->path($route)]];

        //Only shrines hang under a region. The `movement` branch — every non-shrine
        //association — attaches straight to `Movement` with no intermediate node, and a
        //shrine with no `countryRegion` is filed under `World`, which laminas renders as
        //no extra crumb at all rather than as a "World" one.
        $region = $entity['countryRegion'] ?? null;
        if ('sch-shrine' === $kind && is_string($region) && '' !== $region) {
            $trail[] = [
                'label' => $region,
                'href'  => $this->urls->path('shrines', [], ['fragment' => $region]),
            ];
        }

        $trail[] = [
            'label'     => $this->localized($entity, 'nameByLocale', $locale),
            'href'      => $selfUrl,
            'translate' => false,
        ];

        return $trail;
    }

    /**
     * The schema.org projection as a plain array for `json_ld()`, or null.
     *
     * `getAssociationSchemaV1()` is annotated as always returning a schema object and
     * the .phtml still guards with `$schema instanceof BaseType` — so the annotation is
     * the thing to distrust, not the guard. Held as `mixed` here so the guard survives
     * PHPStan's belief in the docblock.
     *
     * @return array<string, mixed>|null
     */
    private function schemaArray(mixed $schema): ?array
    {
        return $schema instanceof BaseType ? $schema->toArray() : null;
    }

    /**
     * The translated kind labels, which `Schoenstatt\Service\AssociationKindsService`
     * has already run through the translator — hence no second translate() in the
     * template.
     *
     * @return array<string, string>
     */
    private function kindLabels(): array
    {
        /** @var AssociationKindsService $kinds */
        $kinds = $this->laminas->get(AssociationKindsService::class);

        //annotated `@return array`, and it is — the cast is for the value type, which the
        //template indexes by kind
        /** @var array<string, string> $labels */
        $labels = $kinds->getValueOptions();

        return $labels;
    }

    /**
     * The OpenStreetMap link beside the contact panel, or null when the association has
     * no geo point.
     *
     * @param array<string, mixed> $entity
     */
    private function mapUrl(array $entity): ?string
    {
        $point = $entity['geoPoint'] ?? null;
        if (null === $point) {
            return null;
        }

        return sprintf(
            'https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=14/%s/%s&layers=N',
            $point->latitude,
            $point->longitude,
            $point->latitude,
            $point->longitude
        );
    }

    /**
     * The static map tile, or null when there is no geo point.
     *
     * This is the .phtml's inline Web Mercator tile calculation, moved verbatim — the
     * standard slippy-map formula from the OpenStreetMap wiki the original links to.
     * `M_PI` replaces its hardcoded `3.14159`, which changes the *sixth* decimal of the
     * y coordinate and cannot change a floored tile index at zoom 13.
     *
     * @param array<string, mixed> $entity
     */
    private function mapTileUrl(array $entity): ?string
    {
        $point = $entity['geoPoint'] ?? null;
        if (null === $point) {
            return null;
        }

        $latRad = (float) $point->latitude * (M_PI / 180.0);
        $n      = 2.0 ** self::MAP_ZOOM;
        $xTile  = floor($n * (((float) $point->longitude + 180.0) / 360.0));
        $yTile  = floor($n * (1.0 - (log(tan($latRad) + 1.0 / cos($latRad)) / M_PI)) / 2.0);

        return sprintf('https://a.tile.openstreetmap.org/%d/%d/%d.png', self::MAP_ZOOM, $xTile, $yTile);
    }

    /**
     * The "✓ Up-to-date" tooltip text.
     *
     * `'jeff'` is hardcoded in the original — it interpolates a literal where a user
     * name belongs — and is carried over rather than fixed, because inventing the right
     * name is a content decision. Recorded in docs/BACKLOG.md.
     *
     * @param array<string, mixed> $entity
     */
    private function phonesUpdatedTooltip(array $entity): string
    {
        $updated = $entity['phonesUpdatedOn'] ?? null;
        if (! $updated instanceof DateTimeInterface) {
            return '';
        }

        return sprintf(
            $this->translate('Updated by %s %s'),
            'jeff',
            Carbon::instance($updated)->diffForHumans()
        );
    }

    /**
     * "Add assignment" prefills the role: the association's main role if it has one,
     * otherwise its first. An association with no roles at all gets an unprefilled link.
     *
     * @param array<string, mixed> $entity
     */
    private function addAssignmentUrl(array $entity): string
    {
        $roleId = null;
        if (isset($entity['mainRole']['roleId'])) {
            $roleId = $entity['mainRole']['roleId'];
        } elseif (! empty($entity['roles']) && is_array($entity['roles'])) {
            $first  = current($entity['roles']);
            $roleId = is_array($first) ? ($first['roleId'] ?? null) : null;
        }

        return $this->urls->path(
            'assignments/create',
            [],
            null === $roleId ? [] : ['query' => ['roleId' => $roleId]]
        );
    }

    /**
     * "Add associated association" / "Add apostolate/branch", prefilled with the parent,
     * its country and its time zone — and, for a shrine, with the kind the new row
     * almost always has.
     *
     * @param array<string, mixed> $entity
     */
    private function addChildUrl(array $entity): string
    {
        $query = ['parentId' => $entity['associationId'] ?? null];
        if (isset($entity['country'])) {
            $query['country'] = $entity['country'];
        }
        if (isset($entity['timeZoneId'])) {
            $query['timeZoneId'] = $entity['timeZoneId'];
        }
        if ('sch-shrine' === ($entity['kind'] ?? null)) {
            $query['kind'] = 'sch-diocesan-apostolate';
        }

        return $this->urls->path('associations/create', [], ['query' => $query]);
    }

    /**
     * The per-role "add an assignment to this role" links in the admin panel, keyed by
     * role id so the template can look each one up without assembling a URL.
     *
     * @param array<string, mixed> $entity
     * @return array<int|string, string>
     */
    private function roleAssignmentUrls(array $entity): array
    {
        $roles = $entity['roles'] ?? null;
        if (! is_array($roles)) {
            return [];
        }

        $urls = [];
        foreach ($roles as $role) {
            $roleId = is_array($role) ? ($role['roleId'] ?? null) : null;
            if (is_int($roleId) || is_string($roleId)) {
                $urls[$roleId] = $this->urls->path('assignments/create', [], [
                    'query' => ['roleId' => $roleId],
                ]);
            }
        }

        return $urls;
    }

    /** @param array<string, mixed> $entity */
    private function localized(array $entity, string $key, string $locale): string
    {
        return $this->localizedOrNull($entity, $key, $locale) ?? '';
    }

    /** @param array<string, mixed> $entity */
    private function localizedOrNull(array $entity, string $key, string $locale): ?string
    {
        $byLocale = $entity[$key] ?? null;
        if (! is_array($byLocale) || ! isset($byLocale[$locale]) || ! is_string($byLocale[$locale])) {
            return null;
        }

        return $byLocale[$locale];
    }

    /**
     * Whether anyone is signed in — `zfcUserAuthentication()->hasIdentity()` on the
     * laminas side, and the *only* thing the anonymous-visitor rule tests. Not "may they
     * see this association": any signed-in account, of any role, sees every kind.
     */
    private function hasIdentity(): bool
    {
        /** @var AuthenticationService $auth */
        $auth = $this->laminas->get('JUser\AuthService');

        return $auth->hasIdentity();
    }

    private function translate(string $message): string
    {
        return $this->laminas->get('MvcTranslator')->translate($message, 'Schoenstatt');
    }

    private function flash(string $message): void
    {
        (new FlashMessenger())->setNamespace(FlashMessenger::NAMESPACE_ERROR)->addMessage($message);
    }

    private function notFound(): Response
    {
        return new Response(
            'Association not found.',
            Response::HTTP_NOT_FOUND,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}
