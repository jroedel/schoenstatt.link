<?php
namespace Books\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Laminas\Db\Adapter\Adapter;
use Books\Model\DictionaryTable;

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
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $dbAdapter = $container->get(Adapter::class);

        $config = $container->get('Config');
        $authService = $container->get('zfcuser_auth_service');
        $user = $authService->getIdentity();
        $actingUserId = $user ? $user->id : null;

        $table = new DictionaryTable($dbAdapter, $container, $actingUserId, $config);
        return $table;
    }
}
