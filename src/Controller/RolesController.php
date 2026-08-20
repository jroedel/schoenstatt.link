<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\ServiceBridge;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * GET /roles — every role in every association, with its flags.
 *
 * Guarded `route/roles`: `sch_moderator` and its descendants, 3 effective roles of 43.
 * `Schoenstatt\Controller\RolesController` is an empty subclass of SionController, so
 * the whole action is `getObjects('role')`.
 *
 * This is the page that exercises App\Laminas\EntityFormatter's **role branch** against
 * real data — 1,300-odd rows, each formatted twice: once as its association (a Twig
 * macro) and once as itself (the PHP formatter, with its own `editPencil`/`showLabel`
 * option names and its "Main role"/"Main contact"/"Inactive" labels). Everything else
 * that renders a role does so a few rows at a time inside the changes table; this does
 * it in bulk, which is what makes it a worthwhile check rather than just another index.
 */
final class RolesController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var SchoenstattTable $table */
        $table = $this->laminas->get(SchoenstattTable::class);

        return new Response($this->twig->render('schoenstatt/roles.html.twig', [
            //no headTitle(), not in the `navigation` config
            'page_title' => '',
            'objects'    => $table->getObjects('role'),
        ]));
    }
}
