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
use \lessc;
use \CoffeeScript\Compiler as CsCompiler;
use \JSMin;

class AssetsManager {

    protected $scripts        = array();
    protected $inline_scripts = array();
    protected $css            = array();
    //protected $inline_css     = array();
    protected $inline_script_tags = '';
    protected $script_tags        = '';
    protected $css_tags           = '';

    private $controller, $action;

    public function __construct($controller, $action) {
        $this->controller = $controller;
        $this->action = $action;
    }

    public function __get($var) {
        if ($var == 'inline_script_tags' || $var == 'script_tags' || $var == 'css_tags') {
            return $this->{$var};
        }
        return FALSE;
    }

    public function addScript($uri, $path='/js/', $type='text/javascript', $charset='default') {
        $this->scripts[$type][$charset][] = $path . $uri;
    }

    public function addInlineScript($script, $type='text/javascript') {
        $this->inline_scripts[$type][] = $script;
    }

    public function addCss($uri, $path='/css/', $media='default') {
        $this->css[$media][] = $path . $uri;
    }

    //public function addInlineCss($css_codes) {
    //    $this->inline_css[] = $css_codes;
    //}

    public function loadAssets($rejam=FALSE) {
        $css_cache_key = 'page-csstags';
        $js_cache_key  = 'page-scripttags';
        $development = (Conf::get('CURRENT_ENV') == 'development');
        $skipped = array();
        $css_tags = $script_tags = '';
        // for development, ignore cache and always process the yml and in-line assets
        if (!$development) {
            $css_tags = Cache::get($css_cache_key);
            if ($css_tags) {
                $skipped[] = 'stylesheets';
            }
            $script_tags = Cache::get($js_cache_key);
            if ($script_tags) {
                $skipped[] = 'javascripts';
            }
        }
        if (count($skipped) < 2) {
            $this->loadAssetsFromConfig($skipped);
            $possible_assets = array(
                'stylesheets' => array(
                    $this->controller . '.css',
                    $this->controller . '-' . $this->action . '.css'
                ),
                'javascripts' => array(
                    $this->controller . '.js',
                    $this->controller . '-' . $this->action . '.js'
                )
            );
            foreach ($possible_assets as $type => $assets) {
                $func = 'add' . ($type == 'javascripts' ? 'Script' : 'Css');
                if (!in_array($type, $skipped)) {
                    foreach ($assets as $asset) {
                        $path  = PUBLIC_PATH . ($type == 'javascripts' ? 'js' : 'css') . '/';
                        $raw_path  = APP_ROOT . 'app/assets/' . ($type == 'javascripts' ? 'coffee' : 'less') . '/';
                        $raw_asset = $type == 'javascripts' ? substr($asset, 0, -2) . 'coffee' :  substr($asset, 0, -3) . 'less';                        
                        if (file_exists($path . $asset) || file_exists($raw_path . $raw_asset)) {
                            $this->{$func}($asset);
                        }
                    }
                }
            }
            $jammit = Conf::get('JAM_ASSETS');
            if (!in_array('stylesheets', $skipped)) {
                $css_tags = ($jammit === TRUE) ? $this->jamCss($rejam) : $this->getCss();
            }
            if (!in_array('javascripts', $skipped)) {
                $script_tags = ($jammit === TRUE) ? $this->jamScripts($rejam) : $this->getScripts();
            }
        }
        $inline_script_tags = $this->getInlineScripts();
        $this->css_tags    = $css_tags ? "{$css_tags}\n" : '';
        $this->script_tags = $script_tags ? "{$script_tags}\n" : '';
        $this->inline_script_tags = $inline_script_tags ? "{$inline_script_tags}\n" : '';
    }

