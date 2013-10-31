<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

class RouteNamespace implements RouteBuilder {

    private $name;
    private $resources;
    private $children;

    public function __construct($name) {
        $this->name = $name;
        $this->resources =
        $this->children = array();
    }

    public function addChild($name) { $this->children[] = $name; }

    public function addResources($name) { $this->resources[] = $name; }

    public function &def() {
        $args = func_get_args();
        foreach ($args as $arg) {
            if ($arg instanceof RouteNamespace) {
                $this->addChild($arg);
            } elseif ($arg instanceof RouteResources) {
                $this->addResources($arg);
            }
        }
        return $this;
    }

    public static function __callStatic($method, $args) {
        $self = new self($method);
        foreach ($args as $arg) {
            if ($arg instanceof RouteNamespace) {
                $self->addChild($arg);
            } elseif ($arg instanceof RouteResources) {
                $self->addResources($arg);
            }
        }
        return $self;
    }

    public function getActions() { return array(); }

    public function getName() { return $this->name; }

    public function getRoutes() {
        $args = func_get_args();
        $parent = $args ? $args[0] : array();
        $restful = $members = array();
        if ($this->children) {
            foreach ($this->children as $child) {
                list($r, $m) = $child->getRoutes(array($this->name));
                $restful = array_merge($restful, $r);
                $members = array_merge($members, $m);
            }
        } elseif ($this->resources) {
            $namehead = $parent ? implode('_', $parent) . "_{$this->name}" : $this->name;
            $pathhead = '/' . ($parent ? implode('/', $parent) . "/{$this->name}" : $this->name);
            foreach ($this->resources as $resource) {
                list($routes, $m) = $resource->getRoutes(); 
                foreach ($restful as $routes) {
                    foreach ($routes as $pattern => $route) {
                        list($a, $b ,$c) = $route;
                        $restful[] = array("{$pathhead}/{$a}" => array("{$pathhead}{$b}", $c));
                    }  
                } 
                
                //foreach ($routes as $route) {
                //    list($a, $b ,$c) = $route;
                //    $restful[] = array("{$pathhead}/{$a}" => array("{$pathhead}{$b}", $c));
                //}
                if ($m) {
                    foreach ($m as $k => $v) {
                        foreach ($v as $member) {
                            $member[0] = "{$namehead}_{$member[0]}";
                            $member[1] = "{$pathhead}{$member[1]}";
                            $members["{$pathhead}/{$k}"][] = $member;
                        }
                    }
                }
            }
        }
        return array($restful, $members);
    }

}
?>