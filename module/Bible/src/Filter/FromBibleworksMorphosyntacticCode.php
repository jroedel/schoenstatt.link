<?php
namespace Bible\Filter;

use Zend\Filter\AbstractFilter;

class FromBibleworksMorphosyntacticCode extends AbstractFilter
{
    protected $codes;
    
    /**
     * 
     * {@inheritDoc}
     * @see \Zend\Filter\FilterInterface::filter()
     */
    public function filter($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        $this->getCodes();
        if (isset($this->codes[$value])) {
            return $this->codes[$value];
        }
        return $value;
    }
    
    /**
     * Get a full list of possible morphosyntactic codes with their english equivalent
     * @return array
     */
    public function getGrammarCodes()
    {
        if (isset($this->codes)) {
            return $this->codes;
        }
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
        $this->codes = $return;
        return $this->codes;
    }
    
    /**
     * Appends the keys of the first array of $array onto $key
     * and appends the values of the element of $array onto $text
     * then shifts the first element off and
     * @param string $text
     * @param array $array
     */
    private function codeHelper($key, $text, array $arrays)
    {
        if (is_null($arrays)) {
            throw \Exception("HELP, my arrays is null");
        }
        if ($arrays === array()) {
            return array($key => $text);
        }
        if (!is_array($arrays[0])) {
            throw \Exception("HELP my arrays isn't an array");
        }
        if ($arrays[0] === array()) {
            throw \Exception("HELP");
        }
        
        $return = array();
        $cArray = array_shift($arrays);
        foreach ($cArray as $keyi => $valuei) {
            $return = array_merge($return, $this->codeHelper($key.$keyi, $text.' '.$valuei, $arrays));
        }
        return $return;
    }
}