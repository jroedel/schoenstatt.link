<?php
namespace Schoenstatt\Service;

use Psr\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\EditAssignmentForm;

/**
 * Factory responsible of prepping the AssignmentForm
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class EditAssignmentFormFactory
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

        //@todo can we factor this out? the element is disabled anyways
        $associations = $table->getAssociationValueOptions();

        //@todo can we factor this out? the element is disabled anyways
        $persons = $table->getPersonValueOptions();
        $roleTitlesValueOptions = $table->getJavascriptRoleTitleValueOptions();

        $form = new EditAssignmentForm($roleTitlesValueOptions);
        $form->get('associationId')->setValueOptions($associations);
        $form->get('personId')->setValueOptions($persons);
        $form->prepareforEdit();
        return $form;
    }
}
