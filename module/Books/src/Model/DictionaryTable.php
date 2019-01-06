<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use Cocur\Slugify\Slugify;
use Zend\Db\ResultSet\ResultSetInterface;
use Books\Exception\DuplicateKeyException;

class DictionaryTable extends SionTable
{
    protected function preprocessDictionaryEntry($data, $entityData, $action)
    {
        //calculate slug
        static $filter;
        if (!isset($data['key'])) {
            return $data;
        }
        if (!isset($filter)) {
            $filter = new Slugify();
        }
        $slug = $filter->slugify($data['key']);
        $data['slug'] = $slug;
        
        //check for a duplicate slug-locale
        if (self::ENTITY_ACTION_CREATE === $action 
            && isset($data['slug']) 
            && isset($data['locale'])
            && $this->doesDictionaryEntryAlreadyExist($data['slug'], $data['locale'])
        ) {
            throw new DuplicateKeyException('There is already a dictionary entry for given key and locale');
        }
        
        return $data;
    }
    
    protected function postprocessDictionaryEntry($data, $newEntityData, $action)
    {
        //update the links of all the same slug
        if (isset($data['links'])) {
            $gateway = $this->getTableGateway('sch_dictionary_entries');
            $gateway->update(['Links' => $this->formatDbArray($data['links'])], ['Slug' => $newEntityData['slug']]);
        }
    }
    
    /**
     * Check if there's a pre-existing slug-locale pair in the database
     * @param string $slug
     * @param string $locale
     * @return boolean
     */
    protected function doesDictionaryEntryAlreadyExist($slug, $locale)
    {
        $entitySpec = $this->getEntitySpecification('dictionary-entry');
        $tableName  = $entitySpec->tableName;
        $gateway    = $this->getTableGateway($tableName);
        $result     = $gateway->select(['Slug' => $slug, 'Locale' => $locale]);
        if (!$result instanceof ResultSetInterface || 0 === $result->count()) {
            return false;
        }
        return true;
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
                'isActive' => $this->filterDbBool($row['IsActive']),
                'links' => $this->filterDbArray($row['Links']),
            ];
        } else {
            $data = parent::processEntityRow($entity, $row);
        }
        return $data;
    }
}
