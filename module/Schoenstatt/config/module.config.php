<?php
namespace Schoenstatt;

use SionModel\Problem\EntityProblem;
use Zend\ServiceManager\Proxy\LazyServiceFactory;
use Zend\Router\Http\Segment;
use Zend\Router\Http\Literal;
use Spatie\SchemaOrg\CatholicChurch;
use Spatie\SchemaOrg\PlaceOfWorship;
use Spatie\SchemaOrg\Organization;
use Spatie\SchemaOrg\EducationalOrganization;
use Spatie\SchemaOrg\WebSite;
use JTranslate\Model\CountriesInfo;

$leagueRoles = [
    [
        'roleTitle' => 'Branch leader',
        'singlePosition' => true,
        'sort' => 5,
        'isMainRole' => true,
        'isMainContact' => true,
        'shouldAlwaysBeFilled' => true,
    ],
    [
        'roleTitle' => 'Branch moderator',
        'singlePosition' => false,
        'sort' => 10,
        'isMainRole' => false,
        'isMainContact' => false,
        'shouldAlwaysBeFilled' => false,
    ],
    [
        'roleTitle' => 'Assistant moderator',
        'singlePosition' => false,
        'sort' => 20,
        'isMainRole' => false,
        'isMainContact' => false,
        'shouldAlwaysBeFilled' => false,
    ],
    [
        'roleTitle' => 'Member',
        'singlePosition' => false,
        'sort' => 70,
        'isMainRole' => false,
        'isMainContact' => false,
        'shouldAlwaysBeFilled' => false,
    ],
];

