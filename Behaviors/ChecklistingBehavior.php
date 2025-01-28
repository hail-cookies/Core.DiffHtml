<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\DataSheets\DataCheckWithOutputData;
use exface\Core\CommonLogic\Debugger\LogBooks\BehaviorLogBook;
use exface\Core\CommonLogic\Model\Behaviors\AbstractValidatingBehavior;
use exface\Core\CommonLogic\Model\Behaviors\BehaviorDataCheckList;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Exceptions\DataSheets\DataCheckFailedErrorMultiple;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataCheckListInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;

/**
 * Applies a checklist to the input data and persists the results at a configurable data address.
 * 
 * This behavior is similar to the `ValidatingBehavior` except for the result of the checks: in contrast to the
 *  `ValidatingBehavior`, that produces errors if at least one condition was matched, the `ChecklistingBehavior` merely
 * saves its findings to the data source allowing you to process them later.
 * 
 * Depending on your configuration this behavior reacts to `OnCreateData`, `OnUpdateData` or both. This means
 * it always triggers after the respective transaction has been completed. This is to ensure that all data necessary
 * for generating the output data is available and up-to-date.
 * 
 * Once triggered, the behavior runs all its data checks for that event. Whenever a data-item matches any of
 * those data checks, that data check will output a checklist item. Checklist items can be warnings, hints, 
 * errors - anything, that is not critical, but important to see for the user.
 * 
 * Checklist items generated by this behavior will be saved to whatever table you specified in the configuration.
 * Ideally that table  resides in the same data source as the checked object. See below for more details on how to
 * create this table.
 * 
 * You can think of this behavior as "taking notes" about state of your data. In this metaphor the output table is your
 * notebook.  You are responsible for the quality of the information in it as well as its structure. As such you should
 * view your definition of the output sheet as the actual notes you will be taking. This becomes especially potent,
 * once you start using placeholders as values in your output sheet. You could for instance persist a complex relation
 * to make your future work easier (e.g. `"Product":"[#ORDER_POS__PRODUCT#]"`).
 * 
 * ## Setup
 * 
 * 1. Create a new table for the app this behavior belongs to. It will serve as persistent storage for the output
 * data this behavior generates. This table needs to fulfill the following conditions:
 * 
 *      - It must have a matching column for each column defined in the `rows` property of `output_data_sheet`
 * (matching name and datatype).
 *      - It must have the default system columns of your respective app (e.g. `id`, `modified_by`, etc.).
 *      - It must have a column that matches your `affected_uid_alias`, both in name and datatype. 
 *      - You can find an example definition in the section `Examples`.
 * 
 * 2. Create a MetaObject for this table that **inherits from its BaseObject**. 
 * 
 * 3. If your data source is derived from LogBase, you need to add a LogBase-Class to the Data Source Settings of the
 * newly created MetaObject, for example:
 * `{"LOGBASE_CLASS":"ScaLink.OneLink.LieferscheinPosStatus"}` 
 * 
 * 4. Then, attach a new `ChecklistingBehavior` to the MetaObject that you actually wish to modify (for example the
 * OrderPosition) and configure the behavior as needed.
 * 
 * 5. If properly configured, the behavior will now write its output to the table you have created whenever its
 * conditions are met. You can now read said data from the table to create useful effects, such as rendering
 * notifications.
 * 
 * ## Placeholders
 * 
 * This behavior supports basic data placeholders. Depending on the event context, it may even be able to access
 * pre-transaction:
 * 
 * - `[#~new:attribute_alias#]`: Access post-transaction data. This placeholder is available in all event contexts.
 * - `[#~old:attribute_alias#]`: Access pre-transaction data, i.e. the data before it was modified. This placeholder is
 * only available for `check_on_update`. If  you try to use it in `check_always` or check_on_create` the behavior will
 * throw an error.
 * 
 * ## Examples
 * 
 * ### Example SQL for an Output Table 
 * 
 * ```
 * 
 *  CREATE TABLE [dbo].[CHECKLIST] (
 *      [id] bigint NOT NULL,
 *      [ZeitNeu] datetime NOT NULL,
 *      [ZeitAend] datetime NOT NULL,
 *      [UserNeu] nvarchar(50) NOT NULL,
 *      [UserAend] nvarchar(50) NOT NULL,
 *      [Betreiber] nvarchar(8) NOT NULL,
 *      [CRITICALITY] int NOT NULL,
 *      [LABEL] nvarchar(50) NOT NULL,
 *      [MESSAGE] nvarchar(100) NOT NULL,
 *      [COLOR] nvarchar(20) NOT NULL,
 *      [ICON] nvarchar(100) NOT NULL,
 *      [AFFECTED_UID] int NOT NULL
 *  );
 * 
 * ```
 * 
 * ### Example UXON Definition with one DataCheck
 * 
 * ```
 *  {
 *      "check_on_update": [{
 *          "affected_uid_alias": "AFFECTED_UID"
 *          "output_data_sheet": {
 *              "object_alias": "my.APP.CHECKLIST",
 *              "rows": [{
 *                  "CRITICALITY": "0",
 *                  "LABEL": "Error",
 *                  "MESSAGE": "This order includes products, that are not available for ordering yet!",
 *                  "COLOR": "red",
 *                  "ICON":"sap-icon://message-warning"
 *              }]     
 *          },
 *          "operator": "AND",
 *          "conditions": [{
 *              "expression": "[#ORDER_POS__PRODUCT__LIFECYCLE_STATE:MIN#]",
 *              "comparator": "<",
 *              "value": "50"
 *          }]
 *       }]
 *  }
 * 
 * ```
 * 
 * @author Andrej Kabachnik, Georg Bieger
 * 
 */
