<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Model\SchoenstattTable;
use ChordPro\GuessKey;
use ChordPro\MonospaceFormatter;
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

    public function importMusicasJuly2019()
    {
        $ids = [45,141,27,89,243,49,267,1,29,307,114,142,118,355,335,62,36,37,215,248,61,270,193,
            324,119,35,113,253,222,41,282,328,350,60,13,58,18,345,56,172,334,12,348,169,221,100,
            134,297,145,11,202,296,44,48,101,127,103,244,8,125,365,6,316,290,213,226,351,358,110,
            327,294,136,204,190,314,188,43,17,42,220,281,227,164,137,195,235,81,4,255,217,315,363,
            269,109,82,232,285,168,234,54,165,228,280,151,176,246,247,28,64,21,85,19,233,34,258,
            357,219,105,271,278,287,323,157,353,7,68,153,53,115,181,325,57,317,340,38,279,124,225,
            173,241,183,66,23,167,274,308,326,322,251,143,344,46,367,209,192,166,362,295,139,218,
            10,318,284,277,266,292,236,206,121,25,163,88,16,208,55,298,15,123,275,260,203,133,179,
            191,122,9,92,130,160,154,194,332,343,360,132,245,155,152,249,212,178,180,242,93,333,
            331,26,310,91,356,108,162,230,239,339,131,47,161,250,238,346,120,216,341,309,338,
            106,214,67,135,223,330,273,342,210,69,177,73,320,50,321,359,33,354,116,65,20,347,22,
            364,319,70,39,144,337,207,99,40,170,329,368,205,313,128,366,336,237,175,240,254,126,
            311,229,189,286,80,159,312,71,231,31,289,140,252,107,276,352,86,156,305,361,349,104,
            32,257,72,306,30,83,304,272,256,111,293,129,14,288,3,90,5,224,268,117,51,2,196,63,102,
            174,283,84,112,158,87,259,74,138,24,182,59,291];
        $objects = $this->queryObjects('composition', ['compositionId' => $ids]);
        $parser = new \ChordPro\Parser();
        //regex to fix extra metadata
        $re = '/({t:[^}]+})(.*)$/m';
        $subst = '\\1';
        $guessKey = new GuessKey();
        $monospace = new MonospaceFormatter();
        $count = 0;
        foreach ($objects as $object) {
            if (! isset($object['disambiguatingDescription'])) {
                continue;
            }

            //import file
            $filename = "data/musicas/" . $object['disambiguatingDescription'];
            if (file_exists($filename)) {
                $myfile = fopen($filename, "r");
                if (false === $myfile) {
                    throw new \Exception("Error reading $filename");
                }
                $text = trim(fread($myfile, filesize($filename)));

                $text = preg_replace($re, $subst, $text);
                $data = ['chordProSpec' => $text];
                fclose($myfile);
                // Create song object after parsing txt
                $song = $parser->parse($text);
                $key = $song->getKey([]);
                if (! isset($key)) {
                    $key = $guessKey->guessKey($song);
                    var_dump($key);
                }
                if (isset($key)) {
                    $data['musicalKey'] = $key;
                    $data['originalKey'] = $key;
                }
                $data['lyrics'] = $monospace->format($song, ['no_chords' => true]);

                //delete disambiguating description
                $data['disambiguatingDescription'] = null;

                //update
                $this->updateEntity('composition', $object['compositionId'], $data, [], false);
                $count++;
            }
        }
        return $count;
    }
}
