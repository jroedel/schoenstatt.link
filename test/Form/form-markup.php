<?php

/**
 * The markup every form in the application produces, recorded through
 * `SionModel\Form\BootstrapFormRenderer` over the laminas form model.
 *
 * GENERATED FILE — regenerate with:
 *
 *     docker compose exec -T -u www-data app php -d memory_limit=1G \
 *         test/Form/regenerate-form-markup.php
 *
 * This is the contract iteration A's form model has to meet. Keyed
 * `Form\Class::path/to/element` => state => helper => markup, with the same paths
 * `test/Element/element-surface.php` uses so the two can be read side by side.
 *
 * Three states, and **`populated` and `invalid` record only what differs from
 * `pristine`**: a helper missing from them renders identically there. See
 * `SchoenstattTest\Form\FormMarkup` for what each state is, what is normalised and why an
 * option list longer than four collapses to a digest.
 *
 * @return array<string, array<string, array<string, string>>>
 */

declare(strict_types=1);

return array (
  'App\\Books\\Import\\ImportMappingFieldset::adminNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><select name="adminNotes" class="form-control"></select><p class="help-block">Visible only to library administrators. Markdown.</p></div>',
      'element' => '<select name="adminNotes" class="form-control"></select>',
      'label' => '<label for="adminNotes">Admin notes</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Visible only to library administrators. Markdown.</p>',
      'select_without_options' => '<select name="adminNotes"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::adminTags' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin tags</label><select name="adminTags" class="form-control"></select><p class="help-block">Administrator-only tags, separated by a vertical bar.</p></div>',
      'element' => '<select name="adminTags" class="form-control"></select>',
      'label' => '<label for="adminTags">Admin tags</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Administrator-only tags, separated by a vertical bar.</p>',
      'select_without_options' => '<select name="adminTags"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::authorsText' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Author</label><select name="authorsText" class="form-control"></select><p class="help-block">One name, or several separated by a vertical bar: Kentenich, Josef | Schlickmann, Anna.</p></div>',
      'element' => '<select name="authorsText" class="form-control"></select>',
      'label' => '<label for="authorsText">Author</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One name, or several separated by a vertical bar: Kentenich, Josef | Schlickmann, Anna.</p>',
      'select_without_options' => '<select name="authorsText"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::bookEdition' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Edition</label><select name="bookEdition" class="form-control"></select><p class="help-block">Which edition or printing this copy is — the field that tells two copies of the same work apart when nothing else does.</p></div>',
      'element' => '<select name="bookEdition" class="form-control"></select>',
      'label' => '<label for="bookEdition">Edition</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Which edition or printing this copy is — the field that tells two copies of the same work apart when nothing else does.</p>',
      'select_without_options' => '<select name="bookEdition"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::callNumber' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Call number</label><select name="callNumber" class="form-control"></select><p class="help-block">Where the copy stands on the shelf, as printed on its label.</p></div>',
      'element' => '<select name="callNumber" class="form-control"></select>',
      'label' => '<label for="callNumber">Call number</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Where the copy stands on the shelf, as printed on its label.</p>',
      'select_without_options' => '<select name="callNumber"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::category' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Category</label><select name="category" class="form-control"></select><p class="help-block">One classification for the copy. For several subject terms use Keywords instead.</p></div>',
      'element' => '<select name="category" class="form-control"></select>',
      'label' => '<label for="category">Category</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One classification for the copy. For several subject terms use Keywords instead.</p>',
      'select_without_options' => '<select name="category"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::collection' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Collection</label><select name="collection" class="form-control"></select><p class="help-block">The collection within this library. A name that does not exist yet is created by the import. Ignored entirely by libraries that do not use collections.</p></div>',
      'element' => '<select name="collection" class="form-control"></select>',
      'label' => '<label for="collection">Collection</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">The collection within this library. A name that does not exist yet is created by the import. Ignored entirely by libraries that do not use collections.</p>',
      'select_without_options' => '<select name="collection"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::inLanguage' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage" class="form-control"></select><p class="help-block">Two-letter code: en, es, de, pt, it, fr, la, pl.</p></div>',
      'element' => '<select name="inLanguage" class="form-control"></select>',
      'label' => '<label for="inLanguage">Language</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Two-letter code: en, es, de, pt, it, fr, la, pl.</p>',
      'select_without_options' => '<select name="inLanguage"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::isbn' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>ISBN</label><select name="isbn" class="form-control"></select></div>',
      'element' => '<select name="isbn" class="form-control"></select>',
      'label' => '<label for="isbn">ISBN</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="isbn"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::keywords' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Keywords</label><select name="keywords" class="form-control"></select><p class="help-block">Subject terms shown to readers, separated by a vertical bar.</p></div>',
      'element' => '<select name="keywords" class="form-control"></select>',
      'label' => '<label for="keywords">Keywords</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Subject terms shown to readers, separated by a vertical bar.</p>',
      'select_without_options' => '<select name="keywords"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::newCallNumber' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>New call number</label><select name="newCallNumber" class="form-control"></select><p class="help-block">Only when the printed call number should change. It goes onto the next labels printed.</p></div>',
      'element' => '<select name="newCallNumber" class="form-control"></select>',
      'label' => '<label for="newCallNumber">New call number</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Only when the printed call number should change. It goes onto the next labels printed.</p>',
      'select_without_options' => '<select name="newCallNumber"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::numberOfPages' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Pages</label><select name="numberOfPages" class="form-control"></select><p class="help-block">A whole number.</p></div>',
      'element' => '<select name="numberOfPages" class="form-control"></select>',
      'label' => '<label for="numberOfPages">Pages</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">A whole number.</p>',
      'select_without_options' => '<select name="numberOfPages"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::publicNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Public notes</label><select name="publicNotes" class="form-control"></select><p class="help-block">Shown to readers. Markdown.</p></div>',
      'element' => '<select name="publicNotes" class="form-control"></select>',
      'label' => '<label for="publicNotes">Public notes</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Shown to readers. Markdown.</p>',
      'select_without_options' => '<select name="publicNotes"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::publicationId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Literature ID</label><select name="publicationId" class="form-control"></select><p class="help-block">Links this copy to a record in the site-wide literature catalogue. When it names an existing record, that record supplies the title, author, year, publisher, place, pages, language and ISBN — whatever those columns say in the spreadsheet is ignored for that row.</p></div>',
      'element' => '<select name="publicationId" class="form-control"></select>',
      'label' => '<label for="publicationId">Literature ID</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Links this copy to a record in the site-wide literature catalogue. When it names an existing record, that record supplies the title, author, year, publisher, place, pages, language and ISBN — whatever those columns say in the spreadsheet is ignored for that row.</p>',
      'select_without_options' => '<select name="publicationId"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::publishedYear' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Year published</label><select name="publishedYear" class="form-control"></select><p class="help-block">A four-digit year.</p></div>',
      'element' => '<select name="publishedYear" class="form-control"></select>',
      'label' => '<label for="publishedYear">Year published</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">A four-digit year.</p>',
      'select_without_options' => '<select name="publishedYear"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::publisher' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Publisher</label><select name="publisher" class="form-control"></select></div>',
      'element' => '<select name="publisher" class="form-control"></select>',
      'label' => '<label for="publisher">Publisher</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="publisher"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::publishingPlace' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Place of publication</label><select name="publishingPlace" class="form-control"></select></div>',
      'element' => '<select name="publishingPlace" class="form-control"></select>',
      'label' => '<label for="publishingPlace">Place of publication</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="publishingPlace"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::title' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Title *</label><select name="title" class="form-control"></select><p class="help-block">Required, unless the row carries a Literature ID that names an existing record.</p></div>',
      'element' => '<select name="title" class="form-control"></select>',
      'label' => '<label for="title">Title *</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Required, unless the row carries a Literature ID that names an existing record.</p>',
      'select_without_options' => '<select name="title"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingFieldset::withinLibraryId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Barcode *</label><select name="withinLibraryId" class="form-control"></select><p class="help-block">The library&#039;s own number for this copy. Required, must be a whole number, and must be unique within the library — it is what an import matches a row to an existing book by.</p></div>',
      'element' => '<select name="withinLibraryId" class="form-control"></select>',
      'label' => '<label for="withinLibraryId">Barcode *</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">The library&#039;s own number for this copy. Required, must be a whole number, and must be unique within the library — it is what an import matches a row to an existing book by.</p>',
      'select_without_options' => '<select name="withinLibraryId"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="import_mapping" action="&#x2F;form-action" class="form-horizontal" id="import_mapping">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/adminNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><select name="adminNotes" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Visible only to library administrators. Markdown.</p></div>',
      'element' => '<select name="adminNotes" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="adminNotes">Admin notes</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Visible only to library administrators. Markdown.</p>',
      'select_without_options' => '<select name="adminNotes"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><select name="adminNotes" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">Visible only to library administrators. Markdown.</p></div>',
      'element' => '<select name="adminNotes" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="adminNotes"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><select name="map&#x5B;adminNotes&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Visible only to library administrators. Markdown.</p></div>',
      'element' => '<select name="map&#x5B;adminNotes&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;adminNotes&#x5D;">Admin notes</label>',
      'select_without_options' => '<select name="map&#x5B;adminNotes&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/adminTags' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin tags</label><select name="adminTags" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Administrator-only tags, separated by a vertical bar.</p></div>',
      'element' => '<select name="adminTags" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="adminTags">Admin tags</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Administrator-only tags, separated by a vertical bar.</p>',
      'select_without_options' => '<select name="adminTags"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Admin tags</label><select name="adminTags" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">Administrator-only tags, separated by a vertical bar.</p></div>',
      'element' => '<select name="adminTags" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="adminTags"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Admin tags</label><select name="map&#x5B;adminTags&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Administrator-only tags, separated by a vertical bar.</p></div>',
      'element' => '<select name="map&#x5B;adminTags&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;adminTags&#x5D;">Admin tags</label>',
      'select_without_options' => '<select name="map&#x5B;adminTags&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/authorsText' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Author</label><select name="authorsText" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">One name, or several separated by a vertical bar: Kentenich, Josef | Schlickmann, Anna.</p></div>',
      'element' => '<select name="authorsText" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="authorsText">Author</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One name, or several separated by a vertical bar: Kentenich, Josef | Schlickmann, Anna.</p>',
      'select_without_options' => '<select name="authorsText"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Author</label><select name="authorsText" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">One name, or several separated by a vertical bar: Kentenich, Josef | Schlickmann, Anna.</p></div>',
      'element' => '<select name="authorsText" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="authorsText"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Author</label><select name="map&#x5B;authorsText&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">One name, or several separated by a vertical bar: Kentenich, Josef | Schlickmann, Anna.</p></div>',
      'element' => '<select name="map&#x5B;authorsText&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;authorsText&#x5D;">Author</label>',
      'select_without_options' => '<select name="map&#x5B;authorsText&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/bookEdition' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Edition</label><select name="bookEdition" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Which edition or printing this copy is — the field that tells two copies of the same work apart when nothing else does.</p></div>',
      'element' => '<select name="bookEdition" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="bookEdition">Edition</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Which edition or printing this copy is — the field that tells two copies of the same work apart when nothing else does.</p>',
      'select_without_options' => '<select name="bookEdition"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Edition</label><select name="bookEdition" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">Which edition or printing this copy is — the field that tells two copies of the same work apart when nothing else does.</p></div>',
      'element' => '<select name="bookEdition" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="bookEdition"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Edition</label><select name="map&#x5B;bookEdition&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Which edition or printing this copy is — the field that tells two copies of the same work apart when nothing else does.</p></div>',
      'element' => '<select name="map&#x5B;bookEdition&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;bookEdition&#x5D;">Edition</label>',
      'select_without_options' => '<select name="map&#x5B;bookEdition&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/callNumber' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Call number</label><select name="callNumber" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Where the copy stands on the shelf, as printed on its label.</p></div>',
      'element' => '<select name="callNumber" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="callNumber">Call number</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Where the copy stands on the shelf, as printed on its label.</p>',
      'select_without_options' => '<select name="callNumber"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Call number</label><select name="callNumber" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">Where the copy stands on the shelf, as printed on its label.</p></div>',
      'element' => '<select name="callNumber" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="callNumber"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Call number</label><select name="map&#x5B;callNumber&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Where the copy stands on the shelf, as printed on its label.</p></div>',
      'element' => '<select name="map&#x5B;callNumber&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;callNumber&#x5D;">Call number</label>',
      'select_without_options' => '<select name="map&#x5B;callNumber&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/category' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Category</label><select name="category" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">One classification for the copy. For several subject terms use Keywords instead.</p></div>',
      'element' => '<select name="category" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="category">Category</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One classification for the copy. For several subject terms use Keywords instead.</p>',
      'select_without_options' => '<select name="category"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Category</label><select name="category" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">One classification for the copy. For several subject terms use Keywords instead.</p></div>',
      'element' => '<select name="category" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="category"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Category</label><select name="map&#x5B;category&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">One classification for the copy. For several subject terms use Keywords instead.</p></div>',
      'element' => '<select name="map&#x5B;category&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;category&#x5D;">Category</label>',
      'select_without_options' => '<select name="map&#x5B;category&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/collection' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Collection</label><select name="collection" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">The collection within this library. A name that does not exist yet is created by the import. Ignored entirely by libraries that do not use collections.</p></div>',
      'element' => '<select name="collection" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="collection">Collection</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">The collection within this library. A name that does not exist yet is created by the import. Ignored entirely by libraries that do not use collections.</p>',
      'select_without_options' => '<select name="collection"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Collection</label><select name="collection" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">The collection within this library. A name that does not exist yet is created by the import. Ignored entirely by libraries that do not use collections.</p></div>',
      'element' => '<select name="collection" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="collection"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Collection</label><select name="map&#x5B;collection&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">The collection within this library. A name that does not exist yet is created by the import. Ignored entirely by libraries that do not use collections.</p></div>',
      'element' => '<select name="map&#x5B;collection&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;collection&#x5D;">Collection</label>',
      'select_without_options' => '<select name="map&#x5B;collection&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/inLanguage' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Two-letter code: en, es, de, pt, it, fr, la, pl.</p></div>',
      'element' => '<select name="inLanguage" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="inLanguage">Language</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Two-letter code: en, es, de, pt, it, fr, la, pl.</p>',
      'select_without_options' => '<select name="inLanguage"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">Two-letter code: en, es, de, pt, it, fr, la, pl.</p></div>',
      'element' => '<select name="inLanguage" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="inLanguage"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="map&#x5B;inLanguage&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Two-letter code: en, es, de, pt, it, fr, la, pl.</p></div>',
      'element' => '<select name="map&#x5B;inLanguage&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;inLanguage&#x5D;">Language</label>',
      'select_without_options' => '<select name="map&#x5B;inLanguage&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/isbn' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>ISBN</label><select name="isbn" class="form-control"><option value="" selected>— not imported —</option>
</select></div>',
      'element' => '<select name="isbn" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="isbn">ISBN</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="isbn"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>ISBN</label><select name="isbn" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="isbn" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="isbn"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>ISBN</label><select name="map&#x5B;isbn&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select></div>',
      'element' => '<select name="map&#x5B;isbn&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;isbn&#x5D;">ISBN</label>',
      'select_without_options' => '<select name="map&#x5B;isbn&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/keywords' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Keywords</label><select name="keywords" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Subject terms shown to readers, separated by a vertical bar.</p></div>',
      'element' => '<select name="keywords" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="keywords">Keywords</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Subject terms shown to readers, separated by a vertical bar.</p>',
      'select_without_options' => '<select name="keywords"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Keywords</label><select name="keywords" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">Subject terms shown to readers, separated by a vertical bar.</p></div>',
      'element' => '<select name="keywords" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="keywords"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Keywords</label><select name="map&#x5B;keywords&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Subject terms shown to readers, separated by a vertical bar.</p></div>',
      'element' => '<select name="map&#x5B;keywords&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;keywords&#x5D;">Keywords</label>',
      'select_without_options' => '<select name="map&#x5B;keywords&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/newCallNumber' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>New call number</label><select name="newCallNumber" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Only when the printed call number should change. It goes onto the next labels printed.</p></div>',
      'element' => '<select name="newCallNumber" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="newCallNumber">New call number</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Only when the printed call number should change. It goes onto the next labels printed.</p>',
      'select_without_options' => '<select name="newCallNumber"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>New call number</label><select name="newCallNumber" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">Only when the printed call number should change. It goes onto the next labels printed.</p></div>',
      'element' => '<select name="newCallNumber" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="newCallNumber"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>New call number</label><select name="map&#x5B;newCallNumber&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Only when the printed call number should change. It goes onto the next labels printed.</p></div>',
      'element' => '<select name="map&#x5B;newCallNumber&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;newCallNumber&#x5D;">New call number</label>',
      'select_without_options' => '<select name="map&#x5B;newCallNumber&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/numberOfPages' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Pages</label><select name="numberOfPages" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">A whole number.</p></div>',
      'element' => '<select name="numberOfPages" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="numberOfPages">Pages</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">A whole number.</p>',
      'select_without_options' => '<select name="numberOfPages"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Pages</label><select name="numberOfPages" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">A whole number.</p></div>',
      'element' => '<select name="numberOfPages" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="numberOfPages"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Pages</label><select name="map&#x5B;numberOfPages&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">A whole number.</p></div>',
      'element' => '<select name="map&#x5B;numberOfPages&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;numberOfPages&#x5D;">Pages</label>',
      'select_without_options' => '<select name="map&#x5B;numberOfPages&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/publicNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Public notes</label><select name="publicNotes" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Shown to readers. Markdown.</p></div>',
      'element' => '<select name="publicNotes" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="publicNotes">Public notes</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Shown to readers. Markdown.</p>',
      'select_without_options' => '<select name="publicNotes"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Public notes</label><select name="publicNotes" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">Shown to readers. Markdown.</p></div>',
      'element' => '<select name="publicNotes" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="publicNotes"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Public notes</label><select name="map&#x5B;publicNotes&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Shown to readers. Markdown.</p></div>',
      'element' => '<select name="map&#x5B;publicNotes&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;publicNotes&#x5D;">Public notes</label>',
      'select_without_options' => '<select name="map&#x5B;publicNotes&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/publicationId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Literature ID</label><select name="publicationId" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Links this copy to a record in the site-wide literature catalogue. When it names an existing record, that record supplies the title, author, year, publisher, place, pages, language and ISBN — whatever those columns say in the spreadsheet is ignored for that row.</p></div>',
      'element' => '<select name="publicationId" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="publicationId">Literature ID</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Links this copy to a record in the site-wide literature catalogue. When it names an existing record, that record supplies the title, author, year, publisher, place, pages, language and ISBN — whatever those columns say in the spreadsheet is ignored for that row.</p>',
      'select_without_options' => '<select name="publicationId"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Literature ID</label><select name="publicationId" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">Links this copy to a record in the site-wide literature catalogue. When it names an existing record, that record supplies the title, author, year, publisher, place, pages, language and ISBN — whatever those columns say in the spreadsheet is ignored for that row.</p></div>',
      'element' => '<select name="publicationId" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="publicationId"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Literature ID</label><select name="map&#x5B;publicationId&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Links this copy to a record in the site-wide literature catalogue. When it names an existing record, that record supplies the title, author, year, publisher, place, pages, language and ISBN — whatever those columns say in the spreadsheet is ignored for that row.</p></div>',
      'element' => '<select name="map&#x5B;publicationId&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;publicationId&#x5D;">Literature ID</label>',
      'select_without_options' => '<select name="map&#x5B;publicationId&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/publishedYear' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Year published</label><select name="publishedYear" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">A four-digit year.</p></div>',
      'element' => '<select name="publishedYear" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="publishedYear">Year published</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">A four-digit year.</p>',
      'select_without_options' => '<select name="publishedYear"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Year published</label><select name="publishedYear" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">A four-digit year.</p></div>',
      'element' => '<select name="publishedYear" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="publishedYear"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Year published</label><select name="map&#x5B;publishedYear&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">A four-digit year.</p></div>',
      'element' => '<select name="map&#x5B;publishedYear&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;publishedYear&#x5D;">Year published</label>',
      'select_without_options' => '<select name="map&#x5B;publishedYear&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/publisher' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Publisher</label><select name="publisher" class="form-control"><option value="" selected>— not imported —</option>
</select></div>',
      'element' => '<select name="publisher" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="publisher">Publisher</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="publisher"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Publisher</label><select name="publisher" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="publisher" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="publisher"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Publisher</label><select name="map&#x5B;publisher&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select></div>',
      'element' => '<select name="map&#x5B;publisher&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;publisher&#x5D;">Publisher</label>',
      'select_without_options' => '<select name="map&#x5B;publisher&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/publishingPlace' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Place of publication</label><select name="publishingPlace" class="form-control"><option value="" selected>— not imported —</option>
</select></div>',
      'element' => '<select name="publishingPlace" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="publishingPlace">Place of publication</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="publishingPlace"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Place of publication</label><select name="publishingPlace" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="publishingPlace" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="publishingPlace"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Place of publication</label><select name="map&#x5B;publishingPlace&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select></div>',
      'element' => '<select name="map&#x5B;publishingPlace&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;publishingPlace&#x5D;">Place of publication</label>',
      'select_without_options' => '<select name="map&#x5B;publishingPlace&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/title' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Title *</label><select name="title" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Required, unless the row carries a Literature ID that names an existing record.</p></div>',
      'element' => '<select name="title" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="title">Title *</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Required, unless the row carries a Literature ID that names an existing record.</p>',
      'select_without_options' => '<select name="title"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Title *</label><select name="title" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">Required, unless the row carries a Literature ID that names an existing record.</p></div>',
      'element' => '<select name="title" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="title"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Title *</label><select name="map&#x5B;title&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">Required, unless the row carries a Literature ID that names an existing record.</p></div>',
      'element' => '<select name="map&#x5B;title&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;title&#x5D;">Title *</label>',
      'select_without_options' => '<select name="map&#x5B;title&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::map/withinLibraryId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Barcode *</label><select name="withinLibraryId" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">The library&#039;s own number for this copy. Required, must be a whole number, and must be unique within the library — it is what an import matches a row to an existing book by.</p></div>',
      'element' => '<select name="withinLibraryId" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="withinLibraryId">Barcode *</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">The library&#039;s own number for this copy. Required, must be a whole number, and must be unique within the library — it is what an import matches a row to an existing book by.</p>',
      'select_without_options' => '<select name="withinLibraryId"><option value="" selected>— not imported —</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Barcode *</label><select name="withinLibraryId" class="form-control"><option value="">— not imported —</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">The library&#039;s own number for this copy. Required, must be a whole number, and must be unique within the library — it is what an import matches a row to an existing book by.</p></div>',
      'element' => '<select name="withinLibraryId" class="form-control"><option value="">— not imported —</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="withinLibraryId"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Barcode *</label><select name="map&#x5B;withinLibraryId&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select><p class="help-block">The library&#039;s own number for this copy. Required, must be a whole number, and must be unique within the library — it is what an import matches a row to an existing book by.</p></div>',
      'element' => '<select name="map&#x5B;withinLibraryId&#x5D;" class="form-control"><option value="" selected>— not imported —</option>
</select>',
      'label' => '<label for="map&#x5B;withinLibraryId&#x5D;">Barcode *</label>',
      'select_without_options' => '<select name="map&#x5B;withinLibraryId&#x5D;"><option value="" selected>— not imported —</option>
</select>',
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Save&#x20;and&#x20;preview">Save and preview</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Save&#x20;and&#x20;preview">Save and preview</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Save&#x20;and&#x20;preview">Save and preview</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\ImportMappingForm::worksheet' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Worksheet</label><select name="worksheet" class="form-control"></select><p class="help-block">This file has several sheets. Choose the one holding the books.</p></div>',
      'element' => '<select name="worksheet" class="form-control"></select>',
      'label' => '<label for="worksheet">Worksheet</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">This file has several sheets. Choose the one holding the books.</p>',
      'select_without_options' => '<select name="worksheet"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\RunImportForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="run_import" action="&#x2F;form-action" class="form-horizontal" id="run_import">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\RunImportForm::digest' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="digest" class="form-control" value="fuzz">',
      'element' => '<input type="hidden" name="digest" class="form-control" value="fuzz">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="digest" value="fuzz">',
      'hidden' => '<input type="hidden" name="digest" value="fuzz">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="digest" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="digest" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="digest" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="digest" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="digest" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="digest" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>The input does not match against pattern &#039;/\\A[0-9a-f]{40}\\z/&#039;</li></ul>',
      'text' => '<input type="text" name="digest" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="digest" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\RunImportForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\Import\\RunImportForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="run-import" class="btn-warning&#x20;btn" value="Run&#x20;this&#x20;import">Run this import</button></div>',
      'element' => '<button type="submit" name="submit" id="run-import" class="btn-warning&#x20;btn" value="Run&#x20;this&#x20;import">Run this import</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="run-import" class="btn-warning&#x20;btn" value="Run&#x20;this&#x20;import">Run this import</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\LibraryDeleteForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="library_delete" action="&#x2F;form-action" class="form-horizontal" id="library_delete">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\LibraryDeleteForm::library_name' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label for="library_name">Type the library name to confirm</label><input type="text" name="library_name" id="library_name" class="form-control&#x20;form-control" autocomplete="off" spellcheck="false" required value=""></div>',
      'element' => '<input type="text" name="library_name" id="library_name" class="form-control&#x20;form-control" autocomplete="off" spellcheck="false" required value="">',
      'label' => '<label for="library_name">Type the library name to confirm</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="library_name" id="library_name" class="form-control" autocomplete="off" spellcheck="false" required value="">',
      'hidden' => '<input type="hidden" name="library_name" id="library_name" class="form-control" autocomplete="off" spellcheck="false" required value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label for="library_name">Type the library name to confirm</label><input type="text" name="library_name" id="library_name" class="form-control&#x20;form-control" autocomplete="off" spellcheck="false" required value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="library_name" id="library_name" class="form-control&#x20;form-control" autocomplete="off" spellcheck="false" required value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="library_name" id="library_name" class="form-control" autocomplete="off" spellcheck="false" required value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="library_name" id="library_name" class="form-control" autocomplete="off" spellcheck="false" required value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label for="library_name">Type the library name to confirm</label><input type="text" name="library_name" id="library_name" class="form-control&#x20;form-control" autocomplete="off" spellcheck="false" required value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>That is not this library&#039;s name. Nothing has been deleted.</li></ul></div>',
      'element' => '<input type="text" name="library_name" id="library_name" class="form-control&#x20;form-control" autocomplete="off" spellcheck="false" required value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>That is not this library&#039;s name. Nothing has been deleted.</li></ul>',
      'text' => '<input type="text" name="library_name" id="library_name" class="form-control" autocomplete="off" spellcheck="false" required value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="library_name" id="library_name" class="form-control" autocomplete="off" spellcheck="false" required value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\LibraryDeleteForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\LibraryDeleteForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn&#x20;btn-danger" value="Delete&#x20;this&#x20;library&#x20;permanently">Delete this library permanently</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn&#x20;btn-danger" value="Delete&#x20;this&#x20;library&#x20;permanently">Delete this library permanently</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn&#x20;btn-danger" value="Delete&#x20;this&#x20;library&#x20;permanently">Delete this library permanently</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\RefreshSortForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="refresh_sort" action="&#x2F;form-action" class="form-horizontal" id="refresh_sort">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\RefreshSortForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'App\\Books\\RefreshSortForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-warning&#x20;btn" value="Refresh&#x20;all&#x20;library&#x20;sort&#x20;text">Refresh all library sort text</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-warning&#x20;btn" value="Refresh&#x20;all&#x20;library&#x20;sort&#x20;text">Refresh all library sort text</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-warning&#x20;btn" value="Refresh&#x20;all&#x20;library&#x20;sort&#x20;text">Refresh all library sort text</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="book" action="&#x2F;form-action" class="form-horizontal" id="book">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::adminNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea>',
      'label' => '<label for="adminNotes">Admin notes</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::adminTags' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin tags</label><select name="adminTags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="adminTags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="adminTags">Admin tags</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="adminTags&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Admin tags</label><select name="adminTags&#x5B;&#x5D;" multiple class="form-control"><option value="" selected></option>
</select></div>',
      'element' => '<select name="adminTags&#x5B;&#x5D;" multiple class="form-control"><option value="" selected></option>
</select>',
      'select_without_options' => '<select name="adminTags&#x5B;&#x5D;" multiple><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::authors' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Author(s)</label><select name="authors&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="1a&#x20;jornada&#x20;de&#x20;dirigentes&#x20;Ypacara&#xED;">1a jornada de dirigentes Ypacaraí</option>
<option value="A.&#x20;Cabr&#xE9;">A. Cabré</option>
<!-- 1886 more options, 534a5bd0fd052c89dfb2229b30db01a4 -->
<option value="Jes&#xFA;s&#x20;Alvarez">Jesús Alvarez</option>
</select></div>',
      'element' => '<select name="authors&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="1a&#x20;jornada&#x20;de&#x20;dirigentes&#x20;Ypacara&#xED;">1a jornada de dirigentes Ypacaraí</option>
<option value="A.&#x20;Cabr&#xE9;">A. Cabré</option>
<!-- 1886 more options, 534a5bd0fd052c89dfb2229b30db01a4 -->
<option value="Jes&#xFA;s&#x20;Alvarez">Jesús Alvarez</option>
</select>',
      'label' => '<label for="authors">Author(s)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="authors&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Author(s)</label><select name="authors&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="1a&#x20;jornada&#x20;de&#x20;dirigentes&#x20;Ypacara&#xED;" selected>1a jornada de dirigentes Ypacaraí</option>
<option value="A.&#x20;Cabr&#xE9;">A. Cabré</option>
<!-- 1886 more options, 534a5bd0fd052c89dfb2229b30db01a4 -->
<option value="Jes&#xFA;s&#x20;Alvarez">Jesús Alvarez</option>
</select></div>',
      'element' => '<select name="authors&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="1a&#x20;jornada&#x20;de&#x20;dirigentes&#x20;Ypacara&#xED;" selected>1a jornada de dirigentes Ypacaraí</option>
<option value="A.&#x20;Cabr&#xE9;">A. Cabré</option>
<!-- 1886 more options, 534a5bd0fd052c89dfb2229b30db01a4 -->
<option value="Jes&#xFA;s&#x20;Alvarez">Jesús Alvarez</option>
</select>',
      'select_without_options' => '<select name="authors&#x5B;&#x5D;" multiple><option value="1a&#x20;jornada&#x20;de&#x20;dirigentes&#x20;Ypacara&#xED;" selected>1a jornada de dirigentes Ypacaraí</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::authorsText' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Author</label><input type="text" name="authorsText" maxlength="500" class="form-control" value=""><p class="help-block">One name, or several separated by a vertical bar.</p></div>',
      'element' => '<input type="text" name="authorsText" maxlength="500" class="form-control" value="">',
      'label' => '<label for="authorsText">Author</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One name, or several separated by a vertical bar.</p>',
      'text' => '<input type="text" name="authorsText" maxlength="500" value="">',
      'hidden' => '<input type="hidden" name="authorsText" maxlength="500" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Author</label><input type="text" name="authorsText" maxlength="500" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">One name, or several separated by a vertical bar.</p></div>',
      'element' => '<input type="text" name="authorsText" maxlength="500" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="authorsText" maxlength="500" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="authorsText" maxlength="500" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Author</label><input type="text" name="authorsText" maxlength="500" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">One name, or several separated by a vertical bar.</p></div>',
      'element' => '<input type="text" name="authorsText" maxlength="500" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="authorsText" maxlength="500" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="authorsText" maxlength="500" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::bookEdition' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Edition</label><input type="text" name="bookEdition" maxlength="50" class="form-control" value=""><p class="help-block">Which edition or printing this copy is — what distinguishes it from another copy of the same work.</p></div>',
      'element' => '<input type="text" name="bookEdition" maxlength="50" class="form-control" value="">',
      'label' => '<label for="bookEdition">Edition</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Which edition or printing this copy is — what distinguishes it from another copy of the same work.</p>',
      'text' => '<input type="text" name="bookEdition" maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="bookEdition" maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Edition</label><input type="text" name="bookEdition" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">Which edition or printing this copy is — what distinguishes it from another copy of the same work.</p></div>',
      'element' => '<input type="text" name="bookEdition" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="bookEdition" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="bookEdition" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Edition</label><input type="text" name="bookEdition" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">Which edition or printing this copy is — what distinguishes it from another copy of the same work.</p></div>',
      'element' => '<input type="text" name="bookEdition" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="bookEdition" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="bookEdition" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::callNumber' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Call number</label><input type="text" name="callNumber" maxlength="50" required class="form-control" value=""><p class="help-block">Where this copy stands on the shelf, as printed on its label.</p></div>',
      'element' => '<input type="text" name="callNumber" maxlength="50" required class="form-control" value="">',
      'label' => '<label for="callNumber">Call number</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Where this copy stands on the shelf, as printed on its label.</p>',
      'text' => '<input type="text" name="callNumber" maxlength="50" required value="">',
      'hidden' => '<input type="hidden" name="callNumber" maxlength="50" required value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Call number</label><input type="text" name="callNumber" maxlength="50" required class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">Where this copy stands on the shelf, as printed on its label.</p></div>',
      'element' => '<input type="text" name="callNumber" maxlength="50" required class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="callNumber" maxlength="50" required value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="callNumber" maxlength="50" required value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Call number</label><input type="text" name="callNumber" maxlength="50" required class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">Where this copy stands on the shelf, as printed on its label.</p></div>',
      'element' => '<input type="text" name="callNumber" maxlength="50" required class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="callNumber" maxlength="50" required value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="callNumber" maxlength="50" required value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::category' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Category</label><select name="category" class="form-control"><option value=""></option>
<option value="A.T.,&#x20;SALMOS.&#x20;BIBLIA">A.T., SALMOS. BIBLIA</option>
<option value="ABUSO&#x20;-&#x20;IGLESIA">ABUSO - IGLESIA</option>
<!-- 812 more options, 05123dc9d49b7ab41732075643cdb92c -->
<option value="ZOOLOGIA">ZOOLOGIA</option>
</select><p class="help-block">One classification for this copy. For several subject terms use Keywords.</p></div>',
      'element' => '<select name="category" class="form-control"><option value=""></option>
<option value="A.T.,&#x20;SALMOS.&#x20;BIBLIA">A.T., SALMOS. BIBLIA</option>
<option value="ABUSO&#x20;-&#x20;IGLESIA">ABUSO - IGLESIA</option>
<!-- 812 more options, 05123dc9d49b7ab41732075643cdb92c -->
<option value="ZOOLOGIA">ZOOLOGIA</option>
</select>',
      'label' => '<label for="category">Category</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One classification for this copy. For several subject terms use Keywords.</p>',
      'select_without_options' => '<select name="category"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Category</label><select name="category" class="form-control"><option value=""></option>
<option value="A.T.,&#x20;SALMOS.&#x20;BIBLIA" selected>A.T., SALMOS. BIBLIA</option>
<option value="ABUSO&#x20;-&#x20;IGLESIA">ABUSO - IGLESIA</option>
<!-- 812 more options, 05123dc9d49b7ab41732075643cdb92c -->
<option value="ZOOLOGIA">ZOOLOGIA</option>
</select><p class="help-block">One classification for this copy. For several subject terms use Keywords.</p></div>',
      'element' => '<select name="category" class="form-control"><option value=""></option>
<option value="A.T.,&#x20;SALMOS.&#x20;BIBLIA" selected>A.T., SALMOS. BIBLIA</option>
<option value="ABUSO&#x20;-&#x20;IGLESIA">ABUSO - IGLESIA</option>
<!-- 812 more options, 05123dc9d49b7ab41732075643cdb92c -->
<option value="ZOOLOGIA">ZOOLOGIA</option>
</select>',
      'select_without_options' => '<select name="category"><option value="A.T.,&#x20;SALMOS.&#x20;BIBLIA" selected>A.T., SALMOS. BIBLIA</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::collectionId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Collection</label><select name="collectionId" class="form-control"><option value="4" selected>General</option>
<option value="6">Pallotti</option>
<option value="5">Schoenstatt</option>
<option value="7">Tesis</option>
</select></div>',
      'element' => '<select name="collectionId" class="form-control"><option value="4" selected>General</option>
<option value="6">Pallotti</option>
<option value="5">Schoenstatt</option>
<option value="7">Tesis</option>
</select>',
      'label' => '<label for="collectionId">Collection</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="collectionId"><option value="4" selected>General</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Collection</label><select name="collectionId" class="form-control"><option value="4">General</option>
<option value="6">Pallotti</option>
<option value="5">Schoenstatt</option>
<option value="7">Tesis</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="collectionId" class="form-control"><option value="4">General</option>
<option value="6">Pallotti</option>
<option value="5">Schoenstatt</option>
<option value="7">Tesis</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="collectionId"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::inLanguage' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select></div>',
      'element' => '<select name="inLanguage&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select>',
      'label' => '<label for="inLanguage">Language</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="inLanguage&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="aa" selected>Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select></div>',
      'element' => '<select name="inLanguage&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="aa" selected>Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select>',
      'select_without_options' => '<select name="inLanguage&#x5B;&#x5D;" multiple><option value="aa" selected>Afar</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::inactivationReason' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Inactivation reason</label><input type="text" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="text" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" class="form-control" value="">',
      'label' => '<label for="inactivationReason">Inactivation reason</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Inactivation reason</label><input type="text" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Inactivation reason</label><input type="text" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="inactivationReason" placeholder="ex.&#x20;Book&#x20;lost" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::isActive' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1" checked> Active?</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1" checked> Active?</label>',
      'label' => '<label for="isActive">Active?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1"> Active?</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1"> Active?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::isbn' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>ISBN</label><input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value=""></div>',
      'element' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value="">',
      'label' => '<label for="isbn">ISBN</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="">',
      'hidden' => '<input type="hidden" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>ISBN</label><input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>ISBN</label><input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::keywords' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Keywords</label><select name="keywords&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="biography">biography</option>
<option value="christ">christ</option>
<!-- 250 more options, a5ff7789a36dd535c6420936dd56ab1a -->
<option value="USA">USA</option>
</select><p class="help-block">Subject terms shown to readers. Several are fine.</p></div>',
      'element' => '<select name="keywords&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="biography">biography</option>
<option value="christ">christ</option>
<!-- 250 more options, a5ff7789a36dd535c6420936dd56ab1a -->
<option value="USA">USA</option>
</select>',
      'label' => '<label for="keywords">Keywords</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Subject terms shown to readers. Several are fine.</p>',
      'select_without_options' => '<select name="keywords&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Keywords</label><select name="keywords&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="biography" selected>biography</option>
<option value="christ">christ</option>
<!-- 250 more options, a5ff7789a36dd535c6420936dd56ab1a -->
<option value="USA">USA</option>
</select><p class="help-block">Subject terms shown to readers. Several are fine.</p></div>',
      'element' => '<select name="keywords&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="biography" selected>biography</option>
<option value="christ">christ</option>
<!-- 250 more options, a5ff7789a36dd535c6420936dd56ab1a -->
<option value="USA">USA</option>
</select>',
      'select_without_options' => '<select name="keywords&#x5B;&#x5D;" multiple><option value="biography" selected>biography</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::libraryId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="libraryId" value="">',
      'hidden' => '<input type="hidden" name="libraryId" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="1">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="1">',
      'text' => '<input type="text" name="libraryId" value="1">',
      'hidden' => '<input type="hidden" name="libraryId" value="1">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="1">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="1">',
      'text' => '<input type="text" name="libraryId" value="1">',
      'hidden' => '<input type="hidden" name="libraryId" value="1">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::newCallNumber' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>New call number</label><input type="text" name="newCallNumber" maxlength="50" class="form-control" value=""><p class="help-block">Use this field when the printed call number should be changed. It will be added to the next labels to be printed.</p></div>',
      'element' => '<input type="text" name="newCallNumber" maxlength="50" class="form-control" value="">',
      'label' => '<label for="newCallNumber">New call number</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Use this field when the printed call number should be changed. It will be added to the next labels to be printed.</p>',
      'text' => '<input type="text" name="newCallNumber" maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="newCallNumber" maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>New call number</label><input type="text" name="newCallNumber" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">Use this field when the printed call number should be changed. It will be added to the next labels to be printed.</p></div>',
      'element' => '<input type="text" name="newCallNumber" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="newCallNumber" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="newCallNumber" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>New call number</label><input type="text" name="newCallNumber" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">Use this field when the printed call number should be changed. It will be added to the next labels to be printed.</p></div>',
      'element' => '<input type="text" name="newCallNumber" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="newCallNumber" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="newCallNumber" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::nextWithinLibraryId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label for="nextWithinLibraryId">Use next free barcode</label><input type="button" name="nextWithinLibraryId" id="nextWithinLibraryId" class="btn&#x20;btn-default&#x20;form-control" value=""></div>',
      'element' => '<input type="button" name="nextWithinLibraryId" id="nextWithinLibraryId" class="btn&#x20;btn-default&#x20;form-control" value="">',
      'label' => '<label for="nextWithinLibraryId">Use next free barcode</label>',
      'errors' => '',
      'help_block' => '',
      'button' => '<button type="button" name="nextWithinLibraryId" id="nextWithinLibraryId" class="btn&#x20;btn-default" value="">Use next free barcode</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::numberOfPages' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Number of pages</label><input type="number" name="numberOfPages" min="0" max="6000" step="any" class="form-control" value=""></div>',
      'element' => '<input type="number" name="numberOfPages" min="0" max="6000" step="any" class="form-control" value="">',
      'label' => '<label for="numberOfPages">Number of pages</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="numberOfPages" value="">',
      'hidden' => '<input type="hidden" name="numberOfPages" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Number of pages</label><input type="number" name="numberOfPages" min="0" max="6000" step="any" class="form-control" value="42"></div>',
      'element' => '<input type="number" name="numberOfPages" min="0" max="6000" step="any" class="form-control" value="42">',
      'text' => '<input type="text" name="numberOfPages" value="42">',
      'hidden' => '<input type="hidden" name="numberOfPages" value="42">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Number of pages</label><input type="number" name="numberOfPages" min="0" max="6000" step="any" class="form-control" value="forty-two"></div>',
      'element' => '<input type="number" name="numberOfPages" min="0" max="6000" step="any" class="form-control" value="forty-two">',
      'text' => '<input type="text" name="numberOfPages" value="forty-two">',
      'hidden' => '<input type="hidden" name="numberOfPages" value="forty-two">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::publicNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Public notes</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea>',
      'label' => '<label for="publicNotes">Public notes</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Public notes</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Public notes</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::publicationId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Linked literature record</label><select name="publicationId" class="form-control"><option value=""></option>
<option value="2154">Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select><p class="help-block">The work in the site-wide literature catalogue that this copy is of. While it is set, a spreadsheet import takes the title, author, year, publisher, place, pages, language and ISBN from that record rather than from the spreadsheet.</p></div>',
      'element' => '<select name="publicationId" class="form-control"><option value=""></option>
<option value="2154">Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select>',
      'label' => '<label for="publicationId">Linked literature record</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">The work in the site-wide literature catalogue that this copy is of. While it is set, a spreadsheet import takes the title, author, year, publisher, place, pages, language and ISBN from that record rather than from the spreadsheet.</p>',
      'select_without_options' => '<select name="publicationId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Linked literature record</label><select name="publicationId" class="form-control"><option value=""></option>
<option value="2154" selected>Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select><p class="help-block">The work in the site-wide literature catalogue that this copy is of. While it is set, a spreadsheet import takes the title, author, year, publisher, place, pages, language and ISBN from that record rather than from the spreadsheet.</p></div>',
      'element' => '<select name="publicationId" class="form-control"><option value=""></option>
<option value="2154" selected>Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select>',
      'select_without_options' => '<select name="publicationId"><option value="2154" selected>Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Linked literature record</label><select name="publicationId" class="form-control"><option value=""></option>
<option value="2154">Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">The work in the site-wide literature catalogue that this copy is of. While it is set, a spreadsheet import takes the title, author, year, publisher, place, pages, language and ISBN from that record rather than from the spreadsheet.</p></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::publishedYear' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Year published</label><input type="number" name="publishedYear" min="1800" max="2027" step="1" class="form-control" value=""></div>',
      'element' => '<input type="number" name="publishedYear" min="1800" max="2027" step="1" class="form-control" value="">',
      'label' => '<label for="publishedYear">Year published</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="publishedYear" value="">',
      'hidden' => '<input type="hidden" name="publishedYear" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Year published</label><input type="number" name="publishedYear" min="1800" max="2027" step="1" class="form-control" value="42"></div>',
      'element' => '<input type="number" name="publishedYear" min="1800" max="2027" step="1" class="form-control" value="42">',
      'text' => '<input type="text" name="publishedYear" value="42">',
      'hidden' => '<input type="hidden" name="publishedYear" value="42">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Year published</label><input type="number" name="publishedYear" min="1800" max="2027" step="1" class="form-control" value="forty-two"></div>',
      'element' => '<input type="number" name="publishedYear" min="1800" max="2027" step="1" class="form-control" value="forty-two">',
      'text' => '<input type="text" name="publishedYear" value="forty-two">',
      'hidden' => '<input type="hidden" name="publishedYear" value="forty-two">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::publisher' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Publisher</label><select name="publisher" class="form-control"><option value=""></option>
<option value="Agape&#x20;Libros">Agape Libros</option>
<option value="Aguilar">Aguilar</option>
<!-- 393 more options, a204bfa44a3b39732571e13294d036ba -->
<option value="zigzag">zigzag</option>
</select></div>',
      'element' => '<select name="publisher" class="form-control"><option value=""></option>
<option value="Agape&#x20;Libros">Agape Libros</option>
<option value="Aguilar">Aguilar</option>
<!-- 393 more options, a204bfa44a3b39732571e13294d036ba -->
<option value="zigzag">zigzag</option>
</select>',
      'label' => '<label for="publisher">Publisher</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="publisher"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Publisher</label><select name="publisher" class="form-control"><option value=""></option>
<option value="Agape&#x20;Libros" selected>Agape Libros</option>
<option value="Aguilar">Aguilar</option>
<!-- 393 more options, a204bfa44a3b39732571e13294d036ba -->
<option value="zigzag">zigzag</option>
</select></div>',
      'element' => '<select name="publisher" class="form-control"><option value=""></option>
<option value="Agape&#x20;Libros" selected>Agape Libros</option>
<option value="Aguilar">Aguilar</option>
<!-- 393 more options, a204bfa44a3b39732571e13294d036ba -->
<option value="zigzag">zigzag</option>
</select>',
      'select_without_options' => '<select name="publisher"><option value="Agape&#x20;Libros" selected>Agape Libros</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::publishingPlace' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Publishing place</label><input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value=""></div>',
      'element' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value="">',
      'label' => '<label for="publishingPlace">Publishing place</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="">',
      'hidden' => '<input type="hidden" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Publishing place</label><input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Publishing place</label><input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::title' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Title</label><input type="text" name="title" required maxlength="300" class="form-control" value=""></div>',
      'element' => '<input type="text" name="title" required maxlength="300" class="form-control" value="">',
      'label' => '<label for="title">Title</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="title" required maxlength="300" value="">',
      'hidden' => '<input type="hidden" name="title" required maxlength="300" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Title</label><input type="text" name="title" required maxlength="300" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="title" required maxlength="300" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="title" required maxlength="300" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="title" required maxlength="300" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Title</label><input type="text" name="title" required maxlength="300" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="title" required maxlength="300" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="title" required maxlength="300" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="title" required maxlength="300" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\BookForm::withinLibraryId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Barcode</label><input type="number" name="withinLibraryId" required min="0" step="1" class="form-control" value=""><p class="help-block">This library&#039;s own number for this copy. It must be unique within the library, and it is what a spreadsheet import matches a row to this book by.</p></div>',
      'element' => '<input type="number" name="withinLibraryId" required min="0" step="1" class="form-control" value="">',
      'label' => '<label for="withinLibraryId">Barcode</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">This library&#039;s own number for this copy. It must be unique within the library, and it is what a spreadsheet import matches a row to this book by.</p>',
      'text' => '<input type="text" name="withinLibraryId" required value="">',
      'hidden' => '<input type="hidden" name="withinLibraryId" required value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Barcode</label><input type="number" name="withinLibraryId" required min="0" step="1" class="form-control" value="42"><p class="help-block">This library&#039;s own number for this copy. It must be unique within the library, and it is what a spreadsheet import matches a row to this book by.</p></div>',
      'element' => '<input type="number" name="withinLibraryId" required min="0" step="1" class="form-control" value="42">',
      'text' => '<input type="text" name="withinLibraryId" required value="42">',
      'hidden' => '<input type="hidden" name="withinLibraryId" required value="42">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Barcode</label><input type="number" name="withinLibraryId" required min="0" step="1" class="form-control" value="forty-two"><ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul><p class="help-block">This library&#039;s own number for this copy. It must be unique within the library, and it is what a spreadsheet import matches a row to this book by.</p></div>',
      'element' => '<input type="number" name="withinLibraryId" required min="0" step="1" class="form-control" value="forty-two">',
      'errors' => '<ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul>',
      'text' => '<input type="text" name="withinLibraryId" required value="forty-two">',
      'hidden' => '<input type="hidden" name="withinLibraryId" required value="forty-two">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CheckinForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="checkin" action="&#x2F;form-action" class="form-horizontal" id="checkin">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CheckinForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CheckinForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CheckinForm::withinLibraryIds' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids to check in</label><textarea name="withinLibraryIds" required tabindex="1" class="form-control"></textarea><p class="help-block">One barcode per line</p></div>',
      'element' => '<textarea name="withinLibraryIds" required tabindex="1" class="form-control"></textarea>',
      'label' => '<label for="withinLibraryIds">Book Ids to check in</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One barcode per line</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids to check in</label><textarea name="withinLibraryIds" required tabindex="1" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">One barcode per line</p></div>',
      'element' => '<textarea name="withinLibraryIds" required tabindex="1" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids to check in</label><textarea name="withinLibraryIds" required tabindex="1" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><p class="help-block">One barcode per line</p></div>',
      'element' => '<textarea name="withinLibraryIds" required tabindex="1" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CheckoutForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="checkout" action="&#x2F;form-action" class="form-horizontal" id="checkout">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CheckoutForm::adminNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Notes</label><textarea name="adminNotes" rows="3" tabindex="3" class="form-control"></textarea></div>',
      'element' => '<textarea name="adminNotes" rows="3" tabindex="3" class="form-control"></textarea>',
      'label' => '<label for="adminNotes">Notes</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Notes</label><textarea name="adminNotes" rows="3" tabindex="3" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="adminNotes" rows="3" tabindex="3" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Notes</label><textarea name="adminNotes" rows="3" tabindex="3" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="adminNotes" rows="3" tabindex="3" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CheckoutForm::personId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Who&#039;s checking out?</label><select name="personId" tabindex="2" class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="personId" tabindex="2" class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="personId">Who&#039;s checking out?</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="personId" tabindex="2"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Who&#039;s checking out?</label><select name="personId" tabindex="2" class="form-control"><option value="" selected></option>
</select></div>',
      'element' => '<select name="personId" tabindex="2" class="form-control"><option value="" selected></option>
</select>',
      'select_without_options' => '<select name="personId" tabindex="2"><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CheckoutForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CheckoutForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" tabindex="4" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" tabindex="4" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" tabindex="4" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CheckoutForm::withinLibraryIds' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control"></textarea><p class="help-block">One barcode per line</p></div>',
      'element' => '<textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control"></textarea>',
      'label' => '<label for="withinLibraryIds">Book Ids</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One barcode per line</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">One barcode per line</p></div>',
      'element' => '<textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul><p class="help-block">One barcode per line</p></div>',
      'element' => '<textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
      'errors' => '<ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="post" name="collection" action="&#x2F;form-action" class="form-horizontal" id="collection">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::abbreviation' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Abbreviation</label><input type="text" name="abbreviation" maxlength="12" class="form-control" value=""><p class="help-block">This abbreviation will help sort search results. Keep it short to preserve database space since it is added to each book.</p></div>',
      'element' => '<input type="text" name="abbreviation" maxlength="12" class="form-control" value="">',
      'label' => '<label for="abbreviation">Abbreviation</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">This abbreviation will help sort search results. Keep it short to preserve database space since it is added to each book.</p>',
      'text' => '<input type="text" name="abbreviation" maxlength="12" value="">',
      'hidden' => '<input type="hidden" name="abbreviation" maxlength="12" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Abbreviation</label><input type="text" name="abbreviation" maxlength="12" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">This abbreviation will help sort search results. Keep it short to preserve database space since it is added to each book.</p></div>',
      'element' => '<input type="text" name="abbreviation" maxlength="12" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="abbreviation" maxlength="12" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="abbreviation" maxlength="12" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Abbreviation</label><input type="text" name="abbreviation" maxlength="12" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">This abbreviation will help sort search results. Keep it short to preserve database space since it is added to each book.</p></div>',
      'element' => '<input type="text" name="abbreviation" maxlength="12" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="abbreviation" maxlength="12" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="abbreviation" maxlength="12" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::adminNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea>',
      'label' => '<label for="adminNotes">Admin notes</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::callNumberExplanation' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Call number explanation</label><textarea name="callNumberExplanation" maxlength="1000" class="form-control"></textarea></div>',
      'element' => '<textarea name="callNumberExplanation" maxlength="1000" class="form-control"></textarea>',
      'label' => '<label for="callNumberExplanation">Call number explanation</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Call number explanation</label><textarea name="callNumberExplanation" maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="callNumberExplanation" maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Call number explanation</label><textarea name="callNumberExplanation" maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="callNumberExplanation" maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::callNumberHelpText' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Call number help text</label><input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value="">',
      'label' => '<label for="callNumberHelpText">Call number help text</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="callNumberHelpText" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="callNumberHelpText" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Call number help text</label><input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="callNumberHelpText" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="callNumberHelpText" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Call number help text</label><input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="callNumberHelpText" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="callNumberHelpText" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::callNumberRegex' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Call number regex</label><input type="text" name="callNumberRegex" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="text" name="callNumberRegex" maxlength="255" class="form-control" value="">',
      'label' => '<label for="callNumberRegex">Call number regex</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="callNumberRegex" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="callNumberRegex" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Call number regex</label><input type="text" name="callNumberRegex" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="callNumberRegex" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="callNumberRegex" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="callNumberRegex" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Call number regex</label><input type="text" name="callNumberRegex" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="callNumberRegex" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="callNumberRegex" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="callNumberRegex" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::defaultCheckoutTimePeriodInDays' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Default checkout time period (days)</label><input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="14"></div>',
      'element' => '<input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="14">',
      'label' => '<label for="defaultCheckoutTimePeriodInDays">Default checkout time period (days)</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="defaultCheckoutTimePeriodInDays" value="14">',
      'hidden' => '<input type="hidden" name="defaultCheckoutTimePeriodInDays" value="14">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Default checkout time period (days)</label><input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="42"></div>',
      'element' => '<input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="42">',
      'text' => '<input type="text" name="defaultCheckoutTimePeriodInDays" value="42">',
      'hidden' => '<input type="hidden" name="defaultCheckoutTimePeriodInDays" value="42">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Default checkout time period (days)</label><input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="forty-two"></div>',
      'element' => '<input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="forty-two">',
      'text' => '<input type="text" name="defaultCheckoutTimePeriodInDays" value="forty-two">',
      'hidden' => '<input type="hidden" name="defaultCheckoutTimePeriodInDays" value="forty-two">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::description' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="3" maxlength="1000" class="form-control"></textarea></div>',
      'element' => '<textarea name="description" rows="3" maxlength="1000" class="form-control"></textarea>',
      'label' => '<label for="description">Description</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="3" maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="description" rows="3" maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="3" maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="description" rows="3" maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::enforceCallNumberRegex' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="enforceCallNumberRegex" value="0"><label><input type="checkbox" name="enforceCallNumberRegex" value="1" checked> Enforce call number regex?</label></div>',
      'element' => '<input type="hidden" name="enforceCallNumberRegex" value="0"><label><input type="checkbox" name="enforceCallNumberRegex" value="1" checked> Enforce call number regex?</label>',
      'label' => '<label for="enforceCallNumberRegex">Enforce call number regex?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="enforceCallNumberRegex" value="0"><label><input type="checkbox" name="enforceCallNumberRegex" value="1"> Enforce call number regex?</label></div>',
      'element' => '<input type="hidden" name="enforceCallNumberRegex" value="0"><label><input type="checkbox" name="enforceCallNumberRegex" value="1"> Enforce call number regex?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::isActive' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1" checked> Active?</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1" checked> Active?</label>',
      'label' => '<label for="isActive">Active?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1"> Active?</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1"> Active?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::labelLine1' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Label line 1</label><input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value=""><p class="help-block">The three label line fields describe how to format a label for the spine of a book.</p></div>',
      'element' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value="">',
      'label' => '<label for="labelLine1">Label line 1</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">The three label line fields describe how to format a label for the spine of a book.</p>',
      'text' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Label line 1</label><input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">The three label line fields describe how to format a label for the spine of a book.</p></div>',
      'element' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Label line 1</label><input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">The three label line fields describe how to format a label for the spine of a book.</p></div>',
      'element' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::labelLine2' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Label line 2</label><input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value="">',
      'label' => '<label for="labelLine2">Label line 2</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Label line 2</label><input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Label line 2</label><input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::labelLine3' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Label line 3</label><input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value="">',
      'label' => '<label for="labelLine3">Label line 3</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Label line 3</label><input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Label line 3</label><input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::libraryId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="1">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="1">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="libraryId" value="1">',
      'hidden' => '<input type="hidden" name="libraryId" value="1">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="libraryId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="libraryId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="libraryId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="libraryId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::mainShowDisplay' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Main view format</label><select name="mainShowDisplay" class="form-control"><option value=""></option>
<option value="show-categories" selected>Show categories</option>
<option value="show-collections">Show collections</option>
<option value="show-collections-categories">Show collections and categories</option>
</select></div>',
      'element' => '<select name="mainShowDisplay" class="form-control"><option value=""></option>
<option value="show-categories" selected>Show categories</option>
<option value="show-collections">Show collections</option>
<option value="show-collections-categories">Show collections and categories</option>
</select>',
      'label' => '<label for="mainShowDisplay">Main view format</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="mainShowDisplay"><option value="show-categories" selected>Show categories</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Main view format</label><select name="mainShowDisplay" class="form-control"><option value=""></option>
<option value="show-categories">Show categories</option>
<option value="show-collections">Show collections</option>
<option value="show-collections-categories">Show collections and categories</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="mainShowDisplay" class="form-control"><option value=""></option>
<option value="show-categories">Show categories</option>
<option value="show-collections">Show collections</option>
<option value="show-collections-categories">Show collections and categories</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="mainShowDisplay"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::name' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Name</label><input type="text" name="name" maxlength="50" class="form-control" value=""></div>',
      'element' => '<input type="text" name="name" maxlength="50" class="form-control" value="">',
      'label' => '<label for="name">Name</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="name" maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="name" maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Name</label><input type="text" name="name" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="name" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="name" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="name" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Name</label><input type="text" name="name" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="name" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="name" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="name" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::requireCallNumbers' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="requireCallNumbers" value="0"><label><input type="checkbox" name="requireCallNumbers" value="1" checked> Require call numbers?</label></div>',
      'element' => '<input type="hidden" name="requireCallNumbers" value="0"><label><input type="checkbox" name="requireCallNumbers" value="1" checked> Require call numbers?</label>',
      'label' => '<label for="requireCallNumbers">Require call numbers?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="requireCallNumbers" value="0"><label><input type="checkbox" name="requireCallNumbers" value="1"> Require call numbers?</label></div>',
      'element' => '<input type="hidden" name="requireCallNumbers" value="0"><label><input type="checkbox" name="requireCallNumbers" value="1"> Require call numbers?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::sortTextFormat' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Sort text format</label><input type="text" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" class="form-control" value=""><p class="help-block">Format text to pass to sprintf. See https://secure.php.net/manual/en/function.sprintf.php for more info. The first parameter passed is the collection abbreviation followed by the capture groups of the call number.</p></div>',
      'element' => '<input type="text" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" class="form-control" value="">',
      'label' => '<label for="sortTextFormat">Sort text format</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Format text to pass to sprintf. See https://secure.php.net/manual/en/function.sprintf.php for more info. The first parameter passed is the collection abbreviation followed by the capture groups of the call number.</p>',
      'text' => '<input type="text" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Sort text format</label><input type="text" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">Format text to pass to sprintf. See https://secure.php.net/manual/en/function.sprintf.php for more info. The first parameter passed is the collection abbreviation followed by the capture groups of the call number.</p></div>',
      'element' => '<input type="text" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Sort text format</label><input type="text" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">Format text to pass to sprintf. See https://secure.php.net/manual/en/function.sprintf.php for more info. The first parameter passed is the collection abbreviation followed by the capture groups of the call number.</p></div>',
      'element' => '<input type="text" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="sortTextFormat" placeholder="&#x25;1&#x24;s&#x25;2&#x24;-8s&#x25;3&#x24;04d&#x25;4&#x24;03d&#x25;5&#x24;03d" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CollectionForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="composition" action="&#x2F;form-action" class="form-horizontal" id="composition">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::chordProSpec' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Chord pro specification</label><textarea name="chordProSpec" maxlength="2000" rows="8" class="form-control"></textarea><p class="help-block">See <a href="https://www.chordpro.org/">Chord pro markup</a>. The metadata will be automatically added afterwards. Html tags are not allowed.</p></div>',
      'element' => '<textarea name="chordProSpec" maxlength="2000" rows="8" class="form-control"></textarea>',
      'label' => '<label for="chordProSpec">Chord pro specification</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">See &lt;a href=&quot;https://www.chordpro.org/&quot;&gt;Chord pro markup&lt;/a&gt;. The metadata will be automatically added afterwards. Html tags are not allowed.</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Chord pro specification</label><textarea name="chordProSpec" maxlength="2000" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">See <a href="https://www.chordpro.org/">Chord pro markup</a>. The metadata will be automatically added afterwards. Html tags are not allowed.</p></div>',
      'element' => '<textarea name="chordProSpec" maxlength="2000" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Chord pro specification</label><textarea name="chordProSpec" maxlength="2000" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><p class="help-block">See <a href="https://www.chordpro.org/">Chord pro markup</a>. The metadata will be automatically added afterwards. Html tags are not allowed.</p></div>',
      'element' => '<textarea name="chordProSpec" maxlength="2000" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::composersAll' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Composer(s)</label><select name="composersAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="composersAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="composersAll">Composer(s)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="composersAll&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Composer(s)</label><select name="composersAll&#x5B;&#x5D;" multiple class="form-control"><option value="" selected></option>
</select></div>',
      'element' => '<select name="composersAll&#x5B;&#x5D;" multiple class="form-control"><option value="" selected></option>
</select>',
      'select_without_options' => '<select name="composersAll&#x5B;&#x5D;" multiple><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::copyrightContactEmail' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Copyright contact email</label><input type="email" name="copyrightContactEmail" maxlength="70" class="form-control" value=""></div>',
      'element' => '<input type="email" name="copyrightContactEmail" maxlength="70" class="form-control" value="">',
      'label' => '<label for="copyrightContactEmail">Copyright contact email</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="copyrightContactEmail" maxlength="70" value="">',
      'hidden' => '<input type="hidden" name="copyrightContactEmail" maxlength="70" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Copyright contact email</label><input type="email" name="copyrightContactEmail" maxlength="70" class="form-control" value="baseline&#x40;example.com"></div>',
      'element' => '<input type="email" name="copyrightContactEmail" maxlength="70" class="form-control" value="baseline&#x40;example.com">',
      'text' => '<input type="text" name="copyrightContactEmail" maxlength="70" value="baseline&#x40;example.com">',
      'hidden' => '<input type="hidden" name="copyrightContactEmail" maxlength="70" value="baseline&#x40;example.com">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Copyright contact email</label><input type="email" name="copyrightContactEmail" maxlength="70" class="form-control" value="not-an-email"><ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul></div>',
      'element' => '<input type="email" name="copyrightContactEmail" maxlength="70" class="form-control" value="not-an-email">',
      'errors' => '<ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul>',
      'text' => '<input type="text" name="copyrightContactEmail" maxlength="70" value="not-an-email">',
      'hidden' => '<input type="hidden" name="copyrightContactEmail" maxlength="70" value="not-an-email">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::copyrightInfo' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Copyright info</label><textarea name="copyrightInfo" maxlength="500" class="form-control"></textarea><p class="help-block">Include information about the copyright owner, contact information, and under what licence it has been published.</p></div>',
      'element' => '<textarea name="copyrightInfo" maxlength="500" class="form-control"></textarea>',
      'label' => '<label for="copyrightInfo">Copyright info</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Include information about the copyright owner, contact information, and under what licence it has been published.</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Copyright info</label><textarea name="copyrightInfo" maxlength="500" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">Include information about the copyright owner, contact information, and under what licence it has been published.</p></div>',
      'element' => '<textarea name="copyrightInfo" maxlength="500" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Copyright info</label><textarea name="copyrightInfo" maxlength="500" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><p class="help-block">Include information about the copyright owner, contact information, and under what licence it has been published.</p></div>',
      'element' => '<textarea name="copyrightInfo" maxlength="500" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::country' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Country of origin</label><select name="country" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select></div>',
      'element' => '<select name="country" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select>',
      'label' => '<label for="country">Country of origin</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="country"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Country of origin</label><select name="country" class="form-control"><option value=""></option>
<option value="AF" selected>Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select></div>',
      'element' => '<select name="country" class="form-control"><option value=""></option>
<option value="AF" selected>Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select>',
      'select_without_options' => '<select name="country"><option value="AF" selected>Afghanistan</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Country of origin</label><select name="country" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::derivedFromCompositionId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Derived or translated from</label><select name="derivedFromCompositionId" class="form-control"><option value=""></option>
<option value="372">Website with many Schoenstatt songs</option>
<option value="320">Kyrie, eleison</option>
<!-- 332 more options, cee9d192143c3436d29ea77f0ecbb198 -->
<option value="182">Wait for the Lord (Confia em Deus)</option>
</select></div>',
      'element' => '<select name="derivedFromCompositionId" class="form-control"><option value=""></option>
<option value="372">Website with many Schoenstatt songs</option>
<option value="320">Kyrie, eleison</option>
<!-- 332 more options, cee9d192143c3436d29ea77f0ecbb198 -->
<option value="182">Wait for the Lord (Confia em Deus)</option>
</select>',
      'label' => '<label for="derivedFromCompositionId">Derived or translated from</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="derivedFromCompositionId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Derived or translated from</label><select name="derivedFromCompositionId" class="form-control"><option value=""></option>
<option value="372" selected>Website with many Schoenstatt songs</option>
<option value="320">Kyrie, eleison</option>
<!-- 332 more options, cee9d192143c3436d29ea77f0ecbb198 -->
<option value="182">Wait for the Lord (Confia em Deus)</option>
</select></div>',
      'element' => '<select name="derivedFromCompositionId" class="form-control"><option value=""></option>
<option value="372" selected>Website with many Schoenstatt songs</option>
<option value="320">Kyrie, eleison</option>
<!-- 332 more options, cee9d192143c3436d29ea77f0ecbb198 -->
<option value="182">Wait for the Lord (Confia em Deus)</option>
</select>',
      'select_without_options' => '<select name="derivedFromCompositionId"><option value="372" selected>Website with many Schoenstatt songs</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Derived or translated from</label><select name="derivedFromCompositionId" class="form-control"><option value=""></option>
<option value="372">Website with many Schoenstatt songs</option>
<option value="320">Kyrie, eleison</option>
<!-- 332 more options, cee9d192143c3436d29ea77f0ecbb198 -->
<option value="182">Wait for the Lord (Confia em Deus)</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::disambiguatingDescription' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Disambiguating subtitle</label><input type="text" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" class="form-control" value=""><p class="help-block">Please only use when composition needs to be distinguished from another similarly-named composition.</p></div>',
      'element' => '<input type="text" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" class="form-control" value="">',
      'label' => '<label for="disambiguatingDescription">Disambiguating subtitle</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Please only use when composition needs to be distinguished from another similarly-named composition.</p>',
      'text' => '<input type="text" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Disambiguating subtitle</label><input type="text" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">Please only use when composition needs to be distinguished from another similarly-named composition.</p></div>',
      'element' => '<input type="text" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Disambiguating subtitle</label><input type="text" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">Please only use when composition needs to be distinguished from another similarly-named composition.</p></div>',
      'element' => '<input type="text" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="disambiguatingDescription" placeholder="ex.&#x20;Santo&#x20;de&#x20;la&#x20;misa&#x20;criolla" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::inLanguage' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage" class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select></div>',
      'element' => '<select name="inLanguage" class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select>',
      'label' => '<label for="inLanguage">Language</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="inLanguage"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage" class="form-control"><option value=""></option>
<option value="aa" selected>Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select></div>',
      'element' => '<select name="inLanguage" class="form-control"><option value=""></option>
<option value="aa" selected>Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select>',
      'select_without_options' => '<select name="inLanguage"><option value="aa" selected>Afar</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage" class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::lilyPondSpec' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>LilyPond music notation</label><textarea name="lilyPondSpec" maxlength="5000" rows="8" class="form-control"></textarea><p class="help-block">See <a href="http://lilypond.org/">LilyPond markup</a>.</p></div>',
      'element' => '<textarea name="lilyPondSpec" maxlength="5000" rows="8" class="form-control"></textarea>',
      'label' => '<label for="lilyPondSpec">LilyPond music notation</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">See &lt;a href=&quot;http://lilypond.org/&quot;&gt;LilyPond markup&lt;/a&gt;.</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>LilyPond music notation</label><textarea name="lilyPondSpec" maxlength="5000" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">See <a href="http://lilypond.org/">LilyPond markup</a>.</p></div>',
      'element' => '<textarea name="lilyPondSpec" maxlength="5000" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>LilyPond music notation</label><textarea name="lilyPondSpec" maxlength="5000" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><p class="help-block">See <a href="http://lilypond.org/">LilyPond markup</a>.</p></div>',
      'element' => '<textarea name="lilyPondSpec" maxlength="5000" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::lyricistsAll' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Lyricist(s)</label><select name="lyricistsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="lyricistsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="lyricistsAll">Lyricist(s)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="lyricistsAll&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Lyricist(s)</label><select name="lyricistsAll&#x5B;&#x5D;" multiple class="form-control"><option value="" selected></option>
</select></div>',
      'element' => '<select name="lyricistsAll&#x5B;&#x5D;" multiple class="form-control"><option value="" selected></option>
</select>',
      'select_without_options' => '<select name="lyricistsAll&#x5B;&#x5D;" multiple><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::lyrics' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Lyrics</label><textarea name="lyrics" maxlength="500" rows="6" class="form-control"></textarea><p class="help-block">If song is specified with chord pro, it&#039;s not necessary to fill in the lyrics separately.</p></div>',
      'element' => '<textarea name="lyrics" maxlength="500" rows="6" class="form-control"></textarea>',
      'label' => '<label for="lyrics">Lyrics</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">If song is specified with chord pro, it&#039;s not necessary to fill in the lyrics separately.</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Lyrics</label><textarea name="lyrics" maxlength="500" rows="6" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">If song is specified with chord pro, it&#039;s not necessary to fill in the lyrics separately.</p></div>',
      'element' => '<textarea name="lyrics" maxlength="500" rows="6" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Lyrics</label><textarea name="lyrics" maxlength="500" rows="6" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><p class="help-block">If song is specified with chord pro, it&#039;s not necessary to fill in the lyrics separately.</p></div>',
      'element' => '<textarea name="lyrics" maxlength="500" rows="6" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::name' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Composition name</label><input type="text" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" class="form-control" value=""></div>',
      'element' => '<input type="text" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" class="form-control" value="">',
      'label' => '<label for="name">Composition name</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" value="">',
      'hidden' => '<input type="hidden" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Composition name</label><input type="text" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Composition name</label><input type="text" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="name" required placeholder="ex.&#x20;Mar&#xED;a&#x20;de&#x20;la&#x20;Alianza" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::openLicenseUrl' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Creative commons license</label><select name="openLicenseUrl" class="form-control"><option value=""></option>
<option value="https&#x3A;&#x2F;&#x2F;creativecommons.org&#x2F;licenses&#x2F;by&#x2F;4.0">Attribution 4.0</option>
<option value="https&#x3A;&#x2F;&#x2F;creativecommons.org&#x2F;licenses&#x2F;by-sa&#x2F;4.0">Attribution-ShareAlike 4.0</option>
<!-- 5 more options, c3693e71c35cc6b730c4f26704453c89 -->
<option value="https&#x3A;&#x2F;&#x2F;wiki.creativecommons.org&#x2F;wiki&#x2F;Public_domain">Public domain</option>
</select><p class="help-block">If you speak with the original artist (copyright holder), please consider requesting that they release their song(s) under one of the <a href="https://creativecommons.org/licenses/">Creative Commons licenses</a>. This doesn\'t mean they need to "surrender" their copyrights, but instead sets the "default permissions" for using the song. If they decide to release it under one of these licenses, please ask for an email containing this decision and forward it to <a href="mailto:webmaster@schoenstatt.link">webmaster@schoenstatt.link</a>.</p></div>',
      'element' => '<select name="openLicenseUrl" class="form-control"><option value=""></option>
<option value="https&#x3A;&#x2F;&#x2F;creativecommons.org&#x2F;licenses&#x2F;by&#x2F;4.0">Attribution 4.0</option>
<option value="https&#x3A;&#x2F;&#x2F;creativecommons.org&#x2F;licenses&#x2F;by-sa&#x2F;4.0">Attribution-ShareAlike 4.0</option>
<!-- 5 more options, c3693e71c35cc6b730c4f26704453c89 -->
<option value="https&#x3A;&#x2F;&#x2F;wiki.creativecommons.org&#x2F;wiki&#x2F;Public_domain">Public domain</option>
</select>',
      'label' => '<label for="openLicenseUrl">Creative commons license</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">If you speak with the original artist (copyright holder), please consider requesting that they release their song(s) under one of the &lt;a href=&quot;https://creativecommons.org/licenses/&quot;&gt;Creative Commons licenses&lt;/a&gt;. This doesn&#039;t mean they need to &quot;surrender&quot; their copyrights, but instead sets the &quot;default permissions&quot; for using the song. If they decide to release it under one of these licenses, please ask for an email containing this decision and forward it to &lt;a href=&quot;mailto:webmaster@schoenstatt.link&quot;&gt;webmaster@schoenstatt.link&lt;/a&gt;.</p>',
      'select_without_options' => '<select name="openLicenseUrl"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Creative commons license</label><select name="openLicenseUrl" class="form-control"><option value=""></option>
<option value="https&#x3A;&#x2F;&#x2F;creativecommons.org&#x2F;licenses&#x2F;by&#x2F;4.0" selected>Attribution 4.0</option>
<option value="https&#x3A;&#x2F;&#x2F;creativecommons.org&#x2F;licenses&#x2F;by-sa&#x2F;4.0">Attribution-ShareAlike 4.0</option>
<!-- 5 more options, c3693e71c35cc6b730c4f26704453c89 -->
<option value="https&#x3A;&#x2F;&#x2F;wiki.creativecommons.org&#x2F;wiki&#x2F;Public_domain">Public domain</option>
</select><p class="help-block">If you speak with the original artist (copyright holder), please consider requesting that they release their song(s) under one of the <a href="https://creativecommons.org/licenses/">Creative Commons licenses</a>. This doesn\'t mean they need to "surrender" their copyrights, but instead sets the "default permissions" for using the song. If they decide to release it under one of these licenses, please ask for an email containing this decision and forward it to <a href="mailto:webmaster@schoenstatt.link">webmaster@schoenstatt.link</a>.</p></div>',
      'element' => '<select name="openLicenseUrl" class="form-control"><option value=""></option>
<option value="https&#x3A;&#x2F;&#x2F;creativecommons.org&#x2F;licenses&#x2F;by&#x2F;4.0" selected>Attribution 4.0</option>
<option value="https&#x3A;&#x2F;&#x2F;creativecommons.org&#x2F;licenses&#x2F;by-sa&#x2F;4.0">Attribution-ShareAlike 4.0</option>
<!-- 5 more options, c3693e71c35cc6b730c4f26704453c89 -->
<option value="https&#x3A;&#x2F;&#x2F;wiki.creativecommons.org&#x2F;wiki&#x2F;Public_domain">Public domain</option>
</select>',
      'select_without_options' => '<select name="openLicenseUrl"><option value="https&#x3A;&#x2F;&#x2F;creativecommons.org&#x2F;licenses&#x2F;by&#x2F;4.0" selected>Attribution 4.0</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Creative commons license</label><select name="openLicenseUrl" class="form-control"><option value=""></option>
<option value="https&#x3A;&#x2F;&#x2F;creativecommons.org&#x2F;licenses&#x2F;by&#x2F;4.0">Attribution 4.0</option>
<option value="https&#x3A;&#x2F;&#x2F;creativecommons.org&#x2F;licenses&#x2F;by-sa&#x2F;4.0">Attribution-ShareAlike 4.0</option>
<!-- 5 more options, c3693e71c35cc6b730c4f26704453c89 -->
<option value="https&#x3A;&#x2F;&#x2F;wiki.creativecommons.org&#x2F;wiki&#x2F;Public_domain">Public domain</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">If you speak with the original artist (copyright holder), please consider requesting that they release their song(s) under one of the <a href="https://creativecommons.org/licenses/">Creative Commons licenses</a>. This doesn\'t mean they need to "surrender" their copyrights, but instead sets the "default permissions" for using the song. If they decide to release it under one of these licenses, please ask for an email containing this decision and forward it to <a href="mailto:webmaster@schoenstatt.link">webmaster@schoenstatt.link</a>.</p></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::tags' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Tags</label><select name="tags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="Liturgia-Perd&#xE3;o">Liturgia-Perdão</option>
<option value="Indice&#x3A;Ingl&#xEA;s">Indice:Inglês</option>
<!-- 50 more options, 7e911cd4658612811b6bd7aa93821470 -->
<option value="Peregrina&#xE7;&#xE3;o&#x20;2008">Peregrinação 2008</option>
</select><p class="help-block">Tags regarding the content, form or liturgical use of the song.</p></div>',
      'element' => '<select name="tags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="Liturgia-Perd&#xE3;o">Liturgia-Perdão</option>
<option value="Indice&#x3A;Ingl&#xEA;s">Indice:Inglês</option>
<!-- 50 more options, 7e911cd4658612811b6bd7aa93821470 -->
<option value="Peregrina&#xE7;&#xE3;o&#x20;2008">Peregrinação 2008</option>
</select>',
      'label' => '<label for="tags">Tags</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Tags regarding the content, form or liturgical use of the song.</p>',
      'select_without_options' => '<select name="tags&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Tags</label><select name="tags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="Liturgia-Perd&#xE3;o" selected>Liturgia-Perdão</option>
<option value="Indice&#x3A;Ingl&#xEA;s">Indice:Inglês</option>
<!-- 50 more options, 7e911cd4658612811b6bd7aa93821470 -->
<option value="Peregrina&#xE7;&#xE3;o&#x20;2008">Peregrinação 2008</option>
</select><p class="help-block">Tags regarding the content, form or liturgical use of the song.</p></div>',
      'element' => '<select name="tags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="Liturgia-Perd&#xE3;o" selected>Liturgia-Perdão</option>
<option value="Indice&#x3A;Ingl&#xEA;s">Indice:Inglês</option>
<!-- 50 more options, 7e911cd4658612811b6bd7aa93821470 -->
<option value="Peregrina&#xE7;&#xE3;o&#x20;2008">Peregrinação 2008</option>
</select>',
      'select_without_options' => '<select name="tags&#x5B;&#x5D;" multiple><option value="Liturgia-Perd&#xE3;o" selected>Liturgia-Perdão</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::url1' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="">',
      'label' => '<label for="url1">URL 1</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::url1Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 1 Label</label><select name="url1Label" class="form-control"><option value=""></option>
<option value="Album">Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select></div>',
      'element' => '<select name="url1Label" class="form-control"><option value=""></option>
<option value="Album">Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select>',
      'label' => '<label for="url1Label">URL 1 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url1Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 1 Label</label><select name="url1Label" class="form-control"><option value=""></option>
<option value="Album" selected>Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select></div>',
      'element' => '<select name="url1Label" class="form-control"><option value=""></option>
<option value="Album" selected>Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select>',
      'select_without_options' => '<select name="url1Label"><option value="Album" selected>Album</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::url2' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="">',
      'label' => '<label for="url2">URL 2</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::url2Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 2 Label</label><select name="url2Label" class="form-control"><option value=""></option>
<option value="Album">Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select></div>',
      'element' => '<select name="url2Label" class="form-control"><option value=""></option>
<option value="Album">Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select>',
      'label' => '<label for="url2Label">URL 2 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url2Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 2 Label</label><select name="url2Label" class="form-control"><option value=""></option>
<option value="Album" selected>Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select></div>',
      'element' => '<select name="url2Label" class="form-control"><option value=""></option>
<option value="Album" selected>Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select>',
      'select_without_options' => '<select name="url2Label"><option value="Album" selected>Album</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::url3' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="">',
      'label' => '<label for="url3">URL 3</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::url3Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 3 Label</label><select name="url3Label" class="form-control"><option value=""></option>
<option value="Album">Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select></div>',
      'element' => '<select name="url3Label" class="form-control"><option value=""></option>
<option value="Album">Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select>',
      'label' => '<label for="url3Label">URL 3 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url3Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 3 Label</label><select name="url3Label" class="form-control"><option value=""></option>
<option value="Album" selected>Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select></div>',
      'element' => '<select name="url3Label" class="form-control"><option value=""></option>
<option value="Album" selected>Album</option>
<option value="Lyrics">Lyrics</option>
<!-- 1 more options, bf1d7c7b219e88a0ea34fdf4a5b759d9 -->
<option value="Reference">Reference</option>
</select>',
      'select_without_options' => '<select name="url3Label"><option value="Album" selected>Album</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CompositionForm::yearPublished' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Published date</label><input type="text" name="yearPublished" placeholder="2007" class="form-control" value=""><p class="help-block">The year is plenty; if a more specific date is available, use format YYYY-MM-DD</p></div>',
      'element' => '<input type="text" name="yearPublished" placeholder="2007" class="form-control" value="">',
      'label' => '<label for="yearPublished">Published date</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">The year is plenty; if a more specific date is available, use format YYYY-MM-DD</p>',
      'text' => '<input type="text" name="yearPublished" placeholder="2007" value="">',
      'hidden' => '<input type="hidden" name="yearPublished" placeholder="2007" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Published date</label><input type="text" name="yearPublished" placeholder="2007" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">The year is plenty; if a more specific date is available, use format YYYY-MM-DD</p></div>',
      'element' => '<input type="text" name="yearPublished" placeholder="2007" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="yearPublished" placeholder="2007" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="yearPublished" placeholder="2007" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Published date</label><input type="text" name="yearPublished" placeholder="2007" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>Please enter a valid date. Remember to add a `0` before single digit month and day numbers.</li></ul><p class="help-block">The year is plenty; if a more specific date is available, use format YYYY-MM-DD</p></div>',
      'element' => '<input type="text" name="yearPublished" placeholder="2007" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Please enter a valid date. Remember to add a `0` before single digit month and day numbers.</li></ul>',
      'text' => '<input type="text" name="yearPublished" placeholder="2007" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="yearPublished" placeholder="2007" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CopyToMainCorpusForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="post" name="publication_copy_to_main_corpus" action="&#x2F;form-action" class="form-horizontal" id="publication_copy_to_main_corpus">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CopyToMainCorpusForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CopyToMainCorpusForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="copy-to-main-corpus-submit" class="btn&#x20;btn-primary" value="Copy&#x20;into&#x20;main&#x20;corpus">Copy into main corpus</button></div>',
      'element' => '<button type="submit" name="submit" id="copy-to-main-corpus-submit" class="btn&#x20;btn-primary" value="Copy&#x20;into&#x20;main&#x20;corpus">Copy into main corpus</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="copy-to-main-corpus-submit" class="btn&#x20;btn-primary" value="Copy&#x20;into&#x20;main&#x20;corpus">Copy into main corpus</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CreateNewEditionForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="post" name="publication_create_new_edition" action="&#x2F;form-action" class="form-horizontal" id="publication_create_new_edition">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CreateNewEditionForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\CreateNewEditionForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="create-new-edition-submit" class="btn&#x20;btn-primary" value="Add&#x20;another&#x20;edition">Add another edition</button></div>',
      'element' => '<button type="submit" name="submit" id="create-new-edition-submit" class="btn&#x20;btn-primary" value="Add&#x20;another&#x20;edition">Add another edition</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="create-new-edition-submit" class="btn&#x20;btn-primary" value="Add&#x20;another&#x20;edition">Add another edition</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="dictionary-entry" action="&#x2F;form-action" class="form-horizontal" id="dictionary-entry">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::directTranslation' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Direct Translation</label><input type="text" name="directTranslation" maxlength="255" class="form-control" value=""><p class="help-block">Most probable translated text that could directly replace the German key text</p></div>',
      'element' => '<input type="text" name="directTranslation" maxlength="255" class="form-control" value="">',
      'label' => '<label for="directTranslation">Direct Translation</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Most probable translated text that could directly replace the German key text</p>',
      'text' => '<input type="text" name="directTranslation" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="directTranslation" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Direct Translation</label><input type="text" name="directTranslation" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">Most probable translated text that could directly replace the German key text</p></div>',
      'element' => '<input type="text" name="directTranslation" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="directTranslation" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="directTranslation" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Direct Translation</label><input type="text" name="directTranslation" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">Most probable translated text that could directly replace the German key text</p></div>',
      'element' => '<input type="text" name="directTranslation" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="directTranslation" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="directTranslation" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::entry' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Dictionary Entry</label><textarea name="entry" data-provide="markdown" data-parser="CommonMark" required maxlength="1000" class="form-control"></textarea></div>',
      'element' => '<textarea name="entry" data-provide="markdown" data-parser="CommonMark" required maxlength="1000" class="form-control"></textarea>',
      'label' => '<label for="entry">Dictionary Entry</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Dictionary Entry</label><textarea name="entry" data-provide="markdown" data-parser="CommonMark" required maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="entry" data-provide="markdown" data-parser="CommonMark" required maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Dictionary Entry</label><textarea name="entry" data-provide="markdown" data-parser="CommonMark" required maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="entry" data-provide="markdown" data-parser="CommonMark" required maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::isActive' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1" checked> Active?</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1" checked> Active?</label>',
      'label' => '<label for="isActive">Active?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1"> Active?</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1"> Active?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::key' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Key (German)</label><input type="text" name="key" required maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="text" name="key" required maxlength="255" class="form-control" value="">',
      'label' => '<label for="key">Key (German)</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="key" required maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="key" required maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Key (German)</label><input type="text" name="key" required maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="key" required maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="key" required maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="key" required maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Key (German)</label><input type="text" name="key" required maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="key" required maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="key" required maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="key" required maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::links' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Links</label><select name="links&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="die-welt-als-ersatzgott">(Die Welt als) Ersatzgott</option>
<option value="freiheit-ist-entscheidungs-und-durchsetzungsfaehigkeit">(Freiheit ist) Entscheidungs- und Durchsetzungsfähigkeit</option>
<!-- 1349 more options, f3992202b20ea27446adff525abc129a -->
<option value="selbstzerfasserung">Selbstzerfasserung</option>
</select></div>',
      'element' => '<select name="links&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="die-welt-als-ersatzgott">(Die Welt als) Ersatzgott</option>
<option value="freiheit-ist-entscheidungs-und-durchsetzungsfaehigkeit">(Freiheit ist) Entscheidungs- und Durchsetzungsfähigkeit</option>
<!-- 1349 more options, f3992202b20ea27446adff525abc129a -->
<option value="selbstzerfasserung">Selbstzerfasserung</option>
</select>',
      'label' => '<label for="links">Links</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="links&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Links</label><select name="links&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="die-welt-als-ersatzgott" selected>(Die Welt als) Ersatzgott</option>
<option value="freiheit-ist-entscheidungs-und-durchsetzungsfaehigkeit">(Freiheit ist) Entscheidungs- und Durchsetzungsfähigkeit</option>
<!-- 1349 more options, f3992202b20ea27446adff525abc129a -->
<option value="selbstzerfasserung">Selbstzerfasserung</option>
</select></div>',
      'element' => '<select name="links&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="die-welt-als-ersatzgott" selected>(Die Welt als) Ersatzgott</option>
<option value="freiheit-ist-entscheidungs-und-durchsetzungsfaehigkeit">(Freiheit ist) Entscheidungs- und Durchsetzungsfähigkeit</option>
<!-- 1349 more options, f3992202b20ea27446adff525abc129a -->
<option value="selbstzerfasserung">Selbstzerfasserung</option>
</select>',
      'select_without_options' => '<select name="links&#x5B;&#x5D;" multiple><option value="die-welt-als-ersatzgott" selected>(Die Welt als) Ersatzgott</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::locale' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="locale" required class="form-control"><option value="en_US">English</option>
<option value="es_ES">Spanish</option>
<option value="pt_PT">Portugues (Portugal)</option>
<!-- 2 more options, 7024ca81f879e45506f8eb440bb4c87b -->
<option value="hu_HU">Hungarian</option>
</select></div>',
      'element' => '<select name="locale" required class="form-control"><option value="en_US">English</option>
<option value="es_ES">Spanish</option>
<option value="pt_PT">Portugues (Portugal)</option>
<!-- 2 more options, 7024ca81f879e45506f8eb440bb4c87b -->
<option value="hu_HU">Hungarian</option>
</select>',
      'label' => '<label for="locale">Language</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="locale" required></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="locale" required class="form-control"><option value="en_US" selected>English</option>
<option value="es_ES">Spanish</option>
<option value="pt_PT">Portugues (Portugal)</option>
<!-- 2 more options, 7024ca81f879e45506f8eb440bb4c87b -->
<option value="hu_HU">Hungarian</option>
</select></div>',
      'element' => '<select name="locale" required class="form-control"><option value="en_US" selected>English</option>
<option value="es_ES">Spanish</option>
<option value="pt_PT">Portugues (Portugal)</option>
<!-- 2 more options, 7024ca81f879e45506f8eb440bb4c87b -->
<option value="hu_HU">Hungarian</option>
</select>',
      'select_without_options' => '<select name="locale" required><option value="en_US" selected>English</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="locale" required class="form-control"><option value="en_US">English</option>
<option value="es_ES">Spanish</option>
<option value="pt_PT">Portugues (Portugal)</option>
<!-- 2 more options, 7024ca81f879e45506f8eb440bb4c87b -->
<option value="hu_HU">Hungarian</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\DictionaryEntryForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\ImportForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="post" name="library-import" action="&#x2F;form-action" enctype="multipart&#x2F;form-data" class="form-horizontal" id="library-import">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\ImportForm::description' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="4" maxlength="1000" class="form-control"></textarea></div>',
      'element' => '<textarea name="description" rows="4" maxlength="1000" class="form-control"></textarea>',
      'label' => '<label for="description">Description</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="4" maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="description" rows="4" maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="4" maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="description" rows="4" maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\ImportForm::file' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Spreadsheet</label><input type="file" name="file" accept=".xlsx,.xls,.ods" class="form-control"><p class="help-block">An .xlsx, .xls or .ods file. Download the template above if you do not have one yet.</p></div>',
      'element' => '<input type="file" name="file" accept=".xlsx,.xls,.ods" class="form-control">',
      'label' => '<label for="file">Spreadsheet</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">An .xlsx, .xls or .ods file. Download the template above if you do not have one yet.</p>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\ImportForm::isCompleteImport' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isCompleteImport" value="0"><label><input type="checkbox" name="isCompleteImport" value="1"> Complete import?</label></div><p class="help-block">Tick only if this file is the whole library. Every active book it does not list will be marked inactive.</p>',
      'element' => '<input type="hidden" name="isCompleteImport" value="0"><label><input type="checkbox" name="isCompleteImport" value="1"> Complete import?</label>',
      'label' => '<label for="isCompleteImport">Complete import?</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Tick only if this file is the whole library. Every active book it does not list will be marked inactive.</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isCompleteImport" value="0"><label><input type="checkbox" name="isCompleteImport" value="1" checked> Complete import?</label></div><p class="help-block">Tick only if this file is the whole library. Every active book it does not list will be marked inactive.</p>',
      'element' => '<input type="hidden" name="isCompleteImport" value="0"><label><input type="checkbox" name="isCompleteImport" value="1" checked> Complete import?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\ImportForm::libraryId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="">',
      'label' => '<label for="libraryId">Library</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="libraryId" value="">',
      'hidden' => '<input type="hidden" name="libraryId" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="libraryId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="libraryId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="libraryId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="libraryId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\ImportForm::name' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Import name</label><input type="text" name="name" maxlength="100" class="form-control" value=""><p class="help-block">Something you will recognise later, such as &quot;Jornada de trabajo, June&quot;.</p></div>',
      'element' => '<input type="text" name="name" maxlength="100" class="form-control" value="">',
      'label' => '<label for="name">Import name</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Something you will recognise later, such as &quot;Jornada de trabajo, June&quot;.</p>',
      'text' => '<input type="text" name="name" maxlength="100" value="">',
      'hidden' => '<input type="hidden" name="name" maxlength="100" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Import name</label><input type="text" name="name" maxlength="100" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">Something you will recognise later, such as &quot;Jornada de trabajo, June&quot;.</p></div>',
      'element' => '<input type="text" name="name" maxlength="100" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="name" maxlength="100" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="name" maxlength="100" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Import name</label><input type="text" name="name" maxlength="100" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">Something you will recognise later, such as &quot;Jornada de trabajo, June&quot;.</p></div>',
      'element' => '<input type="text" name="name" maxlength="100" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="name" maxlength="100" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="name" maxlength="100" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\ImportForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\ImportForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Upload&#x20;and&#x20;continue">Upload and continue</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Upload&#x20;and&#x20;continue">Upload and continue</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Upload&#x20;and&#x20;continue">Upload and continue</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\InactivationForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="inactivation" action="&#x2F;form-action" class="form-horizontal" id="inactivation">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\InactivationForm::inactivationReason' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Inactivation reason</label><input type="text" name="inactivationReason" tabindex="3" class="form-control" value=""></div>',
      'element' => '<input type="text" name="inactivationReason" tabindex="3" class="form-control" value="">',
      'label' => '<label for="inactivationReason">Inactivation reason</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="inactivationReason" tabindex="3" value="">',
      'hidden' => '<input type="hidden" name="inactivationReason" tabindex="3" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Inactivation reason</label><input type="text" name="inactivationReason" tabindex="3" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="inactivationReason" tabindex="3" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="inactivationReason" tabindex="3" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="inactivationReason" tabindex="3" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Inactivation reason</label><input type="text" name="inactivationReason" tabindex="3" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="inactivationReason" tabindex="3" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="inactivationReason" tabindex="3" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="inactivationReason" tabindex="3" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\InactivationForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\InactivationForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" tabindex="4" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" tabindex="4" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" tabindex="4" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\InactivationForm::withinLibraryIds' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control"></textarea><p class="help-block">One barcode per line</p></div>',
      'element' => '<textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control"></textarea>',
      'label' => '<label for="withinLibraryIds">Book Ids</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One barcode per line</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">One barcode per line</p></div>',
      'element' => '<textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul><p class="help-block">One barcode per line</p></div>',
      'element' => '<textarea name="withinLibraryIds" required tabindex="1" rows="6" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
      'errors' => '<ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="post" name="library" action="&#x2F;form-action" class="form-horizontal" id="library">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::adminNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea>',
      'label' => '<label for="adminNotes">Admin notes</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::allowCollectionlessBooks' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="allowCollectionlessBooks" value="0"><label><input type="checkbox" name="allowCollectionlessBooks" value="1" checked> Allow collectionless books?</label></div>',
      'element' => '<input type="hidden" name="allowCollectionlessBooks" value="0"><label><input type="checkbox" name="allowCollectionlessBooks" value="1" checked> Allow collectionless books?</label>',
      'label' => '<label for="allowCollectionlessBooks">Allow collectionless books?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="allowCollectionlessBooks" value="0"><label><input type="checkbox" name="allowCollectionlessBooks" value="1"> Allow collectionless books?</label></div>',
      'element' => '<input type="hidden" name="allowCollectionlessBooks" value="0"><label><input type="checkbox" name="allowCollectionlessBooks" value="1"> Allow collectionless books?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::barcodeText' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Barcode text</label><input type="text" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" class="form-control" value=""></div>',
      'element' => '<input type="text" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" class="form-control" value="">',
      'label' => '<label for="barcodeText">Barcode text</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Barcode text</label><input type="text" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Barcode text</label><input type="text" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="barcodeText" placeholder="ex.&#x20;Bibliotheca&#x20;Sion" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::callNumberExplanation' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Call number explanation</label><textarea name="callNumberExplanation" maxlength="1000" class="form-control"></textarea></div>',
      'element' => '<textarea name="callNumberExplanation" maxlength="1000" class="form-control"></textarea>',
      'label' => '<label for="callNumberExplanation">Call number explanation</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Call number explanation</label><textarea name="callNumberExplanation" maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="callNumberExplanation" maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Call number explanation</label><textarea name="callNumberExplanation" maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="callNumberExplanation" maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::callNumberHelpText' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Call number help text</label><input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value="">',
      'label' => '<label for="callNumberHelpText">Call number help text</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="callNumberHelpText" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="callNumberHelpText" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Call number help text</label><input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="callNumberHelpText" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="callNumberHelpText" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Call number help text</label><input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="callNumberHelpText" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="callNumberHelpText" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="callNumberHelpText" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::callNumberPlaceholder' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Call number placeholder</label><input type="text" name="callNumberPlaceholder" maxlength="50" class="form-control" value=""></div>',
      'element' => '<input type="text" name="callNumberPlaceholder" maxlength="50" class="form-control" value="">',
      'label' => '<label for="callNumberPlaceholder">Call number placeholder</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="callNumberPlaceholder" maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="callNumberPlaceholder" maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Call number placeholder</label><input type="text" name="callNumberPlaceholder" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="callNumberPlaceholder" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="callNumberPlaceholder" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="callNumberPlaceholder" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Call number placeholder</label><input type="text" name="callNumberPlaceholder" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="callNumberPlaceholder" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="callNumberPlaceholder" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="callNumberPlaceholder" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::callNumberRegex' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Call number regex</label><input type="text" name="callNumberRegex" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="text" name="callNumberRegex" maxlength="255" class="form-control" value="">',
      'label' => '<label for="callNumberRegex">Call number regex</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="callNumberRegex" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="callNumberRegex" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Call number regex</label><input type="text" name="callNumberRegex" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="callNumberRegex" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="callNumberRegex" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="callNumberRegex" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Call number regex</label><input type="text" name="callNumberRegex" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="callNumberRegex" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="callNumberRegex" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="callNumberRegex" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::checkoutBooksRole' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Who can checkout books?</label><select name="checkoutBooksRole" class="form-control"><option value=""></option>
<option value="guest">Public</option>
<option value="lib_user">Authenticated users</option>
<!-- 2 more options, 169787e767dcfa48605d0c2b87194d64 -->
<option value="lib_patres">Patres</option>
</select></div>',
      'element' => '<select name="checkoutBooksRole" class="form-control"><option value=""></option>
<option value="guest">Public</option>
<option value="lib_user">Authenticated users</option>
<!-- 2 more options, 169787e767dcfa48605d0c2b87194d64 -->
<option value="lib_patres">Patres</option>
</select>',
      'label' => '<label for="checkoutBooksRole">Who can checkout books?</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="checkoutBooksRole"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Who can checkout books?</label><select name="checkoutBooksRole" class="form-control"><option value=""></option>
<option value="guest" selected>Public</option>
<option value="lib_user">Authenticated users</option>
<!-- 2 more options, 169787e767dcfa48605d0c2b87194d64 -->
<option value="lib_patres">Patres</option>
</select></div>',
      'element' => '<select name="checkoutBooksRole" class="form-control"><option value=""></option>
<option value="guest" selected>Public</option>
<option value="lib_user">Authenticated users</option>
<!-- 2 more options, 169787e767dcfa48605d0c2b87194d64 -->
<option value="lib_patres">Patres</option>
</select>',
      'select_without_options' => '<select name="checkoutBooksRole"><option value="guest" selected>Public</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Who can checkout books?</label><select name="checkoutBooksRole" class="form-control"><option value=""></option>
<option value="guest">Public</option>
<option value="lib_user">Authenticated users</option>
<!-- 2 more options, 169787e767dcfa48605d0c2b87194d64 -->
<option value="lib_patres">Patres</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::checkoutPersonListKind' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Person list for checkouts</label><select name="checkoutPersonListKind" class="form-control"><option value="all-borrowers">All borrowers</option>
<option value="patres-sion">Schoenstatt Fathers</option>
</select><p class="help-block">This controls which person list will be displayed on the checkout form.</p></div>',
      'element' => '<select name="checkoutPersonListKind" class="form-control"><option value="all-borrowers">All borrowers</option>
<option value="patres-sion">Schoenstatt Fathers</option>
</select>',
      'label' => '<label for="checkoutPersonListKind">Person list for checkouts</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">This controls which person list will be displayed on the checkout form.</p>',
      'select_without_options' => '<select name="checkoutPersonListKind"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Person list for checkouts</label><select name="checkoutPersonListKind" class="form-control"><option value="all-borrowers" selected>All borrowers</option>
<option value="patres-sion">Schoenstatt Fathers</option>
</select><p class="help-block">This controls which person list will be displayed on the checkout form.</p></div>',
      'element' => '<select name="checkoutPersonListKind" class="form-control"><option value="all-borrowers" selected>All borrowers</option>
<option value="patres-sion">Schoenstatt Fathers</option>
</select>',
      'select_without_options' => '<select name="checkoutPersonListKind"><option value="all-borrowers" selected>All borrowers</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Person list for checkouts</label><select name="checkoutPersonListKind" class="form-control"><option value="all-borrowers">All borrowers</option>
<option value="patres-sion">Schoenstatt Fathers</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">This controls which person list will be displayed on the checkout form.</p></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::contactEmail' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Contact email</label><input type="email" name="contactEmail" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="email" name="contactEmail" maxlength="255" class="form-control" value="">',
      'label' => '<label for="contactEmail">Contact email</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="contactEmail" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="contactEmail" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Contact email</label><input type="email" name="contactEmail" maxlength="255" class="form-control" value="baseline&#x40;example.com"></div>',
      'element' => '<input type="email" name="contactEmail" maxlength="255" class="form-control" value="baseline&#x40;example.com">',
      'text' => '<input type="text" name="contactEmail" maxlength="255" value="baseline&#x40;example.com">',
      'hidden' => '<input type="hidden" name="contactEmail" maxlength="255" value="baseline&#x40;example.com">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Contact email</label><input type="email" name="contactEmail" maxlength="255" class="form-control" value="not-an-email"><ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li></ul></div>',
      'element' => '<input type="email" name="contactEmail" maxlength="255" class="form-control" value="not-an-email">',
      'errors' => '<ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li></ul>',
      'text' => '<input type="text" name="contactEmail" maxlength="255" value="not-an-email">',
      'hidden' => '<input type="hidden" name="contactEmail" maxlength="255" value="not-an-email">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::contactPersonId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Contact person</label><select name="contactPersonId" class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select></div>',
      'element' => '<select name="contactPersonId" class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'label' => '<label for="contactPersonId">Contact person</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="contactPersonId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Contact person</label><select name="contactPersonId" class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select></div>',
      'element' => '<select name="contactPersonId" class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'select_without_options' => '<select name="contactPersonId"><option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Contact person</label><select name="contactPersonId" class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::createCheckoutsIfCheckingInANonCheckedOutBook' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="createCheckoutsIfCheckingInANonCheckedOutBook" value="0"><label><input type="checkbox" name="createCheckoutsIfCheckingInANonCheckedOutBook" value="1" checked> Create checkouts if checking in a non-checked out book?</label></div>',
      'element' => '<input type="hidden" name="createCheckoutsIfCheckingInANonCheckedOutBook" value="0"><label><input type="checkbox" name="createCheckoutsIfCheckingInANonCheckedOutBook" value="1" checked> Create checkouts if checking in a non-checked out book?</label>',
      'label' => '<label for="createCheckoutsIfCheckingInANonCheckedOutBook">Create checkouts if checking in a non-checked out book?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="createCheckoutsIfCheckingInANonCheckedOutBook" value="0"><label><input type="checkbox" name="createCheckoutsIfCheckingInANonCheckedOutBook" value="1"> Create checkouts if checking in a non-checked out book?</label></div>',
      'element' => '<input type="hidden" name="createCheckoutsIfCheckingInANonCheckedOutBook" value="0"><label><input type="checkbox" name="createCheckoutsIfCheckingInANonCheckedOutBook" value="1"> Create checkouts if checking in a non-checked out book?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::defaultCheckoutPersonId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Default checkout person</label><select name="defaultCheckoutPersonId" class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select><p class="help-block">When books are checked in without a preceding checkout, they&#039;ll be checked out under this person.</p></div>',
      'element' => '<select name="defaultCheckoutPersonId" class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'label' => '<label for="defaultCheckoutPersonId">Default checkout person</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">When books are checked in without a preceding checkout, they&#039;ll be checked out under this person.</p>',
      'select_without_options' => '<select name="defaultCheckoutPersonId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Default checkout person</label><select name="defaultCheckoutPersonId" class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select><p class="help-block">When books are checked in without a preceding checkout, they&#039;ll be checked out under this person.</p></div>',
      'element' => '<select name="defaultCheckoutPersonId" class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'select_without_options' => '<select name="defaultCheckoutPersonId"><option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Default checkout person</label><select name="defaultCheckoutPersonId" class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">When books are checked in without a preceding checkout, they&#039;ll be checked out under this person.</p></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::defaultCheckoutTimePeriodInDays' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Default checkout time period (days)</label><input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="14"></div>',
      'element' => '<input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="14">',
      'label' => '<label for="defaultCheckoutTimePeriodInDays">Default checkout time period (days)</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="defaultCheckoutTimePeriodInDays" value="14">',
      'hidden' => '<input type="hidden" name="defaultCheckoutTimePeriodInDays" value="14">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Default checkout time period (days)</label><input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="42"></div>',
      'element' => '<input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="42">',
      'text' => '<input type="text" name="defaultCheckoutTimePeriodInDays" value="42">',
      'hidden' => '<input type="hidden" name="defaultCheckoutTimePeriodInDays" value="42">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Default checkout time period (days)</label><input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="forty-two"><ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul></div>',
      'element' => '<input type="number" name="defaultCheckoutTimePeriodInDays" min="1" max="365" step="1" class="form-control" value="forty-two">',
      'errors' => '<ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul>',
      'text' => '<input type="text" name="defaultCheckoutTimePeriodInDays" value="forty-two">',
      'hidden' => '<input type="hidden" name="defaultCheckoutTimePeriodInDays" value="forty-two">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::description' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="3" maxlength="1000" class="form-control"></textarea></div>',
      'element' => '<textarea name="description" rows="3" maxlength="1000" class="form-control"></textarea>',
      'label' => '<label for="description">Description</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="3" maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="description" rows="3" maxlength="1000" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="3" maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="description" rows="3" maxlength="1000" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::enableCheckouts' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="enableCheckouts" value="0"><label><input type="checkbox" name="enableCheckouts" value="1"> Enable checkouts?</label></div>',
      'element' => '<input type="hidden" name="enableCheckouts" value="0"><label><input type="checkbox" name="enableCheckouts" value="1"> Enable checkouts?</label>',
      'label' => '<label for="enableCheckouts">Enable checkouts?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="enableCheckouts" value="0"><label><input type="checkbox" name="enableCheckouts" value="1" checked> Enable checkouts?</label></div>',
      'element' => '<input type="hidden" name="enableCheckouts" value="0"><label><input type="checkbox" name="enableCheckouts" value="1" checked> Enable checkouts?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::enforceCallNumberRegex' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="enforceCallNumberRegex" value="0"><label><input type="checkbox" name="enforceCallNumberRegex" value="1" checked> Enforce call number regex?</label></div>',
      'element' => '<input type="hidden" name="enforceCallNumberRegex" value="0"><label><input type="checkbox" name="enforceCallNumberRegex" value="1" checked> Enforce call number regex?</label>',
      'label' => '<label for="enforceCallNumberRegex">Enforce call number regex?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="enforceCallNumberRegex" value="0"><label><input type="checkbox" name="enforceCallNumberRegex" value="1"> Enforce call number regex?</label></div>',
      'element' => '<input type="hidden" name="enforceCallNumberRegex" value="0"><label><input type="checkbox" name="enforceCallNumberRegex" value="1"> Enforce call number regex?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::filiationId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Filiation</label><select name="filiationId" class="form-control"><option value=""></option>
<option value="50">Vaterhaus</option>
<option value="12">Colegio Mayor</option>
<!-- 2 more options, bc3ea888f25cd2309c94a1f1921c3ce6 -->
<option value="7">Bellavista - casa central</option>
</select></div>',
      'element' => '<select name="filiationId" class="form-control"><option value=""></option>
<option value="50">Vaterhaus</option>
<option value="12">Colegio Mayor</option>
<!-- 2 more options, bc3ea888f25cd2309c94a1f1921c3ce6 -->
<option value="7">Bellavista - casa central</option>
</select>',
      'label' => '<label for="filiationId">Filiation</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="filiationId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Filiation</label><select name="filiationId" class="form-control"><option value=""></option>
<option value="50" selected>Vaterhaus</option>
<option value="12">Colegio Mayor</option>
<!-- 2 more options, bc3ea888f25cd2309c94a1f1921c3ce6 -->
<option value="7">Bellavista - casa central</option>
</select></div>',
      'element' => '<select name="filiationId" class="form-control"><option value=""></option>
<option value="50" selected>Vaterhaus</option>
<option value="12">Colegio Mayor</option>
<!-- 2 more options, bc3ea888f25cd2309c94a1f1921c3ce6 -->
<option value="7">Bellavista - casa central</option>
</select>',
      'select_without_options' => '<select name="filiationId"><option value="50" selected>Vaterhaus</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Filiation</label><select name="filiationId" class="form-control"><option value=""></option>
<option value="50">Vaterhaus</option>
<option value="12">Colegio Mayor</option>
<!-- 2 more options, bc3ea888f25cd2309c94a1f1921c3ce6 -->
<option value="7">Bellavista - casa central</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::isActive' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1" checked> Active?</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1" checked> Active?</label>',
      'label' => '<label for="isActive">Active?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1"> Active?</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1"> Active?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::labelLine1' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Label line 1</label><input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value=""><p class="help-block">The three label line fields describe how to format a label for the spine of a book.</p></div>',
      'element' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value="">',
      'label' => '<label for="labelLine1">Label line 1</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">The three label line fields describe how to format a label for the spine of a book.</p>',
      'text' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Label line 1</label><input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">The three label line fields describe how to format a label for the spine of a book.</p></div>',
      'element' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Label line 1</label><input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">The three label line fields describe how to format a label for the spine of a book.</p></div>',
      'element' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="labelLine1" placeholder="&#x3A;short_category" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::labelLine2' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Label line 2</label><input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value="">',
      'label' => '<label for="labelLine2">Label line 2</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Label line 2</label><input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Label line 2</label><input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="labelLine2" placeholder="&#x24;2" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::labelLine3' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Label line 3</label><input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value="">',
      'label' => '<label for="labelLine3">Label line 3</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Label line 3</label><input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Label line 3</label><input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="labelLine3" placeholder="&#x24;3" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::mainCollectionId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Main collection</label><select name="mainCollectionId" class="form-control"><option value=""></option>
<option value="4">General</option>
<option value="6">Pallotti</option>
<!-- 1 more options, 46794a94078dbcc9fe84da1f04c3f9aa -->
<option value="7">Tesis</option>
</select></div>',
      'element' => '<select name="mainCollectionId" class="form-control"><option value=""></option>
<option value="4">General</option>
<option value="6">Pallotti</option>
<!-- 1 more options, 46794a94078dbcc9fe84da1f04c3f9aa -->
<option value="7">Tesis</option>
</select>',
      'label' => '<label for="mainCollectionId">Main collection</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="mainCollectionId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Main collection</label><select name="mainCollectionId" class="form-control"><option value=""></option>
<option value="4" selected>General</option>
<option value="6">Pallotti</option>
<!-- 1 more options, 46794a94078dbcc9fe84da1f04c3f9aa -->
<option value="7">Tesis</option>
</select></div>',
      'element' => '<select name="mainCollectionId" class="form-control"><option value=""></option>
<option value="4" selected>General</option>
<option value="6">Pallotti</option>
<!-- 1 more options, 46794a94078dbcc9fe84da1f04c3f9aa -->
<option value="7">Tesis</option>
</select>',
      'select_without_options' => '<select name="mainCollectionId"><option value="4" selected>General</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Main collection</label><select name="mainCollectionId" class="form-control"><option value=""></option>
<option value="4">General</option>
<option value="6">Pallotti</option>
<!-- 1 more options, 46794a94078dbcc9fe84da1f04c3f9aa -->
<option value="7">Tesis</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::mainShowDisplay' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Main library view screen</label><select name="mainShowDisplay" class="form-control"><option value="show-categories">Show categories</option>
<option value="show-collections">Show collections</option>
<option value="show-collections-categories">Show collections and categories</option>
</select></div>',
      'element' => '<select name="mainShowDisplay" class="form-control"><option value="show-categories">Show categories</option>
<option value="show-collections">Show collections</option>
<option value="show-collections-categories">Show collections and categories</option>
</select>',
      'label' => '<label for="mainShowDisplay">Main library view screen</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="mainShowDisplay"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Main library view screen</label><select name="mainShowDisplay" class="form-control"><option value="show-categories" selected>Show categories</option>
<option value="show-collections">Show collections</option>
<option value="show-collections-categories">Show collections and categories</option>
</select></div>',
      'element' => '<select name="mainShowDisplay" class="form-control"><option value="show-categories" selected>Show categories</option>
<option value="show-collections">Show collections</option>
<option value="show-collections-categories">Show collections and categories</option>
</select>',
      'select_without_options' => '<select name="mainShowDisplay"><option value="show-categories" selected>Show categories</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Main library view screen</label><select name="mainShowDisplay" class="form-control"><option value="show-categories">Show categories</option>
<option value="show-collections">Show collections</option>
<option value="show-collections-categories">Show collections and categories</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::name' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Name</label><input type="text" name="name" maxlength="50" class="form-control" value=""></div>',
      'element' => '<input type="text" name="name" maxlength="50" class="form-control" value="">',
      'label' => '<label for="name">Name</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="name" maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="name" maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Name</label><input type="text" name="name" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="name" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="name" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="name" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Name</label><input type="text" name="name" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="name" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="name" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="name" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::requireCallNumbers' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="requireCallNumbers" value="0"><label><input type="checkbox" name="requireCallNumbers" value="1" checked> Require call numbers?</label></div>',
      'element' => '<input type="hidden" name="requireCallNumbers" value="0"><label><input type="checkbox" name="requireCallNumbers" value="1" checked> Require call numbers?</label>',
      'label' => '<label for="requireCallNumbers">Require call numbers?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="requireCallNumbers" value="0"><label><input type="checkbox" name="requireCallNumbers" value="1"> Require call numbers?</label></div>',
      'element' => '<input type="hidden" name="requireCallNumbers" value="0"><label><input type="checkbox" name="requireCallNumbers" value="1"> Require call numbers?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::sortTextFormat' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Sort text format</label><input type="text" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" class="form-control" value=""><p class="help-block">Format text to pass to sprintf. See https://secure.php.net/manual/en/function.sprintf.php for more info. The first parameter passed is the collection abbreviation followed by the capture groups of the call number.</p></div>',
      'element' => '<input type="text" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" class="form-control" value="">',
      'label' => '<label for="sortTextFormat">Sort text format</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Format text to pass to sprintf. See https://secure.php.net/manual/en/function.sprintf.php for more info. The first parameter passed is the collection abbreviation followed by the capture groups of the call number.</p>',
      'text' => '<input type="text" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Sort text format</label><input type="text" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">Format text to pass to sprintf. See https://secure.php.net/manual/en/function.sprintf.php for more info. The first parameter passed is the collection abbreviation followed by the capture groups of the call number.</p></div>',
      'element' => '<input type="text" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Sort text format</label><input type="text" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">Format text to pass to sprintf. See https://secure.php.net/manual/en/function.sprintf.php for more info. The first parameter passed is the collection abbreviation followed by the capture groups of the call number.</p></div>',
      'element' => '<input type="text" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="sortTextFormat" placeholder="&#x7B;collectionAbbreviation&#x7D;&#x25;1&#x24;06.2f&#x7B;author&#x7D;&#x7B;title&#x7D;&#x25;2&#x24;02d" maxlength="255" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::useCollections' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="useCollections" value="0"><label><input type="checkbox" name="useCollections" value="1" checked> Use collections?</label></div>',
      'element' => '<input type="hidden" name="useCollections" value="0"><label><input type="checkbox" name="useCollections" value="1" checked> Use collections?</label>',
      'label' => '<label for="useCollections">Use collections?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="useCollections" value="0"><label><input type="checkbox" name="useCollections" value="1"> Use collections?</label></div>',
      'element' => '<input type="hidden" name="useCollections" value="0"><label><input type="checkbox" name="useCollections" value="1"> Use collections?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\LibraryForm::viewRole' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Library visibility</label><select name="viewRole" required class="form-control"><option value="guest">Public</option>
<option value="lib_user">Authenticated users</option>
<option value="lib_academic">Academic users</option>
<!-- 1 more options, a907cee52abaa5c7414951401c6f96c4 -->
<option value="lib_patres">Patres</option>
</select></div>',
      'element' => '<select name="viewRole" required class="form-control"><option value="guest">Public</option>
<option value="lib_user">Authenticated users</option>
<option value="lib_academic">Academic users</option>
<!-- 1 more options, a907cee52abaa5c7414951401c6f96c4 -->
<option value="lib_patres">Patres</option>
</select>',
      'label' => '<label for="viewRole">Library visibility</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="viewRole" required></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Library visibility</label><select name="viewRole" required class="form-control"><option value="guest" selected>Public</option>
<option value="lib_user">Authenticated users</option>
<option value="lib_academic">Academic users</option>
<!-- 1 more options, a907cee52abaa5c7414951401c6f96c4 -->
<option value="lib_patres">Patres</option>
</select></div>',
      'element' => '<select name="viewRole" required class="form-control"><option value="guest" selected>Public</option>
<option value="lib_user">Authenticated users</option>
<option value="lib_academic">Academic users</option>
<!-- 1 more options, a907cee52abaa5c7414951401c6f96c4 -->
<option value="lib_patres">Patres</option>
</select>',
      'select_without_options' => '<select name="viewRole" required><option value="guest" selected>Public</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Library visibility</label><select name="viewRole" required class="form-control"><option value="guest">Public</option>
<option value="lib_user">Authenticated users</option>
<option value="lib_academic">Academic users</option>
<!-- 1 more options, a907cee52abaa5c7414951401c6f96c4 -->
<option value="lib_patres">Patres</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\MassCheckoutFieldset::checkedOutOn' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Checked out on</label><input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="<today>"></div>',
      'element' => '<input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="<today>">',
      'label' => '<label for="checkedOutOn">Checked out on</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="checkedOutOn" value="<today>">',
      'hidden' => '<input type="hidden" name="checkedOutOn" value="<today>">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Checked out on</label><input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="2020-01-02"></div>',
      'element' => '<input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="2020-01-02">',
      'text' => '<input type="text" name="checkedOutOn" value="2020-01-02">',
      'hidden' => '<input type="hidden" name="checkedOutOn" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Checked out on</label><input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="not-a-date"></div>',
      'element' => '<input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="not-a-date">',
      'text' => '<input type="text" name="checkedOutOn" value="not-a-date">',
      'hidden' => '<input type="hidden" name="checkedOutOn" value="not-a-date">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\MassCheckoutFieldset::personId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Who&#039;s checking out?</label><select name="personId" class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="personId" class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="personId">Who&#039;s checking out?</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="personId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Who&#039;s checking out?</label><select name="personId" class="form-control"><option value="" selected></option>
</select></div>',
      'element' => '<select name="personId" class="form-control"><option value="" selected></option>
</select>',
      'select_without_options' => '<select name="personId"><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\MassCheckoutFieldset::withinLibraryIds' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><input type="text" name="withinLibraryIds" class="form-control" value=""><p class="help-block">One barcode per line</p></div>',
      'element' => '<input type="text" name="withinLibraryIds" class="form-control" value="">',
      'label' => '<label for="withinLibraryIds">Book Ids</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One barcode per line</p>',
      'text' => '<input type="text" name="withinLibraryIds" value="">',
      'hidden' => '<input type="hidden" name="withinLibraryIds" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><input type="text" name="withinLibraryIds" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">One barcode per line</p></div>',
      'element' => '<input type="text" name="withinLibraryIds" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="withinLibraryIds" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="withinLibraryIds" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><input type="text" name="withinLibraryIds" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">One barcode per line</p></div>',
      'element' => '<input type="text" name="withinLibraryIds" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="withinLibraryIds" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="withinLibraryIds" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\MassCheckoutForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="checkout" action="&#x2F;form-action" class="form-horizontal" id="checkout">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/0/checkedOutOn' => 
  array (
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Checked out on</label><input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="2020-01-02"></div>',
      'element' => '<input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="2020-01-02">',
      'label' => '<label for="checkedOutOn">Checked out on</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="checkedOutOn" value="2020-01-02">',
      'hidden' => '<input type="hidden" name="checkedOutOn" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Checked out on</label><input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="not-a-date"><ul class="help-block"><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul></div>',
      'element' => '<input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="not-a-date">',
      'label' => '<label for="checkedOutOn">Checked out on</label>',
      'errors' => '<ul class="help-block"><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul>',
      'help_block' => '',
      'text' => '<input type="text" name="checkedOutOn" value="not-a-date">',
      'hidden' => '<input type="hidden" name="checkedOutOn" value="not-a-date">',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Checked out on</label><input type="date" name="checkout&#x5B;0&#x5D;&#x5B;checkedOutOn&#x5D;" min="<today>" step="any" class="form-control" value="<today>"></div>',
      'element' => '<input type="date" name="checkout&#x5B;0&#x5D;&#x5B;checkedOutOn&#x5D;" min="<today>" step="any" class="form-control" value="<today>">',
      'label' => '<label for="checkout&#x5B;0&#x5D;&#x5B;checkedOutOn&#x5D;">Checked out on</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="checkout&#x5B;0&#x5D;&#x5B;checkedOutOn&#x5D;" value="<today>">',
      'hidden' => '<input type="hidden" name="checkout&#x5B;0&#x5D;&#x5B;checkedOutOn&#x5D;" value="<today>">',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/0/personId' => 
  array (
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Who&#039;s checking out?</label><select name="personId" class="form-control"><option value="" selected></option>
</select></div>',
      'element' => '<select name="personId" class="form-control"><option value="" selected></option>
</select>',
      'label' => '<label for="personId">Who&#039;s checking out?</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="personId"><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Who&#039;s checking out?</label><select name="personId" class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="personId" class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="personId">Who&#039;s checking out?</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="personId"></select>',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Who&#039;s checking out?</label><select name="checkout&#x5B;0&#x5D;&#x5B;personId&#x5D;" class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="checkout&#x5B;0&#x5D;&#x5B;personId&#x5D;" class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="checkout&#x5B;0&#x5D;&#x5B;personId&#x5D;">Who&#039;s checking out?</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="checkout&#x5B;0&#x5D;&#x5B;personId&#x5D;"></select>',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/0/withinLibraryIds' => 
  array (
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><input type="text" name="withinLibraryIds" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">One barcode per line</p></div>',
      'element' => '<input type="text" name="withinLibraryIds" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'label' => '<label for="withinLibraryIds">Book Ids</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One barcode per line</p>',
      'text' => '<input type="text" name="withinLibraryIds" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="withinLibraryIds" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><input type="text" name="withinLibraryIds" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">One barcode per line</p></div>',
      'element' => '<input type="text" name="withinLibraryIds" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'label' => '<label for="withinLibraryIds">Book Ids</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One barcode per line</p>',
      'text' => '<input type="text" name="withinLibraryIds" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="withinLibraryIds" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><input type="text" name="checkout&#x5B;0&#x5D;&#x5B;withinLibraryIds&#x5D;" class="form-control" value=""><p class="help-block">One barcode per line</p></div>',
      'element' => '<input type="text" name="checkout&#x5B;0&#x5D;&#x5B;withinLibraryIds&#x5D;" class="form-control" value="">',
      'label' => '<label for="checkout&#x5B;0&#x5D;&#x5B;withinLibraryIds&#x5D;">Book Ids</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One barcode per line</p>',
      'text' => '<input type="text" name="checkout&#x5B;0&#x5D;&#x5B;withinLibraryIds&#x5D;" value="">',
      'hidden' => '<input type="hidden" name="checkout&#x5B;0&#x5D;&#x5B;withinLibraryIds&#x5D;" value="">',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/1/checkedOutOn' => 
  array (
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Checked out on</label><input type="date" name="checkout&#x5B;1&#x5D;&#x5B;checkedOutOn&#x5D;" min="<today>" step="any" class="form-control" value="<today>"></div>',
      'element' => '<input type="date" name="checkout&#x5B;1&#x5D;&#x5B;checkedOutOn&#x5D;" min="<today>" step="any" class="form-control" value="<today>">',
      'label' => '<label for="checkout&#x5B;1&#x5D;&#x5B;checkedOutOn&#x5D;">Checked out on</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="checkout&#x5B;1&#x5D;&#x5B;checkedOutOn&#x5D;" value="<today>">',
      'hidden' => '<input type="hidden" name="checkout&#x5B;1&#x5D;&#x5B;checkedOutOn&#x5D;" value="<today>">',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/1/personId' => 
  array (
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Who&#039;s checking out?</label><select name="checkout&#x5B;1&#x5D;&#x5B;personId&#x5D;" class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="checkout&#x5B;1&#x5D;&#x5B;personId&#x5D;" class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="checkout&#x5B;1&#x5D;&#x5B;personId&#x5D;">Who&#039;s checking out?</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="checkout&#x5B;1&#x5D;&#x5B;personId&#x5D;"></select>',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/1/withinLibraryIds' => 
  array (
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><input type="text" name="checkout&#x5B;1&#x5D;&#x5B;withinLibraryIds&#x5D;" class="form-control" value=""><p class="help-block">One barcode per line</p></div>',
      'element' => '<input type="text" name="checkout&#x5B;1&#x5D;&#x5B;withinLibraryIds&#x5D;" class="form-control" value="">',
      'label' => '<label for="checkout&#x5B;1&#x5D;&#x5B;withinLibraryIds&#x5D;">Book Ids</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One barcode per line</p>',
      'text' => '<input type="text" name="checkout&#x5B;1&#x5D;&#x5B;withinLibraryIds&#x5D;" value="">',
      'hidden' => '<input type="hidden" name="checkout&#x5B;1&#x5D;&#x5B;withinLibraryIds&#x5D;" value="">',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/2/checkedOutOn' => 
  array (
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Checked out on</label><input type="date" name="checkout&#x5B;2&#x5D;&#x5B;checkedOutOn&#x5D;" min="<today>" step="any" class="form-control" value="<today>"></div>',
      'element' => '<input type="date" name="checkout&#x5B;2&#x5D;&#x5B;checkedOutOn&#x5D;" min="<today>" step="any" class="form-control" value="<today>">',
      'label' => '<label for="checkout&#x5B;2&#x5D;&#x5B;checkedOutOn&#x5D;">Checked out on</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="checkout&#x5B;2&#x5D;&#x5B;checkedOutOn&#x5D;" value="<today>">',
      'hidden' => '<input type="hidden" name="checkout&#x5B;2&#x5D;&#x5B;checkedOutOn&#x5D;" value="<today>">',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/2/personId' => 
  array (
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Who&#039;s checking out?</label><select name="checkout&#x5B;2&#x5D;&#x5B;personId&#x5D;" class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="checkout&#x5B;2&#x5D;&#x5B;personId&#x5D;" class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="checkout&#x5B;2&#x5D;&#x5B;personId&#x5D;">Who&#039;s checking out?</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="checkout&#x5B;2&#x5D;&#x5B;personId&#x5D;"></select>',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/2/withinLibraryIds' => 
  array (
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><input type="text" name="checkout&#x5B;2&#x5D;&#x5B;withinLibraryIds&#x5D;" class="form-control" value=""><p class="help-block">One barcode per line</p></div>',
      'element' => '<input type="text" name="checkout&#x5B;2&#x5D;&#x5B;withinLibraryIds&#x5D;" class="form-control" value="">',
      'label' => '<label for="checkout&#x5B;2&#x5D;&#x5B;withinLibraryIds&#x5D;">Book Ids</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One barcode per line</p>',
      'text' => '<input type="text" name="checkout&#x5B;2&#x5D;&#x5B;withinLibraryIds&#x5D;" value="">',
      'hidden' => '<input type="hidden" name="checkout&#x5B;2&#x5D;&#x5B;withinLibraryIds&#x5D;" value="">',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/3/checkedOutOn' => 
  array (
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Checked out on</label><input type="date" name="checkout&#x5B;3&#x5D;&#x5B;checkedOutOn&#x5D;" min="<today>" step="any" class="form-control" value="<today>"></div>',
      'element' => '<input type="date" name="checkout&#x5B;3&#x5D;&#x5B;checkedOutOn&#x5D;" min="<today>" step="any" class="form-control" value="<today>">',
      'label' => '<label for="checkout&#x5B;3&#x5D;&#x5B;checkedOutOn&#x5D;">Checked out on</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="checkout&#x5B;3&#x5D;&#x5B;checkedOutOn&#x5D;" value="<today>">',
      'hidden' => '<input type="hidden" name="checkout&#x5B;3&#x5D;&#x5B;checkedOutOn&#x5D;" value="<today>">',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/3/personId' => 
  array (
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Who&#039;s checking out?</label><select name="checkout&#x5B;3&#x5D;&#x5B;personId&#x5D;" class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="checkout&#x5B;3&#x5D;&#x5B;personId&#x5D;" class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="checkout&#x5B;3&#x5D;&#x5B;personId&#x5D;">Who&#039;s checking out?</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="checkout&#x5B;3&#x5D;&#x5B;personId&#x5D;"></select>',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/3/withinLibraryIds' => 
  array (
    'prepared' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><input type="text" name="checkout&#x5B;3&#x5D;&#x5B;withinLibraryIds&#x5D;" class="form-control" value=""><p class="help-block">One barcode per line</p></div>',
      'element' => '<input type="text" name="checkout&#x5B;3&#x5D;&#x5B;withinLibraryIds&#x5D;" class="form-control" value="">',
      'label' => '<label for="checkout&#x5B;3&#x5D;&#x5B;withinLibraryIds&#x5D;">Book Ids</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One barcode per line</p>',
      'text' => '<input type="text" name="checkout&#x5B;3&#x5D;&#x5B;withinLibraryIds&#x5D;" value="">',
      'hidden' => '<input type="hidden" name="checkout&#x5B;3&#x5D;&#x5B;withinLibraryIds&#x5D;" value="">',
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/<target>/checkedOutOn' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Checked out on</label><input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="<today>"></div>',
      'element' => '<input type="date" name="checkedOutOn" min="<today>" step="any" class="form-control" value="<today>">',
      'label' => '<label for="checkedOutOn">Checked out on</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="checkedOutOn" value="<today>">',
      'hidden' => '<input type="hidden" name="checkedOutOn" value="<today>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/<target>/personId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Who&#039;s checking out?</label><select name="personId" class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="personId" class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="personId">Who&#039;s checking out?</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="personId"></select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\MassCheckoutForm::checkout/<target>/withinLibraryIds' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Book Ids</label><input type="text" name="withinLibraryIds" class="form-control" value=""><p class="help-block">One barcode per line</p></div>',
      'element' => '<input type="text" name="withinLibraryIds" class="form-control" value="">',
      'label' => '<label for="withinLibraryIds">Book Ids</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">One barcode per line</p>',
      'text' => '<input type="text" name="withinLibraryIds" value="">',
      'hidden' => '<input type="hidden" name="withinLibraryIds" value="">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\MassCheckoutForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\MassCheckoutForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" tabindex="4" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" tabindex="4" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" tabindex="4" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="publication" action="&#x2F;form-action" class="form-horizontal" id="publication">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::adminNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control"></textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control"></textarea>',
      'label' => '<label for="adminNotes">Admin notes</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::authorsAll' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Author(s)</label><select name="authorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;">(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select></div>',
      'element' => '<select name="authorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;">(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select>',
      'label' => '<label for="authorsAll">Author(s)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="authorsAll&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Author(s)</label><select name="authorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;" selected>(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select></div>',
      'element' => '<select name="authorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;" selected>(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select>',
      'select_without_options' => '<select name="authorsAll&#x5B;&#x5D;" multiple><option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;" selected>(verantw. Redaktionsteam)</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::bookEdition' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Edition number</label><input type="text" name="bookEdition" placeholder="1" maxlength="50" class="form-control" value=""></div>',
      'element' => '<input type="text" name="bookEdition" placeholder="1" maxlength="50" class="form-control" value="">',
      'label' => '<label for="bookEdition">Edition number</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="bookEdition" placeholder="1" maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="bookEdition" placeholder="1" maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Edition number</label><input type="text" name="bookEdition" placeholder="1" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="bookEdition" placeholder="1" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="bookEdition" placeholder="1" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="bookEdition" placeholder="1" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Edition number</label><input type="text" name="bookEdition" placeholder="1" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="bookEdition" placeholder="1" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="bookEdition" placeholder="1" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="bookEdition" placeholder="1" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::bookFormatType' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Book format</label><select name="bookFormatType" class="form-control"><option value=""></option>
<option value="AudiobookFormat">AudiobookFormat</option>
<option value="EBook">EBook</option>
<!-- 3 more options, ea07505ebe37395282d8643db71d2d3e -->
<option value="GraphicNovel">GraphicNovel</option>
</select></div>',
      'element' => '<select name="bookFormatType" class="form-control"><option value=""></option>
<option value="AudiobookFormat">AudiobookFormat</option>
<option value="EBook">EBook</option>
<!-- 3 more options, ea07505ebe37395282d8643db71d2d3e -->
<option value="GraphicNovel">GraphicNovel</option>
</select>',
      'label' => '<label for="bookFormatType">Book format</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="bookFormatType"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Book format</label><select name="bookFormatType" class="form-control"><option value=""></option>
<option value="AudiobookFormat" selected>AudiobookFormat</option>
<option value="EBook">EBook</option>
<!-- 3 more options, ea07505ebe37395282d8643db71d2d3e -->
<option value="GraphicNovel">GraphicNovel</option>
</select></div>',
      'element' => '<select name="bookFormatType" class="form-control"><option value=""></option>
<option value="AudiobookFormat" selected>AudiobookFormat</option>
<option value="EBook">EBook</option>
<!-- 3 more options, ea07505ebe37395282d8643db71d2d3e -->
<option value="GraphicNovel">GraphicNovel</option>
</select>',
      'select_without_options' => '<select name="bookFormatType"><option value="AudiobookFormat" selected>AudiobookFormat</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Book format</label><select name="bookFormatType" class="form-control"><option value=""></option>
<option value="AudiobookFormat">AudiobookFormat</option>
<option value="EBook">EBook</option>
<!-- 3 more options, ea07505ebe37395282d8643db71d2d3e -->
<option value="GraphicNovel">GraphicNovel</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::categoryId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Category</label><select name="categoryId" class="form-control"><option value=""></option>
<option value="2">Fr. Kentenich - Pre-Schoenstatt (1899-1912)</option>
<option value="3">Fr. Kentenich - Founding Era (1912-1919)</option>
<!-- 12 more options, c805d909678e1a5e16759068341f04fd -->
<option value="16">Prayer</option>
</select></div>',
      'element' => '<select name="categoryId" class="form-control"><option value=""></option>
<option value="2">Fr. Kentenich - Pre-Schoenstatt (1899-1912)</option>
<option value="3">Fr. Kentenich - Founding Era (1912-1919)</option>
<!-- 12 more options, c805d909678e1a5e16759068341f04fd -->
<option value="16">Prayer</option>
</select>',
      'label' => '<label for="categoryId">Category</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="categoryId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Category</label><select name="categoryId" class="form-control"><option value=""></option>
<option value="2" selected>Fr. Kentenich - Pre-Schoenstatt (1899-1912)</option>
<option value="3">Fr. Kentenich - Founding Era (1912-1919)</option>
<!-- 12 more options, c805d909678e1a5e16759068341f04fd -->
<option value="16">Prayer</option>
</select></div>',
      'element' => '<select name="categoryId" class="form-control"><option value=""></option>
<option value="2" selected>Fr. Kentenich - Pre-Schoenstatt (1899-1912)</option>
<option value="3">Fr. Kentenich - Founding Era (1912-1919)</option>
<!-- 12 more options, c805d909678e1a5e16759068341f04fd -->
<option value="16">Prayer</option>
</select>',
      'select_without_options' => '<select name="categoryId"><option value="2" selected>Fr. Kentenich - Pre-Schoenstatt (1899-1912)</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Category</label><select name="categoryId" class="form-control"><option value=""></option>
<option value="2">Fr. Kentenich - Pre-Schoenstatt (1899-1912)</option>
<option value="3">Fr. Kentenich - Founding Era (1912-1919)</option>
<!-- 12 more options, c805d909678e1a5e16759068341f04fd -->
<option value="16">Prayer</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::copyrightInfo' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Copyright info</label><textarea name="copyrightInfo" maxlength="500" class="form-control"></textarea><p class="help-block">Include information about the copyright owner, contact information, and under what licence it has been published.</p></div>',
      'element' => '<textarea name="copyrightInfo" maxlength="500" class="form-control"></textarea>',
      'label' => '<label for="copyrightInfo">Copyright info</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Include information about the copyright owner, contact information, and under what licence it has been published.</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Copyright info</label><textarea name="copyrightInfo" maxlength="500" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">Include information about the copyright owner, contact information, and under what licence it has been published.</p></div>',
      'element' => '<textarea name="copyrightInfo" maxlength="500" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Copyright info</label><textarea name="copyrightInfo" maxlength="500" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><p class="help-block">Include information about the copyright owner, contact information, and under what licence it has been published.</p></div>',
      'element' => '<textarea name="copyrightInfo" maxlength="500" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::copyrightYear' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Copyright year</label><input type="number" name="copyrightYear" min="1800" max="2025" step="1" class="form-control" value=""><p class="help-block">The year of the first edition, ie. the creation of the creative work.</p></div>',
      'element' => '<input type="number" name="copyrightYear" min="1800" max="2025" step="1" class="form-control" value="">',
      'label' => '<label for="copyrightYear">Copyright year</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">The year of the first edition, ie. the creation of the creative work.</p>',
      'text' => '<input type="text" name="copyrightYear" value="">',
      'hidden' => '<input type="hidden" name="copyrightYear" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Copyright year</label><input type="number" name="copyrightYear" min="1800" max="2025" step="1" class="form-control" value="42"><p class="help-block">The year of the first edition, ie. the creation of the creative work.</p></div>',
      'element' => '<input type="number" name="copyrightYear" min="1800" max="2025" step="1" class="form-control" value="42">',
      'text' => '<input type="text" name="copyrightYear" value="42">',
      'hidden' => '<input type="hidden" name="copyrightYear" value="42">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Copyright year</label><input type="number" name="copyrightYear" min="1800" max="2025" step="1" class="form-control" value="forty-two"><p class="help-block">The year of the first edition, ie. the creation of the creative work.</p></div>',
      'element' => '<input type="number" name="copyrightYear" min="1800" max="2025" step="1" class="form-control" value="forty-two">',
      'text' => '<input type="text" name="copyrightYear" value="forty-two">',
      'hidden' => '<input type="hidden" name="copyrightYear" value="forty-two">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::datePublishedText' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Published date</label><input type="text" name="datePublishedText" placeholder="2007" class="form-control" value=""><p class="help-block">The year is plenty; if a more specific date is available, use format YYYY-MM-DD</p></div>',
      'element' => '<input type="text" name="datePublishedText" placeholder="2007" class="form-control" value="">',
      'label' => '<label for="datePublishedText">Published date</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">The year is plenty; if a more specific date is available, use format YYYY-MM-DD</p>',
      'text' => '<input type="text" name="datePublishedText" placeholder="2007" value="">',
      'hidden' => '<input type="hidden" name="datePublishedText" placeholder="2007" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Published date</label><input type="text" name="datePublishedText" placeholder="2007" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">The year is plenty; if a more specific date is available, use format YYYY-MM-DD</p></div>',
      'element' => '<input type="text" name="datePublishedText" placeholder="2007" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="datePublishedText" placeholder="2007" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="datePublishedText" placeholder="2007" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Published date</label><input type="text" name="datePublishedText" placeholder="2007" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>Please enter a valid date. Remember to add a `0` before single digit month and day numbers.</li></ul><p class="help-block">The year is plenty; if a more specific date is available, use format YYYY-MM-DD</p></div>',
      'element' => '<input type="text" name="datePublishedText" placeholder="2007" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Please enter a valid date. Remember to add a `0` before single digit month and day numbers.</li></ul>',
      'text' => '<input type="text" name="datePublishedText" placeholder="2007" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="datePublishedText" placeholder="2007" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::description' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="5" class="form-control"></textarea><p class="help-block">Description of the book, helpful to a non-Schoenstatt visitor to the site.</p></div>',
      'element' => '<textarea name="description" rows="5" class="form-control"></textarea>',
      'label' => '<label for="description">Description</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Description of the book, helpful to a non-Schoenstatt visitor to the site.</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="5" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">Description of the book, helpful to a non-Schoenstatt visitor to the site.</p></div>',
      'element' => '<textarea name="description" rows="5" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="description" rows="5" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><p class="help-block">Description of the book, helpful to a non-Schoenstatt visitor to the site.</p></div>',
      'element' => '<textarea name="description" rows="5" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::editionNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Notes relevant to this particular edition</label><textarea name="editionNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control"></textarea></div>',
      'element' => '<textarea name="editionNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control"></textarea>',
      'label' => '<label for="editionNotes">Notes relevant to this particular edition</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Notes relevant to this particular edition</label><textarea name="editionNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="editionNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Notes relevant to this particular edition</label><textarea name="editionNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="editionNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::editorsAll' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Editor(s)</label><select name="editorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;">(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select></div>',
      'element' => '<select name="editorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;">(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select>',
      'label' => '<label for="editorsAll">Editor(s)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="editorsAll&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Editor(s)</label><select name="editorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;" selected>(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select></div>',
      'element' => '<select name="editorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;" selected>(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select>',
      'select_without_options' => '<select name="editorsAll&#x5B;&#x5D;" multiple><option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;" selected>(verantw. Redaktionsteam)</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::hasNoExplictEditionNumber' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="hasNoExplictEditionNumber" value="0"><label><input type="checkbox" name="hasNoExplictEditionNumber" value="1"> Publication has no explicit edition number?</label></div>',
      'element' => '<input type="hidden" name="hasNoExplictEditionNumber" value="0"><label><input type="checkbox" name="hasNoExplictEditionNumber" value="1"> Publication has no explicit edition number?</label>',
      'label' => '<label for="hasNoExplictEditionNumber">Publication has no explicit edition number?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="hasNoExplictEditionNumber" value="0"><label><input type="checkbox" name="hasNoExplictEditionNumber" value="1" checked> Publication has no explicit edition number?</label></div>',
      'element' => '<input type="hidden" name="hasNoExplictEditionNumber" value="0"><label><input type="checkbox" name="hasNoExplictEditionNumber" value="1" checked> Publication has no explicit edition number?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::hasNoISBN' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="hasNoISBN" value="0"><label><input type="checkbox" name="hasNoISBN" value="1"> Publication has no ISBN?</label></div>',
      'element' => '<input type="hidden" name="hasNoISBN" value="0"><label><input type="checkbox" name="hasNoISBN" value="1"> Publication has no ISBN?</label>',
      'label' => '<label for="hasNoISBN">Publication has no ISBN?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="hasNoISBN" value="0"><label><input type="checkbox" name="hasNoISBN" value="1" checked> Publication has no ISBN?</label></div>',
      'element' => '<input type="hidden" name="hasNoISBN" value="0"><label><input type="checkbox" name="hasNoISBN" value="1" checked> Publication has no ISBN?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::inLanguage' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage&#x5B;&#x5D;" required multiple class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select></div>',
      'element' => '<select name="inLanguage&#x5B;&#x5D;" required multiple class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select>',
      'label' => '<label for="inLanguage">Language</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="inLanguage&#x5B;&#x5D;" required multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage&#x5B;&#x5D;" required multiple class="form-control"><option value=""></option>
<option value="aa" selected>Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select></div>',
      'element' => '<select name="inLanguage&#x5B;&#x5D;" required multiple class="form-control"><option value=""></option>
<option value="aa" selected>Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select>',
      'select_without_options' => '<select name="inLanguage&#x5B;&#x5D;" required multiple><option value="aa" selected>Afar</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::isFormallyPublished' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isFormallyPublished" value="0"><label><input type="checkbox" name="isFormallyPublished" value="1"> Is formally published?</label></div>',
      'element' => '<input type="hidden" name="isFormallyPublished" value="0"><label><input type="checkbox" name="isFormallyPublished" value="1"> Is formally published?</label>',
      'label' => '<label for="isFormallyPublished">Is formally published?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isFormallyPublished" value="0"><label><input type="checkbox" name="isFormallyPublished" value="1" checked> Is formally published?</label></div>',
      'element' => '<input type="hidden" name="isFormallyPublished" value="0"><label><input type="checkbox" name="isFormallyPublished" value="1" checked> Is formally published?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::isRevisedWithBookInHand' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isRevisedWithBookInHand" value="0"><label><input type="checkbox" name="isRevisedWithBookInHand" value="1"> Data has been revised with book in hand?</label></div>',
      'element' => '<input type="hidden" name="isRevisedWithBookInHand" value="0"><label><input type="checkbox" name="isRevisedWithBookInHand" value="1"> Data has been revised with book in hand?</label>',
      'label' => '<label for="isRevisedWithBookInHand">Data has been revised with book in hand?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isRevisedWithBookInHand" value="0"><label><input type="checkbox" name="isRevisedWithBookInHand" value="1" checked> Data has been revised with book in hand?</label></div>',
      'element' => '<input type="hidden" name="isRevisedWithBookInHand" value="0"><label><input type="checkbox" name="isRevisedWithBookInHand" value="1" checked> Data has been revised with book in hand?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::isScientificWork' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isScientificWork" value="0"><label><input type="checkbox" name="isScientificWork" value="1"> Scientific work?</label></div>',
      'element' => '<input type="hidden" name="isScientificWork" value="0"><label><input type="checkbox" name="isScientificWork" value="1"> Scientific work?</label>',
      'label' => '<label for="isScientificWork">Scientific work?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isScientificWork" value="0"><label><input type="checkbox" name="isScientificWork" value="1" checked> Scientific work?</label></div>',
      'element' => '<input type="hidden" name="isScientificWork" value="0"><label><input type="checkbox" name="isScientificWork" value="1" checked> Scientific work?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::isbn' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>ISBN</label><input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value=""></div>',
      'element' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value="">',
      'label' => '<label for="isbn">ISBN</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="">',
      'hidden' => '<input type="hidden" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>ISBN</label><input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>ISBN</label><input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="isbn" placeholder="ex.&#x20;9780030426599" maxlength="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::keywords' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Keywords</label><select name="keywords&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="joseph&#x20;kentenich">joseph kentenich</option>
<option value="youth">youth</option>
<!-- 467 more options, aa4d33d132a3967d111eb16d61a0ed4a -->
<option value="Dialectical&#x20;Materialism">Dialectical Materialism</option>
</select><p class="help-block">Thematic tags regarding the content of the book</p></div>',
      'element' => '<select name="keywords&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="joseph&#x20;kentenich">joseph kentenich</option>
<option value="youth">youth</option>
<!-- 467 more options, aa4d33d132a3967d111eb16d61a0ed4a -->
<option value="Dialectical&#x20;Materialism">Dialectical Materialism</option>
</select>',
      'label' => '<label for="keywords">Keywords</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Thematic tags regarding the content of the book</p>',
      'select_without_options' => '<select name="keywords&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Keywords</label><select name="keywords&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="joseph&#x20;kentenich" selected>joseph kentenich</option>
<option value="youth">youth</option>
<!-- 467 more options, aa4d33d132a3967d111eb16d61a0ed4a -->
<option value="Dialectical&#x20;Materialism">Dialectical Materialism</option>
</select><p class="help-block">Thematic tags regarding the content of the book</p></div>',
      'element' => '<select name="keywords&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="joseph&#x20;kentenich" selected>joseph kentenich</option>
<option value="youth">youth</option>
<!-- 467 more options, aa4d33d132a3967d111eb16d61a0ed4a -->
<option value="Dialectical&#x20;Materialism">Dialectical Materialism</option>
</select>',
      'select_without_options' => '<select name="keywords&#x5B;&#x5D;" multiple><option value="joseph&#x20;kentenich" selected>joseph kentenich</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::mainPublicationId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Main publication (use for outdated editions)</label><select name="mainPublicationId" class="form-control"><option value=""></option>
<option value="2154">Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select><p class="help-block">To avoid showing multiple editions of the same book in the index, please select the latest edition of this work from the list.</p></div>',
      'element' => '<select name="mainPublicationId" class="form-control"><option value=""></option>
<option value="2154">Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select>',
      'label' => '<label for="mainPublicationId">Main publication (use for outdated editions)</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">To avoid showing multiple editions of the same book in the index, please select the latest edition of this work from the list.</p>',
      'select_without_options' => '<select name="mainPublicationId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Main publication (use for outdated editions)</label><select name="mainPublicationId" class="form-control"><option value=""></option>
<option value="2154" selected>Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select><p class="help-block">To avoid showing multiple editions of the same book in the index, please select the latest edition of this work from the list.</p></div>',
      'element' => '<select name="mainPublicationId" class="form-control"><option value=""></option>
<option value="2154" selected>Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select>',
      'select_without_options' => '<select name="mainPublicationId"><option value="2154" selected>Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Main publication (use for outdated editions)</label><select name="mainPublicationId" class="form-control"><option value=""></option>
<option value="2154">Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">To avoid showing multiple editions of the same book in the index, please select the latest edition of this work from the list.</p></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::numberOfPages' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Number of pages</label><input type="text" name="numberOfPages" placeholder="xxi&#x2B;133" class="form-control" value=""><p class="help-block">Normally, just the number of pages unless book has roman numeral-numbered pages at the beginning. In this case, use the form `xxxii+442`.</p></div>',
      'element' => '<input type="text" name="numberOfPages" placeholder="xxi&#x2B;133" class="form-control" value="">',
      'label' => '<label for="numberOfPages">Number of pages</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Normally, just the number of pages unless book has roman numeral-numbered pages at the beginning. In this case, use the form `xxxii+442`.</p>',
      'text' => '<input type="text" name="numberOfPages" placeholder="xxi&#x2B;133" value="">',
      'hidden' => '<input type="hidden" name="numberOfPages" placeholder="xxi&#x2B;133" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Number of pages</label><input type="text" name="numberOfPages" placeholder="xxi&#x2B;133" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">Normally, just the number of pages unless book has roman numeral-numbered pages at the beginning. In this case, use the form `xxxii+442`.</p></div>',
      'element' => '<input type="text" name="numberOfPages" placeholder="xxi&#x2B;133" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="numberOfPages" placeholder="xxi&#x2B;133" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="numberOfPages" placeholder="xxi&#x2B;133" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Number of pages</label><input type="text" name="numberOfPages" placeholder="xxi&#x2B;133" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>Please use a combination of roman numerals and/or numbers separated by `+`.</li></ul><p class="help-block">Normally, just the number of pages unless book has roman numeral-numbered pages at the beginning. In this case, use the form `xxxii+442`.</p></div>',
      'element' => '<input type="text" name="numberOfPages" placeholder="xxi&#x2B;133" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Please use a combination of roman numerals and/or numbers separated by `+`.</li></ul>',
      'text' => '<input type="text" name="numberOfPages" placeholder="xxi&#x2B;133" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="numberOfPages" placeholder="xxi&#x2B;133" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::publicNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Notes relevant to the whole work (including other editions)</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control"></textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control"></textarea>',
      'label' => '<label for="publicNotes">Notes relevant to the whole work (including other editions)</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Notes relevant to the whole work (including other editions)</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Notes relevant to the whole work (including other editions)</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::publisher' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Publisher</label><select name="publisher" class="form-control"><option value=""></option>
<option value="&#x201E;Der&#x20;Els&#xE4;sser&quot;,&#x20;Buchdruckerei&#x20;und&#x20;Zeitungsverlag&#x20;Stra&#xDF;burg">„Der Elsässer&quot;, Buchdruckerei und Zeitungsverlag Straßburg</option>
<option value="&#x201E;Farul&#x20;Nou&quot;&#x20;Bucuresti">„Farul Nou&quot; Bucuresti</option>
<!-- 845 more options, f0710bf2ec6dcce90019d1518a5fccff -->
<option value="Zisterzienserkloster&#x20;Schlierbach&#x20;in&#x20;Ober&#xF6;sterreich">Zisterzienserkloster Schlierbach in Oberösterreich</option>
</select></div>',
      'element' => '<select name="publisher" class="form-control"><option value=""></option>
<option value="&#x201E;Der&#x20;Els&#xE4;sser&quot;,&#x20;Buchdruckerei&#x20;und&#x20;Zeitungsverlag&#x20;Stra&#xDF;burg">„Der Elsässer&quot;, Buchdruckerei und Zeitungsverlag Straßburg</option>
<option value="&#x201E;Farul&#x20;Nou&quot;&#x20;Bucuresti">„Farul Nou&quot; Bucuresti</option>
<!-- 845 more options, f0710bf2ec6dcce90019d1518a5fccff -->
<option value="Zisterzienserkloster&#x20;Schlierbach&#x20;in&#x20;Ober&#xF6;sterreich">Zisterzienserkloster Schlierbach in Oberösterreich</option>
</select>',
      'label' => '<label for="publisher">Publisher</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="publisher"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Publisher</label><select name="publisher" class="form-control"><option value=""></option>
<option value="&#x201E;Der&#x20;Els&#xE4;sser&quot;,&#x20;Buchdruckerei&#x20;und&#x20;Zeitungsverlag&#x20;Stra&#xDF;burg" selected>„Der Elsässer&quot;, Buchdruckerei und Zeitungsverlag Straßburg</option>
<option value="&#x201E;Farul&#x20;Nou&quot;&#x20;Bucuresti">„Farul Nou&quot; Bucuresti</option>
<!-- 845 more options, f0710bf2ec6dcce90019d1518a5fccff -->
<option value="Zisterzienserkloster&#x20;Schlierbach&#x20;in&#x20;Ober&#xF6;sterreich">Zisterzienserkloster Schlierbach in Oberösterreich</option>
</select></div>',
      'element' => '<select name="publisher" class="form-control"><option value=""></option>
<option value="&#x201E;Der&#x20;Els&#xE4;sser&quot;,&#x20;Buchdruckerei&#x20;und&#x20;Zeitungsverlag&#x20;Stra&#xDF;burg" selected>„Der Elsässer&quot;, Buchdruckerei und Zeitungsverlag Straßburg</option>
<option value="&#x201E;Farul&#x20;Nou&quot;&#x20;Bucuresti">„Farul Nou&quot; Bucuresti</option>
<!-- 845 more options, f0710bf2ec6dcce90019d1518a5fccff -->
<option value="Zisterzienserkloster&#x20;Schlierbach&#x20;in&#x20;Ober&#xF6;sterreich">Zisterzienserkloster Schlierbach in Oberösterreich</option>
</select>',
      'select_without_options' => '<select name="publisher"><option value="&#x201E;Der&#x20;Els&#xE4;sser&quot;,&#x20;Buchdruckerei&#x20;und&#x20;Zeitungsverlag&#x20;Stra&#xDF;burg" selected>„Der Elsässer&quot;, Buchdruckerei und Zeitungsverlag Straßburg</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::publishingPlace' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Publishing place</label><input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value=""></div>',
      'element' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value="">',
      'label' => '<label for="publishingPlace">Publishing place</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="">',
      'hidden' => '<input type="hidden" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Publishing place</label><input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Publishing place</label><input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="publishingPlace" placeholder="Madrid,&#x20;Spain" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::resourceId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Access level</label><select name="resourceId" class="form-control"><option value=""></option>
<option value="publication_public" selected>Public</option>
<option value="publication_user">Authenticated users</option>
<!-- 5 more options, cb5a5bdd417a318094a6c6b132314957 -->
<option value="publication_sisters">Sisters of Mary</option>
</select><p class="help-block">This defines which users will see this book information listed</p></div>',
      'element' => '<select name="resourceId" class="form-control"><option value=""></option>
<option value="publication_public" selected>Public</option>
<option value="publication_user">Authenticated users</option>
<!-- 5 more options, cb5a5bdd417a318094a6c6b132314957 -->
<option value="publication_sisters">Sisters of Mary</option>
</select>',
      'label' => '<label for="resourceId">Access level</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">This defines which users will see this book information listed</p>',
      'select_without_options' => '<select name="resourceId"><option value="publication_public" selected>Public</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Access level</label><select name="resourceId" class="form-control"><option value=""></option>
<option value="publication_public">Public</option>
<option value="publication_user">Authenticated users</option>
<!-- 5 more options, cb5a5bdd417a318094a6c6b132314957 -->
<option value="publication_sisters">Sisters of Mary</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">This defines which users will see this book information listed</p></div>',
      'element' => '<select name="resourceId" class="form-control"><option value=""></option>
<option value="publication_public">Public</option>
<option value="publication_user">Authenticated users</option>
<!-- 5 more options, cb5a5bdd417a318094a6c6b132314957 -->
<option value="publication_sisters">Sisters of Mary</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="resourceId"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::title' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Title</label><input type="text" name="title" required maxlength="300" class="form-control" value=""></div>',
      'element' => '<input type="text" name="title" required maxlength="300" class="form-control" value="">',
      'label' => '<label for="title">Title</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="title" required maxlength="300" value="">',
      'hidden' => '<input type="hidden" name="title" required maxlength="300" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Title</label><input type="text" name="title" required maxlength="300" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="title" required maxlength="300" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="title" required maxlength="300" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="title" required maxlength="300" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Title</label><input type="text" name="title" required maxlength="300" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="title" required maxlength="300" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="title" required maxlength="300" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="title" required maxlength="300" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::translatedFromPublicationId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Translated from</label><select name="translatedFromPublicationId" class="form-control"><option value=""></option>
<option value="2154">Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select><p class="help-block">The work in the original language from which this book was translated. If there is more than one edition in the original language, select the newest edition.</p></div>',
      'element' => '<select name="translatedFromPublicationId" class="form-control"><option value=""></option>
<option value="2154">Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select>',
      'label' => '<label for="translatedFromPublicationId">Translated from</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">The work in the original language from which this book was translated. If there is more than one edition in the original language, select the newest edition.</p>',
      'select_without_options' => '<select name="translatedFromPublicationId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Translated from</label><select name="translatedFromPublicationId" class="form-control"><option value=""></option>
<option value="2154" selected>Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select><p class="help-block">The work in the original language from which this book was translated. If there is more than one edition in the original language, select the newest edition.</p></div>',
      'element' => '<select name="translatedFromPublicationId" class="form-control"><option value=""></option>
<option value="2154" selected>Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select>',
      'select_without_options' => '<select name="translatedFromPublicationId"><option value="2154" selected>Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Translated from</label><select name="translatedFromPublicationId" class="form-control"><option value=""></option>
<option value="2154">Les années cachées - Père Joseph Kentenich, enfance et jeunesse (1885-1912) []</option>
<option value="2110">Bajo la Protección de María - Tomo 1 [3, 1978]</option>
<!-- 4200 more options, ad497b763232ebdb51b2a80caaaf2b2a -->
<option value="10164">Im Dienste Mariens [5, 1935]</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul><p class="help-block">The work in the original language from which this book was translated. If there is more than one edition in the original language, select the newest edition.</p></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::translatorsAll' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Translator</label><select name="translatorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;">(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select></div>',
      'element' => '<select name="translatorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;">(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select>',
      'label' => '<label for="translatorsAll">Translator</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="translatorsAll&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Translator</label><select name="translatorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;" selected>(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select></div>',
      'element' => '<select name="translatorsAll&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;" selected>(verantw. Redaktionsteam)</option>
<option value="2&#xAA;&#x20;Escuela&#x20;de&#x20;Jefes&#x20;del&#x20;Paraguay&#x20;&#x28;JM&#x29;&#x20;-&#x20;&#x20;&#x201C;Conqu">2ª Escuela de Jefes del Paraguay (JM) -  “Conqu</option>
<!-- 1928 more options, d2f827319a0aa4b49cab4f60bf076d3b -->
<option value="p629">Óscar Iván Saldívar</option>
</select>',
      'select_without_options' => '<select name="translatorsAll&#x5B;&#x5D;" multiple><option value="&#x28;verantw.&#x20;Redaktionsteam&#x29;" selected>(verantw. Redaktionsteam)</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::url1' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="">',
      'label' => '<label for="url1">URL 1</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::url1Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 1 Label</label><select name="url1Label" class="form-control"><option value=""></option>
<option value="Download">Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select></div>',
      'element' => '<select name="url1Label" class="form-control"><option value=""></option>
<option value="Download">Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select>',
      'label' => '<label for="url1Label">URL 1 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url1Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 1 Label</label><select name="url1Label" class="form-control"><option value=""></option>
<option value="Download" selected>Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select></div>',
      'element' => '<select name="url1Label" class="form-control"><option value=""></option>
<option value="Download" selected>Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select>',
      'select_without_options' => '<select name="url1Label"><option value="Download" selected>Download</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::url2' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="">',
      'label' => '<label for="url2">URL 2</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::url2Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 2 Label</label><select name="url2Label" class="form-control"><option value=""></option>
<option value="Download">Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select></div>',
      'element' => '<select name="url2Label" class="form-control"><option value=""></option>
<option value="Download">Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select>',
      'label' => '<label for="url2Label">URL 2 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url2Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 2 Label</label><select name="url2Label" class="form-control"><option value=""></option>
<option value="Download" selected>Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select></div>',
      'element' => '<select name="url2Label" class="form-control"><option value=""></option>
<option value="Download" selected>Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select>',
      'select_without_options' => '<select name="url2Label"><option value="Download" selected>Download</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::url3' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="">',
      'label' => '<label for="url3">URL 3</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::url3Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>URL 3 Label</label><select name="url3Label" class="form-control"><option value=""></option>
<option value="Download">Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select></div>',
      'element' => '<select name="url3Label" class="form-control"><option value=""></option>
<option value="Download">Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select>',
      'label' => '<label for="url3Label">URL 3 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url3Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>URL 3 Label</label><select name="url3Label" class="form-control"><option value=""></option>
<option value="Download" selected>Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select></div>',
      'element' => '<select name="url3Label" class="form-control"><option value=""></option>
<option value="Download" selected>Download</option>
<option value="Purchase">Purchase</option>
<!-- 2 more options, f6f2681848e6e4873244c3d37a0b5d41 -->
<option value="Information">Information</option>
</select>',
      'select_without_options' => '<select name="url3Label"><option value="Download" selected>Download</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationForm::volumeNumber' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Volume number</label><input type="text" name="volumeNumber" maxlength="25" class="form-control" value=""></div>',
      'element' => '<input type="text" name="volumeNumber" maxlength="25" class="form-control" value="">',
      'label' => '<label for="volumeNumber">Volume number</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="volumeNumber" maxlength="25" value="">',
      'hidden' => '<input type="hidden" name="volumeNumber" maxlength="25" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Volume number</label><input type="text" name="volumeNumber" maxlength="25" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="volumeNumber" maxlength="25" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="volumeNumber" maxlength="25" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="volumeNumber" maxlength="25" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Volume number</label><input type="text" name="volumeNumber" maxlength="25" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="volumeNumber" maxlength="25" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="volumeNumber" maxlength="25" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="volumeNumber" maxlength="25" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationsSearchForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="GET" name="search_publications" action="&#x2F;form-action" class="form-horizontal" id="search_publications">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationsSearchForm::inLanguage' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Languages</label><select name="inLanguage&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select></div>',
      'element' => '<select name="inLanguage&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select>',
      'label' => '<label for="inLanguage">Languages</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="inLanguage&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Languages</label><select name="inLanguage&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="aa" selected>Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select></div>',
      'element' => '<select name="inLanguage&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="aa" selected>Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select>',
      'select_without_options' => '<select name="inLanguage&#x5B;&#x5D;" multiple><option value="aa" selected>Afar</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationsSearchForm::includeDataSources' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="includeDataSources" value="0"><label><input type="checkbox" name="includeDataSources" value="1"> Show data sources?</label></div>',
      'element' => '<input type="hidden" name="includeDataSources" value="0"><label><input type="checkbox" name="includeDataSources" value="1"> Show data sources?</label>',
      'label' => '<label for="includeDataSources">Show data sources?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="includeDataSources" value="0"><label><input type="checkbox" name="includeDataSources" value="1" checked> Show data sources?</label></div>',
      'element' => '<input type="hidden" name="includeDataSources" value="0"><label><input type="checkbox" name="includeDataSources" value="1" checked> Show data sources?</label>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="includeDataSources" value="0"><label><input type="checkbox" name="includeDataSources" value="1"> Show data sources?</label></div><ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationsSearchForm::search' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search&#x20;Catalogs" size="50" value=""></div>',
      'element' => '<input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search&#x20;Catalogs" size="50" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="search" class="input-lg&#x20;search-query" placeholder="Search&#x20;Catalogs" size="50" value="">',
      'hidden' => '<input type="hidden" name="search" class="input-lg&#x20;search-query" placeholder="Search&#x20;Catalogs" size="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search&#x20;Catalogs" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search&#x20;Catalogs" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="search" class="input-lg&#x20;search-query" placeholder="Search&#x20;Catalogs" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="search" class="input-lg&#x20;search-query" placeholder="Search&#x20;Catalogs" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search&#x20;Catalogs" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search&#x20;Catalogs" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="search" class="input-lg&#x20;search-query" placeholder="Search&#x20;Catalogs" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="search" class="input-lg&#x20;search-query" placeholder="Search&#x20;Catalogs" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationsSearchForm::showEditionsSeparately' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="showEditionsSeparately" value="0"><label><input type="checkbox" name="showEditionsSeparately" value="1"> Show editions separately?</label></div>',
      'element' => '<input type="hidden" name="showEditionsSeparately" value="0"><label><input type="checkbox" name="showEditionsSeparately" value="1"> Show editions separately?</label>',
      'label' => '<label for="showEditionsSeparately">Show editions separately?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="showEditionsSeparately" value="0"><label><input type="checkbox" name="showEditionsSeparately" value="1" checked> Show editions separately?</label></div>',
      'element' => '<input type="hidden" name="showEditionsSeparately" value="0"><label><input type="checkbox" name="showEditionsSeparately" value="1" checked> Show editions separately?</label>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="showEditionsSeparately" value="0"><label><input type="checkbox" name="showEditionsSeparately" value="1"> Show editions separately?</label></div><ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\PublicationsSearchForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Search">Search</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Search">Search</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Search">Search</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\SearchForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="GET" name="search" action="&#x2F;form-action" class="form-horizontal" id="search">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\SearchForm::category' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="category" size="50" class="form-control" value=""></div>',
      'element' => '<input type="text" name="category" size="50" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="category" size="50" value="">',
      'hidden' => '<input type="hidden" name="category" size="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="category" size="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="category" size="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="category" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="category" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="category" size="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="category" size="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="category" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="category" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\SearchForm::collectionId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Collection</label><select name="collectionId" class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="collectionId" class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="collectionId">Collection</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="collectionId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Collection</label><select name="collectionId" class="form-control"><option value="" selected></option>
</select></div>',
      'element' => '<select name="collectionId" class="form-control"><option value="" selected></option>
</select>',
      'select_without_options' => '<select name="collectionId"><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Collection</label><select name="collectionId" class="form-control"><option value=""></option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\SearchForm::inLanguage' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><input type="text" name="inLanguage" size="2" class="form-control" value=""></div>',
      'element' => '<input type="text" name="inLanguage" size="2" class="form-control" value="">',
      'label' => '<label for="inLanguage">Language</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="inLanguage" size="2" value="">',
      'hidden' => '<input type="hidden" name="inLanguage" size="2" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><input type="text" name="inLanguage" size="2" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="inLanguage" size="2" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="inLanguage" size="2" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="inLanguage" size="2" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><input type="text" name="inLanguage" size="2" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>The input is more than 2 characters long</li></ul></div>',
      'element' => '<input type="text" name="inLanguage" size="2" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>The input is more than 2 characters long</li></ul>',
      'text' => '<input type="text" name="inLanguage" size="2" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="inLanguage" size="2" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\SearchForm::libraryId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="libraryId" value="">',
      'hidden' => '<input type="hidden" name="libraryId" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="libraryId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="libraryId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="libraryId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="libraryId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="libraryId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="libraryId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\SearchForm::search' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value=""></div>',
      'element' => '<input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="search" class="input-lg&#x20;search-query" placeholder="Search" size="50" value="">',
      'hidden' => '<input type="hidden" name="search" class="input-lg&#x20;search-query" placeholder="Search" size="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="search" class="input-lg&#x20;search-query" placeholder="Search" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="search" class="input-lg&#x20;search-query" placeholder="Search" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="search" class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="search" class="input-lg&#x20;search-query" placeholder="Search" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="search" class="input-lg&#x20;search-query" placeholder="Search" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\SearchForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Search">Search</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Search">Search</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Search">Search</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="text" action="&#x2F;form-action" class="form-horizontal" id="text">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::inLanguage' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Original language</label><select name="inLanguage" class="form-control"><option value=""></option>
<option value="en" selected>English</option>
<option value="de">German</option>
<!-- 2 more options, 40d561e7bdfebaecc03f4f65d035b816 -->
<option value="it">Italian</option>
</select></div>',
      'element' => '<select name="inLanguage" class="form-control"><option value=""></option>
<option value="en" selected>English</option>
<option value="de">German</option>
<!-- 2 more options, 40d561e7bdfebaecc03f4f65d035b816 -->
<option value="it">Italian</option>
</select>',
      'label' => '<label for="inLanguage">Original language</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="inLanguage"><option value="en" selected>English</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Original language</label><select name="inLanguage" class="form-control"><option value=""></option>
<option value="en">English</option>
<option value="de">German</option>
<!-- 2 more options, 40d561e7bdfebaecc03f4f65d035b816 -->
<option value="it">Italian</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="inLanguage" class="form-control"><option value=""></option>
<option value="en">English</option>
<option value="de">German</option>
<!-- 2 more options, 40d561e7bdfebaecc03f4f65d035b816 -->
<option value="it">Italian</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="inLanguage"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::isDraft' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isDraft" value="0"><label><input type="checkbox" name="isDraft" value="1" checked> Is draft?</label></div>',
      'element' => '<input type="hidden" name="isDraft" value="0"><label><input type="checkbox" name="isDraft" value="1" checked> Is draft?</label>',
      'label' => '<label for="isDraft">Is draft?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isDraft" value="0"><label><input type="checkbox" name="isDraft" value="1"> Is draft?</label></div>',
      'element' => '<input type="hidden" name="isDraft" value="0"><label><input type="checkbox" name="isDraft" value="1"> Is draft?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::kind' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="kind" class="form-control" value="jk-text">',
      'element' => '<input type="hidden" name="kind" class="form-control" value="jk-text">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="kind" value="jk-text">',
      'hidden' => '<input type="hidden" name="kind" value="jk-text">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="kind" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="kind" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="kind" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="kind" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="kind" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="kind" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>The two given tokens do not match</li></ul>',
      'text' => '<input type="text" name="kind" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="kind" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::markdownText' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Text</label><textarea name="markdownText" data-provide="markdown" data-parser="CommonMark" rows="12" class="form-control"></textarea></div>',
      'element' => '<textarea name="markdownText" data-provide="markdown" data-parser="CommonMark" rows="12" class="form-control"></textarea>',
      'label' => '<label for="markdownText">Text</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Text</label><textarea name="markdownText" data-provide="markdown" data-parser="CommonMark" rows="12" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="markdownText" data-provide="markdown" data-parser="CommonMark" rows="12" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Text</label><textarea name="markdownText" data-provide="markdown" data-parser="CommonMark" rows="12" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="markdownText" data-provide="markdown" data-parser="CommonMark" rows="12" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::tags' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Tags</label><select name="tags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="tags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="tags">Tags</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="tags&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Tags</label><select name="tags&#x5B;&#x5D;" multiple class="form-control"><option value="" selected></option>
</select></div>',
      'element' => '<select name="tags&#x5B;&#x5D;" multiple class="form-control"><option value="" selected></option>
</select>',
      'select_without_options' => '<select name="tags&#x5B;&#x5D;" multiple><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextForm::title' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Title</label><input type="text" name="title" required maxlength="200" class="form-control" value=""></div>',
      'element' => '<input type="text" name="title" required maxlength="200" class="form-control" value="">',
      'label' => '<label for="title">Title</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="title" required maxlength="200" value="">',
      'hidden' => '<input type="hidden" name="title" required maxlength="200" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Title</label><input type="text" name="title" required maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="title" required maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="title" required maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="title" required maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Title</label><input type="text" name="title" required maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="title" required maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="title" required maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="title" required maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextSearchForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="GET" name="search" action="&#x2F;form-action" class="form-horizontal" id="search">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextSearchForm::inLanguage' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage" class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select></div>',
      'element' => '<select name="inLanguage" class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select>',
      'label' => '<label for="inLanguage">Language</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="inLanguage"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage" class="form-control"><option value=""></option>
<option value="aa" selected>Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select></div>',
      'element' => '<select name="inLanguage" class="form-control"><option value=""></option>
<option value="aa" selected>Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select>',
      'select_without_options' => '<select name="inLanguage"><option value="aa" selected>Afar</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Language</label><select name="inLanguage" class="form-control"><option value=""></option>
<option value="aa">Afar</option>
<option value="ab">Abkhazian</option>
<!-- 636 more options, 083a00c990a5d8abaa18a1ca4c275198 -->
<option value="zun">Zuni</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextSearchForm::search' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" required class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value=""></div>',
      'element' => '<input type="text" name="search" required class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="search" required class="input-lg&#x20;search-query" placeholder="Search" size="50" value="">',
      'hidden' => '<input type="hidden" name="search" required class="input-lg&#x20;search-query" placeholder="Search" size="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" required class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="search" required class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="search" required class="input-lg&#x20;search-query" placeholder="Search" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="search" required class="input-lg&#x20;search-query" placeholder="Search" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" required class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="search" required class="input-lg&#x20;search-query&#x20;form-control" placeholder="Search" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="search" required class="input-lg&#x20;search-query" placeholder="Search" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="search" required class="input-lg&#x20;search-query" placeholder="Search" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Books\\Form\\TextSearchForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Search">Search</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Search">Search</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Search">Search</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\DeletePhraseForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="entity_delete" action="&#x2F;form-action" class="form-horizontal" id="entity_delete">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\DeletePhraseForm::cancel' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input type="button" name="cancel" id="submit" data-dismiss="modal" class="form-control" value="Cancel"></div>',
      'element' => '<input type="button" name="cancel" id="submit" data-dismiss="modal" class="form-control" value="Cancel">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'button' => '<button type="button" name="cancel" id="submit" data-dismiss="modal" class="btn&#x20;btn-default" value="Cancel">Cancel</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\DeletePhraseForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\DeletePhraseForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-danger&#x20;btn" value="Delete">Delete</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-danger&#x20;btn" value="Delete">Delete</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-danger&#x20;btn" value="Delete">Delete</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="edit_phrase" action="&#x2F;form-action" class="form-horizontal" id="edit_phrase">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::de_DE' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>German (Germany)</label><textarea name="de_DE" rows="2" class="form-control"></textarea></div>',
      'element' => '<textarea name="de_DE" rows="2" class="form-control"></textarea>',
      'label' => '<label for="de_DE">German (Germany)</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>German (Germany)</label><textarea name="de_DE" rows="2" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="de_DE" rows="2" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>German (Germany)</label><textarea name="de_DE" rows="2" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="de_DE" rows="2" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::de_DEId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="de_DEId" class="form-control" value="">',
      'element' => '<input type="hidden" name="de_DEId" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="de_DEId" value="">',
      'hidden' => '<input type="hidden" name="de_DEId" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="de_DEId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="de_DEId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="de_DEId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="de_DEId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="de_DEId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="de_DEId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="de_DEId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="de_DEId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::en_US' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>English (United States)</label><textarea name="en_US" rows="2" class="form-control"></textarea></div>',
      'element' => '<textarea name="en_US" rows="2" class="form-control"></textarea>',
      'label' => '<label for="en_US">English (United States)</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>English (United States)</label><textarea name="en_US" rows="2" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="en_US" rows="2" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>English (United States)</label><textarea name="en_US" rows="2" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="en_US" rows="2" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::en_USId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="en_USId" class="form-control" value="">',
      'element' => '<input type="hidden" name="en_USId" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="en_USId" value="">',
      'hidden' => '<input type="hidden" name="en_USId" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="en_USId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="en_USId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="en_USId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="en_USId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="en_USId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="en_USId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="en_USId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="en_USId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::es_ES' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Spanish (Spain)</label><textarea name="es_ES" rows="2" class="form-control"></textarea></div>',
      'element' => '<textarea name="es_ES" rows="2" class="form-control"></textarea>',
      'label' => '<label for="es_ES">Spanish (Spain)</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Spanish (Spain)</label><textarea name="es_ES" rows="2" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="es_ES" rows="2" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Spanish (Spain)</label><textarea name="es_ES" rows="2" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="es_ES" rows="2" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::es_ESId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="es_ESId" class="form-control" value="">',
      'element' => '<input type="hidden" name="es_ESId" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="es_ESId" value="">',
      'hidden' => '<input type="hidden" name="es_ESId" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="es_ESId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="es_ESId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="es_ESId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="es_ESId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="es_ESId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="es_ESId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="es_ESId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="es_ESId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::it_IT' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Italian (Italy)</label><textarea name="it_IT" rows="2" class="form-control"></textarea></div>',
      'element' => '<textarea name="it_IT" rows="2" class="form-control"></textarea>',
      'label' => '<label for="it_IT">Italian (Italy)</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Italian (Italy)</label><textarea name="it_IT" rows="2" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="it_IT" rows="2" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Italian (Italy)</label><textarea name="it_IT" rows="2" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="it_IT" rows="2" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::it_ITId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="it_ITId" class="form-control" value="">',
      'element' => '<input type="hidden" name="it_ITId" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="it_ITId" value="">',
      'hidden' => '<input type="hidden" name="it_ITId" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="it_ITId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="it_ITId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="it_ITId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="it_ITId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="it_ITId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="it_ITId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="it_ITId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="it_ITId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::phrase' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phrase</label><textarea name="phrase" rows="2" readonly class="form-control"></textarea></div>',
      'element' => '<textarea name="phrase" rows="2" readonly class="form-control"></textarea>',
      'label' => '<label for="phrase">Phrase</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phrase</label><textarea name="phrase" rows="2" readonly class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="phrase" rows="2" readonly class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Phrase</label><textarea name="phrase" rows="2" readonly class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="phrase" rows="2" readonly class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::phraseId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="phraseId" class="form-control" value="">',
      'element' => '<input type="hidden" name="phraseId" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="phraseId" value="">',
      'hidden' => '<input type="hidden" name="phraseId" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="phraseId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="phraseId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="phraseId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="phraseId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="phraseId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="phraseId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Phrase not found in database</li></ul>',
      'text' => '<input type="text" name="phraseId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="phraseId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::pt_BR' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Portuguese (Brazil)</label><textarea name="pt_BR" rows="2" class="form-control"></textarea></div>',
      'element' => '<textarea name="pt_BR" rows="2" class="form-control"></textarea>',
      'label' => '<label for="pt_BR">Portuguese (Brazil)</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Portuguese (Brazil)</label><textarea name="pt_BR" rows="2" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="pt_BR" rows="2" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Portuguese (Brazil)</label><textarea name="pt_BR" rows="2" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="pt_BR" rows="2" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::pt_BRId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="pt_BRId" class="form-control" value="">',
      'element' => '<input type="hidden" name="pt_BRId" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="pt_BRId" value="">',
      'hidden' => '<input type="hidden" name="pt_BRId" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="pt_BRId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="pt_BRId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="pt_BRId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="pt_BRId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="pt_BRId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="pt_BRId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="pt_BRId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="pt_BRId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JTranslate\\Form\\EditPhraseForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\CreateRoleForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="post" name="role_create" action="&#x2F;form-action" class="form-horizontal" id="role_create">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\CreateRoleForm::isDefault' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isDefault" value="0"><label><input type="checkbox" name="isDefault" value="1"> Automatically give to new users?</label></div>',
      'element' => '<input type="hidden" name="isDefault" value="0"><label><input type="checkbox" name="isDefault" value="1"> Automatically give to new users?</label>',
      'label' => '<label for="isDefault">Automatically give to new users?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isDefault" value="0"><label><input type="checkbox" name="isDefault" value="1" checked> Automatically give to new users?</label></div>',
      'element' => '<input type="hidden" name="isDefault" value="0"><label><input type="checkbox" name="isDefault" value="1" checked> Automatically give to new users?</label>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isDefault" value="0"><label><input type="checkbox" name="isDefault" value="1"> Automatically give to new users?</label></div><ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\CreateRoleForm::name' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Role name</label><input name="name" type="text" size="30" class="form-control" value=""></div>',
      'element' => '<input name="name" type="text" size="30" class="form-control" value="">',
      'label' => '<label for="name">Role name</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input name="name" type="text" size="30" value="">',
      'hidden' => '<input name="name" type="hidden" size="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Role name</label><input name="name" type="text" size="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input name="name" type="text" size="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input name="name" type="text" size="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input name="name" type="hidden" size="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Role name</label><input name="name" type="text" size="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>The input does not match against pattern &#039;/\\A[0-9A-Za-z_]+\\z/&#039;</li></ul></div>',
      'element' => '<input name="name" type="text" size="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>The input does not match against pattern &#039;/\\A[0-9A-Za-z_]+\\z/&#039;</li></ul>',
      'text' => '<input name="name" type="text" size="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input name="name" type="hidden" size="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\CreateRoleForm::parentId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Parent</label><select name="parentId" class="form-control"><option value=""></option>
<option value="1">administrator (child of user)</option>
<option value="41">bib_administrator (child of bib_user)</option>
<!-- 42 more options, b9ae650eff6589f75fea3cf58b8445f8 -->
<option value="43">view_changes</option>
</select></div>',
      'element' => '<select name="parentId" class="form-control"><option value=""></option>
<option value="1">administrator (child of user)</option>
<option value="41">bib_administrator (child of bib_user)</option>
<!-- 42 more options, b9ae650eff6589f75fea3cf58b8445f8 -->
<option value="43">view_changes</option>
</select>',
      'label' => '<label for="parentId">Parent</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="parentId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Parent</label><select name="parentId" class="form-control"><option value=""></option>
<option value="1" selected>administrator (child of user)</option>
<option value="41">bib_administrator (child of bib_user)</option>
<!-- 42 more options, b9ae650eff6589f75fea3cf58b8445f8 -->
<option value="43">view_changes</option>
</select></div>',
      'element' => '<select name="parentId" class="form-control"><option value=""></option>
<option value="1" selected>administrator (child of user)</option>
<option value="41">bib_administrator (child of bib_user)</option>
<!-- 42 more options, b9ae650eff6589f75fea3cf58b8445f8 -->
<option value="43">view_changes</option>
</select>',
      'select_without_options' => '<select name="parentId"><option value="1" selected>administrator (child of user)</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Parent</label><select name="parentId" class="form-control"><option value=""></option>
<option value="1">administrator (child of user)</option>
<option value="41">bib_administrator (child of bib_user)</option>
<!-- 42 more options, b9ae650eff6589f75fea3cf58b8445f8 -->
<option value="43">view_changes</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\CreateRoleForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\CreateRoleForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="Submit"></div>',
      'element' => '<input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="Submit">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input name="submit" class="btn-primary" type="text" id="submit" value="Submit">',
      'hidden' => '<input name="submit" class="btn-primary" type="hidden" id="submit" value="Submit">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input name="submit" class="btn-primary" type="text" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input name="submit" class="btn-primary" type="hidden" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input name="submit" class="btn-primary" type="text" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input name="submit" class="btn-primary" type="hidden" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\DeleteUserForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="delete_user" action="&#x2F;form-action" class="form-horizontal" id="delete_user">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\DeleteUserForm::cancel' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input type="button" name="cancel" id="cancel" data-dismiss="modal" class="form-control" value="Cancel"></div>',
      'element' => '<input type="button" name="cancel" id="cancel" data-dismiss="modal" class="form-control" value="Cancel">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'button' => '<button type="button" name="cancel" id="cancel" data-dismiss="modal" class="btn&#x20;btn-default" value="Cancel">Cancel</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\DeleteUserForm::delete' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="delete" id="submit" class="btn-danger&#x20;btn" value="Delete">Delete</button></div>',
      'element' => '<button type="submit" name="delete" id="submit" class="btn-danger&#x20;btn" value="Delete">Delete</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="delete" id="submit" class="btn-danger&#x20;btn" value="Delete">Delete</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\DeleteUserForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\DeleteUserForm::userId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="userId" class="form-control" value="">',
      'element' => '<input type="hidden" name="userId" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="userId" value="">',
      'hidden' => '<input type="hidden" name="userId" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="userId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="userId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="userId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="userId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="userId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="userId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Assignment not found in database</li></ul>',
      'text' => '<input type="text" name="userId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="userId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="post" name="user_edit" action="&#x2F;form-action" class="form-horizontal" id="user_edit">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::active' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><label><input type="checkbox" name="active" value="1"> Active</label></div>',
      'element' => '<label><input type="checkbox" name="active" value="1"> Active</label>',
      'label' => '<label for="active">Active</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><label><input type="checkbox" name="active" value="1" checked> Active</label></div>',
      'element' => '<label><input type="checkbox" name="active" value="1" checked> Active</label>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><label><input type="checkbox" name="active" value="1"> Active</label></div><ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::displayName' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Display Name</label><input name="displayName" type="text" size="50" class="form-control" value=""></div>',
      'element' => '<input name="displayName" type="text" size="50" class="form-control" value="">',
      'label' => '<label for="displayName">Display Name</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input name="displayName" type="text" size="50" value="">',
      'hidden' => '<input name="displayName" type="hidden" size="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Display Name</label><input name="displayName" type="text" size="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input name="displayName" type="text" size="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input name="displayName" type="text" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input name="displayName" type="hidden" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Display Name</label><input name="displayName" type="text" size="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input name="displayName" type="text" size="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input name="displayName" type="text" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input name="displayName" type="hidden" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::email' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Email</label><input name="email" type="text" size="50" class="form-control" value=""></div>',
      'element' => '<input name="email" type="text" size="50" class="form-control" value="">',
      'label' => '<label for="email">Email</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input name="email" type="text" size="50" value="">',
      'hidden' => '<input name="email" type="hidden" size="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Email</label><input name="email" type="text" size="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input name="email" type="text" size="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input name="email" type="text" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input name="email" type="hidden" size="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Email</label><input name="email" type="text" size="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul></div>',
      'element' => '<input name="email" type="text" size="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul>',
      'text' => '<input name="email" type="text" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input name="email" type="hidden" size="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::emailVerified' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><label><input type="checkbox" name="emailVerified" value="1"> Email verified</label></div>',
      'element' => '<label><input type="checkbox" name="emailVerified" value="1"> Email verified</label>',
      'label' => '<label for="emailVerified">Email verified</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><label><input type="checkbox" name="emailVerified" value="1" checked> Email verified</label></div>',
      'element' => '<label><input type="checkbox" name="emailVerified" value="1" checked> Email verified</label>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><label><input type="checkbox" name="emailVerified" value="1"> Email verified</label></div><ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::isMultiPersonUser' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isMultiPersonUser" value="0"><label><input type="checkbox" name="isMultiPersonUser" value="1"> Multi-person user?</label></div>',
      'element' => '<input type="hidden" name="isMultiPersonUser" value="0"><label><input type="checkbox" name="isMultiPersonUser" value="1"> Multi-person user?</label>',
      'label' => '<label for="isMultiPersonUser">Multi-person user?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isMultiPersonUser" value="0"><label><input type="checkbox" name="isMultiPersonUser" value="1" checked> Multi-person user?</label></div>',
      'element' => '<input type="hidden" name="isMultiPersonUser" value="0"><label><input type="checkbox" name="isMultiPersonUser" value="1" checked> Multi-person user?</label>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isMultiPersonUser" value="0"><label><input type="checkbox" name="isMultiPersonUser" value="1"> Multi-person user?</label></div><ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::personId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Person reference</label><select name="personId" class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select></div>',
      'element' => '<select name="personId" class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'label' => '<label for="personId">Person reference</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="personId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Person reference</label><select name="personId" class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select></div>',
      'element' => '<select name="personId" class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'select_without_options' => '<select name="personId"><option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::rolesList' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Roles</label><select name="rolesList&#x5B;&#x5D;" multiple class="form-control"><option value="1">administrator (child of user)</option>
<option value="41">bib_administrator (child of bib_user)</option>
<option value="40">bib_user</option>
<!-- 41 more options, 3b84770e9a8b9ce6d360bd2bbff47652 -->
<option value="43">view_changes</option>
</select></div>',
      'element' => '<select name="rolesList&#x5B;&#x5D;" multiple class="form-control"><option value="1">administrator (child of user)</option>
<option value="41">bib_administrator (child of bib_user)</option>
<option value="40">bib_user</option>
<!-- 41 more options, 3b84770e9a8b9ce6d360bd2bbff47652 -->
<option value="43">view_changes</option>
</select>',
      'label' => '<label for="rolesList">Roles</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="rolesList&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Roles</label><select name="rolesList&#x5B;&#x5D;" multiple class="form-control"><option value="1" selected>administrator (child of user)</option>
<option value="41">bib_administrator (child of bib_user)</option>
<option value="40">bib_user</option>
<!-- 41 more options, 3b84770e9a8b9ce6d360bd2bbff47652 -->
<option value="43">view_changes</option>
</select></div>',
      'element' => '<select name="rolesList&#x5B;&#x5D;" multiple class="form-control"><option value="1" selected>administrator (child of user)</option>
<option value="41">bib_administrator (child of bib_user)</option>
<option value="40">bib_user</option>
<!-- 41 more options, 3b84770e9a8b9ce6d360bd2bbff47652 -->
<option value="43">view_changes</option>
</select>',
      'select_without_options' => '<select name="rolesList&#x5B;&#x5D;" multiple><option value="1" selected>administrator (child of user)</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="Submit"></div>',
      'element' => '<input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="Submit">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input name="submit" class="btn-primary" type="text" id="submit" value="Submit">',
      'hidden' => '<input name="submit" class="btn-primary" type="hidden" id="submit" value="Submit">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input name="submit" class="btn-primary" type="text" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input name="submit" class="btn-primary" type="hidden" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input name="submit" class="btn-primary" type="text" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input name="submit" class="btn-primary" type="hidden" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::userId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input name="userId" type="hidden" class="form-control" value="">',
      'element' => '<input name="userId" type="hidden" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input name="userId" type="text" value="">',
      'hidden' => '<input name="userId" type="hidden" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input name="userId" type="hidden" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input name="userId" type="hidden" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input name="userId" type="text" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input name="userId" type="hidden" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input name="userId" type="hidden" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input name="userId" type="hidden" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul>',
      'text' => '<input name="userId" type="text" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input name="userId" type="hidden" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\EditUserForm::username' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Username</label><input name="username" type="text" size="30" class="form-control" value=""></div>',
      'element' => '<input name="username" type="text" size="30" class="form-control" value="">',
      'label' => '<label for="username">Username</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input name="username" type="text" size="30" value="">',
      'hidden' => '<input name="username" type="hidden" size="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Username</label><input name="username" type="text" size="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input name="username" type="text" size="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input name="username" type="text" size="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input name="username" type="hidden" size="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Username</label><input name="username" type="text" size="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>The input does not match against pattern &#039;/\\A[0-9A-Za-z-_.]+\\z/&#039;</li></ul></div>',
      'element' => '<input name="username" type="text" size="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>The input does not match against pattern &#039;/\\A[0-9A-Za-z-_.]+\\z/&#039;</li></ul>',
      'text' => '<input name="username" type="text" size="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input name="username" type="hidden" size="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\IssueApiTokenForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="issue_api_token" action="&#x2F;form-action" class="form-horizontal" id="issue_api_token">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\IssueApiTokenForm::issue' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="issue" id="submit" class="btn&#x20;btn-primary" value="Issue&#x20;token">Issue token</button></div>',
      'element' => '<button type="submit" name="issue" id="submit" class="btn&#x20;btn-primary" value="Issue&#x20;token">Issue token</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="issue" id="submit" class="btn&#x20;btn-primary" value="Issue&#x20;token">Issue token</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\IssueApiTokenForm::label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>What is this token for?</label><input type="text" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control&#x20;form-control" value=""></div>',
      'element' => '<input type="text" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control&#x20;form-control" value="">',
      'label' => '<label for="label">What is this token for?</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control" value="">',
      'hidden' => '<input type="hidden" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>What is this token for?</label><input type="text" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control&#x20;form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control&#x20;form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>What is this token for?</label><input type="text" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control&#x20;form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control&#x20;form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="label" maxlength="100" placeholder="e.g.&#x20;nightly&#x20;shrine&#x20;enrichment" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\IssueApiTokenForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\LoginForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="post" name="login" action="&#x2F;form-action" class="form-horizontal" id="login">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\LoginForm::email' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label for="email">Email address</label><input type="email" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" class="form-control" value=""></div>',
      'element' => '<input type="email" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" class="form-control" value="">',
      'label' => '<label for="email">Email address</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" value="">',
      'hidden' => '<input type="hidden" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label for="email">Email address</label><input type="email" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" class="form-control" value="baseline&#x40;example.com"></div>',
      'element' => '<input type="email" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" class="form-control" value="baseline&#x40;example.com">',
      'text' => '<input type="text" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" value="baseline&#x40;example.com">',
      'hidden' => '<input type="hidden" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" value="baseline&#x40;example.com">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label for="email">Email address</label><input type="email" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" class="form-control" value="not-an-email"><ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul></div>',
      'element' => '<input type="email" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" class="form-control" value="not-an-email">',
      'errors' => '<ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul>',
      'text' => '<input type="text" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" value="not-an-email">',
      'hidden' => '<input type="hidden" name="email" id="email" size="40" required autofocus autocomplete="email" placeholder="you&#x40;example.com" value="not-an-email">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\LoginForm::redirect' => 
  array (
    'pristine' => 
    array (
      'row' => '<input name="redirect" type="hidden" class="form-control" value="">',
      'element' => '<input name="redirect" type="hidden" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input name="redirect" type="text" value="">',
      'hidden' => '<input name="redirect" type="hidden" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input name="redirect" type="hidden" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input name="redirect" type="hidden" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input name="redirect" type="text" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input name="redirect" type="hidden" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input name="redirect" type="hidden" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input name="redirect" type="hidden" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input name="redirect" type="text" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input name="redirect" type="hidden" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\LoginForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\LoginForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="Send&#x20;me&#x20;a&#x20;sign-in&#x20;link"></div>',
      'element' => '<input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="Send&#x20;me&#x20;a&#x20;sign-in&#x20;link">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input name="submit" class="btn-primary" type="text" id="submit" value="Send&#x20;me&#x20;a&#x20;sign-in&#x20;link">',
      'hidden' => '<input name="submit" class="btn-primary" type="hidden" id="submit" value="Send&#x20;me&#x20;a&#x20;sign-in&#x20;link">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input name="submit" class="btn-primary" type="text" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input name="submit" class="btn-primary" type="hidden" id="submit" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input name="submit" class="btn-primary&#x20;form-control" type="submit" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input name="submit" class="btn-primary" type="text" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input name="submit" class="btn-primary" type="hidden" id="submit" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\RevokeApiTokenForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="revoke_api_token" action="&#x2F;form-action" class="form-horizontal" id="revoke_api_token">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\RevokeApiTokenForm::revoke' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="revoke" class="btn&#x20;btn-danger&#x20;btn-xs" value="Revoke">Revoke</button></div>',
      'element' => '<button type="submit" name="revoke" class="btn&#x20;btn-danger&#x20;btn-xs" value="Revoke">Revoke</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="revoke" class="btn&#x20;btn-danger&#x20;btn-xs" value="Revoke">Revoke</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'JUser\\Form\\RevokeApiTokenForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="GET" name="advanced-search" action="&#x2F;form-action" class="form-horizontal" id="advanced-search">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::associationCountry' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group  col-md-4"><label>Association country</label><select name="associationCountry" class="form-control"><option value=""></option>
<option value="AR">Argentina</option>
<option value="AU">Australia</option>
<!-- 38 more options, 37702f41b406d4f8ffee6aeb0472848b -->
<option value="UY">Uruguay</option>
</select></div>',
      'element' => '<select name="associationCountry" class="form-control"><option value=""></option>
<option value="AR">Argentina</option>
<option value="AU">Australia</option>
<!-- 38 more options, 37702f41b406d4f8ffee6aeb0472848b -->
<option value="UY">Uruguay</option>
</select>',
      'label' => '<label for="associationCountry">Association country</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="associationCountry"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group  col-md-4"><label>Association country</label><select name="associationCountry" class="form-control"><option value=""></option>
<option value="AR" selected>Argentina</option>
<option value="AU">Australia</option>
<!-- 38 more options, 37702f41b406d4f8ffee6aeb0472848b -->
<option value="UY">Uruguay</option>
</select></div>',
      'element' => '<select name="associationCountry" class="form-control"><option value=""></option>
<option value="AR" selected>Argentina</option>
<option value="AU">Australia</option>
<!-- 38 more options, 37702f41b406d4f8ffee6aeb0472848b -->
<option value="UY">Uruguay</option>
</select>',
      'select_without_options' => '<select name="associationCountry"><option value="AR" selected>Argentina</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group  col-md-4"><label>Association country</label><select name="associationCountry" class="form-control"><option value=""></option>
<option value="AR">Argentina</option>
<option value="AU">Australia</option>
<!-- 38 more options, 37702f41b406d4f8ffee6aeb0472848b -->
<option value="UY">Uruguay</option>
</select><ul class="help-block"><li>The input is more than 2 characters long</li><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input is more than 2 characters long</li><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::associationKind' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group  col-md-4"><label>Association type</label><select name="associationKind" class="form-control"><option value=""></option>
<option value="sch-diocesan-pilgrim-mother">Diocesan Pilgrim Mother organization</option>
<option value="sch-diocesan-pilgrim-movement">Diocesan pilgrim&#039;s movement</option>
<!-- 30 more options, 5248078d3daaae957d843315a45e2cac -->
<option value="sch-young-womens-league-branch">Schoenstatt young women&#039;s branch</option>
</select></div>',
      'element' => '<select name="associationKind" class="form-control"><option value=""></option>
<option value="sch-diocesan-pilgrim-mother">Diocesan Pilgrim Mother organization</option>
<option value="sch-diocesan-pilgrim-movement">Diocesan pilgrim&#039;s movement</option>
<!-- 30 more options, 5248078d3daaae957d843315a45e2cac -->
<option value="sch-young-womens-league-branch">Schoenstatt young women&#039;s branch</option>
</select>',
      'label' => '<label for="associationKind">Association type</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="associationKind"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group  col-md-4"><label>Association type</label><select name="associationKind" class="form-control"><option value=""></option>
<option value="sch-diocesan-pilgrim-mother" selected>Diocesan Pilgrim Mother organization</option>
<option value="sch-diocesan-pilgrim-movement">Diocesan pilgrim&#039;s movement</option>
<!-- 30 more options, 5248078d3daaae957d843315a45e2cac -->
<option value="sch-young-womens-league-branch">Schoenstatt young women&#039;s branch</option>
</select></div>',
      'element' => '<select name="associationKind" class="form-control"><option value=""></option>
<option value="sch-diocesan-pilgrim-mother" selected>Diocesan Pilgrim Mother organization</option>
<option value="sch-diocesan-pilgrim-movement">Diocesan pilgrim&#039;s movement</option>
<!-- 30 more options, 5248078d3daaae957d843315a45e2cac -->
<option value="sch-young-womens-league-branch">Schoenstatt young women&#039;s branch</option>
</select>',
      'select_without_options' => '<select name="associationKind"><option value="sch-diocesan-pilgrim-mother" selected>Diocesan Pilgrim Mother organization</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group  col-md-4"><label>Association type</label><select name="associationKind" class="form-control"><option value=""></option>
<option value="sch-diocesan-pilgrim-mother">Diocesan Pilgrim Mother organization</option>
<option value="sch-diocesan-pilgrim-movement">Diocesan pilgrim&#039;s movement</option>
<!-- 30 more options, 5248078d3daaae957d843315a45e2cac -->
<option value="sch-young-womens-league-branch">Schoenstatt young women&#039;s branch</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::clear' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label for="clear">Clear form</label><input type="button" name="clear" id="clear" class="btn&#x20;btn-default&#x20;form-control" value=""></div>',
      'element' => '<input type="button" name="clear" id="clear" class="btn&#x20;btn-default&#x20;form-control" value="">',
      'label' => '<label for="clear">Clear form</label>',
      'errors' => '',
      'help_block' => '',
      'button' => '<button type="button" name="clear" id="clear" class="btn&#x20;btn-default" value="">Clear form</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::onlyMainRoles' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="onlyMainRoles" value="0"><label><input type="checkbox" name="onlyMainRoles" value="1" checked> Only main roles?</label></div>',
      'element' => '<input type="hidden" name="onlyMainRoles" value="0"><label><input type="checkbox" name="onlyMainRoles" value="1" checked> Only main roles?</label>',
      'label' => '<label for="onlyMainRoles">Only main roles?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="onlyMainRoles" value="0"><label><input type="checkbox" name="onlyMainRoles" value="1"> Only main roles?</label></div>',
      'element' => '<input type="hidden" name="onlyMainRoles" value="0"><label><input type="checkbox" name="onlyMainRoles" value="1"> Only main roles?</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::personName' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group  col-md-4 col-md-offset-4"><label>Person name</label><input type="text" name="personName" class="form-control" value=""></div>',
      'element' => '<input type="text" name="personName" class="form-control" value="">',
      'label' => '<label for="personName">Person name</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="personName" value="">',
      'hidden' => '<input type="hidden" name="personName" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group  col-md-4 col-md-offset-4"><label>Person name</label><input type="text" name="personName" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="personName" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="personName" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="personName" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group  col-md-4 col-md-offset-4"><label>Person name</label><input type="text" name="personName" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="personName" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="personName" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="personName" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::roleTitle' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group  col-md-4"><label for="roleTitleSelect">Role</label><select name="roleTitle&#x5B;&#x5D;" multiple id="roleTitleSelect" class="form-control"><option value=""></option>
<option value="&#x28;General&#x20;or&#x20;main&#x29;&#x20;Secretary">(General or main) Secretary</option>
<option value="Assistant&#x20;moderator">Assistant moderator</option>
<!-- 36 more options, 413e95ff6296aeeb7968cce46d4e72b3 -->
<option value="Women&#x27;s&#x20;Federation">Women&#039;s Federation</option>
</select></div>',
      'element' => '<select name="roleTitle&#x5B;&#x5D;" multiple id="roleTitleSelect" class="form-control"><option value=""></option>
<option value="&#x28;General&#x20;or&#x20;main&#x29;&#x20;Secretary">(General or main) Secretary</option>
<option value="Assistant&#x20;moderator">Assistant moderator</option>
<!-- 36 more options, 413e95ff6296aeeb7968cce46d4e72b3 -->
<option value="Women&#x27;s&#x20;Federation">Women&#039;s Federation</option>
</select>',
      'label' => '<label for="roleTitleSelect">Role</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="roleTitle&#x5B;&#x5D;" multiple id="roleTitleSelect"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group  col-md-4"><label for="roleTitleSelect">Role</label><select name="roleTitle&#x5B;&#x5D;" multiple id="roleTitleSelect" class="form-control"><option value=""></option>
<option value="&#x28;General&#x20;or&#x20;main&#x29;&#x20;Secretary" selected>(General or main) Secretary</option>
<option value="Assistant&#x20;moderator">Assistant moderator</option>
<!-- 36 more options, 413e95ff6296aeeb7968cce46d4e72b3 -->
<option value="Women&#x27;s&#x20;Federation">Women&#039;s Federation</option>
</select></div>',
      'element' => '<select name="roleTitle&#x5B;&#x5D;" multiple id="roleTitleSelect" class="form-control"><option value=""></option>
<option value="&#x28;General&#x20;or&#x20;main&#x29;&#x20;Secretary" selected>(General or main) Secretary</option>
<option value="Assistant&#x20;moderator">Assistant moderator</option>
<!-- 36 more options, 413e95ff6296aeeb7968cce46d4e72b3 -->
<option value="Women&#x27;s&#x20;Federation">Women&#039;s Federation</option>
</select>',
      'select_without_options' => '<select name="roleTitle&#x5B;&#x5D;" multiple id="roleTitleSelect"><option value="&#x28;General&#x20;or&#x20;main&#x29;&#x20;Secretary" selected>(General or main) Secretary</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::search' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group  col-md-4"><label>Multi-search</label><input type="text" name="search" class="form-control" value=""></div>',
      'element' => '<input type="text" name="search" class="form-control" value="">',
      'label' => '<label for="search">Multi-search</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="search" value="">',
      'hidden' => '<input type="hidden" name="search" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group  col-md-4"><label>Multi-search</label><input type="text" name="search" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="search" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="search" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="search" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group  col-md-4"><label>Multi-search</label><input type="text" name="search" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="search" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="search" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="search" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AdvancedSearchForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Search</label><button type="submit" name="submit" class="btn-primary&#x20;btn" value="">Search</button></div>',
      'element' => '<button type="submit" name="submit" class="btn-primary&#x20;btn" value="">Search</button>',
      'label' => '<label for="submit">Search</label>',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" class="btn-primary&#x20;btn" value="">Search</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="create_assignment" action="&#x2F;form-action" class="form-horizontal" id="create_assignment">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::associationId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Association</label><select name="associationId" required class="form-control"><option value=""></option>
<option value="569">Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select></div>',
      'element' => '<select name="associationId" required class="form-control"><option value=""></option>
<option value="569">Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select>',
      'label' => '<label for="associationId">Association</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="associationId" required></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Association</label><select name="associationId" required class="form-control"><option value=""></option>
<option value="569" selected>Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select></div>',
      'element' => '<select name="associationId" required class="form-control"><option value=""></option>
<option value="569" selected>Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select>',
      'select_without_options' => '<select name="associationId" required><option value="569" selected>Austin Caritas</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Association</label><select name="associationId" required class="form-control"><option value=""></option>
<option value="569">Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select><ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul></div>',
      'errors' => '<ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::delete' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input type="button" name="delete" class="btn-danger&#x20;form-control" data-toggle="modal" data-target=".bs-example-modal-sm" value="Delete"></div>',
      'element' => '<input type="button" name="delete" class="btn-danger&#x20;form-control" data-toggle="modal" data-target=".bs-example-modal-sm" value="Delete">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'button' => '<button type="button" name="delete" class="btn-danger&#x20;btn" data-toggle="modal" data-target=".bs-example-modal-sm" value="Delete">Delete</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::endDate' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>End date</label><input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value=""></div>',
      'element' => '<input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value="">',
      'label' => '<label for="endDate">End date</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="endDate" value="">',
      'hidden' => '<input type="hidden" name="endDate" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>End date</label><input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02"></div>',
      'element' => '<input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02">',
      'text' => '<input type="text" name="endDate" value="2020-01-02">',
      'hidden' => '<input type="hidden" name="endDate" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>End date</label><input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value="not-a-date"><ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul></div>',
      'element' => '<input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value="not-a-date">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul>',
      'text' => '<input type="text" name="endDate" value="not-a-date">',
      'hidden' => '<input type="hidden" name="endDate" value="not-a-date">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::endDatePrecision' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the end date is known</label><select name="endDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select></div>',
      'element' => '<select name="endDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'label' => '<label for="endDatePrecision">How precisely the end date is known</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="endDatePrecision"><option value="day" selected>Exact day</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the end date is known</label><select name="endDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="endDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="endDatePrecision"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::personId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Person</label><select name="personId" required class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select></div>',
      'element' => '<select name="personId" required class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'label' => '<label for="personId">Person</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="personId" required></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Person</label><select name="personId" required class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select></div>',
      'element' => '<select name="personId" required class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'select_without_options' => '<select name="personId" required><option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Person</label><select name="personId" required class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select><ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul></div>',
      'errors' => '<ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::roleId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Role</label><select name="roleId" required class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="roleId" required class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="roleId">Role</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="roleId" required></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Role</label><select name="roleId" required class="form-control"><option value="" selected></option>
<option value="1462">Coordinator</option>
<option value="1463">Moderator</option>
<option value="1464">Member</option>
</select></div>',
      'element' => '<select name="roleId" required class="form-control"><option value="" selected></option>
<option value="1462">Coordinator</option>
<option value="1463">Moderator</option>
<option value="1464">Member</option>
</select>',
      'select_without_options' => '<select name="roleId" required><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Role</label><select name="roleId" required class="form-control"><option value=""></option>
<option value="1462">Coordinator</option>
<option value="1463">Moderator</option>
<option value="1464">Member</option>
</select><ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul></div>',
      'element' => '<select name="roleId" required class="form-control"><option value=""></option>
<option value="1462">Coordinator</option>
<option value="1463">Moderator</option>
<option value="1464">Member</option>
</select>',
      'errors' => '<ul class="help-block"><li>Value is required and can&#039;t be empty</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::startDate' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Start date</label><input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value=""></div>',
      'element' => '<input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value="">',
      'label' => '<label for="startDate">Start date</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="startDate" value="">',
      'hidden' => '<input type="hidden" name="startDate" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Start date</label><input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02"></div>',
      'element' => '<input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02">',
      'text' => '<input type="text" name="startDate" value="2020-01-02">',
      'hidden' => '<input type="hidden" name="startDate" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Start date</label><input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value="not-a-date"><ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul></div>',
      'element' => '<input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value="not-a-date">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul>',
      'text' => '<input type="text" name="startDate" value="not-a-date">',
      'hidden' => '<input type="hidden" name="startDate" value="not-a-date">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::startDatePrecision' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the start date is known</label><select name="startDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select></div>',
      'element' => '<select name="startDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'label' => '<label for="startDatePrecision">How precisely the start date is known</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="startDatePrecision"><option value="day" selected>Exact day</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the start date is known</label><select name="startDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="startDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="startDatePrecision"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssignmentForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="edit_association" action="&#x2F;form-action" class="form-horizontal" id="edit_association">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::adminNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control"></textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control"></textarea>',
      'label' => '<label for="adminNotes">Admin notes</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::associationId' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="associationId" class="form-control" value="">',
      'element' => '<input type="hidden" name="associationId" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="associationId" value="">',
      'hidden' => '<input type="hidden" name="associationId" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="associationId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="associationId" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="associationId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="associationId" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="associationId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="associationId" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="associationId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="associationId" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::cityState' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>City/State</label><input type="text" name="cityState" class="form-control" value=""></div>',
      'element' => '<input type="text" name="cityState" class="form-control" value="">',
      'label' => '<label for="cityState">City/State</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="cityState" value="">',
      'hidden' => '<input type="hidden" name="cityState" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>City/State</label><input type="text" name="cityState" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="cityState" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="cityState" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="cityState" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>City/State</label><input type="text" name="cityState" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="cityState" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="cityState" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="cityState" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::country' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Country (if not international)</label><select name="country" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select></div>',
      'element' => '<select name="country" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select>',
      'label' => '<label for="country">Country (if not international)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="country"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Country (if not international)</label><select name="country" class="form-control"><option value=""></option>
<option value="AF" selected>Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select></div>',
      'element' => '<select name="country" class="form-control"><option value=""></option>
<option value="AF" selected>Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select>',
      'select_without_options' => '<select name="country"><option value="AF" selected>Afghanistan</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Country (if not international)</label><select name="country" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select><ul class="help-block"><li>The input is more than 6 characters long</li><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input is more than 6 characters long</li><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::email' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Main Email</label><input type="email" name="email" maxlength="70" class="form-control" value=""></div>',
      'element' => '<input type="email" name="email" maxlength="70" class="form-control" value="">',
      'label' => '<label for="email">Main Email</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="email" maxlength="70" value="">',
      'hidden' => '<input type="hidden" name="email" maxlength="70" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Main Email</label><input type="email" name="email" maxlength="70" class="form-control" value="baseline&#x40;example.com"></div>',
      'element' => '<input type="email" name="email" maxlength="70" class="form-control" value="baseline&#x40;example.com">',
      'text' => '<input type="text" name="email" maxlength="70" value="baseline&#x40;example.com">',
      'hidden' => '<input type="hidden" name="email" maxlength="70" value="baseline&#x40;example.com">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Main Email</label><input type="email" name="email" maxlength="70" class="form-control" value="not-an-email"><ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul></div>',
      'element' => '<input type="email" name="email" maxlength="70" class="form-control" value="not-an-email">',
      'errors' => '<ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul>',
      'text' => '<input type="text" name="email" maxlength="70" value="not-an-email">',
      'hidden' => '<input type="hidden" name="email" maxlength="70" value="not-an-email">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::eventsHuman' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Mass, adoration and reconciliation schedules (free text)</label><textarea name="eventsHuman" placeholder="Covenant&#x20;mass&#x20;every&#x20;3rd&#x20;Sunday,&#x20;daily&#x20;mass&#x20;every&#x20;Wednesday&#x20;at&#x20;7am,&#x20;except&#x20;in&#x20;June&#x20;and&#x20;July.&#x20;Confessions&#x20;will&#x20;be&#x20;offered&#x20;30&#x20;minutes&#x20;before&#x20;every&#x20;mass.&#x20;Youth&#x20;adoration&#x20;every&#x20;1st&#x20;and&#x20;3rd&#x20;Friday&#x20;while&#x20;school&#x20;is&#x20;in&#x20;session.&#x20;Please&#x20;verify&#x20;on&#x20;the&#x20;Facebook&#x20;page." class="form-control"></textarea><p class="help-block">Please add all details available, including exceptions to the general rule. 
(Summer/winter schedules, months without mass, etc.). If you found the schedule on a webpage, include the link 
so users can double-check. Warning: this field is not translated.</p></div>',
      'element' => '<textarea name="eventsHuman" placeholder="Covenant&#x20;mass&#x20;every&#x20;3rd&#x20;Sunday,&#x20;daily&#x20;mass&#x20;every&#x20;Wednesday&#x20;at&#x20;7am,&#x20;except&#x20;in&#x20;June&#x20;and&#x20;July.&#x20;Confessions&#x20;will&#x20;be&#x20;offered&#x20;30&#x20;minutes&#x20;before&#x20;every&#x20;mass.&#x20;Youth&#x20;adoration&#x20;every&#x20;1st&#x20;and&#x20;3rd&#x20;Friday&#x20;while&#x20;school&#x20;is&#x20;in&#x20;session.&#x20;Please&#x20;verify&#x20;on&#x20;the&#x20;Facebook&#x20;page." class="form-control"></textarea>',
      'label' => '<label for="eventsHuman">Mass, adoration and reconciliation schedules (free text)</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Please add all details available, including exceptions to the general rule. 
(Summer/winter schedules, months without mass, etc.). If you found the schedule on a webpage, include the link 
so users can double-check. Warning: this field is not translated.</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Mass, adoration and reconciliation schedules (free text)</label><textarea name="eventsHuman" placeholder="Covenant&#x20;mass&#x20;every&#x20;3rd&#x20;Sunday,&#x20;daily&#x20;mass&#x20;every&#x20;Wednesday&#x20;at&#x20;7am,&#x20;except&#x20;in&#x20;June&#x20;and&#x20;July.&#x20;Confessions&#x20;will&#x20;be&#x20;offered&#x20;30&#x20;minutes&#x20;before&#x20;every&#x20;mass.&#x20;Youth&#x20;adoration&#x20;every&#x20;1st&#x20;and&#x20;3rd&#x20;Friday&#x20;while&#x20;school&#x20;is&#x20;in&#x20;session.&#x20;Please&#x20;verify&#x20;on&#x20;the&#x20;Facebook&#x20;page." class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">Please add all details available, including exceptions to the general rule. 
(Summer/winter schedules, months without mass, etc.). If you found the schedule on a webpage, include the link 
so users can double-check. Warning: this field is not translated.</p></div>',
      'element' => '<textarea name="eventsHuman" placeholder="Covenant&#x20;mass&#x20;every&#x20;3rd&#x20;Sunday,&#x20;daily&#x20;mass&#x20;every&#x20;Wednesday&#x20;at&#x20;7am,&#x20;except&#x20;in&#x20;June&#x20;and&#x20;July.&#x20;Confessions&#x20;will&#x20;be&#x20;offered&#x20;30&#x20;minutes&#x20;before&#x20;every&#x20;mass.&#x20;Youth&#x20;adoration&#x20;every&#x20;1st&#x20;and&#x20;3rd&#x20;Friday&#x20;while&#x20;school&#x20;is&#x20;in&#x20;session.&#x20;Please&#x20;verify&#x20;on&#x20;the&#x20;Facebook&#x20;page." class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Mass, adoration and reconciliation schedules (free text)</label><textarea name="eventsHuman" placeholder="Covenant&#x20;mass&#x20;every&#x20;3rd&#x20;Sunday,&#x20;daily&#x20;mass&#x20;every&#x20;Wednesday&#x20;at&#x20;7am,&#x20;except&#x20;in&#x20;June&#x20;and&#x20;July.&#x20;Confessions&#x20;will&#x20;be&#x20;offered&#x20;30&#x20;minutes&#x20;before&#x20;every&#x20;mass.&#x20;Youth&#x20;adoration&#x20;every&#x20;1st&#x20;and&#x20;3rd&#x20;Friday&#x20;while&#x20;school&#x20;is&#x20;in&#x20;session.&#x20;Please&#x20;verify&#x20;on&#x20;the&#x20;Facebook&#x20;page." class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><p class="help-block">Please add all details available, including exceptions to the general rule. 
(Summer/winter schedules, months without mass, etc.). If you found the schedule on a webpage, include the link 
so users can double-check. Warning: this field is not translated.</p></div>',
      'element' => '<textarea name="eventsHuman" placeholder="Covenant&#x20;mass&#x20;every&#x20;3rd&#x20;Sunday,&#x20;daily&#x20;mass&#x20;every&#x20;Wednesday&#x20;at&#x20;7am,&#x20;except&#x20;in&#x20;June&#x20;and&#x20;July.&#x20;Confessions&#x20;will&#x20;be&#x20;offered&#x20;30&#x20;minutes&#x20;before&#x20;every&#x20;mass.&#x20;Youth&#x20;adoration&#x20;every&#x20;1st&#x20;and&#x20;3rd&#x20;Friday&#x20;while&#x20;school&#x20;is&#x20;in&#x20;session.&#x20;Please&#x20;verify&#x20;on&#x20;the&#x20;Facebook&#x20;page." class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::facebookUrl' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Facebook URL</label><input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="">',
      'label' => '<label for="facebookUrl">Facebook URL</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Facebook URL</label><input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Facebook URL</label><input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::foundationDate' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Foundation date</label><input type="date" name="foundationDate" min="1900-01-01" step="any" class="form-control" value=""></div>',
      'element' => '<input type="date" name="foundationDate" min="1900-01-01" step="any" class="form-control" value="">',
      'label' => '<label for="foundationDate">Foundation date</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="foundationDate" value="">',
      'hidden' => '<input type="hidden" name="foundationDate" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Foundation date</label><input type="date" name="foundationDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02"></div>',
      'element' => '<input type="date" name="foundationDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02">',
      'text' => '<input type="text" name="foundationDate" value="2020-01-02">',
      'hidden' => '<input type="hidden" name="foundationDate" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Foundation date</label><input type="date" name="foundationDate" min="1900-01-01" step="any" class="form-control" value="not-a-date"><ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul></div>',
      'element' => '<input type="date" name="foundationDate" min="1900-01-01" step="any" class="form-control" value="not-a-date">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul>',
      'text' => '<input type="text" name="foundationDate" value="not-a-date">',
      'hidden' => '<input type="hidden" name="foundationDate" value="not-a-date">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::foundationDatePrecision' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the foundation date is known</label><select name="foundationDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select></div>',
      'element' => '<select name="foundationDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'label' => '<label for="foundationDatePrecision">How precisely the foundation date is known</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="foundationDatePrecision"><option value="day" selected>Exact day</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the foundation date is known</label><select name="foundationDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="foundationDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="foundationDatePrecision"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::geoPoint' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Gps location (lat, long)</label><input type="text" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" class="form-control" value=""></div>',
      'element' => '<input type="text" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" class="form-control" value="">',
      'label' => '<label for="geoPoint">Gps location (lat, long)</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" value="">',
      'hidden' => '<input type="hidden" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Gps location (lat, long)</label><input type="text" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Gps location (lat, long)</label><input type="text" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>&lt;script&gt;alert(1)&lt;/script&gt; did not provided a complete Coordinate</li></ul></div>',
      'element' => '<input type="text" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>&lt;script&gt;alert(1)&lt;/script&gt; did not provided a complete Coordinate</li></ul>',
      'text' => '<input type="text" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="geoPoint" placeholder="ex.&#x20;30.311108,&#x20;-97.842738" maxlength="32" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::googlePlaceId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Google place ID</label><input type="text" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" class="form-control" value=""><p class="help-block">Used for linking to the association\'s Google Place, for example, for reviews. Use the <a href="https://developers.google.com/places/place-id">Place ID finder.</a></p></div>',
      'element' => '<input type="text" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" class="form-control" value="">',
      'label' => '<label for="googlePlaceId">Google place ID</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Used for linking to the association&#039;s Google Place, for example, for reviews. Use the &lt;a href=&quot;https://developers.google.com/places/place-id&quot;&gt;Place ID finder.&lt;/a&gt;</p>',
      'text' => '<input type="text" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" value="">',
      'hidden' => '<input type="hidden" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Google place ID</label><input type="text" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">Used for linking to the association\'s Google Place, for example, for reviews. Use the <a href="https://developers.google.com/places/place-id">Place ID finder.</a></p></div>',
      'element' => '<input type="text" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Google place ID</label><input type="text" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">Used for linking to the association\'s Google Place, for example, for reviews. Use the <a href="https://developers.google.com/places/place-id">Place ID finder.</a></p></div>',
      'element' => '<input type="text" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="googlePlaceId" placeholder="ChIJj61dQgK6j4AR4GeTYWZsKWw" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::instagramUser' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Instagram user</label><input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value=""></div>',
      'element' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value="">',
      'label' => '<label for="instagramUser">Instagram user</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="">',
      'hidden' => '<input type="hidden" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Instagram user</label><input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Instagram user</label><input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>Instagram user names should begin with a letter, contain only letters, numbers, &#039;.&#039;, or &#039;_&#039; and be between 1 and 30 characters long.</li></ul></div>',
      'element' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Instagram user names should begin with a letter, contain only letters, numbers, &#039;.&#039;, or &#039;_&#039; and be between 1 and 30 characters long.</li></ul>',
      'text' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::internalName' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Name within Schoenstatt</label><input type="text" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" class="form-control" value=""><p class="help-block">This is the name that will be shown to most users of the page (supposing most users are Schoenstatters). This field has no name formats as the public name field does. If the internal name would be the same as the public name, please leave blank.</p></div>',
      'element' => '<input type="text" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" class="form-control" value="">',
      'label' => '<label for="internalName">Name within Schoenstatt</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">This is the name that will be shown to most users of the page (supposing most users are Schoenstatters). This field has no name formats as the public name field does. If the internal name would be the same as the public name, please leave blank.</p>',
      'text' => '<input type="text" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" value="">',
      'hidden' => '<input type="hidden" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Name within Schoenstatt</label><input type="text" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">This is the name that will be shown to most users of the page (supposing most users are Schoenstatters). This field has no name formats as the public name field does. If the internal name would be the same as the public name, please leave blank.</p></div>',
      'element' => '<input type="text" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Name within Schoenstatt</label><input type="text" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">This is the name that will be shown to most users of the page (supposing most users are Schoenstatters). This field has no name formats as the public name field does. If the internal name would be the same as the public name, please leave blank.</p></div>',
      'element' => '<input type="text" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="internalName" placeholder="ex.&#x20;Exile&#x20;Shrine" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::isActive' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" data-toggle="collapse" data-target="&#x23;canonicalSuppressionGroup" aria-expanded="false" aria-controls="canoicalSuppressionGroup" value="1" checked> Active</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" data-toggle="collapse" data-target="&#x23;canonicalSuppressionGroup" aria-expanded="false" aria-controls="canoicalSuppressionGroup" value="1" checked> Active</label>',
      'label' => '<label for="isActive">Active</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" data-toggle="collapse" data-target="&#x23;canonicalSuppressionGroup" aria-expanded="false" aria-controls="canoicalSuppressionGroup" value="1"> Active</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" data-toggle="collapse" data-target="&#x23;canonicalSuppressionGroup" aria-expanded="false" aria-controls="canoicalSuppressionGroup" value="1"> Active</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::isAuthor' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isAuthor" value="0"><label><input type="checkbox" name="isAuthor" value="1"> Has association authored books?</label></div>',
      'element' => '<input type="hidden" name="isAuthor" value="0"><label><input type="checkbox" name="isAuthor" value="1"> Has association authored books?</label>',
      'label' => '<label for="isAuthor">Has association authored books?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isAuthor" value="0"><label><input type="checkbox" name="isAuthor" value="1" checked> Has association authored books?</label></div>',
      'element' => '<input type="hidden" name="isAuthor" value="0"><label><input type="checkbox" name="isAuthor" value="1" checked> Has association authored books?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::isInternalNameTranslateable' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isInternalNameTranslateable" value="0"><label><input type="checkbox" name="isInternalNameTranslateable" value="1"> Should the internal name be translated?</label></div>',
      'element' => '<input type="hidden" name="isInternalNameTranslateable" value="0"><label><input type="checkbox" name="isInternalNameTranslateable" value="1"> Should the internal name be translated?</label>',
      'label' => '<label for="isInternalNameTranslateable">Should the internal name be translated?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isInternalNameTranslateable" value="0"><label><input type="checkbox" name="isInternalNameTranslateable" value="1" checked> Should the internal name be translated?</label></div>',
      'element' => '<input type="hidden" name="isInternalNameTranslateable" value="0"><label><input type="checkbox" name="isInternalNameTranslateable" value="1" checked> Should the internal name be translated?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::isLifeCommunity' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isLifeCommunity" value="0"><label><input type="checkbox" name="isLifeCommunity" value="1"> Has lifetime membership?</label></div>',
      'element' => '<input type="hidden" name="isLifeCommunity" value="0"><label><input type="checkbox" name="isLifeCommunity" value="1"> Has lifetime membership?</label>',
      'label' => '<label for="isLifeCommunity">Has lifetime membership?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isLifeCommunity" value="0"><label><input type="checkbox" name="isLifeCommunity" value="1" checked> Has lifetime membership?</label></div>',
      'element' => '<input type="hidden" name="isLifeCommunity" value="0"><label><input type="checkbox" name="isLifeCommunity" value="1" checked> Has lifetime membership?</label>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isLifeCommunity" value="0"><label><input type="checkbox" name="isLifeCommunity" value="1"> Has lifetime membership?</label></div><ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::isNameTranslateable' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isNameTranslateable" value="0"><label><input type="checkbox" name="isNameTranslateable" value="1"> Should the name be translated?</label></div>',
      'element' => '<input type="hidden" name="isNameTranslateable" value="0"><label><input type="checkbox" name="isNameTranslateable" value="1"> Should the name be translated?</label>',
      'label' => '<label for="isNameTranslateable">Should the name be translated?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isNameTranslateable" value="0"><label><input type="checkbox" name="isNameTranslateable" value="1" checked> Should the name be translated?</label></div>',
      'element' => '<input type="hidden" name="isNameTranslateable" value="0"><label><input type="checkbox" name="isNameTranslateable" value="1" checked> Should the name be translated?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::kind' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Association type</label><select name="kind" required class="form-control"><option value="sch-diocesan-pilgrim-mother">Diocesan Pilgrim Mother organization</option>
<option value="sch-diocesan-pilgrim-movement">Diocesan pilgrim&#039;s movement</option>
<option value="sch-federation-international-structure">Federation international structure</option>
<!-- 29 more options, f1e489d202c8c249f46bde9dc09c8308 -->
<option value="sch-young-womens-league-branch">Schoenstatt young women&#039;s branch</option>
</select></div>',
      'element' => '<select name="kind" required class="form-control"><option value="sch-diocesan-pilgrim-mother">Diocesan Pilgrim Mother organization</option>
<option value="sch-diocesan-pilgrim-movement">Diocesan pilgrim&#039;s movement</option>
<option value="sch-federation-international-structure">Federation international structure</option>
<!-- 29 more options, f1e489d202c8c249f46bde9dc09c8308 -->
<option value="sch-young-womens-league-branch">Schoenstatt young women&#039;s branch</option>
</select>',
      'label' => '<label for="kind">Association type</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="kind" required></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Association type</label><select name="kind" required class="form-control"><option value="sch-diocesan-pilgrim-mother" selected>Diocesan Pilgrim Mother organization</option>
<option value="sch-diocesan-pilgrim-movement">Diocesan pilgrim&#039;s movement</option>
<option value="sch-federation-international-structure">Federation international structure</option>
<!-- 29 more options, f1e489d202c8c249f46bde9dc09c8308 -->
<option value="sch-young-womens-league-branch">Schoenstatt young women&#039;s branch</option>
</select></div>',
      'element' => '<select name="kind" required class="form-control"><option value="sch-diocesan-pilgrim-mother" selected>Diocesan Pilgrim Mother organization</option>
<option value="sch-diocesan-pilgrim-movement">Diocesan pilgrim&#039;s movement</option>
<option value="sch-federation-international-structure">Federation international structure</option>
<!-- 29 more options, f1e489d202c8c249f46bde9dc09c8308 -->
<option value="sch-young-womens-league-branch">Schoenstatt young women&#039;s branch</option>
</select>',
      'select_without_options' => '<select name="kind" required><option value="sch-diocesan-pilgrim-mother" selected>Diocesan Pilgrim Mother organization</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Association type</label><select name="kind" required class="form-control"><option value="sch-diocesan-pilgrim-mother">Diocesan Pilgrim Mother organization</option>
<option value="sch-diocesan-pilgrim-movement">Diocesan pilgrim&#039;s movement</option>
<option value="sch-federation-international-structure">Federation international structure</option>
<!-- 29 more options, f1e489d202c8c249f46bde9dc09c8308 -->
<option value="sch-young-womens-league-branch">Schoenstatt young women&#039;s branch</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::name' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Name for the public</label><input type="text" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" class="form-control" value=""><p class="help-block">This is the name that would be published in Google Maps (if applicable). Several association types include `name formats` that insert this field within a commonly used format, for example `Schoenstatt Shrine [name]`. This simplifies mass translation, but can be overridden below.
For Schoenstatt Shrine names, please use the name of the closest city to which the shrine would be associated (for example, Tucumán), or in the case of a little known city, add the State/Province separated by a comma  (for example, Sleepy Eye, Minnesota). If there are multiple shrines in the same city, make sure to disambiguate one from the other. Try to keep names as short as possible, but avoid abbreviations. Longer names can be used for the `Name within Schoenstatt`.</p></div>',
      'element' => '<input type="text" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" class="form-control" value="">',
      'label' => '<label for="name">Name for the public</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">This is the name that would be published in Google Maps (if applicable). Several association types include `name formats` that insert this field within a commonly used format, for example `Schoenstatt Shrine [name]`. This simplifies mass translation, but can be overridden below.
For Schoenstatt Shrine names, please use the name of the closest city to which the shrine would be associated (for example, Tucumán), or in the case of a little known city, add the State/Province separated by a comma  (for example, Sleepy Eye, Minnesota). If there are multiple shrines in the same city, make sure to disambiguate one from the other. Try to keep names as short as possible, but avoid abbreviations. Longer names can be used for the `Name within Schoenstatt`.</p>',
      'text' => '<input type="text" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" value="">',
      'hidden' => '<input type="hidden" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Name for the public</label><input type="text" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"><p class="help-block">This is the name that would be published in Google Maps (if applicable). Several association types include `name formats` that insert this field within a commonly used format, for example `Schoenstatt Shrine [name]`. This simplifies mass translation, but can be overridden below.
For Schoenstatt Shrine names, please use the name of the closest city to which the shrine would be associated (for example, Tucumán), or in the case of a little known city, add the State/Province separated by a comma  (for example, Sleepy Eye, Minnesota). If there are multiple shrines in the same city, make sure to disambiguate one from the other. Try to keep names as short as possible, but avoid abbreviations. Longer names can be used for the `Name within Schoenstatt`.</p></div>',
      'element' => '<input type="text" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Name for the public</label><input type="text" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><p class="help-block">This is the name that would be published in Google Maps (if applicable). Several association types include `name formats` that insert this field within a commonly used format, for example `Schoenstatt Shrine [name]`. This simplifies mass translation, but can be overridden below.
For Schoenstatt Shrine names, please use the name of the closest city to which the shrine would be associated (for example, Tucumán), or in the case of a little known city, add the State/Province separated by a comma  (for example, Sleepy Eye, Minnesota). If there are multiple shrines in the same city, make sure to disambiguate one from the other. Try to keep names as short as possible, but avoid abbreviations. Longer names can be used for the `Name within Schoenstatt`.</p></div>',
      'element' => '<input type="text" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="name" required placeholder="ex.&#x20;Schoenstatt&#x20;Fathers" maxlength="200" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::openingHoursHuman' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Opening hours (free text)</label><textarea name="openingHoursHuman" placeholder="Sun-Sat&#x20;9-11am,&#x20;12-8pm,&#x20;except&#x20;first&#x20;Tuesdays&#x20;of&#x20;the&#x20;month" class="form-control"></textarea><p class="help-block">Be as descriptive as possible, including closing days throughout the year! 
There&#039;s nothing worse for a pilgrim than finding a closed door.</p></div>',
      'element' => '<textarea name="openingHoursHuman" placeholder="Sun-Sat&#x20;9-11am,&#x20;12-8pm,&#x20;except&#x20;first&#x20;Tuesdays&#x20;of&#x20;the&#x20;month" class="form-control"></textarea>',
      'label' => '<label for="openingHoursHuman">Opening hours (free text)</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Be as descriptive as possible, including closing days throughout the year! 
There&#039;s nothing worse for a pilgrim than finding a closed door.</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Opening hours (free text)</label><textarea name="openingHoursHuman" placeholder="Sun-Sat&#x20;9-11am,&#x20;12-8pm,&#x20;except&#x20;first&#x20;Tuesdays&#x20;of&#x20;the&#x20;month" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">Be as descriptive as possible, including closing days throughout the year! 
There&#039;s nothing worse for a pilgrim than finding a closed door.</p></div>',
      'element' => '<textarea name="openingHoursHuman" placeholder="Sun-Sat&#x20;9-11am,&#x20;12-8pm,&#x20;except&#x20;first&#x20;Tuesdays&#x20;of&#x20;the&#x20;month" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Opening hours (free text)</label><textarea name="openingHoursHuman" placeholder="Sun-Sat&#x20;9-11am,&#x20;12-8pm,&#x20;except&#x20;first&#x20;Tuesdays&#x20;of&#x20;the&#x20;month" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><p class="help-block">Be as descriptive as possible, including closing days throughout the year! 
There&#039;s nothing worse for a pilgrim than finding a closed door.</p></div>',
      'element' => '<textarea name="openingHoursHuman" placeholder="Sun-Sat&#x20;9-11am,&#x20;12-8pm,&#x20;except&#x20;first&#x20;Tuesdays&#x20;of&#x20;the&#x20;month" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::openingHoursSpecificationJson' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Opening hours specification JSON (advanced users)</label><textarea name="openingHoursSpecificationJson" rows="8" placeholder="&#x7B;&#x0A;&#x20;&#x20;&quot;friday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;saturday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;sunday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;exceptions&quot;&#x3A;&#x20;&#x7B;&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-11-11&quot;&#x3A;&#x20;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-12-25&quot;&#x3A;&#x20;&#x5B;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;01-01&quot;&#x3A;&#x20;&#x5B;&#x5D;&#x0A;&#x7D;&#x7D;" class="form-control"></textarea><p class="help-block">Please see OpeningHours::create([...]);
at <a href="https://github.com/spatie/opening-hours" target="_blank">spatie/opening-hours</a></br>
</p></div>',
      'element' => '<textarea name="openingHoursSpecificationJson" rows="8" placeholder="&#x7B;&#x0A;&#x20;&#x20;&quot;friday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;saturday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;sunday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;exceptions&quot;&#x3A;&#x20;&#x7B;&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-11-11&quot;&#x3A;&#x20;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-12-25&quot;&#x3A;&#x20;&#x5B;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;01-01&quot;&#x3A;&#x20;&#x5B;&#x5D;&#x0A;&#x7D;&#x7D;" class="form-control"></textarea>',
      'label' => '<label for="openingHoursSpecificationJson">Opening hours specification JSON (advanced users)</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Please see OpeningHours::create([...]);
at &lt;a href=&quot;https://github.com/spatie/opening-hours&quot; target=&quot;_blank&quot;&gt;spatie/opening-hours&lt;/a&gt;&lt;/br&gt;
</p>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Opening hours specification JSON (advanced users)</label><textarea name="openingHoursSpecificationJson" rows="8" placeholder="&#x7B;&#x0A;&#x20;&#x20;&quot;friday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;saturday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;sunday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;exceptions&quot;&#x3A;&#x20;&#x7B;&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-11-11&quot;&#x3A;&#x20;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-12-25&quot;&#x3A;&#x20;&#x5B;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;01-01&quot;&#x3A;&#x20;&#x5B;&#x5D;&#x0A;&#x7D;&#x7D;" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea><p class="help-block">Please see OpeningHours::create([...]);
at <a href="https://github.com/spatie/opening-hours" target="_blank">spatie/opening-hours</a></br>
</p></div>',
      'element' => '<textarea name="openingHoursSpecificationJson" rows="8" placeholder="&#x7B;&#x0A;&#x20;&#x20;&quot;friday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;saturday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;sunday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;exceptions&quot;&#x3A;&#x20;&#x7B;&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-11-11&quot;&#x3A;&#x20;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-12-25&quot;&#x3A;&#x20;&#x5B;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;01-01&quot;&#x3A;&#x20;&#x5B;&#x5D;&#x0A;&#x7D;&#x7D;" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Opening hours specification JSON (advanced users)</label><textarea name="openingHoursSpecificationJson" rows="8" placeholder="&#x7B;&#x0A;&#x20;&#x20;&quot;friday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;saturday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;sunday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;exceptions&quot;&#x3A;&#x20;&#x7B;&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-11-11&quot;&#x3A;&#x20;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-12-25&quot;&#x3A;&#x20;&#x5B;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;01-01&quot;&#x3A;&#x20;&#x5B;&#x5D;&#x0A;&#x7D;&#x7D;" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea><ul class="help-block"><li>The input could not be parsed as valid JSON</li></ul><p class="help-block">Please see OpeningHours::create([...]);
at <a href="https://github.com/spatie/opening-hours" target="_blank">spatie/opening-hours</a></br>
</p></div>',
      'element' => '<textarea name="openingHoursSpecificationJson" rows="8" placeholder="&#x7B;&#x0A;&#x20;&#x20;&quot;friday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;saturday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;sunday&quot;&#x3A;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;,&quot;13&#x3A;00-18&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&quot;exceptions&quot;&#x3A;&#x20;&#x7B;&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-11-11&quot;&#x3A;&#x20;&#x5B;&quot;09&#x3A;00-12&#x3A;00&quot;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;2016-12-25&quot;&#x3A;&#x20;&#x5B;&#x5D;,&#x0A;&#x20;&#x20;&#x20;&#x20;&quot;01-01&quot;&#x3A;&#x20;&#x5B;&#x5D;&#x0A;&#x7D;&#x7D;" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
      'errors' => '<ul class="help-block"><li>The input could not be parsed as valid JSON</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::overrideNameFormat' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="overrideNameFormat" value="0"><label><input type="checkbox" name="overrideNameFormat" value="1"> Override name format?</label></div>',
      'element' => '<input type="hidden" name="overrideNameFormat" value="0"><label><input type="checkbox" name="overrideNameFormat" value="1"> Override name format?</label>',
      'label' => '<label for="overrideNameFormat">Override name format?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="overrideNameFormat" value="0"><label><input type="checkbox" name="overrideNameFormat" value="1" checked> Override name format?</label></div>',
      'element' => '<input type="hidden" name="overrideNameFormat" value="0"><label><input type="checkbox" name="overrideNameFormat" value="1" checked> Override name format?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::parentId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Parent organization (for sorting purposes)</label><select name="parentId" class="form-control"><option value=""></option>
<option value="569">Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select></div>',
      'element' => '<select name="parentId" class="form-control"><option value=""></option>
<option value="569">Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select>',
      'label' => '<label for="parentId">Parent organization (for sorting purposes)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="parentId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Parent organization (for sorting purposes)</label><select name="parentId" class="form-control"><option value=""></option>
<option value="569" selected>Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select></div>',
      'element' => '<select name="parentId" class="form-control"><option value=""></option>
<option value="569" selected>Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select>',
      'select_without_options' => '<select name="parentId"><option value="569" selected>Austin Caritas</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::phone1' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 1</label><input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value=""><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="">',
      'label' => '<label for="phone1">Phone 1</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p>',
      'text' => '<input type="text" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
      'hidden' => '<input type="hidden" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 1</label><input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100"><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'text' => '<input type="text" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'hidden' => '<input type="hidden" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Phone 1</label><input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number"><ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number">',
      'errors' => '<ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul>',
      'text' => '<input type="text" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
      'hidden' => '<input type="hidden" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::phone1Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 1 label (optional)</label><select name="phone1Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select></div>',
      'element' => '<select name="phone1Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select>',
      'label' => '<label for="phone1Label">Phone 1 label (optional)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="phone1Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 1 label (optional)</label><select name="phone1Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select></div>',
      'element' => '<select name="phone1Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select>',
      'select_without_options' => '<select name="phone1Label"><option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Phone 1 label (optional)</label><select name="phone1Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 5 more options, 620a5102bba0c6c1f6893aa9de1fc9e0 -->
<option value="no-such-option" selected>no-such-option</option>
</select></div>',
      'element' => '<select name="phone1Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 5 more options, 620a5102bba0c6c1f6893aa9de1fc9e0 -->
<option value="no-such-option" selected>no-such-option</option>
</select>',
      'select_without_options' => '<select name="phone1Label"><option value="no-such-option" selected>no-such-option</option>
</select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::phone2' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 2</label><input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value=""><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="">',
      'label' => '<label for="phone2">Phone 2</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p>',
      'text' => '<input type="text" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
      'hidden' => '<input type="hidden" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 2</label><input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100"><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'text' => '<input type="text" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'hidden' => '<input type="hidden" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Phone 2</label><input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number"><ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number">',
      'errors' => '<ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul>',
      'text' => '<input type="text" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
      'hidden' => '<input type="hidden" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::phone2Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 2 label (optional)</label><select name="phone2Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select></div>',
      'element' => '<select name="phone2Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select>',
      'label' => '<label for="phone2Label">Phone 2 label (optional)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="phone2Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 2 label (optional)</label><select name="phone2Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select></div>',
      'element' => '<select name="phone2Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select>',
      'select_without_options' => '<select name="phone2Label"><option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Phone 2 label (optional)</label><select name="phone2Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 5 more options, 620a5102bba0c6c1f6893aa9de1fc9e0 -->
<option value="no-such-option" selected>no-such-option</option>
</select></div>',
      'element' => '<select name="phone2Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 5 more options, 620a5102bba0c6c1f6893aa9de1fc9e0 -->
<option value="no-such-option" selected>no-such-option</option>
</select>',
      'select_without_options' => '<select name="phone2Label"><option value="no-such-option" selected>no-such-option</option>
</select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::phone3' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 3</label><input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value=""><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="">',
      'label' => '<label for="phone3">Phone 3</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p>',
      'text' => '<input type="text" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
      'hidden' => '<input type="hidden" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 3</label><input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100"><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'text' => '<input type="text" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'hidden' => '<input type="hidden" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Phone 3</label><input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number"><ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number">',
      'errors' => '<ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul>',
      'text' => '<input type="text" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
      'hidden' => '<input type="hidden" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::phone3Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 3 label (optional)</label><select name="phone3Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select></div>',
      'element' => '<select name="phone3Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select>',
      'label' => '<label for="phone3Label">Phone 3 label (optional)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="phone3Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 3 label (optional)</label><select name="phone3Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select></div>',
      'element' => '<select name="phone3Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 4 more options, 5dc9f1f35e3a35dbd9a08f7422af32ec -->
<option value="Fax">Fax</option>
</select>',
      'select_without_options' => '<select name="phone3Label"><option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Phone 3 label (optional)</label><select name="phone3Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 5 more options, 620a5102bba0c6c1f6893aa9de1fc9e0 -->
<option value="no-such-option" selected>no-such-option</option>
</select></div>',
      'element' => '<select name="phone3Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 5 more options, 620a5102bba0c6c1f6893aa9de1fc9e0 -->
<option value="no-such-option" selected>no-such-option</option>
</select>',
      'select_without_options' => '<select name="phone3Label"><option value="no-such-option" selected>no-such-option</option>
</select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::publicNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control"></textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control"></textarea>',
      'label' => '<label for="publicNotes">Description</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Description</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="4" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::street1' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Street line 1</label><input type="text" name="street1" class="form-control" value=""></div>',
      'element' => '<input type="text" name="street1" class="form-control" value="">',
      'label' => '<label for="street1">Street line 1</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="street1" value="">',
      'hidden' => '<input type="hidden" name="street1" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Street line 1</label><input type="text" name="street1" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="street1" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="street1" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="street1" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Street line 1</label><input type="text" name="street1" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="street1" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="street1" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="street1" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::street2' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Street line 2</label><input type="text" name="street2" class="form-control" value=""></div>',
      'element' => '<input type="text" name="street2" class="form-control" value="">',
      'label' => '<label for="street2">Street line 2</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="street2" value="">',
      'hidden' => '<input type="hidden" name="street2" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Street line 2</label><input type="text" name="street2" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="street2" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="street2" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="street2" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Street line 2</label><input type="text" name="street2" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="street2" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="street2" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="street2" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::timeZoneId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Time zone</label><select name="timeZoneId" class="form-control"><option value=""></option>
<option value="Pacific&#x2F;Midway">(GMT-11:00) Midway Island</option>
<option value="Pacific&#x2F;Pago_Pago">(GMT-11:00) American Samoa</option>
<!-- 130 more options, 844d4cf5f077eecedf36f707bef8fab7 -->
<option value="Pacific&#x2F;Apia">(GMT+13:00) Samoa</option>
</select></div>',
      'element' => '<select name="timeZoneId" class="form-control"><option value=""></option>
<option value="Pacific&#x2F;Midway">(GMT-11:00) Midway Island</option>
<option value="Pacific&#x2F;Pago_Pago">(GMT-11:00) American Samoa</option>
<!-- 130 more options, 844d4cf5f077eecedf36f707bef8fab7 -->
<option value="Pacific&#x2F;Apia">(GMT+13:00) Samoa</option>
</select>',
      'label' => '<label for="timeZoneId">Time zone</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="timeZoneId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Time zone</label><select name="timeZoneId" class="form-control"><option value=""></option>
<option value="Pacific&#x2F;Midway" selected>(GMT-11:00) Midway Island</option>
<option value="Pacific&#x2F;Pago_Pago">(GMT-11:00) American Samoa</option>
<!-- 130 more options, 844d4cf5f077eecedf36f707bef8fab7 -->
<option value="Pacific&#x2F;Apia">(GMT+13:00) Samoa</option>
</select></div>',
      'element' => '<select name="timeZoneId" class="form-control"><option value=""></option>
<option value="Pacific&#x2F;Midway" selected>(GMT-11:00) Midway Island</option>
<option value="Pacific&#x2F;Pago_Pago">(GMT-11:00) American Samoa</option>
<!-- 130 more options, 844d4cf5f077eecedf36f707bef8fab7 -->
<option value="Pacific&#x2F;Apia">(GMT+13:00) Samoa</option>
</select>',
      'select_without_options' => '<select name="timeZoneId"><option value="Pacific&#x2F;Midway" selected>(GMT-11:00) Midway Island</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Time zone</label><select name="timeZoneId" class="form-control"><option value=""></option>
<option value="Pacific&#x2F;Midway">(GMT-11:00) Midway Island</option>
<option value="Pacific&#x2F;Pago_Pago">(GMT-11:00) American Samoa</option>
<!-- 130 more options, 844d4cf5f077eecedf36f707bef8fab7 -->
<option value="Pacific&#x2F;Apia">(GMT+13:00) Samoa</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::twitterUser' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Twitter user</label><input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value=""></div>',
      'element' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value="">',
      'label' => '<label for="twitterUser">Twitter user</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="">',
      'hidden' => '<input type="hidden" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Twitter user</label><input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Twitter user</label><input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>Twitter user names should contain only letters, numbers, or &#039;_&#039; and be between 1 and 15 characters long.</li></ul></div>',
      'element' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Twitter user names should contain only letters, numbers, or &#039;_&#039; and be between 1 and 15 characters long.</li></ul>',
      'text' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::url1' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="">',
      'label' => '<label for="url1">Other URL 1</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::url1Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 1 Label</label><select name="url1Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url1Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'label' => '<label for="url1Label">Other URL 1 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url1Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 1 Label</label><select name="url1Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url1Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'select_without_options' => '<select name="url1Label"><option value="Blog" selected>Blog</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::url2' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="">',
      'label' => '<label for="url2">Other URL 2</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::url2Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 2 Label</label><select name="url2Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url2Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'label' => '<label for="url2Label">Other URL 2 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url2Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 2 Label</label><select name="url2Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url2Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'select_without_options' => '<select name="url2Label"><option value="Blog" selected>Blog</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::url3' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="">',
      'label' => '<label for="url3">Other URL 3</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::url3Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 3 Label</label><select name="url3Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url3Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'label' => '<label for="url3Label">Other URL 3 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url3Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 3 Label</label><select name="url3Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url3Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<!-- 3 more options, 1097b31b5bd24c369de9ddce90f362bb -->
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'select_without_options' => '<select name="url3Label"><option value="Blog" selected>Blog</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\AssociationForm::zip' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Zip/PLZ</label><input type="text" name="zip" class="form-control" value=""></div>',
      'element' => '<input type="text" name="zip" class="form-control" value="">',
      'label' => '<label for="zip">Zip/PLZ</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="zip" value="">',
      'hidden' => '<input type="hidden" name="zip" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Zip/PLZ</label><input type="text" name="zip" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="zip" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="zip" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="zip" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Zip/PLZ</label><input type="text" name="zip" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="zip" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="zip" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="zip" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="edit_assignment" action="&#x2F;form-action" class="form-horizontal" id="edit_assignment">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::associationId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Association</label><select name="associationId" required disabled class="form-control"><option value=""></option>
<option value="569">Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select></div>',
      'element' => '<select name="associationId" required disabled class="form-control"><option value=""></option>
<option value="569">Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select>',
      'label' => '<label for="associationId">Association</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="associationId" required disabled></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Association</label><select name="associationId" required disabled class="form-control"><option value=""></option>
<option value="569" selected>Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select></div>',
      'element' => '<select name="associationId" required disabled class="form-control"><option value=""></option>
<option value="569" selected>Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select>',
      'select_without_options' => '<select name="associationId" required disabled><option value="569" selected>Austin Caritas</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::delete' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input type="button" name="delete" class="btn-danger&#x20;form-control" data-toggle="modal" data-target=".bs-example-modal-sm" value="Delete"></div>',
      'element' => '<input type="button" name="delete" class="btn-danger&#x20;form-control" data-toggle="modal" data-target=".bs-example-modal-sm" value="Delete">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'button' => '<button type="button" name="delete" class="btn-danger&#x20;btn" data-toggle="modal" data-target=".bs-example-modal-sm" value="Delete">Delete</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::endDate' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>End date</label><input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value=""></div>',
      'element' => '<input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value="">',
      'label' => '<label for="endDate">End date</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="endDate" value="">',
      'hidden' => '<input type="hidden" name="endDate" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>End date</label><input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02"></div>',
      'element' => '<input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02">',
      'text' => '<input type="text" name="endDate" value="2020-01-02">',
      'hidden' => '<input type="hidden" name="endDate" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>End date</label><input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value="not-a-date"><ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul></div>',
      'element' => '<input type="date" name="endDate" min="1900-01-01" step="any" class="form-control" value="not-a-date">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul>',
      'text' => '<input type="text" name="endDate" value="not-a-date">',
      'hidden' => '<input type="hidden" name="endDate" value="not-a-date">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::endDatePrecision' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the end date is known</label><select name="endDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select></div>',
      'element' => '<select name="endDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'label' => '<label for="endDatePrecision">How precisely the end date is known</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="endDatePrecision"><option value="day" selected>Exact day</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the end date is known</label><select name="endDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="endDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="endDatePrecision"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::personId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Person</label><select name="personId" required disabled class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select></div>',
      'element' => '<select name="personId" required disabled class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'label' => '<label for="personId">Person</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="personId" required disabled></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Person</label><select name="personId" required disabled class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select></div>',
      'element' => '<select name="personId" required disabled class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'select_without_options' => '<select name="personId" required disabled><option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::roleId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Role</label><select name="roleId" required disabled class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="roleId" required disabled class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="roleId">Role</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="roleId" required disabled></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Role</label><select name="roleId" required disabled class="form-control"><option value="" selected></option>
<option value="1462">Coordinator</option>
<option value="1463">Moderator</option>
<option value="1464">Member</option>
</select></div>',
      'element' => '<select name="roleId" required disabled class="form-control"><option value="" selected></option>
<option value="1462">Coordinator</option>
<option value="1463">Moderator</option>
<option value="1464">Member</option>
</select>',
      'select_without_options' => '<select name="roleId" required disabled><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Role</label><select name="roleId" required disabled class="form-control"><option value=""></option>
<option value="1462">Coordinator</option>
<option value="1463">Moderator</option>
<option value="1464">Member</option>
</select></div>',
      'element' => '<select name="roleId" required disabled class="form-control"><option value=""></option>
<option value="1462">Coordinator</option>
<option value="1463">Moderator</option>
<option value="1464">Member</option>
</select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::startDate' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Start date</label><input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value=""></div>',
      'element' => '<input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value="">',
      'label' => '<label for="startDate">Start date</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="startDate" value="">',
      'hidden' => '<input type="hidden" name="startDate" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Start date</label><input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02"></div>',
      'element' => '<input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02">',
      'text' => '<input type="text" name="startDate" value="2020-01-02">',
      'hidden' => '<input type="hidden" name="startDate" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Start date</label><input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value="not-a-date"><ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul></div>',
      'element' => '<input type="date" name="startDate" min="1900-01-01" step="any" class="form-control" value="not-a-date">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul>',
      'text' => '<input type="text" name="startDate" value="not-a-date">',
      'hidden' => '<input type="hidden" name="startDate" value="not-a-date">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::startDatePrecision' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the start date is known</label><select name="startDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select></div>',
      'element' => '<select name="startDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'label' => '<label for="startDatePrecision">How precisely the start date is known</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="startDatePrecision"><option value="day" selected>Exact day</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the start date is known</label><select name="startDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="startDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="startDatePrecision"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\EditAssignmentForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\ImportFatherForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="import_person" action="&#x2F;form-action" class="form-horizontal" id="import_person">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\ImportFatherForm::personId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Person to import</label><select name="personId" class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="personId" class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="personId">Person to import</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="personId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Person to import</label><select name="personId" class="form-control"><option value="" selected></option>
</select></div>',
      'element' => '<select name="personId" class="form-control"><option value="" selected></option>
</select>',
      'select_without_options' => '<select name="personId"><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\ImportFatherForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\ImportFatherForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Import">Import</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Import">Import</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Import">Import</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="edit_person" action="&#x2F;form-action" class="form-horizontal" id="edit_person">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::adminNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea>',
      'label' => '<label for="adminNotes">Admin notes</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Admin notes</label><textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="adminNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::adminTags' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Admin tags</label><select name="adminTags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
</select></div>',
      'element' => '<select name="adminTags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
</select>',
      'label' => '<label for="adminTags">Admin tags</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="adminTags&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Admin tags</label><select name="adminTags&#x5B;&#x5D;" multiple class="form-control"><option value="" selected></option>
</select></div>',
      'element' => '<select name="adminTags&#x5B;&#x5D;" multiple class="form-control"><option value="" selected></option>
</select>',
      'select_without_options' => '<select name="adminTags&#x5B;&#x5D;" multiple><option value="" selected></option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::automaticTitle' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="automaticTitle" value="0"><label><input type="checkbox" name="automaticTitle" data-toggle="collapse" data-target="&#x23;titleGroup" aria-expanded="false" aria-controls="titleGroup" value="1" checked> Automatically generate title</label></div>',
      'element' => '<input type="hidden" name="automaticTitle" value="0"><label><input type="checkbox" name="automaticTitle" data-toggle="collapse" data-target="&#x23;titleGroup" aria-expanded="false" aria-controls="titleGroup" value="1" checked> Automatically generate title</label>',
      'label' => '<label for="automaticTitle">Automatically generate title</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="automaticTitle" value="0"><label><input type="checkbox" name="automaticTitle" data-toggle="collapse" data-target="&#x23;titleGroup" aria-expanded="false" aria-controls="titleGroup" value="1"> Automatically generate title</label></div><ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'element' => '<input type="hidden" name="automaticTitle" value="0"><label><input type="checkbox" name="automaticTitle" data-toggle="collapse" data-target="&#x23;titleGroup" aria-expanded="false" aria-controls="titleGroup" value="1"> Automatically generate title</label>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::birthDate' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Birth date</label><input type="date" name="birthDate" min="1900-01-01" step="any" class="form-control" value=""></div>',
      'element' => '<input type="date" name="birthDate" min="1900-01-01" step="any" class="form-control" value="">',
      'label' => '<label for="birthDate">Birth date</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="birthDate" value="">',
      'hidden' => '<input type="hidden" name="birthDate" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Birth date</label><input type="date" name="birthDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02"></div>',
      'element' => '<input type="date" name="birthDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02">',
      'text' => '<input type="text" name="birthDate" value="2020-01-02">',
      'hidden' => '<input type="hidden" name="birthDate" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Birth date</label><input type="date" name="birthDate" min="1900-01-01" step="any" class="form-control" value="not-a-date"><ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul></div>',
      'element' => '<input type="date" name="birthDate" min="1900-01-01" step="any" class="form-control" value="not-a-date">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul>',
      'text' => '<input type="text" name="birthDate" value="not-a-date">',
      'hidden' => '<input type="hidden" name="birthDate" value="not-a-date">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::birthDatePrecision' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the birth date is known</label><select name="birthDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select></div>',
      'element' => '<select name="birthDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'label' => '<label for="birthDatePrecision">How precisely the birth date is known</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="birthDatePrecision"><option value="day" selected>Exact day</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the birth date is known</label><select name="birthDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="birthDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="birthDatePrecision"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::bishopDate' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Bishop ordination date</label><input type="date" name="bishopDate" min="1900-01-01" step="any" class="form-control" value=""></div>',
      'element' => '<input type="date" name="bishopDate" min="1900-01-01" step="any" class="form-control" value="">',
      'label' => '<label for="bishopDate">Bishop ordination date</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="bishopDate" value="">',
      'hidden' => '<input type="hidden" name="bishopDate" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Bishop ordination date</label><input type="date" name="bishopDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02"></div>',
      'element' => '<input type="date" name="bishopDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02">',
      'text' => '<input type="text" name="bishopDate" value="2020-01-02">',
      'hidden' => '<input type="hidden" name="bishopDate" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Bishop ordination date</label><input type="date" name="bishopDate" min="1900-01-01" step="any" class="form-control" value="not-a-date"><ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul></div>',
      'element' => '<input type="date" name="bishopDate" min="1900-01-01" step="any" class="form-control" value="not-a-date">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul>',
      'text' => '<input type="text" name="bishopDate" value="not-a-date">',
      'hidden' => '<input type="hidden" name="bishopDate" value="not-a-date">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::bishopDatePrecision' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the episcopal ordination date is known</label><select name="bishopDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select></div>',
      'element' => '<select name="bishopDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'label' => '<label for="bishopDatePrecision">How precisely the episcopal ordination date is known</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="bishopDatePrecision"><option value="day" selected>Exact day</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the episcopal ordination date is known</label><select name="bishopDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="bishopDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="bishopDatePrecision"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::cellPhone' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label for="cellPhone">Cell phone (main number)</label><input type="tel" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" class="form-control" value=""><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" class="form-control" value="">',
      'label' => '<label for="cellPhone">Cell phone (main number)</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p>',
      'text' => '<input type="text" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" value="">',
      'hidden' => '<input type="hidden" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label for="cellPhone">Cell phone (main number)</label><input type="tel" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100"><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'text' => '<input type="text" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'hidden' => '<input type="hidden" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label for="cellPhone">Cell phone (main number)</label><input type="tel" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number"><ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number">',
      'errors' => '<ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul>',
      'text' => '<input type="text" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
      'hidden' => '<input type="hidden" name="cellPhone" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" id="cellPhone" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::cellPhoneHasWhatsApp' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="cellPhoneHasWhatsApp" value="0"><label><input type="checkbox" name="cellPhoneHasWhatsApp" value="1"> Cell phone has WhatsApp?</label></div>',
      'element' => '<input type="hidden" name="cellPhoneHasWhatsApp" value="0"><label><input type="checkbox" name="cellPhoneHasWhatsApp" value="1"> Cell phone has WhatsApp?</label>',
      'label' => '<label for="cellPhoneHasWhatsApp">Cell phone has WhatsApp?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="cellPhoneHasWhatsApp" value="0"><label><input type="checkbox" name="cellPhoneHasWhatsApp" value="1" checked> Cell phone has WhatsApp?</label></div>',
      'element' => '<input type="hidden" name="cellPhoneHasWhatsApp" value="0"><label><input type="checkbox" name="cellPhoneHasWhatsApp" value="1" checked> Cell phone has WhatsApp?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::contactNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Contact detail notes</label><textarea name="contactNotes" class="form-control"></textarea></div>',
      'element' => '<textarea name="contactNotes" class="form-control"></textarea>',
      'label' => '<label for="contactNotes">Contact detail notes</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Contact detail notes</label><textarea name="contactNotes" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="contactNotes" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Contact detail notes</label><textarea name="contactNotes" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="contactNotes" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::country' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Home country</label><select name="country" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select></div>',
      'element' => '<select name="country" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select>',
      'label' => '<label for="country">Home country</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="country"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Home country</label><select name="country" class="form-control"><option value=""></option>
<option value="AF" selected>Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select></div>',
      'element' => '<select name="country" class="form-control"><option value=""></option>
<option value="AF" selected>Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select>',
      'select_without_options' => '<select name="country"><option value="AF" selected>Afghanistan</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Home country</label><select name="country" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select><ul class="help-block"><li>The input is more than 2 characters long</li><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input is more than 2 characters long</li><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::deathDate' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Death date</label><input type="date" name="deathDate" min="1900-01-01" step="any" class="form-control" value=""></div>',
      'element' => '<input type="date" name="deathDate" min="1900-01-01" step="any" class="form-control" value="">',
      'label' => '<label for="deathDate">Death date</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="deathDate" value="">',
      'hidden' => '<input type="hidden" name="deathDate" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Death date</label><input type="date" name="deathDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02"></div>',
      'element' => '<input type="date" name="deathDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02">',
      'text' => '<input type="text" name="deathDate" value="2020-01-02">',
      'hidden' => '<input type="hidden" name="deathDate" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Death date</label><input type="date" name="deathDate" min="1900-01-01" step="any" class="form-control" value="not-a-date"><ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul></div>',
      'element' => '<input type="date" name="deathDate" min="1900-01-01" step="any" class="form-control" value="not-a-date">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul>',
      'text' => '<input type="text" name="deathDate" value="not-a-date">',
      'hidden' => '<input type="hidden" name="deathDate" value="not-a-date">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::deathDatePrecision' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the death date is known</label><select name="deathDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select></div>',
      'element' => '<select name="deathDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'label' => '<label for="deathDatePrecision">How precisely the death date is known</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="deathDatePrecision"><option value="day" selected>Exact day</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the death date is known</label><select name="deathDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="deathDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="deathDatePrecision"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::delete' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input type="button" name="delete" class="btn-danger&#x20;form-control" data-toggle="modal" data-target=".bs-example-modal-sm" value="Delete"></div>',
      'element' => '<input type="button" name="delete" class="btn-danger&#x20;form-control" data-toggle="modal" data-target=".bs-example-modal-sm" value="Delete">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'button' => '<button type="button" name="delete" class="btn-danger&#x20;btn" data-toggle="modal" data-target=".bs-example-modal-sm" value="Delete">Delete</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::email' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Main Email</label><input type="email" name="email" maxlength="70" class="form-control" value=""></div>',
      'element' => '<input type="email" name="email" maxlength="70" class="form-control" value="">',
      'label' => '<label for="email">Main Email</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="email" maxlength="70" value="">',
      'hidden' => '<input type="hidden" name="email" maxlength="70" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Main Email</label><input type="email" name="email" maxlength="70" class="form-control" value="baseline&#x40;example.com"></div>',
      'element' => '<input type="email" name="email" maxlength="70" class="form-control" value="baseline&#x40;example.com">',
      'text' => '<input type="text" name="email" maxlength="70" value="baseline&#x40;example.com">',
      'hidden' => '<input type="hidden" name="email" maxlength="70" value="baseline&#x40;example.com">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Main Email</label><input type="email" name="email" maxlength="70" class="form-control" value="not-an-email"><ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul></div>',
      'element' => '<input type="email" name="email" maxlength="70" class="form-control" value="not-an-email">',
      'errors' => '<ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul>',
      'text' => '<input type="text" name="email" maxlength="70" value="not-an-email">',
      'hidden' => '<input type="hidden" name="email" maxlength="70" value="not-an-email">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::email2' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Alternative Email</label><input type="email" name="email2" maxlength="70" class="form-control" value=""></div>',
      'element' => '<input type="email" name="email2" maxlength="70" class="form-control" value="">',
      'label' => '<label for="email2">Alternative Email</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="email2" maxlength="70" value="">',
      'hidden' => '<input type="hidden" name="email2" maxlength="70" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Alternative Email</label><input type="email" name="email2" maxlength="70" class="form-control" value="baseline&#x40;example.com"></div>',
      'element' => '<input type="email" name="email2" maxlength="70" class="form-control" value="baseline&#x40;example.com">',
      'text' => '<input type="text" name="email2" maxlength="70" value="baseline&#x40;example.com">',
      'hidden' => '<input type="hidden" name="email2" maxlength="70" value="baseline&#x40;example.com">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Alternative Email</label><input type="email" name="email2" maxlength="70" class="form-control" value="not-an-email"><ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul></div>',
      'element' => '<input type="email" name="email2" maxlength="70" class="form-control" value="not-an-email">',
      'errors' => '<ul class="help-block"><li>The input does not match against pattern &#039;/^[a-zA-Z0-9.!#$%&amp;&#039;*+\\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\\.[a-zA-Z0-9-]+)*$/&#039;</li><li>The input is not a valid email address. Use the basic format local-part@hostname</li></ul>',
      'text' => '<input type="text" name="email2" maxlength="70" value="not-an-email">',
      'hidden' => '<input type="hidden" name="email2" maxlength="70" value="not-an-email">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::facebookUrl' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Facebook URL</label><input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="">',
      'label' => '<label for="facebookUrl">Facebook URL</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Facebook URL</label><input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Facebook URL</label><input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="facebookUrl" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::firstName' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>First name</label><input type="text" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" class="form-control" value=""></div>',
      'element' => '<input type="text" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" class="form-control" value="">',
      'label' => '<label for="firstName">First name</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>First name</label><input type="text" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>First name</label><input type="text" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="firstName" placeholder="ex.&#x20;John&#x20;Andrew" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::instagramUser' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Instagram user</label><input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value=""></div>',
      'element' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value="">',
      'label' => '<label for="instagramUser">Instagram user</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="">',
      'hidden' => '<input type="hidden" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Instagram user</label><input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Instagram user</label><input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>Instagram user names should begin with a letter, contain only letters, numbers, &#039;.&#039;, or &#039;_&#039; and be between 1 and 30 characters long.</li></ul></div>',
      'element' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Instagram user names should begin with a letter, contain only letters, numbers, &#039;.&#039;, or &#039;_&#039; and be between 1 and 30 characters long.</li></ul>',
      'text' => '<input type="text" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="instagramUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="30" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::isAuthor' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isAuthor" value="0"><label><input type="checkbox" name="isAuthor" value="1"> Is author?</label></div>',
      'element' => '<input type="hidden" name="isAuthor" value="0"><label><input type="checkbox" name="isAuthor" value="1"> Is author?</label>',
      'label' => '<label for="isAuthor">Is author?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isAuthor" value="0"><label><input type="checkbox" name="isAuthor" value="1" checked> Is author?</label></div>',
      'element' => '<input type="hidden" name="isAuthor" value="0"><label><input type="checkbox" name="isAuthor" value="1" checked> Is author?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::isBorrower' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isBorrower" value="0"><label><input type="checkbox" name="isBorrower" value="1"> Is library user?</label></div>',
      'element' => '<input type="hidden" name="isBorrower" value="0"><label><input type="checkbox" name="isBorrower" value="1"> Is library user?</label>',
      'label' => '<label for="isBorrower">Is library user?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isBorrower" value="0"><label><input type="checkbox" name="isBorrower" value="1" checked> Is library user?</label></div>',
      'element' => '<input type="hidden" name="isBorrower" value="0"><label><input type="checkbox" name="isBorrower" value="1" checked> Is library user?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::lastName' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Last name</label><input type="text" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" class="form-control" value=""></div>',
      'element' => '<input type="text" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" class="form-control" value="">',
      'label' => '<label for="lastName">Last name</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Last name</label><input type="text" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Last name</label><input type="text" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="lastName" placeholder="ex.&#x20;Smith&#x20;Johnson" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::lifeCommunity' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Life community</label><select name="lifeCommunity" class="form-control"><option value=""></option>
<option value="81">Schoenstatt Diocesan Priests Federation</option>
<option value="80">Schoenstatt Family Federation</option>
<!-- 8 more options, b2dafad1717f2653327fffe0aa033fe8 -->
<option value="1">Secular Institute of Schoenstatt Fathers</option>
</select></div>',
      'element' => '<select name="lifeCommunity" class="form-control"><option value=""></option>
<option value="81">Schoenstatt Diocesan Priests Federation</option>
<option value="80">Schoenstatt Family Federation</option>
<!-- 8 more options, b2dafad1717f2653327fffe0aa033fe8 -->
<option value="1">Secular Institute of Schoenstatt Fathers</option>
</select>',
      'label' => '<label for="lifeCommunity">Life community</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="lifeCommunity"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Life community</label><select name="lifeCommunity" class="form-control"><option value=""></option>
<option value="81" selected>Schoenstatt Diocesan Priests Federation</option>
<option value="80">Schoenstatt Family Federation</option>
<!-- 8 more options, b2dafad1717f2653327fffe0aa033fe8 -->
<option value="1">Secular Institute of Schoenstatt Fathers</option>
</select></div>',
      'element' => '<select name="lifeCommunity" class="form-control"><option value=""></option>
<option value="81" selected>Schoenstatt Diocesan Priests Federation</option>
<option value="80">Schoenstatt Family Federation</option>
<!-- 8 more options, b2dafad1717f2653327fffe0aa033fe8 -->
<option value="1">Secular Institute of Schoenstatt Fathers</option>
</select>',
      'select_without_options' => '<select name="lifeCommunity"><option value="81" selected>Schoenstatt Diocesan Priests Federation</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Life community</label><select name="lifeCommunity" class="form-control"><option value=""></option>
<option value="81">Schoenstatt Diocesan Priests Federation</option>
<option value="80">Schoenstatt Family Federation</option>
<!-- 8 more options, b2dafad1717f2653327fffe0aa033fe8 -->
<option value="1">Secular Institute of Schoenstatt Fathers</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::manualTitle' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Manual title</label><input type="text" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" class="form-control" value=""></div>',
      'element' => '<input type="text" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" class="form-control" value="">',
      'label' => '<label for="manualTitle">Manual title</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Manual title</label><input type="text" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Manual title</label><input type="text" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="manualTitle" placeholder="ex.&#x20;Fr.&#x20;Prof." maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::nameDay' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Name day</label><input name="nameDay" class="form-control" type="text" value="1900-00-00"></div>',
      'element' => '<input name="nameDay" class="form-control" type="text" value="1900-00-00">',
      'label' => '<label for="nameDay">Name day</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Name day</label><input name="nameDay" class="form-control" type="text" value="2020-01-02"></div>',
      'element' => '<input name="nameDay" class="form-control" type="text" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Name day</label><input name="nameDay" class="form-control" type="text" value="0000-99-99"><ul class="help-block"><li>The input does not appear to be a valid date</li></ul></div>',
      'element' => '<input name="nameDay" class="form-control" type="text" value="0000-99-99">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid date</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::personTags' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Person tags</label><select name="personTags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="bishop">Bishop</option>
<option value="monsignor">Monsignor</option>
<!-- 8 more options, 6b1b95f8040ae5adff7b8898628bdab3 -->
<option value="ms">Ms.</option>
</select></div>',
      'element' => '<select name="personTags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="bishop">Bishop</option>
<option value="monsignor">Monsignor</option>
<!-- 8 more options, 6b1b95f8040ae5adff7b8898628bdab3 -->
<option value="ms">Ms.</option>
</select>',
      'label' => '<label for="personTags">Person tags</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="personTags&#x5B;&#x5D;" multiple></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Person tags</label><select name="personTags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="bishop" selected>Bishop</option>
<option value="monsignor">Monsignor</option>
<!-- 8 more options, 6b1b95f8040ae5adff7b8898628bdab3 -->
<option value="ms">Ms.</option>
</select></div>',
      'element' => '<select name="personTags&#x5B;&#x5D;" multiple class="form-control"><option value=""></option>
<option value="bishop" selected>Bishop</option>
<option value="monsignor">Monsignor</option>
<!-- 8 more options, 6b1b95f8040ae5adff7b8898628bdab3 -->
<option value="ms">Ms.</option>
</select>',
      'select_without_options' => '<select name="personTags&#x5B;&#x5D;" multiple><option value="bishop" selected>Bishop</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::phone1' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 1</label><input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value=""><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="">',
      'label' => '<label for="phone1">Phone 1</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p>',
      'text' => '<input type="text" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
      'hidden' => '<input type="hidden" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 1</label><input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100"><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'text' => '<input type="text" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'hidden' => '<input type="hidden" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Phone 1</label><input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number"><ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number">',
      'errors' => '<ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul>',
      'text' => '<input type="text" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
      'hidden' => '<input type="hidden" name="phone1" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::phone1Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 1 label (optional)</label><select name="phone1Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select></div>',
      'element' => '<select name="phone1Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select>',
      'label' => '<label for="phone1Label">Phone 1 label (optional)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="phone1Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 1 label (optional)</label><select name="phone1Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select></div>',
      'element' => '<select name="phone1Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select>',
      'select_without_options' => '<select name="phone1Label"><option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::phone2' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 2</label><input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value=""><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="">',
      'label' => '<label for="phone2">Phone 2</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p>',
      'text' => '<input type="text" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
      'hidden' => '<input type="hidden" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 2</label><input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100"><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'text' => '<input type="text" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'hidden' => '<input type="hidden" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Phone 2</label><input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number"><ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number">',
      'errors' => '<ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul>',
      'text' => '<input type="text" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
      'hidden' => '<input type="hidden" name="phone2" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::phone2Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 2 label (optional)</label><select name="phone2Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select></div>',
      'element' => '<select name="phone2Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select>',
      'label' => '<label for="phone2Label">Phone 2 label (optional)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="phone2Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 2 label (optional)</label><select name="phone2Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select></div>',
      'element' => '<select name="phone2Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select>',
      'select_without_options' => '<select name="phone2Label"><option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::phone3' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 3</label><input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value=""><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="">',
      'label' => '<label for="phone3">Phone 3</label>',
      'errors' => '',
      'help_block' => '<p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p>',
      'text' => '<input type="text" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
      'hidden' => '<input type="hidden" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 3</label><input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100"><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'text' => '<input type="text" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
      'hidden' => '<input type="hidden" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="&#x2B;1&#x20;202&#x20;555&#x20;0100">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Phone 3</label><input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number"><ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul><p class="help-block">Please begin with a &#039;+&#039; followed by the country code.</p></div>',
      'element' => '<input type="tel" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" class="form-control" value="not&#x20;a&#x20;phone&#x20;number">',
      'errors' => '<ul class="help-block"><li>Please begin with &#039;+&#039; and the country code, and use only numbers, dash, space or parenthesis. &#039; ext. ##&#039; may be added for extensions.</li></ul>',
      'text' => '<input type="text" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
      'hidden' => '<input type="hidden" name="phone3" placeholder="ex.&#x20;&#x2B;49&#x20;151&#x20;55555555" maxlength="30" value="not&#x20;a&#x20;phone&#x20;number">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::phone3Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Phone 3 label (optional)</label><select name="phone3Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select></div>',
      'element' => '<select name="phone3Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone">Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select>',
      'label' => '<label for="phone3Label">Phone 3 label (optional)</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="phone3Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Phone 3 label (optional)</label><select name="phone3Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select></div>',
      'element' => '<select name="phone3Label" class="form-control"><option value=""></option>
<option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
<option value="Only&#x20;WhatsApp">Only WhatsApp</option>
<!-- 3 more options, 4045982fbe31717d4e228a65ecb49969 -->
<option value="Personal&#x20;house&#x20;phone">Personal house phone</option>
</select>',
      'select_without_options' => '<select name="phone3Label"><option value="Alternate&#x20;cell&#x20;phone" selected>Alternate cell phone</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::postCityState' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>City/State</label><input type="text" name="postCityState" class="form-control" value=""></div>',
      'element' => '<input type="text" name="postCityState" class="form-control" value="">',
      'label' => '<label for="postCityState">City/State</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="postCityState" value="">',
      'hidden' => '<input type="hidden" name="postCityState" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>City/State</label><input type="text" name="postCityState" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="postCityState" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="postCityState" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="postCityState" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>City/State</label><input type="text" name="postCityState" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="postCityState" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="postCityState" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="postCityState" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::postCountry' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Country</label><select name="postCountry" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select></div>',
      'element' => '<select name="postCountry" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select>',
      'label' => '<label for="postCountry">Country</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="postCountry"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Country</label><select name="postCountry" class="form-control"><option value=""></option>
<option value="AF" selected>Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select></div>',
      'element' => '<select name="postCountry" class="form-control"><option value=""></option>
<option value="AF" selected>Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select>',
      'select_without_options' => '<select name="postCountry"><option value="AF" selected>Afghanistan</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Country</label><select name="postCountry" class="form-control"><option value=""></option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<!-- 246 more options, a07a9e81dde34fdda89505d77057b98d -->
<option value="AX">Åland Islands</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::postStreet1' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Street Line 1</label><input type="text" name="postStreet1" class="form-control" value=""></div>',
      'element' => '<input type="text" name="postStreet1" class="form-control" value="">',
      'label' => '<label for="postStreet1">Street Line 1</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="postStreet1" value="">',
      'hidden' => '<input type="hidden" name="postStreet1" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Street Line 1</label><input type="text" name="postStreet1" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="postStreet1" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="postStreet1" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="postStreet1" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Street Line 1</label><input type="text" name="postStreet1" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="postStreet1" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="postStreet1" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="postStreet1" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::postStreet2' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Street Line 2</label><input type="text" name="postStreet2" class="form-control" value=""></div>',
      'element' => '<input type="text" name="postStreet2" class="form-control" value="">',
      'label' => '<label for="postStreet2">Street Line 2</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="postStreet2" value="">',
      'hidden' => '<input type="hidden" name="postStreet2" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Street Line 2</label><input type="text" name="postStreet2" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="postStreet2" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="postStreet2" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="postStreet2" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Street Line 2</label><input type="text" name="postStreet2" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="postStreet2" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="postStreet2" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="postStreet2" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::postZip' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Zip/PLZ</label><input type="text" name="postZip" class="form-control" value=""></div>',
      'element' => '<input type="text" name="postZip" class="form-control" value="">',
      'label' => '<label for="postZip">Zip/PLZ</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="postZip" value="">',
      'hidden' => '<input type="hidden" name="postZip" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Zip/PLZ</label><input type="text" name="postZip" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="postZip" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="postZip" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="postZip" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Zip/PLZ</label><input type="text" name="postZip" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="postZip" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="postZip" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="postZip" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::priestDate' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Priest ordination date</label><input type="date" name="priestDate" min="1900-01-01" step="any" class="form-control" value=""></div>',
      'element' => '<input type="date" name="priestDate" min="1900-01-01" step="any" class="form-control" value="">',
      'label' => '<label for="priestDate">Priest ordination date</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="priestDate" value="">',
      'hidden' => '<input type="hidden" name="priestDate" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Priest ordination date</label><input type="date" name="priestDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02"></div>',
      'element' => '<input type="date" name="priestDate" min="1900-01-01" step="any" class="form-control" value="2020-01-02">',
      'text' => '<input type="text" name="priestDate" value="2020-01-02">',
      'hidden' => '<input type="hidden" name="priestDate" value="2020-01-02">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Priest ordination date</label><input type="date" name="priestDate" min="1900-01-01" step="any" class="form-control" value="not-a-date"><ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul></div>',
      'element' => '<input type="date" name="priestDate" min="1900-01-01" step="any" class="form-control" value="not-a-date">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid date</li><li>This does not look like a date. Please enter one like 2020-03-15.</li></ul>',
      'text' => '<input type="text" name="priestDate" value="not-a-date">',
      'hidden' => '<input type="hidden" name="priestDate" value="not-a-date">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::priestDatePrecision' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the ordination date is known</label><select name="priestDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select></div>',
      'element' => '<select name="priestDatePrecision" class="form-control"><option value="day" selected>Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'label' => '<label for="priestDatePrecision">How precisely the ordination date is known</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="priestDatePrecision"><option value="day" selected>Exact day</option>
</select>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>How precisely the ordination date is known</label><select name="priestDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'element' => '<select name="priestDatePrecision" class="form-control"><option value="day">Exact day</option>
<option value="month">Month and year only</option>
<option value="year">Year only</option>
</select>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
      'select_without_options' => '<select name="priestDatePrecision"></select>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::publicNotes' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Public notes</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control"></textarea>',
      'label' => '<label for="publicNotes">Public notes</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Public notes</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Public notes</label><textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="publicNotes" data-provide="markdown" data-parser="CommonMark" rows="8" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::skypeUser' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Skype user</label><input type="text" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" class="form-control" value=""></div>',
      'element' => '<input type="text" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" class="form-control" value="">',
      'label' => '<label for="skypeUser">Skype user</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" value="">',
      'hidden' => '<input type="hidden" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Skype user</label><input type="text" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Skype user</label><input type="text" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>Skype user names should begin with a letter, contain only letters, numbers, &#039;,&#039;, &#039;.&#039;, &#039;-&#039;, or &#039;_&#039; and be between 6 and 32 characters long.</li></ul></div>',
      'element' => '<input type="text" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Skype user names should begin with a letter, contain only letters, numbers, &#039;,&#039;, &#039;.&#039;, &#039;-&#039;, or &#039;_&#039; and be between 6 and 32 characters long.</li></ul>',
      'text' => '<input type="text" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="skypeUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="32" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::slackUser' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Slack user</label><input type="text" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" class="form-control" value=""></div>',
      'element' => '<input type="text" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" class="form-control" value="">',
      'label' => '<label for="slackUser">Slack user</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" value="">',
      'hidden' => '<input type="hidden" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Slack user</label><input type="text" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Slack user</label><input type="text" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>Slack user names should begin with a letter or number, and contain only letters, numbers, &#039;.&#039;, &#039;-&#039;, or &#039;_&#039;.</li></ul></div>',
      'element' => '<input type="text" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Slack user names should begin with a letter or number, and contain only letters, numbers, &#039;.&#039;, &#039;-&#039;, or &#039;_&#039;.</li></ul>',
      'text' => '<input type="text" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="slackUser" placeholder="ex.&#x20;fr.johnsmith" maxlength="50" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::spousePersonId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Spouse</label><select name="spousePersonId" class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select></div>',
      'element' => '<select name="spousePersonId" class="form-control"><option value=""></option>
<option value="633">Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'label' => '<label for="spousePersonId">Spouse</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="spousePersonId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Spouse</label><select name="spousePersonId" class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select></div>',
      'element' => '<select name="spousePersonId" class="form-control"><option value=""></option>
<option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
<option value="630">Abella, Santiago</option>
<!-- 322 more options, 74c9cfd0eedcbad440fe9952f9981ae0 -->
<option value="529">Álamos, Victor</option>
</select>',
      'select_without_options' => '<select name="spousePersonId"><option value="633" selected>Abarca Vallejos, Sergio Franco Alexander</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::twitterUser' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Twitter user</label><input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value=""></div>',
      'element' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value="">',
      'label' => '<label for="twitterUser">Twitter user</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="">',
      'hidden' => '<input type="hidden" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Twitter user</label><input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Twitter user</label><input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"><ul class="help-block"><li>Twitter user names should contain only letters, numbers, or &#039;_&#039; and be between 1 and 15 characters long.</li></ul></div>',
      'element' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'errors' => '<ul class="help-block"><li>Twitter user names should contain only letters, numbers, or &#039;_&#039; and be between 1 and 15 characters long.</li></ul>',
      'text' => '<input type="text" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="twitterUser" placeholder="ex.&#x20;fr_johnsmith" maxlength="15" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::url1' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="">',
      'label' => '<label for="url1">Other URL 1</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 1</label><input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url1" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::url1Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 1 Label</label><select name="url1Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url1Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'label' => '<label for="url1Label">Other URL 1 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url1Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 1 Label</label><select name="url1Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url1Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'select_without_options' => '<select name="url1Label"><option value="Blog" selected>Blog</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::url2' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="">',
      'label' => '<label for="url2">Other URL 2</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 2</label><input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url2" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::url2Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 2 Label</label><select name="url2Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url2Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'label' => '<label for="url2Label">Other URL 2 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url2Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 2 Label</label><select name="url2Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url2Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'select_without_options' => '<select name="url2Label"><option value="Blog" selected>Blog</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::url3' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value=""></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="">',
      'label' => '<label for="url3">Other URL 3</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline"></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="https&#x3A;&#x2F;&#x2F;example.com&#x2F;baseline">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 3</label><input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url"><ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul></div>',
      'element' => '<input type="url" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" class="form-control" value="not&#x20;a&#x20;url">',
      'errors' => '<ul class="help-block"><li>The input does not appear to be a valid Uri</li></ul>',
      'text' => '<input type="text" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
      'hidden' => '<input type="hidden" name="url3" placeholder="ex.&#x20;https&#x3A;&#x2F;&#x2F;www.facebook.com&#x2F;john.smith.34" maxlength="255" value="not&#x20;a&#x20;url">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\PersonForm::url3Label' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 3 Label</label><select name="url3Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url3Label" class="form-control"><option value=""></option>
<option value="Blog">Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'label' => '<label for="url3Label">Other URL 3 Label</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="url3Label"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Other URL 3 Label</label><select name="url3Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select></div>',
      'element' => '<select name="url3Label" class="form-control"><option value=""></option>
<option value="Blog" selected>Blog</option>
<option value="G&#x2B;">G+</option>
<option value="Personal&#x20;website">Personal website</option>
</select>',
      'select_without_options' => '<select name="url3Label"><option value="Blog" selected>Blog</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="edit_role" action="&#x2F;form-action" class="form-horizontal" id="edit_role">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::associationId' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Association</label><select name="associationId" class="form-control"><option value=""></option>
<option value="569">Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select></div>',
      'element' => '<select name="associationId" class="form-control"><option value=""></option>
<option value="569">Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select>',
      'label' => '<label for="associationId">Association</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="associationId"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Association</label><select name="associationId" class="form-control"><option value=""></option>
<option value="569" selected>Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select></div>',
      'element' => '<select name="associationId" class="form-control"><option value=""></option>
<option value="569" selected>Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select>',
      'select_without_options' => '<select name="associationId"><option value="569" selected>Austin Caritas</option>
</select>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Association</label><select name="associationId" class="form-control"><option value=""></option>
<option value="569">Austin Caritas</option>
<option value="571">Casa de la Familia de Schoenstatt - Tucumán</option>
<!-- 492 more options, d1b28fdd9b64fd82ec907da51ee5c12d -->
<option value="263">Women&#039;s youth of Temuco</option>
</select><ul class="help-block"><li>The input was not found in the haystack</li></ul></div>',
      'errors' => '<ul class="help-block"><li>The input was not found in the haystack</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::isActive' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1" checked> Active</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1" checked> Active</label>',
      'label' => '<label for="isActive">Active</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1"> Active</label></div>',
      'element' => '<input type="hidden" name="isActive" value="0"><label><input type="checkbox" name="isActive" value="1"> Active</label>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::isMainContact' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isMainContact" value="0"><label><input type="checkbox" name="isMainContact" value="1"> Is main contact for the association?</label></div>',
      'element' => '<input type="hidden" name="isMainContact" value="0"><label><input type="checkbox" name="isMainContact" value="1"> Is main contact for the association?</label>',
      'label' => '<label for="isMainContact">Is main contact for the association?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isMainContact" value="0"><label><input type="checkbox" name="isMainContact" value="1" checked> Is main contact for the association?</label></div>',
      'element' => '<input type="hidden" name="isMainContact" value="0"><label><input type="checkbox" name="isMainContact" value="1" checked> Is main contact for the association?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::isMainRole' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isMainRole" value="0"><label><input type="checkbox" name="isMainRole" value="1"> Is main role for the association?</label></div>',
      'element' => '<input type="hidden" name="isMainRole" value="0"><label><input type="checkbox" name="isMainRole" value="1"> Is main role for the association?</label>',
      'label' => '<label for="isMainRole">Is main role for the association?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isMainRole" value="0"><label><input type="checkbox" name="isMainRole" value="1" checked> Is main role for the association?</label></div>',
      'element' => '<input type="hidden" name="isMainRole" value="0"><label><input type="checkbox" name="isMainRole" value="1" checked> Is main role for the association?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::isSinglePosition' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isSinglePosition" value="0"><label><input type="checkbox" name="isSinglePosition" value="1"> Does role only have one person at a time?</label></div>',
      'element' => '<input type="hidden" name="isSinglePosition" value="0"><label><input type="checkbox" name="isSinglePosition" value="1"> Does role only have one person at a time?</label>',
      'label' => '<label for="isSinglePosition">Does role only have one person at a time?</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><input type="hidden" name="isSinglePosition" value="0"><label><input type="checkbox" name="isSinglePosition" value="1" checked> Does role only have one person at a time?</label></div>',
      'element' => '<input type="hidden" name="isSinglePosition" value="0"><label><input type="checkbox" name="isSinglePosition" value="1" checked> Does role only have one person at a time?</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::roleTitle' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label for="roleSelect">Role title</label><select name="roleTitle" id="roleSelect" class="form-control"><option value=""></option>
<option value="&#x28;General&#x20;or&#x20;main&#x29;&#x20;Secretary">(General or main) Secretary</option>
<option value="Assistant&#x20;moderator">Assistant moderator</option>
<!-- 36 more options, 413e95ff6296aeeb7968cce46d4e72b3 -->
<option value="Women&#x27;s&#x20;Federation">Women&#039;s Federation</option>
</select></div>',
      'element' => '<select name="roleTitle" id="roleSelect" class="form-control"><option value=""></option>
<option value="&#x28;General&#x20;or&#x20;main&#x29;&#x20;Secretary">(General or main) Secretary</option>
<option value="Assistant&#x20;moderator">Assistant moderator</option>
<!-- 36 more options, 413e95ff6296aeeb7968cce46d4e72b3 -->
<option value="Women&#x27;s&#x20;Federation">Women&#039;s Federation</option>
</select>',
      'label' => '<label for="roleSelect">Role title</label>',
      'errors' => '',
      'help_block' => '',
      'select_without_options' => '<select name="roleTitle" id="roleSelect"></select>',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label for="roleSelect">Role title</label><select name="roleTitle" id="roleSelect" class="form-control"><option value=""></option>
<option value="&#x28;General&#x20;or&#x20;main&#x29;&#x20;Secretary" selected>(General or main) Secretary</option>
<option value="Assistant&#x20;moderator">Assistant moderator</option>
<!-- 36 more options, 413e95ff6296aeeb7968cce46d4e72b3 -->
<option value="Women&#x27;s&#x20;Federation">Women&#039;s Federation</option>
</select></div>',
      'element' => '<select name="roleTitle" id="roleSelect" class="form-control"><option value=""></option>
<option value="&#x28;General&#x20;or&#x20;main&#x29;&#x20;Secretary" selected>(General or main) Secretary</option>
<option value="Assistant&#x20;moderator">Assistant moderator</option>
<!-- 36 more options, 413e95ff6296aeeb7968cce46d4e72b3 -->
<option value="Women&#x27;s&#x20;Federation">Women&#039;s Federation</option>
</select>',
      'select_without_options' => '<select name="roleTitle" id="roleSelect"><option value="&#x28;General&#x20;or&#x20;main&#x29;&#x20;Secretary" selected>(General or main) Secretary</option>
</select>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::sort' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Sort value</label><input type="number" name="sort" min="0" max="100" step="1" class="form-control" value="50"></div>',
      'element' => '<input type="number" name="sort" min="0" max="100" step="1" class="form-control" value="50">',
      'label' => '<label for="sort">Sort value</label>',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="sort" value="50">',
      'hidden' => '<input type="hidden" name="sort" value="50">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Sort value</label><input type="number" name="sort" min="0" max="100" step="1" class="form-control" value="42"></div>',
      'element' => '<input type="number" name="sort" min="0" max="100" step="1" class="form-control" value="42">',
      'text' => '<input type="text" name="sort" value="42">',
      'hidden' => '<input type="hidden" name="sort" value="42">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Sort value</label><input type="number" name="sort" min="0" max="100" step="1" class="form-control" value="forty-two"></div>',
      'element' => '<input type="number" name="sort" min="0" max="100" step="1" class="form-control" value="forty-two">',
      'text' => '<input type="text" name="sort" value="forty-two">',
      'hidden' => '<input type="hidden" name="sort" value="forty-two">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\RoleForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\SearchForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="GET" name="search" action="&#x2F;form-action" class="form-horizontal" id="search">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\SearchForm::exMembers' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><label><input type="checkbox" name="exMembers" value="true"> Show Ex-members</label></div>',
      'element' => '<label><input type="checkbox" name="exMembers" value="true"> Show Ex-members</label>',
      'label' => '<label for="exMembers">Show Ex-members</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><label><input type="checkbox" name="exMembers" value="true" checked> Show Ex-members</label></div>',
      'element' => '<label><input type="checkbox" name="exMembers" value="true" checked> Show Ex-members</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\SearchForm::search' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" class="input-lg&#x20;form-control" placeholder="Search" value=""></div>',
      'element' => '<input type="text" name="search" class="input-lg&#x20;form-control" placeholder="Search" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="search" class="input-lg" placeholder="Search" value="">',
      'hidden' => '<input type="hidden" name="search" class="input-lg" placeholder="Search" value="">',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" class="input-lg&#x20;form-control" placeholder="Search" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;"></div>',
      'element' => '<input type="text" name="search" class="input-lg&#x20;form-control" placeholder="Search" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="search" class="input-lg" placeholder="Search" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="search" class="input-lg" placeholder="Search" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><input type="text" name="search" class="input-lg&#x20;form-control" placeholder="Search" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;"></div>',
      'element' => '<input type="text" name="search" class="input-lg&#x20;form-control" placeholder="Search" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="search" class="input-lg" placeholder="Search" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="search" class="input-lg" placeholder="Search" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'Schoenstatt\\Form\\SearchForm::showPhotos' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="checkbox"><label><input type="checkbox" name="showPhotos" value="true"> Show Photos</label></div>',
      'element' => '<label><input type="checkbox" name="showPhotos" value="true"> Show Photos</label>',
      'label' => '<label for="showPhotos">Show Photos</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="checkbox"><label><input type="checkbox" name="showPhotos" value="true" checked> Show Photos</label></div>',
      'element' => '<label><input type="checkbox" name="showPhotos" value="true" checked> Show Photos</label>',
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'SionModel\\Form\\CommentForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="comment" action="&#x2F;form-action" class="form-horizontal" id="comment">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'SionModel\\Form\\CommentForm::comment' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><label>Leave a comment</label><textarea name="comment" rows="3" maxlength="500" class="form-control"></textarea></div>',
      'element' => '<textarea name="comment" rows="3" maxlength="500" class="form-control"></textarea>',
      'label' => '<label for="comment">Leave a comment</label>',
      'errors' => '',
      'help_block' => '',
    ),
    'populated' => 
    array (
      'row' => '<div class="form-group "><label>Leave a comment</label><textarea name="comment" rows="3" maxlength="500" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea></div>',
      'element' => '<textarea name="comment" rows="3" maxlength="500" class="form-control">Bäseline &lt;b&gt;value&lt;/b&gt; &amp; &quot;quoted&quot;
second line</textarea>',
    ),
    'invalid' => 
    array (
      'row' => '<div class="form-group "><label>Leave a comment</label><textarea name="comment" rows="3" maxlength="500" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea></div>',
      'element' => '<textarea name="comment" rows="3" maxlength="500" class="form-control">&lt;script&gt;alert(1)&lt;/script&gt;</textarea>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'SionModel\\Form\\CommentForm::redirect' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="redirect" class="form-control" value="">',
      'element' => '<input type="hidden" name="redirect" class="form-control" value="">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="redirect" value="">',
      'hidden' => '<input type="hidden" name="redirect" value="">',
    ),
    'populated' => 
    array (
      'row' => '<input type="hidden" name="redirect" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'element' => '<input type="hidden" name="redirect" class="form-control" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'text' => '<input type="text" name="redirect" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
      'hidden' => '<input type="hidden" name="redirect" value="B&#xE4;seline&#x20;&lt;b&gt;value&lt;&#x2F;b&gt;&#x20;&amp;&#x20;&quot;quoted&quot;&#x20;&#x27;apostrophe&#x27;">',
    ),
    'invalid' => 
    array (
      'row' => '<input type="hidden" name="redirect" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'element' => '<input type="hidden" name="redirect" class="form-control" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'text' => '<input type="text" name="redirect" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
      'hidden' => '<input type="hidden" name="redirect" value="&lt;script&gt;alert&#x28;1&#x29;&lt;&#x2F;script&gt;">',
    ),
    'prepared' => 
    array (
    ),
  ),
  'SionModel\\Form\\CommentForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'SionModel\\Form\\CommentForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-primary&#x20;btn" value="Submit">Submit</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'SionModel\\Form\\DeleteEntityForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="entity_delete" action="&#x2F;form-action" class="form-horizontal" id="entity_delete">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'SionModel\\Form\\DeleteEntityForm::cancel' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><input type="button" name="cancel" id="submit" data-dismiss="modal" class="form-control" value="Cancel"></div>',
      'element' => '<input type="button" name="cancel" id="submit" data-dismiss="modal" class="form-control" value="Cancel">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'button' => '<button type="button" name="cancel" id="submit" data-dismiss="modal" class="btn&#x20;btn-default" value="Cancel">Cancel</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'SionModel\\Form\\DeleteEntityForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
  'SionModel\\Form\\DeleteEntityForm::submit' => 
  array (
    'pristine' => 
    array (
      'row' => '<div class="form-group "><button type="submit" name="submit" id="submit" class="btn-danger&#x20;btn" value="Delete">Delete</button></div>',
      'element' => '<button type="submit" name="submit" id="submit" class="btn-danger&#x20;btn" value="Delete">Delete</button>',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'submit' => '<button type="submit" name="submit" id="submit" class="btn-danger&#x20;btn" value="Delete">Delete</button>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'SionModel\\Form\\SionForm::<form>' => 
  array (
    'pristine' => 
    array (
      'open' => '<form method="POST" name="fuzz" action="&#x2F;form-action" class="form-horizontal" id="fuzz">',
      'close' => '</form>',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
    ),
    'prepared' => 
    array (
    ),
  ),
  'SionModel\\Form\\SionForm::security' => 
  array (
    'pristine' => 
    array (
      'row' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'element' => '<input type="hidden" name="security" class="form-control" value="<csrf-token>">',
      'label' => '',
      'errors' => '',
      'help_block' => '',
      'text' => '<input type="text" name="security" value="<csrf-token>">',
      'hidden' => '<input type="hidden" name="security" value="<csrf-token>">',
    ),
    'populated' => 
    array (
    ),
    'invalid' => 
    array (
      'errors' => '<ul class="help-block"><li>The form submitted was expired, please resubmit</li></ul>',
    ),
    'prepared' => 
    array (
    ),
  ),
);
