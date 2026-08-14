<?php

namespace RestApi;

/**
 * What is left of the REST API module after /api/v1 and /api/v2 were retired.
 *
 * It used to carry ApiController, the base class every v1 controller extended —
 * absorbed from multidots/zf3-rest-api after that repo was deleted from GitHub. All
 * of its subclasses went away with the v1 routes, so the base class went with them,
 * and JWT signing now lives in JUser\Service\ApiTokenService where the v3 API reads
 * it.
 *
 * The module stays for one reason, and it is not vestigial: the api-route-not-found
 * catch-all in config/module.config.php is what makes an unmatched /api/... path
 * answer JSON instead of an HTML error page. Every retired v1 and v2 URL now lands
 * there and is answered 410 Gone, with a successor-version Link to /api/v3; anything
 * else keeps the plain 404. Removing this module would send both to the ordinary 404
 * page — see the comment on that route for the fatal-under-200 history behind it.
 */
class Module
{
    public const VERSION = '3.0.3-dev';

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
