<?php
namespace Bible\Controller;

use SionModel\Controller\SionController;
use Laminas\Math\Rand;
use Laminas\View\Model\ViewModel;

class DhController extends SionController
{
    public function indexAction()
    {
        $objects = [];
        $search = false;
        if ($search) {
            $table = $this->getSionTable();
            $fields = array_keys($this->getEntitySpecification()->updateColumns);
            if (($key = array_search('fullText', $fields)) !== false) {
                unset($fields[$key]);
            }
            $objects = $table->getObjects('dh-page', [], ['fields' => $fields]);
        }
        return new ViewModel([
            'objects' => $objects,
        ]);
    }

    protected function getEntityIdParam($action = 'show', $default = null)
    {
        //when the user is looking for a particular dh number, run the function to find the pageNumber
        if ('show' === $action) {
            $dhNumber = $this->params()->fromRoute('dh_number');
            if (isset($dhNumber)) {
                $table = $this->getSionTable();
                $pageNumber = $table->lookupProbableDhNumberPage($dhNumber);
                return $pageNumber;
            }
        }
        return parent::getEntityIdParam($action, $default);
    }

    public function importAction()
    {
        $really = $this->params()->fromQuery('really') === 'yes';
        if (! $really) {
            return new ViewModel(['results' => []]);
        }
        $doIt = $this->params()->fromQuery('doit') === 'yes';

        $table = $this->getSionTable();
        $objects = $table->getObjects('dh-page');

        $reHeader = '/(?<!\(|\d)(\d{1,4})(?!\)|\d)/';

        $lastHeaderNumber = 0;
        $results = [];
        foreach ($objects as $object) {
            $pageNumber = $object['pageNumber'];
            $fileName = $object['fileName'];
            $filePath = 'public/dh/' . $fileName;
            if (! file_exists($filePath)) {
                throw new \Exception('File doesnt exist');
            }

            $fileNameWoExt = substr($fileName, 0, strlen($fileName) - 4);

            //get image information
            list($width, $height) = getimagesize($filePath);

            //parse header
            $headerPath = "public/dh/headers/$pageNumber.txt";
            $headerHandle = fopen($headerPath, "r");
            if ($headerHandle && ! feof($headerHandle)) {
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
                if ($matches && isset($matches[1]) && ! empty($matches[1])) {
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

            //get full text
            $textFile = $fileNameWoExt . '.markdown';
            $textFilePath = 'public/dh/' . $textFile;
            $fullText = file_get_contents($textFilePath);

            $data = [
                'pageNumber' => $pageNumber,
                'widthInPixels' => $width,
                'heightInPixels' => $height,
                'headerText' => $headerText,
                'headerDhNumber' => $headerDhNumber,
                'fullText' => $fullText,
                'fullTextFileName' => $textFile,
            ];
//             var_dump($data);

            if ($doIt) {
                unset($data['pageNumber']);
                $table->updateEntity('dh-page', $pageNumber, $data, [], false);
                $data['pageNumber'] = $pageNumber;
            }
            $results[] = $data;
        }
        return new \Laminas\View\Model\ViewModel([
            'results' => $results,
        ]);
    }

    protected function removeHyphens()
    {
        $table = $this->getSionTable();
        $objects = $table->getObjects('dh-page');

        $reHyphen = '/-\n([a-záéíóúñ])/';
        $reHyphenSubst = '\\1';
        $results = [];
        foreach ($objects as $object) {
            $fileName = $object['fileName'];
            $filePath = 'public/dh/' . $fileName;
            if (! file_exists($filePath)) {
                throw new \Exception('File doesnt exist');
            }

            $fileNameWoExt = substr($fileName, 0, strlen($fileName) - 4);
            $textFile = $fileNameWoExt . '.txt';
            $textFilePath = 'public/dh/' . $textFile;
            $fullTextFileContents = file_get_contents($textFilePath);

            $result = preg_replace($reHyphen, $reHyphenSubst, $fullTextFileContents);
            $newTextFile = 'public/dh/' . $fileNameWoExt . '-nohyphens' . '.txt';
            file_put_contents($newTextFile, $result);
            $finalTextFile = $fileNameWoExt . '.markdown';
            $command = "pandoc -t markdown -o $finalTextFile $newTextFile";
            $results[] = $command;
        }
        return $results;
    }

    protected function initialImport()
    {
        $table = $this->getSionTable();
        $results = [];
        for ($pageNumber = 1; $pageNumber <= 1631; $pageNumber++) {
            //get a nonce
            $nonce = Rand::getString(14, 'abcdefghijklmnopqrstuvwxyz0123456789');
            $filePageNumber = $pageNumber - 1;
            $origFilePath = "data/dh-orig/page_$filePageNumber.png";
            $newFileName = $nonce . "-page$pageNumber.png";
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
        return new \Laminas\View\Model\ViewModel([
            'results' => $results,
        ]);
    }
}
