<?php

/**
 * What `SionModel\Form\Validation\InputFilter` answers for every form in the application.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php -d memory_limit=1G \
 *         test/Form/regenerate-engine-surface.php
 *
 * Keyed `Form\Class` => dataset => verdict, returned values and messages, over the two
 * datasets `SchoenstattTest\Form\FormData` produces. The values are the array a controller
 * hands to `SionTable::updateEntity()`, which is the half of the contract a rendered-markup
 * baseline cannot see: `value=""` is what both `''` and `null` produce.
 *
 * See `SchoenstattTest\Form\EngineSurface` for what is normalised and why this replaces the
 * three parity tests that die with `Laminas\Form\Form`.
 *
 * @return array<string, array<string, array<string, mixed>>>
 */

declare(strict_types=1);

return array (
  'App\\Books\\Import\\ImportMappingFieldset' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'adminNotes' => '',
        'adminTags' => '',
        'authorsText' => '',
        'bookEdition' => '',
        'callNumber' => '',
        'category' => '',
        'collection' => '',
        'inLanguage' => '',
        'isbn' => '',
        'keywords' => '',
        'newCallNumber' => '',
        'numberOfPages' => '',
        'publicNotes' => '',
        'publicationId' => '',
        'publishedYear' => '',
        'publisher' => '',
        'publishingPlace' => '',
        'title' => '',
        'withinLibraryId' => '',
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'adminNotes' => 'no-such-option',
        'adminTags' => 'no-such-option',
        'authorsText' => 'no-such-option',
        'bookEdition' => 'no-such-option',
        'callNumber' => 'no-such-option',
        'category' => 'no-such-option',
        'collection' => 'no-such-option',
        'inLanguage' => 'no-such-option',
        'isbn' => 'no-such-option',
        'keywords' => 'no-such-option',
        'newCallNumber' => 'no-such-option',
        'numberOfPages' => 'no-such-option',
        'publicNotes' => 'no-such-option',
        'publicationId' => 'no-such-option',
        'publishedYear' => 'no-such-option',
        'publisher' => 'no-such-option',
        'publishingPlace' => 'no-such-option',
        'title' => 'no-such-option',
        'withinLibraryId' => 'no-such-option',
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'map' => 
        array (
          'adminNotes' => '',
          'adminTags' => '',
          'authorsText' => '',
          'bookEdition' => '',
          'callNumber' => '',
          'category' => '',
          'collection' => '',
          'inLanguage' => '',
          'isbn' => '',
          'keywords' => '',
          'newCallNumber' => '',
          'numberOfPages' => '',
          'publicNotes' => '',
          'publicationId' => '',
          'publishedYear' => '',
          'publisher' => '',
          'publishingPlace' => '',
          'title' => '',
          'withinLibraryId' => '',
        ),
        'submit' => NULL,
        'worksheet' => '',
      ),
      'messages' => 
      array (
        'worksheet' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'map' => 
        array (
          'adminNotes' => 'no-such-option',
          'adminTags' => 'no-such-option',
          'authorsText' => 'no-such-option',
          'bookEdition' => 'no-such-option',
          'callNumber' => 'no-such-option',
          'category' => 'no-such-option',
          'collection' => 'no-such-option',
          'inLanguage' => 'no-such-option',
          'isbn' => 'no-such-option',
          'keywords' => 'no-such-option',
          'newCallNumber' => 'no-such-option',
          'numberOfPages' => 'no-such-option',
          'publicNotes' => 'no-such-option',
          'publicationId' => 'no-such-option',
          'publishedYear' => 'no-such-option',
          'publisher' => 'no-such-option',
          'publishingPlace' => 'no-such-option',
          'title' => 'no-such-option',
          'withinLibraryId' => 'no-such-option',
        ),
        'submit' => NULL,
        'worksheet' => 'no-such-option',
      ),
      'messages' => 
      array (
        'map' => 
        array (
          'adminNotes' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'adminTags' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'authorsText' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'bookEdition' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'callNumber' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'category' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'collection' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'inLanguage' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'isbn' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'keywords' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'newCallNumber' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'numberOfPages' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'publicNotes' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'publicationId' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'publishedYear' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'publisher' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'publishingPlace' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'title' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
          'withinLibraryId' => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
        ),
      ),
    ),
  ),
  'App\\Books\\Import\\RunImportForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'digest' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'digest' => 
        array (
          'regexNotMatch' => 'The input does not match against pattern \'/\\A[0-9a-f]{40}\\z/\'',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'digest' => '<script>alert(1)</script>',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'digest' => 
        array (
          'regexNotMatch' => 'The input does not match against pattern \'/\\A[0-9a-f]{40}\\z/\'',
        ),
      ),
    ),
  ),
  'App\\Books\\LibraryDeleteForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'library_name' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'library_name' => 
        array (
          'notSame' => 'That is not this library\'s name. Nothing has been deleted.',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'library_name' => '<script>alert(1)</script>',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'library_name' => 
        array (
          'notSame' => 'That is not this library\'s name. Nothing has been deleted.',
        ),
      ),
    ),
  ),
  'App\\Books\\RefreshSortForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'Books\\Form\\BookForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'adminNotes' => 'Bäseline value & "quoted"
second line',
        'adminTags' => 
        array (
          0 => '',
        ),
        'authors' => 
        array (
          0 => '1a jornada de dirigentes Ypacaraí',
        ),
        'authorsText' => 'Bäseline value & "quoted" \'apostrophe\'',
        'bookEdition' => 'Bäseline value & "quoted" \'apostrophe\'',
        'callNumber' => 'Bäseline value & "quoted" \'apostrophe\'',
        'category' => 'A.T., SALMOS. BIBLIA',
        'collectionId' => '4',
        'inLanguage' => 
        array (
          0 => 'aa',
        ),
        'inactivationReason' => 'Bäseline value & "quoted" \'apostrophe\'',
        'isActive' => 1,
        'isbn' => 'Bäseline value & "quoted" \'apostrophe\'',
        'keywords' => 
        array (
          0 => 'biography',
        ),
        'libraryId' => 0,
        'newCallNumber' => 'Bäseline value & "quoted" \'apostrophe\'',
        'nextWithinLibraryId' => NULL,
        'numberOfPages' => 42,
        'publicNotes' => 'Bäseline value & "quoted"
