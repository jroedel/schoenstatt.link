<?php
namespace Schoenstatt\Service;

use Psr\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\RoleForm;

/**
 * Factory responsible of prepping the RoleForm
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class RoleFormFactory
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
        /** @var \SionModel\I18n\TranslatesMessages $translator */
        $translator = $container->get('translator');

        $associations = $table->getAssociationValueOptions();
        $roleTitles = $table->getRoleTitleValueOptions($translator);

        //`new`, not a plugin manager: `SionModel\Form\Fieldset::getFormFactory()`
        //reaches the element registry on its own, so there is nothing a container
        //could inject that the form does not already find.
        $form = new RoleForm();
        $form->init();

        $form->get('associationId')->setValueOptions($associations);
        $form->get('roleTitle')->setValueOptions($roleTitles);
        return $form;
    }
}
