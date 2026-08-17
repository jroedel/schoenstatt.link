<?php
namespace Books;

use Laminas\Db\Adapter\Adapter;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Books\Mailing\BooksMailer;
use Schoenstatt\Model\SchoenstattTable;
use BjyAuthorize\Guard\Route;
use BjyAuthorize\Provider\Rule\Config;
use Schoenstatt\Service\PatresGateway;
use JTranslate\Model\TranslationsTable;
use SionModel\Service\ProblemService;
use SionModel\Db\Model\FilesTable;
use Books\Service\DriveGateway;
use SionModel\Db\Model\PredicatesTable;
use SionModel\Problem\EntityProblem;
use Laminas\Router\Http\Method;
use Books\Model\DictionaryTable;
use Laminas\Navigation\Navigation;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;

$textColumns = [
    'textId' => 'TextId',
    'title' => 'Title',
    'kind' => 'TextKind',
    'inLanguage' => 'Language',
    'slug' => 'Slug',
    'isDraft' => 'IsDraft',
    'markdownText' => 'MarkdownText',
    'htmlText' => 'HtmlText',
    'plainText' => 'PlainText',
    'wordCount' => 'WordCount',
    'jkTextQuality' => 'JkTextQuality',
    'tags' => 'Tags',
    'adminTags' => 'AdminTags',
    'aclResourceId' => 'AclResourceId',
    'publicNotes' => 'PublicNotes',
    'publicNotesUpdatedBy' => 'PublicNotesUpdatedBy',
    'publicNotesUpdatedOn' => 'PublicNotesUpdatedOn',
    'adminNotes' => 'AdminNotes',
    'adminNotesUpdatedBy' => 'AdminNotesUpdatedBy',
    'adminNotesUpdatedOn' => 'AdminNotesUpdatedOn',
    'legacyEventId' => 'LegacyEventId',
    'legacyFile' => 'LegacyFile',
    'legacyPathDate' => 'LegacyPathDate',
    'legacyFileDateModified' => 'LegacyFileDateModified',
    'updatedOn' => 'UpdatedOn',
    'updatedBy' => 'UpdatedBy',
    'createdOn' => 'CreatedOn',
    'createdBy' => 'CreatedBy',
];

