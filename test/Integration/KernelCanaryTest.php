<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Http\GdprCookieListener;
use App\Http\KernelCanary;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;

use function basename;
use function explode;
use function file_get_contents;
use function getenv;
use function max;
use function min;
use function preg_match;
use function preg_quote;
use function putenv;
use function str_contains;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The canary cookie is named the same thing in PHP and in Apache, and the Apache lines
 * are in the order that makes them work.
 *
 * There are two definitions of the switch and only one of them is code:
 *
 *     public/.htaccess   SetEnvIf Cookie "sl_symfony_canary=1" SYMFONY_KERNEL=1
 *                        SetEnvIf Cookie "sl_symfony_canary=0" SYMFONY_KERNEL=0
 *     App\Http\KernelCanary::COOKIE / ::FORCE_SYMFONY / ::FORCE_LAMINAS
 *
 * Nothing in PHP reads the Apache config, so a rename on either side is invisible: the
 * toggle sets a cookie, the admin is redirected, a success message appears, and the
 * front controller never changes. That failure has no symptom at all, which is why this
 * test reads the .htaccess as text and compares.
 *
 * **Two of these tests exist for the global flip specifically.** That flip is one added
 * line — `SetEnvIf Request_URI ".*" SYMFONY_KERNEL=1` — and there are exactly two ways
 * to write it that look right and silently kill the per-visitor escape hatch back to
 * laminas: putting it *below* the cookie overrides (mod_setenvif takes the last match,
 * so the default wins), or writing it with `SetEnv` (mod_env runs after all of
 * mod_setenvif, so it beats every line whatever the order). Both were measured — the
 * first by probe on 2026-08-09, the second the hard way on 2026-08-08 — and neither has
 * any symptom beyond a cookie that stops doing anything. So they are pinned here, on
 * the file, before the flip is written rather than after.
 *
 * The rest of the file pins the semantics the toggle branches on — `isOverriding`
 * deciding which way to switch, and `hasConsent` deciding whether switching is
 * possible.
 */
class KernelCanaryTest extends TestCase
{
    private const HTACCESS = __DIR__ . '/../../public/.htaccess';

    /**
     * Both directives exist and match on exactly the cookie values PHP writes.
     *
     * Matched loosely on whitespace and quoting, strictly on the name and value: the
     * point is to catch a rename, not to freeze the formatting of an Apache config.
     */
    public function testTheApacheDirectivesMatchTheCookiesPhpWrites(): void
    {
        $htaccess = (string) file_get_contents(self::HTACCESS);

        foreach (
            [
                KernelCanary::FORCE_SYMFONY => '1',
                KernelCanary::FORCE_LAMINAS => '0',
            ] as $cookieValue => $kernel
        ) {
            self::assertSame(
                1,
                preg_match($this->directivePattern((string) $cookieValue, $kernel), $htaccess),
                'public/.htaccess has no SetEnvIf mapping ' . KernelCanary::COOKIE . '='
                . $cookieValue . ' to SYMFONY_KERNEL=' . $kernel . '. The toggle would set a '
                . 'cookie that switches nothing, with a success message and no symptom.'
            );
        }
    }

    /**
     * A site-wide default, if one is present, comes *above* both cookie overrides.
     *
     * This is the assertion that guards the flip. mod_setenvif evaluates top to bottom
     * and the last match wins, so a default written below an override overwrites it and
     * the escape hatch dies silently. Probed 2026-08-09: with the order reversed, a
     * request carrying the opt-out cookie still arrived with SYMFONY_KERNEL=1.
     *
     * Skipped rather than failed while no default exists, because prep landed before
     * the flip on purpose — the file is meant to reach this state and then have one
     * line added to it.
     */
    public function testASiteWideDefaultWouldSitAboveTheCookieOverrides(): void
    {
        $htaccess = (string) file_get_contents(self::HTACCESS);

        //any SetEnvIf/SetEnvIfExpr for this variable that is not one of the two cookie
        //overrides is a site-wide default, whatever it keys on
        $defaults = [];
        foreach (explode("\n", $htaccess) as $offset => $line) {
            if (1 !== preg_match('/^\s*SetEnvIf(Expr)?\s+.*\bSYMFONY_KERNEL=/i', $line)) {
                continue;
            }
            if (str_contains($line, KernelCanary::COOKIE)) {
                continue;
            }
            $defaults[] = $offset;
        }

        if ([] === $defaults) {
            self::markTestSkipped(
                'no site-wide SYMFONY_KERNEL default in public/.htaccess yet — production '
                . 'still defaults to laminas-mvc, which is the pre-flip state'
            );
        }

        $firstOverride = $this->lineOf($htaccess, KernelCanary::FORCE_SYMFONY, '1');
        $secondOverride = $this->lineOf($htaccess, KernelCanary::FORCE_LAMINAS, '0');

        self::assertLessThan(
            min($firstOverride, $secondOverride),
            max($defaults),
            'a site-wide SYMFONY_KERNEL default is declared below one of the '
            . KernelCanary::COOKIE . ' overrides in public/.htaccess. mod_setenvif takes the '
            . 'last match, so the default silently overwrites the override and the per-visitor '
            . 'escape hatch stops working with no other symptom.'
        );
    }

    /**
     * PHP and Apache agree on the name of the variable itself, not just the cookie.
     *
     * public/index.php branches on the literal string; KernelCanary::ENV_VAR is what the
     * toggle reads to decide which kernel to offer. A rename in one place leaves the
     * other reading an unset variable, i.e. silently reporting laminas forever.
     */
    public function testTheEnvironmentVariableIsNamedTheSameEverywhere(): void
    {
        foreach ([self::HTACCESS, __DIR__ . '/../../public/index.php'] as $file) {
            self::assertStringContainsString(
                KernelCanary::ENV_VAR,
                (string) file_get_contents($file),
                basename($file) . ' does not mention ' . KernelCanary::ENV_VAR
                . ', so PHP and Apache no longer agree on which variable selects the front controller'
            );
        }
    }

