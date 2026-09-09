<?php

declare(strict_types=1);

namespace Application\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Cache\Storage;
use SionModel\Cache\StorageFactory;

use function is_array;

/**
 * The application-wide cache, registered as `Laminas\Cache\Storage\StorageInterface`
 * until the laminas exit and as `SionModel\Cache\Storage` now.
 *
 * The configuration is untouched — still the laminas-cache shape it has always been in,
 * which `SionModel\Cache\StorageFactory` reads. What changed with the laminas exit is what
 * comes back: `SionModel\Cache\ApcuStorage` or `FilesystemStorage`, neither of which needs
 * laminas-cache or its serializer plugin.
 */
class CacheFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Storage
    {
        /** @var array<string, mixed> $config */
        $config = $container->get('Config');
        $cache  = $config['cache_options'] ?? [];

        return StorageFactory::fromConfig(is_array($cache) ? $cache : []);
    }
}
