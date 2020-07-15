<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\Stdlib\ResponseInterface;
use Books\Model\EventTextTable;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Model\SchoenstattTable;
use Zend\View\Model\ViewModel;

class BlogController extends SionController
{

    public function indexAction()
    {
        /** @var SionTable $table */
        $table      = $this->getSionTable();
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $objects    = $table->queryObjects($entity, ['kind' => EventTextTable::TEXT_KIND_BLOG]);
        $view = new ViewModel([
            'entity'    => $entity,
            'entitySpec' => $entitySpec,
            'objects'   => $objects,
        ]);

        return $view;
    }

    public function showAction()
    {
        $slug = $this->params()->fromRoute('slug');
        if (! isset($slug)) {
            $id = $this->getEntityIdParam('show');
            $entityObject = $this->getEntityObject($id);
            return $this->redirect()->toRoute('blog/blog-post', ['text_id' => $entityObject['textId'], 'slug' => $entityObject['slug']]);
        }
        $view = parent::showAction();
        if ($view instanceof ResponseInterface) {
            return $view;
        }
        $object = $view->getVariable('entity');
        if ($object['kind'] !== EventTextTable::TEXT_KIND_BLOG) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_INFO)
                ->addMessage('Blog entry not found');
            return $this->redirect()->toRoute('blog');
        }
        $schema = EventTextTable::getBlogPostSchema($object);
        $view->setVariable('schema', $schema);
        $view->setTemplate('books/blog/show');
        return $view;
    }

    public function saveDraftAction()
    {
        //the idea here is to create a new text (kind=blog-draft)
        //every minute or so we could in theory get a lost post back
    }

    /**
     * Makes sure this function returns the textId if passed a site-wide id
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
                $swValidator = new SchoenstattLinkIdentifier('text');
            }
            if (! $swValidator->isValid($id)) {
                throw new \Exception('Invalid site-wide id');
            }
            if (! isset($swFilter)) {
                $swFilter = new \Schoenstatt\Filter\SchoenstattLinkIdentifier('text');
            }
            $id = $swFilter->filter($id);
        } else {
            throw new \Exception('Invalid text id');
        }
        return $id;
    }

    /**
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::redirectAfterEdit()
     */
    public function redirectAfterEdit($id, $data = [], $form = null, $updatedObject = [])
    {
        return $this->redirect()->toRoute('blog/blog-post', ['sw_id' => $updatedObject['identifier'], 'slug' => $updatedObject['slug']]);
    }

    /**
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::redirectAfterCreate()
     */
    public function redirectAfterCreate($newId, $data = [], $form = null)
    {
        $swFilter = new ToSchoenstattLinkIdentifier('text');
        $identifier = $swFilter->filter($newId);
        $slug = SchoenstattTable::getSlug($data['title']);
        return $this->redirect()->toRoute('blog/blog-post', ['sw_id' => $identifier, 'slug' => $slug]);
    }

    /**
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::redirectAfterDelete()
     */
    protected function redirectAfterDelete($actionWasSuccessful = true)
    {
        return $this->redirect()->toRoute('blog');
    }
}
