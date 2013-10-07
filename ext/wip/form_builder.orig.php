<?php
namespace Agilis;

class FormBuilder {

    private $tpl;
    private $elements;
    private $attr;
    private $order;

    public function __construct($partial='form') {
        $this->tpl = new Partial($partial);
        $this->order =
        $this->elements =
        $this->attrs = array();
    }

    public function __set($var, $val) { $this->tpl->{$var} = $val; }

    public function __toString() {
        return $this->render() . '';
    }

    public function addAttr($attr, $val) { $this->attrs[$attr] = $val; }

    public function add($name, Partial $el, $position='end') {
        if ($position == 'start') {
            array_unshift($this->order, $name);
        } elseif ($position = 'end') {
            $this->order[] = $name;
        } elseif (strstr('#', $position)) { // e.g. after#name or before#description
            list($where, $field) = explode('#', $position);
            $rev = array_flip($this->order);
            $index = $rev[$field];
            $index += $where == 'before' ? 0 : 1;
            $s1 = array_slice($this->order, 0, $index);
            $s2 = array_slice($this->order, $index, count($this->order));
            $s1[] = $name;
            $this->order = array_merge($s1, $s2);
        }
        $this->elements[$name] = $el;
    }

    public function getElements() { return $this->elements; }

    public function mergeElements(FormBuilder $form) { $this->elements = array_merge($this->elements, $form->getElements()); }

    public function setElementAttribute($el, $attr, $val) { $this->elements[$el]->{$attr} = $val; }

    public function setAttributes(array $attrs) { $this->attrs = $attrs; }

    public function getAttributes(array $attrs) { return $this->attrs; }

    public function render() {
        $this->tpl->attrs = $this->attrs;
        $forms = array();
        foreach ($this->order as $el) {
            $forms[] = $this->elements[$el];
        }
        $this->tpl->forms = $forms;
        return $this->tpl;
    }

    public static function form_for($route, $customizers=array()) {
        $tpl = '';
        if ($route instanceof Model) {
            list($is_translation, $model_translated) = self::isTranslation($route);
            $method = ($is_translation) ? 'translation' : 'model';
            $tpl = self::$method($route, $customizers);
        } elseif (is_array($route)) {
            $model = end($route);
            list($is_translation, $model_translated) = self::isTranslation($model);
            $method = ($is_translation) ? 'translation' : 'model';
            $tpl   = self::$method($model, $customizers);
            $tpl->index_url = Router::indexLink($route);
            $attrs = $tpl->attrs;
            $action_url = isset($customizers[':action_url']) ? $customizers[':action_url'] : Router::path_for($route);
            $attrs['action'] = $action_url;
            $tpl->attrs = $attrs;
        }
        return $tpl;
    }

