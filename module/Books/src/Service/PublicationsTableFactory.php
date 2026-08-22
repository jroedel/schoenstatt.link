<?php

declare(strict_types=1);

namespace Books\Service;

use Books\Model\PublicationsTable;
use Psr\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;
use SionModel\Service\ActingUserProviderInterface;
use SionModel\Service\EntitiesService;
use SionModel\Service\SionTableWiring;

/**
 * Builds {@see PublicationsTable}.
 */
class PublicationsTableFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): PublicationsTable
    {
        $config = $container->get('Config');

        $table = new PublicationsTable(
            $container->get($config['books']['books_db_adapter']),
            $container->get(EntitiesService::class),
            $container->get('SionModel\Config'),
            $container->get(ActingUserProviderInterface::class)
        );
        SionTableWiring::apply($container, $table);

        $table->setSchoenstattTable($container->get(SchoenstattTable::class));

        return $table;
    }
}
