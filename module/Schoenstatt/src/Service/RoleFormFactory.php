<?php
namespace Schoenstatt\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\RoleForm;

/**
 * Factory responsible of prepping the RoleForm
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class RoleFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var SchoenstattTable $table **/
        $table = $container->get(SchoenstattTable::class);
        /** @var \Laminas\I18n\Translator\Translator $translator */
        $translator = $container->get('translator');

        $associations = $table->getAssociationValueOptions();
        $roleTitles = $table->getRoleTitleValueOptions($translator);

        /** @var \Laminas\Form\FormElementManager $formManager */
        $formManager = $container->get('FormElementManager');
        /** @var RoleForm $form */
        $form = $formManager->get(RoleForm::class);

        $form->get('associationId')->setValueOptions($associations);
        $form->get('roleTitle')->setValueOptions($roleTitles);
        return $form;
    }
}
