<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Model\MusicTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * GET /music — every composition, grouped by the language it is sung in.
 *
 * `Books\Controller\CompositionsController::indexAction()` calls
 * `parent::indexAction()` — `getObjects('composition')` — and then adds the language
 * name table. Both halves are here; there is nothing else to it.
 *
 * The grouping is the template's, not the controller's, and it is done the way the
 * original does it: walk the rows in order and emit a heading whenever `inLanguage`
 * changes. That is a *run*-based grouping, so it depends on the rows arriving sorted
 * by language — which they do, from the entity spec — and it is reproduced rather than
 * replaced by a real group-by, because a real group-by would silently repair a page
 * that today shows a second "Spanish" heading if the sort ever changed.
 *
 * The first run has an empty `inLanguage` and so gets no heading at all: the original
 * seeds `$currentLanguage = ''` and only emits a heading when the value *differs*.
 * Reproduced, which is why the page opens with an unheaded link.
 */
final class MusicController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var MusicTable $table */
        $table = $this->laminas->get(MusicTable::class);

        return new Response($this->twig->render('books/music.html.twig', [
            //index.phtml calls no headTitle(). `music` *is* a top-level navigation item,
            //so it does get a one-crumb trail — measured, and the reason the two are not
            //the same question.
            'page_title'     => '',
            'breadcrumbs'    => [['label' => 'Music', 'href' => $this->urls->path('music')]],
            'objects'        => $table->getObjects('composition'),
            'language_names' => $table->getLanguageNames(),
        ]));
    }
}