second line',
        'publicationId' => 2154,
        'publishedYear' => 42,
        'publisher' => 'Agape Libros',
        'publishingPlace' => 'Bäseline value & "quoted" \'apostrophe\'',
        'submit' => NULL,
        'title' => 'Bäseline value & "quoted" \'apostrophe\'',
        'withinLibraryId' => 42,
      ),
      'messages' => 
      array (
        'isbn' => 
        array (
          'stringLengthTooLong' => 'The input is more than 30 characters long',
        ),
        'publishedYear' => 
        array (
          'notGreaterThanInclusive' => 'The input is not greater than or equal to \'1800\'',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'adminNotes' => 'alert(1)',
        'adminTags' => 
        array (
          0 => 'no-such-option',
        ),
        'authors' => 
        array (
          0 => 'no-such-option',
        ),
        'authorsText' => 'alert(1)',
        'bookEdition' => 'alert(1)',
        'callNumber' => 'alert(1)',
        'category' => 'no-such-option',
        'collectionId' => 'no-such-option',
        'inLanguage' => 
        array (
          0 => 'no-such-option',
        ),
        'inactivationReason' => 'alert(1)',
        'isActive' => 0,
        'isbn' => 'alert(1)',
        'keywords' => 
        array (
          0 => 'no-such-option',
        ),
        'libraryId' => 0,
        'newCallNumber' => 'alert(1)',
        'nextWithinLibraryId' => NULL,
        'numberOfPages' => NULL,
        'publicNotes' => 'alert(1)',
        'publicationId' => 0,
        'publishedYear' => NULL,
        'publisher' => 'no-such-option',
        'publishingPlace' => 'alert(1)',
        'submit' => NULL,
        'title' => 'alert(1)',
        'withinLibraryId' => NULL,
      ),
      'messages' => 
      array (
        'collectionId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'inLanguage' => 
        array (
          0 => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
        ),
        'publicationId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'withinLibraryId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
      ),
    ),
  ),
  'Books\\Form\\CheckinForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'submit' => NULL,
        'withinLibraryIds' => 
        array (
        ),
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'submit' => NULL,
        'withinLibraryIds' => 
        array (
        ),
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'Books\\Form\\CheckoutForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'adminNotes' => 'Bäseline value & "quoted"
second line',
        'personId' => '',
        'submit' => NULL,
        'withinLibraryIds' => 
        array (
        ),
      ),
      'messages' => 
      array (
        'personId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
        'withinLibraryIds' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'adminNotes' => 'alert(1)',
        'personId' => 'no-such-option',
        'submit' => NULL,
        'withinLibraryIds' => 
        array (
        ),
      ),
      'messages' => 
      array (
        'withinLibraryIds' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
      ),
    ),
  ),
  'Books\\Form\\CollectionForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'abbreviation' => 'Bäseline value & "quoted" \'apostrophe\'',
        'adminNotes' => 'Bäseline value & "quoted"
second line',
        'callNumberExplanation' => 'Bäseline value & "quoted"second line',
        'callNumberHelpText' => 'Bäseline value & "quoted" \'apostrophe\'',
        'callNumberRegex' => 'Bäseline value & "quoted" \'apostrophe\'',
        'defaultCheckoutTimePeriodInDays' => 42,
        'description' => 'Bäseline value & "quoted"second line',
        'enforceCallNumberRegex' => 1,
        'isActive' => 1,
        'labelLine1' => 'Bäseline value & "quoted" \'apostrophe\'',
        'labelLine2' => 'Bäseline value & "quoted" \'apostrophe\'',
        'labelLine3' => 'Bäseline value & "quoted" \'apostrophe\'',
        'libraryId' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'mainShowDisplay' => 'show-categories',
        'name' => 'Bäseline value & "quoted" \'apostrophe\'',
        'requireCallNumbers' => 1,
        'sortTextFormat' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'abbreviation' => 
        array (
          'stringLengthTooLong' => 'The input is more than 12 characters long',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'abbreviation' => 'alert(1)',
        'adminNotes' => 'alert(1)',
        'callNumberExplanation' => 'alert(1)',
        'callNumberHelpText' => 'alert(1)',
        'callNumberRegex' => 'alert(1)',
        'defaultCheckoutTimePeriodInDays' => NULL,
        'description' => 'alert(1)',
        'enforceCallNumberRegex' => 0,
        'isActive' => 0,
        'labelLine1' => 'alert(1)',
        'labelLine2' => 'alert(1)',
        'labelLine3' => 'alert(1)',
        'libraryId' => '<script>alert(1)</script>',
        'mainShowDisplay' => 'no-such-option',
        'name' => 'alert(1)',
        'requireCallNumbers' => 0,
        'sortTextFormat' => '<script>alert(1)</script>',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'mainShowDisplay' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
      ),
    ),
  ),
  'Books\\Form\\CompositionForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'chordProSpec' => 'Bäseline value & "quoted"
second line',
        'composersAll' => 
        array (
          0 => '',
        ),
        'copyrightContactEmail' => 'baseline@example.com',
        'copyrightInfo' => 'Bäseline value & "quoted"
second line',
        'country' => 'AF',
        'derivedFromCompositionId' => '372',
        'disambiguatingDescription' => 'Bäseline value & "quoted" \'apostrophe\'',
        'inLanguage' => 'aa',
        'lilyPondSpec' => 'Bäseline value & "quoted"
second line',
        'lyricistsAll' => 
        array (
          0 => '',
        ),
        'lyrics' => 'Bäseline value & "quoted"
second line',
        'name' => 'Bäseline value & "quoted" \'apostrophe\'',
        'openLicenseUrl' => 'https://creativecommons.org/licenses/by/4.0',
        'submit' => NULL,
        'tags' => 
        array (
          0 => 'Liturgia-Perdão',
        ),
        'url1' => 'https://example.com/baseline',
        'url1Label' => 'Album',
        'url2' => 'https://example.com/baseline',
        'url2Label' => 'Album',
        'url3' => 'https://example.com/baseline',
        'url3Label' => 'Album',
        'yearPublished' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
      ),
      'messages' => 
      array (
        'yearPublished' => 
        array (
          'regexNotMatch' => 'Please enter a valid date. Remember to add a `0` before single digit month and day numbers.',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'chordProSpec' => 'alert(1)',
        'composersAll' => 
        array (
          0 => 'no-such-option',
        ),
        'copyrightContactEmail' => 'not-an-email',
        'copyrightInfo' => 'alert(1)',
        'country' => 'NO-SUCH-OPTION',
        'derivedFromCompositionId' => 'no-such-option',
        'disambiguatingDescription' => 'alert(1)',
        'inLanguage' => 'no-such-option',
        'lilyPondSpec' => 'alert(1)',
        'lyricistsAll' => 
        array (
          0 => 'no-such-option',
        ),
        'lyrics' => 'alert(1)',
        'name' => 'alert(1)',
        'openLicenseUrl' => 'no-such-option',
        'submit' => NULL,
        'tags' => 
        array (
          0 => 'no-such-option',
        ),
        'url1' => 'not a url',
        'url1Label' => 'no-such-option',
        'url2' => 'not a url',
        'url2Label' => 'no-such-option',
        'url3' => 'not a url',
        'url3Label' => 'no-such-option',
        'yearPublished' => '<script>alert(1)</script>',
      ),
      'messages' => 
      array (
        'copyrightContactEmail' => 
        array (
          'emailAddressInvalidFormat' => 'The input is not a valid email address. Use the basic format local-part@hostname',
          'regexNotMatch' => 'The input does not match against pattern \'/^[a-zA-Z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/\'',
        ),
        'country' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'derivedFromCompositionId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'inLanguage' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'openLicenseUrl' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'url1' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
        'url2' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
        'url3' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
        'yearPublished' => 
        array (
          'regexNotMatch' => 'Please enter a valid date. Remember to add a `0` before single digit month and day numbers.',
        ),
      ),
    ),
  ),
  'Books\\Form\\CopyToMainCorpusForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'Books\\Form\\CreateNewEditionForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'Books\\Form\\DictionaryEntryForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'directTranslation' => 'Bäseline value & "quoted" \'apostrophe\'',
        'entry' => 'Bäseline <b>value</b> & "quoted"
