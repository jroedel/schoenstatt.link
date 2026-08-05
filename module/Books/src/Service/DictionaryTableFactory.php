<?php
namespace Books\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Laminas\Db\Adapter\Adapter;
use Books\Model\DictionaryTable;
use SionModel\Service\ActingUserProviderInterface;

/**
 * Factory responsible of priming the LibraryTable service
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class DictionaryTableFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $dbAdapter = $container->get(Adapter::class);

        $actingUserProvider = $container->get(ActingUserProviderInterface::class);

        //Three arguments, not four. This passed $config as a fourth for years;
        //DictionaryTable::__construct takes three, and PHP discards surplus
        //arguments to userland functions silently, so the call read as though
        //it configured something and did not. The table reaches config through
        //the container it is already given.
        $table = new DictionaryTable($dbAdapter, $container, $actingUserProvider);
        return $table;
    }
}
