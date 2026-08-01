<?php
namespace Bible\Model;

use SionModel\Db\Model\SionTable;
use Laminas\Db\Sql\Predicate\Operator;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Predicate\In;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Predicate\IsNull;
use Bible\Filter\FromBibleworksMorphosyntacticCode;
use voku\helper\UTF8;

class BibleTable extends SionTable
{
    const TRANSLATIONS = [
        'njb' => 'New Jerusalem Bible',
        'bnt' => 'BW 5 New Testament Greek',
        'bnm' => 'BW 5 New Testament Greek Morphology',
        'bnm2' => 'BW 5 New Testament Greek Morphology',
        'jeresp' => 'Biblia de Jerusalén',
        'septnt' => 'Septuagint + NT',
    ];
    /**
     *
        if ($book['isNewTestament']) {
            $translations = ['njb', 'bnt', 'jeresp'];
        } else {
            $translations = ['njb', 'septnt', 'jeresp'];
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
            $select->order(['verse_id', 'translation_id']); //perhaps this should be the other way around?
        }
        return $select;
    }

    public function processVerseRow($row)
    {
        $data = [
            'id' => $this->filterDbId($row['id']),
            'translation' => $row['translation_id'],
            'verseId' => $this->filterDbId($row['verse_id']),
            'book' => $this->filterDbId($row['book_id']),
            'chapter' => (int)$row['chapter'],
            'verse' => $this->filterDbId($row['verse']),
            'text' => $row['text'],
        ];
        return $data;
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
            'abbreviationJerusalemEn' => $row['abbreviation_jerusalem_en'],
            'abbreviationJerusalemEs' => $row['abbreviation_jerusalem_es'],
            'isNewTestament' => $this->filterDbBool($row['is_new_testament']),
            'genreId' => $this->filterDbBool($row['genre_id']),
            'isCanonical' => $this->filterDbBool($row['is_canonical']),
            'chapterCount' => $this->filterDbInt($row['chapter_count']),
            'oldBookId' => $row['old_book_id'],
        ];
        return $data;
    }

    /**
     * Takes an array of verses and double keys them, first my verseId, then by translation in an associative array
     * @param mixed[] $verses
     * @return mixed[]
     */
    public static function keyVersesByVerseIdAndTranslation($verses)
    {
        $verseTranslations = [];
        foreach ($verses as $object) {
            $verseId = $object['verseId'];
            $translationId = $object['translation'];
            if (! isset($verseTranslations[$verseId])) {
                $verseTranslations[$verseId] = [];
            }
            $verseTranslations[$verseId][$translationId] = $object;
        }
        return $verseTranslations;
    }

    protected function processBookAbbreviationRow($row)
    {
        $data = [
            'abbreviationId' => $this->filterDbId($row['id']),
            'abbreviation' => $row['abbreviation'],
            'bookId' => $this->filterDbId($row['book_id']),
            'language' => $row['language'],
            'isPreferred' => $this->filterDbBool($row['is_preferred']),
        ];
        return $data;
    }

