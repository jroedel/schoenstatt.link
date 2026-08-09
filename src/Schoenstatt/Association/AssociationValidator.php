<?php

declare(strict_types=1);

namespace App\Schoenstatt\Association;

use Laminas\Form\Factory as FormFactory;
use Laminas\Form\FormElementManager;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\ServiceManager\ServiceManager;
use Schoenstatt\Form\AssociationForm;
use SionModel\Form\Element\Phone;

/**
 * The association rules as something an API request can run: one `InputFilter`,
 * built with no MVC, no modules, no merged config and no session.
 *
 * ## Why this builds the form instead of the specification
 *
 * The obvious implementation is `new InputFilter\Factory()` over
 * {@see AssociationInputFilterSpec}, and the first version of this class was exactly
 * that. It was wrong, and the way it was wrong is worth recording because it is
 * invisible from the specification alone.
 *
 * `Laminas\InputFilter\BaseInputFilter::add()` does **not** replace an input that
 * already exists — it merges the new one into the original. `Form::getInputFilter()`
 * adds the element-derived inputs first and the form's specification second, so a
 * field ends up with the union of both validator chains, not the specification's
 * alone. Measured on this form, twelve fields carry a validator the specification
 * never mentions:
 *
 * | field                          | contributed by the element |
 * |--------------------------------|----------------------------|
 * | `email`                        | `Regex`                    |
 * | `url1`, `url2`, `url3`, `facebookUrl` | `Uri`               |
 * | `foundationDate`               | `Date`, `GreaterThan`      |
 * | the six checkboxes             | `InArray`                  |
 *
 * A bare filter over the specification would therefore have been **looser than the
 * web form** on exactly those twelve — an agent could store a malformed URL that a
 * moderator could not. Since the whole point is that the two surfaces validate
 * identically, the API takes the form's own filter and parity stops being something
 * anyone has to maintain.
 *
 * ## Why that costs nothing
 *
 * `laminas-form` requires `laminas-escaper`, `laminas-filter`, `laminas-hydrator`,
 * `laminas-inputfilter`, `laminas-servicemanager`, `laminas-stdlib` and
 * `laminas-validator` — and **not** `laminas-mvc`. Rendering a form needs the MVC
 * stack (view helpers, the plugin managers the layout pulls in); *validating* with
 * one does not. So a headless `AssociationForm` is available on a Symfony-served
 * route today and will still be available after laminas-mvc is removed.
 *
 * The only thing `init()` needs beyond the defaults is SionModel's `Phone` element,
 * registered below as the single invokable rather than by loading SionModel's module
 * config.
 *
 * ## The CSRF seam
 *
 * The form carries a `security` CSRF element, and `Laminas\Validator\Csrf` reads a
 * `Laminas\Session\Container`. An API request has no session, so leaving the input in
 * place would be a fatal rather than a validation failure — and would refuse every
 * agent regardless. It is removed here, and that removal is the *entire* difference
 * between what an agent is held to and what a moderator is held to.
 * `test/Integration/AssociationValidationParityTest` asserts it is the only one.
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
     * A fresh input filter. Never shared: `InputFilter` holds the data and messages of
     * whatever was last validated through it, so handing the same instance to two
     * requests would leak one caller's submission into another's.
     *
     * @return InputFilterInterface<array<string, mixed>>
     */
    public function inputFilter(): InputFilterInterface
    {
        $filter = $this->form()->getInputFilter();

        if ($filter->has(self::SESSION_ONLY_INPUT)) {
            $filter->remove(self::SESSION_ONLY_INPUT);
        }

        return $filter;
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
        $elements = new FormElementManager(new ServiceManager(), [
            'invokables' => ['Phone' => Phone::class],
        ]);

        $form = new AssociationForm();
        $form->setFormFactory(new FormFactory($elements));
        $form->setFieldDomains($this->domains);
        $form->init();

        return $form;
    }
}
