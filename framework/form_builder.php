<?php
namespace Agilis;

class FormBuilder {

    private $tpl;
    private $elements;
    private $fieldsets;
    private $attrs;
    private $order;

    public function __construct($partial='form') {
        $this->tpl = new Partial($partial);
        $this->order =
        $this->elements =
        $this->fieldsets =
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

    public function mergeElements(FormBuilder $form) {
        foreach ($form->getElements() as $n => $el) {
            $this->add($n, $el);
        }
    }

    public function setElementAttribute($el, $attr, $val) { $this->elements[$el]->{$attr} = $val; }

    public function setAttributes(array $attrs) { $this->attrs = $attrs; }

    public function getAttributes(array $attrs) { return $this->attrs; }

    public function render() {
        $this->tpl->attrs = $this->attrs;
        $forms = array();
        foreach ($this->order as $el) {
            $forms[] = $this->elements[$el];
        }
        foreach ($this->fieldsets as $fs) {
            $forms[] = $fs;
        }
        $this->tpl->forms = $forms;
        return $this->tpl;
    }

    public static function form_for($route, $config=array()) {
        $tpl = '';
        if ($route instanceof Model) {
            list($is_translation, $model_translated) = self::isTranslation($route);
            $method = ($is_translation) ? 'translation' : 'model';
            $tpl = self::$method($route, $config);
        } elseif (is_array($route)) {
            $model = end($route);
            list($is_translation, $model_translated) = self::isTranslation($model);
            $method = ($is_translation) ? 'translation' : 'model';
            $tpl   = self::$method($model, $config);
            $tpl->index_url = Router::indexLink($route);
            $attrs = $tpl->attrs;
            $action_url = isset($config[':action_url']) ? $config[':action_url'] : Router::path_for($route);
            $attrs['action'] = $action_url;
            $tpl->attrs = $attrs;
        }
        return $tpl;
    }

    public static function model(Model $model, $config=array()) {
        $form   = new self('model_form');
        $table  = $model->getTable();
        $id     = $model->getId();
        $mclass = get_class($model);
        $class  = String::underscore($mclass)->tolower();
        $name   = ($model->_persisted ? "{$class}_{$id}" : "new_{$class}");
        $action_url = isset($config[':action_url']) ? $config[':action_url'] : Router::path_for($model);
        $form->addAttr('id', 'form_' . $name);
        $form->addAttr('name', $name);
        $form->addAttr('method', 'post');
        $form->addAttr('action', $action_url);
        $map_details = array();
        foreach ($table as $field) {
            if (isset($config[':exclude']) && is_array($config[':exclude']) && in_array($field->name, $config[':exclude'])) {
                continue;
            } else {
                $cfg = isset($config[$field->name]) ? $config[$field->name] : array();
                if (empty($cfg) && $field->is_foreign_key) {
                    $cfg[':type'] = 'lookup';
                }
                $el = self::tableField($model, $field, $cfg);
                if (isset($config[':map']) && ($field->name == $config[':map']['x'] || $field->name == $config[':map']['y'])) {
                    $map_details[$field->name] = $model[$field->name];
                }
                if (isset($config[':map']['desc']) && $field->name == $config[':map']['desc']) {
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
            $map_el->latitude   = $map_details[$config[':map']['x']];
            $map_el->longitude  = $map_details[$config[':map']['y']];
            if (isset($config[':map']['box-id-x'])) { $map_el->lat_boxid  = $config[':map']['box-id-x']; }
            if (isset($config[':map']['box-id-y'])) { $map_el->long_boxid = $config[':map']['box-id-y']; }
            if (isset($config[':map']['box-id-z'])) { $map_el->zoom_boxid = $config[':map']['box-id-z']; }
            if (isset($config[':map']['desc'])) {
                if (isset($map_details[$config[':map']['desc']])) {
                    $map_el->annotation = $map_details[$config[':map']['desc']];
                }
            }
            $form->add('map_canvas', $map_el);
        }
        if (!isset($config[':exclude_xrefs']) || (isset($config[':exclude_xrefs']) && $config[':exclude_xrefs'] === FALSE)) {
            $associates = $mclass::getAssociates();
            if ($associates) {
                foreach ($associates as $name => $associate) {
                    if (!isset($config[':excluded_xrefs']) || (is_array($config[':excluded_xrefs']) && !in_array($name, $config[':excluded_xrefs']))) {
                        if ($associate->type == 'has_and_belongs_to_many') {
                            if (isset($config[$name]) && isset($config[$name][':tree-id'])) {
                                $el = self::xrefTree($name, $associate, $config[$name]);
                            } else {
                                $el = self::xrefSelect($model, $name, $associate);
                            }
                            $form->add($name, $el);
                        } elseif ($associate->type == 'has_one' && $associate->isPolymorphic()) {
                            $cfg = isset($config[$associate->name]) ? $config[$associate->name] : '';
                            $aclass = $associate->getAssociateClass();
                            if ($model->_persisted) {                                
                                if ($model->{$associate->name} instanceof $aclass) {
                                    $o = $model->{$associate->name};
                                } else {
                                    $o = new $aclass;
                                    $o->ref_model = $model->_table;
                                    $o->ref_id    = $model->getId();
                                }
                                $form->addFieldset(self::fieldset($model, $o, $cfg));
                            } else {
                                $o = new $aclass;
                                $o->ref_model = $model->_table;
                                $fs = self::fieldset($model, $o, $cfg);
                                $form->addFieldset($fs);
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
        return $form;
    }

    public static function fieldset(Model $parent, Model $model, $config=array()) {
        $form   = new Partial('fieldset');
        $fields = array();
        $table  = $model->getTable();
        $id     = $model->getId();
        $mclass = get_class($model);
        $form->legend  = isset($config[':legend']) ?  $config[':legend'] : String::titleize($mclass)->to_s;
        $map_details = array();
        foreach ($table as $field) {
            if (isset($config[':exclude']) && is_array($config[':exclude']) && in_array($field->name, $config[':exclude'])) {
                continue;
            } else {
                $cfg = isset($config[$field->name]) ? $config[$field->name] : array();
                if (empty($cfg) && $field->is_foreign_key) {
                    $cfg[':type'] = 'lookup';
                }
                $el = self::tableField($model, $field, $cfg);
                if (isset($config[':map']) && ($field->name == $config[':map']['x'] || $field->name == $config[':map']['y'])) {
                    $map_details[$field->name] = $model[$field->name];
                }
                if (isset($config[':map']['desc']) && $field->name == $config[':map']['desc']) {
                    $map_details[$field->name] = $model[$field->name];
                }
                if ($el) {
                    $fields[] = $el;
                }
            }
        }
        if ($map_details) {
            $map_el = new Partial('map-canvas');
            $map_el->latitude   = $map_details[$config[':map']['x']];
            $map_el->longitude  = $map_details[$config[':map']['y']];
            if (isset($config[':map']['box-id-x'])) { $map_el->lat_boxid  = $config[':map']['box-id-x']; }
            if (isset($config[':map']['box-id-y'])) { $map_el->long_boxid = $config[':map']['box-id-y']; }
            if (isset($config[':map']['box-id-z'])) { $map_el->zoom_boxid = $config[':map']['box-id-z']; }
            if (isset($config[':map']['desc'])) {
                if (isset($map_details[$config[':map']['desc']])) {
                    $map_el->annotation = $map_details[$config[':map']['desc']];
                } elseif (!isset($map_details[$config[':map']['desc']]) && $parent->hasElement($config[':map']['desc'])) {
                    $map_el->annotation = $parent[$config[':map']['desc']];
                }
            }
            //$form->add('map_canvas', $map_el);
            $fields[] = $map_el;
        }
        if (!isset($config[':exclude_xrefs']) || (isset($config[':exclude_xrefs']) && $config[':exclude_xrefs'] === FALSE)) {
            $associates = $mclass::getAssociates();
            if ($associates) {
                foreach ($associates as $name => $associate) {
                    if (!isset($config[':excluded_xrefs']) || (is_array($config[':excluded_xrefs']) && !in_array($name, $config[':excluded_xrefs']))) {
                        if ($associate->type == 'has_and_belongs_to_many') {
                            if (isset($config[$name]) && isset($config[$name][':tree-id'])) {
                                $el = self::xrefTree($name, $associate, $config[$name]);
                            } else {
                                $el = self::xrefSelect($model, $name, $associate);
                            }
                            $fields[] = $el;
                        } elseif ($associate->type == 'has_one' && $associate->isPolymorphic()) {
                            $cfg = isset($config[$associate->name]) ? $config[$associate->name] : '';
                            if ($model->_persisted) {
                                $fields[] = self::fieldset($model, $model[$associate->name], $cfg);
                            } else {
                                $aclass = $associate->getAssociateClass();
                                $o = new $aclass;
                                $o->ref_model = $model->_table;
                                $fs = self::fieldset($model, $o, $cfg);
                                $fields[] = $fs;
                            }
                        }
                    }
                }
            }
        }
        $form->fields = $fields;
        return $form;
    }

    private function addFieldset(Partial $fs) {
        $this->fieldsets[] = $fs;
    }

    private static function xrefTree($name, $associate, $cfg) {
        $el = new Partial('dynatree');
        $el->ajax_url  = $cfg[':ajax-url'];
        $el->tree_id   = $cfg[':tree-id'];
        if (isset($cfg[':add-url'])) {
            $el->add_url = $cfg[':add-url'];
        }
        $el->tree_name = "xrefdata[{$associate->through}][{$associate->model}_id]";
        $el->label     = String::humanize($name)->ucfirst()->to_s;
        return $el;
    }

    private static function xrefSelect($model, $name, $associate) {
        $el = new Partial('xref-many');
        $attrs = array(
            'name' => "xrefdata[{$associate->through}][{$associate->model}_id][]",
        );
        $aclass = $associate->getAssociateClass();
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
        return $el;
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

    public static function tableField(Model $model, TableField $field, $config=array()) {
        $tpl    = '';
        $attrs  = array();
        $mclass = get_class($model);
        if (!$field->is_hidden && !$field->is_createstamp && !$field->is_timestamp && !($field->is_id && !$model->_persisted)) { //!($field->name == 'password' && $field->encrypt_with)
            $label = isset($config[':label']) ? $config[':label'] :
                     ($field->is_foreign_key ? String::substr($field->name, 0, -3)->titleize()->to_s : String::titleize($field->name)->to_s);
            $model_name = String::singularize($model->getTableName());
            if (isset($config[':type']) && $config[':type'] == 'hidden') {
                $tpl = new Partial('input');
                $attrs['type'] = 'hidden';
                $attrs['value'] = isset($config[':value']) ? $config[':value'] : $model[$field->name];
                $label = '';
            } elseif ($field->enum || $field->set || ($field->minvalue && $field->maxvalue) ||
               ($field->is_foreign_key && isset($config) && ($config == 'lookup' || (isset($config[':type']) && $config[':type'] == 'lookup')))) {
                if ($field->is_foreign_key && !$model->_persisted && !empty($model[$field->name])) {
                    $tpl = new Partial('disabled-select');
                    $lookup_criteria = isset($config[':condition']) ? $config[':condition'] : NULL;
                    $tpl->options = $field->lookup($mclass, $lookup_criteria);
                    $tpl->selected  = $model[$field->name];
                } elseif (!$field->set && isset($config) && $config == 'radio') {
                    $tpl = new Partial('radio');
                } else {
                    $tpl = new Partial('select');
                    if (!$field->set) {
                        $tpl->prompt_str = "[{$label}]";
                        if ($field->default) {
                            $tpl->selected = $field->default;
                        }
                    } else {
                        $attrs['multiple'] = 'multiple';
                    }
                }
                if ($field->is_foreign_key) {             
                    $lookup_criteria = isset($config[':condition']) ? $config[':condition'] : NULL;
                    $tpl->options = $field->lookup($mclass, $lookup_criteria);
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
                if ($field->is_boolean && (isset($config[':type']) && $config[':type'] == 'radio')) {
                    $tpl = new Partial('radio');
                    $tpl->options = array('1' => 'Yes', '0' => 'No');
                    if ($model->_persisted) {
                        $tpl->selected = $model[$field->name] ? '1' : '0';
                    }
                } elseif ($field->mimes) {
                    $tpl = new Partial('file');
                    $attrs['accept'] = implode(',', $field->mimes);
                    $attrs['value'] = $model[$field->name];
                    $tpl->show_preview = $field->is_image; //($field->mimes == array('image/*'));
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
                              ($mclass::isPolymorphic() && ($field->name == 'ref_id' || $field->name == 'ref_model')) ||
                              (isset($config[':type']) && $config[':type'] == 'hidden')) {
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
                        } elseif ($field->is_integer) {
                            $attrs['type'] = (isset($config[':type']) && $config[':type'] == 'range') ? 'range' : 'number';
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
            if (isset($config[':disabled']) && $config[':disabled'] === TRUE) {
                $attrs['disabled'] = 'disabled';
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

    public static function translation(Model $model, $config=array()) {
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
                $config = isset($config[$field->name]) ? $config[$field->name] : '';
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
                    $el = self::tableField($model, $field, $config);
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

            return self::model($model, $config);
        }
    }

}
?>