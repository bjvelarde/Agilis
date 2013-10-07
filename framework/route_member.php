<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

class RouteMember  {

    private $name;
    private $resources;
    private $methods;
    private $params;
    private $type;

    public function __construct($name, $options=array()) {
        $this->name      = $name;
        $this->resources = NULL;
        $this->methods   = isset($options['methods']) ? $options['methods'] : array();
        $this->params    = isset($options['params']) ? $options['params'] : array();
        $this->type      = isset($options['type']) ? ($options['type'] === 'plural' ? 'plural' : 'singular') : 'singular';
    }

    public function setRouteResources(RouteResources &$rr) {
        $this->resources = $rr->getModel();
        return $this;
    }

    public function getRouteResources() { return RouteResources::$registry[$this->resources]; }

    public function &methods() {
        $args = func_get_args();
        $accepted = array('get', 'post', 'put', 'delete');
        foreach ($args as $arg) {
            if (in_array($arg, $accepted)) {
                $this->methods[] = $arg;
            }
        }
        return $this;
    }

    public function getName() { return $this->name; }
    
    public function getParams() { return $this->params; }
    
    public function isPlural() { return ($this->type == 'plural'); }
    
    public function getParamsPattern() {
        return ($this->params) ? '/:' . implode('/:', $this->params) : '';
    }

    public function getMethods() {
        return $this->methods ? $this->methods : array('get');
    }

}
?>