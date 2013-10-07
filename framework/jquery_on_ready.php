<?php
namespace Agilis;

class JqueryOnReady {

    public static function onReady() {
        $args = func_get_args();
        if (!empty($args)) {
            $controller = array_shift($args);
            $class = get_class($controller);
            if (!empty($args)) {
                $controller->addInlineScript('$(document).ready(function(){' . implode("\n", array_reverse($args)) . '});');
            }
        }
    }
}
?>