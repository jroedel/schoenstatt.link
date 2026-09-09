<?php
namespace Schoenstatt\Service;

use JTranslate\Model\CountriesInfo;
use JTranslate\View\Helper\CountryName;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\Form\AdvancedSearchForm;

/**
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class AdvancedSearchFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var \Schoenstatt\Model\SchoenstattTable $table **/
        $table = $container->get('Schoenstatt\Model\SchoenstattTable');
        $form = new AdvancedSearchForm();
        $roleTitles = $table->getRoleTitleValueOptions();

        /** @var AssociationKindsService $kindsService */
        $kindsService = $container->get(AssociationKindsService::class);
        $kinds = $kindsService->getValueOptions();

        $form->get('roleTitle')->setValueOptions($roleTitles);
        $form->get('associationKind')->setValueOptions($kinds);

        //retrieve country names. Built here rather than resolved by name: `countryName` is
        //a plain class since 2026-09 and left the module's view_helpers config with the rest
        //of the batch-4 cluster, and there is no view-helper manager to ask any more.
        $countryNames = new CountryName($container->get(CountriesInfo::class));
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