$federationRoles = [
    [
        'roleTitle' => 'General superior',
        'singlePosition' => true,
        'sort' => 10,
        'isMainRole' => true,
        'isMainContact' => true,
        'shouldAlwaysBeFilled' => true,
    ],
    [
        'roleTitle' => 'Councilor',
        'singlePosition' => false,
        'sort' => 15,
        'isMainRole' => false,
        'isMainContact' => false,
        'shouldAlwaysBeFilled' => false,
    ],
];
return [
    'controllers' => [
        'abstract_factories' => [
            \Schoenstatt\Controller\LazyControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            Service\PatresGateway::class            => Service\PatresGatewayFactory::class,
            'Schoenstatt\FathersValueOptions'       => Service\FathersValueOptionsService::class,
            Model\SchoenstattTable::class           => Service\SchoenstattTableFactory::class,
            Form\AdvancedSearchForm::class          => Service\AdvancedSearchFormFactory::class,
            Form\PersonForm::class                  => Service\PersonFormFactory::class,
            Form\AssignmentForm::class              => Service\AssignmentFormFactory::class,
            'Schoenstatt\Form\EditAssignmentForm'   => Service\EditAssignmentFormFactory::class,
            Form\AssociationForm::class             => Service\AssociationFormFactory::class,
            Form\RoleForm::class                    => Service\RoleFormFactory::class,
            'Schoenstatt\Config'                    => Service\ConfigServiceFactory::class,
            Form\ImportFatherForm::class            => Service\ImportFatherFormFactory::class,
            'Schoenstatt\PersonTagsValueOptions'    => Service\PersonTagsValueOptionsFactory::class,
            Service\AssociationKindsService::class  => Service\AssociationKindsServiceFactory::class,
        ],
        'lazy_services' => [
            // Mapping services to their class names is required
            // since the ServiceManager is not a declarative DIC.
            'class_map' => [
                Form\ImportFatherForm::class => Form\ImportFatherForm::class,
                Service\PatresGateway::class => Service\PatresGateway::class,
                Model\SchoenstattTable::class => Model\SchoenstattTable::class,
                Form\PersonForm::class => Form\PersonForm::class,
                Form\AdvancedSearchForm::class => Form\AdvancedSearchForm::class,
                Form\AssignmentForm::class => Form\AssignmentForm::class,
                Form\AssociationForm::class => Form\AssociationForm::class,
                Form\RoleForm::class => Form\RoleForm::class,
            ],
        ],
        'delegators' => [
            Form\ImportFatherForm::class => [
                LazyServiceFactory::class,
            ],
            Service\PatresGateway::class => [
                LazyServiceFactory::class,
            ],
            Model\SchoenstattTable::class => [
                LazyServiceFactory::class,
            ],
            Form\PersonForm::class => [
                LazyServiceFactory::class,
            ],
            Form\AdvancedSearchForm::class => [
                LazyServiceFactory::class,
            ],
            Form\AssignmentForm::class => [
                LazyServiceFactory::class,
            ],
            Form\AssociationForm::class => [
                LazyServiceFactory::class,
            ],
            Form\RoleForm::class => [
                LazyServiceFactory::class,
            ],
        ],
    ],
    'view_manager' => [
        'template_map' => include __DIR__ . '/template_map.config.php',
        'template_path_stack' => [
            'schoenstatt' => __DIR__ . '/../view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'formatEntity'          => Service\FormatEntityFactory::class,
            'formatAssociation'     => Service\FormatAssociationFactory::class,
        ],
        'invokables' => [
            'clipboardButton'       => View\Helper\ClipboardButton::class,
            'formatPerson'          => View\Helper\FormatPerson::class,
            'languageChooser'       => View\Helper\LanguageChooser::class,
            'schoenstattJsonLd'     => View\Helper\SchoenstattJsonLd::class,
        ],
    ],

    'bjyauthorize' => [
            // Resource providers to be used to load all available resources into Zend\Permissions\Acl\Acl
            // Keys are the provider service names, values are the options to be passed to the provider
            'resource_providers'    => [
                Model\SchoenstattTable::class => [],
            ],

            // Rule providers to be used to load all available rules into Zend\Permissions\Acl\Acl
            // Keys are the provider service names, values are the options to be passed to the provider
            'rule_providers'        => [
                Model\SchoenstattTable::class => [],
            ],
    ],
    'schoenstatt' => [
        'general_presidium_id' => 71,
        'person_value_options_providers' => [
            'patres-sion' => [
                'target'    => 'Schoenstatt\FathersValueOptions',
                'label'     => 'Schoenstatt Fathers',
            ],
        ],
        // they will appear in the value options in this order, they will be applied according to the sort order
        'person_tags' => [
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
            'frau' => [
                'title' => 'Frau',
                'label' => 'Frau',
                'sort'  => 10,
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
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 90,
                        'isMainRole' => false,
                        'main' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-institute' => [
                'label' => 'Schoenstatt institute',
                'sort'  => 200,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'General superior',
                        'singlePosition' => true,
                        'sort' => 1,
                        'isMainRole' => true,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'First councilor',
                        'singlePosition' => true,
                        'sort' => 2,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'Councilor',
                        'singlePosition' => false,
                        'sort' => 3,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'General secretary',
                        'singlePosition' => true,
                        'sort' => 4,
                        'isMainRole' => false,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-federation-international-structure' => [ //@todo finish this
                'sort'  => 250,
                'label' => 'Federation international structure',
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 90,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-national-movement' => [
                'label' => 'Schoenstatt national movement',
                'sort'  => 300,
                'name_format' => 'Schoenstatt Movement of %s',
                'should_translate_name_parameter' => true,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Movement director',
                        'singlePosition' => true,
                        'sort' => 20,
                        'isMainRole' => true,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'Presidium president',
                        'singlePosition' => true,
                        'sort' => 21,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Coordinating Sister',
                        'singlePosition' => true,
                        'sort' => 22,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-regional-organization' => [
                'label' => 'Schoenstatt regional organization',
                'sort'  => 350,
                'name_format' => 'Schoenstatt Movement of %s',
                'should_translate_name_parameter' => true,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Regional movement director',
                        'singlePosition' => true,
                        'sort' => 20,
                        'isMainRole' => true,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'Regional movement sister',
                        'singlePosition' => false,
                        'sort' => 40,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-national-federation' => [
                'label' => 'Schoenstatt national federation',
                'sort'  => 499,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => $federationRoles,
            ],
            'sch-national-priests-federation' => [
                'label' => 'Schoenstatt national priests\' federation',
                'name_format' => 'Priests\' federation of %s',
                'should_translate_name_parameter' => true,
                'sort'  => 400,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => $federationRoles,
            ],
            'sch-national-family-federation' => [
                'label' => 'Schoenstatt national family federation',
                'name_format' => 'Family federation of %s',
                'should_translate_name_parameter' => true,
                'sort'  => 410,
                'schema_type' => Organization::class,
                'default_roles' => $federationRoles,
            ],
            'sch-national-mens-federation' => [
                'label' => 'Schoenstatt national men\'s federation',
                'name_format' => 'Men\'s federation of %s',
                'should_translate_name_parameter' => true,
                'sort'  => 420,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => $federationRoles,
            ],
            'sch-national-mothers-federation' => [
                'label' => 'Schoenstatt national mothers\' federation',
                'name_format' => 'Mother\'s federation of %s',
                'should_translate_name_parameter' => true,
                'sort'  => 430,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => $federationRoles,
            ],
            'sch-national-womens-federation' => [
                'label' => 'Schoenstatt national women\'s federation',
                'name_format' => 'Women\'s federation of %s',
                'should_translate_name_parameter' => true,
                'sort'  => 440,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => $federationRoles,
            ],
            'sch-national-apostolate' => [
                'sort'  => 490,
                'label' => 'Schoenstatt national apostolate',
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Coordinator',
                        'singlePosition' => true,
                        'sort' => 10,
                        'isMainRole' => true,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 70,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-shrine' => [
                'sort'  => 495,
                'label' => 'Schoenstatt shrine',
                'name_format' => 'Schoenstatt Shrine %s',
                'should_translate_name_parameter' => false,
                'is_sub_diocesan_association' => false,
                'schema_type' => CatholicChurch::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Rector',
                        'singlePosition' => true,
                        'sort' => 10,
                        'isMainRole' => true,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Public contact',
                        'singlePosition' => false,
                        'sort' => 15,
                        'isMainRole' => false,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                ],
            ],
            'sch-wayside-shrine' => [
                'sort'  => 498,
                'label' => 'Schoenstatt wayside shrine',
                'name_format' => 'Schoenstatt wayside shrine %s',
                'should_translate_name_parameter' => false,
                'is_sub_diocesan_association' => false,
                'schema_type' => PlaceOfWorship::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Public contact',
                        'singlePosition' => false,
                        'sort' => 15,
                        'isMainRole' => false,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                ],
            ],
            'sch-diocesan-movement' => [
                'label' => 'Schoenstatt diocesan movement',
                'name_format' => 'Schoenstatt Movement of %s',
                'should_translate_name_parameter' => false, //don't translate diocese names, in general
                'sort'  => 500,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Diocesan coordinator',
                        'singlePosition' => true,
                        'sort' => 50,
                        'isMainRole' => true,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'Coordination assistant',
                        'singlePosition' => false,
                        'sort' => 54,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Committee member',
                        'singlePosition' => false,
                        'sort' => 55,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-league-branch' => [
                'label' => 'Schoenstatt league branch',
                'sort'  => 699,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => $leagueRoles,
            ],
            'sch-family-league-branch' => [
                'label' => 'Schoenstatt family league branch',
                'name_format' => 'Family league of %s',
                'should_translate_name_parameter' => false,
                'sort'  => 610,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => $leagueRoles,
            ],
            'sch-mothers-league-branch' => [
                'label' => 'Schoenstatt mother\'s league branch',
                'name_format' => 'Mothers\' league of %s',
                'should_translate_name_parameter' => false,
                'sort'  => 615,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => $leagueRoles,
            ],
            'sch-mens-league-branch' => [
                'label' => 'Schoenstatt men\'s league branch',
                'name_format' => 'Men\'s league of %s',
                'should_translate_name_parameter' => false,
                'sort'  => 620,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => $leagueRoles,
            ],
            'sch-womens-league-branch' => [
                'label' => 'Schoenstatt women\'s league branch',
                'name_format' => 'Women\'s league of %s',
                'should_translate_name_parameter' => false,
                'sort'  => 630,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => $leagueRoles,
            ],
            'sch-young-mens-league-branch' => [
                'label' => 'Schoenstatt young men\'s branch',
                'name_format' => 'Men\'s youth of %s',
                'should_translate_name_parameter' => false,
                'sort'  => 640,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => $leagueRoles,
            ],
            'sch-young-womens-league-branch' => [
                'label' => 'Schoenstatt young women\'s branch',
                'name_format' => 'Women\'s youth of %s',
                'should_translate_name_parameter' => false,
                'sort'  => 650,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => $leagueRoles,
            ],
            'sch-professionals-branch' => [
                'label' => 'Schoenstatt professionals league branch',
                'name_format' => 'Professional\'s branch of %s',
                'should_translate_name_parameter' => false,
                'sort'  => 660,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => $leagueRoles,
            ],
            'sch-madrugadores-branch' => [
                'label' => 'Schoenstatt madrugadores branch',
                'name_format' => 'Madrugadores of %s',
                'should_translate_name_parameter' => false,
                'sort'  => 670,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => $leagueRoles,
            ],
            'sch-diocesan-pilgrim-movement' => [
                'sort'  => 675,
                'label' => 'Diocesan pilgrim\'s movement',
                'name_format' => 'Pilgrim movement of %s',
                'should_translate_name_parameter' => false,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Coordinator',
                        'singlePosition' => true,
                        'sort' => 10,
                        'isMainRole' => true,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Moderator',
                        'singlePosition' => true,
                        'sort' => 15,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 70,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-diocesan-pilgrim-mother' => [
                'sort'  => 680,
                'label' => 'Diocesan Pilgrim Mother organization',
                'name_format' => 'Diocesan Pilgrim Mother of %s',
                'should_translate_name_parameter' => false,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Coordinator',
                        'singlePosition' => true,
                        'sort' => 10,
                        'isMainRole' => true,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Moderator',
                        'singlePosition' => true,
                        'sort' => 15,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 70,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-shrine-ministry' => [
                'sort'  => 690,
                'label' => 'Schoenstatt shrine ministry',
                'name_format' => 'Shrine ministry of %s',
                'should_translate_name_parameter' => false,
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Coordinator',
                        'singlePosition' => true,
                        'sort' => 10,
                        'isMainRole' => true,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Moderator',
                        'singlePosition' => false,
                        'sort' => 15,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 70,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-diocesan-apostolate' => [
                'sort'  => 695,
                'label' => 'Schoenstatt diocesan apostolate',
                'is_sub_diocesan_association' => true,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Coordinator',
                        'singlePosition' => true,
                        'sort' => 10,
                        'isMainRole' => true,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Moderator',
                        'singlePosition' => false,
                        'sort' => 15,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 70,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-school' => [
                'label' => 'Schoenstatt school',
                'sort'  => 700,
                'is_sub_diocesan_association' => false,
                'schema_type' => EducationalOrganization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Principal',
                        'singlePosition' => true,
                        'sort' => 5,
                        'isMainRole' => true,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => true,
                    ],
                    [
                        'roleTitle' => 'President',
                        'singlePosition' => true,
                        'sort' => 10,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Board member',
                        'singlePosition' => false,
                        'sort' => 70,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Pastoral director',
                        'singlePosition' => false,
                        'sort' => 80,
                        'isMainRole' => false,
                        'isMainContact' => false,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Movement contact',
                        'singlePosition' => false,
                        'sort' => 80,
                        'isMainRole' => false,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-website' => [
                'label' => 'Schoenstatt website',
                'sort'  => 720,
                'is_sub_diocesan_association' => false,
                'schema_type' => WebSite::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Movement contact',
                        'singlePosition' => false,
                        'sort' => 80,
                        'isMainRole' => false,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-magazine' => [
                'label' => 'Schoenstatt magazine',
                'sort'  => 740,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Movement contact',
                        'singlePosition' => false,
                        'sort' => 80,
                        'isMainRole' => false,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'sch-other' => [
                'label' => 'Other Schoenstatt entity',
                'sort'  => 800,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'Movement contact',
                        'singlePosition' => false,
                        'sort' => 80,
                        'isMainRole' => false,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                ],
            ],
            'legal-entity' => [
                'label' => 'Legal entity',
                'sort'  => 900,
                'is_sub_diocesan_association' => false,
                'schema_type' => Organization::class,
                'default_roles' => [
                    [
                        'roleTitle' => 'President',
                        'singlePosition' => true,
                        'sort' => 80,
                        'isMainRole' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Movement contact',
                        'singlePosition' => false,
                        'sort' => 85,
                        'isMainRole' => false,
                        'isMainContact' => true,
                        'shouldAlwaysBeFilled' => false,
                    ],
                    [
                        'roleTitle' => 'Member',
                        'singlePosition' => false,
                        'sort' => 90,
                        'isMainRole' => false,
                        'isMainContact' => false,
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
            'map' => [
                'logo'      => 'img/map.png',
                'label'     => 'Map',
            ],
            'review' => [
                'logo'      => 'img/review.png',
                'label'     => 'Review',
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
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/admin',
                    'defaults' => [
                        'controller' => Controller\AdminController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'data-problems' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/data-problems',
                            'defaults' => [
                                'controller' => Controller\AdminController::class,
                                'action'     => 'dataProblems',
                            ],
                        ],
                    ],
                    'import-father' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/import-father',
                            'defaults' => [
                                'controller' => Controller\AdminController::class,
                                'action'     => 'importFather',
                            ],
                        ],
                    ],
                    'import-shrines' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/import-shrines',
                            'defaults' => [
                                'controller' => Controller\AdminController::class,
                                'action'     => 'importShrines',
                            ],
                        ],
                    ],
                    'maintenance' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/maintenance',
                            'defaults' => [
                                'controller' => Controller\AdminController::class,
                                'action'     => 'maintenance',
                            ],
                        ],
                    ],
                    'moderate' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/moderate[/:suggestion_id]',
                            'defaults' => [
                                'controller' => Controller\AdminController::class,
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
            'schoenstatt' => [
                'type'    => Literal::class,
                'options' => [
                    // Change this to something specific to your module
                    'route'    => '/movement',
                    'defaults' => [
                        'controller'    => Controller\SchoenstattController::class,
                        'action'        => 'index',
                    ],
                ],
                'may_terminate' => true,
            ],
            'shrines' => [
                'type'    => Literal::class,
                'options' => [
                    // Change this to something specific to your module
                    'route'    => '/shrines',
                    'defaults' => [
                        'controller'    => Controller\SchoenstattController::class,
                        'action'        => 'shrines',
                    ],
                ],
                'may_terminate' => true,
            ],
            'persons' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/persons',
                    'defaults' => [
                        'controller' => Controller\PersonsController::class,
                        'action'     => 'search',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'search' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/search',
                            'defaults' => [
                                'action'     => 'search',
                            ],
                        ],
                    ],
                    'create' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'person' => [
                        'type'    => Segment::class,
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
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action'     => 'edit',
                                        'entity'    => 'person'
                                    ],
                                ],
                            ],
                            'suggest' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/suggest',
                                    'defaults' => [
                                        'action'     => 'suggest',
                                    ],
                                ],
                            ],
                            'moderate' => [
                                'type'    => Segment::class,
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
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/delete',
                                    'defaults' => [
                                        'action'     => 'delete',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'assignments' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/assignments',
                    'defaults' => [
                        'controller' => Controller\AssignmentsController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'search' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/search',
                            'defaults' => [
                                'action'     => 'search',
                            ],
                        ],
                    ],
                    'advanced-search' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/advanced-search',
                            'defaults' => [
                                'action'     => 'advancedSearch',
                            ],
                        ],
                    ],
                    'create' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'assignment' => [
                        'type'    => Segment::class,
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
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action'     => 'edit',
                                    ],
                                ],
                            ],
                            'suggest' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/suggest',
                                    'defaults' => [
                                        'action'     => 'suggest',
                                    ],
                                ],
                            ],
                            'moderate' => [
                                'type'    => Segment::class,
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
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/delete',
                                    'defaults' => [
                                        'action'     => 'delete',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'roles' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/roles',
                    'defaults' => [
                        'controller' => Controller\RolesController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'create' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'role' => [
                        'type'    => Segment::class,
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
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action'     => 'edit',
                                    ],
                                ],
                            ],
                            'delete' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/delete',
                                    'defaults' => [
                                        'action'     => 'delete',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'associations' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/associations',
                    'defaults' => [
                        'controller' => Controller\AssociationsController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'association' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:sw_id',
                            'constraints' => [
                                ':sw_id' => 'SL[0-9]{1,5}W',
                            ],
                            'defaults' => [
                                'controller' => Controller\AssociationsController::class,
                                'action'     => 'show',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'edit' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action'     => 'edit',
                                    ],
                                ],
                            ],
                            'suggest' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/suggest',
                                    'defaults' => [
                                        'action'     => 'suggest',
                                    ],
                                ],
                            ],
                            'moderate' => [
                                'type'    => Segment::class,
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
                            'create-dioceses' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/create-dioceses',
                                    'defaults' => [
                                        'action'     => 'createDioceses',
                                    ],
                                ],
                            ],
                            'delete' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/delete',
                                    'defaults' => [
                                        'action'     => 'delete',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'old-association' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:association_id',
                            'constraints' => [
                                'association_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'controller' => Controller\AssociationsController::class,
                                'action'     => 'sendToNewUrl',
                            ],
                        ],
                    ],
                    'create' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'controller' => Controller\AssociationsController::class,
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'do-work' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/do-work',
                            'defaults' => [
                                'action'     => 'doWork',
                            ],
                        ],
                    ],
                    'import' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/import',
                            'defaults' => [
                                'controller' => Controller\AssociationsController::class,
                                'action'     => 'import',
                            ],
                        ],
                    ],
                ],
            ],
            'api-v1' => [
                'type'    => Literal::class,
                'options' => [
                    // Change this to something specific to your module
                    'route'    => '/api/v1',
                    'defaults' => [
                        'controller'    => Controller\SchoenstattController::class,
                        'action' => 'v1',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'associations' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/associations[/:sw_id]',
                            'defaults' => [
                                'action' => null,
                                'controller' => Controller\AssociationsApiController::class,
                            ],
                            'constraints' => [
                                'sw_id' => 'SL[12][0-9]{4,4}A',
                            ],
                        ],
                    ],
                    'find-by-kind' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/associations/findByKind',
                            'defaults' => [
                                'controller' => Controller\AssociationsApiController::class,
                                'action'     => 'findByKind',
                            ],
                        ],
                    ],
                    'shrines-json' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/associations/shrines.json',
                            'defaults' => [
                                'controller' => Controller\AssociationsApiController::class,
                                'action'     => 'shrinesJson',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'sion_model' => [
        'problem_providers' => [
            Model\SchoenstattTable::class,
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
                'name'                                  => 'person',
                'table_name'                            => 'sch_persons',
                'table_key'                             => 'PersonId',
                'entity_key_field'                      => 'personId',
                'sion_model_class'                      => Model\SchoenstattTable::class,
                'sion_controllers'                      => [Controller\PersonsController::class],
                'controller_services'                   => [

                ],
                'get_object_function'                   => 'getPerson',
                'get_objects_function'                  => 'getPersons',
//                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation'         => [
                ],
                'name_field'                            => 'fullName',
                'name_field_is_translateable'           => false,
                'country_field'                         => 'country',
                'text_columns'                          => [],
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
                'report_changes'                        => true,
                'index_route'                           => 'persons',
//                 'index_template'                         => 'project/events/index',
                'default_route_key'                     => 'person_id',
//                 'show_action_template'                   => 'persons/person/show',
                'show_route'                            => 'persons/person',
                'show_route_key'                        => 'person_id',
                'show_route_key_field'                  => 'personId',
                'edit_action_form'                      => Form\PersonForm::class,
//                 'edit_action_template'                   => 'project/events/edit',
                'edit_route'                            => 'persons/person/edit',
                'edit_route_key'                        => 'person_id',
                'edit_route_key_field'                  => 'personId',
                'create_action_form'                    => Form\PersonForm::class,
                'create_action_valid_data_handler'      => 'createPerson',
                'create_action_redirect_route'          => 'persons/person',
                'create_action_redirect_route_key'      => 'person_id',
                'create_action_redirect_route_key_field'=> 'personId',
//                 'create_action_template'                 => 'project/events/create',
//                 'touch_default_field'                => 'eventId',
//                 'touch_field_route_key'                  => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                   => 'event_id',
                'database_bound_data_preprocessor'      => 'preprocessPerson',
                'database_bound_data_postprocessor'     => 'postprocessPerson',
//                 'moderate_route'                         => 'persons/person/moderate',
//                 'moderate_route_entity_key'          => 'person_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'           => 'delete_event',
                'delete_action_redirect_route'          => 'schoenstatt',
                'update_columns' => [
                    'lastName'                  => 'LastName',
                    'firstName'                 => 'FirstName',
                    'lastNameWithoutAccents'    => 'LastNameWithoutAccents',
                    'firstNameWithoutAccents'   => 'FirstNameWithoutAccents',
                    'personTags'                => 'PersonTags',
                    'lifeCommunity'             => 'LifeCommunity',
                    'manualTitle'               => 'Title',
                    'automaticTitle'            => 'TitleAutomatic',
                    'spousePersonId'            => 'SpousePersonId',
                    'country'                   => 'Country',
                    'birthDate'                 => 'BirthDate',
                    'priestDate'                => 'PriestDate',
                    'bishopDate'                => 'BishopDate',
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
                    'isAuthor'                  => 'IsAuthor',
                    'isBorrower'                => 'IsBorrower',
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
                'name'                                  => 'association',
                'table_name'                            => 'sch_associations',
                'table_key'                             => 'AssociationId',
                'entity_key_field'                      => 'associationId',
                'sion_model_class'                      => Model\SchoenstattTable::class,
                'sion_controllers'                      => [Controller\AssociationsController::class],
                'controller_services'                   => [
                    CountriesInfo::class,
                ],
                'get_object_function'                   => 'getSimpleAssociationBySwId',
                'get_objects_function'                  => 'getAssociations',
                'name_field'                            => 'associationName',
                'name_field_is_translateable'           => false,
                'format_view_helper'                    => 'formatEntity',
                'country_field'                         => 'country',
                'report_changes'                        => true,
                'required_columns_for_creation'         => [ //required for creation
                    'name',
                    'kind',
                ],
                'index_route'                           => 'associations',
//                 'index_template'                        => 'project/events/index',
                'default_route_key'                     => 'sw_id',
                'show_route'                            => 'associations/association',
                'show_route_key'                        => 'sw_id',
                'show_route_key_field'                  => 'identifier',
                'edit_action_form'                      => Form\AssociationForm::class,
//                 'edit_action_template'                   => 'project/events/edit',
                'edit_route'                            => 'associations/association/edit',
                'edit_route_key'                        => 'sw_id',
                'edit_route_key_field'                  => 'identifier',
                'create_action_form'                    => Form\AssociationForm::class,
//                 'create_action_valid_data_handler'      => 'createAssociation',
                'create_action_redirect_route'          => 'associations/association',
                'create_action_redirect_route_key'      => 'sw_id',
                'create_action_redirect_route_key_field'=> 'identifier',
//                 'create_action_template'                   => 'project/events/create',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'             => 'delete_event',
                'delete_action_redirect_route'          => 'associations',
//                 'touch_default_field'                   => 'eventId',
//                 'touch_field_route_key'                   => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                    => 'event_id',
                'database_bound_data_preprocessor'      => 'associationPreprocessor',
                'database_bound_data_postprocessor'     => 'associationPostprocessor',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'             => 'event_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'many_to_one_update_columns'            => [
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
                    'overrideNameFormat'        => 'OverrideNameFormat',
                    'isNameTranslateable'       => 'IsNameTranslateable',
                    'internalName'              => 'InternalName',
                    'isInternalNameTranslateable' => 'IsInternalNameTranslateable',
                    'parentId'                  => 'Parent',
                    'kind'                      => 'Kind',
                    'country'                   => 'Country',
                    'timeZoneId'                => 'TimeZone',
                    'openingHoursHuman'         => 'OpeningHoursHuman',
                    'openingHoursHumanUpdatedOn'=> 'OpeningHoursHumanUpdatedOn',
                    'openingHoursHumanUpdatedBy'=> 'OpeningHoursHumanUpdatedBy',
                    'openingHoursSpecificationJson' => 'OpeningHoursSpecification',
                    'openingHoursSpecificationJsonUpdatedOn' => 'OpeningHoursSpecificationUpdatedOn',
                    'openingHoursSpecificationJsonUpdatedBy' => 'OpeningHoursSpecificationUpdatedBy',
                    'foundationDate'            => 'FoundationDate',
                    'suppressionDate'           => 'SuppressionDate',
                    'isLifeCommunity'           => 'IsLifeCommunity',
                    'isAuthor'                  => 'IsAuthor',
                    'isActive'                  => 'IsActive',

                    'geoPoint'                  => 'Location',
                    'latitude'                  => 'Latitude', //@deprecated
                    'longitude'                 => 'Longitude', //@deprecated
                    'idealEn'                   => 'IdealEn',
                    'idealEs'                   => 'IdealEs',
                    'idealDe'                   => 'IdealDe',
                    'idealPt'                   => 'IdealPt',
                    'idealFr'                   => 'IdealFr',
                    'visitorsInformationEn'     => 'VisitorsInformationEn',
                    'visitorsInformationEs'     => 'VisitorsInformationEs',
                    'visitorsInformationDe'     => 'VisitorsInformationDe',
                    'visitorsInformationPt'     => 'VisitorsInformationPt',
                    'visitorsInformationFr'     => 'VisitorsInformationFr',
                    'historyEn'                 => 'HistoryEn',
                    'historyEs'                 => 'HistoryEs',
                    'historyDe'                 => 'HistoryDe',
                    'historyPt'                 => 'HistoryPt',
                    'historyFr'                 => 'HistoryFr',

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
                    'street1'                   => 'Post1Street1',
                    'street2'                   => 'Post1Street2',
                    'cityState'                 => 'Post1CityState',
                    'zip'                       => 'Post1Zip',
//                     'country'              => 'Post1Country',
                    'postStreet1'               => 'Post2Street1', //@todo DEPRECATED
                    'postStreet2'               => 'Post2Street2', //@todo DEPRECATED
                    'postCityState'             => 'Post2CityState', //@todo DEPRECATED
                    'postZip'                   => 'Post2Zip', //@todo DEPRECATED
                    'postCountry'               => 'Post2Country', //@todo DEPRECATED
                    'googlePlaceId'             => 'GooglePlaceId',
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
                'name'                                  => 'role',
                'table_name'                            => 'sch_roles',
                'table_key'                             => 'RoleId',
                'entity_key_field'                      => 'roleId',
                'sion_model_class'                      => Model\SchoenstattTable::class,
                'sion_controllers'                      => [Controller\RolesController::class],
                'controller_services'                   => [
                    
                ],
                'get_object_function'                   => 'getRole',
                'get_objects_function'                  => 'getRoles',
//                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation'         => [
                    'roleTitle',
                    'associationId',
                ],
                'name_field'                            => 'formattedRoleTitle',
                'name_field_is_translateable'           => false,
//                 'country_field'                          => 'country',
                'text_columns'                          => [],
                'many_to_one_update_columns'            => [
                ],
                'report_changes'                        => true,
                'index_route'                           => 'roles',
//                 'index_template'                         => 'project/events/index',
                'default_route_key'                     => 'role_id',
//                 'show_action_template'                   => 'project/events/show',
                'show_route'                            => 'associations/old-association',
                'show_route_key'                        => 'association_id',
                'show_route_key_field'                  => 'associationId',
                'edit_action_form'                      => Form\RoleForm::class,
//                 'edit_action_template'                   => 'project/events/edit',
                'edit_route'                            => 'roles/role/edit',
                'edit_route_key'                        => 'role_id',
                'edit_route_key_field'                  => 'roleId',
                'create_action_form'                    => Form\RoleForm::class,
//                 'create_action_valid_data_handler'       => 'createEvent',
                'create_action_redirect_route'          => 'associations/old-association',
                'create_action_redirect_route_key'      => 'association_id',
                'create_action_redirect_route_key_field'=> 'associationId',
//                 'create_action_template'                 => 'project/events/create',
//                 'touch_default_field'                => 'eventId',
//                 'touch_field_route_key'                  => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                   => 'event_id',
//                 'database_bound_data_preprocessor'       => 'preprocessEvent',
//                 'database_bound_data_postprocessor'  => 'postprocessEvent',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'          => 'event_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'           => 'delete_event',
                'delete_action_redirect_route'          => 'roles',
                'update_columns' => [
                    'roleId'                    => 'RoleId',
                    'roleTitle'                 => 'RoleTitle',
                    'associationId'             => 'AssociationId',
                    'isMainRole'                => 'IsMainRole',
                    'isMainContact'             => 'IsMainContact',
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
                'name'                                  => 'assignment',
                'table_name'                            => 'sch_assignments',
                'table_key'                             => 'AssignmentId',
                'entity_key_field'                      => 'assignmentId',
                'sion_model_class'                      => Model\SchoenstattTable::class,
                'sion_controllers'                      => [Controller\AssignmentsController::class],
                'controller_services'                   => [
                    Form\AdvancedSearchForm::class,
                ],
                'get_object_function'                   => 'getAssignment',
                'get_objects_function'                  => 'getAssignments',
//                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation'         => [
                    'roleId',
                    'personId',
                ],
                'name_field'                            => 'roleTitle',
                'name_field_is_translateable'           => true,
//                 'country_field'                          => 'country',
                'text_columns'                          => [],
//                 'many_to_one_update_columns'             => [
//                 ],
                'report_changes'                        => true,
                'index_route'                           => 'assignments/search',
//                 'index_template'                         => 'project/events/index',
                'default_route_key'                     => 'assignment_id',
//                 'show_action_template'                   => 'project/events/show',
                'show_route'                            => 'associations/association',
                'show_route_key'                        => 'association_id',
                'show_route_key_field'                  => 'associationId',
                'edit_action_form'                      => 'Schoenstatt\Form\EditAssignmentForm',
//                 'edit_action_template'                   => 'project/events/edit',
                'edit_route'                            => 'assignments/assignment/edit',
                'edit_route_key'                        => 'assignment_id',
                'edit_route_key_field'                  => 'assignmentId',
                'create_action_form'                    => Form\AssignmentForm::class,
//                 'create_action_valid_data_handler'       => 'createEvent',
                'create_action_redirect_route'          => 'associations/association',
                'create_action_redirect_route_key'      => 'association_id',
                'create_action_redirect_route_key_field'=> 'associationId',
//                 'create_action_template'                 => 'project/events/create',
//                 'touch_default_field'                => 'eventId',
//                 'touch_field_route_key'                  => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                   => 'event_id',
//                 'database_bound_data_preprocessor'       => 'preprocessEvent',
//                 'database_bound_data_postprocessor'  => 'postprocessEvent',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'          => 'event_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'           => 'delete_event',
                'delete_action_redirect_route'          => 'associations',
                'update_columns'                        => [
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
    'bjyauthorize' => [
        'guards' => [
            'BjyAuthorize\Guard\Route' => [
                ['route' => 'shrines', 'roles' => ['user', 'guest', null]],
                ['route' => 'admin/import-father', 'roles' => ['sch_administrator']],
                ['route' => 'admin/import-shrines', 'roles' => ['administrator']],
                ['route' => 'admin/maintenance', 'roles' => ['administrator']],
                ['route' => 'assignments/assignment', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'assignments/assignment/edit', 'roles' => ['sch_moderator']],
                ['route' => 'assignments/assignment/delete', 'roles' => ['sch_general_moderator']],
                ['route' => 'assignments/search', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'assignments/advanced-search', 'roles' => ['sch_user']],
                ['route' => 'assignments/create', 'roles' => ['sch_moderator']],

                ['route' => 'persons', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'persons/person', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'persons/search', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'persons/create', 'roles' => ['sch_moderator']],
                ['route' => 'persons/person/edit', 'roles' => ['sch_moderator']],
                ['route' => 'persons/person/suggest', 'roles' => ['sch_user']],
                ['route' => 'persons/person/moderate', 'roles' => ['sch_moderator']],
                ['route' => 'persons/person/delete', 'roles' => ['sch_general_moderator']],

                ['route' => 'associations', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'associations/association', 'roles' => ['sch_user', 'sch_basic', 'guest', 'user']],
                ['route' => 'associations/create', 'roles' => ['sch_moderator']],
                ['route' => 'associations/import', 'roles' => ['sch_administrator']],
                ['route' => 'associations/do-work', 'roles' => ['guest', 'user', null]], //uses api key authorization
                ['route' => 'associations/association/edit', 'roles' => ['sch_moderator']],
                ['route' => 'associations/association/moderate', 'roles' => ['sch_moderator']],
                ['route' => 'associations/association/suggest', 'roles' => ['sch_user']],
                ['route' => 'associations/association/delete', 'roles' => ['sch_general_moderator']],
                ['route' => 'associations/association/create-dioceses', 'roles' => ['sch_general_moderator']],

                ['route' => 'roles', 'roles' => ['sch_moderator']],
                ['route' => 'roles/create', 'roles' => ['sch_moderator']],
                ['route' => 'roles/role', 'roles' => ['sch_moderator']],
                ['route' => 'roles/role/edit', 'roles' => ['sch_moderator']],
                ['route' => 'roles/role/delete', 'roles' => ['sch_general_moderator']],

                ['route' => 'api-v1', 'roles' => ['guest', 'user', null]],
                ['route' => 'api-v1/associations', 'roles' => ['guest', 'user', null]],
                ['route' => 'api-v1/find-by-kind', 'roles' => ['guest', 'user', null]],
                ['route' => 'api-v1/shrines-json', 'roles' => ['guest', 'user', null]],
            ],
        ],
    ],
];
