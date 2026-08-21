<?php

namespace SchoenstattTest\Integration;

use JTranslate\I18n\TranslatableMessage;
use JUser\Host\Severity;
use JUser\Host\UrlBuilderInterface;
use JUser\Twig\JUserExtension;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The three properties of `JUser\Host\` that nothing else can check.
 *
 * JUser 3.0.0 is meant to drop into another application, so its host contract is the
 * one part of the module a consuming application cannot see going wrong: an interface
 * has no behaviour to smoke-test, and this application does not implement any of them
 * yet. What *is* checkable is whether the contract still says what it was written to
 * say, and each of the three below fails silently if it stops.
 *
 * Lives in the integration suite rather than the unit one because two of the three
 * compare against real vendor libraries — laminas-mvc-plugin-flashmessenger and Twig.
 * Neither needs a database or a running app.
 */
class JUserHostContractTest extends TestCase
{
    /**
     * `Severity` and the laminas flash namespaces are the same five strings.
     *
     * Not a formality. A flash message crosses a redirect **in the session**, so during
     * the migration one request writes it through one front controller and a differently
     * rendered page reads it through the other. `JTranslate\View\Helper\FlashMessenger`
     * reads by namespace, so a Severity whose value drifted from the laminas constant
     * would not be an error anywhere: the message would simply never be rendered, and
     * only for visitors who happened to be mid-flow across the two implementations.
     *
     * Asserted as set equality in both directions, so a laminas release adding a
     * namespace fails here too — the enum is meant to be the full set, and a message
     * severity this module cannot express is a message it cannot pass on.
     */
    public function testEverySeverityIsALaminasFlashNamespace(): void
    {
        $laminas = [];
        foreach ((new ReflectionClass(FlashMessenger::class))->getConstants() as $name => $value) {
            if (0 === strpos($name, 'NAMESPACE_')) {
                $laminas[] = $value;
            }
        }

        $ours = array_map(static fn(Severity $s): string => $s->value, Severity::cases());

        sort($laminas);
        sort($ours);

        $this->assertSame($laminas, $ours, 'JUser\Host\Severity must cover exactly the laminas flash namespaces');
    }

    /**
     * `juser_path()` hands all three arguments to the host unchanged, and returns what
     * it gets back.
     *
     * The whole of the function's job, and the reason it is worth pinning is the
     * temptation not to have it: the templates could have gone on calling
     * `laminas_path()`, and then this package would only render inside a host with a
     * laminas router — the exact coupling 3.0.0 exists to remove.
     */
    public function testJuserPathDelegatesEveryArgumentUnchanged(): void
    {
        $urls = $this->recordingUrlBuilder();
        $twig = $this->twig(new JUserExtension($urls));

        $rendered = $twig->render('call', []);

        $this->assertSame('/en/user/verify?token=abc', $rendered);
        $this->assertSame(
            [['zfcuser/verify', ['id' => 7], ['token' => 'abc']]],
            $urls->calls,
            'the route name, the route params and the query must arrive as given'
        );
    }

    /**
     * `juser_layout` is usable where it has to be usable: in `{% extends %}`.
     *
     * A global rather than a function because `{% extends %}` takes an expression, and
     * this is the assertion that the indirection actually resolves at render time rather
     * than needing a literal at compile time. Every template in the package extends it,
     * so if this stops working the module renders nothing at all.
     */
    public function testJuserLayoutIsExtendableAndConfigurable(): void
    {
        $default = $this->twig(new JUserExtension($this->recordingUrlBuilder()));
        $this->assertSame('conventional chrome: page', $default->render('child'));

        $custom = $this->twig(new JUserExtension($this->recordingUrlBuilder(), 'host-layout.html.twig'));
        $this->assertSame("the host's own chrome: page", $custom->render('child'));

        $this->assertSame(
            'layout.html.twig',
            JUserExtension::DEFAULT_LAYOUT,
            'the default must stay the path the templates named before it was configurable'
        );
    }

    /**
     * No framework type is reachable *from the code* of the contract — not as an import,
     * not in a signature, not in a `@param`.
     *
     * The point of `JUser\Host\` is that a host implements it over whatever it has, so a
     * `Laminas\` type in a signature would put the dependency straight back while the
     * `require` block went on claiming otherwise.
     *
     * **Prose is explicitly allowed, and the first version of this test got that wrong.**
     * Every one of these docblocks names the laminas class it replaces — that is what makes
     * the contract readable by someone holding the 2.x code, and it is the opposite of a
     * coupling. So this checks `use` statements and reflected types, which is where a real
     * dependency would live, and leaves the prose alone. A blanket string search reported
     * four of the six files as violations for documenting their own purpose.
     *
     * The two namespaces that *are* allowed are JUser's own and JTranslate's, which stays a
     * dependency of the package.
     */
    public function testNoFrameworkTypeIsReachableFromTheContract(): void
    {
        $dir   = __DIR__ . '/../../module/JUser/src/Host';
        $files = glob($dir . '/*.php');
        $this->assertNotEmpty($files, 'JUser\Host\ must exist and hold the contract');

        foreach ($files as $file) {
            $short = basename($file, '.php');
            $source = (string) file_get_contents($file);

            preg_match_all('/^use\s+([^;]+);/m', $source, $imports);
            foreach ($imports[1] as $imported) {
                $this->assertTrue(
                    $this->isOwnNamespace($imported),
                    sprintf('%s imports %s; a host implements this over whatever it has', $short, $imported)
                );
            }

            //`@param string|TranslatableMessage $x` is a type PHPStan enforces and an
            //implementer copies, so it is code for this purpose even though it is written
            //in a comment. Prose in the same comment is not.
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

            foreach ($this->declaredTypes('JUser\\Host\\' . $short) as $type) {
                $this->assertTrue(
                    $this->isOwnNamespace($type),
                    sprintf('%s names %s in a signature', $short, $type)
                );
            }
        }
    }

    private function isOwnNamespace(string $type): bool
    {
        return 0 === strpos($type, 'JUser\\') || 0 === strpos($type, 'JTranslate\\');
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
                static fn(\ReflectionParameter $p): ?\ReflectionType => $p->getType(),
                $method->getParameters()
            );
            $candidates[] = $method->getReturnType();

            foreach ($candidates as $type) {
                if (! $type instanceof \ReflectionNamedType) {
                    continue;
                }
                //`self`/`static` are how an enum's implicit cases()/from()/tryFrom()
                //declare themselves; they name the class under test, not a dependency
                if (! $type->isBuiltin() && ! in_array($type->getName(), ['self', 'static', 'parent'], true)) {
                    $types[] = $type->getName();
                }
            }
        }

        return $types;
    }

    /** @return UrlBuilderInterface&object{calls: list<array{string, array<string, mixed>, array<string, string>}>} */
    private function recordingUrlBuilder(): object
    {
        return new class implements UrlBuilderInterface {
            /** @var list<array{string, array<string, mixed>, array<string, string>}> */
            public array $calls = [];

            public function path(string $route, array $params = [], array $query = []): string
            {
                $this->calls[] = [$route, $params, $query];

                return '/en/user/verify?token=abc';
            }

            public function url(string $route, array $params = [], array $query = []): string
            {
                return 'http://example.test' . $this->path($route, $params, $query);
            }
        };
    }

    private function twig(JUserExtension $extension): Environment
    {
        $twig = new Environment(new ArrayLoader([
            'call'                  => "{{ juser_path('zfcuser/verify', {'id': 7}, {'token': 'abc'}) }}",
            'child'                 => '{% extends juser_layout %}{% block content %}page{% endblock %}',
            'layout.html.twig'      => 'conventional chrome: {% block content %}{% endblock %}',
            'host-layout.html.twig' => "the host's own chrome: {% block content %}{% endblock %}",
        ]), ['strict_variables' => true, 'cache' => false]);
        $twig->addExtension($extension);

        return $twig;
    }
}
