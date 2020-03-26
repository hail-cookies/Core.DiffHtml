<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\DataSheetCreateDuplicatesForbiddenError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;

/**
 
 */
class PreventDuplicatesBehavior extends AbstractBehavior
{
    private $ignoreDuplicatesInMultiRowCreate = true;
    
    private $ignoreDuplicatesInSingleRowCreate = false;
    
    private $compareAttributes = [];
    
    private $errorCode = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::register()
     */
    public function register() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'handleOnBeforeCreate']);
        
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'handleOnBeforeUpdate']);
        
        $this->setRegistered(true);
        return $this;
    }
    
    /**
     * 
     * @param OnBeforeCreateDataEvent $event
     * @throws DataSheetCreateDuplicatesForbiddenError
     */
    public function handleOnBeforeCreate(OnBeforeCreateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return ;
        }
        
        $eventSheet = $event->getDataSheet();
        $object = $eventSheet->getMetaObject();        
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not extended from it.
        if (! $object->isExactly($this->getObject())) {
            return;
        }
        
        $duplicatesIdx = $this->getDuplicatesIndices($eventSheet);
        $eventRows = $eventSheet->getRows();
        if (count($eventRows) <= 1 && $this->getIgnoreDuplicatesInSingleRowCreate() === true && !empty($duplicatesIdx)) {
            $eventSheet->removeRow(1);
            #TODO this means the data sheet will be empty, so will probably throw an error. Should this be prevented and how
            return;
        } else if (count($eventRows) > 1 && $this->getIgnoreDuplicatesInMultiRowCreate() === true) {
            foreach (array_reverse($duplicatesIdx) as $index) {
                // have to reverse array with indices because rows in data sheet get reindexed when one is removed
                $eventSheet->removeRow($index);
            }
            return;
        }
        $errorMessage = $this->buildDuplicatesErrorMessage($eventSheet, $duplicatesIdx);
        throw new DataSheetCreateDuplicatesForbiddenError($eventSheet, $errorMessage, $this->getDuplicateErrorCode());
    }
    
    /**
     * 
     * @param OnBeforeUpdateDataEvent $event
     * @throws DataSheetCreateDuplicatesForbiddenError
     */
    public function handleOnBeforeUpdate(OnBeforeUpdateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return ;
        }
        
        $eventSheet = $event->getDataSheet();
        $object = $eventSheet->getMetaObject();
        // Do not do anything, if the base object of the data sheet is not the object with the behavior and is not extended from it.
        if (! $object->isExactly($this->getObject())) {
            return;
        }
        
        $duplicatesIdx = $this->getDuplicatesIndices($eventSheet);
        if (empty($duplicatesIdx)) {
            return;
        }
        $errorMessage = $this->buildDuplicatesErrorMessage($eventSheet, $duplicatesIdx);
        throw new DataSheetCreateDuplicatesForbiddenError($eventSheet, $errorMessage, $this->getDuplicateErrorCode());
    }
    
    /**
     * Returns array with indices of rows in given data sheet that are duplicates.
     * 
     * @param DataSheetInterface $eventSheet
     * @return array
     */
    protected function getDuplicatesIndices(DataSheetInterface $eventSheet) : array
    {   
        // check if aliases given in `compare_attributes` actually exist in data sheet, if not dont use it for comparison
        $columns = $eventSheet->getColumns();
        $sheetColumnAliases = [];
        foreach ($columns as $col) {
            $sheetColumnAliases[] = $col->getAttributeAlias();
        }
        $compareAttributes = [];
        foreach ($this->getCompareAttributes() as $attrAlias) {
            if (in_array($attrAlias, $sheetColumnAliases)) {
                $compareAttributes[] = $attrAlias;
            }
        }
        
        $eventRows = $eventSheet->getRows();
        // to get possible duplicates transform every row in event data sheet into a filter for the check sheet
        $checkSheet = $eventSheet->copy();
        $checkSheet->removeRows();
        $orFilterGroup = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_OR, $checkSheet->getMetaObject());
        foreach ($eventRows as $row) {
            $rowFilterGrp = ConditionGroupFactory::createEmpty($this->getWorkbench(), EXF_LOGICAL_AND, $checkSheet->getMetaObject());
            foreach ($compareAttributes as $attrAlias) {
                $dataType = $columns->getByExpression($attrAlias)->getDataType();
                if ($dataType->isValueEmpty($row[$attrAlias]) || $dataType->isValueLogicalNull($row[$attrAlias])) {
                    $rowFilterGrp->addConditionFromString($attrAlias, $row[$attrAlias], ComparatorDataType::EQUALS);
                }
            }
            $orFilterGroup->addNestedGroup($rowFilterGrp);
        }
        $checkSheet->getFilters()->addNestedGroup($orFilterGroup);
        // get data with the applied filters
        $checkSheet->dataRead();
        
        if (empty($checkSheet->getRows())) {
            return [];
        }
        $checkRows = $checkSheet->getRows();
        $duplicatesIdx = [];
        for ($i = 0; $i < count($eventRows); $i++) {
            foreach ($checkRows as $chRow) {
                $duplicate = true;
                foreach ($this->getCompareAttributes() as $attrAlias) {
                    $dataType = $columns->getByExpression($attrAlias)->getDataType();
                    if ($dataType->parse($eventRows[$i][$attrAlias]) != $dataType->parse($chRow[$attrAlias])) {
                        $duplicate = false;
                        break;
                    }
                }
                if ($duplicate === true) {
                    $duplicatesIdx[] = $i;
                    break;
                }
            }
        }
        
        return $duplicatesIdx;
    }
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @param array $duplicatesIdx
     * @return string
     */
    protected function buildDuplicatesErrorMessage(DataSheetInterface $dataSheet, array $duplicatesIdx) : string
    {
        $object = $dataSheet->getMetaObject();
        $labelAttributeAlias = $object->getLabelAttributeAlias();
        $rows = $dataSheet->getRows();
        $errorRowDescriptor = '';
        $errorMessage = '';
        foreach ($duplicatesIdx as $index) {
            $row = $rows[$index];
            if ($labelAttributeAlias !== null && $row[$labelAttributeAlias] !== null){
                $errorRowDescriptor .= "'{$row[$labelAttributeAlias]}', ";
            } else {
                $errorRowDescriptor .= strval($index + 1) . ", ";
            }
        }
        $errorRowDescriptor = substr($errorRowDescriptor, 0, -2);
        try {
            $errorMessage = $this->translate('BEHAVIOR.PREVENTDUPLICATEBEHAVIOR.CREATE_DUPLICATES_FORBIDDEN_ERROR', ['%row%' => $errorRowDescriptor, '%object%' => $object->getAlias()]);
        } catch (\Exception $e) {
            #TODO log the error or such?
            $errorMessage = 'Can not update/create data, as itcontains duplicates of alreadyy existing data!';
        }
        return $errorMessage;
    }
    
    /**
     * The attributes determining if a dataset is a duplicate.
     *
     * @uxon-property compare_attributes
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param string[]|UxonObject $arrayOrUxon
     * @return PreventDuplicatesBehavior
     */
    public function setCompareAttributes($arrayOrUxon) : PreventDuplicatesBehavior
    {
        if ($arrayOrUxon instanceof UxonObject) {
            $this->compareAttributes = $arrayOrUxon->toArray();
        } elseif (is_array($arrayOrUxon)) {
            $this->compareAttributes = $arrayOrUxon;
        }
        return $this;
    }
    
    /**
     *
     * @throws BehaviorConfigurationError
     * @return array
     */
    protected function getCompareAttributes() : array
    {
        if (empty($this->compareAttributes)) {
            throw new BehaviorConfigurationError($this->getObject(), "No attributes were set in '{$this->getAlias()}' of the object '{$this->getObject()->getAlias()}' to determine if a dataset is a duplicate or not! Set atleast one attribute via the 'compare_attributes' uxon property!");
        }
        return $this->compareAttributes;
    }
    
    /**
     * @uxon-property ignore_duplicates_in_multi_row_create
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return PreventDuplicatesBehavior
     */
    public function setIgnoreDuplicatesInMultiRowCreate (bool $trueOrFalse) : PreventDuplicatesBehavior
    {
        $this->ignoreDuplicatesInMultiRowCreate = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getIgnoreDuplicatesInMultiRowCreate() : bool
    {
        return $this->ignoreDuplicatesInMultiRowCreate;
    }
    
    /**
     * @uxon-property ignore_duplicates_in_single_row_create
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return PreventDuplicatesBehavior
     */
    public function setIgnoreDuplicatesInSingleRowCreate(bool $trueOrFalse) : PreventDuplicatesBehavior
    {
        $this->ignoreDuplicatesInSingleRowCreate = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getIgnoreDuplicatesInSingleRowCreate() : bool
    {
        return $this->ignoreDuplicatesInSingleRowCreate;
    }
    
    /**
     * @uxon-property duplicate_error_code
     * @uxon-type string
     * 
     * @param string $code
     * @return PreventDuplicatesBehavior
     */
    public function setDuplicateErrorCode (string $code) : PreventDuplicatesBehavior
    {
        $this->errorCode = $code;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    protected function getDuplicateErrorCode() : ?string
    {
        return $this->errorCode;
    }
    
    /**
     * 
     * @param string $messageId
     * @param array $placeholderValues
     * @param float $pluralNumber
     * @return string
     */
    protected function translate(string $messageId, array $placeholderValues = null, float $pluralNumber = null) : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate($messageId, $placeholderValues, $pluralNumber);
    }
}