<?php

declare(strict_types=1);

namespace App\JTranslate\Host;

use App\Laminas\HostMessages;
use JTranslate\Host\FlashInterface;
use JTranslate\Host\Severity;
use JTranslate\I18n\TranslatableMessage;

/**
 * `JTranslate\Host\FlashInterface` over this application's two messengers.
 *
 * The twin of `App\JUser\Host\Flash`, and as short for the same reason: `Severity`'s values
 * *are* the laminas flash namespaces, so the mapping is `$severity->value`. Both adapters
 * share one {@see HostMessages} — one `FlashMessenger` per request, not per module, which
 * is the property that keeps two messages from becoming one.
 *
 * `test/Integration/JTranslateHostContractTest` pins the five values against the laminas
 * constants. That test is not ceremony: a message written by this surface crosses a
 * redirect **in the session**, and on a value that did not match, the layout would look in
 * a bucket nothing wrote to. Every successful save on this surface redirects, so the
 * messages that would vanish are precisely the ones confirming a write.
 */
final class Flash implements FlashInterface
{
    public function __construct(private readonly HostMessages $messages)
    {
    }

    public function flash(Severity $severity, string|TranslatableMessage $message): void
    {
        $this->messages->flash($severity->value, $message);
    }

    public function now(Severity $severity, string|TranslatableMessage $message): void
    {
        $this->messages->now($severity->value, $message);
    }
}
