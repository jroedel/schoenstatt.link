<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Model\PublicationsTable;

/**
 * Factory responsible of priming the SchoenstattTable service
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class PublicationsTableFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('Config');
        $dbAdapter = $serviceLocator->get($config['books']['books_db_adapter']);

		/** @var  User $userService **/
		$userService = $serviceLocator->get('zfcuser_user_service');
		$user = $userService->getAuthService()->getIdentity();
		$actingUserId = $user ? $user->id : null;

		$table = new PublicationsTable($dbAdapter, $serviceLocator, $actingUserId);
		return $table;
    }
}
