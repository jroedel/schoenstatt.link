<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Books\Form\SearchForm;
use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Books\Model\LibraryTable;

class LibrariesController extends SionController
{
    public function __construct()
    {
        return parent::__construct('library');
    }

    public function showAction()
    {
        $view = parent::showAction();
        $entityObject = $view->getVariable('entity');
        $params = $this->params()->fromQuery();
        if (!is_null($entityObject['libraryId'])) {
            $params['libraryId'] = $entityObject['libraryId'];
        }
        $form = new SearchForm();
        $form->setData($params);
        $books = null;

        $table = $this->getSionTable();

        if ($form->isValid()) {
            $data = $form->getData();
            foreach ($data as $key => $value) {
                if (is_null($value)) {
                    unset($data[$key]);
                }
            }

            if (count($data) > 1) {
                $data['maxResults'] = 200;
                $books = $table->searchBooks($data);
            }
        }

        if (is_array($books) && empty($books)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }
        $view->setVariable('books', $books);
        $view->setVariable('form', $form);

        return $view;
    }

    public function adminAction()
    {
        return $this->showAction();
    }

    public function searchAction()
    {
        $params = $this->params()->fromQuery();
        $form = new SearchForm();
        $form->setData($params);
        $books = null;

        if ($form->isValid()) {
            $notAllowed = [];
//             if (!$this->isAllowed('book_teo', 'read')) {
//                 $notAllowed[] = '\'DigitaSión Teo\'';
//                 $notAllowed[] = '\'DigitaSión Fil\'';
//             }
//             if (!$this->isAllowed('book_sch', 'read')) {
//                 $notAllowed[] = '\'DigitaSión Sch\'';
//             }
            $data = $form->getData();

            if (!empty($data)) {
                $sm = $this->getServiceLocator();
                /** @var LibraryTable $table */
                $table = $sm->get('Books\Model\LibraryTable');
//                 $data['notAllowed'] = $notAllowed;
                $data['maxResults'] = 200;
                $books = $table->searchBooks($data);
                $libraries = $this->transformBookQueryIntoLibraries($books);
            }
        }

        if (is_array($books) && empty($books)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }

        return new ViewModel([
            'entities'  => $libraries,
            'form'      => $form,
        ]);
    }

    public function bookListAction()
    {
        /** @var LibraryTable $table */
        $table = $this->getSionTable();
        $books = $table->getBooks();
        return new ViewModel([
            'objects' => $books,
        ]);
    }

    public function transformBookQueryIntoLibraries($books)
    {
        $entities = [];
        foreach ($books as $bookId => $book) {
            if (!key_exists($book['libraryId'], $entities)) {
                $entities[$book['libraryId']] = $book['library'];
            }
            $entities[$book['libraryId']]['books'][] = $book;
        }
        return $entities;
    }

    /**
     * MVC Action to receive user data including an excel to import, worksheet to import from,
     * column mapping. Also, displays import confirmation to the user who should confirm
     */
    public function importBooksAction()
    {
        $columnNames = [
            'ID'        => 'withinLibrarylId',
            'Título'    => 'title',
            'Autor'     => 'author',
            'Lomo'      => 'callNumber',
            'Categoría' => 'category',
            'Subtítulo' => 'publicNotes',
            'Editorial' => 'title',
            'Ciudad'    => 'title',
            'Año'       => 'title',
            'ISBN'      => 'title',
            'Páginas'   => 'pages',
            'Idioma'    => 'language',
            'Info'      => 'title',
            'Categorías'=> 'title',
            'Orden'     => 'title',

            //             'bookId'        => $id,
        //             'author'        => $author,
        //             'title'         => $title,
        //             'edition'       => $this->filterDbString($row['edition']),
        //             'callNumber'    => $this->filterDbString($row['call_number']),
        //             'category'      => $this->filterDbString($row['category']),
        //             'pages'         => $this->filterDbInt($row['pages']),
        //             'language'      => $this->filterDbString($row['lang']),
        //             'withinLibrarylId'=> $this->filterDbId($row['original_id']),
        //             'libraryId'     => $this->filterDbId($row['library_id']),
        //             'publicationId' => $this->filterDbId($row['publication_id']),
        //             'updatedOn'     => $this->filterDbDate($row['updated_at']),
        //             'updatedBy'     => $this->filterDbId($row['updated_by']),
        //             'createdOn'     => $this->filterDbDate($row['created_at']),
        //             'createdBy'     => $this->filterDbId($row['created_by']),
        ];
        $file = file("data/books.txt");
        $rows = array();
        foreach ($file as $line) {
            $row = str_getcsv($line, '|');
            $rows[] = $add;
            if (!isset($add[1]))
                var_dump(current($rows));
        }
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
                $this->eLibraryTable->update($dataEbook, $keyEbook);
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
                $this->eLibraryTable->insert(array_merge($keyEbook, $dataEbook));
                ++$Ebookinserts;
            }
        }

        return new ViewModel(array(
            'stats' => $stats." updates: ".$updates.'  inserts: '.$inserts.'  no-update: '.$noupdate.
            ' Ebook updates: '.$Ebookupdates.' Ebook inserts: '.$Ebookinserts,
            'print' => $print
        ));
    }

}