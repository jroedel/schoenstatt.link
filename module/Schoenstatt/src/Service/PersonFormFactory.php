<?php
namespace Schoenstatt\Service;

use Psr\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\PersonForm;

/**
 * Factory responsible of priming the PatresTable service
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class PersonFormFactory
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var \Schoenstatt\Model\SchoenstattTable $table **/
        $table = $container->get(SchoenstattTable::class);

        $persons = $table->getPersonValueOptions();
        $countryNames = $container->get('CountryValueOptions');

        $lifeCommunities = $table->getAssociationValueOptions(false, false);

        $personTags = $container->get('Schoenstatt\PersonTagsValueOptions');
//      $adminTags = $table->getPersonAdminTags();

        //`new`, not a plugin manager: `SionModel\Form\Fieldset::getFormFactory()`
        //reaches the element registry on its own, so there is nothing a container
        //could inject that the form does not already find.
        $form = new PersonForm();
        $form->init();

        $form->get('spousePersonId')->setValueOptions($persons);
        $form->get('country')->setValueOptions($countryNames);
        $form->get('lifeCommunity')->setValueOptions($lifeCommunities);
        $form->get('postCountry')->setValueOptions($countryNames);
        $form->get('personTags')->setValueOptions($personTags);
//      $form->get('adminTags')->setValueOptions($adminTags);
        return $form;
    }
}
