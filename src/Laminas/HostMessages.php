<?php

declare(strict_types=1);

namespace App\Laminas;

use JTranslate\I18n\TranslatableMessage;
use Laminas\Session\ManagerInterface;
use SionModel\Messaging\FlashMessages;
use SionModel\Messaging\NowMessages;

/**
 * This application's two messengers, behind one object per request.
 *
 * Every module that serves a page here needs to tell the visitor something, and each
 * declares its own host contract for it — `JUser\Host\FlashInterface`,
 * `JTranslate\Host\FlashInterface`. Those interfaces are structurally identical and
 * deliberately separate (JUser depends on JTranslate, so JTranslate cannot depend back),
 * which leaves the *implementation* as the thing that must not be duplicated. This is it;
 * the two adapters in `src/JUser/Host/` and `src/JTranslate/Host/` are the enum-to-string
 * mapping and nothing else, and the ported controllers take this class directly.
 *
 * ## One store for the whole request, and that is the entire point
 *
 * {@see FlashMessages} moves **every namespace** out of the session container into its
 * own memory the first time it is used. That is correct for reading last request's
 * messages — and fatal if a second *instance* does it after the first has already written:
 * the second's move takes the first's message out of the session, keeps it in an object
 * discarded at the end of the request, and only the second message survives.
 *
 * Measured 2026-08-21 on sign-in redemption, which reports "You are signed in." and "but
 * not there" together and showed only the second. So App\Kernel builds this once per
 * request, and everything that writes or renders a message takes it.
 *
 * Rendering is `App\Twig\LaminasExtension::flashMessages()` and `nowMessages()`, through
 * `JTranslate\I18n\MessageRenderer`; the layout calls both.
 */
final class HostMessages
{
    private ?FlashMessages $flashes = null;

    private readonly NowMessages $current;

    /**
     * @param ManagerInterface|null $sessions the session manager the flash container
     *        belongs to; null means the container's default manager, which the
     *        application's SessionListener starts
     */
    public function __construct(private readonly ?ManagerInterface $sessions = null)
    {
        $this->current = new NowMessages();
    }

    /**
     * Survives a redirect; read by the next page rendered in this session.
     *
     * @param string $namespace one of the FlashMessages::NAMESPACE_* constants — which is
     *        exactly what each module's `Severity` enum has for a value, on purpose
     */
    public function flash(string $namespace, string|TranslatableMessage $message): void
    {
        $this->flashes()->add($namespace, $message);
    }

    /** Rendered by the response being returned now. */
    public function now(string $namespace, string|TranslatableMessage $message): void
    {
        $this->current->add($namespace, $message);
    }

    /**
     * What the previous request flashed in one namespace.
     *
     * @return list<mixed>
     */
    public function flashed(string $namespace): array
    {
        return $this->flashes()->messages($namespace);
    }

    /**
     * What this request said so far in one namespace.
     *
     * @return list<mixed>
     */
    public function current(string $namespace): array
    {
        return $this->current->messages($namespace);
    }

    /**
     * Lazily, because touching the store opens the session container: a request that
     * neither flashes nor renders the layout (an API call, /_health) must not.
     */
    private function flashes(): FlashMessages
    {
        return $this->flashes ??= new FlashMessages($this->sessions);
    }
}
