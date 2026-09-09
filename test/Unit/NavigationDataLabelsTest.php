<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use Application\Module;
use Application\Navigation\PageBuilder;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../module/Application/src/Navigation/PageBuilder.php';
require_once __DIR__ . '/../../module/Application/src/Module.php';

/**
 * Which navigation labels are record content, decided away from the request.
 *
 * The navigation carries one page per publication, composition, library and association,
 * labelled with the row's own title or name. Nothing may translate those: a translator miss
 * is how a phrase is filed, so translating them files one row per record — which is what
 * happened between 2026-08-10 and -11, when publication titles reached 61% of the phrase
 * table (`docs/api-v3.md` §12).
 *
 * `markDataLabels()` is the whole of that decision, and it is deliberately static and pure so
 * it can be checked here rather than only through a rendered page — the mistake it prevents
 * is not visible in the markup, only in a table nobody reads until an agent asks what is left
 * to translate. The rule lives in `Application\Navigation\PageBuilder` since the builders were
 * extracted for the Symfony side to share; `Application\Module::markDataLabels()` delegates to
 * it and is still the name the breadcrumb partial and this test use. Both files are required
 * directly: neither touches a Laminas class at class level — the `use` statements resolve only
 * when a method that needs one is called, and none of these do — so this stays in the
 * vendor-free unit suite.
 */
class NavigationDataLabelsTest extends TestCase
{
    /** The branches as onBootstrap() holds them, one page each, nested where the real ones are. */
    private function branches(): array
    {
        return [
            'dictionary-pages'  => [['label' => 'German to Spanish Dictionary', 'route' => 'dictionary/inLanguage']],
            'publication-pages' => [[
                'label' => 'German Schoenstatt Literature',
                'route' => 'publications/index',
                'pages' => [['label' => 'Das Ehe-Ideal', 'route' => 'publication']],
            ]],
            'library-pages'     => [['label' => 'Schoenstatt Fathers Library', 'route' => 'libraries/library']],
            'music-pages'       => [['label' => 'Obrigado', 'route' => 'composition']],
            'association-pages' => [
                'movement'        => [['label' => 'Schoenstatt Fathers', 'route' => 'association']],
                'shrinesByRegion' => [
                    'Africa' => [
                        'label' => 'Africa',
                        'route' => 'shrines',
                        'pages' => [['label' => 'Santuario Mont Sion Gikungu', 'route' => 'association']],
                    ],
                ],
                'shrinesWorld'    => [['label' => 'Original Shrine', 'route' => 'association']],
                'waysideShrines'  => [['label' => 'Wayside shrine Hörde', 'route' => 'association']],
            ],
        ];
    }

    public function testEveryLabelBuiltFromARecordIsFlagged(): void
    {
        $marked = Module::markDataLabels($this->branches());

        $flagged = [];
        $unflagged = [];
        self::walk($marked, $flagged, $unflagged);

        self::assertSame(
            [
                'Africa',
            ],
            $unflagged,
            'a label built from a database row was left translatable. Each one costs a phrase row '
            . 'per record, in two text domains, the first time an Italian page renders it.'
        );
        self::assertContains('Das Ehe-Ideal', $flagged, 'a nested publication title was missed');
        self::assertContains('Santuario Mont Sion Gikungu', $flagged, 'a nested shrine name was missed');
    }

    /**
     * The region level stays translatable, and that is not an oversight.
     *
     * 'Africa', 'Germany', 'Argentina' are country and region names with real translations in
     * all five languages. A fix that flagged the whole association branch would take those
     * back to English and nothing would fail.
     */
    public function testARegionKeepsItsTranslation(): void
    {
        $marked = Module::markDataLabels($this->branches());
        $region = $marked['association-pages']['shrinesByRegion']['Africa'];

        self::assertArrayNotHasKey(
            Module::LABEL_IS_DATA,
            $region,
            'the region level of shrinesByRegion is interface text: its labels are country and '
            . 'region names, translated on purpose in all five languages'
        );
        self::assertTrue(
            $region['pages'][0][Module::LABEL_IS_DATA],
            'the shrines under a region are data even though the region above them is not'
        );
    }

    /**
     * A branch the cache did not hand over is skipped rather than fatal.
     *
     * onBootstrap() runs before anything can report an error, and a cache miss on one branch is
     * routine on a full APCu segment, so a missing key must not be an exception there.
     */
    /**
     * The delegation itself, because the alias is what every existing caller uses.
     *
     * partial/breadcrumbs.phtml reads `Application\Module::LABEL_IS_DATA` and the smoke test
     * names `Module::markDataLabels()`; if the delegate were dropped the flag would silently
     * stop being set and one row per record would start arriving again.
     */
    public function testModuleStillDelegatesToTheBuilder(): void
    {
        self::assertSame(PageBuilder::LABEL_IS_DATA, Module::LABEL_IS_DATA);
        self::assertSame(
            PageBuilder::markDataLabels($this->branches()),
            Module::markDataLabels($this->branches())
        );
    }

    public function testAMissingOrEmptyBranchIsTolerated(): void
    {
        self::assertSame([], Module::markDataLabels([]));
        self::assertSame(
            ['association-pages' => ['movement' => []]],
            Module::markDataLabels(['association-pages' => ['movement' => []]])
        );
        self::assertSame(
            ['publication-pages' => 'nonsense'],
            Module::markDataLabels(['publication-pages' => 'nonsense']),
            'a cache item of the wrong shape must not stop the site booting'
        );
    }

    /**
     * Collect every label in the structure, split by whether it carries the flag.
     *
     * @param list<string> $flagged
     * @param list<string> $unflagged
     */
    private static function walk(array $node, array &$flagged, array &$unflagged): void
    {
        if (isset($node['label']) && is_string($node['label'])) {
            if (! empty($node[Module::LABEL_IS_DATA])) {
                $flagged[] = $node['label'];
            } else {
                $unflagged[] = $node['label'];
            }
        }
        foreach ($node as $key => $value) {
            if (is_array($value) && Module::LABEL_IS_DATA !== $key) {
                self::walk($value, $flagged, $unflagged);
            }
        }
    }
}
