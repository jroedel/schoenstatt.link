<?php

/**
 * What every element in the application answers, recorded from `Laminas\Form\Element\*`.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php test/Element/regenerate-element-surface.php
 *
 * This is the contract step 6's element model has to meet. See
 * `SchoenstattTest\Element\ElementSurface` for what is recorded, what is normalised and
 * why value options are a digest rather than a list.
 *
 * @return array<string, array<string, mixed>>
 */

declare(strict_types=1);

return array (
  'App\\Books\\Import\\ImportMappingFieldset::adminNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'adminNotes',
    'label' => 'Admin notes',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'adminNotes',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Visible only to library administrators. Markdown.',
      'label' => 'Admin notes',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::adminTags' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'adminTags',
    'label' => 'Admin tags',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'adminTags',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Administrator-only tags, separated by a vertical bar.',
      'label' => 'Admin tags',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::authorsText' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'authorsText',
    'label' => 'Author',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'authorsText',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'One name, or several separated by a vertical bar: Kentenich, Josef | Schlickmann, Anna.',
      'label' => 'Author',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::bookEdition' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'bookEdition',
    'label' => 'Edition',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'bookEdition',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Which edition or printing this copy is — the field that tells two copies of the same work apart when nothing else does.',
      'label' => 'Edition',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::callNumber' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'callNumber',
    'label' => 'Call number',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'callNumber',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Where the copy stands on the shelf, as printed on its label.',
      'label' => 'Call number',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::category' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'category',
    'label' => 'Category',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'category',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'One classification for the copy. For several subject terms use Keywords instead.',
      'label' => 'Category',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::collection' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'collection',
    'label' => 'Collection',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'collection',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'The collection within this library. A name that does not exist yet is created by the import. Ignored entirely by libraries that do not use collections.',
      'label' => 'Collection',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::inLanguage' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'inLanguage',
    'label' => 'Language',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'inLanguage',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Two-letter code: en, es, de, pt, it, fr, la, pl.',
      'label' => 'Language',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::isbn' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'isbn',
    'label' => 'ISBN',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'isbn',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => '',
      'label' => 'ISBN',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::keywords' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'keywords',
    'label' => 'Keywords',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'keywords',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Subject terms shown to readers, separated by a vertical bar.',
      'label' => 'Keywords',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::newCallNumber' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'newCallNumber',
    'label' => 'New call number',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'newCallNumber',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Only when the printed call number should change. It goes onto the next labels printed.',
      'label' => 'New call number',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::numberOfPages' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'numberOfPages',
    'label' => 'Pages',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'numberOfPages',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'A whole number.',
      'label' => 'Pages',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::publicNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publicNotes',
    'label' => 'Public notes',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'publicNotes',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Shown to readers. Markdown.',
      'label' => 'Public notes',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::publicationId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publicationId',
    'label' => 'Literature ID',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'publicationId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Links this copy to a record in the site-wide literature catalogue. When it names an existing record, that record supplies the title, author, year, publisher, place, pages, language and ISBN — whatever those columns say in the spreadsheet is ignored for that row.',
      'label' => 'Literature ID',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::publishedYear' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publishedYear',
    'label' => 'Year published',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'publishedYear',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'A four-digit year.',
      'label' => 'Year published',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::publisher' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publisher',
    'label' => 'Publisher',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'publisher',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => '',
      'label' => 'Publisher',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::publishingPlace' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publishingPlace',
    'label' => 'Place of publication',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'publishingPlace',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => '',
      'label' => 'Place of publication',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::title' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'title',
    'label' => 'Title *',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'title',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Required, unless the row carries a Literature ID that names an existing record.',
      'label' => 'Title *',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingFieldset::withinLibraryId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'withinLibraryId',
    'label' => 'Barcode *',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'withinLibraryId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'The library\'s own number for this copy. Required, must be a whole number, and must be unique within the library — it is what an import matches a row to an existing book by.',
      'label' => 'Barcode *',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map' => 
  array (
    'class' => 'App\\Books\\Import\\ImportMappingFieldset',
    'name' => 'map',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'map',
    ),
    'options' => 
    array (
    ),
    'fieldset' => true,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/adminNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'adminNotes',
    'label' => 'Admin notes',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'adminNotes',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Visible only to library administrators. Markdown.',
      'label' => 'Admin notes',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/adminTags' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'adminTags',
    'label' => 'Admin tags',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'adminTags',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Administrator-only tags, separated by a vertical bar.',
      'label' => 'Admin tags',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/authorsText' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'authorsText',
    'label' => 'Author',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'authorsText',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'One name, or several separated by a vertical bar: Kentenich, Josef | Schlickmann, Anna.',
      'label' => 'Author',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/bookEdition' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'bookEdition',
    'label' => 'Edition',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'bookEdition',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Which edition or printing this copy is — the field that tells two copies of the same work apart when nothing else does.',
      'label' => 'Edition',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/callNumber' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'callNumber',
    'label' => 'Call number',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'callNumber',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Where the copy stands on the shelf, as printed on its label.',
      'label' => 'Call number',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/category' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'category',
    'label' => 'Category',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'category',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'One classification for the copy. For several subject terms use Keywords instead.',
      'label' => 'Category',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/collection' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'collection',
    'label' => 'Collection',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'collection',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'The collection within this library. A name that does not exist yet is created by the import. Ignored entirely by libraries that do not use collections.',
      'label' => 'Collection',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/inLanguage' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'inLanguage',
    'label' => 'Language',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'inLanguage',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Two-letter code: en, es, de, pt, it, fr, la, pl.',
      'label' => 'Language',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/isbn' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'isbn',
    'label' => 'ISBN',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'isbn',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => '',
      'label' => 'ISBN',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/keywords' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'keywords',
    'label' => 'Keywords',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'keywords',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Subject terms shown to readers, separated by a vertical bar.',
      'label' => 'Keywords',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/newCallNumber' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'newCallNumber',
    'label' => 'New call number',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'newCallNumber',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Only when the printed call number should change. It goes onto the next labels printed.',
      'label' => 'New call number',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/numberOfPages' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'numberOfPages',
    'label' => 'Pages',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'numberOfPages',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'A whole number.',
      'label' => 'Pages',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/publicNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publicNotes',
    'label' => 'Public notes',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'publicNotes',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Shown to readers. Markdown.',
      'label' => 'Public notes',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/publicationId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publicationId',
    'label' => 'Literature ID',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'publicationId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Links this copy to a record in the site-wide literature catalogue. When it names an existing record, that record supplies the title, author, year, publisher, place, pages, language and ISBN — whatever those columns say in the spreadsheet is ignored for that row.',
      'label' => 'Literature ID',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/publishedYear' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publishedYear',
    'label' => 'Year published',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'publishedYear',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'A four-digit year.',
      'label' => 'Year published',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/publisher' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publisher',
    'label' => 'Publisher',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'publisher',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => '',
      'label' => 'Publisher',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/publishingPlace' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publishingPlace',
    'label' => 'Place of publication',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'publishingPlace',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => '',
      'label' => 'Place of publication',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/title' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'title',
    'label' => 'Title *',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'title',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'Required, unless the row carries a Literature ID that names an existing record.',
      'label' => 'Title *',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::map/withinLibraryId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'withinLibraryId',
    'label' => 'Barcode *',
    'value' => '',
    'attributes' => 
    array (
      'name' => 'withinLibraryId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'The library\'s own number for this copy. Required, must be a whole number, and must be unique within the library — it is what an import matches a row to an existing book by.',
      'label' => 'Barcode *',
      'value_options' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 1,
      'digest' => 'b1cea609d09438c545675971523c7f48',
      'first' => 
      array (
        '' => '— not imported —',
      ),
      'last' => 
      array (
        '' => '— not imported —',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\ImportMappingForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Save and preview',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::worksheet' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'worksheet',
    'label' => 'Worksheet',
    'value' => 'fuzz',
    'attributes' => 
    array (
      'name' => 'worksheet',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'This file has several sheets. Choose the one holding the books.',
      'label' => 'Worksheet',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => NULL,
  ),
  'App\\Books\\Import\\RunImportForm::digest' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'digest',
    'label' => NULL,
    'value' => 'fuzz',
    'attributes' => 
    array (
      'name' => 'digest',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'App\\Books\\Import\\RunImportForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'App\\Books\\Import\\RunImportForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Run this import',
    'attributes' => 
    array (
      'class' => 'btn-warning',
      'id' => 'run-import',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'App\\Books\\LibraryDeleteForm::library_name' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'library_name',
    'label' => 'Type the library name to confirm',
    'value' => NULL,
    'attributes' => 
    array (
      'autocomplete' => 'off',
      'class' => 'form-control',
      'id' => 'library_name',
      'name' => 'library_name',
      'required' => 'required',
      'spellcheck' => 'false',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Type the library name to confirm',
    ),
  ),
  'App\\Books\\LibraryDeleteForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'App\\Books\\LibraryDeleteForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Delete this library permanently',
    'attributes' => 
    array (
      'class' => 'btn btn-danger',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'App\\Books\\RefreshSortForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'App\\Books\\RefreshSortForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Refresh all library sort text',
    'attributes' => 
    array (
      'class' => 'btn-warning',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::adminNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'adminNotes',
    'label' => 'Admin notes',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'adminNotes',
      'required' => false,
      'rows' => 8,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Admin notes',
    ),
  ),
  'Books\\Form\\BookForm::adminTags' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'adminTags',
    'label' => 'Admin tags',
    'value' => NULL,
    'attributes' => 
    array (
      'multiple' => true,
      'name' => 'adminTags',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Admin tags',
      'placeholder' => 'Select tags or type new ones...',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\BookForm::authors' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'authors',
    'label' => 'Author(s)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'multiple' => true,
      'name' => 'authors',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Author(s)',
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 1890,
      'digest' => '33ef54305855fe306857859594900f56',
      'first' => 
      array (
        '' => '',
        '1a jornada de dirigentes Ypacaraí' => '1a jornada de dirigentes Ypacaraí',
        'A. Cabré' => 'A. Cabré',
      ),
      'last' => 
      array (
        'Jesús Alvarez' => 'Jesús Alvarez',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\BookForm::authorsText' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'authorsText',
    'label' => 'Author',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'name' => 'authorsText',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'One name, or several separated by a vertical bar.',
      'label' => 'Author',
    ),
  ),
  'Books\\Form\\BookForm::bookEdition' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'bookEdition',
    'label' => 'Edition',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'bookEdition',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'Which edition or printing this copy is — what distinguishes it from another copy of the same work.',
      'label' => 'Edition',
    ),
  ),
  'Books\\Form\\BookForm::callNumber' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'callNumber',
    'label' => 'Call number',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'callNumber',
      'required' => true,
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'Where this copy stands on the shelf, as printed on its label.',
      'label' => 'Call number',
    ),
  ),
  'Books\\Form\\BookForm::category' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'category',
    'label' => 'Category',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'name' => 'category',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'help-block' => 'One classification for this copy. For several subject terms use Keywords.',
      'label' => 'Category',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 816,
      'digest' => '3d953fafade06a4d7383199bb3f17f32',
      'first' => 
      array (
        '' => '',
        'A.T., SALMOS. BIBLIA' => 'A.T., SALMOS. BIBLIA',
        'ABUSO - IGLESIA' => 'ABUSO - IGLESIA',
      ),
      'last' => 
      array (
        'ZOOLOGIA' => 'ZOOLOGIA',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\BookForm::collectionId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'collectionId',
    'label' => 'Collection',
    'value' => 4,
    'attributes' => 
    array (
      'disabled' => false,
      'name' => 'collectionId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Collection',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 4,
      'digest' => '4a1931387005b4f46189287c69310212',
      'first' => 
      array (
        4 => 'General',
        5 => 'Schoenstatt',
        6 => 'Pallotti',
      ),
      'last' => 
      array (
        7 => 'Tesis',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Books\\Form\\BookForm::inLanguage' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'inLanguage',
    'label' => 'Language',
    'value' => NULL,
    'attributes' => 
    array (
      'multiple' => true,
      'name' => 'inLanguage',
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Language',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 639,
      'digest' => '7a4b8b0ee3a9dea96b267fadeda02a9e',
      'first' => 
      array (
        'aa' => 'Afar',
        'ab' => 'Abkhazian',
        'af' => 'Afrikaans',
      ),
      'last' => 
      array (
        'zun' => 'Zuni',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\BookForm::inactivationReason' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'inactivationReason',
    'label' => 'Inactivation reason',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'inactivationReason',
      'placeholder' => 'ex. Book lost',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Inactivation reason',
    ),
  ),
  'Books\\Form\\BookForm::isActive' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isActive',
    'label' => 'Active?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'isActive',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Active?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\BookForm::isbn' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'isbn',
    'label' => 'ISBN',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '30',
      'name' => 'isbn',
      'placeholder' => 'ex. 9780030426599',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'ISBN',
    ),
  ),
  'Books\\Form\\BookForm::keywords' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'keywords',
    'label' => 'Keywords',
    'value' => NULL,
    'attributes' => 
    array (
      'multiple' => true,
      'name' => 'keywords',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'help-block' => 'Subject terms shown to readers. Several are fine.',
      'label' => 'Keywords',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 253,
      'digest' => '0db4af6e819ec3a94e67a4d835dd4093',
      'first' => 
      array (
        'biography' => 'biography',
        'christ' => 'christ',
        'church' => 'church',
      ),
      'last' => 
      array (
        'USA' => 'USA',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\BookForm::libraryId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'libraryId',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'libraryId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::newCallNumber' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'newCallNumber',
    'label' => 'New call number',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'newCallNumber',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'Use this field when the printed call number should be changed. It will be added to the next labels to be printed.',
      'label' => 'New call number',
    ),
  ),
  'Books\\Form\\BookForm::nextWithinLibraryId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Button',
    'name' => 'nextWithinLibraryId',
    'label' => 'Use next free barcode',
    'value' => NULL,
    'attributes' => 
    array (
      'class' => 'btn btn-default',
      'id' => 'nextWithinLibraryId',
      'name' => 'nextWithinLibraryId',
      'type' => 'button',
    ),
    'options' => 
    array (
      'label' => 'Use next free barcode',
    ),
  ),
  'Books\\Form\\BookForm::numberOfPages' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Number',
    'name' => 'numberOfPages',
    'label' => 'Number of pages',
    'value' => NULL,
    'attributes' => 
    array (
      'inclusive' => true,
      'max' => 6000,
      'min' => 0,
      'name' => 'numberOfPages',
      'step' => 'any',
      'type' => 'number',
    ),
    'options' => 
    array (
      'label' => 'Number of pages',
    ),
  ),
  'Books\\Form\\BookForm::publicNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'publicNotes',
    'label' => 'Public notes',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'publicNotes',
      'required' => false,
      'rows' => 8,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Public notes',
    ),
  ),
  'Books\\Form\\BookForm::publicationId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publicationId',
    'label' => 'Linked literature record',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'publicationId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => false,
      'empty_option' => '',
      'help-block' => 'The work in the site-wide literature catalogue that this copy is of. While it is set, a spreadsheet import takes the title, author, year, publisher, place, pages, language and ISBN from that record rather than from the spreadsheet.',
      'label' => 'Linked literature record',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 4203,
      'digest' => '1af8f6b4f460cca55aef43727679ca93',
      'first' => 
      array (
        2110 => 'Bajo la Protección de María - Tomo 1 [3, 1978]',
        2154 => 'Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []',
        6581 => 'Bajo la Protección de María - Tomo 1 [1989-10]',
      ),
      'last' => 
      array (
        10164 => 'Im Dienste Mariens [5, 1935]',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\BookForm::publishedYear' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Number',
    'name' => 'publishedYear',
    'label' => 'Year published',
    'value' => NULL,
    'attributes' => 
    array (
      'inclusive' => true,
      'max' => 2027,
      'min' => 1800,
      'name' => 'publishedYear',
      'step' => 1,
      'type' => 'number',
    ),
    'options' => 
    array (
      'label' => 'Year published',
    ),
  ),
  'Books\\Form\\BookForm::publisher' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publisher',
    'label' => 'Publisher',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'publisher',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Publisher',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 396,
      'digest' => '8a793d21e7cb1b5abfa776927893dbcb',
      'first' => 
      array (
        'Agape Libros' => 'Agape Libros',
        'Aguilar' => 'Aguilar',
        'Aguilar Chilena Ediciones' => 'Aguilar Chilena Ediciones',
      ),
      'last' => 
      array (
        'zigzag' => 'zigzag',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\BookForm::publishingPlace' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'publishingPlace',
    'label' => 'Publishing place',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '200',
      'name' => 'publishingPlace',
      'placeholder' => 'Madrid, Spain',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Publishing place',
    ),
  ),
  'Books\\Form\\BookForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\BookForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::title' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'title',
    'label' => 'Title',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '300',
      'name' => 'title',
      'required' => true,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Title',
    ),
  ),
  'Books\\Form\\BookForm::withinLibraryId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Number',
    'name' => 'withinLibraryId',
    'label' => 'Barcode',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => 0,
      'name' => 'withinLibraryId',
      'required' => true,
      'step' => 1,
      'type' => 'number',
    ),
    'options' => 
    array (
      'help-block' => 'This library\'s own number for this copy. It must be unique within the library, and it is what a spreadsheet import matches a row to this book by.',
      'label' => 'Barcode',
    ),
  ),
  'Books\\Form\\CheckinForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\CheckinForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\CheckinForm::withinLibraryIds' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'withinLibraryIds',
    'label' => 'Book Ids to check in',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'withinLibraryIds',
      'required' => true,
      'tabindex' => 1,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'One barcode per line',
      'label' => 'Book Ids to check in',
    ),
  ),
  'Books\\Form\\CheckoutForm::adminNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'adminNotes',
    'label' => 'Notes',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'adminNotes',
      'required' => false,
      'rows' => 3,
      'tabindex' => 3,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Notes',
      'required' => false,
    ),
  ),
  'Books\\Form\\CheckoutForm::personId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'personId',
    'label' => 'Who\'s checking out?',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'personId',
      'tabindex' => 2,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Who\'s checking out?',
      'required' => true,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CheckoutForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\CheckoutForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'tabindex' => 4,
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\CheckoutForm::withinLibraryIds' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'withinLibraryIds',
    'label' => 'Book Ids',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'withinLibraryIds',
      'required' => true,
      'rows' => 6,
      'tabindex' => 1,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'One barcode per line',
      'label' => 'Book Ids',
    ),
  ),
  'Books\\Form\\CollectionForm::abbreviation' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'abbreviation',
    'label' => 'Abbreviation',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '12',
      'name' => 'abbreviation',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'This abbreviation will help sort search results. Keep it short to preserve database space since it is added to each book.',
      'label' => 'Abbreviation',
      'required' => true,
    ),
  ),
  'Books\\Form\\CollectionForm::adminNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'adminNotes',
    'label' => 'Admin notes',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'adminNotes',
      'required' => false,
      'rows' => 8,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Admin notes',
      'required' => false,
    ),
  ),
  'Books\\Form\\CollectionForm::callNumberExplanation' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'callNumberExplanation',
    'label' => 'Call number explanation',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '1000',
      'name' => 'callNumberExplanation',
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Call number explanation',
      'required' => true,
    ),
  ),
  'Books\\Form\\CollectionForm::callNumberHelpText' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'callNumberHelpText',
    'label' => 'Call number help text',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'callNumberHelpText',
      'rows' => '3',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Call number help text',
      'required' => false,
    ),
  ),
  'Books\\Form\\CollectionForm::callNumberRegex' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'callNumberRegex',
    'label' => 'Call number regex',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'callNumberRegex',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Call number regex',
      'required' => true,
    ),
  ),
  'Books\\Form\\CollectionForm::defaultCheckoutTimePeriodInDays' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Number',
    'name' => 'defaultCheckoutTimePeriodInDays',
    'label' => 'Default checkout time period (days)',
    'value' => 14,
    'attributes' => 
    array (
      'inclusive' => true,
      'max' => 365,
      'min' => 1,
      'name' => 'defaultCheckoutTimePeriodInDays',
      'step' => 1,
      'type' => 'number',
    ),
    'options' => 
    array (
      'label' => 'Default checkout time period (days)',
      'required' => false,
    ),
  ),
  'Books\\Form\\CollectionForm::description' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'description',
    'label' => 'Description',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '1000',
      'name' => 'description',
      'rows' => '3',
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Description',
      'required' => false,
    ),
  ),
  'Books\\Form\\CollectionForm::enforceCallNumberRegex' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'enforceCallNumberRegex',
    'label' => 'Enforce call number regex?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'enforceCallNumberRegex',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Enforce call number regex?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\CollectionForm::isActive' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isActive',
    'label' => 'Active?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'isActive',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Active?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\CollectionForm::labelLine1' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'labelLine1',
    'label' => 'Label line 1',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'labelLine1',
      'placeholder' => ':short_category',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'The three label line fields describe how to format a label for the spine of a book.',
      'label' => 'Label line 1',
      'required' => true,
    ),
  ),
  'Books\\Form\\CollectionForm::labelLine2' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'labelLine2',
    'label' => 'Label line 2',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'labelLine2',
      'placeholder' => '$2',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Label line 2',
      'required' => true,
    ),
  ),
  'Books\\Form\\CollectionForm::labelLine3' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'labelLine3',
    'label' => 'Label line 3',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'labelLine3',
      'placeholder' => '$3',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Label line 3',
      'required' => true,
    ),
  ),
  'Books\\Form\\CollectionForm::libraryId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'libraryId',
    'label' => NULL,
    'value' => 1,
    'attributes' => 
    array (
      'name' => 'libraryId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
      'required' => true,
    ),
  ),
  'Books\\Form\\CollectionForm::mainShowDisplay' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'mainShowDisplay',
    'label' => 'Main view format',
    'value' => 'show-categories',
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'mainShowDisplay',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => false,
      'empty_option' => '',
      'label' => 'Main view format',
      'required' => true,
      'unselected_value' => '',
      'value_options' => 
      array (
        'show-categories' => 'Show categories',
        'show-collections' => 'Show collections',
        'show-collections-categories' => 'Show collections and categories',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => '5c635f6eaf9a8d3746f47f519ba887ad',
      'first' => 
      array (
        'show-categories' => 'Show categories',
        'show-collections' => 'Show collections',
        'show-collections-categories' => 'Show collections and categories',
      ),
      'last' => 
      array (
        'show-collections-categories' => 'Show collections and categories',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CollectionForm::name' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'name',
    'label' => 'Name',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'name',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Name',
      'required' => true,
    ),
  ),
  'Books\\Form\\CollectionForm::requireCallNumbers' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'requireCallNumbers',
    'label' => 'Require call numbers?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'requireCallNumbers',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Require call numbers?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\CollectionForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\CollectionForm::sortTextFormat' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'sortTextFormat',
    'label' => 'Sort text format',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'sortTextFormat',
      'placeholder' => '%1$s%2$-8s%3$04d%4$03d%5$03d',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'Format text to pass to sprintf. See https://secure.php.net/manual/en/function.sprintf.php for more info. The first parameter passed is the collection abbreviation followed by the capture groups of the call number.',
      'label' => 'Sort text format',
      'required' => false,
    ),
  ),
  'Books\\Form\\CollectionForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::chordProSpec' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'chordProSpec',
    'label' => 'Chord pro specification',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '2000',
      'name' => 'chordProSpec',
      'rows' => 8,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'See <a href="https://www.chordpro.org/">Chord pro markup</a>. The metadata will be automatically added afterwards. Html tags are not allowed.',
      'label' => 'Chord pro specification',
    ),
  ),
  'Books\\Form\\CompositionForm::composersAll' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'composersAll',
    'label' => 'Composer(s)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'multiple' => true,
      'name' => 'composersAll',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Composer(s)',
      'required' => false,
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CompositionForm::copyrightContactEmail' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Email',
    'name' => 'copyrightContactEmail',
    'label' => 'Copyright contact email',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '70',
      'name' => 'copyrightContactEmail',
      'required' => false,
      'type' => 'email',
    ),
    'options' => 
    array (
      'label' => 'Copyright contact email',
    ),
  ),
  'Books\\Form\\CompositionForm::copyrightInfo' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'copyrightInfo',
    'label' => 'Copyright info',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'name' => 'copyrightInfo',
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'Include information about the copyright owner, contact information, and under what licence it has been published.',
      'label' => 'Copyright info',
      'required' => false,
    ),
  ),
  'Books\\Form\\CompositionForm::country' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'country',
    'label' => 'Country of origin',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'country',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Country of origin',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 249,
      'digest' => '054d02752ee6095bc3bb3601e547c227',
      'first' => 
      array (
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
      ),
      'last' => 
      array (
        'AX' => 'Åland Islands',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CompositionForm::derivedFromCompositionId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'derivedFromCompositionId',
    'label' => 'Derived or translated from',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'derivedFromCompositionId',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Derived or translated from',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 335,
      'digest' => '30135fe869c4b215b13e76007a0232a5',
      'first' => 
      array (
        320 => 'Kyrie, eleison',
        369 => 'Lord, I need you',
        372 => 'Website with many Schoenstatt songs',
      ),
      'last' => 
      array (
        182 => 'Wait for the Lord (Confia em Deus)',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CompositionForm::disambiguatingDescription' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'disambiguatingDescription',
    'label' => 'Disambiguating subtitle',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'disambiguatingDescription',
      'placeholder' => 'ex. Santo de la misa criolla',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'Please only use when composition needs to be distinguished from another similarly-named composition.',
      'label' => 'Disambiguating subtitle',
    ),
  ),
  'Books\\Form\\CompositionForm::inLanguage' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'inLanguage',
    'label' => 'Language',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'inLanguage',
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Language',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 639,
      'digest' => '7a4b8b0ee3a9dea96b267fadeda02a9e',
      'first' => 
      array (
        'aa' => 'Afar',
        'ab' => 'Abkhazian',
        'af' => 'Afrikaans',
      ),
      'last' => 
      array (
        'zun' => 'Zuni',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CompositionForm::lilyPondSpec' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'lilyPondSpec',
    'label' => 'LilyPond music notation',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '5000',
      'name' => 'lilyPondSpec',
      'rows' => 8,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'See <a href="http://lilypond.org/">LilyPond markup</a>.',
      'label' => 'LilyPond music notation',
      'required' => false,
    ),
  ),
  'Books\\Form\\CompositionForm::lyricistsAll' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'lyricistsAll',
    'label' => 'Lyricist(s)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'multiple' => true,
      'name' => 'lyricistsAll',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Lyricist(s)',
      'required' => false,
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CompositionForm::lyrics' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'lyrics',
    'label' => 'Lyrics',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'name' => 'lyrics',
      'rows' => 6,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'If song is specified with chord pro, it\'s not necessary to fill in the lyrics separately.',
      'label' => 'Lyrics',
    ),
  ),
  'Books\\Form\\CompositionForm::name' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'name',
    'label' => 'Composition name',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '200',
      'name' => 'name',
      'placeholder' => 'ex. María de la Alianza',
      'required' => true,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Composition name',
    ),
  ),
  'Books\\Form\\CompositionForm::openLicenseUrl' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'openLicenseUrl',
    'label' => 'Creative commons license',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'openLicenseUrl',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'help-block' => 'If you speak with the original artist (copyright holder), please consider requesting that they release their song(s) under one of the <a href="https://creativecommons.org/licenses/">Creative Commons licenses</a>. This doesn\'t mean they need to "surrender" their copyrights, but instead sets the "default permissions" for using the song. If they decide to release it under one of these licenses, please ask for an email containing this decision and forward it to <a href="mailto:webmaster@schoenstatt.link">webmaster@schoenstatt.link</a>.',
      'label' => 'Creative commons license',
      'unselected_value' => '',
      'value_options' => 
      array (
        'https://creativecommons.org/about/cc0' => 'CC0 No Rights Reserved',
        'https://creativecommons.org/licenses/by-nc-nd/4.0' => 'Attribution-NonCommercial-NoDerivs 4.0',
        'https://creativecommons.org/licenses/by-nc-sa/4.0' => 'Attribution-NonCommercial-ShareAlike 4.0',
        'https://creativecommons.org/licenses/by-nc/4.0' => 'Attribution-NonCommercial 4.0',
        'https://creativecommons.org/licenses/by-nd/4.0' => 'Attribution-NoDerivs 4.0',
        'https://creativecommons.org/licenses/by-sa/4.0' => 'Attribution-ShareAlike 4.0',
        'https://creativecommons.org/licenses/by/4.0' => 'Attribution 4.0',
        'https://wiki.creativecommons.org/wiki/Public_domain' => 'Public domain',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 8,
      'digest' => '4c141e3ce0075842100f5d8738f8ac60',
      'first' => 
      array (
        'https://creativecommons.org/licenses/by-nd/4.0' => 'Attribution-NoDerivs 4.0',
        'https://creativecommons.org/licenses/by-sa/4.0' => 'Attribution-ShareAlike 4.0',
        'https://creativecommons.org/licenses/by/4.0' => 'Attribution 4.0',
      ),
      'last' => 
      array (
        'https://wiki.creativecommons.org/wiki/Public_domain' => 'Public domain',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CompositionForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\CompositionForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::tags' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'tags',
    'label' => 'Tags',
    'value' => NULL,
    'attributes' => 
    array (
      'multiple' => true,
      'name' => 'tags',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'help-block' => 'Tags regarding the content, form or liturgical use of the song.',
      'label' => 'Tags',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 53,
      'digest' => '03d72453e0c24e8979d1ee3dacae0177',
      'first' => 
      array (
        'Indice:Inglês' => 'Indice:Inglês',
        'Liturgia-Perdão' => 'Liturgia-Perdão',
        'Maria' => 'Maria',
      ),
      'last' => 
      array (
        'Peregrinação 2008' => 'Peregrinação 2008',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CompositionForm::url1' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url1',
    'label' => 'URL 1',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'url1',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'required' => false,
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'URL 1',
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Books\\Form\\CompositionForm::url1Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url1Label',
    'label' => 'URL 1 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'url1Label',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'URL 1 Label',
      'unselected_value' => '',
      'value_options' => 
      array (
        'Album' => 'Album',
        'Lyrics' => 'Lyrics',
        'Media' => 'Media',
        'Reference' => 'Reference',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 4,
      'digest' => 'c6484eca76956fbb332adff451078a5f',
      'first' => 
      array (
        'Album' => 'Album',
        'Lyrics' => 'Lyrics',
        'Media' => 'Media',
      ),
      'last' => 
      array (
        'Reference' => 'Reference',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CompositionForm::url2' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url2',
    'label' => 'URL 2',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'url2',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'required' => false,
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'URL 2',
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Books\\Form\\CompositionForm::url2Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url2Label',
    'label' => 'URL 2 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'url2Label',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'URL 2 Label',
      'unselected_value' => '',
      'value_options' => 
      array (
        'Album' => 'Album',
        'Lyrics' => 'Lyrics',
        'Media' => 'Media',
        'Reference' => 'Reference',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 4,
      'digest' => 'c6484eca76956fbb332adff451078a5f',
      'first' => 
      array (
        'Album' => 'Album',
        'Lyrics' => 'Lyrics',
        'Media' => 'Media',
      ),
      'last' => 
      array (
        'Reference' => 'Reference',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CompositionForm::url3' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url3',
    'label' => 'URL 3',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'url3',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'required' => false,
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'URL 3',
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Books\\Form\\CompositionForm::url3Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url3Label',
    'label' => 'URL 3 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'url3Label',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'URL 3 Label',
      'unselected_value' => '',
      'value_options' => 
      array (
        'Album' => 'Album',
        'Lyrics' => 'Lyrics',
        'Media' => 'Media',
        'Reference' => 'Reference',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 4,
      'digest' => 'c6484eca76956fbb332adff451078a5f',
      'first' => 
      array (
        'Album' => 'Album',
        'Lyrics' => 'Lyrics',
        'Media' => 'Media',
      ),
      'last' => 
      array (
        'Reference' => 'Reference',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\CompositionForm::yearPublished' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'yearPublished',
    'label' => 'Published date',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'yearPublished',
      'placeholder' => '2007',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'The year is plenty; if a more specific date is available, use format YYYY-MM-DD',
      'label' => 'Published date',
    ),
  ),
  'Books\\Form\\CopyToMainCorpusForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\CopyToMainCorpusForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Copy into main corpus',
    'attributes' => 
    array (
      'class' => 'btn btn-primary',
      'id' => 'copy-to-main-corpus-submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\CreateNewEditionForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\CreateNewEditionForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Add another edition',
    'attributes' => 
    array (
      'class' => 'btn btn-primary',
      'id' => 'create-new-edition-submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::directTranslation' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'directTranslation',
    'label' => 'Direct Translation',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'directTranslation',
      'required' => false,
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'Most probable translated text that could directly replace the German key text',
      'label' => 'Direct Translation',
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::entry' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'entry',
    'label' => 'Dictionary Entry',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'maxlength' => '1000',
      'name' => 'entry',
      'required' => true,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Dictionary Entry',
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::isActive' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isActive',
    'label' => 'Active?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'isActive',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Active?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\DictionaryEntryForm::key' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'key',
    'label' => 'Key (German)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'key',
      'required' => true,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Key (German)',
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::links' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'links',
    'label' => 'Links',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'multiple' => true,
      'name' => 'links',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Links',
      'required' => false,
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 1352,
      'digest' => 'aeaf89df2d9835bf65a0471998a2b406',
      'first' => 
      array (
        'abbild' => 'Abbild',
        'die-welt-als-ersatzgott' => '(Die Welt als) Ersatzgott',
        'freiheit-ist-entscheidungs-und-durchsetzungsfaehigkeit' => '(Freiheit ist) Entscheidungs- und Durchsetzungsfähigkeit',
      ),
      'last' => 
      array (
        'selbstzerfasserung' => 'Selbstzerfasserung',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\DictionaryEntryForm::locale' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'locale',
    'label' => 'Language',
    'value' => NULL,
    'attributes' => 
    array (
      'disabled' => false,
      'maxlength' => '6',
      'name' => 'locale',
      'required' => true,
      'type' => 'select',
    ),
    'options' => 
    array (
      'label' => 'Language',
      'value_options' => 
      array (
        'cs_CZ' => 'Czech',
        'en_US' => 'English',
        'es_ES' => 'Spanish',
        'fr_FR' => 'French',
        'hu_HU' => 'Hungarian',
        'pt_PT' => 'Portugues (Portugal)',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 6,
      'digest' => '1a0ab0f20fe8ba718442fb04fff30876',
      'first' => 
      array (
        'en_US' => 'English',
        'es_ES' => 'Spanish',
        'pt_PT' => 'Portugues (Portugal)',
      ),
      'last' => 
      array (
        'hu_HU' => 'Hungarian',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Books\\Form\\DictionaryEntryForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\ImportForm::description' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'description',
    'label' => 'Description',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '1000',
      'name' => 'description',
      'rows' => '4',
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Description',
      'required' => false,
    ),
  ),
  'Books\\Form\\ImportForm::file' => 
  array (
    'class' => 'Laminas\\Form\\Element\\File',
    'name' => 'file',
    'label' => 'Spreadsheet',
    'value' => NULL,
    'attributes' => 
    array (
      'accept' => '.xlsx,.xls,.ods',
      'name' => 'file',
      'type' => 'file',
    ),
    'options' => 
    array (
      'help-block' => 'An .xlsx, .xls or .ods file. Download the template above if you do not have one yet.',
      'label' => 'Spreadsheet',
      'required' => true,
    ),
  ),
  'Books\\Form\\ImportForm::isCompleteImport' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isCompleteImport',
    'label' => 'Complete import?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isCompleteImport',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'help-block' => 'Tick only if this file is the whole library. Every active book it does not list will be marked inactive.',
      'label' => 'Complete import?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\ImportForm::libraryId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'libraryId',
    'label' => 'Library',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'libraryId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
      'label' => 'Library',
      'required' => true,
    ),
  ),
  'Books\\Form\\ImportForm::name' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'name',
    'label' => 'Import name',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '100',
      'name' => 'name',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'Something you will recognise later, such as "Jornada de trabajo, June".',
      'label' => 'Import name',
      'required' => true,
    ),
  ),
  'Books\\Form\\ImportForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\ImportForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Upload and continue',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\InactivationForm::inactivationReason' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'inactivationReason',
    'label' => 'Inactivation reason',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'inactivationReason',
      'required' => false,
      'tabindex' => 3,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Inactivation reason',
      'required' => false,
    ),
  ),
  'Books\\Form\\InactivationForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\InactivationForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'tabindex' => 4,
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\InactivationForm::withinLibraryIds' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'withinLibraryIds',
    'label' => 'Book Ids',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'withinLibraryIds',
      'required' => true,
      'rows' => 6,
      'tabindex' => 1,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'One barcode per line',
      'label' => 'Book Ids',
    ),
  ),
  'Books\\Form\\LibraryForm::adminNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'adminNotes',
    'label' => 'Admin notes',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'adminNotes',
      'required' => false,
      'rows' => 8,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Admin notes',
      'required' => false,
    ),
  ),
  'Books\\Form\\LibraryForm::allowCollectionlessBooks' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'allowCollectionlessBooks',
    'label' => 'Allow collectionless books?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'allowCollectionlessBooks',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Allow collectionless books?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\LibraryForm::barcodeText' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'barcodeText',
    'label' => 'Barcode text',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'barcodeText',
      'placeholder' => 'ex. Bibliotheca Sion',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Barcode text',
      'required' => true,
    ),
  ),
  'Books\\Form\\LibraryForm::callNumberExplanation' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'callNumberExplanation',
    'label' => 'Call number explanation',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '1000',
      'name' => 'callNumberExplanation',
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Call number explanation',
      'required' => true,
    ),
  ),
  'Books\\Form\\LibraryForm::callNumberHelpText' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'callNumberHelpText',
    'label' => 'Call number help text',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'callNumberHelpText',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Call number help text',
      'required' => false,
    ),
  ),
  'Books\\Form\\LibraryForm::callNumberPlaceholder' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'callNumberPlaceholder',
    'label' => 'Call number placeholder',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'callNumberPlaceholder',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Call number placeholder',
      'required' => false,
    ),
  ),
  'Books\\Form\\LibraryForm::callNumberRegex' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'callNumberRegex',
    'label' => 'Call number regex',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'callNumberRegex',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Call number regex',
      'required' => true,
    ),
  ),
  'Books\\Form\\LibraryForm::checkoutBooksRole' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'checkoutBooksRole',
    'label' => 'Who can checkout books?',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'checkoutBooksRole',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Who can checkout books?',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 5,
      'digest' => '30525852a056be4c7a6131bde437bb7b',
      'first' => 
      array (
        'guest' => 'Public',
        'lib_academic' => 'Academic users',
        'lib_user' => 'Authenticated users',
      ),
      'last' => 
      array (
        'lib_patres' => 'Patres',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\LibraryForm::checkoutPersonListKind' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'checkoutPersonListKind',
    'label' => 'Person list for checkouts',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'checkoutPersonListKind',
      'type' => 'select',
    ),
    'options' => 
    array (
      'help-block' => 'This controls which person list will be displayed on the checkout form.',
      'label' => 'Person list for checkouts',
      'required' => true,
    ),
    'valueOptions' => 
    array (
      'count' => 2,
      'digest' => 'def3116a320701fbf9a27c878a919433',
      'first' => 
      array (
        'all-borrowers' => 'All borrowers',
        'patres-sion' => 'Schoenstatt Fathers',
      ),
      'last' => 
      array (
        'patres-sion' => 'Schoenstatt Fathers',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Books\\Form\\LibraryForm::contactEmail' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Email',
    'name' => 'contactEmail',
    'label' => 'Contact email',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'contactEmail',
      'type' => 'email',
    ),
    'options' => 
    array (
      'label' => 'Contact email',
      'required' => false,
    ),
  ),
  'Books\\Form\\LibraryForm::contactPersonId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'contactPersonId',
    'label' => 'Contact person',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'contactPersonId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => false,
      'empty_option' => '',
      'label' => 'Contact person',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 325,
      'digest' => 'd5d786e6b2cd20f22ce850baf06cd29e',
      'first' => 
      array (
        630 => 'Abella, Santiago',
        633 => 'Abarca Vallejos, Sergio Franco Alexander',
        658 => 'Abud Sittler, Christián José',
      ),
      'last' => 
      array (
        529 => 'Álamos, Victor',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\LibraryForm::createCheckoutsIfCheckingInANonCheckedOutBook' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'createCheckoutsIfCheckingInANonCheckedOutBook',
    'label' => 'Create checkouts if checking in a non-checked out book?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'createCheckoutsIfCheckingInANonCheckedOutBook',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Create checkouts if checking in a non-checked out book?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\LibraryForm::defaultCheckoutPersonId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'defaultCheckoutPersonId',
    'label' => 'Default checkout person',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'defaultCheckoutPersonId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => false,
      'empty_option' => '',
      'help-block' => 'When books are checked in without a preceding checkout, they\'ll be checked out under this person.',
      'label' => 'Default checkout person',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 325,
      'digest' => 'd5d786e6b2cd20f22ce850baf06cd29e',
      'first' => 
      array (
        630 => 'Abella, Santiago',
        633 => 'Abarca Vallejos, Sergio Franco Alexander',
        658 => 'Abud Sittler, Christián José',
      ),
      'last' => 
      array (
        529 => 'Álamos, Victor',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\LibraryForm::defaultCheckoutTimePeriodInDays' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Number',
    'name' => 'defaultCheckoutTimePeriodInDays',
    'label' => 'Default checkout time period (days)',
    'value' => 14,
    'attributes' => 
    array (
      'inclusive' => true,
      'max' => 365,
      'min' => 1,
      'name' => 'defaultCheckoutTimePeriodInDays',
      'step' => 1,
      'type' => 'number',
    ),
    'options' => 
    array (
      'label' => 'Default checkout time period (days)',
      'required' => true,
    ),
  ),
  'Books\\Form\\LibraryForm::description' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'description',
    'label' => 'Description',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '1000',
      'name' => 'description',
      'rows' => '3',
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Description',
      'required' => false,
    ),
  ),
  'Books\\Form\\LibraryForm::enableCheckouts' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'enableCheckouts',
    'label' => 'Enable checkouts?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'enableCheckouts',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Enable checkouts?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\LibraryForm::enforceCallNumberRegex' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'enforceCallNumberRegex',
    'label' => 'Enforce call number regex?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'enforceCallNumberRegex',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Enforce call number regex?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\LibraryForm::filiationId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'filiationId',
    'label' => 'Filiation',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'filiationId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => false,
      'empty_option' => '',
      'label' => 'Filiation',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 5,
      'digest' => '915ce5a196d5c08370d27302e4d8a359',
      'first' => 
      array (
        12 => 'Colegio Mayor',
        44 => 'Studentat Kentenich Vidhyaniketan',
        50 => 'Vaterhaus',
      ),
      'last' => 
      array (
        7 => 'Bellavista - casa central',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\LibraryForm::isActive' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isActive',
    'label' => 'Active?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'isActive',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Active?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\LibraryForm::labelLine1' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'labelLine1',
    'label' => 'Label line 1',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'labelLine1',
      'placeholder' => ':short_category',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'The three label line fields describe how to format a label for the spine of a book.',
      'label' => 'Label line 1',
      'required' => true,
    ),
  ),
  'Books\\Form\\LibraryForm::labelLine2' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'labelLine2',
    'label' => 'Label line 2',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'labelLine2',
      'placeholder' => '$2',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Label line 2',
      'required' => true,
    ),
  ),
  'Books\\Form\\LibraryForm::labelLine3' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'labelLine3',
    'label' => 'Label line 3',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'labelLine3',
      'placeholder' => '$3',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Label line 3',
      'required' => true,
    ),
  ),
  'Books\\Form\\LibraryForm::mainCollectionId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'mainCollectionId',
    'label' => 'Main collection',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'mainCollectionId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => false,
      'empty_option' => '',
      'label' => 'Main collection',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 4,
      'digest' => '4a1931387005b4f46189287c69310212',
      'first' => 
      array (
        4 => 'General',
        5 => 'Schoenstatt',
        6 => 'Pallotti',
      ),
      'last' => 
      array (
        7 => 'Tesis',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\LibraryForm::mainShowDisplay' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'mainShowDisplay',
    'label' => 'Main library view screen',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'mainShowDisplay',
      'type' => 'select',
    ),
    'options' => 
    array (
      'label' => 'Main library view screen',
      'required' => true,
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => '5c635f6eaf9a8d3746f47f519ba887ad',
      'first' => 
      array (
        'show-categories' => 'Show categories',
        'show-collections' => 'Show collections',
        'show-collections-categories' => 'Show collections and categories',
      ),
      'last' => 
      array (
        'show-collections-categories' => 'Show collections and categories',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Books\\Form\\LibraryForm::name' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'name',
    'label' => 'Name',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'name',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Name',
      'required' => true,
    ),
  ),
  'Books\\Form\\LibraryForm::requireCallNumbers' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'requireCallNumbers',
    'label' => 'Require call numbers?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'requireCallNumbers',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Require call numbers?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\LibraryForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\LibraryForm::sortTextFormat' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'sortTextFormat',
    'label' => 'Sort text format',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'sortTextFormat',
      'placeholder' => '{collectionAbbreviation}%1$06.2f{author}{title}%2$02d',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'Format text to pass to sprintf. See https://secure.php.net/manual/en/function.sprintf.php for more info. The first parameter passed is the collection abbreviation followed by the capture groups of the call number.',
      'label' => 'Sort text format',
      'required' => false,
    ),
  ),
  'Books\\Form\\LibraryForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::useCollections' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'useCollections',
    'label' => 'Use collections?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'useCollections',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Use collections?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\LibraryForm::viewRole' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'viewRole',
    'label' => 'Library visibility',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'viewRole',
      'required' => true,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => NULL,
      'label' => 'Library visibility',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 5,
      'digest' => '30525852a056be4c7a6131bde437bb7b',
      'first' => 
      array (
        'guest' => 'Public',
        'lib_academic' => 'Academic users',
        'lib_user' => 'Authenticated users',
      ),
      'last' => 
      array (
        'lib_patres' => 'Patres',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Books\\Form\\MassCheckoutFieldset::checkedOutOn' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Date',
    'name' => 'checkedOutOn',
    'label' => 'Checked out on',
    'value' => '<today>',
    'attributes' => 
    array (
      'min' => '<today>',
      'name' => 'checkedOutOn',
      'required' => false,
      'step' => 'any',
      'type' => 'date',
    ),
    'options' => 
    array (
      'label' => 'Checked out on',
    ),
  ),
  'Books\\Form\\MassCheckoutFieldset::personId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'personId',
    'label' => 'Who\'s checking out?',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'personId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'allow_empty' => true,
      'continue_if_empty' => true,
      'empty_option' => '',
      'label' => 'Who\'s checking out?',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\MassCheckoutFieldset::withinLibraryIds' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'withinLibraryIds',
    'label' => 'Book Ids',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'withinLibraryIds',
      'required' => false,
      'rows' => 6,
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'One barcode per line',
      'label' => 'Book Ids',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Collection',
    'name' => 'checkout',
    'label' => 'Book Ids',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'checkout',
      'required' => true,
    ),
    'options' => 
    array (
      'allow_add' => true,
      'allow_remove' => true,
      'count' => 4,
      'label' => 'Book Ids',
      'should_create_template' => true,
      'target_element' => 
      array (
        'type' => 'Books\\Form\\MassCheckoutFieldset',
      ),
    ),
    'fieldset' => true,
  ),
  'Books\\Form\\MassCheckoutForm::checkout/<target>/checkedOutOn' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Date',
    'name' => 'checkedOutOn',
    'label' => 'Checked out on',
    'value' => '<today>',
    'attributes' => 
    array (
      'min' => '<today>',
      'name' => 'checkedOutOn',
      'required' => false,
      'step' => 'any',
      'type' => 'date',
    ),
    'options' => 
    array (
      'label' => 'Checked out on',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/<target>/personId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'personId',
    'label' => 'Who\'s checking out?',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'personId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'allow_empty' => true,
      'continue_if_empty' => true,
      'empty_option' => '',
      'label' => 'Who\'s checking out?',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\MassCheckoutForm::checkout/<target>/withinLibraryIds' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'withinLibraryIds',
    'label' => 'Book Ids',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'withinLibraryIds',
      'required' => false,
      'rows' => 6,
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'One barcode per line',
      'label' => 'Book Ids',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\MassCheckoutForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'tabindex' => 4,
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::adminNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'adminNotes',
    'label' => 'Admin notes',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'adminNotes',
      'required' => false,
      'rows' => 4,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Admin notes',
      'required' => false,
    ),
  ),
  'Books\\Form\\PublicationForm::authorsAll' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'authorsAll',
    'label' => 'Author(s)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'multiple' => true,
      'name' => 'authorsAll',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Author(s)',
      'required' => false,
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 1932,
      'digest' => '8df4c1b877f5825b09b62a98e1512865',
      'first' => 
      array (
        '' => '',
        '(verantw. Redaktionsteam)' => '(verantw. Redaktionsteam)',
        '2ª Escuela de Jefes del Paraguay (JM) -  “Conqu' => '2ª Escuela de Jefes del Paraguay (JM) -  “Conqu',
      ),
      'last' => 
      array (
        'p629' => 'Óscar Iván Saldívar',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::bookEdition' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'bookEdition',
    'label' => 'Edition number',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'bookEdition',
      'placeholder' => '1',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Edition number',
      'required' => false,
    ),
  ),
  'Books\\Form\\PublicationForm::bookFormatType' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'bookFormatType',
    'label' => 'Book format',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'bookFormatType',
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Book format',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 6,
      'digest' => '316fc50b5971da86b77702118c7fe133',
      'first' => 
      array (
        'AudiobookFormat' => 'AudiobookFormat',
        'EBook' => 'EBook',
        'Hardcover' => 'Hardcover',
      ),
      'last' => 
      array (
        'GraphicNovel' => 'GraphicNovel',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::categoryId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'categoryId',
    'label' => 'Category',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'categoryId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Category',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 15,
      'digest' => 'd3edcb54e0241163b7ae9d363358fa93',
      'first' => 
      array (
        2 => 'Fr. Kentenich - Pre-Schoenstatt (1899-1912)',
        3 => 'Fr. Kentenich - Founding Era (1912-1919)',
        4 => 'Fr. Kentenich - The Growing Movement (1920-1941)',
      ),
      'last' => 
      array (
        16 => 'Prayer',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::copyrightInfo' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'copyrightInfo',
    'label' => 'Copyright info',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'name' => 'copyrightInfo',
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'Include information about the copyright owner, contact information, and under what licence it has been published.',
      'label' => 'Copyright info',
      'required' => false,
    ),
  ),
  'Books\\Form\\PublicationForm::copyrightYear' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Number',
    'name' => 'copyrightYear',
    'label' => 'Copyright year',
    'value' => NULL,
    'attributes' => 
    array (
      'inclusive' => true,
      'max' => 2025,
      'min' => 1800,
      'name' => 'copyrightYear',
      'step' => 1,
      'type' => 'number',
    ),
    'options' => 
    array (
      'help-block' => 'The year of the first edition, ie. the creation of the creative work.',
      'label' => 'Copyright year',
      'required' => false,
    ),
  ),
  'Books\\Form\\PublicationForm::datePublishedText' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'datePublishedText',
    'label' => 'Published date',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'datePublishedText',
      'placeholder' => '2007',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'The year is plenty; if a more specific date is available, use format YYYY-MM-DD',
      'label' => 'Published date',
      'required' => false,
    ),
  ),
  'Books\\Form\\PublicationForm::description' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'description',
    'label' => 'Description',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'description',
      'rows' => 5,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'Description of the book, helpful to a non-Schoenstatt visitor to the site.',
      'label' => 'Description',
      'required' => false,
    ),
  ),
  'Books\\Form\\PublicationForm::editionNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'editionNotes',
    'label' => 'Notes relevant to this particular edition',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'editionNotes',
      'required' => false,
      'rows' => 4,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Notes relevant to this particular edition',
      'required' => false,
    ),
  ),
  'Books\\Form\\PublicationForm::editorsAll' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'editorsAll',
    'label' => 'Editor(s)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'multiple' => true,
      'name' => 'editorsAll',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Editor(s)',
      'required' => false,
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 1932,
      'digest' => '8df4c1b877f5825b09b62a98e1512865',
      'first' => 
      array (
        '' => '',
        '(verantw. Redaktionsteam)' => '(verantw. Redaktionsteam)',
        '2ª Escuela de Jefes del Paraguay (JM) -  “Conqu' => '2ª Escuela de Jefes del Paraguay (JM) -  “Conqu',
      ),
      'last' => 
      array (
        'p629' => 'Óscar Iván Saldívar',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::hasNoExplictEditionNumber' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'hasNoExplictEditionNumber',
    'label' => 'Publication has no explicit edition number?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'hasNoExplictEditionNumber',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Publication has no explicit edition number?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\PublicationForm::hasNoISBN' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'hasNoISBN',
    'label' => 'Publication has no ISBN?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'hasNoISBN',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Publication has no ISBN?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\PublicationForm::inLanguage' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'inLanguage',
    'label' => 'Language',
    'value' => NULL,
    'attributes' => 
    array (
      'multiple' => true,
      'name' => 'inLanguage',
      'required' => true,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Language',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 639,
      'digest' => '7a4b8b0ee3a9dea96b267fadeda02a9e',
      'first' => 
      array (
        'aa' => 'Afar',
        'ab' => 'Abkhazian',
        'af' => 'Afrikaans',
      ),
      'last' => 
      array (
        'zun' => 'Zuni',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::isFormallyPublished' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isFormallyPublished',
    'label' => 'Is formally published?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isFormallyPublished',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Is formally published?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\PublicationForm::isRevisedWithBookInHand' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isRevisedWithBookInHand',
    'label' => 'Data has been revised with book in hand?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isRevisedWithBookInHand',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Data has been revised with book in hand?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\PublicationForm::isScientificWork' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isScientificWork',
    'label' => 'Scientific work?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isScientificWork',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Scientific work?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\PublicationForm::isbn' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'isbn',
    'label' => 'ISBN',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '30',
      'name' => 'isbn',
      'placeholder' => 'ex. 9780030426599',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'ISBN',
      'required' => false,
    ),
  ),
  'Books\\Form\\PublicationForm::keywords' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'keywords',
    'label' => 'Keywords',
    'value' => NULL,
    'attributes' => 
    array (
      'multiple' => true,
      'name' => 'keywords',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'help-block' => 'Thematic tags regarding the content of the book',
      'label' => 'Keywords',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 470,
      'digest' => '283b50f1eebf2718c3e1fe1a5c6082b6',
      'first' => 
      array (
        'document' => 'document',
        'joseph kentenich' => 'joseph kentenich',
        'youth' => 'youth',
      ),
      'last' => 
      array (
        'Dialectical Materialism' => 'Dialectical Materialism',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::mainPublicationId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'mainPublicationId',
    'label' => 'Main publication (use for outdated editions)',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'mainPublicationId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => false,
      'empty_option' => '',
      'help-block' => 'To avoid showing multiple editions of the same book in the index, please select the latest edition of this work from the list.',
      'label' => 'Main publication (use for outdated editions)',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 4203,
      'digest' => '1af8f6b4f460cca55aef43727679ca93',
      'first' => 
      array (
        2110 => 'Bajo la Protección de María - Tomo 1 [3, 1978]',
        2154 => 'Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []',
        6581 => 'Bajo la Protección de María - Tomo 1 [1989-10]',
      ),
      'last' => 
      array (
        10164 => 'Im Dienste Mariens [5, 1935]',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::numberOfPages' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'numberOfPages',
    'label' => 'Number of pages',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'numberOfPages',
      'placeholder' => 'xxi+133',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'Normally, just the number of pages unless book has roman numeral-numbered pages at the beginning. In this case, use the form `xxxii+442`.',
      'label' => 'Number of pages',
      'required' => false,
    ),
  ),
  'Books\\Form\\PublicationForm::publicNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'publicNotes',
    'label' => 'Notes relevant to the whole work (including other editions)',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'publicNotes',
      'required' => false,
      'rows' => 4,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => '',
      'label' => 'Notes relevant to the whole work (including other editions)',
      'required' => false,
    ),
  ),
  'Books\\Form\\PublicationForm::publisher' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'publisher',
    'label' => 'Publisher',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'publisher',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Publisher',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 848,
      'digest' => '0f18fe3e9c9ad0136835fb2447625088',
      'first' => 
      array (
        'A. Deichertsche Verlagsbuchhandlung, Leipzig' => 'A. Deichertsche Verlagsbuchhandlung, Leipzig',
        '„Der Elsässer", Buchdruckerei und Zeitungsverlag Straßburg' => '„Der Elsässer", Buchdruckerei und Zeitungsverlag Straßburg',
        '„Farul Nou" Bucuresti' => '„Farul Nou" Bucuresti',
      ),
      'last' => 
      array (
        'Zisterzienserkloster Schlierbach in Oberösterreich' => 'Zisterzienserkloster Schlierbach in Oberösterreich',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::publishingPlace' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'publishingPlace',
    'label' => 'Publishing place',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '200',
      'name' => 'publishingPlace',
      'placeholder' => 'Madrid, Spain',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Publishing place',
      'required' => true,
    ),
  ),
  'Books\\Form\\PublicationForm::resourceId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'resourceId',
    'label' => 'Access level',
    'value' => 'publication_public',
    'attributes' => 
    array (
      'name' => 'resourceId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'help-block' => 'This defines which users will see this book information listed',
      'label' => 'Access level',
      'required' => true,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 8,
      'digest' => '2cdc84cc0009b53a71ef7f11f0b43878',
      'first' => 
      array (
        'publication_institute' => 'Institute users',
        'publication_public' => 'Public',
        'publication_user' => 'Authenticated users',
      ),
      'last' => 
      array (
        'publication_sisters' => 'Sisters of Mary',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\PublicationForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::title' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'title',
    'label' => 'Title',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '300',
      'name' => 'title',
      'required' => true,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Title',
    ),
  ),
  'Books\\Form\\PublicationForm::translatedFromPublicationId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'translatedFromPublicationId',
    'label' => 'Translated from',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'translatedFromPublicationId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => false,
      'empty_option' => '',
      'help-block' => 'The work in the original language from which this book was translated. If there is more than one edition in the original language, select the newest edition.',
      'label' => 'Translated from',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 4203,
      'digest' => '1af8f6b4f460cca55aef43727679ca93',
      'first' => 
      array (
        2110 => 'Bajo la Protección de María - Tomo 1 [3, 1978]',
        2154 => 'Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []',
        6581 => 'Bajo la Protección de María - Tomo 1 [1989-10]',
      ),
      'last' => 
      array (
        10164 => 'Im Dienste Mariens [5, 1935]',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::translatorsAll' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'translatorsAll',
    'label' => 'Translator',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '500',
      'multiple' => true,
      'name' => 'translatorsAll',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Translator',
      'required' => false,
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 1932,
      'digest' => '8df4c1b877f5825b09b62a98e1512865',
      'first' => 
      array (
        '' => '',
        '(verantw. Redaktionsteam)' => '(verantw. Redaktionsteam)',
        '2ª Escuela de Jefes del Paraguay (JM) -  “Conqu' => '2ª Escuela de Jefes del Paraguay (JM) -  “Conqu',
      ),
      'last' => 
      array (
        'p629' => 'Óscar Iván Saldívar',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::url1' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url1',
    'label' => 'URL 1',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'url1',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'URL 1',
      'required' => false,
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Books\\Form\\PublicationForm::url1Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url1Label',
    'label' => 'URL 1 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'url1Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'URL 1 Label',
      'required' => false,
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 5,
      'digest' => 'e432183b4bcea623316238bdf982269b',
      'first' => 
      array (
        'Borrow' => 'Borrow',
        'Download' => 'Download',
        'Purchase' => 'Purchase',
      ),
      'last' => 
      array (
        'Information' => 'Information',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::url2' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url2',
    'label' => 'URL 2',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'url2',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'URL 2',
      'required' => false,
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Books\\Form\\PublicationForm::url2Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url2Label',
    'label' => 'URL 2 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'url2Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'URL 2 Label',
      'required' => false,
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 5,
      'digest' => 'e432183b4bcea623316238bdf982269b',
      'first' => 
      array (
        'Borrow' => 'Borrow',
        'Download' => 'Download',
        'Purchase' => 'Purchase',
      ),
      'last' => 
      array (
        'Information' => 'Information',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::url3' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url3',
    'label' => 'URL 3',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'url3',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'URL 3',
      'required' => false,
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Books\\Form\\PublicationForm::url3Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url3Label',
    'label' => 'URL 3 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'url3Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'URL 3 Label',
      'required' => false,
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 5,
      'digest' => 'e432183b4bcea623316238bdf982269b',
      'first' => 
      array (
        'Borrow' => 'Borrow',
        'Download' => 'Download',
        'Purchase' => 'Purchase',
      ),
      'last' => 
      array (
        'Information' => 'Information',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationForm::volumeNumber' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'volumeNumber',
    'label' => 'Volume number',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '25',
      'name' => 'volumeNumber',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Volume number',
      'required' => false,
    ),
  ),
  'Books\\Form\\PublicationsSearchForm::inLanguage' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'inLanguage',
    'label' => 'Languages',
    'value' => NULL,
    'attributes' => 
    array (
      'multiple' => true,
      'name' => 'inLanguage',
      'placeholder' => 'Languages',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Languages',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 639,
      'digest' => '7a4b8b0ee3a9dea96b267fadeda02a9e',
      'first' => 
      array (
        'aa' => 'Afar',
        'ab' => 'Abkhazian',
        'af' => 'Afrikaans',
      ),
      'last' => 
      array (
        'zun' => 'Zuni',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\PublicationsSearchForm::includeDataSources' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'includeDataSources',
    'label' => 'Show data sources?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'includeDataSources',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Show data sources?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\PublicationsSearchForm::search' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'search',
    'label' => '',
    'value' => NULL,
    'attributes' => 
    array (
      'class' => 'input-lg search-query',
      'name' => 'search',
      'placeholder' => 'Search Catalogs',
      'required' => false,
      'size' => 50,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => '',
    ),
  ),
  'Books\\Form\\PublicationsSearchForm::showEditionsSeparately' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'showEditionsSeparately',
    'label' => 'Show editions separately?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'showEditionsSeparately',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Show editions separately?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\PublicationsSearchForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Search',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\SearchForm::category' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'category',
    'label' => '',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'category',
      'required' => false,
      'size' => 50,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => '',
    ),
  ),
  'Books\\Form\\SearchForm::collectionId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'collectionId',
    'label' => 'Collection',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'collectionId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Collection',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\SearchForm::inLanguage' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'inLanguage',
    'label' => 'Language',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'inLanguage',
      'required' => false,
      'size' => 2,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Language',
    ),
  ),
  'Books\\Form\\SearchForm::libraryId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'libraryId',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'libraryId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
      'required' => true,
    ),
  ),
  'Books\\Form\\SearchForm::search' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'search',
    'label' => '',
    'value' => NULL,
    'attributes' => 
    array (
      'class' => 'input-lg search-query',
      'name' => 'search',
      'placeholder' => 'Search',
      'required' => false,
      'size' => 50,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => '',
    ),
  ),
  'Books\\Form\\SearchForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Search',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::inLanguage' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'inLanguage',
    'label' => 'Original language',
    'value' => 'en',
    'attributes' => 
    array (
      'name' => 'inLanguage',
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Original language',
      'unselected_value' => '',
      'value_options' => 
      array (
        'de' => 'German',
        'en' => 'English',
        'es' => 'Spanish',
        'it' => 'Italian',
        'pt' => 'Portuguese',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 5,
      'digest' => 'e564cda112ef411950d1525d2321d2ad',
      'first' => 
      array (
        'de' => 'German',
        'en' => 'English',
        'pt' => 'Portuguese',
      ),
      'last' => 
      array (
        'it' => 'Italian',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\TextForm::isDraft' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isDraft',
    'label' => 'Is draft?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'isDraft',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Is draft?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Books\\Form\\TextForm::kind' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'kind',
    'label' => NULL,
    'value' => 'jk-text',
    'attributes' => 
    array (
      'name' => 'kind',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::markdownText' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'markdownText',
    'label' => 'Text',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'markdownText',
      'required' => false,
      'rows' => 12,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Text',
    ),
  ),
  'Books\\Form\\TextForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Books\\Form\\TextForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::tags' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'tags',
    'label' => 'Tags',
    'value' => NULL,
    'attributes' => 
    array (
      'multiple' => true,
      'name' => 'tags',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Tags',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\TextForm::title' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'title',
    'label' => 'Title',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '200',
      'name' => 'title',
      'required' => true,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Title',
    ),
  ),
  'Books\\Form\\TextSearchForm::inLanguage' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'inLanguage',
    'label' => 'Language',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'inLanguage',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Language',
      'value_options' => 
      array (
        'aa' => 'Afar',
        'ab' => 'Abkhazian',
        'ace' => 'Acehnese',
        'ach' => 'Acoli',
        'ada' => 'Adangme',
        'ady' => 'Adyghe',
        'ae' => 'Avestan',
        'aeb' => 'Tunisian Arabic',
        'af' => 'Afrikaans',
        'afh' => 'Afrihili',
        'agq' => 'Aghem',
        'ain' => 'Ainu',
        'ak' => 'Akan',
        'akk' => 'Akkadian',
        'akz' => 'Alabama',
        'ale' => 'Aleut',
        'aln' => 'Gheg Albanian',
        'alt' => 'Southern Altai',
        'am' => 'Amharic',
        'an' => 'Aragonese',
        'ang' => 'Old English',
        'ann' => 'Obolo',
        'anp' => 'Angika',
        'ar' => 'Arabic',
        'arc' => 'Aramaic',
        'arn' => 'Mapuche',
        'aro' => 'Araona',
        'arp' => 'Arapaho',
        'arq' => 'Algerian Arabic',
        'ars' => 'Najdi Arabic',
        'arw' => 'Arawak',
        'ary' => 'Moroccan Arabic',
        'arz' => 'Egyptian Arabic',
        'as' => 'Assamese',
        'asa' => 'Asu',
        'ase' => 'American Sign Language',
        'ast' => 'Asturian',
        'atj' => 'Atikamekw',
        'av' => 'Avaric',
        'avk' => 'Kotava',
        'awa' => 'Awadhi',
        'ay' => 'Aymara',
        'az' => 'Azerbaijani',
        'ba' => 'Bashkir',
        'bal' => 'Baluchi',
        'ban' => 'Balinese',
        'bar' => 'Bavarian',
        'bas' => 'Basaa',
        'bax' => 'Bamun',
        'bbc' => 'Batak Toba',
        'bbj' => 'Ghomala',
        'be' => 'Belarusian',
        'bej' => 'Beja',
        'bem' => 'Bemba',
        'bew' => 'Betawi',
        'bez' => 'Bena',
        'bfd' => 'Bafut',
        'bfq' => 'Badaga',
        'bg' => 'Bulgarian',
        'bgc' => 'Haryanvi',
        'bgn' => 'Western Balochi',
        'bh' => 'Bihari languages',
        'bho' => 'Bhojpuri',
        'bi' => 'Bislama',
        'bik' => 'Bikol',
        'bin' => 'Bini',
        'bjn' => 'Banjar',
        'bkm' => 'Kom',
        'bla' => 'Siksiká',
        'blo' => 'Anii',
        'blt' => 'Tai Dam',
        'bm' => 'Bambara',
        'bn' => 'Bengali',
        'bo' => 'Tibetan',
        'bpy' => 'Bishnupriya',
        'bqi' => 'Bakhtiari',
        'br' => 'Breton',
        'bra' => 'Braj',
        'brh' => 'Brahui',
        'brx' => 'Bodo',
        'bs' => 'Bosnian',
        'bss' => 'Akoose',
        'bua' => 'Buriat',
        'bug' => 'Buginese',
        'bum' => 'Bulu',
        'byn' => 'Blin',
        'byv' => 'Medumba',
        'ca' => 'Catalan; Valencian',
        'cad' => 'Caddo',
        'car' => 'Carib',
        'cay' => 'Cayuga',
        'cch' => 'Atsam',
        'ccp' => 'Chakma',
        'ce' => 'Chechen',
        'ceb' => 'Cebuano',
        'cgg' => 'Chiga',
        'ch' => 'Chamorro',
        'chb' => 'Chibcha',
        'chg' => 'Chagatai',
        'chk' => 'Chuukese',
        'chm' => 'Mari',
        'chn' => 'Chinook Jargon',
        'cho' => 'Choctaw',
        'chp' => 'Chipewyan',
        'chr' => 'Cherokee',
        'chy' => 'Cheyenne',
        'cic' => 'Chickasaw',
        'ckb' => 'Central Kurdish',
        'clc' => 'Chilcotin',
        'co' => 'Corsican',
        'cop' => 'Coptic',
        'cps' => 'Capiznon',
        'cr' => 'Cree',
        'crg' => 'Michif',
        'crh' => 'Crimean Tatar',
        'crj' => 'Southern East Cree',
        'crk' => 'Plains Cree',
        'crl' => 'Northern East Cree',
        'crm' => 'Moose Cree',
        'crr' => 'Carolina Algonquian',
        'crs' => 'Seselwa Creole French',
        'cs' => 'Czech',
        'csb' => 'Kashubian',
        'csw' => 'Swampy Cree',
        'cu' => 'Church Slavic; Old Slavonic; Church Slavonic; Old Bulgarian; Old Church Slavonic',
        'cv' => 'Chuvash',
        'cwd' => 'Woods Cree',
        'cy' => 'Welsh',
        'da' => 'Danish',
        'dak' => 'Dakota',
        'dar' => 'Dargwa',
        'dav' => 'Taita',
        'de' => 'German',
        'del' => 'Delaware',
        'den' => 'Slave',
        'dgr' => 'Dogrib',
        'din' => 'Dinka',
        'dje' => 'Zarma',
        'doi' => 'Dogri',
        'dsb' => 'Lower Sorbian',
        'dtp' => 'Central Dusun',
        'dua' => 'Duala',
        'dum' => 'Middle Dutch',
        'dv' => 'Divehi; Dhivehi; Maldivian',
        'dyo' => 'Jola-Fonyi',
        'dyu' => 'Dyula',
        'dz' => 'Dzongkha',
        'dzg' => 'Dazaga',
        'ebu' => 'Embu',
        'ee' => 'Ewe',
        'efi' => 'Efik',
        'egl' => 'Emilian',
        'egy' => 'Ancient Egyptian',
        'eka' => 'Ekajuk',
        'el' => 'Greek, Modern (1453-)',
        'elx' => 'Elamite',
        'en' => 'English',
        'enm' => 'Middle English',
        'eo' => 'Esperanto',
        'es' => 'Spanish',
        'esu' => 'Central Yupik',
        'et' => 'Estonian',
        'eu' => 'Basque',
        'ewo' => 'Ewondo',
        'ext' => 'Extremaduran',
        'fa' => 'Persian',
        'fan' => 'Fang',
        'fat' => 'Fanti',
        'ff' => 'Fulah',
        'fi' => 'Finnish',
        'fil' => 'Filipino',
        'fit' => 'Tornedalen Finnish',
        'fj' => 'Fijian',
        'fo' => 'Faroese',
        'fon' => 'Fon',
        'fr' => 'French',
        'frc' => 'Cajun French',
        'frm' => 'Middle French',
        'fro' => 'Old French',
        'frp' => 'Arpitan',
        'frr' => 'Northern Frisian',
        'frs' => 'Eastern Frisian',
        'fur' => 'Friulian',
        'fy' => 'Western Frisian',
        'ga' => 'Irish',
        'gaa' => 'Ga',
        'gag' => 'Gagauz',
        'gan' => 'Gan Chinese',
        'gay' => 'Gayo',
        'gba' => 'Gbaya',
        'gbz' => 'Zoroastrian Dari',
        'gd' => 'Gaelic; Scottish Gaelic',
        'gez' => 'Geez',
        'gil' => 'Gilbertese',
        'gl' => 'Galician',
        'glk' => 'Gilaki',
        'gmh' => 'Middle High German',
        'gn' => 'Guarani',
        'goh' => 'Old High German',
        'gon' => 'Gondi',
        'gor' => 'Gorontalo',
        'got' => 'Gothic',
        'grb' => 'Grebo',
        'grc' => 'Ancient Greek',
        'gsw' => 'Swiss German',
        'gu' => 'Gujarati',
        'guc' => 'Wayuu',
        'gur' => 'Frafra',
        'guz' => 'Gusii',
        'gv' => 'Manx',
        'gwi' => 'Gwichʼin',
        'ha' => 'Hausa',
        'hai' => 'Haida',
        'hak' => 'Hakka Chinese',
        'haw' => 'Hawaiian',
        'hax' => 'Southern Haida',
        'hdn' => 'Northern Haida',
        'he' => 'Hebrew',
        'hi' => 'Hindi',
        'hif' => 'Fiji Hindi',
        'hil' => 'Hiligaynon',
        'hit' => 'Hittite',
        'hmn' => 'Hmong',
        'hnj' => 'Hmong Njua',
        'ho' => 'Hiri Motu',
        'hr' => 'Croatian',
        'hsb' => 'Upper Sorbian',
        'hsn' => 'Xiang Chinese',
        'ht' => 'Haitian; Haitian Creole',
        'hu' => 'Hungarian',
        'hup' => 'Hupa',
        'hur' => 'Halkomelem',
        'hy' => 'Armenian',
        'hz' => 'Herero',
        'ia' => 'Interlingua (International Auxiliary Language Association)',
        'iba' => 'Iban',
        'ibb' => 'Ibibio',
        'id' => 'Indonesian',
        'ie' => 'Interlingue; Occidental',
        'ig' => 'Igbo',
        'ii' => 'Sichuan Yi; Nuosu',
        'ik' => 'Inupiaq',
        'ike' => 'Eastern Canadian Inuktitut',
        'ikt' => 'Western Canadian Inuktitut',
        'ilo' => 'Iloko',
        'inh' => 'Ingush',
        'io' => 'Ido',
        'is' => 'Icelandic',
        'it' => 'Italian',
        'iu' => 'Inuktitut',
        'izh' => 'Ingrian',
        'ja' => 'Japanese',
        'jam' => 'Jamaican Creole English',
        'jbo' => 'Lojban',
        'jgo' => 'Ngomba',
        'jmc' => 'Machame',
        'jpr' => 'Judeo-Persian',
        'jrb' => 'Judeo-Arabic',
        'jut' => 'Jutish',
        'jv' => 'Javanese',
        'ka' => 'Georgian',
        'kaa' => 'Kara-Kalpak',
        'kab' => 'Kabyle',
        'kac' => 'Kachin',
        'kaj' => 'Jju',
        'kam' => 'Kamba',
        'kaw' => 'Kawi',
        'kbd' => 'Kabardian',
        'kbl' => 'Kanembu',
        'kcg' => 'Tyap',
        'kde' => 'Makonde',
        'kea' => 'Kabuverdianu',
        'ken' => 'Kenyang',
        'kfo' => 'Koro',
        'kg' => 'Kongo',
        'kgp' => 'Kaingang',
        'kha' => 'Khasi',
        'kho' => 'Khotanese',
        'khq' => 'Koyra Chiini',
        'khw' => 'Khowar',
        'ki' => 'Kikuyu; Gikuyu',
        'kiu' => 'Kirmanjki',
        'kj' => 'Kuanyama; Kwanyama',
        'kk' => 'Kazakh',
        'kkj' => 'Kako',
        'kl' => 'Kalaallisut; Greenlandic',
        'kln' => 'Kalenjin',
        'km' => 'Central Khmer',
        'kmb' => 'Kimbundu',
        'kn' => 'Kannada',
        'ko' => 'Korean',
        'koi' => 'Komi-Permyak',
        'kok' => 'Konkani',
        'kos' => 'Kosraean',
        'kpe' => 'Kpelle',
        'kr' => 'Kanuri',
        'krc' => 'Karachay-Balkar',
        'kri' => 'Krio',
        'krj' => 'Kinaray-a',
        'krl' => 'Karelian',
        'kru' => 'Kurukh',
        'ks' => 'Kashmiri',
        'ksb' => 'Shambala',
        'ksf' => 'Bafia',
        'ksh' => 'Colognian',
        'ku' => 'Kurdish',
        'kum' => 'Kumyk',
        'kut' => 'Kutenai',
        'kv' => 'Komi',
        'kw' => 'Cornish',
        'kwk' => 'Kwakʼwala',
        'kxv' => 'Kuvi',
        'ky' => 'Kirghiz; Kyrgyz',
        'la' => 'Latin',
        'lad' => 'Ladino',
        'lag' => 'Langi',
        'lah' => 'Western Panjabi',
        'lam' => 'Lamba',
        'lb' => 'Luxembourgish; Letzeburgesch',
        'lez' => 'Lezghian',
        'lfn' => 'Lingua Franca Nova',
        'lg' => 'Ganda',
        'li' => 'Limburgan; Limburger; Limburgish',
        'lij' => 'Ligurian',
        'lil' => 'Lillooet',
        'liv' => 'Livonian',
        'lkt' => 'Lakota',
        'lmo' => 'Lombard',
        'ln' => 'Lingala',
        'lo' => 'Lao',
        'lol' => 'Mongo',
        'lou' => 'Louisiana Creole',
        'loz' => 'Lozi',
        'lrc' => 'Northern Luri',
        'lsm' => 'Saamia',
        'lt' => 'Lithuanian',
        'ltg' => 'Latgalian',
        'lu' => 'Luba-Katanga',
        'lua' => 'Luba-Lulua',
        'lui' => 'Luiseno',
        'lun' => 'Lunda',
        'luo' => 'Luo',
        'lus' => 'Mizo',
        'luy' => 'Luyia',
        'lv' => 'Latvian',
        'lzh' => 'Literary Chinese',
        'lzz' => 'Laz',
        'mad' => 'Madurese',
        'maf' => 'Mafa',
        'mag' => 'Magahi',
        'mai' => 'Maithili',
        'mak' => 'Makasar',
        'man' => 'Mandingo',
        'mas' => 'Masai',
        'mde' => 'Maba',
        'mdf' => 'Moksha',
        'mdr' => 'Mandar',
        'men' => 'Mende',
        'mer' => 'Meru',
        'mfe' => 'Morisyen',
        'mg' => 'Malagasy',
        'mga' => 'Middle Irish',
        'mgh' => 'Makhuwa-Meetto',
        'mgo' => 'Metaʼ',
        'mh' => 'Marshallese',
        'mi' => 'Maori',
        'mic' => 'Mi\'kmaw',
        'min' => 'Minangkabau',
        'mk' => 'Macedonian',
        'ml' => 'Malayalam',
        'mn' => 'Mongolian',
        'mnc' => 'Manchu',
        'mni' => 'Manipuri',
        'moe' => 'Innu-aimun',
        'moh' => 'Mohawk',
        'mos' => 'Mossi',
        'mr' => 'Marathi',
        'mrj' => 'Western Mari',
        'ms' => 'Malay',
        'mt' => 'Maltese',
        'mua' => 'Mundang',
        'mul' => 'Multiple languages',
        'mus' => 'Muscogee',
        'mwl' => 'Mirandese',
        'mwr' => 'Marwari',
        'mwv' => 'Mentawai',
        'my' => 'Burmese',
        'mye' => 'Myene',
        'myv' => 'Erzya',
        'mzn' => 'Mazanderani',
        'na' => 'Nauru',
        'nan' => 'Min Nan Chinese',
        'nap' => 'Neapolitan',
        'naq' => 'Nama',
        'nb' => 'Bokmål, Norwegian; Norwegian Bokmål',
        'nd' => 'Ndebele, North; North Ndebele',
        'nds' => 'Low German',
        'ne' => 'Nepali',
        'new' => 'Newari',
        'ng' => 'Ndonga',
        'nia' => 'Nias',
        'niu' => 'Niuean',
        'njo' => 'Ao Naga',
        'nl' => 'Dutch; Flemish',
        'nmg' => 'Kwasio',
        'nn' => 'Norwegian Nynorsk; Nynorsk, Norwegian',
        'nnh' => 'Ngiemboon',
        'no' => 'Norwegian',
        'nog' => 'Nogai',
        'non' => 'Old Norse',
        'nov' => 'Novial',
        'nqo' => 'N’Ko',
        'nr' => 'Ndebele, South; South Ndebele',
        'nso' => 'Northern Sotho',
        'nus' => 'Nuer',
        'nv' => 'Navajo; Navaho',
        'nwc' => 'Classical Newari',
        'ny' => 'Chichewa; Chewa; Nyanja',
        'nym' => 'Nyamwezi',
        'nyn' => 'Nyankole',
        'nyo' => 'Nyoro',
        'nzi' => 'Nzima',
        'oc' => 'Occitan (post 1500); Provençal',
        'oj' => 'Ojibwa',
        'ojb' => 'Northwestern Ojibwa',
        'ojc' => 'Central Ojibwa',
        'ojg' => 'Eastern Ojibwa',
        'ojs' => 'Oji-Cree',
        'ojw' => 'Western Ojibwa',
        'oka' => 'Okanagan',
        'om' => 'Oromo',
        'or' => 'Oriya',
        'os' => 'Ossetian; Ossetic',
        'osa' => 'Osage',
        'ota' => 'Ottoman Turkish',
        'pa' => 'Panjabi; Punjabi',
        'pag' => 'Pangasinan',
        'pal' => 'Pahlavi',
        'pam' => 'Pampanga',
        'pap' => 'Papiamento',
        'pau' => 'Palauan',
        'pcd' => 'Picard',
        'pcm' => 'Nigerian Pidgin',
        'pdc' => 'Pennsylvania German',
        'pdt' => 'Plautdietsch',
        'peo' => 'Old Persian',
        'pfl' => 'Palatine German',
        'phn' => 'Phoenician',
        'pi' => 'Pali',
        'pis' => 'Pijin',
        'pl' => 'Polish',
        'pms' => 'Piedmontese',
        'pnt' => 'Pontic',
        'pon' => 'Pohnpeian',
        'pqm' => 'Maliseet-Passamaquoddy',
        'prg' => 'Prussian',
        'pro' => 'Old Provençal',
        'ps' => 'Pushto; Pashto',
        'pt' => 'Portuguese',
        'qu' => 'Quechua',
        'quc' => 'Kʼicheʼ',
        'qug' => 'Chimborazo Highland Quichua',
        'raj' => 'Rajasthani',
        'rap' => 'Rapanui',
        'rar' => 'Rarotongan',
        'rgn' => 'Romagnol',
        'rhg' => 'Rohingya',
        'rif' => 'Riffian',
        'rm' => 'Romansh',
        'rn' => 'Rundi',
        'ro' => 'Romanian; Moldavian; Moldovan',
        'rof' => 'Rombo',
        'rom' => 'Romany',
        'rtm' => 'Rotuman',
        'ru' => 'Russian',
        'rue' => 'Rusyn',
        'rug' => 'Roviana',
        'rup' => 'Aromanian',
        'rw' => 'Kinyarwanda',
        'rwk' => 'Rwa',
        'sa' => 'Sanskrit',
        'sad' => 'Sandawe',
        'sah' => 'Yakut',
        'sam' => 'Samaritan Aramaic',
        'saq' => 'Samburu',
        'sas' => 'Sasak',
        'sat' => 'Santali',
        'saz' => 'Saurashtra',
        'sba' => 'Ngambay',
        'sbp' => 'Sangu',
        'sc' => 'Sardinian',
        'scn' => 'Sicilian',
        'sco' => 'Scots',
        'sd' => 'Sindhi',
        'sdc' => 'Sassarese Sardinian',
        'sdh' => 'Southern Kurdish',
        'se' => 'Northern Sami',
        'see' => 'Seneca',
        'seh' => 'Sena',
        'sei' => 'Seri',
        'sel' => 'Selkup',
        'ses' => 'Koyraboro Senni',
        'sg' => 'Sango',
        'sga' => 'Old Irish',
        'sgs' => 'Samogitian',
        'shi' => 'Tachelhit',
        'shn' => 'Shan',
        'shu' => 'Chadian Arabic',
        'si' => 'Sinhala; Sinhalese',
        'sid' => 'Sidamo',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'slh' => 'Southern Lushootseed',
        'sli' => 'Lower Silesian',
        'sly' => 'Selayar',
        'sm' => 'Samoan',
        'sma' => 'Southern Sami',
        'smj' => 'Lule Sami',
        'smn' => 'Inari Sami',
        'sms' => 'Skolt Sami',
        'sn' => 'Shona',
        'snk' => 'Soninke',
        'so' => 'Somali',
        'sog' => 'Sogdien',
        'sq' => 'Albanian',
        'sr' => 'Serbian',
        'srn' => 'Sranan Tongo',
        'srr' => 'Serer',
        'ss' => 'Swati',
        'ssy' => 'Saho',
        'st' => 'Sotho, Southern',
        'stq' => 'Saterland Frisian',
        'str' => 'Straits Salish',
        'su' => 'Sundanese',
        'suk' => 'Sukuma',
        'sus' => 'Susu',
        'sux' => 'Sumerian',
        'sv' => 'Swedish',
        'sw' => 'Swahili',
        'swb' => 'Comorian',
        'syc' => 'Classical Syriac',
        'syr' => 'Syriac',
        'szl' => 'Silesian',
        'ta' => 'Tamil',
        'tce' => 'Southern Tutchone',
        'tcy' => 'Tulu',
        'te' => 'Telugu',
        'tem' => 'Timne',
        'teo' => 'Teso',
        'ter' => 'Tereno',
        'tet' => 'Tetum',
        'tg' => 'Tajik',
        'tgx' => 'Tagish',
        'th' => 'Thai',
        'tht' => 'Tahltan',
        'ti' => 'Tigrinya',
        'tig' => 'Tigre',
        'tiv' => 'Tiv',
        'tk' => 'Turkmen',
        'tkl' => 'Tokelau',
        'tkr' => 'Tsakhur',
        'tl' => 'Tagalog',
        'tlh' => 'Klingon',
        'tli' => 'Tlingit',
        'tly' => 'Talysh',
        'tmh' => 'Tamashek',
        'tn' => 'Tswana',
        'to' => 'Tonga (Tonga Islands)',
        'tog' => 'Nyasa Tonga',
        'tok' => 'Toki Pona',
        'tpi' => 'Tok Pisin',
        'tr' => 'Turkish',
        'tru' => 'Turoyo',
        'trv' => 'Taroko',
        'trw' => 'Torwali',
        'ts' => 'Tsonga',
        'tsd' => 'Tsakonian',
        'tsi' => 'Tsimshian',
        'tt' => 'Tatar',
        'ttm' => 'Northern Tutchone',
        'ttt' => 'Muslim Tat',
        'tum' => 'Tumbuka',
        'tvl' => 'Tuvalu',
        'tw' => 'Twi',
        'twq' => 'Tasawaq',
        'ty' => 'Tahitian',
        'tyv' => 'Tuvinian',
        'tzm' => 'Central Atlas Tamazight',
        'udm' => 'Udmurt',
        'ug' => 'Uighur; Uyghur',
        'uga' => 'Ugaritic',
        'uk' => 'Ukrainian',
        'umb' => 'Umbundu',
        'ur' => 'Urdu',
        'uz' => 'Uzbek',
        'vai' => 'Vai',
        've' => 'Venda',
        'vec' => 'Venetian',
        'vep' => 'Veps',
        'vi' => 'Vietnamese',
        'vls' => 'West Flemish',
        'vmf' => 'Main-Franconian',
        'vmw' => 'Makhuwa',
        'vo' => 'Volapük',
        'vot' => 'Votic',
        'vro' => 'Võro',
        'vun' => 'Vunjo',
        'wa' => 'Walloon',
        'wae' => 'Walser',
        'wal' => 'Wolaytta',
        'war' => 'Waray',
        'was' => 'Washo',
        'wbp' => 'Warlpiri',
        'wo' => 'Wolof',
        'wuu' => 'Wu Chinese',
        'xal' => 'Kalmyk',
        'xh' => 'Xhosa',
        'xmf' => 'Mingrelian',
        'xnr' => 'Kangri',
        'xog' => 'Soga',
        'yao' => 'Yao',
        'yap' => 'Yapese',
        'yav' => 'Yangben',
        'ybb' => 'Yemba',
        'yi' => 'Yiddish',
        'yo' => 'Yoruba',
        'yrl' => 'Nheengatu',
        'yue' => 'Cantonese',
        'za' => 'Zhuang; Chuang',
        'zap' => 'Zapotec',
        'zbl' => 'Blissymbols',
        'zea' => 'Zeelandic',
        'zen' => 'Zenaga',
        'zgh' => 'Standard Moroccan Tamazight',
        'zh' => 'Chinese',
        'zu' => 'Zulu',
        'zun' => 'Zuni',
        'zxx' => 'No linguistic content',
        'zza' => 'Zaza',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 639,
      'digest' => '7a4b8b0ee3a9dea96b267fadeda02a9e',
      'first' => 
      array (
        'aa' => 'Afar',
        'ab' => 'Abkhazian',
        'af' => 'Afrikaans',
      ),
      'last' => 
      array (
        'zun' => 'Zuni',
      ),
    ),
    'emptyOption' => '',
  ),
  'Books\\Form\\TextSearchForm::search' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'search',
    'label' => '',
    'value' => NULL,
    'attributes' => 
    array (
      'class' => 'input-lg search-query',
      'name' => 'search',
      'placeholder' => 'Search',
      'required' => true,
      'size' => 50,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => '',
    ),
  ),
  'Books\\Form\\TextSearchForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Search',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'JTranslate\\Form\\DeletePhraseForm::cancel' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Button',
    'name' => 'cancel',
    'label' => NULL,
    'value' => 'Cancel',
    'attributes' => 
    array (
      'data-dismiss' => 'modal',
      'id' => 'submit',
      'name' => 'cancel',
      'type' => 'button',
    ),
    'options' => 
    array (
    ),
  ),
  'JTranslate\\Form\\DeletePhraseForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'JTranslate\\Form\\DeletePhraseForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Delete',
    'attributes' => 
    array (
      'class' => 'btn-danger',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::de_DE' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'de_DE',
    'label' => 'German (Germany)',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'de_DE',
      'rows' => 2,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'German (Germany)',
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::de_DEId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'de_DEId',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'de_DEId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::en_US' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'en_US',
    'label' => 'English (United States)',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'en_US',
      'rows' => 2,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'English (United States)',
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::en_USId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'en_USId',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'en_USId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::es_ES' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'es_ES',
    'label' => 'Spanish (Spain)',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'es_ES',
      'rows' => 2,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Spanish (Spain)',
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::es_ESId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'es_ESId',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'es_ESId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::it_IT' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'it_IT',
    'label' => 'Italian (Italy)',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'it_IT',
      'rows' => 2,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Italian (Italy)',
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::it_ITId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'it_ITId',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'it_ITId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::phrase' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'phrase',
    'label' => 'Phrase',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'phrase',
      'readonly' => true,
      'rows' => 2,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Phrase',
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::phraseId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'phraseId',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'phraseId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::pt_BR' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'pt_BR',
    'label' => 'Portuguese (Brazil)',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'pt_BR',
      'rows' => 2,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Portuguese (Brazil)',
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::pt_BRId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'pt_BRId',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'pt_BRId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
    ),
    'csrfValidatorOptions' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'JUser\\Form\\CreateRoleForm::isDefault' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isDefault',
    'label' => 'Automatically give to new users?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isDefault',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Automatically give to new users?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'JUser\\Form\\CreateRoleForm::name' => 
  array (
    'class' => 'Laminas\\Form\\Element',
    'name' => 'name',
    'label' => 'Role name',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'name',
      'size' => '30',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Role name',
    ),
  ),
  'JUser\\Form\\CreateRoleForm::parentId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'parentId',
    'label' => 'Parent',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'parentId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Parent',
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 45,
      'digest' => 'd2f2651cf8ce8b3743b05fa5714487fa',
      'first' => 
      array (
        1 => 'administrator (child of user)',
        40 => 'bib_user',
        41 => 'bib_administrator (child of bib_user)',
      ),
      'last' => 
      array (
        43 => 'view_changes',
      ),
    ),
    'emptyOption' => '',
  ),
  'JUser\\Form\\CreateRoleForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
    ),
    'csrfValidatorOptions' => 
    array (
    ),
  ),
  'JUser\\Form\\CreateRoleForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'JUser\\Form\\DeleteUserForm::cancel' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Button',
    'name' => 'cancel',
    'label' => NULL,
    'value' => 'Cancel',
    'attributes' => 
    array (
      'data-dismiss' => 'modal',
      'id' => 'cancel',
      'name' => 'cancel',
      'type' => 'button',
    ),
    'options' => 
    array (
    ),
  ),
  'JUser\\Form\\DeleteUserForm::delete' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'delete',
    'label' => NULL,
    'value' => 'Delete',
    'attributes' => 
    array (
      'class' => 'btn-danger',
      'id' => 'submit',
      'name' => 'delete',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'JUser\\Form\\DeleteUserForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
    ),
    'csrfValidatorOptions' => 
    array (
    ),
  ),
  'JUser\\Form\\DeleteUserForm::userId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'userId',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'userId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::active' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'active',
    'label' => 'Active',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'active',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Active',
      'unchecked_value' => '0',
      'use_hidden_element' => false,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => false,
  ),
  'JUser\\Form\\EditUserForm::displayName' => 
  array (
    'class' => 'Laminas\\Form\\Element',
    'name' => 'displayName',
    'label' => 'Display Name',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'displayName',
      'size' => '50',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Display Name',
    ),
  ),
  'JUser\\Form\\EditUserForm::email' => 
  array (
    'class' => 'Laminas\\Form\\Element',
    'name' => 'email',
    'label' => 'Email',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'email',
      'size' => '50',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Email',
    ),
  ),
  'JUser\\Form\\EditUserForm::emailVerified' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'emailVerified',
    'label' => 'Email verified',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'emailVerified',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Email verified',
      'unchecked_value' => '0',
      'use_hidden_element' => false,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => false,
  ),
  'JUser\\Form\\EditUserForm::isMultiPersonUser' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isMultiPersonUser',
    'label' => 'Multi-person user?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isMultiPersonUser',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Multi-person user?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'JUser\\Form\\EditUserForm::personId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'personId',
    'label' => 'Person reference',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'personId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Person reference',
    ),
    'valueOptions' => 
    array (
      'count' => 325,
      'digest' => 'd5d786e6b2cd20f22ce850baf06cd29e',
      'first' => 
      array (
        630 => 'Abella, Santiago',
        633 => 'Abarca Vallejos, Sergio Franco Alexander',
        658 => 'Abud Sittler, Christián José',
      ),
      'last' => 
      array (
        529 => 'Álamos, Victor',
      ),
    ),
    'emptyOption' => '',
  ),
  'JUser\\Form\\EditUserForm::rolesList' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'rolesList',
    'label' => 'Roles',
    'value' => NULL,
    'attributes' => 
    array (
      'multiple' => 'multiple',
      'name' => 'rolesList',
      'type' => 'select',
    ),
    'options' => 
    array (
      'label' => 'Roles',
    ),
    'valueOptions' => 
    array (
      'count' => 45,
      'digest' => 'd2f2651cf8ce8b3743b05fa5714487fa',
      'first' => 
      array (
        1 => 'administrator (child of user)',
        40 => 'bib_user',
        41 => 'bib_administrator (child of bib_user)',
      ),
      'last' => 
      array (
        43 => 'view_changes',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'JUser\\Form\\EditUserForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
    ),
    'csrfValidatorOptions' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::userId' => 
  array (
    'class' => 'Laminas\\Form\\Element',
    'name' => 'userId',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'userId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::username' => 
  array (
    'class' => 'Laminas\\Form\\Element',
    'name' => 'username',
    'label' => 'Username',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'username',
      'size' => '30',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Username',
    ),
  ),
  'JUser\\Form\\IssueApiTokenForm::issue' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'issue',
    'label' => NULL,
    'value' => 'Issue token',
    'attributes' => 
    array (
      'class' => 'btn btn-primary',
      'id' => 'submit',
      'name' => 'issue',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'JUser\\Form\\IssueApiTokenForm::label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'label',
    'label' => 'What is this token for?',
    'value' => NULL,
    'attributes' => 
    array (
      'class' => 'form-control',
      'maxlength' => 100,
      'name' => 'label',
      'placeholder' => 'e.g. nightly shrine enrichment',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'What is this token for?',
    ),
  ),
  'JUser\\Form\\IssueApiTokenForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
    ),
    'csrfValidatorOptions' => 
    array (
    ),
  ),
  'JUser\\Form\\LoginForm::email' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Email',
    'name' => 'email',
    'label' => 'Email address',
    'value' => NULL,
    'attributes' => 
    array (
      'autocomplete' => 'email',
      'autofocus' => true,
      'id' => 'email',
      'name' => 'email',
      'placeholder' => 'you@example.com',
      'required' => true,
      'size' => '40',
      'type' => 'email',
    ),
    'options' => 
    array (
      'label' => 'Email address',
    ),
  ),
  'JUser\\Form\\LoginForm::redirect' => 
  array (
    'class' => 'Laminas\\Form\\Element',
    'name' => 'redirect',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'redirect',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'JUser\\Form\\LoginForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
    ),
    'csrfValidatorOptions' => 
    array (
    ),
  ),
  'JUser\\Form\\LoginForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Send me a sign-in link',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'JUser\\Form\\RevokeApiTokenForm::revoke' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'revoke',
    'label' => NULL,
    'value' => 'Revoke',
    'attributes' => 
    array (
      'class' => 'btn btn-danger btn-xs',
      'name' => 'revoke',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'JUser\\Form\\RevokeApiTokenForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
    ),
    'csrfValidatorOptions' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::associationCountry' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'associationCountry',
    'label' => 'Association country',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'associationCountry',
      'type' => 'select',
    ),
    'options' => 
    array (
      'column-size' => 'md-4',
      'empty_option' => '',
      'label' => 'Association country',
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 41,
      'digest' => 'b762e820d0bf6dfbc9474fa680a7df70',
      'first' => 
      array (
        'AR' => 'Argentina',
        'AT' => 'Austria',
        'AU' => 'Australia',
      ),
      'last' => 
      array (
        'UY' => 'Uruguay',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::associationKind' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'associationKind',
    'label' => 'Association type',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'associationKind',
      'type' => 'select',
    ),
    'options' => 
    array (
      'column-size' => 'md-4',
      'empty_option' => '',
      'label' => 'Association type',
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 33,
      'digest' => 'f397513215f9781978016a7221718b08',
      'first' => 
      array (
        'sch-diocesan-pilgrim-mother' => 'Diocesan Pilgrim Mother organization',
        'sch-diocesan-pilgrim-movement' => 'Diocesan pilgrim\'s movement',
        'sch-federation-international-structure' => 'Federation international structure',
      ),
      'last' => 
      array (
        'sch-young-womens-league-branch' => 'Schoenstatt young women\'s branch',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::clear' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Button',
    'name' => 'clear',
    'label' => 'Clear form',
    'value' => NULL,
    'attributes' => 
    array (
      'class' => 'btn btn-default',
      'id' => 'clear',
      'name' => 'clear',
      'type' => 'button',
    ),
    'options' => 
    array (
      'label' => 'Clear form',
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::onlyMainRoles' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'onlyMainRoles',
    'label' => 'Only main roles?',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'onlyMainRoles',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Only main roles?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::personName' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'personName',
    'label' => 'Person name',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => 3,
      'name' => 'personName',
      'type' => 'text',
    ),
    'options' => 
    array (
      'column-size' => 'md-4 col-md-offset-4',
      'label' => 'Person name',
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::roleTitle' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'roleTitle',
    'label' => 'Role',
    'value' => NULL,
    'attributes' => 
    array (
      'id' => 'roleTitleSelect',
      'multiple' => true,
      'name' => 'roleTitle',
      'type' => 'select',
    ),
    'options' => 
    array (
      'column-size' => 'md-4',
      'empty_option' => '',
      'label' => 'Role',
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 39,
      'digest' => '76d559ac05227d19c2278a9d12025da7',
      'first' => 
      array (
        '(General or main) Secretary' => '(General or main) Secretary',
        'Assistant moderator' => 'Assistant moderator',
        'Board member' => 'Board member',
      ),
      'last' => 
      array (
        'Women\'s Federation' => 'Women\'s Federation',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::search' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'search',
    'label' => 'Multi-search',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => 3,
      'name' => 'search',
      'type' => 'text',
    ),
    'options' => 
    array (
      'column-size' => 'md-4',
      'label' => 'Multi-search',
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => 'Search',
    'value' => NULL,
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
      'label' => 'Search',
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::associationId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'associationId',
    'label' => 'Association',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'associationId',
      'required' => true,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Association',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 496,
      'digest' => '614e0a6d254b851ea56eb49ce4128a42',
      'first' => 
      array (
        322 => 'Cenacle of Bellavista',
        569 => 'Austin Caritas',
        571 => 'Casa de la Familia de Schoenstatt - Tucumán',
      ),
      'last' => 
      array (
        263 => 'Women\'s youth of Temuco',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssignmentForm::delete' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Button',
    'name' => 'delete',
    'label' => NULL,
    'value' => 'Delete',
    'attributes' => 
    array (
      'class' => 'btn-danger',
      'data-target' => '.bs-example-modal-sm',
      'data-toggle' => 'modal',
      'name' => 'delete',
      'type' => 'button',
    ),
    'options' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::endDate' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Date',
    'name' => 'endDate',
    'label' => 'End date',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => '1900-01-01',
      'name' => 'endDate',
      'required' => false,
      'step' => 'any',
      'type' => 'date',
    ),
    'options' => 
    array (
      'format' => 'Y-m-d',
      'label' => 'End date',
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::endDatePrecision' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'endDatePrecision',
    'label' => 'How precisely the end date is known',
    'value' => 'day',
    'attributes' => 
    array (
      'name' => 'endDatePrecision',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'label' => 'How precisely the end date is known',
      'value_options' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'a75572cb886cee31cda5ee477f30c413',
      'first' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
      'last' => 
      array (
        'year' => 'Year only',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Schoenstatt\\Form\\AssignmentForm::personId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'personId',
    'label' => 'Person',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'personId',
      'required' => true,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Person',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 325,
      'digest' => 'd5d786e6b2cd20f22ce850baf06cd29e',
      'first' => 
      array (
        630 => 'Abella, Santiago',
        633 => 'Abarca Vallejos, Sergio Franco Alexander',
        658 => 'Abud Sittler, Christián José',
      ),
      'last' => 
      array (
        529 => 'Álamos, Victor',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssignmentForm::roleId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'roleId',
    'label' => 'Role',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'roleId',
      'required' => true,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Role',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssignmentForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::startDate' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Date',
    'name' => 'startDate',
    'label' => 'Start date',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => '1900-01-01',
      'name' => 'startDate',
      'required' => false,
      'step' => 'any',
      'type' => 'date',
    ),
    'options' => 
    array (
      'format' => 'Y-m-d',
      'label' => 'Start date',
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::startDatePrecision' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'startDatePrecision',
    'label' => 'How precisely the start date is known',
    'value' => 'day',
    'attributes' => 
    array (
      'name' => 'startDatePrecision',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'label' => 'How precisely the start date is known',
      'value_options' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'a75572cb886cee31cda5ee477f30c413',
      'first' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
      'last' => 
      array (
        'year' => 'Year only',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Schoenstatt\\Form\\AssignmentForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::adminNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'adminNotes',
    'label' => 'Admin notes',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'adminNotes',
      'required' => false,
      'rows' => 4,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Admin notes',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::associationId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'associationId',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'associationId',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::cityState' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'cityState',
    'label' => 'City/State',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'cityState',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'City/State',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::country' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'country',
    'label' => 'Country (if not international)',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'country',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Country (if not international)',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 249,
      'digest' => '054d02752ee6095bc3bb3601e547c227',
      'first' => 
      array (
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
      ),
      'last' => 
      array (
        'AX' => 'Åland Islands',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssociationForm::email' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Email',
    'name' => 'email',
    'label' => 'Main Email',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '70',
      'name' => 'email',
      'required' => false,
      'type' => 'email',
    ),
    'options' => 
    array (
      'label' => 'Main Email',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::eventsHuman' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'eventsHuman',
    'label' => 'Mass, adoration and reconciliation schedules (free text)',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'eventsHuman',
      'placeholder' => 'Covenant mass every 3rd Sunday, daily mass every Wednesday at 7am, except in June and July. Confessions will be offered 30 minutes before every mass. Youth adoration every 1st and 3rd Friday while school is in session. Please verify on the Facebook page.',
      'required' => false,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'Please add all details available, including exceptions to the general rule. 
(Summer/winter schedules, months without mass, etc.). If you found the schedule on a webpage, include the link 
so users can double-check. Warning: this field is not translated.',
      'label' => 'Mass, adoration and reconciliation schedules (free text)',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::facebookUrl' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'facebookUrl',
    'label' => 'Facebook URL',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'facebookUrl',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'Facebook URL',
      'required' => false,
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::foundationDate' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Date',
    'name' => 'foundationDate',
    'label' => 'Foundation date',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => '1900-01-01',
      'name' => 'foundationDate',
      'required' => false,
      'step' => 'any',
      'type' => 'date',
    ),
    'options' => 
    array (
      'format' => 'Y-m-d',
      'label' => 'Foundation date',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::foundationDatePrecision' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'foundationDatePrecision',
    'label' => 'How precisely the foundation date is known',
    'value' => 'day',
    'attributes' => 
    array (
      'name' => 'foundationDatePrecision',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'label' => 'How precisely the foundation date is known',
      'value_options' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'a75572cb886cee31cda5ee477f30c413',
      'first' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
      'last' => 
      array (
        'year' => 'Year only',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Schoenstatt\\Form\\AssociationForm::geoPoint' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'geoPoint',
    'label' => 'Gps location (lat, long)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '32',
      'name' => 'geoPoint',
      'placeholder' => 'ex. 30.311108, -97.842738',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Gps location (lat, long)',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::googlePlaceId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'googlePlaceId',
    'label' => 'Google place ID',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '200',
      'name' => 'googlePlaceId',
      'placeholder' => 'ChIJj61dQgK6j4AR4GeTYWZsKWw',
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'Used for linking to the association\'s Google Place, for example, for reviews. Use the <a href="https://developers.google.com/places/place-id">Place ID finder.</a>',
      'label' => 'Google place ID',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::instagramUser' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'instagramUser',
    'label' => 'Instagram user',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '30',
      'name' => 'instagramUser',
      'placeholder' => 'ex. fr_johnsmith',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Instagram user',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::internalName' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'internalName',
    'label' => 'Name within Schoenstatt',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '200',
      'name' => 'internalName',
      'placeholder' => 'ex. Exile Shrine',
      'required' => false,
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'This is the name that will be shown to most users of the page (supposing most users are Schoenstatters). This field has no name formats as the public name field does. If the internal name would be the same as the public name, please leave blank.',
      'label' => 'Name within Schoenstatt',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::isActive' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isActive',
    'label' => 'Active',
    'value' => '1',
    'attributes' => 
    array (
      'aria-controls' => 'canoicalSuppressionGroup',
      'aria-expanded' => 'false',
      'data-target' => '#canonicalSuppressionGroup',
      'data-toggle' => 'collapse',
      'name' => 'isActive',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Active',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\AssociationForm::isAuthor' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isAuthor',
    'label' => 'Has association authored books?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isAuthor',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Has association authored books?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\AssociationForm::isInternalNameTranslateable' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isInternalNameTranslateable',
    'label' => 'Should the internal name be translated?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isInternalNameTranslateable',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Should the internal name be translated?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\AssociationForm::isLifeCommunity' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isLifeCommunity',
    'label' => 'Has lifetime membership?',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'isLifeCommunity',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Has lifetime membership?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\AssociationForm::isNameTranslateable' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isNameTranslateable',
    'label' => 'Should the name be translated?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isNameTranslateable',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Should the name be translated?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\AssociationForm::kind' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'kind',
    'label' => 'Association type',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'kind',
      'required' => true,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'label' => 'Association type',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 33,
      'digest' => 'f397513215f9781978016a7221718b08',
      'first' => 
      array (
        'sch-diocesan-pilgrim-mother' => 'Diocesan Pilgrim Mother organization',
        'sch-diocesan-pilgrim-movement' => 'Diocesan pilgrim\'s movement',
        'sch-federation-international-structure' => 'Federation international structure',
      ),
      'last' => 
      array (
        'sch-young-womens-league-branch' => 'Schoenstatt young women\'s branch',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Schoenstatt\\Form\\AssociationForm::name' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'name',
    'label' => 'Name for the public',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '200',
      'name' => 'name',
      'placeholder' => 'ex. Schoenstatt Fathers',
      'required' => true,
      'type' => 'text',
    ),
    'options' => 
    array (
      'help-block' => 'This is the name that would be published in Google Maps (if applicable). Several association types include `name formats` that insert this field within a commonly used format, for example `Schoenstatt Shrine [name]`. This simplifies mass translation, but can be overridden below.
For Schoenstatt Shrine names, please use the name of the closest city to which the shrine would be associated (for example, Tucumán), or in the case of a little known city, add the State/Province separated by a comma  (for example, Sleepy Eye, Minnesota). If there are multiple shrines in the same city, make sure to disambiguate one from the other. Try to keep names as short as possible, but avoid abbreviations. Longer names can be used for the `Name within Schoenstatt`.',
      'label' => 'Name for the public',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::openingHoursHuman' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'openingHoursHuman',
    'label' => 'Opening hours (free text)',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'openingHoursHuman',
      'placeholder' => 'Sun-Sat 9-11am, 12-8pm, except first Tuesdays of the month',
      'required' => false,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'Be as descriptive as possible, including closing days throughout the year! 
There\'s nothing worse for a pilgrim than finding a closed door.',
      'label' => 'Opening hours (free text)',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::openingHoursSpecificationJson' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'openingHoursSpecificationJson',
    'label' => 'Opening hours specification JSON (advanced users)',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'openingHoursSpecificationJson',
      'placeholder' => '{
  "friday":["09:00-12:00","13:00-18:00"],
  "saturday":["09:00-12:00","13:00-18:00"],
  "sunday":["09:00-12:00","13:00-18:00"],
  "exceptions": {
    "2016-11-11": ["09:00-12:00"],
    "2016-12-25": [],
    "01-01": []
}}',
      'required' => false,
      'rows' => 8,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'help-block' => 'Please see OpeningHours::create([...]);
at <a href="https://github.com/spatie/opening-hours" target="_blank">spatie/opening-hours</a></br>
',
      'label' => 'Opening hours specification JSON (advanced users)',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::overrideNameFormat' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'overrideNameFormat',
    'label' => 'Override name format?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'overrideNameFormat',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Override name format?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\AssociationForm::parentId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'parentId',
    'label' => 'Parent organization (for sorting purposes)',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'parentId',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Parent organization (for sorting purposes)',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 496,
      'digest' => '614e0a6d254b851ea56eb49ce4128a42',
      'first' => 
      array (
        322 => 'Cenacle of Bellavista',
        569 => 'Austin Caritas',
        571 => 'Casa de la Familia de Schoenstatt - Tucumán',
      ),
      'last' => 
      array (
        263 => 'Women\'s youth of Temuco',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssociationForm::phone1' => 
  array (
    'class' => 'SionModel\\Form\\Element\\Phone',
    'name' => 'phone1',
    'label' => 'Phone 1',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '30',
      'name' => 'phone1',
      'placeholder' => 'ex. +49 151 55555555',
      'type' => 'tel',
    ),
    'options' => 
    array (
      'help-block' => 'Please begin with a \'+\' followed by the country code.',
      'label' => 'Phone 1',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::phone1Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'phone1Label',
    'label' => 'Phone 1 label (optional)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'phone1Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Phone 1 label (optional)',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Fax' => 'Fax',
        'Movement house' => 'Movement house',
        'Office' => 'Office',
        'Only WhatsApp' => 'Only WhatsApp',
        'Parish' => 'Parish',
        'Personal house phone' => 'Personal house phone',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 7,
      'digest' => '7486861c10a495d58b3f72a9e81f0dcb',
      'first' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Movement house' => 'Movement house',
        'Only WhatsApp' => 'Only WhatsApp',
      ),
      'last' => 
      array (
        'Fax' => 'Fax',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssociationForm::phone2' => 
  array (
    'class' => 'SionModel\\Form\\Element\\Phone',
    'name' => 'phone2',
    'label' => 'Phone 2',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '30',
      'name' => 'phone2',
      'placeholder' => 'ex. +49 151 55555555',
      'type' => 'tel',
    ),
    'options' => 
    array (
      'help-block' => 'Please begin with a \'+\' followed by the country code.',
      'label' => 'Phone 2',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::phone2Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'phone2Label',
    'label' => 'Phone 2 label (optional)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'phone2Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Phone 2 label (optional)',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Fax' => 'Fax',
        'Movement house' => 'Movement house',
        'Office' => 'Office',
        'Only WhatsApp' => 'Only WhatsApp',
        'Parish' => 'Parish',
        'Personal house phone' => 'Personal house phone',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 7,
      'digest' => '7486861c10a495d58b3f72a9e81f0dcb',
      'first' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Movement house' => 'Movement house',
        'Only WhatsApp' => 'Only WhatsApp',
      ),
      'last' => 
      array (
        'Fax' => 'Fax',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssociationForm::phone3' => 
  array (
    'class' => 'SionModel\\Form\\Element\\Phone',
    'name' => 'phone3',
    'label' => 'Phone 3',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '30',
      'name' => 'phone3',
      'placeholder' => 'ex. +49 151 55555555',
      'type' => 'tel',
    ),
    'options' => 
    array (
      'help-block' => 'Please begin with a \'+\' followed by the country code.',
      'label' => 'Phone 3',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::phone3Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'phone3Label',
    'label' => 'Phone 3 label (optional)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'phone3Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Phone 3 label (optional)',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Fax' => 'Fax',
        'Movement house' => 'Movement house',
        'Office' => 'Office',
        'Only WhatsApp' => 'Only WhatsApp',
        'Parish' => 'Parish',
        'Personal house phone' => 'Personal house phone',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 7,
      'digest' => '7486861c10a495d58b3f72a9e81f0dcb',
      'first' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Movement house' => 'Movement house',
        'Only WhatsApp' => 'Only WhatsApp',
      ),
      'last' => 
      array (
        'Fax' => 'Fax',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssociationForm::publicNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'publicNotes',
    'label' => 'Description',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'publicNotes',
      'required' => false,
      'rows' => 4,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Description',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 600,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 600,
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::street1' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'street1',
    'label' => 'Street line 1',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'street1',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Street line 1',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::street2' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'street2',
    'label' => 'Street line 2',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'street2',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Street line 2',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::timeZoneId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'timeZoneId',
    'label' => 'Time zone',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'timeZoneId',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Time zone',
      'unselected_value' => '',
      'value_options' => 
      array (
        '' => '',
        'Africa/Algiers' => '(GMT+01:00) West Central Africa',
        'Africa/Cairo' => '(GMT+03:00) Cairo',
        'Africa/Casablanca' => '(GMT+01:00) Casablanca',
        'Africa/Harare' => '(GMT+02:00) Harare',
        'Africa/Johannesburg' => '(GMT+02:00) Pretoria',
        'Africa/Monrovia' => '(GMT+00:00) Monrovia',
        'Africa/Nairobi' => '(GMT+03:00) Nairobi',
        'America/Adak' => '(GMT-09:00) Aleutian Islands',
        'America/Argentina/Buenos_Aires' => '(GMT-03:00) Buenos Aires',
        'America/Bogota' => '(GMT-05:00) Bogota',
        'America/Caracas' => '(GMT-04:00) Caracas',
        'America/Chicago' => '(GMT-05:00) Central Time (US & Canada)',
        'America/Chihuahua' => '(GMT-06:00) Chihuahua',
        'America/Denver' => '(GMT-06:00) Mountain Time (US & Canada)',
        'America/Godthab' => '(GMT-01:00) Greenland',
        'America/Guatemala' => '(GMT-06:00) Central America',
        'America/Guyana' => '(GMT-04:00) Georgetown',
        'America/Halifax' => '(GMT-03:00) Atlantic Time (Canada)',
        'America/Juneau' => '(GMT-08:00) Alaska',
        'America/La_Paz' => '(GMT-04:00) La Paz',
        'America/Lima' => '(GMT-05:00) Quito',
        'America/Los_Angeles' => '(GMT-07:00) Pacific Time (US & Canada)',
        'America/Mazatlan' => '(GMT-07:00) Mazatlan',
        'America/Mexico_City' => '(GMT-06:00) Mexico City',
        'America/Monterrey' => '(GMT-06:00) Monterrey',
        'America/Montevideo' => '(GMT-03:00) Montevideo',
        'America/New_York' => '(GMT-04:00) Eastern Time (US & Canada)',
        'America/Phoenix' => '(GMT-07:00) Arizona',
        'America/Puerto_Rico' => '(GMT-04:00) Puerto Rico',
        'America/Regina' => '(GMT-06:00) Saskatchewan',
        'America/Santiago' => '(GMT-03:00) Santiago',
        'America/Sao_Paulo' => '(GMT-03:00) Brasilia',
        'America/St_Johns' => '(GMT-02:30) Newfoundland',
        'America/Tijuana' => '(GMT-07:00) Tijuana',
        'Asia/Almaty' => '(GMT+05:00) Almaty',
        'Asia/Baghdad' => '(GMT+03:00) Baghdad',
        'Asia/Baku' => '(GMT+04:00) Baku',
        'Asia/Bangkok' => '(GMT+07:00) Hanoi',
        'Asia/Chongqing' => '(GMT+08:00) Chongqing',
        'Asia/Colombo' => '(GMT+05:30) Sri Jayawardenepura',
        'Asia/Dhaka' => '(GMT+06:00) Dhaka',
        'Asia/Hong_Kong' => '(GMT+08:00) Hong Kong',
        'Asia/Irkutsk' => '(GMT+08:00) Irkutsk',
        'Asia/Jakarta' => '(GMT+07:00) Jakarta',
        'Asia/Jerusalem' => '(GMT+03:00) Jerusalem',
        'Asia/Kabul' => '(GMT+04:30) Kabul',
        'Asia/Kamchatka' => '(GMT+12:00) Kamchatka',
        'Asia/Karachi' => '(GMT+05:00) Karachi',
        'Asia/Kathmandu' => '(GMT+05:45) Kathmandu',
        'Asia/Kolkata' => '(GMT+05:30) New Delhi',
        'Asia/Krasnoyarsk' => '(GMT+07:00) Krasnoyarsk',
        'Asia/Kuala_Lumpur' => '(GMT+08:00) Kuala Lumpur',
        'Asia/Kuwait' => '(GMT+03:00) Kuwait',
        'Asia/Magadan' => '(GMT+11:00) Magadan',
        'Asia/Muscat' => '(GMT+04:00) Muscat',
        'Asia/Novosibirsk' => '(GMT+07:00) Novosibirsk',
        'Asia/Rangoon' => '(GMT+06:30) Rangoon',
        'Asia/Riyadh' => '(GMT+03:00) Riyadh',
        'Asia/Seoul' => '(GMT+09:00) Seoul',
        'Asia/Shanghai' => '(GMT+08:00) Beijing',
        'Asia/Singapore' => '(GMT+08:00) Singapore',
        'Asia/Srednekolymsk' => '(GMT+11:00) Srednekolymsk',
        'Asia/Taipei' => '(GMT+08:00) Taipei',
        'Asia/Tashkent' => '(GMT+05:00) Tashkent',
        'Asia/Tbilisi' => '(GMT+04:00) Tbilisi',
        'Asia/Tehran' => '(GMT+03:30) Tehran',
        'Asia/Tokyo' => '(GMT+09:00) Tokyo',
        'Asia/Ulaanbaatar' => '(GMT+08:00) Ulaanbaatar',
        'Asia/Urumqi' => '(GMT+06:00) Urumqi',
        'Asia/Vladivostok' => '(GMT+10:00) Vladivostok',
        'Asia/Yakutsk' => '(GMT+09:00) Yakutsk',
        'Asia/Yekaterinburg' => '(GMT+05:00) Ekaterinburg',
        'Asia/Yerevan' => '(GMT+04:00) Yerevan',
        'Atlantic/Azores' => '(GMT+00:00) Azores',
        'Atlantic/Cape_Verde' => '(GMT-01:00) Cape Verde Is.',
        'Atlantic/South_Georgia' => '(GMT-02:00) Mid-Atlantic',
        'Australia/Adelaide' => '(GMT+09:30) Adelaide',
        'Australia/Brisbane' => '(GMT+10:00) Brisbane',
        'Australia/Darwin' => '(GMT+09:30) Darwin',
        'Australia/Hobart' => '(GMT+10:00) Hobart',
        'Australia/Melbourne' => '(GMT+10:00) Melbourne',
        'Australia/Perth' => '(GMT+08:00) Perth',
        'Australia/Sydney' => '(GMT+10:00) Sydney',
        'Etc/UTC' => '(GMT+00:00) UTC',
        'Europe/Amsterdam' => '(GMT+02:00) Amsterdam',
        'Europe/Athens' => '(GMT+03:00) Athens',
        'Europe/Belgrade' => '(GMT+02:00) Belgrade',
        'Europe/Berlin' => '(GMT+02:00) Bern',
        'Europe/Bratislava' => '(GMT+02:00) Bratislava',
        'Europe/Brussels' => '(GMT+02:00) Brussels',
        'Europe/Bucharest' => '(GMT+03:00) Bucharest',
        'Europe/Budapest' => '(GMT+02:00) Budapest',
        'Europe/Copenhagen' => '(GMT+02:00) Copenhagen',
        'Europe/Dublin' => '(GMT+01:00) Dublin',
        'Europe/Helsinki' => '(GMT+03:00) Helsinki',
        'Europe/Istanbul' => '(GMT+03:00) Istanbul',
        'Europe/Kaliningrad' => '(GMT+02:00) Kaliningrad',
        'Europe/Kiev' => '(GMT+03:00) Kyiv',
        'Europe/Lisbon' => '(GMT+01:00) Lisbon',
        'Europe/Ljubljana' => '(GMT+02:00) Ljubljana',
        'Europe/London' => '(GMT+01:00) London',
        'Europe/Madrid' => '(GMT+02:00) Madrid',
        'Europe/Minsk' => '(GMT+03:00) Minsk',
        'Europe/Moscow' => '(GMT+03:00) St. Petersburg',
        'Europe/Paris' => '(GMT+02:00) Paris',
        'Europe/Prague' => '(GMT+02:00) Prague',
        'Europe/Riga' => '(GMT+03:00) Riga',
        'Europe/Rome' => '(GMT+02:00) Rome',
        'Europe/Samara' => '(GMT+04:00) Samara',
        'Europe/Sarajevo' => '(GMT+02:00) Sarajevo',
        'Europe/Skopje' => '(GMT+02:00) Skopje',
        'Europe/Sofia' => '(GMT+03:00) Sofia',
        'Europe/Stockholm' => '(GMT+02:00) Stockholm',
        'Europe/Tallinn' => '(GMT+03:00) Tallinn',
        'Europe/Vienna' => '(GMT+02:00) Vienna',
        'Europe/Vilnius' => '(GMT+03:00) Vilnius',
        'Europe/Volgograd' => '(GMT+03:00) Volgograd',
        'Europe/Warsaw' => '(GMT+02:00) Warsaw',
        'Europe/Zagreb' => '(GMT+02:00) Zagreb',
        'Pacific/Apia' => '(GMT+13:00) Samoa',
        'Pacific/Auckland' => '(GMT+12:00) Wellington',
        'Pacific/Chatham' => '(GMT+12:45) Chatham Is.',
        'Pacific/Fakaofo' => '(GMT+13:00) Tokelau Is.',
        'Pacific/Fiji' => '(GMT+12:00) Fiji',
        'Pacific/Guadalcanal' => '(GMT+11:00) Solomon Is.',
        'Pacific/Guam' => '(GMT+10:00) Guam',
        'Pacific/Honolulu' => '(GMT-10:00) Hawaii',
        'Pacific/Majuro' => '(GMT+12:00) Marshall Is.',
        'Pacific/Midway' => '(GMT-11:00) Midway Island',
        'Pacific/Noumea' => '(GMT+11:00) New Caledonia',
        'Pacific/Pago_Pago' => '(GMT-11:00) American Samoa',
        'Pacific/Port_Moresby' => '(GMT+10:00) Port Moresby',
        'Pacific/Tongatapu' => '(GMT+13:00) Nuku\'alofa',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 134,
      'digest' => '7ad29bac55b6773026244843ca960ca3',
      'first' => 
      array (
        '' => '',
        'Pacific/Midway' => '(GMT-11:00) Midway Island',
        'Pacific/Pago_Pago' => '(GMT-11:00) American Samoa',
      ),
      'last' => 
      array (
        'Pacific/Apia' => '(GMT+13:00) Samoa',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssociationForm::twitterUser' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'twitterUser',
    'label' => 'Twitter user',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '15',
      'name' => 'twitterUser',
      'placeholder' => 'ex. fr_johnsmith',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Twitter user',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::url1' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url1',
    'label' => 'Other URL 1',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'url1',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'Other URL 1',
      'required' => false,
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::url1Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url1Label',
    'label' => 'Other URL 1 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'url1Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Other URL 1 Label',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Information' => 'Information',
        'Map' => 'Map',
        'Media' => 'Media',
        'Personal website' => 'Personal website',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 6,
      'digest' => '4a1d4822b6fd93446c22e8435ea72a87',
      'first' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Information' => 'Information',
      ),
      'last' => 
      array (
        'Personal website' => 'Personal website',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssociationForm::url2' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url2',
    'label' => 'Other URL 2',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'url2',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'Other URL 2',
      'required' => false,
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::url2Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url2Label',
    'label' => 'Other URL 2 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'url2Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Other URL 2 Label',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Information' => 'Information',
        'Map' => 'Map',
        'Media' => 'Media',
        'Personal website' => 'Personal website',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 6,
      'digest' => '4a1d4822b6fd93446c22e8435ea72a87',
      'first' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Information' => 'Information',
      ),
      'last' => 
      array (
        'Personal website' => 'Personal website',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssociationForm::url3' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url3',
    'label' => 'Other URL 3',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'url3',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'Other URL 3',
      'required' => false,
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::url3Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url3Label',
    'label' => 'Other URL 3 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'url3Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Other URL 3 Label',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Information' => 'Information',
        'Map' => 'Map',
        'Media' => 'Media',
        'Personal website' => 'Personal website',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 6,
      'digest' => '4a1d4822b6fd93446c22e8435ea72a87',
      'first' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Information' => 'Information',
      ),
      'last' => 
      array (
        'Personal website' => 'Personal website',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\AssociationForm::zip' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'zip',
    'label' => 'Zip/PLZ',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'zip',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Zip/PLZ',
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::associationId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'associationId',
    'label' => 'Association',
    'value' => NULL,
    'attributes' => 
    array (
      'disabled' => true,
      'name' => 'associationId',
      'required' => true,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Association',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 496,
      'digest' => '614e0a6d254b851ea56eb49ce4128a42',
      'first' => 
      array (
        322 => 'Cenacle of Bellavista',
        569 => 'Austin Caritas',
        571 => 'Casa de la Familia de Schoenstatt - Tucumán',
      ),
      'last' => 
      array (
        263 => 'Women\'s youth of Temuco',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::delete' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Button',
    'name' => 'delete',
    'label' => NULL,
    'value' => 'Delete',
    'attributes' => 
    array (
      'class' => 'btn-danger',
      'data-target' => '.bs-example-modal-sm',
      'data-toggle' => 'modal',
      'name' => 'delete',
      'type' => 'button',
    ),
    'options' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::endDate' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Date',
    'name' => 'endDate',
    'label' => 'End date',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => '1900-01-01',
      'name' => 'endDate',
      'required' => false,
      'step' => 'any',
      'type' => 'date',
    ),
    'options' => 
    array (
      'format' => 'Y-m-d',
      'label' => 'End date',
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::endDatePrecision' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'endDatePrecision',
    'label' => 'How precisely the end date is known',
    'value' => 'day',
    'attributes' => 
    array (
      'name' => 'endDatePrecision',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'label' => 'How precisely the end date is known',
      'value_options' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'a75572cb886cee31cda5ee477f30c413',
      'first' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
      'last' => 
      array (
        'year' => 'Year only',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::personId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'personId',
    'label' => 'Person',
    'value' => NULL,
    'attributes' => 
    array (
      'disabled' => true,
      'name' => 'personId',
      'required' => true,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Person',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 325,
      'digest' => 'd5d786e6b2cd20f22ce850baf06cd29e',
      'first' => 
      array (
        630 => 'Abella, Santiago',
        633 => 'Abarca Vallejos, Sergio Franco Alexander',
        658 => 'Abud Sittler, Christián José',
      ),
      'last' => 
      array (
        529 => 'Álamos, Victor',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::roleId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'roleId',
    'label' => 'Role',
    'value' => NULL,
    'attributes' => 
    array (
      'disabled' => true,
      'name' => 'roleId',
      'required' => true,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Role',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::startDate' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Date',
    'name' => 'startDate',
    'label' => 'Start date',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => '1900-01-01',
      'name' => 'startDate',
      'required' => false,
      'step' => 'any',
      'type' => 'date',
    ),
    'options' => 
    array (
      'format' => 'Y-m-d',
      'label' => 'Start date',
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::startDatePrecision' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'startDatePrecision',
    'label' => 'How precisely the start date is known',
    'value' => 'day',
    'attributes' => 
    array (
      'name' => 'startDatePrecision',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'label' => 'How precisely the start date is known',
      'value_options' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'a75572cb886cee31cda5ee477f30c413',
      'first' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
      'last' => 
      array (
        'year' => 'Year only',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\ImportFatherForm::personId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'personId',
    'label' => 'Person to import',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'personId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Person to import',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\ImportFatherForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Schoenstatt\\Form\\ImportFatherForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Import',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::adminNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'adminNotes',
    'label' => 'Admin notes',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'adminNotes',
      'required' => false,
      'rows' => 8,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Admin notes',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::adminTags' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'adminTags',
    'label' => 'Admin tags',
    'value' => NULL,
    'attributes' => 
    array (
      'multiple' => true,
      'name' => 'adminTags',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Admin tags',
      'placeholder' => 'Select tags or type new ones...',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 0,
      'digest' => '40cd750bba9870f18aada2478b24840a',
      'first' => 
      array (
      ),
      'last' => 
      array (
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\PersonForm::automaticTitle' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'automaticTitle',
    'label' => 'Automatically generate title',
    'value' => '1',
    'attributes' => 
    array (
      'aria-controls' => 'titleGroup',
      'aria-expanded' => 'false',
      'data-target' => '#titleGroup',
      'data-toggle' => 'collapse',
      'name' => 'automaticTitle',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Automatically generate title',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\PersonForm::birthDate' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Date',
    'name' => 'birthDate',
    'label' => 'Birth date',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => '1900-01-01',
      'name' => 'birthDate',
      'required' => false,
      'step' => 'any',
      'type' => 'date',
    ),
    'options' => 
    array (
      'format' => 'Y-m-d',
      'label' => 'Birth date',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::birthDatePrecision' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'birthDatePrecision',
    'label' => 'How precisely the birth date is known',
    'value' => 'day',
    'attributes' => 
    array (
      'name' => 'birthDatePrecision',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'label' => 'How precisely the birth date is known',
      'value_options' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'a75572cb886cee31cda5ee477f30c413',
      'first' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
      'last' => 
      array (
        'year' => 'Year only',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Schoenstatt\\Form\\PersonForm::bishopDate' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Date',
    'name' => 'bishopDate',
    'label' => 'Bishop ordination date',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => '1900-01-01',
      'name' => 'bishopDate',
      'required' => false,
      'step' => 'any',
      'type' => 'date',
    ),
    'options' => 
    array (
      'format' => 'Y-m-d',
      'label' => 'Bishop ordination date',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::bishopDatePrecision' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'bishopDatePrecision',
    'label' => 'How precisely the episcopal ordination date is known',
    'value' => 'day',
    'attributes' => 
    array (
      'name' => 'bishopDatePrecision',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'label' => 'How precisely the episcopal ordination date is known',
      'value_options' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'a75572cb886cee31cda5ee477f30c413',
      'first' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
      'last' => 
      array (
        'year' => 'Year only',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Schoenstatt\\Form\\PersonForm::cellPhone' => 
  array (
    'class' => 'SionModel\\Form\\Element\\Phone',
    'name' => 'cellPhone',
    'label' => 'Cell phone (main number)',
    'value' => NULL,
    'attributes' => 
    array (
      'id' => 'cellPhone',
      'maxlength' => '30',
      'name' => 'cellPhone',
      'placeholder' => 'ex. +49 151 55555555',
      'type' => 'tel',
    ),
    'options' => 
    array (
      'help-block' => 'Please begin with a \'+\' followed by the country code.',
      'label' => 'Cell phone (main number)',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::cellPhoneHasWhatsApp' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'cellPhoneHasWhatsApp',
    'label' => 'Cell phone has WhatsApp?',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'cellPhoneHasWhatsApp',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Cell phone has WhatsApp?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\PersonForm::contactNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'contactNotes',
    'label' => 'Contact detail notes',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'contactNotes',
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Contact detail notes',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::country' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'country',
    'label' => 'Home country',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'country',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Home country',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 249,
      'digest' => '054d02752ee6095bc3bb3601e547c227',
      'first' => 
      array (
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
      ),
      'last' => 
      array (
        'AX' => 'Åland Islands',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\PersonForm::deathDate' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Date',
    'name' => 'deathDate',
    'label' => 'Death date',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => '1900-01-01',
      'name' => 'deathDate',
      'required' => false,
      'step' => 'any',
      'type' => 'date',
    ),
    'options' => 
    array (
      'format' => 'Y-m-d',
      'label' => 'Death date',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::deathDatePrecision' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'deathDatePrecision',
    'label' => 'How precisely the death date is known',
    'value' => 'day',
    'attributes' => 
    array (
      'name' => 'deathDatePrecision',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'label' => 'How precisely the death date is known',
      'value_options' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'a75572cb886cee31cda5ee477f30c413',
      'first' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
      'last' => 
      array (
        'year' => 'Year only',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Schoenstatt\\Form\\PersonForm::delete' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Button',
    'name' => 'delete',
    'label' => NULL,
    'value' => 'Delete',
    'attributes' => 
    array (
      'class' => 'btn-danger',
      'data-target' => '.bs-example-modal-sm',
      'data-toggle' => 'modal',
      'name' => 'delete',
      'type' => 'button',
    ),
    'options' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::email' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Email',
    'name' => 'email',
    'label' => 'Main Email',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '70',
      'name' => 'email',
      'required' => false,
      'type' => 'email',
    ),
    'options' => 
    array (
      'label' => 'Main Email',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::email2' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Email',
    'name' => 'email2',
    'label' => 'Alternative Email',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '70',
      'name' => 'email2',
      'required' => false,
      'type' => 'email',
    ),
    'options' => 
    array (
      'label' => 'Alternative Email',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::facebookUrl' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'facebookUrl',
    'label' => 'Facebook URL',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'facebookUrl',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'Facebook URL',
      'required' => false,
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::firstName' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'firstName',
    'label' => 'First name',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'firstName',
      'placeholder' => 'ex. John Andrew',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'First name',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::instagramUser' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'instagramUser',
    'label' => 'Instagram user',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '30',
      'name' => 'instagramUser',
      'placeholder' => 'ex. fr_johnsmith',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Instagram user',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::isAuthor' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isAuthor',
    'label' => 'Is author?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isAuthor',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Is author?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\PersonForm::isBorrower' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isBorrower',
    'label' => 'Is library user?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isBorrower',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Is library user?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\PersonForm::lastName' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'lastName',
    'label' => 'Last name',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'lastName',
      'placeholder' => 'ex. Smith Johnson',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Last name',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::lifeCommunity' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'lifeCommunity',
    'label' => 'Life community',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'lifeCommunity',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Life community',
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 11,
      'digest' => '3a163b637ae3703c147e5714cafc9302',
      'first' => 
      array (
        79 => 'Schoenstatt Institute Brothers of Mary',
        80 => 'Schoenstatt Family Federation',
        81 => 'Schoenstatt Diocesan Priests Federation',
      ),
      'last' => 
      array (
        1 => 'Secular Institute of Schoenstatt Fathers',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\PersonForm::manualTitle' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'manualTitle',
    'label' => 'Manual title',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'manualTitle',
      'placeholder' => 'ex. Fr. Prof.',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Manual title',
      'required' => true,
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::nameDay' => 
  array (
    'class' => 'Laminas\\Form\\Element\\DateSelect',
    'name' => 'nameDay',
    'label' => 'Name day',
    'value' => '1900-00-00',
    'attributes' => 
    array (
      'name' => 'nameDay',
    ),
    'options' => 
    array (
      'create_empty_option' => true,
      'day_attributes' => 
      array (
        'class' => 'form-control',
      ),
      'label' => 'Name day',
      'min_year' => 1900,
      'month_attributes' => 
      array (
        'class' => 'form-control',
      ),
      'render_delimiters' => false,
      'year_attributes' => 
      array (
        'hidden' => true,
        'value' => 1900,
      ),
    ),
    'dayElementName' => 'day',
    'monthElementName' => 'month',
    'yearElementName' => 'year',
  ),
  'Schoenstatt\\Form\\PersonForm::personTags' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'personTags',
    'label' => 'Person tags',
    'value' => NULL,
    'attributes' => 
    array (
      'multiple' => true,
      'name' => 'personTags',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Person tags',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 11,
      'digest' => 'ac41bb87df0982ddadec37df47c32b7a',
      'first' => 
      array (
        'bishop' => 'Bishop',
        'monsignor' => 'Monsignor',
        'priest' => 'Priest',
      ),
      'last' => 
      array (
        'ms' => 'Ms.',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\PersonForm::phone1' => 
  array (
    'class' => 'SionModel\\Form\\Element\\Phone',
    'name' => 'phone1',
    'label' => 'Phone 1',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '30',
      'name' => 'phone1',
      'placeholder' => 'ex. +49 151 55555555',
      'type' => 'tel',
    ),
    'options' => 
    array (
      'help-block' => 'Please begin with a \'+\' followed by the country code.',
      'label' => 'Phone 1',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::phone1Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'phone1Label',
    'label' => 'Phone 1 label (optional)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'phone1Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Phone 1 label (optional)',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Movement house' => 'Movement house',
        'Office' => 'Office',
        'Only WhatsApp' => 'Only WhatsApp',
        'Parish' => 'Parish',
        'Personal house phone' => 'Personal house phone',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 6,
      'digest' => 'ffedbc28db9990d1bbcc064e60c7cf65',
      'first' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Movement house' => 'Movement house',
        'Only WhatsApp' => 'Only WhatsApp',
      ),
      'last' => 
      array (
        'Personal house phone' => 'Personal house phone',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\PersonForm::phone2' => 
  array (
    'class' => 'SionModel\\Form\\Element\\Phone',
    'name' => 'phone2',
    'label' => 'Phone 2',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '30',
      'name' => 'phone2',
      'placeholder' => 'ex. +49 151 55555555',
      'type' => 'tel',
    ),
    'options' => 
    array (
      'help-block' => 'Please begin with a \'+\' followed by the country code.',
      'label' => 'Phone 2',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::phone2Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'phone2Label',
    'label' => 'Phone 2 label (optional)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'phone2Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Phone 2 label (optional)',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Movement house' => 'Movement house',
        'Office' => 'Office',
        'Only WhatsApp' => 'Only WhatsApp',
        'Parish' => 'Parish',
        'Personal house phone' => 'Personal house phone',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 6,
      'digest' => 'ffedbc28db9990d1bbcc064e60c7cf65',
      'first' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Movement house' => 'Movement house',
        'Only WhatsApp' => 'Only WhatsApp',
      ),
      'last' => 
      array (
        'Personal house phone' => 'Personal house phone',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\PersonForm::phone3' => 
  array (
    'class' => 'SionModel\\Form\\Element\\Phone',
    'name' => 'phone3',
    'label' => 'Phone 3',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '30',
      'name' => 'phone3',
      'placeholder' => 'ex. +49 151 55555555',
      'type' => 'tel',
    ),
    'options' => 
    array (
      'help-block' => 'Please begin with a \'+\' followed by the country code.',
      'label' => 'Phone 3',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::phone3Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'phone3Label',
    'label' => 'Phone 3 label (optional)',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'phone3Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Phone 3 label (optional)',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Movement house' => 'Movement house',
        'Office' => 'Office',
        'Only WhatsApp' => 'Only WhatsApp',
        'Parish' => 'Parish',
        'Personal house phone' => 'Personal house phone',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 6,
      'digest' => 'ffedbc28db9990d1bbcc064e60c7cf65',
      'first' => 
      array (
        'Alternate cell phone' => 'Alternate cell phone',
        'Movement house' => 'Movement house',
        'Only WhatsApp' => 'Only WhatsApp',
      ),
      'last' => 
      array (
        'Personal house phone' => 'Personal house phone',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\PersonForm::postCityState' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'postCityState',
    'label' => 'City/State',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'postCityState',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'City/State',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::postCountry' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'postCountry',
    'label' => 'Country',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'postCountry',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Country',
      'unselected_value' => '',
    ),
    'valueOptions' => 
    array (
      'count' => 249,
      'digest' => '054d02752ee6095bc3bb3601e547c227',
      'first' => 
      array (
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
      ),
      'last' => 
      array (
        'AX' => 'Åland Islands',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\PersonForm::postStreet1' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'postStreet1',
    'label' => 'Street Line 1',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'postStreet1',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Street Line 1',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::postStreet2' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'postStreet2',
    'label' => 'Street Line 2',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'postStreet2',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Street Line 2',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::postZip' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'postZip',
    'label' => 'Zip/PLZ',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'postZip',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Zip/PLZ',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::priestDate' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Date',
    'name' => 'priestDate',
    'label' => 'Priest ordination date',
    'value' => NULL,
    'attributes' => 
    array (
      'min' => '1900-01-01',
      'name' => 'priestDate',
      'required' => false,
      'step' => 'any',
      'type' => 'date',
    ),
    'options' => 
    array (
      'format' => 'Y-m-d',
      'label' => 'Priest ordination date',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::priestDatePrecision' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'priestDatePrecision',
    'label' => 'How precisely the ordination date is known',
    'value' => 'day',
    'attributes' => 
    array (
      'name' => 'priestDatePrecision',
      'required' => false,
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'label' => 'How precisely the ordination date is known',
      'value_options' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'a75572cb886cee31cda5ee477f30c413',
      'first' => 
      array (
        'day' => 'Exact day',
        'month' => 'Month and year only',
        'year' => 'Year only',
      ),
      'last' => 
      array (
        'year' => 'Year only',
      ),
    ),
    'emptyOption' => NULL,
  ),
  'Schoenstatt\\Form\\PersonForm::publicNotes' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'publicNotes',
    'label' => 'Public notes',
    'value' => NULL,
    'attributes' => 
    array (
      'data-parser' => 'CommonMark',
      'data-provide' => 'markdown',
      'name' => 'publicNotes',
      'required' => false,
      'rows' => 8,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Public notes',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::skypeUser' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'skypeUser',
    'label' => 'Skype user',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '32',
      'name' => 'skypeUser',
      'placeholder' => 'ex. fr_johnsmith',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Skype user',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::slackUser' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'slackUser',
    'label' => 'Slack user',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'slackUser',
      'placeholder' => 'ex. fr.johnsmith',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Slack user',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::spousePersonId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'spousePersonId',
    'label' => 'Spouse',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'spousePersonId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => false,
      'empty_option' => '',
      'label' => 'Spouse',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 325,
      'digest' => 'd5d786e6b2cd20f22ce850baf06cd29e',
      'first' => 
      array (
        630 => 'Abella, Santiago',
        633 => 'Abarca Vallejos, Sergio Franco Alexander',
        658 => 'Abud Sittler, Christián José',
      ),
      'last' => 
      array (
        529 => 'Álamos, Victor',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\PersonForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::twitterUser' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'twitterUser',
    'label' => 'Twitter user',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '15',
      'name' => 'twitterUser',
      'placeholder' => 'ex. fr_johnsmith',
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => 'Twitter user',
      'required' => false,
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::url1' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url1',
    'label' => 'Other URL 1',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'url1',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'Other URL 1',
      'required' => false,
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::url1Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url1Label',
    'label' => 'Other URL 1 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'url1Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Other URL 1 Label',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Personal website' => 'Personal website',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'f68017e5300ccc60ed78a63c16221fde',
      'first' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Personal website' => 'Personal website',
      ),
      'last' => 
      array (
        'Personal website' => 'Personal website',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\PersonForm::url2' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url2',
    'label' => 'Other URL 2',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'url2',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'Other URL 2',
      'required' => false,
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::url2Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url2Label',
    'label' => 'Other URL 2 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'url2Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Other URL 2 Label',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Personal website' => 'Personal website',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'f68017e5300ccc60ed78a63c16221fde',
      'first' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Personal website' => 'Personal website',
      ),
      'last' => 
      array (
        'Personal website' => 'Personal website',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\PersonForm::url3' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Url',
    'name' => 'url3',
    'label' => 'Other URL 3',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '255',
      'name' => 'url3',
      'placeholder' => 'ex. https://www.facebook.com/john.smith.34',
      'type' => 'url',
    ),
    'options' => 
    array (
      'allowRelative' => false,
      'label' => 'Other URL 3',
      'required' => false,
      'uriHandler' => 'Laminas\\Uri\\Http',
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::url3Label' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'url3Label',
    'label' => 'Other URL 3 Label',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => '50',
      'name' => 'url3Label',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Other URL 3 Label',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Personal website' => 'Personal website',
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 3,
      'digest' => 'f68017e5300ccc60ed78a63c16221fde',
      'first' => 
      array (
        'Blog' => 'Blog',
        'G+' => 'G+',
        'Personal website' => 'Personal website',
      ),
      'last' => 
      array (
        'Personal website' => 'Personal website',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\RoleForm::associationId' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'associationId',
    'label' => 'Association',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'associationId',
      'type' => 'select',
    ),
    'options' => 
    array (
      'empty_option' => '',
      'label' => 'Association',
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 496,
      'digest' => '614e0a6d254b851ea56eb49ce4128a42',
      'first' => 
      array (
        322 => 'Cenacle of Bellavista',
        569 => 'Austin Caritas',
        571 => 'Casa de la Familia de Schoenstatt - Tucumán',
      ),
      'last' => 
      array (
        263 => 'Women\'s youth of Temuco',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\RoleForm::isActive' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isActive',
    'label' => 'Active',
    'value' => '1',
    'attributes' => 
    array (
      'name' => 'isActive',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Active',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\RoleForm::isMainContact' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isMainContact',
    'label' => 'Is main contact for the association?',
    'value' => '0',
    'attributes' => 
    array (
      'name' => 'isMainContact',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Is main contact for the association?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\RoleForm::isMainRole' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isMainRole',
    'label' => 'Is main role for the association?',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'isMainRole',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Is main role for the association?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\RoleForm::isSinglePosition' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'isSinglePosition',
    'label' => 'Does role only have one person at a time?',
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'isSinglePosition',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => '1',
      'label' => 'Does role only have one person at a time?',
      'unchecked_value' => '0',
      'use_hidden_element' => true,
    ),
    'checkedValue' => '1',
    'uncheckedValue' => '0',
    'useHiddenElement' => true,
  ),
  'Schoenstatt\\Form\\RoleForm::roleTitle' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Select',
    'name' => 'roleTitle',
    'label' => 'Role title',
    'value' => NULL,
    'attributes' => 
    array (
      'id' => 'roleSelect',
      'maxlength' => '50',
      'name' => 'roleTitle',
      'type' => 'select',
    ),
    'options' => 
    array (
      'disable_inarray_validator' => true,
      'empty_option' => '',
      'label' => 'Role title',
      'required' => false,
      'unselected_value' => '',
      'value_options' => 
      array (
      ),
    ),
    'valueOptions' => 
    array (
      'count' => 39,
      'digest' => '76d559ac05227d19c2278a9d12025da7',
      'first' => 
      array (
        '(General or main) Secretary' => '(General or main) Secretary',
        'Assistant moderator' => 'Assistant moderator',
        'Board member' => 'Board member',
      ),
      'last' => 
      array (
        'Women\'s Federation' => 'Women\'s Federation',
      ),
    ),
    'emptyOption' => '',
  ),
  'Schoenstatt\\Form\\RoleForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::sort' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Number',
    'name' => 'sort',
    'label' => 'Sort value',
    'value' => 50,
    'attributes' => 
    array (
      'inclusive' => true,
      'max' => 100,
      'min' => 0,
      'name' => 'sort',
      'step' => 1,
      'type' => 'number',
    ),
    'options' => 
    array (
      'label' => 'Sort value',
      'required' => true,
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\SearchForm::exMembers' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'exMembers',
    'label' => 'Show Ex-members',
    'value' => 'false',
    'attributes' => 
    array (
      'name' => 'exMembers',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => 'true',
      'label' => 'Show Ex-members',
      'required' => false,
      'unchecked_value' => 'false',
      'use_hidden_element' => false,
    ),
    'checkedValue' => 'true',
    'uncheckedValue' => 'false',
    'useHiddenElement' => false,
  ),
  'Schoenstatt\\Form\\SearchForm::search' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Text',
    'name' => 'search',
    'label' => '',
    'value' => NULL,
    'attributes' => 
    array (
      'class' => 'input-lg',
      'name' => 'search',
      'placeholder' => 'Search',
      'required' => false,
      'type' => 'text',
    ),
    'options' => 
    array (
      'label' => '',
    ),
  ),
  'Schoenstatt\\Form\\SearchForm::showPhotos' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Checkbox',
    'name' => 'showPhotos',
    'label' => 'Show Photos',
    'value' => 'false',
    'attributes' => 
    array (
      'name' => 'showPhotos',
      'type' => 'checkbox',
    ),
    'options' => 
    array (
      'checked_value' => 'true',
      'label' => 'Show Photos',
      'required' => false,
      'unchecked_value' => 'false',
      'use_hidden_element' => false,
    ),
    'checkedValue' => 'true',
    'uncheckedValue' => 'false',
    'useHiddenElement' => false,
  ),
  'SionModel\\Form\\CommentForm::comment' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Textarea',
    'name' => 'comment',
    'label' => 'Leave a comment',
    'value' => NULL,
    'attributes' => 
    array (
      'maxlength' => 500,
      'name' => 'comment',
      'required' => false,
      'rows' => 3,
      'type' => 'textarea',
    ),
    'options' => 
    array (
      'label' => 'Leave a comment',
    ),
  ),
  'SionModel\\Form\\CommentForm::redirect' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Hidden',
    'name' => 'redirect',
    'label' => NULL,
    'value' => NULL,
    'attributes' => 
    array (
      'name' => 'redirect',
      'type' => 'hidden',
    ),
    'options' => 
    array (
    ),
  ),
  'SionModel\\Form\\CommentForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'SionModel\\Form\\CommentForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Submit',
    'attributes' => 
    array (
      'class' => 'btn-primary',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'SionModel\\Form\\DeleteEntityForm::cancel' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Button',
    'name' => 'cancel',
    'label' => NULL,
    'value' => 'Cancel',
    'attributes' => 
    array (
      'data-dismiss' => 'modal',
      'id' => 'submit',
      'name' => 'cancel',
      'type' => 'button',
    ),
    'options' => 
    array (
    ),
  ),
  'SionModel\\Form\\DeleteEntityForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
  'SionModel\\Form\\DeleteEntityForm::submit' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Submit',
    'name' => 'submit',
    'label' => NULL,
    'value' => 'Delete',
    'attributes' => 
    array (
      'class' => 'btn-danger',
      'id' => 'submit',
      'name' => 'submit',
      'type' => 'submit',
    ),
    'options' => 
    array (
    ),
  ),
  'SionModel\\Form\\SionForm::security' => 
  array (
    'class' => 'Laminas\\Form\\Element\\Csrf',
    'name' => 'security',
    'label' => NULL,
    'value' => '<csrf-token>',
    'attributes' => 
    array (
      'name' => 'security',
      'type' => 'hidden',
      'value' => '<csrf-token>',
    ),
    'options' => 
    array (
      'csrf_options' => 
      array (
        'timeout' => 900,
      ),
    ),
    'csrfValidatorOptions' => 
    array (
      'timeout' => 900,
    ),
  ),
);
