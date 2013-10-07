<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

abstract class Cell extends DynaStruct {

    protected $_tpl_config;

    public function __construct($data=array(), $config=NULL) {
        parent::__construct($data);
        $this->_tpl_config = $config;
    }

    protected function render() {
        $backtrace = debug_backtrace();
        $template  = String::underscore($backtrace[1]['function'])->to_s;
        $path      = substr($backtrace[0]['file'], 0, -4); //dirname($backtrace[0]['file']);
        $config = Params::path($path);
        if ($this->_tpl_config instanceof Params) {
            $config = $config->merge($this->_tpl_config);
        }
        $tpl  = new Template($template, $config);
        if (!$tpl->isCached()) {
            $vars = $this->getElements();
            if (!empty($vars)) {
                foreach ($vars as $k => $v) {
                    $tpl[$k] = $v;
                }
            }
        }
        return $tpl;
    }

}
?>