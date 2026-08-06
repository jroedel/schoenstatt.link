<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * How a route says "no" — declared per route, never sniffed from the request.
 *
 * `Accept` is the obvious thing to branch on and it is the wrong thing. The two
 * maintenance endpoints exist to be called by deploy hooks and monitoring, and
 * neither `tools/smoke-prod.sh` nor phploy's hooks send an `Accept` header at all;
 * curl's default is `*&#47;*`. A guard that guessed would answer them with an HTML
 * login page and a 302, which a hook reads as success — precisely the failure
 * App\Http\MaintenanceKey was written to avoid. So the shape of a refusal is a
 * property of the endpoint, fixed at the point the route is declared, and a reader
 * of config/symfony/routes.php can see it there.
 */
enum DenialStyle
{
    /**
     * The browser contract JUser\View\RedirectionStrategy implements: 302 to the
     * sign-in page for an anonymous visitor, 403 with the error page for a
     * signed-in one who is not allowed.
     */
    case Html;

    /**
     * A machine contract: 401 or 403 with a JSON body and no redirect, so a caller
     * that follows redirects cannot mistake a refusal for an answer.
     */
    case Json;
}
