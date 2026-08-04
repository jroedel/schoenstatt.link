<?php
namespace Books\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Model\DictionaryTable;
use Books\Form\DictionaryEntryForm;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class DictionaryEntryFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var DictionaryTable $table **/
        $table = $container->get(DictionaryTable::class);

        $links = $table->getLinksValueOptions();

        $form = new DictionaryEntryForm();
        $form->get('links')->setValueOptions($links);
        return $form;
    }
}
