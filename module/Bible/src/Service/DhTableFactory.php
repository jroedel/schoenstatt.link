<?php
namespace Bible\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Bible\Model\DhTable;
use SionModel\Service\ActingUserProviderInterface;

class DhTableFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $dbAdapter = $container->get($config['books']['books_db_adapter']);

        $actingUserProvider = $container->get(ActingUserProviderInterface::class);

        $table = new DhTable($dbAdapter, $container, $actingUserProvider);
        return $table;
    }
}
