<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;

class CompositionsController extends SionController
{
    /**
     * Makes sure this function returns the compositionId if passed a site-wide id
     *
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::getEntityIdParam()
     */
    protected function getEntityIdParam($action = 'show', $default = null)
    {
        static $swValidator;
        static $swFilter;
        $id = $this->params()->fromRoute('sw_id');
        if (isset($id)) {
            if (!isset($swValidator)) {
                $swValidator = new SchoenstattLinkIdentifier();
            }
            if (!$swValidator->isValid($id)) {
                throw new \Exception('Invalid site-wide id');
            }
            if (!isset($swFilter)) {
                $swFilter = new \Schoenstatt\Filter\SchoenstattLinkIdentifier();
            }
            $id = $swFilter->filter($id);
        } else {
            throw new \Exception('Invalid composition id');
        }
        return $id;
    }
}
