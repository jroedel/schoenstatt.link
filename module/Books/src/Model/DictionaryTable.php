<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use Cocur\Slugify\Slugify;

class DictionaryTable extends SionTable
{
    protected function preprocessDictionaryEntry($data, $entityData, $action)
    {
        //calculate slug
        static $filter;
        if (!isset($data['entry'])) {
            return $data;
        }
        if (!isset($filter)) {
            $filter = new Slugify();
        }
        $slug = $filter->slugify($data['entry']);
        $data['slug'] = $slug;
        return $data;
    }
    
    protected function postprocessDictionaryEntry($data, $newEntityData, $action)
    {
        //update the links of all the same slug
        if (isset($data['links'])) {
            $gateway = $this->getTableGateway('sch_dictionary_entries');
            $gateway->update(['Links' => $data['links']], ['Slug' => $newEntityData['slug']]);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SionModel\Db\Model\SionTable::processEntityRow()
     */
    protected function processEntityRow($entity, array $row)
    {
        if ('dictionary-entry' === $entity) {
            $data = [
                'entryId' => $this->filterDbInt($row['EntryId']),
                'key' => $row['KeyDe'],
                'slug' => $row['Slug'],
                'locale' => $row['Locale'],
                'directTranslation' => $row['DirectTranslation'],
                'entry' => $row['Entry'],
                'links' => $this->filterDbArray($row['Links']),
            ];
        } else {
            $data = parent::processEntityRow($entity, $row);
        }
        return $data;
    }
}
