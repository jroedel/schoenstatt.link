<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

use function implode;
use function preg_match;
use function strlen;

/**
 * The reading surface — the ten routes ported to the Symfony kernel on 2026-08-12: the
 * four entity show pages, the comment route three of them need, the three literature
 * browse pages and the three pre-2020 redirects.
 *
 * **The real verification of this batch is not here**, for the reason
 * Batch4SymfonySmokeTest gives: these pages were diffed against their laminas renderings
 * across all five locales and both identities with `tools/port-baseline.php`, which is
 * the only check that sees a wrong translation or a panel that stopped rendering. Six
 * defects came out of that diff and none of them would have failed a status-code test.
 * This file is the CI-side net, and every assertion in it is one of those six turned into
 * a regression guard.
 */
class ReadingSurfaceSmokeTest extends SmokeTestCase
{
    /**
     * The discriminator between the two front controllers, from ShrinesSymfonySmokeTest:
     * laminas sends `Set-Cookie: slm_locale=en_US` on every response because SlmLocale's
     * cookie strategy sets it at MvcEvent::FINISH, and a ported route never runs
     * SlmLocale. A locale cookie coming back means the request went through
     * App\Http\LegacyBridge and every other assertion is measuring the laminas page.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function portedPaths(): iterable
    {
        yield 'association (shrine)' => ['/en/SL100319A', 'Original Schoenstatt Shrine'];
        yield 'composition'          => ['/en/SL500001C', 'Obrigado'];
        yield 'publication'          => ['/en/SL202186L', 'Publication Info'];
        yield 'literature home'      => ['/en/literature', 'Schoenstatt Literature Tools'];
        yield 'literature index'     => ['/en/literature/es', 'Schoenstatt Literature in Spanish'];
        yield 'literature search'    => ['/en/literature/search', 'Publications search'];
    }

    #[DataProvider('portedPaths')]
    public function testEachPortedPathIsServedBySymfony(string $path, string $marker): void
    {
        $response = $this->get($path);

        self::assertSame(200, $response['status'], "$path did not answer 200");
        self::assertStringContainsString($marker, $response['body'], "$path is missing its own content");
        //`slm_locale=en_US`, not bare `slm_locale`: App\Http\GdprCookieListener *deletes*
        //that cookie on a Symfony-served response, so the header carries
        //`slm_locale=deleted` and a substring test on the name alone fails on every page
        //it is meant to pass.
        self::assertStringNotContainsString(
            'slm_locale=en_US',
            implode("\n", $response['headers']),
            "$path came back with SlmLocale's cookie, so laminas served it through the bridge"
        );
    }

    /**
     * `/literature/{lang}` for every language the catalogue has, because the page took
     * itself down once and only one language was being watched.
     *
     * A breadcrumb leaf was passed without an `href`, which the layout reads
     * unconditionally; Twig's strict_variables turned that into a RuntimeError and every
     * language page became an empty 200 — the fatal-200 signature. A single-language check
     * would have caught it, and a single-language check is what nearly missed it: the
     * manual probe before the breadcrumb was added had passed.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function catalogueLanguages(): iterable
    {
        foreach (['de', 'es', 'en', 'pt', 'it'] as $language) {
            yield $language => ["/en/literature/$language"];
        }
    }

    #[DataProvider('catalogueLanguages')]
    public function testEachLanguageCatalogueRendersARealPage(string $path): void
    {
        $response = $this->get($path);

        self::assertSame(200, $response['status']);
        //the fatal-200 wedge is an ~800-byte body; a catalogue is tens of kilobytes
        self::assertGreaterThan(
            5000,
            strlen($response['body']),
            "$path answered 200 with almost no body, which is what a template error looks like"
        );
    }

    /**
     * The three pre-2020 redirects, each a **301** to the site-wide identifier form, and
     * each taking SlmLocale's locale hop first.
     *
     * Both halves have been wrong here. The association pair 302'd to the index because
     * the slug was read from `slug` where an association keeps `slugByLocale`, which looks
     * exactly like a row that does not exist; and all three skipped the locale hop, on the
     * reasonable-sounding grounds that a redirect to a redirect is wasted.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function redirects(): iterable
    {
        yield 'publication by id'    => ['/en/literature/1', '/en/SL200001L/'];
        yield 'association by id'    => ['/en/associations/1', '/en/SL100001A/'];
        yield 'association by sw_id' => ['/en/associations/SL100001A', '/en/SL100001A/'];
    }

    #[DataProvider('redirects')]
    public function testEachLegacyUrlIsPermanentlyRedirected(string $path, string $target): void
    {
        $response = $this->get($path);

        self::assertSame(301, $response['status'], "$path must be a permanent redirect, not a temporary one");
        self::assertStringContainsString($target, $response['redirect']);
    }

    /** The bare form takes the locale hop first, as SlmLocale does on the laminas side. */
    public function testTheBareFormOfARedirectGoesToTheLocalePrefixedOneFirst(): void
    {
        $response = $this->get('/associations/1');

        self::assertSame(302, $response['status']);
        self::assertStringContainsString('/en/associations/1', $response['redirect']);
    }

