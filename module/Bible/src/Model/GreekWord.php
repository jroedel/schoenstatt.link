<?php
namespace Bible\Model;

use Bible\Filter\FromBibleworksMorphosyntacticCode;
use voku\helper\UTF8;

class GreekWord
{
    const BNM_WORD_GRAMMAR = '/(.+)@([a-z])(.*)/u';

    protected $root;

    protected $partOfSpeech;

    protected $morphologyText;

    protected $morphologyCode;

    public function __construct($bntWord, $bnmWord)
    {
        $partsOfSpeechCodes = FromBibleworksMorphosyntacticCode::getPartOfSpeechCodes();
        $matches = null;
        preg_match(self::BNM_WORD_GRAMMAR, $bnmWord, $matches, PREG_OFFSET_CAPTURE, 0);
        if (! isset($matches)) {
            throw new \Exception("Unrecognized BNM word: $bnmWord");
        }
        $this->root = UTF8::filter($matches[1][0]);
        $partOfSpeechCode = $matches[2][0];
        if (! isset($partsOfSpeechCodes[$partOfSpeechCode])) {
            throw new \Exception("Unrecognized part of speech: $partOfSpeechCode");
        }
        $this->partOfSpeech = $partsOfSpeechCodes[$partOfSpeechCode];
        $this->morphologyCode = $partOfSpeechCode . $matches[3][0];
//         $word = [
//             'root' => $this->root,
//             'partOfSpeech' => $this->partOfSpeech,
//             'morphologyCode' => $this->morphologyCode,
//         ];
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function getPartOfSpeech()
    {
        return $this->partOfSpeech;
    }

    public function getMorphologyText()
    {
        if (isset($this->morphologyText)) {
            return $this->morphologyText;
        }
        $codes = FromBibleworksMorphosyntacticCode::getPartOfSpeechCodes();
        if (is_string($this->morphologyCode) && isset($codes[$this->morphologyCode])) {
            $this->morphologyText = $codes[$this->morphologyCode];
        }
        return $this->morphologyText;
    }

    public function getMorphologyCode()
    {
        return $this->morphologyCode;
    }
}
