<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

Conf::ifNotDefined('ACTION_404', '404');

final class Router extends Singleton {

    private $path;
    private $method;
    private $root;

    public static $routes = array();
    private static $registry = array();

    protected function init() {
        $server = ServerVars::getInstance();
        $this->path = '';
        $sef_str = $server->path_info ? $server->path_info : $server->query_string;
        if (strlen($sef_str) > 1) {
            $server->php_self = str_replace($sef_str, '', $server->php_self);
            $this->path = urldecode($sef_str);
        }
        $method = isset($_POST['method']) ? strtolower($_POST['method']) :
                 (isset($_GET['method']) ? strtolower($_GET['method']) : 'get');
        if (!empty($_POST)) {
            $this->method = ($method == 'put') ? 'put' : (($method == 'delete') ? 'delete' : 'post');
        } else {
            $this->method = ($method == 'delete') ? 'delete' : 'get';
        }
        $this->root = Params::controller('home')->action('index')->controller_class('HomeController');
    }

    public static function __callStatic($method, $args) {
        $route_type = (substr($method, -5) == '_path') ? 'path' : ((substr($method, -4) == '_url') ? 'url' : NULL);
        if ($route_type) {
            $key = md5($route_type . serialize($args));
            $cachekey = 'RoutesRegistry';
            $cache = Cache::get($cachekey);
            if (!$cache || ($cache && !isset($cache[$method][$key]))) {
                if ($route_type == 'path') {
                    $route_name = substr($method, 0, -5);                    
                    if (self::getRoute($route_name, $args)) {
                        $cache[$method][$key] = self::path_of($route_name, $args);
                    } elseif (preg_match('/^(new_|edit_|)([a-z0-9_]+)_path$/', $method, $matches)) {
                        $action = ($matches[1] != '') ? substr($matches[1], 0, -1) : $matches[1];
                        $model  = $args ? $args[0] : NULL;
                        $cache[$method][$key] = self::objectPath($matches[2], $action, $model);
                    }
                } else {
                    $route = substr($method, 0, -4);
                    if ($route == 'root') {
                        $cache[$method][$key] = self::getRootUrl();
                    } else {
                        $path   = self::__callStatic($route . '_path', $args);
                        if ($path) {
                            if ($server_alias = Conf::get('SERVER_ALIAS')) {
                                $path = substr($path, strlen('/' . $server_alias));
                            }
                            $cache[$method][$key] = self::getRootUrl() . $path;
                        }
                    }
                }
                if (isset($cache[$method][$key])) {
                    Cache::set($cachekey, $cache);
                }
            }
            return isset($cache[$method][$key]) ? $cache[$method][$key] : '';
        }
    }


    public function __get($var) { if ($var == 'root' || $var == 'method') { return $this->{$var}; } }

    public function route() {
        if ($this->path) {
            $cachekey = md5('route-' . $this->path . $this->method . serialize($_REQUEST));
            $params  = Cache::get($cachekey);
            if (!$params) {
                $params = $this->getParams();
                Cache::set($cachekey, $params);
            }
            return $params;
        } else {
            return $this->root;
        }
    }

    public function setRoot($route) {
        if (strstr($route, '#')) {
            list($controller, $action) = explode('#', $route);
            $this->root = $this->root->controller($controller)->action($action);
        }
    }

    public static function addRoute($name, $pattern, $options=array()) {
        $options['methods'] = isset($options['methods']) ?
                              (is_array($options['methods']) ? $options['methods'] : array($options['methods'])) :
                              array('get');
        $options['action'] = isset($options['action']) ? $options['action'] : '?';
        if (!CraftyArray::isAssoc($options['methods'])) {
            $methods = array();
            foreach ($options['methods'] as $m) {
                $methods[$m] = $options['action'];
            }
            unset($options['action']);
            $options['methods'] = $methods;
        }
        $path_name = substr($name, -5) == '-sing' ? substr($name, 0, -5) : $name;
        self::$routes[$name] = array_merge(
            array('pattern' => $pattern, 'path_name' => $path_name),
            $options
        );
    }

