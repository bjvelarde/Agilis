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

Conf::check('APP_ROOT', 'TEMPLATE_PATH');
Conf::ifNotDefined('TEMPLATE_EXT', '.phtml');

class Template extends DynaStruct {

    const CACHE_DIR = 'cache/templates/';
    /**
     * @var string The template uri
     */
    protected $file;
    /**
     * @var string The cache key
     */
    protected $cache_key;
    /**
     * @var int TTL in seconds
     */
    protected $ttl;
    /**
     * @var string cache identifier.
     */
    protected $cache_id;
    /**
     * Constructor
     *
     * @param string $file The file name you want to load
     * @param string $path The local template repository
     */
    public function __construct($file, $config=NULL) {
        $config = (isset($config) && is_string($config)) ? Params::path($config) : $config;
        $path = ($config instanceof Params && isset($config->path)) ? $config->path : TEMPLATE_PATH;
        $this->findTemplateFile($file, $path);
        parent::__construct();
        if (isset($config->cache_id)) {
            $url_unique = Common::ifEmpty($config->url_unique, TRUE);
            $user_id    = Common::ifEmpty($config->user_id, ''); // provide only if unique cache per user
            $locale     = Common::ifEmpty($config->locale, '');
            $this->cache_id  = isset($config->cache_id) ? ($url_unique ? $config->cache_id . $_SERVER['REQUEST_URI'] : $config->cache_id) : $_SERVER['REQUEST_URI'];
            $this->cache_id .= $locale;
            $this->cache_id .= $user_id;
            $this->ttl       = Common::ifEmpty($config->ttl, CACHE_TTL);
            $this->cache_key = $file . $this->cache_id;
        }
    }

    public function __call($method, $args) {
        if (substr($method, -5) == '_path' || substr($method, -4) == '_url') {
            return call_user_func_array(array(__NAMESPACE__ . '\Router', $method), $args);
        } elseif (($delegate = PluginManager::findPlugin($this, $method)) !== NULL) {
            //array_unshift($args, $this);
            //var_dump($delegate, $args); exit;
            return call_user_func_array($delegate, $args);
        }
        return parent::__call($method, $args);
    }

    public static function __callStatic($method, $args) {
        if (substr($method, -5) == '_path' || substr($method, -4) == '_url') {            
            return call_user_func_array(array(__NAMESPACE__ . '\Router', $method), $args);
        } elseif (($delegate = PluginManager::findPlugin(__CLASS__, $method)) !== NULL) {
            //array_unshift($args, $this);
            //var_dump($delegate, $args); exit;
            return call_user_func_array($delegate, $args);
        }
        return ''; //parent::__call($method, $args);
    }

    public function __set($var, $val) {
        if (!$this->isCached()) {
            parent::__set($var, $val);
        }
    }
    /**
     * Open, parse, and return the template file.
     *
     * @return string
     */
    protected function fetch() {        
        extract($this->_elements); // Extract the vars to local namespace
        ob_start();
        include($this->file);
        $contents = ob_get_contents();
        ob_end_clean();
        $contents = trim($contents);
        if ($this->cache_id) {
            Cache::set($this->cache_key, $contents, Cache::ANY_STORAGE, CACHE_TTL, APP_ROOT . self::CACHE_DIR, $this->ttl);
        }
        return $contents;
    }
    /**
     * Check if this template is cached
     *
     * @return bool
     */
    public function isCached() {
        if ($this->cache_id) {
            $cache = Cache::get($this->cache_key);
            return !(is_null($cache));
        }
        return FALSE;
    }
    /*
     * Convert the template into string
     */
    public function __toString() {
        try {
            $contents = $this->isCached() ? trim(Cache::get($this->cache_key)) : '';
            return ($contents)? $contents: $this->fetch();
        } catch (\Exception $e) {
            return $e->getTraceAsString() . ' >> ' . $e->getMessage();
        }
    }
    /**
     * Call a function with output buffering
     *
     * @return string
     */
    public static function obCall() {
        ob_start();
        $args   = func_get_args();
        $func   = array_shift($args);
        $output = call_user_func_array($func, $args);
        $output = (!$output) ? ob_get_contents() : $output;
        ob_end_clean();
        return $output;
    }
    /**
     * Get the actual path of the template
     *
     * @param string $file The template file
     * @param string $path The local path to the template
     * @return string
     * @throw TemplateException
     */
    protected function findTemplateFile($file, $path='') {
        $path  = trim($path);
        $lpath = (substr($path, -1) == '/') ? $path : "{$path}/";
        $file .= TEMPLATE_EXT;
        if (file_exists($lpath . $file)) {
            $this->file = $lpath . $file;
        } elseif (file_exists(TEMPLATE_PATH . $file)) {
            $this->file = TEMPLATE_PATH . $file;
        } elseif (file_exists(AGILIS_PATH . 'templates/' . $file)) {
            $this->file = AGILIS_PATH . 'templates/' . $file;
        } else {
            throw new TemplateException($file);
        }
    }
    /**
     * Retrieve the array of variables.
     *
     * @return array
     */
    public function getVars() { return $this->getElements(); }
    /**
     * Retrieve the filename.
     *
     * @return string
     */
    public function getFilename() { return $this->file; }
    /**
     * Load an array of param values.
     *
     * @param Params $params The param values
     */
    public function loadParams(Params $params) {
        foreach ($params as $k => $v) {
            $this->{$k} = $v;
        }
    }
    /**
     * Include a template file
     *
     * @param string $tpl The template file
     * @param Params $params The param values
     */
    public function includeTpl(Template $tpl, Params $params=NULL) {
        if (!$tpl->isCached()) {
            $tpl->loadParams($params);
            $tpl->merge($this);
        }
        echo $tpl;
    }

    public static function partial($partial, $config=array()) {
        $params = new Params(isset($config['params']) ? $config['params'] : array());
        unset($config['params']);
        $p = new Partial($partial, $config);
        $p->loadParams($params);
        echo "$p";
    }

    public static function cell($cell, $cellview, $params=array(), $config=array()) {
        $cell = String::camelize($cell) . 'Cell';
        $method = String::camelize($cellview)->to_s;
        $c = new $cell($params, $config);
        echo $c->{$method}();
    }

    public static function p(&$str) {
        echo (isset($str) ? $str : '');
    }

    //public static function t($str) { return Translator::t($str); }	

}
/**
 * Thrown if template file is missing
 */
class TemplateException extends \Exception {

    public function __construct($file) {
        parent::__construct("Missing template file: $file");
    }
}
?>