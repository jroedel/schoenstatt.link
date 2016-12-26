<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Db\TableGateway\TableGateway;
use SionModel\Service\EntitiesService;
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
		$tableGateway = new TableGateway('', $dbAdapter);

		/** @var  User $userService **/
		$userService = $serviceLocator->get('zfcuser_user_service');
		$user = $userService->getAuthService()->getIdentity();

		/**
		 * @var EntitiesService $entities
		 */
		$entities = $serviceLocator->get('SionModel\Service\EntitiesService');
// 		/**
// 		 * @var ProblemService $problemService
// 		 */
// 		$problemService = $serviceLocator->get('SionModel\Service\ProblemService');
// 		$entityProblemPrototype = $problemService->getEntityProblemPrototype();
		//$tableGateway, $entities, $actingUserId, $changesTableName, $entityProblemPrototype, $userTable)
		$table = new SchoenstattTable($tableGateway, $entities->getEntities(), $user->id, 'sch_changes');
		return $table;
    }
}
