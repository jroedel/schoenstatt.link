<?php

/**
 * Every name the application container answers to, and what it answers.
 *
 * Generated: `php composer.phar container-surface-baseline`. Recorded on 2026-09-21
 * against `Laminas\ServiceManager\ServiceManager`, so that App\Services\Container
 * could be held to it. Asserted by SchoenstattTest\Integration\ContainerSurfaceTest.
 *
 * `instance` names the sharing group: every entry carrying the same label is the same
 * object. `probe` reads state a delegator set. `error` is the root cause a name fails
 * with in a console process, which is part of the contract too.
 */

declare(strict_types=1);

return array (
  'App\\Acl\\IsAllowed' => 
  array (
    'type' => 'App\\Acl\\IsAllowed',
    'instance' => 'App\\Acl\\IsAllowed',
    'shared' => true,
  ),
  'App\\Console\\Command\\BuildSitemapCommand' => 
  array (
    'type' => 'App\\Console\\Command\\BuildSitemapCommand',
    'instance' => 'App\\Console\\Command\\BuildSitemapCommand',
    'shared' => true,
  ),
  'App\\Console\\Command\\ImportLibraryBooksCommand' => 
  array (
    'type' => 'App\\Console\\Command\\ImportLibraryBooksCommand',
    'instance' => 'App\\Console\\Command\\ImportLibraryBooksCommand',
    'shared' => true,
  ),
  'App\\Console\\Command\\SendBookNoticesCommand' => 
  array (
    'type' => 'App\\Console\\Command\\SendBookNoticesCommand',
    'instance' => 'App\\Console\\Command\\SendBookNoticesCommand',
    'shared' => true,
  ),
  'App\\Modules\\ModuleConfig' => 
  array (
    'type' => 'App\\Modules\\ModuleConfig',
    'instance' => 'App\\Modules\\ModuleConfig',
    'shared' => true,
  ),
  'App\\Session\\HttpSession' => 
  array (
    'type' => 'App\\Session\\HttpSession',
    'instance' => 'App\\Session\\HttpSession',
    'shared' => true,
  ),
  'ApplicationConfig' => 
  array (
    'type' => 'array',
    'keys' => 2,
  ),
  'Books\\AuthorsValueOptions' => 
  array (
    'type' => 'array',
    'keys' => 81,
  ),
  'Books\\BorrowersValueOptions' => 
  array (
    'type' => 'array',
    'keys' => 125,
  ),
  'Books\\Cache' => 
  array (
    'type' => 'SionModel\\Cache\\FilesystemStorage',
    'instance' => 'Books\\Cache',
    'shared' => true,
  ),
  'Books\\Config' => 
  array (
    'type' => 'array',
    'keys' => 11,
  ),
  'Books\\FathersObjects' => 
  array (
    'type' => 'array',
    'keys' => 0,
  ),
  'Books\\Form\\CompositionForm' => 
  array (
    'type' => 'Books\\Form\\CompositionForm',
    'instance' => 'Books\\Form\\CompositionForm',
    'shared' => true,
  ),
  'Books\\Form\\DictionaryEntryForm' => 
  array (
    'type' => 'Books\\Form\\DictionaryEntryForm',
    'instance' => 'Books\\Form\\DictionaryEntryForm',
    'shared' => true,
  ),
  'Books\\Form\\PublicationForm' => 
  array (
    'type' => 'Books\\Form\\PublicationForm',
    'instance' => 'Books\\Form\\PublicationForm',
    'shared' => true,
  ),
  'Books\\Form\\PublicationsSearchForm' => 
  array (
    'type' => 'Books\\Form\\PublicationsSearchForm',
    'instance' => 'Books\\Form\\PublicationsSearchForm',
    'shared' => true,
  ),
  'Books\\Form\\SearchForm' => 
  array (
    'type' => 'Books\\Form\\SearchForm',
    'instance' => 'Books\\Form\\SearchForm',
    'shared' => true,
  ),
  'Books\\Form\\TextForm' => 
  array (
    'type' => 'Books\\Form\\TextForm',
    'instance' => 'Books\\Form\\TextForm',
    'shared' => true,
  ),
  'Books\\Mailing\\BooksMailer' => 
  array (
    'type' => 'Books\\Mailing\\BooksMailer',
    'instance' => 'Books\\Mailing\\BooksMailer',
    'shared' => true,
  ),
  'Books\\Model\\BorrowerTokenTable' => 
  array (
    'type' => 'Books\\Model\\BorrowerTokenTable',
    'instance' => 'Books\\Model\\BorrowerTokenTable',
    'shared' => true,
  ),
  'Books\\Model\\DictionaryTable' => 
  array (
    'type' => 'Books\\Model\\DictionaryTable',
    'instance' => 'Books\\Model\\DictionaryTable',
    'shared' => true,
  ),
  'Books\\Model\\EventTextTable' => 
  array (
    'type' => 'Books\\Model\\EventTextTable',
    'instance' => 'Books\\Model\\EventTextTable',
    'shared' => true,
  ),
  'Books\\Model\\LibraryTable' => 
  array (
    'type' => 'Books\\Model\\LibraryTable',
    'instance' => 'Books\\Model\\LibraryTable',
    'shared' => true,
  ),
  'Books\\Model\\MusicTable' => 
  array (
    'type' => 'Books\\Model\\MusicTable',
    'instance' => 'Books\\Model\\MusicTable',
    'shared' => true,
  ),
  'Books\\Model\\PublicationsTable' => 
  array (
    'type' => 'Books\\Model\\PublicationsTable',
    'instance' => 'Books\\Model\\PublicationsTable',
    'shared' => true,
  ),
  'Books\\Service\\DriveGateway' => 
  array (
    'type' => 'Books\\Service\\DriveGateway',
    'instance' => 'Books\\Service\\DriveGateway',
    'shared' => true,
  ),
  'Books\\Service\\SpreadsheetReader' => 
  array (
    'type' => 'Books\\Service\\SpreadsheetReader',
    'instance' => 'Books\\Service\\SpreadsheetReader',
    'shared' => true,
  ),
  'Config' => 
  array (
    'type' => 'array',
    'keys' => 19,
  ),
  'ConfigCacheFiles' => 
  array (
    'type' => 'array',
    'keys' => 2,
  ),
  'Configuration' => 
  array (
    'type' => 'array',
    'keys' => 19,
  ),
  'CountryValueOptions' => 
  array (
    'type' => 'array',
    'keys' => 249,
  ),
  'ExceptionsLogger' => 
  array (
    'type' => 'Monolog\\Logger',
    'instance' => 'ExceptionsLogger',
    'shared' => true,
  ),
  'JTranslate\\Cache' => 
  array (
    'type' => 'SionModel\\Cache\\ApcuStorage',
    'instance' => 'JTranslate\\Cache',
    'shared' => true,
  ),
  'JTranslate\\Cache\\PhraseCache' => 
  array (
    'type' => 'JTranslate\\Cache\\PhraseCache',
    'instance' => 'JTranslate\\Cache\\PhraseCache',
    'shared' => true,
  ),
  'JTranslate\\Config' => 
  array (
    'type' => 'array',
    'keys' => 11,
  ),
  'JTranslate\\Console\\Command\\ExportCatalogsCommand' => 
  array (
    'type' => 'JTranslate\\Console\\Command\\ExportCatalogsCommand',
    'instance' => 'JTranslate\\Console\\Command\\ExportCatalogsCommand',
    'shared' => true,
  ),
  'JTranslate\\Console\\Command\\MigrateCommand' => 
  array (
    'type' => 'JTranslate\\Console\\Command\\MigrateCommand',
    'instance' => 'JTranslate\\Console\\Command\\MigrateCommand',
    'shared' => true,
  ),
  'JTranslate\\Console\\Command\\RetirePhrasesCommand' => 
  array (
    'type' => 'JTranslate\\Console\\Command\\RetirePhrasesCommand',
    'instance' => 'JTranslate\\Console\\Command\\RetirePhrasesCommand',
    'shared' => true,
  ),
  'JTranslate\\Form\\EditPhraseForm' => 
  array (
    'type' => 'JTranslate\\Form\\EditPhraseForm',
    'instance' => 'JTranslate\\Form\\EditPhraseForm',
    'shared' => true,
  ),
  'JTranslate\\Form\\PhraseValidator' => 
  array (
    'type' => 'JTranslate\\Form\\PhraseValidator',
    'instance' => 'JTranslate\\Form\\PhraseValidator',
    'shared' => true,
  ),
  'JTranslate\\I18n\\Translator\\Translator' => 
  array (
    'type' => 'JTranslate\\I18n\\Translator\\Translator',
    'instance' => 'JTranslate\\I18n\\Translator\\Translator',
    'shared' => true,
    'probe' => 'locale=en_US fallback=en_US',
  ),
  'JTranslate\\Migration\\MigrationRunner' => 
  array (
    'type' => 'JTranslate\\Migration\\MigrationRunner',
    'instance' => 'JTranslate\\Migration\\MigrationRunner',
    'shared' => true,
  ),
  'JTranslate\\Model\\CountriesInfo' => 
  array (
    'type' => 'JTranslate\\Model\\CountriesInfo',
    'instance' => 'JTranslate\\Model\\CountriesInfo',
    'shared' => true,
  ),
  'JTranslate\\Model\\TranslationsTable' => 
  array (
    'type' => 'JTranslate\\Model\\TranslationsTable',
    'instance' => 'JTranslate\\Model\\TranslationsTable',
    'shared' => true,
    'probe' => 'userModules=Application,Books,JTranslate,JUser,Schoenstatt,SionModel',
  ),
  'JUser\\Cache' => 
  array (
    'type' => 'SionModel\\Cache\\ApcuStorage',
    'instance' => 'JUser\\Cache',
    'shared' => true,
  ),
  'JUser\\Config' => 
  array (
    'type' => 'array',
    'keys' => 8,
  ),
  'JUser\\Form\\CreateRoleForm' => 
  array (
    'type' => 'JUser\\Form\\CreateRoleForm',
    'instance' => 'JUser\\Form\\CreateRoleForm',
    'shared' => true,
  ),
  'JUser\\Form\\EditUserForm' => 
  array (
    'type' => 'JUser\\Form\\EditUserForm',
    'instance' => 'JUser\\Form\\EditUserForm',
    'shared' => true,
  ),
  'JUser\\Host\\IdentityInterface' => 
  array (
    'type' => 'JUser\\Authentication\\SessionIdentity',
    'instance' => 'JUser\\Host\\IdentityInterface',
    'shared' => true,
  ),
  'JUser\\Host\\SessionInterface' => 
  array (
    'type' => 'App\\JUser\\Host\\Session',
    'instance' => 'JUser\\Host\\SessionInterface',
    'shared' => true,
  ),
  'JUser\\Logger' => 
  array (
    'type' => 'Monolog\\Logger',
    'instance' => 'JUser\\Logger',
    'shared' => true,
  ),
  'JUser\\Model\\ApiTokenTable' => 
  array (
    'type' => 'JUser\\Model\\ApiTokenTable',
    'instance' => 'JUser\\Model\\ApiTokenTable',
    'shared' => true,
  ),
  'JUser\\Model\\UserTable' => 
  array (
    'type' => 'JUser\\Model\\UserTable',
    'instance' => 'JUser\\Model\\UserTable',
    'shared' => true,
  ),
  'JUser\\Service\\ApiTokenService' => 
  array (
    'type' => 'JUser\\Service\\ApiTokenService',
    'instance' => 'JUser\\Service\\ApiTokenService',
    'shared' => true,
  ),
  'JUser\\Service\\LoginTokenService' => 
  array (
    'type' => 'JUser\\Service\\LoginTokenService',
    'instance' => 'JUser\\Service\\LoginTokenService',
    'shared' => true,
  ),
  'JUser\\Service\\Mailer' => 
  array (
    'type' => 'JUser\\Service\\Mailer',
    'instance' => 'JUser\\Service\\Mailer',
    'shared' => true,
  ),
  'Laminas\\Db\\Adapter\\Adapter' => 
  array (
    'type' => 'Laminas\\Db\\Adapter\\Adapter',
    'instance' => 'Laminas\\Db\\Adapter\\Adapter',
    'shared' => true,
  ),
  'Laminas\\Translator\\TranslatorInterface' => 
  array (
    'type' => 'JTranslate\\I18n\\Translator\\Translator',
    'instance' => 'JTranslate\\I18n\\Translator\\Translator',
    'shared' => true,
    'probe' => 'locale=en_US fallback=en_US',
  ),
  'MvcTranslator' => 
  array (
    'type' => 'JTranslate\\I18n\\Translator\\Translator',
    'instance' => 'JTranslate\\I18n\\Translator\\Translator',
    'shared' => true,
    'probe' => 'locale=en_US fallback=en_US',
  ),
  'Psr\\Log\\LoggerInterface' => 
  array (
    'type' => 'Monolog\\Logger',
    'instance' => 'JUser\\Logger',
    'shared' => true,
  ),
  'Schoenstatt\\Config' => 
  array (
    'type' => 'array',
    'keys' => 14,
  ),
  'Schoenstatt\\FathersValueOptions' => 
  array (
    'type' => 'array',
    'keys' => 0,
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm' => 
  array (
    'type' => 'Schoenstatt\\Form\\AdvancedSearchForm',
    'instance' => 'Schoenstatt\\Form\\AdvancedSearchForm',
    'shared' => true,
  ),
  'Schoenstatt\\Form\\AssignmentForm' => 
  array (
    'type' => 'Schoenstatt\\Form\\AssignmentForm',
    'instance' => 'Schoenstatt\\Form\\AssignmentForm',
    'shared' => true,
  ),
  'Schoenstatt\\Form\\AssociationForm' => 
  array (
    'type' => 'Schoenstatt\\Form\\AssociationForm',
    'instance' => 'Schoenstatt\\Form\\AssociationForm',
    'shared' => true,
  ),
  'Schoenstatt\\Form\\EditAssignmentForm' => 
  array (
    'type' => 'Schoenstatt\\Form\\EditAssignmentForm',
    'instance' => 'Schoenstatt\\Form\\EditAssignmentForm',
    'shared' => true,
  ),
  'Schoenstatt\\Form\\ImportFatherForm' => 
  array (
    'type' => 'Schoenstatt\\Form\\ImportFatherForm',
    'instance' => 'Schoenstatt\\Form\\ImportFatherForm',
    'shared' => true,
  ),
  'Schoenstatt\\Form\\PersonForm' => 
  array (
    'type' => 'Schoenstatt\\Form\\PersonForm',
    'instance' => 'Schoenstatt\\Form\\PersonForm',
    'shared' => true,
  ),
  'Schoenstatt\\Form\\RoleForm' => 
  array (
    'type' => 'Schoenstatt\\Form\\RoleForm',
    'instance' => 'Schoenstatt\\Form\\RoleForm',
    'shared' => true,
  ),
  'Schoenstatt\\Model\\SchoenstattTable' => 
  array (
    'type' => 'Schoenstatt\\Model\\SchoenstattTable',
    'instance' => 'Schoenstatt\\Model\\SchoenstattTable',
    'shared' => true,
  ),
  'Schoenstatt\\PersonTagsValueOptions' => 
  array (
    'type' => 'array',
    'keys' => 11,
  ),
  'Schoenstatt\\Service\\AssociationKindsService' => 
  array (
    'type' => 'Schoenstatt\\Service\\AssociationKindsService',
    'instance' => 'Schoenstatt\\Service\\AssociationKindsService',
    'shared' => true,
  ),
  'Schoenstatt\\Service\\PatresGateway' => 
  array (
    'type' => 'Schoenstatt\\Service\\PatresGateway',
    'instance' => 'Schoenstatt\\Service\\PatresGateway',
    'shared' => true,
  ),
  'SionModel\\Cache\\EntityChangeListeners' => 
  array (
    'type' => 'SionModel\\Cache\\EntityChangeListeners',
    'instance' => 'SionModel\\Cache\\EntityChangeListeners',
    'shared' => true,
  ),
  'SionModel\\Cache\\Storage' => 
  array (
    'type' => 'SionModel\\Cache\\FilesystemStorage',
    'instance' => 'SionModel\\Cache\\Storage',
    'shared' => true,
  ),
  'SionModel\\Config' => 
  array (
    'type' => 'array',
    'keys' => 30,
  ),
  'SionModel\\Console\\Command\\ClearConfigCacheCommand' => 
  array (
    'type' => 'SionModel\\Console\\Command\\ClearConfigCacheCommand',
    'instance' => 'SionModel\\Console\\Command\\ClearConfigCacheCommand',
    'shared' => true,
  ),
  'SionModel\\Console\\Command\\FlushPersistentCacheCommand' => 
  array (
    'type' => 'SionModel\\Console\\Command\\FlushPersistentCacheCommand',
    'instance' => 'SionModel\\Console\\Command\\FlushPersistentCacheCommand',
    'shared' => true,
  ),
  'SionModel\\Db\\Model\\FilesTable' => 
  array (
    'type' => 'SionModel\\Db\\Model\\FilesTable',
    'instance' => 'SionModel\\Db\\Model\\FilesTable',
    'shared' => true,
  ),
  'SionModel\\Db\\Model\\PredicatesTable' => 
  array (
    'type' => 'SionModel\\Db\\Model\\PredicatesTable',
    'instance' => 'SionModel\\Db\\Model\\PredicatesTable',
    'shared' => true,
  ),
  'SionModel\\Error\\ExceptionNotifier' => 
  array (
    'type' => 'SionModel\\Error\\ExceptionNotifier',
    'instance' => 'SionModel\\Error\\ExceptionNotifier',
    'shared' => true,
  ),
  'SionModel\\Error\\ExceptionStore' => 
  array (
    'type' => 'SionModel\\Error\\ExceptionStore',
    'instance' => 'SionModel\\Error\\ExceptionStore',
    'shared' => true,
  ),
  'SionModel\\Error\\Fingerprinter' => 
  array (
    'type' => 'SionModel\\Error\\Fingerprinter',
    'instance' => 'SionModel\\Error\\Fingerprinter',
    'shared' => true,
  ),
  'SionModel\\Error\\RequestContext' => 
  array (
    'type' => 'SionModel\\Error\\RequestContext',
    'instance' => 'SionModel\\Error\\RequestContext',
    'shared' => true,
  ),
  'SionModel\\ExceptionMailTransport' => 
  array (
    'type' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
    'instance' => 'SionModel\\ExceptionMailTransport',
    'shared' => true,
  ),
  'SionModel\\I18n\\LanguageSupport' => 
  array (
    'type' => 'SionModel\\I18n\\LanguageSupport',
    'instance' => 'SionModel\\I18n\\LanguageSupport',
    'shared' => true,
  ),
  'SionModel\\Logger' => 
  array (
    'type' => 'Monolog\\Logger',
    'instance' => 'JUser\\Logger',
    'shared' => true,
  ),
  'SionModel\\MailTransport' => 
  array (
    'type' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
    'instance' => 'SionModel\\ExceptionMailTransport',
    'shared' => true,
  ),
  'SionModel\\Mailing\\Mailer' => 
  array (
    'type' => 'SionModel\\Mailing\\Mailer',
    'instance' => 'SionModel\\Mailing\\Mailer',
    'shared' => true,
  ),
  'SionModel\\Mailing\\TemplateRendererInterface' => 
  array (
    'type' => 'SionModel\\Mailing\\TwigTemplateRenderer',
    'instance' => 'SionModel\\Mailing\\TemplateRendererInterface',
    'shared' => true,
  ),
  'SionModel\\PersistentCache' => 
  array (
    'type' => 'SionModel\\Cache\\ApcuStorage',
    'instance' => 'SionModel\\PersistentCache',
    'shared' => true,
  ),
  'SionModel\\Service\\ActingUserProviderInterface' => 
  array (
    'type' => 'JUser\\Service\\IdentityActingUserProvider',
    'instance' => 'SionModel\\Service\\ActingUserProviderInterface',
    'shared' => true,
  ),
  'SionModel\\Service\\ChangesCollector' => 
  array (
    'type' => 'SionModel\\Service\\ChangesCollector',
    'instance' => 'SionModel\\Service\\ChangesCollector',
    'shared' => true,
  ),
  'SionModel\\Service\\EntitiesService' => 
  array (
    'type' => 'SionModel\\Service\\EntitiesService',
    'instance' => 'SionModel\\Service\\EntitiesService',
    'shared' => true,
  ),
  'SionModel\\Service\\ErrorHandling' => 
  array (
    'type' => 'SionModel\\Service\\ErrorHandling',
    'instance' => 'SionModel\\Service\\ErrorHandling',
    'shared' => true,
  ),
  'SionModel\\Service\\ProblemService' => 
  array (
    'type' => 'SionModel\\Service\\ProblemService',
    'instance' => 'SionModel\\Service\\ProblemService',
    'shared' => true,
  ),
  'config' => 
  array (
    'type' => 'array',
    'keys' => 19,
  ),
  'configuration' => 
  array (
    'type' => 'array',
    'keys' => 19,
  ),
  'jtranslate_db_adapter' => 
  array (
    'type' => 'Laminas\\Db\\Adapter\\Adapter',
    'instance' => 'Laminas\\Db\\Adapter\\Adapter',
    'shared' => true,
  ),
  'jtranslate_translator' => 
  array (
    'type' => 'JTranslate\\I18n\\Translator\\Translator',
    'instance' => 'JTranslate\\I18n\\Translator\\Translator',
    'shared' => true,
    'probe' => 'locale=en_US fallback=en_US',
  ),
  'translator' => 
  array (
    'type' => 'JTranslate\\I18n\\Translator\\Translator',
    'instance' => 'JTranslate\\I18n\\Translator\\Translator',
    'shared' => true,
    'probe' => 'locale=en_US fallback=en_US',
  ),
  'zfcuser_user_mapper' => 
  array (
    'type' => 'JUser\\Model\\UserTable',
    'instance' => 'JUser\\Model\\UserTable',
    'shared' => true,
  ),
  'zfcuser_zend_db_adapter' => 
  array (
    'type' => 'Laminas\\Db\\Adapter\\Adapter',
    'instance' => 'Laminas\\Db\\Adapter\\Adapter',
    'shared' => true,
  ),
);
