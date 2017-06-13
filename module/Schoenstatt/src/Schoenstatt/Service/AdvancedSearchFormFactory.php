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
// 		$generations = $table->getGenerationValueOptions();
// 		$filiations = $table->getFiliationValueOptions(false, false);
// 		$courses = $table->getCourseValueOptions();
// 		$houses = $table->getHouseValueOptions(false);
// 		$territories = $table->getTerritoryValueOptions();
		$roleTitles = $table->getRoleTitleValueOptions();
// 		$form->get('generation')->setValueOptions($generations);
// 		$form->get('course')->setValueOptions($courses);
// 		$form->get('homeTerritory')->setValueOptions($territories);
// 		$form->get('responsibleTerritory')->setValueOptions($territories);
		$form->get('roleTitle')->setValueOptions($roleTitles);
// 		$form->get('filiation')->setValueOptions($filiations);
// 		$form->get('house')->setValueOptions($houses);

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
