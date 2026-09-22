<?php

declare(strict_types=1);

namespace SchoenstattTest\Container;

use function file_exists;
use function is_array;

/**
 * The recording of the container's answers, and the names that are not in it.
 *
 * Deliberately free of anything the container is built from, so that the half asserting the
 * contract cannot be broken by the half under test — the same split as
 * {@see \SchoenstattTest\Session\RecordedSession}.
 */
final class RecordedContainer
{
    public const FILE = __DIR__ . '/container-surface.php';

    /**
     * Names {@see ContainerFactory} registers itself, which are therefore not in the merged
     * `service_manager` key and cannot be discovered from it.
     *
     * The container's own class id is **not** here: it is the one name that necessarily
     * changes when the container class does, so `ContainerSurfaceTest` asserts it directly
     * rather than recording a string that is guaranteed to move.
     */
    public const ADDED_BY_FACTORY = [
        'ApplicationConfig',
        'Config',
        'ConfigCacheFiles',
        'Configuration',
        'MvcTranslator',
        'config',
        'configuration',
        'jtranslate_translator',
        'App\Acl\IsAllowed',
        'App\Modules\ModuleConfig',
        'JTranslate\I18n\Translator\Translator',
        'JTranslate\Model\TranslationsTable',
        'SionModel\I18n\TranslatesMessages',
    ];

    /** @return array<string,array<string,mixed>> */
    public static function load(): array
    {
        if (! file_exists(self::FILE)) {
            return [];
        }

        /** @var mixed $recorded */
        $recorded = require self::FILE;

        return is_array($recorded) ? $recorded : [];
    }
}
