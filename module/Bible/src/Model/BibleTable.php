<?php
namespace Bible\Model;

use SionModel\Db\Model\SionTable;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate\IsNull;

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
            $select->order(['order_jerusalem_es']);
        } elseif ('bible-translation' === $entity) {
            
        } elseif ('bible-verse' === $entity) {
            $select->order(['verse_id']);
        }
        return $select;
    }
    
    public function processBookRow($row)
    {
        $data = [
            'bookId' => $this->filterDbId($row['book_id']),
            'name' => $row['name_en'],
            'nameEs' => $row['name_es'],
            'nameDe' => $row['name_de'],
            'namePt' => $row['name_pt'],
            'nameFr' => $row['name_fr'],
            'orderJerusalemEn' => $this->filterDbInt($row['order_jerusalem_en']),
            'orderJerusalemEs' => $this->filterDbInt($row['order_jerusalem_es']),
            'isNewTestament' => $this->filterDbBool($row['is_new_testament']),
            'genreId' => $this->filterDbBool($row['genre_id']),
            'isCanonical' => $this->filterDbBool($row['is_canonical']),
            'chapterCount' => $this->filterDbInt($row['chapter_count']),
            'oldBookId' => $row['old_book_id'],
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
    
    /**
     * Move data from bib_verses to new table
     */
    public function importToNewTable()
    {
        $select = new Select('bib_verses');
        $select->columns([Select::SQL_STAR]);
        $select->where(new IsNull('verse_id'));
        $gateway = $this->getTableGateway('bib_verses');
        $result = $gateway->selectWith($select);
        $results = $result->toArray();
        
        $bookNumberLookup = $this->getBookNumberLookup();
        //prepare rows to insert
        $updates = [];
        $i = 0;
        foreach ($results as $row) {
            if ($i > 5000) {
                break;
            }
            $i++;
            $id = $row['id'];
            $bookAbbrev = $row['book_old_id'];
            if (!isset($bookNumberLookup[$bookAbbrev])) {
                throw new \Exception('Unknown book: '.$bookAbbrev);
            }
            //create the composite book/chapter/verse id
            $bookNumber = $bookNumberLookup[$bookAbbrev];
            $chapterNumber = $row['chapter'];
            $verseNumber = $row['verse'];
            $compositeId = sprintf("%1$02d%2$03d%3$03d", $bookNumber, $chapterNumber, $verseNumber);
//             var_dump($compositeId);
            $updates[$id] = [
                'verse_id' => $compositeId,
                'book_id' => $bookNumber,
            ];
        }
        var_dump($updates);
        //insert rows to new table
        $destGateway = $this->getTableGatewayForEntity('bible-verse');
        foreach ($updates as $id => $set) {
            $destGateway->update($set, ['id' => $id]);
        }
    }
    
    public function getBookNumberLookup()
    {
        $books = $this->getObjects('bible-book');
        $lookup = [];
        foreach ($books as $id => $book) {
            if (isset($book['oldBookId'])) {
                $lookup[$book['oldBookId']] = $id;
            }
        }
        return $lookup;
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
