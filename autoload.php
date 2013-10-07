<?php
namespace Agilis;

require_once(AGILIS_PATH . 'lib/string.php');
require_once(AGILIS_PATH . 'lib/singleton.php');
require_once(AGILIS_PATH . 'lib/conf.php');
require_once(AGILIS_PATH . 'lib/common.php');
require_once(AGILIS_PATH . 'lib/file_cache.php');

Conf::ifNotDefined('CONF_CACHE_DIR', 'cache/config/');

spl_autoload_register(function ($class) {
    $cache = FileCache::get('autoload');
    $cache = $cache ? unserialize($cache) : NULL;
    $class_uri = NULL;
    if ($cache && isset($cache[$class])) {
        $class_uri = $cache[$class];
    } else {
        $cache = !empty($cache) ? $cache : array();
        if (strstr($class, __NAMESPACE__ . "\\")) {
            list($nspace, $klass) = explode("\\", $class);
            $paths = array(
                AGILIS_PATH . 'framework',
                AGILIS_PATH . 'lib'
            );
            $filename = String::underscore($klass) . '.php';
            foreach ($paths as $path) {
                if (($p = Common::findUri($filename, $path)) !== FALSE) {
                    $class_uri = $p . $filename;
                    break;
                }
            }
        } else {
            $filename = String::underscore($class) . '.php';
            if (($p = Common::findUri($filename, AGILIS_PATH . 'lib')) !== FALSE) {
                $class_uri = $p . $filename;
            }
        }
        if ($class_uri) {
            $cache[$class] = $class_uri;
            FileCache::set('autoload', serialize($cache), APP_ROOT . CONF_CACHE_DIR);
        }
    }
    if ($class_uri) {
        require_once($class_uri);
    }
});
?>