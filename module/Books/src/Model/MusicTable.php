<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;

class MusicTable extends SionTable
{
    protected function processCompositionRow($row)
    {
        $data = [
            'compositionId' => $row['CompositionId'],
            'name' => $row['Name'],
            'disambiguatingDescription' => $row['DisambiguatingDescription'],
            'slug' => $row['Slug'],
            'inLanguage' => $row['InLanguage'],
            'country' => $row['Country'],
            'yearPublished' => $row['YearPublished'],
            'composerText' => $row['ComposerText'],
            'lyricistText' => $row['LyricistText'],
            'tags' => $row['Tags'],
            'derivedFromCompositionId' => $row['DerivedFromCompositionId'],
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
        ];
        return $data;
    }
    
    public function linkCompositions(&$objects)
    {
        //get linked composers/lyricists
        
        //link 'em up
    }
}
