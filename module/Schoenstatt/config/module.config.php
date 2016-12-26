<?php
return [
    'controllers' => [
        'invokables' => [
            'Schoenstatt\Controller\Schoenstatt' => 'Schoenstatt\Controller\SchoenstattController',
            'Schoenstatt\Controller\Persons' => 'Schoenstatt\Controller\PersonsController',
            'Schoenstatt\Controller\Admin' => 'Schoenstatt\Controller\AdminController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Schoenstatt\Model\SchoenstattTable' => 'Schoenstatt\Service\SchoenstattTableFactory',
            'CountryValueOptions'                => 'Schoenstatt\Service\CountryValueOptionsFactory',
            'Schoenstatt\Form\PersonForm'        => 'Schoenstatt\Service\PersonFormFactory',
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
            'contacts' => [
                'type'    => 'Literal',
                'options' => [
                    // Change this to something specific to your module
                    'route'    => '/contacts',
                    'defaults' => [
                        // Change this value to reflect the namespace in which
                        // the controllers for your module are found
                        '__NAMESPACE__' => 'Schoenstatt\Controller',
                        'controller'    => 'Schoenstatt',
                        'action'        => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    // This route is a sane default when developing a module;
                    // as you solidify the routes for your module, however,
                    // you may want to remove it and replace it with more
                    // specific routes.
                    'search' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/search',
                            'defaults' => [
                                'action'     => 'search',
                            ],
                        ],
                    ],
                ],
            ],
            'persons' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/persons',
                    'defaults' => [
                        'controller' => 'Schoenstatt\Controller\Persons',
                        'action'     => 'show',
                    ],
                ],
                'may_terminate' => false,
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
                    'person' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/:person_id',
                            'constraints' => [
                                'person_id' => '[0-9]{1,5}',
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
        ],
    ],
    'sion_model' => [
        'entities' => [
            'person' => [
                'table_name' => 'sch_persons',
                'table_key' => 'PersonId',
                'entity_key_field' => 'personId',
                'update_reference_data_function' => 'getPerson',
                'name_column' => 'fullName',
                'has_dedicated_suggest_form' => false,
//                 'scope' => 'Person',
                'database_bound_data_preprocessor' => 'preprocessPerson', //this will separate the nationality array
                'required_columns_for_creation' => [ //required for creation
        	        'lastName',
        	        'firstName',
        	    ],
                'moderate_route' => 'persons/person/moderate',
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
                    'baptismDate',
                    'deaconDate',
                    'priestDate',
                    'bishopDate',
                    'deathDate',
                    'leaveDate',
                    'emailsUpdatedOn',
                    'phonesUpdatedOn',
                    'updatedOn',
                    'createdOn',
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
                    'contactNotes'              => 'contactInfo',

                    'lastName'                  => 'personalInfo',
                    'firstName'                 => 'personalInfo',
                    'lastNameWithoutAccents'    => 'personalInfo',
                    'firstNameWithoutAccents'   => 'personalInfo',
                    'friendlyLastName'          => 'personalInfo',
                    'friendlyFirstName'         => 'personalInfo',
                    'manualTitle'               => 'personalInfo',
                    'automaticTitle'            => 'personalInfo',
                    'country'                   => 'personalInfo',
                    'publicNotes'               => 'personalInfo',
                    'birthDate'                 => 'personalInfo',
                    'nameDay'                   => 'personalInfo',
                    'baptismDate'               => 'personalInfo',
                    'deaconDate'                => 'personalInfo',
                    'priestDate'                => 'personalInfo',
                    'bishopDate'                => 'personalInfo',
                    'deathDate'                 => 'personalInfo',
                    'leaveDate'                 => 'personalInfo',

                    'adminTags'                 => 'privateInfo',
                    'birthCity'                 => 'privateInfo',
                    'nationalities'             => 'privateInfo',
                    'adminNotes'                => 'privateInfo',
                ],
                'update_columns' => [
                    'lastName'                  => 'LastName',
                    'firstName'                 => 'FirstName',
                    'lastNameWithoutAccents'    => 'LastNameWithoutAccents',
                    'firstNameWithoutAccents'   => 'FirstNameWithoutAccents',
                    'religiousStatus'           => 'ReligiousStatus',
                    'manualTitle'               => 'Title',
                    'automaticTitle'            => 'TitleAutomatic',
                    'country'                   => 'Country',
                    'birthDate'                 => 'BirthDate',
                    'nameDay'                   => 'NameDay',
                    'deaconDate'                => 'DeaconDate',
                    'priestDate'                => 'PriestDate',
                    'bishopDate'                => 'BishopDate',
                    'deathDate'                 => 'DeathDate',
                    'leaveDate'                 => 'LeaveDate',
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
                    'privateInfoUpdatedOn'      => 'PrivateInfoUpdatedOn',
                    'privateInfoUpdatedBy'      => 'PrivateInfoUpdatedBy',

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
                    'contactNotes'              => 'ContactNotes',
                    'contactInfoUpdatedOn'      => 'ContactInfoUpdatedOn',
                    'contactInfoUpdatedBy'      => 'ContactInfoUpdatedBy',

                    'personId'                  => 'PersonId',
                    'updatedOn'                 => 'UpdatedOn',
                    'updatedBy'                 => 'UpdatedBy',
                    'createdOn'                 => 'CreatedOn',
                    'createdBy'                 => 'CreatedBy',
        	    ],
            ],
        ],
    ],
    'schoenstatt' => [

    ],
    'view_manager' => [
        'template_path_stack' => [
            'Schoenstatt' => __DIR__ . '/../view',
        ],
    ],
];