    /**
     * A merged publication sends an anonymous visitor to the surviving edition, and shows
     * the merged row itself to anyone holding `publication_user`. The 301 is the visitor's
     * only route to the real record.
     */
    public function testAMergedPublicationRedirectsToItsSurvivingEdition(): void
    {
        $response = $this->get('/en/SL200417L');

        self::assertSame(301, $response['status']);
        self::assertStringContainsString('/en/SL207340L/', $response['redirect']);
    }

    /**
     * A GET on the comment route **falls through to laminas**, which is a stronger
     * reproduction than the 405 this was first written to expect.
     *
     * Declaring the Symfony route POST-only does not make a GET a 405: the catch-all
     * `legacy` route matches every path, so Symfony's UrlMatcher finds *a* route and never
     * raises MethodNotAllowed. The GET therefore reaches App\Http\LegacyBridge and gets
     * exactly what it got before the port — a 302 to sign-in for an anonymous visitor, and
     * for a signed-in one the same 500 the missing `sion-model/comment/create` template has
     * always produced.
     *
     * That is the right answer and it is worth pinning, because it is easy to "fix" into a
     * 405 by moving the route below the catch-all or adding a method-not-allowed twin, and
     * either would change behaviour a caller may depend on.
     */
    public function testAGetOnTheCommentRouteStillReachesLaminas(): void
    {
        $response = $this->get('/en/comments/create/composition/1');

        self::assertSame(302, $response['status'], 'an anonymous GET is the guard redirect, as on laminas');
        self::assertStringContainsString('/user/login', $response['redirect']);
    }

    /**
     * The restricted show page refuses an anonymous visitor by sending them to sign in —
     * the guard branch that proves App\Authorization\RouteGuard runs on a route whose
     * controller would otherwise have rendered a page.
     */
    public function testTheTextShowPageIsGuarded(): void
    {
        $response = $this->get('/en/SL400003T');

        self::assertSame(302, $response['status']);
        self::assertStringContainsString('/user/login', $response['redirect']);
    }

    /**
     * An anonymous visitor may read a shrine and not an institute, and that rule is in
     * `AssociationsController::showAction()` rather than in the ACL — no guard entry
     * hints at it and `tools/acl-table.php` cannot see it. Both halves are asserted
     * because either alone passes against a page that got the rule backwards.
     */
    public function testOnlyShrinesAreReadableAnonymously(): void
    {
        self::assertSame(200, $this->get('/en/SL100319A')['status'], 'a shrine is public');

        $institute = $this->get('/en/SL100001A');
        self::assertSame(302, $institute['status'], 'a non-shrine association is not');
        self::assertStringContainsString('/en/', $institute['redirect']);
    }

    /**
     * The comment form's `redirect` field carries the page the visitor is on.
     *
     * Empty, every posted comment falls through to App\Controller\CommentCreateController's
     * referer fallback — a different URL whenever the visitor arrived from anywhere but the
     * page itself. It was empty, and the symptom was thirteen missing bytes in a hidden
     * input on a page that otherwise rendered perfectly.
     *
     * Anonymous visitors get no form (`route/comments/create` is guarded `user`), so this
     * asserts the *absence* for them and leaves the populated case to the parity test.
     */
    public function testTheCommentFormIsNotOfferedAnonymously(): void
    {
        $body = $this->get('/en/SL500001C')['body'];

        self::assertStringNotContainsString('commentCreatePanel', $body);
    }

    /**
     * The publication page's field labels are translated, which they were not.
     *
     * `Books\View\Helper\FormatField` translates through the *view* translator, whose text
     * domain JTranslate's dispatch listener sets from the controller's namespace and which
     * nothing set on a Symfony route. The lookup landed in `default`, missed, and returned
     * the English source — invisible on `/en/…`, where the source *is* the English text,
     * and wrong on every other locale. This is the check that only a non-English page can
     * make.
     */
    public function testPublicationFieldLabelsAreTranslated(): void
    {
        $body = $this->get('/es/SL202186L')['body'];

        self::assertStringContainsString('Número de páginas', $body);
        self::assertStringNotContainsString('Number of pages', $body);
    }

    /**
     * The catalogue rows name their language — "Catálogo de livros em Francês", not
     * "…em fr".
     *
     * The page passes `languages` (the ISO-639 map) and `layout.html.twig` set a variable
     * of the same name for the locale chooser. A `{% set %}` at the top level of a layout
     * is in scope when `{% block content %}` is called from it, so the layout's five
     * options shadowed the map, every key missed, and the template's own `is defined`
     * fallback printed the raw code. It reached production, and the page looked *almost*
     * right — the pattern around it was translated, only the noun was not.
     *
     * Portuguese, because English cannot see this: the fallback prints `fr` where English
     * prints "French", and both are plausible until you know which one you asked for.
     */
    public function testTheCatalogueRowsNameTheirLanguageRatherThanItsCode(): void
    {
        $body = $this->get('/pt/literature')['body'];

        self::assertStringContainsString('Catálogo de livros em Francês', $body);
        self::assertStringNotContainsString('Catálogo de livros em fr', $body);
    }

