<?php

declare(strict_types=1);

namespace App\Http;

use App\Laminas\PhraseFlush;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

use function is_string;

/**
 * Writes the phrases a Symfony-served request discovered, after the response has
 * gone out.
 *
 * The laminas counterpart is the `MvcEvent::EVENT_FINISH` listener in
 * `JTranslate\Service\TranslationsTableFactory`, which a ported route never reaches;
 * see App\Laminas\PhraseFlush for what that cost. This one runs on `terminate`
 * rather than `response` because the write is pure bookkeeping — the visitor has no
 * reason to wait for it, and `public/index.php` does call `terminate()`.
 *
 * Costs nothing on a request that never translated: PhraseFlush is armed only by
 * TranslatorConfigurator, i.e. only when the translator was actually built.
 */
final class PhraseFlushListener
{
    public function __construct(private readonly PhraseFlush $phrases)
    {
    }

    public function __invoke(TerminateEvent $event): void
    {
        if (! $this->phrases->isArmed()) {
            return;
        }

        /** @var mixed $route */
        $route = $event->getRequest()->attributes->get('_route');

        $this->phrases->flush(is_string($route) ? $route : null);
    }
}
