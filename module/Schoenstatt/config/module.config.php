<?php
return [
    'controllers' => [
        'invokables' => [
            'Schoenstatt\Controller\Schoenstatt'    => 'Schoenstatt\Controller\SchoenstattController',
            'Schoenstatt\Controller\Persons'        => 'Schoenstatt\Controller\PersonsController',
            'Schoenstatt\Controller\Associations'   => 'Schoenstatt\Controller\AssociationsController',
            'Schoenstatt\Controller\Admin'          => 'Schoenstatt\Controller\AdminController',
            'Schoenstatt\Controller\Assignments'    => 'Schoenstatt\Controller\AssignmentsController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Schoenstatt\Model\SchoenstattTable' => 'Schoenstatt\Service\SchoenstattTableFactory',
            'Schoenstatt\Form\PersonForm'        => 'Schoenstatt\Service\PersonFormFactory',
            'Schoenstatt\Form\AssociationForm'   => 'Schoenstatt\Service\AssociationFormFactory',
            'Schoenstatt\Config'                 => 'Schoenstatt\Service\ConfigServiceFactory',
            'Schoenstatt\Form\ImportFatherForm'  => 'Schoenstatt\Service\ImportFatherFormFactory',
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
        ],
        'invokables' => [
            'clipboardButton'		=> 'Schoenstatt\View\Helper\ClipboardButton',
            'editPencil'		    => 'Schoenstatt\View\Helper\EditPencil',
        	'formatPerson'          => 'Schoenstatt\View\Helper\FormatPerson',
            'languageChooser'       => 'Schoenstatt\View\Helper\LanguageChooser',
        ],
    ],
    'schoenstatt' => [
        'association_kinds' => [
            'sch-institute' => [
                'label' => 'Schoenstatt institute',
                'default_roles' => [
                    [
                        'roleTitle' => 'General superior',
                        'singlePosition' => true,
                        'sort' => 100,
                        'mainRole' => true,
                    ],
                    [
                        'roleTitle' => 'Councilor',
                        'singlePosition' => false,
                        'sort' => 100,
                        'mainRole' => true,
                    ]
                ],
            ],
            'sch-national-federation' => [
                'label' => 'Schoenstatt national federation',
            ],
            'sch-diocesan-movement' => [
                'label' => 'Schoenstatt diocesan movement',
            ],
            'sch-national-movement' => [
                'label' => 'Schoenstatt national movement',
            ],
            'sch-movement-international-structure' => [
                'label' => 'Schoenstatt movement international structure',
            ],
            'sch-other' => [
                'label' => 'Other Schoenstatt entity',
            ],
            'legal-entity' => [
                'label' => 'Legal entity',
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
                            'edit-personal-info' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/edit-personal-info',
                                    'defaults' => [
                                        'action'    => 'edit',
                                        'entity'    => 'personalInfo'
                                    ],
                                ],
                            ],
                            'edit-contact-info' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/edit-contact-info',
                                    'defaults' => [
                                        'action'     => 'edit',
                                        'entity'    => 'contactInfo'
                                    ],
                                ],
                            ],
                            'edit-private-info' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/edit-private-info',
                                    'defaults' => [
                                        'action'    => 'edit',
                                        'entity'    => 'privateInfo'
                                    ],
                                ],
                            ],
                            'suggest-contact-info' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/suggest-contact-info',
                                    'defaults' => [
                                        'action'     => 'suggest-contact-info',
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
                ],
            ],
        ],
    ],
    'sion_model' => [
		'changes_table' => 'sch_changes',
        'visits_table' => 'sch_visits',
        'entities' => [
            'person' => [
                'table_name' => 'sch_persons',
                'table_key' => 'PersonId',
                'entity_key_field' => 'personId',
                'update_reference_data_function' => 'getPerson',
                'name_field' => 'fullName',
                'report_changes' => true,
                'has_dedicated_suggest_form' => false,
//                 'scope' => 'Person',
                'database_bound_data_preprocessor' => 'preprocessPerson', //this will separate the nationality array
                'required_columns_for_creation' => [ //required for creation
        	        'lastName',
        	        'firstName',
        	    ],
//                 'moderate_route' => 'persons/person/moderate',
                'moderate_route_entity_key' => 'person_id',
                'show_route' => 'fathers/father',
                'show_route_key' => 'person_id',
                'show_route_key_field' => 'personId',
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
                    'religiousStatus'           => 'personalInfo',
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
                    'religiousStatus'           => 'ReligiousStatus',
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
                    'birthCity'                 => 'BirthCity',
                    'nationalities'             => 'Nationalities',
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
                'table_name' => 'sch_associations',
                'table_key' => 'AssociationId',
                'entity_key_field' => 'associationId',
                'update_reference_data_function' => 'getAssociation',
                'name_field' => 'associationName',
                'report_changes' => true,
                'has_dedicated_suggest_form' => false,
//                 'scope' => 'Person',
//                 'database_bound_data_preprocessor' => 'preprocessPerson', //this will separate the nationality array
                'required_columns_for_creation' => [ //required for creation
        	        'name',
        	        'kind',
        	    ],
//                 'moderate_route' => 'associations/association/moderate',
                'moderate_route_entity_key' => 'association_id',
                'show_route' => 'associations/association',
                'show_route_key' => 'association_id',
                'show_route_key_field' => 'associationId',
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
                'update_reference_data_function' => 'getRole',
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
                'update_reference_data_function' => 'getAssignment',
                'name_field' => 'name',
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
                    'personId'                  => 'Personid',
                    'startDate'                 => 'StartDate',
                    'endDate'                   => 'EndDate',
                    'isActive'                  => 'IsActive',
                    'updatedOn'                 => 'UpdatedOn',
                    'updatedBy'                 => 'UpdatedBy',
                    'createdOn'                 => 'CreatedOn',
                    'createdBy'                 => 'CreatedBy',
        	    ],
            ],
            'publication' => [
                'table_name' => 'lib_publications',
                'table_key' => 'PublicationId',
                'entity_key_field' => 'publicationId',
                'update_reference_data_function' => 'getPublication',
                'name_field' => 'name',
                'has_dedicated_suggest_form' => false,
                'report_changes' => true,
                // 'scope' => 'Publication',
                // 'database_bound_data_preprocessor' => 'preprocessPublication', //this will separate the nationality array
                'required_columns_for_creation' => [ //required for creation
                    'name',
                ],
                // 'moderate_route' => 'publications/publication/moderate',
                // 'moderate_route_entity_key' => 'publication_id',
                'show_route' => 'publications/publication',
                'show_route_key' => 'publication_id',
                'show_route_key_field' => 'publicationId',
                'text_columns' => [
                ],
                'date_columns' => [
                    'updatedOn',
                    'createdOn',
                ],
                'many_to_one_update_columns' => [
                ],
                'update_columns' => [
                    'publicationId'             => 'PublicationId',
                    'title'                     => 'PublicationTitle',
                    'authorPerson1'             => 'AuthorPerson1',
                    'authorPerson2'             => 'AuthorPerson2',
                    'authorPerson3'             => 'AuthorPerson3',
                    'authors'                   => 'Authors',
                    'bookEdition'               => 'BookEdition',
                    'inLanuage'                 => 'InLanuage',
                    'description'               => 'Description',
                    'isbn'                      => 'Isbn',
                    'illustrator'               => 'Illustrator',
                    'translator'                => 'Translator',
                    'numberOfPages'             => 'NumberOfPages',
                    'copyrightYear'             => 'CopyrightYear',
                    'publisher'                 => 'Publisher',
                    'publishingPlace'           => 'PublishingPlace',
                    'datePublished'             => 'DatePublished',
                    'publishingStatus'          => 'PublishingStatus',
                    'bookFormatType'            => 'BookFormatType',
                    'genre'                     => 'Genre',
                    'url'                       => 'Url',
                    'textId'                    => 'TextId',
                    'mainPublication'           => 'MainPublication', //mainEntity
                    'quality'                   => 'Quality',
                    'qualityNotes'              => 'QualityNotes',
                    'volumeNumber'              => 'VolumeNumber',
                    'containedIn'               => 'ContainedIn',
                    'containedInIsbn'           => 'ContainedInIsbn',
                    'keywords'                  => 'Keywords',

                    'isAccessableForFree'       => 'IsAccessableForFree',
                    'isInternalForPatres'       => 'IsInternalForPatres',
                    'isScientificWork'          => 'IsScientificWork',
                    'isAwaitingMerge'           => 'IsAwaitingMerge',
                    'hasBeenMerged'             => 'HasBeenMerged',
                    'publicNotes'               => 'PublicNotes',
                    'publicNotesUpdatedOn'      => 'PublicNotesUpdatedOn',
                    'publicNotesUpdatedBy'      => 'PublicNotesUpdatedBy',
                    'adminTags'                 => 'AdminTags',
                    'adminNotes'                => 'AdminNotes', //store source info here
                    'adminNotesUpdatedOn'       => 'AdminNotesUpdatedOn',
                    'adminNotesUpdatedBy'       => 'AdminNotesUpdatedBy',
                    'updatedOn'                 => 'UpdatedOn',
                    'updatedBy'                 => 'UpdatedBy',
                    'createdOn'                 => 'CreatedOn',
                    'createdBy'                 => 'CreatedBy',

                    'dataSource'                => 'DataSource',
                    'dataSourceId'              => 'DataSourceId',
                    'dataSourceUpdatedOn'       => 'DataSourceUpdatedOn',
                    'jkPeriod'                  => 'JkPeriod', //Should be passed to the JkEvent table
                    'jkEventId'                 => 'JkEventId',
                    'location'                  => 'Location',
                    'jkCategory'                => 'JkCategory', //DEPRECATED
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
];
