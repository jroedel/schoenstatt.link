<?php

declare(strict_types=1);

namespace App\Books\Import;

use Laminas\Filter\StringTrim;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Regex;
use SionModel\Form\CsrfSpec;

/**
 * The confirmation between a librarian and several thousand book records.
 *
 * ## Why a confirmation, and why it carries a digest
 *
 * The old page's "Import data" button sat beside a table of eleven thousand rows and
 * did the work on a plain submit. Nothing named the scale first. Import 3's plan, still
 * reachable today, would inactivate **1,281** of Colegio Mayor's 10,874 active books,
 * and import 1's would inactivate **3,492** — both from spreadsheets last touched in
 * August 2017.
 *
 * The digest is the part that is not merely a button. An import is planned on one
 * request and applied on the next, and in between the catalogue can move: another
 * librarian adds books, or the file is replaced. Applying a plan the operator never saw
 * is the thing worth preventing, so the counts they were shown are hashed into the form
 * and checked against a freshly computed plan before anything is written. A mismatch
 * re-renders the preview instead of importing.
 *
 * The digest itself is App\Books\Import\ImportPlan::digest(); this only carries it.
 *
 * @extends Form<array<string, mixed>>
 */
final class RunImportForm extends Form implements InputFilterProviderInterface
{
    public function __construct(string $digest, string $buttonLabel = 'Run this import')
    {
        parent::__construct('run_import');

        $this->setAttribute('method', 'POST');

        $this->add([
            'name'    => 'security',
            'type'    => 'csrf',
            //900 seconds, matching SionModel\Form\DeleteEntityForm and
            //App\Books\RefreshSortForm — the application's other two confirmations.
            'options' => ['csrf_options' => ['timeout' => 900]],
        ]);
        $this->add([
            'name'       => 'digest',
            'type'       => 'Hidden',
            'attributes' => ['value' => $digest],
        ]);
        $this->add([
            'name'       => 'submit',
            'type'       => 'Submit',
            'attributes' => [
                'value' => $buttonLabel,
                'id'    => 'run-import',
                'class' => 'btn-warning',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputFilterSpecification(): array
    {
        return [
            //Restated rather than left to the element — see SionModel\Form\CsrfSpec.
            'security' => CsrfSpec::forElement($this->get('security')),
            'digest'   => [
                'required'   => true,
                'filters'    => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        //`ImportPlan::digest()` is a sha1, so the field's whole domain is
                        //forty lowercase hex characters — a shape check rather than a length
                        //bound because the shape is known exactly. The controller compares
                        //the posted value against a freshly computed digest anyway, so this
                        //changes no verdict; what it changes is that a hostile value is
                        //refused by the form rather than reaching a string comparison, and
                        //that the field stops reading as unvalidated to anyone auditing it.
                        'name'    => Regex::class,
                        'options' => ['pattern' => '/\A[0-9a-f]{40}\z/'],
                    ],
                ],
            ],
        ];
    }
}
