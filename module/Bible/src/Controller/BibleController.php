<?php
namespace Bible\Controller;

use Zend\Db\Sql\Where;

use Zend\Db\Sql\Select;

use SionModel\Db\Model\SionTable;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Bible\Model\Verse;

class BibleController extends AbstractActionController
{
    /**
     * 
     * @var SionTable
     */
	protected $verseTable;
	
    public function indexAction()
    {
        $translation = $this->params('translation');
        $sql = "SELECT book_id, MAX(chapter) AS LastChapter FROM bib_verses WHERE translation_id = ? GROUP BY book_id";
        $books = $this->getVerseTable()->fetchSome(null, $sql, array($translation), true);
        return new ViewModel(array(
            'translation' => $translation,
            'books' => $books,
            'bookAbbrev' => $this->getBookAbbrev(),
            'translAbbrev' => $this->getTranslAbbrev()
        ));
    }
    
    public function bibleAction()
    {
        $translation = $this->params('translation');
        $book = $this->params('book');
        $chapter = $this->params('chapter');
        $where = new Where();
        $where->equalTo('translation_id', $translation);
        if (!is_null($chapter)) $where->equalTo('chapter', $chapter);
        if (!is_null($book)) $where->equalTo('book_id', $book);
        $select = function (Select $select) use ($where) {
            $select->where($where);
            $select->order('book_id, chapter, verse');
        };
        $verses = $this->getVerseTable()->fetchSome($select);

        return new ViewModel(array(
            'verses' => $verses,
            'bookAbbrev' => $this->getBookAbbrev(),
            'translAbbrev' => $this->getTranslAbbrev()
        ));
    }

    public function bntAction()
    {
        //$translation = $this->params('translation');
        $book = $this->params('book');
        $chapter = $this->params('chapter');
        $verse = $this->params('verse');
        
        $where = new Where();
        $where->equalTo('book_id', $book)
              ->equalTo('chapter', $chapter);
        if (!is_null($verse))
            $where->equalTo('verse', $verse);
        $select = function (Select $select) use ($where) {
            $w = clone $where;
            $select->where($w->equalTo('translation_id', 'bnt'));
            $select->order('verse');
        };
        $verses = $this->getVerseTable()->fetchSome($select, null, null, true);
        $select = function (Select $select) use ($where) {
            $w = clone $where;
            $select->where($w->equalTo('translation_id', 'bnm'));
            $select->order('verse');
        };
        $versesBnm = $this->getVerseTable()->fetchSome($select, null, null, true);
    
        $select = function (Select $select) use ($where) {
            $w = clone $where;
            $select->where($w->equalTo('translation_id', 'nab'));
            $select->order('verse');
        };
        $versesNab = $this->getVerseTable()->fetchSome($select, null, null, true);
        
        $select = function (Select $select) use ($where) {
            $w = clone $where;
            $select->where($w->equalTo('translation_id', 'lba'));
            $select->order('verse');
        };
        $versesLba = $this->getVerseTable()->fetchSome($select, null, null, true);
        
        $verses = SionTable::keyArray($verses, 'verse', true);
        $versesBnm = SionTable::keyArray($versesBnm, 'verse', true);
        $versesNab = SionTable::keyArray($versesNab, 'verse', true);
        $versesLba = SionTable::keyArray($versesLba, 'verse', true);
        
        return new ViewModel(array(
            'book' => $book,
            'chapter' => $chapter,
            'bnt' => $verses,
            'bnm' => $versesBnm,
            'nab' => $versesNab,
            'lba' => $versesLba,
            'codes' => $this->getCodes(),
            'bookAbbrev' => $this->getBookAbbrev(),
            'translAbbrev' => $this->getTranslAbbrev()
        ));
    }
    