    public static function build() {
        $args = func_get_args();
        if (!empty($args)) {
            foreach ($args as $rb) {
                if ($rb instanceof RouteBuilder) {
                    list($restful, $members) = $rb->getRoutes();
                    foreach ($restful as $route) {
                        $name = $route[0];
                        $name = $name{0} == '/' ? substr($name, 1) : $name;
                        self::buildResources($name, $route[1], $route[2]);
                    }
                    if ($members) {
                        foreach ($members as $k => $m) {
                            foreach ($m as $member) {
                                $methods = array();
                                $paths = explode('/', $member[1]);
                                while(1) {
                                    $action = array_pop($paths);
                                    if ($action{0} != ':') break;
                                }
                                foreach ($member[2] as $m) {
                                    $methods[$m] = $action;
                                }
                                $controller = ($k{0} == '/' ? substr($k, 1) : $k);
                                self::addRoute($member[0], $member[1], array(
                                    'controller' => $controller,
                                    'methods'    => $methods
                                ));
                            }
                        }
                    }
                }
            }
        }
    }

    // use for controller action redirection
    public static function getPathName($controller, $action) {
        if (isset(self::$routes[$action]) && self::$routes[$action]['controller'] == $controller &&
            isset(self::$routes[$action]['methods']['get']) && self::$routes[$action]['methods']['get'] == $action) {
            return self::$routes[$action]['path_name'];
        } else {
            foreach (self::$routes as $route => $info) {
                if ($info['controller'] == $controller && isset($info['controller']['methods']['get']) && $info['controller']['methods']['get'] == $action) {
                    return $info['path_name'];
                }
            }
        }
        return 'root';
    }

    public static function getRoot() {
        $instance = self::getInstance();
        return $instance->root;
    }

    public static function getRoute($name='default', $args=NULL) {
        if (isset(self::$routes[$name]) || isset(self::$routes["{$name}-sing"])) {
            if (!isset(self::$routes["{$name}-sing"])) { // || !$args
                return self::$routes[$name];
            } else {
                $keys = self::getKeys(self::$routes["{$name}-sing"]['pattern']);
                return (count($keys) == count($args)) ? self::$routes["{$name}-sing"] : self::$routes[$name];
            }
        }
        return NULL;
    }

    public static function getRouteArgs($route) {
        $info = self::getRoute($route);
        return self::getKeys($info['pattern']);
    }

    public static function match($pattern, $options=array()) {
        $opt  = array();
        $name = isset($options['as']) ? $options['as'] : uniqid('route_');
        $opt['methods'] = isset($options['via']) ? (is_array($options['via']) ? $options['via'] : array($options['via'])) : array('get');
        if (isset($options['route']) && strstr($options['route'], '#')) {
            list($opt['controller'], $opt['action']) = explode('#', $options['route']);
        } else {
            if (isset($options['controller'])) {
                $opt['controller'] = $options['controller'];
            }
            if (isset($options['action'])) {
                $opt['action'] = $options['action'];
            }
        }
        self::addRoute($name, $pattern, $opt);
    }

    public static function member($member, $options=array()) { return new RouteMember($member, $options); }

    public static function collection($member, $options=array()) {
        return new RouteMember($member, array_merge($options, array('type' => 'plural')));
    }

    public static function indexLink($model) {
        if ($model instanceof Model) {
            $model_name = $model->getTableName();
            $url = "{$model_name}_url";
            return self::$url();
        } elseif (is_array($model)) {
            $m1    = array_pop($model);
            $paths = $params = array();
            foreach ($model as $m) {
                if ($m instanceof Model) {
                    if (!$m->_persisted) {
                        throw new RouterException('Non-persistent parent');
                    }
                    $m_id     = $m->getId();
                    $table    = $m->getTableName();
                    $paths[]  = $table;
                    $params[] = $m_id;
                    $field    = String::singularize($table) . '_id';
                    if (!$m1->_persisted) {
                        $m1[$field] = $m_id;
                    }
                } else {
                    $paths[] = $m;
                }
            }
            $paths[] = $m1->getTableName();
            $paths[] = 'url';
            $paths = implode('_', $paths);
            return call_user_func_array(array(__CLASS__, $paths), $params);
        }
        return $model;
    }

