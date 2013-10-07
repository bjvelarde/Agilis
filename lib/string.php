<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

//require_once('inflections.php');
//require_once('inflector.php');

use \Inflector as Inflector;
/**
 * A class to encapsulate most native string operations including inflections
 *
 * @author Benjie Velarde bvelarde@gmail.com
 * @copyright 2012 BV  
 */
class String {

    private $str;
    /**
     * Constructor
     *
     * @param string $str The subject string
     */
    public function __construct($str) { $this->str = (is_scalar($str) || $str instanceof self) ? "$str" : '';  }

    public function __get($var) { if ($var == 'to_s') { return $this->str; } }

    public function __toString() { return $this->str; }

    public function __call($method, $args) {
        $retstr = $this->str;
		if ($method == 'replace' || $method == 'ireplace') {
            $args[] = $this->str;
            $result = call_user_func_array('str_' . $method, $args);            
            return new self($result);
        } elseif (in_array($method, array('preg_match', 'preg_match_all'))) { 
            $pattern = array_shift($args);
            $matches = array();           
            $args = array_merge(array(&$matches), $args); 
            array_unshift($args, $this->str);
            array_unshift($args, $pattern);         
            call_user_func_array($method, $args);
            array_shift($args);
            array_shift($args);
            return array_shift($args);
        } elseif (in_array($method, array('preg_split', 'explode', 'split'))) {
            $pattern = array_shift($args);
            array_unshift($args, $this->str);
            array_unshift($args, $pattern);
            return call_user_func_array($method, $args);
        } elseif (in_array($method, array('preg_filter', 'preg_replace', 'preg_replace_callback'))) {
            $pattern = array_shift($args);
            $arg2 = array_shift($args);
            array_unshift($args, $this->str);
            array_unshift($args, $arg2);
            array_unshift($args, $pattern);
            $result = call_user_func_array($method, $args);
            return new self($result);
        } elseif (method_exists('Inflector', $method)) {
            eval("\$retstr = Inflector::$method('$this->str');");
        } elseif (method_exists($this, '_' . $method)) {
            $func = '_' . $method;
            if ($args) {
                return call_user_func_array(array($this, $func), $args);
            }            
            return $this->{$func}();
        } else {
            $supported_funcs = array(
                'addcslashes', 'addslashes', 'chop', 'chunk_split', 'convert_cyr_string',
                'convert_uudecode', 'convert_uuencode', 'count_chars ', 'crc32', 'crypt',
                'hebrev', 'hebrevc', 'html_entity_decode', 'htmlentities', 'htmlspecialchars_decode',
                'htmlspecialchars', 'lcfirst', 'levenshtein', 'ltrim', 'md5', 'metaphone',
                'nl2br', 'ord', 'preg_quote', 'quoted_printable_decode', 'quoted_printable_encode',
                'quotemeta', 'rtrim', 'sha1', 'similar_text', 'soundex', 'sscanf',
                'substr_compare', 'substr_count', 'substr_replace', 'substr', 'trim', 'ucfirst',
                'ucwords', 'urlencode', 'urldecode', 'wordwrap'
            );
            if (function_exists('str' . $method) || function_exists('str_' . $method) || in_array($method, $supported_funcs)) {
                $func = $method;
                if (!in_array($method, $supported_funcs)) {
                    $func = function_exists('str' . $method) ? 'str' . $method : 'str_' . $method;
                }
                array_unshift($args, $this->str);
                $retstr = call_user_func_array($func, $args);
            }
        }
        return is_string($retstr) ? new self($retstr) : $retstr;
    }
    /*
     * allow some methods to be called statically
     */
    public static function __callStatic($method, $args) {
        if (in_array($method, array('preg_match', 'preg_match_all', 'preg_split', 'explode', 'split'))) {
            $arg1 = array_shift($args);
            $str  = array_shift($args);
            array_unshift($args, $arg1);
        } elseif (in_array($method, array('preg_filter', 'preg_replace', 'preg_replace_callback'))) {
            $arg1 = array_shift($args);
            $arg2 = array_shift($args);
            $str  = array_shift($args);
            array_unshift($args, $arg2);
            array_unshift($args, $arg1);
        } elseif ($method == 'replace' || $method == 'ireplace') {
            $str = array_pop($args);
        } else {
            $str = array_shift($args);
        }
        $string = new self($str);
        return $string->__call($method, $args);
    }
    /** 
     * Split the string by a delimiter and return a CraftyArray
     * 
     * @param string $delim
     * @return CraftyArray
     */
    public function craftyExplode($delim) { return new CraftyArray(explode($delim, $this->str)); }    
    /**
     * Checks if string starts with any of the given list
     *
     * @param string $pattern,...
     * @return bool
     */
    public function startsWith() {
        $strings = func_get_args();
        foreach ($strings as $string) {
            if (strpos($this->str, $string) === 0) {
                return $string;
            }
        }
        return FALSE;
    }
    /**
     * Get the numbers in a string
     *
     * @access private
     * @return int
     */
    private function _getDigits() {
        $digits = '';
        $len = $this->len();
        if ($len > 0) {
            for ($i = 0; $i < $len; $i++) {
                $temp = $this->substr($i, 1);
                if ($temp >= 0 && $temp <= 9) {
                    $digits .= $temp;
                }
            }
        }
        return $digits;
    }    
    /**
     * Check if string has space(s)
     *
     * @access private
     * @return bool
     */
    private function _has_space() { return preg_match("/\s/", $this->str); }    
    /**
     * Check if plural
     *
     * @access private
     * @return bool
     */
    private function _is_plural() { return ($this->singularize()->to_s != $this->str); } 
    /**
     * Check if singular
     *
     * @access private
     * @return bool
     */
    private function _is_singular() { return ($this->singularize()->to_s == $this->str); }

    private function _truncate($length, $find_nearest_space=TRUE) {
        if ($this->len() > $length) {
            $str = substr($this->str, 0, $length - 3);
            if ($find_nearest_space) {
                $last_space_index = strrpos($str, ' ');
                $str = substr($str, 0, $last_space_index);
            }
            $str .= '...';
            $this->str = $str;
        }
        return $this;
    }      
    
}
?>