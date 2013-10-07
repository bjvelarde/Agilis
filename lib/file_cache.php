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

Conf::ifNotDefined('CACHE_TTL', 86400);

class FileCache {
    
    private static $registry;
    
    public static function set($key, $content, $dir='', $ttl=CACHE_TTL) {
        $dir = $dir ? $dir : APP_ROOT . CONF_CACHE_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0755, TRUE)) {
            throw new Exception('Failed to create cache dir: ' . $dir);
        }    
        $cachefile = $dir . md5($key);
        $cachedir  = APP_ROOT . 'cache';
        if (!is_dir($cachedir) && !@mkdir($cachedir, 0755, TRUE)) {
            throw new Exception('Failed to create cache dir: ' . $cachedir);
        }
        $regcachefile = $cachedir . '/' . md5('registry');
        if (!file_exists($cachefile)) { touch($cachefile); }
        file_put_contents($cachefile, $content);
        self::$registry[$key] = array($dir, $ttl);
        if (!file_exists($regcachefile)) { touch($regcachefile); }
        file_put_contents($regcachefile, serialize(self::$registry));
    }
    
    public static function get($key) {
        $registry_cache = APP_ROOT . 'cache/' . md5('registry');
        if (file_exists($registry_cache) && empty(self::$registry)) {
            $registry = file_get_contents($registry_cache);
            if ($registry) {
                self::$registry = unserialize($registry);
            }
        }
        if (isset(self::$registry[$key])) {            
            list($dir, $ttl) = self::$registry[$key];
            $file = $dir . md5($key);
            if (file_exists($file)) {
                $expire_time = filemtime($file) + $ttl;
                if (time() > $expire_time) {
                    unlink($file);
                    return NULL;
                } else {
                    return file_get_contents($file);
                }
            }            
        }
        return NULL;
    }
    
    public static function mtime($key) {
        if (isset(self::$registry[$key])) {
            list($dir, $ttl) = self::$registry[$key];
            $file = $dir . md5($key);
            if (file_exists($file)) {
                return filemtime($file);
            }            
        }
        return 0;    
    }
    
}
?>