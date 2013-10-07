<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;
/**
 * A class to easily create HTTP (Request|Response) Headers
 */
abstract class HttpHeader extends FixedStruct {
    /*
     * render the class as string
     */
    public function __toString() {
        $headers = array();
        foreach ($this as $key => $value) {
            if ($value) {
                $headers[] = $this->convertKey($key) . ": $value";
            }
        }
        return implode("\n", $headers) . "\n";
    }
    /**
     * Initialize the header values
     *
     * @param Params $values
     */
    protected function initValues(Params $values) {
        if ($values) {
            foreach ($values as $header => $value) {
                $this->{$header} = $value;
            }
        }
    }
    /**
     * Convert class property into a valid http header key
     *
     * @param string $key
     * @return string
     */
    protected function convertKey($key) {
        $key_parts = explode('_', $key);
        $key = array();
        foreach ($key_parts as $key_part) {
            $key[] = ucfirst($key_part);
        }
        return implode('-', $key);
    }
}
?>