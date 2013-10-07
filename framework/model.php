<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

use \ReflectionClass;

Conf::ifNotDefined('CACHE_TTL', 86400);
/**
 * Base Object Model
 *
 * @author Benjie Velarde bvelarde@gmail.com
 * @copyright 2012 BV
 */
abstract class Model extends DynaStruct {

    const DIR = 'app/models/';
    /**
     * @var array Holds any arbitrary properties.
     */
    protected $_tempvars;
    /**
     * @var array Holds any arbitrary cache for associates.
     */
    protected $_assoc_cache;
    /**
     * @var array Holds validation errors.
     */
    protected $_errors;
    /**
     * @static array Registry of association, and scoping rules
     */
    protected static $rules = array();

    private static $cfgtmp = array();

    public function __construct($data=array()) {
        $this->_tempvars = array('_persisted' => FALSE, '_modified' => FALSE, '_custom_slug' => FALSE);
        $this->_assoc_cache = array();
        $this->_errors   = array();
        parent::__construct();
        //$class  = get_class($this);
        //$table = Table::getInstance($class);
        //if (!$table instanceof Table) {
        //    throw new ModelException($class);
        //} elseif (!self::isLocked()) {
        //    static::config();
        //    self::lock();
        //}
        $this->loadData($data);
        $this->after_initialize();
    }

    public static function configure() {
        self::unlock();
        static::config();
        self::lock();
    }

    public function __call($method, $args) {
        // to go around quirky behavior of php 5.3.x when trying to invoke __callStatic inside a non-static method
        $ss1 = substr($method, 0, 8);
        $ss2 = substr($method, 0, 9);
        if ($ss1 == 'find_by_' || $ss1 == 'last_by_' || $ss2 == 'first_by_') {
            return self::__callStatic($method, $args);
        }
        // end hack
        // try to instantiate a has-many associate
        if (substr($method, 0, 3) == 'new') {
            $class = substr($method, 3);
            $assoc = String::pluralize($class)->underscore()->to_s;
            if (self::isAssociate($assoc)) {
                $association = self::getAssociation($assoc);
                if ($association->type == 'has_many' && !$association->through) {
                    $data = $args ? $args[0] : array();
                    $class = String::classify($association->model)->to_s;
                    $associate = new $class($data);
                    $model = $association->inversed_by ? $association->inversed_by : String::underscore(get_class($this))->to_s;
                    if ($this->_persisted) { $associate->{$model} = $this; }
                    return $associate;
                }
            }
        }
        if (self::isScope($method)) {
            $where = $args ? $args[0] : array();
            return $this->getScope($method, $where);
        } elseif (self::isAssociate($method)) {
            $where   = $args ? $args[0] : array();
            $options = ($args && count($args) > 1) ? $args[1] : array();
            return $this->getAssociate($method, $where, $options);
        } elseif (ModelRules::isCallback($method)) {
            return $this->callback($method);
        } elseif (($delegate = PluginManager::findPlugin($this, $method)) !== NULL) {
		    //$args = array_merge(array(&$this), $args);
            array_unshift($args, $this);
            return call_user_func_array($delegate, $args);
        }
        return parent::__call($method, $args);
    }

    public function __get($var) {
        switch ($var) {
            case '_table':
                return self::getTableName();
            case '_rules':
                return self::getRules();
            case '_fields':
                return self::getFields();
            case '_errors':
                return $this->_errors;
            default:
                $table = self::getTable();
                if (self::isAssociate($var)) {
                    return $this->getAssociate($var);
                } elseif (self::isScope($var)) {
                    return $this->getScope($var);
                } elseif ($table->hasElement($var)) {
                    $val = parent::__get($var);
                    return ($table[$var]->set) ? explode(',', $val) : $val;
                } elseif (isset($this->_tempvars[$var])) {
                    return $this->_tempvars[$var];
                } elseif (self::isPolymorphic()) {
                    $ref_class = String::camelize($var)->to_s;
                    $ref_obj =  NULL;
                    if (isset($this['ref_id'])) {
                        $ref_obj = $ref_class::first($this['ref_id']);
                    }
                    return $ref_obj ? $ref_obj : new $ref_class;
                }
            return NULL;
        }
    }

