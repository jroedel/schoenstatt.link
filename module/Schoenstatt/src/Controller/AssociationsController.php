<?php
namespace Schoenstatt\Controller;

use Laminas\View\Model\JsonModel;
use SionModel\Controller\MaintenanceKeyTrait;
use SionModel\Controller\SionController;
use Laminas\Filter\StripTags;
use Schoenstatt\Validator\TimeZone;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;

class AssociationsController extends SionController
{
    use MaintenanceKeyTrait;

    public function sendToNewUrlAction()
    {
        $id = $this->params()->fromRoute('association_id');
        $swId = $this->params()->fromRoute('sw_id');
        if (isset($id)) {
            $object = $this->getEntityObject($id);
            if (isset($object)) {
                $locale = \Locale::getDefault();
                /**
                 * @var \Laminas\Http\Response $response
                 */
                $response = $this->redirect()->toRoute(
                    'association',
                    ['sw_id' => $object['identifier'], 'slug' => $object['slugByLocale'][$locale]]
                );
                $response->setStatusCode(301);
                return $response;
            }
        } elseif (isset($swId) && is_string($swId)) {
            $usePreApril2020Format = strlen($swId) === 8;
            $swFilter = new \Schoenstatt\Filter\SchoenstattLinkIdentifier('association', $usePreApril2020Format);
            //we can assume this will work because of the parameter constraints on the route
            $id = $swFilter->filter($swId);
            $object = $this->getEntityObject($id);
            if (isset($object)) {
                $locale = \Locale::getDefault();
                /**
                 * @var \Laminas\Http\Response $response
                 */
                $response = $this->redirect()->toRoute(
                    'association',
                    ['sw_id' => $object['identifier'], 'slug' => $object['slugByLocale'][$locale]]
                );
                $response->setStatusCode(301);
                return $response;
            }
        }
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
        ->addMessage(ucwords($entity) . ' not found.');
        $redirectRoute = $entitySpec->indexRoute ? $entitySpec->indexRoute : $this->getDefaultRedirectRoute();
        return $this->redirect()->toRoute($redirectRoute);
    }

    /**
     * Makes sure this function returns the associationId if passed a site-wide id
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
            $id = $this->params()->fromRoute('association_id');
        }
        return $id;
    }

    public function editAction()
    {
        $view = parent::editAction();
        if ($view instanceof \Laminas\Stdlib\ResponseInterface) {
            return $view;
        }
        $entity = $view->getVariable('entity');
        /** @var AssociationForm $form */
        $form = $view->getVariable('form');
        if (isset($entity['country'])) {
            $tzList = TimeZone::getTimeZoneValueOptions($entity['country']);
            if (! empty($tzList)) {
                $form->get('timeZoneId')->setValueOptions($tzList);
            }
        }
        if ('sch-shrine' === $entity['kind'] || 'sch-wayside-shrine' === $entity['kind']) {
            $publicNotes = $form->get('publicNotes');
            $publicNotes->setLabel('First-time visitor information');
            $publicNotes->setAttribute('placeholder', "Turn right at the first driveway after getting off the highway.

**Confession**: By appointment, please don't hesitate to call.");
        }
        return $view;
    }

    /**
     *
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::getEntityObject()
     */
    public function getEntityObject($id)
    {
        if (isset($this->object[$id])) {
            return $this->object[$id];
        }
        $table = $this->getSionTable();
        $this->object[$id] = $table->getAssociation($id);
        return $this->object[$id];
    }

    public function showAction()
    {
        $view = parent::showAction();
        if ($view instanceof \Laminas\Stdlib\ResponseInterface) {
            return $view;
        }
        //set nationalOrganizations
        /** @var \Schoenstatt\Model\SchoenstattTable $table */
        $table = $this->getSionTable();
        $association = $view->getVariable('entity');
        if (! $this->zfcUserAuthentication()->hasIdentity()
            && $association['kind'] !== 'sch-shrine'
            && $association['kind'] !== 'sch-wayside-shrine'
        ) {
            return $this->redirect()->toRoute('welcome');
        }
        if ($association['kind'] === 'sch-national-movement' && isset($association['country'])) {
            $nationalOrganizations = $table->getNationalAssociations($association['country']);
            if (isset($nationalOrganizations[$association['associationId']])) {
                unset($nationalOrganizations[$association['associationId']]);
            }
            $association['nationalOrganizations'] = $nationalOrganizations;
            $view->setVariable('entity', $association);
        }
        /** @var CatholicChurch $schema */
        $schema = $table->getAssociationSchemaV1($association);
        $view->setVariable('schema', $schema);

        $changes = $table->getEntityChanges('association', $association['associationId']);
        $view->setVariable('changes', $changes);

        return $view;
    }

    /**
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::createAction()
     */
    public function createAction()
    {
        $view = parent::createAction();
        if ($view instanceof \Laminas\Stdlib\ResponseInterface) {
            return $view;
        }

        //check if we were passed a valid country param and set it in the form
        $countryParam = $this->params()->fromQuery('country');
        $parentParam = $this->params()->fromQuery('parentId');
        $nameParam = $this->params()->fromQuery('name');
        $tzParam = $this->params()->fromQuery('timeZoneId');
        $kindParam = $this->params()->fromQuery('kind');
        if (! $this->getRequest()->isPost() && ((isset($countryParam) &&
            is_string($countryParam) && strlen($countryParam) == 2) ||
            isset($parentParam) || isset($nameParam)) || isset($tzParam) || isset($kindParam)
        ) {
            $form = $view->getVariable('form');
            $haveSetSomething = false;
            $countries = $form->get('country')->getValueOptions();
            if (! is_null($countryParam) && key_exists($countryParam, $countries)) {
                $form->get('country')->setValue($countryParam);
                $haveSetSomething = true;
            }
            $parents = $form->get('parentId')->getValueOptions();
            if (! is_null($parentParam) && key_exists($parentParam, $parents)) {
                $form->get('parentId')->setValue($parentParam);
                $haveSetSomething = true;
            }
            if (isset($nameParam)) {
                $filter = new StripTags();
                $form->get('name')->setValue($filter->filter($nameParam));
                $haveSetSomething = true;
            }
            if (isset($tzParam)) {
                $filter = new StripTags();
                $form->get('timeZoneId')->setValue($filter->filter($tzParam));
                $haveSetSomething = true;
            }
            if (isset($kindParam)) {
                $filter = new StripTags();
                $form->get('kind')->setValue($filter->filter($kindParam));
                $haveSetSomething = true;
            }
            if ($haveSetSomething) { //@todo this shouldn't be necessary as we have the same object reference
                $view->setVariable('form', $form);
            }
        }
        return $view;
    }
}