    public static function path_for($model, $full_url=FALSE) {
        $last_str = $full_url ? 'url' : 'path';
        if ($model instanceof Model) {
            if ($model->_persisted) {
                $model_name = String::singularize($model->getTableName());
                $url = "{$model_name}_{$last_str}";
                return self::$url($model);
            } else {
                $model_name = $model->getTableName();
                $url = "{$model_name}_{$last_str}";
                return self::$url();
            }
        } elseif (is_array($model)) {
            $m1    = array_pop($model);
            $paths = $params = array();
            foreach ($model as $m) {
                if ($m instanceof Model) {
                    if (!$m->_persisted) {
                        throw new RouterException('Non-persistent parent');
                    }
                    $m_id     = $m->getId();
                    $table_sing = String::singularize($m->getTableName())->to_s;
                    $paths[]  = $table_sing;
                    $params[] = $m_id;
                    $field    = $table_sing . '_id';
                    if (!$m1->_persisted) {
                        $m1[$field] = $m_id;
                    }
                } else {
                    $paths[] = $m;
                }
            }
            $paths[] = $m1->_persisted ? String::singularize($m1->getTableName()) : $m1->getTableName();
            $paths[] = $last_str;
            if ($m1->_persisted) { $params[] = $m1; }
            $paths = implode('_', $paths);
            return call_user_func_array(array(__CLASS__, $paths), $params);
        }
        return $model;
    }

    public static function url_for($model) { return self::path_for($model, TRUE); }

    public static function name_space($name) { return new RouteNamespace($name); }

    public static function path_of($route_name, $args) {
        $route_info    = Common::ifEmpty(Router::getRoute($route_name, $args), Router::getRoute());
        $pattern       = $route_info['pattern'];
        $num_reqd      = self::getNumRequiredParams($pattern);
        $num_reqd_wild = self::getNumRequiredWildCardArgs($pattern);
        $keys          = self::getKeys($pattern);
        list($wilds, $args1) = self::getWildCardArgs($args);
        $num_args = count($args1);
        $num_wild = count($wilds);
        $server_alias = Conf::get('SERVER_ALIAS');
        $server_alias = $server_alias ? "/{$server_alias}" : '';
        if ($num_args >= $num_reqd && $num_args <= count($keys) && $num_wild >= $num_reqd_wild) {
            for ($i = 0; $i < $num_args; $i++) {
                $param = ($args[$i] instanceof Model) ? $args[$i]->getId() : $args[$i];
                $pattern = str_replace($keys[$i], $param, $pattern);
            }
            if ($wilds) {
                for ($i = 0; $i < $num_wild; $i++) {
                    list($wildname, $wildvals) = each($wilds[$i]);
                    $wildcard = String::replace(' ', '-', implode('/', $wildvals))->urlencode();
                    $pattern  = str_replace('*' .  $wildname, $wildcard, $pattern);
                }
            }
            if (preg_match('/\*[a-z0-9_\-]+/', $pattern)) {
                throw new RouterException('Too few wildcard data for route: ' . $route_name);
            }
            if (preg_match('/^(.*)(\([:\/a-z0-9_\-]+\))$/', $pattern, $matches)) {

                $pattern = $matches[1];
                $options = $matches[2];
				if (substr($options, 0, 3) == '(/:') {
				    $options = '';
				} else {
                    $options = str_replace('(', '', $options);
                    $options = str_replace(')', '', $options);
                    $options = preg_replace('/(([a-z0-9_\/]+\/)?:[a-z0-9_\/]+)+$/', '', $options);
				}
                $pattern .= $options;
                return $server_alias . $pattern;
            } else {
                $pattern = str_replace('(', '', $pattern);
                $pattern = str_replace(')', '', $pattern);
                return $server_alias . preg_replace('/(\/:[a-z0-9_\/]+)+$/', '', $pattern); //remove optional params with no args
            }
        } else {
            throw new RouterException('Too few or too many arguments supplied to route: ' . $route_name);
        }
    }