    public function __set($var, $val) {
        $table      = self::getTable();
        $foreign_key = "{$var}_id";
        if ($table->hasElement($var) || $table->hasElement($foreign_key)) { // check if variable is a field in table
            if ($table->hasElement($foreign_key)) {
                $assoc_class = self::getAssociateClass($var);
                if ($assoc_class && $val instanceof $assoc_class) {
                    $var = $foreign_key;
                    $val = $val->getId();
                }
            }
            if (isset($table[$var]) && $table[$var]->set && is_array($val)) {
                $val = implode(',', $val);
            } elseif ($table[$var]->is_boolean && in_array(strtolower($val), array('on', 'off', 'yes', 'no', 'true', 'false', 'y', 'n', '1', '0', 't', 'f'))) {
                $val = in_array(strtolower($val), array('on', 'yes', 'true', 'y', '1', 't')) ? 1 : 0;
            }
            $immutable = $table[$var]->is_immutable;
            // do not set value if field is immutable AND not-empty
            if ((!$immutable || ($immutable && empty($this[$var]))) && $this[$var] !== $val) {
                parent::__set($var, $val);
                if ($this->_persisted) {
                    $this->_modified = TRUE;
                }
                if ($var == 'slug') {
                    $this->_custom_slug = TRUE;
                }
            }
        } elseif (self::isPolymorphic() && $this->ref_model == $var) {
            $ref_class = String::camelize($var)->to_s;
            if ($val instanceof $ref_class) {
                $this->ref_id = $var->getId();
            }
        } else {
            // otherwise, store variable as an arbitrary property
            $this->_tempvars[$var] = $val;
        }
    }

    public function __toString() {
        $title_key = self::getTitleKey();
        return ($title_key && $this[$title_key]) ? $this[$title_key] :
               ($this->_persisted ? (string)$this->getId() : get_class($this));
    }

    public static function __callStatic($method, $args) {
        $substr = substr($method, 0, 8);
        if ($substr == 'find_by_' || $substr == 'last_by_') {
            $fields = explode('_and_', substr($method, 8));
            $options = array();
            if ($substr == 'last_by_') {
                $options = array('order_by' => array(self::getIdKey() => 'DESC'), 'limit' => 1);
            } elseif (count($fields) < count($args)) {
                $options = array_pop($args);
            }
            return self::find(array_combine($fields, $args), $options);
        } elseif (substr($method, 0, 9) == 'first_by_') {
            $args[] = array('limit' => 1);
            $method = 'find_by_' . substr($method, 9);
            return self::__callStatic($method, $args);
        } elseif (self::isScope($method)) {
            $scope = self::getScopeRule($method);
            list($scope_criteria, $scope_options) = $scope;
            list($k, $v) = each($scope_criteria);
            if (is_scalar($v)) { // support only internal scopes
                $scope_criteria = (isset($args[0]) && is_array($args[0])) ? array_merge($scope_criteria, $args[0]) : $scope_criteria;
                $scope_options = (isset($args[1]) && is_array($args[1])) ? array_merge($scope_options, $args[1]) : $scope_options;
                return self::find($scope_criteria, $scope_options);
            }
            return NULL;
        } elseif (!self::isLocked()) {
            $table =& self::loadTable();
            $rules  =& self::loadRules();
            if ($method == 'hidden_from_forms') {
                foreach ($args as $a) {
                    if ($table->hasElement($a)) {
                        $table[$a] = $table[$a]->hidden = TRUE;
                    }
                }
            } elseif (preg_match('/^encrypt_([a-z0-9]+)_with$/', $method, $matches)) {
                $field = array_pop($matches);
                if ($table->hasElement($field)) {
                    $table[$field] = $table[$field]->encrypt_with($args[0]);
                }
            } elseif (preg_match('/^to_string_by_([a-z0-9]+)$/', $method, $matches)) {
                $field = array_pop($matches);
                if ($table->hasElement($field)) {
                    $table->setTitleKey($field);
                }
            } elseif (method_exists($rules, $method) && is_callable(array($rules, $method))) {
                return call_user_func_array(array($rules, $method), $args);
            } elseif (method_exists(__NAMESPACE__ . '\ModelValidation', $method) && is_callable(array(__NAMESPACE__ . '\ModelValidation', $method))) {
                $args = array_merge(array(&$table), $args);
                return call_user_func_array(array(__NAMESPACE__ . '\ModelValidation', $method), $args);
            } elseif (!$rules->makeAssociation($method, $args) &&
                !$rules->setCallback($method, $args) &&
                !ModelValidation::defineValidation($table, $method, $args)) {
                if (($delegate = PluginManager::findPlugin(get_called_class(), $method)) !== NULL) {
                    array_unshift($args, get_called_class());
                    return call_user_func_array($delegate, $args);
                }
                return NULL;
            }
        } elseif (($delegate = PluginManager::findPlugin(get_called_class(), $method)) !== NULL) {
            array_unshift($args, get_called_class());
            return call_user_func_array($delegate, $args);
        }
    }

