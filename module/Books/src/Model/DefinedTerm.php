<?php
namespace Books\Model;

use Spatie\SchemaOrg\Intangible;

/**
 * A word, name, acronym, phrase, etc. with a formal definition.
 * Often used in the context of category or subject classification,
 * glossaries or dictionaries, product or creative work types, etc.
 * Use the name property for the term being defined, use termCode if the
 * term has an alpha-numeric code allocated, use description to provide the
 * definition of the term.
 *
 * @see http://schema.org/DefinedTerm
 */
class DefinedTerm extends Intangible
{
    /**
     * A DefinedTermSet that contains this term.
     *
     * @param DefinedTermSet|DefinedTermSet[]|string|string[] $
     *
     * @return static
     *
     * @see https://pending.schema.org/inDefinedTermSet
     */
    public function inDefinedTermSet($inDefinedTermSet)
    {
        return $this->setProperty('inDefinedTermSet', $inDefinedTermSet);
    }

    /**
     * A Defined Term contained in this term set.
     *
     * @param string|string[] $
     *
     * @return static
     *
     * @see https://pending.schema.org/termCode
     */
    public function termCode($termCode)
    {
        return $this->setProperty('termCode', $termCode);
    }
}
