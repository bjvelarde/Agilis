<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

abstract class DataSource {

    protected $config;
    protected $conn;
    
    public function __construct(DataSourceConfig $config) { 
        $this->config = $config; 
        //$this->conn   = $conn;
        $this->connect();
    } 
    
    public function __call($method, $args) {
        return call_user_func_array(array($this->conn, $method), $args);
    }
    
    public function __get($var) { 
        if ($var == 'data_source_config') {
            return $this->config;
        } elseif (isset($this->config[$var])) {
            return $this->config[$var];
        } elseif (isset($this->conn) && property_exists($this->conn, $var)) {            
            return $this->conn->{$var};
        }
    }
    
    abstract public function connect();
}
?>