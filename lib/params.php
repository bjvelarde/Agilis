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
 * A simple extension of DynaStruct designed to provide a single arg interface to methods expecting variable number of args and without prescribed order
 */
class Params extends DynaStruct {

    public static function __callStatic($method, $args) {
	    $val = $args ? $args[0] : '';
		return new self(array($method => $val));
	}
    /**
     * Check for presence of required parameter
     *
     * @throw ParamsException
     */
    public function checkRequired() {
        $args = func_get_args();
        $missing = array();
        foreach ($args as $arg) {
            if (!isset($this[$arg])) {
                $missing[] = $arg;
                //throw new ParamsException("Missing param $var");
            }
        }
        if ($missing) {
            throw new ParamsException($missing);
        }
    }

}
/**
 * Exception thrown when there are missing params
 */
class ParamsException extends \Exception {

    public function __construct(array $missing) {
        parent::__construct('Missing required params: ' . implode(',', $missing));
    }
}
?>