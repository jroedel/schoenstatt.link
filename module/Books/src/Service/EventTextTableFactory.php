<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Model\EventTextTable;
use JUser\Model\UserTable;

/**
 * Factory responsible of priming the EventTextTableFactory service
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class EventTextTableFactory implements FactoryInterface
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

        /** @var UserTable $userTable */
        $userTable = $container->get(UserTable::class);
        $userNames = $userTable->getUserNames();

        $table = new EventTextTable($dbAdapter, $container, $actingUserId, $config, $userNames);

//         $schTable = $container->get(SchoenstattTable::class);
//         $table->setSchoenstattTable($schTable);
        return $table;
    }
}
