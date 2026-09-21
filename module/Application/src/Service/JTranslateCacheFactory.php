<?php

declare(strict_types=1);

namespace Application\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Cache\Storage;
use SionModel\Cache\StorageFactory;

use function is_array;

/**
 * JTranslate's phrase cache.
 *
 * That module builds no cache of its own — it asks the host for a PSR-16 one by service id
 * (`jtranslate.cache_service`) — so this is where its namespace and TTL are decided, from
 * the same `jtranslate.cache_options` block the laminas storage was built from.
 *
 * Sibling of {@see CacheFactory}, which does the same for the application-wide cache from
 * the `cache_options` block. A named factory rather than the closure it was until
 * 2026-09-21; see {@see JUserSessionFactory} for why that mattered.
 */
class JTranslateCacheFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Storage
    {
        /** @var array<string, mixed> $config */
        $config = $container->get('Config');
        $cache  = $config['jtranslate']['cache_options'] ?? [];

        return StorageFactory::fromConfig(is_array($cache) ? $cache : []);
    }
}
