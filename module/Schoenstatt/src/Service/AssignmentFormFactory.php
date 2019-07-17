<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\Form\AssignmentForm;
use Schoenstatt\Model\SchoenstattTable;

/**
 * Factory responsible of prepping the AssociationForm
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class AssignmentFormFactory implements FactoryInterface
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

        $associations = $table->getAssociationValueOptions();

        $persons = $table->getPersonValueOptions();
        $roleTitlesValueOptions = $table->getJavascriptRoleTitleValueOptions();

        $form = new AssignmentForm($roleTitlesValueOptions);
        $form->get('associationId')->setValueOptions($associations);
        $form->get('personId')->setValueOptions($persons);
        return $form;
    }
}
