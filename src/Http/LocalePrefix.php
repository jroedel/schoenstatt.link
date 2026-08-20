<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Whether a Symfony-served route answers its own *unprefixed* form, or redirects to
 * the prefixed one the way SlmLocale would.
 *
 * Every ported path is declared twice — `/music` and `/{_locale}/music` — because
 * every caller uses the prefixed form and Symfony, unlike laminas, has no
 * `SlmLocale\Strategy\UriPathStrategy` to strip the segment before routing. But
 * SlmLocale does not *serve* the unprefixed form either: it answers it with a 302 to
 * the negotiated language, so the page exists at one URL rather than two. The absence
 * of the `_locale` request attribute is how the unprefixed twin is recognised.
 *
 * ## Why this is a declaration and not a rule
 *
 * The decision is genuinely per route, which is what kept it in the controllers for so
 * long. `/_health` has no prefixed form at all. The two maintenance endpoints a deploy
 * hook calls — `sm-cache-status`, `sm-clear-persistent-cache` — are asked for
 * unprefixed and must answer, while `sm-phpinfo`, in the same tree and reached only
 * from a browser, redirects like any other page. `/api/v3` speaks JSON to a program
 * that would not follow a 302 to a language. None of that is inferable from the path,
 * so it is stated here, beside {@see \App\Authorization\RouteAccess}, in the file that
 * already is the migration status of the site.
 *
 * `servedHere()` requires a reason for the same purpose `RouteAccess::openToEveryone()`
 * does: the quiet option is the one that needs to justify itself. Both halves are
 * required arguments of the `$ported()` helper, so a route cannot acquire either policy
 * by omission.
 *
 * ## The params are derived, not declared
 *
 * The redirect target is assembled from the *laminas* route the ported one shadows —
 * `App\Http\SymfonyRoute::routeName()`, i.e. the Symfony name minus `.locale` — and its
 * parameters are the path's own placeholders, read back out of the request. That was
 * measured against all 42 hand-written call sites this class replaced: every one passed
 * exactly the placeholders of its own path, omitting the optional ones it had not
 * matched (`['sw_id' => …] + ['slug' => …]`, `['library_id' => …]`, `['inLanguage' =>
 * …]`), and every one targeted its own route name. So there was nothing per-route to
 * declare beyond the policy, and deriving it removes the failure mode those call sites
 * had: a target assembled by hand from the wrong parameters is a 302 to a URL that
 * 404s, and nothing about it is visible until someone follows the link.
 *
 * The `$ported()` helper reads the placeholders straight off the path string it is
 * already given, rather than compiling the Route at request time — the answer cannot
 * change between declaration and dispatch, and the listener stays free of the
 * collection.
 */
final class LocalePrefix
{
    /**
     * Route default / request attribute the declaration travels in.
     *
     * Underscore-prefixed like Symfony's own, and for the reason
     * `RouteAccess::ATTRIBUTE` gives: it is framework plumbing rather than a routing
     * placeholder, so ArgumentResolver will never bind it to a controller parameter.
     */
    public const ATTRIBUTE = '_locale_prefix';

    /**
     * @param list<string> $params the path's own placeholders, filled in by $ported().
     *        Empty on a literal path, and never consulted when $reason is set.
     */
    private function __construct(
        /** Why this route answers unprefixed. Null exactly when the route redirects. */
        public readonly ?string $reason,
        public readonly array $params = []
    ) {
    }

    /**
     * Answer the unprefixed form with a 302 to the prefixed one, as SlmLocale does.
     *
     * This is what every ported HTML page wants: the page then exists at one URL, which
     * is also what keeps the canonical link pointing somewhere that is not *also*
     * served here. See docs/sitemap.md on why that matters more than it looks.
     */
    public static function redirectsToPrefixed(): self
    {
        return new self(null);
    }

    /**
     * Serve the unprefixed path as itself.
     *
     * The reason is stored, asserted non-empty, and repeated in
     * `test/Integration/LocalePrefixDeclarationTest::NO_REDIRECT` — which is where the
     * whole picture lives, deliberately rather than in `tools/acl-table.php`: that tool
     * is the authorization oracle, and which URL a page answers at is not an
     * authorization question. Keeping them apart is what stops the ACL baseline churning
     * every time a route's locale handling changes.
     */
    public static function servedHere(string $reason): self
    {
        return new self($reason);
    }

    public function redirects(): bool
    {
        return null === $this->reason;
    }

    /**
     * The same declaration carrying the placeholders of the path it was declared on.
     * Called once per route by $ported(); nothing else should need it.
     *
     * @param list<string> $params
     */
    public function withParams(array $params): self
    {
        return new self($this->reason, $params);
    }
}
