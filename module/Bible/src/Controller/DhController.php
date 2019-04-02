<?php
namespace Bible\Controller;

use SionModel\Controller\SionController;
use Zend\Mvc\Console\View\ViewModel;
use Zend\Math\Rand;

class DhController extends SionController
{
    public function importAction()
    {
        $really = $this->params()->fromQuery('really') === 'yes';
        if (!$really) {
            return new ViewModel(['results' => []]);
        }
        $doIt = $this->params()->fromQuery('doit') === 'yes';
        
        $table = $this->getSionTable();
        $objects = $table->getObjects('dh-page');
        
        $reHeader = '/(?<!\(|\d)(\d{1,4})(?!\)|\d)/';
        $lastHeaderNumber = 0; //@todo set to 0
        $results = [];
        $pageDhNumbers = [];
        foreach ($objects as $object) {
            $pageNumber = $object['pageNumber'];
//             if ($pageNumber < 458) { //458
//                 continue;
//             }
            $fileName = $object['fileName'];
            $filePath = 'public/dh/'.$fileName;
            if (!file_exists($filePath)) {
                throw new \Exception('File doesnt exist');
            }
            
            $fileNameWoExt = substr($fileName, 0, strlen($fileName)-4);
//             var_dump($filePath);
//             var_dump($fileNameWoExt);
            
            //get image information
            list($width, $height) = getimagesize($filePath);
            
            //parse header
            $headerPath = "public/dh/headers/$pageNumber.txt";
            $headerHandle = fopen($headerPath, "r");
            if ($headerHandle && !feof($headerHandle)) {
                $headerText = fgets($headerHandle);
            }
            fclose($headerHandle);
            $headerText = trim($headerText);
            
            $headerDhNumber = null;
            if ($pageNumber >= 54) {
                $matches = null;
                preg_match_all($reHeader, $headerText, $matches);
                $closestIndex = -1;
                $closestDifference = 10000;
                if ($matches && isset($matches[1]) && !empty($matches[1])) {
                    foreach ($matches[1] as $key => $value) {
                        $number = (int)$value;
                        if ($number < $lastHeaderNumber) {
                            continue;
                        }
                        $difference = $number - $lastHeaderNumber;
                        if ($difference < $closestDifference) {
                            $closestIndex = $key;
                            $closestDifference = $difference;
                        }
                    }
                    if ($closestIndex > -1) {
                        $headerDhNumber = (int)$matches[1][$closestIndex];
                        $lastHeaderNumber = $headerDhNumber;
                    }
                }
            }
            $pageDhNumbers[$pageNumber] = $headerDhNumber;
            
            //get full text
            $textFile = $fileNameWoExt.'.txt';
            $textFilePath = 'public/dh/'.$textFile;
            
            //remove hyphens
            
            
            $data = [
                'widthInPixels' => $width,
                'heightInPixels' => $height,
                'headerText' => $headerText,
                'headerDhNumber' => $headerDhNumber,
//                 'fullText' => $fullText,
                'fullTextFileName' => $textFile
            ];
//             var_dump($data);
            
            if ($doIt) {
                $result = $table->createEntity('dh-page', $data, false);
                $data['result'] = $result;
            }
            $results[] = $data;
//             break;
        }
        var_dump($pageDhNumbers);
        return new \Zend\View\Model\ViewModel([
            'results' => $results,
        ]);
    }
    
    protected function initialImport()
    {
        $table = $this->getSionTable();
        $results = [];
        for ($pageNumber = 1; $pageNumber <= 1631; $pageNumber++) {
            //get a nonce
            $nonce = Rand::getString(14, 'abcdefghijklmnopqrstuvwxyz0123456789');
            $filePageNumber = $pageNumber-1;
            $origFilePath = "data/dh-orig/page_$filePageNumber.png";
            $newFileName = $nonce."-page$pageNumber.png";
            $newFilePath = "public/dh/$newFileName";
            
            $rename = "mv $origFilePath $newFilePath";
            //put together insert data
            $data = [
                'pageNumber' => $pageNumber,
                'fileName' => $newFileName,
                'move' => $rename,
            ];
            //insert
            $result = $table->createEntity('dh-page', $data, false);
            $data['result'] = $result;
            $results[] = $data;
        }
        return new \Zend\View\Model\ViewModel([
            'results' => $results,
        ]);
    }
}
