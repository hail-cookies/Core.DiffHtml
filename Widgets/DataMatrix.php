<?php
namespace exface\Core\Widgets;

/**
 * A DataTable with certain columns being transposed.
 * 
 * Starting with a DataTable, you make it create additional columns with the values from the label_column as headers
 * and values taken from the data_column. The other columns will keep their values. Thus, the DataMatrix has less
 * rows than the underlying table, because some of the are summarized to a single row with more columns.
 * 
 * The following example will create a color/size matrix with product stock levels out of a table listing
 * the current stock level for each color-size-combination individually:
 * 		  {
 * 	        "widget_type": "DataMatrix",
 * 	        "object_alias": "PRODUCT_COLOR_SIZE",
 * 	        "hide_toolbars": true,
 * 	        "caption": "Stock matrix",
 * 	        "columns": [
 * 	          {
 * 	            "attribute_alias": "COLOR__LABEL"
 * 	          },
 * 	          {
 * 	            "attribute_alias": "SIZE",
 * 	            "id": "SIZE"
 * 	          },
 * 	          {
 * 	            "attribute_alias": "STOCKS__AVAILABLE:SUM",
 * 	            "id": "STOCK_AVAILABLE"
 * 	          }
 * 	        ],
 * 	        "label_column_id": "SIZE",
 * 	        "data_column_id": "STOCK_AVAILABLE",
 * 	        "sorters": [
 * 	          {
 * 	            "attribute_alias": "COLOR__LABEL",
 * 	            "direction": "ASC"
 * 	          },
 * 	          {
 * 	            "attribute_alias": "SIZING_LENGTH",
 * 	            "direction": "ASC"
 * 	          }
 * 	        ]
 * 	      }
 * 
 * @author Andrej Kabachnik
 *
 */
class DataMatrix extends DataTable {
	
	protected function init(){
		parent:: init();
		$this->set_paginate(false);
		$this->set_show_row_numbers(false);
		$this->set_multi_select(false);
		$this->set_lazy_loading(false);
	}
		  
}
?>