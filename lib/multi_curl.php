<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

/**
 * cURL multi handle class
 */
class MultiCurl {
    /**
     * @var handle
     */
    private $handle;
    
    private $handles;
    /*
     * Constructor
     */
    public function __construct() {
        $this->handle  = curl_multi_init();
        $this->handles = array();
    }
    /*
     * Destructor
     */
    public function __destruct() {
        if (is_resource($this->handle)) {
            if ($this->handles) {
                foreach ($this->handles as $h) {
                    curl_multi_remove_handle($this->handle, $h);
                }
            }
            curl_multi_close($this->handle);
        }
    }
    /*
     * No support for cloning
     */
    private function __clone() {}
    /*
     * Allow native curl multi-handle functions to be called as class methods
     */
    public function __call($method, $args) {
        $not_allowed = array('init', 'close');
        if (!in_array($method, $not_allowed)) {
            $method = 'curl_multi_' . $method;
            if (function_exists($method)) {
                if ($method == 'curl_multi_add_handle' || $method == 'curl_multi_remove_handle') {
                    $args[0] = ($args[0] instanceof Curly) ? $args[0]->handle : $args[0];
                    if ($method == 'curl_multi_add_handle') {
                        $this->handles[] = $args[0];
                    } else {
                        for ($i = 0; $i < count($this->handles); $i++) {
                            if ($this->handles[$i] == $args[0]) {
                                unset($this->handles[$i]);
                                break;
                            }
                        }
                    }
                }
                array_unshift($args, $this->handle);
                return call_user_func_array($method, $args);
            }
        }
    }
    
    public function execute() {
        do {
            $mrc = curl_multi_exec($this->handle, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);        
        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($this->handle) != -1) {
                do {
                    $mrc = curl_multi_exec($this->handle, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }    
    }

}
?>