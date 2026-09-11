<?php

declare(strict_types=1);

namespace SchoenstattTest\Fuzz;

use App\Books\CheckoutForms;
use App\Books\LibraryScopedForms;
use App\Laminas\ContainerFactory;
use App\Laminas\ServiceBridge;
use Laminas\Form\Fieldset;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Session\Config\ConfigInterface as SessionConfigInterface;
use Laminas\Session\Config\StandardConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Throwable;

/**
 * Discovers every form and fieldset in the application and builds a real
 * instance of each one.
 *
 * ## Why discovery is by filesystem scan and never a hardcoded list
 *
 * The whole value of this harness is that a form added next month is checked
 * without anyone remembering to check it. A list of 43 class names would be
 * correct on the day it was written and wrong thereafter, and its wrongness would
 * be invisible — a green suite that silently stopped covering the newest form is
 * worse than no suite. So the source tree is walked (see formSourceFiles() for the
 * two rules), every concrete `Laminas\Form\Fieldset` descendant is a subject, and
 * nothing here knows how many there should be. (`COUNT_SANITY_FLOOR` guards the opposite failure: a
 * renamed directory making every assertion pass vacuously.)
 *
 * ## Why the container, and why it can be built without bootstrapping
 *
 * A bare `new BookForm()` compiles and runs, but it is not the form the
 * application uses: `BookFormFactory` is what fills in the author, collection,
 * publisher, language and category value options, and an empty `InArray` haystack
 * is a different validation surface from a populated one. Where a form has a
 * registered factory, this class resolves it through the ServiceManager so the
 * subject under test is the real thing.
 *
 * The container is built the way `bin/console` and every integration test build it:
 * `App\Laminas\ContainerFactory::build()`, with the config caches off so the module
 * listener never writes `data/config/`. No request, no route stack, no HTTP.
 *
 * Two seams have to be filled in for the form factories specifically, and both are
 * documented here rather than hidden, because each is a place where the harness
 * departs from production:
 *
 * 1. **A library.** The book, collection, library and checkout forms only mean
 *    something against one library's value options, and the application builds them
 *    through `App\Books\LibraryScopedForms` and `App\Books\CheckoutForms` with the
 *    library id taken off the route. `CheckoutForms::mass()` is asked too: the mass
 *    checkout form's `personId` sits inside its collection's target element, where a
 *    bare construction leaves it with no options at all. There is no route here, so the harness asks the
 *    same two classes for library 1 — a real id from the capsule's production data.
 *    The alternative — skipping four forms — is exactly the outcome the brief
 *    forbids, since an unconstructable form is where a hole hides.
 *
 * 2. **A session config.** `SuggestFormFactory` reaches the authentication
 *    service, which reaches `Laminas\Session\Config\ConfigInterface`, whose
 *    factory calls `ini_set('session.cache_expire', …)`. Under CLI that fails as
 *    soon as anything has been written to stdout ("Session ini settings cannot be
 *    changed after headers have already been sent") — and a PHPUnit run has always
 *    written its progress output by then. A `StandardConfig` is registered
 *    instead: it holds the same values without touching php.ini. Nothing in a form
 *    depends on the session's *behaviour*, only on the service resolving.
 *
 * ## Read-only by construction
 *
 * The form factories query the database for value options; several of them must,
 * and the capsule has a database, so they are allowed to. Nothing here writes:
 * `sqlStatements()` records every statement the adapter executed during the run so
 * a test can prove it, rather than asking the reader to take it on faith.
 *
 * ## Diagnostics
 *
 * Constructing these forms emits a steady stream of pre-existing PHP deprecations
 * and warnings from 2020-era module code (`DateTime::__construct(null)`,
 * undefined-index reads in `SchoenstattTable`). That debt is real but it is not
 * this harness's subject, and `phpunit.xml.dist` sets `failOnWarning="true"`, so
 * left alone it would paint the suite red for reasons unrelated to validation.
 * Every call into application code therefore runs inside `quietly()`, which
 * installs its own error handler, counts what it swallowed, and restores PHPUnit's.
 * The counts are exposed through `diagnostics()` so the noise is suppressed rather
 * than hidden.
 */
final class FormRepository
{
    /** The library the four library-scoped forms are built for. A real id from the capsule's production data. */
    private const LIBRARY_ID = 1;

