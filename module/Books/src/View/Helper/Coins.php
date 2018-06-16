<?php
namespace Books\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Spatie\SchemaOrg\Schema;
use OpenURL\ContextObject;

class Coins extends AbstractHelper
{
    public static $fieldPropertyMap = [
        'name'                  => 'rft.btitle',
        'authorText'            => 'rft.au',
        'inLanguage'            => 'rft.language',
        'numberOfPages'         => 'rft.tpages',
        'bookEdition'           => 'rft.edition',
        'copyrightYear'         => 'rft.date',
        'isbn'                  => 'rft.isbn',
        'numberOfPages'         => 'rft.tpages',
        'publisher'             => 'rft.publisher',
        'publishingPlace'       => 'rft.place',
        //'rft.aufirst'                   => '',
        //'rft.aulast'                    => '',
        //'bookFormatTypeUrl'             => 'bookFormat',
        //'copyrightYear'                 => 'copyrightYear',
        //'description'                   => 'description',
        //'genre'                         => 'genre',
        //'illustrator'                   => 'illustrator',
        //'translatedFromPublication'     => 'isBasedOn',
        //'containedIn'                   => 'isPartOf',
        //'bookCoverFile'                 => 'image',
        //'isAccessibleForFree'           => 'isAccessibleForFree',
        //'keywords'                      => 'keywords',
        //'translatorPersonId'            => 'translator',
        //'url'                           => 'url',
        //'image'                         => 'image',
    ];

    public function __invoke($entityType, $object)
    {
        $openUrlObj = null;
        switch ($entityType) {
            case 'publication':
                $coinsArray = $this->generateCoinsAttributes($object);
                $openUrlObj = ContextObject::loadArray($coinsArray);
                break;
            default:
                throw new \InvalidArgumentException('Helper only accepts publication objects');
                break;
        }
        if ($openUrlObj instanceof ContextObject) {
            return $openUrlObj->toCoins();
        }
        return '';
    }

    public function generateCoinsAttributes($publication)
    {
        $coinsArray = [
            'url_ver' => 'Z39.88-2004',
            'ctx_ver' => 'Z39.88-2004',
            'rfr_id'  => 'info:sid/schoenstatt.link:'.$publication['publicationId'],
            'rft_val_fmt' => 'info:ofi/fmt:kev:mtx:book',
            'rft.genre' => 'book', //@todo update
        ];

        //special: author, 'sameAs', translatedFromPublicationId (isBasedOnUrl)
        foreach (self::$fieldPropertyMap as $field => $property) {
            switch ($field) {
                case 'authorText':
                    var_dump($publication['authorsText']);
                    if (!empty($publication['authorPersons'])) {
                        // @todo this
                    } elseif (isset($publication['authorsText'])) {
                        $authors = $publication['authorsText'];
                        $authorObjects = [];
                        foreach ($authors as $authorName) {
                            $authorObjects[] = $authorName;
                        }
                        if (1 === count($authorObjects)) {
                            $authorObjects = $authorObjects[0];
                        }
                        $coinsArray[$property] = $authorObjects;
                    }
                    break;
//                 case 'sameAs': //urls
//                     $sameAs = [];
//                     foreach ($publication['urls'] as $urlObject) {
//                         $sameAs[] = $url['url'];
//                     }
//                     if (1 === count($sameAs)) {
//                         $sameAs = $sameAs[0];
//                     }
//                     $book->$property($sameAs);
//                     break;
//                 case 'translatedFromPublication':
//                     if (is_array($publication['translatedFromPublication'])) {
//                         $translatedFrom = $this->generateBookSchema($publication['translatedFromPublication']);
//                         $book->$property($translatedFrom);
//                     }
//                     break;
                case 'url':
                    $book->url($this->view->localeUrl('en_US', 'publications/publication', ['publication_id' => $publication['publicationId']])->__toString());
//                 case 'keywords':
//                     if (!empty($publication[$field])) {
//                         $book->$property(implode(',', $publication[$field]));
//                     }
//                     break;
                case 'containedIn':
                    //isBasedOnUrl too
                    ;
                    break;
                case 'publisher':
                    //first check if we have a publisherAssociation
                    if (isset($publication['publisherAssociation'])) {
                        //$book->publisher($this->view->schoenstattJsonLd('association', $publication['publisherAssociation']));
                        $coinsArray[$property] = $publication['publisherAssociation']['name'];
                    } elseif (isset($publication['publisher'])) {//else, publisher
                        $coinsArray[$property] = $publication['publisher'];
                    }
                    break;
                case 'isbn':
                    if (isset($publication['isbn'])) {
                        $isbn = $publication['isbn'];
                        $coinsArray[$property] = $isbn;
                        $coinsArray['rft_id'] = "urn:isbn:$isbn";
                    }
                    break;
//                                     case 'bookFormatType':
//                                         $book->bookFormat(Schema::bookFormatType($publication[$field]));
//                                         break;
//                 case 'image':
//                     if (file_exists(sprintf('public/covers/%s.jpg', $publication['publicationId']))) {
//                         $imageUrl = sprintf('https://schoenstatt.link/covers/%s.jpg', $publication['publicationId']);
//                         $book->image($imageUrl);
//                     }
//                     break;
                default:
                    if (array_key_exists($field, $publication) && isset($publication[$field]) &&
                        (!is_array($publication[$field]) || !empty($publication[$field]))
                    ) {
                        $coinsArray[$property] = $publication[$field];
                    }
                    break;
            }
        }
        return $coinsArray;
    }
}

