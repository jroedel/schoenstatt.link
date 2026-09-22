<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function class_exists;
use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;
use function sprintf;

/**
 * The application's service container: names in, objects out.
 *
 * `Laminas\ServiceManager\ServiceManager` did this until 2026-09-21. What that package
 * offered and what this application ever asked of it were very different lists, and the
 * measurement is the reason this class is short. Across the merged configuration of all six
 * modules plus what {@see \App\Laminas\ContainerFactory} adds, the whole container is
 * **five keys**: `services`, `factories`, `invokables`, `aliases` and `delegators`. There is
 * not one abstract factory, not one initializer and not one `shared` override left — the
 * last abstract factory went with laminas-session and the last initializer with
 * laminas-eventmanager, both earlier this month. Lazy services, the proxy manager, the
 * plugin-manager hierarchy and the per-name share flags were never used at all.
 *
 * Unsupported keys are therefore **rejected, not ignored**. A container that silently drops
 * an `abstract_factories` key would leave the author of the next one debugging a missing
 * service with nothing to read; {@see configure()} throws instead, and the exception says
 * which key and what to do.
 *
 * Three behaviours are reproduced deliberately because code depends on them:
 *
 * - **Alias chains resolve to their end.** `Configuration` → `configuration` → `Config` →
 *   `config` is a real chain in the bootstrap configuration, and a delegator or a factory is
 *   found under the *resolved* name only.
 * - **Delegators are keyed by the resolved name.** This is laminas' rule and the reason a
 *   delegator written against an alias silently never runs, so keeping it keeps the two
 *   registered ones (the translator and the ACL) working exactly as they do today.
 * - **A factory's exception is wrapped**, so the name of the service that failed is in the
 *   message rather than only in the stack trace. `Error` is not caught: a TypeError in a
 *   factory is a defect, and burying it under a container exception is how it stays one.
 *
 * Services are shared: `get()` builds once and returns the same instance thereafter.
 * {@see build()} is the escape hatch that ignores the cache and passes options, which one
 * test uses to get a second, independently configured table.
 */
final class Container implements ContainerInterface
{
    /** The keys this container implements; anything else in a configuration is an error. */
    private const KNOWN_KEYS = ['services', 'factories', 'invokables', 'aliases', 'delegators'];

    /** @var array<string,mixed> Built or injected instances, keyed by resolved name and by every alias asked for. */
    private array $services = [];

    /** @var array<string,callable|class-string> */
    private array $factories = [];

    /** @var array<string,string> alias => target, one hop; {@see resolve()} follows the chain. */
    private array $aliases = [];

    /** @var array<string,list<callable|class-string>> resolved name => delegator factories, outermost last. */
    private array $delegators = [];

    /** Whether a name already held may be redefined; false everywhere but during bootstrap. */
    private bool $allowOverride = false;

    /** @param array<string,mixed> $config */
    public function __construct(array $config = [])
    {
        if ([] !== $config) {
            $this->configure($config);
        }
    }

    /**
     * Apply a configuration array. Later calls win over earlier ones for the same name.
     *
     * @param array<string,mixed> $config
     */
    public function configure(array $config): void
    {
        foreach ($config as $key => $value) {
            if (! in_array($key, self::KNOWN_KEYS, true)) {
                throw new ServiceNotCreated(sprintf(
                    'Container configuration key "%s" is not supported. This container implements'
                    . ' %s only; see the class docblock for why, and add the feature there rather'
                    . ' than expecting it to be present.',
                    $key,
                    implode(', ', self::KNOWN_KEYS)
                ));
            }
            if (! is_array($value)) {
                throw new ServiceNotCreated(sprintf('Container configuration key "%s" must be an array.', $key));
            }
        }

        if (! $this->allowOverride) {
            foreach (self::KNOWN_KEYS as $key) {
                foreach (array_keys($config[$key] ?? []) as $name) {
                    if (array_key_exists((string) $name, $this->services)) {
                        throw new ServiceNotCreated(sprintf(
                            'Cannot redefine "%s": the container has already built or been given it.'
                            . ' Call setAllowOverride(true) first if that is intended.',
                            (string) $name
                        ));
                    }
                }
            }
        }

        //An invokable is a factory that says `new $class` and, where the name is not the
        //class, an alias onto it — which is exactly what laminas' InvokableFactory did.
        foreach ($config['invokables'] ?? [] as $name => $class) {
            $this->factories[$class] = static fn(): object => new $class();
            if ($name !== $class) {
                $this->aliases[(string) $name] = $class;
            }
        }

        $this->services  = ($config['services'] ?? []) + $this->services;
        $this->factories = ($config['factories'] ?? []) + $this->factories;
        $this->aliases   = ($config['aliases'] ?? []) + $this->aliases;

        foreach ($config['delegators'] ?? [] as $name => $delegators) {
            $this->delegators[(string) $name] = array_merge($this->delegators[(string) $name] ?? [], $delegators);
        }
    }

