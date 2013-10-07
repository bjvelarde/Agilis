<?php
namespace Agilis;

class JqGridColModel extends DynaStruct {

    private $_field;

    public function __construct(TableField $field, $config=array()) {
        parent::__construct();
        $hidden         = in_array('hidden', $config);
        $shown_fkey     = in_array('shown_fkey', $config);
        $edit_hidden    = in_array('edit_hidden', $config);
        $search_hidden  = !in_array('nosearch_hidden', $config);
        $editable       = !in_array('disabled', $config);
        $this->_field   = $field;
        //$name = (($field->is_foreign_key && $shown_fkey) ? substr($field->name, 0, -3) : $field->name);
        $this->name     = isset($config['class']) ? "{$config['class']}-" . $field->name : $field->name;
        $this->index    = $field->name;
        $this->search   = !($field->is_id || ($field->is_foreign_key && !$shown_fkey));
        $this->hidden   = ($hidden || $field->is_id || ($field->is_foreign_key && !$shown_fkey) || $field->is_text || $field->mimes || $field->encrypt_with);
        $this->editable = ($editable && !$field->is_id && !$field->is_timestamp && !$field->is_createstamp); // && !($field->is_foreign_key && $shown_fkey)
        $this->editrules = array('edithidden' => $edit_hidden);
        $this->searchoptions = array('searchhidden' => $search_hidden);
        if (in_array('key', $config)) {
            $this->key = TRUE;
        }
		$this->getWidth();
        $this->editTypeAndOptions();
		$this->getFormatter();
    }

	private function getWidth() {
	    $this->resizable = FALSE;
		$label_width = strlen($this->_field->name) * 8;
		if ($this->_field->is_id) {
			$this->width = 60;
		} elseif ($this->_field->is_string || $this->_field->is_foreign_key) {
		    $this->width = $this->_field->maxlen ? ceil($this->_field->maxlen * 7.4) : 256;
			$this->resizable = TRUE;
        } elseif ($this->_field->is_datetime || $this->_field->is_timestamp) {
		    $this->width = 140;
        } elseif ($this->_field->is_date || $this->_field->is_numeric) {
		    $this->width = 80;
			$this->align = 'center';
        } else {
		    $this->width = 80;
        }
		$this->width = ($this->width < $label_width) ? $label_width : $this->width;
	}

	private function getFormatter() {
	    if ($this->_field->is_date || $this->_field->is_timestamp || $this->_field->is_datetime) {
		    $this->formatter = $this->sorttype = 'date';
            if ($this->_field->is_datetime || $this->_field->is_timestamp) {
                $this->formatoptions = array('srcformat' => 'Y-m-d H:i:s', 'newformat' => 'M d, Y h:i a');
			} else {
			    $this->formatoptions = array('srcformat' => 'Y-m-d', 'newformat' => 'M d, Y');
			}
        }
	}

    private function editTypeAndOptions() {
        if ($this->_field->is_boolean) {
            $this->edittype = 'checkbox';
            $editoptions['value'] = str_replace(' or ', ':', $this->_field->options);
        } elseif ($this->_field->is_text || $this->_field->is_rich_text ||
            in_array($this->_field->name, array('description', 'desc', 'short_description', 'short_desc'))) {
            $this->edittype = 'textarea';
            if ($this->_field->is_rich_text) {
                $editoptions['class'] = 'rte';
            } else {
                $editoptions = array(
                    'rows' => 6,
                    'cols' => 40
                );
            }
        } elseif ($this->_field->is_datetime) {
            $editoptions = array(
                'dataInit'     => "function(el){ $(el).datetimepicker({dateFormat:'M d, yy hh:mm tt'}); }"
                /*,
                'defaultValue' => "function(el){ var currentTime = new Date(); var month = parseInt(currentTime.getMonth() + 1); "
                                . "month = month <= 9 ? '0' + month : month; var day = currentTime.getDate(); day = day <= 9 ? '0' + day : day; "
                                . "var year = currentTime.getFullYear(); return year + '-' + month + '-' + day + ' 00:00:00'; }"*/
            );
        } elseif ($this->_field->is_date) {
            $editoptions = array(
                'dataInit'     => "function(el){ $(el).datepicker({dateFormat:'M d, yy'}); }"
                /*,
                'defaultValue' => "function(el){ var currentTime = new Date(); var month = parseInt(currentTime.getMonth() + 1); "
                                . "month = month <= 9 ? '0' + month : month; var day = currentTime.getDate(); day = day <= 9 ? '0' + day : day; "
                                . "var year = currentTime.getFullYear(); return year + '-' + month + '-' + day; }"*/
            );
        } elseif ($this->_field->mimes) {
            $this->edittype = 'file';
            $editoptions['accepts']  = implode(',', $this->_field->mimes);
            //$editoptions['enctype']  = 'multipart/form-data';
           // $editoptions['dataInit'] = "function(el){ $(el).fileUploader(); }";
        } elseif ($this->_field->enum || $this->_field->set) {
            $this->edittype = 'select';
            $values = $this->_field->enum ? $this->_field->enum : $this->_field->set;
            $editoptions = array(
                'value' => array_combine($values, $values)
            );
            if ($this->_field->set) {
                $editoptions['multiple'] = TRUE;
                $editoptions['size'] = 4;
            }
        } else {
            if ($this->_field->is_foreign_key && !$this->hidden) {
                $editoptions['size'] = 60;
            } else {
                $editoptions['size'] = $this->_field->is_numeric ? 8 : 60;
            }    
        }
        if ($this->_field->is_createstamp || $this->_field->is_timestamp || $this->_field->is_foreign_key) {
            $editoptions['disabled'] = 'disabled';
        }
        if ($this->_field->is_foreign_key) {
            $this->formoptions = array('label' => String::substr($this->_field->name, 0, -3)->titleize()->to_s);
        }
        $this->editoptions = $editoptions;
    }
}
?>