    public static function isLocked() {
        $class = get_called_class();
        $table = isset(self::$cfgtmp[$class]['table']) ? self::$cfgtmp[$class]['table'] : NULL;
        $rules = isset(self::$cfgtmp[$class]['rules']) ? self::$cfgtmp[$class]['rules'] : NULL;
        $table = ($table instanceof Table) ? $table : self::getTable();
        $rules  = ($rules instanceof ModelRules) ? $rules : self::getRules();
        $locked = ($table->isLocked() || $rules->isLocked());
        if (!$locked) {
            if (!isset(self::$cfgtmp[$class]['table'])) {
                self::$cfgtmp[$class]['table'] = $table;
            }
            if (!isset(self::$cfgtmp[$class]['rules'])) {
                self::$cfgtmp[$class]['rules']  = $rules;
            }
            self::$cfgtmp[$class]['table']->unlock();
            self::$cfgtmp[$class]['rules']->unlock();
        }
        return $locked;
    }

    public static function lock() {
        $class = get_called_class();
        self::$cfgtmp[$class]['table']->lock();
        self::$cfgtmp[$class]['rules']->lock();
        unset(self::$cfgtmp[$class]['table']);
        unset(self::$cfgtmp[$class]['rules']);
    }

    public static function unlock() {
        $class = get_called_class();
        self::$cfgtmp[$class]['table'] = self::getTable();
        self::$cfgtmp[$class]['rules'] = self::getRules();
        self::$cfgtmp[$class]['table']->unlock();
        self::$cfgtmp[$class]['rules']->unlock();
    }

    public function checkOwners() {
        if ($this->_persisted) {
            $class = get_class($this);
            $owners = isset(self::$rules[$class]['owners']) ?
                      self::$rules[$class]['owners'] : NULL;
            if ($owners) {
                $associates = self::getAssociates();
                foreach ($owners as $owner) {
                    if (!$this->{$owner}) {
                        $cleanup = $associates[$owner]->dependent;
                        if ($this->_persisted) {
                            if ($cleanup == 'nullify') {
                                $this["{$owner}_id"] = NULL;
                                $this->save();
                            } else {
                                $this->{$cleanup}();
                            }
                        } else {
                            break;
                        }
                    }
                }
            }
        }
    }

    public function dump() { return self::getTable()->dump($this);  }

    public static function generate($name) {
        $class = String::classify($name);
        $file  = APP_ROOT . self::DIR . $class->underscore() . '.php';
        $contents = "<?php\nuse Agilis\Model;\n\nclass $class extends Model {\n\n    "
                  . "protected static function config() {\n        "
                  . "//associations\n\n        //validations\n\n"
                  . "        //scope\n    }\n}\n?>";
        if (file_put_contents($file, $contents)) {
            return $file;
        }
        return FALSE;
    }

    public function getData() { return $this->getElements(); } // alias for getElements, just to make more sense

    public function getErrors() { return $this->_errors; }

    public function getId() { return $this[self::getIdKey()]; }

    public function getCreateStamp() {
        $key = self::getTable()->_createstamp_key;
        return ($key) ? strtotime($this[$key]) : NULL;
    }

    public function getUpdateStamp() {
        $key = self::getTable()->_timestamp_key;
        return ($key) ? strtotime($this[$key]) : NULL;
    }

    public function loadData($data=array()) {
        if ($data && CraftyArray::isAssoc($data)) {
            foreach ($data as $key => $val) {
                if (self::getTable()->hasElement($key)) {
                    $this->{$key} = $val;
                } else {
                    $this->_tempvars[$key] = $val;
                }
            }
        }
    }

    private function cleanUpdateParams(array $data) {
        $clean = array();
        $table = self::getTable();
        if ($data && CraftyArray::isAssoc($data)) {
            foreach ($data as $key => $val) {
                if ($table->hasElement($key)) {
                    if (($table[$key]->set && is_array($val))) {
                        $val = implode(',', $val);
                    } elseif ($table[$key]->is_boolean) {
                        if ($val !== TRUE && $val !== FALSE && $val !== 1 && $val !== 0) {
                            if (in_array(strtolower($val), array('on', 'off', 'yes', 'no', 'true', 'false', 'y', 'n', '1', '0', 't', 'f'))) {
                                $val = in_array(strtolower($val), array('on', 'yes', 'true', 'y', '1', 't')) ? 1 : 0;
                            }
                        }
                    } elseif ($table[$key]->encrypt_with) {
                        if ($this[$key] != $val) {
                            $encryptor = $table[$key]->encrypt_with;
                            $val = $encryptor($val);
                        }
                    }
                    $clean[$key] = $val;
                }
            }
        }
        return $clean;
    }
    /*-----------------------------------------------------------------------------
     * Table methods
     */
    public static function foreignKey() {
        return String::singularize(self::getTableName()) . '_id';
    }
    
