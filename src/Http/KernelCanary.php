<?php

declare(strict_types=1);

namespace App\Http;

use ArrayAccess;

use function getenv;
use function is_array;
use function is_object;
use function is_scalar;
use function method_exists;

/**
 * The cookie that puts one visitor on the front controller the rest of the site is
 * not on.
 *
 * `public/.htaccess` carries a site-wide default and two per-visitor overrides:
 *
 *     SetEnvIf Request_URI ".*"                 SYMFONY_KERNEL=1   (the flip; absent = laminas)
 *     SetEnvIf Cookie "sl_symfony_canary=1"     SYMFONY_KERNEL=1
 *     SetEnvIf Cookie "sl_symfony_canary=0"     SYMFONY_KERNEL=0
 *
 * So the cookie has **three** states, not two: absent means "whatever everyone else
 * gets", `1` forces App\Kernel and `0` forces Laminas\Mvc\Application. Both overrides
 * exist at once on purpose — before the flip the cookie is how the ported routes get
 * exercised against production's real data, real ICU and real translations without a
 * window in which all traffic is on them; after it, the same cookie is the escape
 * hatch back to laminas for one visitor without a deploy. See docs/strangler.md.
 *
 * **This class is the name's only definition in PHP**, shared by the laminas action
 * that toggles it (Application\Controller\IndexController::kernelSwitchAction) and by
 * the tests. The other definition is the Apache config above, which no PHP can read;
 * `test/Integration/KernelCanaryTest` compares the two so they cannot drift.
 *
 * A laminas controller reaching into `App\` inverts the usual direction, and is right
 * here for one reason: the toggle has to work under **both** front controllers. You
 * turn the canary on while laminas is serving you and off while Symfony is, so the
 * action must live where both can reach it — i.e. on the laminas side, which the
 * Symfony kernel bridges every unported path to. One definition, reachable from both,
 * beats two that can disagree.
 *
 * **Not a secret and not a security boundary.** Anyone may set either value. That is
 * safe because both front controllers consult the same ACL — App\Authorization\RouteGuard
 * asks about the same `route/<name>` resources BjyAuthorize\Guard\Route does, from the
 * same config — so an opted-in visitor reaches nothing they could not already reach.
 * The *toggle* is nevertheless restricted to administrators, because a menu item that
 * changes how the site renders has no business being offered to visitors.
 */
final class KernelCanary
{
    /** Must match the `SetEnvIf Cookie` directives in public/.htaccess, exactly. */
    public const COOKIE = 'sl_symfony_canary';

    /** Opt in to App\Kernel: the directive matches on the literal `sl_symfony_canary=1`. */
    public const FORCE_SYMFONY = '1';

    /**
     * Opt back out to Laminas\Mvc\Application, whatever the site default is.
     *
     * A no-op while the default is still laminas, and deliberately deployed anyway:
     * it means the global flip adds exactly one line to public/.htaccess rather than
     * flipping one and adding another, and the escape hatch is already proven to work
     * before it is the only one there is.
     */
    public const FORCE_LAMINAS = '0';

    /**
     * The environment variable public/index.php branches on, and the only way PHP can
     * learn which front controller is serving *this* request. Apache exports it to
     * both, so a bridged request sees it too — which is what lets one action offer the
     * other kernel without knowing anything about the site default.
     */
    public const ENV_VAR = 'SYMFONY_KERNEL';

    /**
     * The consent cookie both GDPR strategies check before they allow any Set-Cookie
     * to survive. Without it the toggle cannot work at all, which the action reports
     * rather than failing silently.
     *
     * @see \Application\View\GdprStrategy::onFinish()  header_remove('Set-Cookie')
     * @see \App\Http\GdprCookieListener                the same rule, Symfony side
     */
    public const CONSENT_COOKIE = GdprCookieListener::CONSENT_COOKIE;

    /**
     * Is this visitor overriding the site default at all, either way?
     *
     * The question the toggle asks, because its two branches are "clear the override"
     * and "add one" — not "on" and "off". Anything other than the two values Apache
     * matches on is not the canary: treating a stray value as an override would offer
     * to clear something that was never set.
     *
     * @param array<string, mixed>|object|null $cookies $_COOKIE, or a laminas/Symfony cookie bag
     */
    public static function isOverriding(mixed $cookies): bool
    {
        $value = self::read($cookies, self::COOKIE);

        return self::FORCE_SYMFONY === $value || self::FORCE_LAMINAS === $value;
    }

    /** @param array<string, mixed>|object|null $cookies */
    public static function forcesSymfony(mixed $cookies): bool
    {
        return self::FORCE_SYMFONY === self::read($cookies, self::COOKIE);
    }

    /** @param array<string, mixed>|object|null $cookies */
    public static function forcesLaminas(mixed $cookies): bool
    {
        return self::FORCE_LAMINAS === self::read($cookies, self::COOKIE);
    }

    /**
     * Which front controller is serving *this* request.
     *
     * Read from the environment rather than inferred from the cookie, and that is the
     * whole trick: the answer already accounts for the site default, the two overrides
     * and their order, none of which PHP can see. So the toggle can always offer "the
     * other one" and needs no change on the day the default flips.
     *
     * Deliberately the same expression as public/index.php's branch, down to the
     * `?: '0'` — a variable set to the empty string must read as laminas in both places
     * or the toggle would offer a kernel the visitor is already on.
     */
    public static function symfonyKernelIsLive(): bool
    {
        return self::FORCE_SYMFONY === (string) (getenv(self::ENV_VAR) ?: '0');
    }

    /** @param array<string, mixed>|object|null $cookies */
    public static function hasConsent(mixed $cookies): bool
    {
        return 'true' === self::read($cookies, self::CONSENT_COOKIE);
    }

    /**
     * One reader for the three shapes a cookie jar arrives in here: `$_COOKIE`, a
     * `Laminas\Stdlib\Parameters` (ArrayAccess) from the laminas request, and a
     * Symfony `InputBag`. Kept deliberately dumb — a missing cookie is simply absent.
     *
     * @param array<string, mixed>|object|null $cookies
     */
    private static function read(mixed $cookies, string $name): ?string
    {
        if (is_array($cookies)) {
            $value = $cookies[$name] ?? null;
        } elseif ($cookies instanceof ArrayAccess) {
            $value = $cookies->offsetExists($name) ? $cookies[$name] : null;
        } elseif (is_object($cookies) && method_exists($cookies, 'get')) {
            /** @var mixed $value */
            $value = $cookies->get($name);
        } else {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
