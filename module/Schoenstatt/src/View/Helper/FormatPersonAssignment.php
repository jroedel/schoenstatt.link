<?php
// Schoenstatt/View/Helper/FormatPersonAssignment.php

namespace Schoenstatt\View\Helper;

use Zend\View\Helper\AbstractHelper;

class FormatPersonAssignment extends AbstractHelper
{
    /**
     *
     * @param string $name
     * @param string $country
     * @param int $id
     * @param bool $editOption
     */
    public function __invoke($assignment, $editOption = false)
    {
        //if there's not enough info we won't do anything
        if (!$assignment && !is_array($assignment)) {
            return '';
        }
        //"Course Leader of Sanctuarium Vivum from 23/04/2016"
        $finalMarkup = '<strong>'.$this->view->escapeHtml($assignment['roleTitle']).'</strong> ';
        switch ($assignment['scope']) {
//          case 'Filiation':
//              if ($assignment['scopeName']) {
//                  echo $this->view->formatFiliation($assignment['scopeName'], null, $assignment['scopeId']);
//              } else {
//                  echo $this->view->translate("a filiation");
//              }
//              break;
//          case 'Course':
//              if ($assignment['scopeName']) {
//                  echo $this->view->formatCourse($assignment['scopeName'], $assignment['scopeId']);
//              } else {
//                  echo $this->view->translate("a course");
//              }
//             break;
//          case 'Territory':
//              if ($assignment['scopeName']) {
//                  echo $this->view->formatCourse($assignment['scopeName'], $assignment['scopeId']);
//              } else {
//                  echo $this->view->translate("a course");
//              }
//              break;
            case 'Community':
                break;
            default:
                $finalMarkup .= $this->view->translate("of").' ';
                $finalMarkup .= $this->view->formatScope(
                    $assignment['baseScope'],
                    $assignment['scopeName'],
                    $assignment['scopeId']
                );
                break;
        }
        echo ' ';
        
        if ($assignment['startDate']) {
            $finalMarkup .= ' '.$this->view->translate("starting").' '.$this->view->dateFormat(
                $assignment['startDate'],
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::NONE
            );
        }
        if ($assignment['endDate']) {
            $finalMarkup .= ' '.$this->view->translate("until").' '.$this->view->dateFormat(
                $assignment['endDate'],
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::NONE
            );
        }
        if ($assignment['assignmentId']) {
            $finalMarkup .= $this->view->editPencil('assignment', $assignment['assignmentId']);
        }
        return $finalMarkup;
    }
}
