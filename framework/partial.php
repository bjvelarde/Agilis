<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

class Partial extends Template {
    /**
     * Constructor
     *
     * @param string $file The file name you want to load
     * @param string $path The local template repository
     */
    public function __construct($file, $config=NULL) {
        parent::__construct("_{$file}", $config);
    }    
}
?>