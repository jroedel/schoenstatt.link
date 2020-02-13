<?php
namespace Bible\Controller;

use Zend\View\Model\ViewModel;
use SionModel\Controller\SionController;
use Bible\Model\BibleTable;
use Zend\Db\Sql\Predicate\Between;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\Db\Sql\Predicate\In;
use Bible\Form\BibleSearchForm;
use Zend\Db\Sql\Predicate\Like;
use voku\helper\UTF8;

class BibleController extends SionController
{
    public function todaysReadingAudioAction()
    {
        $date = new \DateTime();
        $url = $this->getReadingUrlForDate($date);
        return $this->redirect()->toUrl($url);
    }
    
    public function tomorrowsReadingAudioAction()
    {
        $date = new \DateTime();
        $date->add(new \DateInterval('P1D'));
        $url = $this->getReadingUrlForDate($date);
        return $this->redirect()->toUrl($url);
    }
    
    public function sundaysReadingAudioAction()
    {
        $date = new \DateTime();
        $count = 0;
        while (0 != $date->format("w") && $count < 7) {
            $date->add(new \DateInterval('P1D'));
            $count++;
        }
        $url = $this->getReadingUrlForDate($date);
        return $this->redirect()->toUrl($url);
    }
    
    /**
     * Converts a DateTime object into a URL for readings from the USCCB
     * For example, Dec 31, 2019 is transformed into
     * http://ccc.usccb.org/cccradio/NABPodcasts/2019/19_12_31.mp3
     * @param \DateTime $date
     * @return string
     */
    public function getReadingUrlForDate(\DateTime $date)
    {
        $url = "http://ccc.usccb.org/cccradio/NABPodcasts/"
            . date_format($date, "Y")
            . "/"
            . date_format($date, "y_m_d")
            . ".mp3";
        return $url;
    }
    
    /**
     * Routing currently doesn't allow access to this action @todo revise this function
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::indexAction()
     */
    public function indexAction()
    {
//         $translation = $this->params('translation');
        /** @var \Bible\Model\BibleTable $table */
        $table = $this->getSionTable();
        $books = $table->getObjects('bible-book');
        $form = new BibleSearchForm();
        return new ViewModel([
            'books' => $books,
            'form' => $form,
        ]);
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
        $bookId = $this->params()->fromRoute('book');
        $chapter = (int)$this->params()->fromRoute('chapter');
        
        /** @var BibleTable $table */
        $table = $this->getSionTable();
        if (!is_numeric($bookId) && is_string($bookId)) {
            $map = $table->getBookAbbreviationMap();
            $bookId = strtolower($bookId);
            if (!isset($map[$bookId])) {
                throw new \Exception('Book not found');
            }
            $bookId = $map[$bookId];
        }
        //check book/chapter against cached list of book/chapters available
        $book = $table->getObject('bible-book', $bookId);
        if (!isset($book)) {
            throw new \Exception('Book not found');
        } elseif ($chapter < 1 || $chapter > $book['chapterCount']) { //@todo make sure there's no corner cases
            throw new \Exception('Chapter not found');
        }
        
        //check what translations we need to grab
        if ($book['isNewTestament']) {
            $translations = ['njb', 'bnt2', 'jeresp'];
        } else {
            $translations = ['njb', 'septnt', 'jeresp'];
        }
        //grab from the db, think that not necessarily each verse will be available in each translation
        //we'll need an object to do a between query
        $min = sprintf("%1$02d%2$03d000", $bookId, $chapter);
        $max = sprintf("%1$02d%2$03d000", $bookId, $chapter+1);
        
        $verseId = new Between('verse_id', $min, $max);
        $where = new PredicateSet([$verseId, new In('translation_id', $translations)]);
        $verses = $table->queryObjects('bible-verse', $where);
        $verseTranslations = BibleTable::keyVersesByVerseIdAndTranslation($verses);
        
        $form = new BibleSearchForm();
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
            'form' => $form,
        ]);
        $view->setTemplate('bible/bible/columns');
        return $view;
    }
    
    public function createGreekReferenceAction()
    {
        /** @var BibleTable $table */
        $table = $this->getSionTable();
        $rootWords = $table->createGreekReference();
        $view = new ViewModel([
            'objects' => $rootWords,
        ]);
        $view->setTemplate('bible/greek-root/index');
        return $view;
    }
    
    public function normalizeGreekAction()
    {
        /** @var BibleTable $table */
        $table = $this->getSionTable();
        $table->normalizeBnmUtf8('bnm', 'bnm2');
        $table->normalizeBnmUtf8('bnt', 'bnt2');
        return new ViewModel([]);
    }
    
    public function searchAction()
    {
        //verify params
        $verses = null;
        $books = [];
        $form = new BibleSearchForm();
        $data = $this->params()->fromQuery();
        $search = null;
        if (!empty($data)) {
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                //fetch list of verses, maybe even group by the verse
                /** @var BibleTable $table */
                $table = $this->getSionTable();
                $search = $data['search'];
                $searchClause = new Like('text', '%'.UTF8::filter($search).'%');
                $translations = ['njb', 'septnt', 'jeresp', 'bnm2'];
                $where = new PredicateSet([new In('translation_id', $translations), $searchClause]);
                $verses = $table->getObjects('bible-verse', $where);
                $books = $table->getObjects('bible-book');
            }
        }
        return new ViewModel([
            'form' => $form,
            'verses' => $verses,
            'books' => $books,
            'search' => $search,
        ]);
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