    public static function hasField($var) { return self::getTable()->hasElement($var); }
    
    public static function getDbAdapter() { return self::getTable()->getDbAdapter(); }

    public static function getDB() { return self::getTable()->_db; }

    public static function getFields() { return self::getTable()->getFieldNames(); }

    public static function getIdKey() { return self::getTable()->_id_key; }

    public static function getPrimaryKeys() { return self::getTable()->_primary_keys; }

    public static function getTable() { return Table::getInstance(get_called_class()); }

    public static function getTableName() { return self::getTable()->_name; }

    public static function getTitleKey() { return self::getTable()->_title_key; }

    public static function isPolymorphic() {
        return self::getTable()->_polymorphic;
    }

    public static function polymorphic() {
        $class = get_called_class();
        $table =& self::loadTable();
        self::$cfgtmp[$class]['table'] = $table->polymorphic();
    }

    public static function total($where=array()) { return self::getTable()->total($where); }

    public static function &loadTable() {
        $class = get_called_class();
        if (!(self::$cfgtmp[$class]['table'] instanceof Table)) {
            $table = Table::$class();
            if (!$table->isLocked()) {
                self::$cfgtmp[$class]['table'] = $table;
            }
        }
        self::$cfgtmp[$class]['table'] = isset(self::$cfgtmp[$class]['table']) ? self::$cfgtmp[$class]['table'] : NULL;
        return self::$cfgtmp[$class]['table'];
    }
    /*-----------------------------------------------------------------------------
     * General Rules methods
     */
    public static function getRules() {
        $class = get_called_class();
        return ModelRules::$class();
    }

    public static function &loadRules() {
        $class = get_called_class();
        if (!(self::$cfgtmp[$class]['rules'] instanceof Table)) {
            $rules = ModelRules::$class();
            if (!$rules->isLocked()) {
                self::$cfgtmp[$class]['rules'] = $rules;
            }
        }
        self::$cfgtmp[$class]['rules'] = isset(self::$cfgtmp[$class]['rules']) ? self::$cfgtmp[$class]['rules'] : NULL;
        return self::$cfgtmp[$class]['rules'];
    }
    /*-----------------------------------------------------------------------------
     * Crud methods
     */
    /**
     * Retrieve all records matching given search criteria.
     *
     * @param array $what A key-value pair search criteria
     * @param array $options Search option such as limit, order_by and sort
     * @return array
     */
    public static function all($what=NULL, $options=array()) {        
        $class = get_called_class();
        unset($options['limit'], $options['offset']);
        $all = self::find($what, $options);        
        return  ($all instanceof ModelCollection) ? $all :
            (($all instanceof Model)? new ModelCollection($class, array($all)) : new ModelCollection($class));
    }
    /**
     * Create an instance and make it immediately persistent on the database.
     *
     * @return Model | NULL
     */
    public static function create() {
        $reflection = new ReflectionClass(get_called_class());
        $instance   = $reflection->newInstanceArgs(func_get_args());
        $ret = $instance->save() ? $instance : $instance->getErrors();
        return $ret;
    }
    /**
     * Delete a record on the database
     *
     * @return bool
     */
    public function delete() {
        if ($this->_persisted) {
            $db_adapter = self::getDbAdapter();
            $return = $db_adapter->delete($this);
            if ($return) {
                $this->cleanDependents();
            }
            return $return;
        }
        return FALSE;
    }

    public static function deleteMany($criteria=array()) {
        $db_adapter = self::getDbAdapter();
        return $db_adapter->deleteMany(self::getTable(), $criteria);
    }
    /**
     * Delete a record on the database (with fat)
     *
     * @return bool
     */
    public function destroy() {
        if ($this->_persisted && $this->before_destroy()) {
            if ($this->delete()) {
                return $this->after_destroy();
            }
        }
        return FALSE;
    }

    public static function where(array $criteria) {
        return new QueryWhere($criteria);
    }