    /**
     * Get a simple associative array that maps bible book abbreviations to their respective bookId.
     * Lower case versions are automatically added
     * @return int[]
     */
    public function getBookAbbreviationMap()
    {
        $cacheKey = 'book-abbreviation-map';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $abbreviationMap = [];

        $objects = $this->getObjects('bible-book-abbreviation');
        foreach ($objects as $object) {
            $abbreviationMap[strtolower($object['abbreviation'])] = $object['bookId'];
        }

        $objects = $this->getObjects('bible-book');
        foreach ($objects as $object) {
            $abbreviationMap[strtolower($object['name'])] = $object['bookId'];
            if (isset($object['nameEs'])) {
                $abbreviationMap[strtolower($object['nameEs'])] = $object['bookId'];
            }
            if (isset($object['nameDe'])) {
                $abbreviationMap[strtolower($object['nameDe'])] = $object['bookId'];
            }
            if (isset($object['namePt'])) {
                $abbreviationMap[strtolower($object['namePt'])] = $object['bookId'];
            }
            if (isset($object['nameFr'])) {
                $abbreviationMap[strtolower($object['nameFr'])] = $object['bookId'];
            }
        }

        $this->cacheEntityObjects($cacheKey, $abbreviationMap, ['bible-book-abbreviation']);
        return $abbreviationMap;
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
            if (! isset($bookNumberLookup[$bookAbbrev])) {
                throw new \Exception('Unknown book: ' . $bookAbbrev);
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
//         var_dump($updates);
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

    public function createGreekReference()
    {
        /*
         * Tis sould actually be 3 separate steps:
         * 1. Import reek dictionary
         * 2. Import reek words
         * 3. Import verse words
         * Greek words:
            - word_id
            - Word root
            - Part of speech
            - First occurence (link to a bible verse)
            - Number of occurences
            - Género (for nouns)
            - Paradigm part 1 (noun: Ns, adj: Nsm, verb: Aps1)
            - Paradigm part 2 (noun: Gs, adj: Nsf, verb: aoristo)
            - Paradigm part 3 (noun: artículo, adj: Nsf, verb: futuro)
            - Paradigm part 4 (verb: i forget)
            - FormsAvailable (pipe-separated list of forms)
            - DictionaryEntryId (for greek dictionary reference)

            Verse words:
            - id
            - verse_id
            - root_word_id
            - word (actual word from the text, not the root version)
            - form (perhaps just the code that we have for the sake of saving space)
            - order (which number word is it in the sentence; we'll have to learn how to cut contractions off properly)
         */
        $greekCheck = $this->getObjects('greek-root');
        $shouldInsertGreek = empty($greekCheck);
        $verses = $this->getObjects('bible-verse', ['translation' => ['bnt2', 'bnm2']]);
        $verseTranslations = self::keyVersesByVerseIdAndTranslation($verses);
//         $regex = '/(.+)@(.{1,6})/ui';
        $bntCleanupSubstitution = '/[,.\]\[\(\)·;]/u';
        $morphoFilter = new FromBibleworksMorphosyntacticCode();
        $morphoCodes = $morphoFilter->getGrammarCodes();

        $rootWords = [];
        $verseWords = [];

        $i = 0;
        foreach ($verseTranslations as $verseId => $verse) {
//             if ($i > 1000) {
//                 break;
//             }
            if (! isset($verse['bnt2']) || ! isset($verse['bnm2'])) {
                throw new \Exception('Missing entry ' . $verseId);
            }
            $textBnt = UTF8::trim($verse['bnt2']['text']);
            $textBnm = UTF8::trim($verse['bnm2']['text']);
            if ('' === $textBnt || '' === $textBnm) {
                continue;
            }
            $wordsBnt = preg_split("/[ ᾽]+/u", $textBnt);
            $wordsBnm = preg_split("/[ ᾽]+/u", $textBnm);

            for ($j = count($wordsBnm) - 1; $j >= 0; $j--) {
                //remove non-words. In Bnm, all should have a '@'
                if (! is_string($wordsBnm[$j])) {
                    unset($wordsBnm[$j]);
                    continue;
                }
                $value = $wordsBnm[$j];
                if (false === strpos($value, '@')) {
                    var_dump("Removing word '$value' from verse $verseId");
                    unset($wordsBnm[$j]);
                }
            }
            //cleanup punctuation
            for ($j = count($wordsBnt) - 1; $j >= 0; $j--) {
                if (! is_string($wordsBnt[$j])) {
                    unset($wordsBnt[$j]);
                    continue;
                }
                $cleanWord = trim(preg_replace($bntCleanupSubstitution, '', $wordsBnt[$j]));
                if ('' === $cleanWord) {
                    unset($wordsBnt[$j]);
                }
            }

            if (count($wordsBnt) !== count($wordsBnm)) {
                var_dump($textBnt);
                var_dump($wordsBnt);
                var_dump($wordsBnm);
                throw new \Exception('Uneven arrays at ' . $verseId);
            }
            foreach ($wordsBnm as $wordKey => $wordWithCode) {
//                 $matches = null;
//                 preg_match($regex, $wordWithCode, $matches);
//                 if (!isset($matches[1])) {
//                     var_dump($textBnt);
//                     var_dump($textBnm);
//                     var_dump($wordsBnt);
//                     var_dump($wordsBnm);
//                     throw new \Exception('Missing root at '.$verseId);
//                 }
//                 $root = $matches[1];
//                 if (!isset($matches[2])) {
//                     throw new \Exception('Missing morpho at '.$verseId);
//                 }
//                 $code = $matches[2];
//                 if (!isset($morphoCodes[$code])) {
//                     var_dump("Missing morpho code '$code' at $verseId");
// //                     throw new \Exception("Missing morpho code '$code' at $verseId");
//                 }
                $word = new GreekWord($wordsBnt[$wordKey], $wordsBnm[$wordKey]);
                $root = $word->getRoot();
                $lowerRoot = UTF8::strtolower($root);
                if (! isset($rootWords[$lowerRoot])) {
                    $rootWords[$lowerRoot] = [
                        'root' => $root,
                        'partOfSpeech' => $word->getPartOfSpeech(),
                        'occurrenceCount' => 1,
                        'firstVerseOccurrence' => $verseId,
                        'formsAvailable' => [$word->getMorphologyCode()],
                        'johnOccurrenceCount' => 0,
                        'johnFirstVerseOccurrence' => null,
                    ];
                } else {
                    $rootWords[$lowerRoot]['occurrenceCount']++;
                    $morphoCode = $word->getMorphologyCode();
                    if (! in_array($morphoCode, $rootWords[$lowerRoot]['formsAvailable'], true)) {
                        $rootWords[$lowerRoot]['formsAvailable'][] = $morphoCode;
                    }
                }
                if ($verse['bnt2']['book'] == 43) {
                    $rootWords[$lowerRoot]['johnOccurrenceCount']++;
                    if (! isset($rootWords[$lowerRoot]['johnFirstVerseOccurrence'])) {
                        $rootWords[$lowerRoot]['johnFirstVerseOccurrence'] = $verseId;
                    }
                }
            }
            $i++;
        }
        ksort($rootWords);
        if ($shouldInsertGreek) {
            foreach ($rootWords as $object) {
                try {
                    $this->createEntity('greek-root', $object);
                } catch (\Exception $e) {
                    var_dump($object);
                }
            }
        }
        return $rootWords;
    }

    public function normalizeBnmUtf8($sourceTranslation, $destinationTranslation)
    {
        $check = $this->getObjects('bible-verse', ['translation' => $destinationTranslation]);
        if (! empty($check)) {
            throw new \Exception("Tried creating $destinationTranslation bible again");
        }
        $verses = $this->getObjects('bible-verse', ['translation' => $sourceTranslation]);
        foreach ($verses as $object) {
            $aNewVerse = $object;
            unset($aNewVerse['id']);
            $aNewVerse['translation'] = $destinationTranslation;
            $aNewVerse['text'] = UTF8::filter($object['text']);
            $this->createEntity('bible-verse', $aNewVerse);
        }
    }

    protected function processGreekRootRow($row)
    {
        $root = $row['root'];
        $partOfSpeech = $row['part_of_speech'];
        $paradigmPart1 = $row['paradigm_part_1'];
        $paradigmPart2 = $row['paradigm_part_2'];
        $paradigmPart3 = $row['paradigm_part_3'];
        $paradigmPart4 = $row['paradigm_part_4'];
        $paradigm = null;
        if (in_array($partOfSpeech, ['noun', 'verb', 'adjective'], true)) {
            $paradigm = $root . ', '
                . (isset($paradigmPart2) ? $paradigmPart2 : '———') . ', '
                . (isset($paradigmPart3) ? $paradigmPart3 : '———');
            if ('verb' === $partOfSpeech) {
                $paradigm .= ', ' . (isset($paradigmPart4) ? $paradigmPart4 : '———');
            }
        }
        $data = [
            'rootId' => $this->filterDbId($row['root_id']),
            'root' => $root,
            'partOfSpeech' => $partOfSpeech,
            'firstVerseOccurrence' => $row['first_verse_occurrence'],
            'occurrenceCount' => $row['occurrence_count'],
            'gender' => $row['gender'],
            'paradigmPart1' => $paradigmPart1,
            'paradigmPart2' => $paradigmPart2,
            'paradigmPart3' => $paradigmPart3,
            'paradigmPart4' => $paradigmPart4,
            'formsAvailable' => $this->filterDbArray($row['forms_available']),
            'dictionaryEntryId' => $row['dictionary_entry_id'],
            'johnOccurrenceCount' => $row['john_occurrence_count'],
            'johnFirstVerseOccurrence' => $row['john_first_verse_occurrence'],

            'paradigm' => $paradigm,
        ];
        return $data;
    }

    /**
     * @todo get rid of this, unecessary
     * @return string[]
     */
    public function getBookAbbrev()
    {
        static $abbrevs;
        if (! isset($abbrevs)) {
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
