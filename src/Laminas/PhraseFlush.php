<?php

declare(strict_types=1);

namespace App\Laminas;

use JTranslate\Model\TranslationsTable;

/**
 * Holds the translations table for the end-of-request phrase flush, if a
 * Symfony-served request built one.
 *
 * ## The bug this exists for
 *
 * A phrase enters `trans_phrases` in two steps: the translator fires
 * `EVENT_MISSING_TRANSLATION`, JTranslate's listener queues the miss, and
 * `TranslationsTable::flush()` writes the queue at the end of the request.
 * `TranslationsTableFactory::wireEndOfRequestFlush()` hangs that second step on
 * `MvcEvent::EVENT_FINISH`, which a Symfony-served route never reaches — its own
 * comment says "a console command or a Symfony-served request calls flush()
 * explicitly instead", and nothing in `src/` did.
 *
 * So on a ported route, discovery was a no-op: the miss was queued and thrown away.
 * Nothing failed and nothing logged, because a queued-but-unwritten phrase renders
 * exactly like a written one — the translator falls back to the source either way.
 * Measured: a first-ever `translate('World')` on `/it/shrines` recorded nothing.
 * Since `public/.htaccess` makes the Symfony kernel the site default, that is the
 * whole site from the next deploy onward, and the site's own recovery story — "a
 * deleted phrase comes back the next time a page renders it" — would stop holding.
 *
 * ## Why a holder and not just `$container->get(TranslationsTable::class)`
 *
 * Because the flush has to cost nothing on a request that never translated.
 * `App\Http\SessionListener` builds the ServiceBridge on *every* request, so "has
 * laminas been touched" is not the question; "was the translator built" is, and the
 * ServiceManager has no public way to ask whether a service was instantiated.
 * Asking for the table anyway would build it — and its factory reaches for
 * `Application` to wire the laminas flush, so `/_health` and the maintenance
 * endpoints would pay for a laminas MVC application they exist to avoid.
 *
 * `App\Laminas\TranslatorConfigurator` runs exactly when the translator is first
 * built, which is exactly when a phrase can be discovered, so it arms this. An
 * unarmed flush is one instanceof and a return.
 */
final class PhraseFlush
{
    private ?TranslationsTable $table = null;

    /** Called by TranslatorConfigurator, which builds the table anyway. */
    public function arm(TranslationsTable $table): void
    {
        $this->table = $table;
    }

    public function isArmed(): bool
    {
        return null !== $this->table;
    }

    /**
     * Write whatever the request discovered.
     *
     * `flush()` is cheap when nothing was discovered — it returns on an empty queue
     * before any of the expensive work — so this needs no guard of its own beyond
     * the arming.
     *
     * @param string|null $routeName recorded on a new phrase as the place it was
     *        first seen, which is the only context a translator ever gets. The
     *        Symfony route name is the counterpart of the laminas one that
     *        `MvcEvent::EVENT_FINISH` passes.
     */
    public function flush(?string $routeName = null): void
    {
        $this->table?->flush($routeName);
    }
}