    public static function select($fields, $where=NULL, $options=array()) {
        $db_adapter = self::getDbAdapter();
        return $db_adapter->select(self::getTable(), $fields, $where, $options);
    }
    /**
     * A generic search engine that allows retrieving a record by just passing the record ID.
     *
     * @param array $what A key-value pair search criteria
     * @param array $options Search option such as limit, order_by and sort
     * @return mixed
     */
    public static function find($what=NULL, $options=array()) {
	    if ($what === 0) return NULL;
        $db_adapter = self::getDbAdapter();
        // check if we need to do a join
        $joined = '';
        if (is_array($what)) {
            foreach ($what as $k => $v) {
                if (self::isAssociate($k)) {
                    $joined = $k; break;
                }
            }
        }
        if (!$joined) {
            if (isset($options['group_by'])) {
                foreach ($options['group_by'] as $k => $v) {
                    if (self::isAssociate($k)) {
                        $joined = $k; break;
                    }
                }
            }
        }
        if (!$joined) {
            if (isset($options['order_by'])) {
                if (is_array($options['order_by']) || (is_string($options['order_by']) && strtolower($options['order_by']) != 'random')) {
                    $options['order_by'] = is_array($options['order_by']) ? $options['order_by'] : array($options['order_by'] => 'asc');
                    foreach ($options['order_by'] as $k => $v) {
                        if (self::isAssociate($k)) {
                            $joined = $k; break;
                        }
                    }
                }    
            }
        }
        $class = get_called_class();
        if (isset($options['paginate'])) {
            $paginate = $options['paginate'];
            unset($options['paginate']);
            $options['limit']  = isset($paginate['per_page']) ? $paginate['per_page'] : APP_PER_PAGE;
            $options['offset'] = ($paginate['page'] - 1) * $options['limit'];
        }
        if  ($joined) {
            return $db_adapter->crossFind(new $class, self::getAssociation($joined), $what, $options);
        } else {
            if (is_scalar($what) && !isset($options['limit'])) {
                $options['limit'] = 1;
            }
            return $db_adapter->find(self::getTable(), $what, $options);
        }
    }
    /**
     * Retrieve the first record matching the given search criteria
     *
     * @param array $what A key-value pair search criteria
     * @param array $options Search option such as limit, order_by and sort
     * @return Model | NULL
     */
    public static function first($what=NULL, $options=array()) {
        $options['limit']  = 1;
        $options['offset'] = 0;
        return self::find($what, $options);
    }
    /**
     * Insert the record and make it persistent on the database.
     *
     * @return bool
     */
    public function insert() {
        if (!$this->_persisted && $this->before_create()) {
            if ($this->validate()) {
                $this->createSlug();
                if (self::getDbAdapter()->insert($this)) {
                    return $this->after_create();
                }    
            }
        }
        return FALSE;
    }
    /**
     * Retrieve the last record matching the given search criteria
     *
     * @param array $what A key-value pair search criteria
     * @param array $options Search option such as limit, order_by and sort
     * @return Model | NULL
     */
    public static function last($what=NULL, $options=array()) {
        if (empty($options) && ($idkey = self::getIdKey())) {
            $options['order_by'] = array($idkey => 'desc');
            $options['limit']  = 1;
            $options['offset'] = 0;
            return self::find($what, $options);
        } else {
            $results = self::find($what, $options);
            return $results->pop();
        }
    }
    /**
     * Saves record on the database or create one for non-persisted record.
     *
     * @return bool
     */
    public function save($params=array()) {
        if ($this->before_save()) {            
            if ($this->_persisted) {
                $return = $this->update($params);
            } else {
                if (!empty($params)) {
                    $params = $this->cleanUpdateParams($params);
                    $this->_elements = array_merge($this->_elements, $params);
                }                
                $return = $this->insert();
            }
            if ($return) {
                return $this->after_save();
            }
        }
        return FALSE;
    }
    /**
     * Update record on the database.
     *
     * @return bool
     */
    public function update($params=array()) {
        if (!empty($params)) {
            $params = $this->cleanUpdateParams($params);
            if (isset($params['slug']) && $params['slug']) {
                $this->_custom_slug = TRUE;                
            }            
            $elements = $params ? array_merge($this->_elements, $params) : $this->_elements;
            $this->_modified = (!($this->_elements == $elements));
            if ($this->_modified) {
                $this->_elements = $elements;
            }
        }
        $bu = $this->before_update();
        if ($this->_persisted && $this->_modified && $bu) {
            if ($this->validate()) {
                $this->createSlug();
                if (self::getDbAdapter()->update($this)) {
                    return $this->after_update();
                }
                return FALSE;
            }
        }
        return FALSE;
    }

    public static function updateMany(array $pairs=array(), $criteria=array()) {
        $db_adapter = self::getDbAdapter();
        return $db_adapter->updateMany(self::getTable(), $pairs, $criteria);
    }
    /*-----------------------------------------------------------------------------
     * Scope methods
     */
    //protected static function getInternalScope($var) {  return self::getRules()->getInternalScope($var);  }

