<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

use \ReflectionClass;
/**
 * Most basic framework controller
 *
 * @author Benjie Velarde bvelarde@gmail.com
 * @copyright 2012 BV
 */
abstract class Controller extends Singleton {

    const DIR = 'app/controllers/';

    protected $helper;
    protected $params;
    protected $error;

    protected function init() {
        $class  = get_class($this);
        $helper = str_replace('Controller', 'Helper', $class);
        if (class_exists($helper)) {
            $this->helper = $helper::getInstance();
        }
        $this->params = array();
    }

    public function __call($method, $args) {      
        $action = 'action' . String::camelize($method);
        $cachekey = 'controller-actions';
        $key = md5(serialize($this) . $action . serialize($args));
        $cache = Cache::get($cachekey);
        $callback = array($this, 'action404');
        $found = FALSE;
        if (!isset($cache[$key])) {
            if (method_exists($this, $action)) {
                $callback = array($this, $action);
                $found = TRUE;
            } elseif (($delegate = PluginManager::findPlugin($this, $method)) !== NULL) {
                $args = array_merge(array(&$this), $args);
                $callback = $delegate;
                $found = TRUE;
            } else {
                if ($this->helper && method_exists($this->helper, $method)) {
                    $callback = array($this->helper, $method);
                    $found = TRUE;
                } else {
                    // try to escalate call to ancestor helpers
                    $obj = $this;
                    while (($parent = get_parent_class($obj)) !== FALSE) {
                        $r = new ReflectionClass($parent);
                        if ($r->isAbstract()) {
                            break;
                        }
                        $obj = $parent::getInstance();
                        $parent_helper = $obj->getHelper();
                        if ($parent_helper) {
                            if (method_exists($parent_helper, $method)){
                                $callback = array($parent_helper, $method);
                                $found = TRUE;
                            }
                        }
                    }
                }
            }
            if ($found) {
                $cache[$key]['callback'] = $callback;
                $cache[$key]['args']     = $args;
                Cache::set($cachekey, $cache);
            }
        } else {
            $callback = $cache[$key]['callback'];
            $args     = $cache[$key]['args'];
        }
        return call_user_func_array($callback, $args);
    }

    public static function generate($name, $base='') {
        $name  = new String($name);
        $path  = $name->explode('/');
        $cname = array_pop($path); // remove the last part
        $vpath = VIEWS_PATH . ($path ? implode('/', $path) : '') . "/{$cname}";
        $path  = APP_ROOT . self::DIR . ($path ? implode('/', $path) : '');
        $path = (substr($path, -1) == '/') ? $path : "{$path}/";
        if (!file_exists($path) && !@mkdir($path, 0777, TRUE)) {
            throw new \Exception('failed to create controller path: ' . $path);
        }
        $file  = $path . "{$cname}_controller.php";
        $class = $name->replace('/', '_')->camelize();
        $base  = $base ? String::camelize($base)->to_s : '';
        $contents = "<?php\nuse Agilis\\{$base}Controller;\n\nclass {$class}Controller extends {$base}Controller {\n\n    protected function actionIndex() {\n    }\n\n"
                  . "    protected function actionNew() {\n    }\n\n    protected function actionCreate() {\n    }\n\n"
                  . "    protected function actionShow() {\n    }\n\n    protected function actionEdit() {\n    }\n\n"
                  . "    protected function actionUpdate() {\n    }\n\n    protected function actionDestroy() {\n    }\n\n}\n?>";
        if (file_put_contents($file, $contents)) {
            $views = array('index', 'new', 'show', 'edit');
            foreach ($views as $view) {
                if (file_exists($vpath) || (!file_exists($vpath) && @mkdir($vpath, 0777, TRUE))) {
                    touch($vpath . "/{$view}.phtml");
                }
            }
            return $file;
        }
        return FALSE;
    }

    public function getHelper() { return $this->helper; }

    public function beforeDispatch($params) {
        $this->params = ($params instanceof Params) ? $params->getElements() : ((is_array($params) && CraftyArray::isAssoc($params)) ? $params : array());
    }

    protected function checkRequiredParams() {
        $args = func_get_args();
        if ($args) {
            $missing = array();
            foreach ($args as $param) {
                if (!isset($this->params[$param])) {
                    $missing[] = $param;
                }
            }
            if ($missing) {
                throw new ControllerException($missing);
            }
        }
    }    

    abstract protected function action404();
}
/**
 * Thrown when there are missing required params
 */
class ControllerException extends \Exception {

    public function __construct(array $missing) {
        parent::__construct('Missing params: [' . implode(', ', $missing) . ']');
    }

}
?>