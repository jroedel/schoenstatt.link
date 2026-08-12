<?php

declare(strict_types=1);

namespace App\Laminas;

use Closure;
use Laminas\View\Helper\AbstractHelper;
use Stringable;

/**
 * `SlmLocale\View\Helper\LocaleUrl`, reimplemented against App\Laminas\RouteUrl and
 * registered into the helper plugin manager in its place.
 *
 * ## Why this exists rather than another entry on the refused list
 *
 * `localeUrl` is one of the helpers App\Laminas\ViewHelpers refuses by name: its factory
 * calls `$application->getMvcEvent()->getRouteMatch()`, which is null on a Symfony-served
 * route, and the failure is the familiar "Call to a member function getRouteMatch() on
 * null" thrown from inside laminas-view on a page that is otherwise fine.
 *
 * Refusing it is only workable while nothing *reachable* calls it. `Books\View\Helper\
 * BooksJsonLd` does — one line, in its `url` branch, to give a publication's schema.org
 * `Book` its canonical `en_US` address — and that helper is 208 lines of field-to-property
 * mapping that would otherwise have to be copied wholesale to replace one URL. Overriding
 * the helper it reaches is the smaller change by two orders of magnitude, and it fixes
 * every future caller at the same time.
 *
 * **This was found by a rendering failure, not by review.** The audit that cleared the
 * other bridged helpers grepped for `$this->view->url(`, and `localeUrl` is a different
 * name; `/en/SL202186L` came back as an empty 200 — the fatal-200 signature — and the
 * exception record named the line. The lesson is in ViewHelpers' docblock now: the test
 * is "does it reach a *URL-assembling* helper", not "does it call url()".
 *
 * ## What it reproduces, and the one thing it does not
 *
 * The original assembles the route and then hands the path to
 * `SlmLocale\Locale\Detector::assemble()`, which the UriPathStrategy answers by
 * prefixing the locale segment. `RouteUrl::localized()` is that prefixing, so the two
 * agree on `/en/SL202186L/…`.
 *
 * `$reuseMatchedParams` is accepted and ignored, as it must be: there is no RouteMatch
 * here to reuse anything from. Every call in this application passes explicit params —
 * BooksJsonLd passes `sw_id` and `slug` — so the parameter is unreachable rather than
 * unimplemented.
 *
 * A `Stringable` is returned rather than a string because callers do
 * `->__toString()` on the result: the original returns a `Laminas\Uri\Uri`.
 */
final class LocaleUrlSubstitute extends AbstractHelper
{
    /** @param Closure(): RouteUrl $urls resolved lazily; see App\Kernel::viewHelpers() */
    public function __construct(private readonly Closure $urls)
    {
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    public function __invoke(
        string $locale,
        ?string $name = null,
        array $params = [],
        array $options = [],
        bool $reuseMatchedParams = true
    ): Stringable {
        if (null === $name) {
            //the original's no-route-match branch answers with the current path. Nothing
            //in this application takes it, and a Symfony-served route always knows its
            //own name, so an empty string is the honest answer rather than a guess.
            return new class ('') implements Stringable {
                public function __construct(private readonly string $url)
                {
                }

                public function __toString(): string
                {
                    return $this->url;
                }
            };
        }

        $urls = ($this->urls)();
        $url  = $urls->localized($urls->path($name, $params, $options), $locale);

        return new class ($url) implements Stringable {
            public function __construct(private readonly string $url)
            {
            }

            public function __toString(): string
            {
                return $this->url;
            }
        };
    }
}
