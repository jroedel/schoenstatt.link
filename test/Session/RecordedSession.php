<?php

declare(strict_types=1);

namespace SchoenstattTest\Session;

use JTranslate\I18n\TranslatableMessage;
use SplQueue;

use function get_class;
use function is_array;
use function is_object;
use function is_scalar;
use function iterator_to_array;
use function sprintf;

/**
 * The laminas-free half of the session recording: how a recovered value is written down.
 *
 * Separate from {@see SessionSurface} on purpose. That class builds the recording and can
 * only run while `laminas/laminas-session` is installed, which it no longer is;
 * `SessionSurfaceTest` has to run for ever. A recording outlives its subject and the
 * instrument that took it does not.
 */
final class RecordedSession
{
    /** The fixed clock the recording was taken on, so it does not move between runs. */
    public const NOW = 1790000000;

    /**
     * A comparable rendering of a recovered value.
     *
     * The expectations in the recording are written by hand — they are the contract, not a
     * record of what the code happens to do — so an object has to reduce to something a
     * human can write down. `TranslatableMessage` is the only object that crosses this
     * boundary, and it does so inside an `SplQueue` of flash messages.
     */
    public static function describe(mixed $value): mixed
    {
        if ($value instanceof SplQueue) {
            $value = iterator_to_array($value, false);
        }

        if (is_array($value)) {
            $described = [];
            foreach ($value as $key => $item) {
                $described[$key] = self::describe($item);
            }

            return $described;
        }

        if ($value instanceof TranslatableMessage) {
            return sprintf(
                '%s(%s @ %s)',
                TranslatableMessage::class,
                $value->getTemplate(),
                (string) $value->getTextDomain()
            );
        }

        if (is_object($value)) {
            return get_class($value);
        }

        return is_scalar($value) || null === $value ? $value : null;
    }
}
