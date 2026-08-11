<?php

declare(strict_types=1);

namespace App\Form;

use Laminas\Escaper\Escaper;
use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Csrf;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Textarea;
use Laminas\Form\ElementInterface;
use Laminas\Form\FormInterface;

use function implode;
use function is_array;
use function is_bool;
use function array_flip;
use function array_intersect_key;
use function is_object;
use function is_scalar;
use function method_exists;
use function strip_tags;
use function sprintf;

/**
 * The form layer a Symfony-served route did not have.
 *
 * `docs/strangler.md` names this as the single largest thing between the migration
 * and its end: every create/edit/delete page renders a `Laminas\Form` through
 * laminas' form view helpers, and a Symfony-served route cannot call a view helper —
 * the helper plugin manager is built from the MVC event. So the markup has to be
 * produced by something else, and this is that something.
 *
 * ## It reproduces TwbBundle, deliberately and exactly
 *
 * The laminas side renders through `SionModel\Form\View\Helper\SionFormRow`, which
 * extends `TwbBundle\Form\View\Helper\TwbBundleFormRow` — Bootstrap 3 markup. This
 * class emits the same bytes rather than "equivalent" markup, for two reasons that
 * are really one: `public/css/gen-basic.css` and the selectize/markdown JS bundle
 * are both written against that exact structure, and **production still serves the
 * laminas rendering** while the capsule serves this one. A page that differs
 * structurally is a page that looks different depending on which front controller
 * answered it.
 *
 * The details that are not obvious and are all load-bearing:
 *
 * - **Attribute order is `type`, `name`, the element's declared attributes in
 *   declaration order, `class`, `value`.** That falls out of how
 *   `Laminas\Form\View\Helper\FormInput` builds its array, and it is the order the
 *   captured baseline has.
 * - **A false boolean attribute is omitted, a true one is rendered bare** —
 *   `required`, not `required="required"`.
 * - **`<div class="form-group ">` carries a trailing space.** TwbBundle concatenates
 *   an (empty) state class onto the group class. It is in the baseline, so it is
 *   here.
 * - **`formHidden()` emits no `class`, but a hidden rendered through a row does.**
 *   `associationId` goes through the first and the CSRF token through the second, so
 *   the two hidden inputs on this form genuinely differ.
 * - **Escaping is laminas-escaper's**, not Twig's, so `&#x20;` and friends appear in
 *   attribute values exactly where the baseline has them.
 *
 * ## What it does not do
 *
 * Only the element types the association form uses are implemented, and an unknown
 * one throws rather than falling back to a plain text input — a silent fallback would
 * render a `Select` as an empty box and lose the value on save. `Collection`,
 * `File`, `DateSelect` and the rest are absent because no ported form has one yet;
 * add them when a form that needs them moves.
 *
 * Translation is a callable rather than a translator instance, so this class needs
 * nothing from laminas-i18n: the caller passes App\Twig\LaminasExtension's translate.
 */
final class BootstrapFormRenderer
{
    private readonly Escaper $escaper;

    /** @param callable(string): string $translate */
    public function __construct(private readonly mixed $translate)
    {
        $this->escaper = new Escaper('utf-8');
    }

    /** @param FormInterface<array<string, mixed>> $form */
    public function open(FormInterface $form, string $action): string
    {
        $name = (string) $form->getName();

        return sprintf(
            '<form method="POST" name="%s" action="%s" class="form-horizontal" id="%s">',
            $this->escaper->escapeHtmlAttr($name),
            $this->escaper->escapeHtmlAttr($action),
            $this->escaper->escapeHtmlAttr($name)
        );
    }

    public function close(): string
    {
        return '</form>';
    }

