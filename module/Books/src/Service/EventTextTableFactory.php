<?php

declare(strict_types=1);

namespace Books\Service;

use Books\Model\EventTextTable;
use JUser\Model\UserTable;
use Psr\Container\ContainerInterface;
use SionModel\Service\ActingUserProviderInterface;
use SionModel\Service\EntitiesService;
use SionModel\Service\SionTableWiring;

/**
 * Builds {@see EventTextTable}.
 *
 * Note that this factory resolves the user table **eagerly**, for its usernames, and has
 * always done so. That is safe from here and would not be from inside a SionTable
 * constructor, which is the distinction the whole change rests on: by the time a factory
 * runs, nothing else is half-built.
 */
class EventTextTableFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): EventTextTable
    {
        $config = $container->get('Config');

        /** @var UserTable $userTable */
        $userTable = $container->get(UserTable::class);

        $table = new EventTextTable(
            $container->get($config['books']['books_db_adapter']),
            $container->get(EntitiesService::class),
            $container->get('SionModel\Config'),
            $container->get(ActingUserProviderInterface::class),
            $config,
            $userTable->getUserNames()
        );
        SionTableWiring::apply($container, $table);

        return $table;
    }
}