    public static function resources($model, $actions=array()) { return new RouteResources($model, $actions); }

    public static function resource($model, $actions=array()) {
        $resource = new RouteResources($model, $actions);
        $resource->singular = TRUE;
        return $resource;
    }

    public static function root_to($route) {
        $self = self::getInstance();
        $self->setRoot($route);
    }

    public static function routes(array $routes) {
        foreach ($routes as $key => $route) {
            if (is_array($route)) {
                $pattern = array_shift($route);
                $options = $route;
            } else {
                $options = array();
                $pattern = $route;
            }
            self::addRoute($key, $pattern, $options);
        }
    }

    private static function buildResources($model_name, $pattern, $actions=array()) {
        $namespaces = '';
        $singular = FALSE;
        if (substr($model_name, -5) == '-sing') {
            $singular = TRUE;
        }
        $route_key = $model_name;
        $controller = str_replace('|', '_', $model_name);
        $controller = $singular ? substr($controller, 0, -5) : $controller;
        if (strstr($route_key, '/')) {
            $split = explode('/', $route_key);
            $route_key = array_pop($split);
            $namespaces = implode('_', $split);
        }
        if (strstr($route_key, '|')) {
            $split = explode('|', $route_key);
            $route_key = array_pop($split);
            for ($i = 0; $i < count($split); $i++) {
                $split[$i] = String::is_singular($split[$i]) ? $split[$i] : String::singularize($split[$i])->to_s;
            }
            $split[] = $route_key;
            $route_key = implode('_', $split);
        }
        if ($namespaces) {
            $route_key = $namespaces . "_{$route_key}";
        }
        $singular_name = String::singularize($route_key)->to_s;
        if (!$singular && (empty($actions) || in_array('index', $actions) || in_array('create', $actions))) {
            if (empty($actions)) {
                $methods = array('get' => 'index', 'post' => 'create');
            } else {
                $methods = array();
                if (in_array('index', $actions)) {
                    $methods['get'] = 'index';
                }
                if (in_array('new', $actions)) {
                    $methods['post'] = 'create';
                }
            }
            self::addRoute($route_key, "{$pattern}(/page/:page)", array(
                'controller' => $controller,
                'methods'    => $methods
            ));
        }
        if (empty($actions) || in_array('new', $actions)) {
            self::addRoute('new_' . $singular_name, $pattern . '/new', array(
                'controller' => $controller,
                'methods'    => array('get' => 'new')
            ));
        }
        if (empty($actions) || in_array('edit', $actions)) {
            self::addRoute('edit_' . $singular_name, $pattern . '/' . ($singular ? '': ':id/'). 'edit', array(
                'controller' => $controller,
                'methods'    => array('get' => 'edit')
            ));
        }
        if (empty($actions) || in_array('show', $actions) || in_array('new', $actions) || in_array('edit', $actions) || in_array('delete', $actions)) {
            if (empty($actions)) {
                $methods = array('get' => 'show', 'put' => 'update', 'delete' => 'destroy');
                if ($singular) {
                    $methods['post'] = 'create';
                }
            } else {
                $methods = array();
                if (in_array('show', $actions)) {
                    $methods['get'] = 'show';
                }
                if (in_array('edit', $actions)) {
                    $methods['put'] = 'update';
                }
                if (in_array('destroy', $actions)) {
                    $methods['delete'] = 'destroy';
                }
                if ($singular && in_array('new', $actions)) {
                    $methods['post'] = 'create';
                }
            }
            self::addRoute($singular_name, $pattern . ($singular ? '': '/:id'), array(
                'controller' => $controller,
                'methods' => $methods
            ));
        }
    }

