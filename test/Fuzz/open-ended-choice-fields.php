<?php

declare(strict_types=1);

/**
 * Choice fields whose option list is a suggestion, not a domain.
 *
 * ## Why this file exists
 *
 * `FormGapCollector` reports a `Select`, `Radio` or `MultiCheckbox` named in a form's
 * `getInputFilterSpecification()` without an `InArray` as a gap, because naming it there
 * discards the element's own input and the option list stops constraining anything. That was
 * true of 88 fields on 2026-08-15 — and **fixing all 88 the same way would have been a bug.**
 *
 * Roughly half of them are `selectize({create: true})` in the view: the moderator types a value
 * that is not in the list and the list grows. A tag is created by typing it; a book regularly
 * names an author the database has never seen; "Home" and "Work" are whatever anyone wrote
 * before. Adding an `InArray` to those does not close a hole, it removes a feature — cataloguing
 * would stop until an administrator added the missing person.
 *
 * So the two cases are opposite fixes wearing one description, and the baseline could not tell
 * them apart. Every entry here is a field where **no domain check is the correct answer**, and
 * the remaining `choiceFieldsWithoutDomain` entries are the ones still worth closing.
 *
 * ## How each entry was decided
 *
 * By the `create:` flag on the field's `selectize()` call, read from `templates/` and
 * `module/&ast;/view/`. That is the view telling the server what the field means, which is
 * uncomfortable — the fact lives in JavaScript and is enforced (or not) in PHP — but it is
 * where this application states it, and inventing a second declaration would let the two drift.
 *
 * One inconsistency turned up in the reading and is *not* accepted here: `country` is
 * `create: true` on the composition form and `create: false` on the person and association
 * forms, so on one page a moderator can invent a country. `mus_compositions.Country` holds
 * `pt` and `cl` against an uppercase-keyed list, which is what that permission produced. It is
 * filed in docs/BACKLOG.md rather than declared open by design.
 *
 * ## Adding to this file
 *
 * Deliberately, and with the reason. An entry that no longer corresponds to a reported gap is
 * itself reported — see `openEndedDeclarationsStale` in the baseline — so a field that gains an
 * `InArray` cannot be left declared open here, and a typo cannot sit unnoticed.
 *
 * @return list<string> `Form class::element name`
 */

return [
    // Tag pickers. A tag is created by typing it; the option list is the tags other records
    // already use, which is a suggestion and by definition not a domain.
    'Books\Form\BookForm::adminTags',
    'Books\Form\BookForm::keywords',
    'Books\Form\CompositionForm::tags',
    'Books\Form\EventForm::adminTags',
    'Books\Form\EventForm::tags',
    'Books\Form\PublicationForm::keywords',
    'Books\Form\TextForm::tags',
    'Schoenstatt\Form\PersonForm::adminTags',
    'Schoenstatt\Form\PersonForm::personTags',

    // Contributor pickers. `create: true` because a book or a composition regularly names
    // someone the database has never seen, and refusing that would mean cataloguing stops
    // until an admin adds the person.
    'Books\Form\BookForm::authors',
    'Books\Form\CompositionForm::composersAll',
    'Books\Form\CompositionForm::lyricistsAll',
    'Books\Form\PublicationForm::authorsAll',
    'Books\Form\PublicationForm::editorsAll',
    'Books\Form\PublicationForm::translatorsAll',

    // Free-text fields wearing a select, where the options are the values already in use:
    // a publisher, a category, a role title.
    'Books\Form\BookForm::category',
    'Books\Form\BookForm::publisher',
    'Books\Form\PublicationForm::publisher',
    'Schoenstatt\Form\AdvancedSearchForm::roleTitle',
    'Schoenstatt\Form\RoleForm::roleTitle',

    // Contact labels — "Home", "Work", "Mobile". The list is whatever other records use and
    // a moderator is meant to be able to write their own.
    'Books\Form\CompositionForm::url1Label',
    'Books\Form\CompositionForm::url2Label',
    'Books\Form\CompositionForm::url3Label',
    'Books\Form\PublicationForm::url1Label',
    'Books\Form\PublicationForm::url2Label',
    'Books\Form\PublicationForm::url3Label',
    'Schoenstatt\Form\AssociationForm::phone1Label',
    'Schoenstatt\Form\AssociationForm::phone2Label',
    'Schoenstatt\Form\AssociationForm::phone3Label',
    'Schoenstatt\Form\AssociationForm::url1Label',
    'Schoenstatt\Form\AssociationForm::url2Label',
    'Schoenstatt\Form\AssociationForm::url3Label',
    'Schoenstatt\Form\PersonForm::phone1Label',
    'Schoenstatt\Form\PersonForm::phone2Label',
    'Schoenstatt\Form\PersonForm::phone3Label',
    'Schoenstatt\Form\PersonForm::url1Label',
    'Schoenstatt\Form\PersonForm::url2Label',
    'Schoenstatt\Form\PersonForm::url3Label',
];
