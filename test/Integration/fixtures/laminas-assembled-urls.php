<?php

declare(strict_types=1);

/**
 * What the **laminas** router assembled for each route and locale, captured 2026-09-09
 * from `Laminas\Router\Http\TreeRouteStack` immediately before laminas-router was
 * removed and the live comparison became impossible.
 *
 * The oracle for test/Integration/SymfonyUrlParityTest: a frozen record of the old
 * implementation, not a second reading of the new one. Regenerating it from the Symfony
 * generator would turn the test into an echo, so it must not be regenerated — a
 * disagreement here is a real change in a URL the site emits.
 *
 * Keyed `<symfony route name>|<locale>`; `params` are the values that produced it.
 */

return array (
  'acknowledgements.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/acknowledgements',
  ),
  'acknowledgements.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/acknowledgements',
  ),
  'admin.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/admin',
  ),
  'admin.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/admin',
  ),
  'admin/import-father.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/admin/import-father',
  ),
  'admin/import-father.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/admin/import-father',
  ),
  'assignments/advanced-search.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/assignments/advanced-search',
  ),
  'assignments/advanced-search.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/assignments/advanced-search',
  ),
  'assignments/assignment/delete.locale|en' => 
  array (
    'params' => 
    array (
      'assignment_id' => '1',
    ),
    'expected' => '/en/assignments/1/delete',
  ),
  'assignments/assignment/delete.locale|es' => 
  array (
    'params' => 
    array (
      'assignment_id' => '1',
    ),
    'expected' => '/es/assignments/1/delete',
  ),
  'assignments/assignment/edit.locale|en' => 
  array (
    'params' => 
    array (
      'assignment_id' => '1',
    ),
    'expected' => '/en/assignments/1/edit',
  ),
  'assignments/assignment/edit.locale|es' => 
  array (
    'params' => 
    array (
      'assignment_id' => '1',
    ),
    'expected' => '/es/assignments/1/edit',
  ),
  'assignments/create.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/assignments/create',
  ),
  'assignments/create.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/assignments/create',
  ),
  'assignments/search.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/assignments/search',
  ),
  'assignments/search.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/assignments/search',
  ),
  'association-delete.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL110000A',
    ),
    'expected' => '/en/SL110000A/delete',
  ),
  'association-delete.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL110000A',
    ),
    'expected' => '/es/SL110000A/delete',
  ),
  'association-edit.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL110000A',
    ),
    'expected' => '/en/SL110000A/edit',
  ),
  'association-edit.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL110000A',
    ),
    'expected' => '/es/SL110000A/edit',
  ),
  'association.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL110000A',
      'slug' => '1',
    ),
    'expected' => '/en/SL110000A/1',
  ),
  'association.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL110000A',
      'slug' => '1',
    ),
    'expected' => '/es/SL110000A/1',
  ),
  'associations.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/associations',
  ),
  'associations.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/associations',
  ),
  'associations/association.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL110000A',
    ),
    'expected' => '/en/associations/SL110000A',
  ),
  'associations/association.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL110000A',
    ),
    'expected' => '/es/associations/SL110000A',
  ),
  'associations/create.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/associations/create',
  ),
  'associations/create.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/associations/create',
  ),
  'associations/old-association.locale|en' => 
  array (
    'params' => 
    array (
      'association_id' => '1',
    ),
    'expected' => '/en/associations/1',
  ),
  'associations/old-association.locale|es' => 
  array (
    'params' => 
    array (
      'association_id' => '1',
    ),
    'expected' => '/es/associations/1',
  ),
  'books/book.locale|en' => 
  array (
    'params' => 
    array (
      'book_id' => '1',
    ),
    'expected' => '/en/books/1',
  ),
  'books/book.locale|es' => 
  array (
    'params' => 
    array (
      'book_id' => '1',
    ),
    'expected' => '/es/books/1',
  ),
  'books/book/edit.locale|en' => 
  array (
    'params' => 
    array (
      'book_id' => '1',
    ),
    'expected' => '/en/books/1/edit',
  ),
  'books/book/edit.locale|es' => 
  array (
    'params' => 
    array (
      'book_id' => '1',
    ),
    'expected' => '/es/books/1/edit',
  ),
  'books/create.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/books/create/1',
  ),
  'books/create.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/books/create/1',
  ),
  'borrowers/borrower.locale|en' => 
  array (
    'params' => 
    array (
      'person_id' => '1',
    ),
    'expected' => '/en/borrowers/1',
  ),
  'borrowers/borrower.locale|es' => 
  array (
    'params' => 
    array (
      'person_id' => '1',
    ),
    'expected' => '/es/borrowers/1',
  ),
  'checkouts/library.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/checkouts/library/1',
  ),
  'checkouts/library.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/checkouts/library/1',
  ),
  'checkouts/library/current.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/checkouts/library/1/current',
  ),
  'checkouts/library/current.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/checkouts/library/1/current',
  ),
  'checkouts/library/overdue.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/checkouts/library/1/overdue',
  ),
  'checkouts/library/overdue.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/checkouts/library/1/overdue',
  ),
  'collections/collection/edit.locale|en' => 
  array (
    'params' => 
    array (
      'collection_id' => '1',
    ),
    'expected' => '/en/collections/1/edit',
  ),
  'collections/collection/edit.locale|es' => 
  array (
    'params' => 
    array (
      'collection_id' => '1',
    ),
    'expected' => '/es/collections/1/edit',
  ),
  'collections/create.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/collections/create/1',
  ),
  'collections/create.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/collections/create/1',
  ),
  'comments/create.locale|en' => 
  array (
    'params' => 
    array (
      'entity' => 'es',
      'entity_id' => '1',
      'kind' => 'comment',
    ),
    'expected' => '/en/comments/create/es/1',
  ),
  'comments/create.locale|es' => 
  array (
    'params' => 
    array (
      'entity' => 'es',
      'entity_id' => '1',
      'kind' => 'comment',
    ),
    'expected' => '/es/comments/create/es/1',
  ),
  'composition-delete.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL510000C',
    ),
    'expected' => '/en/SL510000C/delete',
  ),
  'composition-delete.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL510000C',
    ),
    'expected' => '/es/SL510000C/delete',
  ),
  'composition-edit.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL510000C',
    ),
    'expected' => '/en/SL510000C/edit',
  ),
  'composition-edit.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL510000C',
    ),
    'expected' => '/es/SL510000C/edit',
  ),
  'composition.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL510000C',
      'slug' => '1',
    ),
    'expected' => '/en/SL510000C/1',
  ),
  'composition.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL510000C',
      'slug' => '1',
    ),
    'expected' => '/es/SL510000C/1',
  ),
  'developers.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/developers',
  ),
  'developers.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/developers',
  ),
  'dictionary.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/dictionary',
  ),
  'dictionary.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/dictionary',
  ),
  'dictionary/create.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/dictionary/create',
  ),
  'dictionary/create.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/dictionary/create',
  ),
  'dictionary/entry/edit.locale|en' => 
  array (
    'params' => 
    array (
      'entry_id' => '1',
    ),
    'expected' => '/en/dictionary/1/edit',
  ),
  'dictionary/entry/edit.locale|es' => 
  array (
    'params' => 
    array (
      'entry_id' => '1',
    ),
    'expected' => '/es/dictionary/1/edit',
  ),
  'dictionary/inLanguage.locale|en' => 
  array (
    'params' => 
    array (
      'inLanguage' => 'es',
    ),
    'expected' => '/en/dictionary/es',
  ),
  'dictionary/inLanguage.locale|es' => 
  array (
    'params' => 
    array (
      'inLanguage' => 'es',
    ),
    'expected' => '/es/dictionary/es',
  ),
  'events.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/timeline',
  ),
  'events.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/timeline',
  ),
  'jtranslate.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/admin/translations',
  ),
  'jtranslate.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/admin/translations',
  ),
  'jtranslate/phrase/delete.locale|en' => 
  array (
    'params' => 
    array (
      'phrase_id' => '1',
    ),
    'expected' => '/en/admin/translations/1/delete',
  ),
  'jtranslate/phrase/delete.locale|es' => 
  array (
    'params' => 
    array (
      'phrase_id' => '1',
    ),
    'expected' => '/es/admin/translations/1/delete',
  ),
  'jtranslate/phrase/edit.locale|en' => 
  array (
    'params' => 
    array (
      'phrase_id' => '1',
    ),
    'expected' => '/en/admin/translations/1/edit',
  ),
  'jtranslate/phrase/edit.locale|es' => 
  array (
    'params' => 
    array (
      'phrase_id' => '1',
    ),
    'expected' => '/es/admin/translations/1/edit',
  ),
  'juser.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/users',
  ),
  'juser.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/users',
  ),
  'juser/create-role.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/users/roles/create',
  ),
  'juser/create-role.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/users/roles/create',
  ),
  'juser/create.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/users/create',
  ),
  'juser/create.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/users/create',
  ),
  'juser/user/api-token-revoke.locale|en' => 
  array (
    'params' => 
    array (
      'user_id' => '1',
      'token_id' => '1',
    ),
    'expected' => '/en/users/1/api-tokens/1/revoke',
  ),
  'juser/user/api-token-revoke.locale|es' => 
  array (
    'params' => 
    array (
      'user_id' => '1',
      'token_id' => '1',
    ),
    'expected' => '/es/users/1/api-tokens/1/revoke',
  ),
  'juser/user/api-tokens.locale|en' => 
  array (
    'params' => 
    array (
      'user_id' => '1',
    ),
    'expected' => '/en/users/1/api-tokens',
  ),
  'juser/user/api-tokens.locale|es' => 
  array (
    'params' => 
    array (
      'user_id' => '1',
    ),
    'expected' => '/es/users/1/api-tokens',
  ),
  'juser/user/delete.locale|en' => 
  array (
    'params' => 
    array (
      'user_id' => '1',
    ),
    'expected' => '/en/users/1/delete',
  ),
  'juser/user/delete.locale|es' => 
  array (
    'params' => 
    array (
      'user_id' => '1',
    ),
    'expected' => '/es/users/1/delete',
  ),
  'juser/user/edit.locale|en' => 
  array (
    'params' => 
    array (
      'user_id' => '1',
    ),
    'expected' => '/en/users/1/edit',
  ),
  'juser/user/edit.locale|es' => 
  array (
    'params' => 
    array (
      'user_id' => '1',
    ),
    'expected' => '/es/users/1/edit',
  ),
  'libraries.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/libraries',
  ),
  'libraries.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/libraries',
  ),
  'libraries/create.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/libraries/create',
  ),
  'libraries/create.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/libraries/create',
  ),
  'libraries/library.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1',
  ),
  'libraries/library.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1',
  ),
  'libraries/library/admin.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/admin',
  ),
  'libraries/library/admin.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/admin',
  ),
  'libraries/library/book-list-json.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/book-list-json',
  ),
  'libraries/library/book-list-json.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/book-list-json',
  ),
  'libraries/library/book-list.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/book-list',
  ),
  'libraries/library/book-list.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/book-list',
  ),
  'libraries/library/checkin.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/checkin',
  ),
  'libraries/library/checkin.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/checkin',
  ),
  'libraries/library/checkout.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/checkout',
  ),
  'libraries/library/checkout.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/checkout',
  ),
  'libraries/library/collections.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/collections',
  ),
  'libraries/library/collections.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/collections',
  ),
  'libraries/library/data-problems.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/data-problems',
  ),
  'libraries/library/data-problems.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/data-problems',
  ),
  'libraries/library/delete.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/delete',
  ),
  'libraries/library/delete.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/delete',
  ),
  'libraries/library/edit.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/edit',
  ),
  'libraries/library/edit.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/edit',
  ),
  'libraries/library/inactivate-books.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/inactivate-books',
  ),
  'libraries/library/inactivate-books.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/inactivate-books',
  ),
  'libraries/library/label-management.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/label-management',
  ),
  'libraries/library/label-management.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/label-management',
  ),
  'libraries/library/mass-checkout.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/mass-checkout',
  ),
  'libraries/library/mass-checkout.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/mass-checkout',
  ),
  'libraries/library/refresh-sort.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/refresh-sort',
  ),
  'libraries/library/refresh-sort.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/refresh-sort',
  ),
  'libraries/library/send-book-notices.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/send-book-notices',
  ),
  'libraries/library/send-book-notices.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/send-book-notices',
  ),
  'libraries/library/sort-debugging.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/libraries/1/sort-debugging',
  ),
  'libraries/library/sort-debugging.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/libraries/1/sort-debugging',
  ),
  'library-imports/library-import.locale|en' => 
  array (
    'params' => 
    array (
      'import_id' => '1',
    ),
    'expected' => '/en/library-imports/1',
  ),
  'library-imports/library-import.locale|es' => 
  array (
    'params' => 
    array (
      'import_id' => '1',
    ),
    'expected' => '/es/library-imports/1',
  ),
  'library-imports/library-import/edit.locale|en' => 
  array (
    'params' => 
    array (
      'import_id' => '1',
    ),
    'expected' => '/en/library-imports/1/edit',
  ),
  'library-imports/library-import/edit.locale|es' => 
  array (
    'params' => 
    array (
      'import_id' => '1',
    ),
    'expected' => '/es/library-imports/1/edit',
  ),
  'library-imports/library.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/library-imports/library/1',
  ),
  'library-imports/library.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/library-imports/library/1',
  ),
  'library-imports/library/create.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/library-imports/library/1/create',
  ),
  'library-imports/library/create.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/library-imports/library/1/create',
  ),
  'library-imports/library/template.locale|en' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/en/library-imports/library/1/template',
  ),
  'library-imports/library/template.locale|es' => 
  array (
    'params' => 
    array (
      'library_id' => '1',
    ),
    'expected' => '/es/library-imports/library/1/template',
  ),
  'music.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/music',
  ),
  'music.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/music',
  ),
  'music/create-composition.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/music/create-composition',
  ),
  'music/create-composition.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/music/create-composition',
  ),
  'persons.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/persons',
  ),
  'persons.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/persons',
  ),
  'persons/create.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/persons/create',
  ),
  'persons/create.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/persons/create',
  ),
  'persons/person.locale|en' => 
  array (
    'params' => 
    array (
      'person_id' => '1',
    ),
    'expected' => '/en/persons/1',
  ),
  'persons/person.locale|es' => 
  array (
    'params' => 
    array (
      'person_id' => '1',
    ),
    'expected' => '/es/persons/1',
  ),
  'persons/person/delete.locale|en' => 
  array (
    'params' => 
    array (
      'person_id' => '1',
    ),
    'expected' => '/en/persons/1/delete',
  ),
  'persons/person/delete.locale|es' => 
  array (
    'params' => 
    array (
      'person_id' => '1',
    ),
    'expected' => '/es/persons/1/delete',
  ),
  'persons/person/edit.locale|en' => 
  array (
    'params' => 
    array (
      'person_id' => '1',
    ),
    'expected' => '/en/persons/1/edit',
  ),
  'persons/person/edit.locale|es' => 
  array (
    'params' => 
    array (
      'person_id' => '1',
    ),
    'expected' => '/es/persons/1/edit',
  ),
  'persons/search.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/persons/search',
  ),
  'persons/search.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/persons/search',
  ),
  'privacy.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/privacy',
  ),
  'privacy.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/privacy',
  ),
  'publication-copy-to-main-corpus.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL210000L',
    ),
    'expected' => '/en/SL210000L/copy-to-main-corpus',
  ),
  'publication-copy-to-main-corpus.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL210000L',
    ),
    'expected' => '/es/SL210000L/copy-to-main-corpus',
  ),
  'publication-create-new-edition.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL210000L',
    ),
    'expected' => '/en/SL210000L/create-new-edition',
  ),
  'publication-create-new-edition.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL210000L',
    ),
    'expected' => '/es/SL210000L/create-new-edition',
  ),
  'publication-delete.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL210000L',
    ),
    'expected' => '/en/SL210000L/delete',
  ),
  'publication-delete.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL210000L',
    ),
    'expected' => '/es/SL210000L/delete',
  ),
  'publication-edit.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL210000L',
    ),
    'expected' => '/en/SL210000L/edit',
  ),
  'publication-edit.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL210000L',
    ),
    'expected' => '/es/SL210000L/edit',
  ),
  'publication.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL210000L',
      'slug' => '1',
    ),
    'expected' => '/en/SL210000L/1',
  ),
  'publication.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL210000L',
      'slug' => '1',
    ),
    'expected' => '/es/SL210000L/1',
  ),
  'publications.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/literature',
  ),
  'publications.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/literature',
  ),
  'publications/create.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/literature/create',
  ),
  'publications/create.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/literature/create',
  ),
  'publications/export.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/literature/export',
  ),
  'publications/export.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/literature/export',
  ),
  'publications/index.locale|en' => 
  array (
    'params' => 
    array (
      'inLanguage' => 'es',
    ),
    'expected' => '/en/literature/es',
  ),
  'publications/index.locale|es' => 
  array (
    'params' => 
    array (
      'inLanguage' => 'es',
    ),
    'expected' => '/es/literature/es',
  ),
  'publications/one-fifty-preguntas.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/literature/150-preguntas-sobre-schoenstatt',
  ),
  'publications/one-fifty-preguntas.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/literature/150-preguntas-sobre-schoenstatt',
  ),
  'publications/prime-authors.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/literature/prime-authors',
  ),
  'publications/prime-authors.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/literature/prime-authors',
  ),
  'publications/publication-old.locale|en' => 
  array (
    'params' => 
    array (
      'publication_id' => '1',
    ),
    'expected' => '/en/literature/1',
  ),
  'publications/publication-old.locale|es' => 
  array (
    'params' => 
    array (
      'publication_id' => '1',
    ),
    'expected' => '/es/literature/1',
  ),
  'publications/search.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/literature/search',
  ),
  'publications/search.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/literature/search',
  ),
  'redirect-pre-april-2020-sl-id.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL10000A',
      'slug' => '1',
    ),
    'expected' => '/en/SL10000A/1',
  ),
  'redirect-pre-april-2020-sl-id.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL10000A',
      'slug' => '1',
    ),
    'expected' => '/es/SL10000A/1',
  ),
  'roles.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/roles',
  ),
  'roles.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/roles',
  ),
  'roles/create.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/roles/create',
  ),
  'roles/create.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/roles/create',
  ),
  'roles/role/delete.locale|en' => 
  array (
    'params' => 
    array (
      'role_id' => '1',
    ),
    'expected' => '/en/roles/1/delete',
  ),
  'roles/role/delete.locale|es' => 
  array (
    'params' => 
    array (
      'role_id' => '1',
    ),
    'expected' => '/es/roles/1/delete',
  ),
  'roles/role/edit.locale|en' => 
  array (
    'params' => 
    array (
      'role_id' => '1',
    ),
    'expected' => '/en/roles/1/edit',
  ),
  'roles/role/edit.locale|es' => 
  array (
    'params' => 
    array (
      'role_id' => '1',
    ),
    'expected' => '/es/roles/1/edit',
  ),
  'schoenstatt.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/movement',
  ),
  'schoenstatt.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/movement',
  ),
  'shrines.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/shrines',
  ),
  'shrines.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/shrines',
  ),
  'shrines/submitting-photos.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/shrines/submitting-photos',
  ),
  'shrines/submitting-photos.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/shrines/submitting-photos',
  ),
  'sion-model/data-problems.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/sm/data-problems',
  ),
  'sion-model/data-problems.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/sm/data-problems',
  ),
  'sion-model/phpinfo.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/sm/phpinfo',
  ),
  'sion-model/phpinfo.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/sm/phpinfo',
  ),
  'sion-model/view-changes.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/sm/view-changes',
  ),
  'sion-model/view-changes.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/sm/view-changes',
  ),
  'sitemap.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/sitemap.xml',
  ),
  'sitemap.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/sitemap.xml',
  ),
  'text-delete.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL410000T',
    ),
    'expected' => '/en/SL410000T/delete',
  ),
  'text-delete.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL410000T',
    ),
    'expected' => '/es/SL410000T/delete',
  ),
  'text-edit.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL410000T',
    ),
    'expected' => '/en/SL410000T/edit',
  ),
  'text-edit.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL410000T',
    ),
    'expected' => '/es/SL410000T/edit',
  ),
  'text.locale|en' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL410000T',
      'slug' => '1',
    ),
    'expected' => '/en/SL410000T/1',
  ),
  'text.locale|es' => 
  array (
    'params' => 
    array (
      'sw_id' => 'SL410000T',
      'slug' => '1',
    ),
    'expected' => '/es/SL410000T/1',
  ),
  'texts.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/texts',
  ),
  'texts.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/texts',
  ),
  'texts/create.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/texts/create',
  ),
  'texts/create.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/texts/create',
  ),
  'wayside-shrines.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/wayside-shrines',
  ),
  'wayside-shrines.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/wayside-shrines',
  ),
  'welcome.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/',
  ),
  'welcome.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/',
  ),
  'zfcuser.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/user',
  ),
  'zfcuser.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/user',
  ),
  'zfcuser/login.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/user/login',
  ),
  'zfcuser/login.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/user/login',
  ),
  'zfcuser/logout.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/user/logout',
  ),
  'zfcuser/logout.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/user/logout',
  ),
  'zfcuser/verify.locale|en' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/en/user/verify',
  ),
  'zfcuser/verify.locale|es' => 
  array (
    'params' => 
    array (
    ),
    'expected' => '/es/user/verify',
  ),
);