    /**
     * A renamed directory must fail loudly, not silently cover nothing. 35 while only
     * `module/` was walked; 40 once `src/` joined it, which is four forms below the 41
     * discovered — a floor, not a count, so adding a form needs no edit here.
     */
    public const COUNT_SANITY_FLOOR = 40;

    private static ?self $instance = null;

    private ?ServiceManager $container = null;

    /** @var array<string, class-string>|null */
    private ?array $discovered = null;

    /** @var array<class-string, Fieldset>|null */
    private ?array $forms = null;

    /** @var array<class-string, string>|null */
    private ?array $failures = null;

    /** @var array<class-string, string> */
    private array $origins = [];

    /** @var array<string, int> */
    private array $diagnostics = [];

    /** @var list<string> */
    private array $sql = [];

    private function __construct()
    {
    }

    /**
     * Built once per process. Loading the modules and querying value options is
     * the expensive part of the whole harness; both test classes share it.
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * A repository outside the shared one, built here and now.
     *
     * The singleton is built by whichever test touches it first, under whatever process
     * state that test left — and `SchoenstattTable` reads `\Locale::getDefault()` when a
     * form factory asks it for value options, so "whatever state" decides the labels of
     * 136 selects. Run alone, `SchoenstattTest\Element\ElementSurface` saw every one of the
     * association options labelled `null` (a CLI process defaults to `en_US_POSIX`, which is
     * not a key of `nameByLocale`); run after a test that had set a real locale, it saw the
     * names. The baseline was recorded in the first state and the full suite produced the
     * second.
     *
     * So a caller that needs the forms built under conditions it controls asks for its
     * own, rather than reaching for a shared object whose contents depend on test order.
     * It costs a second build — module loading and the value-option queries — which is
     * why it is not what {@see instance()} does.
     */
    public static function fresh(): self
    {
        return new self();
    }

    // ------------------------------------------------------------- discovery

    /**
     * Every concrete form/fieldset class in the files formSourceFiles() offers, keyed
     * by class name and pointing at the file that declares it.
     *
     * Abstract classes are skipped (nothing instantiates them), as is anything
     * that is not a `Fieldset` descendant — `Form/Element/Phone.php` and
     * `Form/View/Helper/SionFormRow.php` live under a `Form/` directory without
     * being forms.
     *
     * @return array<class-string, string> class => file
     */
    public function discover(): array
    {
        if (null !== $this->discovered) {
            return $this->discovered;
        }

        $found = [];

        foreach ($this->formSourceFiles() as $file) {
            $source = file_get_contents($file);
            if (false === $source) {
                continue;
            }
            if (! preg_match('/^namespace\s+([^;]+);/m', $source, $namespace)) {
                continue;
            }
            if (! preg_match('/^(?:final\s+|abstract\s+)*class\s+(\w+)/m', $source, $class)) {
                continue;
            }

            $fqcn = trim($namespace[1]) . '\\' . $class[1];
            if (! class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);
            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Fieldset::class)) {
                continue;
            }

