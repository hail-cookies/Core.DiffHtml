<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\DataTypes\RelationTypeDataType;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Model\ModelInterface;
use exface\Core\Exceptions\Model\MetaRelationAliasAmbiguousError;
use exface\Core\DataTypes\RelationCardinalityDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Model\MetaObjectNotFoundError;
use exface\Core\Exceptions\Model\MetaRelationBrokenError;

class Relation implements MetaRelationInterface
{

    // Properties to be dublicated on copy()
    private $id;

    private $alias;
    
    private $aliasModifier = '';

    private $name;
    
    private $leftObject = null;
    
    private $leftKeyAttribute = null;
    
    private $rightObjectUid = null;
    
    private $rightObject = null;
    
    private $rightKeyAttributeUid = null;
    
    private $rightKeyAttribute = null;

    private $type = null;
    
    private $cardinalty = null;

    private $inherited_from_object_id = null;
    
    private $leftObjectToBeDeletedWithRightObject = false;
    
    private $leftObjectToBeCopiedWithRightObject = false;
    
    private $rightObjectToBeDeletedWithLeftObject = false;
    
    private $rightObjectToBeCopiedWithLeftObject = false;

    // Properties NOT to be dublicated on copy()
    private $exface = null;

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::__construct()
     */
    public function __construct(
        Workbench $workbench,
        RelationCardinalityDataType $cardinality,
        string $uid,
        string $alias,
        string $aliasModifier,
        MetaObjectInterface $leftObject,
        MetaAttributeInterface $leftKeyAttribute,
        string $rightObjectUid,
        string $rightObjectKeyAttributeUid = null)
    {
        $this->exface = $workbench;
        $this->id = $uid;
        $this->alias = $alias;
        $this->aliasModifier = $aliasModifier;
        $this->leftObject = $leftObject;
        $this->leftKeyAttribute = $leftKeyAttribute;
        $this->rightObjectUid = $rightObjectUid;
        $this->rightKeyAttributeUid = $rightObjectKeyAttributeUid;
        $this->cardinalty = $cardinality;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getId()
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getAlias()
     */
    public function getAlias() : string
    {
        return $this->alias;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getAliasModifier()
     */
    public function getAliasModifier() : string
    {
        return $this->aliasModifier;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getAliasWithModifier()
     */
    public function getAliasWithModifier() : string
    {
        return $this->getAlias() . ($this->getAliasModifier() && $this->requiresModifier() ? '[' . $this->getAliasModifier() . ']' : '');
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getName()
     */
    public function getName() : string
    {
        if ($this->name === null) {
            if ($this->isReverseRelation()) {
                $this->name = $this->getRightObject()->getName();
            } else {
                $this->name = $this->getLeftKeyAttribute()->getName();
            }
        }
        return $this->name;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getLeftObject()
     */
    public function getLeftObject() : MetaObjectInterface
    {
        return $this->leftObject;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getLeftKeyAttribute()
     */
    public function getLeftKeyAttribute() : MetaAttributeInterface
    {
        return $this->leftKeyAttribute;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRightObject()
     */
    public function getRightObject() : MetaObjectInterface
    {
        if ($this->rightObject === null) {
            try {
                $this->rightObject = $this->getModel()->getObjectById($this->rightObjectUid);
            } catch (MetaObjectNotFoundError $e) {
                throw new MetaRelationBrokenError($this->getLeftObject(), 'Relation "' . $this->getAlias() . '" of object "' . $this->getLeftObject()->getAliasWithNamespace() . '" broken: right object not found! ' . $e->getMessage(), '7D97VLN', $e);
            }
        }
        return $this->rightObject;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRightObjectId()
     */
    public function getRightObjectId() : string
    {
        return $this->rightObjectUid;
    }
    
    /**
     * Returns TRUE if the right key of this relation is not specified explictily (thus defaults to the UID)
     * 
     * @return bool
     */
    protected function getRightKeyIsUnspecified() : bool
    {
        return $this->rightKeyAttributeUid === null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRightKeyAttribute()
     */
    public function getRightKeyAttribute(bool $appendRelationPath = false) : MetaAttributeInterface
    {
        if ($this->rightKeyAttribute === null) {
            if ($this->rightKeyAttributeUid !== null) {
                $attr = $this->getRightObject()->getAttributes()->getByAttributeId($this->rightKeyAttributeUid);
            } else {
                $attr = $this->getRightObject()->getUidAttribute();
            }
            $this->rightKeyAttribute = $appendRelationPath !== true ? $attr : $this->getRightAttribute($attr->getAlias());
        }
        // FIXME $appendRelationPath should also have effect if $this->rightKeyAttribute is set. However
        // we should check the code, that uses getRightKeyAttribute(true) first!
        return $this->rightKeyAttribute;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getRightAttribute()
     */
    public function getRightAttribute(string $aliasRelativeToRightObject) : MetaAttributeInterface
    {
        return $this->getLeftObject()->getAttribute(RelationPath::relationPathAdd($this->getAlias(), $aliasRelativeToRightObject));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getType()
     */
    public function getType() : RelationTypeDataType
    {
        if ($this->type === null) {
            switch ($this->getCardinality()->__toString()) {
                case RelationCardinalityDataType::N_TO_ONE:
                case RelationCardinalityDataType::ONE_TO_ONE:
                    $this->type = RelationTypeDataType::REGULAR($this->getWorkbench());
                    break;
                default:
                    $this->type = RelationTypeDataType::REVERSE($this->getWorkbench());
            }
        }
        return $this->type;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getCardinality()
     */
    public function getCardinality() : RelationCardinalityDataType
    {
        return $this->cardinalty;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getInheritedFromObjectId()
     */
    public function getInheritedFromObjectId() : ?string
    {
        return $this->inherited_from_object_id;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setInheritedFromObjectId()
     */
    public function setInheritedFromObjectId($value) : MetaRelationInterface
    {
        $this->inherited_from_object_id = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isInherited()
     */
    public function isInherited() : bool
    {
        return $this->inherited_from_object_id !== null;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getReversedRelation()
     */
    public function getReversedRelation() : MetaRelationInterface
    {
        if ($this->isForwardRelation()) {
            // If it is a regular relation, it will be a reverse one from the point of view 
            // of the related object. That is identified by the alias of the object it leads 
            // to (in our case, the current object)
            $thisModifier = $this->getAliasModifier();
            if ($this->getCardinality()->__toString() === RelationCardinalityDataType::ONE_TO_ONE
            && $this->getRightKeyAttribute()->isRelation() 
            // IDEA Will this ever happen?? Isn't $thisModifier always empty for forward
            // relations? Not sure, what exactly this branch was for...
            && $this->getRightKeyAttribute()->getRelation()->getAlias() === $thisModifier
            ) {
                $reverse = $this->getRightKeyAttribute()->getRelation();
            } else {
                // It is a bit strange, that the current modifier is passed here to
                // to the right object when getting the reverse relation. It seems
                // more corret to pass the alias of this relation as modifier, which
                // is done in case the modifier is empty. However, we did not dare
                // to change this code entirely - perhaps, tha current modifier is
                // important for some 1-to-1 relations or similar.
                $reverse = $this->getRightObject()->getRelation($this->getLeftObject()->getAlias(), $thisModifier !== '' ? $thisModifier : $this->getAlias());
            }
        } elseif ($this->isReverseRelation()) {
            // If it is a reverse relation, it will be a regular one from the point of view of the related object. 
            // That is identified by its alias.
            $reverse = $this->getRightKeyAttribute()->getRelation();
        } else {
            throw new RuntimeException('Cannot reverse relation "' . $this->toString() . '" of meta object "' . $this->getLeftObject()->getAliasWithNamespace() . '": invalid relation type!');
        }
        return $reverse;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::copy()
     */
    public function copy(MetaObjectInterface $toObject = null) : self
    {
        return clone $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::withExtendedObject()
     */
    public function withExtendedObject(MetaObjectInterface $newObject) : MetaRelationInterface
    {
        $thisObj = $this->getLeftObject();
        if (! $newObject->is($thisObj)) {
            throw new UnexpectedValueException('Cannot use Relation::withExtendedObject() on object ' . $newObject->__toString() . ' because it does not extend ' . $thisObj->__toString());
        }
        // This means: $extendedObj->getRelation(x)->getLeftObject() = $parentObj. There are cases
        // when this leads to inconsistencies. If we have a REPORT with multiple REVISIONs and
        // a REPORT_2, which extends REPORT, than REVISION__ID:COUNT works for REPORT and for REPORT_2. 
        // But if REVISION has a separate relation to REPORT_2, than REPORT_2 has two reverse relations
        // from REVISION and REVISION__ID:COUNT becomes umbiguous! It now must be REVISION[REPORT]__ID
        // or REVISION[REPORT_2]__ID, but InheritedRelation::needsModifier() does not know, that it was
        // inherited into an object, that adds conflicts. In fact, it does not know its new object at
        // all! It only knows, that it was inherited, but not where to.
        $clone = new Relation(
            $this->getWorkbench(),
            $this->getCardinality(),
            $this->getId(),
            $this->getAlias(), // IDEA should not the new relation have the alias of the new object?
            $this->getAliasModifier(),
            $newObject,
            $newObject->getAttribute($this->getLeftKeyAttribute()->getAlias()),
            // Self-relations (pointing from the parent to the parent) need to point from the extending object 
            // to the extending object.
            // For example, if we are extending the FILE object, the relation to the folder should not point
            // to the original file object, but rather to the extending object, which may have a custom base
            // address, etc.
            ($thisObj->getId() === $this->getRightObjectId() ? $newObject->getId() : $this->getRightObjectId()),
            $this->getRightKeyIsUnspecified() === true ?  null : $newObject->getAttribute($this->getRightKeyAttribute()->getAlias())->getId()
        );
        return $clone;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getModel()
     */
    public function getModel() : ModelInterface
    {
        return $this->getWorkbench()->model();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isReverseRelation()
     */
    public function isReverseRelation() : bool
    {
        $card = $this->getCardinality()->__toString();
        return $card === RelationCardinalityDataType::ONE_TO_N || $card === RelationCardinalityDataType::N_TO_M;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isForwardRelation()
     */
    public function isForwardRelation() : bool
    {
        return $this->isReverseRelation() === false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::is()
     */
    public function is(MetaRelationInterface $other_relation) : bool
    {
        return $this->getId() === $other_relation->getId() && $this->getCardinality()->isEqual($other_relation->getCardinality());
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isExactly()
     */
    public function isExactly(MetaRelationInterface $other_relation) : bool
    {
        if ($this->is($other_relation) && $this->getLeftObject()->isExactly($other_relation->getLeftObject())) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::toString()
     */
    public function toString() : string
    {
        return $this->getLeftObject()->getAliasWithNamespace() . '[' . $this->getLeftKeyAttribute()->getAlias() . '] -> ' . $this->getRightObject()->getAliasWithNamespace() . '[' . $this->getRightKeyAttribute()->getAlias() . ']';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::reverse()
     */
    public function reverse() : MetaRelationInterface
    {
        return $this->getReversedRelation();
    }
    
    /**
     * 
     * @return bool
     */
    protected function requiresModifier() : bool
    {
        if ($this->isForwardRelation() === true) {
            return false;
        }
        
        try {
            if ($this->getLeftObject()->getRelation($this->getAlias())->isExactly($this) === false) {
                return true;
            }
        } catch (MetaRelationAliasAmbiguousError $e) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isLeftObjectToBeDeletedWithRightObject()
     */
    public function isLeftObjectToBeDeletedWithRightObject() : bool
    {
        return $this->leftObjectToBeDeletedWithRightObject;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setLeftObjectToBeDeletedWithRightObject()
     */
    public function setLeftObjectToBeDeletedWithRightObject(bool $value) : MetaRelationInterface
    {
        $this->leftObjectToBeDeletedWithRightObject = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isLeftObjectToBeCopiedWithRightObject() : bool
    {
        return $this->leftObjectToBeCopiedWithRightObject;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isLeftObjectToBeCopiedWithRightObject()
     */
    public function setLeftObjectToBeCopiedWithRightObject(bool $value) : MetaRelationInterface
    {
        $this->leftObjectToBeCopiedWithRightObject = $value;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isRightObjectToBeDeletedWithLeftObject()
     */
    public function isRightObjectToBeDeletedWithLeftObject() : bool
    {
        return $this->rightObjectToBeDeletedWithLeftObject;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::setRightObjectToBeDeletedWithLeftObject()
     */
    public function setRightObjectToBeDeletedWithLeftObject(bool $value) : MetaRelationInterface
    {
        $this->rightObjectToBeDeletedWithLeftObject = $value;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isRightObjectToBeCopiedWithLeftObject() : bool
    {
        return $this->rightObjectToBeCopiedWithLeftObject;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::isRightObjectToBeCopiedWithLeftObject()
     */
    public function setRightObjectToBeCopiedWithLeftObject(bool $value) : MetaRelationInterface
    {
        $this->rightObjectToBeCopiedWithLeftObject = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationInterface::getDefaultEditorUxon()
     */
    public function getDefaultEditorUxon() : UxonObject
    {
        $relationWidgetType = $this->getWorkbench()->getConfig()->getOption('FACADES.DEFAULT_WIDGET_FOR_RELATIONS');
        
        $uxon = new UxonObject([
            "widget_type" => $relationWidgetType
        ]);
        
        if ($relationWidgetType === 'InputComboTable' && $this->getRightKeyAttribute()->isUidForObject() === false) {
            $uxon->setProperty("value_attribute_alias", $this->getRightKeyAttribute()->getAliasWithRelationPath());
        }
        
        return $uxon;
    }
}
?>