    public static function getScopeRule($scope) { return self::getRules()->getScopeRule($scope); }

    protected function getScope($var, $where=array()) {
        $scope = self::getScopeRule($var);
        if (is_array($scope) && !CraftyArray::isAssoc($scope)) { // this is an external /associate scope
            list($scope, $options) = $scope;
            list($associate, $criteria) = each($scope);

			if (is_string($criteria) && $criteria{0} == ':') { //this is a callback
			    $callback = substr($criteria, 1);
				$args = array($where, $options);
			    if (method_exists($this, $callback)) {
				    return call_user_func_array(array($this, $callback), $args);
				} else {
                    $this_class = get_class($this);
				    if (method_exists($this_class, $callback)) {
					    return call_user_func_array(array($this_class, $callback), $args);
					} else {
					    throw new Exception("Undefined callback: {$this_class}::{$callback}();");
					}
				}
			}

            $where = is_string($where) ? array('__PARTIAL_SQL__' => $where) : $where;
            $criteria = is_string($criteria) ? array('__PARTIAL_SQL__' => $criteria) : $criteria;
            $criteria = array_merge($criteria, $where);
            if (self::isAssociate($associate)) {
			    return $this->getAssociate($associate, $criteria, $options);
                //$association = self::getAssociation($associate);
                //if ($association->type == 'has_one' || $association->type == 'belongs_to') {
                //    return NULL;
                //}
                //$assoc_class = String::camelize($association->model)->to_s;
                //$foreign_key = String::underscore(get_class($this)) . '_id';
                //if ($association->through) {
                //    $collection = $this->crossFind($association, $criteria, $options);
                //    if (isset($options['model'])) {
                //        for ($i = 0; $i < count($collection); $i++) {
                //            $collection[$i] = $collection[$i]->{$options['model']};
                //        }
                //    }
                //    return $collection;
                //} else {
                //    $criteria = array_merge(array($foreign_key => $this->getId()), $criteria);
                //    if (isset($options['limit'])) {
                //        if ($options['limit'] == 1) {
                //            return $assoc_class::first($criteria);
                //        }
                //    }
                //    return $assoc_class::all($criteria, $options);
                //}
            }
        } else {
            list($criteria, $options) = $scope;
            return self::all($criteria, $options);
        }
    }

    protected static function getScopes() { return self::getRules()->getScopes(); }

    protected static function isScope($scope) { return self::getRules()->isScope($scope); }

    public static function scope($name, $criteria, $options=array()) {
        if (!self::isLocked()) {
            $rules =& self::loadRules();
            $rules->scope($name, $criteria, $options);
        }
    }
    /*-----------------------------------------------------------------------------
     * Validation methods
     */
    protected function validate() {
        if ($this->before_validation()) {
            foreach (self::getTable() as $key => $field) {
                // skip for auto-increment, create-stamp, and timestamp fields
                if (!$field->is_id && !$field->is_createstamp && !$field->is_timestamp) {
                    $value = isset($this->_elements[$key]) ? $this->_elements[$key] : NULL;
                    list($result, $error) = $field->validate($value);
                    if (!$result) {
                        $this->_errors[] = $error;
                    }
                }
            }
            if (empty($this->_errors)) {
                return $this->after_validation();
            }
        }
        return FALSE;
    }
    /*-----------------------------------------------------------------------------
     * Callback rules methods
     */
    private static function setCallback($method, $args) { return self::getRules()->setCallback($method, $args); }

    private function callback($method) {
        $model_id = $this->getId();
        $rules = self::getRules();
        if ($rules->isCallbackDefined($method)) {
            if (!$rules->getCallbackStatus($model_id)) {
                // if previous callback failed, cancel all succeeding callbacks
                return FALSE;
            }
            $callback = $rules->getCallback($method);
            $args     = array();
            if (is_string($callback)) {
                if ($callback{0} == ':') {
                    $callback = array($this, substr($callback, 1));
                } elseif (class_exists($callback)) {
                    if (!(method_exists($callback, $method) && is_callable(array($callback, $method)))) {
                        throw new ModelCallbackException("Can't find or call $method in class $callback");
                    }
                    $callback = array($callback, $method);
                    $args[] = $this;
                } elseif (!function_exists($callback)) {
                    throw new ModelCallbackException("Can't find function $callback");
                } else {
                    $args[] = $this;
                }
            } elseif (is_object($callback)) {
                if (method_exists($callback, $method) && is_callable($callback)) {
                    $callback = array($callback, $method);
                } elseif (!is_callable($callback)) {
                    $cb_class = get_class($callback);
                    throw new ModelCallbackException("Can't find $method in class $cb_class or instance of $cb_class is not callable");
                }
                $args[] = $this;
            }
            if (!is_callable($callback)) {
                throw new ModelCallbackException("Callback for $method is not callable!");
            }
            $status = call_user_func_array($callback, $args);
            $rules->setCallbackStatus($model_id, $status);
            return $status;
        }
        return TRUE;
    }
    /*-----------------------------------------------------------------------------
     * Association methods
     */
    public static function getAssociateClass($var) { return self::getRules()->getAssociateClass($var); }

