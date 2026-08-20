<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Sion\EntityShow;
use App\Sion\SiteWideIdentifier;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Schoenstatt\Validator\SchoenstattLinkIdentifier as IdentifierValidator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function is_array;
use function is_string;

/**
 * GET /{sw_id}[/{slug}] for a text — a document from the Kentenich corpus.
 *
 * **The batch's restricted show page.** `route/text` is guarded `texts_user`, so an
 * anonymous visitor gets the sign-in redirect and a signed-in visitor without the role
 * gets 403 — the same three outcomes `/en/admin` established, on a page that also has a
 * body worth comparing. Every other show page here is public, which is why this one is
 * in the batch: a guarded page proves App\Authorization\RouteGuard runs on a route whose
 * controller would otherwise have rendered.
 *
 * ## The simplest of the four, and the only one with no schema.org block
 *
 * `Books\Controller\TextsController` does not override `showAction()` at all, so this
 * page is `SionController::showAction()` and nothing else — no decoration to reproduce.
 * `texts/show.phtml` guards its JSON-LD with `isset($this->schema)` and nothing ever
 * sets it, so the block never renders. Confirmed against the captured baseline rather
 * than reasoned about: `signed-in-en-SL400003T` contains **zero** `application/ld+json`
 * blocks where the composition and association pages each contain one.
 *
 * ## The title is data, and is not translated
 *
 * The .phtml calls `headTitle()->setTranslatorEnabled(false)` before setting the title,
 * with six lines of comment saying why: a corpus title is a filename
 * (`1965-0613PRED.md`) or a bibliographic citation, 219 of them had accumulated as
 * permanent rows in the phrase table by 2026-05-30, and "translating" a filename changes
 * a lookup string. `page_title_translate: false` is the layout's equivalent, and losing
 * it would quietly refill that table.
 */
final class TextController
{
    private const ENTITY = 'text';

    public function __construct(
        private readonly EntityShow $show,
        private readonly \Twig\Environment $twig,
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

        $id = SiteWideIdentifier::toId(IdentifierValidator::ENTITY_TEXT, $swId);
        if (null === $id) {
            return $this->notFound();
        }

        //the request path is `url(null, [], [], true)` — the page the visitor is on,
        //which is where a posted comment returns them
        $selfUrl = $request->getPathInfo();
        $data    = $this->show->load(self::ENTITY, $id, null, $selfUrl);
        if (null === $data) {
            //`text` declares no index_route, so showAction() falls back to
            //getDefaultRedirectRoute() — `welcome`, from sionmodel.global.php
            $this->flash($this->show->deniedMessage(self::ENTITY, $id));

            return new RedirectResponse($this->urls->path('welcome'), Response::HTTP_FOUND);
        }

        $tags = $data->entity['tags'] ?? [];

        return new Response($this->twig->render('books/text.html.twig', [
            'page_title'           => (string) ($data->entity['title'] ?? ''),
            'page_title_translate' => false,
            //[Texts, <title>] — the corpus index and this document. Same
            //`translate: false` rule as the other three, and for the sharper reason
            //recorded in this class's docblock: 219 corpus titles had already accumulated
            //as permanent phrase rows by 2026-05-30.
            'breadcrumbs'          => [
                ['label' => 'Texts', 'href' => $this->urls->path('texts')],
                [
                    'label'     => (string) ($data->entity['title'] ?? ''),
                    'href'      => $selfUrl,
                    'translate' => false,
                ],
            ],
            'entity'               => $data->entity,
            'tags'                 => is_array($tags) ? $tags : [],
            'comments'             => $data->comments,
            'comment_form'         => $data->commentForm,
            'comment_action'       => $this->urls->path('comments/create', [
                'entity'    => self::ENTITY,
                'entity_id' => $id,
            ]),
            'visits'               => $data->visits,
        ]));
    }

    private function flash(string $message): void
    {
        (new FlashMessenger())->setNamespace(FlashMessenger::NAMESPACE_ERROR)->addMessage($message);
    }

    private function notFound(): Response
    {
        return new Response(
            'Text not found.',
            Response::HTTP_NOT_FOUND,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}