    /**
     * The search box's placeholder is translated.
     *
     * `Laminas\Form\View\Helper\AbstractHelper::translateHtmlAttributeValue()` translates
     * `placeholder` and `title` on every element a form view helper renders;
     * App\Form\BootstrapFormRenderer did not, so the box read "Search Catalogs" in all
     * five locales against laminas' "Busca nos catálogos". It is asserted here on the
     * *escaped* form, because laminas-escaper writes the spaces as `&#x20;` and a test
     * against the plain string passes for the wrong reason on a page that also contains
     * the phrase in prose.
     */
    public function testTheSearchPlaceholderIsTranslated(): void
    {
        $body = $this->get('/pt/literature')['body'];

        self::assertStringContainsString('placeholder="Busca&#x20;nos&#x20;cat&#xE1;logos"', $body);
        self::assertStringNotContainsString('placeholder="Search', $body);
    }

    /**
     * The middle crumb, restored 2026-08-13 — the one thing the ported show pages were
     * still missing against laminas.
     *
     * A publication sits under its language catalogue and a shrine under its region;
     * laminas gets both from the Navigation service, and these pages state them from the
     * record instead. Deriving them would mean unserializing the 2.24 MB `publication-pages`
     * branch on every show page to learn a string the entity already holds — see
     * App\View\NavigationTree's docblock on who may walk the tree.
     *
     * Asserted as an ordered trail rather than as "contains the label", because the whole
     * point is the crumb's *position*: a label appearing anywhere on the page would pass a
     * substring test while the trail stayed two crumbs long.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function trailsWithAMiddleCrumb(): iterable
    {
        yield 'publication under its language catalogue' => [
            '/en/SL202154L/les-annees-cachees-pere-joseph-kentenich-enfance-e',
            '#<ol class="breadcrumb">.*?>\s*Literature\s*<.*?>\s*Schoenstatt Literature in French\s*<'
                //the leaf's own text, matched on its ASCII head: Twig escapes markup, not
                //accented letters, so the body carries "Les années cachées" verbatim
                . '.*?Les ann.*?es cach.*?es.*?</ol>#s',
        ];
        yield 'shrine under its region' => [
            '/en/SL100319A/original-schoenstatt-shrine',
            '#<ol class="breadcrumb">.*?>\s*Shrines\s*<.*?>\s*Europe\s*<'
                . '.*?>\s*Original Schoenstatt Shrine\s*<.*?</ol>#s',
        ];
    }

    #[DataProvider('trailsWithAMiddleCrumb')]
    public function testTheTrailNamesTheIndexTheRecordBelongsTo(string $path, string $pattern): void
    {
        self::assertSame(
            1,
            preg_match($pattern, $this->get($path)['body']),
            "$path is missing its middle breadcrumb, or has it in the wrong place"
        );
    }

    /**
     * The language crumb links where it says, and says what the page it links to says.
     *
     * `PublicationController::catalogueTitle()` reproduces
     * `LiteratureController::indexTitle()` rather than sharing it, so the two can drift —
     * this is what would notice. The crumb reading "Schoenstatt Literature in French" while
     * the catalogue heading reads something else is the exact failure it guards.
     */
    public function testTheLanguageCrumbAgreesWithTheCatalogueItLinksTo(): void
    {
        $body = $this->get('/en/SL202154L/les-annees-cachees-pere-joseph-kentenich-enfance-e')['body'];

        self::assertSame(
            1,
            preg_match('#<a href="(/en/literature/fr)">\s*([^<]+?)\s*</a>#', $body, $crumb),
            'the publication trail carries no link to a language catalogue'
        );

        $catalogue = $this->get($crumb[1])['body'];
        self::assertStringContainsString(
            '<h2>' . $crumb[2] . '</h2>',
            $catalogue,
            'the breadcrumb and the page it points at disagree about the catalogue\'s name'
        );
    }

    /**
     * A composition's breadcrumb is `Music > <name>`, with the name **not** translated.
     *
     * The trail was absent entirely at first, which
     * test/Smoke/BreadcrumbDataLabelsSmokeTest caught — it asserts against `trans_phrases`
     * rather than the markup, because a translated record name reads correctly and files a
     * phrase per row. This asserts the shape; that test asserts the cost.
     */
    public function testACompositionBreadcrumbNamesTheMusicIndexAndTheSong(): void
    {
        $body = $this->get('/en/SL500001C/obrigado')['body'];

        self::assertSame(
            1,
            preg_match('#<ol class="breadcrumb">.*?>\s*Music\s*<.*?>\s*Obrigado\s*<.*?</ol>#s', $body),
            'the composition breadcrumb should read Music > Obrigado'
        );
    }
}
