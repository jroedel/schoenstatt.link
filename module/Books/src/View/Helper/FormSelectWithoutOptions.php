<?php

namespace Books\View\Helper;

use Laminas\Form\Element\Select as SelectElement;
use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\AbstractHelper;
use Laminas\Form\View\Helper\FormSelect;

/**
 * Renders a <select> containing only the options that are currently selected.
 *
 * Used for the author/editor/publication pickers in
 * books/publications/fields-partial.phtml, where shipping the full option list
 * would be enormous and the remaining choices are fetched over AJAX instead.
 *
 * This used to extend Laminas\Form\View\Helper\FormSelect purely to intercept
 * renderOptions(). laminas marked FormSelect `@final`, and a non-subclass
 * cannot intercept a method the parent calls internally, so the narrowing now
 * happens up front: the element is cloned with its value options reduced to the
 * selected set and rendering is delegated to a stock FormSelect.
 */
class FormSelectWithoutOptions extends AbstractHelper
{
    /**
     * @param  ElementInterface|null $element
     * @return string|self
     */
    public function __invoke(?ElementInterface $element = null)
    {
        if (null === $element) {
            return $this;
        }

        return $this->render($element);
    }

    public function render(ElementInterface $element): string
    {
        return $this->formSelect()->render($this->withOnlySelectedOptions($element));
    }

    /**
     * Kept public because it was public on the old subclass.
     *
     * @param array<array-key, mixed> $options
     * @param array<array-key, mixed> $selectedOptions
     */
    public function renderOptions(array $options, array $selectedOptions = []): string
    {
        return $this->formSelect()->renderOptions(
            $this->onlySelected($options, $selectedOptions),
            $selectedOptions
        );
    }

    /**
     * A copy of the element carrying only its selected value options.
     *
     * Anything that is not a Select is passed through untouched so that
     * FormSelect raises its own, more informative, exception.
     */
    private function withOnlySelectedOptions(ElementInterface $element): ElementInterface
    {
        if (! $element instanceof SelectElement) {
            return $element;
        }

        $selected = (array) $element->getValue();

        $narrowed = clone $element;
        $narrowed->setValueOptions($this->onlySelected($element->getValueOptions(), $selected));

        // FormSelect::render() prepends the empty option *after* the point at
        // which the old subclass filtered, so an unselected empty option used
        // to be dropped along with everything else. Preserve that.
        if (null !== $element->getEmptyOption() && ! in_array('', $selected)) {
            $narrowed->setEmptyOption(null);
        }

        return $narrowed;
    }

    /**
     * Reduce $options to the entries named in $selectedOptions.
     *
     * Reproduced from the old subclass including its ordering: the result
     * follows the order of $selectedOptions, not that of $options.
     *
     * @param  array<array-key, mixed> $options
     * @param  array<array-key, mixed> $selectedOptions
     * @return array<array-key, mixed>
     */
    private function onlySelected(array $options, array $selectedOptions): array
    {
        $newOptions = [];
        foreach ($selectedOptions as $value) {
            if (isset($options[$value])) {
                $newOptions[$value] = $options[$value];
            }
        }

        return $newOptions;
    }

    /**
     * A stock FormSelect carrying this helper's view and translator state.
     */
    private function formSelect(): FormSelect
    {
        $helper = new FormSelect();
        $helper->setView($this->getView());

        if ($this->hasTranslator()) {
            $helper->setTranslator($this->getTranslator(), $this->getTranslatorTextDomain());
        }
        $helper->setTranslatorEnabled($this->isTranslatorEnabled());

        return $helper;
    }
}
