<?php

declare(strict_types=1);

namespace App\Http;

use ArrayAccess;

use function is_array;
use function is_object;
use function is_scalar;
use function method_exists;

/**
 * The cookie that puts one visitor on the Symfony kernel.
 *
 * Production serves laminas-mvc to everyone; `public/.htaccess` carries
 *
 *     SetEnvIf Cookie "sl_symfony_canary=1" SYMFONY_KERNEL=1
 *
 * so a request carrying this cookie is served by App\Kernel instead. That is how the
 * ported routes get exercised against production's real data, real ICU and real
 * translations without a window in which all traffic is on them — see
 * docs/strangler.md.
 *
 * **This class is the name's only definition in PHP**, shared by the laminas action
 * that toggles it (Application\Controller\IndexController::kernelSwitchAction) and by
 * the tests. The other definition is the Apache directive above, which no PHP can
 * read; `test/Integration/KernelCanaryTest` compares the two so they cannot drift.
 *
 * A laminas controller reaching into `App\` inverts the usual direction, and is right
 * here for one reason: the toggle has to work under **both** front controllers. You
 * turn the canary on while laminas is serving you and off while Symfony is, so the
 * action must live where both can reach it — i.e. on the laminas side, which the
 * Symfony kernel bridges every unported path to. One definition, reachable from both,
 * beats two that can disagree.
 *
 * **Not a secret and not a security boundary.** Anyone may set it. That is safe
 * because both front controllers consult the same ACL — App\Authorization\RouteGuard
 * asks about the same `route/<name>` resources BjyAuthorize\Guard\Route does, from the
 * same config — so an opted-in visitor reaches nothing they could not already reach.
 * The *toggle* is nevertheless restricted to administrators, because a menu item that
 * changes how the site renders has no business being offered to visitors.
 */
final class KernelCanary
{
    /** Must match the `SetEnvIf Cookie` directive in public/.htaccess, exactly. */
    public const COOKIE = 'sl_symfony_canary';

    /** Likewise: the directive matches on the literal `sl_symfony_canary=1`. */
    public const VALUE = '1';

    /**
     * The consent cookie both GDPR strategies check before they allow any Set-Cookie
     * to survive. Without it the toggle cannot work at all, which the action reports
     * rather than failing silently.
     *
     * @see \Application\View\GdprStrategy::onFinish()  header_remove('Set-Cookie')
     * @see \App\Http\GdprCookieListener                the same rule, Symfony side
     */
    public const CONSENT_COOKIE = GdprCookieListener::CONSENT_COOKIE;

    /** @param array<string, mixed>|object|null $cookies $_COOKIE, or a laminas/Symfony cookie bag */
    public static function isActive(mixed $cookies): bool
    {
        return self::VALUE === self::read($cookies, self::COOKIE);
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