second line',
        'isActive' => 1,
        'key' => 'Bäseline value & "quoted" \'apostrophe\'',
        'links' => 
        array (
          0 => 'die-welt-als-ersatzgott',
        ),
        'locale' => 'en_US',
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'directTranslation' => 'alert(1)',
        'entry' => '<script>alert(1)</script>',
        'isActive' => 0,
        'key' => 'alert(1)',
        'links' => 
        array (
          0 => 'no-such-option',
        ),
        'locale' => 'no-such-option',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'links' => 
        array (
          0 => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
        ),
        'locale' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
      ),
    ),
  ),
  'Books\\Form\\ImportForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'description' => 'Bäseline value & "quoted"second line',
        'file' => NULL,
        'isCompleteImport' => 1,
        'libraryId' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'name' => 'Bäseline value & "quoted" \'apostrophe\'',
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'description' => 'alert(1)',
        'file' => NULL,
        'isCompleteImport' => 0,
        'libraryId' => '<script>alert(1)</script>',
        'name' => 'alert(1)',
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'Books\\Form\\InactivationForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'inactivationReason' => 'Bäseline value & "quoted" \'apostrophe\'',
        'submit' => NULL,
        'withinLibraryIds' => 
        array (
        ),
      ),
      'messages' => 
      array (
        'withinLibraryIds' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'inactivationReason' => 'alert(1)',
        'submit' => NULL,
        'withinLibraryIds' => 
        array (
        ),
      ),
      'messages' => 
      array (
        'withinLibraryIds' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
      ),
    ),
  ),
  'Books\\Form\\LibraryForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'adminNotes' => 'Bäseline value & "quoted"
second line',
        'allowCollectionlessBooks' => 1,
        'barcodeText' => 'Bäseline value & "quoted" \'apostrophe\'',
        'callNumberExplanation' => 'Bäseline value & "quoted"
second line',
        'callNumberHelpText' => 'Bäseline value & "quoted" \'apostrophe\'',
        'callNumberPlaceholder' => 'Bäseline value & "quoted" \'apostrophe\'',
        'callNumberRegex' => 'Bäseline value & "quoted" \'apostrophe\'',
        'checkoutBooksRole' => 'guest',
        'checkoutPersonListKind' => 'all-borrowers',
        'contactEmail' => 'baseline@example.com',
        'contactPersonId' => '633',
        'createCheckoutsIfCheckingInANonCheckedOutBook' => 1,
        'defaultCheckoutPersonId' => '633',
        'defaultCheckoutTimePeriodInDays' => 42,
        'description' => 'Bäseline value & "quoted"second line',
        'enableCheckouts' => 1,
        'enforceCallNumberRegex' => 1,
        'filiationId' => '50',
        'isActive' => 1,
        'labelLine1' => 'Bäseline value & "quoted" \'apostrophe\'',
        'labelLine2' => 'Bäseline value & "quoted" \'apostrophe\'',
        'labelLine3' => 'Bäseline value & "quoted" \'apostrophe\'',
        'mainCollectionId' => '4',
        'mainShowDisplay' => 'show-categories',
        'name' => 'Bäseline value & "quoted" \'apostrophe\'',
        'requireCallNumbers' => 1,
        'sortTextFormat' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'submit' => NULL,
        'useCollections' => 1,
        'viewRole' => 'guest',
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'adminNotes' => 'alert(1)',
        'allowCollectionlessBooks' => 0,
        'barcodeText' => 'alert(1)',
        'callNumberExplanation' => 'alert(1)',
        'callNumberHelpText' => 'alert(1)',
        'callNumberPlaceholder' => 'alert(1)',
        'callNumberRegex' => 'alert(1)',
        'checkoutBooksRole' => 'no-such-option',
        'checkoutPersonListKind' => 'no-such-option',
        'contactEmail' => 'not-an-email',
        'contactPersonId' => 'no-such-option',
        'createCheckoutsIfCheckingInANonCheckedOutBook' => 0,
        'defaultCheckoutPersonId' => 'no-such-option',
        'defaultCheckoutTimePeriodInDays' => NULL,
        'description' => 'alert(1)',
        'enableCheckouts' => 0,
        'enforceCallNumberRegex' => 0,
        'filiationId' => 'no-such-option',
        'isActive' => 0,
        'labelLine1' => 'alert(1)',
        'labelLine2' => 'alert(1)',
        'labelLine3' => 'alert(1)',
        'mainCollectionId' => 'no-such-option',
        'mainShowDisplay' => 'no-such-option',
        'name' => 'alert(1)',
        'requireCallNumbers' => 0,
        'sortTextFormat' => '<script>alert(1)</script>',
        'submit' => NULL,
        'useCollections' => 0,
        'viewRole' => 'no-such-option',
      ),
      'messages' => 
      array (
        'checkoutBooksRole' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'checkoutPersonListKind' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'contactEmail' => 
        array (
          'regexNotMatch' => 'The input does not match against pattern \'/^[a-zA-Z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/\'',
        ),
        'contactPersonId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'defaultCheckoutPersonId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'defaultCheckoutTimePeriodInDays' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
        'filiationId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'mainCollectionId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'mainShowDisplay' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'viewRole' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
      ),
    ),
  ),
  'Books\\Form\\MassCheckoutFieldset' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'checkedOutOn' => '<DateTime>',
        'personId' => NULL,
        'withinLibraryIds' => 
        array (
        ),
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'checkedOutOn' => 'not-a-date',
        'personId' => 'no-such-option',
        'withinLibraryIds' => 
        array (
        ),
      ),
      'messages' => 
      array (
        'checkedOutOn' => 
        array (
          'dateNotParseable' => 'This does not look like a date. Please enter one like 2020-03-15.',
        ),
      ),
    ),
  ),
  'Books\\Form\\MassCheckoutForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'checkout' => 
        array (
          0 => 
          array (
            'checkedOutOn' => '<DateTime>',
            'personId' => NULL,
            'withinLibraryIds' => 
            array (
            ),
          ),
        ),
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'checkout' => 
        array (
          0 => 
          array (
            'checkedOutOn' => 'not-a-date',
            'personId' => 'no-such-option',
            'withinLibraryIds' => 
            array (
            ),
          ),
        ),
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'checkout' => 
        array (
          0 => 
          array (
            'checkedOutOn' => 
            array (
              'dateNotParseable' => 'This does not look like a date. Please enter one like 2020-03-15.',
            ),
          ),
        ),
      ),
    ),
  ),
  'Books\\Form\\PublicationForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'adminNotes' => 'Bäseline value & "quoted"