    /**
     * A whole labelled row: the shape `formRow()` produces for everything the
     * association form renders through it.
     */
    public function row(ElementInterface $element, bool $translateOptions = true): string
    {
        //TwbBundle wraps nothing around a hidden input — there is no label, no help and
        //nothing to lay out. The CSRF token is the one element on this form that
        //reaches row() and is hidden, and a stray form-group around it adds vertical
        //space above the submit button.
        if ($element instanceof Csrf || 'hidden' === $element->getAttribute('type')) {
            return $this->element($element);
        }

        if ($element instanceof Checkbox) {
            //A checkbox is its own label, so TwbBundle emits no separate one.
            return '<div class="form-group ">' . $this->element($element) . $this->errors($element)
                . $this->rowHelpBlock($element) . '</div>';
        }

        return '<div class="form-group ">'
            . $this->label($element, withFor: false)
            . $this->element($element, $translateOptions)
            . $this->errors($element)
            . $this->rowHelpBlock($element)
            . '</div>';
    }

    /**
     * TwbBundle's help block, which is **not** SionModel's — the two differ and both
     * appear on this page.
     *
     * `TwbBundleFormRow::renderHelpBlock()` translates first, then escapes *only if
     * the text contains no tags at all* (`strip_tags($t) === $t`). So `phone1`'s help
     * arrives with `&#039;` for its apostrophe while
     * `openingHoursSpecificationJson`'s keeps a live `<a href>`. Reproducing the rule
     * rather than picking one behaviour is what makes the two renderings match; it is
     * also, for the record, an XSS footgun in the original — a help block is authored
     * in PHP, so nothing user-supplied reaches it today.
     */
    private function rowHelpBlock(ElementInterface $element): string
    {
        $text = $element->getOption('help-block');
        if (! is_scalar($text) || '' === (string) $text) {
            return '';
        }

        $translated = ($this->translate)((string) $text);
        if (strip_tags($translated) === $translated) {
            $translated = $this->escaper->escapeHtml($translated);
        }

        return sprintf('<p class="help-block">%s</p>', $translated);
    }

    /**
     * `formLabel()`'s output, which carries a `for` — unlike the label `formRow()`
     * writes. The association form's hand-built first two groups use this one and the
     * rest use the row, so both forms of label appear on the page.
     */
    public function label(ElementInterface $element, bool $withFor = true): string
    {
        $label = $element->getLabel();
        if (null === $label || '' === $label) {
            return '';
        }

        $text = $this->escaper->escapeHtml(($this->translate)($label));

        if (! $withFor) {
            return '<label>' . $text . '</label>';
        }

        return sprintf(
            '<label for="%s">%s</label>',
            $this->escaper->escapeHtmlAttr((string) $element->getName()),
            $text
        );
    }

    /** The control itself, with no label, errors or help block around it. */
    public function element(ElementInterface $element, bool $translateOptions = true): string
    {
        if ($element instanceof Checkbox) {
            return $this->checkbox($element);
        }
        if ($element instanceof Select) {
            return $this->select($element, $translateOptions);
        }
        if ($element instanceof Textarea) {
            return $this->textarea($element);
        }
        if ($element instanceof Submit) {
            return $this->submit($element);
        }
        if ($element instanceof Csrf) {
            return $this->input($element, 'hidden', withClass: true);
        }

        $type = $element->getAttribute('type');

        return $this->input($element, is_scalar($type) ? (string) $type : 'text', withClass: true);
    }

    /** `formHidden()`: no `class`, unlike a hidden rendered through a row. */
    public function hidden(ElementInterface $element): string
    {
        return $this->input($element, 'hidden', withClass: false);
    }

