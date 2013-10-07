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
 * A data structure with fixed properties
 */
class FixedStruct extends DynaStruct {
    /**
     * Class constructor.
     *
     * @param array $attrlist
     * @throw FixedStructException
     */
    public function __construct() {
        $args = func_get_args();
        if (empty($args)) {
            throw new FixedStructException;
        }
        foreach ($args as $attr) {
            if (is_string($attr)) {
                $elements[trim($attr)] = '';
            }
        }
        parent::__construct($elements);
    }
    /*
     * make sure we only set properties defined in the constructor
     */
    public function __set($var, $val) {
        if ($this->hasElement($var)) {
            parent::__set($var, $val);
        }
    }
}
/**
 * Exception thrown when no argument is supplied in the constructor
 */
class FixedStructException extends \Exception {

    public function __construct() {
        parent::__construct('FixedStruct::__construct() requires an argument list of property names');
    }

}
?>