    public function setService(string $name, mixed $service): void
    {
        if (! $this->allowOverride && array_key_exists($name, $this->services)) {
            throw new ServiceNotCreated(sprintf('Cannot replace the existing service "%s".', $name));
        }

        $this->services[$name] = $service;
    }

    public function setAllowOverride(bool $flag): void
    {
        $this->allowOverride = $flag;
    }

    public function getAllowOverride(): bool
    {
        return $this->allowOverride;
    }

    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->services)) {
            return true;
        }

        $resolved = $this->resolve($id);

        return array_key_exists($resolved, $this->services) || isset($this->factories[$resolved]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }

        $resolved = $this->resolve($id);
        if (array_key_exists($resolved, $this->services)) {
            //cache under the alias too, so a second get() through the same name is one lookup
            return $this->services[$id] = $this->services[$resolved];
        }

        $service = $this->create($resolved, $id, null);

        $this->services[$resolved] = $service;
        if ($resolved !== $id) {
            $this->services[$id] = $service;
        }

        return $service;
    }

    /**
     * Build a fresh instance, ignoring anything already shared and passing options through.
     *
     * @param array<string,mixed>|null $options
     */
    public function build(string $name, ?array $options = null): mixed
    {
        $resolved = $this->resolve($name);
        if (! isset($this->factories[$resolved])) {
            throw new UnknownService(sprintf(
                'Cannot build "%s": no factory is registered for it%s.',
                $name,
                $resolved !== $name ? sprintf(' (it resolves to "%s")', $resolved) : ''
            ));
        }

        return $this->create($resolved, $name, $options);
    }

    /** @param array<string,mixed>|null $options */
    private function create(string $resolved, string $requested, ?array $options): mixed
    {
        if (! isset($this->factories[$resolved])) {
            throw new UnknownService(sprintf(
                'The container has nothing named "%s"%s.',
                $requested,
                $resolved !== $requested ? sprintf(' (it resolves to "%s")', $resolved) : ''
            ));
        }

        try {
            $creation = fn(): mixed => ($this->callable($this->factories[$resolved]))($this, $resolved, $options);

            foreach ($this->delegators[$resolved] ?? [] as $delegator) {
                $previous = $creation;
                $factory  = $this->callable($delegator);
                $creation = fn(): mixed => $factory($this, $resolved, $previous, $options);
            }

            return $creation();
        } catch (UnknownService | ServiceNotCreated $e) {
            throw $e;
        } catch (Exception $e) {
            throw new ServiceNotCreated(
                sprintf('Service "%s" could not be created: %s', $requested, $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /** Factories and delegators are given either as a callable or as a class name to instantiate. */
    private function callable(mixed $factory): callable
    {
        if (is_string($factory)) {
            if (! class_exists($factory)) {
                throw new ServiceNotCreated(sprintf('Factory class "%s" does not exist.', $factory));
            }
            $factory = new $factory();
        }

        if (! is_callable($factory)) {
            throw new ServiceNotCreated(sprintf(
                'A factory must be callable; %s is not.',
                get_debug_type($factory)
            ));
        }

        return $factory;
    }

    /** Follow an alias to the name that actually carries the factory. */
    private function resolve(string $name): string
    {
        $seen = [];
        while (isset($this->aliases[$name])) {
            if (isset($seen[$name])) {
                throw new ServiceNotCreated(sprintf('The alias "%s" is part of a cycle.', $name));
            }
            $seen[$name] = true;
            $name        = $this->aliases[$name];
        }

        return $name;
    }
}
