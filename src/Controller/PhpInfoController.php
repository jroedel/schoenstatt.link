<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function ob_get_clean;
use function ob_start;
use function phpinfo;

/**
 * GET /sm/phpinfo — the PHP configuration of the process serving the site.
 *
 * The most tightly guarded route on the site: `route/sion-model/phpinfo` admits
 * `sch_administrator` and nothing else, and that role has **no descendants**, so the
 * effective role set is exactly one of 43. That is the reason this port is worth
 * having beyond the page itself — `/admin` proved App\Authorization\RouteGuard admits
 * the right people, and one role with no inheritance is the sharpest available test
 * that it refuses everyone else.
 *
 * There is no page logic to port: SionModelController::phpInfoAction() returns `[]`
 * and phpinfo.phtml is `echo phpinfo();`.
 *
 * ## The nested document is reproduced, not fixed
 *
 * `phpinfo()` prints a *complete* HTML document — its own doctype, `<html>`, `<head>`
 * with a stylesheet and `<body>` — and the laminas layout wraps that in the site
 * chrome. Measured on the laminas rendering: two `<!DOCTYPE>`, two `<html>` and two
 * `<body>` in one response. That is invalid HTML and every browser renders it anyway,
 * which is presumably why it has never been noticed.
 *
 * It is reproduced rather than corrected because this is a port, and a port whose
 * output differs is a port whose difference has to be justified. `phpinfo(INFO_ALL)`
 * is what the original calls; passing INFO_MODULES-and-friends to get a fragment
 * instead would drop sections. If this page is ever cleaned up, the change belongs to
 * the page and not to the migration — and doing it here would have meant no baseline
 * to compare against.
 *
 * `ob_start()` rather than letting phpinfo() write to the output buffer directly:
 * a Symfony controller returns its body, and phpinfo() has no return-string mode.
 */
final class PhpInfoController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        //as SlmLocale would, and as the other ported HTML routes do. The maintenance
        //endpoints deliberately do *not* redirect, because a deploy hook calls them
        //unprefixed; this page is only ever reached from a browser.
        if (null === $request->attributes->get('_locale')) {
            return new RedirectResponse(
                $this->urls->path('sion-model/phpinfo'),
                Response::HTTP_FOUND
            );
        }

        ob_start();
        phpinfo();
        $info = (string) ob_get_clean();

        return new Response($this->twig->render('sion-model/phpinfo.html.twig', [
            //phpinfo.phtml calls no headTitle(), so laminas renders the site name
            //alone — measured. '' is how the layout is told to omit the separator.
            'page_title' => '',
            'php_info'   => $info,
        ]));
    }
}
