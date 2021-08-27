<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Model\MusicTable;

/**
 * Factory responsible of priming the SchoenstattTable service
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class MusicTableFactory implements FactoryInterface
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

        $table = new MusicTable($dbAdapter, $container, $actingUserId);

        return $table;
    }
}
