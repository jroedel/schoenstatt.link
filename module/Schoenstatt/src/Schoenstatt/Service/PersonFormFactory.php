<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class PersonFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return CreateTimelineEventForm
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var \Patres\Model\SchoenstattTable $table **/
		$table = $serviceLocator->get ( 'Schoenstatt\Model\SchoenstattTable' );
		/** @var \Zend\I18n\Translator\Translator $translator */
		$translator = $serviceLocator->get ( 'translator' );

		$valueOptions = array();

		$countryNames = $serviceLocator->get ( 'CountryValueOptions' );

		$adminTags = $table->getPersonAdminTags();

		/** @var FormElementManagerV2Polyfill $formManager */
		$formManager = $serviceLocator->get('FormElementManager');
		/** @var \Schoenstatt\Form\PersonForm $form */
		$form = $formManager->get('Schoenstatt\Form\PersonForm', [], true);

		$form->get('country')->setValueOptions($countryNames);
		$form->get('nationalities')->setValueOptions($countryNames);
		$form->get('adminTags')->setValueOptions($adminTags);
		return $form;
    }
}
