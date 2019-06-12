<?php

namespace GraphQL\SchemaGenerator\CodeGenerator;

use GraphQL\Enumeration\FieldTypeKindEnum;
use GraphQL\SchemaGenerator\CodeGenerator\CodeFile\ClassFile;
use GraphQL\SchemaObject\QueryObject;
use GraphQL\Util\StringLiteralFormatter;

/**
 * Class QueryObjectClassBuilder
 *
 * @package GraphQL\SchemaManager\CodeGenerator
 */
class QueryObjectClassBuilder extends ObjectClassBuilder
{
    /** @var array  */
    protected $singleInstanceObject = ["Edges", "Node"];
    
    /**
     * QueryObjectClassBuilder constructor.
     *
     * @param string $writeDir
     * @param string $objectName
     * @param string $namespace
     */
    public function __construct(string $writeDir, string $objectName, string $namespace = self::DEFAULT_NAMESPACE)
    {
        $className = $objectName . 'QueryObject';

        $this->classFile = new ClassFile($writeDir, $className);
        $this->classFile->setNamespace($namespace);
        if ($namespace !== self::DEFAULT_NAMESPACE) {
            $this->classFile->addImport('GraphQL\\SchemaObject\\QueryObject');
        }
        $this->classFile->extendsClass('QueryObject');

        // Special case for handling root query object
        if ($objectName === QueryObject::ROOT_QUERY_OBJECT_NAME) {
            $objectName = 'query';
        }
        $this->classFile->addConstant('OBJECT_NAME', $objectName);
    }

    /**
     * @param string $fieldName
     */
    public function addScalarField(string $fieldName)
    {
        $upperCamelCaseProp = StringLiteralFormatter::formatUpperCamelCase($fieldName);
        $this->addSimpleSelector($fieldName, $upperCamelCaseProp);
    }

    /**
     * @param string $fieldName
     * @param string $typeName
     * @param string $argsObjectName
     */
    public function addObjectField(string $fieldName, string $typeName, string $argsObjectName, string $typeKind)
    {
        $upperCamelCaseProp = StringLiteralFormatter::formatUpperCamelCase($fieldName);
        $this->addObjectSelector($fieldName, $upperCamelCaseProp, $typeName, $argsObjectName, $typeKind);
    }

    /**
     * @param string $propertyName
     * @param string $upperCamelName
     */
    protected function addSimpleSelector(string $propertyName, string $upperCamelName)
    {
        $method = "public function select$upperCamelName()
{
    \$this->selectField(\"$propertyName\");

    return \$this;
}";
        $this->classFile->addMethod($method);
    }

    /**
     * @param string $fieldName
     * @param string $upperCamelName
     * @param string $fieldTypeName
     * @param string $argsObjectName
     */
    protected function addObjectSelector(string $fieldName, string $upperCamelName, string $fieldTypeName, string $argsObjectName, string $typeKind)
    {
        $objectKind = $this->getObjectKind($typeKind);
        $objectClassName  = $fieldTypeName . $objectKind;
        $argsMapClassName = $argsObjectName . 'ArgumentsObject';

        // generate required version of object instantiation code
        $instantiateObjectCode = (in_array($upperCamelName, $this->singleInstanceObject))
            ? "\$object = \$this->getSelectionObjectIfExists(new $objectClassName(\"$fieldName\"));"
            : "\$object = new $objectClassName(\"$fieldName\");";

        // generate required version of object selectField code
        $selectFieldCode = (in_array($upperCamelName, $this->singleInstanceObject))
            ? "if (!\$this->selectionObjectExists(\$object)) {
                    \$this->selectField(\$object);
                }"
            : "\$this->selectField(\$object);";

        // method code
        $method = "public function select$upperCamelName($argsMapClassName \$argsObject = null)
{
    " . $instantiateObjectCode . "
    if (\$argsObject !== null) {
        \$object->appendArguments(\$argsObject->toArray());
    }
    " . $selectFieldCode . "

    return \$object;
}";
        $this->classFile->addMethod($method);
    }

    /***
     * @param string $typeKind
     * @return string
     */
    protected function getObjectKind(string $typeKind)
    {
        switch($typeKind) { // may need to add other types
            case FieldTypeKindEnum::ENUM_OBJECT:
                return 'EnumObject';
            default: 
                return 'QueryObject';
        }
    }

    /**
     * This method builds the class and writes it to the file system
     */
    public function build(): void
    {
        $this->classFile->writeFile();
    }
}