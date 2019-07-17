<?php
namespace Books\Service;

use Books\Model\LibraryTable;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Zend\Db\Adapter\Adapter;

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
        $authService = $container->get('zfcuser_auth_service');
        $user = $authService->getIdentity();
        $actingUserId = $user ? $user->id : null;

        $table = new LibraryTable($dbAdapter, $container, $actingUserId, $config);
        return $table;
    }
}
