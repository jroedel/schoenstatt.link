<?php
namespace Books;

use Zend\Db\Adapter\Adapter;
use Books\Service\LibraryControllerFactory;
use Books\Controller\PublicationsController;
use Books\Controller\LibrariesController;
use Books\Controller\BooksController;
use Books\Controller\CheckoutsController;
use Books\Controller\BorrowersController;
use Books\Controller\CollectionsController;
use Books\Controller\LibraryImportsController;
use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use Books\Service\ConfigServiceFactory;
use Books\Service\PublicationsTableFactory;
use Books\Model\PublicationsTable;
use Books\Service\LibraryTableServiceFactory;
use Books\Model\LibraryTable;
use Books\Form\SearchForm;
use Books\Service\SearchFormFactory;
use Books\Service\LibraryFormFactory;
use Books\Form\LibraryForm;
use Books\Form\CollectionForm;
use Books\Service\CollectionFormFactory;
use Books\Service\CheckoutFormFactory;
use Books\Service\PublicationsSearchFormFactory;
use Books\Form\PublicationsSearchForm;
use Books\Form\BookForm;
use Books\Service\BookFormFactory;
use Books\Service\FathersObjectsFactory;
use Books\Service\BorrowersValueOptionsService;
use Books\Service\AuthorsValueOptionsService;
use Books\Service\LanguagesValueOptionsFactory;
use Books\Service\BooksMailerFactory;
use Books\Mailing\BooksMailer;
use Books\Service\PublicationFormFactory;
use Books\Form\PublicationForm;
use Books\Service\FormatPublicationFactory;
use Books\View\Helper\FormatPublicationUrlObject;
use Books\View\Helper\FormatField;
use Books\View\Helper\BooksJsonLd;
use Books\View\Helper\FormSelectWithoutOptions;
use Books\Form\ImportForm;
use Schoenstatt\Model\SchoenstattTable;
use BjyAuthorize\Guard\Route;
use BjyAuthorize\Provider\Rule\Config;
use Schoenstatt\Service\PatresGateway;
use JTranslate\Model\TranslationsTable;
use SionModel\Service\ProblemService;
use SionModel\Db\Model\FilesTable;
use Zend\ServiceManager\Proxy\LazyServiceFactory;
use Books\View\Helper\Coins;
use Books\Form;
use Books\Model;
use Books\Service\DriveGateway;
use Books\Service\DriveGatewayFactory;
use Books\View\Helper\FileSize;
use Books\Service\LibraryInfoFactory;
use SionModel\Db\Model\PredicatesTable;