    public function getTranslAbbrev()
    {
        return array(
           'bnt' => 'Greek Bible',
           'nab' => 'English Bible',
           'lba' => 'Biblia en español');
    }
    public function getBookAbbrev()
    {
        return array(
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
);
    }
    public function getCodes()
    {
        $nouns = array(
            array('n' => 'noun'),
            array('n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'),
            array('m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.'),
            array('s' => 'sing.', 'p' => 'plu.'),
            array('c' => 'comm.', 'p' => 'prop.'),
        );
        $participles = array(
            array('v' => 'verb'),
            array('p' => 'part.'),
            array('p' => 'pres.', 'f' => 'fut.', 'a' => 'aor.', 'i' => 'imp.', 'x' => 'perf.', 'y' => 'pluperf.', 'z' => 'futperf.'),
            array('a' => 'act.', 'm' => 'mid.', 'p' => 'pass.', 'e' => 'mid/pass.'),
            array('n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'),
            array('m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.'),
            array('s' => 'sing.', 'p' => 'plu.')
        );
        $partShort = array(
            array('v' => ''),
            array('p' => 'part.'),
            array('p' => 'pres.', 'f' => 'fut.', 'a' => 'aor.', 'i' => 'imp.', 'x' => 'perf.', 'y' => 'pluperf.', 'z' => 'futperf.'),
            array('a' => 'act.', 'm' => 'mid.', 'p' => 'pass.', 'e' => 'mid/pass.'),
            array('n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'),
            array('m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.'),
        );
        $infinitives = array(
            array('v' => 'verb'),
            array('n' => 'inf.'),
            array('p' => 'pres.', 'f' => 'fut.', 'a' => 'aor.', 'i' => 'imp.', 'x' => 'perf.', 'y' => 'pluperf.', 'z' => 'futperf.'),
            array('a' => 'act.', 'm' => 'mid.', 'p' => 'pass.', 'e' => 'mid/pass.'),
        );
        $otherVerbsMoods = array(
            array('v' => 'verb'),
            array('i' => 'ind.', 'd' => 'imp.', 's' => 'subj.', 'o' => 'opt.'),
            array('p' => 'pres.', 'f' => 'fut.', 'a' => 'aor.', 'i' => 'imp.', 'x' => 'perf.', 'y' => 'pluperf.', 'z' => 'futperf.'),
            array('a' => 'act.', 'm' => 'mid.', 'p' => 'pass.', 'e' => 'mid/pass.'),
            array('1' => '1p.', '2' => '2p.', '3' => '3p.'),
            array('s' => 'sing.', 'p' => 'plu.')
        );
        $adj = array(
            array('a' => 'adj.'),
            array('n' => 'norm.', 's' => 'possesive', 'd' => 'demonstrative', 'q' => 'interr.', 'i' => 'indef.', 't' => 'intensive', 'c' => 'card. num.', 'o' => 'ord. num.', 'm' => 'numeral'),
            array('n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'),
            array('m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.'),
            array('s' => 'sing.', 'p' => 'plu.'),
            array('c' => 'comparative', 's' => 'superlative', 'n' => ''),
        );
        $adjOthers = array(
            array('a' => 'adj.'),
            array('n' => 'norm.', 's' => 'possesive', 'd' => 'demonstrative', 'q' => 'interr.', 'i' => 'indef.', 't' => 'intensive', 'c' => 'card. num.', 'o' => 'ord. num.', 'm' => 'numeral'),
        );
        $art = array(
            array('d' => 'art.'),
            array('n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'),
            array('m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.'),
            array('s' => 'sing.', 'p' => 'plu.'),
        );
        $pro = array(
            array('r' => 'pro.'),
            array('p' => 'pers.', 'r' => 'relative', 'd' => 'demonstrative', 'q' => 'interr.', 'i' => 'indefinite', 't' => 'intensive', 'x' => 'refl.', 'e' => 'reciprocal'),
            array('n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'),
            array('m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.', '-' => ''),
            array('s' => 'sing.', 'p' => 'plu.'),
        );
        $proOther = array(
                array('r' => 'pro.'),
                array('p' => 'pers.', 'r' => 'relative', 'd' => 'demonstrative', 'q' => 'interr.', 'i' => 'indefinite', 't' => 'intensive', 'x' => 'refl.', 'e' => 'reciprocal'),
                array('n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'),
        );
        $conj = array(
            array('c' => 'conjunction'),
            array('s' => 'subordinate', 'c' => 'coordinating'),
        );
        $prep = array(
                array('p' => 'prep.'),
                array('g' => '+ gen.', 'd' => '+ dat.', 'a' => '+ acc.', 'p' => ''),
        );
        $various = array(
            'b' => 'adverb',
            'x' => 'particle',
            't' => 'indeclinable noun',
            'i' => 'interjection',
        );
        
        $return = array();
        $return = array_merge($return, $this->codeHelper('', '', $nouns));
        $return = array_merge($return, $this->codeHelper('', '', $participles));
        $return = array_merge($return, $this->codeHelper('', '', $infinitives));
        $return = array_merge($return, $this->codeHelper('', '', $otherVerbsMoods));
        $return = array_merge($return, $this->codeHelper('', '', $partShort));
        $return = array_merge($return, $this->codeHelper('', '', $adj));
        $return = array_merge($return, $this->codeHelper('', '', $adjOthers));
        $return = array_merge($return, $this->codeHelper('', '', $art));
        $return = array_merge($return, $this->codeHelper('', '', $pro));
        $return = array_merge($return, $this->codeHelper('', '', $proOther));
        $return = array_merge($return, $this->codeHelper('', '', $prep));
        $return = array_merge($return, $this->codeHelper('', '', $conj));
        $return = array_merge($return, $various);
        return $return;
    }
    
