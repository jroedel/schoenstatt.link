<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Laminas\HostMessages;
use JTranslate\I18n\TranslatableMessage;
use JUser\Host\FlashInterface;
use JUser\Host\Severity;

/**
 * `JUser\Host\FlashInterface` over this application's two messengers.
 *
 * `Severity` maps to a laminas namespace by value — `Severity::Error->value === 'error'` is
 * `FlashMessenger::NAMESPACE_ERROR` — which is deliberate on JUser's side and is what makes
 * this class a one-liner each way. `test/Integration/JUserHostContractTest` pins the five
 * against the constants, so a laminas rename fails a test rather than silently dropping
 * messages.
 *
 * The messengers themselves are {@see HostMessages}, which App\Kernel builds **once per
 * request** and hands to this adapter and to JTranslate's. That is not tidying: two
 * `FlashMessenger` instances silently lose one of two messages, and until 2026-09-08 the
 * memo lived in this class — which was one per module and therefore one *too many* the
 * moment a second module started serving pages here. See that class for the measurement.
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
