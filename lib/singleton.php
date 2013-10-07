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
 * Base class for most Singleton classes
 *
 * @author Benjie Velarde bvelarde@gmail.com
 * @copyright 2012 BV
 */
abstract class Singleton {
    /**
     * @static Registry of singleton instances
     */
    private static $instances;
    /**
     * Get an instance from singletion registry
     *
     * @return Singleton
     */
    public static function getInstance() {
        $class = get_called_class();
        return (isset(self::$instances[$class]) && (self::$instances[$class] instanceof $class)) ? 
               self::$instances[$class]: 
               self::$instances[$class] = new $class;
    }
    
    private function __construct() { $this->init(); }
    
    private function __clone() {}
    
    protected function init() {}

}
?>