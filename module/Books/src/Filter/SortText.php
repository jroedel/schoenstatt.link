<?php
namespace Books\Filter;

use Zend\Filter\PregReplace;

class SortText extends PregReplace
{
    const PARAMETER_TOKENS = [
        'collectionAbbreviation',
        'inLanguage'
    ];
    protected $options = [
        'pattern'     => null,
        'sortText' => '',
    ];
    
    /**
     * Set together with the pattern
     * @var string $form
     */
    protected $format;
    
    protected $parametersToAppendToRegexCaptureGroups = [];
    
    protected $captureGroupCount = 0;
    
    public function __construct($pattern, $sortText)
    {
        $this->setSortText($sortText);
        parent::__construct($pattern);
    }
    
    public function filter($book)
    {
        if (!is_array($book)) {
            return null;
        }
//         var_dump($book);
        $callNumber = $book['callNumber'];
        if (isset($callNumber) && is_string($callNumber)) {
            //match regex against value
            $regex = $this->getPattern();
            $matches = null;
//             var_dump($matches);
            if (!preg_match($regex, $callNumber, $matches)) {
                return null;
            }
            array_shift($matches);
        } else {
            $matches = [];
        }
        $additionalParams = $this->resolveMetaParametersToValues($book);
        $params = array_merge($matches, $additionalParams);
//         var_dump($params);
        $format = $this->getSortText();
        array_unshift($params, $format);
        $result = call_user_func_array('sprintf', $params);
        return $result;
    }
    
    protected function resolveMetaParametersToValues($book)
    {
        $paramNames = $this->getParametersToAppendToRegexCaptureGroups();
//         var_dump($paramNames);
        $results = [];
        foreach ($paramNames as $paramName) {
            switch ($paramName) {
                case 'collectionAbbreviation':
                    $results[] = isset($book['collectionAbbreviation']) ? $book['collectionAbbreviation'] : "";
                    break;
                case 'inLanguage':
                    $results[] = isset($book['inLanguage']) && is_array($book['inLanguage']) 
                        && isset($book['inLanguage'][0]) 
                        ? substr($book['inLanguage'][0], 0, 2)
                        : '';
                    break;
                default:
                    ;
                break;
            }
        }
//         var_dump($results);
        return $results;
    }
    
    public function setPattern($pattern)
    {
        //this will check the validity of the pattern
        parent::setPattern($pattern);
        
        //check how many capturing groups there are in the regex group
        $captureGroupCount = $this->countPatternCaptureGroups();
//         var_dump($captureGroupCount);
        
        //do the replacements to calculate the format, and $parametersToAppendToRegexCaptureGroups
        $tokenRegex = '/\{([^}]*?)(?:\|([^}]+))?\}/';
        $sortTextFormatWithTokens = $this->getSortText();
        
        $currentTokenNumber = $captureGroupCount + 1;
        $matches = null;
        $offset = 0;
        $parametersToAppendToRegexCaptureGroups = [];
        $finalFormat = '';
//         var_dump($sortTextFormatWithTokens);
        //loop through special tokens in the format string. Tokens may include their own printf formats
        while (preg_match($tokenRegex, $sortTextFormatWithTokens, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $tokenName = $matches[1][0];
            $tokenPosition = $matches[0][1];
            $tokenLength = mb_strlen($matches[0][0]);
            $newOffset = $offset + $tokenPosition + $tokenLength;
            if ($tokenPosition > $offset) { //we skipped over some stuff, fill in finalFormat
                $finalFormat .= mb_substr($sortTextFormatWithTokens, $offset, $tokenPosition - $offset);
            }
            
            //validate the parameter token name
            if (!in_array($tokenName, self::PARAMETER_TOKENS, true)) {
                throw new \InvalidArgumentException('Bad token name: '.$tokenName);
            }
            $parametersToAppendToRegexCaptureGroups[] = $tokenName;
            
            //find printf format to associate with this new parameter
            $isFormatInluded = count($matches) === 3;
            //assign a partial format string to this token
            if ($isFormatInluded) {
                //@todo add some validation
                $tokenFormat = $matches[2][0];
            } else {
                $tokenFormat = '%'.$currentTokenNumber.'$s';
            }
            $currentTokenNumber++;
            $finalFormat .= $tokenFormat;
            $offset = $newOffset;
        }
        
        //fill in the stuff at the end of the string
        if (mb_strlen($sortTextFormatWithTokens) > $offset) {
            $finalFormat .= mb_substr($sortTextFormatWithTokens, $offset, mb_strlen($sortTextFormatWithTokens) - $offset);
        }
//         var_dump($finalFormat);
        
        $this->setSortText($finalFormat);
        $this->setParametersToAppendToRegexCaptureGroups($parametersToAppendToRegexCaptureGroups);
        
        return $this;
    }
    
    /**
     * Return the number of capture groups in the pattern regex
     * @throws \Exception
     * @return number
     */
    protected function countPatternCaptureGroups()
    {
        $pattern = $this->getPattern();
        if (!isset($pattern)) {
            throw new \Exception('No pattern set');
        }
        $re = '/(\(\?|\\\\\[|\[(?:\\\\\]|.)*?\]|\\\\\(|[^(])+/';
        
        $matches = null;
        if (!preg_match_all($re, $pattern, $matches, PREG_SET_ORDER, 0)) {
            return 0;
        }
        $count = count($matches) - 1;
        return $count;
    }
    
    public function setSortText($sortText)
    {
        $this->options['sortText'] = $sortText;
        return $this;
    }
    
    public function getSortText()
    {
        return $this->options['sortText'];
    }

    public function getParametersToAppendToRegexCaptureGroups()
    {
        return $this->parametersToAppendToRegexCaptureGroups;
    }
    
    public function setParametersToAppendToRegexCaptureGroups($parametersToAppendToRegexCaptureGroups)
    {
        if (!is_array($parametersToAppendToRegexCaptureGroups)) {
            throw new \InvalidArgumentException('Argument must be an array');
        }
        $this->parametersToAppendToRegexCaptureGroups = $parametersToAppendToRegexCaptureGroups;
    }
    /**
     * This function shouldn't be used
     */
    public function setReplacement($replacement)
    {
        throw new \Exception('Not available for this class');
    }
    
    public function getReplacement()
    {
        throw new \Exception('Not available for this class');
    }
}