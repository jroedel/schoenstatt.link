<?php

namespace Books\Controller;

use SamUser\Form\EditUserForm;

use BjyAuthorize\Controller\Plugin\IsAllowed;

use Zend\Filter\StaticFilter;

use Zend\Form\Element;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use SionModel\Entity\Book;

class LibraryController extends AbstractActionController
{
    /**
     * @var SionTable
     */
    protected $bookTable;
    
    /**
     * @var SionTable
     */
    protected $eBookTable;

    public function setBookTable(\SionModel\Db\Model\SionTable $bookTable)
    {
        $this->bookTable = $bookTable;
    }
    
    public function setEBookTable(\SionModel\Db\Model\SionTable $eBookTable)
    {
        $this->eBookTable = $eBookTable;
    }
    
    public function indexAction()
    {
        $books = null;
        $query = '%'.$this->params()->fromQuery('search').'%';
        $in = "";
//         $notAllowed = array();
//         if (!$this->isAllowed('book_teo', 'read')) {
//             $notAllowed[] = '\'DigitaSión Teo\'';
//             $notAllowed[] = '\'DigitaSión Fil\'';
//         }
//         if (!$this->isAllowed('book_sch', 'read'))
//             $notAllowed[] = '\'DigitaSión Sch\'';
//         if (count($notAllowed) > 0) {
//             $inText = implode(', ', $notAllowed);
//             $in = "AND (`library` NOT IN ($inText))";
//         }
        $validator = new \Zend\Validator\StringLength(array('min' => 6, 'max' => 30));
        $validator->setEncoding("UTF-8");
        if ($validator->isValid($query)) {
            if (!is_null($query)) {
                $sql = "SELECT b.`book_id`,b.`library`,b.`author`,b.`title`,b.`edition`,
b.`call_number`,b.`category`,b.`pages`,b.`lang`,b.`original_id`,
e.`file_type`, e.`file_size`, e.`url`
FROM `lib_books` b LEFT OUTER JOIN `lib_ebooks` e ON b.book_id = e.book_id
WHERE (`author` like ? OR 
`title` like ?)$in ORDER BY `library`, `author` 
LIMIT 300";
                //var_dump($sql);
                //the $query variable is properly escaped by Adapter.
                $books = $this->bookTable->fetchSome(null, $sql, array($query, $query), true);
                //var_dump($books);
                $books = self::keyArray($books, 'library');
            }
        }
        return new ViewModel(array(
            'libraries' => $books,
            'query' => $this->params()->fromQuery('search'),
        ));
    }
    