    public static function model(Model $model, $customizers=array()) {
        $form   = new self('model_form');
        $table  = $model->getTable();
        $id     = $model->getId();
        $forms  = array();
        $mclass = get_class($model);
        $class  = String::underscore($mclass)->tolower();
        $name   = ($model->_persisted ? "{$class}_{$id}" : "new_{$class}");
        $action_url = isset($customizers[':action_url']) ? $customizers[':action_url'] : Router::path_for($model);
        $form->addAttr('id', 'form_' . $name);
        $form->addAttr('name', $name);
        $form->addAttr('method', 'post');
        $form->addAttr('action', $action_url);
        $form->caption = ($model->_persisted ? 'Edit ' : 'New ') . String::titleize(get_class($model));
        $map_details = array();
        foreach ($table as $field) {
            if (isset($customizers[':exclude']) && is_array($customizers[':exclude']) && in_array($field->name, $customizers[':exclude'])) {
                continue;
            } else {
                $customizer = isset($customizers[$field->name]) ? $customizers[$field->name] : '';
                if ($customizer == '' && $field->is_foreign_key) {
                    $customizer = 'lookup';
                }
                $el = self::tableField($model, $field, $customizer);
                if (isset($customizers[':map']) && ($field->name == $customizers[':map']['x'] || $field->name == $customizers[':map']['y'] || $field->name == $customizers[':map']['desc'])) {
                    $map_details[$field->name] = $model[$field->name];
                }
                if ($el) {
                    $form->add($field->name, $el);
                }
                if ($field->mimes) {
                    $form->addAttr('enctype', 'multipart/form-data');
                }
            }
        }
        if ($map_details) {
            $map_el = new Partial('map-canvas');
            $map_el->latitude   = $map_details[$customizers[':map']['x']];
            $map_el->longitude  = $map_details[$customizers[':map']['y']];
            $map_el->lat_boxid  = $customizers[':map']['box-id-x'];
            $map_el->long_boxid = $customizers[':map']['box-id-y'];
            $map_el->annotation = $map_details[$customizers[':map']['desc']];
            $form->add('map_canvas', $map_el);
        }
        if (!isset($customizers[':exclude_xrefs']) || (isset($customizers[':exclude_xrefs']) && (is_array($customizers[':exclude_xrefs']) || $customizers[':exclude_xrefs'] === FALSE))) {
            $associates = $mclass::getAssociates();            
            if ($associates) {                
                foreach ($associates as $name => $associate) {                
                    if (!isset($customizers[':excluded_xrefs']) || (is_array($customizers[':excluded_xrefs']) && !in_array($name, $customizers[':excluded_xrefs']))) {                        
                        $aclass = $associate->getAssociateClass();
                        if ($associate->type == 'has_and_belongs_to_many') {
                            $el = new Partial('xref-many');
                            $attrs = array(
                                'name'     => "xrefdata[{$associate->through}][{$associate->model}_id][]",
                                //'multiple' => 'multiple'
                            );
                            $rec = $aclass::all(NULL, array('order_by' => $associate->getAssociateTable()->_title_key));
                            $selected = array();
                            foreach ($model->{$name} as $assoc_item) {
                                $selected[] = $assoc_item->getId();
                            }
                            $options = array();
                            if ($rec->count() > 0) {
                                foreach ($rec as $r) {
                                    $options[$r->getId()] = "$r";
                                }
                            } else {
                                $options = array('0' => 'Empty');
                                $attrs['disabled'] = 'disabled';
                            }
                            $el->attrs    = $attrs;
                            $el->selected = $selected;
                            $el->options  = $options;
                            $el->dynamic_add_id = $associate->model;
                            $el->label    = String::humanize($name)->ucfirst()->to_s;
                            $form->add($name, $el);
                        } elseif ($associate->type == 'has_one' && $associate->isPolymorphic()) {                            
                            if ($model->_persisted) {
                                $form->mergeElements(self::model($model[$associate->name], $customizers));
                            } else {
                                $f = self::model(new $aclass, $customizers);
                                $f->setElementAttribute('ref_table', $model->_table);
                                $form->mergeElements($f);                                
                            }
                        }
                    }
                }
            }
        }
        $form->add('csrf', self::csrf());
        if ($model->_persisted) {
            $form->save_btn = 'Update';
            $form->method   = 'put';
        } else {
            $form->save_btn = 'Save';
        }
        //$tpl->index_url = Router::indexLink($model);
        return $form;
    }

    public static function csrf() {
        $csrfkey = Conf::get('CSRF_SESSION_KEY');
        $csrfkey = $csrfkey ? $csrfkey : 'csrf';
        $csrf = String::md5(microtime() * time())->substr(0, 16);
        Session::set($csrfkey, $csrf);
        $tpl = new Partial('input');
        $tpl->attrs = array(
            'name'  => 'csrf',
            'type'  => 'hidden',
            'value' => $csrf
        );
        return $tpl;
    }

