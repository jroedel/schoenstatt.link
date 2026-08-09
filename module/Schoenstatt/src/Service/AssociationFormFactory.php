<?php
namespace Schoenstatt\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use App\Schoenstatt\Association\AssociationFieldDomains;
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

        //The dropdown contents above and the validation domains below are deliberately
        //not the same thing: a dropdown offers only active parents, while a row whose
        //parent was later deactivated must still be editable. See
        //App\Schoenstatt\Association\AssociationFieldDomains.
        $form->setFieldDomains(AssociationFieldDomains::fromServices(
            static fn (string $id): mixed => $container->get($id)
        ));

        return $form;
    }
}
