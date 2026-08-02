<?php
namespace Books\Filter;

use Laminas\Filter\AbstractFilter;

class KentenichPeriodFromDate extends AbstractFilter
{
    const PERIOD_PRE_SCHOENSTATT = 1;
    const PERIOD_FOUNDING_ERA = 2;
    const PERIOD_GROWING_MOVEMENT = 3;
    const PERIOD_KOBLENZ_AND_DACHAU = 4;
    const PERIOD_INTERNATIONALIZATION_AND_CONFRONTATION = 5;
    const PERIOD_EXILE = 6;
    const PERIOD_FINAL_YEARS = 7;
    const PERIOD_POST_FOUNDER = 8;

    const PERIOD_NAMES = [
        self::PERIOD_PRE_SCHOENSTATT => 'Pre-Schoenstatt (1899-1912)',
        self::PERIOD_FOUNDING_ERA => 'Founding Era (1912-1919)',
        self::PERIOD_GROWING_MOVEMENT => 'The Growing Movement (1920-1941)',
        self::PERIOD_KOBLENZ_AND_DACHAU => 'Koblenz and Dachau (1941-45)',
        self::PERIOD_INTERNATIONALIZATION_AND_CONFRONTATION => 'Internationalization and Confrontation (1945-51)',
        self::PERIOD_EXILE => 'The Exile (1952-65)',
        self::PERIOD_FINAL_YEARS => 'The Final Years (1965-68)',
        self::PERIOD_POST_FOUNDER => 'After the Founder (1968-)',
    ];

    const PERIOD_END_DATES = [
        self::PERIOD_PRE_SCHOENSTATT => '1912-10-29',
        self::PERIOD_FOUNDING_ERA => '1920-01-01',
        self::PERIOD_GROWING_MOVEMENT => '1941-01-01',
        self::PERIOD_KOBLENZ_AND_DACHAU => '1945-06-01',
        self::PERIOD_INTERNATIONALIZATION_AND_CONFRONTATION => '1952-01-01',
        self::PERIOD_EXILE => '1965-09-16',
        self::PERIOD_FINAL_YEARS => '1969-01-01',
        self::PERIOD_POST_FOUNDER => '1969-01-01',
    ];

    /**
     * An associative array mapping a period to its (non-inclusive) end date
     * @var array $periodEndDateMap
     */
    protected $periodEndDateMap;

    public function __construct()
    {
        $utc = new \DateTimeZone('UTC');
        $this->periodEndDateMap = [];
        foreach (self::PERIOD_END_DATES as $period => $endDateText) {
            $this->periodEndDateMap[$period] = date_create_from_format('Y-m-d', $endDateText, $utc);
        }
    }

    /**
     * {@inheritDoc}
     * @see \Laminas\Filter\FilterInterface::filter()
     */
    public function filter($value)
    {
        if (! $value instanceof \DateTime) {
            throw new \InvalidArgumentException('Only DateTime objects accepted.');
        }

        foreach ($this->periodEndDateMap as $period => $endDate) {
            if ($value < $endDate) {
                return $period;
            }
        }
        return self::PERIOD_POST_FOUNDER;
    }
}
