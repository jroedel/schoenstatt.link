<?php
namespace Bible\Model;

use SionModel\Db\Model\SionTable;

class DhTable extends SionTable
{
    public const LAST_PAGE_NUMBER = 1631;
    
    protected function processDhRow(array $row)
    {
        $fileName = $row['FileName'];
        $processedRow = [
            'pageNumber' => $row['PageNumber'],
            'fileName' => $fileName,
            'referenceNumber' => $row['ReferenceNumber'],
            'startDhNumber' => $row['StartDhNumber'],
            'endDhNumber' => $row['EndDhNumber'],
            
            'fileUrl' => "/dh/$fileName",
        ];
        return $processedRow;
    }
}
