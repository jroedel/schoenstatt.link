<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Markup;

use function dirname;
use function file_get_contents;
use function is_file;

/**
 * GET /literature/150-preguntas-sobre-schoenstatt — a book, as one long HTML page.
 *
 * The whole of `books/publications/one-fifty-preguntas.phtml` is
 * `readfile("data/publications/one-fifty-preguntas.html")`, and the action behind it
 * is `return new ViewModel()`. So this is the only ported route whose content comes
 * from a file on disk rather than from the database or a template, and the port is a
 * `file_get_contents` handed to the layout.
 *
 * Two things follow from that and are deliberate:
 *
 * - **The file is trusted markup.** It is a checked-in document, not user input, and it
 *   is emitted unescaped exactly as `readfile()` emits it. It arrives at the template as
 *   a Twig\Markup so no `|raw` is needed at the call site.
 * - **A missing file raises.** `readfile()` on a missing path emits a warning and
 *   renders an empty page — a 200 with the chrome and no book in it. That is the worst
 *   of the available behaviours for a page nobody looks at often, so this raises
 *   instead and lets SionModel\Error\FatalErrorHandler report it.
 *
 * The path is resolved from this file rather than from the working directory. The
 * original's relative `data/publications/…` works only because the front controller
 * chdir()s to the project root, which is true today and is not a property worth
 * depending on twice.
 */
final class OneFiftyPreguntasController
{
    private const DOCUMENT = '/data/publications/one-fifty-preguntas.html';

    public function __construct(
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $path = dirname(__DIR__, 2) . self::DOCUMENT;
        if (! is_file($path)) {
            throw new RuntimeException('The 150-preguntas document is missing: ' . $path);
        }

        return new Response($this->twig->render('books/one-fifty-preguntas.html.twig', [
            //one-fifty-preguntas.phtml calls no headTitle(), so there is no title prefix
            //— the <h1> inside the document is the only heading. The trail comes from the
            //publication branch Application\Module::onBootstrap() builds under Literature.
            'page_title'  => '',
            'breadcrumbs' => [
                ['label' => 'Literature', 'href' => $this->urls->path('publications')],
                [
                    //'translate' => false because this label is a *work's title*, and the
                    //layout translates a crumb unless told not to. A title cannot be
                    //translated — rendering this one in Portuguese would name an edition
                    //that does not exist — and the attempt is not free: a translator miss
                    //is what files a phrase, so this crumb filed itself into `Books` and
                    //`default` on 2026-08-10, the day phrase discovery started working on
                    //Symfony-served routes. Same defect as the laminas breadcrumb's
                    //publication titles, one crumb wide; see
                    //docs/api-v3.md §12 and Application\Module's
                    //LABEL_IS_DATA, which covers this page's *laminas* navigation entry.
                    'label'     => '150 preguntas sobre Schoenstatt',
                    'href'      => $this->urls->path('publications/one-fifty-preguntas'),
                    'translate' => false,
                ],
            ],
            'document'    => new Markup((string) file_get_contents($path), 'UTF-8'),
        ]));
    }
}
