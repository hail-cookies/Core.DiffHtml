<?php
namespace exface\Core\DataTypes;

use exface\Core\Interfaces\WorkbenchInterface;

/**
 * 
 * @author andrej.kabachnik
 *
 */
class DateTimeDataType extends DateDataType
{   
    const DATETIME_FORMAT_INTERNAL = 'Y-m-d H:i:s';
    
    const DATETIME_ICU_FORMAT_INTERNAL = 'yyyy-MM-dd HH:mm:ss';
    
    private $showSeconds = false;
    
    /**
     * 
     * @param \DateTime $date
     * @return string
     */
    public static function formatDateNormalized(\DateTime $date) : string
    {
        return $date->format(self::DATETIME_FORMAT_INTERNAL);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::getFormatToParseTo()
     */
    public function getFormatToParseTo() : string
    {
        return self::DATETIME_ICU_FORMAT_INTERNAL;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::getFormat()
     */
    public function getFormat() : string
    {
        return $this->hasCustomFormat() ? parent::getFormat() : static::getFormatForCurrentTranslation($this->getWorkbench(), $this->getShowSeconds());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\DataTypes\DateDataType::getInputFormatHint()
     */
    public function getInputFormatHint() : string
    {
        return $this->getApp()->getTranslator()->translate('LOCALIZATION.DATE.DATETIME_FORMAT_HINT');
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string
     */
    protected static function getFormatForCurrentTranslation(WorkbenchInterface $workbench, bool $withSeconds = false) : string
    {
        return $workbench->getCoreApp()->getTranslator()->translate($withSeconds ? 'LOCALIZATION.DATE.DATETIME_FORMAT_WITH_SECONDS' : 'LOCALIZATION.DATE.DATETIME_FORMAT');
    }
    
    /**
     *
     * @return bool
     */
    public function getShowSeconds() : bool
    {
        return $this->showSeconds;
    }
    
    /**
     * Set to TRUE to show the seconds (has no effect when custom `format` specified!).
     *
     * @uxon-property show_seconds
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param bool $value
     * @return TimeDataType
     */
    public function setShowSeconds(bool $value) : DateTimeDataType
    {
        $this->showSeconds = $value;
        return $this;
    }
}