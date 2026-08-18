<?php

declare(strict_types=1);

namespace App\Books\Import;

use Laminas\Form\Form;

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
final class RunImportForm extends Form
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
}
