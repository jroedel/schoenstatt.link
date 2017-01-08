<?php
// Schoenstatt/View/Helper/EditPencil.php

namespace Schoenstatt\View\Helper;

use Zend\View\Helper\AbstractHelper;

class EditPencil extends AbstractHelper
{
    /**
     * @todo I should be getting this info from the config
     * @var unknown
     */
    protected $scopeRoutes = [
        'association'   => ['route' => 'associations/association/edit', 'key' => 'association_id'],
        'person'        => ['route' => 'persons/person/edit', 'key' => 'person_id'],
        'role'          => ['route' => 'roles/role/edit', 'key' => 'role_id'],
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
