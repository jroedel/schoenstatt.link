<?php

declare(strict_types=1);

namespace App\JUser;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

/**
 * The "you need cookies to sign in" page, served **at whatever URL asked for it**.
 *
 * This is the Symfony half of `Application\View\GdprStrategy::onRoute()`, which swapped the
 * route match of `zfcuser/login` and `zfcuser/verify` for the explainer when the visitor had
 * not consented. That listener runs on `MvcEvent::EVENT_ROUTE` and so does not run at all
 * for a ported route; its other half, `onFinish()`, is already reproduced by
 * `App\Http\GdprCookieListener`.
 *
 * **A 200 at the requested URL, not a redirect**, and the distinction is the reason this
 * class exists rather than each controller redirecting to `/sign-in-no-cookies`. A redirect
 * would rewrite the address bar of someone who had just clicked an emailed link, and the
 * whole point of the gate is that the link survives: `redeemToken()` spends a token only on
 * a successful redemption, so consenting and reloading works.
 *
 * **This is the only thing that renders the page.** The `sign-in-no-cookies` laminas route
 * exists and is not ported, and it has never been reachable: it has no guard entry, so
 * BjyAuthorize's default deny applies. The route-match swap worked only because it ran at
 * priority -5000, after the guard had already approved `zfcuser/login`. See the note on the
 * withdrawn route declaration in config/symfony/routes.php.
 */
final class CookieExplainer
{
    private const TEMPLATE = 'content/sign-in-no-cookies.html.twig';

    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * `page_title` is **empty rather than absent**: the .phtml sets no `headTitle()`, and
     * `layout.html.twig` reads `page_title` unguarded under `strict_variables`, so omitting
     * it is a fatal that arrives as a 200 with an empty body.
     *
     * @throws TwigError
     */
    public function response(): Response
    {
        return new Response($this->twig->render(self::TEMPLATE, ['page_title' => '']));
    }
}
