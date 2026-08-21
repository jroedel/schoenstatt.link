<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Laminas\ServiceBridge;
use JTranslate\Controller\Plugin\NowMessenger;
use JTranslate\I18n\TranslatableMessage;
use JUser\Host\FlashInterface;
use JUser\Host\Severity;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;

/**
 * `JUser\Host\FlashInterface` over this application's two messengers.
 *
 * `Severity` maps to a laminas namespace by value — `Severity::Error->value === 'error'` is
 * `FlashMessenger::NAMESPACE_ERROR` — which is deliberate on JUser's side and is what makes
 * this class a one-liner each way. `test/Integration/JUserHostContractTest` pins the five
 * against the constants, so a laminas rename fails a test rather than silently dropping
 * messages.
 *
 * ## One FlashMessenger for the whole request, and it is not an optimisation
 *
 * `addMessage()` calls `getMessagesFromContainer()` the first time an instance is used,
 * which moves **every namespace** out of the session container into that instance's own
 * memory and unsets it from the container. That is correct for reading last request's
 * messages — and fatal if a second *instance* does it after the first has already written:
 * the second's move takes the first's message out of the session, keeps it in an object
 * discarded at the end of the request, and only the second message survives.
 *
 * Measured 2026-08-21 on redemption, which reports "You are signed in." and "but not there"
 * together and showed only the second. The laminas controller never had the problem because
 * `$this->flashMessenger()` is a *shared* controller plugin — the same object both times —
 * so it is one a port introduces rather than one it inherits.
 *
 * This class is registered once per request in App\Kernel and memoizes the messenger, which
 * is the discipline `FlashInterface`'s docblock asks a host for.
 */
final class Flash implements FlashInterface
{
    private ?FlashMessenger $messenger = null;

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    public function flash(Severity $severity, string|TranslatableMessage $message): void
    {
        $this->messenger ??= new FlashMessenger();
        /** @phpstan-ignore argument.type (the plugin declares string and documents otherwise) */
        $this->messenger->setNamespace($severity->value)->addMessage($message);
    }

    /**
     * `nowMessenger` out of the controller plugin manager, which is where JTranslate
     * registers it. Fetched per call rather than memoized: it writes nowhere but its own
     * instance, the manager shares it, and there is no session container for a second
     * instance to steal from.
     */
    public function now(Severity $severity, string|TranslatableMessage $message): void
    {
        /** @var NowMessenger $messenger */
        $messenger = $this->laminas->get('ControllerPluginManager')->get('nowMessenger');
        /** @phpstan-ignore argument.type (the plugin declares string and documents otherwise) */
        $messenger->setNamespace($severity->value)->addMessage($message);
    }
}
