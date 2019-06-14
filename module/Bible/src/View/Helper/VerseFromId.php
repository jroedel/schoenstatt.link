<?php
namespace Bible\View\Helper;

use Zend\View\Helper\AbstractHelper;

class VerseFromId extends AbstractHelper
{
    protected $books;
    
    public function __construct(array $books)
    {
        $this->books = $books;
    }
    
    public function __invoke($verseId)
    {
        if (!isset($verseId)) {
            return '';
        }
        $verseId = (string)$verseId;
        if (!isset($verseId) || !is_string($verseId) || '' === $verseId) {
            return '';
        }
        $re = '/^(\d\d)(\d\d\d)(\d\d\d)$/';
        $matches = null;
        preg_match($re, $verseId, $matches);
        if (!isset($matches)) {
            throw new \Exception('Invalid verse id');
        }
        $bookId = (int)$matches[1];
        $chapter = (int)$matches[2];
        $verse = (int)$matches[3];
        $url = $this->view->url('bible/text', [
            'book' => $bookId,
            'chapter' => $chapter]).'#'.$verse;
        if (isset($this->books[$bookId])) {
            //@todo add the NJB abbreviation to table to use here
            $bookAbbreviation = isset($this->books[$bookId]['abbreviationJerusalemEs'])
                ? $this->books[$bookId]['abbreviationJerusalemEs']
                : $this->books[$bookId]['name'];
            $format = "%s %s,%s";
            $text = sprintf($format, $bookAbbreviation, $chapter, $verse);
        } else {
            $text = $verseId;
        }
        $linkFormat = '<a href="%s">%s</a>';
        $result = sprintf($linkFormat, $url, $text);
        return $result;
    }
}