    public static function tableField(Model $model, TableField $field, $customizer='') {
        $tpl    = '';
        $attrs  = array();
        $mclass = get_class($model);
        if (!$field->is_hidden && !$field->is_createstamp && !$field->is_timestamp && !($field->is_id && !$model->_persisted)) { //!($field->name == 'password' && $field->encrypt_with)
            $label = $field->is_foreign_key ? String::substr($field->name, 0, -3)->titleize()->to_s : String::titleize($field->name)->to_s;
            $model_name = String::singularize($model->getTableName());
            if (isset($customizer) && is_array($customizer) && $customizer['type'] == 'hidden') {
                $tpl = new Partial('input');
                $attrs['type'] = 'hidden';
                $attrs['value'] = isset($customizer['value']) ? $customizer['value'] : $model[$field->name];
                $label = '';
            } elseif ($field->enum || $field->set || ($field->minvalue && $field->maxvalue) ||
               ($field->is_foreign_key && isset($customizer) && ($customizer == 'lookup' || (is_array($customizer) && isset($customizer['type']) && $customizer['type'] == 'lookup')))) {
                if ($field->is_foreign_key && !$model->_persisted && !empty($model[$field->name])) {
                    $tpl = new Partial('disabled-select');
                    $lookup_criteria = isset($customizer['condition']) ? $customizer['condition'] : NULL;
                    $tpl->options = $field->lookup($lookup_criteria);
                    $attrs['value'] =
                    $tpl->selected  = $model[$field->name];
                } elseif (!$field->set && isset($customizer) && $customizer == 'radio') {
                    $tpl = new Partial('radio');
                } else {
                    $tpl = new Partial('select');
                    if (!$field->set) {
                        $tpl->prompt_str = "[{$label}]";
                    } else {
                        $attrs['multiple'] = 'multiple';
                    }
                }
                if ($field->is_foreign_key) {
                    $lookup_criteria = (is_array($customizer) && isset($customizer['condition'])) ? $customizer['condition'] : NULL;
                    $tpl->options = $field->lookup($lookup_criteria);
                } else {
                    $options = $field->enum ? $field->enum : ($field->set ? $field->set : range($field->minvalue, $field->maxvalue));
                    $titleized = array();
                    foreach ($options as $option) {
                        $titleized[] = String::titleize($option);
                    }
                    $tpl->options = array_combine($options, $titleized);
                }
                if ($model->_persisted) {
                    $tpl->selected = $model[$field->name]; // $field->enum ? $model[$field->name] : explode(',', $model[$field->name]);
                }
            } elseif ($field->is_text || $field->is_rich_text) {
                $tpl = new Partial('textarea');
                $attrs['value'] = $model[$field->name];
                if ($field->is_rich_text) {
                    $attrs['class'] = 'rte';
                }
            } else {
                if ($field->is_boolean && isset($customizer) && $customizer == 'radio') {
                    $tpl = new Partial('radio');
                    $tpl->options = array('1' => 'Yes', '0' => 'No');
                    if ($model->_persisted) {
                        $tpl->selected = $model[$field->name] ? '1' : '0';
                    }
                } elseif ($field->mimes) {
                    $tpl = new Partial('file');
                    $attrs['accept'] = implode(',', $field->mimes);
                    $attrs['value'] = $model[$field->name];
                    $tpl->show_preview = ($field->mimes == array('image/*'));
                    $tpl->file_key = $model_name . '-' . $field->name;
                } else {
                    $tpl = new Partial('input');
                    if ($field->is_boolean) {
                        $attrs['type'] = 'checkbox';
                        if (($model->_persisted && $model[$field->name] == TRUE) ||
                            (!$model->_persisted && $field->default === TRUE)) {
                            $attrs['checked'] = 'checked';
                        }
                    } elseif ($field->is_id || $field->is_foreign_key ||
                              ($mclass::isPolymorphic() && ($field->name == 'ref_id' || $field->name == 'ref_table')) ||
                              (isset($customizer) && $customizer == 'hidden')) {
                        $attrs['type'] = 'hidden';
                        $attrs['value'] = $model[$field->name];
                        $label = '';
                        if (!$field->is_id) {
                            $fmodel = substr($field->name, 0, -3);
                            //$label .= ': ' . $model->{$fmodel};
                        }
                    } else {
                        $attrs['type'] = 'text';
                        if ($field->name == 'password') {
                            $attrs['class'] = 'password';
                        }
                        //$attrs['type'] = $field->name == 'password' ? 'password' : $attrs['type'];
                        $value = $model->_persisted ? $model[$field->name] : (empty($model[$field->name]) ? $field->default: $model[$field->name]);
                        $attrs['value'] = $value;
                        if ($field->is_date || $field->is_datetime) {
                            $attrs['type'] = $field->is_date ? 'date' : 'datetime';
                            $attrs['class'] = 'datepicker';
                        } elseif ($field->is_email) {
                            $attrs['type'] = 'email';
                        } elseif ($field->is_time) {
                            $attrs['type'] = 'time';
                        } elseif ($field->is_url) {
                            $attrs['type'] = 'url';
                        } elseif ($field->is_color) {
                            $attrs['type'] = 'color';
                       // } elseif (in_array($field->name, array('phone', 'tel', 'mobile', 'phoneno', 'phonenum', 'mobileno', 'mobilenum'))) {
                       //     $attrs['type'] = 'tel';
                        } elseif ($field->is_numeric) {
                            $attrs['type'] = (isset($customizer) && $customizer == 'range') ? 'range' : 'number';
                            if ($field->minvalue) {
                                $attrs['min'] = $field->minvalue;
                            }
                            if ($field->maxvalue) {
                                $attrs['max'] = $field->maxvalue;
                            }
                            if ($field->step) {
                                $attrs['step'] = $field->step;
                            }
                        }
                    }
                    if ($field->is_numeric && !$field->is_id) {
                        $attrs['align'] = 'right';
                        $attrs['class'] = 'numeric';
                    }
                }
            }
            //$model_name = String::singularize($model->getTableName());
            $table = $model->getTable();
            $attrs['name'] = "formdata[{$model_name}][{$field->name}]" . (isset($attrs['multiple']) ? '[]' : '');
            $attrs['id']   = "{$model_name}{$model[$table->_id_key]}" . "_{$field->name}";
            if ($field->is_required) {
                $attrs['required'] = 'required';
            }
            $tpl->attrs = $attrs;
            $tpl->required = $field->is_required;
            if (!$field->is_id) {
                $tpl->label = $label;
            }
        }
        return $tpl;
    }

