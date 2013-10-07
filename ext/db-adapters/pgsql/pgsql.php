<?php
namespace Agilis;

class Pgsql {  

    protected $link; 
    protected $connstr;
    protected $persistent; 
   
    public function __construct($connstr, $persistent=FALSE) {
        $this->connstr = $connstr;        
        $this->persistent = $persistent;
        $this->connect();  
    }
	
	public function __destruct() { 
	    if (is_resource($this->link)) {
            pg_close($this->link);
        } 
    }
	
	public function __get($var) { if ($var == 'connection') { return $this->link; } }
	
	public function __call($method, $args) {	
	    $func = 'pg_' . $method;
		$first6 = substr($func, 0, 6);
		$first9 = substr($func, 0, 9);
		if (function_exists($func) && 
		    $first9 != 'pg_fetch_' && 
			$first9 != 'pg_field_' && 
			$first9 != 'pg_result' && 			
			$first6 != 'pg_num' &&
			$first6 != 'pg_lo_' && 
			!in_array($func, array(
				'pg_connect', 
				'pg_pconnect', 
				'pg_close',
				'pg_affected_rows',
				'pg_free_result',
				'pg_last_oid',
				'pg_unescape_bytea'
		    )
		)) {
		    if (!$this->isConnected()) { $this->connect(); }
		    if ($func == 'pg_trace') {
			    $argscount = count($args);
				if ($argscount < 2) {
				    $args[] = 'w';
				}
				if ($argscount < 3) {
				    $args[] = $this->link;
				}
			} else {			    
			    array_unshift($args, $this->link);
			}
			$result = call_user_func_array($func, $args);
			if (($func == 'pg_query' || $func == 'pg_query_params' || $func == 'pg_prepare') && !is_resource($result)) {
			    throw new PgsqlQueryException($args[1], $this->last_error());
			}
			return ($func == 'pg_query' || $func == 'pg_query_params' || $func == 'pg_prepare') ? new PgsqlResult($result) : $result;
			
		}
		return NULL;
	}
	
	protected function connect() {	
        $connector = ($this->persistent) ? 'pg_pconnect' : 'pg_connect';
        $tries = 0;
        do {
            if (($this->link = $connector($this->connstr)) !== FALSE) {
                break;
            }            
        } while(3 > $tries++); 
        if (!$this->link) {
            throw new PgsqlException($this->connstr);
        }
    }
    
    public function isConnected() { return is_resource($this->link); }
    
    public function begin() { return $this->query('BEGIN'); }
    
    public function commit() { return $this->query('COMMIT'); }

}

class PgsqlException extends \Exception {

    public function __construct($connstr) {
        parent::__construct("Failed to connect to Postres using $connstr");
    }
}

class PgsqlQueryException extends \Exception {

    public function __construct($sql, $error) {
        parent::__construct("Postgres Query Failed: $error [SQL: $sql]");
    }
}
?>