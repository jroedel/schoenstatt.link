<?php
namespace Schoenstatt\Controller;

use Zend\View\Model\JsonModel;
use SionModel\Controller\SionController;
use Zend\View\Model\ViewModel;
use JTranslate\Model\CountriesInfo;
use Zend\Filter\StripTags;
use BjyAuthorize\Exception\UnAuthorizedException;
use Zend\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Schoenstatt\Validator\TimeZone;

class AssociationsController extends SionController
{
    public function sendToNewUrl()
    {
        $associationId = $this->params()->fromRoute('association_id');
        /** @var \Schoenstatt\Model\SchoenstattTable $table */
        $table = $this->getSionTable();
        $object = $table->getSimpleAssociation($associationId);
        if (!isset($object)) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage(ucwords($this->entity).' not found.');
            $redirectRoute = 'associations';
            return $this->redirect()->toRoute($redirectRoute);
        }
        return $this->redirect()->toRoute('associations/association', ['sw_id' => $object['identifier']]);
    }

    /**
     *
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::updateEntityPostFormValidation()
     */
    public function updateEntityPostFormValidation($id, $data, $form)
    {
        $entity = $this->getEntity();
        /** @var SionTable $table **/
        $table = $this->getSionTable();

        //hack to make sure we call updateEntity with the int id
        $idFilter = new \Schoenstatt\Filter\SchoenstattLinkIdentifier();

        $table->updateEntity($entity, $idFilter->filter($id), $data);
        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
            ->addMessage(ucfirst($entity).' successfully updated.');
        $this->redirectAfterEdit($id);
    }

    public function editAction()
    {
        $view = parent::editAction();
        if ($view instanceof \Zend\Stdlib\ResponseInterface) {
            return $view;
        }
        $entity = $view->getVariable('entity');
        /** @var AssociationForm $form */
        $form = $view->getVariable('form');
        if (isset($entity['country'])) {
            $tzList = TimeZone::getTimeZoneValueOptions($entity['country']);
            if (!empty($tzList)) {
                $form->get('timeZoneId')->setValueOptions($tzList);
            }
        }
        if ('sch-shrine' === $entity['kind'] || 'sch-wayside-shrine' === $entity['kind']) {
            $publicNotes = $form->get('publicNotes');
            $publicNotes->setLabel('Visitor information (also Mass/Adoration/Confession information)');
            $publicNotes->setAttribute('placeholder', "Turn right at the first driveway after getting off the highway.

**Mass Times**: Sunday 11:00am, every 3rd Sunday 7pm

**Adoration**: Sunday 8pm

**Confession**: By appointment, please don't hesitate to call.");
        }
        return $view;
    }

    public function showAction()
    {
        $view = parent::showAction();
        if ($view instanceof \Zend\Stdlib\ResponseInterface) {
            return $view;
        }
        //set nationalOrganizations
        /** @var SchoenstattTable $table */
        $table = $this->getSionTable();
        $association = $view->getVariable('entity');
        if (!$this->zfcUserAuthentication()->hasIdentity()
            && $association['kind'] !== 'sch-shrine'
            && $association['kind'] !== 'sch-wayside-shrine'
        ) {
            return $this->redirect()->toRoute('welcome');
        }
        if ($association['kind'] == 'sch-national-movement' && !is_null($association['country'])) {
            $nationalOrganizations = $table->getNationalAssociations($association['country']);
            if (key_exists($association['associationId'], $nationalOrganizations)) {
                unset($nationalOrganizations[$association['associationId']]);
            }
            $association['nationalOrganizations'] = $nationalOrganizations;
            $view->setVariable('entity', $association);
        }
        $schema = $table->getAssociationSchema($association);
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
        if ($view instanceof \Zend\Stdlib\ResponseInterface) {
            return $view;
        }

        //check if we were passed a valid country param and set it in the form
        $countryParam = $this->params()->fromQuery('country');
        $parentParam = $this->params()->fromQuery('parentId');
        $nameParam = $this->params()->fromQuery('name');
        if (!$this->getRequest()->isPost() && ((!is_null($countryParam) &&
            is_string($countryParam) && strlen($countryParam) == 2) ||
                !is_null($parentParam) || !is_null($nameParam))
        ) {
            $form = $view->getVariable('form');
            $haveSetSomething = false;
            $countries = $form->get('country')->getValueOptions();
            if (!is_null($countryParam) && key_exists($countryParam, $countries)) {
                $form->get('country')->setValue($countryParam);
                $haveSetSomething = true;
            }
            $parents = $form->get('parentId')->getValueOptions();
            if (!is_null($parentParam) && key_exists($parentParam, $parents)) {
                $form->get('parentId')->setValue($parentParam);
                $haveSetSomething = true;
            }
            if (!is_null($nameParam)) {
                $filter = new StripTags();
                $form->get('name')->setValue($filter->filter($nameParam));
                $haveSetSomething = true;
            }
            if ($haveSetSomething) {
                $view->setVariable('form', $form);
            }
        }
        return $view;
    }

    public function doWorkAction()
    {
        $key = $this->params()->fromQuery('key', null);
        if (!isset($key)) {
            throw new UnAuthorizedException();
        }
        $config = $this->config['sion_model'];
        $apiKeys = isset($config['api_keys']) && is_array($config['api_keys']) ? $config['api_keys'] : [];
        if (!in_array($key, $apiKeys)) {
            throw new UnAuthorizedException();
        }
        /**
         * @var SchoenstattTable $table
         */
        $table = $this->getSionTable();
        $result = $table->updateAssociationMd5s();
        return ['result' => $result];
    }

    public function createDiocesesAction()
    {
        //get entity

        //make sure it's a national or sch-regional-organization, this action doesn't apply to any other associations

        //validate the form

        //insert the diocese and associated branches

        //for now just insert the info I want :D
        $request = $this->getRequest();
        if ($request->isPost()) {
            $chileNationalMovement = 8;
            $data = $this->getChileInfo();
            foreach ($data as $diocesanInfo) {
                $this->createDiocesanMovement($chileNationalMovement, $diocesanInfo);
            }
        }
        return new ViewModel([

        ]);
    }

    /**
     * Create a new diocese associated with the national movement and selected
     * @param int $parentMovementId
     * @param mixed[] $data
     */
    public function createDiocesanMovement($parentMovementId, $data)
    {
        /** @var SchoenstattTable $table */
        $table = $this->getSionTable();
        $parentData = $table->getAssociation($parentMovementId);
        if ($parentData['kind'] != 'sch-national-movement' && $parentData['kind'] != 'sch-regional-organization') {
            throw new \Exception('Parent movement should be a national movement.');
        }
        $data['kind'] = 'sch-diocesan-movement';
        $data['parentId'] = $parentMovementId;
        $data['country'] = $parentData['country'];
        $newId = $table->createEntity('association', $data);
        return $newId;
    }

    public function importAction()
    {
        return;
        /** @var SchoenstattTable $table */
        $table = $this->getSionTable();
        /** @var CountriesInfo $countryInfo */
        $countryInfo = $this->services[CountriesInfo::class];
        $countryNames = $countryInfo->getCountryNames();
        $entities = $table->getAssociations();
        $return = [];
        foreach ($entities as $associationId => $association) {
            if ($association['kind'] == 'sch-national-movement' && $association['country'] &&
                key_exists($association['country'], $countryNames)
            ) {
                $newName = $countryNames[$association['country']];
                $data = [
                    'name' => $newName
                ];
                $return[$associationId] = $newName;
                $table->updateEntity('association', $associationId, $data);
            }
        }
        return new JsonModel(['updated' => $return]);
        return; //disable to prevent duplicate records being inserted
        $toImport = [
            ['name' => 'Schoenstatt Movement of Argentina', 'country' => 'AR',
                'publicNotes' => 'Formally constituted', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Australia', 'country' => 'AU', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Brazil', 'country' => 'BR', 'publicNotes' => 'Formally constituted',
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Burundi', 'country' => 'BI', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Chile', 'country' => 'CL', 'publicNotes' => 'Formally constituted',
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Germany', 'country' => 'DE',
                'publicNotes' => 'Formally constituted', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Ecuador', 'country' => 'EC', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of India-Kerala', 'country' => 'IN',
                'publicNotes' => 'No national presidium', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of India-Tamil Nadu', 'country' => 'IN',
                'publicNotes' => 'No national presidium', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Mexico', 'country' => 'MX',
                'publicNotes' => 'No national presidium', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Austria', 'country' => 'AT', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Paraguay', 'country' => 'PT', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Poland', 'country' => 'PL', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Portugal', 'country' => 'PT',
                'publicNotes' => 'National presidium works, but is not yet approved by the general presidium',
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Switzerland', 'country' => 'CH',
                'publicNotes' => 'Formally constituted', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Spain', 'country' => 'ES', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Czech Republic', 'country' => 'CZ', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of USA-North', 'country' => 'US',
                'publicNotes' => 'A regional presidium', 'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Colombia', 'country' => 'CO', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Cuba', 'country' => 'CU', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Peru', 'country' => 'PE', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of South Africa', 'country' => 'ZA', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of USA-South', 'country' => 'US', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of England', 'country' => 'GB', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Italy', 'country' => 'IT',
                'publicNotes' => 'Not official, it was a creation through the general presidium, '
                .'that hasn\'t continued since the time of Fr. Ludovico Tedeschi.',
                'kind' => 'sch-national-movement',],
            ['name' => 'Schoenstatt Movement of Croatia', 'country' => 'HR', 'publicNotes' => null,
                'kind' => 'sch-national-movement',],
        ];

        /** @var SchoenstattTable $table */
        $table = $this->getSionTable();
        foreach ($toImport as $data) {
            $newId = $table->createEntity('association', $data);
            $table->createAssociatedRoles($newId, $data['kind']);
        }
        return new JsonModel(['imported' => $toImport]);
    }

    protected function getChileInfo()
    {
//         return [[
//             'name' => 'Test3',
//             'addShrineMinistry' => false,
//             'addPilgrimMovement' => false,
//             'addPilgrimMother' => true,
//             'addProfessionalsBranch' => false,
//             'addMadrugadores' => false,
//             'addWomensYouthBranch' => false,
//             'addMensYouthBranch' => false,
//             'addWomensBranch' => false,
//             'addMothersBranch' => true,
//             'addMensBranch' => false,
//             'addFamilyBranch' => true,
//         ]];
        $data = [
//             [
//                 'name' => 'Aconcagua',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Agua Santa',
//                 'addShrineMinistry' => true,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Angol',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => false,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => false,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Antofagasta',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => true,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Arica',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Bellavista',
//                 'addShrineMinistry' => true,
//                 'addPilgrimMovement' => true,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => true,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => true,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => true,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Calama',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Campanario',
//                 'addShrineMinistry' => true,
//                 'addPilgrimMovement' => true,
//                 'addPilgrimMother' => false,
//                 'addProfessionalsBranch' => true,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => true,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Chillán',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => false,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Colina',
//                 'addShrineMinistry' => true,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => false,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Concepción',
//                 'addShrineMinistry' => true,
//                 'addPilgrimMovement' => true,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Copiapó',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => false,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Coronel',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => false,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Coyhaique',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => false,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => false,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Curicó',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Iquique',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'La Serena',
//                 'addShrineMinistry' => true,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Linares',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => false,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Los Ángeles',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Los Pinos',
//                 'addShrineMinistry' => true,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Maipo',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Maipú',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Melipilla',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => false,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Monte Schoenstatt',
//                 'addShrineMinistry' => true,
//                 'addPilgrimMovement' => true,
//                 'addPilgrimMother' => false,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => true,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Nuevo Belén',
//                 'addShrineMinistry' => true,
//                 'addPilgrimMovement' => true,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Osorno',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => false,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => false,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Providencia',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => true,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Puerto Montt',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Punta Arenas',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => false,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Quillota',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => false,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Rancagua',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => true,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => true,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'San Fernando',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => false,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Talca',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Temuco',
//                 'addShrineMinistry' => true,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => true,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => true,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Valdivia',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => false,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => true,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => false,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
//             [
//                 'name' => 'Vallenar',
//                 'addShrineMinistry' => false,
//                 'addPilgrimMovement' => false,
//                 'addPilgrimMother' => true,
//                 'addProfessionalsBranch' => false,
//                 'addMadrugadores' => false,
//                 'addWomensYouthBranch' => false,
//                 'addMensYouthBranch' => false,
//                 'addWomensBranch' => false,
//                 'addMothersBranch' => false,
//                 'addMensBranch' => false,
//                 'addFamilyBranch' => true,
//             ],
            [
                'name' => 'Villa Alemana',
                'addShrineMinistry' => false,
                'addPilgrimMovement' => false,
                'addPilgrimMother' => true,
                'addProfessionalsBranch' => false,
                'addMadrugadores' => false,
                'addWomensYouthBranch' => false,
                'addMensYouthBranch' => false,
                'addWomensBranch' => false,
                'addMothersBranch' => false,
                'addMensBranch' => false,
                'addFamilyBranch' => true,
            ],
        ];
        return $data;
    }
}
