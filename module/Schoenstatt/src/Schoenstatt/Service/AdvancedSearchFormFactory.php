<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Schoenstatt\Form\AdvancedSearchForm;
use Schoenstatt\Model\SchoenstattTable;

/**
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class AdvancedSearchFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return AdvancedSearchForm
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        /** @var SchoenstattTable $table **/
		$table = $serviceLocator->get ( 'Schoenstatt\Model\SchoenstattTable' );
		$form = new AdvancedSearchForm();
		$roleTitles = $table->getRoleTitleValueOptions();

		/** @var AssociationKindsService $kindsService */
		$kindsService = $serviceLocator->get('Schoenstatt\AssociationKindsService');
		$kinds = $kindsService->getValueOptions();

		$form->get('roleTitle')->setValueOptions($roleTitles);
		$form->get('associationKind')->setValueOptions($kinds);

		//retrieve country names
		$viewHelperManager = $serviceLocator->get('ViewHelperManager');
		$countryNames = $viewHelperManager->get('countryName');
		$usedCountries = $table->getCountries();
		$countries = [];
		foreach ($usedCountries as $value) {
		    $translation = $countryNames->__invoke($value);
		    if ($translation) {
                $countries[$value] = $countryNames->__invoke($value);
		    }
		}
		asort($countries);
		$form->get('associationCountry')->setValueOptions($countries);

		return $form;
    }
}
