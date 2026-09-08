<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\ServiceBridge;
use Books\Model\PublicationsTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The two whole-corpus listings for moderators: `publications/export` and
 * `publications/prime-authors`. Ported 2026-09-08 (batch 16).
 *
 * ## `export` is a page, not a spreadsheet
 *
 * docs/strangler.md described this route as "returns a spreadsheet, not HTML" until this
 * port, and that was inferred from the name. `exportAction()` is `parent::indexAction()`
 * — every publication through `getObjects()` — rendered by `export.phtml`, which is the
 * `publication-list` partial with four columns: id, authors, title, edition. So it is the
 * whole corpus as one HTML table, 10,166 rows, and presumably the thing a moderator
 * copies into a spreadsheet by hand. The template is the shared `_publication-list`
 * partial, whose per-row `is_allowed(resourceId, 'show')` is the only thing keeping the
 * institute corpus out of a moderator's view — the *query* is not filtered by ACL.
 *
 * ## `prime-authors` is a report
 *
 * `PublicationsTable::getAuthors()` groups every publication under each of its
 * `authorsText` entries and the template prints one row per author with the formatted
 * publications after it. Read-only, which docs/BACKLOG.md checked on 2026-08-17 when the
 * maintenance sweeps around it were retired. Neither page sets a title, so both pass
 * `''` — the layout's "no prefix", which is what a `.phtml` that never called
 * `headTitle()` gets.
 *
 * ## Memory
 *
 * Both build `query-objects-publication` — 10,166 rows × 80 fields — which
 * App\Controller\LiteratureController measured at 192 MiB on the first call and 210 MiB
 * peak through the bridge, against production's 512M limit. Same order here; the export
 * renders every one of those rows where the language index renders a fifth of them.
 */
final class PublicationReportsController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig
    ) {
    }

    /** GET /literature/export — every publication, four columns. */
    public function export(Request $request): Response
    {
        /** @var array<mixed> $objects */
        $objects = $this->table()->getObjects('publication');

        return new Response($this->twig->render('books/literature-export.html.twig', [
            'page_title' => '',
            'objects'    => $objects,
        ]));
    }

    /** GET /literature/prime-authors — each author with their publications. */
    public function primeAuthors(Request $request): Response
    {
        /** @var array<string, array<int|string, array<string, mixed>>> $authors */
        $authors = $this->table()->getAuthors();

        return new Response($this->twig->render('books/literature-prime-authors.html.twig', [
            'page_title' => '',
            'authors'    => $authors,
        ]));
    }

    private function table(): PublicationsTable
    {
        /** @var PublicationsTable $table */
        $table = $this->laminas->get(PublicationsTable::class);

        return $table;
    }
}
