<?php
namespace Bible\Filter;

use Laminas\Filter\AbstractFilter;

class FromBibleworksMorphosyntacticCode extends AbstractFilter
{
    protected $codes;

    /**
     *
     * {@inheritDoc}
     * @see \Laminas\Filter\FilterInterface::filter()
     */
    public function filter($value)
    {
        if (! is_string($value)) {
            return $value;
        }
        $this->getCodes();
        if (isset($this->codes[$value])) {
            return $this->codes[$value];
        }
        return $value;
    }

    public static function getPartOfSpeechCodes()
    {
        return [
            'n' => 'noun',
            'v' => 'verb',
            'a' => 'adjective',
            'd' => 'article',
            'r' => 'pronoun',
            'c' => 'conjunction',
            'p' => 'prep.',
            'b' => 'adverb',
            'x' => 'particle',
            't' => 'noun',
            'i' => 'interjection',
        ];
    }

    /**
     * Get a full list of possible morphosyntactic codes with their english equivalent
     * @return array
     */
    public static function getGrammarCodes()
    {
        $nouns = [
            ['n' => 'noun'],
            ['n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'],
            ['m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.'],
            ['s' => 'sing.', 'p' => 'plu.'],
            ['c' => 'comm.', 'p' => 'prop.'],

        ];
        $participles = [
            ['v' => 'verb'],
            ['p' => 'part.'],
            ['p' => 'pres.', 'f' => 'fut.', 'a' => 'aor.', 'i' => 'imp.', 'x' => 'perf.', 'y' => 'pluperf.', 'z' => 'futperf.'],
            ['a' => 'act.', 'm' => 'mid.', 'p' => 'pass.', 'e' => 'mid/pass.'],
            ['n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'],
            ['m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.'],
            ['s' => 'sing.', 'p' => 'plu.'],
        ];
        $partShort = [
            ['v' => ''],
            ['p' => 'part.'],
            ['p' => 'pres.', 'f' => 'fut.', 'a' => 'aor.', 'i' => 'imp.', 'x' => 'perf.', 'y' => 'pluperf.', 'z' => 'futperf.'],
            ['a' => 'act.', 'm' => 'mid.', 'p' => 'pass.', 'e' => 'mid/pass.'],
            ['n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'],
            ['m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.'],
        ];
        $infinitives = [
            ['v' => 'verb'],
            ['n' => 'inf.'],
            ['p' => 'pres.', 'f' => 'fut.', 'a' => 'aor.', 'i' => 'imp.', 'x' => 'perf.', 'y' => 'pluperf.', 'z' => 'futperf.'],
            ['a' => 'act.', 'm' => 'mid.', 'p' => 'pass.', 'e' => 'mid/pass.'],
        ];
        $otherVerbsMoods = [
            ['v' => 'verb'],
            ['i' => 'ind.', 'd' => 'imp.', 's' => 'subj.', 'o' => 'opt.'],
            ['p' => 'pres.', 'f' => 'fut.', 'a' => 'aor.', 'i' => 'imp.', 'x' => 'perf.', 'y' => 'pluperf.', 'z' => 'futperf.'],
            ['a' => 'act.', 'm' => 'mid.', 'p' => 'pass.', 'e' => 'mid/pass.'],
            ['1' => '1p.', '2' => '2p.', '3' => '3p.'],
            ['s' => 'sing.', 'p' => 'plu.'],
            ];
        $adj = [
            ['a' => 'adj.'],
            ['n' => 'norm.', 's' => 'possesive', 'd' => 'demonstrative', 'q' => 'interr.', 'i' => 'indef.', 't' => 'intensive', 'c' => 'card. num.', 'o' => 'ord. num.', 'm' => 'numeral'],
            ['n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'],
            ['m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.'],
            ['s' => 'sing.', 'p' => 'plu.'],
            ['c' => 'comparative', 's' => 'superlative', 'n' => ''],
        ];
        $adjOthers = [
            ['a' => 'adj.'],
            ['n' => 'norm.', 's' => 'possesive', 'd' => 'demonstrative', 'q' => 'interr.', 'i' => 'indef.', 't' => 'intensive', 'c' => 'card. num.', 'o' => 'ord. num.', 'm' => 'numeral'],
        ];
        $art = [
            ['d' => 'art.'],
            ['n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'],
            ['m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.'],
            ['s' => 'sing.', 'p' => 'plu.'],
        ];
        $pro = [
            ['r' => 'pro.'],
            ['p' => 'pers.', 'r' => 'relative', 'd' => 'demonstrative', 'q' => 'interr.', 'i' => 'indefinite', 't' => 'intensive', 'x' => 'refl.', 'e' => 'reciprocal'],
            ['n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'],
            ['m' => 'masc.', 'f' => 'fem.', 'n' => 'neu.', '-' => ''],
            ['s' => 'sing.', 'p' => 'plu.'],
        ];
        $proOther = [
            ['r' => 'pro.'],
            ['p' => 'pers.', 'r' => 'relative', 'd' => 'demonstrative', 'q' => 'interr.', 'i' => 'indefinite', 't' => 'intensive', 'x' => 'refl.', 'e' => 'reciprocal'],
            ['n' => 'nom.', 'g' => 'gen.', 'd' => 'dat.', 'a' => 'acc.', 'v' => 'voc.'],
        ];
        $conj = [
            ['c' => 'conjunction'],
            ['s' => 'subordinate', 'c' => 'coordinating'],
        ];
        $prep = [
            ['p' => 'prep.'],
            ['g' => '+ gen.', 'd' => '+ dat.', 'a' => '+ acc.', 'p' => ''],
        ];
        $various = [
            'b' => 'adverb',
            'x' => 'particle',
            't' => 'indeclinable noun',
            'i' => 'interjection',
        ];

        $return = [];
        $return = array_merge($return, self::codeHelper('', '', $nouns));
        $return = array_merge($return, self::codeHelper('', '', $participles));
        $return = array_merge($return, self::codeHelper('', '', $infinitives));
        $return = array_merge($return, self::codeHelper('', '', $otherVerbsMoods));
        $return = array_merge($return, self::codeHelper('', '', $partShort));
        $return = array_merge($return, self::codeHelper('', '', $adj));
        $return = array_merge($return, self::codeHelper('', '', $adjOthers));
        $return = array_merge($return, self::codeHelper('', '', $art));
        $return = array_merge($return, self::codeHelper('', '', $pro));
        $return = array_merge($return, self::codeHelper('', '', $proOther));
        $return = array_merge($return, self::codeHelper('', '', $prep));
        $return = array_merge($return, self::codeHelper('', '', $conj));
        $return = array_merge($return, $various);
        return $return;
    }

    /**
     * Appends the keys of the first array of $array onto $key
     * and appends the values of the element of $array onto $text
     * then shifts the first element off and
     * @param string $text
     * @param array $array
     */
    private static function codeHelper($key, $text, array $arrays)
    {
        if (is_null($arrays)) {
            throw \Exception("HELP, my arrays is null");
        }
        if (empty($arrays)) {
            return [$key => $text];
        }
        if (! is_array($arrays[0])) {
            throw \Exception("HELP my arrays isn't an array");
        }
        if (empty($arrays[0])) {
            throw \Exception("HELP");
        }

        $return = [];
        $cArray = array_shift($arrays);
        foreach ($cArray as $keyi => $valuei) {
            $return = array_merge($return, self::codeHelper($key . $keyi, $text . ' ' . $valuei, $arrays));
        }
        return $return;
    }
}
