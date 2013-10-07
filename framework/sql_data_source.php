<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;

abstract class SqlDataSource extends DataSource {    

    abstract public function fetch($query, $type='');

    abstract public function fetchAll($query, $key='', $type='');

    abstract public function fetchColumn($query, $col=0);
    
    abstract public function escape($value);
    
    abstract public function query($query);

}
?>