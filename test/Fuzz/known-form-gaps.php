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

    // Choice fields declared in test/Fuzz/open-ended-choice-fields.php as having no domain to enforce:
    // the view lets a moderator type a value the list does not offer, so an InArray would remove a
    // feature rather than close a hole. Listed rather than hidden, because "unvalidated on purpose" is
    // still unvalidated.
    'choiceFieldsOpenByDesign' => [
        'Books\\Form\\BookForm: \'adminTags\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\BookForm: \'authors\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\BookForm: \'category\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\BookForm: \'keywords\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\BookForm: \'publisher\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\CompositionForm: \'composersAll\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\CompositionForm: \'lyricistsAll\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\CompositionForm: \'tags\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\CompositionForm: \'url1Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\CompositionForm: \'url2Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\CompositionForm: \'url3Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\PublicationForm: \'authorsAll\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\PublicationForm: \'editorsAll\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\PublicationForm: \'keywords\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\PublicationForm: \'publisher\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\PublicationForm: \'translatorsAll\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\PublicationForm: \'url1Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\PublicationForm: \'url2Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\PublicationForm: \'url3Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Books\\Form\\TextForm: \'tags\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\AssociationForm: \'phone1Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\AssociationForm: \'phone2Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\AssociationForm: \'phone3Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\AssociationForm: \'url1Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\AssociationForm: \'url2Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\AssociationForm: \'url3Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\PersonForm: \'adminTags\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\PersonForm: \'personTags\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\PersonForm: \'phone1Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\PersonForm: \'phone2Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\PersonForm: \'phone3Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\PersonForm: \'url1Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\PersonForm: \'url2Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\PersonForm: \'url3Label\' is a select whose options are a suggestion, not a domain — declared open',
        'Schoenstatt\\Form\\RoleForm: \'roleTitle\' is a select whose options are a suggestion, not a domain — declared open',
    ],

    // Select/Radio/MultiCheckbox fields that set disable_inarray_validator and get no InArray back
    // from the spec, so nothing constrains them to their own option list. That option, not the spec,
    // is what removes a domain: laminas merges the spec input into the element's rather than replacing
    // it. The quiet corruption case: short wrong-domain values fit every column.
    'choiceFieldsWithoutDomain' => [
    ],

    // filters/validators keys in an element definition, which Laminas\Form\Factory discards without a
    // word. The field looks protected in the source and is completely unvalidated.
    'deadElementKeys' => [
        'module/Schoenstatt/src/Form/PersonForm.php:506 element \'publicNotes\' carries a dead \'filters\' key in its element definition',
    ],

    // Data elements the input filter spec never names. A plain Text/Textarea/Hidden in this state is
    // total pass-through: no filter, no validator, no length bound.
    'elementsMissingFromSpec' => [
    ],

    // Entries in open-ended-choice-fields.php that matched no field this run. Either the field is
    // constrained now and the declaration hides the next regression on it, or the entry is a typo
    // asserting nothing. Expected to be empty.
    'openEndedDeclarationsStale' => [
    ],

    // Columns where database/*.sql claims more room than the live schema has, which makes the
    // column-width check above too permissive for those fields.
    'parsedWidthsLooserThanLiveSchema' => [
        'sch_roles.RoleTitle: database/*.sql says 100, the live schema says 50',
    ],

    // Spec keys naming no element. The field the entry was meant to protect is unvalidated, and
    // getData() gains a key holding null.
    'specKeysWithoutElement' => [
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
        'Books\\Form\\CompositionForm: \'copyrightInfo\' (textarea) has no length bound',
        'Books\\Form\\CompositionForm: \'lilyPondSpec\' (textarea) has no length bound',
        'Books\\Form\\CompositionForm: \'lyrics\' (textarea) has no length bound',
        'Books\\Form\\CompositionForm: \'url1\' (url) has no length bound',
        'Books\\Form\\CompositionForm: \'url2\' (url) has no length bound',
        'Books\\Form\\CompositionForm: \'url3\' (url) has no length bound',
        'Books\\Form\\ImportForm: \'libraryId\' (hidden) has no length bound',
        'Books\\Form\\InactivationForm: \'withinLibraryIds\' (textarea) has no length bound',
        'Books\\Form\\LibraryForm: \'adminNotes\' (textarea) has no length bound',
        'Books\\Form\\MassCheckoutFieldset: \'withinLibraryIds\' (text) has no length bound',
        'Books\\Form\\PublicationForm: \'url1\' (url) has no length bound',
        'Books\\Form\\PublicationForm: \'url2\' (url) has no length bound',
        'Books\\Form\\PublicationForm: \'url3\' (url) has no length bound',
        'Books\\Form\\SearchForm: \'libraryId\' (hidden) has no length bound',
        'Books\\Form\\TextForm: \'kind\' (hidden) has no length bound',
        'Books\\Form\\TextForm: \'markdownText\' (textarea) has no length bound',
        'JUser\\Form\\DeleteUserForm: \'userId\' (hidden) has no length bound',
        'JUser\\Form\\EditUserForm: \'email\' (element) has no length bound',
        'JUser\\Form\\EditUserForm: \'userId\' (element) has no length bound',
        'JUser\\Form\\LoginForm: \'redirect\' (element) has no length bound',
        'Schoenstatt\\Form\\AdvancedSearchForm: \'search\' (text) has no length bound',
        'Schoenstatt\\Form\\AssociationForm: \'associationId\' (hidden) has no length bound',
        'Schoenstatt\\Form\\AssociationForm: \'geoPoint\' (text) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'adminNotes\' (textarea) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'facebookUrl\' (url) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'instagramUser\' (text) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'publicNotes\' (textarea) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'twitterUser\' (text) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'url1\' (url) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'url2\' (url) has no length bound',
        'Schoenstatt\\Form\\PersonForm: \'url3\' (url) has no length bound',
        'Schoenstatt\\Form\\SearchForm: \'search\' (text) has no length bound',
    ],

    // Forms no test in this suite can examine, because nothing can build them.
    'unconstructableForms' => [
    ],

    // Validators that exist only because an element supplied them, with nothing in the form
    // specification saying so — a Select's own InArray, Uri on a Url element, Csrf on the security
    // element no form names. The work list for step 5: SionModel\Form\Validation\InputFilter is driven
    // by the specification alone, so every entry here is a check that disappears on the day it
    // replaces Laminas\InputFilter. Must reach zero before that cutover.
    'validationSuppliedOnlyByElement' => [
        'Books\\Form\\CheckoutForm: \'personId\' is validated by InArray, which the input filter spec does not declare',
        'Schoenstatt\\Form\\ImportFatherForm: \'personId\' is validated by InArray, which the input filter spec does not declare',
    ],
];
