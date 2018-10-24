<?php
namespace Schoenstatt\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Spatie\SchemaOrg\Schema;

class SchoenstattJsonLd extends AbstractHelper
{
    public static $organizationFieldSchemaPropertyMap = [
        'formattedName'                 => 'name',
    ];

    public static $personFieldSchemaPropertyMap = [
        'fullName'                      => 'name',
    ];

    public function __invoke($entityType, $object)
    {
        $schemaObject = null;
        switch ($entityType) {
            case 'association':
                $schemaObject = $this->generateOrganizationSchema($object);
                break;
            case 'person':
                $schemaObject = $this->generatePersonSchema($object);
                break;
            default:
                throw new \InvalidArgumentException('Helper only accepts publication objects');
                break;
        }
        if (is_object($schemaObject)) {
            return $schemaObject->toScript();
        }
    }

    public function generatePersonSchema($object)
    {
        $schema = Schema::person();

        foreach (self::$personFieldSchemaPropertyMap as $field => $property) {
            switch ($field) {
                case 'containedIn':
                    //isBasedOnUrl too
                    ;
                    break;
                default:
                    if (array_key_exists($field, $object) && !is_null($object[$field]) &&
                    (!is_array($object[$field]) || !empty($object[$field]))
                    ) {
                        $book->$property($object[$field]);
                    }
                    break;
            }
        }
        return $schema;
    }

    public function generateOrganizationSchema($object)
    {
        $schema = Schema::organization();

        foreach (self::$organizationFieldSchemaPropertyMap as $field => $property) {
            switch ($field) {
                case 'containedIn':
                    //isBasedOnUrl too
                    ;
                    break;
                default:
                    if (array_key_exists($field, $object) && !is_null($object[$field]) &&
                    (!is_array($object[$field]) || !empty($object[$field]))
                    ) {
                        $book->$property($object[$field]);
                    }
                    break;
            }
        }
        return $schema;
    }
}
