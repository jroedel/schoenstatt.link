<?php

declare(strict_types=1);

namespace App\Laminas;

use JTranslate\Controller\Plugin\NowMessenger;
use JTranslate\I18n\TranslatableMessage;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;

/**
 * This application's two messengers, behind one object per request.
 *
 * Every module that serves a page here needs to tell the visitor something, and each
 * declares its own host contract for it — `JUser\Host\FlashInterface`,
 * `JTranslate\Host\FlashInterface`. Those interfaces are structurally identical and
 * deliberately separate (JUser depends on JTranslate, so JTranslate cannot depend back),
 * which leaves the *implementation* as the thing that must not be duplicated. This is it;
 * the two adapters in `src/JUser/Host/` and `src/JTranslate/Host/` are the enum-to-string
 * mapping and nothing else.
 *
 * ## One FlashMessenger for the whole request, and that is the entire point
 *
 * `addMessage()` calls `getMessagesFromContainer()` the first time an instance is used,
 * which moves **every namespace** out of the session container into that instance's own
 * memory and unsets it from the container. That is correct for reading last request's
 * messages — and fatal if a second *instance* does it after the first has already written:
 * the second's move takes the first's message out of the session, keeps it in an object
 * discarded at the end of the request, and only the second message survives.
 *
 * Measured 2026-08-21 on sign-in redemption, which reports "You are signed in." and "but
 * not there" together and showed only the second. The laminas controllers never had the
 * problem because `$this->flashMessenger()` is a *shared* controller plugin — the same
 * object every time — so it is a defect a port introduces rather than one it inherits.
 *
 * Two modules serving pages made that sharper than one instance per module can express:
 * the requirement is one messenger per **request**, not one per contract. So the memo lives
 * here, App\Kernel builds this once, and both adapters take it.
 */
final class HostMessages
{
    private ?FlashMessenger $messenger = null;

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * Survives a redirect; read by the next page rendered in this session.
     *
     * @param string $namespace a laminas flash namespace — which is exactly what each
     *        module's `Severity` enum has for a value, on purpose
     * @param string|TranslatableMessage $message
     */
    public function flash(string $namespace, string|TranslatableMessage $message): void
    {
        $this->messenger ??= new FlashMessenger();
        /** @phpstan-ignore argument.type (the plugin declares string and documents otherwise) */
        $this->messenger->setNamespace($namespace)->addMessage($message);
    }

    /**
     * Rendered by the response being returned now.
     *
     * `nowMessenger` out of the controller plugin manager, which is where JTranslate
     * registers it. Fetched per call rather than memoized: it writes nowhere but its own
     * instance, the manager shares it, and there is no session container for a second
     * instance to steal from.
     *
     * @param string|TranslatableMessage $message
     */
    public function now(string $namespace, string|TranslatableMessage $message): void
    {
        /** @var NowMessenger $messenger */
        $messenger = $this->laminas->get('ControllerPluginManager')->get('nowMessenger');
        /** @phpstan-ignore argument.type (the plugin declares string and documents otherwise) */
        $messenger->setNamespace($namespace)->addMessage($message);
    }
}
