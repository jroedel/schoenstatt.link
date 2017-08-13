<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Form\FormElementManager\FormElementManagerV2Polyfill;

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
        /** @var \Schoenstatt\Model\SchoenstattTable $table **/
		$table = $serviceLocator->get ( 'Schoenstatt\Model\SchoenstattTable' );

		$persons = $table->getPersonValueOptions();
		$countryNames = $serviceLocator->get ( 'CountryValueOptions' );

		$lifeCommunities = $table->getAssociationValueOptions(false, false);

		$personTags = $serviceLocator->get ( 'Schoenstatt\PersonTagsValueOptions' );
// 		$adminTags = $table->getPersonAdminTags();

		/** @var FormElementManagerV2Polyfill $formManager */
		$formManager = $serviceLocator->get('FormElementManager');
		/** @var \Schoenstatt\Form\PersonForm $form */
		$form = $formManager->get('Schoenstatt\Form\PersonForm', [], true);

		$form->get('spousePersonId')->setValueOptions($persons);
		$form->get('country')->setValueOptions($countryNames);
		$form->get('lifeCommunity')->setValueOptions($lifeCommunities);
		$form->get('postCountry')->setValueOptions($countryNames);
		$form->get('personTags')->setValueOptions($personTags);
// 		$form->get('adminTags')->setValueOptions($adminTags);
		return $form;
    }
}
