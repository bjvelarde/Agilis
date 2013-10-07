<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

use \Exception;
use \Spyc;

Conf::ifNotDefined('CONF_CACHE_DIR', 'cache/config/');

final class PluginManager extends Singleton {

    const DIR = 'config/plugins';

    public static $plugins = array();

    public static function classPlugin($delegator, $delegate, $method, $alias='') {
        $alias = $alias ? $alias : $method;
        if (!isset(self::$plugins['class'][$delegator][$delegate][$alias])) {
            self::$plugins['class'][$delegator][$delegate][$alias] = $method;
            self::$plugins['class']['callbacks']["{$delegator}-{$alias}"] = array($delegate, $method);
        } else {
            throw new DuplicatePluginMethodException($delegator, $alias);
        }         
    }

    public static function configure() {
        $key         = 'plugins';        
        $cache       = FileCache::get($key);
        $mtime       = FileCache::mtime($key);
        $entries     = scandir(APP_ROOT . self::DIR);
        $renew_cache = FALSE;
        foreach ($entries as $entry) {
            if (substr($entry, -4) == '.yml') {
                $yml_file = APP_ROOT . self::DIR . "/$entry";
                if (!$cache || ($cache && filemtime($yml_file) > $mtime)) {
                    $renew_cache = TRUE;
                    $config = Spyc::YAMLLoad($yml_file);
                    foreach ($config as $type => $plugins) {
                        foreach ($plugins as $delegator => $plugin) {
                            foreach ($plugin as $delegate => $methods) {
                                foreach ($methods as $method => $alias) {
                                    $func = "{$type}Plugin";
                                    self::$func($delegator, $delegate, $method, $alias);
                                }
                            }
                        }
                    }
                }
            }
        }        
        if ($renew_cache) {
            self::cache();
        } else {
            self::$plugins = unserialize(base64_decode($cache));
        }
    }
    
    private static function cache() {
        $key   = 'plugins';
        $cache = base64_encode(serialize(self::$plugins));
        FileCache::set($key, $cache, APP_ROOT . CONF_CACHE_DIR);    
    }
    
    private static function getCache() {
        $key   = 'plugins';
        $cache = FileCache::get($key);
        self::$plugins = unserialize(base64_decode($cache));
        return self::$plugins;
    }
    
    public static function objectPlugin($delegator, $delegate, $method, $alias='') {
        $alias = $alias ? $alias : $method;
        if (!isset(self::$plugins['object'][$delegator][$delegate][$alias])) {
            self::$plugins['object'][$delegator][$delegate][$alias] = $method;
            self::$plugins['object']['callbacks']["{$delegator}-{$alias}"] = array($delegate, $method);
        } else {
            throw new DuplicatePluginMethodException($delegator, $alias);
        }        
    }
    
    public static function findPlugin($mixed, $method) {
        self::refreshRegistry();
        if (!empty(self::$plugins)) {
            if (is_object($mixed)) {
                $plugins = isset(self::$plugins['object']) ? self::$plugins['object'] : array();
                $class = get_class($mixed);
                $type = 'object';
            } else {
                $plugins = isset(self::$plugins['class']) ? self::$plugins['class'] : array();
                $class = $mixed;
                $type = 'class';
            }
            $callbackkey = "{$class}-{$method}";
            if (!isset($plugins['callbacks'][$callbackkey])) {
                foreach ($plugins as $delegator => $plugin) {                
                    if ($class == $delegator || $class == __NAMESPACE__ . "\\". $delegator || is_subclass_of($class, $delegator) || is_subclass_of($class, __NAMESPACE__ . "\\". $delegator)) {                    
                        foreach ($plugin as $delegate => $aliases) {
                            if (isset($aliases[$method])) {
                                $delegate_method = $aliases[$method];
                                $callback = array($delegate, $delegate_method);
                                $plugins['callbacks'][$callbackkey] = is_callable($callback) ? $callback : array(__NAMESPACE__ . "\\" . $delegate, $delegate_method);
                                self::$plugins[$type] = $plugins;
                                self::cache();
                                return $plugins['callbacks'][$callbackkey];
                            }
                        }
                    }
                }
                return NULL;
            }
            return $plugins['callbacks'][$callbackkey];
        }
        return NULL;
    }
    
    private static function refreshRegistry() {
        if (empty(self::$plugins)) {
            self::getCache();
        }
    }
}

class DuplicatePluginMethodException extends Exception {

    public function __construct($delegator, $alias) {
        parent::__construct("Duplicate plugin method '{$alias}' for $delegator");
    }
}
?>