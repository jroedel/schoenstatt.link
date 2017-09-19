<?php
namespace Books\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Spatie\SchemaOrg\Schema;

class BooksJsonLd extends AbstractHelper
{
    public static $fieldSchemaPropertyMap = [
        'author'                        => 'author',
        'bookEdition'                   => 'bookEdition',
        'bookFormatTypeUrl'             => 'bookFormat',
        'copyrightYear'                 => 'copyrightYear',
        'datePublished'                 => 'datePublished',
        'description'                   => 'description',
        'genre'                         => 'genre',
        'isbn'                          => 'isbn',
        'illustrator'                   => 'illustrator',
        'inLanguage'                    => 'inLanguage',
        'translatedFromPublication'     => 'isBasedOn',
        'containedIn'                   => 'isPartOf',
        'bookCoverFile'                 => 'image',
        'isAccessibleForFree'           => 'isAccessibleForFree',
        'keywords'                      => 'keywords',
        'title'                         => 'name',
        'numberOfPages'                 => 'numberOfPages',
        'publisher'                     => 'publisher',
        'translatorPersonId'            => 'translator',
    ];

    public function __invoke($entityType, $object)
    {
        $schemaObject = null;
        switch ($entityType) {
            case 'publication':
                $schemaObject = $this->generateBookSchema($object);
                break;
            default:
                throw new \InvalidArgumentException('Helper only accepts publication objects');
                break;
        }
        if (is_object($schemaObject)) {
            return $schemaObject->toScript();
        }
    }

    public function generateBookSchema($publication)
    {
        $book = Schema::book();

        //special: author, 'sameAs', translatedFromPublicationId (isBasedOnUrl)
        foreach (self::$fieldSchemaPropertyMap as $field => $property) {
            switch ($field) {
                case 'author':

                    break;
                case 'sameAs': //urls
                    $sameAs = [];
                    foreach ($publication['urls'] as $urlObject) {
                        $sameAs[] = $url['url'];
                    }
                    if (1 === count($sameAs)) {
                        $sameAs = $sameAs[0];
                    }
                    $book->$property($sameAs);
                    break;
                case 'translatedFromPublication':
                    if (is_array($publication['translatedFromPublication'])) {
                        $book->$property($this->generateSchemaObject($publication['translatedFromPublication']));
                    }
                    break;
                case 'containedIn':
                    //isBasedOnUrl too
                    ;
                    break;
                case 'publisher':
                    //first check if we have a publisherAssociation
                    if (!is_null($publication['publisherAssociation'])) {
                        $book->publisher($this->view->schoenstattJsonLd('association', $publication['publisherAssociation']));
                    } elseif (!is_null($publication['publisher'])) {//else, publisher
                        $book->publisher($publication['publisher']);
                    }
                    break;
//                                     case 'bookFormatType':
//                                         $book->bookFormat(Schema::bookFormatType($publication[$field]));
//                                         break;

                default:
                    if (array_key_exists($field, $publication) && !is_null($publication[$field]) &&
                        (!is_array($publication[$field]) || !empty($publication[$field]))
                    ) {
                        $book->$property($publication[$field]);
                    }
                    break;
            }
        }
        return $book;
    }
}
