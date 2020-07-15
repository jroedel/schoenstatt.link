<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\View\Helper\FormatEntity;
use SionModel\Service\EntitiesService;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class FormatEntityFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $parentLocator = $container->getServiceLocator();
        /** @var AssociationKindsService $kindsService */
        $kindsService = $parentLocator->get(AssociationKindsService::class);
        $valueOptions = $kindsService->getValueOptions();

        $entityService = $parentLocator->get(EntitiesService::class);
        $viewHelper = new FormatEntity($entityService, $valueOptions, true);
        return $viewHelper;
    }
}