second line',
        'authorsAll' => 
        array (
          0 => '(verantw. Redaktionsteam)',
        ),
        'bookEdition' => 'Bäseline value & "quoted" \'apostrophe\'',
        'bookFormatType' => 'AudiobookFormat',
        'categoryId' => '2',
        'copyrightInfo' => 'Bäseline value & "quoted"
second line',
        'copyrightYear' => 42,
        'datePublishedText' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'description' => 'Bäseline value & "quoted"second line',
        'editionNotes' => 'Bäseline value & "quoted"
second line',
        'editorsAll' => 
        array (
          0 => '(verantw. Redaktionsteam)',
        ),
        'hasNoExplictEditionNumber' => 1,
        'hasNoISBN' => 1,
        'inLanguage' => 
        array (
          0 => 'aa',
        ),
        'isFormallyPublished' => 1,
        'isRevisedWithBookInHand' => 1,
        'isScientificWork' => 1,
        'isbn' => 'Bäseline value & "quoted" \'apostrophe\'',
        'keywords' => 
        array (
          0 => 'joseph kentenich',
        ),
        'mainPublicationId' => '2154',
        'numberOfPages' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'publicNotes' => 'Bäseline value & "quoted"
second line',
        'publisher' => '„Der Elsässer", Buchdruckerei und Zeitungsverlag Straßburg',
        'publishingPlace' => 'Bäseline value & "quoted" \'apostrophe\'',
        'resourceId' => 'publication_public',
        'submit' => NULL,
        'title' => 'Bäseline value & "quoted" \'apostrophe\'',
        'translatedFromPublicationId' => '2154',
        'translatorsAll' => 
        array (
          0 => '(verantw. Redaktionsteam)',
        ),
        'url1' => 'https://example.com/baseline',
        'url1Label' => 'Download',
        'url2' => 'https://example.com/baseline',
        'url2Label' => 'Download',
        'url3' => 'https://example.com/baseline',
        'url3Label' => 'Download',
        'volumeNumber' => 'Bäseline value & "quoted" \'apostrophe\'',
      ),
      'messages' => 
      array (
        'copyrightYear' => 
        array (
          'notGreaterThanInclusive' => 'The input is not greater than or equal to \'1800\'',
        ),
        'datePublishedText' => 
        array (
          'regexNotMatch' => 'Please enter a valid date. Remember to add a `0` before single digit month and day numbers.',
        ),
        'isbn' => 
        array (
          'stringLengthTooLong' => 'The input is more than 30 characters long',
        ),
        'numberOfPages' => 
        array (
          'regexNotMatch' => 'Please use a combination of roman numerals and/or numbers separated by `+`.',
        ),
        'volumeNumber' => 
        array (
          'stringLengthTooLong' => 'The input is more than 25 characters long',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'adminNotes' => 'alert(1)',
        'authorsAll' => 
        array (
          0 => 'no-such-option',
        ),
        'bookEdition' => 'alert(1)',
        'bookFormatType' => 'no-such-option',
        'categoryId' => 'no-such-option',
        'copyrightInfo' => 'alert(1)',
        'copyrightYear' => NULL,
        'datePublishedText' => '<script>alert(1)</script>',
        'description' => 'alert(1)',
        'editionNotes' => 'alert(1)',
        'editorsAll' => 
        array (
          0 => 'no-such-option',
        ),
        'hasNoExplictEditionNumber' => 0,
        'hasNoISBN' => 0,
        'inLanguage' => 
        array (
          0 => 'no-such-option',
        ),
        'isFormallyPublished' => 0,
        'isRevisedWithBookInHand' => 0,
        'isScientificWork' => 0,
        'isbn' => 'alert(1)',
        'keywords' => 
        array (
          0 => 'no-such-option',
        ),
        'mainPublicationId' => 'no-such-option',
        'numberOfPages' => '<script>alert(1)</script>',
        'publicNotes' => 'alert(1)',
        'publisher' => 'no-such-option',
        'publishingPlace' => 'alert(1)',
        'resourceId' => 'no-such-option',
        'submit' => NULL,
        'title' => 'alert(1)',
        'translatedFromPublicationId' => 'no-such-option',
        'translatorsAll' => 
        array (
          0 => 'no-such-option',
        ),
        'url1' => 'not a url',
        'url1Label' => 'no-such-option',
        'url2' => 'not a url',
        'url2Label' => 'no-such-option',
        'url3' => 'not a url',
        'url3Label' => 'no-such-option',
        'volumeNumber' => 'alert(1)',
      ),
      'messages' => 
      array (
        'bookFormatType' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'categoryId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'datePublishedText' => 
        array (
          'regexNotMatch' => 'Please enter a valid date. Remember to add a `0` before single digit month and day numbers.',
        ),
        'inLanguage' => 
        array (
          0 => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
        ),
        'mainPublicationId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'numberOfPages' => 
        array (
          'regexNotMatch' => 'Please use a combination of roman numerals and/or numbers separated by `+`.',
        ),
        'resourceId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'translatedFromPublicationId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'url1' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
        'url2' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
        'url3' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
      ),
    ),
  ),
  'Books\\Form\\PublicationsSearchForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'inLanguage' => 
        array (
          0 => 'aa',
        ),
        'includeDataSources' => '1',
        'search' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'showEditionsSeparately' => '1',
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'inLanguage' => 
        array (
          0 => 'no-such-option',
        ),
        'includeDataSources' => 'neither-checked-nor-unchecked',
        'search' => '<script>alert(1)</script>',
        'showEditionsSeparately' => 'neither-checked-nor-unchecked',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'inLanguage' => 
        array (
          0 => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
        ),
        'includeDataSources' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'showEditionsSeparately' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
      ),
    ),
  ),
  'Books\\Form\\SearchForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'category' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'collectionId' => NULL,
        'inLanguage' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'libraryId' => NULL,
        'search' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'inLanguage' => 
        array (
          'stringLengthTooLong' => 'The input is more than 2 characters long',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'category' => '<script>alert(1)</script>',
        'collectionId' => 'no-such-option',
        'inLanguage' => '<script>alert(1)</script>',
        'libraryId' => NULL,
        'search' => '<script>alert(1)</script>',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'collectionId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'inLanguage' => 
        array (
          'stringLengthTooLong' => 'The input is more than 2 characters long',
        ),
      ),
    ),
  ),
  'Books\\Form\\TextForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'inLanguage' => 'en',
        'isDraft' => 1,
        'kind' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'markdownText' => 'Bäseline value & "quoted"
