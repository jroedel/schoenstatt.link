<?php

declare(strict_types=1);

namespace App\Time;

use DateTimeImmutable;
use DateTimeZone;

use function abs;
use function array_multisort;
use function intdiv;
use function sprintf;
use function str_pad;
use function timezone_offset_get;

use const SORT_NUMERIC;
use const STR_PAD_LEFT;

/**
 * The time-zone dropdown, in our own code.
 *
 * What `ScottConnerly\TimeZone\TimeZoneSelect::get_time_zones()` did, minus the package —
 * which was **GPL-2.0-only** inside a BSD-3-Clause application, and which only one caller
 * ever reached, for two of the seven fields it returned. The names come from
 * {@see timezone-names.php}; see that file for where they come from and what changed.
 *
 * ## The offset is the zone's *standard* offset, deliberately
 *
 * The original computed each label's offset with `timezone_offset_get($zone, new DateTime())`
 * — the offset *now* — so every label followed daylight saving. `Europe/Berlin` read
 * `(GMT+02:00) Bern` in July and `(GMT+01:00)` in November, and 136 of those labels are
 * recorded in `test/Element/element-surface.php`. That recording was therefore due to break
 * on 2026-10-25 and again on 2026-11-01, with a diff no commit caused and an obvious wrong
 * fix waiting (regenerate it). A label that says what a zone *is* rather than what it is
 * doing this week is both stabler and more useful in a dropdown.
 *
 * The ordering is the original's and is kept: sort by offset ascending, ties in file order,
 * which is what puts the date line first and keeps regional neighbours together.
 */
final class TimeZoneOptions
{
    /** @var array<string, string>|null display name => IANA identifier */
    private static ?array $names = null;

    /**
     * Every offered zone, as `identifier => label`.
     *
     * Where two names share an identifier — Rails lists both `Edinburgh` and `London` for
     * `Europe/London` — the later name wins, because that is what keying by identifier did
     * before and the recordings hold those answers.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $options = [];
        foreach (self::sortedByOffset() as [$identifier, $label]) {
            $options[$identifier] = $label;
        }

        return $options;
    }

    /**
     * @return list<array{string, string}> identifier and label, offset ascending
     */
    private static function sortedByOffset(): array
    {
        $rows    = [];
        $offsets = [];
        $order   = [];
        $index   = 0;

        foreach (self::names() as $name => $identifier) {
            $offset = self::standardOffset($identifier);

            $rows[]    = [$identifier, sprintf('(GMT%s) %s', self::formatOffset($offset), $name)];
            $offsets[] = $offset;
            $order[]   = $index++;
        }

        //Ties keep their original order, which is what the file's grouping is for — without
        //the second key `International Date Line West` stops being first.
        array_multisort($offsets, SORT_NUMERIC, $order, $rows);

        return $rows;
    }

    /**
     * The offset the zone keeps outside daylight saving.
     *
     * Read at the start of the year and at midsummer, taking the smaller: for a northern
     * zone that is January, for a southern one July, and for a zone with no daylight saving
     * the two are equal. `getTransitions()` would answer this more directly but costs a
     * scan of the zone's whole history for each of 154 zones.
     */
    private static function standardOffset(string $identifier): int
    {
        $zone = new DateTimeZone($identifier);

        $winter = timezone_offset_get($zone, new DateTimeImmutable('2026-01-15T12:00:00Z'));
        $summer = timezone_offset_get($zone, new DateTimeImmutable('2026-07-15T12:00:00Z'));

        return $winter <= $summer ? $winter : $summer;
    }

    /** `+HH:MM`, zero padded, as the package wrote it. */
    private static function formatOffset(int $seconds): string
    {
        $sign      = $seconds >= 0 ? '+' : '-';
        $magnitude = abs($seconds);

        return sprintf(
            '%s%s:%s',
            $sign,
            str_pad((string) intdiv($magnitude, 3600), 2, '0', STR_PAD_LEFT),
            str_pad((string) intdiv($magnitude % 3600, 60), 2, '0', STR_PAD_LEFT)
        );
    }

    /** @return array<string, string> */
    private static function names(): array
    {
        /** @var array<string, string> $names */
        $names = self::$names ??= require __DIR__ . '/timezone-names.php';

        return $names;
    }
}
