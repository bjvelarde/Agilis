<?php
namespace Agilis;

class JqGridNavButton extends FixedStruct {

    public function __construct() {
        parent::__construct('title', 'caption', 'buttonicon', 'onClickButton', 'position');
    }

    public static function __callStatic($method, $args) {
	    $val = $args ? $args[0] : '';
		$b = new self;
        $b->{$method} = $val;
        return $b;
	}
    
    public function __set($var, $val) {
        if ($var == 'onClickButton') {
            $val = "function() { $val } ";
        }
        parent::__set($var, $val);
    }

    public function __toString() { return JsEncoder::encode($this->_elements) . ''; }

}
?>