<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Schoenstatt\Model\SchoenstattTable;

/**
 * Factory responsible of priming the SchoenstattTable service
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class SchoenstattTableFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $dbAdapter = $serviceLocator->get('Zend\Db\Adapter\Adapter');

        $config = $serviceLocator->get ( 'Config' );

		/** @var  User $userService **/
		$userService = $serviceLocator->get('zfcuser_user_service');
		$user = $userService->getAuthService()->getIdentity();
		$actingUserId = $user ? $user->id : null;

		$table = new SchoenstattTable($dbAdapter, $serviceLocator, $actingUserId, $config['schoenstatt']);
		return $table;
    }
}
