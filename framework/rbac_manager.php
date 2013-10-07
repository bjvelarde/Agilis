<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

use \Spyc;

class RbacManager {

    private $controller, $action;

    public function __construct($controller, $action) {
        $this->controller = $controller;
        $this->action     = $action;
    }

    public function __get($var) {
        if (property_exists($this, $var)) {
            return $this->{$var};
        }
        return NULL;
    }
    
    public function allowUser(RbacUser $user) {        
        if (($config = $this->loadConfig()) !== NULL) {
            $role = $user->getRole();            
            if (isset($config[$this->controller])) {
                if (isset($config[$this->controller]['role']) && !in_array($config[$this->controller]['role'], array('all', $role))) {
                    return FALSE;
                }
                if (isset($config[$this->controller]['actions'][$this->action]) && !in_array($config[$this->controller]['actions'][$this->action], array('all', $role))) {
                    return FALSE;
                }
            }
        }
        return TRUE;
    }

    private function loadConfig() {
        $yaml_file = APP_ROOT . 'config/rbac.yml';
        if (file_exists($yaml_file)) {
            return Spyc::YAMLLoad($yaml_file);
        }
        return NULL;
    }    

}
?>