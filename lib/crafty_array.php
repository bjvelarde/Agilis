<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

use \DomDocument as DomDocument;
use \DomElement as DomElement;
/**
 * Class that wraps primitive array functions and more
 */
class CraftyArray implements \IteratorAggregate, \ArrayAccess, \Countable {
    /**
     * @var the primitive array
     */
    protected $_elements;
    /**
     * Constructor
     *
     * @param mixed $data. Accepts any data type.
     */
    public function __construct($data=array()) {
        $data = empty($data) ? array() :
               (is_object($data)? get_object_vars($data) :
               (is_array($data) ? $data : array($data)));
        $this->_elements = $data;
    }
    /**
     * Allow php array functions to be called as class methods
     *
     * @return mixed
     */
    public function __call($method, $args) {
        $func = NULL;
        if (function_exists('array_' . $method)) {
            $func = 'array_' . $method;
            if (in_array($method, array(
                'multisort', 'shift', 'unshift', 'pop', 'push',
                'splice', 'walk_recursive', 'walk'
            ))) {
                $args = array_merge(array(&$this->_elements), $args);
            } elseif ($method == 'search' || $method == 'map') {
                $arg1 = array_shift($args);
                array_unshift($args, $this->_elements);
                array_unshift($args, $arg1);
            } else {
                if ($method != 'key_exists') {
                    array_unshift($args, $this->_elements);
                } else {
                    $temp = array();
                    $temp[] = array_shift($args);
                    $temp[] = $this->_elements;
                    $args = $temp;
                }
            }
        } elseif (in_array($method, array(
            'arsort', 'asort', 'each', 'end', 'extract', 'krsort', 'natcasesort',
            'natsort', 'next', 'pos', 'prev', 'reset', 'rsort', 'shuffle', 'sort', 'uksort', 'usort'
        ))) {
            $func = $method;
            $args = array_merge(array(&$this->_elements), $args);
        } elseif ($method == 'contains') {
            $func = $method;
            $arg1 = array_shift($args);
            array_unshift($args, $this->_elements);
            array_unshift($args, $arg1);
        } elseif ($method == 'count' || $method == 'size') {
            $func = $method;
            array_unshift($args, $this->_elements);
        } elseif ($method == 'implode') {
            $temp = array();
            $temp[] = array_shift($args);
            $temp[] = $this->_elements;
            $args = $temp;
        }
        if ($func) {
            $ret = call_user_func_array($func, $args);
            return is_array($ret) ? new self($ret) : $ret;
        } elseif ($method == 'jsonEncode') {
            $method = "_{$method}";
            return $this->{$method}();
        }
        return NULL;
    }
    /**
     * Allow elements to be retrieved like class properties
     *
     * @return mixed
     */
    public function __get($var) {
        if ($var == 'is_assoc') {
            return self::isAssoc($this->_elements);
        }
        return isset($this->_elements[$var]) ? $this->_elements[$var] : NULL;
    }
    /**
     * Check if an element key exists
     *
     * @return bool
     */
    public function __isset($var) { return isset($this->_elements[$var]); }
    /**
     * Allow setting of elements like class properties
     */
    public function __set($var, $val) { $this->_elements[$var] = $val; }
    /**
     * Unset an element
     */
    public function __unset($var) { unset($this->_elements[$var]); }
    
