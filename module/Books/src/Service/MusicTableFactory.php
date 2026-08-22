<?php

declare(strict_types=1);

namespace Books\Service;

use Books\Model\MusicTable;
use Psr\Container\ContainerInterface;
use SionModel\Service\ActingUserProviderInterface;
use SionModel\Service\EntitiesService;
use SionModel\Service\SionTableWiring;

/**
 * Builds {@see MusicTable}.
 */
class MusicTableFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MusicTable
    {
        $config = $container->get('Config');

        $table = new MusicTable(
            $container->get($config['books']['books_db_adapter']),
            $container->get(EntitiesService::class),
            $container->get('SionModel\Config'),
            $container->get(ActingUserProviderInterface::class)
        );
        SionTableWiring::apply($container, $table);

        return $table;
    }
}
