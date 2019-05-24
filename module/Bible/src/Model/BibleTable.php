<?php
namespace Bible\Model;

use SionModel\Db\Model\SionTable;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Select;

class BibleTable extends SionTable
{
    
    /**
     *
     * {@inheritDoc}
     * @see \SionModel\Db\Model\SionTable::getSelectPrototype()
     */
    protected function getSelectPrototype($entity)
    {
        $select = parent::getSelectPrototype($entity);
        if ('bible-book' === $entity) {
            $select->order(['IsActive' => Select::ORDER_DESCENDING, 'KeyDe']);
        } elseif ('bible-translation' === $entity) {
            
        } elseif ('bible-verse' === $entity) {
            
        }
        return $select;
    }
    
    public function processVerseRow($row)
    {
        $data = [
            
        ];
        return $data;
    }
    
    //@todo this should all be covered by the SionModel::queryObjects function!
    public function getVerses($query = [], $options = [])
    {
        $entitySpec = $this->getEntitySpecification('bible-verse');
        $fieldMap = $entitySpec->updateColumns;
        $gateway = $this->getTableGateway($entitySpec->tableName);
        $select = $this->getSelectPrototype('bible-verse');
        $select->order('book_id, chapter, verse');
        $where = new Where();
        
        if (isset($query['chapter'])) {
            if (is_string($query['chapter'])) {
                $chapterClause = new Operator($fieldMap['chapter'], Operator::OPERATOR_EQUAL_TO, $query['chapter']);
            } elseif (is_array($query['chapter'])) {
                $chapterClause = new In($fieldMap['chapter'], $query['chapter']);
            }
            $where->addPredicate($chapterClause);
        }
        if (isset($query['book'])) {
            if (is_string($query['book'])) {
                $bookClause = new Operator($fieldMap['book'], Operator::OPERATOR_EQUAL_TO, $query['book']);
            } elseif (is_array($query['book'])) {
                $bookClause = new In($fieldMap['book'], $query['book']);
            }
            $where->addPredicate($bookClause);
        }
        if (isset($query['translation'])) {
            if (is_string($query['translation'])) {
                $translationClause = new Operator($fieldMap['translation'], Operator::OPERATOR_EQUAL_TO, $query['translation']);
            } elseif (is_array($query['translation'])) {
                $translationClause = new In($fieldMap['translation'], $query['translation']);
            }
            $where->addPredicate($translationClause);
        }
        if (isset($query['verse'])) {
            if (is_string($query['verse'])) {
                $verseClause = new Operator($fieldMap['verse'], Operator::OPERATOR_EQUAL_TO, $query['verse']);
            } elseif (is_array($query['verse'])) {
                $verseClause = new In($fieldMap['verse'], $query['verse']);
            }
            $where->addPredicate($verseClause);
        }
        
        if (isset($options['limit'])) {
            $select->limit($options['limit']);
        }
        
        $select->where($where);
        $results = $gateway->selectWith($select);
        return $results->toArray();
    }
    
    public function getBooks($translation = 'nab')
    {
        $sql = "SELECT book_id, MAX(chapter) AS last_chapter FROM bib_verses WHERE translation_id = ? GROUP BY book_id";
        $rows = $this->fetchSome(null, $sql, array($translation), true);
        //prime the books variable
        $abbrevs = $this->getBookAbbrev();
        $books = [];
        
        $bookOrder = array_keys($abbrevs);
        
        foreach ($rows as $row) {
            $book = $row['book_id'];
            $lastChapter = $row['last_chapter'];
            $bookNumber = array_search($book, $bookOrder, true);
            if (false === $bookNumber) {
                throw new \Exception("Stumbled accross an unknown book: `$book`");
            }
            $books[$bookNumber] = [
                'bookName' => $abbrevs[$book],
                'bookAbbreviation' => $book,
                'lastChapter' => $lastChapter,
            ];
        }
        ksort($books);
        return $books;
    }
    
    /**
     * Move data from bib_verses to new table
     */
    public function importToNewTable()
    {
        $select = new Select('bib_verses');
        $select->columns([Select::SQL_STAR]);
        $gateway = $this->getTableGateway('bib_verses');
        $result = $gateway->selectWith($select);
        $results = $result->toArray();
        
        $bookNumberLookup = $this->getBookNumberLookup();
        //prepare rows to insert
        $newRows = [];
        $i = 0;
        foreach ($results as $row) {
            if ($i > 15) {
                break;
            }
            $i++;
            $bookAbbrev = $row['book_id'];
            if (!isset($bookNumberLookup[$bookAbbrev])) {
                throw new \Exception('Unknown book: '.$bookAbbrev);
            }
            //create the composite book/chapter/verse id
            $bookNumber = $bookNumberLookup[$bookAbbrev];
            $chapterNumber = $row['chapter'];
            $verseNumber = $row['verse'];
            $compositeId = sprintf("%1$02d%2$02d%3$02d", $bookNumber, $chapterNumber, $verseNumber);
//             var_dump($compositeId);
            $newRows[] = [
                'translation_id' => $row['translation_id'],
                'verse_id' => $compositeId,
                'book_id' => $bookNumber,
                'chapter' => $chapterNumber,
                'verse' => $verseNumber,
                'text' => $row['text'],
            ];
        }
        var_dump($newRows);
        //insert rows to new table
//         $destGateway = $this->getTableGatewayForEntity('verse');
//         foreach ($newRows as $set) {
//             $destGateway->insert($set);
//         }
    }
    
