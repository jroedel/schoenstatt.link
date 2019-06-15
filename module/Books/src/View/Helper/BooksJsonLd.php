<?php
namespace Books\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Spatie\SchemaOrg\Schema;

class BooksJsonLd extends AbstractHelper
{
    public static $fieldSchemaPropertyMap = [
        'authorsText'                   => 'authorsText',
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
        'title'                         => 'name', //should this be title?
        'numberOfPages'                 => 'numberOfPages',
        'publisher'                     => 'publisher',
        'translatorPersonId'            => 'translator',
        'url'                           => 'url',
        'image'                         => 'image',
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
                case 'authorsText':
//                     if (!empty($publication['authorPersons'])) {

//                     } else
                    if (!is_null($publication['authorsText'])) {
                        $authors = $publication['authorsText'];
                        $authorObjects = [];
                        foreach ($authors as $authorName) {
                            $authorObjects[] = Schema::person()->name($authorName);
                        }
                        if (1 === count($authorObjects)) {
                            $authorObjects = $authorObjects[0];
                        }
                        $book->author($authorObjects);
                    }
                    break;
                case 'sameAs': //urls
                    $sameAs = [];
                    foreach ($publication['urls'] as $urlObject) {
                        $sameAs[] = $urlObject['url'];
                    }
                    if (1 === count($sameAs)) {
                        $sameAs = $sameAs[0];
                    }
                    $book->$property($sameAs);
                    break;
                case 'translatedFromPublication':
                    if (is_array($publication['translatedFromPublication'])) {
                        $translatedFrom = $this->generateBookSchema($publication['translatedFromPublication']);
                        $book->$property($translatedFrom);
                    }
                    break;
                case 'url':
                    $book->url($this->view->localeUrl(
                        'en_US',
                        'publications/publication',
                        ['publication_id' => $publication['publicationId']]
                    )->__toString());
                    break;
                case 'keywords':
                    if (!empty($publication[$field])) {
                        $book->$property(implode(',', $publication[$field]));
                    }
                    break;
                case 'containedIn':
                    //isBasedOnUrl too
                    ;
                    break;
                case 'publisher':
                    //first check if we have a publisherAssociation
//                     if (!is_null($publication['publisherAssociation'])) {
//                         $book->publisher($this->view->schoenstattJsonLd('association', $publication['publisherAssociation']));
//                     } else
                    if (isset($publication['publisher'])) {//else, publisher
                        $publisher = Schema::organization()->name($publication['publisher']);
                        $book->publisher($publisher);
                    }
                    break;
//                                     case 'bookFormatType':
//                                         $book->bookFormat(Schema::bookFormatType($publication[$field]));
//                                         break;
                case 'image':
                    if (file_exists(sprintf('public/covers/%s.jpg', $publication['publicationId']))) {
                        $imageUrl = sprintf('https://schoenstatt.link/covers/%s.jpg', $publication['publicationId']);
                        $book->image($imageUrl);
                    }
                    break;
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
/* Example from https://developers.google.com/search/docs/data-types/books
 * <script type="application/ld+json">
{
  "@context":"http://schema.org",
  "@type":"Book",
  "name" : "The Catcher in the Rye",
  "author": {
    "@type":"Person",
    "name":"J.D. Salinger"
  },
  "url" : "http://www.barnesandnoble.com/store/info/offer/JDSalinger",
  "workExample" : [{
    "@type": "Book",
    "isbn": "031676948",
    "bookEdition": "2nd Edition",
    "bookFormat": "http://schema.org/Hardcover",
    "potentialAction":{
    "@type":"ReadAction",
    "target":
      {
        "@type":"EntryPoint",
        "urlTemplate":"http://www.barnesandnoble.com/store/info/offer/0316769487?purchase=true",
        "actionPlatform":[
          "http://schema.org/DesktopWebPlatform",
          "http://schema.org/IOSPlatform",
          "http://schema.org/AndroidPlatform"
        ]
      },
      "expectsAcceptanceOf":{
        "@type":"Offer",
        "Price":6.99,
        "priceCurrency":"USD",
        "eligibleRegion" : {
          "@type":"Country",
          "name":"US"
        },
        "availability": "http://schema.org/InStock"
      }
    }
  },{
    "@type": "Book",
    "isbn": "031676947",
    "bookEdition": "1st Edition",
    "bookFormat": "http://schema.org/EBook",
    "potentialAction":{
    "@type":"ReadAction",
    "target":
      {
        "@type":"EntryPoint",
        "urlTemplate":"http://www.barnesandnoble.com/store/info/offer/031676947?purchase=true",
        "actionPlatform":[
          "http://schema.org/DesktopWebPlatform",
          "http://schema.org/IOSPlatform",
          "http://schema.org/AndroidPlatform"
        ]
      },
      "expectsAcceptanceOf":{
        "@type":"Offer",
        "Price":1.99,
        "priceCurrency":"USD",
        "eligibleRegion" : {
          "@type":"Country",
          "name":"UK"
        },
        "availability": "http://schema.org/InStock"
      }
    }
  }]
}
</script>
 */