class ChecklistingBehavior extends AbstractValidatingBehavior
{    
    /**
     * @see AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $evtManager = $this->getWorkbench()->eventManager();
        $evtManager->addListener(OnCreateDataEvent::getEventName(), $this->getEventHandlerToPerformChecks(), $this->getPriority());
        $evtManager->addListener(OnUpdateDataEvent::getEventName(), $this->getEventHandlerToPerformChecks(), $this->getPriority());
        $evtManager->addListener(OnBeforeUpdateDataEvent::getEventName(), $this->getEventHandlerToCacheOldData(), $this->getPriority());
        $evtManager->addListener(OnBeforeDeleteDataEvent::getEventName(), $this->getEventHandlerToClearData(), $this->getPriority());
        
        return $this;
    }

    /**
     * @see AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $evtManager = $this->getWorkbench()->eventManager();
        $evtManager->removeListener(OnCreateDataEvent::getEventName(), $this->getEventHandlerToPerformChecks());
        $evtManager->removeListener(OnUpdateDataEvent::getEventName(), $this->getEventHandlerToPerformChecks());
        $evtManager->removeListener(OnBeforeUpdateDataEvent::getEventName(), $this->getEventHandlerToCacheOldData());
        $evtManager->removeListener(OnBeforeDeleteDataEvent::getEventName(), $this->getEventHandlerToClearData());
        
        return $this;
    }

    protected function getEventHandlerToClearData() : callable
    {
        return [$this, 'onDeleteClearStaleData'];
    }
    
    protected function generateDataChecks(UxonObject $uxonObject): DataCheckListInterface
    {
        $dataCheckList = new BehaviorDataCheckList($this->getWorkbench(), $this);
        foreach ($uxonObject as $uxon) {
            $dataCheckList->add(new DataCheckWithOutputData($this->getWorkbench(), $uxon));
        }
        
        return $dataCheckList;
    }


    protected function processValidationResult(
        DataSheetEventInterface $event, 
        ?DataCheckFailedErrorMultiple $result, 
        BehaviorLogBook $logbook): void
    {
        $transaction = $this->clearPreviousChecklistItems(
            $event->getDataSheet(), 
            $this->getRelevantUxons($event, $logbook),
            $logbook);

        if(!$result) {
            $logbook->addLine('The data did not match any of the data checks.');
            return;
        }
        
        $outputSheets = [];
        foreach ($result->getAllErrors() as $error) {
            $check = $error->getCheck();
            if(!$check instanceof DataCheckWithOutputData) {
                continue;
            }

            if(!$checkOutputSheet = $check->getOutputDataSheet()) {
                continue;
            }
            
            $metaObjectAlias = $checkOutputSheet->getMetaObject()->getAlias();
            if(key_exists($metaObjectAlias,$outputSheets)) {
                $outputSheets[$metaObjectAlias]->addRows($checkOutputSheet->getRows());
            } else {
                // We need to maintain separate sheets for each MetaObjectAlias, in case the designer
                // configured data checks associated with different MetaObjects.
                $outputSheets[$metaObjectAlias] = $checkOutputSheet;
            }
        }
        
        $logbook->addLine('Processing output data sheets...');
        $logbook->addIndent(1);
        foreach ($outputSheets as $metaObjectAlias => $outputSheet) {
            if($outputSheet === null || $outputSheet->countRows() === 0) {
                continue;
            }

            $logbook->addDataSheet('Output-'.$metaObjectAlias, $outputSheet);
            $logbook->addLine('Working on sheet for '.$metaObjectAlias.'...');
            $logbook->addLine('Writing data to cache.');
            $count = $outputSheet->dataUpdate(true, $transaction);
            $logbook->addLine('Added '.$count.' lines to cache.');
        }
        $logbook->addIndent(-1);
    }

    /**
     * Clears all related checklist entries, whenever one or more rows of the associated MetaObkect are deleted.
     * 
     * @param DataSheetEventInterface $event
     * @return void
     */
    public function onDeleteClearStaleData(DataSheetEventInterface $event) : void
    {
        $eventSheet = $event->getDataSheet();
        if(!$this->isRelevantData($eventSheet)) {
            return;
        }

        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));

        $this->inProgress = true;
        $logbook = new BehaviorLogBook($this->getAlias(), $this, $event);
        $logbook->addDataSheet('Data', $eventSheet);
        $logbook->addLine('Clearing checklist data for ' . $eventSheet->countRows() . ' rows of ' . $eventSheet->getMetaObject()->__toString());
        $this->clearPreviousChecklistItems($eventSheet, $this->uxonsPerEventContext, $logbook);
    }

    /**
     * Clears the previous data items from the checklist, for a given set of data check UXONs and UIDs.
     * 
     * This function deletes all checklist items, that match both the data checks in the given UXONs and
     * any of the UIDs in the event sheet. This essentially clears the way for overwriting or updating the checklist
     * for these UIDs.
     * 
     * @param DataSheetInterface $eventSheet
     * @param array              $uxons
     * @param BehaviorLogBook    $logBook
     * @return DataTransactionInterface
     */
    protected function clearPreviousChecklistItems(DataSheetInterface $eventSheet, array $uxons, BehaviorLogBook $logBook) : DataTransactionInterface
    {
        $affectedUidAliases = [];
        foreach ($uxons as $uxon) {
            if(empty($uxon) || !$uxon instanceof UxonObject) {
                continue;
            }

            foreach ($uxon as $dataCheckUxon) {
                $dataCheck = new DataCheckWithOutputData($this->getWorkbench(), $dataCheckUxon);
                $uidAlias = $dataCheck->getAffectedUidAlias();
                $objectAlias = $dataCheck->getOutputDataSheetUxon()->getProperty('object_alias');

                if(!empty($uidAlias) && !empty($objectAlias)) {
                    $affectedUidAliases[$objectAlias] = $uidAlias;
                }
            }
        }
        
        $transaction = $eventSheet->getWorkbench()->data()->startTransaction();
        $uids = $eventSheet->getColumnValues($eventSheet->getUidColumnName());
        
        foreach ($affectedUidAliases as $objectAlias => $uidAlias) {
            $deleteSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $objectAlias);
            foreach ($uids as $uid) {
                $deleteSheet->addRow([$uidAlias => $uid]);
            }

            $logBook->addLine('Affected UID-Alias is '.$uidAlias.'.');
            // We filter by affected UID rather than by native UID to ensure that our delete operation finds all cached outputs,
            // especially if they were part of the source transaction.
            $deleteSheet->getFilters()->addConditionFromValueArray($uidAlias, $deleteSheet->getColumnValues($uidAlias));
            // We want to delete ALL entries for any given affected UID to ensure that the cache only contains outputs
            // that actually matched the current round of validations. This way we essentially clean up stale data.
            // Remove the UID column, because otherwise dataDelete() ignores filters and goes by UID.
            $deleteSheet->getColumns()->remove($deleteSheet->getUidColumn());
            $logBook->addLine('Deleting data with affected UIDs from cache.');
            $count = $deleteSheet->dataDelete($transaction);
            $logBook->addLine('Deleted '.$count.' lines from checklist.');
        }
        
        return $transaction;
    }

    /**
     * Triggers only when data is being CREATED.
     * 
     *  ### Placeholders:
     * 
     *  - `[#~new:alias#]`: Loads the value the specified alias will hold AFTER the event has been applied.
     * 
     * @uxon-property check_on_create
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheckWithOutputData[]
     * @uxon-template [{"affected_uid_alias":"AFFECTED_UID", "output_data_sheet":{"object_alias": "", "rows": [{"CRITICALITY":"0", "LABELS":"", "MESSAGE":"", "COLOR":"", "ICON":"sap-icon://message-warning"}]}, "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setCheckOnCreate(UxonObject $uxon) : AbstractValidatingBehavior
    {
        $this->setUxonForEventContext($uxon,self::CONTEXT_ON_CREATE);
        return $this;
    }

    /**
     * Triggers only when data is being UPDATED.
     * 
     * ### Placeholders:
     * 
     *  - `[#~old:alias#]`: Loads the value the specified alias held BEFORE the event was applied.
     *  - `[#~new:alias#]`: Loads the value the specified alias will hold AFTER the event has been applied.
     * 
     * @uxon-property check_on_update
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheckWithOutputData[]
     * @uxon-template [{"affected_uid_alias":"AFFECTED_UID", "output_data_sheet":{"object_alias": "", "rows": [{"CRITICALITY":"0", "LABELS":"", "MESSAGE":"", "COLOR":"", "ICON":"sap-icon://message-warning"}]}, "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * 
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setCheckOnUpdate(UxonObject $uxon) : AbstractValidatingBehavior
    {
        $this->setUxonForEventContext($uxon,self::CONTEXT_ON_UPDATE);
        return $this;
    }

    /**
     * Triggers BOTH when data is being CREATED and UPDATED.
     * 
     * ### Placeholders:
     * 
     * - `[#~new:alias#]`: Loads the value the specified alias will hold AFTER the event has been applied.
     * 
     * @uxon-property check_always
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheckWithOutputData[]
     * @uxon-template [{"affected_uid_alias":"AFFECTED_UID", "output_data_sheet":{"object_alias": "", "rows": [{"CRITICALITY":"0", "LABELS":"", "MESSAGE":"", "COLOR":"", "ICON":"sap-icon://message-warning"}]}, "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     * 
     * @param UxonObject $uxon
     * @return AbstractValidatingBehavior
     */
    public function setCheckAlways(UxonObject $uxon) : AbstractValidatingBehavior
    {
        $this->setUxonForEventContext($uxon,self::CONTEXT_ON_ANY);
        return $this;
    }
}