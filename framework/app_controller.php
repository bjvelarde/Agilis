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

Conf::check('VIEWS_PATH', 'TEMPLATE_PATH');

class AppController extends Controller {

    protected $_cache_view;

    protected $vars;
    protected $_view_path;
    protected $_page_tpl;
    protected $_layout_tpl;
    protected $_page;
    protected $_layout;
    protected $_assetsmgr;
    protected $_meta_data = array();
    protected $_page_cache;
    protected $_cache_key;

    protected $flash = array();

    protected function init() {
        $this->vars = new Params;
        if (!isset($this->_view_path)) {
            $class = String::tolower(get_class($this))->substr(0, -10)->to_s;
            $this->_view_path = (file_exists(VIEWS_PATH . $class)) ? VIEWS_PATH . $class . '/' : TEMPLATE_PATH;
        }
        if (!isset($this->_layout_tpl)) {
            $this->_layout_tpl = 'default';
        }
        $this->_layout = new Template($this->_layout_tpl, TEMPLATE_PATH . 'layouts/');
        $this->_cache_view = TRUE;
        parent::init();
    }

    public function __set($var, $val) { $this->vars[$var] = $val; }

    public function __get($var) { return isset($this->vars[$var]) ? $this->vars[$var] : NULL; }

    public function beforeDispatch($params) {
        parent::beforeDispatch($params);
        $this->getPageCache();
        if ($this->_page_cache === NULL) { // || !$this->_assetsmgr || !($this->_assetsmgr instanceof AssetsManager)
            $this->_assetsmgr = new AssetsManager($this->params['controller'], $this->params['action']);
        }
    }

    public function __call($method, $args) {
        if ($this->_cache_key !== NULL) {
            if ($this->_page_cache === NULL) {
                ob_start();
                parent::__call($method, $args);
                $contents = ob_get_contents();
                Cache::set(
                    $this->_cache_key,
                    $contents,
                    Cache::ANY_STORAGE,
                    CACHE_TTL,
                    APP_ROOT . Template::CACHE_DIR
                );
                ob_end_clean();
                echo $contents;
            } else {
                echo $this->_page_cache;
            }
        } else {
            return parent::__call($method, $args);
        }
    }

    public function flash($type, $message) {
        $this->flash[] = array($type => $message);
        Session::set($this->getFlashSessionKey(), $this->flash);
    }

    protected function getFlashMessages() {
        $from_session = Session::get($this->getFlashSessionKey());
        return is_array($from_session) ? $from_session : $this->flash;
    }

    protected function getFlashSessionKey() {
        $flash_session_key = Conf::get('FLASH_SESSION_KEY');
        return ($flash_session_key) ? $flash_session_key : 'flash-messages';
    }

    protected function clearFlash() {
        $this->flash = array();
        Session::unregister($this->getFlashSessionKey());
        echo 'Flash messages cleared';
    }

    protected function render($viewname='') {
        $cachekey = 'controller-views';
        $key = md5(serialize($this) . $viewname);
        $cache = $this->_cache_view ? Cache::get($cachekey) : array();
        $rejam = (isset($_GET['jam']) && strtolower($_GET['jam']) == 'true');        
        if (!isset($cache[$key]) || $rejam) {
            $page_title = '';
            $vpath = $this->_view_path;
            $backtrace = debug_backtrace();
            $ctrlpath = str_replace("\\", '/', dirname($backtrace[0]['file']));
            $root_ctrlpath = str_replace("\\", '/', APP_ROOT . Controller::DIR);
            $root_ctrlpath = substr($root_ctrlpath, -1) == '/' ? substr($root_ctrlpath, 0, -1) : $root_ctrlpath;
            if (substr($ctrlpath, 0, strlen($root_ctrlpath)) == $root_ctrlpath) {  //strlen($ctrlpath) >= strlen($root_ctrlpath)
                $info = pathinfo($backtrace[0]['file']);
                $file_name = substr(basename($backtrace[0]['file'], '.' . $info['extension']), 0, -11);
                $vpath = VIEWS_PATH . str_replace($root_ctrlpath, '', $ctrlpath) . "/{$file_name}";
            }
            if (empty($viewname)) {
                $bt        = $backtrace[5];
                $template  = $bt['function'];
                if (!isset($this->_layout->title)) {
                    $class = String::ucfirst($bt['class'])->substr(0, -10)->to_s;
                    $page_title = "{$class} > " . String::titleize($template);
                }
            } else {
                $template = $viewname;
            }
            $this->_layout->flash = $this->getFlashMessages(); //$this->flash;
            $this->_layout->view = new Template($template, $vpath);
            $this->_layout->loadParams($this->vars); // load them up for layout for convenience
            $this->_layout->view->loadParams($this->vars);
            $this->_layout->title = $page_title ? $page_title : 'Home';
            $this->loadMetaData();
            //$this->loadAssets($rejam);
            $cache[$key] = $this->_layout;
            Cache::set($cachekey, $cache);
        } else {
            $this->_layout = $cache[$key];
        }
        $this->loadAssets($rejam);
        header('Expires: Sat, 26 Jun 1972 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', FALSE);
        header('Pragma: no-cache');
        echo $this->_layout; //$cache[$key];
        $this->clearFlash();
    }

    protected function redirect($action) {
        if (is_array($action)) {
            $route = $action['route'];
            $args  = isset($action['args']) ? $action['args'] : array();
            $url   = call_user_func_array(array(__NAMESPACE__ . '\Router', "{$route}_url"), $args);
            if (isset($action['protocol']) && $action['protocol'] === 'https') {
                $url = str_replace('http', 'https', $url);
            }
            header("Location: $url");
        } else {
            $rejam = (isset($_GET['jam']) && strtolower($_GET['jam']) == 'true');
            $root_route = Router::getRoot();
            if ($root_route->countroller == $this->params['controller'] && $root_route->action == $action) {
                $this->params['path_name'] = 'root';
            } else {
                $this->params['path_name'] = Router::getPathName($this->params['controller'], $action);
            }
            $this->path_name = $this->params['path_name'];
            $this->_assetsmgr = new AssetsManager($this->params['controller'], $action);
            $this->loadAssets($rejam);
            return $this->{$action}();
        }
    }