    private function directivePattern(string $cookieValue, string $kernel): string
    {
        return '/SetEnvIf\s+Cookie\s+"'
            . preg_quote(KernelCanary::COOKIE, '/') . '='
            . preg_quote($cookieValue, '/')
            . '"\s+SYMFONY_KERNEL=' . preg_quote($kernel, '/') . '/';
    }

    /** Which line of the file carries one of the two overrides. */
    private function lineOf(string $htaccess, string $cookieValue, string $kernel): int
    {
        foreach (explode("\n", $htaccess) as $offset => $line) {
            if (1 === preg_match($this->directivePattern($cookieValue, $kernel), $line)) {
                return $offset;
            }
        }

        self::fail(
            'public/.htaccess has no ' . KernelCanary::COOKIE . '=' . $cookieValue . ' override, '
            . 'which testTheApacheDirectivesMatchTheCookiesPhpWrites should already have said'
        );
    }

    /**
     * Nothing grants the kernel with `SetEnv`. mod_env runs *after* the whole of
     * mod_setenvif, so a `SetEnv SYMFONY_KERNEL` beats both cookie overrides regardless
     * of the order they appear in. That is not a hypothetical; it is the mistake made
     * while building this, on 2026-08-08.
     *
     * It is also the most tempting way to write the global flip — one line, no regex —
     * which is precisely why this stays: the flip written that way works for everyone and
     * takes the escape hatch with it.
     */
    public function testNothingElseSetsTheKernelVariable(): void
    {
        $htaccess = (string) file_get_contents(self::HTACCESS);

        self::assertSame(
            0,
            preg_match('/^\s*SetEnv\s+"?SYMFONY_KERNEL/mi', $htaccess),
            'public/.htaccess contains a `SetEnv SYMFONY_KERNEL`, which silently disables both '
            . 'cookie overrides — mod_env runs after mod_setenvif and wins whatever the file '
            . 'order. Write the site-wide default as `SetEnvIf Request_URI ".*" SYMFONY_KERNEL=1` '
            . 'above them instead.'
        );
    }

    /**
     * The toggle reads its own state from the request to decide which way to switch, and
     * the three cookie states are distinct: absent is not the same as either override.
     */
    public function testTheThreeCookieStatesAreToldApart(): void
    {
        $forcingSymfony = [KernelCanary::COOKIE => KernelCanary::FORCE_SYMFONY];
        $forcingLaminas = [KernelCanary::COOKIE => KernelCanary::FORCE_LAMINAS];

        self::assertTrue(KernelCanary::isOverriding($forcingSymfony));
        self::assertTrue(KernelCanary::isOverriding($forcingLaminas));
        self::assertTrue(KernelCanary::forcesSymfony($forcingSymfony));
        self::assertFalse(KernelCanary::forcesSymfony($forcingLaminas));
        self::assertTrue(KernelCanary::forcesLaminas($forcingLaminas));
        self::assertFalse(KernelCanary::forcesLaminas($forcingSymfony));

        //absent means "whatever everyone else gets", which is neither override. Reading it
        //as one would make the toggle offer to clear something that was never set.
        self::assertFalse(KernelCanary::isOverriding([]));
        self::assertFalse(KernelCanary::isOverriding(null));
        self::assertFalse(KernelCanary::forcesSymfony([]));
        self::assertFalse(KernelCanary::forcesLaminas([]));

        //Apache matches the two literal values and nothing else, so nothing else counts
        self::assertFalse(KernelCanary::isOverriding([KernelCanary::COOKIE => 'true']));
        self::assertFalse(KernelCanary::isOverriding([KernelCanary::COOKIE => '']));
    }

    /**
     * Which kernel the toggle offers comes from the environment, so that neither this
     * class nor the action has to know what public/.htaccess makes the default — the one
     * fact PHP cannot read, and the one that changes on flip day.
     */
    public function testTheLiveKernelIsReadFromTheEnvironmentTheWayIndexPhpReadsIt(): void
    {
        $restore = getenv(KernelCanary::ENV_VAR);

        try {
            putenv(KernelCanary::ENV_VAR . '=1');
            self::assertTrue(KernelCanary::symfonyKernelIsLive());

            putenv(KernelCanary::ENV_VAR . '=0');
            self::assertFalse(KernelCanary::symfonyKernelIsLive());

            //index.php reads `getenv(...) ?: '0'`, so an empty value is laminas there. If it
            //read as Symfony here the toggle would offer the kernel the visitor is already on.
            putenv(KernelCanary::ENV_VAR . '=');
            self::assertFalse(KernelCanary::symfonyKernelIsLive());

            putenv(KernelCanary::ENV_VAR);
            self::assertFalse(KernelCanary::symfonyKernelIsLive());
        } finally {
            false === $restore
                ? putenv(KernelCanary::ENV_VAR)
                : putenv(KernelCanary::ENV_VAR . '=' . $restore);
        }
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
        $forcing = [KernelCanary::COOKIE => KernelCanary::FORCE_SYMFONY];

        self::assertTrue(KernelCanary::forcesSymfony($forcing));
        self::assertTrue(KernelCanary::forcesSymfony(new InputBag($forcing)));
        self::assertFalse(KernelCanary::forcesSymfony(new InputBag([])));
        self::assertFalse(KernelCanary::forcesSymfony('not a cookie jar'));
    }
}
