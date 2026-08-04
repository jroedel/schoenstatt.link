<?php
namespace Books\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\View\Helper\FormatPublication;
use SionModel\Service\EntitiesService;
use Books\Model\PublicationsTable;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class FormatPublicationFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $parentLocator = $container->getServiceLocator();
        $entityService = $parentLocator->get(EntitiesService::class);

        /** @var PublicationsTable $table */
        $table = $parentLocator->get(PublicationsTable::class);
        $valueOptions = $table->getAuthorsValueOptions();

        $viewHelper = new FormatPublication($entityService, true);
        $viewHelper->setAuthorValueOptions($valueOptions);

        return $viewHelper;
    }
}
