<?php
namespace Agilis;

class PgsqlResult {  

    protected $resource;  
   
    public function __construct($resource) { $this->resource = $resource; }
	
	public function __call($method, $args) {
	    $func1 = 'pg_result_' . $method;
	    $func2 = 'pg_' . $method;
	    $func = function_exists($func1) ? $func1 : $func2;
		$first6 = substr($func, 0, 6);
		$first9 = substr($func, 0, 9);
		if (function_exists($func) && ( 
		    $first9 == 'pg_fetch_' || 
			$first9 == 'pg_field_' || 
			$first9 == 'pg_result' || 
			$first6 == 'pg_num' ||
			$func == 'pg_affected_rows' ||
			$func == 'pg_free_result')) {
		    array_unshift($args, $this->resource);
		    return call_user_func_array($func, $args);
		} 
		return NULL;
	}
	
	public function insert_id() {
	    $result = $this->fetch_row();
	    return $result[0];
	}

    public function free() { return $this->free_result(); }             
}
?>