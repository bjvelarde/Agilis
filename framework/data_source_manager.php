<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;
use \Spyc;

Conf::ifNotDefined('CONF_CACHE_DIR', 'cache/config/');

class DataSourceManager extends Singleton {

    private static $registry;
    private static $connections;
    
	public static function addSource($alias, DataSourceConfig $config, $env='') {
        $env = $env ? $env : Conf::get('CURRENT_ENV');
	    self::$connections[$env][$alias] = $config;
	}  

    public static function configure() {
        $key      = 'dbconfig';
        $yml_file = APP_ROOT . 'config/database.yml';
        $cache    = FileCache::get($key);
        $mtime    = FileCache::mtime($key);
        if (!$cache || ($cache && filemtime($yml_file) > $mtime)) {
            $config = Spyc::YAMLLoad($yml_file);
            foreach ($config as $env => $connections) {
                foreach ($connections as $alias => $params) {
                    $cfgclass = __NAMESPACE__ . "\\" . String::camelize($params['engine']) . 'Config';
                    $datasrc  = new $cfgclass(new Params($params));
                    self::addSource($alias, $datasrc, $env);
                }
            }
            $cache = base64_encode(serialize(self::$connections));
            FileCache::set($key, $cache, APP_ROOT . CONF_CACHE_DIR);
        } else {
            self::$connections = unserialize(base64_decode($cache));
        }
    } 

    public static function close($alias='master', $env='') {
        $env = $env ? $env : Conf::get('CURRENT_ENV');
        $alias = $alias ? $alias : 'master';
        if (is_resource(self::$registry[$env][$alias])) {
            self::$registry[$env][$alias]->close();
        }
    }    
    
    public static function connect($alias='master', $env='') {
        $env = $env ? $env : Conf::get('CURRENT_ENV');
        $alias = $alias ? $alias : 'master';
        //echo '<pre>'; var_dump(self::$connections, $env, $alias);
        $config = self::$connections[$env][$alias];
		$class  = __NAMESPACE__ . "\\" . $config->_class;
        if (!isset(self::$registry[$env][$alias]) || 
           (isset(self::$registry[$env][$alias]) && 
           !(self::$registry[$env][$alias] instanceof $class))) {
            self::$registry[$env][$alias] = new $class($config);
        }
        return self::$registry[$env][$alias];
    }
    
	public static function getSource($alias='master', $env='') {
        $env = $env ? $env : Conf::get('CURRENT_ENV');
	    return isset(self::$connections[$env][$alias]) ? self::$connections[$env][$alias] : NULL;
	}
    
    public static function getSourceAliases($env='') {
        $env = $env ? $env : Conf::get('CURRENT_ENV');
        return array_keys(self::$connections[$env]);
    }

}
?>
