<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

class RouteResources implements RouteBuilder {

    private $model;
    private $actions;
    private $children;
    private $members;

    public static $registry = array();

    public $singular;

    public function __construct($model, $actions=array()) {
        $this->model    = $model;
        $this->actions  = isset($actions['only']) ? $actions['only'] : array();
        $this->children = array();
        $this->members  = array();
        $this->singular = FALSE;
        self::$registry[$this->model] = $this;
    }

    public function &nest() {
        $args = func_get_args();
        foreach ($args as $arg) {
            $this->children[] = ($arg instanceof RouteResources) ? $arg :
            (($arg instanceof RouteMember) ? $arg->getRouteResources() : new self($arg));
        }
        self::$registry[$this->model] = $this;
        return $this;
    }

    public function isNested() { return (count($this->children) > 0); }

    public function hasMembers() { return (count($this->members) > 0); }

    public function &members() {
        $args = func_get_args();
        foreach ($args as $arg) {
            if ($arg instanceof RouteMember) {
                $arg->setRouteResources($this);
                $this->members[] = $arg;
            } else {
                if (is_array($arg) && count($arg) > 1) {
                    $name    = $arg[0];
                    $options = $arg[1];
                } else {
                    $name    = $arg;
                    $options = array();
                }
                $m = new RouteMember($name, $options);
                $m->setRouteResources($this);
                $this->members[] = $m;
            }
        }
        self::$registry[$this->model] = $this;
        return $this;
    }

    public function &member($member, $options=array()) {
        $m = ($member instanceof RouteMember) ? $member : new RouteMember($member, $options);
        $m->setRouteResources($this);
        $this->members[] = $m;
        self::$registry[$this->model] = $this;
        return $this;
    }

    public function &collections() {
        $args = func_get_args();
        foreach ($args as $arg) {
            if ($arg instanceof RouteMember) {
                $arg->setRouteResources($this);
                $this->members[] = $arg;
            } else {
                if (is_array($arg) && count($arg) > 1) {
                    $name    = $arg[0];
                    $options = $arg[1];
                } else {
                    $name    = $arg;
                    $options = array();
                }
                $options = array_merge($options, array('type' => 'plural'));
                $m = new RouteMember($name, $options);
                $m->setRouteResources($this);
                $this->members[] = $m;
            }
        }
        self::$registry[$this->model] = $this;
        return $this;
    }

    public function &collection($member, $options=array()) {
        $options = array_merge($options, array('type' => 'plural'));
        return $this->member($member, $options);
    }

    public function getActions() { return $this->actions; }

    public function getModel() { return $this->model; }

    public function getMembers($parent=array()) {
        $nameparts = $pathparts = array($this->model);
        if ($parent) {
            $nameparts = array_merge($parent, $nameparts);
            $immediate = array_pop($parent);

            array_unshift($pathparts, ':' . String::singularize($immediate). '_id');
            array_unshift($pathparts, $immediate);
            $parent = array_reverse($parent);
            foreach ($parent as $p) {
                array_unshift($pathparts, ':' . String::singularize($p). '_id');
                array_unshift($pathparts, $p);
            }
        }
        $children = $members = array();
        if ($this->isNested()) {
            foreach ($this->children as $c) {
                $mchildren = $c->getMembers($nameparts);                
                if ($mchildren) {
                    foreach ($mchildren as $key => $mc) {                        
                       $children[$key] = isset($children[$key]) ? array_merge($children[$key], $mc) : $mc;
                    }
                }
            }
        }
        if ($this->hasMembers()) {        
            foreach ($this->members as $m) {
                $end = NULL;
                if ($m->isPlural() && count($nameparts) > 1) {
                    $end = array_pop($nameparts);
                }
                if (!$m->isPlural() || ($m->isPlural() && count($nameparts) > 1)) {
                    $temp = array();
                    foreach ($nameparts as $n) {
                        $temp[] = String::singularize($n)->to_s;
                    }
                    $np = $temp;
                } else {
                    $np = $nameparts;
                }                
                if ($m->isPlural() && $end) {
                    $np[] = $end;
                }
                $npkey = implode('_', $np);
                $pp = $pathparts;
                $np[] = $pp[] = $m->getName();
                $members[$npkey][] = array(implode('_', $np), '/' . implode('/', $pp) . $m->getParamsPattern(), $m->getMethods());
            }
            //$members = array(implode('_', $nameparts) => $members);
        }        
        return array_merge_recursive($children, $members);
    }

    public function getChildren($parent=array()) {
        $nameparts = $pathparts = array($this->model);
        if (!empty($parent)) {
            $nameparts = array_merge($parent, $nameparts);
            $immediate = array_pop($parent);
            array_unshift($pathparts, ':' . String::singularize($immediate). '_id');
            array_unshift($pathparts, $immediate);
            $parent = array_reverse($parent);
            foreach ($parent as $p) {
                array_unshift($pathparts, ':' . String::singularize($p). '_id');
                array_unshift($pathparts, $p);
            }
        }
        if ($this->isNested()) {
            $children = array();
            foreach ($this->children as $c) {
                $grand_children = $c->getChildren($nameparts);
                if ($grand_children) {
                    foreach ($grand_children as $gc) {
                        if (!is_array($gc)) {
                            if (is_array($grand_children)) {
                                $children[] = $grand_children;
                            }
                            break;
                        } else {
                            $children[] = $gc;
                        }
                    }
                }
            }
            return $children;
        } else {
            $data = $name_stack = $path_stack = array();
            $index = $count = 0;
            $popped = NULL;
            foreach ($nameparts as $np) {
                if ($popped) {
                    $path_stack[] = $popped;
                }
                $name_stack[] = $np;
                $path_stack[] = $pathparts[$index];
                if (isset($pathparts[$index + 1]) && count($pathparts) >= $index) {
                    $path_stack[] = $pathparts[$index + 1];
                }
                if (count($name_stack) > 1) {
                    $ideal_path_size = ((count($name_stack) - 1) * 2) + 1;
                    if (count($path_stack) > $ideal_path_size) {
                        $popped = array_pop($path_stack);
                    }
                    $name = implode('|', $name_stack) . ($this->singular ? '-sing' : '');
                    $data[] = array($name, '/' . implode('/', $path_stack), $this->actions);
                } elseif (count($name_stack) == 1) {
                    $name = $name_stack[0] . ($this->singular ? '-sing' : '');
                    $data[] = array($name, '/' . $path_stack[0], $this->actions);
                }
                $index += 2;
                $count++;
            }
            return $data;
        }
    }

    public function getRoutes() {
        $restfuls = $this->getChildren();
        return array(
            $restfuls,
            $this->getMembers()
        );
    }

}
?>