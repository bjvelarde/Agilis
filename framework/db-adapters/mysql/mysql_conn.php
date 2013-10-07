<?php
/**
 * @author Benjie Velarde
 * @copyright (c) 2009, Benjie Velarde
 * @license http://opensource.org/licenses/bsd-license.php The BSD License
 */
namespace Agilis;

use \mysqli;

class MysqlConn extends SqlDataSource {

    public function __construct(MysqlConfig $datasrc) {
        //$conn = new mysqli($datasrc->host, $datasrc->user, $datasrc->pass, $datasrc->dbname, $datasrc->port, $datasrc->sock);
		//if ($conn->connect_error) {
		//    throw new MysqlConnException($this);
		//}
        //$conn->set_charset($datasrc->charset);
        parent::__construct($datasrc);
    }
    
    public function connect() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname, $this->port, $this->sock);
		if ($this->conn->connect_error) {
		    throw new MysqlConnException($this);
		}
        $this->set_charset($this->charset);    
    }
	
	public function query($query) {
        $this->connect();
	    $stmt = $this->conn->query($query);
		if (!$stmt) {
		    throw new MysqlQueryException($this, $query);
		}
		return $stmt;
	}

	public function prepare($query) {
        $this->connect();
	    $stmt = $this->conn->prepare($query);
		if (!$stmt) {         
		    throw new MysqlQueryException($this, $query);
		}
		return $stmt;
	}	
    
    public function escape($value) { return $this->conn->real_escape_string($value); }
    /**
     * Fetch a single record from Database that matched the query
     *
     * @param string $query
     * @param int $type
     * @return array
     */
    public function fetch($query, $type=MYSQLI_ASSOC) {
        $stmt = $this->query($query);
        return $stmt->fetch_array($type);
    }
    /**
     * Fetch all records from Database that matched the query
     *
     * @param string $query
     * @param string $key if we want our dataset to have associative keys
     * @param int $type
     * @return array
     */
    public function fetchAll($query, $key='', $type=MYSQLI_ASSOC) {
        $stmt = $this->query($query);
        $data = array();
        //see if we are using msqli native driver
        if (function_exists('mysqli_fetch_all')) {
            $data = $stmt->fetch_all($type);
            if ($data && $key) {
                $newdata = array();
                foreach ($data as $rec) {                    
                    $newdata[$rec[$key]] = $rec;
                }
                $data = $newdata;
            }
        } else { // we are using libmysql
            $newdata = array();
            while (($rec = $stmt->fetch_array($type)) !== NULL) {                
                if ($key) {
                    $newdata[$rec[$key]] = $rec;
                } else {
                    $newdata[] = $rec;
                }
            }
            $data = $newdata;
        }
        return $data;
    }
    /**
     * Fetch a single record's column from Database that matched the query
     *
     * @param string $query
     * @param int $col column number
     * @return array
     */
    public function fetchColumn($query, $col=0) {
        $stmt = $this->query($query);
        $data = $stmt->fetch_array(MYSQLI_NUM);
        return $data[$col];
    }

}

class MysqlConnException extends \Exception {

    public function __construct(MysqlConn $ds) {
        parent::__construct('MySQL Connection Error: ' . $ds->connect_error . ' #:' . $ds->connect_errno);
    }
}

class MysqlQueryException extends \Exception {

    public function __construct(MysqlConn $ds, $sql) {
        parent::__construct('MySQL Query Error: ' . $ds->error . ' #:' . $ds->errno . ' SQL:' . $sql);
    }
}
?>