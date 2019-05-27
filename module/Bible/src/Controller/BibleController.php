<?php
namespace Bible\Controller;

use Zend\Db\Sql\Where;
use Zend\Db\Sql\Select;
use SionModel\Db\Model\SionTable;
use Zend\View\Model\ViewModel;
use SionModel\Controller\SionController;
use Bible\Model\BibleTable;
use Bible\Filter\FromBibleworksMorphosyntacticCode;
use Zend\Db\Sql\Predicate\Between;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\Db\Sql\Predicate\In;

class BibleController extends SionController
{
    /**
     * Routing currently doesn't allow access to this action @todo revise this function
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::indexAction()
     */
    public function indexAction()
    {
        $translation = $this->params('translation');
        /** @var \Bible\Model\BibleTable $table */
        $table = $this->getSionTable();
        $books = $table->getBooks($translation);
        return new ViewModel(array(
            'translation' => $translation,
            'books' => $books,
            'bookAbbrev' => $table->getBookAbbrev(),
            'translAbbrev' => $this->getTranslAbbrev()
        ));
    }
    
    /**
     * Phase 1: Only show specific chapters of books. People can link to a particular verse
     * by using the # numeral symbol
     * 
     * @todo Phase 2: Change the routing to allow users to show only a particular verse
     * 
     * @todo Phase 3: Implement caching somehow
     */
    public function textAction()
    {
        //figure out what book/chapter we're talking about
        $bookId = (int)$this->params()->fromRoute('book');
        $chapter = (int)$this->params()->fromRoute('chapter');
        
        //check book/chapter against cached list of book/chapters available
        $table = $this->getSionTable();
        $book = $table->getObject('bible-book', $bookId);
        if (!isset($book)) {
            throw new \Exception('Book not found');
        } elseif ($chapter < 1 || $chapter > $book['chapterCount']) { //@todo make sure there's no corner cases
            throw new \Exception('Chapter not found');
        }
        
        //check what translations we need to grab
        $translations = ['nab', 'bnt', 'lba'];
        //grab from the db, think that not necessarily each verse will be available in each translation
        //we'll need an object to do a between query
        $min = sprintf("%1$02d%2$03d000", $bookId, $chapter);
        $max = sprintf("%1$02d%2$03d000", $bookId, $chapter+1);
        
        $verseId = new Between('verse_id', $min, $max);
        $where = new PredicateSet([$verseId, new In('translation_id', $translations)]);
        $verses = $table->queryObjects('bible-verse', $where);
        $verseTranslations = BibleTable::keyVersesByVerseIdAndTranslation($verses);
        //pass it all on keyed first by verse then by translation
        /*
         * [
         * 01001001 => [
         *      'nab' => 'In the beginning'
         *      'bnt' => 'εν αρχε ο λογος',
         *  ],
         * 01001002 => [
         *      'nab' => '...',
         *      'bnt' => '...',
         *  ],
         */ 
        $view = new ViewModel([
            'bookId' => $bookId,
            'chapter' => $chapter,
            'book' => $book,
            'translations' => $translations,
            'verseTranslations' => $verseTranslations,
        ]);
        $view->setTemplate('bible/bible/columns');
        return $view;
    }
    
    /**
     * Routing currently doesn't allow access to this action @todo revise this function
     */ 
    public function bibleAction()
    {
        $translation = $this->params('translation');
        $book = $this->params('book');
        $chapter = $this->params('chapter');
        /** @var \Bible\Model\BibleTable $table */
        $table = $this->getSionTable();
        $verses = $table->getVerses([
            'translation' => $translation,
            'book' => $book,
            'chapter' => $chapter,
        ]);
        return new ViewModel(array(
            'verses' => $verses,
            'possibleVerses' => $table->getPossibleVerses(),
            'bookAbbrev' => $this->getBookAbbrev(),
            'translAbbrev' => $this->getTranslAbbrev()
        ));
    }
    
