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
 * A singleton class to wrap the $_SERVER variable
 */
class ServerVars extends Singleton {
    /*
     * Retrieves the value of a Server variable.
     */
    public function __get($var) {
        $var = trim(strtoupper($var));
        return isset($_SERVER[$var])? $_SERVER[$var]: NULL;
    }
    /*
     * Sets a value for a Server variable.
     */
    public function __set($var, $val) {
        $var = trim(strtoupper($var));
        $_SERVER[$var] = $val;
    }
    /**
	 * Check if $_SERVER is empty
	 *
	 * @return bool
	 */    
    public function is_empty() { return !count($_SERVER); }
}
?>
