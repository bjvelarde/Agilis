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
 * Framework Configuration Class - Provide a clean/standard way to define all configuration values, be they immutable or variable
 */
final class Conf extends Singleton {

    const VARIABLE = FALSE;
    /**
     * Placeholder of the immutable non-scalar quasi-constant variables
     * @static
     */
    private static $immutables;
    /**
     * Placeholder of the global (mutable) variables
     * @static
     */
    private static $vars;
    /**
     * Allow access to the constants as class properties of a Config instance
     *
     * @return mixed
     */
    public function __get($name) { return self::get($name); }
    /**
     * Allow setting/modification of variable (not-immutable) Config entries
     */
    public function __set($name, $val) { self::set($name, $val, self::VARIABLE); }
    /**
     * Check for required configuration entries.
     *
     * @throw ConfException
     */
    public static function check() {
        $args = func_get_args();
        $missing = array();
        foreach ($args as $arg) {
            if (($val = self::get($arg)) === NULL) {
                $missing[] = $arg;
            }
        }
        if ($missing) {
            throw new ConfException($missing);
        }
    }
    /**
     * Getter of config constants.
     *
     * @param string $name The entry's name.
     * @return mixed
     */
    public static function get($name) {
        $name = strtoupper($name);
        return isset(self::$immutables[$name]) ? self::$immutables[$name] :
               (isset(self::$vars[$name]) ? self::$vars[$name] :
               (defined($name) ? constant($name) : NULL));
    }
    /**
     * Define a configuration entry if not yet defined.
     *
     * @param string $const Name of the config entry
     * @param mixed $value
     */
    public static function ifNotDefined($const, $value) {
        if (Conf::get($const) === NULL) {
            Conf::set($const, $value);
        }
    }
    /**
     * Attempt to fetch config entry from cache.
     *
     * @param string $name The entry's name.
     * @return mixed
     */
    public static function memget($name) {
        $name = strtoupper($name);
        $key = "{conf}-{$name}";
        if (function_exists('fetch')) {
            $val = apc_fetch($key);
        } elseif (isset(self::$immutables['MEMCACHE']) && self::$immutables['MEMCACHE'] instanceof Memcache) {
            $val = self::$immutables['MEMCACHE']->get($key);
        }
        if (!$val) {
            $val = self::get($name);
        }
        return $val;
    }
    /**
     * Setter of configuration entries and attempt to store in cache
     *
     * @param string $name The entry name.
     * @param mixed $val The entry value.
     * @param bool $immutable Set to false if you plan to alter the value later
     */
    public static function memset($name, $val, $immutable=TRUE) {
        $name = strtoupper($name);
        $key = "{conf}-{$name}";
        if (function_exists('apc_store')) {
            apc_store($key, $val, CACHE_TTL);
        } elseif (isset(self::$immutables['MEMCACHE']) && self::$immutables['MEMCACHE'] instanceof Memcache) {
            self::$immutables['MEMCACHE']->set($key, $val, 0, CACHE_TTL);
        } elseif (function_exists('wincache_ucache_set')) {
            wincache_ucache_set($key, $val, CACHE_TTL);
        } else {
            self::set($name, $val, $immutable);
        }
    }
    /**
     * Setter of configuration entries.
     *
     * @param string $name The entry name.
     * @param mixed $val The entry value.
     * @param bool $immutable Set to false if you plan to alter the value later
     */
    public static function set($name, $val, $immutable=TRUE) {
        $name = strtoupper($name);
        if (!defined($name) && !isset(self::$immutables[$name]) && (!$immutable || ($immutable && !isset(self::$vars[$name])))) {
            if (!$immutable) {
                self::$vars[$name] = $val;
            } elseif (is_scalar($val)) {
                define($name, $val);
            } else {
                self::$immutables[$name] = $val;
            }
        }
    }
    
    public static function setvar($name, $val) {
        self::set($name, $val, self::VARIABLE);
    }

}
/**
 * Exception thrown when call to Conf::check() fails.
 */
class ConfException extends \Exception {
    /**
     * Constructor
     *
     * @param array $missing Array of missing required config entries
     */
    public function __construct(array $missing) {
        parent::__construct('Please define the following in your config file: ' . implode(', ', $missing));
    }

}
?>