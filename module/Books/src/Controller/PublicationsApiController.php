<?php
namespace Books\Controller;

use Laminas\View\Model\JsonModel;
use Books\Model\PublicationsTable;
use RestApi\Controller\ApiController;

/**
 * Extends ApiController so that 'isAuthorizationRequired' on this controller's
 * routes means something — see the note on LibrariesApiController. The
 * /api/v1/literature routes are deliberately public (see the module config), so
 * this changes nothing about who may read them today; it makes the route config
 * the authority on that, instead of the class hierarchy.
 */
class PublicationsApiController extends ApiController
{
    const PUBLICAITON_API_FIELDS = [
        'publicationId', 'title', 'subtitle', 'authorsText', 'bookEdition',
        'categoryId', 'inLanguage', 'description', 'isbn',
        'editorsText', 'translatorsText', 'numberOfPages',
        'copyrightYear', 'copyrightInfo', 'datePublishedText', 'publisher',
        'publishingPlace', 'datePublished', 'publishingStatus', 'bookFormatType',
        'mainPublicationId', 'translatedFromPublicationId', 'volumeNumber', 'containedIn',
        'containedInIsbn', 'genre', 'keywords', 'isAccessibleForFree',
        'isScientificWork', 'hasNoExplictEditionNumber', 'hasNoISBN', 'isRevisedWithBookInHand',
        'isFormallyPublished', 'urls', 'editionNotes', 'publicNotes',
        'categoryName', 'categorySort', 'isSubEdition'
    ];

    /**
     * @var PublicationsTable $libraryTable
     */
    protected $table;

    /**
     * @var array $config
     */
    protected $config;

    public function __construct(PublicationsTable $table, array $config)
    {
        $this->setIdentifierName('publication_id');
        $this->table = $table;
        $this->config = $config;
    }

    public function getList()
    {
//         $params = $this->params()->fromQuery();
        $table = $this->table;
        $objects = $table->searchPublications();
        self::prepPublicationObjects($objects);

        return new JsonModel([
            'items'         => array_values($objects),
        ], ['prettyPrint' => false]);
    }

    public function get($id)
    {
        $table = $this->table;
        $object = $table->getPublication($id);
        self::prepPublicationObject($object);
        return new JsonModel($object, ['prettyPrint' => true]);
    }

    protected static function prepPublicationObjects(array &$objects)
    {
        $objectIds = array_keys($objects);
        foreach ($objectIds as $key) {
            self::prepPublicationObject($objects[$key]);
        }
    }

    /**
     * This function formats a library array from the LibraryTable for standard API output
     * @param array $data
     */
    protected static function prepPublicationObject(array &$data)
    {
        $dataKeys = array_keys($data);
        foreach ($dataKeys as $key) {
            if (! in_array($key, self::PUBLICAITON_API_FIELDS, true)) {
                unset($data[$key]);
            }
        }
        self::jsonSerializeDateTimeObjects($data);
    }

    /**
     * Recursively look for Datetime objects and serialize them for use in JSON
     * @param array $data
     */
    public static function jsonSerializeDateTimeObjects(array &$data)
    {
        foreach ($data as $key => $value) {
            if ($value instanceof \DateTime) {
                $data[$key] = $value->format('Y-m-d\TH:i:s\Z');
            } elseif (is_array($value)) {
                self::jsonSerializeDateTimeObjects($data[$key]);
            }
        }
    }

    protected function sendFailedMessage($message, $statusCode = 401)
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $response->sendHeaders();
        $response->setContent($message);
        return $response;
    }
}
