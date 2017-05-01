<?php
namespace Bible\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Model\LibraryTable;
use Bible\Model\BibleTable;

/**
 * Factory responsible of priming the LibraryTable service
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class BibleTableServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $dbAdapter = $serviceLocator->get('Zend\Db\Adapter\Adapter');

		$config = $serviceLocator->get ( 'Bible\Config' );
		/** @var  User $userService **/
		$userService = $serviceLocator->get('zfcuser_user_service');
		$user = $userService->getAuthService()->getIdentity();
		$actingUserId = $user ? $user->id : null;

		$table = new BibleTable( $dbAdapter, $serviceLocator, $actingUserId, $config);
		return $table;
    }
}
