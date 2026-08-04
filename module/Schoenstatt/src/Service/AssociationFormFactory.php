<?php
namespace Schoenstatt\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\Form\AssociationForm;
use Schoenstatt\Model\SchoenstattTable;

/**
 * Factory responsible of prepping the AssociationForm
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class AssociationFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var SchoenstattTable $table **/
        $table = $container->get(SchoenstattTable::class);

        $associations = $table->getAssociationValueOptions();

        /** @var AssociationKindsService $kindsService */
        $kindsService = $container->get(AssociationKindsService::class);
        $kinds = $kindsService->getValueOptions();

        $countryNames = $container->get('CountryValueOptions');

        /** @var \Laminas\Form\FormElementManager $formManager */
        $formManager = $container->get('FormElementManager');
        /** @var \Schoenstatt\Form\AssociationForm $form */
        $form = $formManager->get(AssociationForm::class);

        $form->get('parentId')->setValueOptions($associations);
        $form->get('kind')->setValueOptions($kinds);
        $form->get('country')->setValueOptions($countryNames);
        return $form;
    }
}