return [
    'books' => [
        'books_db_adapter' => Adapter::class,
        'book_format_type_value_options' => [
            'AudiobookFormat'   => 'AudiobookFormat',
            'EBook'             => 'EBook',
            'Hardcover'         => 'Hardcover',
            'Paperback'         => 'Paperback',
            'Manuscript'        => 'Manuscript',
            'GraphicNovel'      => 'GraphicNovel',
        ],
        'library_filiation_options' => [
            50  => 'Vaterhaus',
            12  => 'Colegio Mayor',
            44  => 'Studentat Kentenich Vidhyaniketan',
            35  => 'Novitiate Tuparenda',
            7   => 'Bellavista - casa central',
        ],
        'language_value_options' => [
            'en' => 'English',
            'es' => 'Spanish',
            'de' => 'German',
            'fr' => 'French',
            'pl' => 'Polish',
            'cz' => 'Czhec',
            'fr' => 'French',
            'la' => 'Latin',
            'gr' => 'Greek',
        ],
        'url_label_value_options' => [
            'Download'  => 'Download',
            'Purchase'  => 'Purchase',
            'Borrow'    => 'Borrow',
            'Wikipedia' => 'Wikipedia',
            'Information'=> 'Information',
        ],
        'admin_pages' => [
            'books/create'            => [
                'label' => "Add new book",
                'description' => 'Add a book to the database.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'checkouts/library/current'=> [
                'label' => "Review checkouts",
                'description' => 'List and review current checkouts for this library.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'checkouts/library/overdue'=> [
                'label' => "Review overdue books",
                'description' => 'List and review overdue checkouts for this library.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'libraries/library/checkin'=> [
                'label' => "Check-in books",
                'description' => 'Check books back into the library.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'libraries/library/mass-checkout'=> [
                'label' => "Mass book checkouts",
                'description' => 'Register offline checkout notices.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'libraries/library/inactivate-books'=> [
                'label' => "Mass book inactivation",
                'description' => 'Inactivate books in bulk.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'libraries/library/label-management'=> [
                'label' => "Label management",
                'description' => 'Print new call number labels and manage books pending a label change.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'libraries/library/batch-operations'=> [
                'label' => "Batch book operations",
                'description' => 'Perform changes to multiple books including inactivation, label printing, or changes to collection, language, or category.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
//             'libraries/library/inactivate-books'=> [
//                 'label' => "Inactivate books",
//                 'description' => 'Remove books from the library.',
//                 'route_parameters' => [
//                     'library_id' => ':libraryId',
//                 ],
//             ],
//             'libraries/popular-books'=> [
//                 'label' => "Popular books",
//                 'description' => 'View list of most popular books.',
//             ],
//             'admin/view-searches'       => [
//                 'label' => "View Searches",
//                 'description' => '',
//             ],
            'library-imports/library'   => [
                'label' => "Library import/export",
                'description' => 'Import or export library book information.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'libraries/library/edit'   => [
                'label' => "Library configuration",
                'description' => 'Edit library configuration options. For advanced users.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'sion-model/view-changes'   => [
                'label' => "View changes",
                'description' => 'View the recent changes made to the database.',
            ],
//             'juser'                     => [
//                 'label' => "User management",
//                 'description' => 'Add new users, modify permissions, or reset passwords.',
//             ],
//             'admin/moderate'            => [
//                 'label' => "Review suggestions",
//                 'description' => 'Moderate user data suggestions.',
//             ],
//             'jtranslate'                => [
//                 'label' => "Manage translations",
//                 'description' => 'Update database translations',
//             ],
            'sion-model/data-problems'  => [
                'label' => "Data problems",
                'description' => 'Review potential problems with the data in the database.',
            ],
            'sion-model/auto-fix-data-problems' => [
                'label' => "Auto-fix data problems",
                'description' => 'Try to automatically fix some of the data problems.',
            ],
//             'admin/website-status'      => [
//                 'label' => "Website status",
//                 'description' => 'Known issues or upcoming plans.',
//             ],
//             'admin/export-fathers'      => [
//                 'label' => "Export fathers",
//                 'description' => '',
//             ],
        ],
    ],
    'schoenstatt' => [
        'person_value_options_providers' => [
            'all-borrowers' => [
                'target'    => 'Books\BorrowersValueOptions',
                'label'     => 'All borrowers',
            ],
        ],
    ],
     'controllers' => [
//         'invokables' => [
//             PublicationsController::class   => PublicationsController::class,
//             LibrariesController::class      => LibrariesController::class,
//             BooksController::class          => BooksController::class,
//             CheckoutsController::class      => CheckoutsController::class,
//             CollectionsController::class    => CollectionsController::class,
//             LibraryImportsController::class => LibraryImportsController::class,
//             BorrowersController::class      => BorrowersController::class,
//         ],
         'factories' => [
             PublicationsController::class      => SionControllerFactory::class,
             LibrariesController::class         => SionControllerFactory::class,
             BooksController::class             => SionControllerFactory::class,
             CheckoutsController::class         => SionControllerFactory::class,
             CollectionsController::class       => SionControllerFactory::class,
             LibraryImportsController::class    => SionControllerFactory::class,
             Controller\EventsController::class => SionControllerFactory::class,
        ],
        'abstract_factories' => [
            \Books\Controller\LazyControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Books\Config'                  => ConfigServiceFactory::class,
            PublicationsTable::class        => PublicationsTableFactory::class,
            LibraryTable::class             => LibraryTableServiceFactory::class,
            SearchForm::class               => SearchFormFactory::class,
            LibraryForm::class              => LibraryFormFactory::class,
            CollectionForm::class           => CollectionFormFactory::class,
            'Books\Form\CreateCheckoutForm' => CheckoutFormFactory::class,
            PublicationForm::class          => PublicationFormFactory::class,
            PublicationsSearchForm::class   => PublicationsSearchFormFactory::class,
            BookForm::class                 => BookFormFactory::class,
            'Books\FathersObjects'          => FathersObjectsFactory::class,
            'Books\BorrowersValueOptions'   => BorrowersValueOptionsService::class,
            'Books\AuthorsValueOptions'     => AuthorsValueOptionsService::class,
            'Books\LanguagesValueOptions'   => LanguagesValueOptionsFactory::class,
            BooksMailer::class              => BooksMailerFactory::class,
            DriveGateway::class             => DriveGatewayFactory::class,
        ],
        'lazy_services' => [
            // Mapping services to their class names is required
            // since the ServiceManager is not a declarative DIC.
            'class_map' => [
                LibraryForm::class => LibraryForm::class,
                SearchForm::class => SearchForm::class,
                BooksMailer::class => BooksMailer::class,
                PublicationsTable::class => PublicationsTable::class,
            ],
        ],
        'delegators' => [
            LibraryForm::class => [
                LazyServiceFactory::class,
            ],
            SearchForm::class => [
                LazyServiceFactory::class,
            ],
            BooksMailer::class => [
                LazyServiceFactory::class,
            ],
            PublicationsTable::class => [
                LazyServiceFactory::class,
            ],
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'formatPublication'             => FormatPublicationFactory::class,
            'libraryInfo'                   => LibraryInfoFactory::class
        ],
        'invokables' => [
            'coins'                         => Coins::class,
            'fileSize'                      => FileSize::class,
            'formatPublicationUrlObject'    => FormatPublicationUrlObject::class,
            'formatField'                   => FormatField::class,
            'booksJsonLd'                   => BooksJsonLd::class,
            'formSelectWithoutOptions'      => FormSelectWithoutOptions::class,
        ],
    ],
    'known_issues' => [ //possible keys: description, completed
//             'Assignment changes' => [
//                 'description' => 'Assignment names aren\'t appearing correctly in the "View Changes" page.',
//             ],
    ],
    'upcoming_features' => [ //possible keys: description, completed
        'Automatic repeat-translations' => [
            'description' => 'When we insert a new phrase to be translated, we should make sure check for the same phrase in a different domain, and insert the translations as well.',
        ],
        'Automated data issue tracking system' => [
            'description' => 'In order to qualitatively improve data completeness, we would identify high, medium and low importance issues regarding data records to more systematically track down missing information for the database.',
        ],
        'New email verification system' => [
            'description' => 'Of the personal contact information, the most important is the user\'s email address. Verification data is out-of-date, and should be updated.',
        ],
        'Photo upload system' => [
            'description' => 'The usefulness of this site for the average father could be greatly improved by adding user-uploaded photo capabilities. This applies especially for photos of course life.'
        ],
    ],
    'router' => [
        'routes' => [
            'publications' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/literature',
                    'defaults' => [
                        'controller' => PublicationsController::class,
                        'action'     => 'languageIndex',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'index' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:inLanguage',
                            'constraints' => [
                                'inLanguage' => '[a-z]{2,2}',
                            ],
                            'defaults' => [
                                'action'     => 'index',
                            ],
                        ],
                    ],
                    'export' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/export',
                            'defaults' => [
                                'action'     => 'export',
                            ],
                        ],
                    ],
                    'prime-authors' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/prime-authors',
                            'defaults' => [
                                'action'     => 'primeAuthors',
                            ],
                        ],
                    ],
                    'trim-titles' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/trim-titles',
                            'defaults' => [
                                'action'     => 'trimTitles',
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
                    'import' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/import',
                            'defaults' => [
                                'action'     => 'import',
                            ],
                        ],
                    ],
                    'search' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/search',
                            'defaults' => [
                                'action'     => 'search',
                            ],
                        ],
                    ],
                    'publication' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:publication_id',
                            'constraints' => [
                                'publication_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'action'     => 'show',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'create-new-edition' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/create-new-edition',
                                    'defaults' => [
                                        'action'     => 'createNewEdition',
                                    ],
                                ],
                            ],
                            'upload-cover' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/upload-cover',
                                    'defaults' => [
                                        'action'     => 'uploadCover',
                                    ],
                                ],
                            ],
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
                        ],
                    ],
                ],
            ],
            'books' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/books',
                    'defaults' => [
                        'controller' => BooksController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'book' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:book_id',
                            'constraints' => [
                                'book_id' => '[0-9]{1,5}',
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
                        ],
                    ],
                    'create' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/create/:library_id',
                            'constraints' => [
                                'library_id' => '[0-9]{1,3}',
                            ],
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                ],
            ],
            'libraries' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/libraries',
                    'defaults' => [
                        'controller' => LibrariesController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'library' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:library_id',
                            'constraints' => [
                                'library_id' => '[0-9]{1,4}', //@todo accept library names
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
                            'delete' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/delete',
                                    'defaults' => [
                                        'action'     => 'delete',
                                    ],
                                ],
                            ],
                            'checkout' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/checkout',
                                    'defaults' => [
                                        'action'     => 'create',
                                        'controller' => CheckoutsController::class,
                                    ],
                                ],
                            ],
                            'checkin' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/checkin',
                                    'defaults' => [
                                        'action'     => 'checkin',
                                        'controller' => CheckoutsController::class,
                                    ],
                                ],
                            ],
                            'mass-checkout' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/mass-checkout',
                                    'defaults' => [
                                        'action'     => 'massCheckout',
                                        'controller' => CheckoutsController::class,
                                    ],
                                ],
                            ],
                            'label-management' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/label-management',
                                    'defaults' => [
                                        'action'     => 'labelManagement',
                                        'controller' => LibrariesController::class,
                                    ],
                                ],
                            ],
                            'batch-operations' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/batch-operations',
                                    'defaults' => [
                                        'action'     => 'batchOperations',
                                        'controller' => LibrariesController::class,
                                    ],
                                ],
                            ],
                            'send-book-notices' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/send-book-notices',
                                    'defaults' => [
                                        'action'     => 'sendBookNotices',
                                        'controller' => LibrariesController::class,
                                    ],
                                ],
                            ],
                            'inactivate-books' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/inactivate-books',
                                    'defaults' => [
                                        'action'     => 'inactivateBooks',
                                        'controller' => LibrariesController::class,
                                    ],
                                ],
                            ],
                            'admin' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/admin',
                                    'defaults' => [
                                        'action'     => 'admin',
                                    ],
                                ],
                            ],
                            'import' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/import',
                                    'defaults' => [
                                        'action'     => 'import',
                                    ],
                                ],
                            ],
                            'book-list' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/book-list',
                                    'defaults' => [
                                        'action'     => 'bookList',
                                    ],
                                ],
                            ],
                            'book-list-json' => [
                                'type' => Literal::class,
                                'options' => [
                                    'route'    => '/book-list-json',
                                    'defaults' => [
                                        'action'     => 'getBookListJson',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'create' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                ],
            ],
            'borrowers' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/borrowers',
                    'defaults' => [
                        'controller'=> BorrowersController::class,
                        'action'    => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'borrower' => [
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
                    ],
                    'fix-person-id' => [ //We will never show all imports at once, just per-library
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/fix-person-id',
                            'defaults' => [
                                'action'     => 'fixPersonId',
                            ],
                        ],
                    ],
                ],
            ],
            'library-imports' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/library-imports',
                    'defaults' => [
                        'controller'=> LibraryImportsController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'library' => [ //We will never show all imports at once, just per-library
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/library/:library_id',
                            'constraints' => [
                                'library_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'action'     => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'create' => [ //We will never show all imports at once, just per-library
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/create',
                                    'defaults' => [
                                        'action'     => 'create',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'library-import' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:import_id',
                            'constraints' => [
                                'import_id' => '[0-9]{1,5}',
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
                            'cancel' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/cancel',
                                    'defaults' => [
                                        'action'     => 'cancel',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'checkouts' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/checkouts',
                    'defaults' => [
                        'controller'=> CheckoutsController::class,
                        'action'    => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'checkout' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:checkout_id',
                            'constraints' => [
                                'book_id' => '[0-9]{1,5}',
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
                        ],
                    ],
                    'library' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/library/:library_id',
                            'constraints' => [
                                'library_id'    => '[0-9]{1,5}',
                                'subset'        => '(all|current|overdue)',
                            ],
                            'defaults' => [
                                'action'    => 'library',
                                'subset'    => 'all',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'current' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/current',
                                    'defaults' => [
                                        'subset'     => 'current',
                                    ],
                                ],
                            ],
                            'overdue' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/overdue',
                                    'defaults' => [
                                        'subset'     => 'overdue',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'collections' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/collections',
                    'defaults' => [
                        'controller' => CollectionsController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'collection' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:collection_id',
                            'constraints' => [
                                'collection_id' => '[0-9]{1,5}',
                            ],
                        ],
                        'may_terminate' => false,
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
                        ],
                    ],
                    'create' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/create/:library_id',
                            'constraints' => [
                                'library_id' => '[0-9]{1,3}',
                            ],
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'sion_model' => [
        'entities' => [
            /**
             * For more information on entity config:
             * @see \SionModel\Entity\Entity
             */
            'event' => [
                'name'                                      => 'event',
                'table_name'                                => 'events',
                'table_key'                                 => 'EventId',
                'sion_controllers'                          => [Controller\EventsController::class],
                'controller_services'                       => [],
                'entity_key_field'                          => 'eventId',
                'sion_model_class'                          => Model\EventTextTable::class,
                'get_object_function'                       => 'getEvent',
                'get_objects_function'                      => 'getEvents',
                //                 'format_view_helper'                        => 'formatEvent',
                'required_columns_for_creation'             => [
                    'startDate',
                    'durationInDays',
                    'accuracy'
                ],
                'name_field'                                => 'titleEn',
                'name_field_is_translateable'               => false,
                'country_field'                             => 'country',
                //                 'text_columns'                              => [],
//                 'many_to_one_update_columns'                => [
    //                     'email'    => 'contactInfo',
    //                     'cell'    => 'contactInfo',
    //                 ],
                'report_changes'                            => true,
                'index_route'                               => 'events',
                'index_template'                            => 'books/events/index',
                'default_route_key'                         => 'association_id',
                //                 'show_action_template'                      => 'project/events/show',
                'show_route'                                => 'events/event',
                'show_route_key'                            => 'event_id',
                'show_route_key_field'                      => 'eventId',
                //'edit_action_form'                          => Form\EditEventForm::class,
                //                 'edit_action_template'                      => 'project/events/edit',
                'edit_route'                                => 'events/event/edit',
                'edit_route_key'                            => 'event_id',
                'edit_route_key_field'                      => 'eventId',
                //'create_action_form'                        => Form\CreateEventForm::class,
                'create_action_valid_data_handler'          => 'createEvent',
                'create_action_redirect_route'              => 'events/event',
                'create_action_redirect_route_key'          => 'event_id',
                'create_action_redirect_route_key_field'    => 'eventId',
//                 'create_action_template'                    => 'project/events/create',
//                 'touch_default_field'                       => 'eventId',
//                 'touch_route_key'                           => 'event_id',
//                 'touch_field_route_key'                     => 'event_id',
//                 'touch_json_route'                          => 'events/event/touch',
//                 'touch_json_route_key'                      => 'event_id',
//                 'database_bound_data_preprocessor'          => 'preprocessEvent',
//                 'database_bound_data_postprocessor'         => 'postprocessEvent',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'moderate_route_entity_key'                 => 'event_id',
//                 'suggest_form'                              => Form\SuggestEventForm::class,
                'enable_delete_action'                      => true,
                'delete_route_key'                          => 'event_id',
                'delete_action_redirect_route'              => 'events',
                
                'acl_resource_id_field'                     => 'aclResourcesId',
                'acl_show_permission'                       => 'show',
                'acl_edit_permission'                       => 'edit',
                'acl_suggest_permission'                    => 'suggest',
                'acl_moderate_permission'                   => 'moderate',
                'acl_delete_permission'                     => 'delete',
                
                'update_columns'                            => [
                    'eventId' => 'EventId',
                    'titleEn' => 'TitleEn',
                    'titleEs' => 'TitleEs',
                    'titleDe' => 'TitleDe',
                    'titlePt' => 'TitlePt',
                    'titleFr' => 'TitleFr',
                    'country' => 'Country',
                    'originalLanguage' => 'OriginalLanguage',
                    'descriptionEn' => 'DescriptionEn',
                    'descriptionEs' => 'DescriptionEs',
                    'descriptionDe' => 'DescriptionDe',
                    'descriptionPt' => 'DescriptionPt',
                    'descriptionFr' => 'DescriptionFr',
                    'startDate' => 'StartDate',
                    'durationInDays' => 'DurationInDays',
                    'accuracy' => 'Accuracy',
                    'bestTextQuality' => 'BestTextQuality',
                    'place' => 'Place',
                    'tags' => 'Tags',
                    'adminTags' => 'AdminTags',
                    'audienceText' => 'AudienceText',
                    'abbreviationEn' => 'AbbreviationEn',
                    'abbreviationEs' => 'AbbreviationEs',
                    'abbreviationDe' => 'AbbreviationDe',
                    'abbreviationPt' => 'AbbreviationPt',
                    'abbreviationFr' => 'AbbreviationFr',
                    'aclResourcesId' => 'AclResourcesId',
                    'publicNotes' => 'PublicNotes',
                    'publicNotesUpdatedOn' => 'PublicNotesUpdatedOn',
                    'publicNotesUpdatedBy' => 'PublicNotesUpdatedBy',
                    'adminNotes' => 'AdminNotes',
                    'adminNotesUpdatedOn' => 'AdminNotesUpdatedOn',
                    'adminNotesUpdatedBy' => 'AdminNotesUpdatedBy',
                    'updatedOn' => 'UpdatedOn',
                    'updatedBy' => 'UpdatedBy',
                    'createdOn' => 'CreatedOn',
                    'createdBy' => 'CreatedBy',
                    'legacySource' => 'LegacySource',
                    'legacyFile' => 'LegacyFile',
                    'legacyFileDateModified' => 'LegacyFileDateModified',
                ],
            ],
            'library' => [
                'name'									=> 'library',
                'table_name' 							=> 'lib_libraries',
                'table_key' 							=> 'LibraryId',
                'entity_key_field'               		=> 'libraryId',
                'sion_model_class'               		=> LibraryTable::class,
                'sion_controllers'                      => [LibrariesController::class],
                'controller_services'                   => [
                    SearchForm::class,
                    'Books\BorrowersValueOptions',
                    SchoenstattTable::class,
                    TranslationsTable::class,
                    ProblemService::class,
                    //BooksMailer::class, @todo return this to the controller
                    PublicationsTable::class,
                ],
                'get_object_function' 					=> 'getLibrary',
                'get_objects_function'               	=> 'getUnlinkedLibraries',
//                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation' 		=> [
                    'name',
                ],
                'name_field'               				=> 'name',
                'name_field_is_translateable'           => true,
//                 'country_field'               			=> 'country',
                'report_changes'               			=> true,
                'index_route'               			=> 'libraries',
//                 'index_template'               			=> 'project/events/index',
                'default_route_key'                     => 'library_id',
//                 'show_action_template'               	=> 'project/events/show',
                'show_route' 							=> 'libraries/library',
                'show_route_key' 						=> 'library_id',
                'show_route_key_field' 					=> 'libraryId',
                'edit_action_form'               		=> LibraryForm::class,
//                 'edit_action_template'               	=> 'libraries/library/edit',
                'edit_route'               				=> 'libraries/library/edit',
                'edit_route_key'               			=> 'library_id',
                'edit_route_key_field'           		=> 'libraryId',
                'create_action_form'              		=> LibraryForm::class,
//                 'create_action_valid_data_handler'		=> 'createEvent',
                'create_action_redirect_route'         	=> 'libraries/library',
                'create_action_redirect_route_key'    	=> 'library_id',
                'create_action_redirect_route_key_field'=> 'libraryId',
//                 'create_action_template'           		=> 'project/events/create',
//                 'touch_default_field'               	=> 'eventId',
//                 'touch_field_route_key'           		=> 'event_id',
//                 'touch_json_route'               		=> 'events/event/touch',
//                 'touch_json_route_key'            		=> 'event_id',
//                 'database_bound_data_preprocessor' 		=> 'preprocessEvent',
//                 'database_bound_data_postprocessor' 	=> 'postprocessEvent',
//                 'moderate_route' 						=> 'events/event/moderate',
//                 'moderate_route_entity_key' 			=> 'event_id',
//                 'suggest_form'               			=> 'Project\Form\SuggestEventForm',
//                 'enable_delete_action' 					=> true,
//                 'delete_action_acl_resource' 			=> 'event_:id',
//                 'delete_action_acl_permission' 			=> 'delete_event',
//                 'delete_action_redirect_route' 			=> 'events',
                'acl_resource_id_field'                     => 'resourceId',
                'acl_show_permission'                       => 'show',
                'acl_edit_permission'                       => 'administrate',
                'acl_suggest_permission'                    => 'suggest',
                'acl_moderate_permission'                   => 'administrate',
//                 'acl_delete_permission'                     => 'administrate',
                'update_columns' => [
                    'libraryId'             => 'LibraryId',
                    'name'                  => 'LibraryName',
                    'description'           => 'Description',
                    'callNumberPlaceholder' => 'CallNumberPlaceholder',
                    'callNumberHelpText'    => 'CallNumberHelpText',
                    'callNumberExplanation' => 'CallNumberExplanation',
                    'filiationId'           => 'FiliationId',
                    'contactPersonId'       => 'ContactPerson',
                    'contactEmail'          => 'ContactEmail',
                    'mainShowDisplay'       => 'MainShowDisplay',
                    'useCollections'        => 'UseCollections',
                    'allowCollectionlessBooks'=> 'AllowCollectionlessBooks',
                    'mainCollectionId'      => 'MainCollectionId',
                    'requireCallNumbers'    => 'RequireCallNumbers',
                    'callNumberRegex'       => 'CallNumberRegex',
                    'enforceCallNumberRegex'=> 'EnforceCallNumberRegex',
                    'labelLine1'            => 'LabelLine1',
                    'labelLine2'            => 'LabelLine2',
                    'labelLine3'            => 'LabelLine3',
                    'barcodeText'           => 'BarcodeText',
                    'createCheckoutsIfCheckingInANonCheckedOutBook' => 'CreateCheckoutsIfCheckingInANonCheckedOutBook',
                    'defaultCheckoutPersonId' => 'DefaultCheckoutPersonId',
                    'defaultCheckoutTimePeriodInDays' => 'DefaultCheckoutTimePeriodInDays',
                    'enableCheckouts'       => 'EnableCheckouts',
                    'isPublicallyListed'    => 'IsPublicallyListed',
                    'checkoutPersonListKind'=> 'CheckoutPersonListKind',
                    'checkoutBooksRole'     => 'CheckoutBooksRole',
                    'viewRole'              => 'ViewRole',
                    'isActive'              => 'IsActive',
                    'adminNotes'            => 'AdminNotes',
                    'adminNotesUpdatedOn'   => 'AdminNotesUpdatedOn',
                    'adminNotesUpdatedBy'   => 'AdminNotesUpdatedBy',
                    'updatedOn'             => 'UpdatedOn',
                    'updatedBy'             => 'UpdatedBy',
                    'createdOn'             => 'CreatedOn',
                    'createdBy'             => 'CreatedBy',
                ],
            ],
            'library-import' => [
                'name'                                      => 'library-import',
                'table_name'                                => 'lib_imports',
                'table_key'                                 => 'ImportId',
                'entity_key_field'                          => 'importId',
                'sion_model_class'                          => LibraryTable::class,
                'sion_controllers'                          => [LibraryImportsController::class],
                'controller_services'                       => [
                    PublicationsTable::class,
                ],
                'get_object_function'                       => 'getLibraryImport',
                'get_objects_function'                      => 'getLibraryImports',
//                 'format_view_helper'                        => 'formatEvent',
                'required_columns_for_creation'             => [
                    'name',
                    'libraryId',
                    'filePath',
                ],
                'name_field'                                => 'name',
                'name_field_is_translateable'               => false,
//                 'country_field'                             => 'country',
//                 'text_columns'                              => [],
//                 'many_to_one_update_columns'                => [
//                     'email'    => 'contactInfo',
//                     'cell'    => 'contactInfo',
//                 ],
                'report_changes'                            => true,
                'index_route'                               => 'library-imports/library',
//                 'index_template'                            => 'project/events/index',
                'default_route_key'                         => 'import_id',
//                 'show_action_template'                      => 'project/events/show',
                'show_route'                                => 'library-imports/library-import',
                'show_route_key'                            => 'import_id',
                'show_route_key_field'                      => 'importId',
                'edit_action_form'                          => ImportForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
                'edit_route'                                => 'library-imports/library-import/edit',
                'edit_route_key'                            => 'import_id',
                'edit_route_key_field'                      => 'importId',
                'create_action_form'                        => ImportForm::class,
//                 'create_action_valid_data_handler'          => 'createEvent',
                'create_action_redirect_route'              => 'library-imports/library-import/edit',
                'create_action_redirect_route_key'          => 'import_id',
                'create_action_redirect_route_key_field'    => 'importId',
//                 'create_action_template'                    => 'project/events/create',
//                 'touch_default_field'                       => 'eventId',
//                 'touch_route_key'                           => 'event_id',
//                 'touch_field_route_key'                     => 'event_id',
//                 'touch_json_route'                          => 'events/event/touch',
//                 'touch_json_route_key'                      => 'event_id',
                'database_bound_data_preprocessor'          => 'preprocessLibraryImport',
//                 'database_bound_data_postprocessor'         => 'postprocessEvent',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'moderate_route_entity_key'                 => 'event_id',
//                 'suggest_form'                              => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                      => true,
//                 'delete_action_acl_resource'                => 'event_:id',
//                 'delete_action_acl_permission'              => 'delete_event',
//                 'delete_action_redirect_route'              => 'events',
                'acl_resource_id_field'                     => 'resourceId',
                'acl_show_permission'                       => 'administrate',
                'acl_edit_permission'                       => 'administrate',
//                 'acl_suggest_permission'                    => 'suggest',
//                 'acl_moderate_permission'                   => 'administrate',
                'acl_delete_permission'                     => 'administrate',
                'update_columns'                            => [
                    'importId'                  => 'ImportId',
                    'name'                      => 'ImportName',
                    'libraryId'                 => 'LibraryId',
                    'status'                    => 'Status',
                    'description'               => 'Description',
                    'columnMappingSerialized'   => 'ColumnMapping',
                    'worksheet'                 => 'Worksheet',
                    'filePath'                  => 'FilePath',
                    'isCompleteImport'          => 'IsCompleteImport',
                    'booksUpdated'              => 'BooksUpdated',
                    'booksCreated'              => 'BooksCreated',
                    'booksInactivated'          => 'BooksDeleted',
                    'createdOn'                 => 'CreatedOn',
                    'createdBy'                 => 'CreatedBy',
                    'updatedOn'                 => 'UpdatedOn',
                    'updatedBy'                 => 'UpdatedBy',
                ],
            ],
            'book' => [
                'name'                                      => 'book',
                'table_name'                                => 'lib_books',
                'table_key'                                 => 'book_id',
                'entity_key_field'                          => 'bookId',
                'sion_model_class'                          => LibraryTable::class,
                'sion_controllers'                          => [BooksController::class],
                'controller_services'                       => [
                    'Books\BorrowersValueOptions',
                    PublicationsTable::class,
                    LibraryTable::class,
                ],
                'get_object_function'                       => 'getSimpleBook',
                'get_objects_function'                      => 'getBooks',
//                 'format_view_helper'                        => 'formatEvent',
                'required_columns_for_creation'             => [
                    'withinLibraryId',
                    'title',
                    'libraryId',
                ],
                'name_field'                                => 'title',
                'name_field_is_translateable'               => false,
//                 'country_field'                             => 'country',
//                 'text_columns'                              => [],
//                 'many_to_one_update_columns'                => [
//                     'email'    => 'contactInfo',
//                     'cell'    => 'contactInfo',
//                 ],
                'report_changes'                            => true,
                'index_route'                               => 'libraries',
//                 'index_template'                            => 'project/events/index',
                'default_route_key'                         => 'book_id',
//                 'show_action_template'                      => 'project/events/show',
                'show_route'                                => 'books/book',
                'show_route_key'                            => 'book_id',
                'show_route_key_field'                      => 'bookId',
                'edit_action_form'                          => BookForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
                'edit_route'                                => 'books/book/edit',
                'edit_route_key'                            => 'book_id',
                'edit_route_key_field'                      => 'bookId',
                'create_action_form'                        => BookForm::class,
//                 'create_action_valid_data_handler'          => 'createEvent',
                'create_action_redirect_route'              => 'books/book',
                'create_action_redirect_route_key'          => 'book_id',
                'create_action_redirect_route_key_field'    => 'bookId',
//                 'create_action_template'                    => 'project/events/create',
//                 'touch_default_field'                       => 'eventId',
//                 'touch_route_key'                           => 'event_id',
//                 'touch_field_route_key'                     => 'event_id',
//                 'touch_json_route'                          => 'events/event/touch',
//                 'touch_json_route_key'                      => 'event_id',
                'database_bound_data_preprocessor'          => 'preprocessBook',
//                 'database_bound_data_postprocessor'         => 'postprocessEvent',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'moderate_route_entity_key'                 => 'event_id',
//                 'suggest_form'                              => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                      => false,
//                 'delete_action_acl_resource'                => 'event_:id',
//                 'delete_action_acl_permission'              => 'delete_event',
//                 'delete_action_redirect_route'              => 'events',
                'acl_resource_id_field'                     => 'resourceId',
                'acl_show_permission'                       => 'show',
                'acl_edit_permission'                       => 'administrate',
//                 'acl_suggest_permission'                    => 'suggest',
                'acl_moderate_permission'                   => 'administrate',
//                 'acl_delete_permission'                     => 'administrate',
                'update_columns' => [
                    'bookId'                    => 'book_id',
                    'collectionId'              => 'collection_id',
                    'authorText'                => 'author',
                    'title'                     => 'title',
                    'bookEdition'               => 'edition',
                    'callNumber'                => 'call_number',
                    'newCallNumber'             => 'new_call_number',
                    'category'                  => 'category',
                    'numberOfPages'             => 'pages',
                    'inLanguage'                => 'lang',
                    'withinLibraryId'           => 'original_id',
                    'libraryId'                 => 'library_id',
                    'publicationId'             => 'publication_id',
                    'isActive'                  => 'is_active',

                    'copyrightYear'             => 'copyright_year',
                    'publisher'                 => 'publisher',
                    'publishingPlace'           => 'publisher_place',
                    'isbn'                      => 'isbn',
                    'keywords'                  => 'public_tags',
                    'publicNotes'               => 'public_notes',
                    'publicNotesUpdatedOn'      => 'public_notes_updated_at',
                    'publicNotesUpdatedBy'      => 'public_notes_updated_by',
                    'adminTags'                 => 'admin_tags',
                    'adminNotes'                => 'admin_notes', //store source info here
                    'adminNotesUpdatedOn'       => 'admin_notes_updated_at',
                    'adminNotesUpdatedBy'       => 'admin_notes_updated_by',

                    'updatedOn'     => 'updated_at',
                    'updatedBy'     => 'updated_by',
                    'createdOn'     => 'created_at',
                    'createdBy'     => 'created_by',
                ],
            ],
            /**
             * For more information on entity config:
             * @see \SionModel\Entity\Entity
             */
            'checkout' => [
                'name'									=> 'checkout',
                'table_name' 							=> 'lib_checkouts',
                'table_key' 							=> 'CheckoutId',
                'entity_key_field'               		=> 'checkoutId',
                'sion_model_class'               		=> LibraryTable::class,
                'sion_controllers'                      => [CheckoutsController::class],
                'controller_services'                   => [
                    PatresGateway::class,
                    SchoenstattTable::class,
                    'Books\FathersObjects',
                    'Schoenstatt\FathersValueOptions',
                ],
                'get_object_function' 					=> 'getCheckout',
                'get_objects_function'               	=> 'getCheckouts',
                'required_columns_for_creation' 		=> [
                    'personId',
                    'bookId',
                    'checkedOutOn',
                    'checkedOutBy',
                ],
                'name_field'               				=> 'dueOn',
                'name_field_is_translateable'           => false,
//                 'country_field'               			=> 'country',
//                 'text_columns'               			=> [],
//                 'many_to_one_update_columns'     		=> [
    //                     'email'	=> 'contactInfo',
    //                     'cell'	=> 'contactInfo',
    //                 ],
                'report_changes'               			=> false,
//                 'index_route'               			=> 'events',
//                 'index_template'               			=> 'project/events/index',
//                 'show_action_template'               	=> 'books/checkouts/show',
                'show_route' 							=> 'checkouts/checkout',
                'show_route_key' 						=> 'checkout_id',
                'show_route_key_field' 					=> 'checkoutId',
//                 'edit_action_form'               		=> 'Books\Form\EditCheckoutForm',
//                 'edit_action_template'               	=> 'project/events/edit',
//                 'edit_route'               				=> 'events/event/edit',
//                 'edit_route_key'               			=> 'event_id',
//                 'edit_route_key_field'           		=> 'eventId',
                'create_action_form'              		=> 'Books\Form\CreateCheckoutForm',
                'create_action_valid_data_handler'		=> 'createCheckouts',
                'create_action_redirect_route'         	=> 'borrowers/borrower',
                'create_action_redirect_route_key'    	=> 'person_id',
                'create_action_redirect_route_key_field'=> 'personId',
                'create_action_template'           		=> 'books/checkouts/multiple-checkouts',
//                 'touch_default_field'               	=> 'eventId',
//                 'touch_field_route_key'           		=> 'event_id',
//                 'touch_json_route'               		=> 'events/event/touch',
//                 'touch_json_route_key'            		=> 'event_id',
                'database_bound_data_preprocessor' 		=> 'preprocessCheckout',
//                 'database_bound_data_postprocessor' 	=> 'postprocessEvent',
//                 'moderate_route' 						=> 'events/event/moderate',
//                 'moderate_route_entity_key' 			=> 'event_id',
//                 'suggest_form'               			=> 'Project\Form\SuggestEventForm',
                'enable_delete_action' 					=> true,
                'delete_action_acl_resource' 			=> 'checkout_:id',
                'delete_action_acl_permission' 			=> 'delete_checkout',
                'delete_action_redirect_route' 			=> 'checkouts',
                'update_columns' 						=> [
                    'checkoutId'            => 'CheckoutId',
                    'personId'              => 'PersonId',
                    'bookId'                => 'BookId',
                    'checkedOutOn'          => 'CheckedOutOn',
                    'checkedOutBy'          => 'CheckedOutBy',
                    'checkedOutIp'          => 'CheckedOutIp',
                    'checkedOutUserAgent'   => 'CheckedOutUserAgent',
                    'dueOn'                 => 'DueOn',
                    'timesRenewed'          => 'TimesRenewed',
                    'lastRenewedOn'         => 'LastRenewedOn',
                    'checkedInOn'           => 'CheckedInOn',
                    'checkedInBy'           => 'CheckedInBy',
                    'checkedInIp'           => 'CheckedInIp',
                    'checkedInUserAgent'    => 'CheckedInUserAgent',
                    'AdminNotes'            => 'AdminNotes',
                    'AdminNotesUpdatedOn'   => 'AdminNotesUpdatedOn',
                    'AdminNotesUpdatedBy'   => 'AdminNotesUpdatedBy',
                    'updatedOn'             => 'UpdatedOn',
                    'updatedBy'             => 'UpdatedBy',
                ],
            ],
            'publication' => [
                'name'									=> 'publication',
                'table_name' 							=> 'sch_publications',
                'table_key' 							=> 'PublicationId',
                'entity_key_field'               		=> 'publicationId',
                'sion_model_class'               		=> PublicationsTable::class,
                'sion_controllers'                      => [PublicationsController::class],
                'controller_services'                   => [
                    FilesTable::class,
                    PublicationsSearchForm::class,
                    'Books\LanguagesValueOptions',
                    DriveGateway::class,
                    LibraryTable::class,
                    PredicatesTable::class,
                ],
                'get_object_function' 					=> 'getPublication',
                'get_objects_function'               	=> 'getPublications',
                'format_view_helper'                    => 'formatPublication',
                'required_columns_for_creation' 		=> [
                    'title'
                ],
                'name_field'               				=> 'title',
                'name_field_is_translateable'           => false,
                'country_field'               			=> 'country',
                'text_columns'               			=> [],
//                 'many_to_one_update_columns'     		=> [
//                     'email'	=> 'contactInfo',
//                     'cell'	=> 'contactInfo',
//                 ],
                'report_changes'               			=> true,

                'acl_resource_id_field'                 => 'resourceId',
                'acl_show_permission'                   => 'show',
                'acl_edit_permission'                   => 'edit',
                'acl_suggest_permission'                => 'suggest',
                'acl_moderate_permission'               => 'moderate',
                'acl_delete_permission'                 => 'delete',

                'index_route'               			=> 'publications',
//                 'index_template'               			=> 'project/events/index',
                'default_route_key'                     => 'publication_id',
//                 'show_action_template'               	=> 'project/events/show',
                'show_route' 							=> 'publications/publication',
                'show_route_key' 						=> 'publication_id',
                'show_route_key_field' 					=> 'publicationId',
                'edit_action_form'               		=> PublicationForm::class,
//                 'edit_action_template'               	=> 'project/events/edit',
                'edit_route'               				=> 'publications/publication/edit',
                'edit_route_key'               			=> 'publication_id',
                'edit_route_key_field'           		=> 'publicationId',
                'create_action_form'              		=> PublicationForm::class,
//                 'create_action_valid_data_handler'		=> 'createEvent',
                'create_action_redirect_route'         	=> 'publications/publication',
                'create_action_redirect_route_key'    	=> 'publication_id',
                'create_action_redirect_route_key_field'=> 'publicationId',
//                 'create_action_template'           		=> 'project/events/create',
//                 'touch_default_field'               	=> 'publicationId',
//                 'touch_field_route_key'           		=> 'publication_id',
//                 'touch_json_route'               		=> 'publications/publication/touch',
//                 'touch_json_route_key'            		=> 'publication_id',
                'database_bound_data_preprocessor' 		=> 'preprocessPublication',
//                 'database_bound_data_postprocessor' 	=> 'postprocessEvent',
//                 'moderate_route' 						=> 'events/event/moderate',
//                 'moderate_route_entity_key' 			=> 'event_id',
//                 'suggest_form'               			=> 'Project\Form\SuggestEventForm',
                'enable_delete_action' 					=> true,
//                 'delete_action_acl_resource' 			=> 'event_:id',
//                 'delete_action_acl_permission' 			=> 'delete_event',
                'delete_action_redirect_route' 			=> 'publications',
                'update_columns' => [
                    'publicationId'             => 'PublicationId',
                    'title'                     => 'Title',
                    'resourceId'                => 'ResourceId',
                    'authorsText'               => 'Authors',
                    'bookEdition'               => 'BookEdition',
                    'inLanguage'                => 'InLanguage',
                    'categoryId'                => 'CategoryId',
                    'description'               => 'Description',
                    'isbn'                      => 'Isbn',

                    'authorPerson1Id'           => 'AuthorPerson1',
                    'authorPerson2Id'           => 'AuthorPerson2',
                    'authorPerson3Id'           => 'AuthorPerson3',
                    'authorPerson4Id'           => 'AuthorPerson4',
                    'authorPerson5Id'           => 'AuthorPerson5',
                    'authorAssociation1Id'      => 'AuthorAssociationId1',
                    'authorAssociation2Id'      => 'AuthorAssociationId2',
                    'authorAssociation3Id'      => 'AuthorAssociationId3',
                    'editorPerson1Id'           => 'EditorId',
                    'editorPerson2Id'           => 'Editor2Id',
                    'editorPerson3Id'           => 'Editor3Id',
                    'editorAssociation1Id'      => 'EditorAssociationId1',
                    'editorsText'                => 'Editor',
                    'translatorPerson1Id'       => 'TranslatorId',
                    'translatorPerson2Id'       => 'Translator2Id',
                    'translatorPerson3Id'       => 'Translator3Id',
                    'translatorsText'            => 'Translator',
                    'illustratorPerson1Id'      => 'IllustratorId',
                    'illustratorsText'           => 'Illustrator',

                    'numberOfPages'             => 'NumberOfPages',
                    'copyrightYear'             => 'CopyrightYear',
                    'publisher'                 => 'Publisher',
                    'publisherAssociationId'    => 'PublisherAssociationId',
                    'publishingPlace'           => 'PublishingPlace',
                    'datePublished'             => 'DatePublished',
                    'publishingStatus'          => 'PublishingStatus',
                    'bookFormatType'            => 'BookFormatType',
                    'genre'                     => 'Genre',
                    'url1'                      => 'Url1',
                    'url1Label'                 => 'Url1Label',
                    'url2'                      => 'Url2',
                    'url2Label'                 => 'Url2Label',
                    'url3'                      => 'Url3',
                    'url3Label'                 => 'Url3Label',
                    'mainPublicationId'         => 'MainPublicationId', //mainEntity
                    'translatedFromPublicationId'=> 'TranslatedFromPublicationId',
                    'volumeNumber'              => 'VolumeNumber',
                    'containedIn'               => 'ContainedIn',
                    'containedInIsbn'           => 'ContainedInIsbn',
                    'keywords'                  => 'PublicTags',

                    'isAccessibleForFree'       => 'IsAccessableForFree',
                    'isScientificWork'          => 'IsScientificWork',
                    'isAwaitingMerge'           => 'IsAwaitingMerge',

                    'hasNoExplictEditionNumber' => 'HasNoExplictEditionNumber',
                    'hasNoISBN'                 => 'HasNoISBN',
                    'isRevisedWithBookInHand'   => 'IsRevisedWithBookInHand',
                    'publishDataAsJsonLd'       => 'PublishDataAsJsonLd',
                    'isFormallyPublished'       => 'IsFormallyPublished',

                    'hasBeenMerged'             => 'HasBeenMerged',
                    'editionNotes'              => 'EditionNotes',
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
                    'jkQuality'                 => 'JkQuality',
                    'jkQualityNotes'            => 'JkQualityNotes',
                    'jkPeriod'                  => 'JkPeriod',
                    'jkEventId'                 => 'JkEventId',
                ],
            ],
            'collection' => [
                'name'                                      => 'collection',
                'table_name'                                => 'lib_collections',
                'table_key'                                 => 'CollectionId',
                'entity_key_field'                          => 'collectionId',
                'sion_model_class'                          => LibraryTable::class,
                'sion_controllers'                          => [CollectionsController::class],
                'controller_services'                       => [],
                'get_object_function'                       => 'getSimpleCollection',
                'get_objects_function'                      => 'getUnlinkedCollections',
//                 'format_view_helper'                        => 'formatEvent',
                'required_columns_for_creation'             => [
                    'libraryId',
                    'name'
                ],
                'name_field'                                => 'name',
                'name_field_is_translateable'               => true,
//                 'country_field'                             => 'country',
//                 'text_columns'                              => [],
//                 'many_to_one_update_columns'                => [
//                     'email'    => 'contactInfo',
//                     'cell'    => 'contactInfo',
//                 ],
                'report_changes'                            => true,
                'index_route'                               => 'libraries',
//                 'index_template'                            => 'project/events/index',
                'default_route_key'                         => 'collection_id',
//                 'show_action_template'                      => 'project/events/show',
                'show_route'                                => 'libraries/library',
                'show_route_key'                            => 'library_id',
                'show_route_key_field'                      => 'libraryId',
                'edit_action_form'                          => CollectionForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
                'edit_route'                                => 'collections/collection/edit',
                'edit_route_key'                            => 'collection_id',
                'edit_route_key_field'                      => 'collectionId',
                'create_action_form'                        => CollectionForm::class,
//                 'create_action_valid_data_handler'          => 'createEvent',s
                'create_action_redirect_route'              => 'libraries/library',
                'create_action_redirect_route_key'          => 'library_id',
                'create_action_redirect_route_key_field'    => 'libraryId',
//                 'create_action_template'                    => 'project/events/create',
//                 'touch_default_field'                       => 'eventId',
//                 'touch_route_key'                           => 'event_id',
//                 'touch_field_route_key'                     => 'event_id',
//                 'touch_json_route'                          => 'events/event/touch',
//                 'touch_json_route_key'                      => 'event_id',
//                 'database_bound_data_preprocessor'          => 'preprocessEvent',
//                 'database_bound_data_postprocessor'         => 'postprocessEvent',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'moderate_route_entity_key'                 => 'event_id',
//                 'suggest_form'                              => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                      => true,
//                 'delete_action_acl_resource'                => 'event_:id',
//                 'delete_action_acl_permission'              => 'delete_event',
//                 'delete_action_redirect_route'              => 'events',
                'acl_resource_id_field'                     => 'resourceId',
                'acl_show_permission'                       => 'show',
                'acl_edit_permission'                       => 'administrate',
//                 'acl_suggest_permission'                    => 'suggest',
                'acl_moderate_permission'                   => 'administrate',
                'acl_delete_permission'                     => 'administrate',
                'update_columns'                            => [
                    'collectionId'              => 'CollectionId',
                    'libraryId'                 => 'LibraryId',
                    'name'                      => 'CollectionName',
                    'description'               => 'Description',
                    'callNumberRegex'           => 'CallNumberRegex',
                    'callNumberHelpText'        => 'CallNumberHelpText',
                    'callNumberExplanation'     => 'CallNumberExplanation',
                    'mainShowDisplay'           => 'MainShowDisplay',
                    'labelLine1'                => 'LabelLine1',
                    'labelLine2'                => 'LabelLine2',
                    'labelLine3'                => 'LabelLine3',
                    'defaultCheckoutTimePeriodInDays' => 'DefaultCheckoutTimePeriodInDays',
                    'enforceCallNumberRegex'    => 'EnforceCallNumberRegex',
                    'requireCallNumbers'        => 'RequireCallNumbers',
                    'isActive'                  => 'IsActive',
                    'adminNotes'                => 'AdminNotes',
                    'adminNotesUpdatedOn'       => 'AdminNotesUpdatedOn',
                    'adminNotesUpdatedBy'       => 'AdminNotesUpdatedBy',
                    'updatedOn'                 => 'UpdatedOn',
                    'updatedBy'                 => 'UpdatedBy',
                    'createdOn'                 => 'CreatedOn',
                    'createdBy'                 => 'CreatedBy',
                ],
            ],
            'borrower' => [
                'name'                                      => 'borrower',
                'table_name'                                => 'sch_persons',
                'table_key'                                 => 'PersonId',
                'entity_key_field'                          => 'personId',
                'sion_model_class'                          => SchoenstattTable::class,
                'sion_controllers'                          => [],//BorrowersController::class],
                'controller_services'                       => [
                    
                ],
                'get_object_function'                       => 'getPerson',
                'get_objects_function'                      => 'getPersons',
//                 'format_view_helper'                        => 'formatEvent',
                'required_columns_for_creation'             => [
                    'title'
                ],
                'name_field'                                => 'fullFriendlyName',
                'name_field_is_translateable'               => false,
                'country_field'                             => 'country',
//                 'text_columns'                              => [],
//                 'many_to_one_update_columns'                => [
//                     'email'    => 'contactInfo',
//                     'cell'    => 'contactInfo',
//                 ],
                'report_changes'                            => false,
//                 'index_route'                               => 'events',
//                 'index_template'                            => 'project/events/index',
                'default_route_key'                         => 'person_id',
//                 'show_action_template'                      => 'project/events/show',
                'show_route'                                => 'borrowers/borrower',
                'show_route_key'                            => 'person_id',
                'show_route_key_field'                      => 'personId',
//                 'edit_action_form'                          => 'Project\Form\EditEventForm',
//                 'edit_action_template'                      => 'project/events/edit',
//                 'edit_route'                                => 'events/event/edit',
//                 'edit_route_key'                            => 'event_id',
//                 'edit_route_key_field'                      => 'eventId',
//                 'create_action_form'                        => 'Project\Form\CreateEventForm',
//                 'create_action_valid_data_handler'          => 'createEvent',
//                 'create_action_redirect_route'              => 'events/event',
//                 'create_action_redirect_route_key'          => 'event_id',
//                 'create_action_redirect_route_key_field'    => 'eventId',
//                 'create_action_template'                    => 'project/events/create',
//                 'touch_default_field'                       => 'eventId',
//                 'touch_route_key'                           => 'event_id',
//                 'touch_field_route_key'                     => 'event_id',
//                 'touch_json_route'                          => 'events/event/touch',
//                 'touch_json_route_key'                      => 'event_id',
//                 'database_bound_data_preprocessor'          => 'preprocessEvent',
//                 'database_bound_data_postprocessor'         => 'postprocessEvent',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'moderate_route_entity_key'                 => 'event_id',
//                 'suggest_form'                              => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                      => false,
                'delete_action_acl_resource'                => 'event_:id',
                'delete_action_acl_permission'              => 'delete_event',
                'delete_action_redirect_route'              => 'events',
                'update_columns'                            => [
                ],
            ],
        ],
    ],

    'bjyauthorize' => [
        // resource providers provide a list of resources that will be tracked
        // in the ACL. like roles, they can be hierarchical
        'resource_providers' => [
           \BjyAuthorize\Provider\Resource\Config::class => [
               'book',
               'book_teo',
               'book_sch',
               'publication_patres',
               'publication_institute',
               'publication_user',
               'publication_public',
               'view_checkout_person', //see who has a library book
           ],
           LibraryTable::class => LibraryTable::class
         ],

        /* rules can be specified here with the format:
         * array(roles (array), resource, array(privilege (array|string), assertion))
        * assertions will be loaded using the service manager and must implement
        * Zend\Acl\Assertion\AssertionInterface.
        * *if you use assertions, define them using the service manager!*
        */
        'rule_providers' => [
            Config::class => [
                'allow' => [
                    [['pub_patres', 'pub_general_moderator'], 'publication_patres', 'show'],
                    [['pub_patres_moderator', 'pub_general_moderator'], 'publication_patres', 'show-admin-info'],
                    [['pub_patres_moderator', 'pub_general_moderator'], 'publication_patres', 'edit'],
                    [['pub_patres_moderator', 'pub_general_moderator'], 'publication_patres', 'delete'],


                    [['pub_institute', 'pub_general_moderator'], 'publication_institute', 'show'],
                    [['pub_institute_moderator', 'pub_general_moderator'], 'publication_institute', 'show-admin-info'],
                    [['pub_institute_moderator', 'pub_general_moderator'], 'publication_institute', 'edit'],
                    [['pub_institute_moderator', 'pub_general_moderator'], 'publication_institute', 'delete'],

                    [['user'], 'publication_user', 'show'],
                    [['pub_moderator', 'pub_general_moderator'], 'publication_user', 'show-admin-info'],
                    [['pub_moderator', 'pub_general_moderator'], 'publication_user', 'edit'],
                    [['pub_moderator', 'pub_general_moderator'], 'publication_user', 'delete'],

                    [['guest', 'user'], 'publication_public', 'show'],
                    [['pub_moderator', 'pub_general_moderator'], 'publication_public', 'show-admin-info'],
                    [['pub_moderator', 'pub_general_moderator'], 'publication_public', 'edit'],
                    [['pub_moderator', 'pub_general_moderator'], 'publication_public', 'delete'],

                    //library permissions
                    [['lib_patres'], 'view_checkout_person'],
                ],
            ],
            LibraryTable::class => LibraryTable::class
        ],
        'guards' => [
            Route::class => [
                ['route' => 'publications', 'roles' => ['guest', 'user']],
                ['route' => 'publications/prime-authors', 'roles' => ['pub_administrator']],
                ['route' => 'publications/trim-titles', 'roles' => ['pub_administrator']],

                ['route' => 'publications/search', 'roles' => ['guest', 'user']],
                ['route' => 'publications/import', 'roles' => ['pub_administrator']],
                ['route' => 'publications/create', 'roles' => ['pub_moderator']],
                ['route' => 'publications/index', 'roles' => ['guest', 'user']],
                ['route' => 'publications/export', 'roles' => ['pub_moderator']],
                ['route' => 'publications/publication', 'roles' => ['guest', 'user']],
                ['route' => 'publications/publication/upload-cover', 'roles' => ['pub_moderator']],
                ['route' => 'publications/publication/create-new-edition', 'roles' => ['pub_moderator']],
                ['route' => 'publications/publication/edit', 'roles' => ['pub_moderator']],
                ['route' => 'publications/publication/delete', 'roles' => ['pub_moderator']],
                ['route' => 'home', 'roles' => ['guest', 'lib_user']],
                ['route' => 'libraries', 'roles' => ['guest', 'lib_user']],

                //@todo define library-specific ACL

                ['route' => 'books/book', 'roles' => ['guest', 'lib_user']],
                ['route' => 'books/book/edit', 'roles' => ['lib_user']],
                ['route' => 'books/create', 'roles' => ['lib_user']],

                ['route' => 'libraries/library', 'roles' => ['guest', 'lib_user']],
                ['route' => 'libraries/library/edit', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/create', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/book-list', 'roles' => ['guest', 'lib_user']],
                ['route' => 'libraries/library/checkout', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/checkin', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/mass-checkout', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/batch-operations', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/label-management', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/send-book-notices', 'roles' => ['user', 'guest']], //controller action has additional protection
                ['route' => 'libraries/library/inactivate-books', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/admin', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/book-list-json', 'roles' => ['lib_user']],
                ['route' => 'libraries/import', 'roles' => ['lib_user']],
                ['route' => 'libraries/checkouts', 'roles' => ['lib_user']],
                ['route' => 'libraries/checkouts/library', 'roles' => ['lib_user']],
                ['route' => 'borrowers', 'roles' => ['lib_user']],
                ['route' => 'borrowers/borrower', 'roles' => ['lib_user']],
                ['route' => 'borrowers/fix-person-id', 'roles' => ['lib_administrator']],

                ['route' => 'collections/collection/edit', 'roles' => ['lib_user']],
                ['route' => 'collections/create', 'roles' => ['lib_user']],

                ['route' => 'checkouts/library', 'roles' => ['lib_user']],
                ['route' => 'checkouts/library/overdue', 'roles' => ['lib_user']],
                ['route' => 'checkouts/library/current', 'roles' => ['lib_user']],

                ['route' => 'library-imports/library', 'roles' => ['lib_user']],
                ['route' => 'library-imports/library-import', 'roles' => ['lib_user']],
                ['route' => 'library-imports/library/create', 'roles' => ['lib_user']],
                ['route' => 'library-imports/library-import/cancel', 'roles' => ['lib_user']],
                ['route' => 'library-imports/library-import/edit', 'roles' => ['lib_user']],
            ],
        ],
    ],
    'view_manager' => [
        'template_map' => include __DIR__ . '/template_map.config.php',
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
];
