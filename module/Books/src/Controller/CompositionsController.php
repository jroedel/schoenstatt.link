<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Laminas\Stdlib\ResponseInterface;
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
            if (! isset($swValidator)) {
                $swValidator = new SchoenstattLinkIdentifier();
            }
            if (! $swValidator->isValid($id)) {
                throw new \Exception('Invalid site-wide id');
            }
            if (! isset($swFilter)) {
                $swFilter = new \Schoenstatt\Filter\SchoenstattLinkIdentifier();
            }
            $id = $swFilter->filter($id);
        } else {
            throw new \Exception('Invalid composition id');
        }
        return $id;
    }

    public function indexAction()
    {
        $view = parent::indexAction();

        $languages = $this->getSionTable()->getLanguageNames();
        $view->setVariable('languageNames', $languages);
        return $view;
    }

    public function showAction()
    {
        $view = parent::showAction();
        if ($view instanceof ResponseInterface) {
            return $view;
        }
        $object = $view->getVariable('entity');
        /** @var \Books\Model\MusicTable $table */
        $table = $this->getSionTable();
        $schema = $table->getCompositionSchemaV1($object);
        $view->setVariable('schema', $schema);
        return $view;
    }
}