second line',
        'submit' => NULL,
        'tags' => 
        array (
          0 => '',
        ),
        'title' => 'Bäseline value & "quoted" \'apostrophe\'',
      ),
      'messages' => 
      array (
        'kind' => 
        array (
          'notSame' => 'The two given tokens do not match',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'inLanguage' => 'no-such-option',
        'isDraft' => 0,
        'kind' => '<script>alert(1)</script>',
        'markdownText' => 'alert(1)',
        'submit' => NULL,
        'tags' => 
        array (
          0 => 'no-such-option',
        ),
        'title' => 'alert(1)',
      ),
      'messages' => 
      array (
        'inLanguage' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'kind' => 
        array (
          'notSame' => 'The two given tokens do not match',
        ),
      ),
    ),
  ),
  'Books\\Form\\TextSearchForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'inLanguage' => 'aa',
        'search' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'inLanguage' => 'no-such-option',
        'search' => '<script>alert(1)</script>',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'inLanguage' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
      ),
    ),
  ),
  'JTranslate\\Form\\DeletePhraseForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'cancel' => NULL,
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'cancel' => NULL,
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'de_DE' => 'Bäseline <b>value</b> & "quoted"
second line',
        'de_DEId' => 0,
        'en_US' => 'Bäseline <b>value</b> & "quoted"
second line',
        'en_USId' => 0,
        'es_ES' => 'Bäseline <b>value</b> & "quoted"
second line',
        'es_ESId' => 0,
        'it_IT' => 'Bäseline <b>value</b> & "quoted"
second line',
        'it_ITId' => 0,
        'phrase' => 'Bäseline <b>value</b> & "quoted"
second line',
        'phraseId' => 0,
        'pt_BR' => 'Bäseline <b>value</b> & "quoted"
second line',
        'pt_BRId' => 0,
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'phraseId' => 
        array (
          'noRecordFound' => 'Phrase not found in database',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'de_DE' => '<script>alert(1)</script>',
        'de_DEId' => 0,
        'en_US' => '<script>alert(1)</script>',
        'en_USId' => 0,
        'es_ES' => '<script>alert(1)</script>',
        'es_ESId' => 0,
        'it_IT' => '<script>alert(1)</script>',
        'it_ITId' => 0,
        'phrase' => '<script>alert(1)</script>',
        'phraseId' => 0,
        'pt_BR' => '<script>alert(1)</script>',
        'pt_BRId' => 0,
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'phraseId' => 
        array (
          'noRecordFound' => 'Phrase not found in database',
        ),
      ),
    ),
  ),
  'JUser\\Form\\CreateRoleForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'isDefault' => '1',
        'name' => 'Bäseline value & "quoted" \'apostrophe\'',
        'parentId' => '1',
        'submit' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
      ),
      'messages' => 
      array (
        'name' => 
        array (
          'regexNotMatch' => 'The input does not match against pattern \'/\\A[0-9A-Za-z_]+\\z/\'',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'isDefault' => 'neither-checked-nor-unchecked',
        'name' => 'alert(1)',
        'parentId' => 'no-such-option',
        'submit' => '<script>alert(1)</script>',
      ),
      'messages' => 
      array (
        'isDefault' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'name' => 
        array (
          'regexNotMatch' => 'The input does not match against pattern \'/\\A[0-9A-Za-z_]+\\z/\'',
        ),
        'parentId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
      ),
    ),
  ),
  'JUser\\Form\\DeleteUserForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'cancel' => NULL,
        'delete' => NULL,
        'userId' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
      ),
      'messages' => 
      array (
        'userId' => 
        array (
          'noRecordFound' => 'Assignment not found in database',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'cancel' => NULL,
        'delete' => NULL,
        'userId' => '<script>alert(1)</script>',
      ),
      'messages' => 
      array (
        'userId' => 
        array (
          'noRecordFound' => 'Assignment not found in database',
        ),
      ),
    ),
  ),
  'JUser\\Form\\EditUserForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'active' => '1',
        'displayName' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'email' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'emailVerified' => '1',
        'isMultiPersonUser' => '1',
        'personId' => 633,
        'rolesList' => 
        array (
          0 => '1',
        ),
        'submit' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'userId' => NULL,
        'username' => 'Bäseline value & "quoted" \'apostrophe\'',
      ),
      'messages' => 
      array (
        'displayName' => 
        array (
          'stringLengthTooLong' => 'The input is more than 40 characters long',
        ),
        'email' => 
        array (
          'emailAddressInvalidFormat' => 'The input is not a valid email address. Use the basic format local-part@hostname',
        ),
        'userId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
        'username' => 
        array (
          'regexNotMatch' => 'The input does not match against pattern \'/\\A[0-9A-Za-z-_.]+\\z/\'',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'active' => 'neither-checked-nor-unchecked',
        'displayName' => '<script>alert(1)</script>',
        'email' => '<script>alert(1)</script>',
        'emailVerified' => 'neither-checked-nor-unchecked',
        'isMultiPersonUser' => 'neither-checked-nor-unchecked',
        'personId' => NULL,
        'rolesList' => 
        array (
          0 => 'no-such-option',
        ),
        'submit' => '<script>alert(1)</script>',
        'userId' => NULL,
        'username' => 'alert(1)',
      ),
      'messages' => 
      array (
        'active' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'email' => 
        array (
          'emailAddressInvalidFormat' => 'The input is not a valid email address. Use the basic format local-part@hostname',
        ),
        'emailVerified' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'isMultiPersonUser' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'rolesList' => 
        array (
          0 => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
        ),
        'userId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
        'username' => 
        array (
          'regexNotMatch' => 'The input does not match against pattern \'/\\A[0-9A-Za-z-_.]+\\z/\'',
        ),
      ),
    ),
  ),
  'JUser\\Form\\IssueApiTokenForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'issue' => NULL,
        'label' => 'Bäseline value & "quoted" \'apostrophe\'',
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'issue' => NULL,
        'label' => 'alert(1)',
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'JUser\\Form\\LoginForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'email' => 'baseline@example.com',
        'redirect' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'submit' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'email' => 'not-an-email',
        'redirect' => '<script>alert(1)</script>',
        'submit' => '<script>alert(1)</script>',
      ),
      'messages' => 
      array (
        'email' => 
        array (
          'emailAddressInvalidFormat' => 'The input is not a valid email address. Use the basic format local-part@hostname',
          'regexNotMatch' => 'The input does not match against pattern \'/^[a-zA-Z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/\'',
        ),
      ),
    ),
  ),
  'JUser\\Form\\RevokeApiTokenForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'revoke' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'revoke' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'associationCountry' => 'AR',
        'associationKind' => 'sch-diocesan-pilgrim-mother',
        'clear' => NULL,
        'onlyMainRoles' => 1,
        'personName' => 'Bäseline value & "quoted" \'apostrophe\'',
        'roleTitle' => 
        array (
          0 => '(General or main) Secretary',
        ),
        'search' => 'Bäseline value & "quoted" \'apostrophe\'',
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'associationCountry' => 'no-such-option',
        'associationKind' => 'no-such-option',
        'clear' => NULL,
        'onlyMainRoles' => 0,
        'personName' => 'alert(1)',
        'roleTitle' => 
        array (
          0 => 'no-such-option',
        ),
        'search' => 'alert(1)',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'associationCountry' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
          'stringLengthTooLong' => 'The input is more than 2 characters long',
        ),
        'associationKind' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'roleTitle' => 
        array (
          0 => 
          array (
            'notInArray' => 'The input was not found in the haystack',
          ),
        ),
      ),
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'associationId' => 569,
        'delete' => NULL,
        'endDate' => '<DateTime>',
        'endDatePrecision' => 'day',
        'personId' => 633,
        'roleId' => NULL,
        'startDate' => '<DateTime>',
        'startDatePrecision' => 'day',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'roleId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'associationId' => NULL,
        'delete' => NULL,
        'endDate' => 'not-a-date',
        'endDatePrecision' => 'no-such-option',
        'personId' => NULL,
        'roleId' => NULL,
        'startDate' => 'not-a-date',
        'startDatePrecision' => 'no-such-option',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'associationId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
        'endDate' => 
        array (
          'dateInvalidDate' => 'The input does not appear to be a valid date',
          'dateNotParseable' => 'This does not look like a date. Please enter one like 2020-03-15.',
        ),
        'endDatePrecision' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'personId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
        'roleId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
        'startDate' => 
        array (
          'dateInvalidDate' => 'The input does not appear to be a valid date',
          'dateNotParseable' => 'This does not look like a date. Please enter one like 2020-03-15.',
        ),
        'startDatePrecision' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
      ),
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'adminNotes' => 'Bäseline value & "quoted"
second line',
        'associationId' => 0,
        'cityState' => 'Bäseline value & "quoted" \'apostrophe\'',
        'country' => 'AF',
        'email' => 'baseline@example.com',
        'eventsHuman' => 'Bäseline <b>value</b> & "quoted"
