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
use \Exception;

class Application extends Singleton {

    protected $controller;
    protected $action;
    protected $params;    
   
    public function init() {
        $router = Router::getInstance();
        $params = $router->route();
        $this->controller = $params->controller_class;
        $this->action     = $params->action;
        $this->params     = $params->getElements();
    }

    public function dispatch() {
        $controller = $this->controller;
        $controller = $controller::getInstance();
        $controller->beforeDispatch($this->params);
        return $controller->{$this->action}();
    }
    
    public static function run() {
        Session::start();
        return self::getInstance()->dispatch();
    }

    public static function configure() {
        $yml_file = APP_ROOT . 'config/config.yml';
        if (!file_exists($yml_file)) {
            throw new Exception("Missing config file: $yml_file");
        }
        self::_configure($yml_file);
        $env_config = APP_ROOT . Conf::get('CURRENT_ENV') . '/config.yml';
        if (file_exists($env_config)) {
            self::_configure($env_config);
        }        
    }
    
    private static function _configure($yml_file) {
        $config = Spyc::YAMLLoad($yml_file);
        foreach ($config as $type => $cfg) {
            foreach ($cfg as $k => $v) {
                if ($type == 'variables') {
                    Conf::setvar($k, $v);
                } else {
                    Conf::set($k, $v);
                }
            }    
        }    
    }
}
?>