    protected function action404() {
        $this->_view_path = TEMPLATE_PATH;
        $this->render();
    }

    protected function setPageTitle($title) { $this->_layout->title = $title; }

    protected function setLayout($layout, $path='') {
        $path = $path ? $path : TEMPLATE_PATH . 'layouts/';
        $this->_layout_tpl = $layout;
        $this->_layout = new Template($this->_layout_tpl, $path);
    }

    protected function setLayoutVars(Params $params) { $this->_layout->loadParams($params); }

    protected function addMetaData($name, $content, $type='name') {
        if ($type !== NULL) { $type = ($type == 'name') ? $type : 'http-equiv'; }
        if ($type == 'name' && ($name == 'keywords' || $name == 'description') && isset($this->meta_data[$type][$name])) {
            $this->_meta_data[$type][$name] .= ",{$content}";
        } elseif ($type) {
            $this->_meta_data[$type][$name] = $content;
        } else {
            $this->_meta_data[$name] = $content;
        }
    }

    public function addMetaKeywords($content) { $this->addMetaData('keywords', $content); }

    public function addMetaDescription($content) { $this->addMetaData('description', $content); }

    public function addScript($uri, $path='', $type='text/javascript', $charset='default') {
        $this->_assetsmgr->addScript($uri, $path, $type, $charset);
    }

    public function addInlineScript($script, $type='text/javascript') {
        $this->_assetsmgr->addInlineScript($script, $type);
    }

    public function addCss($uri, $path='', $media='screen') {
        $this->_assetsmgr->addCss($uri, $path, $media);
    }

    public function addInlineCss($css_codes) {
        $this->_assetsmgr->addInlineCss($css_codes);
    }

    public function setMetaCharSet($charset) {
        $this->addMetaData('Content-Type', "text/html; charset={$charset}", 'http-equiv');
    }

    public function setShortCutIcon($uri) {
        $link = new Partial('link_tag');
        $link->rel  = 'shortcut icon';
        $link->href = $uri;
        $this->_layout->shortcut_icon = "{$link}\n";
    }

    public function t($word) { return Translator::t($word); }

    protected function csrfCheck() {
        $csrfkey = Conf::get('CSRF_SESSION_KEY');
        $csrfkey = $csrfkey ? $csrfkey : 'csrf';
        $csrf = Session::get($csrfkey);
        Session::unregister($csrfkey);
        return ((!isset($this->params['csrf']) && !$csrf) || (isset($this->params['csrf']) && $this->params['csrf'] == $csrf));
    }

    private function loadMetaData() {
        $cache_key = 'page-metatags';
        $development = (Conf::get('CURRENT_ENV') == 'development');
        // for development, ignore cache and always process the yml
        $metatags = $development ? '' : Cache::get($cache_key);
        if (!$metatags) {
            $yaml_file = APP_ROOT . 'config/metadata.yml';
            if (file_exists($yaml_file)) {
                $metadata = Spyc::YAMLLoad($yaml_file);
                foreach ($metadata as $type => $pair) {
                    if (is_array($pair)) {
                        list($k, $v) = each($pair);
                        $this->addMetaData($k, $v, $type);
                    } else {
                        $this->addMetaData($type, $pair, NULL);
                    }
                }
            }
            $metatags = $this->getMetaTags();
            if (!$development) {
                Cache::set($cache_key, $metatags, Cache::ANY_STORAGE, CACHE_TTL, Template::CACHE_DIR);
            }
        }
        $this->_layout->meta_tags = !empty($metatags) ? "{$metatags}\n" : '';
    }

    private function loadAssets($rejam=FALSE) {
        $this->_assetsmgr->loadAssets($rejam);
        $this->_layout->css = $this->_assetsmgr->css_tags;
        $this->_layout->scripts = $this->_assetsmgr->script_tags;
        $this->_layout->inline_scripts = $this->_assetsmgr->inline_script_tags;
    }

    private function getMetaTags() {
        $meta_tags = '';
        foreach ($this->_meta_data as $type => $metas) {
            $tag = new Partial('meta_tag');
            if (is_array($metas)) {
                foreach ($metas as $name => $content) {
                    $tag->type    = $type;
                    $tag->name    = $name;
                    $tag->content = $content;
                }
            } else {
                $tag->name    = $type;
                $tag->content = $metas;
            }
            $meta_tags .= "\n{$tag}";
        }
        return $meta_tags;
    }

    private function getPageCache() {
        $cache = NULL;
        $cache_key = NULL;
        $yml_file = APP_ROOT . 'config/page-caching.yml';
        if (file_exists($yml_file)) {
            $config = \Spyc::YAMLLoad($yml_file);
            $controller = $this->params['controller'];
            $action = $this->params['action'];
            $namespace = NULL;
            if (strstr($controller, '/')) {
                $parts = explode('/', $controller);
                $namespace = $parts[0] . '/';
            }
            if ((isset($config[$controller]) && in_array($action, $config[$controller])) ||
               ($namespace !== NULL && isset($config[$namespace]) && in_array($action, $config[$namespace]))) {
                $cache_key = md5(json_encode($this->params));
                $cache = Cache::get($cache_key);
            }
        }
        $this->_page_cache = $cache;
        $this->_cache_key  = $cache_key;
    }
}
?>