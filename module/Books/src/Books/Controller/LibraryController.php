<?php

namespace Books\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use SionModel\Entity\Book;

class LibraryController extends AbstractActionController
{

    public function indexAction()
    {
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
}