    /**
     * Routing currently doesn't allow access to this action @todo revise this function
     */ 
    public function bntAction()
    {
        //$translation = $this->params('translation');
        $book = $this->params('book');
        $chapter = $this->params('chapter');
        $verse = $this->params('verse');
        
        $where = new Where();
        $where->equalTo('book_id', $book)
              ->equalTo('chapter', $chapter);
        if (isset($verse)) {
            $where->equalTo('verse', $verse);
        }
        $select = function (Select $select) use ($where) {
            $w = clone $where;
            $select->where($w->equalTo('translation_id', 'bnt'));
            $select->order('verse');
        };
        /** @var \Bible\Model\BibleTable $table */
        $table = $this->getSionTable();
        $verses = $table->fetchSome($select, null, null, true);
        $select = function (Select $select) use ($where) {
            $w = clone $where;
            $select->where($w->equalTo('translation_id', 'bnm'));
            $select->order('verse');
        };
        $versesBnm = $table->fetchSome($select, null, null, true);
    
        $select = function (Select $select) use ($where) {
            $w = clone $where;
            $select->where($w->equalTo('translation_id', 'nab'));
            $select->order('verse');
        };
        $versesNab = $table->fetchSome($select, null, null, true);
        
        $select = function (Select $select) use ($where) {
            $w = clone $where;
            $select->where($w->equalTo('translation_id', 'lba'));
            $select->order('verse');
        };
        $versesLba = $table->fetchSome($select, null, null, true);
        
        $verses = SionTable::keyArray($verses, 'verse', true);
        $versesBnm = SionTable::keyArray($versesBnm, 'verse', true);
        $versesNab = SionTable::keyArray($versesNab, 'verse', true);
        $versesLba = SionTable::keyArray($versesLba, 'verse', true);
        
        $morphoFilter = new FromBibleworksMorphosyntacticCode();
        
        return new ViewModel(array(
            'book' => $book,
            'chapter' => $chapter,
            'bnt' => $verses,
            'bnm' => $versesBnm,
            'nab' => $versesNab,
            'lba' => $versesLba,
            'codes' => $morphoFilter->getGrammarCodes(),
            'bookAbbrev' => $this->getBookAbbrev(),
            'translAbbrev' => $this->getTranslAbbrev()
        ));
    }
    
    /**
     * @todo create a translations table for the information
     */
    public function getTranslAbbrev()
    {
        return [
           'bnt' => 'Greek Bible',
           'nab' => 'English Bible',
           'lba' => 'Biblia en español'
        ];
    }
    
    /**
     * This action will migrate data from the old style table to the new 2019-05-23
     */
    public function migrateAction()
    {
        /** @var BibleTable $table */
        $table = $this->getSionTable();
        $table->importToNewTable();
        return new ViewModel([]);
    }
    
    public function importAction()
    {
        $translation = $this->params('translation');
        
        $file = file($translation.".txt");
        $preg = "/(?P<book>\\w{3,3}) (?P<chapter>\\d\\d{0,2}):(?P<verse>\\d\\d{0,2}) {1,2}(?P<text>.*)/iu";
        $unParsed = [];
        $parsed = [];
        foreach ($file as $line) {
            $cline = null;
            if (preg_match($preg, $line, $cline)) {
                $parsed[] = $cline;
            } else {
                $unParsed[] = $cline;
            }
        }
        
        if ($this->getRequest()->isPost()) {
            $verses = array();
            $imported = 0;
            $failed = 0;
            /** @var \Bible\Model\BibleTable $table */
            $table = $this->getSionTable();
            foreach ($parsed as $verse) {
                $data = array(
                    'translation_id' => $translation,
                    'book_id' => $verse['book'],
                    'chapter' => $verse['chapter'],
                    'verse' => $verse['verse'],
                    'text' => $verse['text'],
                    //'lang' => 'grc'
                );
                if ($table->insert($data)) {
                    $imported++;
                } else {
                    $failed++;
                    $verses[] = $data;
                }
            }
            $text = $imported.' rows inserted, '.$failed.' rows failed.';
        } else {
            $text = count($parsed).' rows parsed. '.count($unParsed). ' rows could not be parsed, shown below.';
            $verses = $unParsed;
        }
        
        return new ViewModel(array(
            'text' => $text,
            'verses' => $verses
        ));
    }
}
