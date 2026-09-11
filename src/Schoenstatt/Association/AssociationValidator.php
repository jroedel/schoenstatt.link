<?php

declare(strict_types=1);

namespace App\Schoenstatt\Association;

use Schoenstatt\Form\AssociationForm;
use SionModel\Form\Element\Registry;
use SionModel\Form\Validation\FormSpecification;
use SionModel\Form\Validation\InputFilter as Engine;

/**
 * The association rules as something an API request can run, built with no MVC, no
 * modules, no merged config and no session.
 *
 * ## Why this builds the form rather than the specification directly
 *
 * The obvious implementation is an engine over {@see AssociationInputFilterSpec}, and
 * the first version of this class was exactly that. It was wrong for a reason that is
 * invisible from the specification alone: `Laminas\Form\Form::getInputFilter()` built
 * an input per **element** as well as per spec key and merged the two, so the form the
 * moderator submitted was held to rules the specification never mentioned — twelve
 * fields' worth on this form (a `Regex` on `email`, a `Uri` on each of the four URLs,
 * `Date` and `GreaterThan` on `foundationDate`, an `InArray` on each of the six
 * checkboxes). A filter over the specification alone would have been **looser than the
 * web form**, and an agent could have stored a malformed URL that a moderator could not.
 *
 * Since the validation cutover all twelve are stated in the specification, so that
 * particular gap is closed — and this still goes through the form, and should.
 * `AssociationForm` composes its specification out of `AssociationInputFilterSpec` *plus*
 * what the form adds around it, and asking the form is what keeps "the API is held to the
 * web form's rules" true by construction rather than by two lists someone maintains.
 * `test/Integration/AssociationValidationParityTest` checks both halves: that the
 * specification is still the whole of it, and that the CSRF key is the only thing the API
 * drops.
 *
 * ## Why that costs nothing
 *
 * Validating with a form needs no MVC stack: rendering one does (view helpers, the
 * plugin managers the layout pulls in), validating does not. So a headless
 * `AssociationForm` is available on a Symfony-served route today, and the engine it
 * validates through — {@see Engine} — is the same one every web form uses, resolving
 * its rules out of a bare `ServiceManager`.
 *
 * The elements come from {@see Registry} — the one list every path that builds an element
 * reads, SionModel's `Phone` among them — which the form's own factory reaches without
 * being told, so this path needs no module config and no container.
 *
 * ## The CSRF seam
 *
 * The form carries a `security` CSRF element, and `Laminas\Validator\Csrf` reads a
 * `Laminas\Session\Container`. An API request has no session, so leaving the rule in
 * place would be a fatal rather than a validation failure — and would refuse every
 * agent regardless. Its key is dropped from the specification here, and that removal is
 * the *entire* difference between what an agent is held to and what a moderator is held
 * to. `AssociationValidationParityTest` asserts it is the only one.
 */
final class AssociationValidator
{
    /**
     * The one input an API caller is not held to, because it presupposes a browser
     * session. See the class docblock.
     */
    public const SESSION_ONLY_INPUT = 'security';

    public function __construct(private readonly AssociationFieldDomains $domains)
    {
    }

    /**
     * Build the domains from the application's services and wrap them.
     *
     * @param callable(string): mixed $service
     */
    public static function fromServices(callable $service): self
    {
        return new self(AssociationFieldDomains::fromServices($service));
    }

    /**
     * A fresh engine over this form's specification. Never shared: the engine holds the
     * data and messages of whatever was last validated through it, so handing the same
     * instance to two requests would leak one caller's submission into another's.
     *
     * The CSRF rule is removed by dropping its key — the specification is plain data, so
     * "hold the agent to everything except this" is an `unset`, where the laminas filter
     * this replaced needed `has()` and `remove()` on an assembled object.
     */
    public function inputFilter(): Engine
    {
        $spec = $this->specification();
        unset($spec[self::SESSION_ONLY_INPUT]);

        return Engine::withLaminasRules($spec);
    }

    /**
     * The whole form's specification, CSRF included.
     *
     * @return array<string, mixed>
     */
    public function specification(): array
    {
        return FormSpecification::of($this->form());
    }

    /**
     * The field names an API caller may write.
     *
     * @return list<string>
     */
    public function writableFields(): array
    {
        return (new AssociationInputFilterSpec($this->domains))->fieldNames();
    }

    /**
     * A form built without the application: no module loading, no config merge, no
     * MVC event, no session.
     */
    private function form(): AssociationForm
    {
        //Nothing to wire: `SionModel\Form\Fieldset::getFormFactory()` reaches
        //`SionModel\Form\Element\Registry` on its own, and that is the one list every path
        //reads. It took a hand-built `FormElementManager` over a `ServiceManager` here
        //until the form model landed, for the single purpose of naming the same classes.
        $form = new AssociationForm();
        $form->setFieldDomains($this->domains);
        $form->init();

        return $form;
    }
}