    public function getArray() { return $this->_elements; }
    /**
     * Allow php array functions to be called as static class methods
     *
     * @return mixed
     */
    public static function __callStatic($method, $args) {
        if (is_array($args[0]) || is_object($args[0])) {
            $args[0] = is_object($args[0]) ? get_object_vars($args[0]) : $args[0];
            $self = new self(array_shift($args));
            return call_user_func_array(array($self, $method), $args);
        }
        return NULL;
    }
    /**
     * Render the array as XML string
     *
     * @param string $nodename The tagname
     * @param string $type The xml type NODE | TREE
     * @param string $enctype Encoding type, defaults to UTF-8
     * @param string $xmlver The XML Version, defaults to 1.0
     * @param bool $ignore_blanks Don't render the empty attributes or children nodes
     *
     * @return string
     */
    public function asXml($nodename='node', $type='NODE', $enctype='UTF-8', $xmlver='1.0', $ignore_blanks=TRUE) {
        return $this->toDom($nodename, $type, $enctype, $xmlver, $ignore_blanks)->saveXML();
    }
    /**
     * Render the array as single XML node string
     *
     * @param string $nodename The tagname
     * @param string $enctype Encoding type, defaults to UTF-8
     * @param string $xmlver The XML Version, defaults to 1.0
     * @param bool $ignore_blanks Don't render the empty attributes or children nodes
     *
     * @return string
     */
    public function asXmlNode($nodename='node', $enctype='UTF-8', $xmlver='1.0', $ignore_blanks=TRUE) {
        return $this->asXml($nodename, 'NODE', $enctype, $xmlver, $ignore_blanks);
    }
    /**
     * Render the array as XML tree string
     *
     * @param string $nodename The tagname
     * @param string $enctype Encoding type, defaults to UTF-8
     * @param string $xmlver The XML Version, defaults to 1.0
     * @param bool $ignore_blanks Don't render the empty attributes or children nodes
     *
     * @return string
     */
    public function asXmlTree($nodename='node', $enctype='UTF-8', $xmlver='1.0', $ignore_blanks=TRUE) {
        return $this->asXml($nodename, 'TREE', $enctype, $xmlver, $ignore_blanks);
    }
    /**
     * Sort a dataset according to a specified column name.
     *
     * @param string $column The hash-key
     * @param string $sort ASC | DESC
     *
     * @return CraftyArray
     */
    public function columnSort($column, $sort='ASC') {
        if ($this->count() > 0) {
            $copy = $this->_elements;
            $sortarr = array();
            foreach ($copy as $row) {
                $sortarr[] = $row[$column];
            }
            $sort = ($sort == 'DESC')? SORT_DESC: SORT_ASC;
            array_multisort($sortarr, $sort, SORT_REGULAR, $copy);
        }
        return new self($copy) ;
    }

