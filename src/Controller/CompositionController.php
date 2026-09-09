<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Sion\EntityShow;
use App\View\PreferredUrls;
use App\Sion\SiteWideIdentifier;
use Books\Model\MusicTable;
use ChordPro\HtmlFormatter;
use ChordPro\Parser;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Schoenstatt\Validator\SchoenstattLinkIdentifier as IdentifierValidator;
use Spatie\SchemaOrg\BaseType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twig\Environment;

use function is_string;

/**
 * GET /{sw_id}[/{slug}] for a composition — an individual song.
 *
 * docs/laminas-exit.md listed this route as blocked: "`composition` has a comment
 * predicate, so `SionController::showAction()` builds a `CommentForm` and the
 * template renders it". That was accurate, and App\Controller\CommentCreateController
 * plus App\Sion\EntityShow are what unblock it.
 *
 * Guarded `['guest', 'sch_basic', 'sch_user', 'user']` — a `guest` entry means every
 * anonymous visitor, so this is a public page. Unlike the association show page it
 * carries no second, controller-level rule about who may see which row: every
 * composition is public.
 *
 * ## The ChordPro rendering happens here, not in the template
 *
 * `compositions/show.phtml` parses the stored ChordPro source and formats it to HTML
 * inline, and appends `chordpro.css` through `headLink()` while doing it. Neither
 * belongs in Twig — the parse is work, and the conditional stylesheet is a decision —
 * so both move into the controller: the formatted HTML arrives as a variable and
 * `chordpro` says whether to emit the `<link>`.
 *
 * The parse is wrapped, which the .phtml does not do. `nicolaswurtz/chordpro-php` is
 * pinned at `dev-master` through a personal fork, its input is a free-text column a
 * moderator types, and a parse failure on the laminas side is a 500 on a public page.
 * Here it costs the chord chart and keeps the rest of the song.
 */
final class CompositionController
{
    private const ENTITY = 'composition';

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly EntityShow $show,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
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

        $id = SiteWideIdentifier::toId(IdentifierValidator::ENTITY_COMPOSITION, $swId);
        if (null === $id) {
            return $this->notFound();
        }

        //the request path is `url(null, [], [], true)` — the page the visitor is on,
        //which is where a posted comment returns them
        $selfUrl = $request->getPathInfo();
        $data    = $this->show->load(self::ENTITY, $id, null, $selfUrl);
        if (null === $data) {
            //`index_route` for `composition` is `music`, so that is where
            //SionController::showAction() sends a visitor whose song is missing
            $this->flash($this->show->deniedMessage(self::ENTITY, $id));

            return new RedirectResponse($this->urls->path('music'), Response::HTTP_FOUND);
        }

        /** @var MusicTable $table */
        $table  = $this->laminas->get(MusicTable::class);
        $schema = $table->getCompositionSchemaV1($data->entity);

        $chordPro = $data->entity['chordProSpec'] ?? null;

        return new Response($this->twig->render('books/composition.html.twig', [
            /*
             * The five URLs this record should be indexed under, for the layout's canonical
             * and hreflang links. A composition has a single `Slug` column, so the five differ
             * only in the locale prefix — but this is still needed, because a request that
             * omits the slug would otherwise declare the slugless URL canonical while the
             * sitemap and every menu link advertise the slugged one. See App\View\PreferredUrls.
             */
            'locale_paths'         => $this->preferredUrls->forRecord(
                self::ENTITY,
                ['sw_id' => $swId, 'slug' => $data->entity['slug'] ?? null]
            ),
            //showAction() sets no headTitle at all for a composition — the name is an
            //<h2> in the body and the <title> is the site name alone. Measured against
            //the laminas rendering of /en/SL500001C rather than assumed, because every
            //other show page in this batch does set one.
            'page_title'   => '',
            //[Music, <name>] — the trail laminas derives from the Navigation service,
            //measured on /it/SL500001C/obrigado. `translate: false` on the leaf is the
            //whole point of test/Smoke/BreadcrumbDataLabelsSmokeTest: a composition name
            //run through the translator is a miss, and a miss files a phrase per row.
            'breadcrumbs'  => [
                ['label' => 'Music', 'href' => $this->urls->path('music')],
                ['label' => (string) ($data->entity['name'] ?? ''), 'href' => $selfUrl, 'translate' => false],
            ],
            'entity'       => $data->entity,
            'entity_id'    => $id,
            'sw_id'        => $swId,
            'chordpro'     => is_string($chordPro) && '' !== $chordPro,
            'chordpro_html' => $this->chordProHtml($chordPro),
            'changes'      => $data->changes,
            'comments'     => $data->comments,
            'comment_form' => $data->commentForm,
            'comment_action' => $this->urls->path('comments/create', [
                'entity'    => self::ENTITY,
                'entity_id' => $id,
            ]),
            'visits'       => $data->visits,
            'edit_url'     => $this->urls->path('composition-edit', ['sw_id' => $swId]),
            'delete_url'   => $this->urls->path('composition-delete', ['sw_id' => $swId]),
            'schema'       => $schema instanceof BaseType ? $schema->toArray() : null,
        ]));
    }

    /**
     * The chord chart, or the empty string when there is none or the parser refuses
     * the source — see the class docblock for why a failure is swallowed here and is
     * not on the laminas side.
     */
    private function chordProHtml(mixed $source): string
    {
        if (! is_string($source) || '' === $source) {
            return '';
        }

        try {
            //the two options compositions/show.phtml passes, spelled out rather than
            //defaulted: the formatter's own defaults have changed under this fork before
            return (new HtmlFormatter())->format(
                (new Parser())->parse($source),
                ['french' => false, 'no_chords' => false]
            );
        } catch (Throwable) {
            return '';
        }
    }

    private function flash(string $message): void
    {
        (new FlashMessenger())->setNamespace(FlashMessenger::NAMESPACE_ERROR)->addMessage($message);
    }

    private function notFound(): Response
    {
        return new Response(
            'Composition not found.',
            Response::HTTP_NOT_FOUND,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}
