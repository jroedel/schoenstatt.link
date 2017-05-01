<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Schoenstatt\Form\AssociationForm;
use Schoenstatt\Model\SchoenstattTable;
use Zend\Form\FormElementManager\FormElementManagerV2Polyfill;

/**
 * Factory responsible of prepping the AssociationForm
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class RoleFormFactory implements FactoryInterface
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
		$roleTitles = $table->getRoleTitleValueOptions($translator);

		/** @var FormElementManagerV2Polyfill $formManager */
		$formManager = $serviceLocator->get('FormElementManager');
		/** @var \Schoenstatt\Form\AssociationForm $form */
		$form = $formManager->get('Schoenstatt\Form\RoleForm', [], true);

		$form->get('associationId')->setValueOptions($associations);
		$form->get('roleTitle')->setValueOptions($roleTitles);
		return $form;
    }
}