    /**
     * Appends the keys of the first array of $array onto $key
     * and appends the values of the element of $array onto $text
     * then shifts the first element off and 
     * @param unknown $text
     * @param array $array
     */
    private function codeHelper($key, $text, array $arrays)
    {
        if (is_null($arrays))
            throw \Exception("HELP, my arrays is null");
        if ($arrays === array())
            return array($key => $text);
        if (!is_array($arrays[0]))
            throw \Exception("HELP my arrays isn't an array");
        if ($arrays[0] === array())
            throw \Exception("HELP");
        
        $return = array();
        $cArray = array_shift($arrays);
        foreach ($cArray as $keyi => $valuei) {
            $return = array_merge($return, $this->codeHelper($key.$keyi, $text.' '.$valuei, $arrays));
        }
        return $return;
    }
    
    public function importAction()
    {
        $translation = $this->params('translation');
        
        $file = file($translation.".txt");
        $preg = "/(?P<book>\\w{3,3}) (?P<chapter>\\d\\d{0,2}):(?P<verse>\\d\\d{0,2}) {1,2}(?P<text>.*)/iu";
        $i = 0;
        $unParsed = array();
        foreach ($file as $line) {
            if (preg_match($preg, $line, $cline)) {
                $parsed[] = $cline;
            }
            else {
                $unParsed[] = $cline;
            }
        }
        
        if ($this->getRequest()->isPost()) {
            $verses = array();
            $imported = 0;
            $failed = 0;
            $table = $this->getVerseTable();
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
    
    /**
     * @return SionTable
     */
    public function getVerseTable()
    {
        if (!$this->verseTable) {
            $sm = $this->getServiceLocator();
            $this->verseTable = $sm->get('Bible\Model\VerseTable');
        }
        return $this->verseTable;
    }
    
    private function transcode($text)
    {
        $replaced = $text;
        static $rules = array(
                'Ξ' => '[',
                'Π' => ']',
                '~A' => 'Ἁ',
                '~E' => 'Ἑ',
                '~I' => 'Ἱ ',
                '~O' => 'Ὁ',
                '~H' => 'Ἡ',
                '~U' => 'Ὑ',
                '~R' => 'Ῥ',
                '~W' => 'Ὡ',
                'i?' => 'ϊ',
                'u?' => 'ϋ',
                'a,|' => 'ᾴ',
                'h,|' => 'ῄ',
                'w,|' => 'ῴ',
                'a|,' => 'ᾴ',
                'h|,' => 'ῄ',
                'w|,' => 'ῴ',
                'a.|' => 'ᾲ',
                'h.|' => 'ῂ',
                'w.|' => 'ῲ',
                'a|.' => 'ᾲ',
                'h|.' => 'ῂ',
                'w|.' => 'ῲ',
                'a/|' => 'ᾷ',
                'h/|' => 'ῇ',
                'w/|' => 'ῷ',
                'a|/' => 'ᾷ',
                'h|/' => 'ῇ',
                'w|/' => 'ῷ',
                'av|' => 'ᾀ',
                'hv|' => 'ᾐ',
                'wv|' => 'ᾠ',
                'a|v' => 'ᾀ',
                'h|v' => 'ᾐ',
                'w|v' => 'ᾠ',
                'a;|' => 'ᾄ',
                'h;|' => 'ᾔ',
                'w;|' => 'ᾤ',
                'a|;' => 'ᾄ',
                'h|;' => 'ᾔ',
                'w|;' => 'ᾤ',
                'a\'|' => 'ᾂ',
                'h\'|' => 'ᾒ',
                'w\'|' => 'ᾢ',
                'a|\'' => 'ᾂ',
                'h|\'' => 'ᾒ',
                'w|\'' => 'ᾢ',
                'a=|' => 'ᾆ',
                'h=|' => 'ᾖ',
                'w=|' => 'ᾦ',
                'a|=' => 'ᾆ',
                'h|=' => 'ᾖ',
                'w|=' => 'ᾦ',
                'a`|' => 'ᾁ',
                'h`|' => 'ᾑ',
                'w`|' => 'ᾡ',
                'a|`' => 'ᾁ',
                'h|`' => 'ᾑ',
                'w|`' => 'ᾡ',
                'a[|' => 'ᾅ',
                'h[|' => 'ᾕ',
                'w[|' => 'ᾥ',
                'a|[' => 'ᾅ',
                'h|[' => 'ᾕ',
                'w|[' => 'ᾥ',
                'a|]' => 'ᾃ',
                'h|]' => 'ᾓ',
                'w|]' => 'ᾣ',
                'a]|' => 'ᾃ',
                'h]|' => 'ᾓ',
                'w]|' => 'ᾣ',
                'a-|' => 'ᾇ',
                'h-|' => 'ᾗ',
                'w-|' => 'ᾧ',
                'a|-' => 'ᾇ',
                'h|-' => 'ᾗ',
                'w|-' => 'ᾧ',
                'A|' => 'ᾼ',
                'H|' => 'ῌ',
                'W|' => 'ῼ',
                'a|' => 'ᾳ',
                'h|' => 'ῃ',
                'w|' => 'ῳ',
                'av' => 'ἀ',
                'ev' => 'ἐ',
                'iv' => 'ἰ',
                'ov' => 'ὀ',
                'uv' => 'ὐ',
                'hv' => 'ἠ',
                'wv' => 'ὠ',
                'a/' => 'ᾶ',
                'i/' => 'ῖ',
                'u/' => 'ῦ',
                'h/' => 'ῆ',
                'w/' => 'ῶ',
                'A,' => 'Ά',
                'E,' => 'Έ',
                'I,' => 'Ί',
                'O,' => 'Ό',
                'U,' => 'Ύ',
                'H,' => 'Ή',
                'W,' => 'Ώ',
                'a,' => 'ά',
                'e,' => 'έ',
                'i,' => 'ί',
                'o,' => 'ό',
                'u,' => 'ύ',
                'h,' => 'ή',
                'w,' => 'ώ',
                'VA' => 'Ἀ',
                'VE' => 'Ἐ',
                'VI' => 'Ἰ',
                'VO' => 'Ὀ',
                'VH' => 'Ἠ',
                'VW' => 'Ὠ',
                '{A' => 'Ἅ',
                '{E' => 'Ἕ',
                '{I' => 'Ἵ',
                '{O' => 'Ὅ',
                '{U' => 'Ὕ',
                '{H' => 'Ἥ',
                '{W' => 'Ὥ',
                'a=' => 'ἆ',
                'i=' => 'ἶ',
                'u=' => 'ὖ',
                'h=' => 'ἦ',
                'w=' => 'ὦ',
                '}A' => 'Ἃ',
                '}E' => 'Ἓ',
                '}I' => 'Ἳ',
                '}O' => 'Ὃ',
                '}U' => 'Ὓ',
                '}H' => 'Ἣ',
                '}W' => 'Ὣ',
                ':A' => 'Ἄ',
                ':E' => 'Ἔ',
                ':I' => 'Ἴ',
                ':O' => 'Ὄ',
                ':H' => 'Ἤ',
                ':W' => 'Ὤ',
                'a`' => 'ἁ',
                'e`' => 'ἑ',
                'i`' => 'ἱ',
                'o`' => 'ὁ',
                'u`' => 'ὑ',
                'h`' => 'ἡ',
                'w`' => 'ὡ',
                'a;' => 'ἄ',
                'e;' => 'ἔ',
                'i;' => 'ἴ',
                'o;' => 'ὄ',
                'u;' => 'ὔ',
                'h;' => 'ἤ',
                'w;' => 'ὤ',
                'a[' => 'ἅ',
                'e[' => 'ἕ',
                'i[' => 'ἵ',
                'o[' => 'ὅ',
                'u[' => 'ὕ',
                'h[' => 'ἥ',
                'w[' => 'ὥ',
                'a]' => 'ἃ',
                'e]' => 'ἓ',
                'i]' => 'ἳ',
                'o]' => 'ὃ',
                'u]' => 'ὓ',
                'h]' => 'ἣ',
                'w]' => 'ὣ',
                'a.' => 'ὰ',
                'e.' => 'ὲ',
                'i.' => 'ὶ',
                'o.' => 'ὸ',
                'u.' => 'ὺ',
                'h.' => 'ὴ',
                'w.' => 'ὼ',
                'a\'' => 'ἂ',
                'e\'' => 'ἒ',
                'i\'' => 'ἲ',
                'o\'' => 'ὂ',
                'u\'' => 'ὒ',
                'h\'' => 'ἢ',
                'w\'' => 'ὢ',
                'a-' => 'ἇ',
                'i-' => 'ἷ',
                'u-' => 'ὗ',
                'h-' => 'ἧ',
                'w-' => 'ὧ',
    
                'A' => 'Α',
                'E' => 'Ε',
                'I' => 'Ι',
                'O' => 'Ο',
                'U' => 'Υ',
                'H' => 'Η',
                'W' => 'Ω',
                'a' => 'α',
                'e' => 'ε',
                'i' => 'ι',
                'o' => 'ο',
                'u' => 'υ',
                'h' => 'η',
                'w' => 'ω',
                '\\' => '·',
                ')' => '.',
                '!' => '+',
                '@' => '[',
                '#' => ']',
                '$' => '(',
                '%' => ')',
                '^' => '*',
                '&' => '-',
                '*' => ';',
                'Î' => '[',
                'Ð' => ']',
                'Å' => '.',
                'V' => '\'',
                'È' => ';',
                'Ε' => '.',
    
    
                'B' => 'Β',
                'b' => 'β',
                'C' => 'Χ',
                'c' => 'χ',
                'D' => 'Δ',
                'd' => 'δ',
                'F' => 'Φ',
                'f' => 'φ',
                'G' => 'Γ',
                'g' => 'γ',
                'j' => 'ς',
                'K' => 'Κ',
                'k' => 'κ',
                'L' => 'Λ',
                'l' => 'λ',
                'M' => 'Μ',
                'm' => 'μ',
                'N' => 'Ν',
                'n' => 'ν',
                'P' => 'Π',
                'p' => 'π',
                'Q' => 'Θ',
                'q' => 'θ',
                'R' => 'Ρ',
                'r' => 'ρ',
                'S' => 'Σ',
                's' => 'σ',
                'T' => 'Τ',
                't' => 'τ',
                'X' => 'Ξ',
                'x' => 'ξ',
                'Y' => 'Ψ',
                'y' => 'ψ',
                'Z' => 'Ζ',
                'z' => 'ζ',
                '(' => ',',
        );
        foreach ($rules as $r => $value) {
            if (strlen($value))
            {
                $replaced = str_replace($r, $value, $replaced);
            }
        }
        return $replaced;
    }
}