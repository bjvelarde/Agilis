<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class McveConnection {
    
    private $conn;
    
    public function __construct() {
        if (!function_exists('m_initengine')) {
            throw new Exception('MCVE/Libmonetra is not installed');
        }
        if (!m_initengine(NULL)) {
            throw new McveException('MCVE Initialize engine failed');
        }
        $this->conn = m_initconn();
        if (!m_connect($this->conn)) {
            throw new McveException(m_connectionerror());
        }
    }
    
    public function __get($var) { 
        if ($var == 'conn' || $var == 'connection') {
            return $this->conn; 
        }
    }
    
    public function __call($method, $args) {
       if (!in_array($method, array(
           'initengine', 'connect', 'connectionerror', 
           'destroyconn', 'destroyengine'
       ))) {
           $method = 'm_' . $method;
           if (function_exists($method)) {
               if (!in_array($method, array('m_sslcert_gen_hash', 'm_uwait'))) {
                   array_unshift($args, $this->conn);
               }
               return call_user_func_array($method, $args);
           }
       }
    }
    
    public function __destruct() { 
        m_destroyconn($this->conn);
        m_destroyengine(); 
    }
}

class McveException extends \Exception {}
?>