<?php

declare(strict_types=1);

namespace SchoenstattTest\Container;

use App\Laminas\ContainerFactory;
use JTranslate\I18n\Translator\Translator;
use Locale;
use JTranslate\Model\TranslationsTable;
use ReflectionProperty;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Throwable;

use function array_keys;
use function array_merge;
use function count;
use function get_debug_type;
use function is_array;
use function is_object;
use function implode;
use function ksort;
use function spl_object_id;
use function sort;
use function sprintf;

/**
 * Builds the application container and describes every name in it.
 *
 * The generator half of {@see RecordedContainer}. It exists because a container swap has no
 * other honest proof: the package being removed is what answers every `get()` in the
 * application, so "the tests still pass" only says the paths the tests walk still work, and
 * the paths they do not walk are most of the container. Recording the answers while
 * `Laminas\ServiceManager\ServiceManager` is still installed, then asserting the replacement
 * reproduces them, is the same evidence that carried the merged-config and session swaps —
 * and the recording outlives the package, where a parity test would die with it.
 *
 * Four things are recorded per name, and each one is a way the swap could go wrong:
 *
 * - **the type** — the wrong factory ran, or none did;
 * - **the sharing group** — every name that yields the *same instance* collapses to one
 *   label. An alias that stopped resolving, or a service built twice in one request, moves
 *   a name out of its group and nothing else would show it;
 * - **a probe**, for the two services a delegator decorates. Both delegators return the
 *   object the factory made, so the type cannot see whether one ran; the probe reads the
 *   state it sets;
 * - **the failure**, unwrapped to its root cause, for names that cannot be built in a
 *   console process at all. A container that starts *succeeding* where this one throws has
 *   changed as much as one that stops.
 *
 * Adding a service to a module config therefore moves this recording, by one entry. That is
 * deliberate: `composer container-surface-baseline` regenerates it and the added lines say
 * what the new name resolves to, which is the thing a reviewer wants to see anyway.
 */
final class ContainerSurface
{
    /**
     * The names to resolve, taken from the merged `service_manager` key plus what
     * {@see ContainerFactory} adds itself. Not from the container's own internals: those are
     * private to the package under replacement, and a recording that reads them could not be
     * regenerated afterwards.
     *
     * @return list<string>
     */
    public static function names(ContainerInterface $container): array
    {
        /** @var array<string,mixed> $config */
        $config = $container->get('config');
        /** @var array<string,mixed> $sm */
        $sm = is_array($config['service_manager'] ?? null) ? $config['service_manager'] : [];

        $names = [];
        foreach (['services', 'factories', 'invokables', 'aliases'] as $key) {
            $names = array_merge($names, array_keys(is_array($sm[$key] ?? null) ? $sm[$key] : []));
        }

        $names = array_merge($names, RecordedContainer::ADDED_BY_FACTORY);

        $unique = [];
        foreach ($names as $name) {
            $unique[(string) $name] = true;
        }
        $names = array_keys($unique);
        sort($names);

        return $names;
    }

    /**
     * @param array<string,mixed> $appConfig
     * @return array<string,array<string,mixed>>
     */
    public static function describe(array $appConfig): array
    {
        //A console process has `en_US_POSIX` as its default locale, which is not a key in any
        //`nameByLocale` array, so building the tables under it emits an "Undefined array key"
        //warning and records 496 null labels. Neither is what a request sees, and this is the
        //only place that resolves every service, so the locale is pinned to what a request
        //has rather than inherited from the SAPI.
        $locale = Locale::getDefault();
        Locale::setDefault('en_US');

        try {
            return self::collect($appConfig);
        } finally {
            Locale::setDefault($locale);
        }
    }

    /**
     * @param array<string,mixed> $appConfig
     * @return array<string,array<string,mixed>>
     */
    private static function collect(array $appConfig): array
    {
        $container = ContainerFactory::build($appConfig);

        $surface = [];
        $groups  = [];
        foreach (self::names($container) as $name) {
            $surface[$name] = self::describeOne($container, $name, $groups);
        }

        ksort($surface);

        return $surface;
    }

    /**
     * @param array<int,string> $groups instance id => the first name that produced it
     * @return array<string,mixed>
     */
    private static function describeOne(ContainerInterface $container, string $name, array &$groups): array
    {
        try {
            /** @var mixed $service */
            $service = $container->get($name);
        } catch (Throwable $e) {
            return ['error' => self::rootCause($e)];
        }

        $entry = ['type' => get_debug_type($service)];

        if (is_object($service)) {
            $id = spl_object_id($service);
            //A name that yields an instance already seen belongs to that instance's group;
            //otherwise it opens one. The label is a name, so the recording reads as
            //"these names are the same object" without holding an id that moves every run.
            $entry['instance'] = $groups[$id] ??= $name;

            //asking twice must answer the same object: the container shares by default, and
            //a service built afresh per call is a bug that no type or group would reveal
            $entry['shared'] = $container->get($name) === $service;
        } elseif (is_array($service)) {
            $entry['keys'] = count($service);
        } else {
            $entry['value'] = $service;
        }

        $probe = self::probe($name, $service);
        if (null !== $probe) {
            $entry['probe'] = $probe;
        }

        return $entry;
    }

    /** The state a delegator sets, for the two names one is registered on. */
    private static function probe(string $name, mixed $service): ?string
    {
        if ($service instanceof Translator) {
            return sprintf(
                'locale=%s fallback=%s',
                $service->getLocale(),
                (string) $service->getFallbackLocale()
            );
        }

        if ($service instanceof TranslationsTable) {
            //`setUserModules()` is the whole of what TranslationsTableConfigurator does and
            //there is no getter, so the property is read directly. A delegator that stopped
            //running leaves this empty, and nothing else in the entry would move.
            $property = new ReflectionProperty(TranslationsTable::class, 'userModules');
            /** @var array<string,mixed> $modules */
            $modules = $property->getValue($service);
            $keys    = array_keys($modules);
            sort($keys);

            return 'userModules=' . implode(',', $keys);
        }

        return null;
    }

    /**
     * The exception a factory actually threw, with the container's wrapper taken off.
     *
     * Two containers report a failure under two different class names and two different
     * messages; what has to match is the reason, which is the innermost exception that is
     * not a container exception.
     */
    private static function rootCause(Throwable $e): string
    {
        $cause = $e;
        while ($cause instanceof ContainerExceptionInterface && null !== $cause->getPrevious()) {
            $cause = $cause->getPrevious();
        }

        return $cause::class . ': ' . $cause->getMessage();
    }
}
