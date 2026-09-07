<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use JTranslate\Controller\PhraseDeleteController;
use JTranslate\Controller\PhraseEditController;
use JTranslate\Controller\PhraseIndexController;
use JTranslate\Routing\RouteAudience;
use JTranslate\Routing\Routes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `module/JTranslate/config/symfony-routes.php` — the three routes the module asks a host
 * to declare, and the facts about them nothing else checks.
 *
 * The fragment is a closure that calls back into the host's own route registration rather
 * than a `RouteCollection`, so it has no shape a type can pin: what it declares is only
 * visible by *running* it. This drives it with a recording callback, which doubles as the
 * cheapest demonstration of how a host consumes it. The JUser twin of this file is
 * {@see JUserRouteFragmentTest}, and the two are deliberately alike.
 *
 * What each test is for:
 *
 * - **the names**, because a route name is what a host's guard entry addresses, what
 *   `App\Schoenstatt\AdminIndex` links to, and what `docs/acl-baseline.json` snapshots. A
 *   literal in the fragment that drifts from `JTranslate\Routing\Routes` is a dead link on
 *   one side and an unguarded page on the other, and neither end fails.
 * - **the audience**, because a route that loses its guard keeps working. This surface
 *   publishes every phrase of the project — including free text a moderator wrote as a
 *   per-locale description — and its delete route destroys every translation of a phrase.
 * - **the `phrase_id` constraint**, because it is the one place the fragment deliberately
 *   does *not* reproduce the laminas route it replaces: `[0-9]+` here against
 *   `[0-9]{1,5}` there. Pinned so that "widened on purpose" cannot quietly become
 *   "narrowed back by someone tidying".
 */
final class JTranslateRouteFragmentTest extends TestCase
{
    /**
     * @return list<array{string, string, string|array<int, string>, RouteAudience,
     *     array<string, mixed>, array<string, string>}>
     */
    private function declared(): array
    {
        $fragment = require __DIR__ . '/../../module/JTranslate/config/symfony-routes.php';

        $declared = [];
        $fragment(function (
            string $name,
            string $path,
            string|array $controller,
            RouteAudience $audience,
            array $defaults = [],
            array $requirements = []
        ) use (&$declared): void {
            $declared[] = [$name, $path, $controller, $audience, $defaults, $requirements];
        });

        return $declared;
    }

    public function testItDeclaresThreeRoutesInOrder(): void
    {
        $this->assertSame(
            [Routes::INDEX, Routes::PHRASE_EDIT, Routes::PHRASE_DELETE],
            array_column($this->declared(), 0)
        );
    }

    public function testEveryPathAndControllerIsWhatItShouldBe(): void
    {
        $byName = [];
        foreach ($this->declared() as [$name, $path, $controller]) {
            $byName[$name] = [$path, $controller];
        }

        $this->assertSame(['/admin/translations', PhraseIndexController::class], $byName[Routes::INDEX]);
        $this->assertSame(
            ['/admin/translations/{phrase_id}/edit', PhraseEditController::class],
            $byName[Routes::PHRASE_EDIT]
        );
        $this->assertSame(
            ['/admin/translations/{phrase_id}/delete', PhraseDeleteController::class],
            $byName[Routes::PHRASE_DELETE]
        );
    }

    /**
     * All three are `Translator`, including the listing.
     *
     * Read-only is not a lesser audience here: the worklist shows every phrase this project
     * owns, and on this site some of those are per-locale descriptions a moderator wrote
     * rather than UI strings — see docs/BACKLOG.md on why the phrase table is private to
     * its project.
     */
    public function testEveryRouteIsForATranslator(): void
    {
        foreach ($this->declared() as [$name, , , $audience]) {
            $this->assertSame(RouteAudience::Translator, $audience, $name);
        }
    }

    /**
     * `phrase_id` is `[0-9]+` on both routes that take one, and nothing else is constrained.
     *
     * The widening is the point: ids are past 14,000 and climb every time a page renders a
     * string nobody has recorded, so `[0-9]{1,5}` would eventually stop matching a phrase
     * whose pencil link the listing still renders — a 404 on one row while every other row
     * works.
     */
    public function testPhraseIdIsWidenedAndEveryParameterIsConstrained(): void
    {
        $expected = [
            Routes::PHRASE_EDIT   => ['phrase_id' => '[0-9]+'],
            Routes::PHRASE_DELETE => ['phrase_id' => '[0-9]+'],
        ];

        foreach ($this->declared() as [$name, $path, , , $defaults, $requirements]) {
            $this->assertSame($expected[$name] ?? [], $requirements, $name);
            $this->assertSame([], $defaults, $name . ' needs no route default');

            //and the converse: a path with a placeholder must appear above
            if (str_contains($path, '{')) {
                $this->assertArrayHasKey($name, $expected, $name . ' has parameters but no constraints');
            }
        }
    }

    /**
     * The listing is declared before the two paths that extend it.
     *
     * Belt and braces — the segments after `{phrase_id}` are literal, so no matcher could
     * confuse them — but a matcher that takes the first match should not depend on that.
     */
    public function testTheIndexIsDeclaredFirst(): void
    {
        $paths = array_column($this->declared(), 1);

        $this->assertSame('/admin/translations', $paths[0]);
        foreach (array_slice($paths, 1) as $path) {
            $this->assertStringStartsWith('/admin/translations/', $path);
        }
    }

    /** Nothing in the fragment names a route with a literal string. */
    public function testEveryDeclaredNameIsAConstant(): void
    {
        $constants = (new ReflectionClass(Routes::class))->getConstants();

        foreach (array_column($this->declared(), 0) as $name) {
            $this->assertContains($name, $constants, $name . ' is not declared in JTranslate\Routing\Routes');
        }
        $this->assertCount(3, $constants, 'a constant with no route, or a route with no constant');
    }
}
