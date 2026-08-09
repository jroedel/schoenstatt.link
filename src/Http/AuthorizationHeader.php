<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

use function apache_request_headers;
use function function_exists;
use function is_string;
use function strtolower;

/**
 * The `Authorization` header, which is not simply there for the asking.
 *
 * Apache does not put `Authorization` into the CGI/SAPI environment. It is withheld
 * deliberately — the header carries a credential and the environment is visible to
 * everything in the process — so `$_SERVER['HTTP_AUTHORIZATION']` is absent even
 * though the client sent it, and `Request::createFromGlobals()`, which builds its
 * header bag from `$_SERVER`, therefore reports no Authorization header at all.
 *
 * Measured in the capsule against `apache2handler`: a request carrying
 * `Authorization: Bearer …` produced **no** `AUTH`-ish key in `$_SERVER`, while
 * `apache_request_headers()` returned the header intact. Every v3 request looked
 * exactly like an unauthenticated one, and the API answered 401 to a perfectly valid
 * token.
 *
 * ## Why this is fixed here and not in Apache
 *
 * `CGIPassAuth On`, or a `SetEnvIf Authorization` line, would also work — and would
 * not survive the trip to production. The vhost is `COPY`d into the image rather than
 * mounted, `public/.htaccess` is untracked and machine-specific, and deployment is a
 * phploy file sync with no server-configuration step. A fix that lives in the
 * application ships with the application; a fix that lives in Apache config ships
 * nowhere and fails first on the environment nobody can test.
 *
 * The Symfony request is still asked first, so nothing changes on a SAPI that does
 * populate `$_SERVER` (php-fpm with `fastcgi_pass_header`, the built-in server, and
 * the smoke suite's own synthetic requests). `apache_request_headers()` is only the
 * fallback, and only exists under mod_php.
 */
final class AuthorizationHeader
{
    public static function from(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (is_string($header) && '' !== $header) {
            return $header;
        }

        if (! function_exists('apache_request_headers')) {
            return null;
        }

        foreach (apache_request_headers() as $name => $value) {
            //Header names are case-insensitive and Apache preserves what the client
            //sent, so `authorization` and `Authorization` both have to match.
            if ('authorization' === strtolower((string) $name) && is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return null;
    }
}