    private static function getKeys($pattern) {
        preg_match_all('/:[a-z0-9_\-]+/', $pattern, $matches);
        return $matches ? array_shift($matches) : NULL;
    }

    private static function getNumRequiredParams($pattern) {
        $pattern = preg_replace('/\(.+\)/', '', $pattern);
        preg_match_all('/\/:[a-z0-9_\-]+/', $pattern, $matches);
        return count(array_shift($matches));
    }

    private static function getNumRequiredWildCardArgs($pattern) {
        $pattern = preg_replace('/\(.+\)/', '', $pattern);
        preg_match_all('/\/\*[a-z0-9_\-]+/', $pattern, $matches);
        return $matches ? count(array_shift($matches)) : 0;
    }

    private static function objectPath($model_name, $action='', $model=NULL) {
        $original   = $model_name;
        $model_name = String::pluralize($model_name)->to_s;
        $server_alias = Conf::get('SERVER_ALIAS');
        $server_alias = $server_alias ? "/{$server_alias}/" : '/';
        if ($original != $model_name) {
            if ($action == 'new') {
                return $server_alias . $model_name . '/new';
            } else {
                if (!$model) {
                    throw new RouterException('Too few parameters');
                }
                if ($model instanceof Model) {
                    $id = $model->getId();
                } else {
                    throw new RouterException('Invalid argument');
                }
                $real_model_name = String::pluralize(get_class($model))->underscore()->to_s;
                if ($real_model_name != $model_name) {
                    throw new RouterException('Invalid model: ' . $real_model_name . ' <> ' . $model_name);
                }

                $path = $server_alias . $model_name . '/' . $id;
                if ($action == 'edit') {
                    $path .= '/edit';
                }
                return $path;
            }
        } else {
            return $server_alias . $model_name;
        }
    }

    private function getParams() {
        $params = new Params;
        $exact_match =
        $best_matches = array();
        if ($_SERVER['PATH_INFO']) {
            foreach (self::$routes as $k => $routeinfo) {
                $route = $routeinfo['pattern'];
                //prioritize exact match
                if (!$exact_match && $route == $_SERVER['PATH_INFO']) {
                    $exact_match = array($k, $routeinfo);                    
                } else { //if (!$best_match)
                    $constraints = isset($routeinfo['constraints']) ? $routeinfo['constraints'] : array();
                    if (($keys = self::getKeys($route)) !== NULL) {
                        if (($matches = $this->matchPath($route, $constraints)) !== FALSE) {
                            $matched_params = new Params;
                            $matched_params->path_name($routeinfo['path_name']);
                            if (isset($routeinfo['controller'])) {
                                $matched_params->controller($routeinfo['controller']);
                            }
                            if (isset($routeinfo['methods'][$this->method]) && $routeinfo['methods'][$this->method] != '?') {
                                $matched_params->action($routeinfo['methods'][$this->method]);
                            }
                            if (!empty($keys)) {
                                $index = 0;
                                foreach ($matches as $match) {
                                    $varkey = $keys[$index];
                                    $varkey = $varkey == ':action' ? 'action' : $varkey;
                                    if (!strstr($match, '/')) {
                                        if (!isset($matched_params[$varkey])) {
                                            $matched_params[$varkey] = $match;
                                        }
                                        $index++;
                                    }
                                }
                            }
                            $matched_params->if_empty_controller($k);
                            $best_matches[] = array($routeinfo, $matched_params);
                        }
                    }
                }
            }
            if ($exact_match) {
                list($name, $em) = $exact_match;
                $params->path_name($em['path_name']);
                if (isset($em['controller'])) {
                    $params->controller($em['controller']);
                }
                if (isset($em['methods'][$this->method]) && $em['methods'][$this->method] != '?') {
                    $params->action($em['methods'][$this->method]);
                }
                $params->if_empty_controller($name);
            } elseif ($best_matches) {
                $path_parts = explode('/', $this->path);
                $hi_score = 0;
                $hi_score_index = 0;
                for ($i = 0; $i < count($best_matches); $i++) {
                    $patt_parts = explode('/', $best_matches[$i][0]['pattern']);
                    $score = 0;
                    for ($j = 0; $j < count($patt_parts); $j++) {
                        $part = $patt_parts[$j];
                        if ($part !== '' && $part{0} !== ':') {
                            if (isset($path_parts[$j]) && $part === $path_parts[$j]) {
                                $score++;
                            }
                        }
                    }
                    if ($score >= $hi_score) {
                        $hi_score = $score;
                        $hi_score_index = $i;
                    }
                }
                $params = $best_matches[$hi_score_index][1];
            }
        }
        // see if there are query strings
        if (!empty($_GET)) {
            foreach ($_GET as $k => $v) {
                if (!isset($params[$k])) {
                    $params[$k] = $v;
                }
            }
        }
        $params->if_empty_action(Conf::get('ACTION_404'));
        $params->if_empty_controller($this->root->controller);
        return $this->addApplicationParams($params);
    }

