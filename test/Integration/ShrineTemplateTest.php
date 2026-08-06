<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Http\CspNonce;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use App\Twig\TwigFactory;
use Laminas\Db\Adapter\Adapter;
use Locale;
use PHPUnit\Framework\TestCase;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Service\AssociationKindsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;
use Twig\Environment;

use function is_readable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Renders the shrine templates against real database rows, with no HTTP.
 *
 * The smoke suite covers what an anonymous visitor gets end to end. What it cannot
 * reach is the *moderator* table — that markup only renders for someone allowed to
 * edit an association, and the suite has no such session — so it is rendered here
 * directly instead. That matters more than it sounds: the Twig environment runs with
 * `strict_variables` on, so a column that is absent rather than null is an exception
 * and not a blank cell, and the moderator table reads six columns the public one
 * never touches.
 *
 * The environment is built through App\Twig\TwigFactory, the same call App\Kernel
 * makes, so this is the real thing and not a lookalike.
 *
 * \Locale::setDefault() first, for the reason App\Http\LocaleListener exists: under
 * the CLI SAPI the default is en_US_POSIX and every `nameByLocale[$locale]` lookup
 * in the table and the templates would miss.
 */
class ShrineTemplateTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    public static function setUpBeforeClass(): void
    {
        Locale::setDefault('en_US');
    }

    /**
     * Everything here reads the shrines table, so a machine without a database
     * skips rather than fails. The local-config check comes *first* and on purpose:
     * without config/autoload/local.php, merely asking the container for the
     * adapter raises "Undefined array key db" — and `failOnWarning` is on, so a
     * warning is a failure no later catch can undo.
     */
    protected function setUp(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            /** @var Adapter $adapter */
            $adapter = $this->bridge()->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
            $this->bridge()->get(SchoenstattTable::class);
        } catch (Throwable $e) {
            self::markTestSkipped(
                'no reachable database: ' . $e->getMessage()
                . ' — this test needs the capsule up (docker compose up -d)'
            );
        }
    }

    /**
     * Both tables against both indexes. The wayside cases are not padding: the
     * `sch-wayside-shrine` rows come through the same projection but are a different
     * 43 rows, and under `strict_variables` a column that is absent on one of them
     * and present on every shrine would be an exception rather than a blank cell.
     *
     * @return array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function tableTemplateProvider(): array
    {
        $public    = ['<th>Association</th>', '<th>Kind</th>', '<th>Primary contact</th>'];
        $moderator = ['<th>Shrine</th>', '<th>Photo?</th>', '<th>Name translated?</th>'];

        return [
            'public table, shrines'            => ['schoenstatt/_associations-table.html.twig', 'getShrines', $public],
            'moderator table, shrines'         => ['schoenstatt/_shrines-table.html.twig', 'getShrines', $moderator],
            'public table, wayside shrines'    => [
                'schoenstatt/_associations-table.html.twig',
                'getWaysideShrines',
                $public,
            ],
            'moderator table, wayside shrines' => [
                'schoenstatt/_shrines-table.html.twig',
                'getWaysideShrines',
                $moderator,
            ],
        ];
    }

    /**
     * @param list<string> $expectedHeaders
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tableTemplateProvider')]
    public function testEachShrineTableRendersEveryRow(
        string $template,
        string $tableMethod,
        array $expectedHeaders
    ): void {
        $shrines = $this->shrines($tableMethod);

        $html = $this->twig()->render($template, [
            'objects'     => $shrines,
            'kind_labels' => $this->kindLabels(),
        ]);

        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString($header, $html);
        }
        $this->assertSame(
            count($shrines) + 1,
            substr_count($html, '<tr>'),
            'one row per shrine plus the header row'
        );
        $this->assertStringNotContainsString('Undefined', $html);
    }

    /**
     * Each shrine is linked by identifier and locale slug, which is the one thing
     * the templates cannot get from a laminas view helper: FormatAssociation ends in
     * `$this->view->url()` and needs an MvcEvent. The link therefore goes through
     * App\Laminas\RouteUrl, and this is the assertion that it produces the same URL.
     */
    public function testShrinesAreLinkedByIdentifierAndLocaleSlug(): void
    {
        $shrines = $this->shrines();
        $first   = reset($shrines);
        $this->assertIsArray($first);

        $html = $this->twig()->render('schoenstatt/_associations-table.html.twig', [
            'objects'     => $shrines,
            'kind_labels' => $this->kindLabels(),
        ]);

        $this->assertStringContainsString(
            sprintf('href="/en/%s/%s"', $first['identifier'], $first['slugByLocale']['en_US']),
            $html
        );
        $this->assertStringContainsString($first['nameByLocale']['en_US'], $html);
    }

    /**
     * Twig autoescapes and the .phtml it replaces did not, so the formatter functions
     * are declared `is_safe: html` — an escaping mistake there shows up as visible
     * `&lt;span` in the output rather than as a security hole. The flag markup a
     * laminas view helper produced is the shortest thing that would break.
     */
    public function testHelperMarkupIsNotDoubleEscaped(): void
    {
        $html = $this->twig()->render('schoenstatt/_associations-table.html.twig', [
            'objects'     => $this->shrines(),
            'kind_labels' => $this->kindLabels(),
        ]);

        $this->assertStringContainsString('<span class="flag-icon', $html);
        $this->assertStringNotContainsString('&lt;span class=', $html);
        $this->assertStringNotContainsString('&amp;nbsp;', $html);
        $this->assertStringNotContainsString('&amp;#x', $html, 'an already-escaped entity got escaped again');
    }

    /** @return array<int|string, array<string, mixed>> */
    private function shrines(string $tableMethod = 'getShrines'): array
    {
        /** @var SchoenstattTable $table */
        $table = $this->bridge()->get(SchoenstattTable::class);
        $shrines = $table->$tableMethod();
        $this->assertNotEmpty($shrines, "no rows from $tableMethod — this test would prove nothing");

        return $shrines;
    }

    /** @return array<string, string> */
    private function kindLabels(): array
    {
        /** @var AssociationKindsService $kinds */
        $kinds = $this->bridge()->get(AssociationKindsService::class);

        return $kinds->getValueOptions();
    }

    private function twig(): Environment
    {
        $requests = new RequestStack();
        $request  = Request::create('/en/shrines');
        $request->attributes->set('_route', 'shrines.locale');
        $request->attributes->set('_locale', 'en');
        $requests->push($request);

        return (new TwigFactory())->create(
            $this->bridge(),
            new ViewHelpers($this->bridge()),
            new RouteUrl($this->bridge(), ''),
            $requests,
            new CspNonce()
        );
    }

    /** Config caches off, for the reason CacheStatusParityTest states: no test may write data/config/. */
    private function bridge(): ServiceBridge
    {
        if (null !== self::$bridge) {
            return self::$bridge;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        return self::$bridge = new ServiceBridge($appConfig);
    }
}