    public function documentAction()
    {
        $contentTypes = array(
            'pdf' => 'application/pdf',
            'htm' => 'text/html',
            'html'=> 'text/html',
            'xlsx'=> 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $sql = "SELECT b.`book_id`,b.`library`,b.`author`,b.`title`,b.`edition`,
b.`call_number`,b.`category`,b.`pages`,b.`lang`,b.`original_id`,
e.`filename`, e.`file_type`, e.`file_size`, e.`resource_id`
FROM `lib_books` b LEFT OUTER JOIN `lib_ebooks` e ON b.book_id = e.book_id
WHERE (b.`book_id` = ?) LIMIT 1";
        
        $name = $this->params('book_id');
        
        try {

            $records = $this->bookTable->fetchSome(null, $sql, (array($name)),true);
            if (!isset($records))
                throw new \Exception('Book not found.');
            $record = $records[0];
            if (is_null($record['resource_id']) || !$this->isAllowed($record['resource_id'], 'read')) {
                throw new \Exception('You do not have permission to view this page.');
            }
    
            $file = getcwd().'\\store\\'.$record['original_id'];//pathinfo($name)['basename'];
            if (file_exists($file)) {
                $response = new \Zend\Http\Response\Stream();
                $response->setStream(fopen($file, 'rb'));
                $response->setStatusCode(200);
                $response->getHeaders()->addHeaders(array(
                    'Content-Type' => $contentTypes[$record['file_type']],
                    'Content-Disposition' => 'attachment;filename="'.$record['filename'].'"',
                    'Content-Length' => $record['file_size'],
                    'Cache-Control' => 'max-age=0',
                ));
                
                return $response;
            } else {
                throw new \Exception('File not exist');
            }
        }
        catch (\Exception $e) {
            $this->flashMessenger()->setNamespace('error')->addMessage('404');
            return $this->redirect()->toUrl('/');
        }
    }
    
    public function updateAction()
    {
        $postData = $this->params()->fromPost('libraries');
        if (!$postData) {
            $postData = file_get_contents('libraries.json');
        }
        
//         var_dump($this->params()->fromPost('pdf'));
//         var_dump($this->params()->fromPost());
        //var_dump($postData);
        if ($postData) {
            $libraryData = json_decode($postData, true);
        }
        else
            throw "Didn't receive any post data.";
        var_dump($libraryData['libraries'][0]['items']);
        return;
        $stats = (memory_get_usage() - $startMemory). ' bytes';
        $today = date_format(new \DateTime(null, new \DateTimeZone('UTC')), "Y-m-d H:i:s");
        $print = array();
        $updates = 0;
        $inserts = 0;
        $Ebookupdates = 0;
        $Ebookinserts = 0;
        $noupdate = 0;
        foreach ($rows as $row) {
            //check if record exists
            $oid = $row[0];
            $book_id = null;
            $lib = $row[1];
            $exists = false;
            $existsEbook = false;
            $letsUpdate = false;
            $checkEbook = isset($row[9]);
            $letsUpdateEbook = false;
            if (isset($records[$oid])) {
                foreach ($records[$oid] as $record) {
                    if ($record["library"] == $lib) {
                        $exists = true;
                        $existsEbook = isset($record["ebook_id"]);
                        $book_id = $record["book_id"];
                        //check if the record needs to be updated
                        if (strcmp($record["author"], $row[2]) != 0 ||
                                strcmp($record["title"], $row[3]) != 0 ||
                                strcmp($record["edition"], $row[4]) != 0 ||
                                strcmp($record["call_number"], $row[5]) != 0 ||
                                strcmp($record["category"], $row[6]) != 0 ||
                                strcmp($record["pages"], $row[7]) != 0 ||
                                strcmp($record["lang"], $row[8]) != 0) {
                            $letsUpdate = true;
                        }
                        if ($checkEbook && (
                                strcmp($record["filename"], $row[9]) != 0 ||
                                strcmp($record["file_type"], $row[10]) != 0 ||
                                $record["file_size"] != $row[11] ||
                                strcmp($record["url"], $row[12]) != 0)) {
                            $letsUpdateEbook = true;
                        }
                    }
                }
            }
        
            if ($exists && !$letsUpdate && !$letsUpdateEbook) {
                ++$noupdate;
                continue;
            }
        
            $data = array(
                    'author' => $row[2],
                    'title' => $row[3],
                    'edition' => $row[4],
                    'call_number' => $row[5],
                    'category' => $row[6],
                    'pages' => $row[7],
                    'lang' => $row[8],
                    'updated_at' => $today
            );
            $key = array(
                    'original_id' => $oid,
                    'library' => $lib);
        
            $dataEbook = array(
                    'filename' => $row[9],
                    'file_type' => $row[10],
                    'file_size' => $row[11],
                    'url' => $row[12],
                    'resource_id' => 'book_teo',
                    'updated_at' => $today,
                    'updated_by' => 'importer',
            );
            $keyEbook = array(
                    'book_id' => $book_id);
        
            //mandar books
            if ($exists) {
                $print[] = array('update' => array_merge($key, $data));
                $this->bookTable->update($data, $key);
                ++$updates;
            } else {
                $print[] = array('insert' => array_merge($key, $data));
                $test = $this->bookTable->insert(array_merge($key, $data));
                ++$inserts;
            }
             
            //mandar ebooks
            if ($existsEbook) {
                $print[] = array('update' => array_merge($keyEbook, $dataEbook));
                $this->eBookTable->update($dataEbook, $keyEbook);
                ++$Ebookupdates;
            } else {
                //query book_id if we don't have it
                if (is_null($keyEbook['book_id']))
                {
                    $result = $this->bookTable->fetchSome(null, $bookIdSql, array_values($key), true);
                    if (isset($result[0]["book_id"]))
                        $keyEbook['book_id'] = $result[0]["book_id"];
                }
                //insert ebook
                $print[] = array('insert' => array_merge($keyEbook, $dataEbook));
                $dataEbook['created_at'] = $today;
                $dataEbook['created_by'] = 'importer';
                $this->eBookTable->insert(array_merge($keyEbook, $dataEbook));
                ++$Ebookinserts;
            }
        }
        
        return new ViewModel(array(
                'stats' => $stats." updates: ".$updates.'  inserts: '.$inserts.'  no-update: '.$noupdate.
                ' Ebook updates: '.$Ebookupdates.' Ebook inserts: '.$Ebookinserts,
                'print' => $print
        ));
    }
    
    public function importAction()
    {
        //import file
        //parse csv
        //query the event_id/lang pairs
        //foreach csv row
        //insert/update eng
        //insert/update spa
        //don't forget to include translation info (
        
        $file = file("books.txt");
        $rows = array();
        $libsInFile = array();
        foreach ($file as $line) {
            $add = str_getcsv($line, '|');
            $rows[] = $add;
            if (!isset($add[1]))
                var_dump(current($rows));
            if (!in_array($add[1], $libsInFile))
                $libsInFile[] = $add[1];
            if (count(current($rows)) != 9 && count(current($rows)) != 13)
                var_dump(current($rows));
        }
        $in  = str_repeat('?,', count($libsInFile) - 1) . '?';
        $sql = "SELECT b . * , e.ebook_id, e.filename, e.file_type, e.file_size, e.url
FROM  `lib_books` b LEFT OUTER JOIN  `lib_ebooks` e ON e.book_id = b.book_id 
WHERE library IN ($in) ORDER BY original_id";
        $bookIdSql = "SELECT book_id FROM `lib_books` WHERE (original_id = ?) AND (library = ?)";
        //var_dump($sql);
        $startMemory = memory_get_usage();
        $records = $this->bookTable->fetchSome(null, $sql, $libsInFile, true);
        //var_dump($records);
        $records = self::keyArray($records, 'original_id');

        $stats = (memory_get_usage() - $startMemory). ' bytes';
        $today = date_format(new \DateTime(null, new \DateTimeZone('UTC')), "Y-m-d H:i:s");
        $print = array();
        $updates = 0;
        $inserts = 0;
        $Ebookupdates = 0;
        $Ebookinserts = 0;
        $noupdate = 0;
        foreach ($rows as $row) {
            //check if record exists
            $oid = $row[0];
            $book_id = null;
            $lib = $row[1];
            $exists = false;
            $existsEbook = false;
            $letsUpdate = false;
            $checkEbook = isset($row[9]);
            $letsUpdateEbook = false;
            if (isset($records[$oid])) {
                foreach ($records[$oid] as $record) {
                    if ($record["library"] == $lib) {
                        $exists = true;
                        $existsEbook = isset($record["ebook_id"]);
                        $book_id = $record["book_id"];
                        //check if the record needs to be updated
                        if (strcmp($record["author"], $row[2]) != 0 ||
                        strcmp($record["title"], $row[3]) != 0 ||
                        strcmp($record["edition"], $row[4]) != 0 ||
                        strcmp($record["call_number"], $row[5]) != 0 ||
                        strcmp($record["category"], $row[6]) != 0 ||
                        strcmp($record["pages"], $row[7]) != 0 ||
                        strcmp($record["lang"], $row[8]) != 0) {
                            $letsUpdate = true;
                        }
                        if ($checkEbook && (
                            strcmp($record["filename"], $row[9]) != 0 ||
                            strcmp($record["file_type"], $row[10]) != 0 ||
                            $record["file_size"] != $row[11] ||
                            strcmp($record["url"], $row[12]) != 0)) {
                            $letsUpdateEbook = true;
                        }
                    }
                }
            }
            
            if ($exists && !$letsUpdate && !$letsUpdateEbook) {
                ++$noupdate;
                continue;
            }
            
            $data = array(
                'author' => $row[2],
                'title' => $row[3],
                'edition' => $row[4],
                'call_number' => $row[5],
                'category' => $row[6],
                'pages' => $row[7],
                'lang' => $row[8],
                'updated_at' => $today
            );
            $key = array(
                'original_id' => $oid,
                'library' => $lib);
            
            $dataEbook = array(
                'filename' => $row[9],
                'file_type' => $row[10],
                'file_size' => $row[11],
                'url' => $row[12],
                'resource_id' => 'book_teo',
                'updated_at' => $today,
                'updated_by' => 'importer',
            );
            $keyEbook = array(
                'book_id' => $book_id);
            
            //mandar books
             if ($exists) {
                 $print[] = array('update' => array_merge($key, $data));
                 $this->bookTable->update($data, $key);
                 ++$updates;
             } else {
                 $print[] = array('insert' => array_merge($key, $data));
                 $test = $this->bookTable->insert(array_merge($key, $data));
                 ++$inserts;
             }
             
             //mandar ebooks
             if ($existsEbook) {
                 $print[] = array('update' => array_merge($keyEbook, $dataEbook));
                 $this->eBookTable->update($dataEbook, $keyEbook);
                 ++$Ebookupdates;
             } else {
                 //query book_id if we don't have it
                 if (is_null($keyEbook['book_id']))
                 {
                     $result = $this->bookTable->fetchSome(null, $bookIdSql, array_values($key), true);
                     if (isset($result[0]["book_id"]))
                         $keyEbook['book_id'] = $result[0]["book_id"];
                 }
                 //insert ebook
                 $print[] = array('insert' => array_merge($keyEbook, $dataEbook));
                 $dataEbook['created_at'] = $today;
                 $dataEbook['created_by'] = 'importer';
                 $this->eBookTable->insert(array_merge($keyEbook, $dataEbook));
                 ++$Ebookinserts;
             }
        }
        
        return new ViewModel(array(
           'stats' => $stats." updates: ".$updates.'  inserts: '.$inserts.'  no-update: '.$noupdate.
                ' Ebook updates: '.$Ebookupdates.' Ebook inserts: '.$Ebookinserts,
           'print' => $print
        ));
    }

    /*
    public function testAction()
    {
        $event = $this->getBooksTranslTable()->getRecord(array('event_transl_event_id' => 1003));
        $test = 0;
        $newBooks = new Books();
        
        return new ViewModel(array(
            'event' => $event,
            'test' => $test,
            'newBooks' => $newBooks,
        ));
    }

    public function addAction()
    {
        return $this->redirect()->toRoute('event');

        $form = $this->_getEditEventForm();
        $form->get('submit')->setAttribute('value', 'Add');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $event = new Event();
            $form->bind($event);
            //$tmp = $request->getPost()->toArray();
            //var_dump($tmp);
            $form->setData($this->filterSlashes($request->getPost()->toArray())); //@todo filter slashes
            //ini_set('xdebug.var_display_max_depth', '4');
            if ($form->isValid()) {
                $event = $form->getData();
                
                $this->getEventTable()->saveRecord($event);
                if (is_array($event->eventTransls)) { 
                    $eventTransTable = $this->getEventTranslTable();
                    foreach ($event->eventTransls as $transl) {
                        $eventTransTable->saveRecord($transl);
                    }
                }
                // Redirect to list of events
                return $this->redirect()->toRoute('event');
            }
        }

        return array('form' => $form);
  
        }
        */
    public function editAction()
    {
        //return $this->redirect()->toRoute('library', array('action'=>'add'));
        
        //TODO check to make sure that they could possibly edit some book, if not kick them out
        $id = (Int)$this->params('book');
        if (!$id) {
            return $this->redirect()->toRoute('library/book', array('action'=>'add'));
        }

            $sql = "SELECT b.`book_id`,b.`library`,b.`author`,b.`title`,b.`edition`,
b.`call_number`,b.`category`,b.`pages`,b.`lang`,b.`original_id`,
e.`filename`, e.`file_type`, e.`file_size`, e.`resource_id`
FROM `lib_books` b LEFT OUTER JOIN `lib_ebooks` e ON b.book_id = e.book_id
WHERE (b.`book_id` = ?) LIMIT 1";
        
        $book_id = $this->params('book');
        
        try {
            $records = $this->bookTable->fetchSome(null, $sql, (array($book_id)),true);
            if (!isset($records))
                throw new \Exception('Requested resource not found.');
            $book = $records[0];
            if (is_null($book['resource_id']) || !$this->isAllowed($book['resource_id'], 'write')) {
                throw new \Exception('You do not have permission to view this page.');
            }
        }
        catch (\Exception $e) {
            $this->flashMessenger()->setNamespace('error')->addMessage('404');
            return $this->redirect()->toRoute('library');
        }
        
        $form = new EditUserForm();
        $request = $this->getRequest();
        if ($request->isPost()) {
            $form->bind($book);
            //$tmp = $request->getPost()->toArray();
            //var_dump($tmp);
            $form->setData($this->filterSlashes($request->getPost()->toArray())); //@todo filter slashes
            //ini_set('xdebug.var_display_max_depth', '4');
            if ($form->isValid()) {

                //var_dump($form);
                //var_dump($form->getData());
                $book = $form->getData();
                $this->bookTable->saveRecord($book);
//                 if (is_array($event->eventTransls)) { 
//                     $eventTransTable = $this->getEventTranslTable();
//                     foreach ($event->eventTransls as $transl) {
//                         $eventTransTable->saveRecord($transl);
//                     }
//                 }
                // Redirect to list of events
                return $this->redirect()->toRoute('event');
            }
        } else {
            if (!isset($book))
            {
                //throw \Exception('oh no');
                return $this->redirect()->toRoute('library');
            }
            $form->bind($book);
            //$this->addSpanAttrToCollection($form->get('event')->get('eventTransls'));
            
            $form->get('submit')->setAttribute('value', 'Edit');
            
        }
           
        return new ViewModel(array(
            'book_id' => $id,
            'book' => $book,
            'form' => $form,
        ));
    }
    
    /**
     * 
     * @param \Zend\Form\Fieldset $collection
     */
    private function addSpanAttrToCollection($collection)
    {
        $lang = array(
            'deu' => 'Deutsch',
            'spa' => 'Español',
            'eng' => 'English');
        foreach ($collection as $fieldset) {
            $fieldset->setAttribute('class', 'span3');
            $fieldset->setLabel($lang[$fieldset->get('lang')->getValue()]);
        }
    }
    
    private function filterSlashes($arr)
    {
    	if (get_magic_quotes_gpc()) {
            $arr = array_map('stripslashes', $arr);
    	}
        return $arr;
    }
    
    /**
     * 
     * @param array $data
     * @param string $key
     */
    private static function keyArray($data, $key)
    {
        $return = array();
        foreach ($data as $datum) {
            if (!isset($return[$datum[$key]]))
                $return[$datum[$key]] = array($datum);
            else
                $return[$datum[$key]][] = $datum;
        }
        return $return;
    }

    /*
    public function deleteAction()
    {
        $id = (int)$this->params('event_id');
        if (!$id) {
            return $this->redirect()->toRoute('event');
        }

        $request = $this->getRequest();
        if ($request->isPost()) {
            $del = $request->getPost()->get('del', 'No');
            if ($del == 'Yes') {
                $id = (int)$request->getPost()->get('event_id');
                $event = $this->getEntityManager()->find('Event\Entity\Event', $id);
                    if ($event) {
                        $this->getEntityManager()->remove($event);
                        $this->getEntityManager()->flush();
                    }
            }

            // Redirect to list of event
            return $this->redirect()->toRoute('event');
        }
        return array(
            'event_id' => $id,
            'event' => $this->getEntityManager()->find('Event\Entity\Event', $id)
        );
        
    }
   
*/
 
}
