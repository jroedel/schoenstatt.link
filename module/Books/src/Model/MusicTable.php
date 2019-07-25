<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Model\SchoenstattTable;

class MusicTable extends SionTable
{
    protected function processCompositionRow($row)
    {
        static $identifierFilter;
        if (!isset($identifierFilter)) {
            $identifierFilter = new ToSchoenstattLinkIdentifier('composition');
        }
        $id = $this->filterDbId($row['CompositionId']);
        $identifier = $identifierFilter->filter($id);
        
        $name = $row['CompositionName'];
        $slug = $row['Slug'];
        if (!isset($slug)) {
            $slug = SchoenstattTable::getSlug($name);
            if (isset($slug)) {
                $this->slylyUpdateCompositionSlug($id, $slug);
            }
        }
        
        $data = [
            'compositionId' => $id,
            'name' => $name,
            'disambiguatingDescription' => $row['DisambiguatingDescription'],
            'slug' => $slug,
            'inLanguage' => $row['InLanguage'],
            'country' => $row['Country'],
            'yearPublished' => $row['YearPublished'],
            'composerText' => $this->filterDbArray($row['ComposerText']),
            'lyricistText' => $this->filterDbArray($row['LyricistText']),
            'tags' => $this->filterDbArray($row['Tags']),
            'derivedFromCompositionId' => $this->filterDbId($row['DerivedFromCompositionId']),
            'lyrics' => $row['Lyrics'],
            'chordProSpec' => $row['ChordProSpec'],
            'lilyPondSpec' => $row['LilyPondSpec'],
            'musicalKey' => $row['MusicalKey'],
            'alternateKey' => $row['AlternateKey'],
            'alternateKeyLabel' => $row['AlternateKeyLabel'],
            'copyrightInfo' => $row['CopyrightInfo'],
            'copyrightContactEmail' => $row['CopyrightContactEmail'],
            'url1' => $row['Url1'],
            'url1Label' => $row['Url1Label'],
            'url2' => $row['Url2'],
            'url2Label' => $row['Url2Label'],
            'url3' => $row['Url3'],
            'url3Label' => $row['Url3Label'],
            'createdOn'             => $this->filterDbDate($row['CreatedOn']),
            'createdBy'             => $this->filterDbId($row['CreatedBy']),
            'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
            'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
            
            'identifier' => $identifier,
            //these fields integrate composerText and authors linked through predicates
            'composersAll' => [],
            'lyricistsAll' => [],
        ];
        return $data;
    }
    
    public function linkCompositions(&$objects)
    {
        //get linked composers/lyricists
        
        //link 'em up
    }
    
    protected function slylyUpdateCompositionSlug($compositionId, $slug)
    {
        $gateway = $this->getTableGatewayForEntity('composition');
        $result = $gateway->update(['Slug' => $slug], ['CompositionId' => $compositionId]);
        return $result;
    }
    
    public function getTagsValueOptions()
    {
        $cacheKey = 'composition-tags-value-options';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $objects = $this->getObjects('composition');
        $valueOptions = [];
        foreach ($objects as $object) {
            foreach ($object['tags'] as $tag) {
                if (!isset($valueOptions[$tag])) {
                    $valueOptions[$tag] = $tag;
                }
            }
        }
        $this->cacheEntityObjects($cacheKey, $valueOptions, ['composition']);
        return $valueOptions;
    }
    
    public function getCompositionValueOptions()
    {
        $cacheKey = 'composition-value-options';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $valueOptions = [];
        $objects = $this->getObjects('composition');
        foreach ($objects as $object) {
            if (isset($object['disambiguatingDescription'])) {
                $label = sprintf("%s (%s)", $object['name'], $object['disambiguatingDescription']);
            } else {
                $label = $object['name'];
            }
            $valueOptions[$object['compositionId']] = $label;
        }
        $this->cacheEntityObjects($cacheKey, $valueOptions, ['composition']);
        return $valueOptions;
    }
    
    public function getAuthorTextValueOptions()
    {
        $cacheKey = 'composition-author-texts';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $sql = "SELECT Author FROM
(SELECT DISTINCT `ComposerText` AS Author FROM `mus_compositions` a
UNION SELECT DISTINCT `LyricistText` AS Author FROM `mus_compositions` b) e
GROUP BY Author ORDER BY Author";
        $results = $this->fetchSome(null, $sql, null);
        
        $authors = [];
        $authorConcatenations = []; //these might be repeated so we have to check
        foreach ($results as $row) {
            $author = $this->filterDbString($row['Author']);
            if (isset($author)) {
                if (false !== strpos($author, '|')) {
                    $authorsList = $this->filterDbArray($author);
                    foreach ($authorsList as $author) {
                        $authorConcatenations[$author] = $author;
                    }
                } else {
                    $authors[$author] = $author;
                }
            }
        }
        //factor in the concatenated authors, making sure not to push duplicates
        foreach ($authorConcatenations as $key => $value) {
            if (!isset($authors[$key])) {
                $authors[$key] = $value;
            }
        }
        $this->cacheEntityObjects($cacheKey, $authors, ['composition']);
        return $authors;
    }
}