    protected static function isTranslation(Model $model) {
        $mclass = get_class($model);
        $model_name = String::underscore($mclass)->tolower()->to_s;
        $tokens = explode('_', $model_name);
        $last_token = array_pop($tokens);
        return array(($last_token == 'translation'), implode('_', $tokens));
    }

    public static function translation(Model $model, $customizers=array()) {
        list($is_translation, $model_translated) = self::isTranslation($model);
        if ($is_translation) {
            $form   = new self('model_form');
            $table  = $model->getTable();
            $id     = $model->getId();
            $original = $model->{$model_translated};
            $forms  = array();
            $mclass = get_class($model);
            $class  = String::underscore($mclass)->tolower();
            $name   = ($model->_persisted ? "{$class}_{$id}" : "new_{$class}");
            $form->addAttr('id', 'form_' . $name);
            $form->addAttr('name', $name);
            $form->addAttr('method', 'post');
            $form->addAttr('action', Router::path_for($model));
            $form->caption = ($model->_persisted ? 'Edit ' : 'New ') . String::titleize(get_class($model));
            foreach ($table as $field) {
                $customizer = isset($customizers[$field->name]) ? $customizers[$field->name] : '';
                if ($field->is_foreign_key) {
                    $el = new Partial('input');
                    $el->attrs = array(
                        'type'  => 'hidden',
                        'name'  => "formdata[{$class}][{$field->name}]",
                        'value' => $model[$field->name],
                    );
                } elseif ($field->name == 'locale') {
                    $el = new Partial('form_translation_symbol');
                    $el->locale = $model->locale;
                    $el->name = $class;
                } else {
                    $el = self::tableField($model, $field, $customizer);
                    if ($el && $field->name != 'publish' && !$field->is_id) {
                        $locale_el = new Partial('translation_input');
                        $locale_el->form_entry = $el;
                        $locale_el->original = $original->{$field->name};
                        $locale_el->field = $field;
                        $el = $locale_el;
                    }
                }
                if ($el) {
                    $form->add($field->name, $el);
                }
                if ($field->mimes) {
                    $form->addAttr('enctype', 'multipart/form-data');
                }
            }
            $form->add('csrf', self::csrf());
            if ($model->_persisted) {
                $form->save_btn = 'Update';
                $form->method   = 'put';
            } else {
                $form->save_btn = 'Save';
            }
            //$tpl->index_url = Router::indexLink($model);
            return $form;
        } else {

            return self::model($model, $customizers);
        }
    }

}
?>