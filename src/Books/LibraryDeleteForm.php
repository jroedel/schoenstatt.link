<?php

declare(strict_types=1);

namespace App\Books;

use Laminas\Filter\StringTrim;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Identical;
use Laminas\Validator\NotEmpty;

/**
 * The confirmation that stands between a visitor and an entire library.
 *
 * Every other delete on this site is a CSRF token and a button, and that is proportionate:
 * `SionModel\Form\DeleteEntityForm` guards the removal of one record, which somebody can
 * retype. This one guards up to 16,383 books in a single POST, with no undo and — since
 * the change log records an aggregate rather than a row per book — no way to reconstruct
 * what was in it. So it asks for the library's name to be typed.
 *
 * ## Why the name, and why exactly
 *
 * The comparison is `Identical` against the stored `LibraryName`, after trimming, and it
 * is **case-sensitive**. A destructive confirmation whose purpose is to make the visitor
 * stop and read stops working the moment it accepts an approximation, and the page prints
 * the name directly above the field so there is nothing to guess at.
 *
 * Trimming is the one concession, because it is the one difference a copy-paste
 * introduces — a trailing space off a double-click selection is not a sign of doubt.
 *
 * ## The validator lives in the input filter, not on the element
 *
 * `Laminas\Form\Factory::configureElement()` reads only `name`, `options` and `attributes`,
 * so a `validators` key inside an element definition is **discarded silently** — the
 * documented trap in CLAUDE.md, and the one that leaves a field looking protected while
 * accepting anything. Hence `InputFilterProviderInterface`.
 *
 * The expected name is a constructor argument rather than a setter for the same reason:
 * a form that can be built without its token, and validate against an empty one, would
 * accept an empty submission for a library whose name is empty. There is no such library
 * today, and the constructor makes it unrepresentable rather than unlikely.
 *
 * The 900-second CSRF timeout matches `DeleteEntityForm` and `RefreshSortForm`, which are
 * the application's other two confirmation forms.
 *
 * @extends Form<array<string, mixed>>
 */
final class LibraryDeleteForm extends Form implements InputFilterProviderInterface
{
    public const NAME_FIELD = 'library_name';

    public function __construct(private readonly string $expectedName)
    {
        parent::__construct('library_delete');

        $this->setAttribute('method', 'POST');

        $this->add([
            'name'    => 'security',
            'type'    => 'csrf',
            'options' => [
                'csrf_options' => ['timeout' => 900],
            ],
        ]);
        $this->add([
            'name'       => self::NAME_FIELD,
            'type'       => 'Text',
            'options'    => [
                'label' => 'Type the library name to confirm',
            ],
            'attributes' => [
                'id'           => self::NAME_FIELD,
                'class'        => 'form-control',
                //autocomplete off and spellcheck off: the browser offering to complete
                //this field would defeat the entire point of asking for it.
                'autocomplete' => 'off',
                'spellcheck'   => 'false',
                'required'     => 'required',
            ],
        ]);
        $this->add([
            'name'       => 'submit',
            'type'       => 'Submit',
            'attributes' => [
                'value' => 'Delete this library permanently',
                'id'    => 'submit',
                'class' => 'btn btn-danger',
            ],
        ]);
        //A Button, not a Submit — the same fix DeleteEntityForm needed on 2026-08-14, when
        //clicking Cancel deleted the record on both front controllers because the action
        //validated only the CSRF token. The controller carries the server-side second lock.
        $this->add([
            'name'       => 'cancel',
            'type'       => 'Button',
            'options'    => ['label' => 'Cancel'],
            'attributes' => [
                'id'    => 'cancel',
                'class' => 'btn btn-secondary',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function getInputFilterSpecification(): array
    {
        return [
            self::NAME_FIELD => [
                'required'   => true,
                'filters'    => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name'    => NotEmpty::class,
                        'options' => [
                            'messages' => [
                                NotEmpty::IS_EMPTY => 'Type the library name to confirm the deletion.',
                            ],
                        ],
                    ],
                    [
                        'name'    => Identical::class,
                        'options' => [
                            'token'   => $this->expectedName,
                            //=== rather than ==, so nothing about type juggling can make a
                            //different string compare equal to this one.
                            'strict'  => true,
                            'messages' => [
                                Identical::NOT_SAME => 'That is not this library\'s name. '
                                    . 'Nothing has been deleted.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