    public function getBookNumberLookup()
    {
        return ['Mat' => 65];
    }
    
    public function getBookAbbrev()
    {
        static $abbrevs;
        if (!isset($abbrevs)) {
            $abbrevs = [
                'Gen' => 'Genesis',
                'Exo' => 'Exodus',
                'Lev' => 'Leviticus',
                'Num' => 'Numbers',
                'Deu' => 'Deuteronomy',
                'Jos' => 'Joshua',
                'Jdg' => 'Judges',
                'Rut' => 'Ruth',
                '1Sa' => '1 Samuel',
                '2Sa' => '2 Samuel',
                '1Ki' => '1 Kings',
                '2Ki' => '2 Kings',
                '1Ch' => '1 Chronicles',
                '2Ch' => '2 Chronicles',
                'Ezr' => 'Ezra',
                'Neh' => 'Nehemiah',
                'Est' => 'Esther',
                'Job' => 'Job',
                'Psa' => 'Psalm',
                'Pro' => 'Proverbs',
                'Ecc' => 'Ecclesiastes',
                'Sol' => 'Song of Solomon',
                'Isa' => 'Isaiah',
                'Jer' => 'Jeremiah',
                'Lam' => 'Lamentations',
                'Eze' => 'Ezekiel',
                'Dan' => 'Daniel',
                'Hos' => 'Hosea',
                'Joe' => 'Joel',
                'Amo' => 'Amos',
                'Oba' => 'Obadiah',
                'Jon' => 'Jonah',
                'Mic' => 'Micah',
                'Nah' => 'Nahum',
                'Hab' => 'Habakkuk',
                'Zep' => 'Zephaniah',
                'Hag' => 'Haggai',
                'Zec' => 'Zechariah',
                'Mal' => 'Malachi',
                'Mat' => 'Matthew',
                'Mar' => 'Mark',
                'Luk' => 'Luke',
                'Joh' => 'John',
                'Act' => 'Acts',
                'Rom' => 'Romans',
                '1Co' => '1 Corinthians',
                '2Co' => '2 Corinthians',
                'Gal' => 'Galatians',
                'Eph' => 'Ephesians',
                'Phi' => 'Philippians',
                'Col' => 'Colossians',
                '1Th' => '1 Thessalonians',
                '2Th' => '2 Thessalonians',
                '1Ti' => '1 Timothy',
                '2Ti' => '2 Timothy',
                'Tit' => 'Titus',
                'Phm' => 'Philemon',
                'Heb' => 'Hebrews',
                'Jam' => 'James',
                '1Pe' => '1 Peter',
                '2Pe' => '2 Peter',
                '1Jo' => '1 John',
                '2Jo' => '2 John',
                '3Jo' => '3 John',
                'Jud' => 'Jude',
                'Rev' => 'Revelation',
                '1Es' => '1 Esdras',
                'Jdt' => 'Judith',
                'Tob' => 'Tobit',
                '1Ma' => '1 Maccabees',
                '2Ma' => '2 Maccabees',
                '3Ma' => '3 Maccabees',
                '4Ma' => '4 Maccabees',
                'Ode' => 'Odes',
                'Wis' => 'Wisdom',
                'Sir' => 'Sirach',
                'Sip' => 'Sip',
                'Pss' => 'Psalms of Solomon',
                'Bar' => 'Baruch',
                'Epj' => 'Epistle of Jeremiah',
                'Sus' => 'Susanna',
                'Bel' => 'Bel',
                'Pra' => 'Prayer of Azariah',
                'Dng' => 'Daniel (Greek)',
                'Prm' => 'Prayer of Manasseh',
                'Psx' => 'Psalm(151)',
                'Lao' => 'Laodiceans',
                '4Es' => '4 Esdras',
                'Esg' => 'Esther (Greek)',
                'Jsa' => 'Joshua (A)',
                'Jda' => 'Judges (A)',
                'Tbs' => 'Tobit (S)',
                'Sut' => 'Susanna (TH)',
                'Dat' => 'Daniel (TH)',
                'Bet' => 'Bel (TH)',
                'WCF' => 'WCF',
                'WLC' => 'WLC',
                'WSC' => 'WSC',
            ];
        }
        return $abbrevs;
    }
}
