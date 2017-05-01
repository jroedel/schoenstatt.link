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
class AssociationFormFactory implements FactoryInterface
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

		$kinds = $serviceLocator->get('Schoenstatt\AssociationKindsValueOptions');

		$countryNames = $serviceLocator->get ( 'CountryValueOptions' );

		/** @var FormElementManagerV2Polyfill $formManager */
		$formManager = $serviceLocator->get('FormElementManager');
		/** @var \Schoenstatt\Form\AssociationForm $form */
		$form = $formManager->get('Schoenstatt\Form\AssociationForm', [], true);

		$form->get('parent')->setValueOptions($associations);
		$form->get('kind')->setValueOptions($kinds);
		$form->get('country')->setValueOptions($countryNames);
		return $form;
    }
}
