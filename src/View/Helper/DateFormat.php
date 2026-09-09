<?php

declare(strict_types=1);

namespace App\View\Helper;

use IntlDateFormatter;
use Locale;

use function date_default_timezone_get;
use function md5;

/**
 * The `dateFormat` view helper, formerly `Laminas\I18n\View\Helper\DateFormat`.
 *
 * A thin front for `IntlDateFormatter`, which is what the original was too — ext/intl does
 * the work of knowing that a medium date is "18 Aug 2026" in one locale and "18. Aug. 2026"
 * in another, and nothing here second-guesses it.
 *
 * The signature is the original's, because the call sites are: `App\Twig\LaminasExtension`
 * asks for SHORT/NONE, MEDIUM/NONE and MEDIUM/MEDIUM, and `SionModel`'s entity formatter
 * builds a `title` attribute the same way.
 *
 * Formatters are memoized per shape. Constructing an `IntlDateFormatter` loads locale data,
 * and a changes table formats a date per row.
 *
 * Not a `Laminas\View\Helper\AbstractHelper` since 2026-09: it asks the renderer for
 * nothing. App\Laminas\ViewHelpers constructs it directly — there is no plugin manager to
 * resolve it from since laminas-view was removed.
 */
final class DateFormat
{
    /** @var array<string, IntlDateFormatter> */
    private array $formatters = [];

    private ?string $locale = null;

    private ?string $timezone = null;

    /**
     * @param mixed $date anything IntlDateFormatter::format() accepts — a DateTimeInterface,
     *        an IntlCalendar, or a timestamp
     */
    public function __invoke(
        mixed $date,
        int $dateType = IntlDateFormatter::NONE,
        int $timeType = IntlDateFormatter::NONE,
        ?string $locale = null,
        ?string $pattern = null
    ): string|false {
        $locale ??= $this->getLocale();
        $timezone = $this->getTimezone();
        $id       = md5($dateType . "\0" . $timeType . "\0" . $locale . "\0" . (string) $pattern . "\0" . $timezone);

        $this->formatters[$id] ??= new IntlDateFormatter(
            $locale,
            $dateType,
            $timeType,
            $timezone,
            IntlDateFormatter::GREGORIAN,
            $pattern ?? ''
        );

        return $this->formatters[$id]->format($date);
    }

    public function getLocale(): string
    {
        return $this->locale ?? Locale::getDefault();
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        $this->formatters = [];

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone ?? date_default_timezone_get();
    }

    public function setTimezone(string $timezone): self
    {
        $this->timezone = $timezone;
        $this->formatters = [];

        return $this;
    }
}
