<?php

namespace Books\Form;

use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

/**
 * Confirms creating a new edition of a publication.
 *
 * The same shape as `CopyToMainCorpusForm`, for the same reason. Until 2026-09-08
 * `PublicationsController::createNewEditionAction()` **created the row on a plain GET**:
 * no method check, no token, no confirmation — one INSERT and a redirect to the new
 * row's edit form. The route's `pub_moderator` guard kept it from the public, but nine
 * effective roles hold that, and a browser prefetching a link a moderator merely hovered
 * over was enough to file a new publication. The copy-to-main-corpus action had exactly
 * this until 2026-08-14 and got a confirmation then; this one was left as it was because
 * nothing measured it, and it was found on porting.
 *
 * Two elements and deliberately no Cancel — cancelling is an ordinary link back to the
 * publication in the template, so there is no submission it could be mistaken for. See
 * `CopyToMainCorpusForm` for the history behind that choice.
 *
 * The 900-second CSRF timeout matches the application's other confirmation forms.
 */
class CreateNewEditionForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('publication_create_new_edition');

        $this->setAttribute('method', 'post');

        $this->add([
            'name' => 'security',
            'type' => 'csrf',
            'options' => [
                'csrf_options' => [
                    'timeout' => 900,
                ],
            ],
        ]);
        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Add another edition',
                'id' => 'create-new-edition-submit',
                'class' => 'btn btn-primary',
            ],
        ]);
    }

    /**
     * Nothing to specify: `security` brings its own CSRF validator and `submit` is a
     * button rather than data. See `CopyToMainCorpusForm::getInputFilterSpecification()`
     * for why the empty array is stated rather than the method omitted.
     *
     * @return array<string, mixed>
     */
    public function getInputFilterSpecification()
    {
        return [];
    }
}
