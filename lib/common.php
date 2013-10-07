<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;
/**
 * Collection of various static utility methods
 */
final class Common extends Singleton {
    /**
     * Easily turn-on/off dumping of variables for debugging
     *
     * @param mixed $mixed
     */
    public static function debug($mixed) {
        Conf::ifNotDefined('DEBUG_MODE', FALSE);
        if (DEBUG_MODE) {
            if (is_scalar($mixed)) {
                echo "<pre>\n$mixed\n</pre>\n";
            } else {
                echo "<pre>\n";
                var_dump($mixed);
                echo "</pre>\n";
            }
        }
    }
    /**
     * Get Client IP Address
     *
     * @return string
     */
    public static function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    /**
     * Convenient ternary method
     *
     * @param mixed $testvalue The subject being checked for existence
     * @param mixed $else The value to return if subject is empty
     * @param mixed $newvalue Optional value to return if subject is not empty
     * @return mixed
     */
    public static function ifEmpty($testvalue, $else, $newvalue=NULL) {
        return isset($testvalue) ? (isset($newvalue) ? $newvalue : $testvalue) : $else;
    }

    public static function findUri($search, $path) {
        $path = substr($path, -1) == '/' ? $path : "{$path}/";
        $uri = $path . $search;
        if (file_exists($uri)) { return $path; }
        $files = scandir($path);
        foreach ($files as $file) {
            if (is_dir($path . $file) && $file != '.' && $file != '..') {
                if (($p = self::findUri($search, $path . $file)) !== FALSE) {
                    return $p;
                }
            }
        }
        return FALSE;
    }
    
    public static function autoloadFind($path, $class, $ns='') {                            
        $files = scandir($path);        
        foreach ($files as $f) {
            $child_path = "{$path}/{$f}";            
            if (is_dir($child_path)) {
                if ($f != '.' && $f != '..') {
                    if (($uri = self::autoloadFind($child_path, $class, $ns)) !== FALSE) {
                        return $uri;
                    }
                }                                   
            } elseif (substr($child_path, -4) == '.php') {
                $contents = file_get_contents($child_path);
                if ((($ns && preg_match('/namespace\s+(' . $ns . ');/', $contents)) || !$ns) &&
                    preg_match('/(class|interface)\s+(' . $class . ')\s+/', $contents)) {
                    return $child_path;
                }  
            }
        }
        return FALSE;
    }

    public static function devLog($sql, $args=array()) {
        Conf::ifNotDefined('DEBUG_MODE', FALSE);
        $args = $args ? $args : array();
        if (DEBUG_MODE) {
            $content = "SQL: [$sql] ARGS: [" . implode(', ', $args) . "]\n";
            if (!file_exists('dev.log')) { touch('dev.log'); }
            file_put_contents('dev.log', $content, FILE_APPEND);
        }
    }

    public static function getCurrentUrl($request_uri=TRUE) {
        if (
            isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
        ){
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }
        $url = $protocol . $_SERVER['HTTP_HOST'];
        // use port if non default
        if (isset($_SERVER['SERVER_PORT']) && strpos($url, ':' . $_SERVER['SERVER_PORT']) === FALSE) {
            $url .= ($protocol === 'http://' && $_SERVER['SERVER_PORT'] != 80 && !isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) || 
                    ($protocol === 'https://' && $_SERVER['SERVER_PORT'] != 443 && !isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? ':' . $_SERVER['SERVER_PORT'] : '';
        }
        $url .= ($request_uri) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];        
        return $url;
    }    
}
?>