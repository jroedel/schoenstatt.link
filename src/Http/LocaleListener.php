<?php

declare(strict_types=1);

namespace App\Http;

use App\Locale\Locales;
use Locale;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

use function array_values;
use function in_array;
use function is_string;

/**
 * Sets \Locale::getDefault() for Symfony-served routes, from the matched route's
 * `_locale` or — when the path carries no locale prefix — by negotiation.
 *
 * This is not cosmetic and not deferrable. In a Symfony-served request nothing has
 * run SlmLocale, so the process default is whatever PHP inherited, measured as
 * `en_US_POSIX` in the capsule. Schoenstatt\Model\SchoenstattTable indexes its
 * per-locale arrays *by that string* (`$v['nameByLocale'][$locale]` in getShrines(),
 * and again in every format helper), so a wrong default is not a wrong language: it
 * is "Undefined array key en_US_POSIX" and null names. It also has to happen before
 * anything is fetched from the laminas container, because
 * JTranslate\View\Helper\Flag reads the locale in its *constructor* and caches the
 * translated country names for good — which is why this is a kernel.request
 * listener and not a line at the top of each controller.
 *
 * The negotiation reproduces the order in `slm_locale.strategies`: the URI path
 * first (i.e. `_locale`, already matched by the router), then the `slm_locale`
 * cookie, then Accept-Language, then the configured default. A ported route whose
 * bare form is reachable is expected to answer it with a redirect to the prefixed
 * form, the way SlmLocale's `redirect_when_found` does — and the locale that
 * redirect points at is the one settled here.
 *
 * Bridged requests are skipped: SlmLocale makes all of these decisions for those,
 * from the same alias table, and pre-empting it would make this listener responsible
 * for a choice laminas is still making.
 */
final class LocaleListener
{
    public const LOCALE_COOKIE = 'slm_locale';

    public function __invoke(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (! SymfonyRoute::isPorted($request)) {
            return;
        }

        $alias = $request->attributes->get('_locale');
        Locale::setDefault(is_string($alias) ? Locales::localeFor($alias) : $this->negotiate($request));
    }

    private function negotiate(Request $request): string
    {
        $cookie = $request->cookies->get(self::LOCALE_COOKIE);
        if (is_string($cookie) && in_array($cookie, Locales::ALIASES, true)) {
            return $cookie;
        }

        //getPreferredLanguage falls back to the first entry, and ALIASES is ordered
        //with the default locale first, so this covers "no Accept-Language" too
        $preferred = $request->getPreferredLanguage(array_values(Locales::ALIASES));

        return is_string($preferred) && in_array($preferred, Locales::ALIASES, true)
            ? $preferred
            : Locales::DEFAULT_LOCALE;
    }
}
