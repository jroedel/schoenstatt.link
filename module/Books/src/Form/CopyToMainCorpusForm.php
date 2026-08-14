<?php

namespace Books\Form;

use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

/**
 * Confirms copying a data-sourced publication into the main corpus.
 *
 * The form exists because the copy used to happen on a **plain GET**:
 * `PublicationsController::copyToMainCorpusAction()` called
 * `copyPublicationToMainCorpus()` — an INSERT — with no method check and no
 * confirmation, so a link prefetch, a "open all in tabs", or an accidental click
 * silently duplicated a publication. The route's `pub_moderator` guard kept that
 * away from the public, but nine effective roles hold it, and browsers prefetch
 * links a signed-in moderator has merely hovered over.
 *
 * ## Two elements, and deliberately no Cancel
 *
 * `SionModel\Form\DeleteEntityForm` carries a long docblock about its Cancel
 * button, which for years rendered as `type="submit"` and therefore *deleted* the
 * record it was offering to spare. Rather than repeat the shape and rely on
 * getting the type right, cancelling here is an ordinary `<a>` back to the
 * publication — not a form element at all, so there is no submission it could
 * ever be mistaken for.
 *
 * The 900-second CSRF timeout matches DeleteEntityForm's: long enough to read a
 * confirmation page, short enough that a token left open in a tab overnight is
 * not still live.
 */
class CopyToMainCorpusForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('publication_copy_to_main_corpus');

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
                'value' => 'Copy into main corpus',
                'id' => 'copy-to-main-corpus-submit',
                'class' => 'btn btn-primary',
            ],
        ]);
    }

    /**
     * Nothing to specify: `security` brings its own CSRF validator and `submit` is a
     * button rather than data. Returning an empty spec is what DeleteEntityForm does,
     * and the fuzz harness reads it — an element missing from here would silently get
     * `['required' => false]` and no validation at all, which is the trap
     * test/Fuzz/FormValidationContractTest exists to catch.
     *
     * @return array<string, mixed>
     */
    public function getInputFilterSpecification()
    {
        return [];
    }
}
