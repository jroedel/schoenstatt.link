<?php
namespace Books\Service;

use Books\Model\LibraryTable;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Laminas\Db\Adapter\Adapter;
use SionModel\Service\ActingUserProviderInterface;

/**
 * Factory responsible of priming the LibraryTable service
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class LibraryTableServiceFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $dbAdapter = $container->get(Adapter::class);

        $config = $container->get('Config');
        $actingUserProvider = $container->get(ActingUserProviderInterface::class);

        $table = new LibraryTable($dbAdapter, $container, $actingUserProvider, $config);
        return $table;
    }
}
