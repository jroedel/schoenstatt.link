<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Http\GdprCookieListener;
use App\Http\KernelCanary;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;

use function file_get_contents;
use function preg_match;
use function preg_quote;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The canary cookie is named the same thing in PHP and in Apache.
 *
 * There are two definitions of it and only one of them is code:
 *
 *     public/.htaccess   SetEnvIf Cookie "sl_symfony_canary=1" SYMFONY_KERNEL=1
 *     App\Http\KernelCanary::COOKIE / ::VALUE
 *
 * Nothing in PHP reads the Apache directive, so a rename on either side is invisible:
 * the toggle sets a cookie, the admin is redirected, a success message appears, and the
 * front controller never changes. That failure has no symptom at all, which is why this
 * test reads the .htaccess as text and compares.
 *
 * The rest of the file pins the semantics the toggle branches on — `isActive` deciding
 * which way to switch, and `hasConsent` deciding whether switching is possible.
 */
class KernelCanaryTest extends TestCase
{
    private const HTACCESS = __DIR__ . '/../../public/.htaccess';

    /**
     * The directive exists and matches on exactly the cookie PHP writes.
     *
     * Matched loosely on whitespace and quoting, strictly on the name and value: the
     * point is to catch a rename, not to freeze the formatting of an Apache config.
     */
    public function testTheApacheDirectiveMatchesTheCookiePhpWrites(): void
    {
        $htaccess = (string) file_get_contents(self::HTACCESS);

        $pattern = '/SetEnvIf\s+Cookie\s+"'
            . preg_quote(KernelCanary::COOKIE, '/') . '='
            . preg_quote(KernelCanary::VALUE, '/')
            . '"\s+SYMFONY_KERNEL=1/';

        self::assertSame(
            1,
            preg_match($pattern, $htaccess),
            'public/.htaccess has no SetEnvIf matching ' . KernelCanary::COOKIE . '='
            . KernelCanary::VALUE . '. The toggle would set a cookie that switches nothing, '
            . 'with a success message and no symptom.'
        );
    }

    /**
     * And nothing else grants the kernel. A second directive — a `SetEnv
     * SYMFONY_KERNEL`, say — would override the canary entirely: mod_env runs *after*
     * mod_setenvif, so it wins regardless of the order the two appear in. That is not a
     * hypothetical; it is the mistake made while building this.
     */
    public function testNothingElseSetsTheKernelVariable(): void
    {
        $htaccess = (string) file_get_contents(self::HTACCESS);

        self::assertSame(
            0,
            preg_match('/^\s*SetEnv\s+"?SYMFONY_KERNEL/mi', $htaccess),
            'public/.htaccess contains a `SetEnv SYMFONY_KERNEL`, which silently disables the '
            . 'cookie gate — mod_env runs after mod_setenvif and wins whatever the file order'
        );
    }

    /** The toggle reads its own state from the request to decide which way to switch. */
    public function testIsActiveRecognisesOnlyTheExactValue(): void
    {
        self::assertTrue(KernelCanary::isActive([KernelCanary::COOKIE => KernelCanary::VALUE]));

        //Apache matches the literal `sl_symfony_canary=1`; anything else is not the canary,
        //and treating it as such would make the toggle offer to switch something off that
        //was never on
        self::assertFalse(KernelCanary::isActive([KernelCanary::COOKIE => '0']));
        self::assertFalse(KernelCanary::isActive([KernelCanary::COOKIE => 'true']));
        self::assertFalse(KernelCanary::isActive([]));
        self::assertFalse(KernelCanary::isActive(null));
    }

    /**
     * Consent gates the whole feature: both GDPR strategies call
     * header_remove('Set-Cookie') without it, so the toggle cannot work and says so
     * instead of appearing to succeed.
     */
    public function testHasConsentMatchesWhatTheGdprStrategiesLookFor(): void
    {
        self::assertSame(
            GdprCookieListener::CONSENT_COOKIE,
            KernelCanary::CONSENT_COOKIE,
            'the toggle is checking a different consent cookie from the listener that strips '
            . 'Set-Cookie, so it would report success on a request whose cookie is about to vanish'
        );

        self::assertTrue(KernelCanary::hasConsent([KernelCanary::CONSENT_COOKIE => 'true']));
        self::assertFalse(KernelCanary::hasConsent([KernelCanary::CONSENT_COOKIE => 'false']));
        self::assertFalse(KernelCanary::hasConsent([]));
    }

    /** The three cookie-jar shapes it is handed: $_COOKIE, a Symfony InputBag, and null. */
    public function testItReadsEveryCookieJarShapeItIsGiven(): void
    {
        self::assertTrue(KernelCanary::isActive([KernelCanary::COOKIE => KernelCanary::VALUE]));
        self::assertTrue(KernelCanary::isActive(new InputBag([KernelCanary::COOKIE => KernelCanary::VALUE])));
        self::assertFalse(KernelCanary::isActive(new InputBag([])));
        self::assertFalse(KernelCanary::isActive('not a cookie jar'));
    }
}
