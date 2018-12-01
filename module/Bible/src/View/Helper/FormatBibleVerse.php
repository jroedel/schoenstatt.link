<?php
namespace Bible\View\Helper;

use Zend\View\Helper\AbstractHelper;

class FormatBibleVerse extends AbstractHelper
{
    /**
     *
     * @param array $person
     * @param array $options
     *
     * available options:
     *  'display' => $this::FRIENDLY_FIRST_FIRST, (see class consts)
     *  'flag' => true,
     *  'link' => true,
     *  'displayEditPencil' => false,
     */
    public function __invoke($person, $options = [])
    {
    }
}
