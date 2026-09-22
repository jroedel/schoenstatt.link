<?php
namespace Books\Service;

use Psr\Container\ContainerInterface;
use Books\Model\EventTextTable;
use Books\Form\TextForm;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class TextFormFactory
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var EventTextTable $table **/
        $table = $container->get(EventTextTable::class);

        $keywords = $table->getTextTagsOptions(EventTextTable::TEXT_KIND_JK_TEXT);

        $form = new TextForm();
        $form->get('tags')->setValueOptions($keywords);
        return $form;
    }
}
