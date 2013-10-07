<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

class Cache extends Singleton {

    const ANY_STORAGE = 'any',
          MEMCACHE    = 'memcache',
          APC         = 'apc',
          WINCACHE    = 'wincache',
          FILECACHE   = 'filecache';

    public static function set($key, $object, $storage=self::ANY_STORAGE, $ttl=CACHE_TTL, $dir='') {        
        $cache = base64_encode(serialize($object));
        if (($storage == self::ANY_STORAGE || $storage == self::MEMCACHE) &&
            ($memcache = Conf::get('MEMCACHE')) instanceof Memcache) {
            if (!$memcache->replace($key, $cache, 0, $ttl)) {
                $memcache->set($key, $cache, 0, $ttl);
            }
        } elseif (($storage == self::ANY_STORAGE || $storage == self::APC) && function_exists('apc_store')) {
            if (apc_exists($key)) {
                apc_delete($key);
            }
            apc_store($key, $cache, $ttl);
        } elseif (($storage == self::ANY_STORAGE || $storage == self::WINCACHE) && function_exists('wincache_ucache_set')) {
            wincache_ucache_set($key, $cache, $ttl);
        }  else {
            $dir = $dir ? $dir : APP_ROOT . CONF_CACHE_DIR;
            FileCache::set($key, $cache, $dir, $ttl);
        }
    }

    public static function get($key, $storage=self::ANY_STORAGE) {
        $cache = NULL;
        if (($storage == self::ANY_STORAGE || $storage == self::MEMCACHE) &&
            ($memcache = Conf::get('MEMCACHE')) instanceof Memcache) {
            $cache = $memcache->get($key);
        } elseif (($storage == self::ANY_STORAGE || $storage == self::APC) && function_exists('apc_fetch')) {
            $cache = apc_fetch($key);
        } elseif (($storage == self::ANY_STORAGE || $storage == self::WINCACHE) && function_exists('wincache_ucache_get')) {
            $cache = wincache_ucache_get($key);
        } else {
            $cache = FileCache::get($key);
        }
        return $cache ? unserialize(base64_decode($cache)) : NULL;
    }

    public static function clear() {
        try {
            if (($memcache = Conf::get('MEMCACHE')) instanceof Memcache) {
                $memchace->flush();
                echo "memcache cleared...\n";
            } elseif (function_exists('apc_clear_cache')) {
                apc_clear_cache();
                apc_clear_cache('user');
                echo "apc cleared...\n";
            }
            self::clearDir(APP_ROOT . 'cache');
            echo 'DONE!';
        } catch (Exception $e) {
            echo $e->getMessage() . "\n" . $e->getTraceAsString();
        }
    }

    private static function clearDir($folder) {
        $f = new Folder($folder);
        $files = $f->scan();
        if (!empty($files)) {
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $file = "$folder/$file";
                    if (is_dir($file)) {
                        self::clearDir($file);
                        echo "$file cleared...\n";
                    } else {
                        unlink($file);
                        echo "$file deleted...\n";
                    }
                }
            }
        }
    }

}
?>