<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\Widgets\iShowText;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\DataSheets\DataColumn;

/**
 * The text widget simply shows text with an optional title created from the caption of the widget
 * @author Andrej Kabachnik
 *
 */
class Text extends AbstractWidget implements iShowSingleAttribute, iHaveValue, iShowText {
	private $text = NULL;
	private $attribute_alias = null;
	private $align = null;
	private $data_type = null;
	private $size = null;
	private $style = null;
	private $aggregate_function = null;
	private $ignore_default_value = null;
	
	public function get_text() {
		if (is_null($this->text)){
			return $this->get_value();
		}
		return $this->text;
	}
	
	public function set_text($value) {
		$this->text = $value;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iShowSingleAttribute::get_attribute_alias()
	 */
	public function get_attribute_alias() {
		return $this->attribute_alias;
	}
	
	public function set_attribute_alias($value) {
		$this->attribute_alias = $value;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_read()
	 */
	public function prepare_data_sheet_to_read(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_read($data_sheet);
		
		// FIXME how to prefill values, that were defined by a widget link???
		/*if ($this->get_value_expression() && $this->get_value_expression()->is_reference()){
			$ref_widget = $this->get_value_expression()->get_widget_link()->get_widget();
			if ($ref_widget instanceof ComboTable){
				$data_column = $ref_widget->get_table()->get_column($this->get_value_expression()->get_widget_link()->get_column_id());
				var_dump($data_column->get_attribute_alias());
			}
		} else*/
		if ($this->get_meta_object()->is($data_sheet->get_meta_object())){
			// If we are looking for attributes of the object of this widget, then just return the attribute_alias
			$data_sheet->get_columns()->add_from_expression($this->get_attribute_alias());
		} else {
			// If not...
			if ($this->get_attribute() && $rel_path = $this->get_attribute()->get_relation_path()->to_string()){
				$rel_parts = RelationPath::relation_path_parse($rel_path);
				if (is_array($rel_parts)){
					$related_obj = $this->get_meta_object();
					foreach ($rel_parts as $rel_nr => $rel_part){
						$related_obj = $related_obj->get_related_object($rel_part);
						unset($rel_parts[$rel_nr]);
						if ($related_obj->is_exactly($data_sheet->get_meta_object())){
							$attr_path = implode(RelationPath::RELATION_SEPARATOR, $rel_parts);
							$attr = ($attr_path ? $attr_path . RelationPath::RELATION_SEPARATOR : '') . $this->get_attribute()->get_alias();
							$data_sheet->get_columns()->add_from_expression($attr);
						}
					}
				}
			}
			
			// Otherwise we are looking for attributes relative to another object
			// So try to find a relation from this widgets object to the data sheet object and vice versa
			if ($this->get_attribute()){
				if ($this->get_attribute()->is_relation() && $this->get_attribute()->get_relation()->get_related_object()->is($data_sheet->get_meta_object())){
					// If this widget represents the direct relation attribute, the attribute to display would be the UID of the
					// of the related object (e.g. trying to fill the order positions attribute "ORDER" relative to the object
					// "ORDER" should result in the attribute UID of ORDER because it holds the same value)
					$data_sheet->get_columns()->add_from_expression($this->get_attribute()->get_relation()->get_related_object_key_alias());
				} elseif ($rel = $data_sheet->get_meta_object()->find_relation($this->get_meta_object(), true)){
					// If the attribute is not a relation, we still can use it for prefills if we find a relation to access
					// it from the $data_sheet's object.
					// TODO currently we use the first relation found. However, this does not work well if that relation
					// is an attribute of an inherited object. Perhaps it would be better to prefer direct attributes. But how?
					$rel_path = RelationPath::relation_path_add($rel->get_alias(), $this->get_attribute()->get_alias());
					if ($data_sheet->get_meta_object()->has_attribute($rel_path)){
						$data_sheet->get_columns()->add_from_attribute($data_sheet->get_meta_object()->get_attribute($rel_path));
					}
				} 
			}
		}
		
		return $data_sheet;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_prefill()
	 */
	public function prepare_data_sheet_to_prefill(DataSheetInterface $data_sheet = null){
		// Do not request any prefill data, if the value is already set explicitly (e.g. a fixed value)
		if ($this->get_value()){
			return $data_sheet;
		}
		return $this->prepare_data_sheet_to_read($data_sheet);
	}
	
	/**
	 * Prefills the input with a value taken from the corresponding column of a given data sheet
	 * @see \exface\Core\Widgets\AbstractWidget::prefill()
	 */
	protected function do_prefill(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet){
		parent::do_prefill($data_sheet);
		// Do not do anything, if the value is already set explicitly (e.g. a fixed value)
		if ($this->get_value()){
			return;
		}
		// To figure out, which attributes we need from the data sheet, we just run prepare_data_sheet_to_prefill()
		// Since an Input only needs one value, we take the first one from the returned array, fetch it from the data sheet
		// and set it as the value of our input.
		$prefill_columns = $this->prepare_data_sheet_to_prefill($this->get_workbench()->data()->create_data_sheet($data_sheet->get_meta_object()))->get_columns();
		if ($col = $prefill_columns->get_first()){
			if (count($data_sheet->get_column_values($col->get_name(false))) > 1 && $this->get_aggregate_function()){
				$this->set_value(DataColumn::aggregate_values($data_sheet->get_column_values($col->get_name(false)), $this->get_aggregate_function()));
			} else {
				$this->set_value($data_sheet->get_cell_value($col->get_name(), 0));
			}
		}
	}
	
	public function get_aggregate_function() {
		return $this->aggregate_function;
	}
	
	public function set_aggregate_function($value) {
		$this->aggregate_function = $value;
		return $this;
	}  
	
	public function get_caption(){
		if (!parent::get_caption()){
			if ($attr = $this->get_attribute()){
				$this->set_caption($attr->get_name());
			}
		}
		return parent::get_caption();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iShowSingleAttribute::get_attribute()
	 */
	public function get_attribute(){
		if (!$this->get_attribute_alias()){
			return null;
		}
		return $this->get_meta_object()->get_attribute($this->get_attribute_alias());
	}	
	
	public function get_size() {
		return $this->size;
	}
	
	public function set_size($value) {
		$this->size = $value;
		return $this;
	}
	
	public function get_style() {
		return $this->style;
	}
	
	public function set_style($value) {
		$this->style = $value;
		return $this;
	}  
	
	public function get_align() {
		if (!$this->align){
			if ($this->get_data_type()->is(EXF_DATA_TYPE_NUMBER)
					|| $this->get_data_type()->is(EXF_DATA_TYPE_PRICE)){
						$this->align = EXF_ALIGN_RIGHT;
			} elseif ($this->get_data_type()->is(EXF_DATA_TYPE_BOOLEAN)){
				$this->align = EXF_ALIGN_CENTER;
			} else {
				$this->align = EXF_ALIGN_LEFT;
			}
		}
		return $this->align;
	}
	
	public function set_align($value) {
		if (!defined('EXF_ALIGN_' . mb_strtoupper($value))){
			throw new WidgetPropertyInvalidValueError($this, 'Invalid alignment value "' . $value . '": use "left", "rigth" or "center"!');
		}
		$this->align = constant('EXF_ALIGN_' . mb_strtoupper($value));
		return $this;
	}
	
	/**
	 * Returns the data type of the column as a constant (e.g. EXF_DATA_TYPE_NUMBER). The column's
	 * data_type can either be set explicitly by UXON, or is derived from the shown meta attribute.
	 * If there is neither an attribute bound to the column, nor an explicit data_type EXF_DATA_TYPE_STRING
	 * is returned.
	 *
	 * @return AbstractDataType
	 */
	public function get_data_type(){
		if ($this->data_type){
			return $this->data_type;
		} elseif ($attr = $this->get_attribute()){
			return $attr->get_data_type();
		} else {
			$exface = $this->get_workbench();
			return DataTypeFactory::create_from_alias($exface, EXF_DATA_TYPE_STRING);
		}
	}	  
	  
}
?>