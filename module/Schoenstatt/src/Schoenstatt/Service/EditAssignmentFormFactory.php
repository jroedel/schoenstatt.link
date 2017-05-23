<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Schoenstatt\Form\AssociationForm;
use Schoenstatt\Form\AssignmentForm;

/**
 * Factory responsible of prepping the AssignmentForm
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class EditAssignmentFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return AssociationForm
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var \Schoenstatt\Model\SchoenstattTable $table */
        $table = $serviceLocator->get('Schoenstatt\Model\SchoenstattTable');
        /** @var AssignmentForm $form */
		$form = $serviceLocator->get('Schoenstatt\Form\AssignmentForm');

		$form->prepareforEdit();
		return $form;
    }
}