return [
    //Overdue notices as a console command, so the schedule needs no API key. The
    //superproject's bin/console resolves these from the service manager lazily, so
    //registering one costs nothing until it is the command being run.
    'console' => [
        'commands' => [
            'books:send-notices' => \App\Console\Command\SendBookNoticesCommand::class,
        ],
    ],
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
        'publication_resource_id_options' => [
            'publication_public'    => 'Public',
            'publication_user'      => 'Authenticated users',
            'publication_institute' => 'Institute users',
            'publication_brothers'  => 'Brothers of Mary',
            'publication_families'  => 'Institute of Families',
            'publication_ladies'    => 'Ladies of Schoenstatt',
            'publication_patres'    => 'Schoenstatt Fathers',
            'publication_sisters'   => 'Sisters of Mary',
        ],
        'publication_resource_id_default_option' => 'publication_public',
        'language_value_options' => [
            'en' => 'English',
            'es' => 'Spanish',
            'de' => 'German',
            'fr' => 'French',
            'pl' => 'Polish',
            'cz' => 'Czech',
            'fr' => 'French',
            'la' => 'Latin',
            'gr' => 'Greek',
            'it' => 'Italian',
        ],
        'url_label_value_options' => [
            'Download'  => 'Download',
            'Purchase'  => 'Purchase',
            'Borrow'    => 'Borrow',
            'Wikipedia' => 'Wikipedia',
            'Information' => 'Information',
        ],
        'admin_pages' => [
            'books/create'            => [
                'label' => "Add new book",
                'description' => 'Add a book to the database.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'checkouts/library/current' => [
                'label' => "Review checkouts",
                'description' => 'List and review current checkouts for this library.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'checkouts/library/overdue' => [
                'label' => "Review overdue books",
                'description' => 'List and review overdue checkouts for this library.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'libraries/library/checkin' => [
                'label' => "Check-in books",
                'description' => 'Check books back into the library.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'libraries/library/mass-checkout' => [
                'label' => "Mass book checkouts",
                'description' => 'Register offline checkout notices.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'libraries/library/inactivate-books' => [
                'label' => "Mass book inactivation",
                'description' => 'Inactivate books in bulk.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
//             'libraries/library/label-management'=> [
//                 'label' => "Label management",
//                 'description' => 'Print new call number labels and manage books pending a label change.',
//                 'route_parameters' => [
//                     'library_id' => ':libraryId',
//                 ],
//             ],
//             'libraries/library/batch-operations'=> [
//                 'label' => "Batch book operations",
//                 'description' => 'Perform changes to multiple books including inactivation, label printing, or changes to collection, language, or category.',
//                 'route_parameters' => [
//                     'library_id' => ':libraryId',
//                 ],
//             ],
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
            'libraries/library/sort-debugging'   => [
                'label' => "Sort text debugging",
                'description' => 'Preview the results of the sort-text strings. This improves the order books appear in. For advanced users.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
            'libraries/library/collections'   => [
                'label' => "Configure library collections",
                'description' => 'Collections within a library represent important physical separations within a library; configure them here. For advanced users.',
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
            'libraries/library/data-problems'  => [
                'label' => "Data problems",
                'description' => 'Review potential problems with the data in the database.',
                'route_parameters' => [
                    'library_id' => ':libraryId',
                ],
            ],
//             'sion-model/auto-fix-data-problems' => [
//                 'label' => "Auto-fix data problems",
//                 'description' => 'Try to automatically fix some data problems.',
//             ],
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
         'abstract_factories' => [
            \Books\Controller\LazyControllerFactory::class,
         ],
     ],
    'service_manager' => [
        'factories' => [
            'Books\Cache'                       => Service\CacheFactory::class,
            'Books\Config'                      => Service\ConfigServiceFactory::class,
            Model\PublicationsTable::class      => Service\PublicationsTableFactory::class,
            Model\LibraryTable::class           => Service\LibraryTableServiceFactory::class,
            Model\BorrowerTokenTable::class     => Service\BorrowerTokenTableFactory::class,
            \App\Console\Command\SendBookNoticesCommand::class => \App\Console\Command\SendBookNoticesCommandFactory::class,
            Model\EventTextTable::class         => Service\EventTextTableFactory::class,
            Model\DictionaryTable::class        => Service\DictionaryTableFactory::class,
            Form\SearchForm::class              => Service\SearchFormFactory::class,
            Form\LibraryForm::class             => Service\LibraryFormFactory::class,
            Form\CollectionForm::class          => Service\CollectionFormFactory::class,
            'Books\Form\CreateCheckoutForm'     => Service\CheckoutFormFactory::class,
            Form\PublicationForm::class         => Service\PublicationFormFactory::class,
            Form\PublicationsSearchForm::class  => Service\PublicationsSearchFormFactory::class,
            Form\BookForm::class                => Service\BookFormFactory::class,
            'Books\FathersObjects'              => Service\FathersObjectsFactory::class,
            'Books\BorrowersValueOptions'       => Service\BorrowersValueOptionsService::class,
            'Books\AuthorsValueOptions'         => Service\AuthorsValueOptionsService::class,
            Mailing\BooksMailer::class          => Service\BooksMailerFactory::class,
            Service\DriveGateway::class         => Service\DriveGatewayFactory::class,
            Form\TextForm::class                => Service\TextFormFactory::class,
            Form\DictionaryEntryForm::class     => Service\DictionaryEntryFormFactory::class,
            Model\MusicTable::class             => Service\MusicTableFactory::class,
            Form\CompositionForm::class         => Service\CompositionFormFactory::class,
            Service\SpreadsheetReader::class    => InvokableFactory::class,
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'formatPublication'             => Service\FormatPublicationFactory::class,
            'libraryInfo'                   => Service\LibraryInfoFactory::class
        ],
        'invokables' => [
            'coins'                         => View\Helper\Coins::class,
            'fileSize'                      => View\Helper\FileSize::class,
            'formatPublicationUrlObject'    => View\Helper\FormatPublicationUrlObject::class,
            'formatField'                   => View\Helper\FormatField::class,
            'booksJsonLd'                   => View\Helper\BooksJsonLd::class,
            'formSelectWithoutOptions'      => View\Helper\FormSelectWithoutOptions::class,
            'markdown'                      => View\Helper\Markdown::class,
        ],
    ],
    'router' => [
        'routes' => [
            // The `admin` route itself belongs to the Schoenstatt module. Books used to
            // graft `literature-maintenance` and its six children onto it — the 2020
            // data-source migration — retired 2026-08-17 once measuring showed the
            // migration had finished. See database/db8.2.sql.
            'publication' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id[/:slug]',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_PUBLICATION],
                            '/^$'
                        ),
                        'slug' => '[a-z0-9-]{1,200}',
                    ],
                    'defaults' => [
                        'controller' => Controller\PublicationsController::class,
                        'action'     => 'show',
                    ],
                ],
            ],
            'publication-edit' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id/edit',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_PUBLICATION],
                            '/^$'
                        ),
                    ],
                    'defaults' => [
                        'controller' => Controller\PublicationsController::class,
                        'action'     => 'edit',
                    ],
                ],
            ],
            'publication-create-new-edition' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id/create-new-edition',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_PUBLICATION],
                            '/^$'
                        ),
                    ],
                    'defaults' => [
                        'controller' => Controller\PublicationsController::class,
                        'action'     => 'createNewEdition',
                    ],
                ],
            ],
            'publication-copy-to-main-corpus' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id/copy-to-main-corpus',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_PUBLICATION],
                            '/^$'
                        ),
                    ],
                    'defaults' => [
                        'controller' => Controller\PublicationsController::class,
                        'action'     => 'copyToMainCorpus',
                    ],
                ],
            ],
            'publication-delete' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id/delete',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_PUBLICATION],
                            '/^$'
                        ),
                    ],
                    'defaults' => [
                        'controller' => Controller\PublicationsController::class,
                        'action'     => 'delete',
                    ],
                ],
            ],
            'publication-upload-cover' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id/upload-cover',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_PUBLICATION],
                            '/^$'
                        ),
                    ],
                    'defaults' => [
                        'controller' => Controller\PublicationsController::class,
                        'action'     => 'uploadCover',
                    ],
                ],
            ],
            'publications' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/literature',
                    'defaults' => [
                        'controller' => Controller\PublicationsController::class,
                        'action'     => 'literatureHome',
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
                    'create' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
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
                    'one-fifty-preguntas' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'     => '/150-preguntas-sobre-schoenstatt',
                            'defaults' => [
                                'action'     => 'oneFiftyPreguntas',
                            ],
                        ],
                    ],
                    'publication-old' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:publication_id',
                            'constraints' => [
                                'publication_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'action'     => 'sendToNewUrl',
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
                        'controller' => Controller\BooksController::class,
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
                        'controller' => Controller\LibrariesController::class,
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
                                        'controller' => Controller\CheckoutsController::class,
                                    ],
                                ],
                            ],
                            'checkin' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/checkin',
                                    'defaults' => [
                                        'action'     => 'checkin',
                                        'controller' => Controller\CheckoutsController::class,
                                    ],
                                ],
                            ],
                            'mass-checkout' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/mass-checkout',
                                    'defaults' => [
                                        'action'     => 'massCheckout',
                                        'controller' => Controller\CheckoutsController::class,
                                    ],
                                ],
                            ],
                            'label-management' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/label-management',
                                    'defaults' => [
                                        'action'     => 'labelManagement',
                                        'controller' => Controller\LibrariesController::class,
                                    ],
                                ],
                            ],
                            'batch-operations' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/batch-operations',
                                    'defaults' => [
                                        'action'     => 'batchOperations',
                                        'controller' => Controller\LibrariesController::class,
                                    ],
                                ],
                            ],
                            'send-book-notices' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/send-book-notices',
                                    'defaults' => [
                                        'action'     => 'sendBookNotices',
                                        'controller' => Controller\LibrariesController::class,
                                    ],
                                ],
                            ],
                            'inactivate-books' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/inactivate-books',
                                    'defaults' => [
                                        'action'     => 'inactivateBooks',
                                        'controller' => Controller\LibrariesController::class,
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
                            'data-problems' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/data-problems',
                                    'defaults' => [
                                        'action'     => 'dataProblems',
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
                            'collections' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/collections',
                                    'defaults' => [
                                        'controller' => Controller\CollectionsController::class,
                                        'action'     => 'index',
                                    ],
                                ],
                            ],
                            'sort-debugging' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/sort-debugging',
                                    'defaults' => [
                                        'action'     => 'sortDebugging',
                                    ],
                                ],
                            ],
                            'refresh-sort' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/refresh-sort',
                                    'defaults' => [
                                        'action'     => 'refreshSort',
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
                        'controller' => Controller\BorrowersController::class,
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
                ],
            ],
            'library-imports' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/library-imports',
                    'defaults' => [
                        'controller' => Controller\LibraryImportsController::class,
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
            // `may_terminate` is false on purpose: /checkouts is not a page, only the
            // prefix of /checkouts/library/:id. It carried an `index` action, plus
            // `checkout` (/:checkout_id) and `checkout/edit` children, until 2026-08-17.
            // All three were reachable by nobody — no guard entry, so default-deny — and
            // there was nothing behind the door either: they resolved to SionController's
            // generic index/show/edit, and the `checkout` entity spec has index_template,
            // show_action_template, edit_action_form and edit_action_template all
            // commented out. Nothing linked to them. A library's loans are read through
            // checkouts/library/{current,overdue} and a person's through
            // borrowers/borrower, both of which work.
            //
            // The `controller` default stays: the `library` child has none of its own.
            'checkouts' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/checkouts',
                    'defaults' => [
                        'controller' => Controller\CheckoutsController::class,
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
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
                        'controller' => Controller\CollectionsController::class,
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
            'dictionary' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/dictionary',
                    'defaults' => [
                        'controller' => Controller\DictionaryController::class,
                        //Without this the route matches and then 404s:
                        //AbstractActionController::onDispatch reads the action
                        //parameter with a default of 'not-found' and dispatches
                        //notFoundAction(). The action is SionController's
                        //generic indexAction, and books/dictionary/index.phtml
                        //has been sitting there unrendered the whole time.
                        'action' => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'entry' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:entry_id',
                            'constraints' => [
                                'entry_id' => '[0-9]{1,5}',
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
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'inLanguage' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:inLanguage',
                            'constraints' => [
                                'inLanguage' => '[a-z]{2,2}',
                            ],
                            'defaults' => [
                                'action'     => 'inLanguage',
                            ],
                        ],
                    ],
                ],
            ],
            'texts' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/texts',
                    'defaults' => [
                        'controller' => Controller\TextsController::class,
                        'action' => 'search',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'create' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'controller' => Controller\TextsController::class,
                                'action'     => 'create',
                            ],
                        ],
                    ],
                ],
            ],
            'text' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id[/:slug]',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_TEXT],
                            '/^$'
                        ),
                        'slug' => '[a-z0-9-]{1,200}',
                    ],
                    'defaults' => [
                        'controller' => Controller\TextsController::class,
                        'action'     => 'show',
                    ],
                ],
            ],
            'text-edit' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id/edit',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_TEXT],
                            '/^$'
                        ),
                    ],
                    'defaults' => [
                        'controller' => Controller\TextsController::class,
                        'action'     => 'edit',
                    ],
                ],
            ],
            'text-delete' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id/delete',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_TEXT],
                            '/^$'
                        ),
                    ],
                    'defaults' => [
                        'controller' => Controller\TextsController::class,
                        'action'     => 'delete',
                    ],
                ],
            ],
            'events' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/timeline',
                    'defaults' => [
                        'controller' => Controller\EventsController::class,
                        'action' => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'create' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'controller' => Controller\EventsController::class,
                                'action'     => 'create',
                            ],
                        ],
                    ],
                ],
            ],
            'event' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id[/:slug]',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_EVENT],
                            '/^$'
                        ),
                        'slug' => '[a-z0-9-]{1,200}',
                    ],
                    'defaults' => [
                        'controller' => Controller\EventsController::class,
                        'action'     => 'show',
                    ],
                ],
            ],
            'event-edit' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id/edit',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_EVENT],
                            '/^$'
                        ),
                    ],
                    'defaults' => [
                        'controller' => Controller\EventsController::class,
                        'action'     => 'edit',
                    ],
                ],
            ],
            'event-delete' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id/delete',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_EVENT],
                            '/^$'
                        ),
                    ],
                    'defaults' => [
                        'controller' => Controller\EventsController::class,
                        'action'     => 'delete',
                    ],
                ],
            ],
            'composition' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id[/:slug]',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_COMPOSITION],
                            '/^$'
                        ),
                        'slug' => '[a-z0-9-]{1,200}',
                    ],
                    'defaults' => [
                        'controller' => Controller\CompositionsController::class,
                        'action'     => 'show',
                    ],
                ],
            ],
            'composition-edit' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id/edit',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_COMPOSITION],
                            '/^$'
                        ),
                    ],
                    'defaults' => [
                        'controller' => Controller\CompositionsController::class,
                        'action'     => 'edit',
                    ],
                ],
            ],
            'composition-delete' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/:sw_id/delete',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::ENTITY_REGEXS[SchoenstattLinkIdentifier::ENTITY_COMPOSITION],
                            '/^$'
                        ),
                    ],
                    'defaults' => [
                        'controller' => Controller\CompositionsController::class,
                        'action'     => 'delete',
                    ],
                ],
            ],
            'music' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/music',
                    'defaults' => [
                        'controller' => Controller\CompositionsController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'create-composition' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/create-composition',
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
        'problem_providers' => [
            Model\LibraryTable::class,
        ],
        'problem_specifications' => [
            'book-missing-call-number' => [
                'entity'            => 'book',
                'defaultSeverity'   => EntityProblem::SEVERITY_ERROR,
                'text'              => 'No call number for book',
            ],
            'book-invalid-call-number' => [
                'entity'            => 'book',
                'defaultSeverity'   => EntityProblem::SEVERITY_WARNING,
                'text'              => 'Invalid call number for book',
            ],
            'library-missing-call-number-format' => [
                'entity'            => 'library',
                'defaultSeverity'   => EntityProblem::SEVERITY_WARNING,
                'text'              => 'No call number format for library',
            ],
            'library-invalid-call-number-format' => [
                'entity'            => 'library',
                'defaultSeverity'   => EntityProblem::SEVERITY_ERROR,
                'text'              => 'Invalid call number format for library',
            ],
            'library-missing-sort-text-format' => [
                'entity'            => 'library',
                'defaultSeverity'   => EntityProblem::SEVERITY_ERROR,
                'text'              => 'No sort text format for library',
            ],
            'collection-missing-call-number-format' => [
                'entity'            => 'collection',
                'defaultSeverity'   => EntityProblem::SEVERITY_ERROR,
                'text'              => 'No call number format for collection',
            ],
            'collection-invalid-call-number-format' => [
                'entity'            => 'collection',
                'defaultSeverity'   => EntityProblem::SEVERITY_ERROR,
                'text'              => 'Invalid call number format for collection',
            ],
            'collection-missing-sort-text-format' => [
                'entity'            => 'collection',
                'defaultSeverity'   => EntityProblem::SEVERITY_ERROR,
                'text'              => 'No sort text format for collection',
            ],
        ],
        'entities' => [
            /**
             * For more information on entity config:
             * @see \SionModel\Entity\Entity
             */
            /**
             * The Fr. Kentenich timeline. **Only the index route is reachable** — `event`,
             * `event-edit`, `event-delete` and `events/create` have no entry in
             * acl.global.php and BjyAuthorize default-denies them. That is deliberate and
             * documented in docs/timeline-and-corpus.md; do not add guard entries without
             * reading it, because the write surface needs a form (there is none), a show
             * template (there is none), and an ACL resource that exists (see below).
             *
             * Five keys below were corrected on 2026-08-15. They had described the April
             * 2020 *draft* of this entity rather than the schema database/db6.1.sql actually
             * shipped, and because every route that would exercise them is denied, nothing
             * ever failed to reveal it. Each correction is annotated where it sits.
             */
            'event' => [
                'name'                                      => 'event',
                'table_name'                                => 'events',
                'table_key'                                 => 'EventId',
                'sion_controllers'                          => [Controller\EventsController::class],
                'controller_services'                       => [],
                'entity_key_field'                          => 'eventId',
                'sion_model_class'                          => Model\EventTextTable::class,
                'row_processor_function'                    => 'processEventRow',
//                 'get_object_function'                       => 'getEvent',
//                 'get_objects_function'                      => 'getEvents',
//                 'format_view_helper'                        => 'formatEvent',
                //CORRECTED: this named `durationInDays` and `accuracy`, neither of which is a
                //field of this entity. db6.1 shipped `Duration` + `DurationUnit` and
                //`StartDatePrecision` instead, and the last two are NOT NULL with defaults, so
                //`startDate` is the only column a create genuinely has to be given.
                'required_columns_for_creation'             => [
                    'startDate',
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
                //CORRECTED: this said `association_id`, copy-pasted from the associations
                //entity. Every route below takes a site-wide identifier on a `:sw_id` segment.
                'default_route_key'                         => 'sw_id',
//                 'show_action_template'                      => 'project/events/show',
                //CORRECTED (four keys): the routes are top-level `event` / `event-edit`, not
                //children of `events` — that route has exactly one child, `create`. And the
                //segment is `:sw_id` matching /^SL(6[0-9]{5,5})E$/, not a bare `event_id`, so
                //the key field is `identifier`. `processEventRow()` did not emit one of those
                //until 2026-08-15 either, which is why no event URL could be built at all.
                //Mirrors the `publication` entity below, which is the working example.
                'show_route'                                => 'event',
                'show_route_key'                            => 'sw_id',
                'show_route_key_field'                      => 'identifier',
//                 'edit_action_form'                          => Form\EditEventForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
                'edit_route'                                => 'event-edit',
                'edit_route_key'                            => 'sw_id',
                'edit_route_key_field'                      => 'identifier',
                //'create_action_form'                        => Form\CreateEventForm::class,
                //CORRECTED: `create_action_valid_data_handler => 'createEvent'` named a method
                //that is defined nowhere in the repository — not on EventTextTable, not on
                //SionTable, nowhere. Commented out rather than pointed at something, because
                //there is nothing to point it at until Part 2 of docs/timeline-and-corpus.md.
//                 'create_action_valid_data_handler'          => 'createEvent',
                'create_action_redirect_route'              => 'event',
                'create_action_redirect_route_key'          => 'sw_id',
                'create_action_redirect_route_key_field'    => 'identifier',
//                 'create_action_template'                    => 'project/events/create',
//                 'database_bound_data_preprocessor'          => 'preprocessEvent',
//                 'database_bound_data_postprocessor'         => 'postprocessEvent',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'moderate_route_entity_key'                 => 'event_id',
//                 'suggest_form'                              => Form\SuggestEventForm::class,
                'enable_delete_action'                      => true,
                'delete_route_key'                          => 'sw_id',
                'delete_action_redirect_route'              => 'events',

                //CORRECTED: `aclResourcesId`, with an `s`. db6.1's last statement renamed the
                //column `AclResourcesId` to `AclResourceId` and this key was not followed
                //along, so the per-row permission lookup read a field `processEventRow()` has
                //never emitted.
                //
                //**Fixing the name is not the same as the check working.** All 527 rows carry
                //`evt_public`, and that resource is registered nowhere: the config provider in
                //acl.global.php does not declare it, and `EventTextTable::getResources()` —
                //which is a registered resource provider — only reads `SELECT DISTINCT
                //AclResourceId FROM texts`, so it emits `txt_institute` and `txt_public` and
                //no event resource at all. Harmless while the four routes are denied; a
                //prerequisite the moment one is opened. Part 2 of docs/timeline-and-corpus.md.
                'acl_resource_id_field'                     => 'aclResourceId',
//                 'acl_show_permission'                       => 'show',
//                 'acl_edit_permission'                       => 'edit',
//                 'acl_suggest_permission'                    => 'suggest',
//                 'acl_moderate_permission'                   => 'moderate',
//                 'acl_delete_permission'                     => 'delete',

                'update_columns'                            => [
                    'eventId' => 'EventId',
                    'titleEn' => 'TitleEn',
                    'titleEs' => 'TitleEs',
                    'titleDe' => 'TitleDe',
                    'titlePt' => 'TitlePt',
                    'titleIt' => 'TitleIt',
                    'titleFr' => 'TitleFr',
                    'zoom' => 'Zoom',
                    'country' => 'Country',
                    'place' => 'Place',
                    'originalLanguage' => 'OriginalLanguage',
                    'slugEn' => 'SlugEn',
                    'slugEs' => 'SlugEs',
                    'slugDe' => 'SlugDe',
                    'slugPt' => 'SlugPt',
                    'slugIt' => 'SlugIt',
                    'slugFr' => 'SlugFr',
                    'wikidataSubjectId' => 'WikidataSubjectId',
                    'wikidataPropertyId' => 'WikidataPropertyId',
                    'wikidataLinkByDefault' => 'WikidataLinkByDefault',
                    'descriptionEn' => 'DescriptionEn',
                    'descriptionEs' => 'DescriptionEs',
                    'descriptionDe' => 'DescriptionDe',
                    'descriptionPt' => 'DescriptionPt',
                    'descriptionIt' => 'DescriptionIt',
                    'descriptionFr' => 'DescriptionFr',
                    'startDate' => 'StartDate',
                    'startDatePrecision' => 'StartDatePrecision',
                    'duration' => 'Duration',
                    'durationUnit' => 'DurationUnit',
                    'bestTextQuality' => 'BestTextQuality',
                    'tags' => 'Tags',
                    'adminTags' => 'AdminTags',
                    'audienceText' => 'AudienceText',
                    'abbreviationEn' => 'AbbreviationEn',
                    'abbreviationEs' => 'AbbreviationEs',
                    'abbreviationDe' => 'AbbreviationDe',
                    'abbreviationPt' => 'AbbreviationPt',
                    'abbreviationIt' => 'AbbreviationIt',
                    'abbreviationFr' => 'AbbreviationFr',
                    'aclResourceId' => 'AclResourceId',
                    'url1' => 'Url1',
                    'url1Label' => 'Url1Label',
                    'url2' => 'Url2',
                    'url2Label' => 'Url2Label',
                    'url3' => 'Url3',
                    'url3Label' => 'Url3Label',
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
                'name'                                  => 'library',
                'table_name'                            => 'lib_libraries',
                'table_key'                             => 'LibraryId',
                'entity_key_field'                      => 'libraryId',
                'sion_model_class'                      => Model\LibraryTable::class,
                'sion_controllers'                      => [Controller\LibrariesController::class],
                'controller_services'                   => [
                    Form\SearchForm::class,
                    'Books\BorrowersValueOptions',
                    SchoenstattTable::class,
                    TranslationsTable::class,
                    ProblemService::class,
                    BooksMailer::class,
                    Model\PublicationsTable::class,
                ],
                'row_processor_function'                => 'processLibraryRow',
//                 'get_object_function'                   => 'getLibrary',
//                 'get_objects_function'                  => 'getUnlinkedLibraries',
//                 'format_view_helper'                    => 'formatEvent',
                'required_columns_for_creation'         => [
                    'name',
                ],
                'name_field'                            => 'name',
                'name_field_is_translateable'           => true,
//                 'country_field'                          => 'country',
                'report_changes'                        => true,
                'index_route'                           => 'libraries',
//                 'index_template'                         => 'project/events/index',
                'default_route_key'                     => 'library_id',
//                 'show_action_template'                   => 'project/events/show',
                'show_route'                            => 'libraries/library',
                'show_route_key'                        => 'library_id',
                'show_route_key_field'                  => 'libraryId',
                'edit_action_form'                      => Form\LibraryForm::class,
//                 'edit_action_template'                   => 'libraries/library/edit',
                'edit_route'                            => 'libraries/library/edit',
                'edit_route_key'                        => 'library_id',
                'edit_route_key_field'                  => 'libraryId',
                'create_action_form'                    => Form\LibraryForm::class,
//                 'create_action_valid_data_handler'       => 'createEvent',
                'create_action_redirect_route'          => 'libraries/library',
                'create_action_redirect_route_key'      => 'library_id',
                'create_action_redirect_route_key_field' => 'libraryId',
//                 'create_action_template'                 => 'project/events/create',
//                 'database_bound_data_preprocessor'       => 'preprocessEvent',
//                 'database_bound_data_postprocessor'  => 'postprocessEvent',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'          => 'event_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
//                 'enable_delete_action'                   => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'           => 'delete_event',
//                 'delete_action_redirect_route'           => 'events',
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
                    'sortTextFormat'        => 'SortTextFormat',
                    'callNumberPlaceholder' => 'CallNumberPlaceholder',
                    'callNumberHelpText'    => 'CallNumberHelpText',
                    'callNumberExplanation' => 'CallNumberExplanation',
                    'filiationId'           => 'FiliationId',
                    'contactPersonId'       => 'ContactPerson',
                    'contactEmail'          => 'ContactEmail',
                    'mainShowDisplay'       => 'MainShowDisplay',
                    'useCollections'        => 'UseCollections',
                    'allowCollectionlessBooks' => 'AllowCollectionlessBooks',
                    'mainCollectionId'      => 'MainCollectionId',
                    'requireCallNumbers'    => 'RequireCallNumbers',
                    'callNumberRegex'       => 'CallNumberRegex',
                    'enforceCallNumberRegex' => 'EnforceCallNumberRegex',
                    'labelLine1'            => 'LabelLine1',
                    'labelLine2'            => 'LabelLine2',
                    'labelLine3'            => 'LabelLine3',
                    'barcodeText'           => 'BarcodeText',
                    'createCheckoutsIfCheckingInANonCheckedOutBook' => 'CreateCheckoutsIfCheckingInANonCheckedOutBook',
                    'defaultCheckoutPersonId' => 'DefaultCheckoutPersonId',
                    'defaultCheckoutTimePeriodInDays' => 'DefaultCheckoutTimePeriodInDays',
                    'maximumBookRenewals'   => 'MaximumBookRenewals',
                    'enableCheckouts'       => 'EnableCheckouts',
                    'checkoutPersonListKind' => 'CheckoutPersonListKind',
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
                'sion_model_class'                          => Model\LibraryTable::class,
                'sion_controllers'                          => [Controller\LibraryImportsController::class],
                'controller_services'                       => [
                    Model\PublicationsTable::class,
                    Service\SpreadsheetReader::class,
                ],
                'get_object_function'                       => 'getLibraryImport',
                'get_objects_function'                      => 'getLibraryImports',
                'row_processor_function'                    => 'processLibraryImportRow',
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
                'edit_action_form'                          => Form\ImportForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
                'edit_route'                                => 'library-imports/library-import/edit',
                'edit_route_key'                            => 'import_id',
                'edit_route_key_field'                      => 'importId',
                'create_action_form'                        => Form\ImportForm::class,
//                 'create_action_valid_data_handler'          => 'createEvent',
                'create_action_redirect_route'              => 'library-imports/library-import/edit',
                'create_action_redirect_route_key'          => 'import_id',
                'create_action_redirect_route_key_field'    => 'importId',
//                 'create_action_template'                    => 'project/events/create',
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
                'sion_model_class'                          => Model\LibraryTable::class,
                'sion_controllers'                          => [Controller\BooksController::class],
                'controller_services'                       => [
                    'Books\BorrowersValueOptions',
                    Model\PublicationsTable::class,
                    Model\LibraryTable::class,
                ],
//                 'get_object_function'                       => 'getSimpleBook',
//                 'get_objects_function'                      => 'getBooks',
                'row_processor_function'                    => 'processBookRow',
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
                'edit_action_form'                          => Form\BookForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
                'edit_route'                                => 'books/book/edit',
                'edit_route_key'                            => 'book_id',
                'edit_route_key_field'                      => 'bookId',
                'create_action_form'                        => Form\BookForm::class,
//                 'create_action_valid_data_handler'          => 'createEvent',
                'create_action_redirect_route'              => 'books/book',
                'create_action_redirect_route_key'          => 'book_id',
                'create_action_redirect_route_key_field'    => 'bookId',
//                 'create_action_template'                    => 'project/events/create',
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
                    'authorsText'               => 'author',
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
                    'sortText'                  => 'sort_text',
                    'isActive'                  => 'is_active',
                    'inactivationReason'        => 'inactivation_reason',

                    'publishedYear'             => 'copyright_year',
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
                'name'                                  => 'checkout',
                'table_name'                            => 'lib_checkouts',
                'table_key'                             => 'CheckoutId',
                'entity_key_field'                      => 'checkoutId',
                'sion_model_class'                      => Model\LibraryTable::class,
                'sion_controllers'                      => [Controller\CheckoutsController::class],
                'controller_services'                   => [
                    PatresGateway::class,
                    SchoenstattTable::class,
                    'Books\FathersObjects',
                    'Schoenstatt\FathersValueOptions',
                ],
                'get_object_function'                   => 'getCheckout',
                'get_objects_function'                  => 'getCheckouts',
                'row_processor_function'                => 'processCheckoutRow',
                'required_columns_for_creation'         => [
                    'personId',
                    'bookId',
                    'checkedOutOn',
                    'checkedOutBy',
                ],
                'name_field'                            => 'dueOn',
                'name_field_is_translateable'           => false,
//                 'country_field'                          => 'country',
//                 'text_columns'                           => [],
//                 'many_to_one_update_columns'             => [
    //                     'email'  => 'contactInfo',
    //                     'cell'   => 'contactInfo',
    //                 ],
                'report_changes'                        => false,
//                 'index_route'                        => 'events',
//                 'index_template'                         => 'project/events/index',
//                 'show_action_template'                   => 'books/checkouts/show',
//                 'show_route'                             => 'checkouts/checkout',
//                 'show_route_key'                         => 'checkout_id',
//                 'show_route_key_field'                   => 'checkoutId',
//                 'edit_action_form'                       => 'Books\Form\EditCheckoutForm',
//                 'edit_action_template'                   => 'project/events/edit',
//                 'edit_route'                             => 'events/event/edit',
//                 'edit_route_key'                         => 'event_id',
//                 'edit_route_key_field'                   => 'eventId',
                'create_action_form'                    => 'Books\Form\CreateCheckoutForm',
                'create_action_valid_data_handler'      => 'createCheckouts',
                'create_action_redirect_route'          => 'borrowers/borrower',
                'create_action_redirect_route_key'      => 'person_id',
                'create_action_redirect_route_key_field' => 'personId',
                'create_action_template'                => 'books/checkouts/multiple-checkouts',
                'database_bound_data_preprocessor'      => 'preprocessCheckout',
//                 'database_bound_data_postprocessor'  => 'postprocessEvent',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'          => 'event_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                // The three show_* keys above and the four delete_* keys below were live
                // until 2026-08-17 and none of them ever did anything. There is no
                // checkouts delete route for `enable_delete_action` to enable;
                // `checkout_:id` is a resource no provider emits (LibraryTable::
                // getResources() emits `library_<id>` only), so the permission could
                // never be granted; and both route names point at routes deleted with
                // the record views. Commented rather than removed, to match how every
                // other unbuilt key in this spec is recorded.
//                 'enable_delete_action'                   => true,
//                 'delete_action_acl_resource'             => 'checkout_:id',
//                 'delete_action_acl_permission'           => 'delete_checkout',
//                 'delete_action_redirect_route'           => 'checkouts',
                'update_columns'                        => [
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
                'name'                                  => 'publication',
                'table_name'                            => 'sch_publications',
                'table_key'                             => 'PublicationId',
                'entity_key_field'                      => 'publicationId',
                'sion_model_class'                      => Model\PublicationsTable::class,
                'sion_controllers'                      => [Controller\PublicationsController::class],
                'controller_services'                   => [
                    FilesTable::class,
                    Form\PublicationsSearchForm::class,
                    DriveGateway::class,
                    Model\LibraryTable::class,
                    PredicatesTable::class,
                    Model\DictionaryTable::class,
                ],
                'get_object_function'                   => 'getPublication',
//                 'get_objects_function'                  => 'getUnlinkedPublications',
                'row_processor_function'                => 'processPublicationRow',
                'format_view_helper'                    => 'formatPublication',
                'required_columns_for_creation'         => [
                    'title', 'resourceId'
                ],
                'name_field'                            => 'title',
                'name_field_is_translateable'           => false,
//                 'country_field'                         => 'country',
                'text_columns'                          => [],
//                 'many_to_one_update_columns'             => [
//                     'email'  => 'contactInfo',
//                     'cell'   => 'contactInfo',
//                 ],
                'report_changes'                        => true,

                'acl_resource_id_field'                 => 'resourceId',
                'acl_show_permission'                   => 'show',
                'acl_edit_permission'                   => 'edit',
                'acl_suggest_permission'                => 'suggest',
                'acl_moderate_permission'               => 'moderate',
                'acl_delete_permission'                 => 'delete',

                'index_route'                           => 'publications',
//                 'index_template'                         => 'project/events/index',
                'default_route_key'                     => 'sw_id',
//                 'show_action_template'                   => 'project/events/show',
                'show_route'                            => 'publication',
                'show_route_key'                        => 'sw_id',
                'show_route_key_field'                  => 'identifier',
                'edit_action_form'                      => Form\PublicationForm::class,
//                 'edit_action_template'                   => 'project/events/edit',
                'edit_route'                            => 'publication-edit',
                'edit_route_key'                        => 'sw_id',
                'edit_route_key_field'                  => 'identifier',
                'create_action_form'                    => Form\PublicationForm::class,
//                 'create_action_valid_data_handler'       => 'createEvent',
                'create_action_redirect_route'          => 'publication',
                'create_action_redirect_route_key'      => 'sw_id',
                'create_action_redirect_route_key_field' => 'identifier',
//                 'create_action_template'                 => 'project/events/create',
                'database_bound_data_preprocessor'      => 'preprocessPublication',
                'database_bound_data_postprocessor'     => 'postprocessPublication',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'          => 'event_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'           => 'delete_event',
                'delete_action_redirect_route'          => 'publications',
                'update_columns' => [
                    'publicationId'             => 'PublicationId',
                    'title'                     => 'Title',
                    'titleNoAccents'            => 'TitleNoAccents',
                    'slug'                      => 'Slug',
                    'subtitle'                  => 'Subtitle',
                    'subtitleNoAccents'         => 'SubtitleNoAccents',
                    'resourceId'                => 'ResourceId',
                    'authorsText'               => 'Authors',
                    'authorsNoAccents'          => 'AuthorsNoAccents',
                    'bookEdition'               => 'BookEdition',
                    'inLanguage'                => 'InLanguage',
                    'categoryId'                => 'CategoryId',
                    'description'               => 'Description',
                    'isbn'                      => 'Isbn',
                    'editorsText'               => 'Editor',
                    'editorsNoAccents'          => 'EditorNoAccents',
                    'translatorsText'           => 'Translator',

                    'numberOfPages'             => 'NumberOfPages',
                    'copyrightYear'             => 'CopyrightYear',
                    'copyrightInfo'             => 'CopyrightInfo',
                    'publisher'                 => 'Publisher',
                    'publishingPlace'           => 'PublishingPlace',
                    'datePublished'             => 'DatePublished',
                    'datePublishedText'         => 'DatePublishedText',
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
                    'translatedFromPublicationId' => 'TranslatedFromPublicationId',
                    'volumeNumber'              => 'VolumeNumber',
                    'containedIn'               => 'ContainedIn',
                    'containedInIsbn'           => 'ContainedInIsbn',
                    'keywords'                  => 'PublicTags',

                    'isAccessibleForFree'       => 'IsAccessableForFree',
                    'isScientificWork'          => 'IsScientificWork',
                    //if the row hasn't been reconciled against the main corpus, isAwaitingMerge=true

                    'hasNoExplictEditionNumber' => 'HasNoExplictEditionNumber',
                    'hasNoISBN'                 => 'HasNoISBN',
                    'isRevisedWithBookInHand'   => 'IsRevisedWithBookInHand',
                    'publishDataAsJsonLd'       => 'PublishDataAsJsonLd',
                    'isFormallyPublished'       => 'IsFormallyPublished',

                    /**
                     * Initial meaning: has the row been merged into another? This implies being hidden
                     *
                     * 2021-08-26:
                     * Now we're maintaining a complete copy of each dataSource record
                     * Any datasource record we want to integrate into our database will be copied.
                     * The newly copied record won't have any dataSource value, but the original will point to the new
                     * one.
                     *
                     * The big question is how to we prep the data for this change?
                     * All record which have hasBeenMerged=true should actually be copied to a new row and the old
                     * row should point to the newly created ID.
                     *
                     * To get an idea of what's going on, look at this query:
                     * SELECT InLanguage, `DataSource`, `IsAwaitingMerge`, COUNT(*) FROM `sch_publications`
                     * GROUP BY DataSource, InLanguage, `IsAwaitingMerge`
                     * ORDER BY `sch_publications`.`InLanguage` ASC, `sch_publications`.`IsAwaitingMerge` ASC
                     *
                     * `Here's the plan:
                     * 1. Copy all the rows that have a Non-null dataSource and isAwaitingMerge=0. At the same time
                     *      also add the mergedIntoPublicationId pointing the old record to the new one
                     * 2. DROP the two columns hasBeenMerged, isAwaitingMerge
                     * 3. Put together a map from old PublicationId to the main corpus
                     * 4. Only display the rows that have a NULL dataSource
                     * 5. Update the PublicationId's from the following sources:
                     *      - MainPublicationId (only if they have a NULL dataSource)
                     *      - TranslatedFromPublicationId
                     *      - lib_books.publication_id
                     *      - Archivos PK
                     *      - Cover image files
                     * 6. Pull everything into a spreadsheet and start linking up/copying dataSource records
                     *      to real records
                     */
                    'hasBeenMerged'             => 'HasBeenMerged', //@todo get rid of hasBeenMerged, it's just all 0's
                    'isAwaitingMerge'           => 'IsAwaitingMerge', //@todo get rid of IsAwaitingMerge
                    'dataSource'                => 'DataSource', //the name of the data source ex. forschungsbibliothek
                    'dataSourceId'              => 'DataSourceId', //the id in the orig. data source
                    'dataSourceUpdatedOn'       => 'DataSourceUpdatedOn', //the last time we imported data from source
                    'mergedIntoPublicationId'   => 'MergedIntoPublicationId', //the final home of the merged data

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

                    'jkQuality'                 => 'JkQuality',
                    'jkQualityNotes'            => 'JkQualityNotes',
                    'jkPeriodId'                => 'JkPeriod',
                    'jkEventId'                 => 'JkEventId',
                ],
            ],
            'collection' => [
                'name'                                      => 'collection',
                'table_name'                                => 'lib_collections',
                'table_key'                                 => 'CollectionId',
                'entity_key_field'                          => 'collectionId',
                'sion_model_class'                          => Model\LibraryTable::class,
                'sion_controllers'                          => [Controller\CollectionsController::class],
                'controller_services'                       => [],
//                 'get_object_function'                       => 'getSimpleCollection',
//                 'get_objects_function'                      => 'getUnlinkedCollections',
                'row_processor_function'                    => 'processCollectionRow',
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
                'edit_action_form'                          => Form\CollectionForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
                'edit_route'                                => 'collections/collection/edit',
                'edit_route_key'                            => 'collection_id',
                'edit_route_key_field'                      => 'collectionId',
                'create_action_form'                        => Form\CollectionForm::class,
//                 'create_action_valid_data_handler'          => 'createEvent',s
                'create_action_redirect_route'              => 'libraries/library',
                'create_action_redirect_route_key'          => 'library_id',
                'create_action_redirect_route_key_field'    => 'libraryId',
//                 'create_action_template'                    => 'project/events/create',
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
                    'abbreviation'              => 'Abbreviation',
                    'description'               => 'Description',
                    'callNumberRegex'           => 'CallNumberRegex',
                    'callNumberHelpText'        => 'CallNumberHelpText',
                    'callNumberExplanation'     => 'CallNumberExplanation',
                    'mainShowDisplay'           => 'MainShowDisplay',
                    'sortTextFormat'        => 'SortTextFormat',
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
            'text' => [
                'name'                                      => 'text',
                'table_name'                                => 'texts',
                'table_key'                                 => 'TextId',
                'entity_key_field'                          => 'textId',
                'sion_model_class'                          => Model\EventTextTable::class,
                'sion_controllers'                          => [
                    Controller\TextsController::class
                ],//BorrowersController::class],
                'controller_services'                       => [],
                'row_processor_function'                    => 'processTextRow',
//                 'get_object_function'                       => 'getText',
//                 'get_objects_function'                      => 'getUnlinkedTexts',
                //                 'format_view_helper'                        => 'formatEvent',
                'required_columns_for_creation'             => [
                    'title',
                    'kind',
                    'inLanguage',
                ],
                'name_field'                                => 'filenamePlusTitle',
                'name_field_is_translateable'               => false,
//                 'country_field'                             => 'country',
                'text_columns'                              => ['markdownText', 'htmlText', 'plainText'],
//                 'many_to_one_update_columns'                => [
    //                     'email'    => 'contactInfo',
    //                     'cell'    => 'contactInfo',
    //                 ],
                'report_changes'                            => true,
                //                 'index_route'                               => 'events',
//                 'index_template'                            => 'project/events/index',
//                 'default_route_key'                         => 'text_id',
                'default_route_params'                     => [
                    'sw_id' => 'identifier',
                    'slug' => 'slug',
                ],
//                 'show_action_template'                      => 'project/events/show',
                'show_route'                                => 'text',
//                 'show_route_key'                            => 'text_id',
//                 'show_route_key_field'                      => 'textId',
                'edit_action_form'                          => Form\TextForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
                'edit_route'                                => 'text-edit',
//                 'edit_route_key'                            => 'text_id',
//                 'edit_route_key_field'                      => 'textId',
                'create_action_form'                        => Form\TextForm::class,
//                 'create_action_redirect_route_key'          => 'text_id',
//                 'create_action_redirect_route_key_field'    => 'textId',
//                 'create_action_template'                    => 'project/events/create',
                'database_bound_data_preprocessor'          => 'preprocessText',
//                 'database_bound_data_postprocessor'         => 'postprocessEvent',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'moderate_route_entity_key'                 => 'event_id',
//                 'suggest_form'                              => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                      => true,
//                 'delete_action_acl_resource'                => 'event_:id',
//                 'delete_action_acl_permission'              => 'delete',
                //`texts`, not `text-delete`, which is where this pointed until 2026-08-14.
                //`text-delete` is the delete route itself — a Segment route on `/:sw_id/delete`
                //— so SionController::redirectAfterDelete() asked the router to assemble it
                //with no parameters and got `Missing parameter "sw_id"`. Every exit from
                //deleteAction() for a text therefore threw: the not-found branch, the two
                //permission branches, and the success branch. The success branch is the one
                //that mattered — the row was already deleted and the change log entry already
                //filed, so a moderator who deleted a text saw an error page and reasonably
                //concluded it had failed. `texts` is the entity's own index and what the four
                //sibling delete routes redirect to.
                'delete_action_redirect_route'              => 'texts',
                'update_columns'                            => $textColumns
            ],
            'dictionary-entry' => [
                'name'                                      => 'dictionary-entry',
                'table_name'                                => 'sch_dictionary_entries',
                'table_key'                                 => 'EntryId',
                'entity_key_field'                          => 'entryId',
                'sion_model_class'                          => Model\DictionaryTable::class,
                'sion_controllers'                          => [Controller\DictionaryController::class],//BorrowersController::class],
                'controller_services'                       => [
                    DictionaryTable::class,
                    Navigation::class,
                ],
//                 'get_object_function'                       => 'getText',
//                 'get_objects_function'                      => 'getUnlinkedTexts',
                //                 'format_view_helper'                        => 'formatEvent',
                'required_columns_for_creation'             => [
                    'key',
                    'locale',
                    'entry',
                ],
                'name_field'                                => 'key',
                'name_field_is_translateable'               => false,
//                 'country_field'                             => 'country',
//                 'text_columns'                              => ['markdownText', 'htmlText', 'plainText'],
//                 'many_to_one_update_columns'                => [
//                     'email'    => 'contactInfo',
//                     'cell'    => 'contactInfo',
//                 ],
                'report_changes'                            => true,
//                 'index_route'                               => 'events',
//                 'index_template'                            => 'project/events/index',
                'default_route_key'                         => 'entry_id',
//                     'show_action_template'                      => 'project/events/show',
                'show_route'                                => 'dictionary/entry',
                'show_route_key'                            => 'entry_id',
                'show_route_key_field'                      => 'entryId',
                 'edit_action_form'                          => Form\DictionaryEntryForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
                'edit_route'                                => 'dictionary/entry/edit',
                'edit_route_key'                            => 'entry_id',
                'edit_route_key_field'                      => 'entryId',
                 'create_action_form'                        => Form\DictionaryEntryForm::class,
                'create_action_redirect_route'              => 'dictionary/entry',
                'create_action_redirect_route_key'          => 'entry_id',
                'create_action_redirect_route_key_field'    => 'entryId',
//                 'create_action_template'                    => 'project/events/create',
                'database_bound_data_preprocessor'          => 'preprocessDictionaryEntry',
                'database_bound_data_postprocessor'         => 'postprocessDictionaryEntry',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'moderate_route_entity_key'                 => 'event_id',
//                 'suggest_form'                              => 'Project\Form\SuggestEventForm',
                'enable_delete_action'                      => true,
//                 'delete_action_acl_resource'                => 'event_:id',
//                 'delete_action_acl_permission'              => 'delete',
                'update_columns'                            => [
                    'entryId' => 'EntryId',
                    'key' => 'KeyDe',
                    'slug' => 'Slug',
                    'locale' => 'Locale',
                    'directTranslation' => 'DirectTranslation',
                    'entry' => 'Entry',
                    'links' => 'Links',
                    'isActive' => 'IsActive',
                    'updatedOn' => 'UpdatedOn',
                    'updatedBy' => 'UpdatedBy',
                    'createdOn' => 'CreatedOn',
                    'createdBy' => 'CreatedBy',
                ],
            ],
            'composition' => [
                'name'                                  => 'composition',
                'table_name'                            => 'mus_compositions',
                'table_key'                             => 'CompositionId',
                'entity_key_field'                      => 'compositionId',
                'sion_model_class'                      => Model\MusicTable::class,
                'sion_controllers'                      => [Controller\CompositionsController::class],
                'controller_services'                   => [
                ],
                'row_processor_function'                => 'processCompositionRow',
//                 'get_object_function'                   => 'getSimpleAssociationBySwId',
//                 'get_objects_function'                  => 'getAssociations',
                'name_field'                            => 'name',
                'name_field_is_translateable'           => false,
//                 'format_view_helper'                    => 'formatEntity',
                'country_field'                         => 'country',
                'report_changes'                        => true,
                'required_columns_for_creation'         => [ //required for creation
                    'name',
                    'inLanguage',
                ],
                'index_route'                           => 'music',
                //                 'index_template'                        => 'project/events/index',
                'default_route_key'                     => 'sw_id',
                'default_route_params'                     => [
                    'sw_id' => 'identifier',
                    'slug' => 'slug',
                ],
                'show_route'                            => 'composition',
//                 'show_route_params'                     => [
//                     'sw_id' => 'identifier',
//                     'slug' => 'slug',
//                 ],
//                 'show_route_key'                        => 'sw_id',
//                 'show_route_key_field'                  => 'identifier',
                'edit_action_form'                      => Form\CompositionForm::class,
//                 'edit_action_template'                   => 'project/events/edit',
                'edit_route'                            => 'composition-edit',
                'edit_route_key'                        => 'sw_id',
                'edit_route_key_field'                  => 'identifier',
                'create_action_form'                    => Form\CompositionForm::class,
//                 'create_action_valid_data_handler'      => 'createAssociation',
                'create_action_redirect_route'          => 'composition',
//                 'create_action_redirect_route_key'      => 'sw_id',
//                 'create_action_redirect_route_key_field'=> 'identifier',
//                 'create_action_template'                   => 'project/events/create',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'             => 'delete_event',
                'delete_action_redirect_route'          => 'music',
//                 'database_bound_data_preprocessor'      => 'associationPreprocessor',
//                 'database_bound_data_postprocessor'     => 'associationPostprocessor',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'             => 'event_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'many_to_one_update_columns'            => [
                ],
                'update_columns' => [
                    'compositionId' => 'CompositionId',
                    'name' => 'CompositionName',
                    'disambiguatingDescription' => 'DisambiguatingDescription',
                    'slug' => 'Slug',
                    'inLanguage' => 'InLanguage',
                    'country' => 'Country',
                    'yearPublished' => 'YearPublished',
                    'composerText' => 'ComposerText',
                    'lyricistText' => 'LyricistText',
                    'openLicenseUrl' => 'OpenLicenseUrl',
                    'copyrightInfo' => 'CopyrightInfo',
                    'copyrightContactEmail' => 'CopyrightContactEmail',
                    'tags' => 'Tags',
                    'derivedFromCompositionId' => 'DerivedFromCompositionId',
                    'chordProSpec' => 'ChordProSpec',
                    'lyrics' => 'Lyrics',
                    'lilyPondSpec' => 'LilyPondSpec',
                    'musicalKey' => 'MusicalKey',
                    'originalKey' => 'OriginalKey',
                    'alternateKey' => 'AlternateKey',
                    'alternateKeyLabel' => 'AlternateKeyLabel',
                    'url1' => 'Url1',
                    'url1Label' => 'Url1Label',
                    'url2' => 'Url2',
                    'url2Label' => 'Url2Label',
                    'url3' => 'Url3',
                    'url3Label' => 'Url3Label',
                    'updatedOn' => 'UpdatedOn',
                    'updatedBy' => 'UpdatedBy',
                    'createdOn' => 'CreatedOn',
                    'createdBy' => 'CreatedBy',
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
                'publication_brothers',
                'publication_families',
                'publication_ladies',
                'publication_patres',
                'publication_sisters',
                'publication_institute',
                'publication_user',
                'publication_public',
                'view_checkout_person', //see who has a library book
            ],
            Model\LibraryTable::class => Model\LibraryTable::class,
            Model\EventTextTable::class => Model\EventTextTable::class,
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

                    [['pub_brothers', 'pub_general_moderator'], 'publication_brothers', 'show'],
                    [['pub_brothers_moderator', 'pub_general_moderator'], 'publication_brothers', 'show-admin-info'],
                    [['pub_brothers_moderator', 'pub_general_moderator'], 'publication_brothers', 'edit'],
                    [['pub_brothers_moderator', 'pub_general_moderator'], 'publication_brothers', 'delete'],

                    [['pub_families', 'pub_general_moderator'], 'publication_families', 'show'],
                    [['pub_families_moderator', 'pub_general_moderator'], 'publication_families', 'show-admin-info'],
                    [['pub_families_moderator', 'pub_general_moderator'], 'publication_families', 'edit'],
                    [['pub_families_moderator', 'pub_general_moderator'], 'publication_families', 'delete'],

                    [['pub_ladies', 'pub_general_moderator'], 'publication_ladies', 'show'],
                    [['pub_ladies_moderator', 'pub_general_moderator'], 'publication_ladies', 'show-admin-info'],
                    [['pub_ladies_moderator', 'pub_general_moderator'], 'publication_ladies', 'edit'],
                    [['pub_ladies_moderator', 'pub_general_moderator'], 'publication_ladies', 'delete'],

                    [['pub_sisters', 'pub_general_moderator'], 'publication_sisters', 'show'],
                    [['pub_sisters_moderator', 'pub_general_moderator'], 'publication_sisters', 'show-admin-info'],
                    [['pub_sisters_moderator', 'pub_general_moderator'], 'publication_sisters', 'edit'],
                    [['pub_sisters_moderator', 'pub_general_moderator'], 'publication_sisters', 'delete'],


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
            Model\LibraryTable::class => Model\LibraryTable::class,
            //Model\EventTextTable is deliberately absent here while remaining a
            //*resource* provider: every rule it ever returned was a blog grant
            //(blog_administrator on blog_post, plus a per-author show), so with the blog
            //gone getRules() would have answered ['allow' => []] on every request.
        ],
        'guards' => [
            Route::class => [
                ['route' => 'publications', 'roles' => ['guest', 'user']],
                ['route' => 'publications/prime-authors', 'roles' => ['pub_administrator']],
                ['route' => 'publications/search', 'roles' => ['guest', 'user']],
                ['route' => 'publications/create', 'roles' => ['pub_moderator']],
                ['route' => 'publications/index', 'roles' => ['guest', 'user']],
                ['route' => 'publications/export', 'roles' => ['pub_moderator']],
                ['route' => 'publications/one-fifty-preguntas', 'roles' => ['guest', 'user']],

                ['route' => 'publications/publication-old', 'roles' => ['guest', 'user']],
                ['route' => 'publication', 'roles' => ['guest', 'user']],
                ['route' => 'publication-edit', 'roles' => ['pub_moderator']],
                ['route' => 'publication-delete', 'roles' => ['pub_moderator']],
                ['route' => 'publication-create-new-edition', 'roles' => ['pub_moderator']],

                ['route' => 'publication-copy-to-main-corpus', 'roles' => ['pub_moderator']],

                ['route' => 'text', 'roles' => ['texts_user']], //extra checks in controller
                ['route' => 'texts', 'roles' => ['texts_user']],
                ['route' => 'text-edit', 'roles' => ['texts_moderator']],
                ['route' => 'text-delete', 'roles' => ['texts_moderator']],
                ['route' => 'texts/create', 'roles' => ['texts_moderator']],


                ['route' => 'dictionary', 'roles' => ['guest', 'user']],
                ['route' => 'dictionary/inLanguage', 'roles' => ['guest', 'user']],
                ['route' => 'dictionary/entry/edit', 'roles' => ['dict_administrator']],
                ['route' => 'dictionary/create', 'roles' => ['dict_administrator']],

                ['route' => 'books/book', 'roles' => ['guest', 'lib_user']],
                ['route' => 'books/book/edit', 'roles' => ['lib_user']],
                ['route' => 'books/create', 'roles' => ['lib_user']],

                ['route' => 'libraries', 'roles' => ['lib_administrator']],
                ['route' => 'libraries/create', 'roles' => ['guest', 'lib_user']],
                ['route' => 'libraries/library', 'roles' => ['guest', 'lib_user']],
                ['route' => 'libraries/library/edit', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/book-list', 'roles' => ['guest', 'lib_user']],
                ['route' => 'libraries/library/checkout', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/checkin', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/mass-checkout', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/batch-operations', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/label-management', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/send-book-notices', 'roles' => ['user', 'guest']], //controller action has additional protection
                ['route' => 'libraries/library/inactivate-books', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/admin', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/data-problems', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/book-list-json', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/sort-debugging', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/refresh-sort', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/collections', 'roles' => ['lib_user']],
                ['route' => 'borrowers', 'roles' => ['lib_user']],
                ['route' => 'borrowers/borrower', 'roles' => ['lib_user']],

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

                ['route' => 'music', 'roles' => ['guest', 'user']],
                ['route' => 'composition', 'roles' => ['sch_user', 'sch_basic', 'guest', 'user']],
                ['route' => 'composition-edit', 'roles' => ['sch_moderator', 'sch_user']],
                ['route' => 'composition-delete', 'roles' => ['sch_general_moderator']],
                ['route' => 'music/create-composition', 'roles' => ['sch_user']],

                /*
                 * `events` is the timeline index at /timeline, and it is the ONLY event
                 * route with a guard entry. `event` (show), `event-edit`, `event-delete`
                 * and `events/create` have none, so BjyAuthorize default-denies all four.
                 *
                 * That is a decision, not an oversight, and it is recorded here because an
                 * absent guard entry and a forgotten one look identical in this file.
                 * Three things are missing before any of the four could be opened, and
                 * none of them is a guard:
                 *
                 *   - no form (EventForm was deleted in batch 12: it matched a pre-db6.1
                 *     draft with fields the schema does not have);
                 *   - no show, edit or create template;
                 *   - no ACL resource — all 527 rows carry `evt_public` and nothing
                 *     registers it, so the per-row check would test an unknown resource.
                 *
                 * There is also no events moderator role in `user_role` to name, and
                 * picking one is a product call about who curates the timeline. Adding a
                 * guard entry alone would turn a clean default-deny into a 500.
                 *
                 * The whole feature — and the two-part plan for finishing it — is in
                 * docs/timeline-and-corpus.md. test/Integration/EventFeatureStateTest pins
                 * this state so that opening one of the four is a deliberate act.
                 */
                ['route' => 'events', 'roles' => ['user', 'guest']],
            ],
        ],
    ],
    'view_manager' => [
        'template_map' => include __DIR__ . '/template_map.config.php',
        'template_path_stack' => [
            __NAMESPACE__ => __DIR__ . '/../view',
        ],
    ],
];