    private function loadAssetsFromConfig(array $skipped) {
        $yaml_file = APP_ROOT . 'config/assets.yml';
        if (file_exists($yaml_file)) {
            $assets = Spyc::YAMLLoad($yaml_file);
            $namespace = 'app';
            if (strstr($this->controller, '/')) {
                $parts = explode('/', $this->controller);
                $namespace = $parts[0];
            }
            foreach ($assets as $type => $asset_list) {
                if (!in_array($type, $skipped)) {
                    $func = 'add' . ($type == 'javascripts' ? 'Script' : 'Css');
                    if (!empty($asset_list['common'])) {
                        foreach ($asset_list['common'] as $action => $asset) {
                            if (is_numeric($action)) {
                                $this->addAsset($func, $asset); //$this->{$func}(basename($asset), dirname($asset) . '/');
                            } elseif ($this->action == $action) {
                                foreach ($asset as $a) {
                                    $this->addAsset($func, $a); //$this->{$func}(basename($a), dirname($a) . '/');
                                }
                            }
                        }
                    }
                    if (!empty($asset_list[$namespace])) {
                        foreach ($asset_list[$namespace] as $action => $asset) {
                            if (is_numeric($action)) {
                                $this->addAsset($func, $asset); //$this->{$func}(basename($asset), dirname($asset) . '/');
                            } elseif ($action == 'common' || $this->action == $action) {
                                foreach ($asset as $a) {
                                    $this->addAsset($func, $a); //$this->{$func}(basename($a), dirname($a) . '/');
                                }
                            }
                        }
                    }
                    if (!empty($asset_list[$this->controller])) {
                        foreach ($asset_list[$this->controller] as $action => $asset) {
                            if (is_numeric($action)) {
                                $this->addAsset($func, $asset); //$this->{$func}(basename($asset), dirname($asset) . '/');
                            } elseif ($action == 'common' || $this->action == $action) {
                                foreach ($asset as $a) {
                                    $this->addAsset($func, $a); //{$func}(basename($a), dirname($a) . '/');
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function addAsset($func, $asset) {
        if ($func == 'addCss' && preg_match('/(.+),\s+media\s+=>\s+(.+)/', $asset, $matches)) {
            return $this->addCss(basename($matches[1]), dirname($matches[1]) . '/', $matches[2]);
        }
        return $this->{$func}(basename($asset), dirname($asset) . '/');
    }

    private function getInlineScripts() {
        $script_tags = '';
        if ($this->inline_scripts) {
            $inlinescripts = '';
            foreach ($this->inline_scripts as $type => $scripts) {
                $attrs = array();
                $tag = new Partial('script_tag');
                $attrs['type'] = $type;
                foreach ($scripts as $script) {
                    $inlinescripts .= "\n{$script}";
                }
                $tag->script_body = $inlinescripts;
                $script_tags .= "\n{$tag}";
            }
        }
        return $script_tags;
    }

    private function getScripts() {
        $script_tags = '';
        if ($this->scripts) {
            foreach ($this->scripts as $type => $type_scripts) {
                foreach ($type_scripts as $charset => $scripts) {
                    foreach ($scripts as $src) {
                        $tag = new Partial('script_tag');
                        $attrs['type'] = $type;
                        $attrs['src']  = self::compileJs($src);
                        if ($charset != 'default') { $attrs['charset'] = $charset; };
                        $tag->attrs  = $attrs;
                        $script_tags .= "\n{$tag}";
                    }
                }
            }
        }
        return $script_tags;
    }

    private function getCss() {
        $css_tags = '';
        if ($this->css) {
            foreach ($this->css as $media => $stylesheets) {
                foreach ($stylesheets as $stylesheet) {
                    self::compileCss($stylesheet);
                    $tag = new Partial('link_tag');
                    if ($media != 'default') { $tag->media = $media; }
                    if (substr($stylesheet, 0, 4) == '/css') {
                        $stylesheet .= '?ts=' . filemtime(PUBLIC_PATH . substr($stylesheet, 1));
                    }
                    $tag->href  = $stylesheet;
                    $tag->rel   = 'stylesheet';
                    $css_tags .= "\n{$tag}";
                }
            }
        }
        //if ($this->inline_css) {
        //    $inline_styles = '';
        //    $tag = new Partial('style_tag');
        //    foreach ($this->inline_css as $styles) {
        //        $inline_styles .= "\n{$styles}";
        //    }
        //    $tag->css_body = $inline_styles;
        //    $css_tags .= "\n{$tag}";
        //}
        return $css_tags;
    }

    private static function compileJs($js_file) {
        if (substr($js_file, 0, 2) != '//') { //ignore remote assets
            /*
            process only assets under public/js
            assuming 3rd-party js will most likely have their own folder
            even if not, it's ok.
            */
            if (substr($js_file, 0, 3) == '/js') {
                $js_file = PUBLIC_PATH . substr($js_file, 1);
            }
            $coffee_file = substr($js_file, 0, -2) . 'coffee';
            $coffee_file = str_replace('public/js', 'app/assets/coffee', $coffee_file);
            if (file_exists($coffee_file) && (filemtime($coffee_file) > filemtime($js_file))) {
                file_put_contents($js_file, CsCompiler::compile(file_get_contents($coffee_file)));
            }
            return self::minifyJs($js_file);
        }
        return $js_file;
    }

    private static function minifyJs($js_file) {
        if (substr($js_file, 0, strlen(PUBLIC_PATH)) != PUBLIC_PATH) {
            $js_file = PUBLIC_PATH . substr($js_file, 1);
        }
        // assume it's minified if it contains .min before .js
        if (substr($js_file, -7) != '.min.js') {
            $min_file = substr($js_file, 0, -3) . '.min.js';
            if (!file_exists($min_file) || (file_exists($min_file) && filemtime($min_file) < filemtime($js_file))) {
                file_put_contents($min_file, JSMin::minify(file_get_contents($js_file)));
            }
        } else {
            $min_file = $js_file;
        }
        return str_replace(PUBLIC_PATH, '/', $min_file) . '?ts=' . filemtime($min_file);
    }

    private static function compileCss($css_file) {
        if (substr($css_file, 0, 2) != '//') { //ignore remote assets
            /*
            process only assets under public/css
            assuming 3rd-party css will most likely have their own folder
            even if not, it's ok.
            */
            if (substr($css_file, 0, 4) == '/css') {
                $css_file = PUBLIC_PATH . substr($css_file, 1);
            }
            $less_file = substr($css_file, 0, -3) . 'less';
            $less_file = str_replace('public/css', 'app/assets/less', $less_file);
            if (file_exists($less_file)) {
                $cache_file = $less_file . '.cache';
                if (file_exists($cache_file)) {
                    $cache = unserialize(file_get_contents($cache_file));
                } else {
                    $cache = $less_file;
                }
                $less = new lessc;
                if ((Conf::get('CURRENT_ENV') == 'production')) {
                    $less->setFormatter('compressed');
                }
                $new_cache = $less->cachedCompile($cache);
                if (!is_array($cache) || $new_cache['updated'] > $cache['updated']) {
                    file_put_contents($cache_file, serialize($new_cache));
                    file_put_contents($css_file, $new_cache['compiled']);
                }
            }
        }
    }

    private function jamCss($rejam=FALSE) {
        $css_tags = '';
        if ($this->css) {
            foreach ($this->css as $media => $stylesheets) {
                $m = $media == 'default' ? '' : "-{$media}";
                $css = "/css/{$this->controller}-{$this->action}{$m}.jammed.css";
                $jam = PUBLIC_PATH . $css;
                if (!file_exists($jam) || $rejam) {
                    $content = '';
                    foreach ($stylesheets as $stylesheet) {
                        if (substr($stylesheet, 0, 2) == '//') {
                            $src = 'http:' . $stylesheet;
                        } else {
                            self::compileCss($stylesheet);
                            $src =  PUBLIC_PATH . substr($stylesheet, 1);
                        }
                        $content .= file_get_contents($src);
                    }
                    $content = preg_replace('#/\*[^*]*\*+([^/][^*]*\*+)*/#', '', $content);
                    $content = preg_replace('/\n/', '', $content);
                    $content = preg_replace('/\s*{\s*/', '{', $content);
                    $content = preg_replace('/\s*}\s*/', '}', $content);
                    $content = preg_replace('/\s*,\s*/', ',', $content);
                    $content = preg_replace('/\s*:\s*/', ':', $content);
                    $content = preg_replace('/\s*;\s*/', ';', $content);
                    $content = preg_replace('/\s+/', ' ', $content);
                    file_put_contents($jam, trim($content));
                }
                $tag = new Partial('link_tag');
                if ($media != 'default') { $tag->media = $media; }
                $tag->href  = $css . '?ts=' . filemtime($jam);
                $tag->rel   = 'stylesheet';
                $css_tags .= "\n{$tag}";
            }
        }
        return $css_tags;
    }

    private function jamScripts($rejam=FALSE) {
        $script_tags = '';
        if ($this->scripts) {
            foreach ($this->scripts as $type => $type_scripts) {
                $t = str_replace('/', '-', $type);
                $t = $t == 'text-javascript' ? '' : "-{$t}";
                foreach ($type_scripts as $charset => $scripts) {
                    $c = $charset == 'default' ? '' : "-{$charset}";
                    $js = "/js/{$this->controller}-{$this->action}{$t}{$c}.jammed.js";
                    $jam = PUBLIC_PATH . $js;
                    if (!file_exists($jam) || $rejam) {
                        $content = '';
                        foreach ($scripts as $src) {
                            if (substr($src, 0, 2) == '//') {
                                $src = 'http:' . $src;
                            } else {
                                $src = PUBLIC_PATH . substr(self::compileJs($src), 1);
                                if (preg_match('/^(.+)(\?ts=\d+)$/', $src)) {
                                    $src = preg_replace('/^(.+)(\?ts=\d+)$/', "$1", $src);
                                }
                            }
                            $content .= file_get_contents($src);
                        }
                        $content = preg_replace('#/\*[^*]*\*+([^/][^*]*\*+)*/#', '', $content);
                        //$content = preg_replace('/\r\n/', '', $content);
                        file_put_contents($jam, trim($content));
                    }
                    $attrs['src']  = $js . '?ts=' . filemtime($jam);
                    $attrs['type'] = $type;
                    if ($charset != 'default') {
                        $attrs['charset'] = $charset;
                    }
                    $tag = new Partial('script_tag');
                    $tag->attrs = $attrs;
                    $script_tags .= "\n{$tag}";
                }
            }
        }
        return $script_tags;
    }
}
?>