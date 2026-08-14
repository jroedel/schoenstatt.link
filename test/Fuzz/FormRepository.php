<?php

declare(strict_types=1);

namespace SchoenstattTest\Fuzz;

use Laminas\Form\Fieldset;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\Router\RouteMatch;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Session\Config\ConfigInterface as SessionConfigInterface;
use Laminas\Session\Config\StandardConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionProperty;
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
 * worse than no suite. So `module/&ast;/src/Form/` is walked, every concrete
 * `Laminas\Form\Fieldset` descendant is a subject, and nothing here knows how
 * many there should be. (`countSanityFloor()` guards the opposite failure: a
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
 * The container is built the way `bin/console` and
 * `test/Integration/AclGuardRouteDriftTest` build it: ServiceManager +
 * `loadModules()`, never `bootstrap()`. No MVC listeners, no request, no route
 * stack, no HTTP. Config caching is forced off so the module listener never writes
 * `data/config/`.
 *
 * Two seams have to be filled in for the form factories specifically, and both are
 * documented here rather than hidden, because each is a place where the harness
 * departs from production:
 *
 * 1. **A route match.** `BookFormFactory`, `LibraryFormFactory`,
 *    `CollectionFormFactory` and `CheckoutFormFactory` all read
 *    `$container->get('Application')->getMvcEvent()->getRouteMatch()` to learn
 *    which library they are building a form for. Without `bootstrap()` there is no
 *    MvcEvent at all, and `Application::$event` is protected with no setter, so one
 *    is injected by reflection. The alternative — skipping four forms — is exactly
 *    the outcome the brief forbids, since an unconstructable form is where a hole
 *    hides. If laminas ever renames that property the reflection throws loudly and
 *    the four forms surface as construction failures, which is the right way to
 *    find out.
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
    /** Route parameters the four route-aware form factories ask for. Real ids from the capsule's production data. */
    private const SEEDED_ROUTE_PARAMS = [
        'library_id'    => 1,
        'book_id'       => 18370,
        'collection_id' => 1,
        'checkout_id'   => 1,
        'person_id'     => 1,
        'publication_id' => 1,
        'text_id'       => 1,
    ];

    /** A renamed module directory must fail loudly, not silently cover nothing. */
    public const COUNT_SANITY_FLOOR = 35;

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

    // ------------------------------------------------------------- discovery

    /**
     * Every concrete form/fieldset class under `module/&ast;/src/Form/`, keyed by
     * class name and pointing at the file that declares it.
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
     * ElementDefinitionScanner, which must see *every* file under `Form/`,
     * including the ones discovery rejects.
     *
     * @return list<string>
     */
    public function formSourceFiles(): array
    {
        $files = [];
        $root  = dirname(__DIR__, 2) . '/module';

        if (! is_dir($root)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if (! str_contains(str_replace('\\', '/', $file->getPathname()), '/src/Form/')) {
                continue;
            }
            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
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
        $constructor = (new ReflectionClass($class))->getConstructor();
        $required    = $constructor?->getNumberOfRequiredParameters() ?? 0;

        if (0 === $required) {
            /** @var Fieldset */
            return new $class();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isOptional()) {
                break;
            }

            $arguments[] = $this->argumentFor($parameter);
        }

        /** @var Fieldset */
        return new $class(...$arguments);
    }

    /**
     * One constructor argument, by the rules in constructDirectly()'s docblock.
     *
     * @throws \RuntimeException when the shape is not one this harness can supply,
     *                           which build() records as a construction failure.
     */
    private function argumentFor(\ReflectionParameter $parameter): mixed
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
        if (! $this->container()->has($service)) {
            throw new \RuntimeException(sprintf(
                'parameter $%s wants %s, which is not a registered service, and the class'
                . ' has no registered factory',
                $parameter->getName(),
                $service
            ));
        }

        return $this->container()->get($service);
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

        return $byClass;
    }

    // -------------------------------------------------------------- container

    public function container(): ServiceManager
    {
        if (null !== $this->container) {
            return $this->container;
        }

        $appConfig = require dirname(__DIR__, 2) . '/config/application.config.php';

        // Never let the module listener write data/config/: it would be a cache
        // owned by the wrong user sitting next to a real deployment.
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        $container = new ServiceManager();
        (new ServiceManagerConfig($appConfig['service_manager'] ?? []))
            ->configureServiceManager($container);
        $container->setService('ApplicationConfig', $appConfig);

        $this->quietly(function () use ($container): void {
            $container->get('ModuleManager')->loadModules();

            $container->setAllowOverride(true);
            $container->setService(SessionConfigInterface::class, new StandardConfig());
            $container->setAllowOverride(false);

            $this->seedRouteMatch($container);
            $this->attachSqlRecorder($container);
        });

        return $this->container = $container;
    }

    /**
     * Give the un-bootstrapped Application an MvcEvent carrying a RouteMatch, so
     * the route-aware form factories can read the ids they need. See the class
     * docblock for why this is reflection.
     */
    private function seedRouteMatch(ServiceManager $container): void
    {
        try {
            $application = $container->get('Application');
            $event       = new MvcEvent();
            $event->setName(MvcEvent::EVENT_ROUTE);
            $event->setTarget($application);
            $event->setApplication($application);
            $event->setRouteMatch(new RouteMatch(self::SEEDED_ROUTE_PARAMS));

            (new ReflectionProperty(Application::class, 'event'))->setValue($application, $event);
        } catch (Throwable) {
            // Leave it unseeded. The four route-aware factories then fail, and
            // those forms are reported as construction failures — visibly.
        }
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
