<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use JTranslate\Host\Severity;
use JTranslate\Host\UrlBuilderInterface;
use JTranslate\Twig\JTranslateExtension;
use JUser\Host\Severity as JUserSeverity;
use SionModel\Messaging\FlashMessages;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The properties of `JTranslate\Host\` that nothing else can check.
 *
 * The twin of {@see JUserHostContractTest}, and for the same reason: an interface has no
 * behaviour to smoke-test, so what is checkable is whether the contract still says what it
 * was written to say. Every property below fails **silently** if it stops holding.
 *
 * Lives in the integration suite because two of these compare against real vendor
 * libraries — laminas-mvc-plugin-flashmessenger and Twig. Neither needs a database or a
 * running app.
 */
final class JTranslateHostContractTest extends TestCase
{
    /**
     * `Severity` and `SionModel\Messaging\FlashMessages`' namespaces are the same five strings.
     *
     * A flash message crosses a redirect **in the session**, so one request writes it and a
     * differently-rendered page reads it. the renderer (`JTranslate\I18n\MessageRenderer`) reads by
     * namespace, so a value that drifted from the laminas constant is not an error
     * anywhere — the message is simply never rendered.
     *
     * That bites harder on this surface than on most: every *successful* write here
     * redirects, so the messages a drift would swallow are exactly the ones confirming that
     * a translation was saved, or warning that it was saved and the catalogs were not
     * rewritten.
     *
     * Set equality in both directions, so a laminas release adding a namespace fails here
     * too: the enum is meant to be the full set.
     */
    public function testEverySeverityIsALaminasFlashNamespace(): void
    {
        $laminas = [];
        foreach ((new ReflectionClass(FlashMessages::class))->getConstants() as $name => $value) {
            if (str_starts_with($name, 'NAMESPACE_')) {
                $laminas[] = $value;
            }
        }

        $ours = array_map(static fn(Severity $s): string => $s->value, Severity::cases());

        sort($laminas);
        sort($ours);

        $this->assertSame(
            $laminas,
            $ours,
            'JTranslate\Host\Severity must cover exactly the FlashMessages namespaces'
        );
    }

    /**
     * The two `Severity` enums agree, case for case.
     *
     * They are separate files on purpose — JUser depends on JTranslate, so JTranslate must
     * not depend back — and `App\Laminas\HostMessages` takes a *string* from either of them.
     * Two enums that drifted would put a message from one module in a bucket the other's
     * page does not read, which is the same silent loss the test above guards for laminas.
     */
    public function testTheTwoModuleSeveritiesAgree(): void
    {
        $ours   = array_map(static fn(Severity $s): string => $s->value, Severity::cases());
        $juser  = array_map(static fn(JUserSeverity $s): string => $s->value, JUserSeverity::cases());
        $names  = array_map(static fn(Severity $s): string => $s->name, Severity::cases());
        $theirs = array_map(static fn(JUserSeverity $s): string => $s->name, JUserSeverity::cases());

        sort($ours);
        sort($juser);
        sort($names);
        sort($theirs);

        $this->assertSame($juser, $ours, 'the two host Severity enums must carry the same values');
        $this->assertSame($theirs, $names, 'the two host Severity enums must carry the same cases');
    }

    /**
     * `jtranslate_path()` hands all three arguments to the host unchanged, and returns what
     * it gets back.
     *
     * The whole of the function's job, and worth pinning because of the temptation not to
     * have it: the templates could have gone on calling the host's `laminas_path()`, and
     * then they would only render inside a host with a laminas router.
     */
    public function testJtranslatePathDelegatesEveryArgumentUnchanged(): void
    {
        $urls = $this->recordingUrlBuilder();
        $twig = $this->twig(new JTranslateExtension($urls));

        $rendered = $twig->render('call', []);

        $this->assertSame('/en/admin/translations/7/edit', $rendered);
        $this->assertSame(
            [['jtranslate/phrase/edit', ['phrase_id' => 7], ['showAll' => 'true']]],
            $urls->calls,
            'the route name, the route params and the query must arrive as given'
        );
    }

    /**
     * `jtranslate_layout` is usable where it has to be usable: in `{% extends %}`.
     *
     * A global rather than a function because `{% extends %}` takes an expression, and this
     * asserts the indirection resolves at render time rather than needing a literal at
     * compile time. All three templates extend it, so if this stops working the module
     * renders nothing at all.
     */
    public function testJtranslateLayoutIsExtendableAndConfigurable(): void
    {
        $default = $this->twig(new JTranslateExtension($this->recordingUrlBuilder()));
        $this->assertSame('conventional chrome: page', $default->render('child'));

        $custom = $this->twig(
            new JTranslateExtension($this->recordingUrlBuilder(), 'host-layout.html.twig')
        );
        $this->assertSame("the host's own chrome: page", $custom->render('child'));

        $this->assertSame(
            'layout.html.twig',
            JTranslateExtension::DEFAULT_LAYOUT,
            'the default must stay the path a conventional host needs no configuration for'
        );
    }

    /**
     * Every template this module ships is addressed through `JTranslateExtension::template()`,
     * and resolves.
     *
     * A bare `'phrase-edit.html.twig'` would resolve against the *host's* loader paths and
     * find whatever it found — a template that renders, not an error. This checks the
     * namespace is what the controllers ask for and that the three files are where the
     * extension says they are.
     */
    public function testEveryShippedTemplateResolvesUnderTheNamespace(): void
    {
        $path = JTranslateExtension::templatePath();
        $this->assertDirectoryExists($path);

        $files = glob($path . '/*.html.twig') ?: [];
        $this->assertNotEmpty($files, 'the module must ship its templates');

        foreach ($files as $file) {
            $name = basename($file, '.html.twig');
            $this->assertSame(
                '@jtranslate/' . $name . '.html.twig',
                JTranslateExtension::template($name)
            );
        }
    }

    /**
     * No framework type is reachable *from the code* of the contract — not as an import, not
     * in a signature, not in a `@param`.
     *
     * The point of `JTranslate\Host\` is that a host implements it over whatever it has, so a
     * `Laminas\` type in a signature would put the dependency straight back.
     *
     * **Prose is explicitly allowed**, for the reason the JUser twin of this test records at
     * length: every docblock there names the laminas class it replaces, which is what makes
     * the contract readable by someone holding the old code. So this checks `use` statements
     * and reflected types, and leaves the prose alone.
     *
     * Note what is *not* checked here and cannot be: this module is still a laminas module —
     * the translator listener, the view helpers and `nowMessenger` all stay — so unlike JUser
     * 3.0.0 the contract existing does not take a package out of `require`. What it buys is
     * that the GUI renders under a front controller that has none of them.
     */
    public function testNoFrameworkTypeIsReachableFromTheContract(): void
    {
        $dir   = __DIR__ . '/../../module/JTranslate/src/Host';
        $files = glob($dir . '/*.php') ?: [];
        $this->assertNotEmpty($files, 'JTranslate\Host\ must exist and hold the contract');

        foreach ($files as $file) {
            $short  = basename($file, '.php');
            $source = (string) file_get_contents($file);

            preg_match_all('/^use\s+([^;]+);/m', $source, $imports);
            foreach ($imports[1] as $imported) {
                $this->assertTrue(
                    $this->isOwnNamespace($imported),
                    sprintf('%s imports %s; a host implements this over whatever it has', $short, $imported)
                );
            }

            //`@param string|TranslatableMessage $x` is a type PHPStan enforces and an
            //implementer copies, so it is code for this purpose even though it is written in
            //a comment. Prose in the same comment is not.
            preg_match_all('/@(?:param|return|var)\s+([^\s]+)/', $source, $annotated);
            foreach ($annotated[1] as $type) {
                foreach (explode('|', $type) as $alternative) {
                    $this->assertStringNotContainsString(
                        '\\',
                        $alternative,
                        sprintf('%s annotates a fully-qualified type (%s)', $short, $alternative)
                    );
                }
            }

            foreach ($this->declaredTypes('JTranslate\\Host\\' . $short) as $type) {
                $this->assertTrue(
                    $this->isOwnNamespace($type),
                    sprintf('%s names %s in a signature', $short, $type)
                );
            }
        }
    }

    private function isOwnNamespace(string $type): bool
    {
        return str_starts_with($type, 'JTranslate\\');
    }

    /**
     * Every class type named in any signature of $class.
     *
     * @return list<string>
     */
    private function declaredTypes(string $class): array
    {
        $types = [];
        foreach ((new ReflectionClass($class))->getMethods() as $method) {
            $candidates = array_map(
                static fn(ReflectionParameter $p): ?ReflectionType => $p->getType(),
                $method->getParameters()
            );
            $candidates[] = $method->getReturnType();

            foreach ($candidates as $type) {
                if (! $type instanceof ReflectionNamedType) {
                    continue;
                }
                //`self`/`static` are how an enum's implicit cases()/from()/tryFrom() declare
                //themselves; they name the class under test, not a dependency
                if (! $type->isBuiltin() && ! in_array($type->getName(), ['self', 'static', 'parent'], true)) {
                    $types[] = $type->getName();
                }
            }
        }

        return $types;
    }

    /**
     * @return UrlBuilderInterface&object{calls: list<array{string, array<string, mixed>,
     *     array<string, string>}>}
     */
    private function recordingUrlBuilder(): object
    {
        return new class implements UrlBuilderInterface {
            /** @var list<array{string, array<string, mixed>, array<string, string>}> */
            public array $calls = [];

            public function path(string $route, array $params = [], array $query = []): string
            {
                $this->calls[] = [$route, $params, $query];

                return '/en/admin/translations/7/edit';
            }

            public function url(string $route, array $params = [], array $query = []): string
            {
                return 'http://example.test' . $this->path($route, $params, $query);
            }
        };
    }

    private function twig(JTranslateExtension $extension): Environment
    {
        $twig = new Environment(new ArrayLoader([
            'call'                   => "{{ jtranslate_path('jtranslate/phrase/edit',"
                . " {'phrase_id': 7}, {'showAll': 'true'}) }}",
            'child'                  => '{% extends jtranslate_layout %}{% block content %}page{% endblock %}',
            'layout.html.twig'       => 'conventional chrome: {% block content %}{% endblock %}',
            'host-layout.html.twig'  => "the host's own chrome: {% block content %}{% endblock %}",
        ]));
        $twig->addExtension($extension);

        return $twig;
    }
}
