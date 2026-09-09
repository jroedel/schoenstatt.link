<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Http\CspNonce;
use App\Laminas\HostMessages;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use App\Twig\TwigFactory;
use JUser\Twig\JUserExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;
use Twig\Environment;

use function dirname;
use function file_get_contents;
use function preg_match_all;
use function preg_replace;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Every Twig template compiles.
 *
 * ## The failure this catches, and why nothing else does
 *
 * A Twig template that does not compile produces an **HTTP 200 with an empty body**. The
 * error is raised while the template is rendering, which is after the response headers
 * have gone out, so the visitor gets a blank page and the status line says everything is
 * fine. `docs/laminas-exit.md` calls this the fatal-200 wedge and records it from a Twig
 * syntax error in an earlier batch and from `CollectionFormFactory` reaching for the route
 * match in batch 7. This test is the check for the first of those. Measured against
 * deliberate breakage rather than assumed, because two of the four things one would expect
 * it to catch it does not:
 *
 * | breakage | caught? |
 * |---|---|
 * | syntax error (`form_row( }}`) | yes |
 * | unknown function | yes |
 * | unknown filter | yes |
 * | `extends` naming a template that is not there | **no** — the parent is resolved when the template renders, not when it loads |
 *
 * ## What it does *not* catch, and this is worth stating because it is why it exists
 *
 * It was written after wedging `templates/books/_collection-fields.html.twig` on
 * 2026-08-15, and **it would not have caught that**. The mechanism was a docblock
 * explaining the partial's own whitespace control and *spelling out* the comment-closing
 * sequence in the prose: Twig has no escape for a delimiter inside a comment, so the
 * comment ended in the middle of a sentence and the rest became template code. The page
 * went from 13,716 bytes to 0.
 *
 * The reason this test stays silent on it is exact and worth knowing: what the prose left
 * behind was a print tag wrapping an ellipsis character, and Twig lexes that as a
 * perfectly ordinary *variable name*. It compiles. It then dies at render under
 * `strict_variables` with `Variable "…" does not exist`. So the class of error is
 * runtime, not compile — the same distinction that keeps undefined variables out of
 * scope here generally.
 *
 * What caught it was the before/after byte capture the refactor was already running, which
 * is the honest answer: for a partial, "does it render" needs a form, a row and a request,
 * and reproducing those in a test is reproducing the controller. The lesson filed in
 * `strangler.md` is therefore about the capture and not about a new guard.
 *
 * The mistake is easy to make here because the house style is a long explanatory docblock
 * at the top of every template, and the thing most worth explaining about a partial is
 * its delimiters. Name them in prose without writing them.
 *
 * ## Why compile and not render
 *
 * Rendering needs a request, a route, a form, a database row — everything the page is
 * about — and a test that supplied all of it would be a second copy of the controller.
 * Compiling needs none of it: `Environment::load()` resolves every function, filter and
 * test name against the registered extensions and evaluates the generated class, which is
 * exactly the class of error that presents as a blank 200. An undefined *variable* is a
 * runtime concern and is deliberately out of scope here — `strict_variables` catches those
 * where they occur.
 *
 * The environment comes from `App\Twig\TwigFactory`, the same call `App\Kernel` makes, for
 * the reason `ShrineTemplateTest` gives: a differently-wired Twig would not know the same
 * function names, and unknown-function is half of what this test is for.
 */
