<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\Form\AssociationForm;
use Schoenstatt\Model\SchoenstattTable;
use Zend\Form\FormElementManager\FormElementManagerV2Polyfill;
use Schoenstatt\Form\RoleForm;

/**
 * Factory responsible of prepping the RoleForm
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class RoleFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var SchoenstattTable $table **/
		$table = $container->get ( SchoenstattTable::class);
		/** @var \Zend\I18n\Translator\Translator $translator */
		$translator = $container->get ( 'translator' );

		$associations = $table->getAssociationValueOptions();
		$roleTitles = $table->getRoleTitleValueOptions($translator);

		/** @var FormElementManagerV2Polyfill $formManager */
		$formManager = $container->get('FormElementManager');
		/** @var RoleForm $form */
		$form = $formManager->get(RoleForm::class, [], true);

		$form->get('associationId')->setValueOptions($associations);
		$form->get('roleTitle')->setValueOptions($roleTitles);
		return $form;
    }
}