    /**
     * `<button type="submit">`, keeping the element's own classes.
     *
     * A submit button is the one control that must *not* get `form-control` — it is a
     * `btn`, and TwbBundle appends that to whatever the element declares rather than
     * replacing it. `value` renders last, as it does on every other input.
     */
    public function submit(ElementInterface $element): string
    {
        $declared = $this->declaredAttributes($element);
        $value    = (string) ($declared['value'] ?? 'Submit');
        $class    = isset($declared['class']) ? (string) $declared['class'] : '';
        unset($declared['value'], $declared['class'], $declared['type'], $declared['name']);

        /** @var array<string, scalar> $attributes */
        $attributes = ['type' => 'submit', 'name' => (string) $element->getName()];
        foreach ($declared as $key => $declaredValue) {
            if (is_scalar($declaredValue)) {
                $attributes[(string) $key] = $declaredValue;
            }
        }
        $attributes['class'] = '' === $class ? 'btn' : $class . ' btn';
        $attributes['value'] = '' === $value ? 'Submit' : $value;

        return sprintf(
            '<button %s>%s</button>',
            $this->attributeString($attributes),
            $this->escaper->escapeHtml(($this->translate)($attributes['value']))
        );
    }

    /**
     * A value as laminas would print it: a scalar directly, and an object through its
     * `__toString()`. The second case is `geoPoint`, whose stored value is a
     * `SionModel\Db\GeoPoint` — treating it as unprintable renders the field empty
     * and a moderator who saves the form then clears the shrine's coordinates.
     */
    private static function asString(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        return is_object($value) && method_exists($value, '__toString') ? (string) $value : '';
    }

    /** @return array<string, bool|float|int|string|null> */
    private function declaredAttributes(ElementInterface $element): array
    {
        return $element->getAttributes();
    }

    /** TwbBundle's `<p class="help-block">`, via SionModel's HelpBlock helper. */
    public function helpBlock(ElementInterface $element): string
    {
        $text = $element->getOption('help-block');
        if (! is_scalar($text) || '' === (string) $text) {
            return '';
        }

        //Escape then translate, in that order — SionModel\View\Helper\HelpBlock does
        //exactly this, which is why the baseline's help blocks contain `&#039;` inside
        //otherwise untranslated English. Reversing it would change the bytes.
        return sprintf('<p class="help-block">%s</p>', ($this->translate)(
            $this->escaper->escapeHtml((string) $text)
        ));
    }

    /**
     * Validation messages, as `formElementErrors()` renders them.
     *
     * Not translated here, and `formElementErrors()` no longer translates either —
     * both were switched off together. A message arrives already interpolated,
     * because laminas substitutes `%value%`, `%hostname%` and `%min%` inside the
     * validator, so translating at this point looked up the *user's input*: a
     * translator miss is what files a phrase, and six strangers' mistyped email
     * hostnames ended up as permanent rows in a table other accounts can read.
     * `AbstractValidator`'s default translator now translates the message templates
     * instead, before interpolation — see JTranslate\Module::onBootstrap and
     * App\Laminas\TranslatorConfigurator. Translating in both places would translate
     * twice.
     */
    public function errors(ElementInterface $element): string
    {
        $messages = $element->getMessages();
        if ([] === $messages) {
            return '';
        }

        $items = '';
        foreach ($messages as $message) {
            if (! is_scalar($message)) {
                continue;
            }
            $items .= '<li>' . $this->escaper->escapeHtml((string) $message) . '</li>';
        }

        return '' === $items ? '' : '<ul class="help-block">' . $items . '</ul>';
    }

    // --------------------------------------------------------------- controls

    private function input(ElementInterface $element, string $type, bool $withClass): string
    {
        $attributes = $this->attributes(
            $element,
            ['type' => $type, 'name' => (string) $element->getName()],
            $withClass
        );

        $attributes['value'] = self::asString($element->getValue());

        return '<input ' . $this->attributeString($attributes) . '>';
    }

    private function textarea(ElementInterface $element): string
    {
        $attributes = $this->attributes($element, ['name' => (string) $element->getName()]);

        return sprintf(
            '<textarea %s>%s</textarea>',
            $this->attributeString($attributes),
            $this->escaper->escapeHtml(self::asString($element->getValue()))
        );
    }

