<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;
/**
 * This class mimics the stdClass in almost all respect except that this class was built so that classes extended from this one can
 * implement it's own __set() and __get() methods whereby defining which members have public or private access.
 *
 * @author Benjie Velarde bvelarde@gmail.com
 * @copyright 2012 BV
 */
class DynaStruct implements \IteratorAggregate, \ArrayAccess, \Countable {
    /**
     * @var array A placeholder for the attributes of the object.
     */
    protected $_elements;
    /**
     * Class constructor.
     *
     * @param mixed $data
     */
    public function __construct($data=array()) {
        $data = is_object($data)? get_object_vars($data) :
                (is_array($data) && (CraftyArray::isAssoc($data) || empty($data))? $data: array('data' => $data));
        $this->_elements = ($data === NULL ? array() : $data);
    }
    /*
     * Allow cascading
     */
    public function __call($method, $args) {
        if (strpos($method, 'if_empty_') === 0) {
            $element = substr($method, 9);
            if (empty($this->_elements[$element])) {
                $method = $element;
            } else {
                $method = NULL;
            }
        }
        if ($method) {
            $this->{$method} = array_shift($args);
        }
        return clone $this;
    }    
    /**
     * Get a dynamic property.
     *
     * @return mixed
     */
    public function __get($element) {
        if ($this->hasElement($element)) {
            return $this->_elements[$element];
        } elseif (strpos($element, 'is_') === 0) {
            $element = substr($element, 3);
            return (bool)$this->_elements[$element];
        } else {
            return NULL;
        }
    }
    /*
     * Overload isset()
     */
    public function __isset($elements) { return isset($this->_elements[$elements]); }   
    /**
     * Set a dynamic property.
     */
    public function __set($element, $val) { $this->_elements[$element] = $val; }
    /*
     * Overload unset()
     */
    public function __unset($element) { unset($this->_elements[$element]); }
    /**
     * Convert to CraftyArray
     *
     * @return CraftyArray
     */
    public function asCraftyArray() { return new CraftyArray($this->_elements); }
    /**
     * Encode the members into an XML node
     *
     * @param string $nodename The node name, default is 'node'
     * @return string
     */
    public function asXmlNode($nodename='node') { return $this->asCraftyArray()->asXmlNode($nodename);  }
    /**
     * Encode the members into an XML tree
     *
     * @param string $nodename The node name, default is 'node'
     * @return string
     */
    public function asXmlTree($nodename='node') { return $this->asCraftyArray()->asXmlTree($nodename); }
    /**
     * Retrieves the members in an array.
     *
     * @return array
     */
    public function getElements() { return $this->_elements; }
    /**
     * Checks if the given string is a valid member name.
     *
     * @param string $element The member name
     * @return bool
     */
    public function hasElement($element) { return (is_array($this->_elements) && array_key_exists($element, $this->_elements)); }
    /**
     * Encode the members into JSON
     *
     * @return string
     */
    public function jsonEncode() { return json_encode($this->_elements); }
    /**
     * Merge with another DynaStruct
     *
     * @param DynaStruct $struct
     */
    public function merge(DynaStruct $struct) {
        $this->_elements = array_merge($this->_elements, $struct->getElements());
        return clone $this;
    }
    /**
     * IteratorAggreate interface implementation
     */
    public function getIterator() { return new \ArrayIterator($this->_elements); }
    /**#@+
     * ArrayAccess interface implementation
     */
    public function offsetExists($element) { return $this->hasElement($element); }

    public function offsetGet($element) { return $this->__get($element); }

    public function offsetSet($element, $value) { $this->__set($element, $value); }

    public function offsetUnset($element) { $this->__unset($element); }
    /**#@-*/
    /**
     * Implement Countable
     */
    public function count() { return count($this->_elements); }
}
?>