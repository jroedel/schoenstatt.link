<?php

declare(strict_types=1);

/**
 * Choice fields whose option list is a suggestion, not a domain.
 *
 * ## Why this file exists
 *
 * `FormGapCollector` reports a `Select`, `Radio` or `MultiCheckbox` that sets
 * `disable_inarray_validator => true` and gets no `InArray` back from the form's
 * `getInputFilterSpecification()`: nothing then constrains it to its option list. On 2026-08-15
 * that was 88 fields by the collector's original reckoning — which counted every field named in
 * a spec, on the belief that naming one discards the element's own input. It does not; laminas
 * merges. 35 fields are in this state, and **fixing them all the same way would still have been
 * a bug.**
 *
 * Most of them are `selectize({create: true})` in the view: the moderator types a value
 * that is not in the list and the list grows. A tag is created by typing it; a book regularly
 * names an author the database has never seen; "Home" and "Work" are whatever anyone wrote
 * before. Adding an `InArray` to those does not close a hole, it removes a feature — cataloguing
 * would stop until an administrator added the missing person.
 *
 * So the two cases are opposite fixes wearing one description, and the baseline could not tell
 * them apart. Every entry here is a field where **no domain check is the correct answer**, and
 * the remaining `choiceFieldsWithoutDomain` entries are the ones still worth closing — as of
 * batch 12 there are none.
 *
 * ## How each entry was decided
 *
 * By the `create:` flag on the field's `selectize()` call, read from `templates/` and
 * `module/&ast;/view/`. That is the view telling the server what the field means, which is
 * uncomfortable — the fact lives in JavaScript and is enforced (or not) in PHP — but it is
 * where this application states it, and inventing a second declaration would let the two drift.
 *
 * It is not the last word, and one entry proved it. `AdvancedSearchForm::roleTitle` was
 * declared here on that flag and taken out again on 2026-08-15: the element does not set
 * `disable_inarray_validator`, so its own `InArray` is live and the field rejects a typed role
 * title today, whatever the view offers. The server, not the template, decides whether a domain
 * is enforced — the `create:` flag only says whether someone *meant* it to be. Where the two
 * disagree the field belongs in docs/BACKLOG.md, not in this list.
 *
 * One inconsistency turned up in the reading and was *not* accepted here: `country` was
 * `create: true` on the composition form and `create: false` on the person and association
 * forms, so on one page a moderator could invent a country. `mus_compositions.Country` held
 * `pt` and `cl` against an uppercase-keyed list, which is what that permission produced. Both
 * halves are fixed as of 2026-08-15 — the template says `create: false` and the form carries a
 * ChoiceDomain InArray — which is why `CompositionForm::country` is not in the list below.
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
