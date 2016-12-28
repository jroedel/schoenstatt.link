<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Schoenstatt\Form\AssociationForm;

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
        /** @var \Patres\Model\SchoenstattTable $table **/
		$table = $serviceLocator->get ( 'Schoenstatt\Model\SchoenstattTable' );
		/** @var \Zend\I18n\Translator\Translator $translator */
		$translator = $serviceLocator->get ( 'translator' );

		$valueOptions = array();

		$countryNames = $serviceLocator->get ( 'CountryValueOptions' );

		$config = $serviceLocator->get ( 'Schoenstatt\Config' );
        if (!isset($config['association_kinds'])) {
            throw new \Exception('The \'association_kinds\' key in the \'schoenstatt\' config must be set.');
        }

		$adminTags = $table->getPersonAdminTags();

		/** @var FormElementManagerV2Polyfill $formManager */
		$formManager = $serviceLocator->get('FormElementManager');
		/** @var \Schoenstatt\Form\AssociationForm $form */
		$form = $formManager->get('Schoenstatt\Form\AssociationForm', [], true);

		$form->get('country')->setValueOptions($countryNames);
		$form->get('kind')->setValueOptions($config['association_kinds']);
		$form->get('adminTags')->setValueOptions($adminTags);
		return $form;
    }
}