second line',
        'facebookUrl' => 'https://example.com/baseline',
        'foundationDate' => '<DateTime>',
        'foundationDatePrecision' => 'day',
        'geoPoint' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'googlePlaceId' => 'Bäseline value & "quoted" \'apostrophe\'',
        'instagramUser' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'internalName' => 'Bäseline value & "quoted" \'apostrophe\'',
        'isActive' => 1,
        'isAuthor' => 1,
        'isInternalNameTranslateable' => 1,
        'isLifeCommunity' => '1',
        'isNameTranslateable' => 1,
        'kind' => 'sch-diocesan-pilgrim-mother',
        'name' => 'Bäseline value & "quoted" \'apostrophe\'',
        'openingHoursHuman' => 'Bäseline <b>value</b> & "quoted"
second line',
        'openingHoursSpecificationJson' => 'Bäseline <b>value</b> & "quoted"
second line',
        'overrideNameFormat' => 1,
        'parentId' => 569,
        'phone1' => '+1 202 555 0100',
        'phone1Label' => 'Alternate cell phone',
        'phone2' => '+1 202 555 0100',
        'phone2Label' => 'Alternate cell phone',
        'phone3' => '+1 202 555 0100',
        'phone3Label' => 'Alternate cell phone',
        'publicNotes' => 'Bäseline value & "quoted"
