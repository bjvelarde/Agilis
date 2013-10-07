<?php
/**
 * @package Agilis
 * @version 1.0
 * @author Benjie Velarde
 * @copyright (c) 2013, Benjie Velarde bvelarde@gmail.com
 * @license http://opensource.org/licenses/PHP-3.0
 */
namespace Agilis;
 
abstract class DataSourceConfig extends FixedStruct {

    public function __construct(Params $config) {
        //$config->checkRequired('layer'); 
	    parent::__construct(
		    'charset',
            '_class',
            'dbname',			
			'driver',			
			'dsn', 
			'engine', 
			'host', 
			'layer',
			'pass', 
			'persistent',
			'port', 
			'protocol', 		
			'scrollable_cursor', 
			'server', 
			'service',			
			'sock', 
			'storage',  
			'user'			
		);
		foreach ($config as $k => $v) {
		    $this[$k] = $v;
		}
	}   
	
	public static function factory($engine, Params $config) {	    
	    $class = String::camelize($engine) . 'Config';
	    return new $class($config);
	}
        
	abstract public function create();
}
?>