<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Interfaces\Widgets\iAmCollapsible;
use exface\Core\Interfaces\Widgets\iShowMessageList;
use exface\Core\Widgets\Traits\iAmCollapsibleTrait;
use exface\Core\Widgets\Traits\iShowMessageListTrait;

/**
 * The configurator is a special widget, that controls the behavior of another widget.
 * 
 * For example, a DataTable will have a configurator, that contains filters,
 * sorters, perhaps some controls to change the aggregations, a sorting widget
 * to move columns and turn them on and off, etc. A chart configurator will 
 * contain filters and sorters too, but instead of colums it will have a tab
 * with chart types, color themes, etc.
 * 
 * Configurators are organized in tabs. Each type of configurator will have 
 * a different set of tabs. However, since configurators are separate widgets,
 * facades may render them as something different than the regular tabs widget:
 * perhaps, an accordion. Facades can also rearrange tab contents: e.g. a
 * desktop facade with lot's of space could render a DataConfigurator with 
 * only two tabs - one for filters and one for everything else. Another option
 * could be a panel with promoted filters and sorters and a dialog with the
 * rest of the configurator rendered as regular tabs. There are lots of
 * possibilities!
 * 
 * Configurators are collapsible. Since the mostly take up significant space,
 * you may want to force a configurator to collapse (e.g. in a detail-table),
 * to free up some space for the main widget.
 * 
 * This is a basic - empty - configurator. It is the base for real configurators
 * like:
 * 
 * @see DataConfigurator
 * @see DataTableConfigurator
 * @see ChartConfigurator
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetConfigurator extends Tabs implements iConfigureWidgets, iAmCollapsible, iShowMessageList
{
    use iAmCollapsibleTrait;

    use iShowMessageListTrait;
    
    private $widget = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iConfigureWidgets::getWidget()
     */
    public function getWidgetConfigured()
    {
        if ($this->widget === null){
            // TODO search recursively for a parent with iHaveConfigurator
            return $this->getParent();
        } 
        
        return $this->widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iConfigureWidgets::setWidget()
     */
    public function setWidgetConfigured(iHaveConfigurator $widget)
    {
        $this->widget = $widget;
        return $this;
    }
}
?>