final class TemplatesCompileTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    #[\PHPUnit\Framework\Attributes\DataProvider('templates')]
    public function testTheTemplateCompiles(string $name): void
    {
        $twig = self::twig();

        //load() compiles the source to PHP and evaluates the class. A syntax error, an
        //unknown function and an unknown filter all throw here; nothing about the page's
        //data is touched. A missing `extends` target does not — see the table above.
        $twig->load($name);

        $this->assertTrue(true, "$name compiles");
    }

    /**
     * Every `extends` names a template that exists.
     *
     * Separate from the compile check because `load()` genuinely does not catch it — the
     * table above is measured, not assumed: Twig resolves a parent when the template
     * *renders*, so a broken `extends` is a fatal-200 that compiles perfectly.
     *
     * Cheap before batch 9 and load-bearing after it. The nine create templates each
     * `extends` their edit twin — `books/book-create.html.twig` extends
     * `books/book-edit.html.twig` — because on laminas the two view scripts share one
     * fields-partial and take their assets from it, so inheriting is the faithful
     * arrangement. It also means a rename of any edit template silently breaks a create
     * page, and this is what makes that loud.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('templates')]
    public function testEveryExtendsTargetExists(string $name): void
    {
        $path   = self::pathOf($name);
        $source = file_get_contents($path);
        if (false === $source) {
            self::markTestSkipped("cannot read $name");
        }

        //Comments first: this project's templates name other templates in prose constantly,
        //and PortedFormsAreSubmittableTest already paid for forgetting that once.
        $markup = (string) preg_replace('/\{#.*?#\}/s', '', $source);

        preg_match_all("/\{%-?\s*extends\s+'([^']+)'/", $markup, $matches);

        foreach ($matches[1] as $parent) {
            $this->assertFileExists(
                self::pathOf($parent),
                "$name extends '$parent', which does not exist — the page will render as an empty 200"
            );
        }

        //JUser's templates extend `juser_layout`, a Twig *global*, so there is no literal
        //for the regex above to find and nothing here to assert. That indirection is checked
        //where it can be — JUserHostContractTest renders a child through it — and the
        //compile test above still loads every one of them.
        $this->assertTrue(true, "$name declares no unresolvable parent");
    }

    /**
     * Every template on disk, discovered rather than listed.
     *
     * A hardcoded list is the one thing this must not be: the failure mode is a template
     * somebody just wrote, and a list is exactly what a new template is missing from. The
     * same argument `test/Fuzz/FormRepository` makes for forms.
     *
     * Names are relative to `templates/` because that is what the FilesystemLoader is
     * rooted at and therefore what every `include` and `extends` in the codebase spells.
     *
     * **No container is built here.** A data provider runs before the test class can skip,
     * and `integration-tests-must-survive-bare-ci` is the rule it would break: on a runner
     * with no database this method still has to answer, so it only reads the filesystem.
     *
     * @return iterable<string, array{string}>
     */
    public static function templates(): iterable
    {
        foreach (self::roots() as $prefix => $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                $path = $file->getPathname();

                if (! str_ends_with($path, '.html.twig')) {
                    continue;
                }

                $relative = str_starts_with($path, $root . '/') ? substr($path, strlen($root) + 1) : $path;
                $name     = $prefix . $relative;

                yield $name => [$name];
            }
        }
    }

    /**
     * The directories Twig can load from, keyed by the prefix a template is addressed under.
     *
     * More than one since 2026-08-21, and the second is the point of this method: JUser
     * ships its own eleven templates and they are addressed `@juser/…`. They are rendered in
     * production by pages this application serves, so leaving them out would mean the *only*
     * templates on the site nothing compiles are the ones on the sign-in path.
     *
     * The prefix has to be right for both tests here — `load()` resolves a name through the
     * same loader the Kernel wires, and the `extends` check turns a name back into a path.
     *
     * @return array<string, string> prefix => absolute directory
     */
    private static function roots(): array
    {
        return [
            ''       => dirname(__DIR__, 2) . '/templates',
            '@juser/' => JUserExtension::templatePath(),
        ];
    }

    /** A template name back to the file it came from, for the `extends` check. */
    private static function pathOf(string $name): string
    {
        foreach (self::roots() as $prefix => $root) {
            if ('' !== $prefix && str_starts_with($name, $prefix)) {
                return $root . '/' . substr($name, strlen($prefix));
            }
        }

        return dirname(__DIR__, 2) . '/templates/' . $name;
    }

    private static function twig(): Environment
    {
        $bridge = self::bridge();

        $requests = new RequestStack();
        //A request is required by the factory's signature and irrelevant to compilation;
        //the locale-prefixed home page is the least surprising thing to hand it.
        $request = Request::create('/en/');
        $request->attributes->set('_route', 'home.locale');
        $request->attributes->set('_locale', 'en');
        $requests->push($request);

        return (new TwigFactory())->create(
            $bridge,
            new ViewHelpers($bridge, fn (): RouteUrl => new RouteUrl($bridge, '')),
            new RouteUrl($bridge, ''),
            $requests,
            new CspNonce(),
            new HostMessages()
        );
    }

    /** Config caches off, as in every integration test here: no test may write data/config/. */
    private static function bridge(): ServiceBridge
    {
        if (null !== self::$bridge) {
            return self::$bridge;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        try {
            return self::$bridge = new ServiceBridge($appConfig);
        } catch (Throwable $e) {
            self::markTestSkipped('cannot build the laminas container: ' . $e->getMessage());
        }
    }
}
