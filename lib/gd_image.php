<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;
/**
 * A wrapper class for GD image functions.
 */  
class GdImage {
    /**
	 * @var resource
	 */
    protected $resource = NULL;
    /*
	 * Destroy the resource
	 */
    public function __destruct() {
        if (is_resource($this->resource)) {
            $this->destroy();
        }
    }
    /*
	 * get the resource for whatever purpose this may serve
	 */
    public function __get($var) {
        return ($var == 'resource') ? $this->resource : NULL;
    }
    /*
	 * allow native image functions to be called as methods of this class
	 */
    public function __call($method, $args) {
        $method = 'image' . $method;
        $no_resource = array('imagettfbbox');
        if (function_exists($method)) {
            if (substr($method, 0, 11) == 'imagecreate') {
                $this->resource = call_user_func_array($method, $args);
            } elseif (is_resource($this->resource)) {
                if (substr($method, 0, 9) == 'imagecopy') {
                    $args[0] = ($args[0] instanceof self) ? $args[0]->resource : $args[0];
                }
                if (!in_array($method, $no_resource)) {
                    array_unshift($args, $this->resource);
                }
                return call_user_func_array($method, $args);
            }
        }
    }
}
?>