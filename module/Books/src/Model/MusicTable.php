<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Model\SchoenstattTable;
use Spatie\SchemaOrg\MusicComposition;
use Spatie\SchemaOrg\CreativeWork;

class MusicTable extends SionTable
{
    /**
     *
     * {@inheritDoc}
     * @see \SionModel\Db\Model\SionTable::getSelectPrototype()
     */
    protected function getSelectPrototype($entity)
    {
        $select = parent::getSelectPrototype($entity);
        if ('composition' === $entity) {
            $select->order(['InLanguage', 'CompositionName']);
        }
        return $select;
    }

    protected function processCompositionRow($row)
    {
        static $identifierFilter;
        if (! isset($identifierFilter)) {
            $identifierFilter = new ToSchoenstattLinkIdentifier('composition');
        }
        $id = $this->filterDbId($row['CompositionId']);
        $identifier = $identifierFilter->filter($id);

        $name = $row['CompositionName'];
        $slug = $row['Slug'];
        if (! isset($slug)) {
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
            'originalKey' => $row['OriginalKey'],
            'alternateKey' => $row['AlternateKey'],
            'alternateKeyLabel' => $row['AlternateKeyLabel'],
            'openLicenseUrl' => $row['OpenLicenseUrl'],
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
            'jsonId' => "https://schoenstatt.link/en/" . $identifier,
        ];
        return $data;
    }

    public function getCompositionSchemaV1($object)
    {
        $schema = new MusicComposition();
        $properties = [
            '@id' => $object['jsonId'],
            'name' => $object['name'],
            'inLanguage' => $object['inLanguage'],
            'genre' => 'Religious',
            'isFamilyFriendly' => true,
        ];
        $schema->addProperties($properties);
        if (isset($object['composerText'])) {
            $schema->setProperty('composer', $object['composerText']);
        }
        if (isset($object['lyricistText'])) {
            $schema->setProperty('lyricist', $object['lyricistText']);
        }
        if (isset($object['lyrics'])) {
            $lyrics = new CreativeWork();
            $lyrics->text($object['lyrics']);
            $schema->setProperty('lyrics', $lyrics);
        }
        if (isset($object['musicalKey'])) {
            $schema->setProperty('musicalKey', $object['musicalKey']);
        }
        if (isset($object['yearPublished'])) {
            $schema->setProperty('datePublished', $object['yearPublished']);
        }
        if (isset($object['country'])) {
            $schema->setProperty('locationCreated', $object['country']);
        }
        return $schema;
    }

    public function getCompositionListSchemaV1($objects)//, &$resultingMd5s)
    {
        $schemata = [];
//         $resultingMd5s = [];
        foreach ($objects as $object) {
            $schema = $this->getAssociationSchemaV1($object);
//             $resultingMd5s[$object['jsonId']] = $object['schemaOrgJsonMd5V1ByLocale'][$locale];
            $array = $schema->toArray();
            $schemata[] = $array;
        }
        return $schemata;
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
                if (! isset($valueOptions[$tag])) {
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
            if (! isset($authors[$key])) {
                $authors[$key] = $value;
            }
        }
        $this->cacheEntityObjects($cacheKey, $authors, ['composition']);
        return $authors;
    }
}