second line',
        'street1' => 'Bäseline value & "quoted" \'apostrophe\'',
        'street2' => 'Bäseline value & "quoted" \'apostrophe\'',
        'submit' => NULL,
        'timeZoneId' => 'Pacific/Midway',
        'twitterUser' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'url1' => 'https://example.com/baseline',
        'url1Label' => 'Blog',
        'url2' => 'https://example.com/baseline',
        'url2Label' => 'Blog',
        'url3' => 'https://example.com/baseline',
        'url3Label' => 'Blog',
        'zip' => 'Bäseline value & "quoted" \'apostrophe\'',
      ),
      'messages' => 
      array (
        'geoPoint' => 
        array (
          'gpsPointIncompleteCoordinate' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\' did not provided a complete Coordinate',
        ),
        'instagramUser' => 
        array (
          'regexNotMatch' => 'Instagram user names should begin with a letter, contain only letters, numbers, \'.\', or \'_\' and be between 1 and 30 characters long.',
        ),
        'openingHoursSpecificationJson' => 
        array (
          'invalidJson' => 'The input could not be parsed as valid JSON',
        ),
        'twitterUser' => 
        array (
          'regexNotMatch' => 'Twitter user names should contain only letters, numbers, or \'_\' and be between 1 and 15 characters long.',
        ),
        'zip' => 
        array (
          'stringLengthTooLong' => 'The input is more than 15 characters long',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'adminNotes' => 'alert(1)',
        'associationId' => 0,
        'cityState' => 'alert(1)',
        'country' => 'NO-SUCH-OPTION',
        'email' => 'not-an-email',
        'eventsHuman' => '<script>alert(1)</script>',
        'facebookUrl' => 'not a url',
        'foundationDate' => 'not-a-date',
        'foundationDatePrecision' => 'no-such-option',
        'geoPoint' => '<script>alert(1)</script>',
        'googlePlaceId' => 'alert(1)',
        'instagramUser' => '<script>alert(1)</script>',
        'internalName' => 'alert(1)',
        'isActive' => 0,
        'isAuthor' => 0,
        'isInternalNameTranslateable' => 0,
        'isLifeCommunity' => 'neither-checked-nor-unchecked',
        'isNameTranslateable' => 0,
        'kind' => 'no-such-option',
        'name' => 'alert(1)',
        'openingHoursHuman' => '<script>alert(1)</script>',
        'openingHoursSpecificationJson' => '<script>alert(1)</script>',
        'overrideNameFormat' => 0,
        'parentId' => NULL,
        'phone1' => 'not a phone number',
        'phone1Label' => 'no-such-option',
        'phone2' => 'not a phone number',
        'phone2Label' => 'no-such-option',
        'phone3' => 'not a phone number',
        'phone3Label' => 'no-such-option',
        'publicNotes' => 'alert(1)',
        'street1' => 'alert(1)',
        'street2' => 'alert(1)',
        'submit' => NULL,
        'timeZoneId' => 'no-such-option',
        'twitterUser' => '<script>alert(1)</script>',
        'url1' => 'not a url',
        'url1Label' => 'no-such-option',
        'url2' => 'not a url',
        'url2Label' => 'no-such-option',
        'url3' => 'not a url',
        'url3Label' => 'no-such-option',
        'zip' => 'alert(1)',
      ),
      'messages' => 
      array (
        'country' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
          'stringLengthTooLong' => 'The input is more than 6 characters long',
        ),
        'email' => 
        array (
          'emailAddressInvalidFormat' => 'The input is not a valid email address. Use the basic format local-part@hostname',
          'regexNotMatch' => 'The input does not match against pattern \'/^[a-zA-Z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/\'',
        ),
        'facebookUrl' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
        'foundationDate' => 
        array (
          'dateInvalidDate' => 'The input does not appear to be a valid date',
          'dateNotParseable' => 'This does not look like a date. Please enter one like 2020-03-15.',
        ),
        'foundationDatePrecision' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'geoPoint' => 
        array (
          'gpsPointIncompleteCoordinate' => '<script>alert(1)</script> did not provided a complete Coordinate',
        ),
        'instagramUser' => 
        array (
          'regexNotMatch' => 'Instagram user names should begin with a letter, contain only letters, numbers, \'.\', or \'_\' and be between 1 and 30 characters long.',
        ),
        'isLifeCommunity' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'kind' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'openingHoursSpecificationJson' => 
        array (
          'invalidJson' => 'The input could not be parsed as valid JSON',
        ),
        'phone1' => 
        array (
          'regexNotMatch' => 'Please begin with \'+\' and the country code, and use only numbers, dash, space or parenthesis. \' ext. ##\' may be added for extensions.',
        ),
        'phone2' => 
        array (
          'regexNotMatch' => 'Please begin with \'+\' and the country code, and use only numbers, dash, space or parenthesis. \' ext. ##\' may be added for extensions.',
        ),
        'phone3' => 
        array (
          'regexNotMatch' => 'Please begin with \'+\' and the country code, and use only numbers, dash, space or parenthesis. \' ext. ##\' may be added for extensions.',
        ),
        'timeZoneId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'twitterUser' => 
        array (
          'regexNotMatch' => 'Twitter user names should contain only letters, numbers, or \'_\' and be between 1 and 15 characters long.',
        ),
        'url1' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
        'url2' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
        'url3' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
      ),
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'associationId' => 569,
        'delete' => NULL,
        'endDate' => '<DateTime>',
        'endDatePrecision' => 'day',
        'personId' => 633,
        'roleId' => NULL,
        'startDate' => '<DateTime>',
        'startDatePrecision' => 'day',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'roleId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'associationId' => NULL,
        'delete' => NULL,
        'endDate' => 'not-a-date',
        'endDatePrecision' => 'no-such-option',
        'personId' => NULL,
        'roleId' => NULL,
        'startDate' => 'not-a-date',
        'startDatePrecision' => 'no-such-option',
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'associationId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
        'endDate' => 
        array (
          'dateInvalidDate' => 'The input does not appear to be a valid date',
          'dateNotParseable' => 'This does not look like a date. Please enter one like 2020-03-15.',
        ),
        'endDatePrecision' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'personId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
        'roleId' => 
        array (
          'isEmpty' => 'Value is required and can\'t be empty',
        ),
        'startDate' => 
        array (
          'dateInvalidDate' => 'The input does not appear to be a valid date',
          'dateNotParseable' => 'This does not look like a date. Please enter one like 2020-03-15.',
        ),
        'startDatePrecision' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
      ),
    ),
  ),
  'Schoenstatt\\Form\\ImportFatherForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'personId' => NULL,
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'personId' => NULL,
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'Schoenstatt\\Form\\PersonForm' => 
  array (
    'accepted' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'adminNotes' => 'Bäseline value & "quoted"
second line',
        'adminTags' => 
        array (
          0 => '',
        ),
        'automaticTitle' => '1',
        'birthDate' => '<DateTime>',
        'birthDatePrecision' => 'day',
        'bishopDate' => '<DateTime>',
        'bishopDatePrecision' => 'day',
        'cellPhone' => '+1 202 555 0100',
        'cellPhoneHasWhatsApp' => 1,
        'contactNotes' => 'Bäseline value & "quoted"
second line',
        'country' => 'AF',
        'deathDate' => '<DateTime>',
        'deathDatePrecision' => 'day',
        'delete' => NULL,
        'email' => 'baseline@example.com',
        'email2' => 'baseline@example.com',
        'facebookUrl' => 'https://example.com/baseline',
        'firstName' => 'Bäseline value & "quoted" \'apostrophe\'',
        'instagramUser' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'isAuthor' => 1,
        'isBorrower' => 1,
        'lastName' => 'Bäseline value & "quoted" \'apostrophe\'',
        'lifeCommunity' => '81',
        'manualTitle' => 'Bäseline value & "quoted" \'apostrophe\'',
        'nameDay' => '<DateTime>',
        'personTags' => 
        array (
          0 => 'bishop',
        ),
        'phone1' => '+1 202 555 0100',
        'phone1Label' => 'Alternate cell phone',
        'phone2' => '+1 202 555 0100',
        'phone2Label' => 'Alternate cell phone',
        'phone3' => '+1 202 555 0100',
        'phone3Label' => 'Alternate cell phone',
        'postCityState' => 'Bäseline value & "quoted" \'apostrophe\'',
        'postCountry' => 'AF',
        'postStreet1' => 'Bäseline value & "quoted" \'apostrophe\'',
        'postStreet2' => 'Bäseline value & "quoted" \'apostrophe\'',
        'postZip' => 'Bäseline value & "quoted" \'apostrophe\'',
        'priestDate' => '<DateTime>',
        'priestDatePrecision' => 'day',
        'publicNotes' => 'Bäseline value & "quoted"
second line',
        'skypeUser' => 'Bäseline value & "quoted" \'apostrophe\'',
        'slackUser' => 'bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'spousePersonId' => 633,
        'submit' => NULL,
        'twitterUser' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'url1' => 'https://example.com/baseline',
        'url1Label' => 'Blog',
        'url2' => 'https://example.com/baseline',
        'url2Label' => 'Blog',
        'url3' => 'https://example.com/baseline',
        'url3Label' => 'Blog',
      ),
      'messages' => 
      array (
        'instagramUser' => 
        array (
          'regexNotMatch' => 'Instagram user names should begin with a letter, contain only letters, numbers, \'.\', or \'_\' and be between 1 and 30 characters long.',
        ),
        'postZip' => 
        array (
          'stringLengthTooLong' => 'The input is more than 15 characters long',
        ),
        'skypeUser' => 
        array (
          'regexNotMatch' => 'Skype user names should begin with a letter, contain only letters, numbers, \',\', \'.\', \'-\', or \'_\' and be between 6 and 32 characters long.',
        ),
        'slackUser' => 
        array (
          'regexNotMatch' => 'Slack user names should begin with a letter or number, and contain only letters, numbers, \'.\', \'-\', or \'_\'.',
        ),
        'twitterUser' => 
        array (
          'regexNotMatch' => 'Twitter user names should contain only letters, numbers, or \'_\' and be between 1 and 15 characters long.',
        ),
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'adminNotes' => 'alert(1)',
        'adminTags' => 
        array (
          0 => 'no-such-option',
        ),
        'automaticTitle' => 'neither-checked-nor-unchecked',
        'birthDate' => 'not-a-date',
        'birthDatePrecision' => 'no-such-option',
        'bishopDate' => 'not-a-date',
        'bishopDatePrecision' => 'no-such-option',
        'cellPhone' => 'not a phone number',
        'cellPhoneHasWhatsApp' => 0,
        'contactNotes' => 'alert(1)',
        'country' => 'NO-SUCH-OPTION',
        'deathDate' => 'not-a-date',
        'deathDatePrecision' => 'no-such-option',
        'delete' => NULL,
        'email' => 'not-an-email',
        'email2' => 'not-an-email',
        'facebookUrl' => 'not a url',
        'firstName' => 'alert(1)',
        'instagramUser' => '<script>alert(1)</script>',
        'isAuthor' => 0,
        'isBorrower' => 0,
        'lastName' => 'alert(1)',
        'lifeCommunity' => 'no-such-option',
        'manualTitle' => 'alert(1)',
        'nameDay' => 'not-a-year-99-99',
        'personTags' => 
        array (
          0 => 'no-such-option',
        ),
        'phone1' => 'not a phone number',
        'phone1Label' => 'no-such-option',
        'phone2' => 'not a phone number',
        'phone2Label' => 'no-such-option',
        'phone3' => 'not a phone number',
        'phone3Label' => 'no-such-option',
        'postCityState' => 'alert(1)',
        'postCountry' => 'no-such-option',
        'postStreet1' => 'alert(1)',
        'postStreet2' => 'alert(1)',
        'postZip' => 'alert(1)',
        'priestDate' => 'not-a-date',
        'priestDatePrecision' => 'no-such-option',
        'publicNotes' => 'alert(1)',
        'skypeUser' => 'alert(1)',
        'slackUser' => '<script>alert(1)</script>',
        'spousePersonId' => NULL,
        'submit' => NULL,
        'twitterUser' => '<script>alert(1)</script>',
        'url1' => 'not a url',
        'url1Label' => 'no-such-option',
        'url2' => 'not a url',
        'url2Label' => 'no-such-option',
        'url3' => 'not a url',
        'url3Label' => 'no-such-option',
      ),
      'messages' => 
      array (
        'automaticTitle' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'birthDate' => 
        array (
          'dateInvalidDate' => 'The input does not appear to be a valid date',
          'dateNotParseable' => 'This does not look like a date. Please enter one like 2020-03-15.',
        ),
        'birthDatePrecision' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'bishopDate' => 
        array (
          'dateInvalidDate' => 'The input does not appear to be a valid date',
          'dateNotParseable' => 'This does not look like a date. Please enter one like 2020-03-15.',
        ),
        'bishopDatePrecision' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'cellPhone' => 
        array (
          'regexNotMatch' => 'Please begin with \'+\' and the country code, and use only numbers, dash, space or parenthesis. \' ext. ##\' may be added for extensions.',
        ),
        'country' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
          'stringLengthTooLong' => 'The input is more than 2 characters long',
        ),
        'deathDate' => 
        array (
          'dateInvalidDate' => 'The input does not appear to be a valid date',
          'dateNotParseable' => 'This does not look like a date. Please enter one like 2020-03-15.',
        ),
        'deathDatePrecision' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'email' => 
        array (
          'emailAddressInvalidFormat' => 'The input is not a valid email address. Use the basic format local-part@hostname',
          'regexNotMatch' => 'The input does not match against pattern \'/^[a-zA-Z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/\'',
        ),
        'email2' => 
        array (
          'emailAddressInvalidFormat' => 'The input is not a valid email address. Use the basic format local-part@hostname',
          'regexNotMatch' => 'The input does not match against pattern \'/^[a-zA-Z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/\'',
        ),
        'facebookUrl' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
        'instagramUser' => 
        array (
          'regexNotMatch' => 'Instagram user names should begin with a letter, contain only letters, numbers, \'.\', or \'_\' and be between 1 and 30 characters long.',
        ),
        'lifeCommunity' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'nameDay' => 
        array (
          'dateInvalidDate' => 'The input does not appear to be a valid date',
        ),
        'phone1' => 
        array (
          'regexNotMatch' => 'Please begin with \'+\' and the country code, and use only numbers, dash, space or parenthesis. \' ext. ##\' may be added for extensions.',
        ),
        'phone2' => 
        array (
          'regexNotMatch' => 'Please begin with \'+\' and the country code, and use only numbers, dash, space or parenthesis. \' ext. ##\' may be added for extensions.',
        ),
        'phone3' => 
        array (
          'regexNotMatch' => 'Please begin with \'+\' and the country code, and use only numbers, dash, space or parenthesis. \' ext. ##\' may be added for extensions.',
        ),
        'postCountry' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'priestDate' => 
        array (
          'dateInvalidDate' => 'The input does not appear to be a valid date',
          'dateNotParseable' => 'This does not look like a date. Please enter one like 2020-03-15.',
        ),
        'priestDatePrecision' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
        'skypeUser' => 
        array (
          'regexNotMatch' => 'Skype user names should begin with a letter, contain only letters, numbers, \',\', \'.\', \'-\', or \'_\' and be between 6 and 32 characters long.',
        ),
        'slackUser' => 
        array (
          'regexNotMatch' => 'Slack user names should begin with a letter or number, and contain only letters, numbers, \'.\', \'-\', or \'_\'.',
        ),
        'twitterUser' => 
        array (
          'regexNotMatch' => 'Twitter user names should contain only letters, numbers, or \'_\' and be between 1 and 15 characters long.',
        ),
        'url1' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
        'url2' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
        'url3' => 
        array (
          'notUri' => 'The input does not appear to be a valid Uri',
        ),
      ),
    ),
  ),
  'Schoenstatt\\Form\\RoleForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'associationId' => '569',
        'isActive' => 1,
        'isMainContact' => 1,
        'isMainRole' => 1,
        'isSinglePosition' => 1,
        'roleTitle' => '(General or main) Secretary',
        'sort' => 42,
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => false,
      'values' => 
      array (
        'associationId' => 'no-such-option',
        'isActive' => 0,
        'isMainContact' => 0,
        'isMainRole' => 0,
        'isSinglePosition' => 0,
        'roleTitle' => 'no-such-option',
        'sort' => NULL,
        'submit' => NULL,
      ),
      'messages' => 
      array (
        'associationId' => 
        array (
          'notInArray' => 'The input was not found in the haystack',
        ),
      ),
    ),
  ),
  'Schoenstatt\\Form\\SearchForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'exMembers' => true,
        'search' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'showPhotos' => true,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'exMembers' => true,
        'search' => '<script>alert(1)</script>',
        'showPhotos' => true,
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'SionModel\\Form\\CommentForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'comment' => 'Bäseline value & "quoted"
second line',
        'redirect' => 'Bäseline <b>value</b> & "quoted" \'apostrophe\'',
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'comment' => 'alert(1)',
        'redirect' => '<script>alert(1)</script>',
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'SionModel\\Form\\DeleteEntityForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'cancel' => NULL,
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
        'cancel' => NULL,
        'submit' => NULL,
      ),
      'messages' => 
      array (
      ),
    ),
  ),
  'SionModel\\Form\\SionForm' => 
  array (
    'accepted' => 
    array (
      'valid' => true,
      'values' => 
      array (
      ),
      'messages' => 
      array (
      ),
    ),
    'rejected' => 
    array (
      'valid' => true,
      'values' => 
      array (
      ),
      'messages' => 
      array (
      ),
    ),
  ),
);