            $found[$fqcn] = $file;
        }

        ksort($found);

        return $this->discovered = $found;
    }

    /**
     * The source files discovery considered — also the input to
     * ElementDefinitionScanner, which must see *every* candidate file, including
     * the ones discovery rejects.
     *
     * ## Why two roots and two rules
     *
     * The module tree keeps its forms in one place, so `module/&ast;/src/Form/` is a
     * complete rule there. `src/` does not: the Symfony-side forms sit beside the
     * code that uses them — `App\Books\LibraryDeleteForm` next to `LibraryDelete`,
     * `App\Books\Import\ImportMappingForm` next to the importer — which is the
     * right place for them and the reason a directory rule found none of them.
     *
     * They were invisible to this harness until 2026-09-11, and invisibility is not
     * a small thing here: `RefreshSortForm`, `RunImportForm` and `ImportMappingForm`
     * declare **no input filter specification at all**, so every check they have —
     * the CSRF token on all three, nineteen `InArray` domains on the mapping selects
     * — came from the element half that `SionModel\Form\Validation\InputFilter`
     * cannot see. `validationSuppliedOnlyByElement` read 2 while those three forms
     * were entirely element-validated.
     *
     * So the second rule is by name — `&ast;Form.php` and `&ast;Fieldset.php` anywhere
     * under `src/` — which is a convention the whole tree already follows and which
     * costs nothing to keep. A form named otherwise is still missed; `discover()`
     * cannot help with that, but `FormValidationContractTest` pins the count so a
     * form that stops being seen is a failure rather than a silence.
     *
     * @return list<string>
     */
    public function formSourceFiles(): array
    {
        $files      = [];
        $repository = dirname(__DIR__, 2);

        foreach ([$repository . '/module', $repository . '/src'] as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                if (self::looksLikeAFormSource($file->getPathname())) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Under `module/`, position decides; under `src/`, the file name does. See
     * formSourceFiles() for why the two roots cannot share one rule.
     */
    private static function looksLikeAFormSource(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        if (str_contains($path, '/src/Form/')) {
            return true;
        }

        $name = basename($path);

        return str_ends_with($name, 'Form.php') || str_ends_with($name, 'Fieldset.php');
    }

    // ---------------------------------------------------------- instantiation

    /**
     * Every discovered form, built. Keyed by class name, sorted.
     *
     * @return array<class-string, Fieldset>
     */
    public function forms(): array
    {
        $this->build();

        /** @var array<class-string, Fieldset> */
        return $this->forms;
    }

    /**
     * Forms that could not be built, class => reason. A non-empty result is a
     * finding, not a harness excuse: a form nobody can construct is a form nobody
     * has checked.
     *
     * @return array<class-string, string>
     */
    public function constructionFailures(): array
    {
        $this->build();

        /** @var array<class-string, string> */
        return $this->failures;
    }

    /**
     * How each form was obtained — `container:<service id>` or `new`. Recorded so
     * a reader can tell a fully primed form from a bare one.
     */
    public function originOf(string $class): string
    {
        $this->build();

        return $this->origins[$class] ?? 'unknown';
    }

    private function build(): void
    {
        if (null !== $this->forms) {
            return;
        }

        $this->forms    = [];
        $this->failures = [];

        $container = $this->container();
        $byClass   = $this->containerFormsByProducedClass($container);

        foreach (array_keys($this->discover()) as $class) {
            if (isset($byClass[$class])) {
                $this->forms[$class]   = $byClass[$class][0];
                $this->origins[$class] = 'container:' . $byClass[$class][1];
                continue;
            }

            $built = $this->quietly(function () use ($class): Fieldset|string {
                try {
                    return $this->constructDirectly($class);
                } catch (Throwable $e) {
                    return $e::class . ': ' . $e->getMessage();
                }
            });

            if ($built instanceof Fieldset) {
                $this->forms[$class]   = $built;
                $this->origins[$class] = 'new';
            } else {
                $this->failures[$class] = $built;
            }
        }

        ksort($this->forms);
        ksort($this->failures);
    }

    /**
     * Direct construction for the forms with no registered factory.
     *
     * Constructor arguments are supplied by shape rather than by a per-class
     * lookup table, so a new plain form needs no harness change:
     *  - no required arguments        → `new Foo()`
     *  - a class type the container has → the service (DeleteUserForm's `Adapter`)
     *  - one required `array`         → `new Foo([])` (SuggestForm's entity haystack)
     *  - one required argument        → `new Foo('fuzz')` (Fieldset's `$name`)
     * Anything else is a construction failure and gets reported as one.
     *
     * The service rule is what replaced `seedStaticDbAdapter()`. `DeleteUserForm`'s
     * `RecordExists` validator used to reach its adapter through
     * `GlobalAdapterFeature`'s static registry, which only `JUser\Module::onBootstrap()`
     * populated — so the harness had to reproduce a bootstrap step to build the form at
     * all. The forms take the adapter as a constructor argument now, and asking the
     * container for a class-typed argument is both how they are really built and a rule
     * the next such form gets for free.
     */
    private function constructDirectly(string $class): Fieldset
    {
        /** @var Fieldset */
        return $this->constructByShape($class, 0);
    }

    /**
     * How deep argumentFor() may recurse into a constructor's own arguments. One
     * level is all any form has needed; the limit exists so a cycle is a reported
     * failure rather than a stack overflow.
     */
    private const ARGUMENT_DEPTH_LIMIT = 3;

    private function constructByShape(string $class, int $depth): object
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        $required    = $constructor?->getNumberOfRequiredParameters() ?? 0;

        if (0 === $required) {
            return new $class();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isOptional()) {
                break;
            }

            $arguments[] = $this->argumentFor($parameter, $depth);
        }

        return new $class(...$arguments);
    }

    /**
     * One constructor argument, by the rules in constructDirectly()'s docblock.
     *
     * @throws \RuntimeException when the shape is not one this harness can supply,
     *                           which build() records as a construction failure.
     */
    private function argumentFor(\ReflectionParameter $parameter, int $depth = 0): mixed
    {
        $type = $parameter->getType();

        // Untyped, as `__construct($name)` is throughout laminas-form: the old
        // one-argument rule applied and still does.
        if (null === $type) {
            return 'fuzz';
        }

        if (! $type instanceof \ReflectionNamedType) {
            throw new \RuntimeException(sprintf(
                'parameter $%s has a union or intersection type and the class has no'
                . ' registered factory',
                $parameter->getName()
            ));
        }

        if ('array' === $type->getName()) {
            return [];
        }

        if ($type->isBuiltin()) {
            return 'fuzz';
        }

        $service = $type->getName();
        if ($this->container()->has($service)) {
            return $this->container()->get($service);
        }

        //Not a service, so try the shape rules again one level down. This is what
        //`App\Books\Import\ImportMappingForm` needs: its third argument is a
        //`ColumnMap`, a value object holding two arrays, which nothing registers and
        //nothing should. Recursing is the same rule applied again rather than a new
        //one, and `$depth` stops a constructor that wants its own type from looping.
        if ($depth < self::ARGUMENT_DEPTH_LIMIT && $this->isPlainlyConstructable($service)) {
            return $this->constructByShape($service, $depth + 1);
        }

        throw new \RuntimeException(sprintf(
            'parameter $%s wants %s, which is not a registered service, cannot be built'
            . ' from its own constructor, and the class has no registered factory',
            $parameter->getName(),
            $service
        ));
    }

    /**
     * A concrete class whose constructor this harness can try. Interfaces, abstracts
     * and enums are not: nothing here can choose an implementation, and guessing one
     * would make a construction failure look like a success.
     */
    private function isPlainlyConstructable(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        return (new ReflectionClass($class))->isInstantiable();
    }

    /**
     * Resolve every form-ish service id in the container and index the results by
     * the class each one actually produced.
     *
     * Indexing by produced class rather than by id is what lets the harness follow
     * aliases it was never told about: `Books\Form\CreateCheckoutForm` is a service
     * id with no matching class, and the object it returns is a
     * `Books\Form\CheckoutForm`. Resolving first and asking `::class` afterwards
     * finds that mapping on its own.
     *
     * @return array<class-string, array{0: Fieldset, 1: string}>
     */
    private function containerFormsByProducedClass(ServiceManager $container): array
    {
        $config = $this->quietly(static fn(): array => (array) $container->get('config'));

        $ids = array_keys($config['service_manager']['factories'] ?? []);
        $ids = array_merge($ids, array_keys($config['service_manager']['invokables'] ?? []));
        sort($ids);

        $byClass = [];
        foreach ($ids as $id) {
            if (! str_contains((string) $id, '\\Form\\')) {
                continue;
            }

            $object = $this->quietly(static function () use ($container, $id): ?object {
                try {
                    $service = $container->get($id);
                    return is_object($service) ? $service : null;
                } catch (Throwable) {
                    // Recorded implicitly: the class falls through to direct
                    // construction, and if that fails too it shows up in
                    // constructionFailures() with the real reason.
                    return null;
                }
            });

            if ($object instanceof Fieldset && ! isset($byClass[$object::class])) {
                $byClass[$object::class] = [$object, (string) $id];
            }
        }

        //The four library-scoped forms, built the way the application builds them.
        $bridge = ServiceBridge::around($container);
        $scoped = new LibraryScopedForms($bridge);
        foreach (['book', 'collection', 'library'] as $entity) {
            $form = $this->quietly(static function () use ($scoped, $entity): ?object {
                try {
                    return $scoped->formForLibrary($entity, self::LIBRARY_ID);
                } catch (Throwable) {
                    return null;
                }
            });
            if ($form instanceof Fieldset && ! isset($byClass[$form::class])) {
                $byClass[$form::class] = [$form, LibraryScopedForms::class . "::formForLibrary('$entity')"];
            }
        }
        $checkout = $this->quietly(static function () use ($bridge): ?object {
            try {
                return (new CheckoutForms($bridge))->forLibrary(self::LIBRARY_ID);
            } catch (Throwable) {
                return null;
            }
        });
        if ($checkout instanceof Fieldset && ! isset($byClass[$checkout::class])) {
            $byClass[$checkout::class] = [$checkout, CheckoutForms::class . '::forLibrary()'];
        }

        //The mass-checkout form, for the same reason and with a sharper consequence: its
        //`personId` lives inside the collection's *target element*, so a bare
        //`new MassCheckoutForm()` leaves it with no options and its own InArray rejecting
        //every id. `WholeFormEngineParityTest` reported that as eight disagreements
        //between the engine and laminas before this seam existed — a harness artefact
        //wearing the shape of a finding.
        $mass = $this->quietly(static function () use ($bridge): ?object {
            try {
                return (new CheckoutForms($bridge))->mass();
            } catch (Throwable) {
                return null;
            }
        });
        if ($mass instanceof Fieldset && ! isset($byClass[$mass::class])) {
            $byClass[$mass::class] = [$mass, CheckoutForms::class . '::mass()'];
        }

        return $byClass;
    }

    // -------------------------------------------------------------- container

    public function container(): ServiceManager
    {
        if (null !== $this->container) {
            return $this->container;
        }

        $appConfig = require dirname(__DIR__, 2) . '/config/application.config.php';

        // Config caches off: the module listener must never write data/config/, a cache
        // owned by the wrong user sitting next to a real deployment.
        $container = $this->quietly(static fn (): ServiceManager => ContainerFactory::build($appConfig, false));

        $this->quietly(function () use ($container): void {
            $container->setAllowOverride(true);
            $container->setService(SessionConfigInterface::class, new StandardConfig());
            $container->setAllowOverride(false);

            $this->attachSqlRecorder($container);
        });

        return $this->container = $container;
    }

    /**
     * Record every SQL statement the shared adapter runs, so a test can prove the
     * harness never wrote anything.
     */
    private function attachSqlRecorder(ServiceManager $container): void
    {
        try {
            $adapter = $container->get(\Laminas\Db\Adapter\Adapter::class);
        } catch (Throwable) {
            return;
        }

        $sql     = &$this->sql;
        $adapter->setProfiler(new class ($sql) implements \Laminas\Db\Adapter\Profiler\ProfilerInterface {
            /** @param list<string> $sql */
            public function __construct(private array &$sql)
            {
            }

            public function profilerStart($target): void
            {
                if ($target instanceof \Laminas\Db\Adapter\StatementContainerInterface) {
                    $this->sql[] = $target->getSql();
                } elseif (is_string($target)) {
                    $this->sql[] = $target;
                }
            }

            public function profilerFinish(): void
            {
            }
        });
    }

    /**
     * Every SQL statement executed through the shared adapter since the container
     * was built.
     *
     * @return list<string>
     */
    public function sqlStatements(): array
    {
        return $this->sql;
    }

    // ------------------------------------------------------------ diagnostics

    /**
     * Run application code with PHP diagnostics swallowed and counted. See the
     * class docblock for why this exists.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function quietly(callable $callback): mixed
    {
        $counts = &$this->diagnostics;

        set_error_handler(static function (int $severity) use (&$counts): bool {
            $counts[self::severityName($severity)] = ($counts[self::severityName($severity)] ?? 0) + 1;

            return true;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Pre-existing PHP diagnostics swallowed while building and driving the forms,
     * counted by severity. Reported, never asserted on: it is 2020 debt tracked
     * elsewhere, and pinning a number here would make unrelated fixes fail.
     *
     * @return array<string, int>
     */
    public function diagnostics(): array
    {
        ksort($this->diagnostics);

        return $this->diagnostics;
    }

    private static function severityName(int $severity): string
    {
        return match ($severity) {
            E_WARNING, E_USER_WARNING             => 'warning',
            E_NOTICE, E_USER_NOTICE               => 'notice',
            E_DEPRECATED, E_USER_DEPRECATED       => 'deprecated',
            default                               => 'other',
        };
    }
}
