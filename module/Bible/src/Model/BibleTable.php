<?php
namespace Bible\Model;

use SionModel\Db\Model\SionTable;

class BibleTable extends SionTable
{

//     'Bible\Model\VerseTable' =>  function($sm) {
//     $tableGateway = $sm->get('VerseTableGateway');
//     $where = function (Select $select) {
//         $select->columns(array(
//             'id',
//             'translation_id',
//             'book_id',
//             'chapter',
//             'verse',
//             'text',
//         ));
//         $where = new Where();
//         $select->where($where->equalTo('translation_id', 'gnt'));
//     };
//     $table = new SionTable($tableGateway, $where);
//     return $table;
// },
// 'VerseTableGateway' => function ($sm) {
// $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
// $hydrator = new ObjectPropertyMapper(
//         array(
//             'id' => 'id',
//             'translation_id' => 'translation',
//             'book_id' => 'book',
//             'chapter' => 'chapter',
//             'verse' => 'verse',
//             'text' => 'text',
//         ));
// $rowObjectPrototype = new Verse();

// $resultSet = new KeyedHydratingResultSet(
//         $hydrator, $rowObjectPrototype, array('book', 'chapter', 'verse') //create array using 'id' as key
//         );
// return new HydratingTableGateway('bib_verses', $dbAdapter, null, $resultSet);
// },
}
