<?php
namespace Bible\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Bible\Model\DhTable;

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
        
        $authService = $container->get('zfcuser_auth_service');
        $user = $authService->getIdentity();
        $actingUserId = $user ? $user->id : null;

        $table = new DhTable($dbAdapter, $container, $actingUserId);
        return $table;
    }
}
