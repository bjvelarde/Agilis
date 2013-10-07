<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

class ModelCollection extends CraftyArray {

    protected $model;
    protected $metadata;

    public function  __construct($model, $dataset=array()) {
        parent::__construct();
        $this->model    = $model;
        $this->metadata = NULL;
        if ($dataset) {
            $this->wrap($dataset);
        }
    }

    public function __call($method, $args) {
        if (!empty($this->_elements) && preg_match('/^(all|first|find|last)(_by_([a-z-0-9_]+))?$/', $method)) {
            if (empty($args) || (!empty($args) && !is_array($args[0]) && $args[0] !== NULL)) {
                if ($method == 'first') {
                    return $this->_elements[0];
                } elseif ($method == 'last') {
                    return end($this->_elements);
                } elseif ($method == 'all') {
                    return $this;
                }
                return NULL;
            } elseif (!empty($args)) {
                $options = array();
                if (!strstr($method, '_by_')) {
                    $criteria = (is_array($args[0]) && parent::isAssoc($args[0])) ? $args[0] : array();
                    if (isset($args[1])) {
                        $options = $args[1];
                    }
                } elseif (preg_match('/^(all|first|find|last)_by_([a-z-0-9_]+)$/', $method, $matches)) {
                    $criteria = array_combine(explode('_and_', $matches[2]), $args);
                    $method = $matches[1];
                }
                if (!isset($options['order_by'])) {
                    $class   = $this->model;
                    $orderby = $class::getIdKey();
                    $sorting = 'asc';
                } else {
                    list($orderby, $sorting) = each($options['order_by']);
                }
                if ($criteria) {
                    $results = array();
                    foreach ($this->_elements as $el) {
                        foreach ($criteria as $k => $v) {
                            if (!$this->qualify($el[$k], $v)) {
                                break;
                            }
                            $results[] = $el;
                        }
                    }
                    $result = new ModelCollection($this->model, $results);
                } else {
                    $result = $this;
                }
                if ($orderby) {
                   $result = $result->columnSort($orderby, $sorting);
                }
                if (isset($options['limit'])) {
                    $offset = isset($options['offset']) ? $options['offset'] : 0;
                    $result = $result->slice($offset, $options['limit']);
                }
                if ($method == 'first') {
                    return $result->shift();
                } elseif ($method == 'last') {
                    return $result->pop();
                } else {
                    return $result;
                }
            }
        } elseif (($delegate = PluginManager::findPlugin($this, $method)) !== NULL) {
            array_unshift($args, $this);
            return call_user_func_array($delegate, $args);
        } else {
            return parent::__call($method, $args);
        }
    }

    public static function __callStatic($method, $args) {
        if (preg_match('/^(all|first|find|last)(_by_([a-z-0-9_]+))?$/', $method, $matches)) {
            return call_user_func_array(array($this->model, $method), $args);
        } elseif (($delegate = PluginManager::findPlugin(get_called_class(), $method)) !== NULL) {
            array_unshift($args, get_called_class());
            return call_user_func_array($delegate, $args);
        } else {
            return parent::__callStatic($method, $args);
        }
    }

    public function getModel() { return $this->model; }

    public function wrap(array $dataset) {
        foreach ($dataset as $data) {
           $this->addItem($data);
        }
    }

    public function addItem($data) {
        if ($data instanceof $this->model && $data->_persisted) {
            $this->_elements[] = $data;
        } elseif (!empty($data) && is_array($data) && parent::isAssoc($data)) {
            $class = $this->model;
            $record = new $class($data);
            $record->_persisted = TRUE;
            $record->_modified  = FALSE;
            $record->checkOwners();
            if ($record->_persisted && $record->after_find()) {
                $this->_elements[] = $record;
            }
        }
    }

    public function getData() {
        $data = array();
        if ($this->count() > 0) {
            foreach ($this as $el) {
                $data[] = $el->getData();
            }
        }
        return $data;
    }

    public function addMetaData($key, $data) { $this->metadata[$key] = $data; }

    public function getMetaData($key) {
        return isset($this->metadata[$key]) ? $this->metadata[$key] : NULL;
    }

    public function columnSort($column, $sort='ASC') {
        $this->_elements = parent::columnSort($column, $sort)->getArray();
        return $this;
    }

    public function multiUnique($sub_key) {
        $this->_elements = parent::multiUnique($sub_key)->getArray();
        return $this;
    }

    public function assocBy() {
        $assoc = array();
        $keys = func_get_args();
        foreach ($this->_elements as $e) {
            $hash_key = array();
            foreach ($keys as $k) {
                $hash_key[] = $e->{$k};
            }
            $hash_key = implode('-', $hash_key);
            $assoc[$hash_key] = $e;
        }        
        return $assoc;
    }

    private function qualify($item, $value) {
        if (is_array($value)) {
            list($oper, $val) = $value;
        } else {
            $oper = '=';
            $val  = $value;
        }
        switch ($oper) {
            case '='    : return ($item == $val);
            case '!='   :
            case '<>'   : return ($item != $val);
            case '>'    : return ($item > $val);
            case '<'    : return ($item < $val);
            case '>='   : return ($item >= $val);
            case '<='   : return ($item <= $val);
            case 'like%': return (strcasecmp(substr($item, 0, strlen($val)), $val) === 0);
            case '%like': return (strcasecmp(substr($item, -(strlen($val))), $val) === 0);
            case 'like' : return (strcasecmp($item, $val) === 0);
        }
    }

}
?>