    public function crossFind(ModelAssociate $association, $wheres=array(), $options=array()) {
        $table       = self::getTable();
        $assoc_table = $association->getAssociateTable();
        $xref_table  = $association->getXrefTable();
        $joined       = $xref_table ? ($assoc_table->_db == $table->_db && $table->_db == $xref_table->_db) : ($assoc_table->_db == $table->_db);
        $dataset      = array();
        $class        = get_class($this);
        if ($joined) {
            $db_adapter = $class::getDbAdapter();
            $dataset = $db_adapter->crossFind($this, $association, $wheres, $options);
        } else {
            $xref_class  = $association->getXrefClass();
            $class_to_use = $xref_class ? $xref_class : $association->getAssociateClass();
            $finder      = ($association->type == 'has_one' || $association->type == 'belongs_to') ? 'first' : 'all';
            $foreign_key = self::foreignKey();
            $finder_options = array();
            if (isset($options['order_by'])) {
                foreach ($options['order_by'] as $order_by => $order) {
                    $o = array();
                    if ($order_by == $association->model) {
                        $o[] = $order;
                    }
                }
                $finder_options['order_by'] = $o;
            }
            if (isset($options['group_by'][$association->model])) {
                list ($t, $f) = each($options['group_by']);
                if ($t == $association->model) {
                    $finder_options['group_by'] = $f;
                }
            }
            //if (isset($options['offset'])) {
            //    $finder_options['offset'] = $options['offset'];
            //}
            //if (isset($options['limit'])) {
            //    $finder_options['limit'] = $options['limit'];
            //}
            $xrefset = $class_to_use::$finder(array($foreign_key => $this->getId()), $finder_options);
            if ($xrefset && ($association->type == 'has_many' || $association->type == 'has_and_belongs_to_many')) {
                foreach ($xrefset as $xrefobj) {
                    $dataset[] = $class::first($xrefobj[$foreign_key]);
                }
                if ($dataset && isset($options['order_by'])) {
                    $dset = array();
                    list ($f, $o) = each($options['order_by']);
                    if ($f != $association->model) {
                        foreach($dataset as $record) {
                            $dset[$record[$f]] = $record;
                        }
                        if (strtoupper($o) == 'DESC') {
                            krsort($dset);
                        } else {
                            ksort($dset);
                        }
                        $dataset = array_values($dset);
                    }
                }
                $dataset = new ModelCollection($class, $dataset);
            }
        }
        return ($dataset->count() == 1 && ($association->type == 'has_one' || $association->type == 'belongs_to')) ? $dataset->shift() : $dataset;
    }

    public function getPolymorphicAssociateByModelName($model) {
        $associates = self::getAssociates();
        foreach ($associates as $a) {
            if ($a->model == $model && $a->isPolymorphic()) return $this[$a->name];
        }
        return NULL;
    }

