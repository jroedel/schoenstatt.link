<?php

namespace Books\Filter;

use Laminas\Filter\AbstractFilter;
use Laminas\Filter\Exception\InvalidArgumentException;

/**
 * Builds a library book's sort text from its call number plus book metadata.
 *
 * Extends Laminas\Filter\AbstractFilter rather than Laminas\Filter\PregReplace,
 * which laminas marked `@final`. PregReplace only ever supplied
 * setPattern()/getPattern() here — filter() was overridden wholesale and the
 * replacement API is refused outright — so those two methods are now local.
 */
class SortText extends AbstractFilter
{
    const PARAMETER_TOKENS = [
        'collectionAbbreviation',
        'inLanguage',
        'author',
        'title'
    ];
    protected $options = [
        'pattern'     => null,
        'sortText' => '',
    ];

    //@todo move sortText out of $options
    /**
     * The resolved printf format string without special parameters. Set together with the pattern
     * @var string $form
     */
    protected $format;

    /**
     * The number of parameters in $format
     * @var int $formatParameterCount
     */
    protected $formatParameterCount;

    protected $parametersToAppendToRegexCaptureGroups = [];

    protected $captureGroupCount = 0;

    public function __construct($pattern, $sortText)
    {
        //the order is important
        $this->setSortText($sortText);
        $this->setPattern($pattern);
        mb_internal_encoding("UTF-8");
    }

    public function filter($book)
    {
        if (! is_array($book)) {
            return null;
        }
        //@todo make sure we have `format` and `parameters...`
//         var_dump($book);
        $callNumber = $book['callNumber'];
        if (isset($callNumber) && is_string($callNumber)) {
            //match regex against value
            $regex = $this->getPattern();
            $matches = null;
//             var_dump($matches);
            if (0 == preg_match($regex, $callNumber, $matches)) {
                return null;
                var_dump("no match: `" . $regex . "` `" . $callNumber . "` count: " . mb_strlen($callNumber));
            }
            array_shift($matches);
            //the regex may end with optional capture groups
            while (count($matches) < $this->captureGroupCount) {
                $matches[] = "";
            }
        } else {
            $matches = [];
        }
        $additionalParams = $this->resolveMetaParametersToValues($book);
        $params = array_merge($matches, $additionalParams);
        $format = $this->format;
        if (count($params) !== $this->formatParameterCount) {
            //@todo log this. Think if we should maybe chop off extra params if we have more than necessary
            return null;
        }
        $result = vsprintf($format, $params);
        return $result;
    }

    protected function resolveMetaParametersToValues($book)
    {
        static $fourCharUCaseFilter;
        if (! isset($fourCharUCaseFilter)) {
            $fourCharUCaseFilter = new FourCharUCase();
        }
        $paramNames = $this->parametersToAppendToRegexCaptureGroups;
//         var_dump($paramNames);
        $results = [];
        foreach ($paramNames as $paramName) {
            switch ($paramName) {
                case 'collectionAbbreviation': //@todo always 4 char
                    $results[] = isset($book['collectionAbbreviation']) ? $book['collectionAbbreviation'] : "";
                    break;
                case 'inLanguage': //always 2 chars
                    $results[] = isset($book['inLanguage']) && is_array($book['inLanguage'])
                        && isset($book['inLanguage'][0])
                        ? substr($book['inLanguage'][0], 0, 2)
                        : '';
                    break;
                case 'author': //always 3 chars
                    $results[] = $fourCharUCaseFilter->filter($book['authorsText']);
                    break;
                case 'title': //always 3 chars
                    $results[] = $fourCharUCaseFilter->filter($book['title']);
                    break;
                default:
                    ;
                    break;
            }
        }
//         var_dump($results);
        return $results;
    }

    /**
     *
     * @param string $format
     * @return number
     */
    protected function countPrintfParameters($format)
    {
        if (! isset($format) || ! is_string($format)) {
            return 0;
        }
        $re = '/%(?:\d+\$)?[-\ddfsu]+/';

        $matches = null;
        if (! preg_match_all($re, $format, $matches)) {
            return 0;
        }
        return count($matches[0]);
    }

