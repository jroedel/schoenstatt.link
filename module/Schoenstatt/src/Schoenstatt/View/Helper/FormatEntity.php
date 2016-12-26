<?php
// Application/View/Helper/FormatEntity.php

namespace Schoenstatt\View\Helper;

use Zend\View\Helper\AbstractHelper;

class FormatEntity extends AbstractHelper
{

    /**
     *
     * @param array $person
     * @param array $options
     *
     */
    public function __invoke($entityType, $data, $options = [])
    {
        switch ($entityType) {
            case 'person':
                return $this->view->formatPerson($data, $options);
                break;

            default:
                throw new \InvalidArgumentException('Unsupported entity passed to FormatEntity');
            break;
        }
    }
}