    /**
     * A `Checkbox` with `use_hidden_element`, which every checkbox on this form has:
     * a hidden input carrying the unchecked value, then the box inside its own label.
     */
    private function checkbox(Checkbox $element): string
    {
        $name      = (string) $element->getName();
        $unchecked = $element->getUncheckedValue();
        $checked   = $element->getCheckedValue();

        $markup = '';
        if ($element->useHiddenElement()) {
            $markup .= sprintf(
                '<input type="hidden" name="%s" value="%s">',
                $this->escaper->escapeHtmlAttr($name),
                $this->escaper->escapeHtmlAttr((string) $unchecked)
            );
        }

        $attributes = $this->attributes(
            $element,
            ['type' => 'checkbox', 'name' => $name],
            withClass: false
        );
        //`value` is the checked value, not the current one, and `checked` is what
        //carries the state.
        $attributes['value'] = (string) $checked;
        unset($attributes['required']);

        $current = $element->getValue();
        if ((string) $current === (string) $checked) {
            $attributes['checked'] = true;
        }

        return $markup . sprintf(
            '<label><input %s> %s</label>',
            $this->attributeString($attributes),
            $this->escaper->escapeHtml(($this->translate)((string) $element->getLabel()))
        );
    }

    private function select(Select $element, bool $translateOptions = true): string
    {
        $attributes = $this->attributes($element, ['name' => (string) $element->getName()]);
        //Laminas\Form\View\Helper\FormSelect renders only these; `maxlength`, which
        //three of this form's selects declare, is silently dropped there and must be
        //dropped here too.
        $attributes = array_intersect_key(
            $attributes,
            array_flip(['name', 'autocomplete', 'autofocus', 'disabled', 'form', 'multiple',
                        'required', 'size', 'class'])
        );

        $options = '';
        $empty   = $element->getOption('empty_option');
        if (null !== $empty) {
            $options .= sprintf(
                '<option value="">%s</option>' . "\n",
                $this->escaper->escapeHtml(is_scalar($empty) ? (string) $empty : '')
            );
        }

        $selected = $element->getValue();
        foreach ($element->getValueOptions() as $value => $label) {
            if (is_array($label)) {
                //Option groups: no association-form select uses one, and rendering it
                //wrongly would silently drop every option inside. Skipped loudly rather
                //than half-rendered.
                continue;
            }
            $options .= sprintf(
                '<option value="%s"%s>%s</option>' . "\n",
                $this->escaper->escapeHtmlAttr((string) $value),
                (string) $value === (string) (is_scalar($selected) ? $selected : '') ? ' selected' : '',
                $this->escaper->escapeHtml($translateOptions ? ($this->translate)((string) $label) : (string) $label)
            );
        }

        return sprintf('<select %s>%s</select>', $this->attributeString($attributes), $options);
    }

    // -------------------------------------------------------------- plumbing

    /**
     * The element's attributes, in the order laminas renders them: the seeds first
     * (`type`, `name`), then whatever the element declares, then `class`.
     *
     * `name` and `type` are removed from the declared set before merging so that an
     * element which happens to declare one does not move it to the end.
     *
     * @param array<string, string> $seed
     * @return array<string, scalar>
     */
    private function attributes(ElementInterface $element, array $seed, bool $withClass = true): array
    {
        $declared = $element->getAttributes();
        unset($declared['name'], $declared['type'], $declared['value'], $declared['class']);

        /** @var array<string, scalar> $attributes */
        $attributes = $seed;
        foreach ($declared as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $attributes[(string) $key] = $value;
        }

        if ($withClass) {
            $attributes['class'] = 'form-control';
        }

        return $attributes;
    }

    /**
     * `false` is dropped, `true` renders bare. Everything else is escaped as an
     * attribute value.
     *
     * @param array<string, scalar> $attributes
     */
    private function attributeString(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $parts[] = $this->escaper->escapeHtmlAttr($key);
                }
                continue;
            }
            $parts[] = sprintf(
                '%s="%s"',
                $this->escaper->escapeHtmlAttr($key),
                $this->escaper->escapeHtmlAttr((string) $value)
            );
        }

        return implode(' ', $parts);
    }
}
