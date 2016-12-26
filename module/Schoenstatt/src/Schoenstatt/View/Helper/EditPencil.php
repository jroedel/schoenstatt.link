<?php
// Schoenstatt/View/Helper/EditPencil.php

namespace Schoenstatt\View\Helper;

use Zend\View\Helper\AbstractHelper;

class EditPencil extends AbstractHelper
{
    protected $scopeRoutes = [
        'course'    => ['route' => 'courses/course/edit', 'key' => 'course_id'],
        'generation'=> ['route' => 'generations/generation/edit', 'key' => 'generation_id'],
        'filiation' => ['route' => 'filiations/filiation/edit', 'key' => 'filiation_id'],
        'house'     => ['route' => 'houses/house/edit', 'key' => 'house_id'],
        'assignment'=> ['route' => 'roles/assignment/edit', 'key' => 'assignment_id'],
        'user'      => ['route' => 'juser/user/edit', 'key' => 'user_id'],
        'territory' => ['route' => 'territories/territory/edit', 'key' => 'territory_id'],
        'person'    => ['route' => 'fathers/father/edit', 'key' => 'person_id'],
        'person-misc'       => ['route' => 'fathers/father/edit', 'key' => 'person_id'],
        'person-contact'    => ['route' => 'fathers/father/edit-contact-info', 'key' => 'person_id'],
        'person-personal'   => ['route' => 'fathers/father/edit-personal-info', 'key' => 'person_id'],
        'person-private'    => ['route' => 'fathers/father/edit-private-info', 'key' => 'person_id'],
        'living-situation'  => ['route' => 'living-situations/living-situation/edit', 'key' => 'living_situation_id'],
    ];

    /**
     *
     * @param string $scope
     * @param int $id
     */
    public function __invoke($scope, $id)
    {
    	//if there's not enough info we won't do anything
    	if (!$id || $id=='' || !isset($this->scopeRoutes[$scope])) {
    		return '';
    	}

    	$finalMarkup = '';
    	if ($this->view->isAllowed('route/'.$this->scopeRoutes[$scope]['route'])) {
    		$finalMarkup .= ' <a href="';
    		if (isset($this->scopeRoutes[$scope]['queryRoute'])) {
                $finalMarkup .= $this->view->url($this->scopeRoutes[$scope]['queryRoute'], array(), array('query' => array($this->scopeRoutes[$scope]['key'] => $id)));
    		} else {
                $finalMarkup .= $this->view->url($this->scopeRoutes[$scope]['route'], array($this->scopeRoutes[$scope]['key'] => $id));
    		}
    		$finalMarkup .= '"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>';
    	}
    	return $finalMarkup;
    }
}
