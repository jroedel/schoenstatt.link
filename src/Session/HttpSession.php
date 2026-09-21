<?php

declare(strict_types=1);

namespace App\Session;

use SionModel\Session\PhpSession;
use SionModel\Session\SessionBagInterface;
use SionModel\Session\SessionInterface;

use function array_key_exists;
use function headers_sent;
use function is_bool;
use function ini_set;
use function session_get_cookie_params;
use function session_id;
use function session_name;
use function session_regenerate_id;
use function session_start;
use function session_status;
use function str_replace;
use function strtolower;
use function setcookie;

use const PHP_SESSION_ACTIVE;

/**
 * The session for an HTTP request: cookie policy, starting, and a privilege change.
 *
 * Replaced `Laminas\Session\SessionManager` on 2026-09-21. The reading and writing is
 * {@see PhpSession} in SionModel, which is generic; what is here is the part that is the
 * *host's* to decide — how long the cookie lives, when the session starts, and what
 * happens to it when someone signs in or out.
 *
 * ## Nothing here throws, and that is a fix rather than a style
 *
 * `Laminas\Session\Config\SessionConfig` applied `session_config` with `ini_set()` and
 * threw when it could not: under CLI, once anything has been written to stdout, building
 * it failed with "'session.cache_expire' is not a valid sessions-related ini setting".
 * A PHPUnit run has always written its progress output by then, so two places in this
 * repository work around it — `test/Fuzz/FormRepository` registers a `StandardConfig`
 * instead, and `App\Authorization\Denial` was split out of the route guard so a refusal's
 * shape could be asserted without a session at all. Settings are applied here only when
 * they can be, and a console process simply gets a session it never uses.
 *
 * ## The values are passed through unchanged
 *
 * Including `cache_expire`, which `module/JUser/config/module.config.php` sets to 30 days
 * **in seconds** while PHP reads that setting in minutes. That is how it has been set for
 * as long as the file has existed, it reaches `Cache-Control` on a cached response, and
 * `tools/smoke-prod.sh` asserts that header on `/en/user/login` — so this reproduces it
 * rather than quietly correcting it. Correcting it is a separate change with its own
 * measurement.
 */
final class HttpSession implements SessionInterface
{
    /**
     * The `session_config` keys this applies, each an ini setting under `session.`.
     *
     * Matched with underscores removed, because `Laminas\Session\Config\StandardConfig`
     * camel-cased a key and called a setter, so it took `cookie_http_only` and
     * `cookie_httponly` alike — and `docker/local.docker.php` writes the first spelling
     * while PHP's ini name is the second. Matching the literal key lost `HttpOnly` from the
     * capsule's session cookie, which is how this was found.
     */
    private const SETTINGS = [
        'cache_expire',
        'cache_limiter',
        'cookie_domain',
        'cookie_httponly',
        'cookie_lifetime',
        'cookie_path',
        'cookie_samesite',
        'cookie_secure',
        'gc_divisor',
        'gc_maxlifetime',
        'gc_probability',
        'name',
        'use_cookies',
        'use_only_cookies',
    ];

    private readonly PhpSession $session;

    /** @param array<string, mixed> $config the merged `session_config` block */
    public function __construct(private readonly array $config = [])
    {
        $this->session = new PhpSession();
    }

    /**
     * A namespace, starting the session if it is not already running.
     *
     * The lazy start is not a convenience: `Laminas\Session\Container::__construct()`
     * ended with `$this->getManager()->start()`, so **touching any namespace started the
     * session**, and this application is built around that. `App\Http\GdprCookieListener`
     * exists because of it — the layout asks `isAllowed()`, BjyAuthorize asks JUser for the
     * identity, JUser reads the session, and a `Set-Cookie` lands in the SAPI header list
     * before any listener can object; the listener strips it for a visitor who has not
     * consented. A replacement that did not start here would leave a signed-in moderator
     * without the moderator chrome and a fresh visitor unable to hold a CSRF token.
     */
    public function bag(string $namespace): SessionBagInterface
    {
        $this->start();

        return $this->session->bag($namespace);
    }

    /**
     * Start the session if it is not already running.
     *
     * @return bool whether a session is active afterwards — false is a normal answer for
     *         a console process and for a request whose headers have gone out
     */
    public function start(): bool
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            return true;
        }
        if (headers_sent()) {
            return false;
        }

        $this->applySettings();

        if (! session_start()) {
            return false;
        }

        //What Laminas\Session\Storage\SessionArrayStorage stamped on init, and what the
        //hop expiry in PhpSession measures against. Without it a flash message would be
        //readable for ever, because every request would compare against its own time.
        $this->session->stampAccessTime();

        return true;
    }

    public function isStarted(): bool
    {
        return PHP_SESSION_ACTIVE === session_status();
    }

    /**
     * Issue a new session id, retiring the old one server-side.
     *
     * `$destroyOld` is what makes this a session-fixation defence rather than a gesture:
     * the id an attacker fixed beforehand has to stop working, not merely stop being sent.
     */
    public function regenerateId(bool $destroyOld = true): void
    {
        if (PHP_SESSION_ACTIVE !== session_status() || headers_sent()) {
            return;
        }

        session_regenerate_id($destroyOld);
    }

    /**
     * Stop this session outliving the browser session.
     *
     * `Laminas\Session\SessionManager::forgetMe()` — put the cookie's lifetime back to 0,
     * undoing a remember-me. PHP has no call for "re-send the current cookie with a
     * different lifetime", so the cookie is written again with the same id.
     */
    public function forgetMe(): void
    {
        if (headers_sent()) {
            return;
        }

        $params = session_get_cookie_params();
        $id     = session_id();
        if (false === $id || '' === $id) {
            return;
        }

        setcookie(session_name() ?: 'PHPSESSID', $id, [
            'expires'  => 0,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? '',
        ]);
    }

    /**
     * Apply `session_config` to php.ini, skipping anything PHP will not take.
     *
     * `@` rather than a thrown exception: a rejected setting is worth less than the
     * request. laminas made the opposite choice and it cost two test workarounds.
     */
    private function applySettings(): void
    {
        $supplied = [];
        foreach ($this->config as $key => $value) {
            $supplied[str_replace('_', '', strtolower((string) $key))] = $value;
        }

        foreach (self::SETTINGS as $setting) {
            $normalised = str_replace('_', '', $setting);
            if (! array_key_exists($normalised, $supplied)) {
                continue;
            }

            $value = $supplied[$normalised];
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            @ini_set('session.' . $setting, (string) $value);
        }
    }
}
