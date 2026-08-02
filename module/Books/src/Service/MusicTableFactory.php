<?php
namespace Books\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Model\MusicTable;
use SionModel\Service\ActingUserProviderInterface;

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

        $actingUserProvider = $container->get(ActingUserProviderInterface::class);

        $table = new MusicTable($dbAdapter, $container, $actingUserProvider);

        return $table;
    }
}
