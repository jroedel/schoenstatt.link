<?php
namespace Bible\Model;

use SionModel\Db\Model\SionTable;
use Zend\Db\Sql\Predicate\Between;

class DhTable extends SionTable
{
    public const LAST_PAGE_NUMBER = 1631;
    
    protected function processDhRow(array $row)
    {
        $fileName = $row['FileName'];
        $processedRow = [
            'pageNumber' => $row['PageNumber'],
            'fileName' => $fileName,
            'headerDhNumber' => $row['ReferenceNumber'],
            'startDhNumber' => $row['StartDhNumber'],
            'endDhNumber' => $row['EndDhNumber'],
            
            'headerText' => $row['HeaderText'],
            'widthInPixels' => $row['WidthInPixels'],
            'heightInPixels' => $row['HeightInPixels'],
            'fullTextFileName' => $row['FullTextFileName'],
            'fullText' => $row['FullTextOCR'],
            'fileUrl' => "/dh/$fileName",
        ];
        return $processedRow;
    }
    
    /**
     * Try to find the page closest to a Dh number
     * @param number $dhNumber
     * @throws \Exception
     * @return NULL|number
     */
    public function lookupProbableDhNumberPage($dhNumber)
    {
        if (!is_numeric($dhNumber)) {
            throw new \Exception('Dh number should be numeric');
        }
        $dhNumber = (int)$dhNumber;
        $min = $dhNumber - 17;
        if ($min < 0) {
            $min = 0;
        }
        $max = $dhNumber + 17;
        $where = new Between('ReferenceNumber', $min, $max);
        $objects = $this->queryObjects('dh-page', $where);
        
        $closestPageNumber = null;
        $closestDifference = 40;
        foreach ($objects as $object) {
            if (!isset($object['headerDhNumber']) || !is_numeric($object['headerDhNumber'])) {
                continue;
            }
            $dhHeaderNumber = (int)$object['headerDhNumber'];
            $difference = abs($dhNumber-$dhHeaderNumber);
            if ($difference < $closestDifference) {
                $closestDifference = $difference;
                $closestPageNumber = $object['pageNumber'];
            }
        }
        return $closestPageNumber;
    }
}
