<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Zend\Validator\File\Size;

class LibraryImportsController extends SionController
{
    public function __construct()
    {
        return parent::__construct('library-import');
    }

//     public function createAction()
//     {
//         //load a libraryid from query


//         $form = new ProfileForm();
//         $request = $this->getRequest();
//         if ($request->isPost()) {

//             /** @var \Books\Model\LibraryTable $table */
//             $table = $this->getSionTable();
//             $profile = $this->getServiceLocator()->get('Books\Form\CreateImportForm');
//             $form->setInputFilter($profile->getInputFilter());

//             $data    = array_merge_recursive(
//                 $request->getPost()->toArray(),
//                 $request->getFiles()->toArray()
//             );

//             //set data post and file ...
//             $form->setData($data);

//             if ($form->isValid()) {

//                 $size = new Size(array('max'=>2000000)); //minimum bytes filesize

//                 $adapter = new \Zend\File\Transfer\Adapter\Http();
//                 $adapter->setValidators(array($size), $File['name']);
//                 if (!$adapter->isValid()){
//                     $dataError = $adapter->getMessages();
//                     $error = array();
//                     foreach($dataError as $key=>$row)
//                     {
//                         $error[] = $row;
//                     }
//                     $form->setMessages(array('fileupload'=>$error ));
//                 } else {
//                     $adapter->setDestination(dirname(__DIR__).'/assets');
//                     if ($adapter->receive($File['name'])) {
//                         $profile->exchangeArray($form->getData());
//                         echo 'Profile Name '.$profile->profilename.' upload '.$profile->fileupload;
//                     }
//                 }
//             }
//         }

//         return array('form' => $form);
//     }
}