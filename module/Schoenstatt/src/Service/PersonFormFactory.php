<?php
namespace Schoenstatt\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\PersonForm;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class PersonFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var \Schoenstatt\Model\SchoenstattTable $table **/
        $table = $container->get(SchoenstattTable::class);

        $persons = $table->getPersonValueOptions();
        $countryNames = $container->get('CountryValueOptions');

        $lifeCommunities = $table->getAssociationValueOptions(false, false);

        $personTags = $container->get('Schoenstatt\PersonTagsValueOptions');
//      $adminTags = $table->getPersonAdminTags();

        /** @var FormElementManagerV2Polyfill $formManager */
        $formManager = $container->get('FormElementManager');
        /** @var \Schoenstatt\Form\PersonForm $form */
        $form = $formManager->get(PersonForm::class, [], true);

        $form->get('spousePersonId')->setValueOptions($persons);
        $form->get('country')->setValueOptions($countryNames);
        $form->get('lifeCommunity')->setValueOptions($lifeCommunities);
        $form->get('postCountry')->setValueOptions($countryNames);
        $form->get('personTags')->setValueOptions($personTags);
//      $form->get('adminTags')->setValueOptions($adminTags);
        return $form;
    }
}
