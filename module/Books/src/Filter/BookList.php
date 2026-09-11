<?php
namespace Books\Filter;

use SionModel\Filter\AbstractFilter;

class BookList extends AbstractFilter
{
    /**
     * Pull the five-digit barcodes out of whatever the user pasted in.
     *
     * Anything that is not a string yields no barcodes rather than an exception.
     * This used to `throw new \InvalidArgumentException`, and a filter runs inside
     * InputFilter::isValid(), so the throw escaped as an uncaught exception: a 500
     * with the whole submission lost, reachable by sending
     * `withinLibraryIds[]=x`. The fuzz harness found it.
     *
     * Returning the empty list is the right answer rather than merely the safe
     * one, because of what sits behind this filter at each of its four call sites.
     * CheckinForm, CheckoutForm and InactivationForm all pair it with
     * NotEmpty(EMPTY_ARRAY), so an empty result becomes a proper "no books" field
     * error the user can act on. MassCheckoutFieldset deliberately has no
     * validator there — its rows may be left blank — and an empty list is exactly
     * what a blank row means, so that row is skipped.
     *
     * @todo Allow other barcode patterns for future library schemas
     * {@inheritDoc}
     * @see \SionModel\Filter\FilterInterface::filter()
     */
    public function filter($value)
    {
        if (! is_string($value)) {
            return [];
        }
        $re = '/\d{5,5}/';

        $matches = null;
        preg_match_all($re, $value, $matches, PREG_PATTERN_ORDER, 0);

        return $matches[0];
    }
}