    private function addApplicationParams(Params $params) {
        if (strstr($params->controller, '/')) {
            $split = explode('/', $params->controller);
            $controller = array_pop($split);
            $cpath = implode('/', $split);
            $controller = str_replace('/', '_', "{$cpath}/{$controller}");
        } else {
            $controller = $params->controller;
        }
        $params->controller_class = String::camelize($controller) . 'Controller';
        if ($_REQUEST) {
            foreach ($_REQUEST as $k => $v) {
                $params->{$k} = $v;
            }
        }
        return $params;
    }

    private static function getRootUrl() {
        $root = Conf::get('APP_URL');
        //return substr($root, -1) == '/' ? substr($root, 0, -1) : $root;
        return substr($root, -1) == '/' ? substr($root, 0, -1) : $root;
        //return "{$root}/index.php";
    }

    private static function getWildCardArgs($args) {
        $wca = $newargs = array();
        foreach ($args as $arg) {
            if (is_array($arg)) {
                $wca[] = $arg;
            } else {
               $newargs[] = $arg;
            }
        }
        return array($wca, $newargs);
    }

    private function matchPath($pattern, $constraints) {
        preg_match_all(self::pattern2regxp($pattern, $constraints), $this->path, $matches, PREG_SET_ORDER);
        return $matches ? array_shift($matches) : FALSE;
    }

    private static function pattern2regxp($pattern, $constraints) {
        $p = preg_replace('/\/\*[a-z0-9_\-]+/', '/[/a-z0-9_\-]+', $pattern);
        do { // optional params
            $p = preg_replace('/\(\/([\/:a-z0-9_\-\{\}\?]+)\)/i', '{/$1}?', $p);
        } while (preg_match('/\(\/([\/:a-z0-9_\-\{\}\?]+)\)/i', $p));
        do { // optional patterns
            $p = preg_replace('/\(\/([\/a-z0-9_\-\{\}\?]*)\)/i', '{/$1}?', $p);
        } while (preg_match('/\(\/([\/a-z0-9_\-\{\}\?]*)\)/i', $p));
        if ($constraints) {
            foreach ($constraints as $k => $v) {
                $p = str_replace($k, $v, $p);
            }
        }
        $p = preg_replace('/\/\:[a-z0-9_\-]+/', '/([^/]+)', $p); //'/([A-Za-z0-9_\-]+)'
        $p = str_replace('{', '(', $p);
        $p = str_replace('}', ')', $p);
        $p = str_replace('/', '\/', $p);
        return "/^{$p}$/";
    }

}

class RouterException extends \Exception {}
?>