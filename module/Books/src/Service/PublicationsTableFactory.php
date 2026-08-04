<?php
namespace Books\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Model\PublicationsTable;
use Schoenstatt\Model\SchoenstattTable;
use SionModel\Service\ActingUserProviderInterface;

/**
 * Factory responsible of priming the SchoenstattTable service
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class PublicationsTableFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');
        $dbAdapter = $container->get($config['books']['books_db_adapter']);

        $actingUserProvider = $container->get(ActingUserProviderInterface::class);

        $table = new PublicationsTable($dbAdapter, $container, $actingUserProvider);

        $schTable = $container->get(SchoenstattTable::class);
        $table->setSchoenstattTable($schTable);
        return $table;
    }
}
