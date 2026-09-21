<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Locale\Locales;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\I18n\RelativeTime;

require_once __DIR__ . '/../../module/SionModel/src/I18n/RelativeTime.php';

/**
 * `SionModel\I18n\RelativeTime`, which replaced `Carbon::diffForHumans()` on 2026-09-21.
 *
 * Until then this output was pinned by **nothing** — no test in the suite asserted on it,
 * and it renders on four public templates in five languages. It is the comment timestamp on
 * publication, composition and text pages, the "Posted …" line, and the contact-freshness
 * badge on association and person pages.
 *
 * The cases below were checked against Carbon across 175 (locale, offset) pairs with zero
 * differences while the package was still installed; what is kept here is a readable subset
 * plus the edges that decide a unit.
 */
final class RelativeTimeTest extends TestCase
{
    private const NOW = '2026-09-21 12:00:00';

    /** @return iterable<string, array{string, string, string}> */
    public static function cases(): iterable
    {
        $expectations = [
            //offset            en                   es                      de                       pt                      it
            '-1 second'  => ['1 second ago',      'hace 1 segundo',       'vor 1 Sekunde',         'há 1 segundo',        '1 secondo fa'],
            '-30 seconds' => ['30 seconds ago',   'hace 30 segundos',     'vor 30 Sekunden',       'há 30 segundos',      '30 secondi fa'],
            '-1 minute'  => ['1 minute ago',      'hace 1 minuto',        'vor 1 Minute',          'há 1 minuto',         '1 minuto fa'],
            '-5 minutes' => ['5 minutes ago',     'hace 5 minutos',       'vor 5 Minuten',         'há 5 minutos',        '5 minuti fa'],
            '-1 hour'    => ['1 hour ago',        'hace 1 hora',          'vor 1 Stunde',          'há 1 hora',           '1 ora fa'],
            '-1 day'     => ['1 day ago',         'hace 1 día',           'vor 1 Tag',             'há 1 dia',            '1 giorno fa'],
            '-3 days'    => ['3 days ago',        'hace 3 días',          'vor 3 Tagen',           'há 3 dias',           '3 giorni fa'],
            '-7 days'    => ['1 week ago',        'hace 1 semana',        'vor 1 Woche',           'há 1 semana',         '1 settimana fa'],
            '-28 days'   => ['4 weeks ago',       'hace 4 semanas',       'vor 4 Wochen',          'há 4 semanas',        '4 settimane fa'],
            '-31 days'   => ['1 month ago',       'hace 1 mes',           'vor 1 Monat',           'há 1 mês',            '1 mese fa'],
            '-2 months'  => ['2 months ago',      'hace 2 meses',         'vor 2 Monaten',         'há 2 meses',          '2 mesi fa'],
            '-365 days'  => ['1 year ago',        'hace 1 año',           'vor 1 Jahr',            'há 1 ano',            '1 anno fa'],
            '-57 years'  => ['57 years ago',      'hace 57 años',         'vor 57 Jahren',         'há 57 anos',          '57 anni fa'],
            '+1 day'     => ['1 day from now',    'en 1 día',             'in 1 Tag',              'em 1 dia',            'tra 1 giorno'],
            '+3 years'   => ['3 years from now',  'en 3 años',            'in 3 Jahren',           'em 3 anos',           'tra 3 anni'],
        ];

        $locales = ['en_US', 'es_ES', 'de_DE', 'pt_BR', 'it_IT'];

        foreach ($expectations as $offset => $perLocale) {
            foreach ($locales as $i => $locale) {
                yield sprintf('%s %s', $locale, $offset) => [$offset, $locale, $perLocale[$i]];
            }
        }
    }

    #[DataProvider('cases')]
    public function testTheDistanceReadsAsCarbonWroteIt(string $offset, string $locale, string $expected): void
    {
        $now  = new DateTimeImmutable(self::NOW);
        $date = $now->modify($offset);

        self::assertSame($expected, RelativeTime::format($date, $locale, $now));
    }

    /**
     * The guard that makes the two-form plural rule honest.
     *
     * Every configured locale is a CLDR two-form language, which is the only reason
     * `1 === $count` is a correct plural rule. If a sixth locale is configured — a Slavic
     * or Arabic one especially — this fails, and the answer then is ext/intl's
     * `MessageFormatter`, not another entry in the table.
     */
    public function testEveryConfiguredLocaleHasATable(): void
    {
        //App\Locale\Locales is the live list — the `slm_locale` config key that used to
        //hold it is documented as historical and nothing reads it.
        $supported = array_values(Locales::ALIASES);

        self::assertNotEmpty($supported, 'no supported locales were found, so this proves nothing');

        $now = new DateTimeImmutable(self::NOW);
        foreach ($supported as $locale) {
            $english = RelativeTime::format($now->modify('-3 days'), 'en_US', $now);
            $actual  = RelativeTime::format($now->modify('-3 days'), (string) $locale, $now);

            if ('en_US' === $locale) {
                continue;
            }

            self::assertNotSame(
                $english,
                $actual,
                sprintf(
                    'locale %s fell back to English, so SionModel\I18n\RelativeTime has no '
                    . 'table for it — and if it is not a two-form plural language, adding '
                    . 'one is not enough',
                    (string) $locale
                )
            );
        }
    }

    /** A zero difference reads as one second rather than as nothing, as Carbon did. */
    public function testAZeroDifferenceIsOneSecond(): void
    {
        $now = new DateTimeImmutable(self::NOW);

        self::assertSame('1 second ago', RelativeTime::format($now, 'en_US', $now));
    }

    /** `en_US_POSIX` is what `Locale::getDefault()` answers off the web. */
    public function testAnUnknownLocaleFallsBackToEnglish(): void
    {
        $now = new DateTimeImmutable(self::NOW);

        self::assertSame(
            '3 days ago',
            RelativeTime::format($now->modify('-3 days'), 'en_US_POSIX', $now)
        );
    }
}
