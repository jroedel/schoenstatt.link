<?php
namespace Schoenstatt\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\View\Helper\FormatAssociation;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class FormatAssociationFactory implements FactoryInterface
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

        $viewHelper = new FormatAssociation($valueOptions);
        return $viewHelper;
    }
}
