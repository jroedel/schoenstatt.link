<?php

/**
 * Validation gaps this codebase is known to have, as of the last regeneration.
 *
 * GENERATED FILE — do not hand-edit except to delete an entry that has been fixed.
 * Regenerate with:
 *
 *     docker compose exec -T app php test/Fuzz/regenerate-baseline.php
 *
 * The contract is phpstan-baseline.neon's: no *new* gaps. An entry here is a gap
 * that is accepted for now, not one that is acceptable. Adding to this file should
 * always be a deliberate decision — see SchoenstattTest\Fuzz\GapBaseline.
 *
 * @return array<string, list<string>>
 */

declare(strict_types=1);

return [

    // Proved by running it: a value accepted at a length the form itself declares is too long.
    'boundViolations' => [
    ],

    // Fields allowed to be longer than the column they are written into. SQLSTATE 22001 under
    // STRICT_TRANS_TABLES.
    'boundsLooserThanColumn' => [
        'Books\\Form\\CollectionForm: \'description\' allows 1000 characters but lib_collections.Description holds 255',
        'Schoenstatt\\Form\\PersonForm: \'manualTitle\' allows 50 characters but sch_persons.Title holds 10',
    ],

    // Elements declaring their button-ness only in attributes.type, so they are a base
    // Laminas\Form\Element and a data input as far as the input filter is concerned.
    'buttonsDeclaredOnlyByAttribute' => [
        'JUser\\Form\\CreateRoleForm: \'submit\' declares its button-ness only in attributes.type, so the server sees a plain text input',
        'JUser\\Form\\EditUserForm: \'submit\' declares its button-ness only in attributes.type, so the server sees a plain text input',
        'JUser\\Form\\LoginForm: \'submit\' declares its button-ness only in attributes.type, so the server sees a plain text input',
    ],

    // Select/Radio/MultiCheckbox fields named in the spec without an InArray, so the spec entry
    // replaced the element input and the option list no longer constrains anything. The quiet
    // corruption case: short wrong-domain values fit every column.
    'choiceFieldsWithoutDomain' => [
        'Books\\Form\\BookForm: \'adminTags\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\BookForm: \'authors\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\BookForm: \'category\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\BookForm: \'collectionId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\BookForm: \'inLanguage\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\BookForm: \'keywords\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\BookForm: \'publicationId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\BookForm: \'publisher\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\CollectionForm: \'mainShowDisplay\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\CompositionForm: \'composersAll\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\CompositionForm: \'country\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\CompositionForm: \'derivedFromCompositionId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\CompositionForm: \'inLanguage\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\CompositionForm: \'lyricistsAll\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\CompositionForm: \'openLicenseUrl\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\CompositionForm: \'tags\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\CompositionForm: \'url1Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\CompositionForm: \'url2Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\CompositionForm: \'url3Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\DictionaryEntryForm: \'links\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\DictionaryEntryForm: \'locale\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\EventForm: \'accuracy\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\EventForm: \'aclResourceId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\EventForm: \'adminTags\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\EventForm: \'bestTextQuality\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\EventForm: \'country\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\EventForm: \'originalLanguage\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\EventForm: \'tags\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\EventsSearchForm: \'collectionId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\LibraryForm: \'checkoutBooksRole\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\LibraryForm: \'checkoutPersonListKind\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\LibraryForm: \'contactPersonId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\LibraryForm: \'defaultCheckoutPersonId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\LibraryForm: \'filiationId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\LibraryForm: \'mainCollectionId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\LibraryForm: \'mainShowDisplay\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\LibraryForm: \'viewRole\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\MassCheckoutFieldset: \'personId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'authorsAll\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'bookFormatType\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'categoryId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'editorsAll\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'inLanguage\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'keywords\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'mainPublicationId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'publisher\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'resourceId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'translatedFromPublicationId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'translatorsAll\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'url1Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'url2Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationForm: \'url3Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\PublicationsSearchForm: \'inLanguage\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\SearchForm: \'collectionId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\TextForm: \'inLanguage\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\TextForm: \'tags\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Books\\Form\\TextSearchForm: \'inLanguage\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'JUser\\Form\\CreateRoleForm: \'parentId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'JUser\\Form\\EditUserForm: \'personId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AdvancedSearchForm: \'associationCountry\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AdvancedSearchForm: \'associationKind\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AdvancedSearchForm: \'roleTitle\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AssignmentForm: \'associationId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AssignmentForm: \'personId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AssignmentForm: \'roleId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AssociationForm: \'phone1Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AssociationForm: \'phone2Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AssociationForm: \'phone3Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AssociationForm: \'url1Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AssociationForm: \'url2Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\AssociationForm: \'url3Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\EditAssignmentForm: \'associationId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\EditAssignmentForm: \'personId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\EditAssignmentForm: \'roleId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\ImportFatherForm: \'personId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'adminTags\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'country\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'lifeCommunity\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'personTags\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'phone1Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'phone2Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'phone3Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'postCountry\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'spousePersonId\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'url1Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'url2Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\PersonForm: \'url3Label\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
        'Schoenstatt\\Form\\RoleForm: \'roleTitle\' is a select named in the spec without an InArray, so its option list no longer constrains anything',
    ],

    // filters/validators keys in an element definition, which Laminas\Form\Factory discards without a
    // word. The field looks protected in the source and is completely unvalidated.
    'deadElementKeys' => [
        'module/Books/src/Form/EventForm.php:283 element \'publicNotes\' carries a dead \'filters\' key in its element definition',
        'module/Schoenstatt/src/Form/PersonForm.php:471 element \'publicNotes\' carries a dead \'filters\' key in its element definition',
    ],

    // Data elements the input filter spec never names. A plain Text/Textarea/Hidden in this state is
    // total pass-through: no filter, no validator, no length bound.
    'elementsMissingFromSpec' => [
        'Books\\Form\\CheckoutForm: \'personId\' is not named in the input filter spec (element-provided validation only, no filters)',
        'Books\\Form\\EventsSearchForm: \'category\' is not named in the input filter spec (PASS-THROUGH: no filter, no validator, no length bound)',
        'Books\\Form\\PublicationsSearchForm: \'includeDataSources\' is not named in the input filter spec (element-provided validation only, no filters)',
        'Books\\Form\\SearchForm: \'category\' is not named in the input filter spec (PASS-THROUGH: no filter, no validator, no length bound)',
        'JTranslate\\Form\\DeletePhraseForm: \'cancel\' is not named in the input filter spec (PASS-THROUGH: no filter, no validator, no length bound)',
        'JUser\\Form\\EditUserForm: \'rolesList\' is not named in the input filter spec (element-provided validation only, no filters)',
        'Schoenstatt\\Form\\RoleForm: \'associationId\' is not named in the input filter spec (element-provided validation only, no filters)',
        'SionModel\\Form\\CommentForm: \'comment\' is not named in the input filter spec (PASS-THROUGH: no filter, no validator, no length bound)',
        'SionModel\\Form\\CommentForm: \'redirect\' is not named in the input filter spec (PASS-THROUGH: no filter, no validator, no length bound)',
        'SionModel\\Form\\DeleteEntityForm: \'cancel\' is not named in the input filter spec (PASS-THROUGH: no filter, no validator, no length bound)',
    ],

    // Columns where database/*.sql claims more room than the live schema has, which makes the
    // column-width check above too permissive for those fields.
    'parsedWidthsLooserThanLiveSchema' => [
        'sch_roles.RoleTitle: database/*.sql says 100, the live schema says 50',
    ],

    // Spec keys naming no element. The field the entry was meant to protect is unvalidated, and
    // getData() gains a key holding null.
    'specKeysWithoutElement' => [
        'Books\\Form\\PublicationForm: input filter spec names \'isAccessibleForFree\', which is not an element on this form',
        'Schoenstatt\\Form\\PersonForm: input filter spec names \'cityState\', which is not an element on this form',
        'Schoenstatt\\Form\\PersonForm: input filter spec names \'skypeUser\', which is not an element on this form',
        'Schoenstatt\\Form\\PersonForm: input filter spec names \'slackUser\', which is not an element on this form',
        'Schoenstatt\\Form\\PersonForm: input filter spec names \'street1\', which is not an element on this form',
        'Schoenstatt\\Form\\PersonForm: input filter spec names \'street2\', which is not an element on this form',
        'Schoenstatt\\Form\\PersonForm: input filter spec names \'zip\', which is not an element on this form',
        'SionModel\\Form\\CommentForm: input filter spec names \'text\', which is not an element on this form',
    ],

    // Proved by running it: places where isValid() throws instead of answering. A 500 in a controller
    // action, not a validation failure.
    'throwingInputs' => [
    ],

    // add() calls ElementDefinitionScanner cannot read, because the argument is not an array literal.
    // Non-empty means the dead-key check above has blind spots.
    'unanalyzableAddCalls' => [
    ],

    // Free-text fields with no bound at all on their length.
    'unboundedTextFields' => [
        'Books\\Form\\BookForm: \'authorsText\' (text) has no length bound',
        'Books\\Form\\BookForm: \'libraryId\' (hidden) has no length bound',
        'Books\\Form\\CheckinForm: \'withinLibraryIds\' (textarea) has no length bound',
        'Books\\Form\\CheckoutForm: \'withinLibraryIds\' (textarea) has no length bound',
        'Books\\Form\\CollectionForm: \'adminNotes\' (textarea) has no length bound',
        'Books\\Form\\CollectionForm: \'libraryId\' (hidden) has no length bound',
        'Books\\Form\\CompositionForm: \'chordProSpec\' (textarea) has no length bound',
        'Books\\Form\\CompositionForm: \'copyrightContactEmail\' (email) has no length bound',
        'Books\\Form\\CompositionForm: \'copyrightInfo\' (textarea) has no length bound',
        'Books\\Form\\CompositionForm: \'lilyPondSpec\' (textarea) has no length bound',
        'Books\\Form\\CompositionForm: \'lyrics\' (textarea) has no length bound',
        'Books\\Form\\CompositionForm: \'url1\' (url) has no length bound',
        'Books\\Form\\CompositionForm: \'url2\' (url) has no length bound',
        'Books\\Form\\CompositionForm: \'url3\' (url) has no length bound',
        'Books\\Form\\EventForm: \'adminNotes\' (textarea) has no length bound',
        'Books\\Form\\EventForm: \'publicNotes\' (textarea) has no length bound',
        'Books\\Form\\EventsSearchForm: \'category\' (text) has no length bound',
        'Books\\Form\\EventsSearchForm: \'libraryId\' (hidden) has no length bound',
        'Books\\Form\\ImportForm: \'libraryId\' (hidden) has no length bound',
        'Books\\Form\\InactivationForm: \'withinLibraryIds\' (textarea) has no length bound',
        'Books\\Form\\LibraryForm: \'adminNotes\' (textarea) has no length bound',
        'Books\\Form\\MassCheckoutFieldset: \'withinLibraryIds\' (text) has no length bound',
        'Books\\Form\\PublicationForm: \'url1\' (url) has no length bound',
        'Books\\Form\\PublicationForm: \'url2\' (url) has no length bound',
        'Books\\Form\\PublicationForm: \'url3\' (url) has no length bound',
        'Books\\Form\\SearchForm: \'category\' (text) has no length bound',
        'Books\\Form\\SearchForm: \'libraryId\' (hidden) has no length bound',
        'Books\\Form\\TextForm: \'kind\' (hidden) has no length bound',
        'Books\\Form\\TextForm: \'markdownText\' (textarea) has no length bound',
        'JTranslate\\Form\\DeletePhraseForm: \'cancel\' (element) has no length bound',
        'JUser\\Form\\DeleteUserForm: \'userId\' (hidden) has no length bound',
        'JUser\\Form\\EditUserForm: \'email\' (element) has no length bound',
        'JUser\\Form\\EditUserForm: \'userId\' (element) has no length bound',
        'JUser\\Form\\LoginForm: \'redirect\' (element) has no length bound',
        'Schoenstatt\\Form\\AdvancedSearchForm: \'personName\' (text) has no length bound',
        'Schoenstatt\\Form\\AdvancedSearchForm: \'search\' (text) has no length bound',
        'Schoenstatt\\Form\\AssociationForm: \'associationId\' (hidden) has no length bound',
        'Schoenstatt\\Form\\AssociationForm: \'geoPoint\' (text) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'adminNotes\' (textarea) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'email\' (email) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'email2\' (email) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'facebookUrl\' (url) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'instagramUser\' (text) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'publicNotes\' (textarea) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'twitterUser\' (text) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'url1\' (url) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'url2\' (url) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'url3\' (url) has no length bound',
        'Schoenstatt\\Form\\SearchForm: \'search\' (text) has no length bound',
        'SionModel\\Form\\CommentForm: \'comment\' (textarea) has no length bound',
        'SionModel\\Form\\CommentForm: \'redirect\' (hidden) has no length bound',
        'SionModel\\Form\\DeleteEntityForm: \'cancel\' (element) has no length bound',
    ],

    // Forms no test in this suite can examine, because nothing can build them.
    'unconstructableForms' => [
    ],
];
