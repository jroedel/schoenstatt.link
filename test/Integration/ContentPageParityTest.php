<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Twig\MarkdownExtension;
use Books\View\Helper\Markdown;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file_get_contents;
use function is_file;
use function preg_match_all;
use function sort;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The five ported content pages say the same thing as the .phtml they replace.
 *
 * `/`, /developers, /acknowledgements, /privacy and /shrines/submitting-photos are
 * each a Markdown heredoc in a view script, and porting one means copying that
 * Markdown into a Twig template. Two copies of the site's privacy policy is a real
 * hazard while both front controllers are live — production serves the .phtml, the
 * capsule serves the .twig, and an edit to one is invisible in the other. Nothing but
 * this test would notice.
 *
 * It compares the **Markdown source**, not the rendered page: that is the thing that
 * was copied, so it is the thing whose divergence is a bug. Both sides then go through
 * the same ParsedownExtra, so equal source means equal HTML — asserted once here
 * rather than assumed, because the ported pages reach the parser through
 * App\Twig\MarkdownExtension while the laminas ones reach it through
 * Books\View\Helper\Markdown.
 *
 * When a page's laminas twin is finally deleted, delete its row from the provider —
 * that is the point at which the .twig becomes the only copy and there is no longer a
 * parity claim to make.
 */
class ContentPageParityTest extends TestCase
{
    private const APP_VIEWS = __DIR__ . '/../../module/Application/view/application/index/';
    private const SCH_VIEWS = __DIR__ . '/../../module/Schoenstatt/view/schoenstatt/schoenstatt/';
    private const TEMPLATES = __DIR__ . '/../../templates/content/';

    /**
     * Each case is one page: its Twig template, the .phtml it was copied from, and how
     * many Markdown bodies to expect on each side.
     *
     * submitting-photos is the only two-body page — it picks Spanish or English from
     * \Locale::getDefault() — and its Twig template declares them in the order the
     * `if` reaches them, Spanish first, which is the reverse of the .phtml's array. So
     * the comparison is order-insensitive: what matters is that the *set* of Markdown
     * bodies is the same, not which branch happens to be written first.
     *
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function pages(): array
    {
        return [
            'welcome'           => ['welcome.html.twig', self::APP_VIEWS . 'index.phtml', 1],
            'developers'        => ['developers.html.twig', self::APP_VIEWS . 'developers.phtml', 1],
            'acknowledgements'  => [
                'acknowledgements.html.twig',
                self::APP_VIEWS . 'acknowledgements.phtml',
                1,
            ],
            'privacy'           => ['privacy.html.twig', self::APP_VIEWS . 'privacy.phtml', 1],
            'submitting-photos' => [
                'submitting-photos.html.twig',
                self::SCH_VIEWS . 'submitting-photos.phtml',
                2,
            ],
        ];
    }

    #[DataProvider('pages')]
    public function testTheTwigTemplateCarriesTheSameMarkdownAsThePhtml(
        string $template,
        string $phtml,
        int $expectedBodies
    ): void {
        self::assertTrue(is_file(self::TEMPLATES . $template), "missing template $template");
        self::assertTrue(is_file($phtml), "missing view script $phtml");

        $fromPhtml = $this->heredocBodies((string) file_get_contents($phtml));
        $fromTwig  = $this->verbatimBodies((string) file_get_contents(self::TEMPLATES . $template));

        self::assertCount(
            $expectedBodies,
            $fromPhtml,
            sprintf('expected %d Markdown heredoc(s) in %s', $expectedBodies, $phtml)
        );
        self::assertCount(
            $expectedBodies,
            $fromTwig,
            sprintf('expected %d verbatim Markdown block(s) in %s', $expectedBodies, $template)
        );

        //sorted, so a two-branch page is compared as a set: see the provider's docblock
        $expected = $fromPhtml;
        $actual   = $fromTwig;
        sort($expected);
        sort($actual);

        self::assertSame(
            $expected,
            $actual,
            sprintf(
                '%s and %s no longer carry the same Markdown. Both are live — production '
                . 'serves the .phtml and the capsule serves the .twig — so edit both or '
                . 'neither.',
                $phtml,
                $template
            )
        );
    }

    /**
     * Equal source through the same parser is equal HTML, which is the step that makes
     * the source comparison above sufficient. Worth one assertion because the two
     * pages reach ParsedownExtra by different routes: App\Twig\MarkdownExtension
     * constructs it directly, Books\View\Helper\Markdown constructs it in a view
     * helper's constructor. Neither passes any options, and this is what pins that.
     */
    #[DataProvider('pages')]
    public function testTheSharedParserTurnsThatMarkdownIntoTheSameHtml(
        string $template,
        string $phtml,
        int $expectedBodies
    ): void {
        $bodies = $this->heredocBodies((string) file_get_contents($phtml));
        self::assertCount($expectedBodies, $bodies);

        $viaHelper    = new Markdown();
        $viaExtension = new MarkdownExtension();

        foreach ($bodies as $body) {
            self::assertSame(
                (string) $viaHelper->__invoke($body),
                $viaExtension->markdown($body),
                "the two Markdown entry points disagree for $template"
            );
        }
    }

    /** A defence against the extractors silently matching nothing and every case passing vacuously. */
    public function testTheExtractorsFindSomething(): void
    {
        $bodies = $this->heredocBodies((string) file_get_contents(self::APP_VIEWS . 'privacy.phtml'));
        self::assertCount(1, $bodies);
        self::assertStringContainsString('Schoenstatt Link Privacy Policy', $bodies[0]);

        $twig = $this->verbatimBodies((string) file_get_contents(self::TEMPLATES . 'privacy.html.twig'));
        self::assertCount(1, $twig);
        self::assertStringContainsString('Schoenstatt Link Privacy Policy', $twig[0]);
    }

    /**
     * Every `<<<MD` / `<<<EOT` body in a view script. Both markers are in use: the
     * Application pages picked EOT and the Schoenstatt one picked MD.
     *
     * @return list<string>
     */
    private function heredocBodies(string $source): array
    {
        //no \s* before the closing marker: it would swallow a trailing blank line that
        //the .twig verbatim block preserves, and three of these heredocs have one
        preg_match_all('/<<<(?:MD|EOT)\R(.*?)\R(?:MD|EOT)\b/s', $source, $matches);

        return array_map('strval', $matches[1]);
    }

    /**
     * Every `{% verbatim %}` body in a Twig template, which is where a ported content
     * page keeps its Markdown.
     *
     * @return list<string>
     */
    private function verbatimBodies(string $source): array
    {
        preg_match_all('/\{%\s*verbatim\s*%\}\R(.*?)\R\{%\s*endverbatim\s*%\}/s', $source, $matches);

        return array_map('strval', $matches[1]);
    }
}
