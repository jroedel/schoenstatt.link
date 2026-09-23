<?php

declare(strict_types=1);

namespace App\Modules;

use RuntimeException;
use SionModel\Data\ArrayMerge;

use function array_key_exists;
use function chmod;
use function class_exists;
use function dirname;
use function file_put_contents;
use function glob;
use function is_array;
use function is_file;
use function method_exists;
use function rename;
use function rtrim;
use function sprintf;
use function strlen;
use function strpos;
use function substr;
use function tempnam;
use function unlink;
use function var_export;

/**
 * The merged module configuration: every enabled module's `getConfig()`, with
 * `config/autoload/*.php` layered over it.
 *
 * This is all `laminas/laminas-modulemanager` was doing for this application, and it went
 * with the package on 2026-09-21. Of everything `DefaultListenerAggregate` attached, only
 * `ConfigListener` had any effect here:
 *
 * - `ModuleLoaderListener` ran laminas-loader's `ModuleAutoloader`, whose class-map cache
 *   on disk was `[]` — composer's PSR-4 resolves all seven `Module` classes.
 * - `AutoloaderListener`, `InitTrigger` and `ModuleDependencyCheckerListener` look for
 *   `getAutoloaderConfig()`, `init()` and `getModuleDependencies()`. Every `Module` class
 *   this application loads has `getConfig()` and nothing else.
 * - `OnBootstrapListener` and `LocatorRegistrationListener::setService()` fire on the
 *   **MVC** bootstrap event, and laminas-mvc was removed in 2026-09.
 *
 * Dropping the one `require` line uninstalled five packages, because the config cache was
 * written by `brick/varexporter` (for the closures module configs used to hold) through
 * `webimpress/safe-writer`, and `ConfigListener` read config files through
 * `laminas/laminas-config`. It also returned `nikic/php-parser` to the dev-only set, where
 * `composer.json` declares it for `tools/ctx`.
 *
 * ## What had to be reproduced exactly
 *
 * The merge order — each module in `config/modules.config.php` order, then each glob'd
 * file. The merge *rule* is {@see \SionModel\Data\ArrayMerge}, which this class held
 * until 2026-09-22: **integer keys append rather than overwrite**, which is what makes two
 * modules' `factories` lists combine and two modules' `listeners` lists concatenate, and
 * getting it wrong is silent. It moved to SionModel because `ProblemService` needs the same
 * rule and SionModel is the package both sides can see.
 *
 * Verified at the cutover by building the merged config both ways and diffing the
 * `var_export`s: identical, 21 top-level keys, 0 lines of difference.
 */
final class ModuleConfig
{
    /** @var array<string, mixed>|null */
    private ?array $merged = null;

    /**
     * @param list<string> $modules module names, in load order
     * @param list<string> $globPaths `config_glob_paths`, GLOB_BRACE patterns
     * @param string|null $cacheFile where the merged config is cached, or null for no
     *        caching — which is what a console run and every test want, since a file
     *        written there by the wrong user sits next to a real deployment
     */
    public function __construct(
        private readonly array $modules,
        private readonly array $globPaths,
        private readonly ?string $cacheFile,
    ) {
    }

    /**
     * @param array<string, mixed> $appConfig `config/application.config.php`
     * @param bool $caching whether the merged config may be read from and written to disk
     */
    public static function fromApplicationConfig(array $appConfig, bool $caching): self
    {
        /** @var array<string, mixed> $options */
        $options = is_array($appConfig['module_listener_options'] ?? null)
            ? $appConfig['module_listener_options']
            : [];

        /** @var list<string> $modules */
        $modules = is_array($appConfig['modules'] ?? null) ? $appConfig['modules'] : [];
        /** @var list<string> $globPaths */
        $globPaths = is_array($options['config_glob_paths'] ?? null) ? $options['config_glob_paths'] : [];

        return new self($modules, $globPaths, $caching ? self::cacheFile($options) : null);
    }

    /**
     * Where the merged config is cached, following the names laminas-modulemanager used —
     * deliberately, so an existing tree's file is the same file.
     *
     * @param array<string, mixed> $options `module_listener_options`
     */
    public static function cacheFile(array $options): ?string
    {
        //rtrim as `ListenerOptions::normalizePath()` did, so the printed path matches the
        //file on disk: `cache_dir` is configured with a trailing slash.
        $dir = rtrim((string) ($options['cache_dir'] ?? ''), '/\\');
        if ('' === $dir) {
            //With no cache_dir the laminas listener built its names against an empty
            //directory, i.e. paths at the filesystem root. Nothing was ever cached there.
            return null;
        }

        $key = (string) ($options['config_cache_key'] ?? '');

        return '' === $key
            ? $dir . '/module-config-cache.php'
            : $dir . '/module-config-cache.' . $key . '.php';
    }

    /**
     * The files `cache:clear-config` removes.
     *
     * The second one is laminas-modulemanager's module class-map cache. Nothing has
     * written it since 2026-09-21 — composer's PSR-4 resolves every `Module` class, and
     * the file the listener wrote held `[]` — but a tree that ran the old code still has
     * one on disk, and a clear that left it behind would be a lie. It can go once no
     * working tree predates that change; production never had the problem, because
     * `tools/deploy.sh` gives each release its own empty `data/config`.
     *
     * @param array<string, mixed> $options `module_listener_options`
     * @return list<string>
     */
    public static function cacheFiles(array $options): array
    {
        $file = self::cacheFile($options);
        if (null === $file) {
            return [];
        }

        $directory = rtrim((string) ($options['cache_dir'] ?? ''), '/\\');
        $key       = (string) ($options['module_map_cache_key'] ?? '');

        return [
            $file,
            '' === $key
                ? $directory . '/module-classmap-cache.php'
                : $directory . '/module-classmap-cache.' . $key . '.php',
        ];
    }

