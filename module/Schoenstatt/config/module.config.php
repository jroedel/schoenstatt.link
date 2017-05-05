<?php

use SionModel\Problem\EntityProblem;

return [
    'controllers' => [
        'invokables' => [
            'Schoenstatt\Controller\Schoenstatt'    => 'Schoenstatt\Controller\SchoenstattController',
            'Schoenstatt\Controller\Persons'        => 'Schoenstatt\Controller\PersonsController',
            'Schoenstatt\Controller\Associations'   => 'Schoenstatt\Controller\AssociationsController',
            'Schoenstatt\Controller\Admin'          => 'Schoenstatt\Controller\AdminController',
            'Schoenstatt\Controller\Assignments'    => 'Schoenstatt\Controller\AssignmentsController',
            'Schoenstatt\Controller\Roles'          => 'Schoenstatt\Controller\RolesController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Schoenstatt\Model\SchoenstattTable' => 'Schoenstatt\Service\SchoenstattTableFactory',
            'Schoenstatt\Form\PersonForm'        => 'Schoenstatt\Service\PersonFormFactory',
            'Schoenstatt\Form\AssignmentForm'    => 'Schoenstatt\Service\AssignmentFormFactory',
            'Schoenstatt\Form\AssociationForm'   => 'Schoenstatt\Service\AssociationFormFactory',
            'Schoenstatt\Form\RoleForm'          => 'Schoenstatt\Service\RoleFormFactory',
            'Schoenstatt\Config'                 => 'Schoenstatt\Service\ConfigServiceFactory',
            'Schoenstatt\Form\ImportFatherForm'  => 'Schoenstatt\Service\ImportFatherFormFactory',
            'Schoenstatt\AssociationKindsValueOptions' => 'Schoenstatt\Service\AssociationKindsValueOptionsFactory',
            'Schoenstatt\PersonTagsValueOptions' => 'Schoenstatt\Service\PersonTagsValueOptionsFactory',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            'Schoenstatt' => __DIR__ . '/../view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'formatEntity'          => 'Schoenstatt\Service\FormatEntityFactory',
            'formatAssociation'     => 'Schoenstatt\Service\FormatAssociationFactory',
        ],
        'invokables' => [
            'clipboardButton'		=> 'Schoenstatt\View\Helper\ClipboardButton',
        	'formatPerson'          => 'Schoenstatt\View\Helper\FormatPerson',
            'languageChooser'       => 'Schoenstatt\View\Helper\LanguageChooser',
        ],
    ],

    'bjyauthorize' => [
            // Resource providers to be used to load all available resources into Zend\Permissions\Acl\Acl
            // Keys are the provider service names, values are the options to be passed to the provider
            'resource_providers'    => [
                'Schoenstatt\Model\SchoenstattTable' => [],
            ],

            // Rule providers to be used to load all available rules into Zend\Permissions\Acl\Acl
            // Keys are the provider service names, values are the options to be passed to the provider
            'rule_providers'        => [
                'Schoenstatt\Model\SchoenstattTable' => [],
            ],
    ],
    'schoenstatt' => [
        'person_tags' => [ // they will appear in the value options in this order, they will be applied according to the sort order
            'bishop' => [
                'title' => 'Bish.',
                'label' => 'Bishop',
                'sort'  => 30,
            ],
            'monsignor' => [
                'title' => 'Msgr.',
                'label' => 'Monsignor',
                'sort'  => 40,
            ],
            'priest' => [
                'title' => 'Fr.',
                'label' => 'Priest',
                'sort'  => 50,
            ],
            'deacon' => [
                'title' => 'D.',
                'label' => 'Deacon',
                'sort'  => 60,
            ],
            'sister' => [
                'title' => 'Sr.',
                'label' => 'Sister',
                'sort'  => 70,
            ],
            'doctor' => [
                'title' => 'Dr.',
                'label' => 'Doctor',
                'sort'  => 60,
            ],
            'professor' => [
                'title' => 'Prof.',
                'label' => 'Professor',
                'sort'  => 20,
            ],
            'couple' => [
                'label' => 'Married couple',
                'sort'  => 90,
            ],
            'mr' => [
                'label' => 'Mr.',
                'title' => 'Mr.',
                'sort'  => 10,
            ],
            'ms' => [
                'label' => 'Ms.',
                'title' => 'Ms.',
                'sort'  => 10,
            ],
        ],
        'association_kinds' => [
            'sch-movement-international-structure' => [
                'sort'  => 100,
                'label' => 'Schoenstatt movement international structure',
                'default_roles' => [
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 90,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-institute' => [
                'label' => 'Schoenstatt institute',
                'sort'  => 200,
                'default_roles' => [
                    [
                        'roleTitle' => 'General superior',
                        'singlePosition' => true,
                        'sort' => 1,
                        'mainRole' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'First councilor',
                        'singlePosition' => true,
                        'sort' => 2,
                        'mainRole' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'Councilor',
                        'singlePosition' => false,
                        'sort' => 3,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-federation-international-structure' => [
                'sort'  => 200,
                'label' => 'Federation international structure',
                'default_roles' => [
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 90,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-national-movement' => [
                'label' => 'Schoenstatt national movement',
                'sort'  => 300,
                'default_roles' => [
                    [
                        'roleTitle' => 'Movement director',
                        'singlePosition' => true,
                        'sort' => 20,
                        'mainRole' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'Presidium president',
                        'singlePosition' => true,
                        'sort' => 21,
                        'mainRole' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Coordinating Sister',
                        'singlePosition' => true,
                        'sort' => 22,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-national-federation' => [
                'label' => 'Schoenstatt national federation',
                'sort'  => 400,
                'default_roles' => [
                    [
                        'roleTitle' => 'General superior',
                        'singlePosition' => true,
                        'sort' => 10,
                        'mainRole' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'Councilor',
                        'singlePosition' => false,
                        'sort' => 15,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-diocesan-movement' => [
                'label' => 'Schoenstatt diocesan movement',
                'sort'  => 500,
                'default_roles' => [
                    [
                        'roleTitle' => 'Diocesan coordinator',
                        'singlePosition' => true,
                        'sort' => 50,
                        'mainRole' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'Committee member',
                        'singlePosition' => false,
                        'sort' => 55,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-league-branch' => [
                'label' => 'Schoenstatt league branch',
                'sort'  => 600,
                'default_roles' => [
                    [
                        'roleTitle' => 'Branch leader',
                        'singlePosition' => true,
                        'sort' => 5,
                        'mainRole' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'Branch moderator',
                        'singlePosition' => true,
                        'sort' => 10,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 70,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-school' => [
                'label' => 'Schoenstatt school',
                'sort'  => 650,
                'default_roles' => [
                    [
                        'roleTitle' => 'Principal',
                        'singlePosition' => true,
                        'sort' => 5,
                        'mainRole' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'President',
                        'singlePosition' => true,
                        'sort' => 10,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Board member',
                        'singlePosition' => false,
                        'sort' => 70,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Pastoral director',
                        'singlePosition' => false,
                        'sort' => 80,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Movement contact',
                        'singlePosition' => false,
                        'sort' => 80,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-other' => [
                'label' => 'Other Schoenstatt entity',
                'sort'  => 700,
                'default_roles' => [],
            ],
            'legal-entity' => [
                'label' => 'Legal entity',
                'sort'  => 800,
                'default_roles' => [
                    [
                        'roleTitle' => 'President',
                        'singlePosition' => true,
                        'sort' => 80,
                        'mainRole' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 85,
                        'mainRole' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
        ],
        'post_place_line_format' => ':zip :cityState',
        'post_place_line_format_by_country' => [
            'US' => ':cityState :zip',
            'CL' => ':cityState :zip',
        ],
        'url_map' => [
            'g+' => [
                'android'   => '%s',
                'ios'       => '%s',
                'default'   => '%s',
                'logo'      => 'img/g+.png',
                'label'     => 'G+',
            ],
            'skype' => [
                'android'   => 'skype:%s?call',
                'ios'       => 'skype:%s?call',
                'default'   => 'skype:%s?call',
                'logo'      => 'img/skype.png',
                'userKey'   => 'skypeUser',
                'label'     => 'Skype',
            ],
            'instagram' => [
                'android'   => 'https://www.instagram.com/%s',
                'ios'       => 'instagram://user?username=%s',
                'default'   => 'https://www.instagram.com/%s',
                'logo'      => 'img/instagram.png',
                'userKey'   => 'instagramUser',
                'label'     => 'Instagram',
            ],
            'slack' => [
                'android'   => 'https://schoenstatt-fathers.slack.com/messages/%s/',
                'ios'       => 'https://schoenstatt-fathers.slack.com/messages/%s/',
                'default'   => 'https://schoenstatt-fathers.slack.com/messages/%s/',
                'logo'      => 'img/slack.png',
                'userKey'   => 'slackUser',
                'label'     => 'Slack',
            ],
            'twitter' => [
                'android'   => 'https://twitter.com/%s',
                'ios'       => 'twitter://user?screen_name=%s',
                'default'   => 'https://twitter.com/%s',
                'logo'      => 'img/twitter.png',
                'userKey'   => 'twitterUser',
                'label'     => 'Twitter',
            ],
            'facebook' => [
                'android'   => '%s',
                'ios'       => '%s',
                'default'   => '%s',
                'logo'      => 'img/facebook.png',
                'userKey'   => 'facebookUrl',
                'label'     => 'Facebook',
            ],
            'blog' => [
                'logo'      => 'img/blogger.png',
                'label'     => 'Blog',
            ],
        ],
        'excel_columns' => [
            'fullName'          => 'Name',
//             'condition'         => 'Condition',
            'country'           => 'Home country',
            'email'             => 'Email',
            'cellPhone'         => 'Cell phone',
            'birthDate'         => 'Birthday',
            'deathDate'         => 'Death date',
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/admin',
                    'defaults' => [
                        'controller' => 'Schoenstatt\Controller\Admin',
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'data-problems' => [
                        'type'    => 'Zend\Mvc\Router\Http\Literal',
                        'options' => [
                            'route'    => '/data-problems',
                            'defaults' => [
                                'controller' => 'Schoenstatt\Controller\Admin',
                                'action'     => 'dataProblems',
                            ],
                        ],
                    ],
                    'import-father' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/import-father',
                            'defaults' => [
                                'controller' => 'Schoenstatt\Controller\Admin',
                                'action'     => 'importFather',
                            ],
                        ],
                    ],
                    'moderate' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/moderate[/:suggestion_id]',
                            'defaults' => [
                                'controller' => 'Schoenstatt\Controller\Admin',
                                'action'     => 'moderate',
                            ],
							'constraints' => [
								'suggestion_id' => '[0-9]{1,5}',
							],
                        ],
                        'may_terminate' => true,
                    ],
                ],
            ],
            'home' => [
                'type'    => 'Literal',
                'options' => [
                    // Change this to something specific to your module
                    'route'    => '/',
                    'defaults' => [
                        // Change this value to reflect the namespace in which
                        // the controllers for your module are found
                        '__NAMESPACE__' => 'Schoenstatt\Controller',
                        'controller'    => 'Schoenstatt',
                        'action'        => 'index',
                    ],
                ],
                'may_terminate' => true,
            ],
            'persons' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/persons',
                    'defaults' => [
                        'controller' => 'Schoenstatt\Controller\Persons',
                        'action'     => 'search',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'search' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/search',
                            'defaults' => [
                                'action'     => 'search',
                            ],
                        ],
                    ],
                    'create' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'person' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/:person_id',
                            'constraints' => [
                                'person_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'action'     => 'show',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'edit' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action'     => 'edit',
                                        'entity'    => 'person'
                                    ],
                                ],
                            ],
                            'suggest' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/suggest',
                                    'defaults' => [
                                        'action'     => 'suggest',
                                    ],
                                ],
                            ],
                            'moderate' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'    => '/moderate/:suggestion_id',
                                    'constraints' => [
                                        'suggestion_id' => '[0-9]{1,5}',
                                    ],
                                    'defaults' => [
                                        'action'     => 'moderate',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'assignments' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/assignments',
                    'defaults' => [
                        'controller' => 'Schoenstatt\Controller\Assignments',
                        'action'     => 'search',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'search' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/search',
                            'defaults' => [
                                'action'     => 'search',
                            ],
                        ],
                    ],
                    'create' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'assignment' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/:assignment_id',
                            'constraints' => [
                                'assignment_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'action'     => 'show',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'edit' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action'     => 'edit',
                                    ],
                                ],
                            ],
                            'suggest' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/suggest',
                                    'defaults' => [
                                        'action'     => 'suggest',
                                    ],
                                ],
                            ],
                            'moderate' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'    => '/moderate/:suggestion_id',
                                    'constraints' => [
                                        'suggestion_id' => '[0-9]{1,5}',
                                    ],
                                    'defaults' => [
                                        'action'     => 'moderate',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'roles' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/roles',
                    'defaults' => [
                        'controller' => 'Schoenstatt\Controller\Roles',
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'create' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'role' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/:role_id',
                            'constraints' => [
                                'role_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'action'     => 'show',
                            ],
                        ],
                        'may_terminate' => false, //no show action
                        'child_routes' => [
                            'edit' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action'     => 'edit',
                                        'entity'    => 'role'
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'associations' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/associations',
                    'defaults' => [
                        'controller' => 'Schoenstatt\Controller\Associations',
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'association' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/:association_id',
                            'constraints' => [
                                'course_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'controller' => 'Schoenstatt\Controller\Associations',
                                'action'     => 'show',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'edit' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action'     => 'edit',
                                    ],
                                ],
                            ],
                            'suggest' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/suggest',
                                    'defaults' => [
                                        'action'     => 'suggest',
                                    ],
                                ],
                            ],
                            'moderate' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'    => '/moderate/:suggestion_id',
                                    'constraints' => [
                                        'suggestion_id' => '[0-9]{1,5}',
                                    ],
                                    'defaults' => [
                                        'action'     => 'moderate',
                                    ],
                                ],
                            ],
                            'delete' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/delete',
                                    'defaults' => [
                                        'action'     => 'delete',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'create' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'controller' => 'Schoenstatt\Controller\Associations',
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'import' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/import',
                            'defaults' => [
                                'controller' => 'Schoenstatt\Controller\Associations',
                                'action'     => 'import',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'sion_model' => [
        'problem_providers' => [
            'Schoenstatt\Model\SchoenstattTable',
        ],
        'problem_specifications' => [
            'person-no-email' => [
                'entity'            => 'person',
                'defaultSeverity'   => EntityProblem::SEVERITY_ERROR,
                'text'              => 'No email associated with person',
            ],
            'person-invalid-phone-number' => [
                'entity'            => 'person',
                'defaultSeverity'   => EntityProblem::SEVERITY_WARNING,
                'text'              => 'Invalid phone number associated with person',
            ],
            'association-no-main-role' => [
                'entity'            => 'association',
                'defaultSeverity'   => EntityProblem::SEVERITY_WARNING,
                'text'              => 'No main role specified for national association',
            ],
            'association-multi-main-role' => [
                'entity'            => 'association',
                'defaultSeverity'   => EntityProblem::SEVERITY_ERROR,
                'text'              => 'Multiple main roles specified for association',
            ],
        ],
        'entities' => [
            'person' => [
                'table_name' => 'sch_persons',
                'table_key' => 'PersonId',
                'entity_key_field' => 'personId',
                'get_object_function' => 'getPerson',
                'name_field' => 'fullName',
                'report_changes' => true,
                'has_dedicated_suggest_form' => false,
//                 'scope' => 'Person',
                'database_bound_data_preprocessor' => 'preprocessPerson', //this will separate the nationality array
                'required_columns_for_creation' => [ //required for creation
        	    ],
//                 'moderate_route' => 'persons/person/moderate',
                'moderate_route_entity_key' => 'person_id',
                'show_route' => 'fathers/father',
                'show_route_key' => 'person_id',
                'show_route_key_field' => 'personId',
                'show_route' => 'fathers/father',
                'show_route_key' => 'person_id',
                'show_route_key_field' => 'personId',
                'edit_route' => 'persons/person/edit',
                'edit_route_key' => 'person_id',
                'edit_route_key_field' => 'personId',
                'text_columns' => [
//         	        'adminNotes',
//         	        'publicNotes',
                ],
                'date_columns' => [
                    'publicNotesUpdatedOn',
                    'adminNotesUpdatedOn',
                    'birthDate',
                    'nameDay',
                    'deathDate',
                    'emailsUpdatedOn',
                    'phonesUpdatedOn',
                    'updatedOn',
                    'createdOn',
                    'dataSourceUpdatedOn',
                ],
                'many_to_one_update_columns' => [
                    'email'                     => 'emails',
                    'email2'                    => 'emails',
                    'cellPhone'                 => 'phones',
                    'cellPhoneHasWhatsApp'      => 'phones',
                    'phone1'                    => 'phones',
                    'phone1Label'               => 'phones',
                    'phone2'                    => 'phones',
                    'phone2Label'               => 'phones',
                    'phone3'                    => 'phones',
                    'phone3Label'               => 'phones',

                    'email'                     => 'contactInfo',
                    'email2'                    => 'contactInfo',
                    'skypeUser'                 => 'contactInfo',
                    'cellPhone'                 => 'contactInfo',
                    'cellPhoneHasWhatsApp'      => 'contactInfo',
                    'phone1'                    => 'contactInfo',
                    'phone1Label'               => 'contactInfo',
                    'phone2'                    => 'contactInfo',
                    'phone2Label'               => 'contactInfo',
                    'phone3'                    => 'contactInfo',
                    'phone3Label'               => 'contactInfo',
                    'url1'                      => 'contactInfo',
                    'url1Label'                 => 'contactInfo',
                    'url2'                      => 'contactInfo',
                    'url2Label'                 => 'contactInfo',
                    'url3'                      => 'contactInfo',
                    'url3Label'                 => 'contactInfo',
                    'facebookUrl'               => 'contactInfo',
                    'twitterUser'               => 'contactInfo',
                    'instagramUser'             => 'contactInfo',
                    'slackUser'                 => 'contactInfo',
                    'postStreet1'               => 'contactInfo',
                    'postStreet2'               => 'contactInfo',
                    'postCityState'             => 'contactInfo',
                    'postZip'                   => 'contactInfo',
                    'postCountry'               => 'contactInfo',
                    'contactNotes'              => 'contactInfo',

                    'lastName'                  => 'personalInfo',
                    'firstName'                 => 'personalInfo',
                    'lastNameWithoutAccents'    => 'personalInfo',
                    'firstNameWithoutAccents'   => 'personalInfo',
                    'lifeCommunity'             => 'personalInfo',
                    'manualTitle'               => 'personalInfo',
                    'automaticTitle'            => 'personalInfo',
                    'country'                   => 'personalInfo',
                    'publicNotes'               => 'personalInfo',
                    'birthDate'                 => 'personalInfo',
                    'nameDay'                   => 'personalInfo',
                    'deathDate'                 => 'personalInfo',
                ],
                'update_columns' => [
                    'lastName'                  => 'LastName',
                    'firstName'                 => 'FirstName',
                    'lastNameWithoutAccents'    => 'LastNameWithoutAccents',
                    'firstNameWithoutAccents'   => 'FirstNameWithoutAccents',
                    'personTags'                => 'PersonTags',
                    'lifeCommunity'             => 'LifeCommunity',
                    'manualTitle'               => 'Title',
                    'automaticTitle'            => 'TitleAutomatic',
                    'country'                   => 'Country',
                    'birthDate'                 => 'BirthDate',
                    'nameDay'                   => 'NameDay',
                    'deathDate'                 => 'DeathDate',
                    'publicNotes'               => 'PublicNotes',
                    'publicNotesUpdatedOn'      => 'PublicNotesUpdatedOn',
                    'publicNotesUpdatedBy'      => 'PublicNotesUpdatedBy',
                    'personalInfoUpdatedOn'     => 'PersonalInfoUpdatedOn',
                    'personalInfoUpdatedBy'     => 'PersonalInfoUpdatedBy',

                    'adminTags'                 => 'AdminTags',
                    'adminNotes'                => 'AdminNotes',
                    'adminNotesUpdatedOn'       => 'AdminNotesUpdatedOn',
                    'adminNotesUpdatedBy'       => 'AdminNotesUpdatedBy',

                    'email'                     => 'Email',
                    'email2'                    => 'Email2',
                    'emailsUpdatedOn'           => 'EmailsUpdatedOn',
                    'emailsUpdatedBy'           => 'EmailsUpdatedBy',
                    'cellPhone'                 => 'CellPhone',
                    'cellPhoneHasWhatsApp'      => 'CellPhoneHasWhatsApp',
                    'phone1'                    => 'Phone1',
                    'phone1Label'               => 'Phone1Label',
                    'phone2'                    => 'Phone2',
                    'phone2Label'               => 'Phone2Label',
                    'phone3'                    => 'Phone3',
                    'phone3Label'               => 'Phone3Label',
                    'phonesUpdatedOn'           => 'PhonesUpdatedOn',
                    'phonesUpdatedBy'           => 'PhonesUpdatedBy',
                    'url1'                      => 'Url1',
                    'url1Label'                 => 'Url1Label',
                    'url2'                      => 'Url2',
                    'url2Label'                 => 'Url2Label',
                    'url3'                      => 'Url3',
                    'url3Label'                 => 'Url3Label',
                    'facebookUrl'               => 'FacebookUrl',
                    'skypeUser'                 => 'SkypeUser',
                    'twitterUser'               => 'TwitterUser',
                    'instagramUser'             => 'InstagramUser',
                    'slackUser'                 => 'SlackUser',

                    'postStreet1'               => 'PostStreet1',
                    'postStreet2'               => 'PostStreet2',
                    'postCityState'             => 'PostCityState',
                    'postZip'                   => 'PostZip',
                    'postCountry'               => 'PostCountry',
                    'contactNotes'              => 'ContactNotes',
                    'contactInfoUpdatedOn'      => 'ContactInfoUpdatedOn',
                    'contactInfoUpdatedBy'      => 'ContactInfoUpdatedBy',

                    'personId'                  => 'PersonId',
                    'dataSource'                => 'DataSource',
                    'dataSourceId'              => 'DataSourceId',
                    'dataSourceUpdatedOn'       => 'DataSourceUpdatedOn',
                    'updatedOn'                 => 'UpdatedOn',
                    'updatedBy'                 => 'UpdatedBy',
                    'createdOn'                 => 'CreatedOn',
                    'createdBy'                 => 'CreatedBy',
        	    ],
            ],
            'association' => [
                'table_name'                            => 'sch_associations',
                'table_key'                             => 'AssociationId',
                'entity_key_field'                      => 'associationId',
                'get_object_function'                   => 'getAssociation',
                'sion_model_class'               		=> 'Schoenstatt\Model\SchoenstattTable',
                'get_objects_function'               	=> 'getAssociations',
                'name_field'                            => 'associationName',
                'report_changes'                        => true,
                'has_dedicated_suggest_form'            => false,
//                 'scope' => 'Person',
//                 'database_bound_data_preprocessor' => 'preprocessPerson', //this will separate the nationality array
                'required_columns_for_creation'         => [ //required for creation
        	        'name',
        	        'kind',
        	    ],
//                 'moderate_route' => 'associations/association/moderate',
                'moderate_route_entity_key'             => 'association_id',
                'default_route_key'                     => 'association_id',
                'show_route'                            => 'associations/association',
                'show_route_key'                        => 'association_id',
                'show_route_key_field'                  => 'associationId',
                'edit_route'                            => 'associations/association/edit',
                'edit_route_key'                        => 'association_id',
                'edit_route_key_field'                  => 'associationId',
                'enable_delete_action' 					=> true,
//                 'delete_action_acl_resource' 			=> 'event_:id',
//                 'delete_action_acl_permission' 			=> 'delete_event',
                'delete_action_redirect_route' 			=> 'associations',
                'text_columns' => [
//         	        'adminNotes',
//         	        'publicNotes',
                ],
                'date_columns' => [
                    'publicNotesUpdatedOn',
                    'adminNotesUpdatedOn',
                    'emailsUpdatedOn',
                    'phonesUpdatedOn',
                    'updatedOn',
                    'createdOn',
                ],
                'many_to_one_update_columns' => [
                    'email'                     => 'emails',
                    'email2'                    => 'emails',

                    'phone1'                    => 'phones',
                    'phone1Label'               => 'phones',
                    'phone2'                    => 'phones',
                    'phone2Label'               => 'phones',
                    'phone3'                    => 'phones',
                    'phone3Label'               => 'phones',

                    'email'                     => 'contactInfo',
                    'email2'                    => 'contactInfo',
                    'phone1'                    => 'contactInfo',
                    'phone1Label'               => 'contactInfo',
                    'phone2'                    => 'contactInfo',
                    'phone2Label'               => 'contactInfo',
                    'phone3'                    => 'contactInfo',
                    'phone3Label'               => 'contactInfo',
                    'url1'                      => 'contactInfo',
                    'url1Label'                 => 'contactInfo',
                    'url2'                      => 'contactInfo',
                    'url2Label'                 => 'contactInfo',
                    'url3'                      => 'contactInfo',
                    'url3Label'                 => 'contactInfo',
                    'facebookUrl'               => 'contactInfo',
                    'twitterUser'               => 'contactInfo',
                    'instagramUser'             => 'contactInfo',
                    'contactNotes'              => 'contactInfo',
                ],
                'update_columns' => [
                    'associationId'             => 'AssociationId',
                    'name'                      => 'AssociationName',
                    'parent'                    => 'Parent',
                    'kind'                      => 'Kind',
                    'country'                   => 'Country',
                    'foundationDate'            => 'FoundationDate',
                    'suppressionDate'           => 'SuppressionDate',
                    'isLifeCommunity'           => 'IsLifeCommunity',
                    'isNameTranslateable'       => 'IsNameTranslateable',
                    'isActive'                  => 'IsActive',
                    'publicNotes'               => 'PublicNotes',
                    'publicNotesUpdatedOn'      => 'PublicNotesUpdatedOn',
                    'publicNotesUpdatedBy'      => 'PublicNotesUpdatedBy',
                    'adminTags'                 => 'AdminTags',
                    'adminNotes'                => 'AdminNotes',
                    'adminNotesUpdatedOn'       => 'AdminNotesUpdatedOn',
                    'adminNotesUpdatedBy'       => 'AdminNotesUpdatedBy',
                    'email'                     => 'Email',
                    'email2'                    => 'Email2',
                    'emailsUpdatedOn'           => 'EmailsUpdatedOn',
                    'emailsUpdatedBy'           => 'EmailsUpdatedBy',
                    'phone1'                    => 'Phone1',
                    'phone1Label'               => 'Phone1Label',
                    'phone2'                    => 'Phone2',
                    'phone2Label'               => 'Phone2Label',
                    'phone3'                    => 'Phone3',
                    'phone3Label'               => 'Phone3Label',
                    'phonesUpdatedOn'           => 'PhonesUpdatedOn',
                    'phonesUpdatedBy'           => 'PhonesUpdatedBy',
                    'url1'                      => 'Url1',
                    'url1Label'                 => 'Url1Label',
                    'url2'                      => 'Url2',
                    'url2Label'                 => 'Url2Label',
                    'url3'                      => 'Url3',
                    'url3Label'                 => 'Url3Label',
                    'facebookUrl'               => 'FacebookUrl',
                    'twitterUser'               => 'TwitterUser',
                    'instagramUser'             => 'InstagramUser',
                	'post1Street1'              => 'Post1Street1',
                	'post1Street2'              => 'Post1Street2',
                	'post1CityState'            => 'Post1CityState',
                	'post1Zip'                  => 'Post1Zip',
                	'post1Country'              => 'Post1Country',
                	'post2Street1'              => 'Post2Street1',
                	'post2Street2'              => 'Post2Street2',
                	'post2CityState'            => 'Post2CityState',
                	'post2Zip'                  => 'Post2Zip',
                	'post2Country'              => 'Post2Country',
                    'contactNotes'              => 'ContactNotes',
                    'contactInfoUpdatedOn'      => 'ContactInfoUpdatedOn',
                    'contactInfoUpdatedBy'      => 'ContactInfoUpdatedBy',
                    'updatedOn'                 => 'UpdatedOn',
                    'updatedBy'                 => 'UpdatedBy',
                    'createdOn'                 => 'CreatedOn',
                    'createdBy'                 => 'CreatedBy',
        	    ],
            ],
            'role' => [
                'table_name' => 'sch_roles',
                'table_key' => 'RoleId',
                'entity_key_field' => 'roleId',
                'get_object_function' => 'getRole',
                'name_field' => 'roleTitle',
                'has_dedicated_suggest_form' => false,
                'report_changes' => true,
//                 'scope' => 'Person',
//                 'database_bound_data_preprocessor' => 'preprocessPerson', //this will separate the nationality array
                'required_columns_for_creation' => [ //required for creation
        	        'roleTitle',
        	        'associationId',
        	    ],
//                 'moderate_route' => 'associations/association/moderate',
//                 'moderate_route_entity_key' => 'association_id',
                'show_route' => 'roles/role',
                'show_route_key' => 'role_id',
                'show_route_key_field' => 'roleId',
                'edit_route' => 'roles/role/edit',
                'edit_route_key' => 'role_id',
                'edit_route_key_field' => 'roleId',
                'text_columns' => [
                ],
                'date_columns' => [
                    'updatedOn',
                    'createdOn',
                ],
                'many_to_one_update_columns' => [
                ],
                'update_columns' => [
                    'roleId'                    => 'RoleId',
                    'roleTitle'                 => 'RoleTitle',
                    'associationId'             => 'AssociationId',
                    'isMainRole'                => 'IsMainRole',
                    'isSinglePosition'          => 'IsSinglePosition',
                    'shouldAlwaysBeFilled'      => 'ShouldAlwaysBeFilled',
                    'sort'                      => 'Sort',
                    'isActive'                  => 'IsActive',
                    'updatedOn'                 => 'UpdatedOn',
                    'updatedBy'                 => 'UpdatedBy',
                    'createdOn'                 => 'CreatedOn',
                    'createdBy'                 => 'CreatedBy',
        	    ],
            ],
            'assignment' => [
                'table_name' => 'sch_assignments',
                'table_key' => 'AssignmentId',
                'entity_key_field' => 'roleId',
                'get_object_function' => 'getAssignment',
                'name_field' => 'roleTitle',
                'report_changes' => true,
                'has_dedicated_suggest_form' => false,
//                 'scope' => 'Person',
//                 'database_bound_data_preprocessor' => 'preprocessPerson', //this will separate the nationality array
                'required_columns_for_creation' => [ //required for creation
        	        'roleId',
                    'personId',
        	    ],
//                 'moderate_route' => 'associations/association/moderate',
//                 'moderate_route_entity_key' => 'association_id',
                'show_route' => 'assignments/assignment',
                'show_route_key' => 'assignment_id',
                'show_route_key_field' => 'assignmentId',
                'edit_route' => 'assignments/assignment/edit',
                'edit_route_key' => 'assignment_id',
                'edit_route_key_field' => 'assignmentId',
                'text_columns' => [
                ],
                'date_columns' => [
                    'startDate',
                    'endDate',
                    'updatedOn',
                    'createdOn',
                ],
                'many_to_one_update_columns' => [
                ],
                'update_columns' => [
                    'assignmentId'              => 'AssignmentId',
                    'roleId'                    => 'RoleId',
                    'personId'                  => 'PersonId',
                    'startDate'                 => 'StartDate',
                    'endDate'                   => 'EndDate',
                    'isActive'                  => 'IsActive',
                    'updatedOn'                 => 'UpdatedOn',
                    'updatedBy'                 => 'UpdatedBy',
                    'createdOn'                 => 'CreatedOn',
                    'createdBy'                 => 'CreatedBy',
        	    ],
            ],
        ],
    ],
    'asset_manager' => [
        'resolver_configs' => [
            'collections' => [
                'js/markdown-form-en.js' => [
//                     'js/commonmark.js',
                    'js/markdown.js',
                    'js/to-markdown.js',
                    'js/bootstrap-markdown.js',
                ],
                'js/markdown-form-es.js' => [
//                     'js/commonmark.js',
                    'js/markdown.js',
                    'js/to-markdown.js',
                    'js/bootstrap-markdown.js',
                    'js/bootstrap-markdown.es.js',
                ],
                'js/markdown-form-de.js' => [
//                     'js/commonmark.js',
                    'js/markdown.js',
                    'js/to-markdown.js',
                    'js/bootstrap-markdown.js',
                    'js/bootstrap-markdown.de.js',
                ],
                'js/markdown-form-pt.js' => [
//                     'js/commonmark.js',
                    'js/markdown.js',
                    'js/to-markdown.js',
                    'js/bootstrap-markdown.js',
                    'js/bootstrap-markdown.es.js',
                ],
                'js/role-create.js' => [
                    'js/jquery-ui.min.js',
                    'js/jquery.validate.min.js',
                ],
                'js/person-edit.js' => [
                    'js/jquery-ui.min.js',
                    'js/jquery.validate.min.js',
                    'js/selectize.min.js',
                    'js/fathers-father-edit.js',
                ],
                'js/living-situation-edit.js' => [
                    'js/jquery-ui.min.js',
                    'js/jquery.validate.min.js',
                ],
                'js/advanced-search.js' => [
                    'js/selectize.min.js',
                    'js/advanced-search-custom.js',
                ],
            ],
            'paths' => [
                'Patres' => __DIR__ . '/../public',
            ],
        ],
        'caching' => [
            'js/markdown-form-en.js' => [
                'cache'     => 'AssetManager\\Cache\\FilePathCache',
                'options' => [
                    'dir' => 'public', // path/to/cache
                ],
            ],
            'js/markdown-form-es.js' => [
                'cache'     => 'AssetManager\\Cache\\FilePathCache',
                'options' => [
                    'dir' => 'public', // path/to/cache
                ],
            ],
            'js/markdown-form-de.js' => [
                'cache'     => 'AssetManager\\Cache\\FilePathCache',
                'options' => [
                    'dir' => 'public', // path/to/cache
                ],
            ],
            'js/markdown-form-pt.js' => [
                'cache'     => 'AssetManager\\Cache\\FilePathCache',
                'options' => [
                    'dir' => 'public', // path/to/cache
                ],
            ],
            'js/role-create.js' => [
                'cache'     => 'AssetManager\\Cache\\FilePathCache',
                'options' => [
                    'dir' => 'public', // path/to/cache
                ],
            ],
            'js/person-edit.js' => [
                'cache'     => 'AssetManager\\Cache\\FilePathCache',
                'options' => [
                    'dir' => 'public', // path/to/cache
                ],
            ],
            'js/advanced-search.js' => [
                'cache'     => 'AssetManager\\Cache\\FilePathCache',
                'options' => [
                    'dir' => 'public', // path/to/cache
                ],
            ],
            'js/clipboard.min.js' => [
                'cache'     => 'AssetManager\\Cache\\FilePathCache',
                'options' => [
                    'dir' => 'public', // path/to/cache
                ],
            ],
        ],
        'filters' => [
            'js/markdown-form-en.js' => [
                [
                    'filter' => 'SionModel\\Filter\\JShrinkFilter',
                ],
            ],
            'js/markdown-form-es.js' => [
                [
                    'filter' => 'SionModel\\Filter\\JShrinkFilter',
                ],
            ],
            'js/markdown-form-de.js' => [
                [
                    'filter' => 'SionModel\\Filter\\JShrinkFilter',
                ],
            ],
            'js/markdown-form-pt.js' => [
                [
                    'filter' => 'SionModel\\Filter\\JShrinkFilter',
                ],
            ],
        ],
    ],

    'bjyauthorize' => [
        'guards' => [
            'BjyAuthorize\Guard\Route' => [
                ['route' => 'admin/import-father', 'roles' => ['sch_administrator']],
                ['route' => 'assignments', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'assignments/assignment', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'assignments/assignment/edit', 'roles' => ['sch_general_moderator']],
                ['route' => 'assignments/assignment/delete', 'roles' => ['sch_general_moderator']],
                ['route' => 'assignments/search', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'assignments/create', 'roles' => ['sch_general_moderator']],

                ['route' => 'persons', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'persons/person', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'persons/search', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'persons/create', 'roles' => ['sch_general_moderator']],
                ['route' => 'persons/person/edit', 'roles' => ['sch_general_moderator']],
                ['route' => 'persons/person/moderate', 'roles' => ['sch_moderator']],

                ['route' => 'associations', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'associations/association', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'associations/create', 'roles' => ['sch_general_moderator']],
                ['route' => 'associations/import', 'roles' => ['sch_administrator']],
                ['route' => 'associations/association/edit', 'roles' => ['sch_general_moderator']],
                ['route' => 'associations/association/moderate', 'roles' => ['sch_general_moderator']],
                ['route' => 'associations/association/delete', 'roles' => ['sch_administrator']],

                ['route' => 'roles', 'roles' => ['sch_moderator']],
                ['route' => 'roles/create', 'roles' => ['sch_moderator']],
                ['route' => 'roles/role', 'roles' => ['sch_moderator']],
                ['route' => 'roles/role/edit', 'roles' => ['sch_moderator']],
                ['route' => 'roles/role/delete', 'roles' => ['sch_general_moderator']],
            ],
        ],
    ],

];
