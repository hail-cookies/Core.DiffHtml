<?php
namespace exface\Core\Factories;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\AppInterface;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Selectors\FileSelectorInterface;
use exface\Core\Interfaces\Selectors\ClassSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Instantiates apps.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AppFactory extends AbstractSelectableComponentFactory
{

    /**
     * Creates a new app from the given name resolver
     *
     * @param AppSelectorInterface $selector            
     * @return AppInterface
     */
    public static function create(AppSelectorInterface $selector) : AppInterface
    {
        if ($selector->isUid()) {
            return static::createFromUid($selector->toString(), $selector->getWorkbench());
        }
        
        $class = static::getClassname($selector);
        if (! class_exists($class)) {
            $class = $selector->getClassnameOfDefaultPrototype();
        }
        $app = new $class($selector);
        return $app;
    }

    /**
     * Creates a new app from the given selector.
     * 
     * @param AppSelectorInterface|string $anything
     * @param WorkbenchInterface $workbench
     * @return AppInterface
     */
    public static function createFromAnything($anything, WorkbenchInterface $workbench) : AppInterface
    {
        if ($anything instanceof AppSelectorInterface) {
            return static::create($anything);
        } else {
            return static::create(new AppSelector($workbench, $anything));
        }
    }

    /**
     * Creates a new app from the given alias.
     * 
     * @param string $alias_with_namespace            
     * @param WorkbenchInterface $workbench            
     * @return AppInterface
     */
    public static function createFromAlias($alias_with_namespace, WorkbenchInterface $workbench) : AppInterface
    {
        $selector = new AppSelector($workbench, $alias_with_namespace);
        return static::create($selector);
    }

    /**
     * Creates a new app from the given UID.
     * 
     * @param string $uid
     * @param WorkbenchInterface $exface
     * @return AppInterface
     */
    public static function createFromUid($uid, WorkbenchInterface $exface) : AppInterface
    {
        return $exface->model()->getModelLoader()->loadApp(new AppSelector($exface, $uid));
    }
    
    /**
     * 
     * @param AppSelectorInterface $selector
     * @return string
     */
    protected static function getClassname(AppSelectorInterface $selector) : string
    {
        $string = $selector->toString();
        switch (true) {
            case $selector->isClassname():
                return $string;
            case $selector->isFilepath():
                $string = Filemanager::pathNormalize($string, FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR);
                $vendorFolder = Filemanager::pathNormalize($selector->getWorkbench()->filemanager()->getPathToVendorFolder());
                if (StringDataType::startsWith($string, $vendorFolder)) {
                    $string = substr($string, strlen($vendorFolder));
                }
                $ext = '.' . FileSelectorInterface::PHP_FILE_EXTENSION;
                $string = substr($string, 0, (-1*strlen($ext)));
                $string = str_replace(FileSelectorInterface::NORMALIZED_DIRECTORY_SEPARATOR, ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, $string);
                return $string;
            case $selector->isAlias():
                $vendorAlias = $selector->getVendorAlias();
                $appAlias = substr($string, (strlen($vendorAlias)+1));
                return str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR, $selector->getAppAlias()) . ClassSelectorInterface::CLASS_NAMESPACE_SEPARATOR . $appAlias . 'App';
        }
        throw new InvalidArgumentException('Cannot determine class from selector "' . $selector->toString() . '"!');
    }
}