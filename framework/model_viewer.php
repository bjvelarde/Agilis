<?php
namespace Agilis;

class ModelViewer {

    private $tpl;
    private $elements;
    private $fieldsets;
    private $order;

    public function __construct($partial='model-viewer') {
        $this->tpl = new Partial($partial);
        $this->order =
        $this->elements =
        $this->fieldsets = array();
    }

    public function __set($var, $val) { $this->tpl->{$var} = $val; }
    
    public function __get($var) { return $this->tpl->{$var}; }

    public function __toString() {
        return $this->render() . '';
    }

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

    public function render() {        
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

    public static function view_for($route, $config=array()) {
        $tpl = '';
        if ($route instanceof Model) {
            $tpl = self::model($route, $config);
        } elseif (is_array($route)) {
            $model = end($route);
            $tpl   = self::model($model, $config);
            $tpl->index_url = Router::indexLink($route);
        }
        return $tpl;
    }

    public static function model(Model $model, $config=array()) {
        $form   = new self();
        $table  = $model->getTable();
        $id     = $model->getId();
        $mclass = get_class($model);
        $class  = String::underscore($mclass)->tolower();
        $form->title = 'View ' . String::titleize($mclass);
        $map_details = array();
        foreach ($table as $field) {
            if (isset($config[':exclude']) && is_array($config[':exclude']) && in_array($field->name, $config[':exclude'])) {
                continue;
            } elseif (!$field->is_id && !$field->is_hidden) {
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
            }
        }
        if ($map_details) {
            $map_el = new Partial('map-canvas');
            $map_el->latitude   = $map_details[$config[':map']['x']];
            $map_el->longitude  = $map_details[$config[':map']['y']];
            if (isset($config[':map']['desc']) && isset($map_details[$config[':map']['desc']])) {
                $map_el->annotation = $map_details[$config[':map']['desc']];
            }
            $form->add('map_canvas', $map_el);
        }
        if (!isset($config[':exclude_xrefs']) || (isset($config[':exclude_xrefs']) && $config[':exclude_xrefs'] === FALSE)) {
            $associates = $mclass::getAssociates();
            if ($associates) {
                foreach ($associates as $name => $associate) {
                    if (!isset($config[':excluded_xrefs']) || (is_array($config[':excluded_xrefs']) && !in_array($name, $config[':excluded_xrefs']))) {
                        if ($associate->type == 'has_and_belongs_to_many') {                            
                            $el = self::xrefList($name, $associate, $config[$name]);
                            $form->add($name, $el);
                        } elseif ($associate->type == 'has_one' && $associate->isPolymorphic()) {
                            $cfg = isset($config[$associate->name]) ? $config[$associate->name] : '';
                            $aclass = $associate->getAssociateClass();
                            if ($model->{$associate->name} instanceof $aclass) {
                                $o = $model->{$associate->name};
                            } else {
                                $o = new $aclass;
                                $o->ref_model = $model->_table;
                                $o->ref_id    = $model->getId();
                            }
                            $form->addFieldset(self::fieldset($model, $o, $cfg));
                        }
                    }
                }
            }
        }
        return $form;
    }

    public static function fieldset(Model $parent, Model $model, $config=array()) {
        $form   = new Partial('view-fieldset');
        $fields = array();
        $table  = $model->getTable();
        $id     = $model->getId();
        $mclass = get_class($model);
        $form->legend  = isset($config[':legend']) ?  $config[':legend'] : String::titleize($mclass)->to_s;
        $map_details = array();
        foreach ($table as $field) {
            if (isset($config[':exclude']) && is_array($config[':exclude']) && in_array($field->name, $config[':exclude'])) {
                continue;
            } elseif (!$field->is_id && !$field->is_hidden && $field->name != 'ref_id' && $field->name != 'ref_model') {
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
            if (isset($config[':map']['desc'])) {
                if (isset($map_details[$config[':map']['desc']])) {
                    $map_el->annotation = $map_details[$config[':map']['desc']];
                } elseif (!isset($map_details[$config[':map']['desc']]) && $parent->hasElement($config[':map']['desc'])) {
                    $map_el->annotation = $parent[$config[':map']['desc']];
                }
            }
            $fields[] = $map_el;
        }
        if (!isset($config[':exclude_xrefs']) || (isset($config[':exclude_xrefs']) && $config[':exclude_xrefs'] === FALSE)) {
            $associates = $mclass::getAssociates();
            if ($associates) {
                foreach ($associates as $name => $associate) {
                    if (!isset($config[':excluded_xrefs']) || (is_array($config[':excluded_xrefs']) && !in_array($name, $config[':excluded_xrefs']))) {
                        if ($associate->type == 'has_and_belongs_to_many') {
                            $fields[] = self::xrefList($model, $name);
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

    private static function xrefList($model, $name) {
        $el = new Partial('xref-list');
        $el->items = $model->{$name};
        $el->label = String::titleize($name)->to_s;
        return $el;
    }

    public static function tableField(Model $model, TableField $field, $config=array()) {
        $tpl = '';
        if (!$field->is_hidden && !($field->is_id && !$model->_persisted)) {
            $label = isset($config[':label']) ? $config[':label'] :
                     ($field->is_foreign_key ? String::substr($field->name, 0, -3)->titleize()->to_s : String::titleize($field->name)->to_s);
            $model_name = String::singularize($model->getTableName());
            if (!isset($config[':hidden']) || !$config[':hidden']) {
                $value = $field->is_foreign_key ? $model->{String::substr($field->name, 0, -3)->to_s} : $model[$field->name];
                $tpl = new Partial('field-view');
                $tpl->field = $field;
                $tpl->value = $field->is_boolean ? ($value === TRUE ? 'Yes' : 'No') : 
                             ($field->set ? explode(',', $value) : 
                             (($field->is_timestamp || $field->is_datetime && $value) ? date(Translator::t('date_format'), strtotime($value)) : $value));
                $tpl->label = $label;
            }
        }
        return $tpl;
    }

}
?>