<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

use function preg_match;
use function sprintf;
use function strip_tags;
use function trim;

/**
 * A breadcrumb must not put a record's own name into the phrase table.
 *
 * The navigation carries one page per publication, composition, library and association —
 * ten thousand of them here — labelled with the row's own title or name. Nothing translated
 * a breadcrumb label until 2026-08-10; once something did, every one of those labels became
 * a translator miss, and a miss is exactly how a phrase is filed. Within a day 3,071
 * publication titles were 61% of the whole phrase table, each filed twice because the
 * partial falls back to the `default` domain (`docs/api-change-requests-response.md` §12).
 *
 * The failure is invisible from a rendered page: the crumb reads correctly either way, and
 * the cost lands in a table nobody looks at until an agent asks what is left to translate.
 * So the assertion is on `trans_phrases`, not on the markup — the page is only the trigger.
 *
 * Every case here loads a page whose deepest crumb is data and one whose crumbs are
 * interface, because a fix that stopped translating *everything* would take the site back
 * to the half-translated breadcrumbs of §4 and no phrase-table assertion would notice.
 */
class BreadcrumbDataLabelsSmokeTest extends SmokeTestCase
{
    /**
     * One request per entity kind whose navigation branch is one page per row.
     *
     * @return array<string, array{0: string, 1: string}> [path, the label that must not be filed]
     */
    public static function dataLabelledPages(): array
    {
        return [
            'publication title' => [
                '/it/SL200001L/im-aufwind-der-geschichte-das-babylonische-exil-is',
                'Im Aufwind der Geschichte : das babylonische Exil Israels'
                    . ' - ein Hoffnungszeichen auch für Christen / Rudolf Ammann',
            ],
            'composition name' => ['/it/SL500001C/obrigado', 'Obrigado'],
            'shrine name'      => [
                '/it/SL100458A/schoenstatt-shrine-mont-sion-gikungu',
                'Santuario di Schoenstatt Mont Sion Gikungu',
            ],
        ];
    }

    #[DataProvider('dataLabelledPages')]
    public function testARecordNameNeverBecomesAPhrase(string $path, string $label): void
    {
        $before = $this->phraseIdsFor($label);

        $response = $this->get($path);
        self::assertSame(200, $response['status'], $path . ' did not render, so this proves nothing');

        //the label has to actually appear as the deepest crumb, or the page changed and the
        //test is watching a phrase nothing tries to translate any more
        self::assertStringContainsString(
            $label,
            $this->deepestCrumb($response['body']),
            sprintf('%s no longer shows %s as its deepest breadcrumb', $path, $label)
        );

        self::assertSame(
            $before,
            $this->phraseIdsFor($label),
            sprintf(
                'rendering %s filed "%s" as a phrase. A breadcrumb label that is record data must '
                . 'reach the page untranslated — Application\Module::markDataLabels() flags those '
                . 'navigation pages and partial/breadcrumbs.phtml honours the flag. One row per '
                . 'record, in two text domains, is what this costs.',
                $path,
                $label
            )
        );
    }

    /**
     * An interface label in the same trail is still translated.
     *
     * Rendered rather than looked up in the database: this is the §4 regression, and what went
     * wrong there was visible on the page and nowhere else.
     */
    public function testAnInterfaceLabelInTheSameTrailIsStillTranslated(): void
    {
        $response = $this->get('/it/SL500001C/obrigado');
        self::assertSame(200, $response['status']);

        self::assertMatchesRegularExpression(
            '#<ol class="breadcrumb">.*?>Musica<#s',
            $response['body'],
            'the "Music" crumb is not rendering in Italian. Its label comes from the navigation '
            . 'config, so it is interface text and must keep being translated — the same word '
            . 'appears translated in the menu directly above it.'
        );
    }

    /** The text of the last <li> of the breadcrumb list, tags and whitespace removed. */
    private function deepestCrumb(string $body): string
    {
        if (! preg_match('#<ol class="breadcrumb">(.*?)</ol>#s', $body, $list)) {
            self::fail('the page rendered no breadcrumb trail at all');
        }
        if (! preg_match('#<li[^>]*>(?:(?!</li>).)*</li>\s*$#s', trim($list[1]), $crumb)) {
            self::fail('the breadcrumb trail has no closing crumb');
        }

        return trim(strip_tags($crumb[0]));
    }

    /**
     * The ids of every phrase whose text is exactly this label, retired or not.
     *
     * Compared as a set rather than counted so that a row arriving in one domain while another
     * is retired cannot cancel out.
     *
     * @return list<string>
     */
    private function phraseIdsFor(string $label): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT translation_phrase_id FROM trans_phrases WHERE phrase = ? ORDER BY translation_phrase_id'
        );
        $statement->execute([$label]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function pdo(): PDO
    {
        return new PDO(
            'mysql:host=db;dbname=ourlink_db1;charset=utf8mb4',
            'schoenstatt',
            'schoenstatt',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}
