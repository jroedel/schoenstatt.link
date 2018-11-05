<?php
namespace Schoenstatt\Model;

use SionModel\Filter\MixedCase;

class AssociationKind
{
    /**
     * Name of the entity. camelCase.
     * Example: 'person'
     * @var string $name
     */
    public $name;

    /**
     * User-readable name
     * @var string
     */
    public $label;

    /**
     * Label translated into the current default locale
     * @var string
     */
    public $translatedLabel;
    /**
     * Number used to sort in a list of mixed association kinds
     * @var number
     */
    public $sort;
    /**
     * A string format to be processed with sprintf unless the concrete association
     * overrides the format.
     * @var string
     */
    public $nameFormat;
    /**
     * The localized name format
     */
    public $translatedNameFormat;
    /**
     * Should the name parameter be translated?, If it's a city name, probably not.
     * @var bool
     */
    public $shouldTranslateNameParameter = false;
    /**
     * Is the association kind necessarily organized at a diocesan level?
     * With exception to the umbrella Diocesan Movement.
     * @var string
     */
    public $isSubDiocesanAssociation = false;
    /**
     * A class name of a schema type of the Spatie/schema.org library
     * @var string $schemaType
     */
    public $schemaType;
    /**
     * A list of roles to automatically create when this kind is instantiated
     * @var array
     */
    public $defaultRoles = [];

    public function __construct($name, $entitySpecification)
    {
        if (is_null($name)) {
            throw new \InvalidArgumentException('Name is a required parameter.');
        }
        if (!is_array($entitySpecification)) {
            throw new \InvalidArgumentException('Entity specification must be an array.');
        }
        $this->name = $name;
        $camelCaseFilter = new MixedCase('_');
        foreach ($entitySpecification as $key => $value) {
            $key = $camelCaseFilter->filter($key);
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        if (is_null($this->sort) || !is_numeric($this->sort)) {
            $this->sort = 999;
        }
    }

    /**
     * Check that entity has specified all required information for deleting
     * an instance using the SionController::deleteEntityAction
     * @return boolean
     */
    public function hasNameFormat()
    {
        return !is_null($this->nameFormat);
    }

//     /**
//      * Check that the entity has the necessary configuration to update/create using SionTable
//      * @return boolean
//      */
//     public function isEnabledForUpdateAndCreate()
//     {
//         return !is_null($this->tableName) && !is_null($this->tableKey) &&
//             !is_null($this->getObjectFunction) &&
//             !is_null($this->updateColumns) && !empty($this->updateColumns);
//     }
}
