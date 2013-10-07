<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

class JsEncoder {

    private $data;
    private $quoted;

    public function __construct($data, $quoted=FALSE) {
        $this->data = $data;
        $this->quoted = $quoted;
    }

    public function __toString() { return $this->_encode(); }

    public static function encode($data, $quoted=FALSE) {
        return new self($data, $quoted);
    }

    private function _encode($data=NULL) {
        $data = $data ? $data : $this->data;
        $json = '';
        if (is_array($data) && !CraftyArray::isAssoc($data)) {
            $array = array();
            foreach ($data as $value) {
                if (is_array($value) || is_object($value)) {
                    if (is_array($value) && isset($value['__jsfunc__'])) {
                        $array[] = $value;
                    } else {
                        $array[] = $this->_encode($value);
                    }
                } elseif (is_bool($value)) {
                    $array[] = $value ? 'true' : 'false';
                } elseif (is_numeric($value) || substr($value, 0, 8) == 'function') {
                    $array[] = $value;
                } else {
                    $value = addslashes($value);
                    $array[] = "\"$value\"";
                }
            }
            $json = '[' . implode(',', $array) . ']';
        } elseif ((is_array($data) && CraftyArray::isAssoc($data)) || $data instanceof stdClass || $data instanceof ArrayAccess) {
            $array = array();
            foreach ($data as $key => $value) {
                $str = ($this->quoted ? "\"$key\"" : (strstr($key, ':') ? "\"$key\"" : $key)) . ':';
                if (is_array($value) || is_object($value)) {
                    if (is_array($value) && isset($value['__jsfunc__'])) {
                        $str .= $value;
                    } else {
                        $str .= $this->_encode($value);
                    }
                } elseif (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                    $value = $this->quoted ? "\"$value\"": $value;
                    $str .= $value;
                } elseif (is_numeric($value) || substr($value, 0, 8) == 'function') {
                    if (is_numeric($value) && $this->quoted) {
                        $str .= "\"$value\"";
                    } else {
                        $str .= $value;
                    }
                } else {
                    $str .= "\"$value\"";
                }
                $array[] = $str;
            }
            $json = '{' . implode(',', $array). '}';
        } else {
            $json = json_encode($data);
        }
        return $json;
    }
}
?>