    protected function getAssociate($associate, $wheres=array(), $options=array()) {
	    $this_id   = $this->getId();
		$cache_key = md5($this_id . $associate . serialize($wheres) . serialize($options));
        if (!isset($this->_assoc_cache[$cache_key])) {
            $result = NULL;
            $association = self::getAssociation($associate);
            $assoc_class = $association->getAssociateClass();
            $ref_model   = String::underscore(get_class($this))->to_s;
            $polymorphic_key = $association->isPolymorphic() ? array(
                'ref_id'    => $this_id,
                'ref_model' => $ref_model
            ) : NULL;
            if ($association->type == 'belongs_to') {
                if ($association->isPolymorphic()) {
                    $assoc = $assoc_class::first($polymorphic_key);
                    $result = ($assoc instanceof $assoc_class) ? $assoc : new $assoc_class($polymorphic_key);
                } else {
                    $result = (isset($this["{$association->name}_id"])) ? $assoc_class::first($this["{$association->name}_id"]) : new $assoc_class;
                }
				//$this->_assoc_cache[$associate] = $result;
				//return $result;
            } elseif ($association->through) {
                if (isset($wheres[$assoc_class::getIdKey()]) || $association->type == 'has_one') {
                     $options['limit'] = 1;
                }
                $wheres = !empty($wheres) ? array($association->model => $wheres) : array();
                $result = $this->crossFind($association, $wheres, $options);
                if (isset($options['model'])) {
                    for ($i = 0; $i < count($result); $i++) {
                        $result[$i] = $result[$i]->{$options['model']};
                    }
                }
            } else {
                $foreign_key = ($association->inversed_by) ? "{$association->inversed_by}_id" : self::foreignKey(); //($association->type == 'has_many' && $association->model == $association->name) ? self::foreignKey() : "{$association->name}_id";
                $find_key    = $association->isPolymorphic() ? $polymorphic_key : array($foreign_key => $this_id);
                if ($association->type == 'has_one') {
                    if ($this_id) {
                        $assoc_obj = $assoc_class::first($find_key);
                        if ($assoc_obj instanceof ModelCollection && $assoc_obj->count() < 1) {
                            $assoc_obj = new $assoc_class;
                            if ($association->isPolymorphic()) {
                                $assoc_obj['ref_id']    = $this_id;
                                $assoc_obj['ref_model'] = $ref_model;
                            } else {
                                $assoc_obj[$foreign_key] = $this_id;
                            }
							$this->_assoc_cache[$associate] = $assoc_obj;
							$result = $assoc_obj;
                        } elseif ($assoc_obj instanceof Model) {
							$this->_assoc_cache[$associate] = $assoc_obj;
							$result = $assoc_obj;
                        }
                    }
                } else {
                    if ($this_id) {
                        $find_key = !empty($wheres) ? array_merge($wheres, $find_key) : $find_key;
                        $result = $assoc_class::all($find_key, $options);
                    } else {
                        $result = array();
                    }
                }
            }
			$this->_assoc_cache[$cache_key] = $result;
        }
        return $this->_assoc_cache[$cache_key];
    }

    public static function getAssociates() { return self::getRules()->getAssociates(); }

    protected static function getAssociation($associate) { return self::getRules()->getAssociation($associate); }

    protected static function isAssociate($associate) { return self::getRules()->isAssociate($associate); }

    private function cleanDependents() {
        $associates = self::getAssociates();
        foreach ($associates as $alias => $associate) {
            $associate->cleanUp($this);
        }
    }
    
    private function createSlug() {
        if ($this->hasField('slug') && (!$this->slug || !$this->_custom_slug)) {
            $class = get_class($this);
            if ($this->slug && $this->_custom_slug) {
                $slug = $this->slug;
                $obj  = $class::first_by_slug($slug);
                if ($obj instanceof $class && (!$this->_persisted || $obj->getId() != $this->getId())) {
                    $slug .= '-' . ($this->_persisted ? $this->getId() : uniqid());
                    $this->_elements['slug'] = $slug;                    
                }            
            } else {
                $title_key = self::getTitleKey();
                $title = $this[$title_key];            
                $slug = preg_replace('/\s+/', '-', trim(strtolower($title)));            
                $slug = str_replace('_', '-', $slug);            
                $slug = str_replace('&', '-and', $slug);
                $slug = str_replace('%', '-percent', $slug);
                $slug = str_replace('@', '-at', $slug);            
                $slug = preg_replace('/[^A-Z0-9-]/i', '', $slug);            
                $slug = preg_replace('/-+/', '-', $slug);            
                if (substr($slug, -1) == '-') {
                    $slug = substr($slug, 0, -1);
                }
                if (substr($slug, 0, 1) == '-') {
                    $slug = substr($slug, 1);
                }            
                $slug = $slug ? $slug : ($this->_persisted ? $this->getId() : uniqid());            
                $obj  = $class::first_by_slug($slug);
                if ($obj instanceof $class && (!$this->_persisted || $obj->getId() != $this->getId())) {
                    $slug .= '-' . ($this->_persisted ? $this->getId() : uniqid()); 
                }
                $this->_elements['slug'] = $slug;
            }            
        }
    }
    
    private function checkForSlugInParams(array $params=array()) {
        if ($params && isset($params['slug'])) {
            $this->_custom_slug = TRUE;
            $this->_elements['slug'] = $params['slug'];
        }
    }

    protected static function config() {}
}

class ModelException extends \Exception {

    public function __construct($class) {
        parent::__construct("Table for $class is not defined.");
    }

}

class ModelCallbackException extends \Exception {}
?>