<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Schoenstatt\Form\AssociationForm;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\AssignmentForm;

/**
 * Factory responsible of prepping the AssociationForm
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class AssignmentFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return AssociationForm
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var SchoenstattTable $table **/
		$table = $serviceLocator->get ( 'Schoenstatt\Model\SchoenstattTable' );
		/** @var \Zend\I18n\Translator\Translator $translator */
		$translator = $serviceLocator->get ( 'translator' );

		$associations = $table->getAssociationValueOptions($translator);

		$persons = $table->getPersonValueOptions();

		$form = new AssignmentForm();

		$form->get('associationId')->setValueOptions($associations);
		$form->get('personId')->setValueOptions($persons);
		return $form;
    }
}