    public function setPattern($pattern)
    {
        //this will check the validity of the pattern
        $this->validatePattern($pattern);
        $this->options['pattern'] = $pattern;

        //check how many capturing groups there are in the regex group
        $this->captureGroupCount = $this->countPatternCaptureGroups();
//         var_dump($this->captureGroupCount);

        //do the replacements to calculate the format, and $parametersToAppendToRegexCaptureGroups
        $tokenRegex = '/\{([^}]*?)(?:\|([^}]+))?\}/';
        $sortTextFormatWithTokens = $this->getSortText();

        $currentTokenNumber = $this->captureGroupCount + 1;
        $matches = null;
        $offset = 0;
        $parametersToAppendToRegexCaptureGroups = [];
        $finalFormat = '';
//         var_dump($sortTextFormatWithTokens.' len '.mb_strlen($sortTextFormatWithTokens));
        //loop through special tokens in the format string. Tokens may include their own printf formats
        while (preg_match($tokenRegex, $sortTextFormatWithTokens, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $tokenName = $matches[1][0];
//             var_dump('matched: '.$matches[0][0]);
            $tokenPosition = $matches[0][1];
            $tokenLength = mb_strlen($matches[0][0]);
            $newOffset = $tokenPosition + $tokenLength;
            if ($tokenPosition > $offset) { //we skipped over some stuff, fill in finalFormat
//                 var_dump('Addingg: '.mb_substr($sortTextFormatWithTokens, $offset, $tokenPosition - $offset));
                $finalFormat .= mb_substr($sortTextFormatWithTokens, $offset, $tokenPosition - $offset);
            }

            //validate the parameter token name
            if (! in_array($tokenName, self::PARAMETER_TOKENS, true)) {
                throw new \InvalidArgumentException('Bad token name: ' . $tokenName);
            }
            $parametersToAppendToRegexCaptureGroups[] = $tokenName;

            //find printf format to associate with this new parameter
            $isFormatInluded = count($matches) === 3;
            //assign a partial format string to this token
            if ($isFormatInluded) {
                //@todo add some validation
                $tokenFormat = $matches[2][0];
            } else {
                $tokenFormat = '%' . $currentTokenNumber . '$s';
            }
            $currentTokenNumber++;
//             var_dump('Adding: '.$tokenFormat);
            $finalFormat .= $tokenFormat;
//             var_dump('new offset: '.$newOffset);
            $offset = $newOffset;
        }
//         var_dump('final offset: '.$offset);
        //fill in the stuff at the end of the string
        if (mb_strlen($sortTextFormatWithTokens) > $offset) {
//             var_dump($sortTextFormatWithTokens);
//             var_dump('Addinggg '.(mb_strlen($sortTextFormatWithTokens) - $offset).' chars from '.$offset.': '.mb_substr($sortTextFormatWithTokens, $offset, mb_strlen($sortTextFormatWithTokens) - $offset));
            $finalFormat .= substr($sortTextFormatWithTokens, $offset, mb_strlen($sortTextFormatWithTokens) - $offset);
        }
//         var_dump('final format: '.$finalFormat);

        $this->formatParameterCount = $this->countPrintfParameters($finalFormat);
        if (0 === $this->formatParameterCount) {
            throw new \Exception('Invalid sort text format, no capture groups set: ' . $finalFormat);
        }
        $specialParams = count($parametersToAppendToRegexCaptureGroups);
        $totalParameterCount = $specialParams + $this->captureGroupCount;
        if ($totalParameterCount < $this->formatParameterCount) {
            throw new \Exception("The sort text format `$finalFormat` contains "
                . $this->formatParameterCount
                . " params, but we only have $this->captureGroupCount regex params and $specialParams special params.");
        }
//         var_dump($finalFormat);
        $this->format = $finalFormat;
        $this->parametersToAppendToRegexCaptureGroups = $parametersToAppendToRegexCaptureGroups;

        return $this;
    }

    /**
     * Get the currently set match pattern
     *
     * @return string|null
     */
    public function getPattern()
    {
        return $this->options['pattern'];
    }

    /**
     * Reproduced from Laminas\Filter\PregReplace, which this filter used to
     * extend. Note it checks only for the "e" modifier — it does not verify
     * that the pattern compiles — and that leniency is preserved deliberately.
     *
     * @param string $pattern
     * @throws InvalidArgumentException If the pattern carries the "e" modifier.
     */
    protected function validatePattern($pattern)
    {
        if (! preg_match('/(?<modifier>[imsxeADSUXJu]+)$/', $pattern, $matches)) {
            return;
        }

        if (str_contains($matches['modifier'], 'e')) {
            throw new InvalidArgumentException(sprintf(
                'Pattern for a PregReplace filter may not contain the "e" pattern modifier; received "%s"',
                $pattern
            ));
        }
    }

    /**
     * Return the number of capture groups in the pattern regex
     * @throws \Exception
     * @return number
     */
    protected function countPatternCaptureGroups()
    {
        $pattern = $this->getPattern();
        if (! isset($pattern)) {
            throw new \Exception('No pattern set');
        }
        $re = '/(\(\?|\\\\\[|\[(?:\\\\\]|.)*?\]|\\\\\(|[^(])+/';

        $matches = null;
        if (! preg_match_all($re, $pattern, $matches, PREG_SET_ORDER, 0)) {
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
