<?php

declare(strict_types=1);

namespace Books\Service;

use Books\Model\DictionaryTable;
use Laminas\Db\Adapter\Adapter;
use Laminas\Router\RouteStackInterface;
use Psr\Container\ContainerInterface;
use SionModel\Service\ActingUserProviderInterface;
use SionModel\Service\EntitiesService;
use SionModel\Service\SionTableWiring;

/**
 * Builds {@see DictionaryTable}.
 *
 * The old comment here explained that this passed three arguments rather than four because
 * "the table reaches config through the container it is already given". It is not given one
 * any more: SionTable takes its four required collaborators explicitly, and the router this
 * table used to fetch for itself is now argument five.
 */
class DictionaryTableFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DictionaryTable
    {
        $table = new DictionaryTable(
            $container->get(Adapter::class),
            $container->get(EntitiesService::class),
            $container->get('SionModel\Config'),
            $container->get(ActingUserProviderInterface::class),
            $container->get(RouteStackInterface::class)
        );
        SionTableWiring::apply($container, $table);

        return $table;
    }
}