    public function craftyImplode($glue='') { return new String($this->implode($glue)); }
    /**
     * Insert an element into a given index (position)
     *
     * @param int $index The position
     * @param string $value The element value
     * @return CraftyArray
     */
    public function insert($index, $value) {
        if (!$this->is_assoc) {
            $count = $this->count();
            if ($index == 0) {
                $this->unshift($value);
            } elseif ($count > $index) {
                $slice1 = $this->slice(0, $index);
                $slice2 = $this->slice($index);
                $slice1[] = $value;
                $arr = $slice1->merge($slice2->getArrayCopy());
            } elseif ($index >= $count) {
                $arr[] = $value;
            }
            if ($arr) {
                $this->exchangeArray($arr);
            }
        }
    }
    /**
     * Make unique the dataset records based on a given key
     *
     * @param string $sub_key The hash-key
     * @return CraftyArray
     */
    public function multiUnique($sub_key) {
        $target = $existing_sub_key_values = array();
        foreach ($this as $key => $sub_array) {
            if (!in_array($sub_array[$sub_key], $existing_sub_key_values)) {
                $existing_sub_key_values[] = $sub_array[$sub_key];
                $target[$key] = $sub_array;
            }
        }
        return new self($target) ;
    }
    /**
     * Convert to DomDocument
     *
     * @param string $nodename The tagname
     * @param string $type The xml type NODE | TREE
     * @param string $enctype Encoding type, defaults to UTF-8
     * @param string $xmlver The XML Version, defaults to 1.0
     * @param bool $ignore_blanks Don't render the empty attributes or children nodes
     * @return DomDocument
     */
    public function toDom($nodename='node', $type='NODE', $enctype='UTF-8', $xmlver='1.0', $ignore_blanks=TRUE) {
        $nodename = is_numeric($nodename) ? 'node_' . $nodename : $nodename;
        $dom = new DomDocument($xmlver, $enctype);
        $root = $dom->appendChild(new DomElement($nodename));
        if ($this->count() > 0) {
            foreach ($this as $key => $val) {
                if ($val || (!$val && !$ignore_blanks)) {
                    $childclass = ($type == 'TREE')? 'DomElement': 'DomAttr';
                    if (is_scalar($val)) {
                        $root->appendChild(new $childclass($key, $val));
                    } else {
                        if ($type == 'NODE') {
                            $val = urlencode(json_encode($val));
                            $root->appendChild(new $childclass($key, $val));
                        } else {
                            if (is_object($val)) {
                                if ($val instanceof stdClass) {
                                    $val = self::fromStdClass($val)->toDom($key, $type, $enctype, $xmlver);
                                } else {
                                    // convert this object into stdClass, then we get the DOM
                                    if (method_exists($val, 'toDom')) {
                                        $val = $dom->importNode($val->toDom()->documentElement, TRUE);
                                    } else {
                                        $val = method_exists($val, 'jsonEncode') ? $val->jsonEncode() : json_encode($val);
                                        $val = json_decode($val);
                                        $val = self::fromStdClass($val)->toDom($key, $type, $enctype, $xmlver);
                                    }
                                }
                            } elseif (is_array($val)) {
                                $valarr = new self($val);
                                if ($valarr->is_assoc) {
                                    $val = $valarr->toDom($key, $type, $enctype, $xmlver);
                                } else {
                                    $val_child = self::wrap($val)->toDom($key, $type, $enctype, $xmlver);
                                    $val = new DomDocument($xmlver, $enctype);
                                    $val_root = $val->appendChild(new DomElement($key));
                                    foreach ($val_child->documentElement->childNodes as $childnode) {
                                        $val_root->appendChild($val->importNode($childnode, TRUE));
                                    }
                                }
                            } else {
                                // whatever this is, we won't support it yet, so we make it an empty node.
                                $val = new DomElement($key);
                            }
                            if ($val instanceof DomDocument) {
                                $val = $dom->importNode($val->documentElement, TRUE);
                            }
                            $root->appendChild($val);
                        }
                    }
                }
            }
        }
        return $dom;
    }
    /**
     * Encode to JSON
     *
     * @return string
     */
    private function _jsonEncode() {
        if ($this->count() > 0) {
            $arr = $this->_elements;
            foreach ($arr as $key => $val) {
                if (is_object($val)) {
                    if ($val instanceof stdClass) {
                        $val = get_object_vars($val);
                    } else {
                        // convert this object into stdClass, then we get the array
                        if (method_exists($val, 'jsonEncode')) {
                            $val = json_decode($val->jsonEncode());
                        } else {
                            $val = json_decode(json_encode($val));
                        }
                        $val = get_object_vars($val);
                    }
                }
                $arr[$key] = $val;
            }
        }
        return json_encode($arr);
    }
    /**
     * Create array containing variables and their values
     *
     * @param mixed $variables,...
     * @return array
     */
    public static function compact() {
        return new self(call_user_func_array(
            'compact',
            func_get_args()
        ));
    }

    public static function isAssoc($arr) {
        return (array_keys($arr) !== range(0, count($arr) - 1));
    }
    /**
     * Split a string by string
     *
     * @param string $delim Delimiter
     * @param string $string The input string
     * @param int $limit
     * @return array
     */
    public static function explode() {
        return new self(call_user_func_array(
            'explode',
            func_get_args()
        ));
    }
    /**
     * Convert array of values into an array of references
     *
     * @param array $arr Input array
     * @return array
     */
    public static function makeValuesReferenced($arr){
        $refs = array();
        foreach($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }

    public static function quote(array &$array) {
        for ($i = 0; $i < count($array); $i++) {
            $array[$i] = "'" . addslashes($array[$i]) . "'";
        }
    }
    /**
     * Create an array containing a range of elements
     *
     * @param mixed $start
     * @param mixed $limit
     * @param number $step
     * @return array
     */
    public static function range() {
        return new self(call_user_func_array(
            'range',
            func_get_args()
        ));
    }
    /**
     * IteratorAggreate interface implementation
     */
    public function getIterator() { return new \ArrayIterator($this->_elements); }
    /**#@+
     * ArrayAccess interface implementation
     */
    public function offsetExists($key) { return $this->key_exists($key); }

    public function offsetGet($key) { return $this->key_exists($key)? $this->_elements[$key]: NULL; }

    public function offsetSet($key, $value) { $this->__set($key, $value); }

    public function offsetUnset($key) { $this->__unset($key); }
    /**#@-*/
    /**
     * Countable interface implementation
     */
    public function count() { return count($this->_elements); }
}
?>