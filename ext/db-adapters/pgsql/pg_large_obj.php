<?php
namespace Agilis;

class PgLargeObj {  

    protected $handle;  
    protected $oid;  
    protected $connection; 
   
    public function __construct(Pgsql $conn, $mode='rw', $file='', $obj_id=NULL) {      
        $conn->begin();   
        if (!$file) {
            $this->oid = ($obj_id) ? pg_lo_create($conn->connection, $obj_id) : pg_lo_create($conn->connection);
        } else {
            $this->oid = ($obj_id) ? pg_lo_import($conn->connection, $file, $obj_id) : 
                         pg_lo_import($conn->connection, $file);
        }
        $this->handle = pg_lo_open($conn->connection, $this->oid, $mode);         
        $this->connection = $conn; 
    }
	
	public function __call($method, $args) {
	    $func = 'pg_lo_' . $method;
		if (function_exists($func) && 
		    $func != 'pg_lo_create' && 
		    $func != 'pg_lo_open' &&  
		    $func != 'pg_lo_unlink' &&  
		    $func != 'pg_lo_export') {
		    array_unshift($args, $this->handle);
		    $result = call_user_func_array($func, $args);
		    if ($func == 'pg_lo_close') {
		        $this->connection->commit();
		    }
		    return $result;
		} 
		return NULL;
	}
	
    public function export($file) { 
        return pg_lo_export($this->connection->connection, $this->oid, $file); 
    }   
    
    public function unlink() {
        pg_lo_unlink($this->connection->connection, $this->oid);
        $this->connection->commit();
    }
            
}
?>