    /**
     * The names of the modules this application loads.
     *
     * `App\Laminas\ModuleLanguageDirectories` is the one caller and it wants names, not
     * instances — so unlike the module manager, nothing here instantiates a `Module` class
     * to answer it.
     *
     * @return list<string>
     */
    public function moduleNames(): array
    {
        return $this->modules;
    }

    /** @return array<string, mixed> */
    public function merged(): array
    {
        if (null !== $this->merged) {
            return $this->merged;
        }

        if (null !== $this->cacheFile && is_file($this->cacheFile)) {
            /** @var mixed $cached */
            $cached = include $this->cacheFile;
            if (is_array($cached)) {
                /** @var array<string, mixed> $cached */
                return $this->merged = $cached;
            }
        }

        $merged = $this->build();

        if (null !== $this->cacheFile) {
            $this->writeCache($this->cacheFile, $merged);
        }

        return $this->merged = $merged;
    }

    /**
     * Module configs in declared order, then the glob'd files, folded left.
     *
     * Keyed by source so that a path reached by two glob alternatives is merged once, at
     * its first position — which is what `ConfigListener`'s `$configs[$path]` did.
     *
     * @return array<string, mixed>
     */
    private function build(): array
    {
        /** @var array<string, array<string, mixed>> $configs */
        $configs = [];

        foreach ($this->modules as $module) {
            $class = $module . '\Module';
            if (! class_exists($class)) {
                throw new RuntimeException(sprintf(
                    'Module "%s" is enabled in config/modules.config.php but %s could not be '
                    . 'autoloaded. Module classes are resolved by composer\'s PSR-4 map; check '
                    . 'the module\'s autoload entry in composer.json.',
                    $module,
                    $class
                ));
            }

            /** @var object $instance */
            $instance = new $class();
            if (! method_exists($instance, 'getConfig')) {
                continue;
            }

            /** @var mixed $config */
            $config = $instance->getConfig();
            if (is_array($config)) {
                /** @var array<string, mixed> $config */
                $configs['module:' . $module] = $config;
            }
        }

        foreach ($this->globPaths as $pattern) {
            foreach (self::expand($pattern) as $file) {
                if (array_key_exists('file:' . $file, $configs)) {
                    continue;
                }
                /** @var mixed $config */
                $config = include $file;
                if (! is_array($config)) {
                    throw new RuntimeException(sprintf('%s did not return an array.', $file));
                }
                /** @var array<string, mixed> $config */
                $configs['file:' . $file] = $config;
            }
        }

        $merged = [];
        foreach ($configs as $config) {
            $merged = ArrayMerge::merge($merged, $config);
        }

        return $merged;
    }

    /**
     * `glob()` with brace expansion, in `Laminas\Stdlib\Glob`'s fallback order.
     *
     * The leftmost brace is expanded first and each alternative recursed in turn, so
     * `{{,*.}global,{,*.}local}` yields `global.php`, then `*.global.php` sorted, then
     * `local.php`, then `*.local.php` sorted. That order **is** the configuration
     * precedence — `local` must win over `global` — so it is not an implementation detail.
     *
     * Written out rather than left to `GLOB_BRACE`, which is not available on every
     * platform's libc; laminas passed `forceFallback` for the same reason.
     *
     * @return list<string>
     */
    public static function expand(string $pattern): array
    {
        $open = strpos($pattern, '{');
        if (false === $open) {
            $found = glob($pattern);

            return false === $found ? [] : $found;
        }

        $depth = 0;
        $close = null;
        $parts = [];
        $start = $open + 1;
        for ($i = $open, $length = strlen($pattern); $i < $length; $i++) {
            $character = $pattern[$i];
            if ('{' === $character) {
                $depth++;
            } elseif ('}' === $character) {
                $depth--;
                if (0 === $depth) {
                    $parts[] = substr($pattern, $start, $i - $start);
                    $close   = $i;
                    break;
                }
            } elseif (',' === $character && 1 === $depth) {
                $parts[] = substr($pattern, $start, $i - $start);
                $start   = $i + 1;
            }
        }

        if (null === $close) {
            //An unbalanced brace is not a pattern we expand; hand it to glob() as written.
            $found = glob($pattern);

            return false === $found ? [] : $found;
        }

        $prefix = substr($pattern, 0, $open);
        $suffix = substr($pattern, $close + 1);

        $paths = [];
        foreach ($parts as $part) {
            foreach (self::expand($prefix . $part . $suffix) as $path) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Write the cache as one atomic replacement.
     *
     * A partly-written config file is a fatal on every concurrent request, so the content
     * goes to a temporary file in the same directory and is moved into place — which is
     * what `webimpress/safe-writer` did for the listener. `var_export` is enough only
     * because the merged config holds no objects, and
     * `SchoenstattTest\Integration\MergedConfigIsPlainDataTest` is what keeps that true.
     *
     * @param array<string, mixed> $merged
     */
    private function writeCache(string $file, array $merged): void
    {
        $directory = dirname($file);
        $temporary = tempnam($directory, 'config-cache');
        if (false === $temporary) {
            //An unwritable cache directory is not worth failing a request over: the config
            //is already built and correct, and the next request rebuilds it.
            return;
        }

        $content = "<?php\n\nreturn " . var_export($merged, true) . ";\n";
        if (false === file_put_contents($temporary, $content) || ! rename($temporary, $file)) {
            @unlink($temporary);

            return;
        }

        @chmod($file, 0644);
    }
}
