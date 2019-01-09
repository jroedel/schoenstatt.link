<?php
namespace Books\Model;

use Spatie\SchemaOrg\CreativeWork;

/**
 * A set of defined terms for example a set of categories or a classification scheme,
 * a glossary, dictionary or enumeration.
 *
 * @see http://schema.org/DefinedTermSet
 */
class DefinedTermSet extends CreativeWork
{
    
    /**
     * A Defined Term contained in this term set.
     *
     * @param DefinedTerm|DefinedTerm[]|string|string[] $
     *
     * @return static
     *
     * @see http://schema.org/hasDefinedTerm
     */
    public function hasDefinedTerm($hasDefinedTerm)
    {
        return $this->setProperty('hasDefinedTerm', $hasDefinedTerm);
    }
}
